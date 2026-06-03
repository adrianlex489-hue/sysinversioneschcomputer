<?php
// modules/Caja/caja.php | SysInversiones CH Computer 2026
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';
require_once $ruta_base . 'conf/auditoria.php';
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR_COMERCIAL'))        define('ROL_ASESOR_COMERCIAL', 2);
if (!defined('ROL_TECNICO'))       define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);
verificarPermiso($pdo, 'caja');

$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
$METODOS    = ['efectivo','yape','transferencia'];

$swal = null;
if (isset($_SESSION['swal_caja'])) { $swal = $_SESSION['swal_caja']; unset($_SESSION['swal_caja']); }

function redirigirCaja(string $icon, string $title, string $text): void {
    $_SESSION['swal_caja'] = compact('icon','title','text');
    header('Location: caja.php'); exit;
}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'aperturar') {
        $nombre = trim($_POST['nombre'] ?? '') ?: 'Caja ' . date('d/m/Y');
        $monto  = (float)($_POST['monto_inicial'] ?? 0);
        $obs    = trim($_POST['observacion'] ?? '') ?: null;
        if ($monto < 0) redirigirCaja('warning','Monto inválido','El fondo no puede ser negativo.');
        try {
            $abierta = $pdo->query("SELECT c.id_caja, u.nombre_completo FROM caja c JOIN usuarios u ON u.id_usuario=c.id_usuario WHERE c.estado='abierta' LIMIT 1")->fetch();
            if ($abierta) redirigirCaja('warning','Caja ya abierta','Ya hay una caja abierta por ' . htmlspecialchars($abierta['nombre_completo']) . '. Ciérrala primero.');
            $pdo->prepare("INSERT INTO caja (id_usuario,nombre,monto_inicial,estado,fecha_apertura,observacion) VALUES (?,?,?,'abierta',NOW(),?)")
                ->execute([$id_usuario, $nombre, $monto, $obs]);
            $id_caja_nueva = (int)$pdo->lastInsertId();
            registrarAuditoria($pdo, 'caja', 'apertura', 'caja', $id_caja_nueva,
                "Apertura de caja: \"$nombre\" — Fondo inicial: S/. " . number_format($monto, 2));
            redirigirCaja('success','¡Caja aperturada!', '"' . $nombre . '" iniciada con S/. ' . number_format($monto,2) . '.');
        } catch (PDOException $e) { redirigirCaja('error','Error',$e->getMessage()); }
    }

    if ($accion === 'movimiento_manual') {
        $id_caja = (int)($_POST['id_caja'] ?? 0);
        $tipo    = $_POST['tipo'] ?? '';
        $desc    = trim($_POST['descripcion'] ?? '');
        $monto   = (float)($_POST['monto'] ?? 0);
        $metodo  = in_array($_POST['metodo_pago'] ?? '', $METODOS) ? $_POST['metodo_pago'] : 'efectivo';
        $obs     = trim($_POST['observacion'] ?? '') ?: null;
        if (!$id_caja || !in_array($tipo,['ingreso','egreso']) || empty($desc) || $monto <= 0)
            redirigirCaja('warning','Datos incompletos','Completa todos los campos requeridos.');
        try {
            $c = $pdo->prepare("SELECT estado FROM caja WHERE id_caja=?");
            $c->execute([$id_caja]); $cj = $c->fetch();
            if (!$cj || $cj['estado'] !== 'abierta') redirigirCaja('warning','Caja cerrada','La caja ya no está activa.');
            $pdo->prepare("INSERT INTO movimientos_caja (id_caja,tipo_referencia,id_referencia,id_usuario,tipo,descripcion,observacion,monto,metodo_pago) VALUES (?,'manual',NULL,?,?,?,?,?,?)")
                ->execute([$id_caja,$id_usuario,$tipo,$desc,$obs,$monto,$metodo]);
            registrarAuditoria($pdo, 'caja', 'editar', 'movimientos_caja', $id_caja,
                "Movimiento manual — " . strtoupper($tipo) . ": $desc — S/. " . number_format($monto, 2) . " ($metodo)");
            redirigirCaja('success','Movimiento registrado', strtoupper($tipo) . ' de S/. ' . number_format($monto,2) . ' registrado.');
        } catch (PDOException $e) { redirigirCaja('error','Error',$e->getMessage()); }
    }
}

// ── Caja activa ───────────────────────────────────────────────────────────────
$caja_activa = null;
try {
    $caja_activa = $pdo->query("SELECT c.*, u.nombre_completo FROM caja c JOIN usuarios u ON u.id_usuario=c.id_usuario WHERE c.estado='abierta' ORDER BY c.fecha_apertura DESC LIMIT 1")->fetch();
} catch (PDOException $e) {}

// ── Stats rápidos ─────────────────────────────────────────────────────────────
$stats = ['ventas'=>0,'ventas_contado'=>0,'ventas_credito'=>0,'servicios'=>0,'compras'=>0,'pago_proveedor'=>0,'manual_ing'=>0,'manual_eg'=>0,'total_movs'=>0];
if ($caja_activa) {
    try {
        $stS = $pdo->prepare("SELECT tipo_referencia,tipo,COALESCE(SUM(monto),0) AS total,COUNT(*) AS cnt FROM movimientos_caja WHERE id_caja=? GROUP BY tipo_referencia,tipo");
        $stS->execute([$caja_activa['id_caja']]);
        foreach ($stS->fetchAll() as $r) {
            $stats['total_movs'] += $r['cnt'];
            if ($r['tipo_referencia']==='venta'   && $r['tipo']==='ingreso') $stats['ventas']     += $r['total'];
            if ($r['tipo_referencia']==='servicio' && $r['tipo']==='ingreso') $stats['servicios']  += $r['total'];
            if ($r['tipo_referencia']==='compra'   && $r['tipo']==='egreso')  $stats['compras']    += $r['total'];
            if ($r['tipo_referencia']==='manual'   && $r['tipo']==='ingreso') $stats['manual_ing'] += $r['total'];
            if ($r['tipo_referencia']==='manual'   && $r['tipo']==='egreso')  $stats['manual_eg']  += $r['total'];
        }
        // Separar ventas contado vs abonos de crédito
        $stVC = $pdo->prepare("
            SELECT COALESCE(SUM(mc.monto),0)
            FROM movimientos_caja mc
            JOIN ventas v ON v.id_venta = mc.id_referencia
            WHERE mc.id_caja = ? AND mc.tipo_referencia = 'venta' AND mc.tipo = 'ingreso'
              AND v.tipo_pago = 'contado'
        ");
        $stVC->execute([$caja_activa['id_caja']]);
        $stats['ventas_contado'] = (float)$stVC->fetchColumn();
        $stats['ventas_credito'] = max(0, $stats['ventas'] - $stats['ventas_contado']);

        // Separar compras contado vs pagos a proveedores (crédito)
        $stPP = $pdo->prepare("
            SELECT COALESCE(SUM(mc.monto),0)
            FROM movimientos_caja mc
            JOIN compras c ON c.id_compra = mc.id_referencia
            WHERE mc.id_caja = ? AND mc.tipo_referencia = 'compra' AND mc.tipo = 'egreso'
              AND c.tipo_pago = 'credito'
        ");
        $stPP->execute([$caja_activa['id_caja']]);
        $stats['pago_proveedor'] = (float)$stPP->fetchColumn();
        $stats['compras'] = max(0, $stats['compras'] - $stats['pago_proveedor']);
    } catch (PDOException $e) {}
}
$neto = $stats['ventas'] + $stats['servicios'] + $stats['manual_ing'] - $stats['compras'] - $stats['pago_proveedor'] - $stats['manual_eg'];

$extra_css = '<link rel="stylesheet" href="/sysinversioneschcomputer/modules/Caja/css/caja.css?v=' . time() . '">';
$extra_js  = '<script src="/sysinversioneschcomputer/modules/Caja/js/caja.js?v=' . time() . '"></script>';
include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<div class="content-wrapper">
<div class="content-header"><div class="container-fluid">
<div class="cx-page-header d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h4><i class="fas fa-cash-register mr-2"></i>Gestión de Caja</h4>
        <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones &rsaquo; Caja &rsaquo; <?= $caja_activa ? htmlspecialchars($caja_activa['nombre']) : 'Sin caja abierta' ?></small>
    </div>
    <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
        <?php if ($caja_activa): ?>
        <span class="cx-live-badge"><i class="fas fa-circle mr-1" style="font-size:.5rem;"></i>EN CURSO</span>
        <a href="resumen_caja.php" class="cx-btn cx-btn-ghost"><i class="fas fa-chart-bar mr-1"></i>Resumen</a>
        <a href="movimientos_caja.php" class="cx-btn cx-btn-ghost"><i class="fas fa-list-ul mr-1"></i>Movimientos</a>
        <a href="cierre_caja.php?id_caja=<?= $caja_activa['id_caja'] ?>" class="cx-btn cx-btn-danger"><i class="fas fa-lock mr-1"></i>Cerrar Caja</a>
        <?php endif; ?>
        <a href="historial_caja.php" class="cx-btn cx-btn-ghost"><i class="fas fa-history mr-1"></i>Historial</a>
    </div>
</div>
</div></div>
<div class="content"><div class="container-fluid">

<?php if ($swal): ?>
<script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({icon:'<?= $swal['icon'] ?>',title:'<?= addslashes($swal['title']) ?>',text:'<?= addslashes($swal['text']) ?>',confirmButtonColor:'#0ea5e9',timer:<?= in_array($swal['icon'],['success','info'])?3500:0 ?>,timerProgressBar:<?= in_array($swal['icon'],['success','info'])?'true':'false' ?>,showConfirmButton:<?= in_array($swal['icon'],['success','info'])?'false':'true' ?>});});</script>
<?php endif; ?>

<?php if ($caja_activa): ?>

<!-- KPI Strip -->
<div class="cx-kpi-strip">
    <div class="cx-kpi-info">
        <div class="cx-kpi-name">
            <div class="cx-kpi-name-icon"><i class="fas fa-store"></i></div>
            <?= htmlspecialchars($caja_activa['nombre']) ?>
        </div>
        <div class="cx-kpi-meta">
            <span><i class="fas fa-user-tie mr-1"></i><?= htmlspecialchars($caja_activa['nombre_completo']) ?></span>
            <span class="cx-kpi-meta-sep">·</span>
            <span><i class="fas fa-clock mr-1"></i><?= date('d/m/Y H:i', strtotime($caja_activa['fecha_apertura'])) ?></span>
            <span class="cx-kpi-meta-sep">·</span>
            <span class="cx-kpi-timer" id="cajaDuracion"></span>
        </div>
    </div>
    <div class="cx-kpi-stats">
        <div class="cx-kpi-stat">
            <div class="cx-kpi-lbl"><i class="fas fa-coins mr-1"></i>Fondo inicial</div>
            <div class="cx-kpi-val v-cyan">S/. <?= number_format($caja_activa['monto_inicial'],2) ?></div>
        </div>
        <div class="cx-kpi-divider"></div>
        <div class="cx-kpi-stat">
            <div class="cx-kpi-lbl"><i class="fas fa-wallet mr-1"></i>Saldo actual</div>
            <div class="cx-kpi-val v-green" id="saldoActualVal">—</div>
        </div>
        <div class="cx-kpi-divider"></div>
        <div class="cx-kpi-stat">
            <div class="cx-kpi-lbl"><i class="fas fa-arrow-up mr-1"></i>Ingresos</div>
            <div class="cx-kpi-val v-green" id="totalIngVal">—</div>
        </div>
        <div class="cx-kpi-divider"></div>
        <div class="cx-kpi-stat">
            <div class="cx-kpi-lbl"><i class="fas fa-arrow-down mr-1"></i>Egresos</div>
            <div class="cx-kpi-val v-red" id="totalEgVal">—</div>
        </div>
        <div class="cx-kpi-divider d-none d-lg-block"></div>
        <div class="cx-kpi-stat d-none d-lg-flex">
            <div class="cx-kpi-lbl"><i class="fas fa-exchange-alt mr-1"></i>Movimientos</div>
            <div class="cx-kpi-val v-amber"><?= $stats['total_movs'] ?></div>
        </div>
    </div>
</div>

<!-- Canales de pago -->
<div class="row mb-4">
    <div class="col-12 mb-2 d-flex align-items-center justify-content-between">
        <h6 style="font-size:.88rem;font-weight:700;color:#1e293b;margin:0;"><i class="fas fa-wallet mr-2" style="color:#0ea5e9;"></i>Saldo por canal de pago</h6>
        <button class="cx-btn-refresh" id="btnRefresh"><i class="fas fa-sync-alt mr-1"></i>Actualizar</button>
    </div>
    <?php
    $metodo_cfg = [
        'efectivo'      => ['icon'=>'fas fa-money-bill-wave','bg'=>'linear-gradient(135deg,#064e3b,#10b981)','mc'=>'#10b981','label'=>'Efectivo'],
        'yape'          => ['icon'=>'fas fa-mobile-alt',     'bg'=>'linear-gradient(135deg,#3b0764,#8b5cf6)','mc'=>'#8b5cf6','label'=>'Yape'],
        'transferencia' => ['icon'=>'fas fa-university',     'bg'=>'linear-gradient(135deg,#78350f,#f59e0b)','mc'=>'#f59e0b','label'=>'Transferencia'],
    ];
    foreach ($metodo_cfg as $m => $cfg): ?>
    <div class="col-6 col-md-4 col-lg mb-3">
        <div class="cx-metodo-card" style="--mc:<?= $cfg['mc'] ?>;">
            <div class="cx-metodo-icon" style="background:<?= $cfg['bg'] ?>;"><i class="<?= $cfg['icon'] ?>"></i></div>
            <div class="cx-metodo-label"><?= $cfg['label'] ?></div>
            <div class="cx-metodo-neto" id="neto_<?= $m ?>">S/. 0.00</div>
            <div class="cx-metodo-pills">
                <span class="cx-pill-ing" id="ing_<?= $m ?>"><i class="fas fa-arrow-up mr-1"></i>0.00</span>
                <span class="cx-pill-eg"  id="eg_<?= $m ?>"><i class="fas fa-arrow-down mr-1"></i>0.00</span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Desglose + Movimientos recientes -->
<div class="row mb-4">
    <div class="col-lg-4 mb-3">
        <div class="cx-card h-100">
            <div class="cx-card-hdr green"><i class="fas fa-chart-pie mr-2"></i>Desglose del día</div>
            <div style="padding:6px 0;">
                <?php
                $desglose = [
                    ['dot'=>'#10b981','label'=>'Ventas al contado',        'val'=>$stats['ventas_contado'], 'cls'=>'ing'],
                    ['dot'=>'#6366f1','label'=>'Abonos crédito clientes',  'val'=>$stats['ventas_credito'], 'cls'=>'ing'],
                    ['dot'=>'#06b6d4','label'=>'Cobros de servicio',       'val'=>$stats['servicios'],      'cls'=>'ing'],
                    ['dot'=>'#f59e0b','label'=>'Ingresos manuales',        'val'=>$stats['manual_ing'],     'cls'=>'ing'],
                ];
                foreach ($desglose as $d):
                    if ($d['val'] == 0) continue; ?>
                <div class="cx-desglose-row">
                    <div class="cx-desglose-left">
                        <span class="cx-desglose-dot" style="background:<?= $d['dot'] ?>;"></span>
                        <span class="cx-desglose-lbl"><?= $d['label'] ?></span>
                    </div>
                    <span class="cx-desglose-val <?= $d['cls'] ?>">S/. <?= number_format($d['val'],2) ?></span>
                </div>
                <?php endforeach; ?>
                <div class="cx-desglose-divider"></div>
                <?php
                $egresos = [
                    ['dot'=>'#ef4444','label'=>'Compras al contado',    'val'=>$stats['compras'],        'cls'=>'eg'],
                    ['dot'=>'#dc2626','label'=>'Pagos a proveedores',   'val'=>$stats['pago_proveedor'], 'cls'=>'eg'],
                    ['dot'=>'#f97316','label'=>'Egresos manuales',      'val'=>$stats['manual_eg'],      'cls'=>'eg'],
                ];
                foreach ($egresos as $d):
                    if ($d['val'] == 0) continue; ?>
                <div class="cx-desglose-row">
                    <div class="cx-desglose-left">
                        <span class="cx-desglose-dot" style="background:<?= $d['dot'] ?>;"></span>
                        <span class="cx-desglose-lbl"><?= $d['label'] ?></span>
                    </div>
                    <span class="cx-desglose-val <?= $d['cls'] ?>">− S/. <?= number_format($d['val'],2) ?></span>
                </div>
                <?php endforeach; ?>
                <div class="cx-neto-box">
                    <span><i class="fas fa-coins mr-2"></i>Neto del día</span>
                    <span class="cx-neto-val <?= $neto>=0?'pos':'neg' ?>"><?= $neto>=0?'+':'' ?>S/. <?= number_format($neto,2) ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8 mb-3">
        <div class="cx-card h-100">
            <div class="cx-card-hdr cyan d-flex justify-content-between align-items-center">
                <span><i class="fas fa-bolt mr-2"></i>Últimos movimientos</span>
                <button class="cx-btn cx-btn-success" style="padding:4px 12px;font-size:.76rem;" data-toggle="modal" data-target="#modalMovManual">
                    <i class="fas fa-plus mr-1"></i>Nuevo movimiento
                </button>
            </div>
            <div id="listaMovsRecientes" class="cx-movs-list">
                <div class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2 d-block" style="opacity:.3;"></i>Cargando...</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabla completa -->
<div class="cx-tabla-wrap mb-4">
    <div class="cx-tabla-hdr">
        <span><i class="fas fa-table mr-2"></i>Todos los movimientos de hoy</span>
        <div class="d-flex flex-wrap" style="gap:6px;">
            <select id="filtroTipo" class="cx-filter-sel">
                <option value="">Todos los tipos</option>
                <option value="ingreso">Ingresos</option>
                <option value="egreso">Egresos</option>
            </select>
            <select id="filtroMetodo" class="cx-filter-sel">
                <option value="">Todos los métodos</option>
                <option value="efectivo">Efectivo</option>
                <option value="yape">Yape</option>
                <option value="transferencia">Transferencia</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table id="tablaMovimientos" class="table table-cx table-bordered table-hover table-sm mb-0">
            <thead><tr>
                <th>Fecha/Hora</th><th>Tipo</th><th>Descripción</th>
                <th>Origen</th><th>Método</th><th class="text-right">Monto</th><th>Registrado por</th>
            </tr></thead>
            <tbody id="tbodyMovimientos">
                <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<input type="hidden" id="idCajaActiva" value="<?= $caja_activa['id_caja'] ?>">
<input type="hidden" id="fechaApertura" value="<?= $caja_activa['fecha_apertura'] ?>">

<?php else: ?>
<!-- APERTURA -->
<div class="row justify-content-center mt-3">
    <div class="col-xl-5 col-lg-6 col-md-8">
        <div class="cx-apertura-wrap">
            <div class="cx-apertura-icon"><i class="fas fa-cash-register"></i></div>
            <h4 class="cx-apertura-title">Aperturar Caja del Día</h4>
            <p class="cx-apertura-sub mb-4">Ingresa el fondo inicial para comenzar a operar</p>
            <div class="cx-user-info mb-4">
                <i class="fas fa-user-circle"></i>
                <div>
                    <div style="font-size:.72rem;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:.5px;">Aperturando como</div>
                    <div style="font-size:.92rem;color:#1e3a8a;"><?= htmlspecialchars($_SESSION['nombre_completo'] ?? '') ?></div>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="aperturar">
                <div class="form-group mb-3">
                    <label class="cx-form-label"><i class="fas fa-tag mr-1"></i>Nombre de la caja</label>
                    <input type="text" name="nombre" class="form-control cx-input-text" value="Caja <?= date('d/m/Y') ?>" maxlength="100">
                </div>
                <div class="form-group mb-3">
                    <label class="cx-form-label"><i class="fas fa-coins mr-1"></i>Fondo inicial en efectivo <span class="text-danger">*</span></label>
                    <div class="cx-input-group">
                        <span class="cx-input-prefix">S/.</span>
                        <input type="number" name="monto_inicial" class="form-control cx-input" step="0.01" min="0" value="0.00">
                    </div>
                </div>
                <div class="form-group mb-4">
                    <label class="cx-form-label"><i class="fas fa-comment mr-1"></i>Observación <small class="text-muted">(opcional)</small></label>
                    <textarea name="observacion" class="form-control cx-textarea" rows="2" placeholder="Notas de apertura..." maxlength="300"></textarea>
                </div>
                <button type="submit" class="cx-btn-aperturar"><i class="fas fa-lock-open mr-2"></i>Aperturar Caja</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

</div></div></div>

<!-- MODAL: Nuevo movimiento manual -->
<div class="modal fade" id="modalMovManual" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">
            <div class="cx-modal-hdr">
                <h5 class="m-0 font-weight-bold"><i class="fas fa-exchange-alt mr-2"></i>Nuevo Movimiento Manual</h5>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;text-shadow:none;font-size:1.4rem;"><span>&times;</span></button>
            </div>
            <form id="formMovManual" method="POST">
                <input type="hidden" name="accion" value="movimiento_manual">
                <input type="hidden" name="id_caja" value="<?= $caja_activa['id_caja'] ?? 0 ?>">
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

<?php include $ruta_base . 'includes/footer.php'; ?>
