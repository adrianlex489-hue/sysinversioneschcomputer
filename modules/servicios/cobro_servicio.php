<?php
// ============================================================
// modules/servicios/cobro_servicio.php | SysInversiones 2026
// Cobro de órdenes de servicio técnico
// Tablas: ordenes_servicio, pagos_servicio, movimientos_caja
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexión BD no disponible.');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR_COMERCIAL'))        define('ROL_ASESOR_COMERCIAL', 2);
if (!defined('ROL_TECNICO'))       define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);
verificarPermiso($pdo, 'cobro_servicio');

$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);

$swal = null;
if (isset($_SESSION['swal_cobro'])) { $swal = $_SESSION['swal_cobro']; unset($_SESSION['swal_cobro']); }

function redirigirCobro(string $icon, string $title, string $text): void {
    $_SESSION['swal_cobro'] = compact('icon', 'title', 'text');
    header('Location: cobro_servicio.php'); exit;
}

// ── Caja activa ───────────────────────────────────────────────────────────────
// Se busca cualquier caja abierta (no solo la del usuario actual)
// para que cobros de servicio registrados por cualquier usuario afecten la caja correctamente.
$caja_activa = null;
try {
    $caja_activa = $pdo->query("SELECT id_caja, nombre, monto_inicial, fecha_apertura FROM caja WHERE estado='abierta' ORDER BY fecha_apertura DESC LIMIT 1")->fetch();
} catch (PDOException $e) {}
$hay_caja = !empty($caja_activa);

// ── AJAX: detalle de orden ────────────────────────────────────────────────────
if (isset($_GET['accion']) && $_GET['accion'] === 'detalle_ajax') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id_orden'] ?? 0);
    try {
        $stmt = $pdo->prepare("
            SELECT o.*,
                   CASE o.tipo_cliente
                       WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
                       ELSE TRIM(CONCAT_WS(' ', cn.nombres, cn.apellido_paterno, cn.apellido_materno))
                   END AS cliente_nombre,
                   CASE o.tipo_cliente WHEN 'empresa' THEN 'RUC' ELSE cn.tipo_documento END AS tipo_documento,
                   CASE o.tipo_cliente WHEN 'empresa' THEN ce.ruc ELSE cn.documento_identidad END AS documento_identidad,
                   CASE o.tipo_cliente WHEN 'empresa' THEN ce.telefono ELSE cn.telefono END AS telefono,
                   e.tipo AS equipo_tipo, e.marca, e.modelo, e.numero_serie,
                   u.nombre_completo AS recepcionado_por,
                   t.nombre_completo AS tecnico_nombre
            FROM ordenes_servicio o
            LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = o.id_cliente AND o.tipo_cliente = 'natural'
            LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = o.id_cliente AND o.tipo_cliente = 'empresa'
            JOIN equipos  e ON e.id_equipo  = o.id_equipo
            LEFT JOIN usuarios u ON u.id_usuario = o.id_usuario
            LEFT JOIN usuarios t ON t.id_usuario = o.id_tecnico
            WHERE o.id_orden = ?
        ");
        $stmt->execute([$id]);
        $ord = $stmt->fetch();
        if (!$ord) { echo json_encode(['ok'=>false,'msg'=>'Orden no encontrada.']); exit; }

        $stSrv = $pdo->prepare("SELECT ds.*, s.nombre AS nombre_servicio FROM detalle_orden ds JOIN servicios s ON s.id_servicio = ds.id_servicio WHERE ds.id_orden = ?");
        $stSrv->execute([$id]);
        $servicios = $stSrv->fetchAll();

        $stRep = $pdo->prepare("
            SELECT oc.*, p.nombre_producto, p.codigo,
                   oc.precio_unitario, oc.subtotal, oc.cantidad, oc.descripcion AS nombre_producto_libre, oc.estado AS estado_cot
            FROM orden_cotizaciones oc
            LEFT JOIN productos p ON p.id_producto = oc.id_producto
            WHERE oc.id_orden = ?
            ORDER BY oc.fecha_cotizacion ASC
        ");
        $stRep->execute([$id]);
        $repuestos = $stRep->fetchAll();

        $stPag = $pdo->prepare("SELECT ps.*, u.nombre_completo FROM pagos_servicio ps LEFT JOIN usuarios u ON u.id_usuario = ps.id_usuario WHERE ps.id_orden = ? ORDER BY ps.fecha ASC");
        $stPag->execute([$id]);
        $pagos = $stPag->fetchAll();

        // Calcular total real = servicios + cotizaciones (excluye rechazadas)
        $total_servicios   = array_sum(array_column($servicios, 'subtotal'));
        $total_cotizaciones = array_sum(array_map(
            fn($r) => $r['estado_cot'] !== 'rechazado' ? (float)$r['subtotal'] : 0,
            $repuestos
        ));
        $total_real  = $total_servicios + $total_cotizaciones;
        $total_pagado = array_sum(array_column($pagos, 'monto'));
        $saldo_real  = max(0, round($total_real - $total_pagado, 2));

        echo json_encode([
            'ok'                 => true,
            'orden'              => $ord,
            'servicios'          => $servicios,
            'repuestos'          => $repuestos,
            'pagos'              => $pagos,
            'total_real'         => $total_real,
            'total_servicios'    => $total_servicios,
            'total_cotizaciones' => $total_cotizaciones,
            'saldo_real'         => $saldo_real,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}

// ── POST: registrar cobro ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'registrar_cobro') {
        // ── Validación: caja debe estar abierta ───────────────────────────────
        if (!$hay_caja) {
            redirigirCobro('warning', 'Caja no aperturada', 'Debes abrir una caja antes de registrar cobros de servicio. Ve al módulo de Caja y apertura una sesión.');
        }

        $id_orden    = (int)($_POST['id_orden']    ?? 0);
        $monto       = (float)($_POST['monto']     ?? 0);
        $metodo_pago = $_POST['metodo_pago']        ?? 'efectivo';
        $observacion = trim($_POST['observacion']   ?? '') ?: null;

        if (!$id_orden) redirigirCobro('warning','Error','No se identificó la orden.');
        if ($monto <= 0) redirigirCobro('warning','Monto inválido','El monto debe ser mayor a 0.');
        $metodos_validos = ['efectivo','yape','transferencia'];
        if (!in_array($metodo_pago, $metodos_validos)) $metodo_pago = 'efectivo';

        try {
            $pdo->beginTransaction();

            // Verificar orden
            $stOrd = $pdo->prepare("SELECT id_orden, costo_total, saldo_pendiente, estado_pago, estado FROM ordenes_servicio WHERE id_orden=?");
            $stOrd->execute([$id_orden]);
            $ord = $stOrd->fetch();
            if (!$ord) throw new Exception('Orden no encontrada.');
            if ($ord['estado_pago'] === 'pagado') throw new Exception('Esta orden ya está completamente pagada.');
            if ($ord['estado'] === 'cancelado') throw new Exception('No se puede cobrar una orden cancelada.');

            // Calcular total real = servicios + cotizaciones no rechazadas
            $stSrvTotal = $pdo->prepare("SELECT COALESCE(SUM(subtotal),0) FROM detalle_orden WHERE id_orden=?");
            $stSrvTotal->execute([$id_orden]);
            $total_servicios = (float)$stSrvTotal->fetchColumn();

            $stCotTotal = $pdo->prepare("SELECT COALESCE(SUM(subtotal),0) FROM orden_cotizaciones WHERE id_orden=? AND estado != 'rechazado'");
            $stCotTotal->execute([$id_orden]);
            $total_cotizaciones = (float)$stCotTotal->fetchColumn();

            $total_real = $total_servicios + $total_cotizaciones;

            // Calcular saldo real = total_real - lo ya pagado
            $stPagado = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM pagos_servicio WHERE id_orden=?");
            $stPagado->execute([$id_orden]);
            $ya_pagado = (float)$stPagado->fetchColumn();

            $saldo_actual = max(0, round($total_real - $ya_pagado, 2));
            if ($saldo_actual <= 0) throw new Exception('Esta orden ya está completamente pagada.');
            if ($monto > $saldo_actual + 0.01) throw new Exception('El monto (S/. '.number_format($monto,2).') supera el saldo pendiente (S/. '.number_format($saldo_actual,2).').');

            // Insertar pago
            $pdo->prepare("INSERT INTO pagos_servicio (id_orden, id_usuario, metodo_pago, monto, fecha, observacion) VALUES (?,?,?,?,NOW(),?)")
                ->execute([$id_orden, $id_usuario, $metodo_pago, $monto, $observacion]);

            // Actualizar saldo y estado_pago usando el total real
            $nuevo_saldo  = round($saldo_actual - $monto, 2);
            $nuevo_estado = ($nuevo_saldo <= 0.009) ? 'pagado' : 'pendiente';
            $pdo->prepare("UPDATE ordenes_servicio SET saldo_pendiente=?, estado_pago=?, costo_total=? WHERE id_orden=?")
                ->execute([$nuevo_saldo, $nuevo_estado, $total_real, $id_orden]);

            // Si pagado completamente → pasar a entregado si estaba en listo
            if ($nuevo_estado === 'pagado' && $ord['estado'] === 'listo') {
                $pdo->prepare("UPDATE ordenes_servicio SET estado='entregado' WHERE id_orden=?")->execute([$id_orden]);
                $pdo->prepare("INSERT INTO servicio_historial (id_orden, id_usuario, estado, descripcion, fecha) VALUES (?,?,'entregado','Equipo entregado al cliente tras cobro completo.',NOW())")
                    ->execute([$id_orden, $id_usuario]);
            }

            // Movimiento en caja — dentro de la transacción
            if ($hay_caja) {
                $num_ord = 'ORD-' . str_pad($id_orden, 6, '0', STR_PAD_LEFT);
                $desc_mov = "Cobro servicio {$num_ord}";
                $pdo->prepare("INSERT INTO movimientos_caja (id_caja, tipo_referencia, id_referencia, id_usuario, tipo, descripcion, monto, metodo_pago) VALUES (?,'servicio',?,?,'ingreso',?,?,?)")
                    ->execute([$caja_activa['id_caja'], $id_orden, $id_usuario, $desc_mov, $monto, $metodo_pago]);
            }

            $pdo->commit();

            $msg = $nuevo_estado === 'pagado'
                ? 'Cobro registrado. Orden completamente pagada y marcada como entregada.'
                : 'Cobro de S/. '.number_format($monto,2).' registrado. Saldo pendiente: S/. '.number_format($nuevo_saldo,2).'.';
            redirigirCobro('success', '¡Cobro registrado!', $msg);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            redirigirCobro('error', 'Error al registrar cobro', $e->getMessage());
        }
    }

    // Marcar como sin cobro (servicio gratuito / garantía)
    if ($accion === 'marcar_sin_cobro') {
        $id_orden = (int)($_POST['id_orden'] ?? 0);
        if (!$id_orden) redirigirCobro('warning','Error','No se identificó la orden.');
        try {
            $pdo->prepare("UPDATE ordenes_servicio SET estado_pago='sin_cobro', saldo_pendiente=0 WHERE id_orden=?")->execute([$id_orden]);
            redirigirCobro('info','Sin cobro','La orden fue marcada como sin cobro (garantía/gratuito).');
        } catch (PDOException $e) {
            redirigirCobro('error','Error',$e->getMessage());
        }
    }
}

// ── DATOS ─────────────────────────────────────────────────────────────────────
// Órdenes listas para cobrar (estado=listo, estado_pago=pendiente)
// total_real = servicios + cotizaciones no rechazadas
// saldo_real = total_real - lo ya pagado
$ordenes_cobrar = $pdo->query("
    SELECT o.*,
           CASE o.tipo_cliente
               WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
               ELSE TRIM(CONCAT_WS(', ',
                   NULLIF(TRIM(CONCAT_WS(' ', cn.apellido_paterno, cn.apellido_materno)),''),
                   NULLIF(TRIM(cn.nombres),'')
               ))
           END AS cliente_nombre,
           CASE o.tipo_cliente WHEN 'empresa' THEN 'RUC' ELSE cn.tipo_documento END AS tipo_documento,
           CASE o.tipo_cliente WHEN 'empresa' THEN ce.ruc ELSE cn.documento_identidad END AS documento_identidad,
           CASE o.tipo_cliente WHEN 'empresa' THEN ce.telefono ELSE cn.telefono END AS telefono,
           e.tipo AS equipo_tipo, e.marca, e.modelo,
           u.nombre_completo AS recepcionado_por,
           COALESCE((SELECT SUM(ds.subtotal) FROM detalle_orden ds WHERE ds.id_orden = o.id_orden), 0)
           + COALESCE((SELECT SUM(oc.subtotal) FROM orden_cotizaciones oc WHERE oc.id_orden = o.id_orden AND oc.estado != 'rechazado'), 0)
           AS total_real,
           GREATEST(0,
               COALESCE((SELECT SUM(ds.subtotal) FROM detalle_orden ds WHERE ds.id_orden = o.id_orden), 0)
               + COALESCE((SELECT SUM(oc.subtotal) FROM orden_cotizaciones oc WHERE oc.id_orden = o.id_orden AND oc.estado != 'rechazado'), 0)
               - COALESCE((SELECT SUM(ps.monto) FROM pagos_servicio ps WHERE ps.id_orden = o.id_orden), 0)
           ) AS saldo_real
    FROM ordenes_servicio o
    LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = o.id_cliente AND o.tipo_cliente = 'natural'
    LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = o.id_cliente AND o.tipo_cliente = 'empresa'
    JOIN equipos  e ON e.id_equipo  = o.id_equipo
    LEFT JOIN usuarios u ON u.id_usuario = o.id_usuario
    WHERE o.estado = 'listo' AND o.estado_pago IN ('pendiente','sin_cobro')
    ORDER BY o.fecha_recepcion ASC
")->fetchAll();

// Cobros recientes (últimos 50)
$cobros_recientes = $pdo->query("
    SELECT ps.*,
           u.nombre_completo AS cobrado_por,
           CASE o.tipo_cliente
               WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
               ELSE TRIM(CONCAT_WS(' ', cn.nombres, cn.apellido_paterno))
           END AS cliente_nombre,
           e.tipo AS equipo_tipo, e.marca, e.modelo
    FROM pagos_servicio ps
    LEFT JOIN usuarios u ON u.id_usuario = ps.id_usuario
    JOIN ordenes_servicio o ON o.id_orden = ps.id_orden
    LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = o.id_cliente AND o.tipo_cliente = 'natural'
    LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = o.id_cliente AND o.tipo_cliente = 'empresa'
    JOIN equipos  e ON e.id_equipo  = o.id_equipo
    ORDER BY ps.fecha DESC
    LIMIT 50
")->fetchAll();

// Stats
$stats = [];
$res = $pdo->query("
    SELECT
        COUNT(CASE WHEN estado='listo' AND estado_pago='pendiente' THEN 1 END) AS pendientes_cobro,
        COUNT(CASE WHEN estado_pago='pagado' AND DATE(fecha_recepcion)=CURDATE() THEN 1 END) AS cobrados_hoy,
        COALESCE(SUM(CASE WHEN estado_pago='pagado' AND DATE(fecha_recepcion)=CURDATE() THEN costo_total END),0) AS monto_hoy,
        COUNT(CASE WHEN estado='listo' THEN 1 END) AS total_listos
    FROM ordenes_servicio
");
$stats = $res->fetch();

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>

<link rel="stylesheet" href="css/cobro_servicio.css?v=<?= time() ?>">

<div class="content-wrapper">

    <!-- CABECERA -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-cobro d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4><i class="fas fa-hand-holding-usd mr-2"></i>Cobro de Servicios</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones CH Computer &rsaquo; Servicio Técnico &rsaquo; Cobro</small>
                </div>
                <?php if ($hay_caja): ?>
                <div class="cobro-caja-badge">
                    <i class="fas fa-cash-register mr-1"></i>
                    Caja abierta — <?= htmlspecialchars($caja_activa['nombre']) ?>
                </div>
                <?php else: ?>
                <div class="cobro-caja-badge cobro-caja-sin">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Sin caja abierta — los cobros no se registrarán en caja
                </div>
                <?php endif; ?>
            </div>
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
                    confirmButtonColor: '#0f766e',
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
                    <div class="cobro-stat" style="background:linear-gradient(135deg,#0f766e,#14b8a6);">
                        <i class="fas fa-clock"></i>
                        <div>
                            <div class="cobro-stat-val"><?= (int)$stats['pendientes_cobro'] ?></div>
                            <div class="cobro-stat-lbl">Pendientes de cobro</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="cobro-stat" style="background:linear-gradient(135deg,#166534,#22c55e);">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <div class="cobro-stat-val"><?= (int)$stats['cobrados_hoy'] ?></div>
                            <div class="cobro-stat-lbl">Cobrados hoy</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="cobro-stat" style="background:linear-gradient(135deg,#1a5276,#2980b9);">
                        <i class="fas fa-money-bill-wave"></i>
                        <div>
                            <div class="cobro-stat-val">S/. <?= number_format($stats['monto_hoy'], 0) ?></div>
                            <div class="cobro-stat-lbl">Recaudado hoy</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="cobro-stat" style="background:linear-gradient(135deg,#7c3aed,#a78bfa);">
                        <i class="fas fa-tools"></i>
                        <div>
                            <div class="cobro-stat-val"><?= (int)$stats['total_listos'] ?></div>
                            <div class="cobro-stat-lbl">Órdenes listas</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CARD PRINCIPAL -->
            <div class="card shadow-sm">
                <div class="cobro-card-header d-flex align-items-center justify-content-between">
                    <h6 class="m-0"><i class="fas fa-list mr-2"></i>Gestión de Cobros</h6>
                    <ul class="nav nav-tabs card-header-tabs ml-auto" id="cobroTab" role="tablist" style="border-bottom:none;">
                        <li class="nav-item">
                            <a class="nav-link active text-white font-weight-bold" data-toggle="tab" href="#tab-pendientes" role="tab">
                                <i class="fas fa-clock mr-1"></i> Por cobrar
                                <span class="badge badge-light ml-1"><?= count($ordenes_cobrar) ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white font-weight-bold" data-toggle="tab" href="#tab-historial" role="tab">
                                <i class="fas fa-history mr-1"></i> Historial cobros
                                <span class="badge badge-light ml-1"><?= count($cobros_recientes) ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">

                        <!-- TAB: POR COBRAR -->
                        <div class="tab-pane fade show active" id="tab-pendientes">
                            <?php if (empty($ordenes_cobrar)): ?>
                            <div class="cobro-empty">
                                <i class="fas fa-check-double"></i>
                                <p>No hay órdenes pendientes de cobro</p>
                                <small>Las órdenes aparecen aquí cuando el técnico las marca como "Listo"</small>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table id="tablaPendientes" class="table table-cobro table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Orden</th>
                                            <th>Cliente</th>
                                            <th>Equipo</th>
                                            <th class="text-right">Costo Total</th>
                                            <th class="text-right">Saldo Pendiente</th>
                                            <th class="text-center">Estado Pago</th>
                                            <th>Recepción</th>
                                            <th class="text-center" style="width:130px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($ordenes_cobrar as $o): ?>
                                        <?php
                                        $costo  = (float)($o['total_real'] ?? $o['costo_total'] ?? 0);
                                        $saldo  = (float)($o['saldo_real'] ?? $o['saldo_pendiente'] ?? $costo);
                                        if ($saldo <= 0 && $costo > 0) $saldo = $costo;
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="cobro-num-orden">ORD-<?= str_pad($o['id_orden'],6,'0',STR_PAD_LEFT) ?></span>
                                            </td>
                                            <td>
                                                <div class="font-weight-bold" style="font-size:.85rem;"><?= htmlspecialchars($o['cliente_nombre']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($o['tipo_documento']) ?>: <?= htmlspecialchars($o['documento_identidad']) ?></small>
                                                <?php if ($o['telefono']): ?>
                                                <br><small class="text-muted"><i class="fas fa-phone mr-1"></i><?= htmlspecialchars($o['telefono']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-size:.85rem;font-weight:600;"><?= htmlspecialchars($o['equipo_tipo']) ?> <?= htmlspecialchars($o['marca']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($o['modelo'] ?: '—') ?></small>
                                            </td>
                                            <td class="text-right font-weight-bold">
                                                <?php if ($costo > 0): ?>
                                                    <span style="color:#0f766e;">S/. <?= number_format($costo,2) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-right font-weight-bold">
                                                <?php if ($saldo > 0): ?>
                                                    <span class="cobro-saldo-badge">S/. <?= number_format($saldo,2) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="cobro-badge-<?= $o['estado_pago'] ?? 'pendiente' ?>">
                                                    <?= $o['estado_pago'] === 'sin_cobro' ? 'Sin cobro' : ucfirst($o['estado_pago'] ?? 'pendiente') ?>
                                                </span>
                                            </td>
                                            <td><small><?= date('d/m/Y', strtotime($o['fecha_recepcion'])) ?></small></td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-info btn-ver-detalle" title="Ver detalle"
                                                    data-id="<?= $o['id_orden'] ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if (($o['estado_pago'] ?? 'pendiente') !== 'sin_cobro'): ?>
                                                <button class="btn btn-sm btn-cobrar" title="Registrar cobro"
                                                    data-id="<?= $o['id_orden'] ?>"
                                                    data-orden="ORD-<?= str_pad($o['id_orden'],6,'0',STR_PAD_LEFT) ?>"
                                                    data-cliente="<?= htmlspecialchars($o['cliente_nombre'], ENT_QUOTES) ?>"
                                                    data-costo="<?= $costo ?>"
                                                    data-saldo="<?= $saldo ?>">
                                                    <i class="fas fa-hand-holding-usd mr-1"></i>Cobrar
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB: HISTORIAL COBROS -->
                        <div class="tab-pane fade" id="tab-historial">
                            <div class="table-responsive">
                                <table id="tablaHistorial" class="table table-cobro table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Orden</th>
                                            <th>Cliente</th>
                                            <th>Equipo</th>
                                            <th class="text-right">Monto Cobrado</th>
                                            <th>Método</th>
                                            <th>Cobrado por</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($cobros_recientes as $p): ?>
                                        <tr>
                                            <td><span class="cobro-num-orden">ORD-<?= str_pad($p['id_orden'],6,'0',STR_PAD_LEFT) ?></span></td>
                                            <td>
                                                <div style="font-size:.85rem;"><?= htmlspecialchars($p['cliente_nombre']) ?></div>
                                            </td>
                                            <td>
                                                <div style="font-size:.85rem;font-weight:600;"><?= htmlspecialchars($p['equipo_tipo']) ?> <?= htmlspecialchars($p['marca']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($p['modelo'] ?: '—') ?></small>
                                            </td>
                                            <td class="text-right font-weight-bold text-success">S/. <?= number_format($p['monto'],2) ?></td>
                                            <td>
                                                <span class="cobro-metodo-badge cobro-metodo-<?= $p['metodo_pago'] ?>">
                                                    <i class="<?= ['efectivo'=>'fas fa-money-bill','yape'=>'fas fa-mobile-alt','transferencia'=>'fas fa-university'][$p['metodo_pago']] ?? 'fas fa-money-bill' ?> mr-1"></i>
                                                    <?= ucfirst($p['metodo_pago']) ?>
                                                </span>
                                            </td>
                                            <td><small><?= htmlspecialchars($p['nombre_completo'] ?? '—') ?></small></td>
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
     MODAL: REGISTRAR COBRO
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCobro" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;border:none;">
            <div class="cobro-modal-header">
                <div>
                    <h5 class="m-0"><i class="fas fa-hand-holding-usd mr-2"></i>Registrar Cobro</h5>
                    <div id="cobro-modal-orden" class="cobro-modal-num-orden mt-1"></div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;text-shadow:none;">
                    <span>&times;</span>
                </button>
            </div>

            <!-- Resumen cliente/costo -->
            <div class="cobro-modal-resumen">
                <div class="cobro-resumen-row">
                    <span><i class="fas fa-user mr-1"></i>Cliente</span>
                    <strong id="cobro-modal-cliente">—</strong>
                </div>
                <div class="cobro-resumen-row">
                    <span><i class="fas fa-file-invoice-dollar mr-1"></i>Costo total</span>
                    <strong id="cobro-modal-costo" style="color:#0f766e;">—</strong>
                </div>
                <div class="cobro-resumen-row">
                    <span><i class="fas fa-exclamation-circle mr-1"></i>Saldo pendiente</span>
                    <strong id="cobro-modal-saldo" style="color:#dc2626;">—</strong>
                </div>
            </div>

            <form id="formCobro" method="POST">
                <input type="hidden" name="accion" value="registrar_cobro">
                <input type="hidden" name="id_orden" id="cobro-id-orden">

                <div class="modal-body" style="padding:20px 24px;">

                    <!-- Método de pago -->
                    <div class="form-group mb-3">
                        <label class="cobro-form-label"><i class="fas fa-credit-card mr-1"></i>Método de pago <span class="text-danger">*</span></label>
                        <div class="cobro-metodos-grid">
                            <label class="cobro-metodo-opt">
                                <input type="radio" name="metodo_pago" value="efectivo" checked>
                                <span><i class="fas fa-money-bill-wave"></i><br>Efectivo</span>
                            </label>
                            <label class="cobro-metodo-opt">
                                <input type="radio" name="metodo_pago" value="yape">
                                <span><i class="fas fa-mobile-alt"></i><br>Yape</span>
                            </label>
                            <label class="cobro-metodo-opt">
                                <input type="radio" name="metodo_pago" value="transferencia">
                                <span><i class="fas fa-university"></i><br>Transferencia</span>
                            </label>
                        </div>
                    </div>

                    <!-- Monto -->
                    <div class="form-group mb-3">
                        <label class="cobro-form-label"><i class="fas fa-coins mr-1"></i>Monto a cobrar <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="background:#0f766e;color:#fff;border-color:#0f766e;">S/.</span>
                            </div>
                            <input type="number" name="monto" id="cobro-monto" class="form-control form-control-lg"
                                   step="0.01" min="0.01" placeholder="0.00" required
                                   style="font-size:1.3rem;font-weight:700;text-align:right;">
                        </div>
                        <div id="cobro-monto-hint" class="mt-1" style="font-size:.8rem;color:#64748b;"></div>
                    </div>

                    <!-- Vuelto (solo efectivo) -->
                    <div id="cobro-vuelto-box" class="cobro-vuelto-box" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-exchange-alt mr-1"></i>Vuelto estimado</span>
                            <strong id="cobro-vuelto-val" style="font-size:1.1rem;">S/. 0.00</strong>
                        </div>
                    </div>

                    <!-- Observación -->
                    <div class="form-group mb-0">
                        <label class="cobro-form-label"><i class="fas fa-comment mr-1"></i>Observación <small class="text-muted">(opcional)</small></label>
                        <input type="text" name="observacion" class="form-control" placeholder="Ej: Pago con billete de 100..." maxlength="200">
                    </div>

                </div>

                <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:14px 24px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancelar
                    </button>
                    <button type="button" id="btnSinCobro" class="btn btn-outline-secondary btn-sm" title="Marcar como garantía o gratuito">
                        <i class="fas fa-gift mr-1"></i>Sin cobro
                    </button>
                    <button type="submit" class="btn btn-cobrar-submit">
                        <i class="fas fa-check mr-1"></i>Confirmar cobro
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: DETALLE DE ORDEN
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDetalle" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="max-width:780px;">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;border:none;">
            <div class="cobro-modal-header">
                <div>
                    <h5 class="m-0"><i class="fas fa-clipboard-list mr-2"></i>Detalle de Orden</h5>
                    <div id="det-num-orden" class="cobro-modal-num-orden mt-1"></div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;text-shadow:none;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="detalle-body" style="padding:0;">
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
          . '<script src="/sysinversioneschcomputer/modules/servicios/js/cobro_servicio.js?v=' . time() . '"></script>';
include $ruta_base . 'includes/footer.php';
?>
