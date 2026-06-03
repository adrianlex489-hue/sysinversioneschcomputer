<?php
// modules/Caja/historial_caja.php | SysInversiones CH Computer 2026
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';
if (!isset($pdo)) die('Sin BD');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR_COMERCIAL'))        define('ROL_ASESOR_COMERCIAL', 2);
if (!defined('ROL_TECNICO'))       define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);
verificarPermiso($pdo, 'historial_caja');

$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
$id_rol     = (int)($_SESSION['id_rol']     ?? 0);
$es_admin   = ($id_rol === ROL_ADMINISTRADOR);

$swal = null;
if (isset($_SESSION['swal_caja'])) { $swal = $_SESSION['swal_caja']; unset($_SESSION['swal_caja']); }

$cajas = [];
$stats = ['total'=>0,'recaudado'=>0,'mes'=>0,'con_diferencia'=>0,'abiertas'=>0];
try {
    $where = $es_admin ? '' : 'WHERE c.id_usuario = ' . $id_usuario;
    $cajas = $pdo->query("
        SELECT c.*, u.nombre_completo,
               (SELECT COALESCE(SUM(m.monto),0) FROM movimientos_caja m WHERE m.id_caja=c.id_caja AND m.tipo='ingreso') AS total_ingresos,
               (SELECT COALESCE(SUM(m.monto),0) FROM movimientos_caja m WHERE m.id_caja=c.id_caja AND m.tipo='egreso')  AS total_egresos,
               (SELECT COUNT(*) FROM movimientos_caja m WHERE m.id_caja=c.id_caja) AS total_movimientos
        FROM caja c JOIN usuarios u ON u.id_usuario=c.id_usuario $where
        ORDER BY c.fecha_apertura DESC
    ")->fetchAll();
    $cerradas = array_filter($cajas, fn($c) => $c['estado']==='cerrada');
    $stats['total']          = count($cajas);
    $stats['abiertas']       = count(array_filter($cajas, fn($c) => $c['estado']==='abierta'));
    $stats['recaudado']      = array_sum(array_column(array_values($cerradas), 'monto_final'));
    $stats['mes']            = count(array_filter($cajas, fn($c) => date('Y-m', strtotime($c['fecha_apertura'])) === date('Y-m')));
    $stats['con_diferencia'] = count(array_filter($cerradas, fn($c) => abs((float)$c['diferencia']) > 0.01));
} catch (PDOException $e) {}

$extra_css = '<link rel="stylesheet" href="/sysinversioneschcomputer/modules/Caja/css/caja.css?v=' . time() . '">';
$extra_js  = '<script src="/sysinversioneschcomputer/modules/Caja/js/historial_caja.js?v=' . time() . '"></script>';
include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<div class="content-wrapper">
<div class="content-header"><div class="container-fluid">
<div class="cx-hist-hdr d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h4><i class="fas fa-history mr-2"></i>Historial de Cajas</h4>
        <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones &rsaquo; Caja &rsaquo; Historial</small>
    </div>
    <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
        <a href="caja.php" class="cx-btn cx-btn-ghost"><i class="fas fa-cash-register mr-1"></i>Ir a Caja</a>
        <a href="resumen_caja.php" class="cx-btn cx-btn-ghost"><i class="fas fa-chart-bar mr-1"></i>Resumen</a>
    </div>
</div>
</div></div>
<div class="content"><div class="container-fluid">

<?php if ($swal): ?>
<script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({icon:'<?= $swal['icon'] ?>',title:'<?= addslashes($swal['title']) ?>',text:'<?= addslashes($swal['text'] ?? '') ?>',confirmButtonColor:'#0ea5e9',timer:<?= in_array($swal['icon'],['success','info'])?3500:0 ?>,timerProgressBar:<?= in_array($swal['icon'],['success','info'])?'true':'false' ?>,showConfirmButton:<?= in_array($swal['icon'],['success','info'])?'false':'true' ?>});});</script>
<?php endif; ?>

<!-- Stats -->
<div class="row mb-4">
    <?php
    $hstats = [
        ['bg'=>'linear-gradient(135deg,#0c1a3a,#0f3460)',  'icon'=>'fas fa-cash-register',      'val'=>$stats['total'],                                'lbl'=>'Total cajas'],
        ['bg'=>'linear-gradient(135deg,#047857,#10b981)',  'icon'=>'fas fa-money-bill-wave',     'val'=>'S/. '.number_format($stats['recaudado'],0),    'lbl'=>'Total recaudado'],
        ['bg'=>'linear-gradient(135deg,#0369a1,#0ea5e9)',  'icon'=>'fas fa-calendar-alt',        'val'=>$stats['mes'],                                  'lbl'=>'Cajas este mes'],
        ['bg'=>'linear-gradient(135deg,#c2410c,#f97316)',  'icon'=>'fas fa-exclamation-triangle', 'val'=>$stats['con_diferencia'],                      'lbl'=>'Con diferencia'],
    ];
    foreach ($hstats as $s): ?>
    <div class="col-6 col-md-3 mb-3">
        <div class="cx-hist-stat" style="background:<?= $s['bg'] ?>;">
            <i class="<?= $s['icon'] ?>"></i>
            <div>
                <div class="cx-hist-stat-val"><?= $s['val'] ?></div>
                <div class="cx-hist-stat-lbl"><?= $s['lbl'] ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filtros -->
<div class="cx-filtros mb-4">
    <span class="cx-filtros-lbl"><i class="fas fa-filter mr-1"></i>Filtrar:</span>
    <button class="cx-btn-filtro active" data-filtro="todos">Todas</button>
    <button class="cx-btn-filtro" data-filtro="hoy">Hoy</button>
    <button class="cx-btn-filtro" data-filtro="semana">Esta semana</button>
    <button class="cx-btn-filtro" data-filtro="mes">Este mes</button>
    <button class="cx-btn-filtro" data-filtro="abierta"><i class="fas fa-circle mr-1" style="font-size:.5rem;color:#10b981;"></i>Abiertas</button>
    <button class="cx-btn-filtro" data-filtro="cerrada"><i class="fas fa-lock mr-1"></i>Cerradas</button>
</div>

<!-- Tabla -->
<div class="cx-tabla-wrap mb-4">
    <div class="cx-hist-tabla-hdr">
        <span><i class="fas fa-list-ul mr-2"></i>Registro de cajas</span>
        <span style="background:rgba(255,255,255,.12);color:#fff;border-radius:20px;padding:2px 12px;font-size:.75rem;font-weight:700;"><?= count($cajas) ?> registros</span>
    </div>
    <div class="table-responsive">
        <table id="tablaHistCajas" class="table table-cx table-bordered table-hover table-sm mb-0">
            <thead><tr>
                <th class="text-center">#</th><th>Cajero</th><th>Nombre de caja</th>
                <th>Apertura</th><th>Cierre</th>
                <th class="text-right">Fondo</th><th class="text-right">Ingresos</th>
                <th class="text-right">Egresos</th><th class="text-right">Monto final</th>
                <th class="text-center">Diferencia</th><th class="text-center">Estado</th>
                <th class="text-center" style="width:90px;">Acciones</th>
            </tr></thead>
            <tbody>
            <?php foreach ($cajas as $c):
                $dif = (float)($c['diferencia'] ?? 0);
                $difClass = abs($dif)<0.01 ? 'cx-dif-ok' : ($dif>0 ? 'cx-dif-pos' : 'cx-dif-neg');
                $difTxt   = abs($dif)<0.01 ? '<i class="fas fa-check-circle mr-1"></i>Exacto' : ($dif>0 ? '+S/. '.number_format($dif,2) : '−S/. '.number_format(abs($dif),2));
            ?>
            <tr class="cx-hist-fila" data-estado="<?= $c['estado'] ?>" data-fecha="<?= date('Y-m-d', strtotime($c['fecha_apertura'])) ?>">
                <td class="text-center"><span class="cx-hist-num"><?= $c['id_caja'] ?></span></td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#0c1a3a,#0ea5e9);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-user-tie" style="color:#fff;font-size:.7rem;"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.83rem;color:#1e293b;"><?= htmlspecialchars($c['nombre_completo']) ?></div>
                            <div style="font-size:.7rem;color:#94a3b8;"><?= $c['total_movimientos'] ?> movimientos</div>
                        </div>
                    </div>
                </td>
                <td><span class="cx-hist-nombre"><?= htmlspecialchars($c['nombre'] ?? 'Caja #'.$c['id_caja']) ?></span></td>
                <td>
                    <div style="font-size:.82rem;font-weight:600;color:#1e293b;"><?= date('d/m/Y', strtotime($c['fecha_apertura'])) ?></div>
                    <div style="font-size:.7rem;color:#94a3b8;"><?= date('H:i', strtotime($c['fecha_apertura'])) ?></div>
                </td>
                <td>
                    <?php if ($c['fecha_cierre']): ?>
                    <div style="font-size:.82rem;font-weight:600;color:#1e293b;"><?= date('d/m/Y', strtotime($c['fecha_cierre'])) ?></div>
                    <div style="font-size:.7rem;color:#94a3b8;"><?= date('H:i', strtotime($c['fecha_cierre'])) ?></div>
                    <?php else: ?><span style="color:#94a3b8;font-size:.8rem;">—</span><?php endif; ?>
                </td>
                <td class="text-right"><span style="font-family:monospace;font-weight:700;color:#0369a1;font-size:.83rem;">S/. <?= number_format($c['monto_inicial'],2) ?></span></td>
                <td class="text-right"><span style="font-family:monospace;font-weight:700;color:#059669;font-size:.83rem;">+S/. <?= number_format($c['total_ingresos'],2) ?></span></td>
                <td class="text-right"><span style="font-family:monospace;font-weight:700;color:#dc2626;font-size:.83rem;">−S/. <?= number_format($c['total_egresos'],2) ?></span></td>
                <td class="text-right">
                    <?php if ($c['monto_final'] !== null): ?>
                    <span style="font-family:monospace;font-weight:800;color:#0f172a;font-size:.85rem;">S/. <?= number_format($c['monto_final'],2) ?></span>
                    <?php else: ?><span style="color:#94a3b8;">—</span><?php endif; ?>
                </td>
                <td class="text-center"><span class="<?= $difClass ?>"><?= $difTxt ?></span></td>
                <td class="text-center">
                    <?php if ($c['estado']==='abierta'): ?>
                    <span class="cx-badge-abierta"><i class="fas fa-circle" style="font-size:.45rem;"></i>Abierta</span>
                    <?php else: ?>
                    <span class="cx-badge-cerrada"><i class="fas fa-lock" style="font-size:.7rem;"></i>Cerrada</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <button class="btn btn-sm cx-btn-ver" data-id="<?= $c['id_caja'] ?>" title="Ver detalle"
                        style="background:linear-gradient(135deg,#0369a1,#0ea5e9);color:#fff;border:none;border-radius:7px;padding:5px 9px;">
                        <i class="fas fa-eye"></i>
                    </button>
                    <?php if ($c['estado']==='abierta' && ($es_admin || $c['id_usuario']==$id_usuario)): ?>
                    <a href="cierre_caja.php?id_caja=<?= $c['id_caja'] ?>"
                        style="display:inline-block;margin-left:3px;background:linear-gradient(135deg,#b91c1c,#ef4444);color:#fff;border-radius:7px;padding:5px 9px;text-decoration:none;" title="Cerrar caja">
                        <i class="fas fa-lock"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($cajas)): ?>
            <tr><td colspan="12" class="text-center py-5 text-muted"><i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>No hay cajas registradas</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div></div></div>

<!-- Modal detalle caja -->
<div class="modal fade" id="modalDetalleCaja" tabindex="-1">
    <div class="modal-dialog modal-xl" style="max-width:920px;">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 24px 60px rgba(0,0,0,.2);">
            <div class="cx-modal-hist-hdr d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="m-0 font-weight-bold"><i class="fas fa-cash-register mr-2"></i>Detalle de Caja</h5>
                    <small style="opacity:.7;font-size:.76rem;">Información completa del período</small>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;text-shadow:none;font-size:1.4rem;"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0" id="detalleCajaBody">
                <div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted mb-2 d-block" style="opacity:.4;"></i><span class="text-muted" style="font-size:.83rem;">Cargando...</span></div>
            </div>
            <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:11px 18px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php include $ruta_base . 'includes/footer.php'; ?>
