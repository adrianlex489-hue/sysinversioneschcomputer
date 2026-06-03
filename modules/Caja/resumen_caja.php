<?php
// modules/Caja/resumen_caja.php | SysInversiones CH Computer 2026
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';
if (!isset($pdo)) die('Sin BD');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR_COMERCIAL'))        define('ROL_ASESOR_COMERCIAL', 2);
if (!defined('ROL_TECNICO'))       define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);
verificarPermiso($pdo, 'caja');

$caja_activa = null;
try {
    $caja_activa = $pdo->query("SELECT c.*, u.nombre_completo FROM caja c JOIN usuarios u ON u.id_usuario=c.id_usuario WHERE c.estado='abierta' ORDER BY c.fecha_apertura DESC LIMIT 1")->fetch();
} catch (PDOException $e) {}
if (!$caja_activa) { header('Location: caja.php'); exit; }

$id_caja = (int)$caja_activa['id_caja'];
$METODOS = ['efectivo','yape','transferencia'];

// ── Datos por origen y método ─────────────────────────────────────────────────
$por_origen = [];
$por_metodo = array_fill_keys($METODOS, ['ingreso'=>0,'egreso'=>0]);
$total_ing = $total_eg = 0;

try {
    $st = $pdo->prepare("SELECT tipo_referencia, tipo, metodo_pago, COALESCE(SUM(monto),0) AS total FROM movimientos_caja WHERE id_caja=? GROUP BY tipo_referencia, tipo, metodo_pago");
    $st->execute([$id_caja]);
    foreach ($st->fetchAll() as $r) {
        $origen = $r['tipo_referencia']; $tipo = $r['tipo']; $metodo = $r['metodo_pago']; $monto = (float)$r['total'];
        if (!isset($por_origen[$origen])) $por_origen[$origen] = ['ingreso'=>0,'egreso'=>0];
        $por_origen[$origen][$tipo] += $monto;
        if (isset($por_metodo[$metodo])) $por_metodo[$metodo][$tipo] += $monto;
        if ($tipo==='ingreso') $total_ing += $monto; else $total_eg += $monto;
    }
} catch (PDOException $e) {}

// ── Separar ventas contado vs abonos crédito ──────────────────────────────────
$venta_contado = 0; $venta_credito = 0;
try {
    // Ventas al contado: movimientos de venta cuya venta tiene tipo_pago='contado'
    $stVC = $pdo->prepare("
        SELECT COALESCE(SUM(mc.monto),0)
        FROM movimientos_caja mc
        JOIN ventas v ON v.id_venta = mc.id_referencia
        WHERE mc.id_caja = ? AND mc.tipo_referencia = 'venta' AND mc.tipo = 'ingreso'
          AND v.tipo_pago = 'contado'
    ");
    $stVC->execute([$id_caja]);
    $venta_contado = (float)$stVC->fetchColumn();

    // Abonos a crédito: movimientos de venta cuya venta tiene tipo_pago='credito'
    $stVCr = $pdo->prepare("
        SELECT COALESCE(SUM(mc.monto),0)
        FROM movimientos_caja mc
        JOIN ventas v ON v.id_venta = mc.id_referencia
        WHERE mc.id_caja = ? AND mc.tipo_referencia = 'venta' AND mc.tipo = 'ingreso'
          AND v.tipo_pago = 'credito'
    ");
    $stVCr->execute([$id_caja]);
    $venta_credito = (float)$stVCr->fetchColumn();
} catch (PDOException $e) {}

// ── Separar compras contado vs pagos a proveedores (crédito) ─────────────────
$compra_contado = 0; $pago_proveedor = 0;
try {
    $stCC = $pdo->prepare("
        SELECT COALESCE(SUM(mc.monto),0)
        FROM movimientos_caja mc
        JOIN compras c ON c.id_compra = mc.id_referencia
        WHERE mc.id_caja = ? AND mc.tipo_referencia = 'compra' AND mc.tipo = 'egreso'
          AND c.tipo_pago = 'contado'
    ");
    $stCC->execute([$id_caja]);
    $compra_contado = (float)$stCC->fetchColumn();

    $stCP = $pdo->prepare("
        SELECT COALESCE(SUM(mc.monto),0)
        FROM movimientos_caja mc
        JOIN compras c ON c.id_compra = mc.id_referencia
        WHERE mc.id_caja = ? AND mc.tipo_referencia = 'compra' AND mc.tipo = 'egreso'
          AND c.tipo_pago = 'credito'
    ");
    $stCP->execute([$id_caja]);
    $pago_proveedor = (float)$stCP->fetchColumn();
} catch (PDOException $e) {
    $compra_contado = $por_origen['compra']['egreso'] ?? 0;
}

$saldo_actual = (float)$caja_activa['monto_inicial'] + $total_ing - $total_eg;
$neto = $total_ing - $total_eg;

// ── Movimientos por hora (para gráfico de línea) ──────────────────────────────
$por_hora = array_fill(0, 24, ['ingreso'=>0,'egreso'=>0]);
try {
    $stH = $pdo->prepare("SELECT HOUR(fecha) AS hora, tipo, COALESCE(SUM(monto),0) AS total FROM movimientos_caja WHERE id_caja=? GROUP BY HOUR(fecha), tipo");
    $stH->execute([$id_caja]);
    foreach ($stH->fetchAll() as $r) $por_hora[(int)$r['hora']][$r['tipo']] += (float)$r['total'];
} catch (PDOException $e) {}

// Rango: desde apertura hasta ahora, siempre al menos 2 puntos para que el gráfico se vea
$hora_ini = (int)date('H', strtotime($caja_activa['fecha_apertura']));
$hora_fin = (int)date('H');
// Si apertura y cierre son la misma hora, extender al menos 1 hora más
if ($hora_fin <= $hora_ini) $hora_fin = min(23, $hora_ini + 1);
$horas_labels = $horas_ing = $horas_eg = [];
for ($h = $hora_ini; $h <= $hora_fin; $h++) {
    $horas_labels[] = str_pad($h,2,'0',STR_PAD_LEFT) . ':00';
    $horas_ing[]    = round($por_hora[$h]['ingreso'], 2);
    $horas_eg[]     = round($por_hora[$h]['egreso'],  2);
}
// Si todos los valores son 0, agregar datos de ejemplo para que el gráfico no quede vacío
$hay_datos_hora = array_sum($horas_ing) > 0 || array_sum($horas_eg) > 0;

// ── Datos para gráfico de dona (ingresos por origen) ─────────────────────────
$origen_cfg = [
    'venta_contado' => ['label'=>'Ventas contado',    'color'=>'#0ea5e9'],
    'venta_credito' => ['label'=>'Abonos crédito',    'color'=>'#6366f1'],
    'servicio'      => ['label'=>'Cobros servicio',   'color'=>'#10b981'],
    'manual_ing'    => ['label'=>'Ingresos manuales', 'color'=>'#f59e0b'],
];
$dona_labels = $dona_data = $dona_colors = [];
$dona_map = [
    'venta_contado' => $venta_contado,
    'venta_credito' => $venta_credito,
    'servicio'      => $por_origen['servicio']['ingreso'] ?? 0,
    'manual_ing'    => $por_origen['manual']['ingreso']   ?? 0,
];
foreach ($origen_cfg as $key => $cfg) {
    $val = $dona_map[$key] ?? 0;
    if ($val > 0) { $dona_labels[] = $cfg['label']; $dona_data[] = round($val,2); $dona_colors[] = $cfg['color']; }
}
if (empty($dona_data)) { $dona_labels = ['Sin ingresos']; $dona_data = [1]; $dona_colors = ['#e2e8f0']; }

// ── Datos para gráfico de dona (egresos por origen) ───────────────────────────
$origen_eg_cfg = [
    'compra_contado' => ['label'=>'Compras contado',       'color'=>'#ef4444'],
    'pago_proveedor' => ['label'=>'Pagos a proveedores',   'color'=>'#dc2626'],
    'manual_eg'      => ['label'=>'Egresos manuales',      'color'=>'#f97316'],
];
$dona_eg_labels = $dona_eg_data = $dona_eg_colors = [];
$dona_eg_map = [
    'compra_contado' => $compra_contado,
    'pago_proveedor' => $pago_proveedor,
    'manual_eg'      => $por_origen['manual']['egreso'] ?? 0,
];
foreach ($origen_eg_cfg as $key => $cfg) {
    $val = $dona_eg_map[$key] ?? 0;
    if ($val > 0) { $dona_eg_labels[] = $cfg['label']; $dona_eg_data[] = round($val,2); $dona_eg_colors[] = $cfg['color']; }
}
if (empty($dona_eg_data)) { $dona_eg_labels = ['Sin egresos']; $dona_eg_data = [1]; $dona_eg_colors = ['#e2e8f0']; }

// ── Datos para gráfico de barras (por método) ─────────────────────────────────
$metodo_cfg_res = [
    'efectivo'=>['label'=>'Efectivo','color'=>'#10b981'],
    'yape'=>['label'=>'Yape','color'=>'#8b5cf6'],
    'transferencia'=>['label'=>'Transfer.','color'=>'#f59e0b'],
];
$bar_labels = $bar_ing = $bar_eg = $bar_colors = [];
foreach ($metodo_cfg_res as $m => $cfg) {
    $bar_labels[]  = $cfg['label'];
    $bar_ing[]     = round($por_metodo[$m]['ingreso'], 2);
    $bar_eg[]      = round($por_metodo[$m]['egreso'],  2);
    $bar_colors[]  = $cfg['color'];
}

$extra_css = '<link rel="stylesheet" href="/sysinversioneschcomputer/modules/Caja/css/caja.css?v=' . time() . '">';
$extra_js  = '<script src="/sysinversioneschcomputer/modules/Caja/js/resumen_caja.js?v=' . time() . '"><\/script>';
include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<div class="content-wrapper">
<div class="content-header"><div class="container-fluid">
<div class="cx-page-header d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h4><i class="fas fa-chart-bar mr-2"></i>Resumen de Caja</h4>
        <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones &rsaquo; Caja &rsaquo; <?= htmlspecialchars($caja_activa['nombre']) ?></small>
    </div>
    <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
        <a href="caja.php" class="cx-btn cx-btn-ghost"><i class="fas fa-arrow-left mr-1"></i>Volver a Caja</a>
        <a href="movimientos_caja.php" class="cx-btn cx-btn-ghost"><i class="fas fa-list-ul mr-1"></i>Movimientos</a>
        <a href="cierre_caja.php?id_caja=<?= $id_caja ?>" class="cx-btn cx-btn-danger"><i class="fas fa-lock mr-1"></i>Cerrar Caja</a>
    </div>
</div>
</div></div>
<div class="content"><div class="container-fluid">

<!-- KPIs -->
<div class="row mb-4">
    <?php
    $kpis = [
        ['icon'=>'fas fa-coins',      'bg'=>'linear-gradient(135deg,#0369a1,#0ea5e9)', 'sc'=>'#0ea5e9', 'lbl'=>'Fondo inicial',  'val'=>'S/. '.number_format($caja_activa['monto_inicial'],2), 'color'=>'#0369a1'],
        ['icon'=>'fas fa-arrow-up',   'bg'=>'linear-gradient(135deg,#047857,#10b981)', 'sc'=>'#10b981', 'lbl'=>'Total ingresos', 'val'=>'+S/. '.number_format($total_ing,2),                  'color'=>'#047857'],
        ['icon'=>'fas fa-arrow-down', 'bg'=>'linear-gradient(135deg,#b91c1c,#ef4444)', 'sc'=>'#ef4444', 'lbl'=>'Total egresos',  'val'=>'−S/. '.number_format($total_eg,2),                   'color'=>'#b91c1c'],
        ['icon'=>'fas fa-wallet',     'bg'=>$saldo_actual>=0?'linear-gradient(135deg,#047857,#10b981)':'linear-gradient(135deg,#b91c1c,#ef4444)', 'sc'=>$saldo_actual>=0?'#10b981':'#ef4444', 'lbl'=>'Saldo actual', 'val'=>'S/. '.number_format($saldo_actual,2), 'color'=>$saldo_actual>=0?'#047857':'#b91c1c'],
    ];
    foreach ($kpis as $k): ?>
    <div class="col-md-3 col-6 mb-3">
        <div class="cx-stat-card" style="--sc:<?= $k['sc'] ?>;">
            <div class="cx-stat-icon" style="background:<?= $k['bg'] ?>;"><i class="<?= $k['icon'] ?>"></i></div>
            <div>
                <div class="cx-stat-lbl"><?= $k['lbl'] ?></div>
                <div class="cx-stat-val" style="color:<?= $k['color'] ?>;"><?= $k['val'] ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- GRÁFICOS FILA 1: Línea de tiempo -->
<div class="row mb-4">
    <div class="col-12">
        <div class="cx-card">
            <div class="cx-card-hdr tech"><i class="fas fa-chart-line mr-2"></i>Flujo de caja por hora</div>
            <div style="padding:20px;">
                <canvas id="chartLinea" height="80"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- GRÁFICOS FILA 2: Dona ingresos + Dona egresos -->
<div class="row mb-4">
    <div class="col-lg-6 mb-3">
        <div class="cx-card h-100">
            <div class="cx-card-hdr cyan"><i class="fas fa-chart-pie mr-2"></i>Ingresos por origen</div>
            <div style="padding:20px;display:flex;flex-direction:column;align-items:center;">
                <canvas id="chartDona" height="180" style="max-width:200px;"></canvas>
                <div id="donaLeyenda" style="margin-top:14px;width:100%;"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="cx-card h-100">
            <div class="cx-card-hdr" style="background:linear-gradient(135deg,#b91c1c,#ef4444);"><i class="fas fa-chart-pie mr-2"></i>Egresos por origen</div>
            <div style="padding:20px;display:flex;flex-direction:column;align-items:center;">
                <canvas id="chartDonaEg" height="180" style="max-width:200px;"></canvas>
                <div id="donaEgLeyenda" style="margin-top:14px;width:100%;"></div>
            </div>
        </div>
    </div>
</div>

<!-- GRÁFICOS FILA 2: Barras por método + Desglose por origen -->
<div class="row mb-4">
    <div class="col-lg-7 mb-3">
        <div class="cx-card">
            <div class="cx-card-hdr green"><i class="fas fa-chart-bar mr-2"></i>Ingresos vs Egresos por canal de pago</div>
            <div style="padding:20px;">
                <canvas id="chartBarras" height="130"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-5 mb-3">
        <div class="cx-card h-100">
            <div class="cx-card-hdr amber"><i class="fas fa-layer-group mr-2"></i>Desglose por origen</div>
            <div style="padding:6px 0;">
                <?php
                $origen_full = [
                    'venta_contado' => ['label'=>'Ventas contado',       'icon'=>'fas fa-shopping-cart',    'color'=>'#0ea5e9', 'bg'=>'#e0f2fe', 'ing'=>$venta_contado,  'eg'=>0],
                    'venta_credito' => ['label'=>'Abonos crédito clientes','icon'=>'fas fa-credit-card',    'color'=>'#6366f1', 'bg'=>'#ede9fe', 'ing'=>$venta_credito,  'eg'=>0],
                    'servicio'      => ['label'=>'Cobros de servicio',    'icon'=>'fas fa-tools',            'color'=>'#10b981', 'bg'=>'#dcfce7', 'ing'=>$por_origen['servicio']['ingreso']??0, 'eg'=>0],
                    'compra_cont'   => ['label'=>'Compras al contado',    'icon'=>'fas fa-truck-loading',    'color'=>'#ef4444', 'bg'=>'#fee2e2', 'ing'=>0, 'eg'=>$compra_contado],
                    'pago_prov'     => ['label'=>'Pagos a proveedores',   'icon'=>'fas fa-handshake',        'color'=>'#dc2626', 'bg'=>'#fecaca', 'ing'=>0, 'eg'=>$pago_proveedor],
                    'manual'        => ['label'=>'Movimientos manuales',  'icon'=>'fas fa-hand-holding-usd', 'color'=>'#f59e0b', 'bg'=>'#fef3c7', 'ing'=>$por_origen['manual']['ingreso']??0, 'eg'=>$por_origen['manual']['egreso']??0],
                ];
                $hay_datos = false;
                foreach ($origen_full as $key => $cfg):
                    $ing = $cfg['ing']; $eg = $cfg['eg'];
                    if ($ing == 0 && $eg == 0) continue;
                    $hay_datos = true;
                    $neto_o = $ing - $eg;
                ?>
                <div style="display:flex;align-items:center;gap:11px;padding:11px 18px;border-bottom:1px solid #f8fafc;transition:background .15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                    <div style="width:38px;height:38px;border-radius:10px;background:<?= $cfg['bg'] ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="<?= $cfg['icon'] ?>" style="color:<?= $cfg['color'] ?>;font-size:.85rem;"></i>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:.83rem;font-weight:700;color:#1e293b;"><?= $cfg['label'] ?></div>
                        <div style="font-size:.7rem;color:#94a3b8;margin-top:1px;">
                            <?php if ($ing>0): ?><span style="color:#059669;">+S/. <?= number_format($ing,2) ?></span><?php endif; ?>
                            <?php if ($eg>0):  ?><span style="color:#dc2626;margin-left:6px;">−S/. <?= number_format($eg,2) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div style="font-size:.9rem;font-weight:800;font-family:monospace;color:<?= $neto_o>=0?$cfg['color']:'#dc2626' ?>;">
                        <?= $neto_o>=0?'+':'' ?>S/. <?= number_format($neto_o,2) ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (!$hay_datos): ?>
                <div class="text-center py-4 text-muted" style="font-size:.83rem;"><i class="fas fa-inbox mr-2"></i>Sin movimientos registrados</div>
                <?php endif; ?>
                <div class="cx-neto-box" style="margin:8px 12px 12px;">
                    <span><i class="fas fa-coins mr-2"></i>Neto del día</span>
                    <span class="cx-neto-val <?= $neto>=0?'pos':'neg' ?>"><?= $neto>=0?'+':'' ?>S/. <?= number_format($neto,2) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Accesos rápidos -->
<div class="row mb-4">
    <div class="col-12 mb-2"><h6 style="font-size:.88rem;font-weight:700;color:#1e293b;"><i class="fas fa-bolt mr-2" style="color:#0ea5e9;"></i>Accesos rápidos</h6></div>
    <?php
    $accesos = [
        ['href'=>'movimientos_caja.php',                          'bg'=>'linear-gradient(135deg,#0369a1,#0ea5e9)', 'icon'=>'fas fa-list-ul',    'title'=>'Ver movimientos',  'sub'=>'Tabla completa'],
        ['href'=>'#','modal'=>'modalMovManual',                   'bg'=>'linear-gradient(135deg,#047857,#10b981)', 'icon'=>'fas fa-plus',       'title'=>'Nuevo movimiento', 'sub'=>'Ingreso o egreso manual'],
        ['href'=>'cierre_caja.php?id_caja='.$id_caja,            'bg'=>'linear-gradient(135deg,#b91c1c,#ef4444)', 'icon'=>'fas fa-lock',       'title'=>'Cerrar caja',      'sub'=>'Cuadre y cierre'],
        ['href'=>'historial_caja.php',                            'bg'=>'linear-gradient(135deg,#0c1a3a,#0f3460)', 'icon'=>'fas fa-history',    'title'=>'Historial',        'sub'=>'Cajas anteriores'],
    ];
    foreach ($accesos as $a): ?>
    <div class="col-md-3 col-6 mb-3">
        <a href="<?= $a['href'] ?>" <?= isset($a['modal'])?"data-toggle='modal' data-target='#{$a['modal']}'" :'' ?> style="text-decoration:none;">
            <div style="background:#fff;border-radius:13px;padding:18px;text-align:center;box-shadow:0 2px 14px rgba(0,0,0,.07);border:1px solid #e2e8f0;transition:all .2s;" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.12)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 14px rgba(0,0,0,.07)'">
                <div style="width:46px;height:46px;background:<?= $a['bg'] ?>;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;box-shadow:0 4px 12px rgba(0,0,0,.15);"><i class="<?= $a['icon'] ?>" style="color:#fff;font-size:1rem;"></i></div>
                <div style="font-size:.83rem;font-weight:700;color:#1e293b;"><?= $a['title'] ?></div>
                <div style="font-size:.7rem;color:#94a3b8;margin-top:2px;"><?= $a['sub'] ?></div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

</div></div></div>

<!-- MODAL: Nuevo movimiento manual -->
<div class="modal fade" id="modalMovManual" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">
            <div class="cx-modal-hdr">
                <h5 class="m-0 font-weight-bold"><i class="fas fa-exchange-alt mr-2"></i>Nuevo Movimiento Manual</h5>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;text-shadow:none;font-size:1.4rem;"><span>&times;</span></button>
            </div>
            <form method="POST" action="caja.php">
                <input type="hidden" name="accion" value="movimiento_manual">
                <input type="hidden" name="id_caja" value="<?= $id_caja ?>">
                <input type="hidden" name="tipo" id="hiddenTipoMov" value="">
                <input type="hidden" name="metodo_pago" id="hiddenMetodoMov" value="efectivo">
                <div class="modal-body" style="padding:22px;">
                    <div class="form-group mb-3">
                        <label class="cx-form-label">Tipo de movimiento <span class="text-danger">*</span></label>
                        <div class="cx-tipo-grid">
                            <button type="button" class="cx-btn-tipo" data-tipo="ingreso"><i class="fas fa-arrow-circle-up mr-1"></i>Ingreso</button>
                            <button type="button" class="cx-btn-tipo" data-tipo="egreso"><i class="fas fa-arrow-circle-down mr-1"></i>Egreso</button>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="cx-form-label">Descripción <span class="text-danger">*</span></label>
                        <input type="text" name="descripcion" class="form-control" placeholder="Ej: Pago de luz, Venta extra..." maxlength="150" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="cx-form-label">Método de pago <span class="text-danger">*</span></label>
                        <div class="cx-metodo-btns">
                            <button type="button" class="cx-btn-metodo activo" data-metodo="efectivo">Efectivo</button>
                            <button type="button" class="cx-btn-metodo" data-metodo="yape">Yape</button>
                            <button type="button" class="cx-btn-metodo" data-metodo="transferencia">Transferencia</button>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="cx-form-label">Monto (S/.) <span class="text-danger">*</span></label>
                        <div class="cx-input-group">
                            <span class="cx-input-prefix">S/.</span>
                            <input type="number" name="monto" class="form-control cx-input" step="0.01" min="0.01" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="cx-form-label">Observación <small class="text-muted">(opcional)</small></label>
                        <input type="text" name="observacion" class="form-control" placeholder="Detalle adicional..." maxlength="200">
                    </div>
                </div>
                <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:13px 22px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
                    <button type="submit" class="cx-btn-guardar"><i class="fas fa-save mr-1"></i>Registrar movimiento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Datos para los gráficos — inyectados desde PHP, consumidos por resumen_caja.js -->
<script>
window.RC = {
    horasLabels:  <?= json_encode($horas_labels  ?: []) ?>,
    horasIng:     <?= json_encode($horas_ing     ?: []) ?>,
    horasEg:      <?= json_encode($horas_eg      ?: []) ?>,
    hayDatosHora: <?= $hay_datos_hora ? 'true' : 'false' ?>,
    donaLabels:   <?= json_encode($dona_labels   ?: ['Sin datos']) ?>,
    donaData:     <?= json_encode($dona_data     ?: [1]) ?>,
    donaColors:   <?= json_encode($dona_colors   ?: ['#e2e8f0']) ?>,
    donaEgLabels: <?= json_encode($dona_eg_labels ?: ['Sin datos']) ?>,
    donaEgData:   <?= json_encode($dona_eg_data  ?: [1]) ?>,
    donaEgColors: <?= json_encode($dona_eg_colors ?: ['#e2e8f0']) ?>,
    barLabels:    <?= json_encode($bar_labels    ?: []) ?>,
    barIng:       <?= json_encode($bar_ing       ?: []) ?>,
    barEg:        <?= json_encode($bar_eg        ?: []) ?>,
    barColors:    <?= json_encode($bar_colors    ?: []) ?>
};
</script>

<?php include $ruta_base . 'includes/footer.php'; ?>
