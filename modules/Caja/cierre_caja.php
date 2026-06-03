<?php
// modules/Caja/cierre_caja.php | SysInversiones CH Computer 2026
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';
require_once $ruta_base . 'conf/auditoria.php';
if (!isset($pdo)) die('Sin BD');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR_COMERCIAL'))        define('ROL_ASESOR_COMERCIAL', 2);
if (!defined('ROL_TECNICO'))       define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);
verificarPermiso($pdo, 'caja');

$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
$METODOS    = ['efectivo','yape','transferencia'];

$id_caja = (int)($_GET['id_caja'] ?? 0);
if (!$id_caja) { header('Location: caja.php'); exit; }

$caja = null;
try {
    $st = $pdo->prepare("SELECT c.*,u.nombre_completo FROM caja c JOIN usuarios u ON u.id_usuario=c.id_usuario WHERE c.id_caja=?");
    $st->execute([$id_caja]); $caja = $st->fetch();
} catch (PDOException $e) {}

if (!$caja || $caja['estado'] !== 'abierta') {
    $_SESSION['swal_caja'] = ['icon'=>'warning','title'=>'Caja no disponible','text'=>'Esta caja ya fue cerrada o no existe.'];
    header('Location: caja.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cerrar') {
    $obs      = trim($_POST['observacion'] ?? '') ?: null;
    $contados = [];
    foreach ($METODOS as $m) $contados[$m] = (float)($_POST['contado_'.$m] ?? 0);
    try {
        $stMov = $pdo->prepare("SELECT metodo_pago,tipo,COALESCE(SUM(monto),0) AS total FROM movimientos_caja WHERE id_caja=? GROUP BY metodo_pago,tipo");
        $stMov->execute([$id_caja]);
        $esperado = array_fill_keys($METODOS, 0.0);
        $esperado['efectivo'] = (float)$caja['monto_inicial'];
        foreach ($stMov->fetchAll() as $r) {
            if (!isset($esperado[$r['metodo_pago']])) continue;
            $esperado[$r['metodo_pago']] += $r['tipo']==='ingreso' ? (float)$r['total'] : -(float)$r['total'];
        }
        $monto_final    = round(array_sum($contados), 2);
        $monto_esperado = round(array_sum($esperado), 2);
        $diferencia     = round($monto_final - $monto_esperado, 2);
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE caja SET estado='cerrada',fecha_cierre=NOW(),monto_final=?,monto_esperado=?,diferencia=?,observacion=? WHERE id_caja=?")
            ->execute([$monto_final,$monto_esperado,$diferencia,$obs,$id_caja]);
        $stDet = $pdo->prepare("INSERT INTO cierre_caja_detalle (id_caja,metodo_pago,monto_esperado,monto_contado,diferencia) VALUES (?,?,?,?,?)");
        foreach ($METODOS as $m) $stDet->execute([$id_caja,$m,round($esperado[$m],2),round($contados[$m],2),round($contados[$m]-$esperado[$m],2)]);
        $pdo->commit();
        $msg = abs($diferencia) < 0.01 ? 'Caja cerrada con cuadre exacto ✓'
             : ($diferencia > 0 ? 'Caja cerrada con sobrante de S/. '.number_format($diferencia,2)
                                : 'Caja cerrada con faltante de S/. '.number_format(abs($diferencia),2));
        // Auditoría del cierre
        $desc_audit = "Cierre de caja: \"{$caja['nombre']}\" — Esperado: S/. " . number_format($monto_esperado, 2)
            . " — Contado: S/. " . number_format($monto_final, 2)
            . " — Diferencia: S/. " . number_format($diferencia, 2);
        registrarAuditoria($pdo, 'caja', 'cierre', 'caja', $id_caja, $desc_audit,
            'diferencia', $monto_esperado, $monto_final);
        $_SESSION['swal_caja'] = ['icon'=>abs($diferencia)<0.01?'success':'warning','title'=>'Caja cerrada','text'=>$msg];
        header('Location: historial_caja.php'); exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['swal_caja'] = ['icon'=>'error','title'=>'Error al cerrar','text'=>$e->getMessage()];
        header('Location: cierre_caja.php?id_caja='.$id_caja); exit;
    }
}

$metodo_cfg = [
    'efectivo'      => ['icon'=>'fas fa-money-bill-wave','color'=>'#059669','bg'=>'#dcfce7','label'=>'Efectivo'],
    'yape'          => ['icon'=>'fas fa-mobile-alt',     'color'=>'#5b21b6','bg'=>'#ede9fe','label'=>'Yape'],
    'transferencia' => ['icon'=>'fas fa-university',     'color'=>'#92400e','bg'=>'#fef3c7','label'=>'Transferencia'],
];

$extra_css = '<link rel="stylesheet" href="/sysinversioneschcomputer/modules/Caja/css/caja.css?v=' . time() . '">
             <link rel="stylesheet" href="/sysinversioneschcomputer/modules/Caja/css/cierre_caja.css?v=' . time() . '">';
$extra_js  = '<script src="/sysinversioneschcomputer/modules/Caja/js/cierre_caja.js?v=' . time() . '"></script>';
include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<div class="content-wrapper">
<div class="content-header"><div class="container-fluid">
<div class="cx-cierre-hdr d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h4><i class="fas fa-lock mr-2"></i>Cierre de Caja</h4>
        <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones &rsaquo; Caja &rsaquo; Cierre</small>
    </div>
    <a href="caja.php" class="cx-btn cx-btn-ghost"><i class="fas fa-arrow-left mr-1"></i>Volver a Caja</a>
</div>
</div></div>
<div class="content"><div class="container-fluid">

<!-- Info bar -->
<div class="cx-info-bar mb-4">
    <div class="cx-info-item"><span class="cx-info-lbl"><i class="fas fa-store mr-1"></i>Caja</span><span class="cx-info-val"><?= htmlspecialchars($caja['nombre']) ?></span></div>
    <div class="cx-info-sep"></div>
    <div class="cx-info-item"><span class="cx-info-lbl"><i class="fas fa-user-tie mr-1"></i>Aperturada por</span><span class="cx-info-val"><?= htmlspecialchars($caja['nombre_completo']) ?></span></div>
    <div class="cx-info-sep"></div>
    <div class="cx-info-item"><span class="cx-info-lbl"><i class="fas fa-clock mr-1"></i>Apertura</span><span class="cx-info-val"><?= date('d/m/Y H:i', strtotime($caja['fecha_apertura'])) ?></span></div>
    <div class="cx-info-sep"></div>
    <div class="cx-info-item"><span class="cx-info-lbl"><i class="fas fa-coins mr-1"></i>Fondo inicial</span><span class="cx-info-val" style="color:#10b981;font-weight:700;">S/. <?= number_format($caja['monto_inicial'],2) ?></span></div>
    <div class="cx-info-sep"></div>
    <div class="cx-info-item"><span class="cx-info-lbl"><i class="fas fa-calculator mr-1"></i>Total esperado</span><span class="cx-info-val" id="totalEsperadoBar" style="color:#0ea5e9;font-weight:700;">Calculando...</span></div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="cx-cierre-form-card">
            <div class="cx-cierre-form-hdr">
                <span><i class="fas fa-balance-scale mr-2"></i>Cuadre por canal de pago</span>
                <span class="cx-cierre-form-sub">Ingresa el monto que contaste físicamente en cada canal</span>
            </div>
            <form id="formCierre" method="POST">
                <input type="hidden" name="accion" value="cerrar">
                <input type="hidden" name="id_caja" value="<?= $id_caja ?>">
                <div class="cx-cols-hdr">
                    <div>Canal</div><div>Sistema espera</div><div>Tú contaste</div><div>Diferencia</div>
                </div>
                <?php foreach ($METODOS as $m): $cfg = $metodo_cfg[$m]; ?>
                <div class="cx-metodo-row">
                    <div class="cx-col-metodo">
                        <div class="cx-metodo-icon-sm" style="background:<?= $cfg['bg'] ?>;color:<?= $cfg['color'] ?>;"><i class="<?= $cfg['icon'] ?>"></i></div>
                        <span class="cx-metodo-name"><?= $cfg['label'] ?></span>
                    </div>
                    <div class="cx-col-esp"><span id="esp_<?= $m ?>">—</span></div>
                    <div>
                        <div class="cx-cnt-wrap">
                            <span class="cx-cnt-pre">S/.</span>
                            <input type="number" name="contado_<?= $m ?>" id="cnt_<?= $m ?>" class="cx-cnt-input" step="0.01" min="0" value="0.00" data-metodo="<?= $m ?>">
                        </div>
                    </div>
                    <div class="cx-col-dif"><span id="dif_<?= $m ?>">—</span></div>
                </div>
                <?php endforeach; ?>
                <div class="cx-total-row">
                    <div><strong>TOTAL</strong></div>
                    <div><strong id="totalEsperado">—</strong></div>
                    <div><strong id="totalContado">S/. 0.00</strong></div>
                    <div><strong id="totalDif" class="cx-col-dif">—</strong></div>
                </div>
                <div style="padding:16px 20px;">
                    <div id="difGlobalBox" style="display:none;" class="cx-dif-global mb-3"></div>
                    <div class="form-group mb-3">
                        <label class="cx-form-label"><i class="fas fa-comment mr-1"></i>Observación del cierre <small class="text-muted">(opcional)</small></label>
                        <textarea name="observacion" class="form-control" rows="2" placeholder="Notas del cierre..." maxlength="300"></textarea>
                    </div>
                    <div class="d-flex" style="gap:10px;">
                        <a href="caja.php" class="btn btn-secondary btn-sm"><i class="fas fa-times mr-1"></i>Cancelar</a>
                        <button type="submit" class="cx-btn-cerrar flex-grow-1"><i class="fas fa-lock mr-2"></i>Confirmar Cierre de Caja</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="cx-resumen-card">
            <div class="cx-resumen-hdr"><i class="fas fa-chart-bar mr-2"></i>Resumen del día</div>
            <div id="cierreResumenBody" style="padding:16px;">
                <div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin"></i></div>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="idCajaCierre" value="<?= $id_caja ?>">
</div></div></div>
<?php include $ruta_base . 'includes/footer.php'; ?>
