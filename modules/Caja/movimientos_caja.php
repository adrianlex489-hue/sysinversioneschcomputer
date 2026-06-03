<?php
// modules/Caja/movimientos_caja.php | SysInversiones CH Computer 2026
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

$movimientos = [];
try {
    $st = $pdo->prepare("SELECT m.*, u.nombre_completo AS cajero FROM movimientos_caja m LEFT JOIN usuarios u ON u.id_usuario=m.id_usuario WHERE m.id_caja=? ORDER BY m.fecha DESC");
    $st->execute([$caja_activa['id_caja']]);
    $movimientos = $st->fetchAll();
} catch (PDOException $e) {}

$total_ing = array_sum(array_column(array_filter($movimientos, fn($m) => $m['tipo']==='ingreso'), 'monto'));
$total_eg  = array_sum(array_column(array_filter($movimientos, fn($m) => $m['tipo']==='egreso'),  'monto'));
$neto      = $total_ing - $total_eg;

$extra_css = '<link rel="stylesheet" href="/sysinversioneschcomputer/modules/Caja/css/caja.css?v=' . time() . '">';
$extra_js  = '<script src="/sysinversioneschcomputer/modules/Caja/js/movimientos_caja.js?v=' . time() . '"><\/script>';
include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<div class="content-wrapper">
<div class="content-header"><div class="container-fluid">
<div class="cx-page-header d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h4><i class="fas fa-list-ul mr-2"></i>Movimientos de Caja</h4>
        <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones &rsaquo; Caja &rsaquo; <?= htmlspecialchars($caja_activa['nombre']) ?></small>
    </div>
    <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
        <a href="caja.php" class="cx-btn cx-btn-ghost"><i class="fas fa-arrow-left mr-1"></i>Volver</a>
        <a href="resumen_caja.php" class="cx-btn cx-btn-ghost"><i class="fas fa-chart-bar mr-1"></i>Resumen</a>
        <button class="cx-btn cx-btn-success" data-toggle="modal" data-target="#modalMovManual"><i class="fas fa-plus mr-1"></i>Nuevo movimiento</button>
        <a href="cierre_caja.php?id_caja=<?= $caja_activa['id_caja'] ?>" class="cx-btn cx-btn-danger"><i class="fas fa-lock mr-1"></i>Cerrar Caja</a>
    </div>
</div>
</div></div>
<div class="content"><div class="container-fluid">

<!-- Mini KPIs -->
<div class="row mb-4">
    <?php
    $mkpis = [
        ['sc'=>'#0ea5e9','bg'=>'linear-gradient(135deg,#0369a1,#0ea5e9)','icon'=>'fas fa-store',      'lbl'=>'Caja activa',    'val'=>htmlspecialchars($caja_activa['nombre']),  'color'=>'#0f172a'],
        ['sc'=>'#10b981','bg'=>'linear-gradient(135deg,#047857,#10b981)','icon'=>'fas fa-arrow-up',   'lbl'=>'Total ingresos', 'val'=>'S/. '.number_format($total_ing,2),        'color'=>'#047857'],
        ['sc'=>'#ef4444','bg'=>'linear-gradient(135deg,#b91c1c,#ef4444)','icon'=>'fas fa-arrow-down', 'lbl'=>'Total egresos',  'val'=>'S/. '.number_format($total_eg,2),         'color'=>'#b91c1c'],
        ['sc'=>$neto>=0?'#0ea5e9':'#f97316','bg'=>$neto>=0?'linear-gradient(135deg,#0369a1,#0ea5e9)':'linear-gradient(135deg,#c2410c,#f97316)','icon'=>'fas fa-coins','lbl'=>'Neto del día','val'=>($neto>=0?'+':'').'S/. '.number_format($neto,2),'color'=>$neto>=0?'#0369a1':'#c2410c'],
    ];
    foreach ($mkpis as $k): ?>
    <div class="col-md-3 col-6 mb-3">
        <div class="cx-stat-card" style="--sc:<?= $k['sc'] ?>;">
            <div class="cx-stat-icon" style="background:<?= $k['bg'] ?>;"><i class="<?= $k['icon'] ?>"></i></div>
            <div>
                <div class="cx-stat-lbl"><?= $k['lbl'] ?></div>
                <div class="cx-stat-val" style="color:<?= $k['color'] ?>;font-size:<?= $k['lbl']==='Caja activa'?'.88rem':'1.2rem' ?>;"><?= $k['val'] ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Tabla -->
<div class="cx-tabla-wrap mb-4">
    <div class="cx-tabla-hdr">
        <span><i class="fas fa-exchange-alt mr-2"></i>Todos los movimientos — <?= htmlspecialchars($caja_activa['nombre']) ?></span>
        <span style="background:rgba(255,255,255,.12);color:#fff;border-radius:20px;padding:2px 12px;font-size:.75rem;font-weight:700;"><?= count($movimientos) ?> registros</span>
    </div>
    <div class="table-responsive">
        <table id="tblMovsCaja" class="table table-cx table-bordered table-hover table-sm mb-0">
            <thead><tr>
                <th>Fecha/Hora</th><th>Tipo</th><th>Descripción</th>
                <th>Origen</th><th>Método</th><th class="text-right">Monto</th><th>Registrado por</th>
                <th class="text-center" style="width:60px;">Detalle</th>
            </tr></thead>
            <tbody>
            <?php
            $ref_labels = ['venta'=>'Venta','compra'=>'Compra','servicio'=>'Servicio','manual'=>'Manual'];
            $ref_class  = ['venta'=>'cx-ref-venta','compra'=>'cx-ref-compra','servicio'=>'cx-ref-servicio','manual'=>'cx-ref-manual'];
            $metodo_icons = ['efectivo'=>'fa-money-bill-wave','yape'=>'fa-mobile-alt','transferencia'=>'fa-university'];
            foreach ($movimientos as $m):
                $signo = $m['tipo']==='ingreso' ? '+' : '−';
                $color = $m['tipo']==='ingreso' ? '#059669' : '#dc2626';
            ?>
            <tr>
                <td><small class="text-muted"><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></small></td>
                <td><span class="cx-badge-<?= $m['tipo'] === 'ingreso' ? 'ing' : 'eg' ?>"><?= strtoupper($m['tipo']) ?></span></td>
                <td>
                    <div style="font-weight:600;font-size:.83rem;"><?= htmlspecialchars($m['descripcion']) ?></div>
                    <?php if ($m['observacion']): ?><small class="text-muted"><?= htmlspecialchars($m['observacion']) ?></small><?php endif; ?>
                </td>
                <td>
                    <span class="<?= $ref_class[$m['tipo_referencia']] ?? 'cx-ref-manual' ?>">
                        <?= $ref_labels[$m['tipo_referencia']] ?? $m['tipo_referencia'] ?>
                        <?= $m['id_referencia'] ? ' #'.$m['id_referencia'] : '' ?>
                    </span>
                </td>
                <td>
                    <i class="fas <?= $metodo_icons[$m['metodo_pago']] ?? 'fa-circle' ?> mr-1" style="color:#64748b;"></i>
                    <span style="font-size:.8rem;font-weight:600;text-transform:capitalize;"><?= htmlspecialchars($m['metodo_pago']) ?></span>
                </td>
                <td class="text-right" style="font-family:monospace;font-weight:800;color:<?= $color ?>;">
                    <?= $signo ?> S/. <?= number_format($m['monto'],2) ?>
                </td>
                <td><small><?= htmlspecialchars($m['cajero'] ?? '—') ?></small></td>
                <td class="text-center">
                    <button class="btn-detalle-mov"
                        data-id="<?= $m['id_movimiento'] ?>"
                        data-ref="<?= $m['tipo_referencia'] ?>"
                        data-refid="<?= $m['id_referencia'] ?>"
                        data-tipo="<?= $m['tipo'] ?>"
                        title="Ver detalle">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($movimientos)): ?>
            <tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>No hay movimientos registrados aún</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
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
                <input type="hidden" name="id_caja" value="<?= $caja_activa['id_caja'] ?>">
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

<!-- El JS se carga desde footer.php vía $extra_js -->

<!-- ══════════════════════════════════════════════════════════
     MODAL: DETALLE DE MOVIMIENTO
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDetalleMov" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="max-width:720px;">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 25px 70px rgba(0,0,0,.22);">
            <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:18px 24px;display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <h5 class="m-0 font-weight-bold text-white"><i class="fas fa-search-dollar mr-2" style="color:#38bdf8;"></i>Detalle del Movimiento</h5>
                    <small style="color:#94a3b8;">Información completa del registro de caja</small>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.7;text-shadow:none;font-size:1.5rem;"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0" id="movDetalle-body" style="min-height:200px;max-height:75vh;overflow-y:auto;">
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color:#0ea5e9;opacity:.6;"></i>
                    <p class="mt-3 text-muted" style="font-size:.88rem;">Cargando información...</p>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:12px 20px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* ── Botón ojo ─────────────────────────────────────────────────────────────── */
.btn-detalle-mov {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    background: linear-gradient(135deg, #0369a1, #0ea5e9);
    color: #fff;
    font-size: .8rem;
    cursor: pointer;
    transition: transform .15s, box-shadow .15s, opacity .15s;
    box-shadow: 0 2px 8px rgba(14,165,233,.35);
}
.btn-detalle-mov:hover {
    transform: translateY(-2px) scale(1.08);
    box-shadow: 0 6px 18px rgba(14,165,233,.5);
    opacity: .92;
}
.btn-detalle-mov:active { transform: scale(.95); }

/* ── Contenido del modal ───────────────────────────────────────────────────── */
.mov-det-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 20px 24px 16px;
    border-bottom: 1px solid #f1f5f9;
}
.mov-det-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; color: #fff; flex-shrink: 0;
}
.mov-det-title { font-size: 1rem; font-weight: 700; color: #0f172a; margin: 0; }
.mov-det-sub   { font-size: .78rem; color: #64748b; margin: 2px 0 0; }
.mov-det-monto {
    margin-left: auto; text-align: right;
    font-size: 1.4rem; font-weight: 800; font-family: monospace;
}
.mov-det-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    padding: 0 24px 20px;
}
.mov-det-field {
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}
.mov-det-field:nth-child(odd)  { padding-right: 20px; border-right: 1px solid #f1f5f9; }
.mov-det-field:nth-child(even) { padding-left: 20px; }
.mov-det-field-lbl {
    font-size: .7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: #94a3b8; margin-bottom: 3px;
}
.mov-det-field-val {
    font-size: .88rem; font-weight: 600; color: #1e293b;
}
.mov-det-section {
    margin: 0 24px 20px;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}
.mov-det-section-hdr {
    background: #1e293b;
    color: #fff;
    padding: 8px 14px;
    font-size: .75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.mov-det-section-body { padding: 12px 14px; }
.mov-det-obs {
    margin: 0 24px 20px;
    background: #fffbeb;
    border-left: 4px solid #f59e0b;
    border-radius: 0 8px 8px 0;
    padding: 10px 14px;
    font-size: .84rem;
    color: #78350f;
}
</style>

<?php include $ruta_base . 'includes/footer.php'; ?>
