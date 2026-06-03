<?php
// ============================================================
// modules/Caja/detalle_caja_reporte.php | SysInversiones CH Computer 2026
// Vista exclusiva de desglose completo de una caja aperturada
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';
if (!isset($pdo)) die('Sin BD');
if (!defined('ROL_ADMINISTRADOR'))    define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR_COMERCIAL')) define('ROL_ASESOR_COMERCIAL', 2);
if (!defined('ROL_TECNICO'))          define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);
verificarPermiso($pdo, 'historial_caja');

$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
$id_rol     = (int)($_SESSION['id_rol']     ?? 0);
$es_admin   = ($id_rol === ROL_ADMINISTRADOR);

$id_caja = (int)($_GET['id_caja'] ?? 0);
if (!$id_caja) { header('Location: reporte_cajas.php'); exit; }

// ── Datos de la caja ──────────────────────────────────────────────────────────
$caja = null;
try {
    $st = $pdo->prepare("
        SELECT c.*, u.nombre_completo AS cajero
        FROM caja c
        JOIN usuarios u ON u.id_usuario = c.id_usuario
        WHERE c.id_caja = ?
    ");
    $st->execute([$id_caja]);
    $caja = $st->fetch();
} catch (PDOException $e) {}

if (!$caja) { header('Location: reporte_cajas.php'); exit; }

// Verificar acceso: no-admin solo puede ver sus propias cajas
if (!$es_admin && (int)$caja['id_usuario'] !== $id_usuario) {
    header('Location: reporte_cajas.php'); exit;
}

// ── Movimientos de la caja ────────────────────────────────────────────────────
$movimientos = [];
try {
    $stM = $pdo->prepare("
        SELECT m.id_movimiento, m.tipo, m.tipo_referencia, m.id_referencia,
               m.descripcion, m.monto, m.metodo_pago, m.fecha, m.observacion,
               u.nombre_completo AS cajero
        FROM movimientos_caja m
        LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
        WHERE m.id_caja = ?
        ORDER BY m.fecha ASC
    ");
    $stM->execute([$id_caja]);
    $movimientos = $stM->fetchAll();
} catch (PDOException $e) {}

// ── Totales por tipo y origen ─────────────────────────────────────────────────
$total_ing = $total_eg = 0;
$por_origen = [];
$por_metodo = ['efectivo'=>['ing'=>0,'eg'=>0],'yape'=>['ing'=>0,'eg'=>0],'transferencia'=>['ing'=>0,'eg'=>0]];
foreach ($movimientos as $m) {
    $monto  = (float)$m['monto'];
    $origen = $m['tipo_referencia'];
    $metodo = $m['metodo_pago'];
    if ($m['tipo'] === 'ingreso') {
        $total_ing += $monto;
        $por_origen[$origen]['ing'] = ($por_origen[$origen]['ing'] ?? 0) + $monto;
        if (isset($por_metodo[$metodo])) $por_metodo[$metodo]['ing'] += $monto;
    } else {
        $total_eg += $monto;
        $por_origen[$origen]['eg'] = ($por_origen[$origen]['eg'] ?? 0) + $monto;
        if (isset($por_metodo[$metodo])) $por_metodo[$metodo]['eg'] += $monto;
    }
}
$neto          = $total_ing - $total_eg;
$saldo_final   = (float)$caja['monto_inicial'] + $neto;
$diferencia    = (float)($caja['diferencia'] ?? 0);

// ── Detalle de cierre por método (tabla cierre_caja_detalle) ─────────────────
$cierre_detalle = [];
try {
    $stC = $pdo->prepare("SELECT * FROM cierre_caja_detalle WHERE id_caja = ? ORDER BY metodo_pago");
    $stC->execute([$id_caja]);
    $cierre_detalle = $stC->fetchAll();
} catch (PDOException $e) {}

// ── Gráfico por hora ──────────────────────────────────────────────────────────
$por_hora = [];
foreach ($movimientos as $m) {
    $h = (int)date('H', strtotime($m['fecha']));
    if (!isset($por_hora[$h])) $por_hora[$h] = ['ing'=>0,'eg'=>0];
    if ($m['tipo']==='ingreso') $por_hora[$h]['ing'] += (float)$m['monto'];
    else                        $por_hora[$h]['eg']  += (float)$m['monto'];
}
ksort($por_hora);
$chart_labels = $chart_ing = $chart_eg = [];
foreach ($por_hora as $h => $v) {
    $chart_labels[] = str_pad($h,2,'0',STR_PAD_LEFT).':00';
    $chart_ing[]    = round($v['ing'],2);
    $chart_eg[]     = round($v['eg'],2);
}

// ── Duración ──────────────────────────────────────────────────────────────────
$dur_min = 0;
if ($caja['fecha_apertura']) {
    $fin = $caja['fecha_cierre'] ?? date('Y-m-d H:i:s');
    $dur_min = (int)round((strtotime($fin) - strtotime($caja['fecha_apertura'])) / 60);
}
$dur_txt = $dur_min >= 60 ? floor($dur_min/60).'h '.($dur_min%60).'m' : $dur_min.'m';

$extra_css = '<link rel="stylesheet" href="/sysinversioneschcomputer/modules/Caja/css/reporte_cajas.css?v='.time().'">
              <link rel="stylesheet" href="/sysinversioneschcomputer/modules/Caja/css/caja.css?v='.time().'">';
$extra_js  = '<script src="/sysinversioneschcomputer/modules/Caja/js/detalle_caja_reporte.js?v='.time().'"></script>';
include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<div class="content-wrapper">
<div class="content-header"><div class="container-fluid">
<div class="cx-page-header d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h4><i class="fas fa-cash-register mr-2"></i>Detalle de Caja #<?= $id_caja ?></h4>
        <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones &rsaquo; Caja &rsaquo; Reporte &rsaquo; <?= htmlspecialchars($caja['nombre'] ?? 'Caja #'.$id_caja) ?></small>
    </div>
    <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
        <a href="reporte_cajas.php" class="cx-btn cx-btn-ghost"><i class="fas fa-arrow-left mr-1"></i>Volver al Reporte</a>
        <button type="button" id="btnExportarDetalle" class="cx-btn cx-btn-primary">
            <i class="fas fa-download mr-1"></i>Exportar
        </button>
    </div>
</div>
</div></div>
<div class="content"><div class="container-fluid">

<!-- ── BANNER INFO CAJA ── -->
<div class="rc-filtros-card mb-4" style="background:linear-gradient(135deg,#0c1a3a,#0f3460);border:none;">
    <div class="row align-items-center">
        <div class="col-md-6 mb-2 mb-md-0">
            <div style="display:flex;align-items:center;gap:14px;">
                <div style="width:52px;height:52px;border-radius:14px;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-cash-register" style="color:#fff;font-size:1.4rem;"></i>
                </div>
                <div>
                    <div style="font-size:1.05rem;font-weight:800;color:#fff;"><?= htmlspecialchars($caja['nombre'] ?? 'Caja #'.$id_caja) ?></div>
                    <div style="font-size:.78rem;color:#94a3b8;margin-top:2px;">
                        <i class="fas fa-user-tie mr-1"></i><?= htmlspecialchars($caja['cajero']) ?>
                        &nbsp;·&nbsp;
                        <?php if ($caja['estado']==='abierta'): ?>
                        <span style="background:#10b981;color:#fff;border-radius:20px;padding:1px 10px;font-size:.7rem;font-weight:700;"><i class="fas fa-circle mr-1" style="font-size:.4rem;"></i>Abierta</span>
                        <?php else: ?>
                        <span style="background:#475569;color:#fff;border-radius:20px;padding:1px 10px;font-size:.7rem;font-weight:700;"><i class="fas fa-lock mr-1" style="font-size:.65rem;"></i>Cerrada</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="row text-center">
                <div class="col-4">
                    <div style="font-size:.65rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;">Apertura</div>
                    <div style="font-size:.85rem;font-weight:700;color:#fff;"><?= date('d/m/Y', strtotime($caja['fecha_apertura'])) ?></div>
                    <div style="font-size:.75rem;color:#0ea5e9;"><?= date('H:i:s', strtotime($caja['fecha_apertura'])) ?></div>
                </div>
                <div class="col-4">
                    <div style="font-size:.65rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;">Cierre</div>
                    <?php if ($caja['fecha_cierre']): ?>
                    <div style="font-size:.85rem;font-weight:700;color:#fff;"><?= date('d/m/Y', strtotime($caja['fecha_cierre'])) ?></div>
                    <div style="font-size:.75rem;color:#10b981;"><?= date('H:i:s', strtotime($caja['fecha_cierre'])) ?></div>
                    <?php else: ?>
                    <div style="font-size:.85rem;color:#94a3b8;">—</div>
                    <div style="font-size:.7rem;color:#f59e0b;">En curso</div>
                    <?php endif; ?>
                </div>
                <div class="col-4">
                    <div style="font-size:.65rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;">Duración</div>
                    <div style="font-size:.85rem;font-weight:700;color:#fff;"><?= $dur_txt ?></div>
                    <div style="font-size:.7rem;color:#94a3b8;"><?= count($movimientos) ?> movimientos</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── KPIs ── -->
<div class="row mb-4">
    <div class="col-6 col-md-3 mb-3">
        <div class="rc-kpi" style="--kc:#0ea5e9;background:linear-gradient(135deg,#0c1a3a,#0f3460);">
            <i class="fas fa-coins"></i>
            <div><div class="rc-kpi-val">S/. <?= number_format($caja['monto_inicial'],2) ?></div><div class="rc-kpi-lbl">Fondo inicial</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="rc-kpi" style="--kc:#10b981;background:linear-gradient(135deg,#047857,#10b981);">
            <i class="fas fa-arrow-up"></i>
            <div><div class="rc-kpi-val">+S/. <?= number_format($total_ing,2) ?></div><div class="rc-kpi-lbl">Total ingresos</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="rc-kpi" style="--kc:#ef4444;background:linear-gradient(135deg,#b91c1c,#ef4444);">
            <i class="fas fa-arrow-down"></i>
            <div><div class="rc-kpi-val">−S/. <?= number_format($total_eg,2) ?></div><div class="rc-kpi-lbl">Total egresos</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <?php $kc = $neto>=0?'#10b981':'#ef4444'; $kb = $neto>=0?'linear-gradient(135deg,#047857,#10b981)':'linear-gradient(135deg,#b91c1c,#ef4444)'; ?>
        <div class="rc-kpi" style="--kc:<?= $kc ?>;background:<?= $kb ?>;">
            <i class="fas fa-wallet"></i>
            <div><div class="rc-kpi-val"><?= $neto>=0?'+':'' ?>S/. <?= number_format($neto,2) ?></div><div class="rc-kpi-lbl">Neto del período</div></div>
        </div>
    </div>
</div>

<!-- ── FILA: DESGLOSE + CUADRE ── -->
<div class="row mb-4">
    <div class="col-md-7 mb-3">
        <div class="rc-desglose-card">
            <div class="rc-desglose-hdr"><i class="fas fa-layer-group mr-2"></i>Desglose por origen</div>
            <div class="rc-desglose-body">
                <?php
                $origenes = [
                    'venta'    => ['label'=>'Ventas de productos',  'icon'=>'fa-shopping-cart',   'color'=>'#1e40af','bg'=>'#dbeafe'],
                    'servicio' => ['label'=>'Cobros de servicios',  'icon'=>'fa-tools',            'color'=>'#0f766e','bg'=>'#ccfbf1'],
                    'compra'   => ['label'=>'Compras de productos', 'icon'=>'fa-truck-loading',    'color'=>'#b91c1c','bg'=>'#fee2e2'],
                    'manual'   => ['label'=>'Movimientos manuales','icon'=>'fa-hand-holding-usd','color'=>'#92400e','bg'=>'#fef3c7'],
                ];
                foreach ($origenes as $key => $cfg):
                    $ing = $por_origen[$key]['ing'] ?? 0;
                    $eg  = $por_origen[$key]['eg']  ?? 0;
                    if ($ing == 0 && $eg == 0) continue;
                ?>
                <div class="rc-desglose-item">
                    <div class="rc-desglose-icon" style="background:<?= $cfg['bg'] ?>;color:<?= $cfg['color'] ?>;"><i class="fas <?= $cfg['icon'] ?>"></i></div>
                    <div class="rc-desglose-info" style="flex:1;">
                        <div class="rc-desglose-lbl"><?= $cfg['label'] ?></div>
                        <div style="font-size:.7rem;color:#94a3b8;">
                            <?php if ($ing>0): ?><span style="color:#059669;">+S/. <?= number_format($ing,2) ?></span><?php endif; ?>
                            <?php if ($eg>0):  ?><span style="color:#dc2626;margin-left:6px;">−S/. <?= number_format($eg,2) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div style="font-family:monospace;font-weight:800;font-size:.88rem;color:<?= ($ing-$eg)>=0?'#059669':'#dc2626' ?>;">
                        <?= ($ing-$eg)>=0?'+':'' ?>S/. <?= number_format($ing-$eg,2) ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($movimientos)): ?>
                <div class="text-center py-4 text-muted" style="font-size:.83rem;"><i class="fas fa-inbox mr-2"></i>Sin movimientos</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-5 mb-3">
        <div class="rc-resumen-card">
            <div class="rc-desglose-hdr"><i class="fas fa-balance-scale mr-2"></i>Cuadre de caja</div>
            <div style="padding:14px 16px;">
                <div class="rc-resumen-row"><span>Fondo inicial</span><strong style="color:#0369a1;">S/. <?= number_format($caja['monto_inicial'],2) ?></strong></div>
                <div class="rc-resumen-row"><span>+ Ingresos</span><strong style="color:#059669;">S/. <?= number_format($total_ing,2) ?></strong></div>
                <div class="rc-resumen-row"><span>− Egresos</span><strong style="color:#dc2626;">S/. <?= number_format($total_eg,2) ?></strong></div>
                <div class="rc-resumen-row" style="border-top:2px solid #e2e8f0;margin-top:4px;padding-top:8px;">
                    <span style="font-weight:700;">Monto esperado</span>
                    <strong style="color:#0f172a;font-size:.95rem;">S/. <?= number_format($saldo_final,2) ?></strong>
                </div>
                <?php if ($caja['estado']==='cerrada'): ?>
                <div class="rc-resumen-row"><span>Monto contado</span><strong style="color:#0f172a;">S/. <?= number_format($caja['monto_final']??0,2) ?></strong></div>
                <div class="rc-resumen-row" style="border-top:2px solid #e2e8f0;margin-top:4px;padding-top:8px;">
                    <span style="font-weight:700;">Diferencia</span>
                    <?php if (abs($diferencia)<0.01): ?>
                    <strong style="color:#059669;"><i class="fas fa-check-circle mr-1"></i>Exacto</strong>
                    <?php elseif ($diferencia>0): ?>
                    <strong style="color:#059669;">+S/. <?= number_format($diferencia,2) ?> (sobrante)</strong>
                    <?php else: ?>
                    <strong style="color:#dc2626;">−S/. <?= number_format(abs($diferencia),2) ?> (faltante)</strong>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($caja['observacion']): ?>
                <div style="margin-top:10px;padding:8px 10px;background:#fffbeb;border-left:3px solid #f59e0b;border-radius:0 6px 6px 0;font-size:.78rem;color:#78350f;">
                    <i class="fas fa-comment-alt mr-1"></i><?= htmlspecialchars($caja['observacion']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── CUADRE POR MÉTODO (solo si hay detalle de cierre) ── -->
<?php if (!empty($cierre_detalle)): ?>
<div class="cx-card mb-4">
    <div class="cx-card-hdr tech"><i class="fas fa-credit-card mr-2"></i>Cuadre por canal de pago</div>
    <div class="table-responsive">
        <table class="table table-cx table-bordered table-sm mb-0">
            <thead><tr>
                <th>Canal</th>
                <th class="text-right">Sistema esperaba</th>
                <th class="text-right">Cajero contó</th>
                <th class="text-center">Diferencia</th>
            </tr></thead>
            <tbody>
            <?php
            $metodo_icons = ['efectivo'=>'fa-money-bill-wave','yape'=>'fa-mobile-alt','transferencia'=>'fa-university'];
            $metodo_labels = ['efectivo'=>'Efectivo','yape'=>'Yape','transferencia'=>'Transferencia'];
            foreach ($cierre_detalle as $cd):
                $dif_m = (float)$cd['diferencia'];
            ?>
            <tr>
                <td>
                    <i class="fas <?= $metodo_icons[$cd['metodo_pago']] ?? 'fa-credit-card' ?> mr-2" style="color:#64748b;"></i>
                    <strong><?= $metodo_labels[$cd['metodo_pago']] ?? ucfirst($cd['metodo_pago']) ?></strong>
                </td>
                <td class="text-right" style="font-family:monospace;font-weight:700;color:#0369a1;">S/. <?= number_format($cd['monto_esperado'],2) ?></td>
                <td class="text-right" style="font-family:monospace;font-weight:700;color:#0f172a;">S/. <?= number_format($cd['monto_contado'],2) ?></td>
                <td class="text-center">
                    <?php if (abs($dif_m)<0.01): ?>
                    <span class="cx-dif-ok"><i class="fas fa-check-circle mr-1"></i>Exacto</span>
                    <?php elseif ($dif_m>0): ?>
                    <span class="cx-dif-pos">+S/. <?= number_format($dif_m,2) ?></span>
                    <?php else: ?>
                    <span class="cx-dif-neg">−S/. <?= number_format(abs($dif_m),2) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ── GRÁFICO POR HORA ── -->
<?php if (!empty($chart_labels)): ?>
<div class="cx-card mb-4">
    <div class="cx-card-hdr tech"><i class="fas fa-chart-line mr-2"></i>Flujo de caja por hora</div>
    <div style="padding:20px;"><canvas id="chartDetalleDia" height="80"></canvas></div>
</div>
<?php endif; ?>

<!-- ── TABLA DE MOVIMIENTOS ── -->
<div class="rc-tabla-wrap mb-4">
    <div class="rc-tabla-hdr">
        <span><i class="fas fa-list-ul mr-2"></i>Movimientos de la caja — <?= count($movimientos) ?> registros</span>
        <div class="d-flex align-items-center" style="gap:8px;">
            <input type="text" id="dcBuscar" placeholder="Buscar..." class="rc-buscar-input">
        </div>
    </div>
    <div class="table-responsive">
        <table id="tablaDetalleMovs" class="table table-cx table-bordered table-hover table-sm mb-0">
            <thead><tr>
                <th class="text-center">#</th>
                <th>Descripción</th>
                <th class="text-center">Tipo</th>
                <th class="text-center">Origen</th>
                <th class="text-center">Método</th>
                <th class="text-right">Monto</th>
                <th>Fecha / Hora</th>
                <th>Cajero</th>
                <th class="text-center" style="width:55px;">Ver</th>
            </tr></thead>
            <tbody>
            <?php
            $origen_cfg_t = [
                'venta'    => ['label'=>'Venta',    'color'=>'#0369a1','bg'=>'#e0f2fe'],
                'servicio' => ['label'=>'Servicio', 'color'=>'#0f766e','bg'=>'#ccfbf1'],
                'compra'   => ['label'=>'Compra',   'color'=>'#7c3aed','bg'=>'#ede9fe'],
                'manual'   => ['label'=>'Manual',   'color'=>'#92400e','bg'=>'#fef3c7'],
            ];
            $metodo_cfg_t = [
                'efectivo'      => ['label'=>'Efectivo',      'icon'=>'fa-money-bill-wave','color'=>'#059669'],
                'yape'          => ['label'=>'Yape',          'icon'=>'fa-mobile-alt',     'color'=>'#7c3aed'],
                'transferencia' => ['label'=>'Transferencia', 'icon'=>'fa-university',     'color'=>'#d97706'],
            ];
            foreach ($movimientos as $idx => $m):
                $es_ing = ($m['tipo']==='ingreso');
                $oc = $origen_cfg_t[$m['tipo_referencia']] ?? ['label'=>ucfirst($m['tipo_referencia']),'color'=>'#64748b','bg'=>'#f1f5f9'];
                $mc = $metodo_cfg_t[$m['metodo_pago']] ?? ['label'=>ucfirst($m['metodo_pago']),'icon'=>'fa-credit-card','color'=>'#64748b'];
            ?>
            <tr class="rc-fila" data-search="<?= strtolower(htmlspecialchars($m['descripcion'].' '.$m['cajero'].' '.$m['tipo_referencia'])) ?>">
                <td class="text-center"><span class="cx-hist-num"><?= $idx+1 ?></span></td>
                <td>
                    <div style="font-weight:600;font-size:.83rem;color:#1e293b;"><?= htmlspecialchars($m['descripcion']) ?></div>
                    <?php if ($m['observacion']): ?>
                    <div style="font-size:.7rem;color:#94a3b8;"><i class="fas fa-comment-alt mr-1"></i><?= htmlspecialchars($m['observacion']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($es_ing): ?>
                    <span style="background:#dcfce7;color:#059669;border-radius:20px;padding:2px 10px;font-size:.72rem;font-weight:700;"><i class="fas fa-arrow-up mr-1"></i>Ingreso</span>
                    <?php else: ?>
                    <span style="background:#fee2e2;color:#dc2626;border-radius:20px;padding:2px 10px;font-size:.72rem;font-weight:700;"><i class="fas fa-arrow-down mr-1"></i>Egreso</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <span style="background:<?= $oc['bg'] ?>;color:<?= $oc['color'] ?>;border-radius:20px;padding:2px 10px;font-size:.72rem;font-weight:700;"><?= $oc['label'] ?></span>
                </td>
                <td class="text-center">
                    <span style="color:<?= $mc['color'] ?>;font-size:.8rem;font-weight:600;">
                        <i class="fas <?= $mc['icon'] ?> mr-1"></i><?= $mc['label'] ?>
                    </span>
                </td>
                <td class="text-right" style="font-family:monospace;font-weight:800;font-size:.88rem;color:<?= $es_ing?'#059669':'#dc2626' ?>;">
                    <?= $es_ing?'+':'−' ?>S/. <?= number_format($m['monto'],2) ?>
                </td>
                <td style="font-size:.82rem;">
                    <div style="font-weight:600;"><?= date('d/m/Y', strtotime($m['fecha'])) ?></div>
                    <div style="color:#94a3b8;font-size:.7rem;"><?= date('H:i:s', strtotime($m['fecha'])) ?></div>
                </td>
                <td style="font-size:.8rem;color:#475569;"><?= htmlspecialchars($m['cajero'] ?? '—') ?></td>
                <td class="text-center">
                    <button class="btn-ver-mov"
                        data-id="<?= $m['id_movimiento'] ?>"
                        data-ref="<?= htmlspecialchars($m['tipo_referencia']) ?>"
                        data-refid="<?= (int)($m['id_referencia']??0) ?>"
                        title="Ver detalle"
                        style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;background:linear-gradient(135deg,#0369a1,#0ea5e9);color:#fff;border:none;border-radius:7px;cursor:pointer;font-size:.78rem;">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($movimientos)): ?>
            <tr><td colspan="9" class="text-center py-5 text-muted">
                <i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>
                No hay movimientos registrados en esta caja
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div></div></div>

<!-- ══ MODAL EXPORTAR ══ -->
<div class="modal fade" id="modalExportarDetalle" tabindex="-1" aria-hidden="true">
<div class="modal-dialog" style="max-width:420px;">
<div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#0c1a3a,#0ea5e9);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
    <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-download mr-2"></i>Exportar Detalle de Caja</h6>
    <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-4">
    <p style="font-size:.83rem;color:#64748b;margin-bottom:14px;">
        <i class="fas fa-info-circle mr-1 text-primary"></i>
        Se exportarán los <strong><?= count($movimientos) ?> movimientos</strong> de la caja <strong><?= htmlspecialchars($caja['nombre'] ?? '#'.$id_caja) ?></strong>.
    </p>
    <div style="background:#f8f9fa;border-radius:10px;padding:14px;border:1px solid #e9ecef;">
        <p style="font-weight:600;font-size:.82rem;color:#495057;margin-bottom:10px;"><i class="fas fa-file-export mr-1"></i>Selecciona el formato:</p>
        <div class="d-flex" style="gap:8px;">
            <button type="button" id="dc_btn_csv"
                style="flex:1;background:linear-gradient(135deg,#047857,#10b981);color:#fff;border:none;border-radius:8px;padding:10px 8px;font-weight:700;font-size:.82rem;cursor:pointer;">
                <i class="fas fa-file-csv d-block mb-1" style="font-size:1.3rem;"></i>CSV
            </button>
            <button type="button" id="dc_btn_excel"
                style="flex:1;background:linear-gradient(135deg,#0c1a3a,#0ea5e9);color:#fff;border:none;border-radius:8px;padding:10px 8px;font-weight:700;font-size:.82rem;cursor:pointer;">
                <i class="fas fa-file-excel d-block mb-1" style="font-size:1.3rem;"></i>Excel
            </button>
            <button type="button" id="dc_btn_pdf"
                style="flex:1;background:linear-gradient(135deg,#922b21,#e74c3c);color:#fff;border:none;border-radius:8px;padding:10px 8px;font-weight:700;font-size:.82rem;cursor:pointer;">
                <i class="fas fa-file-pdf d-block mb-1" style="font-size:1.3rem;"></i>PDF
            </button>
        </div>
    </div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:10px 20px;">
    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cerrar</button>
</div>
</div></div></div>

<!-- ══ MODAL DETALLE MOVIMIENTO ══ -->
<div class="modal fade" id="modalDetalleMovimiento" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-xl" style="max-width:860px;">
<div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 24px 60px rgba(0,0,0,.2);">
    <div style="background:linear-gradient(135deg,#0c1a3a,#0f3460);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
        <div>
            <h5 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-receipt mr-2"></i>Detalle del Movimiento</h5>
            <small style="color:rgba(255,255,255,.6);font-size:.74rem;">Información completa del movimiento</small>
        </div>
        <button type="button" class="close" style="color:#fff;opacity:.8;text-shadow:none;font-size:1.4rem;" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div class="modal-body p-0" id="detalleMovBody">
        <div class="text-center py-5">
            <i class="fas fa-spinner fa-spin fa-2x text-muted mb-2 d-block" style="opacity:.4;"></i>
            <span class="text-muted" style="font-size:.83rem;">Cargando...</span>
        </div>
    </div>
    <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:11px 18px;">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cerrar</button>
    </div>
</div>
</div></div>

<!-- Datos para JS -->
<script>
window.DC = {
    id_caja: <?= $id_caja ?>,
    chartLabels: <?= json_encode($chart_labels) ?>,
    chartIng:    <?= json_encode($chart_ing) ?>,
    chartEg:     <?= json_encode($chart_eg) ?>
};
</script>

<?php include $ruta_base . 'includes/footer.php'; ?>
