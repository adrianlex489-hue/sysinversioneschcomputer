<?php
// ============================================================
// modules/caja/caja.php | Botica 2026
// Gestión de Caja por Turnos
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexión BD no disponible.');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_CAJERO'))        define('ROL_CAJERO', 2);
if (!defined('ROL_TRABAJADOR'))    define('ROL_TRABAJADOR', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_CAJERO, ROL_TRABAJADOR]);
verificarPermiso($pdo, 'caja');

$id_usuario = $_SESSION['id_usuario'] ?? 0;
$id_rol     = $_SESSION['id_rol']     ?? 0;
$es_admin   = ($id_rol == ROL_ADMINISTRADOR);

$swal = null;
if (isset($_SESSION['swal_caja'])) { $swal = $_SESSION['swal_caja']; unset($_SESSION['swal_caja']); }

function redirigirCaja(string $icon, string $title, string $text): void {
    $_SESSION['swal_caja'] = compact('icon', 'title', 'text');
    header('Location: caja.php'); exit;
}

// ── AJAX: detalle de caja ─────────────────────────────────────────────────────
if (isset($_GET['accion']) && $_GET['accion'] === 'detalle_ajax') {
    $id = (int)($_GET['id_caja'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT c.*, u.nombre_completo FROM caja c JOIN usuarios u ON c.id_usuario=u.id_usuario WHERE c.id_caja=?");
        $stmt->execute([$id]); $cab = $stmt->fetch();
        if (!$cab) { echo '<div class="alert alert-warning">Caja no encontrada.</div>'; exit; }

        $movs = $pdo->prepare("SELECT m.*, u.nombre_completo FROM movimientos_caja m JOIN usuarios u ON m.id_usuario=u.id_usuario WHERE m.id_caja=? ORDER BY m.fecha DESC");
        $movs->execute([$id]); $movimientos = $movs->fetchAll();

        $turnoMap = ['mañana'=>'badge-turno-manana','tarde'=>'badge-turno-tarde','noche'=>'badge-turno-noche'];
        $turnoLabel = ['mañana'=>'☀️ Mañana','tarde'=>'🌤️ Tarde','noche'=>'🌙 Noche'];

        echo '<div class="row mb-3">';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;">Cajero</small><strong>'.htmlspecialchars($cab['nombre_completo']).'</strong></div>';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;">Turno</small><span class="'.($turnoMap[$cab['turno']]??'').'">'.($turnoLabel[$cab['turno']]??$cab['turno']).'</span></div>';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;">Estado</small>';
        echo $cab['estado']==='abierta' ? '<span class="badge-caja-abierta" style="background:#d4edda;color:#155724;animation:none;">🟢 Abierta</span>' : '<span class="badge-caja-cerrada">🔴 Cerrada</span>';
        echo '</div></div>';
        echo '<div class="row mb-3 pt-2" style="border-top:1px solid #f0f0f0;">';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;">Apertura</small>'.date('d/m/Y H:i',strtotime($cab['fecha_apertura'])).'</div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;">Cierre</small>'.($cab['fecha_cierre']?date('d/m/Y H:i',strtotime($cab['fecha_cierre'])):'—').'</div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;">Monto Inicial</small><strong style="color:#1a7a4a;">S/. '.number_format($cab['monto_inicial'],2).'</strong></div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;">Monto Final</small><strong style="color:#1a7a4a;">'.($cab['monto_final']!==null?'S/. '.number_format($cab['monto_final'],2):'—').'</strong></div>';
        echo '</div>';

        if ($cab['monto_esperado'] !== null) {
            $dif = (float)$cab['diferencia'];
            $difClass = $dif > 0 ? 'diferencia-positiva' : ($dif < 0 ? 'diferencia-negativa' : 'diferencia-cero');
            $difTxt   = $dif > 0 ? '▲ Sobrante S/. '.number_format($dif,2) : ($dif < 0 ? '▼ Faltante S/. '.number_format(abs($dif),2) : '✓ Cuadre exacto');
            echo '<div class="row mb-3 pt-2" style="border-top:1px solid #f0f0f0;">';
            echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;">Monto Esperado</small><strong>S/. '.number_format($cab['monto_esperado'],2).'</strong></div>';
            echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;">Diferencia</small><span class="'.$difClass.'">'.$difTxt.'</span></div>';
            if ($cab['observacion']) echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;">Observación</small>'.htmlspecialchars($cab['observacion']).'</div>';
            echo '</div>';
        }

        echo '<h6 class="font-weight-bold text-muted mb-2" style="font-size:.82rem;"><i class="fas fa-exchange-alt mr-1"></i>MOVIMIENTOS ('.count($movimientos).')</h6>';
        if (empty($movimientos)) {
            echo '<div class="text-center text-muted py-3"><i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>Sin movimientos registrados</div>';
        } else {
            echo '<div class="table-responsive"><table class="table table-sm table-bordered" style="font-size:.82rem;">';
            echo '<thead style="background:#1a7a4a;color:#fff;"><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Ref.</th><th>Método</th><th class="text-right">Monto</th></tr></thead><tbody>';
            foreach ($movimientos as $m) {
                $refMap = ['venta'=>'badge-ref-venta','compra'=>'badge-ref-compra','manual'=>'badge-ref-manual'];
                $refLbl = ['venta'=>'Venta','compra'=>'Compra','manual'=>'Manual'];
                $refBadge = '<span class="'.($refMap[$m['tipo_referencia']]??'').'">'.($refLbl[$m['tipo_referencia']]??'').($m['id_referencia']?' #'.$m['id_referencia']:'').'</span>';
                $color = $m['tipo']==='ingreso' ? '#155724' : '#721c24';
                $signo = $m['tipo']==='ingreso' ? '+' : '-';
                echo '<tr>';
                echo '<td>'.date('d/m/Y H:i',strtotime($m['fecha'])).'</td>';
                echo '<td><span class="badge-'.($m['tipo']==='ingreso'?'ingreso':'egreso').'">'.strtoupper($m['tipo']).'</span></td>';
                echo '<td>'.htmlspecialchars($m['descripcion']).'</td>';
                echo '<td>'.$refBadge.'</td>';
                echo '<td>'.strtoupper($m['metodo_pago']).'</td>';
                echo '<td class="text-right font-weight-bold" style="color:'.$color.';font-family:monospace;">'.$signo.' S/. '.number_format($m['monto'],2).'</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Error: '.htmlspecialchars($e->getMessage()).'</div>';
    }
    exit;
}

// ── CRUD POST ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // APERTURA DE CAJA
    if ($accion === 'aperturar') {
        $turno         = $_POST['turno']         ?? '';
        $monto_inicial = (float)($_POST['monto_inicial'] ?? 0);
        $observacion   = trim($_POST['observacion'] ?? '') ?: null;

        if (!in_array($turno, ['mañana','tarde','noche'])) redirigirCaja('warning','Turno inválido','Selecciona un turno válido.');
        if ($monto_inicial < 0) redirigirCaja('warning','Monto inválido','El monto inicial no puede ser negativo.');

        try {
            // Verificar que no haya caja abierta para este usuario
            $stCheck = $pdo->prepare("SELECT id_caja FROM caja WHERE id_usuario=? AND estado='abierta' LIMIT 1");
            $stCheck->execute([$id_usuario]);
            if ($stCheck->fetchColumn()) redirigirCaja('warning','Caja ya abierta','Ya tienes una caja abierta. Ciérrala antes de aperturar una nueva.');

            $pdo->prepare("INSERT INTO caja (id_usuario,turno,monto_inicial,estado,observacion) VALUES (?,?,?,'abierta',?)")
                ->execute([$id_usuario,$turno,$monto_inicial,$observacion]);
            redirigirCaja('success','¡Caja aperturada!','La caja del turno '.strtoupper($turno).' fue aperturada con S/. '.number_format($monto_inicial,2).' de fondo inicial.');
        } catch (PDOException $e) {
            redirigirCaja('error','Error','No se pudo aperturar la caja: '.$e->getMessage());
        }

    // MOVIMIENTO MANUAL
    } elseif ($accion === 'movimiento_manual') {
        $id_caja     = (int)($_POST['id_caja']      ?? 0);
        $tipo        = $_POST['tipo']               ?? '';
        $descripcion = trim($_POST['descripcion']   ?? '');
        $monto       = (float)($_POST['monto']      ?? 0);
        $metodo_pago = $_POST['metodo_pago']        ?? 'efectivo';
        $observacion = trim($_POST['observacion']   ?? '') ?: null;

        if (!$id_caja)    redirigirCaja('warning','Error','Caja no identificada.');
        if (!in_array($tipo,['ingreso','egreso'])) redirigirCaja('warning','Tipo inválido','Selecciona ingreso o egreso.');
        if (empty($descripcion)) redirigirCaja('warning','Descripción requerida','Ingresa una descripción.');
        if ($monto <= 0)  redirigirCaja('warning','Monto inválido','El monto debe ser mayor a 0.');

        try {
            // Verificar que la caja esté abierta y pertenezca al usuario (o sea admin)
            $stC = $pdo->prepare("SELECT estado,id_usuario FROM caja WHERE id_caja=?");
            $stC->execute([$id_caja]); $caja = $stC->fetch();
            if (!$caja || $caja['estado'] !== 'abierta') redirigirCaja('warning','Caja cerrada','La caja ya no está abierta.');
            if (!$es_admin && $caja['id_usuario'] != $id_usuario) redirigirCaja('error','Sin permiso','No puedes registrar movimientos en esta caja.');

            $pdo->prepare("INSERT INTO movimientos_caja (id_caja,tipo_referencia,id_referencia,id_usuario,tipo,descripcion,monto,metodo_pago) VALUES (?,'manual',NULL,?,?,?,?,?)")
                ->execute([$id_caja,$id_usuario,$tipo,$descripcion,$monto,$metodo_pago]);
            redirigirCaja('success','Movimiento registrado','Se registró un '.strtoupper($tipo).' de S/. '.number_format($monto,2).'.');
        } catch (PDOException $e) {
            redirigirCaja('error','Error',$e->getMessage());
        }

    // CIERRE DE CAJA
    } elseif ($accion === 'cerrar') {
        $id_caja     = (int)($_POST['id_caja']      ?? 0);
        $monto_final = (float)($_POST['monto_final'] ?? 0);
        $observacion = trim($_POST['observacion']   ?? '') ?: null;

        if (!$id_caja) redirigirCaja('warning','Error','Caja no identificada.');
        if ($monto_final < 0) redirigirCaja('warning','Monto inválido','El monto final no puede ser negativo.');

        try {
            $stC = $pdo->prepare("SELECT * FROM caja WHERE id_caja=?");
            $stC->execute([$id_caja]); $caja = $stC->fetch();
            if (!$caja || $caja['estado'] !== 'abierta') redirigirCaja('warning','Caja ya cerrada','Esta caja ya fue cerrada.');
            if (!$es_admin && $caja['id_usuario'] != $id_usuario) redirigirCaja('error','Sin permiso','No puedes cerrar esta caja.');

            // Calcular monto esperado
            $stMov = $pdo->prepare("SELECT tipo, SUM(monto) AS total FROM movimientos_caja WHERE id_caja=? GROUP BY tipo");
            $stMov->execute([$id_caja]); $movTotales = $stMov->fetchAll(PDO::FETCH_KEY_PAIR);
            $total_ingresos = (float)($movTotales['ingreso'] ?? 0);
            $total_egresos  = (float)($movTotales['egreso']  ?? 0);
            $monto_esperado = round((float)$caja['monto_inicial'] + $total_ingresos - $total_egresos, 2);
            $diferencia     = round($monto_final - $monto_esperado, 2);

            $pdo->prepare("UPDATE caja SET estado='cerrada',fecha_cierre=NOW(),monto_final=?,monto_esperado=?,diferencia=?,observacion=? WHERE id_caja=?")
                ->execute([$monto_final,$monto_esperado,$diferencia,$observacion,$id_caja]);

            $msg = $diferencia == 0 ? 'Caja cerrada con cuadre exacto.' : ($diferencia > 0 ? 'Caja cerrada con sobrante de S/. '.number_format($diferencia,2).'.' : 'Caja cerrada con faltante de S/. '.number_format(abs($diferencia),2).'.');
            redirigirCaja('success','Caja cerrada',$msg);
        } catch (PDOException $e) {
            redirigirCaja('error','Error',$e->getMessage());
        }
    }
}

// ── DATOS ─────────────────────────────────────────────────────────────────────
$caja_activa = null;
$movimientos = [];
$historial   = [];
$resumen     = ['ingresos'=>0,'egresos'=>0,'saldo'=>0];
$stats       = ['cajas_hoy'=>0,'total_ingresos_hoy'=>0,'total_egresos_hoy'=>0,'cajas_mes'=>0];

try {
    // Caja activa del usuario actual
    $stCaja = $pdo->prepare("SELECT c.*, u.nombre_completo FROM caja c JOIN usuarios u ON c.id_usuario=u.id_usuario WHERE c.id_usuario=? AND c.estado='abierta' ORDER BY c.fecha_apertura DESC LIMIT 1");
    $stCaja->execute([$id_usuario]);
    $caja_activa = $stCaja->fetch();

    if ($caja_activa) {
        // Movimientos de la caja activa
        $stMov = $pdo->prepare("SELECT m.*, u.nombre_completo FROM movimientos_caja m JOIN usuarios u ON m.id_usuario=u.id_usuario WHERE m.id_caja=? ORDER BY m.fecha DESC");
        $stMov->execute([$caja_activa['id_caja']]);
        $movimientos = $stMov->fetchAll();

        // Resumen financiero
        foreach ($movimientos as $m) {
            if ($m['tipo'] === 'ingreso') $resumen['ingresos'] += (float)$m['monto'];
            else                          $resumen['egresos']  += (float)$m['monto'];
        }
        $resumen['saldo'] = round((float)$caja_activa['monto_inicial'] + $resumen['ingresos'] - $resumen['egresos'], 2);
    }

    // Stats
    $hoy = date('Y-m-d');
    $mes = date('Y-m');
    $stStats = $pdo->prepare("SELECT
        SUM(DATE(fecha_apertura)=?) AS cajas_hoy,
        SUM(DATE_FORMAT(fecha_apertura,'%Y-%m')=?) AS cajas_mes
        FROM caja WHERE id_usuario=?");
    $stStats->execute([$hoy,$mes,$id_usuario]);
    $statsRow = $stStats->fetch();
    $stats['cajas_hoy'] = (int)($statsRow['cajas_hoy'] ?? 0);
    $stats['cajas_mes'] = (int)($statsRow['cajas_mes'] ?? 0);
    $stats['total_ingresos_hoy'] = $resumen['ingresos'];
    $stats['total_egresos_hoy']  = $resumen['egresos'];

} catch (PDOException $e) {
    $swal = ['icon'=>'error','title'=>'Error','text'=>'Error al cargar datos: '.$e->getMessage()];
}

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<link rel="stylesheet" href="css/caja.css">
<div class="content-wrapper">
<div class="content-header"><div class="container-fluid">
<div class="page-header-caja d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h4><i class="fas fa-cash-register mr-2"></i>Gestión de Caja</h4>
        <small><i class="fas fa-map-marker-alt mr-1"></i>Botica 2026 &rsaquo; Caja &rsaquo; <?= $caja_activa ? 'Turno '.ucfirst($caja_activa['turno']).' activo' : 'Sin caja abierta' ?></small>
    </div>
    <?php if ($caja_activa): ?>
    <button id="btnAbrirCierre" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4);font-weight:600;">
        <i class="fas fa-lock mr-1"></i>Cerrar Caja
    </button>
    <?php endif; ?>
</div>
</div></div>
<div class="content"><div class="container-fluid">

<?php if ($swal): ?>
<script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({icon:'<?= $swal['icon'] ?>',title:'<?= addslashes($swal['title']) ?>',text:'<?= addslashes($swal['text']) ?>',confirmButtonColor:'#1a7a4a',timer:<?= in_array($swal['icon'],['success','info'])?3500:0 ?>,timerProgressBar:<?= in_array($swal['icon'],['success','info'])?'true':'false' ?>,showConfirmButton:<?= in_array($swal['icon'],['success','info'])?'false':'true' ?>,});});</script>
<?php endif; ?>

<!-- ── STATS ── -->
<div class="row mb-4">
    <div class="col-md-3 col-6 mb-2">
        <div class="stat-mini-caja" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);">
            <i class="fas fa-cash-register"></i>
            <div><div class="stat-value"><?= $stats['cajas_hoy'] ?></div><div class="stat-label">Cajas hoy</div></div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="stat-mini-caja" style="background:linear-gradient(135deg,#117a8b,#1a9aad);">
            <i class="fas fa-arrow-circle-up"></i>
            <div><div class="stat-value">S/. <?= number_format($stats['total_ingresos_hoy'],2) ?></div><div class="stat-label">Ingresos turno</div></div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="stat-mini-caja" style="background:linear-gradient(135deg,#922b21,#e74c3c);">
            <i class="fas fa-arrow-circle-down"></i>
            <div><div class="stat-value">S/. <?= number_format($stats['total_egresos_hoy'],2) ?></div><div class="stat-label">Egresos turno</div></div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="stat-mini-caja" style="background:linear-gradient(135deg,#6c3483,#8e44ad);">
            <i class="fas fa-history"></i>
            <div>
                <a href="/botica-2026/modules/caja/historial/historial_caja.php"
                   class="btn btn-sm font-weight-bold"
                   style="background:rgba(255,255,255,.25);color:#fff;border:1px solid rgba(255,255,255,.4);border-radius:6px;font-size:.78rem;padding:4px 10px;">
                    <i class="fas fa-external-link-alt mr-1"></i>Historial de Cajas
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($caja_activa): ?>
<!-- ══════════════════════════════════════════════════════════
     CAJA ACTIVA
══════════════════════════════════════════════════════════ -->
<div class="row mb-4">
    <!-- Panel izquierdo: info + resumen -->
    <div class="col-lg-7 mb-3">
        <div class="card-caja-activa">
            <div class="card-caja-activa-header">
                <div>
                    <h5><i class="fas fa-lock-open mr-2"></i>Caja Activa — Turno <?= ucfirst($caja_activa['turno']) ?></h5>
                    <small style="opacity:.85;">Cajero: <?= htmlspecialchars($caja_activa['nombre_completo']) ?></small>
                </div>
                <span class="badge-caja-abierta"><i class="fas fa-circle mr-1" style="font-size:.6rem;"></i>EN CURSO</span>
            </div>
            <div class="card-caja-activa-body">
                <div class="info-caja-grid">
                    <div class="info-caja-item verde">
                        <div class="label"><i class="fas fa-clock mr-1"></i>Apertura</div>
                        <div class="valor"><?= date('d/m/Y H:i', strtotime($caja_activa['fecha_apertura'])) ?></div>
                    </div>
                    <div class="info-caja-item azul">
                        <div class="label"><i class="fas fa-wallet mr-1"></i>Fondo Inicial</div>
                        <div class="valor">S/. <?= number_format($caja_activa['monto_inicial'],2) ?></div>
                    </div>
                    <div class="info-caja-item teal">
                        <div class="label"><i class="fas fa-sun mr-1"></i>Turno</div>
                        <div class="valor">
                            <?php
                            $turnoIcons = ['mañana'=>'☀️','tarde'=>'🌤️','noche'=>'🌙'];
                            echo ($turnoIcons[$caja_activa['turno']]??'').' '.ucfirst($caja_activa['turno']);
                            ?>
                        </div>
                    </div>
                    <div class="info-caja-item naranja">
                        <div class="label"><i class="fas fa-exchange-alt mr-1"></i>Movimientos</div>
                        <div class="valor"><?= count($movimientos) ?></div>
                    </div>
                </div>

                <!-- Resumen financiero -->
                <div class="resumen-caja-box">
                    <div class="resumen-caja-row ingreso">
                        <span><i class="fas fa-plus-circle mr-2 text-success"></i>Total Ingresos</span>
                        <span class="monto">+ S/. <?= number_format($resumen['ingresos'],2) ?></span>
                    </div>
                    <div class="resumen-caja-row egreso">
                        <span><i class="fas fa-minus-circle mr-2 text-danger"></i>Total Egresos</span>
                        <span class="monto">- S/. <?= number_format($resumen['egresos'],2) ?></span>
                    </div>
                    <div class="resumen-caja-row total-caja">
                        <span><i class="fas fa-coins mr-2"></i>Saldo Actual en Caja</span>
                        <span class="monto">S/. <?= number_format($resumen['saldo'],2) ?></span>
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-sm font-weight-bold" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);color:#fff;" data-toggle="modal" data-target="#modalMovimientoManual">
                        <i class="fas fa-plus-circle mr-1"></i>Nuevo Movimiento
                    </button>
                    <button id="btnAbrirCierre2" class="btn btn-sm btn-outline-danger font-weight-bold" onclick="$('#modalCierreCaja').modal('show')">
                        <i class="fas fa-lock mr-1"></i>Cerrar Caja
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel derecho: últimos movimientos -->
    <div class="col-lg-5 mb-3">
        <div class="card h-100" style="border:none;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.07);">
            <div class="card-header-caja-mov">
                <h6><i class="fas fa-list-ul mr-2"></i>Últimos Movimientos</h6>
            </div>
            <div class="card-body p-0" style="max-height:320px;overflow-y:auto;">
                <?php if (empty($movimientos)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>
                    <small>Sin movimientos aún</small>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($movimientos,0,10) as $m): ?>
                    <div class="list-group-item list-group-item-action px-3 py-2" style="border-color:#f0f0f0;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:.82rem;font-weight:600;color:#2d3436;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?= htmlspecialchars($m['descripcion']) ?>
                                </div>
                                <div style="font-size:.72rem;color:#999;margin-top:2px;">
                                    <?= date('H:i',strtotime($m['fecha'])) ?> &bull;
                                    <?php
                                    $refMap = ['venta'=>'badge-ref-venta','compra'=>'badge-ref-compra','manual'=>'badge-ref-manual'];
                                    $refLbl = ['venta'=>'Venta','compra'=>'Compra','manual'=>'Manual'];
                                    echo '<span class="'.($refMap[$m['tipo_referencia']]??'').'">'.($refLbl[$m['tipo_referencia']]??'').($m['id_referencia']?' #'.$m['id_referencia']:'').'</span>';
                                    ?>
                                </div>
                            </div>
                            <div class="ml-2 text-right" style="flex-shrink:0;">
                                <div style="font-size:.88rem;font-weight:700;font-family:monospace;color:<?= $m['tipo']==='ingreso'?'#155724':'#721c24' ?>;">
                                    <?= $m['tipo']==='ingreso'?'+':'-' ?> S/. <?= number_format($m['monto'],2) ?>
                                </div>
                                <span class="badge-<?= $m['tipo']==='ingreso'?'ingreso':'egreso' ?>" style="font-size:.65rem;"><?= strtoupper($m['tipo']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tabla completa de movimientos -->
<div class="card mb-4" style="border:none;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.07);">
    <div class="card-header-caja-mov d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-table mr-2"></i>Todos los Movimientos del Turno</h6>
        <span class="badge badge-light"><?= count($movimientos) ?> registros</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tablaMovimientos" class="table table-movimientos table-bordered table-hover table-sm">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Fecha/Hora</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Referencia</th>
                        <th>Método</th>
                        <th class="text-right">Monto</th>
                        <th>Registrado por</th>
                    </tr>
                </thead>
                <tbody>
                <?php $n=1; foreach ($movimientos as $m): ?>
                    <tr>
                        <td class="text-center"><div class="num-fila-caja"><?= $n++ ?></div></td>
                        <td><?= date('d/m/Y H:i',strtotime($m['fecha'])) ?></td>
                        <td><span class="badge-<?= $m['tipo']==='ingreso'?'ingreso':'egreso' ?>"><?= strtoupper($m['tipo']) ?></span></td>
                        <td><?= htmlspecialchars($m['descripcion']) ?></td>
                        <td>
                            <?php
                            $refMap = ['venta'=>'badge-ref-venta','compra'=>'badge-ref-compra','manual'=>'badge-ref-manual'];
                            $refLbl = ['venta'=>'Venta','compra'=>'Compra','manual'=>'Manual'];
                            echo '<span class="'.($refMap[$m['tipo_referencia']]??'').'">'.($refLbl[$m['tipo_referencia']]??'').($m['id_referencia']?' #'.$m['id_referencia']:'').'</span>';
                            ?>
                        </td>
                        <td><span style="font-size:.8rem;font-weight:600;"><?= strtoupper($m['metodo_pago']) ?></span></td>
                        <td class="text-right font-weight-bold" style="font-family:monospace;color:<?= $m['tipo']==='ingreso'?'#155724':'#721c24' ?>;">
                            <?= $m['tipo']==='ingreso'?'+':'-' ?> S/. <?= number_format($m['monto'],2) ?>
                        </td>
                        <td style="font-size:.82rem;"><?= htmlspecialchars($m['nombre_completo']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════
     SIN CAJA ABIERTA — FORMULARIO APERTURA
══════════════════════════════════════════════════════════ -->
<div class="row mb-4">
    <div class="col-lg-6 mb-3">
        <div class="card-sin-caja">
            <div class="card-sin-caja-header">
                <h5><i class="fas fa-lock mr-2"></i>Sin Caja Abierta</h5>
            </div>
            <div class="card-sin-caja-body">
                <div class="icono-sin-caja">
                    <i class="fas fa-cash-register"></i>
                </div>
                <h5 style="color:#636e72;font-weight:700;">No hay caja activa</h5>
                <p class="text-muted mb-3">Para registrar ventas y compras debes aperturar una caja primero.</p>
                <div class="alerta-caja-bloqueada" style="text-align:left;">
                    <i class="fas fa-exclamation-triangle" style="font-size:1.2rem;display:inline;margin-right:8px;"></i>
                    <strong>Recuerda:</strong> Sin caja abierta no podrás registrar ventas ni compras en el sistema.
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="form-apertura-card">
            <div class="form-apertura-header">
                <h5><i class="fas fa-lock-open mr-2"></i>Aperturar Nueva Caja</h5>
            </div>
            <div class="form-apertura-body">
                <form id="formAperturaCaja" method="POST">
                    <input type="hidden" name="accion" value="aperturar">
                    <input type="hidden" name="turno" id="hidden_turno" value="">

                    <div class="form-group">
                        <label class="form-label-caja"><i class="fas fa-sun mr-1 text-muted"></i>Turno de Trabajo <span class="text-danger">*</span></label>
                        <div class="btn-turno-group">
                            <button type="button" class="btn-turno" data-turno="mañana">
                                <i class="fas fa-sun"></i>Mañana
                            </button>
                            <button type="button" class="btn-turno" data-turno="tarde">
                                <i class="fas fa-cloud-sun"></i>Tarde
                            </button>
                            <button type="button" class="btn-turno" data-turno="noche">
                                <i class="fas fa-moon"></i>Noche
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label-caja"><i class="fas fa-wallet mr-1 text-muted"></i>Monto Inicial (Fondo de Caja)</label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="background:#d4edda;color:#1a7a4a;font-weight:700;border-color:#c8e6c9;">S/.</span>
                            </div>
                            <input type="number" step="0.01" min="0" class="form-control" name="monto_inicial" id="monto_inicial_apertura" value="0.00" placeholder="0.00">
                        </div>
                        <small class="text-muted">Dinero físico con el que inicia el turno</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label-caja"><i class="fas fa-comment mr-1 text-muted"></i>Observación <small class="text-muted">(opcional)</small></label>
                        <textarea class="form-control form-control-sm" name="observacion" rows="2" maxlength="200" placeholder="Ej: Turno normal, sin novedades..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-block font-weight-bold" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);color:#fff;border-radius:8px;padding:10px;">
                        <i class="fas fa-lock-open mr-2"></i>Aperturar Caja
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</div></div></div>

<!-- ══════════════════════════════════════════════════════════
     MODAL MOVIMIENTO MANUAL
══════════════════════════════════════════════════════════ -->
<?php if ($caja_activa): ?>
<div class="modal fade" id="modalMovimientoManual" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#1a7a4a,#27ae60);padding:18px 24px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:.8;font-size:1.4rem;position:absolute;top:12px;right:16px;">&times;</button>
                <h5 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-exchange-alt mr-2"></i>Nuevo Movimiento Manual</h5>
                <small style="color:rgba(255,255,255,.8);">Caja #<?= $caja_activa['id_caja'] ?> — Turno <?= ucfirst($caja_activa['turno']) ?></small>
            </div>
            <div class="modal-body" style="padding:20px 24px;">
                <form id="formMovimientoManual" method="POST">
                    <input type="hidden" name="accion" value="movimiento_manual">
                    <input type="hidden" name="id_caja" value="<?= $caja_activa['id_caja'] ?>">
                    <input type="hidden" name="tipo" id="hidden_tipo_mov" value="">
                    <input type="hidden" name="metodo_pago" id="hidden_metodo_mov" value="efectivo">

                    <div class="form-group">
                        <label class="form-label-caja"><i class="fas fa-arrows-alt-v mr-1 text-muted"></i>Tipo de Movimiento <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn-tipo-mov btn flex-fill font-weight-bold" data-tipo="ingreso"
                                style="border:2px solid #dee2e6;border-radius:8px;padding:10px;background:#fff;color:#555;transition:all .2s;">
                                <i class="fas fa-plus-circle mr-1" style="color:#27ae60;"></i>INGRESO
                            </button>
                            <button type="button" class="btn-tipo-mov btn flex-fill font-weight-bold" data-tipo="egreso"
                                style="border:2px solid #dee2e6;border-radius:8px;padding:10px;background:#fff;color:#555;transition:all .2s;">
                                <i class="fas fa-minus-circle mr-1" style="color:#e74c3c;"></i>EGRESO
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label-caja"><i class="fas fa-align-left mr-1 text-muted"></i>Descripción <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" name="descripcion" id="descripcion_mov" maxlength="150" placeholder="Ej: Pago servicio limpieza, Fondo adicional...">
                    </div>

                    <div class="form-group">
                        <label class="form-label-caja"><i class="fas fa-dollar-sign mr-1 text-muted"></i>Monto <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="background:#d4edda;color:#1a7a4a;font-weight:700;border-color:#c8e6c9;">S/.</span>
                            </div>
                            <input type="number" step="0.01" min="0.01" class="form-control" name="monto" id="monto_mov" placeholder="0.00">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label-caja"><i class="fas fa-credit-card mr-1 text-muted"></i>Método de Pago</label>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach (['efectivo'=>'💵 Efectivo','yape'=>'💜 Yape','plin'=>'💚 Plin','transferencia'=>'🏦 Transferencia','tarjeta'=>'💳 Tarjeta'] as $k=>$v): ?>
                            <button type="button" class="btn-metodo-caja btn btn-sm <?= $k==='efectivo'?'activo':'' ?>" data-metodo="<?= $k ?>"
                                style="border:2px solid <?= $k==='efectivo'?'#1a7a4a':'#dee2e6' ?>;border-radius:8px;background:<?= $k==='efectivo'?'#1a7a4a':'#fff' ?>;color:<?= $k==='efectivo'?'#fff':'#555' ?>;font-size:.8rem;font-weight:600;padding:5px 10px;transition:all .2s;">
                                <?= $v ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label class="form-label-caja"><i class="fas fa-comment mr-1 text-muted"></i>Observación <small class="text-muted">(opcional)</small></label>
                        <textarea class="form-control form-control-sm" name="observacion" id="obs_mov" rows="2" maxlength="150" placeholder="Detalle adicional..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
                <button type="submit" form="formMovimientoManual" id="btnConfirmarMovimiento" class="btn btn-sm font-weight-bold"
                    style="background:linear-gradient(135deg,#1a7a4a,#27ae60);color:#fff;border:none;border-radius:8px;padding:7px 18px;">
                    <i class="fas fa-save mr-1"></i>Registrar Movimiento
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL CIERRE DE CAJA
══════════════════════════════════════════════════════════ -->
<?php
$monto_esperado = round((float)$caja_activa['monto_inicial'] + $resumen['ingresos'] - $resumen['egresos'], 2);
?>
<div class="modal fade" id="modalCierreCaja" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#922b21,#e74c3c);padding:18px 24px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:.8;font-size:1.4rem;position:absolute;top:12px;right:16px;">&times;</button>
                <h5 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-lock mr-2"></i>Cierre de Caja</h5>
                <small style="color:rgba(255,255,255,.8);">Turno <?= ucfirst($caja_activa['turno']) ?> — <?= date('d/m/Y H:i',strtotime($caja_activa['fecha_apertura'])) ?></small>
            </div>
            <div class="modal-body" style="padding:20px 24px;">
                <div class="cierre-resumen-box">
                    <div class="cierre-row">
                        <span><i class="fas fa-wallet mr-2 text-muted"></i>Fondo inicial</span>
                        <strong style="font-family:monospace;">S/. <?= number_format($caja_activa['monto_inicial'],2) ?></strong>
                    </div>
                    <div class="cierre-row">
                        <span><i class="fas fa-plus-circle mr-2 text-success"></i>Total ingresos</span>
                        <strong style="font-family:monospace;color:#155724;">+ S/. <?= number_format($resumen['ingresos'],2) ?></strong>
                    </div>
                    <div class="cierre-row">
                        <span><i class="fas fa-minus-circle mr-2 text-danger"></i>Total egresos</span>
                        <strong style="font-family:monospace;color:#721c24;">- S/. <?= number_format($resumen['egresos'],2) ?></strong>
                    </div>
                    <div class="cierre-row total">
                        <span><i class="fas fa-coins mr-2"></i>Monto esperado en caja</span>
                        <strong style="font-family:monospace;font-size:1.1rem;">S/. <?= number_format($monto_esperado,2) ?></strong>
                    </div>
                </div>

                <form id="formCierreCaja" method="POST">
                    <input type="hidden" name="accion" value="cerrar">
                    <input type="hidden" name="id_caja" value="<?= $caja_activa['id_caja'] ?>">
                    <input type="hidden" name="monto_esperado_hidden" value="<?= $monto_esperado ?>">

                    <div class="form-group">
                        <label class="form-label-caja"><i class="fas fa-hand-holding-usd mr-1 text-muted"></i>Monto Contado Físicamente <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text" style="background:#f8d7da;color:#721c24;font-weight:700;border-color:#f5c6cb;">S/.</span>
                            </div>
                            <input type="number" step="0.01" min="0" class="form-control" name="monto_final" id="monto_final_cierre" placeholder="0.00">
                            <input type="hidden" id="monto_esperado_cierre" value="<?= $monto_esperado ?>">
                        </div>
                        <small class="text-muted">Ingresa el dinero que contaste físicamente en caja</small>
                    </div>

                    <div id="diferencia_cierre" style="display:none;background:#f8f9fa;border-radius:8px;padding:10px 14px;margin-bottom:12px;border:1px solid #dee2e6;">
                        <span id="texto_diferencia" style="font-size:.88rem;"></span>
                    </div>

                    <div class="form-group mb-0">
                        <label class="form-label-caja"><i class="fas fa-comment mr-1 text-muted"></i>Observación de cierre <small class="text-muted">(opcional)</small></label>
                        <textarea class="form-control form-control-sm" name="observacion" rows="2" maxlength="200" placeholder="Novedades del turno..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
                <button type="submit" form="formCierreCaja" class="btn btn-danger btn-sm font-weight-bold">
                    <i class="fas fa-lock mr-1"></i>Confirmar Cierre
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════
     MODAL VER DETALLE CAJA — eliminado (ver historial_caja.php)
══════════════════════════════════════════════════════════ -->

<?php include $ruta_base . 'includes/footer.php'; ?>
<script src="js/caja.js"></script>

