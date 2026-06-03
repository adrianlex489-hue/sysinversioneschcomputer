<?php
// ============================================================
// modules/transacciones/cobro_compras.php | SysInversiones 2026
// Pago de compras a crédito — abonos a proveedores
// Tablas: compras, pagos_compra, cuotas_compra, movimientos_caja
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexión BD no disponible.');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR_COMERCIAL'))        define('ROL_ASESOR_COMERCIAL', 2);
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL]);
verificarPermiso($pdo, 'cobro_compras');

$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);

$swal = null;
if (isset($_SESSION['swal_cobro_comp'])) { $swal = $_SESSION['swal_cobro_comp']; unset($_SESSION['swal_cobro_comp']); }

function redirigirCobroComp(string $icon, string $title, string $text): void {
    $_SESSION['swal_cobro_comp'] = compact('icon', 'title', 'text');
    header('Location: cobro_compras.php'); exit;
}

// ── Caja activa ───────────────────────────────────────────────────────────────
// Se busca cualquier caja abierta (no solo la del usuario actual)
// para que pagos a proveedores registrados por cualquier usuario afecten la caja correctamente.
$caja_activa = null;
try {
    $caja_activa = $pdo->query("SELECT id_caja, nombre, monto_inicial, fecha_apertura FROM caja WHERE estado='abierta' ORDER BY fecha_apertura DESC LIMIT 1")->fetch();
} catch (PDOException $e) {}
$hay_caja = !empty($caja_activa);

// ── AJAX: cuotas de una compra (para modal de pago) ──────────────────────────
if (isset($_GET['accion']) && $_GET['accion'] === 'cuotas_ajax') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id_compra'] ?? 0);
    try {
        $st = $pdo->prepare("SELECT id_cuota, numero_cuota, monto_cuota, fecha_vencimiento, estado FROM cuotas_compra WHERE id_compra = ? ORDER BY numero_cuota ASC");
        $st->execute([$id]);
        echo json_encode(['ok' => true, 'cuotas' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── AJAX: detalle de compra ───────────────────────────────────────────────────
if (isset($_GET['accion']) && $_GET['accion'] === 'detalle_ajax') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id_compra'] ?? 0);
    try {
        $stmt = $pdo->prepare("
            SELECT c.*,
                   p.razon_social AS proveedor_nombre,
                   p.ruc          AS proveedor_ruc,
                   p.telefono     AS proveedor_telefono,
                   p.email        AS proveedor_email,
                   u.nombre_completo AS registrado_por
            FROM compras c
            LEFT JOIN proveedores p ON p.id_proveedor = c.id_proveedor
            LEFT JOIN usuarios u ON u.id_usuario = c.id_usuario
            WHERE c.id_compra = ?
        ");
        $stmt->execute([$id]);
        $compra = $stmt->fetch();
        if (!$compra) { echo json_encode(['ok'=>false,'msg'=>'Compra no encontrada.']); exit; }

        $stDet = $pdo->prepare("
            SELECT dc.*, pr.nombre_producto, pr.codigo
            FROM detalle_compra dc
            LEFT JOIN productos pr ON pr.id_producto = dc.id_producto
            WHERE dc.id_compra = ?
        ");
        $stDet->execute([$id]);
        $detalle = $stDet->fetchAll();

        $stCuotas = $pdo->prepare("SELECT * FROM cuotas_compra WHERE id_compra = ? ORDER BY numero_cuota ASC");
        $stCuotas->execute([$id]);
        $cuotas = $stCuotas->fetchAll();

        $stPag = $pdo->prepare("SELECT pc.*, u.nombre_completo FROM pagos_compra pc LEFT JOIN usuarios u ON u.id_usuario = pc.id_usuario WHERE pc.id_compra = ? ORDER BY pc.fecha ASC");
        $stPag->execute([$id]);
        $pagos = $stPag->fetchAll();

        echo json_encode(['ok'=>true,'compra'=>$compra,'detalle'=>$detalle,'cuotas'=>$cuotas,'pagos'=>$pagos]);
    } catch (PDOException $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── POST: registrar pago a proveedor ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'registrar_pago') {
        // ── Validación: caja debe estar abierta ───────────────────────────────
        if (!$hay_caja) {
            redirigirCobroComp('warning', 'Caja no aperturada', 'Debes abrir una caja antes de registrar pagos a proveedores. Ve al módulo de Caja y apertura una sesión.');
        }

        $id_compra   = (int)($_POST['id_compra']   ?? 0);
        $monto       = (float)($_POST['monto']     ?? 0);
        $metodo_pago = $_POST['metodo_pago']        ?? 'efectivo';
        $observacion = trim($_POST['observacion']   ?? '') ?: null;

        if (!$id_compra) redirigirCobroComp('warning','Error','No se identificó la compra.');
        if ($monto <= 0) redirigirCobroComp('warning','Monto inválido','El monto debe ser mayor a 0.');
        $metodos_validos = ['efectivo','transferencia','yape','plin','tarjeta'];
        if (!in_array($metodo_pago, $metodos_validos)) $metodo_pago = 'efectivo';

        try {
            $pdo->beginTransaction();

            $stComp = $pdo->prepare("SELECT id_compra, total, saldo_pendiente, estado, tipo_pago FROM compras WHERE id_compra=?");
            $stComp->execute([$id_compra]);
            $compra = $stComp->fetch();
            if (!$compra) throw new Exception('Compra no encontrada.');
            if ($compra['tipo_pago'] !== 'credito') throw new Exception('Esta compra no es a crédito.');
            if ($compra['estado'] === 'pagado') throw new Exception('Esta compra ya está completamente pagada.');

            $saldo_actual = (float)$compra['saldo_pendiente'];
            if ($monto > $saldo_actual + 0.01) throw new Exception('El monto (S/. '.number_format($monto,2).') supera el saldo pendiente (S/. '.number_format($saldo_actual,2).').');

            // Insertar pago
            $pdo->prepare("INSERT INTO pagos_compra (id_compra, id_usuario, metodo_pago, monto, observacion, fecha) VALUES (?,?,?,?,?,NOW())")
                ->execute([$id_compra, $id_usuario, $metodo_pago, $monto, $observacion]);

            // Actualizar saldo y estado
            $nuevo_saldo  = round($saldo_actual - $monto, 2);
            $nuevo_estado = ($nuevo_saldo <= 0.009) ? 'pagado' : 'pendiente';
            $pdo->prepare("UPDATE compras SET saldo_pendiente=?, estado=? WHERE id_compra=?")
                ->execute([$nuevo_saldo, $nuevo_estado, $id_compra]);

            // Actualizar cuotas
            if ($nuevo_estado === 'pagado') {
                $pdo->prepare("UPDATE cuotas_compra SET estado='pagado' WHERE id_compra=? AND estado='pendiente'")
                    ->execute([$id_compra]);
            } else {
                $stCuotas = $pdo->prepare("SELECT id_cuota, monto_cuota FROM cuotas_compra WHERE id_compra=? AND estado='pendiente' ORDER BY numero_cuota ASC");
                $stCuotas->execute([$id_compra]);
                $cuotas = $stCuotas->fetchAll();
                $resto = $monto;
                foreach ($cuotas as $c) {
                    if ($resto <= 0) break;
                    if ($resto >= (float)$c['monto_cuota'] - 0.01) {
                        $pdo->prepare("UPDATE cuotas_compra SET estado='pagado' WHERE id_cuota=?")->execute([$c['id_cuota']]);
                        $resto -= (float)$c['monto_cuota'];
                    }
                }
            }

            // Movimiento en caja (egreso — salida de dinero para pagar proveedor) — dentro de la transacción
            if ($hay_caja) {
                $num_comp = 'COMP-' . str_pad($id_compra, 6, '0', STR_PAD_LEFT);
                $desc_mov = "Pago crédito proveedor {$num_comp}";
                $pdo->prepare("INSERT INTO movimientos_caja (id_caja,tipo_referencia,id_referencia,id_usuario,tipo,descripcion,monto,metodo_pago) VALUES (?,'compra',?,?,'egreso',?,?,?)")
                    ->execute([$caja_activa['id_caja'], $id_compra, $id_usuario, $desc_mov, $monto, $metodo_pago]);
            }

            $pdo->commit();

            $msg = $nuevo_estado === 'pagado'
                ? 'Pago registrado. Compra completamente pagada al proveedor.'
                : 'Pago de S/. '.number_format($monto,2).' registrado. Saldo pendiente: S/. '.number_format($nuevo_saldo,2).'.';
            redirigirCobroComp('success', '¡Pago registrado!', $msg);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            redirigirCobroComp('error', 'Error al registrar pago', $e->getMessage());
        }
    }
}

// ── DATOS ─────────────────────────────────────────────────────────────────────
$compras_credito = $pdo->query("
    SELECT c.*,
           p.razon_social AS proveedor_nombre,
           p.ruc          AS proveedor_ruc,
           p.telefono     AS proveedor_telefono
    FROM compras c
    LEFT JOIN proveedores p ON p.id_proveedor = c.id_proveedor
    WHERE c.tipo_pago = 'credito' AND c.estado = 'pendiente'
    ORDER BY c.fecha ASC
")->fetchAll();

$pagos_recientes = $pdo->query("
    SELECT pc.*,
           u.nombre_completo AS pagado_por,
           p.razon_social    AS proveedor_nombre,
           c.tipo_comprobante, c.numero_comprobante
    FROM pagos_compra pc
    LEFT JOIN usuarios u ON u.id_usuario = pc.id_usuario
    JOIN compras c ON c.id_compra = pc.id_compra
    LEFT JOIN proveedores p ON p.id_proveedor = c.id_proveedor
    ORDER BY pc.fecha DESC
    LIMIT 50
")->fetchAll();

$stats_comp = $pdo->query("
    SELECT
        COUNT(CASE WHEN tipo_pago='credito' AND estado='pendiente' THEN 1 END) AS pendientes,
        COALESCE(SUM(CASE WHEN tipo_pago='credito' AND estado='pendiente' THEN saldo_pendiente END),0) AS total_por_pagar,
        COUNT(CASE WHEN tipo_pago='credito' AND estado='pagado' AND DATE(fecha)=CURDATE() THEN 1 END) AS pagados_hoy,
        COALESCE(SUM(CASE WHEN tipo_pago='credito' AND estado='pagado' AND DATE(fecha)=CURDATE() THEN total END),0) AS monto_pagado_hoy
    FROM compras
")->fetch();

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>

<link rel="stylesheet" href="css/cobro_compras.css?v=<?= time() ?>">

<div class="content-wrapper">

    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-cobrocomp d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4><i class="fas fa-truck-loading mr-2"></i>Pago de Compras a Crédito</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones CH Computer &rsaquo; Compras &rsaquo; Pago de Créditos</small>
                </div>
                <?php if ($hay_caja): ?>
                <div class="cobrocomp-caja-badge">
                    <i class="fas fa-cash-register mr-1"></i>
                    Caja abierta — <?= htmlspecialchars($caja_activa['nombre']) ?>
                </div>
                <?php else: ?>
                <div class="cobrocomp-caja-badge cobrocomp-caja-sin">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Sin caja abierta — los pagos no se registrarán en caja
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <?php if ($swal): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon: '<?= $swal['icon'] ?>',
                    title: '<?= addslashes($swal['title']) ?>',
                    text: '<?= addslashes($swal['text']) ?>',
                    confirmButtonColor: '#7c3aed',
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
                    <div class="cobrocomp-stat" style="background:linear-gradient(135deg,#5b21b6,#7c3aed);">
                        <i class="fas fa-clock"></i>
                        <div>
                            <div class="cobrocomp-stat-val"><?= (int)$stats_comp['pendientes'] ?></div>
                            <div class="cobrocomp-stat-lbl">Compras pendientes</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="cobrocomp-stat" style="background:linear-gradient(135deg,#dc2626,#f87171);">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <div class="cobrocomp-stat-val">S/. <?= number_format($stats_comp['total_por_pagar'], 0) ?></div>
                            <div class="cobrocomp-stat-lbl">Total por pagar</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="cobrocomp-stat" style="background:linear-gradient(135deg,#166534,#22c55e);">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <div class="cobrocomp-stat-val"><?= (int)$stats_comp['pagados_hoy'] ?></div>
                            <div class="cobrocomp-stat-lbl">Pagados hoy</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="cobrocomp-stat" style="background:linear-gradient(135deg,#b45309,#f59e0b);">
                        <i class="fas fa-money-bill-wave"></i>
                        <div>
                            <div class="cobrocomp-stat-val">S/. <?= number_format($stats_comp['monto_pagado_hoy'], 0) ?></div>
                            <div class="cobrocomp-stat-lbl">Pagado hoy</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CARD PRINCIPAL -->
            <div class="card shadow-sm">
                <div class="cobrocomp-card-header d-flex align-items-center justify-content-between">
                    <h6 class="m-0"><i class="fas fa-list mr-2"></i>Gestión de Pagos — Compras a Crédito</h6>
                    <ul class="nav nav-tabs card-header-tabs ml-auto" id="cobroCompTab" role="tablist" style="border-bottom:none;">
                        <li class="nav-item">
                            <a class="nav-link active text-white font-weight-bold" data-toggle="tab" href="#tab-pendientes-comp" role="tab">
                                <i class="fas fa-clock mr-1"></i> Por pagar
                                <span class="badge badge-light ml-1"><?= count($compras_credito) ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white font-weight-bold" data-toggle="tab" href="#tab-historial-comp" role="tab">
                                <i class="fas fa-history mr-1"></i> Historial pagos
                                <span class="badge badge-light ml-1"><?= count($pagos_recientes) ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">

                        <!-- TAB: POR PAGAR -->
                        <div class="tab-pane fade show active" id="tab-pendientes-comp">
                            <?php if (empty($compras_credito)): ?>
                            <div class="cobrocomp-empty">
                                <i class="fas fa-check-double"></i>
                                <p>No hay compras a crédito pendientes de pago</p>
                                <small>Las compras a crédito aparecen aquí cuando tienen saldo pendiente</small>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table id="tablaPendientesComp" class="table table-cobrocomp table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Compra</th>
                                            <th>Proveedor</th>
                                            <th>Comprobante</th>
                                            <th class="text-right">Total</th>
                                            <th class="text-right">Saldo Pendiente</th>
                                            <th>Fecha Compra</th>
                                            <th class="text-center" style="width:110px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($compras_credito as $c): ?>
                                        <?php
                                        $total = (float)($c['total'] ?? 0);
                                        $saldo = (float)($c['saldo_pendiente'] ?? $total);
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="cobrocomp-num-comp">COMP-<?= str_pad($c['id_compra'],6,'0',STR_PAD_LEFT) ?></span>
                                            </td>
                                            <td>
                                                <div class="font-weight-bold" style="font-size:.85rem;"><?= htmlspecialchars($c['proveedor_nombre'] ?? '—') ?></div>
                                                <?php if ($c['proveedor_ruc']): ?>
                                                <small class="text-muted">RUC: <?= htmlspecialchars($c['proveedor_ruc']) ?></small>
                                                <?php endif; ?>
                                                <?php if ($c['proveedor_telefono']): ?>
                                                <br><small class="text-muted"><i class="fas fa-phone mr-1"></i><?= htmlspecialchars($c['proveedor_telefono']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="cobrocomp-tipo-badge"><?= ucfirst($c['tipo_comprobante']) ?></span>
                                                <br><small class="text-muted"><?= htmlspecialchars($c['numero_comprobante'] ?? '—') ?></small>
                                            </td>
                                            <td class="text-right font-weight-bold" style="color:#7c3aed;">
                                                S/. <?= number_format($total,2) ?>
                                            </td>
                                            <td class="text-right font-weight-bold">
                                                <span class="cobrocomp-saldo-badge">S/. <?= number_format($saldo,2) ?></span>
                                            </td>
                                            <td><small><?= date('d/m/Y', strtotime($c['fecha'])) ?></small></td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-info btn-ver-detalle-comp" title="Ver detalle"
                                                    data-id="<?= $c['id_compra'] ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-pagar-comp" title="Registrar pago"
                                                    data-id="<?= $c['id_compra'] ?>"
                                                    data-comp="COMP-<?= str_pad($c['id_compra'],6,'0',STR_PAD_LEFT) ?>"
                                                    data-proveedor="<?= htmlspecialchars($c['proveedor_nombre'] ?? '—', ENT_QUOTES) ?>"
                                                    data-total="<?= $total ?>"
                                                    data-saldo="<?= $saldo ?>">
                                                    <i class="fas fa-money-bill-wave mr-1"></i>Pagar
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB: HISTORIAL PAGOS -->
                        <div class="tab-pane fade" id="tab-historial-comp">
                            <div class="table-responsive">
                                <table id="tablaHistorialComp" class="table table-cobrocomp table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Compra</th>
                                            <th>Proveedor</th>
                                            <th>Comprobante</th>
                                            <th class="text-right">Monto Pagado</th>
                                            <th>Método</th>
                                            <th>Registrado por</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($pagos_recientes as $p): ?>
                                        <tr>
                                            <td><span class="cobrocomp-num-comp">COMP-<?= str_pad($p['id_compra'],6,'0',STR_PAD_LEFT) ?></span></td>
                                            <td><div style="font-size:.85rem;"><?= htmlspecialchars($p['proveedor_nombre'] ?? '—') ?></div></td>
                                            <td>
                                                <span class="cobrocomp-tipo-badge"><?= ucfirst($p['tipo_comprobante']) ?></span>
                                                <small class="text-muted ml-1"><?= htmlspecialchars($p['numero_comprobante'] ?? '') ?></small>
                                            </td>
                                            <td class="text-right font-weight-bold text-danger">S/. <?= number_format($p['monto'],2) ?></td>
                                            <td>
                                                <span class="cobrocomp-metodo-badge cobrocomp-metodo-<?= $p['metodo_pago'] ?>">
                                                    <i class="<?= ['efectivo'=>'fas fa-money-bill','yape'=>'fas fa-mobile-alt','plin'=>'fas fa-mobile-alt','transferencia'=>'fas fa-university','tarjeta'=>'fas fa-credit-card'][$p['metodo_pago']] ?? 'fas fa-money-bill' ?> mr-1"></i>
                                                    <?= ucfirst($p['metodo_pago']) ?>
                                                </span>
                                            </td>
                                            <td><small><?= htmlspecialchars($p['pagado_por'] ?? '—') ?></small></td>
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
     MODAL: REGISTRAR PAGO
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalPagoComp" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;border:none;">
            <div class="cobrocomp-modal-header">
                <div>
                    <h5 class="m-0"><i class="fas fa-money-bill-wave mr-2"></i>Registrar Pago a Proveedor</h5>
                    <div id="pago-modal-comp" class="cobrocomp-modal-num mt-1"></div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;text-shadow:none;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="cobrocomp-modal-resumen">
                <div class="cobrocomp-resumen-row">
                    <span><i class="fas fa-truck mr-1"></i>Proveedor</span>
                    <strong id="pago-modal-proveedor">—</strong>
                </div>
                <div class="cobrocomp-resumen-row">
                    <span><i class="fas fa-file-invoice-dollar mr-1"></i>Total compra</span>
                    <strong id="pago-modal-total" style="color:#7c3aed;">—</strong>
                </div>
                <div class="cobrocomp-resumen-row">
                    <span><i class="fas fa-exclamation-circle mr-1"></i>Saldo pendiente</span>
                    <strong id="pago-modal-saldo" style="color:#dc2626;">—</strong>
                </div>
            </div>

            <!-- Cuotas -->
            <div style="padding:10px 20px 0;">
                <div id="comp-cuotas-loading" style="display:none;text-align:center;padding:10px 0;">
                    <i class="fas fa-spinner fa-spin text-muted"></i>
                    <span class="text-muted ml-2" style="font-size:.85rem;">Cargando cuotas...</span>
                </div>
                <div id="comp-cuotas-lista"></div>
            </div>

            <form id="formPagoComp" method="POST">
                <input type="hidden" name="accion" value="registrar_pago">
                <input type="hidden" name="id_compra" id="pago-id-compra">
                <div class="modal-body" style="padding:16px 20px 20px;">
                    <div class="form-group mb-3">
                        <label class="cobrocomp-form-label"><i class="fas fa-credit-card mr-1"></i>Método de pago <span class="text-danger">*</span></label>
                        <div class="cobrocomp-metodos-grid" style="grid-template-columns:repeat(3,1fr);">
                            <label class="cobrocomp-metodo-opt">
                                <input type="radio" name="metodo_pago" value="efectivo" checked>
                                <span><i class="fas fa-money-bill-wave"></i><br>Efectivo</span>
                            </label>
                            <label class="cobrocomp-metodo-opt">
                                <input type="radio" name="metodo_pago" value="yape">
                                <span><i class="fas fa-mobile-alt"></i><br>Yape</span>
                            </label>
                            <label class="cobrocomp-metodo-opt">
                                <input type="radio" name="metodo_pago" value="transferencia">
                                <span><i class="fas fa-university"></i><br>Transferencia</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="cobrocomp-form-label"><i class="fas fa-coins mr-1"></i>Monto a pagar <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="background:#7c3aed;color:#fff;border-color:#7c3aed;">S/.</span>
                            </div>
                            <input type="number" name="monto" id="pago-monto" class="form-control form-control-lg"
                                   step="0.01" min="0.01" placeholder="0.00" required
                                   style="font-size:1.3rem;font-weight:700;text-align:right;">
                        </div>
                        <div id="pago-monto-hint" class="mt-1" style="font-size:.8rem;color:#64748b;"></div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="cobrocomp-form-label"><i class="fas fa-comment mr-1"></i>Observación <small class="text-muted">(opcional)</small></label>
                        <input type="text" name="observacion" class="form-control" placeholder="Ej: Pago cuota 1 proveedor..." maxlength="200">
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:14px 24px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-pagar-submit">
                        <i class="fas fa-check mr-1"></i>Confirmar pago
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: DETALLE DE COMPRA
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDetalleComp" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="max-width:780px;">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;border:none;">
            <div class="cobrocomp-modal-header">
                <div>
                    <h5 class="m-0"><i class="fas fa-clipboard-list mr-2"></i>Detalle de Compra</h5>
                    <div id="det-comp-num" class="cobrocomp-modal-num mt-1"></div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;text-shadow:none;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="detalle-comp-body" style="padding:0;">
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
          . '<script src="/sysinversioneschcomputer/modules/transacciones/js/cobro_compras.js?v=' . time() . '"></script>';
include $ruta_base . 'includes/footer.php';
?>
