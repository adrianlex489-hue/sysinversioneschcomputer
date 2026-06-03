<?php
// ============================================================
// modules/transacciones/cobro_ventas.php | SysInversiones 2026
// Cobro de ventas a crédito — abonos parciales/totales
// Tablas: ventas, pagos_venta, cuotas_venta, movimientos_caja
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexión BD no disponible.');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR_COMERCIAL'))        define('ROL_ASESOR_COMERCIAL', 2);
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL]);
verificarPermiso($pdo, 'cobro_ventas');

$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);

$swal = null;
if (isset($_SESSION['swal_cobro_vta'])) { $swal = $_SESSION['swal_cobro_vta']; unset($_SESSION['swal_cobro_vta']); }

function redirigirCobroVta(string $icon, string $title, string $text): void {
    $_SESSION['swal_cobro_vta'] = compact('icon', 'title', 'text');
    header('Location: cobro_ventas.php'); exit;
}

// ── Caja activa ───────────────────────────────────────────────────────────────
// Se busca cualquier caja abierta (no solo la del usuario actual)
// para que abonos registrados por cualquier usuario afecten la caja correctamente.
$caja_activa = null;
try {
    $caja_activa = $pdo->query("SELECT id_caja, nombre, monto_inicial, fecha_apertura FROM caja WHERE estado='abierta' ORDER BY fecha_apertura DESC LIMIT 1")->fetch();
} catch (PDOException $e) {}
$hay_caja = !empty($caja_activa);

// ── AJAX: detalle de venta ────────────────────────────────────────────────────
if (isset($_GET['accion']) && $_GET['accion'] === 'detalle_ajax') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id_venta'] ?? 0);
    try {
        $stmt = $pdo->prepare("
            SELECT v.*,
                   CASE v.tipo_cliente
                       WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
                       ELSE TRIM(CONCAT_WS(' ', cn.nombres, cn.apellido_paterno, cn.apellido_materno))
                   END AS cliente_nombre,
                   CASE v.tipo_cliente WHEN 'empresa' THEN 'RUC' ELSE cn.tipo_documento END AS tipo_documento,
                   CASE v.tipo_cliente WHEN 'empresa' THEN ce.ruc ELSE cn.documento_identidad END AS documento_identidad,
                   CASE v.tipo_cliente WHEN 'empresa' THEN ce.telefono ELSE cn.telefono END AS telefono,
                   u.nombre_completo AS registrado_por
            FROM ventas v
            LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = v.id_cliente AND v.tipo_cliente = 'natural'
            LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = v.id_cliente AND v.tipo_cliente = 'empresa'
            LEFT JOIN usuarios u ON u.id_usuario = v.id_usuario
            WHERE v.id_venta = ?
        ");
        $stmt->execute([$id]);
        $venta = $stmt->fetch();
        if (!$venta) { echo json_encode(['ok'=>false,'msg'=>'Venta no encontrada.']); exit; }

        $stDet = $pdo->prepare("
            SELECT dv.*, p.nombre_producto, p.codigo
            FROM detalle_venta dv
            LEFT JOIN productos p ON p.id_producto = dv.id_producto
            WHERE dv.id_venta = ?
        ");
        $stDet->execute([$id]);
        $detalle = $stDet->fetchAll();

        $stCuotas = $pdo->prepare("SELECT * FROM cuotas_venta WHERE id_venta = ? ORDER BY numero_cuota ASC");
        $stCuotas->execute([$id]);
        $cuotas = $stCuotas->fetchAll();

        $stPag = $pdo->prepare("SELECT pv.*, u.nombre_completo FROM pagos_venta pv LEFT JOIN usuarios u ON u.id_usuario = pv.id_usuario WHERE pv.id_venta = ? ORDER BY pv.fecha ASC");
        $stPag->execute([$id]);
        $pagos = $stPag->fetchAll();

        echo json_encode(['ok'=>true,'venta'=>$venta,'detalle'=>$detalle,'cuotas'=>$cuotas,'pagos'=>$pagos]);
    } catch (PDOException $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── AJAX: cuotas de una venta (para modal de abono) ──────────────────────────
if (isset($_GET['accion']) && $_GET['accion'] === 'cuotas_ajax') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id_venta'] ?? 0);
    try {
        $stCuotas = $pdo->prepare(
            "SELECT id_cuota, numero_cuota, monto_cuota, fecha_vencimiento, estado
             FROM cuotas_venta WHERE id_venta = ? ORDER BY numero_cuota ASC"
        );
        $stCuotas->execute([$id]);
        $cuotas = $stCuotas->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'cuotas' => $cuotas]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── POST: registrar abono ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'registrar_abono') {
        // ── Validación: caja debe estar abierta ───────────────────────────────
        if (!$hay_caja) {
            redirigirCobroVta('warning', 'Caja no aperturada', 'Debes abrir una caja antes de registrar abonos. Ve al módulo de Caja y apertura una sesión.');
        }

        $id_venta    = (int)($_POST['id_venta']    ?? 0);
        $monto       = (float)($_POST['monto']     ?? 0);
        $metodo_pago = $_POST['metodo_pago']        ?? 'efectivo';
        $observacion = trim($_POST['observacion']   ?? '') ?: null;

        if (!$id_venta) redirigirCobroVta('warning','Error','No se identificó la venta.');
        if ($monto <= 0) redirigirCobroVta('warning','Monto inválido','El monto debe ser mayor a 0.');
        $metodos_validos = ['efectivo','yape','plin','transferencia','tarjeta'];
        if (!in_array($metodo_pago, $metodos_validos)) $metodo_pago = 'efectivo';

        try {
            $pdo->beginTransaction();

            $stVta = $pdo->prepare("SELECT id_venta, total, saldo_pendiente, estado, tipo_pago FROM ventas WHERE id_venta=?");
            $stVta->execute([$id_venta]);
            $venta = $stVta->fetch();
            if (!$venta) throw new Exception('Venta no encontrada.');
            if ($venta['tipo_pago'] !== 'credito') throw new Exception('Esta venta no es a crédito.');
            if ($venta['estado'] === 'pagado') throw new Exception('Esta venta ya está completamente pagada.');

            $saldo_actual = (float)$venta['saldo_pendiente'];
            if ($monto > $saldo_actual + 0.01) throw new Exception('El monto (S/. '.number_format($monto,2).') supera el saldo pendiente (S/. '.number_format($saldo_actual,2).').');

            // Insertar pago
            $pdo->prepare("INSERT INTO pagos_venta (id_venta, id_usuario, metodo_pago, monto, observacion, fecha) VALUES (?,?,?,?,?,NOW())")
                ->execute([$id_venta, $id_usuario, $metodo_pago, $monto, $observacion]);

            // Actualizar saldo y estado
            $nuevo_saldo  = round($saldo_actual - $monto, 2);
            $nuevo_estado = ($nuevo_saldo <= 0.009) ? 'pagado' : 'pendiente';
            $pdo->prepare("UPDATE ventas SET saldo_pendiente=?, estado=? WHERE id_venta=?")
                ->execute([$nuevo_saldo, $nuevo_estado, $id_venta]);

            // Actualizar cuotas (marcar como pagadas de más antigua a más nueva)
            if ($nuevo_estado === 'pagado') {
                $pdo->prepare("UPDATE cuotas_venta SET estado='pagado' WHERE id_venta=? AND estado='pendiente'")
                    ->execute([$id_venta]);
            } else {
                // Marcar cuotas cubiertas por este abono
                $stCuotas = $pdo->prepare("SELECT id_cuota, monto_cuota FROM cuotas_venta WHERE id_venta=? AND estado='pendiente' ORDER BY numero_cuota ASC");
                $stCuotas->execute([$id_venta]);
                $cuotas = $stCuotas->fetchAll();
                $resto = $monto;
                foreach ($cuotas as $c) {
                    if ($resto <= 0) break;
                    if ($resto >= (float)$c['monto_cuota'] - 0.01) {
                        $pdo->prepare("UPDATE cuotas_venta SET estado='pagado' WHERE id_cuota=?")->execute([$c['id_cuota']]);
                        $resto -= (float)$c['monto_cuota'];
                    }
                }
            }

            // Movimiento en caja — dentro de la transacción
            if ($hay_caja) {
                $num_vta = 'VTA-' . str_pad($id_venta, 6, '0', STR_PAD_LEFT);
                $desc_mov = "Abono crédito {$num_vta}";
                $pdo->prepare("INSERT INTO movimientos_caja (id_caja,tipo_referencia,id_referencia,id_usuario,tipo,descripcion,monto,metodo_pago) VALUES (?,'venta',?,?,'ingreso',?,?,?)")
                    ->execute([$caja_activa['id_caja'], $id_venta, $id_usuario, $desc_mov, $monto, $metodo_pago]);
            }

            $pdo->commit();

            $msg = $nuevo_estado === 'pagado'
                ? 'Abono registrado. Venta completamente pagada.'
                : 'Abono de S/. '.number_format($monto,2).' registrado. Saldo pendiente: S/. '.number_format($nuevo_saldo,2).'.';
            redirigirCobroVta('success', '¡Abono registrado!', $msg);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            redirigirCobroVta('error', 'Error al registrar abono', $e->getMessage());
        }
    }
}

// ── DATOS ─────────────────────────────────────────────────────────────────────
// Ventas a crédito con saldo pendiente
$ventas_credito = $pdo->query("
    SELECT v.*,
           CASE v.tipo_cliente
               WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
               ELSE TRIM(CONCAT_WS(', ',
                   NULLIF(TRIM(CONCAT_WS(' ', cn.apellido_paterno, cn.apellido_materno)),''),
                   NULLIF(TRIM(cn.nombres),'')
               ))
           END AS cliente_nombre,
           CASE v.tipo_cliente WHEN 'empresa' THEN 'RUC' ELSE cn.tipo_documento END AS tipo_documento,
           CASE v.tipo_cliente WHEN 'empresa' THEN ce.ruc ELSE cn.documento_identidad END AS documento_identidad,
           CASE v.tipo_cliente WHEN 'empresa' THEN ce.telefono ELSE cn.telefono END AS telefono
    FROM ventas v
    LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = v.id_cliente AND v.tipo_cliente = 'natural'
    LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = v.id_cliente AND v.tipo_cliente = 'empresa'
    WHERE v.tipo_pago = 'credito' AND v.estado = 'pendiente'
    ORDER BY v.fecha_vencimiento_pago ASC, v.fecha ASC
")->fetchAll();

// Abonos recientes (últimos 50)
$abonos_recientes = $pdo->query("
    SELECT pv.*,
           u.nombre_completo AS cobrado_por,
           CASE v.tipo_cliente
               WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
               ELSE TRIM(CONCAT_WS(' ', cn.nombres, cn.apellido_paterno))
           END AS cliente_nombre,
           v.tipo_comprobante, v.numero_comprobante
    FROM pagos_venta pv
    LEFT JOIN usuarios u ON u.id_usuario = pv.id_usuario
    JOIN ventas v ON v.id_venta = pv.id_venta
    LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = v.id_cliente AND v.tipo_cliente = 'natural'
    LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = v.id_cliente AND v.tipo_cliente = 'empresa'
    ORDER BY pv.fecha DESC
    LIMIT 50
")->fetchAll();

// Stats
$stats_vta = $pdo->query("
    SELECT
        COUNT(CASE WHEN tipo_pago='credito' AND estado='pendiente' THEN 1 END) AS pendientes,
        COALESCE(SUM(CASE WHEN tipo_pago='credito' AND estado='pendiente' THEN saldo_pendiente END),0) AS total_por_cobrar,
        COUNT(CASE WHEN tipo_pago='credito' AND estado='pendiente' AND fecha_vencimiento_pago < CURDATE() THEN 1 END) AS vencidas,
        COALESCE(SUM(CASE WHEN tipo_pago='credito' AND estado='pagado' AND DATE(fecha)=CURDATE() THEN total END),0) AS cobrado_hoy
    FROM ventas
")->fetch();

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>

<link rel="stylesheet" href="css/cobro_ventas.css?v=<?= time() ?>">

<div class="content-wrapper">

    <!-- CABECERA -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-cobrovta d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4><i class="fas fa-hand-holding-usd mr-2"></i>Cobro de Ventas a Crédito</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones CH Computer &rsaquo; Ventas &rsaquo; Cobro de Créditos</small>
                </div>
                <?php if ($hay_caja): ?>
                <div class="cobrovta-caja-badge">
                    <i class="fas fa-cash-register mr-1"></i>
                    Caja abierta — <?= htmlspecialchars($caja_activa['nombre']) ?>
                </div>
                <?php else: ?>
                <div class="cobrovta-caja-badge cobrovta-caja-sin">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Sin caja abierta — los abonos no se registrarán en caja
                </div>
                <?php endif; ?>            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- SWAL -->
            <?php if ($swal): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon: '<?= $swal['icon'] ?>',
                    title: '<?= addslashes($swal['title']) ?>',
                    text: '<?= addslashes($swal['text']) ?>',
                    confirmButtonColor: '#1d4ed8',
                    timer: <?= in_array($swal['icon'], ['success','info']) ? 3500 : 0 ?>,
                    timerProgressBar: <?= in_array($swal['icon'], ['success','info']) ? 'true' : 'false' ?>,
                    showConfirmButton: <?= in_array($swal['icon'], ['success','info']) ? 'false' : 'true' ?>,
                });
            });
            </script>
            <?php endif; ?>

            <!-- STATS -->
            <div class="row mb-4">
                <div class="col-6 col-md-3 mb-2">
                    <div class="cobrovta-stat" style="background:linear-gradient(135deg,#1e40af,#3b82f6);">
                        <i class="fas fa-clock"></i>
                        <div>
                            <div class="cobrovta-stat-val"><?= (int)$stats_vta['pendientes'] ?></div>
                            <div class="cobrovta-stat-lbl">Ventas pendientes</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="cobrovta-stat" style="background:linear-gradient(135deg,#dc2626,#f87171);">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <div class="cobrovta-stat-val"><?= (int)$stats_vta['vencidas'] ?></div>
                            <div class="cobrovta-stat-lbl">Vencidas</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="cobrovta-stat" style="background:linear-gradient(135deg,#b45309,#f59e0b);">
                        <i class="fas fa-money-bill-wave"></i>
                        <div>
                            <div class="cobrovta-stat-val">S/. <?= number_format($stats_vta['total_por_cobrar'], 0) ?></div>
                            <div class="cobrovta-stat-lbl">Total por cobrar</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="cobrovta-stat" style="background:linear-gradient(135deg,#166534,#22c55e);">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <div class="cobrovta-stat-val">S/. <?= number_format($stats_vta['cobrado_hoy'], 0) ?></div>
                            <div class="cobrovta-stat-lbl">Cobrado hoy</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CARD PRINCIPAL -->
            <div class="card shadow-sm">
                <div class="cobrovta-card-header d-flex align-items-center justify-content-between">
                    <h6 class="m-0"><i class="fas fa-list mr-2"></i>Gestión de Cobros — Ventas a Crédito</h6>
                    <ul class="nav nav-tabs card-header-tabs ml-auto" id="cobroVtaTab" role="tablist" style="border-bottom:none;">
                        <li class="nav-item">
                            <a class="nav-link active text-white font-weight-bold" data-toggle="tab" href="#tab-pendientes-vta" role="tab">
                                <i class="fas fa-clock mr-1"></i> Por cobrar
                                <span class="badge badge-light ml-1"><?= count($ventas_credito) ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white font-weight-bold" data-toggle="tab" href="#tab-historial-vta" role="tab">
                                <i class="fas fa-history mr-1"></i> Historial abonos
                                <span class="badge badge-light ml-1"><?= count($abonos_recientes) ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">

                        <!-- TAB: POR COBRAR -->
                        <div class="tab-pane fade show active" id="tab-pendientes-vta">
                            <?php if (empty($ventas_credito)): ?>
                            <div class="cobrovta-empty">
                                <i class="fas fa-check-double"></i>
                                <p>No hay ventas a crédito pendientes de cobro</p>
                                <small>Las ventas a crédito aparecen aquí cuando tienen saldo pendiente</small>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table id="tablaPendientesVta" class="table table-cobrovta table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Venta</th>
                                            <th>Cliente</th>
                                            <th>Comprobante</th>
                                            <th class="text-right">Total</th>
                                            <th class="text-right">Saldo Pendiente</th>
                                            <th class="text-center">Vencimiento</th>
                                            <th>Fecha Venta</th>
                                            <th class="text-center" style="width:110px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($ventas_credito as $v): ?>
                                        <?php
                                        $total  = (float)($v['total'] ?? 0);
                                        $saldo  = (float)($v['saldo_pendiente'] ?? $total);
                                        $venc   = $v['fecha_vencimiento_pago'] ?? null;
                                        $vencida = $venc && strtotime($venc) < strtotime('today');
                                        ?>
                                        <tr class="<?= $vencida ? 'fila-vencida' : '' ?>">
                                            <td>
                                                <span class="cobrovta-num-venta">VTA-<?= str_pad($v['id_venta'],6,'0',STR_PAD_LEFT) ?></span>
                                            </td>
                                            <td>
                                                <div class="font-weight-bold" style="font-size:.85rem;"><?= htmlspecialchars($v['cliente_nombre']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($v['tipo_documento']) ?>: <?= htmlspecialchars($v['documento_identidad']) ?></small>
                                                <?php if ($v['telefono']): ?>
                                                <br><small class="text-muted"><i class="fas fa-phone mr-1"></i><?= htmlspecialchars($v['telefono']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="cobrovta-tipo-badge"><?= ucfirst($v['tipo_comprobante']) ?></span>
                                                <br><small class="text-muted"><?= htmlspecialchars($v['numero_comprobante'] ?? '—') ?></small>
                                            </td>
                                            <td class="text-right font-weight-bold" style="color:#1d4ed8;">
                                                S/. <?= number_format($total,2) ?>
                                            </td>
                                            <td class="text-right font-weight-bold">
                                                <span class="cobrovta-saldo-badge">S/. <?= number_format($saldo,2) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($venc): ?>
                                                    <span class="cobrovta-venc-badge <?= $vencida ? 'cobrovta-venc-vencida' : 'cobrovta-venc-ok' ?>">
                                                        <?= $vencida ? '<i class="fas fa-exclamation-triangle mr-1"></i>' : '<i class="fas fa-calendar mr-1"></i>' ?>
                                                        <?= date('d/m/Y', strtotime($venc)) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small><?= date('d/m/Y', strtotime($v['fecha'])) ?></small></td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-info btn-ver-detalle-vta" title="Ver detalle"
                                                    data-id="<?= $v['id_venta'] ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-abonar" title="Registrar abono"
                                                    data-id="<?= $v['id_venta'] ?>"
                                                    data-venta="VTA-<?= str_pad($v['id_venta'],6,'0',STR_PAD_LEFT) ?>"
                                                    data-cliente="<?= htmlspecialchars($v['cliente_nombre'], ENT_QUOTES) ?>"
                                                    data-total="<?= $total ?>"
                                                    data-saldo="<?= $saldo ?>">
                                                    <i class="fas fa-hand-holding-usd mr-1"></i>Abonar
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB: HISTORIAL ABONOS -->
                        <div class="tab-pane fade" id="tab-historial-vta">
                            <div class="table-responsive">
                                <table id="tablaHistorialVta" class="table table-cobrovta table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Venta</th>
                                            <th>Cliente</th>
                                            <th>Comprobante</th>
                                            <th class="text-right">Monto Abonado</th>
                                            <th>Método</th>
                                            <th>Registrado por</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($abonos_recientes as $p): ?>
                                        <tr>
                                            <td><span class="cobrovta-num-venta">VTA-<?= str_pad($p['id_venta'],6,'0',STR_PAD_LEFT) ?></span></td>
                                            <td><div style="font-size:.85rem;"><?= htmlspecialchars($p['cliente_nombre']) ?></div></td>
                                            <td>
                                                <span class="cobrovta-tipo-badge"><?= ucfirst($p['tipo_comprobante']) ?></span>
                                                <small class="text-muted ml-1"><?= htmlspecialchars($p['numero_comprobante'] ?? '') ?></small>
                                            </td>
                                            <td class="text-right font-weight-bold text-success">S/. <?= number_format($p['monto'],2) ?></td>
                                            <td>
                                                <span class="cobrovta-metodo-badge cobrovta-metodo-<?= $p['metodo_pago'] ?>">
                                                    <i class="<?= ['efectivo'=>'fas fa-money-bill','yape'=>'fas fa-mobile-alt','plin'=>'fas fa-mobile-alt','transferencia'=>'fas fa-university','tarjeta'=>'fas fa-credit-card'][$p['metodo_pago']] ?? 'fas fa-money-bill' ?> mr-1"></i>
                                                    <?= ucfirst($p['metodo_pago']) ?>
                                                </span>
                                            </td>
                                            <td><small><?= htmlspecialchars($p['cobrado_por'] ?? '—') ?></small></td>
                                            <td><small><?= date('d/m/Y H:i', strtotime($p['fecha'])) ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: REGISTRAR ABONO
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalAbono" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;border:none;">
            <div class="cobrovta-modal-header">
                <div>
                    <h5 class="m-0"><i class="fas fa-hand-holding-usd mr-2"></i>Registrar Abono</h5>
                    <div id="abono-modal-venta" class="cobrovta-modal-num mt-1"></div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;text-shadow:none;">
                    <span>&times;</span>
                </button>
            </div>

            <!-- Resumen cliente -->
            <div class="cobrovta-modal-resumen">
                <div class="cobrovta-resumen-row">
                    <span><i class="fas fa-user mr-1"></i>Cliente</span>
                    <strong id="abono-modal-cliente">—</strong>
                </div>
                <div class="cobrovta-resumen-row">
                    <span><i class="fas fa-file-invoice-dollar mr-1"></i>Total venta</span>
                    <strong id="abono-modal-total" style="color:#1d4ed8;">—</strong>
                </div>
                <div class="cobrovta-resumen-row">
                    <span><i class="fas fa-exclamation-circle mr-1"></i>Saldo pendiente</span>
                    <strong id="abono-modal-saldo" style="color:#dc2626;">—</strong>
                </div>
            </div>

            <!-- Cuotas -->
            <div id="abono-cuotas-section" style="padding:0 20px 4px;">
                <div id="abono-cuotas-loading" class="text-center py-3" style="display:none;">
                    <i class="fas fa-spinner fa-spin text-muted"></i>
                    <span class="text-muted ml-2" style="font-size:.85rem;">Cargando cuotas...</span>
                </div>
                <div id="abono-cuotas-lista"></div>
            </div>

            <form id="formAbono" method="POST">
                <input type="hidden" name="accion" value="registrar_abono">
                <input type="hidden" name="id_venta" id="abono-id-venta">
                <div class="modal-body" style="padding:16px 20px 20px;">

                    <!-- Método de pago — solo 3 -->
                    <div class="form-group mb-3">
                        <label class="cobrovta-form-label"><i class="fas fa-credit-card mr-1"></i>Método de pago <span class="text-danger">*</span></label>
                        <div class="cobrovta-metodos-grid" style="grid-template-columns:repeat(3,1fr);">
                            <label class="cobrovta-metodo-opt">
                                <input type="radio" name="metodo_pago" value="efectivo" checked>
                                <span><i class="fas fa-money-bill-wave"></i><br>Efectivo</span>
                            </label>
                            <label class="cobrovta-metodo-opt">
                                <input type="radio" name="metodo_pago" value="yape">
                                <span><i class="fas fa-mobile-alt"></i><br>Yape</span>
                            </label>
                            <label class="cobrovta-metodo-opt">
                                <input type="radio" name="metodo_pago" value="transferencia">
                                <span><i class="fas fa-university"></i><br>Transferencia</span>
                            </label>
                        </div>
                    </div>

                    <!-- Monto -->
                    <div class="form-group mb-3">
                        <label class="cobrovta-form-label"><i class="fas fa-coins mr-1"></i>Monto a abonar <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="background:#1d4ed8;color:#fff;border-color:#1d4ed8;">S/.</span>
                            </div>
                            <input type="number" name="monto" id="abono-monto" class="form-control form-control-lg"
                                   step="0.01" min="0.01" placeholder="0.00" required
                                   style="font-size:1.3rem;font-weight:700;text-align:right;">
                        </div>
                        <div id="abono-monto-hint" class="mt-1" style="font-size:.8rem;color:#64748b;"></div>
                    </div>

                    <div id="abono-vuelto-box" class="cobrovta-vuelto-box" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-exchange-alt mr-1"></i>Vuelto estimado</span>
                            <strong id="abono-vuelto-val" style="font-size:1.1rem;">S/. 0.00</strong>
                        </div>
                    </div>

                    <!-- Observación -->
                    <div class="form-group mb-0">
                        <label class="cobrovta-form-label"><i class="fas fa-comment mr-1"></i>Observación <small class="text-muted">(opcional)</small></label>
                        <input type="text" name="observacion" class="form-control" placeholder="Ej: Abono cuota 1..." maxlength="200">
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:14px 24px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-abonar-submit">
                        <i class="fas fa-check mr-1"></i>Confirmar abono
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: DETALLE DE VENTA
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDetalleVta" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="max-width:780px;">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;border:none;">
            <div class="cobrovta-modal-header">
                <div>
                    <h5 class="m-0"><i class="fas fa-clipboard-list mr-2"></i>Detalle de Venta</h5>
                    <div id="det-vta-num" class="cobrovta-modal-num mt-1"></div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;text-shadow:none;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="detalle-vta-body" style="padding:0;">
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                    <p class="mt-2 text-muted">Cargando...</p>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '<script>window.CAJA_ABIERTA = ' . ($hay_caja ? 'true' : 'false') . ';</script>'
          . '<script src="/sysinversioneschcomputer/modules/transacciones/js/cobro_ventas.js?v=' . time() . '"></script>';
include $ruta_base . 'includes/footer.php';
?>
