<?php
// ============================================================
// modules/transacciones/historial/historial_ventas.php
// Historial completo de ventas | Botica 2026
// ============================================================
$ruta_base = '../../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexion BD no disponible.');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_CAJERO'))        define('ROL_CAJERO', 2);
if (!defined('ROL_TRABAJADOR'))    define('ROL_TRABAJADOR', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_CAJERO, ROL_TRABAJADOR]);
verificarPermiso($pdo, 'historial_ventas');

$id_usuario = $_SESSION['id_usuario'] ?? 0;

// Patron PRG
$swal = null;
if (isset($_SESSION['swal_hist_vta'])) { $swal = $_SESSION['swal_hist_vta']; unset($_SESSION['swal_hist_vta']); }

function redirigirHistVta(string $icon, string $title, string $text): void {
    $_SESSION['swal_hist_vta'] = compact('icon', 'title', 'text');
    header('Location: historial_ventas.php'); exit;
}

// Actualizar cuotas vencidas
try {
    $pdo->exec("UPDATE cuotas_venta SET estado='vencido' WHERE estado='pendiente' AND fecha_vencimiento < CURDATE()");
} catch (PDOException $e) {}

// AJAX: detalle de venta
if (isset($_GET['accion']) && $_GET['accion'] === 'detalle_ajax') {
    $id = (int)($_GET['id_venta'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT v.*, CONCAT(c.nombres,' ',c.apellido_paterno) AS nombre_cliente, u.nombre_completo FROM ventas v JOIN clientes c ON v.id_cliente=c.id_cliente JOIN usuarios u ON v.id_usuario=u.id_usuario WHERE v.id_venta=?");
        $stmt->execute([$id]); $cab = $stmt->fetch();
        if (!$cab) { echo '<div class="alert alert-warning">Venta no encontrada.</div>'; exit; }

        $det = $pdo->prepare("SELECT dv.*, p.nombre_producto, p.codigo, l.codigo_lote, l.fecha_vencimiento FROM detalle_venta dv JOIN productos p ON dv.id_producto=p.id_producto JOIN lotes l ON dv.id_lote=l.id_lote WHERE dv.id_venta=?");
        $det->execute([$id]); $items = $det->fetchAll();

        $cuotas = []; $pagos = [];
        try {
            $stC = $pdo->prepare("SELECT * FROM cuotas_venta WHERE id_venta=? ORDER BY numero_cuota"); $stC->execute([$id]); $cuotas = $stC->fetchAll();
            $stP = $pdo->prepare("SELECT pv.*, u.nombre_completo FROM pagos_venta pv JOIN usuarios u ON pv.id_usuario=u.id_usuario WHERE pv.id_venta=? ORDER BY pv.fecha"); $stP->execute([$id]); $pagos = $stP->fetchAll();
        } catch (PDOException $e) {}

        $bMap = ['pagado'=>'badge-hist-vta-pagado','pendiente'=>'badge-hist-vta-pendiente','anulado'=>'badge-hist-vta-anulado'];

        echo '<div class="row mb-2">';
        echo '<div class="col-md-5"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-user mr-1"></i>Cliente</small><strong>'.htmlspecialchars($cab['nombre_cliente']).'</strong></div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-file-invoice mr-1"></i>Comprobante</small><strong>'.htmlspecialchars(ucfirst($cab['tipo_comprobante'])).' '.htmlspecialchars($cab['numero_comprobante']??'---').'</strong></div>';
        echo '<div class="col-md-2"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-credit-card mr-1"></i>T. Pago</small><span class="badge-'.htmlspecialchars($cab['tipo_pago']).'-vta">'.strtoupper($cab['tipo_pago']).'</span></div>';
        echo '<div class="col-md-2"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-circle mr-1"></i>Estado</small><span class="'.($bMap[$cab['estado']]??'').'">'.strtoupper($cab['estado']).'</span></div>';
        echo '</div>';
        echo '<div class="row mb-3 pt-2" style="border-top:1px solid #f0f0f0;">';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-calendar-alt mr-1"></i>Fecha</small>'.date('d/m/Y H:i',strtotime($cab['fecha'])).'</div>';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-user-tie mr-1"></i>Registrado por</small>'.htmlspecialchars($cab['nombre_completo']).'</div>';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-money-bill-wave mr-1"></i>Metodo pago</small>'.ucfirst($cab['metodo_pago']).'</div>';
        echo '</div>';

        echo '<h6 class="font-weight-bold text-muted mb-2" style="font-size:.82rem;"><i class="fas fa-shopping-cart mr-1"></i>PRODUCTOS VENDIDOS</h6>';
        echo '<div class="table-responsive mb-3"><table class="table table-sm table-bordered" style="font-size:.83rem;">';
        echo '<thead style="background:#1a7a4a;color:#fff;"><tr><th><i class="fas fa-pills mr-1"></i>Producto</th><th><i class="fas fa-tag mr-1"></i>Lote</th><th><i class="fas fa-calendar-times mr-1"></i>Vence</th><th class="text-center"><i class="fas fa-sort-numeric-up mr-1"></i>Cant.</th><th class="text-right"><i class="fas fa-dollar-sign mr-1"></i>P.Venta</th><th class="text-right"><i class="fas fa-calculator mr-1"></i>Subtotal</th></tr></thead><tbody>';
        foreach ($items as $it) {
            $vence = $it['fecha_vencimiento'] ? date('d/m/Y',strtotime($it['fecha_vencimiento'])) : '---';
            $hoy = new DateTime(); $fv = new DateTime($it['fecha_vencimiento']);
            $dias = (int)$hoy->diff($fv)->days * ($fv >= $hoy ? 1 : -1);
            $av = $dias < 0 ? '<span class="alerta-vencido ml-1">VENCIDO</span>' : ($dias <= 90 ? '<span class="alerta-vence-pronto ml-1">'.(string)$dias.'d</span>' : '');
            echo '<tr><td><strong>'.htmlspecialchars($it['nombre_producto']).'</strong><br><small class="text-muted">'.htmlspecialchars($it['codigo']).'</small></td>';
            echo '<td><code style="background:#e0f7fa;color:#117a8b;padding:2px 5px;border-radius:3px;">'.htmlspecialchars($it['codigo_lote']).'</code></td>';
            echo '<td>'.(string)$vence.(string)$av.'</td><td class="text-center">'.(string)$it['cantidad'].'</td>';
            echo '<td class="text-right">S/. '.number_format($it['precio_unitario'],2).'</td>';
            echo '<td class="text-right font-weight-bold">S/. '.number_format($it['subtotal'],2).'</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="d-flex justify-content-end mb-3">';
        echo '<div style="background:#f8f9fa;border-radius:10px;padding:14px 20px;border:1px solid #e9ecef;min-width:260px;">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:.87rem;color:#555;border-bottom:1px solid #e9ecef;"><span><i class="fas fa-receipt mr-2 text-muted"></i>Subtotal</span><strong style="font-family:monospace;">S/. '.number_format($cab['subtotal'],2).'</strong></div>';
        if ($cab['descuento']>0) { echo '<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:.87rem;color:#555;border-bottom:1px solid #e9ecef;"><span><i class="fas fa-tag mr-2 text-muted"></i>Descuento</span><strong style="font-family:monospace;color:#e74c3c;">- S/. '.number_format($cab['descuento'],2).'</strong></div>'; }
        if ($cab['aplica_igv']) { echo '<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:.87rem;color:#555;border-bottom:1px solid #e9ecef;"><span><i class="fas fa-percentage mr-2 text-muted"></i>IGV 18%</span><strong style="font-family:monospace;">S/. '.number_format($cab['igv'],2).'</strong></div>'; }
        echo '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0 3px;font-size:1rem;font-weight:700;"><span style="color:#1a7a4a;"><i class="fas fa-check-circle mr-2"></i>TOTAL</span><span style="color:#1a7a4a;font-size:1.15rem;font-family:monospace;">S/. '.number_format($cab['total'],2).'</span></div>';
        echo '</div></div>';

        if ($cab['tipo_pago']==='credito' && !empty($cuotas)) {
            $eC=['pendiente'=>'badge-hist-vta-pendiente','pagado'=>'badge-hist-vta-pagado','vencido'=>'badge-hist-vta-anulado'];
            echo '<h6 class="font-weight-bold text-muted mb-2" style="font-size:.82rem;"><i class="fas fa-calendar-alt mr-1"></i>CRONOGRAMA DE CUOTAS</h6>';
            echo '<div class="table-responsive mb-3"><table class="table table-sm table-bordered" style="font-size:.83rem;">';
            echo '<thead style="background:#e67e22;color:#fff;"><tr><th class="text-center">Cuota</th><th class="text-right">Monto</th><th class="text-center">Vencimiento</th><th class="text-center">Estado</th></tr></thead><tbody>';
            foreach ($cuotas as $cq) {
                echo '<tr><td class="text-center font-weight-bold">'.(string)$cq['numero_cuota'].' / '.count($cuotas).'</td>';
                echo '<td class="text-right font-weight-bold">S/. '.number_format($cq['monto_cuota'],2).'</td>';
                echo '<td class="text-center">'.date('d/m/Y',strtotime($cq['fecha_vencimiento'])).'</td>';
                echo '<td class="text-center"><span class="'.($eC[$cq['estado']]??'').'">'.strtoupper($cq['estado']).'</span></td></tr>';
            }
            echo '</tbody></table></div>';
            if ($cab['saldo_pendiente']>0) echo '<div class="saldo-alerta"><i class="fas fa-exclamation-circle mr-2" style="color:#e67e22;"></i><strong>Saldo pendiente: S/. '.number_format($cab['saldo_pendiente'],2).'</strong></div>';
        }

        if (!empty($pagos)) {
            echo '<h6 class="font-weight-bold text-muted mb-2 mt-3" style="font-size:.82rem;"><i class="fas fa-money-bill-wave mr-1"></i>PAGOS REALIZADOS</h6>';
            echo '<div class="table-responsive"><table class="table table-sm table-bordered" style="font-size:.83rem;">';
            echo '<thead style="background:#1a7a4a;color:#fff;"><tr><th>Fecha</th><th>Metodo</th><th class="text-right">Monto</th><th>Registrado por</th></tr></thead><tbody>';
            foreach ($pagos as $pg) {
                echo '<tr><td>'.date('d/m/Y H:i',strtotime($pg['fecha'])).'</td><td>'.strtoupper($pg['metodo_pago']).'</td>';
                echo '<td class="text-right font-weight-bold text-success">S/. '.number_format($pg['monto'],2).'</td>';
                echo '<td>'.htmlspecialchars($pg['nombre_completo']).'</td></tr>';
            }
            echo '</tbody></table></div>';
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Error: '.htmlspecialchars($e->getMessage()).'</div>';
    }
    exit;
}

// POST: registrar pago y anular
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'registrar_pago') {
        $id_venta    = (int)($_POST['id_venta']      ?? 0);
        $monto       = (float)($_POST['monto_pago']  ?? 0);
        $metodo_pago = $_POST['metodo_pago_abono']   ?? 'efectivo';
        $observacion = trim($_POST['obs_pago']        ?? '') ?: null;
        if (!$id_venta) redirigirHistVta('warning','Error','Venta no identificada.');
        if ($monto <= 0) redirigirHistVta('warning','Monto invalido','El monto debe ser mayor a 0.');
        try {
            $pdo->beginTransaction();
            $stV = $pdo->prepare("SELECT total,saldo_pendiente,estado FROM ventas WHERE id_venta=?"); $stV->execute([$id_venta]); $ven = $stV->fetch();
            if (!$ven || $ven['estado']==='anulado') throw new Exception('La venta no existe o esta anulada.');
            if ($ven['saldo_pendiente'] <= 0) throw new Exception('Esta venta ya esta completamente pagada.');
            if ($monto > $ven['saldo_pendiente'] + 0.01) throw new Exception('El monto (S/. '.number_format($monto,2).') supera el saldo pendiente (S/. '.number_format($ven['saldo_pendiente'],2).').');
            $pdo->prepare("INSERT INTO pagos_venta (id_venta,id_usuario,metodo_pago,monto,observacion) VALUES (?,?,?,?,?)")->execute([$id_venta,$id_usuario,$metodo_pago,$monto,$observacion]);
            $nuevo_saldo  = round($ven['saldo_pendiente'] - $monto, 2);
            $nuevo_estado = ($nuevo_saldo <= 0) ? 'pagado' : 'pendiente';
            $pdo->prepare("UPDATE ventas SET saldo_pendiente=?,estado=? WHERE id_venta=?")->execute([$nuevo_saldo,$nuevo_estado,$id_venta]);
            if ($nuevo_saldo <= 0) {
                try { $pdo->prepare("UPDATE cuotas_venta SET estado='pagado' WHERE id_venta=? AND estado!='pagado'")->execute([$id_venta]); } catch(PDOException $e){}
            } else {
                $totalPagado = round($ven['total'] - $nuevo_saldo, 2);
                try {
                    $stCq = $pdo->prepare("SELECT id_cuota,monto_cuota FROM cuotas_venta WHERE id_venta=? ORDER BY numero_cuota"); $stCq->execute([$id_venta]);
                    $acum = 0;
                    foreach ($stCq->fetchAll() as $cq) {
                        $acum += $cq['monto_cuota'];
                        if ($acum <= $totalPagado) $pdo->prepare("UPDATE cuotas_venta SET estado='pagado' WHERE id_cuota=?")->execute([$cq['id_cuota']]);
                    }
                } catch(PDOException $e){}
            }
            $pdo->commit();
            $msg = $nuevo_saldo<=0 ? "Venta #$id_venta completamente pagada." : "Pago de S/. ".number_format($monto,2)." registrado. Saldo: S/. ".number_format($nuevo_saldo,2).".";
            redirigirHistVta('success','Pago registrado!',$msg);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            redirigirHistVta('error','Error al registrar pago',$e->getMessage());
        }

    } elseif ($accion === 'anular') {
        $id_venta = (int)($_POST['id_venta'] ?? 0);
        if (!$id_venta) redirigirHistVta('warning','ID invalido','No se pudo identificar la venta.');
        try {
            $pdo->beginTransaction();
            $stV = $pdo->prepare("SELECT estado FROM ventas WHERE id_venta=?"); $stV->execute([$id_venta]); $ven = $stV->fetch();
            if (!$ven || $ven['estado']==='anulado') { $pdo->rollBack(); redirigirHistVta('warning','No se puede anular','La venta ya esta anulada.'); }
            $det = $pdo->prepare("SELECT id_producto,id_lote,cantidad FROM detalle_venta WHERE id_venta=?"); $det->execute([$id_venta]);
            foreach ($det->fetchAll() as $d) {
                $pdo->prepare("UPDATE lotes    SET stock_actual=stock_actual+? WHERE id_lote=?")->execute([$d['cantidad'],$d['id_lote']]);
                $pdo->prepare("UPDATE productos SET stock=stock+? WHERE id_producto=?")->execute([$d['cantidad'],$d['id_producto']]);
            }
            $pdo->prepare("UPDATE ventas SET estado='anulado',saldo_pendiente=0 WHERE id_venta=?")->execute([$id_venta]);
            try { $pdo->prepare("UPDATE cuotas_venta SET estado='pagado' WHERE id_venta=?")->execute([$id_venta]); } catch(PDOException $e){}
            $pdo->commit();
            redirigirHistVta('info','Venta anulada',"La venta #$id_venta fue anulada y el stock fue revertido.");
        } catch (PDOException $e) {
            $pdo->rollBack();
            redirigirHistVta('error','Error al anular','Error: '.$e->getMessage());
        }
    }
}

// DATOS
$ventas = [];
$stats = ['total'=>0,'monto'=>0,'pendientes'=>0,'anuladas'=>0,'creditos'=>0];
try {
    $ventas = $pdo->query("SELECT v.id_venta, v.fecha, v.tipo_comprobante, v.numero_comprobante,
        v.total, v.tipo_pago, v.metodo_pago, v.estado, v.saldo_pendiente,
        CONCAT(c.nombres,' ',c.apellido_paterno) AS nombre_cliente, u.nombre_completo,
        (SELECT COUNT(*) FROM cuotas_venta cv WHERE cv.id_venta=v.id_venta AND cv.estado='pendiente') AS cuotas_pendientes,
        (SELECT COUNT(*) FROM cuotas_venta cv WHERE cv.id_venta=v.id_venta AND cv.estado='vencido')   AS cuotas_vencidas,
        (SELECT COUNT(*) FROM cuotas_venta cv WHERE cv.id_venta=v.id_venta) AS total_cuotas
        FROM ventas v
        JOIN clientes c ON v.id_cliente=c.id_cliente
        JOIN usuarios u ON v.id_usuario=u.id_usuario
        ORDER BY v.fecha DESC")->fetchAll();

    $stats['total']     = count($ventas);
    $stats['monto']     = array_sum(array_column(array_filter($ventas, fn($v) => $v['estado']!=='anulado'), 'total'));
    $stats['pendientes']= count(array_filter($ventas, fn($v) => $v['estado']==='pendiente'));
    $stats['anuladas']  = count(array_filter($ventas, fn($v) => $v['estado']==='anulado'));
    $stats['creditos']  = count(array_filter($ventas, fn($v) => $v['tipo_pago']==='credito' && $v['estado']!=='anulado'));
} catch (PDOException $e) {
    $swal = ['icon'=>'error','title'=>'Error','text'=>'Error al cargar datos: '.$e->getMessage()];
}

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<link rel="stylesheet" href="css/historial_ventas.css">

<div class="content-wrapper">
<div class="content-header"><div class="container-fluid">
<div class="page-header-hist-vta d-flex justify-content-between align-items-center flex-wrap">
<div>
<h4><i class="fas fa-history mr-2"></i>Historial de Ventas</h4>
<small><i class="fas fa-map-marker-alt mr-1"></i>Botica 2026 &rsaquo; Transacciones &rsaquo; Historial de Ventas</small>
</div>
<a href="/botica-2026/modules/transacciones/ventas.php" class="btn btn-light font-weight-bold btn-sm">
<i class="fas fa-plus-circle mr-1"></i>Nueva Venta
</a>
</div>
</div></div>

<div class="content"><div class="container-fluid">

<!-- STATS -->
<div class="row mb-4">
<div class="col-md-3 col-6 mb-2">
<div class="stat-mini-hist-vta" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);">
<i class="fas fa-shopping-cart"></i>
<div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total ventas</div></div>
</div>
</div>
<div class="col-md-3 col-6 mb-2">
<div class="stat-mini-hist-vta" style="background:linear-gradient(135deg,#1a5276,#2980b9);">
<i class="fas fa-money-bill-wave"></i>
<div><div class="stat-value">S/. <?= number_format($stats['monto'],0) ?></div><div class="stat-label">Monto total</div></div>
</div>
</div>
<div class="col-md-3 col-6 mb-2">
<div class="stat-mini-hist-vta" style="background:linear-gradient(135deg,#7d6608,#f39c12);">
<i class="fas fa-clock"></i>
<div><div class="stat-value"><?= $stats['pendientes'] ?></div><div class="stat-label">Creditos pendientes</div></div>
</div>
</div>
<div class="col-md-3 col-6 mb-2">
<div class="stat-mini-hist-vta" style="background:linear-gradient(135deg,#922b21,#e74c3c);">
<i class="fas fa-ban"></i>
<div><div class="stat-value"><?= $stats['anuladas'] ?></div><div class="stat-label">Anuladas</div></div>
</div>
</div>
</div>

<?php if ($swal): ?><script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({icon:'<?= $swal['icon'] ?>',title:'<?= addslashes($swal['title']) ?>',text:'<?= addslashes($swal['text']) ?>',confirmButtonColor:'#1a7a4a',timer:<?= in_array($swal['icon'],['success','info'])?3500:0 ?>,timerProgressBar:<?= in_array($swal['icon'],['success','info'])?'true':'false' ?>,showConfirmButton:<?= in_array($swal['icon'],['success','info'])?'false':'true' ?>,});});</script><?php endif; ?>

<!-- FILTROS RAPIDOS -->
<div class="filtros-card d-flex align-items-center gap-3 flex-wrap">
<span style="font-weight:700;font-size:.85rem;color:#495057;"><i class="fas fa-filter mr-1"></i>Filtrar:</span>
<button class="btn btn-sm btn-filtro-estado active" data-estado="todos" style="border-radius:20px;border:2px solid #1a7a4a;background:#1a7a4a;color:#fff;font-weight:600;padding:4px 14px;">Todos</button>
<button class="btn btn-sm btn-filtro-estado" data-estado="pagado" style="border-radius:20px;border:2px solid #27ae60;color:#27ae60;font-weight:600;padding:4px 14px;">Pagados</button>
<button class="btn btn-sm btn-filtro-estado" data-estado="pendiente" style="border-radius:20px;border:2px solid #e67e22;color:#e67e22;font-weight:600;padding:4px 14px;">Pendientes</button>
<button class="btn btn-sm btn-filtro-estado" data-estado="anulado" style="border-radius:20px;border:2px solid #e74c3c;color:#e74c3c;font-weight:600;padding:4px 14px;">Anulados</button>
</div>

<!-- TABLA -->
<div class="card">
<div class="card-header-hist-vta d-flex align-items-center justify-content-between">
<h6 class="mb-0"><i class="fas fa-list mr-2"></i>Registro de Ventas</h6>
<span class="badge badge-light"><?= count($ventas) ?> registros</span>
</div>
<div class="card-body">
<div class="table-responsive">
<table id="tablaHistorialVentas" class="table table-hist-vta table-bordered table-hover table-sm">
<thead><tr>
<th style="width:55px;">#</th>
<th style="width:120px;">Fecha</th>
<th>Cliente</th>
<th style="width:130px;">Comprobante</th>
<th style="width:90px;" class="text-center">T. Pago</th>
<th style="width:80px;" class="text-center">Metodo</th>
<th style="width:110px;" class="text-right">Total</th>
<th style="width:95px;" class="text-center">Estado</th>
<th style="width:120px;" class="text-center">Acciones</th>
</tr></thead>
<tbody>
<?php foreach ($ventas as $v): ?>
<tr class="<?= $v['estado']==='anulado'?'anulada':'' ?>">
<td class="text-center font-weight-bold" style="color:#1a7a4a;">#<?= $v['id_venta'] ?></td>
<td style="font-size:.82rem;"><?= date('d/m/Y H:i',strtotime($v['fecha'])) ?></td>
<td>
<div style="font-weight:600;font-size:.88rem;"><?= htmlspecialchars($v['nombre_cliente']) ?></div>
<div style="font-size:.75rem;color:#999;"><?= htmlspecialchars($v['nombre_completo']) ?></div>
</td>
<td style="font-size:.82rem;">
<?= htmlspecialchars(ucfirst($v['tipo_comprobante'])) ?>
<?php if ($v['numero_comprobante']): ?><br><code style="font-size:.75rem;"><?= htmlspecialchars($v['numero_comprobante']) ?></code><?php endif; ?>
</td>
<td class="text-center">
<?php if ($v['tipo_pago']==='credito'): ?>
<span class="badge-credito-vta"><i class="fas fa-calendar-alt mr-1"></i>CREDITO</span>
<?php if ($v['cuotas_vencidas']>0): ?><div style="font-size:.7rem;color:#e74c3c;font-weight:600;margin-top:2px;"><i class="fas fa-exclamation-triangle mr-1"></i><?= $v['cuotas_vencidas'] ?> vencida(s)</div>
<?php elseif ($v['cuotas_pendientes']>0): ?><div style="font-size:.7rem;color:#e67e22;font-weight:600;margin-top:2px;"><?= $v['cuotas_pendientes'] ?>/<?= $v['total_cuotas'] ?> pendiente(s)</div><?php endif; ?>
<?php else: ?><span class="badge-contado-vta"><i class="fas fa-money-bill-wave mr-1"></i>CONTADO</span><?php endif; ?>
</td>
<td class="text-center" style="font-size:.8rem;text-transform:capitalize;"><?= htmlspecialchars($v['metodo_pago']) ?></td>
<td class="text-right font-weight-bold" style="color:#1a7a4a;">
S/. <?= number_format($v['total'],2) ?>
<?php if ($v['saldo_pendiente']>0): ?><div style="font-size:.72rem;color:#e67e22;font-weight:600;">Saldo: S/. <?= number_format($v['saldo_pendiente'],2) ?></div><?php endif; ?>
</td>
<td class="text-center">
<?php $bMap=['pagado'=>'badge-hist-vta-pagado','pendiente'=>'badge-hist-vta-pendiente','anulado'=>'badge-hist-vta-anulado']; $iMap=['pagado'=>'fa-check-circle','pendiente'=>'fa-clock','anulado'=>'fa-ban']; ?>
<span class="<?= $bMap[$v['estado']]??'' ?>"><i class="fas <?= $iMap[$v['estado']]??'' ?> mr-1"></i><?= strtoupper($v['estado']) ?></span>
</td>
<td class="text-center">
<button class="btn btn-sm btn-info btn-ver-venta" title="Ver detalle" data-id="<?= $v['id_venta'] ?>"><i class="fas fa-eye"></i></button>
<?php if ($v['estado'] !== 'anulado'): ?>
<a href="/botica-2026/modules/Comprobantes/imprimir.php?tipo=venta&id=<?= $v['id_venta'] ?>"
   target="_blank" class="btn btn-sm btn-secondary" title="Imprimir comprobante">
    <i class="fas fa-print"></i>
</a>
<?php endif; ?>
<?php if ($v['estado']==='pendiente' && $v['tipo_pago']==='credito'): ?>
<button class="btn btn-sm btn-success btn-pagar-venta" title="Registrar pago"
data-id="<?= $v['id_venta'] ?>"
data-saldo="<?= $v['saldo_pendiente'] ?>"
data-cliente="<?= htmlspecialchars($v['nombre_cliente'],ENT_QUOTES) ?>"
data-numero="<?= htmlspecialchars(ucfirst($v['tipo_comprobante']).' '.($v['numero_comprobante']??'#'.$v['id_venta']),ENT_QUOTES) ?>">
<i class="fas fa-dollar-sign"></i>
</button>
<?php endif; ?>
<?php if ($v['estado']!=='anulado'): ?>
<button class="btn btn-sm btn-danger btn-anular-venta" title="Anular"
data-id="<?= $v['id_venta'] ?>"
data-numero="<?= htmlspecialchars(ucfirst($v['tipo_comprobante']).' '.($v['numero_comprobante']??'#'.$v['id_venta']),ENT_QUOTES) ?>">
<i class="fas fa-ban"></i>
</button>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>

</div></div>
</div>

<!-- MODAL VER DETALLE -->
<div class="modal fade" id="modalVerVenta" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg"><div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a7a4a,#27ae60);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
<h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-file-invoice-dollar mr-2"></i>Detalle de Venta</h6>
<button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-4"><!-- AJAX --></div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cerrar</button>
</div>
</div></div></div>

<!-- MODAL REGISTRAR PAGO -->
<div class="modal fade" id="modalPagarVenta" tabindex="-1" aria-hidden="true">
<div class="modal-dialog"><div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a7a4a,#27ae60);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
<div>
<h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-dollar-sign mr-2"></i>Registrar Pago</h6>
<small id="pago_cliente_label" style="color:rgba(255,255,255,.85);font-size:.82rem;"></small>
</div>
<button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<form method="POST" id="formRegistrarPagoVta">
<input type="hidden" name="accion" value="registrar_pago">
<input type="hidden" name="id_venta" id="pago_id_venta">
<div class="modal-body p-4">
<div class="saldo-alerta mb-3">
<div style="font-size:.8rem;color:#999;text-transform:uppercase;font-weight:600;">Saldo pendiente</div>
<div id="pago_saldo_display" style="font-size:1.4rem;font-weight:700;color:#e67e22;"></div>
</div>
<div class="form-group">
<label style="font-weight:600;font-size:.83rem;">Monto a pagar <span class="text-danger">*</span></label>
<div class="input-group input-group-sm">
<div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
<input type="number" step="0.01" min="0.01" class="form-control" name="monto_pago" id="monto_pago" placeholder="0.00">
</div>
<small class="text-muted">Puede ser pago parcial o total del saldo</small>
</div>
<div class="form-group">
<label style="font-weight:600;font-size:.83rem;">Metodo de Pago</label>
<div class="d-flex gap-2 flex-wrap">
<button type="button" class="btn-metodo-pago-vta" data-metodo="efectivo"><i class="fas fa-money-bill-wave mr-1"></i>Efectivo</button>
<button type="button" class="btn-metodo-pago-vta" data-metodo="yape"><i class="fas fa-mobile-alt mr-1"></i>Yape</button>
<button type="button" class="btn-metodo-pago-vta" data-metodo="plin"><i class="fas fa-mobile-alt mr-1"></i>Plin</button>
<button type="button" class="btn-metodo-pago-vta" data-metodo="transferencia"><i class="fas fa-university mr-1"></i>Transferencia</button>
<button type="button" class="btn-metodo-pago-vta" data-metodo="tarjeta"><i class="fas fa-credit-card mr-1"></i>Tarjeta</button>
</div>
<input type="hidden" name="metodo_pago_abono" id="metodo_pago_abono" value="efectivo">
</div>
<div class="form-group mb-0">
<label style="font-weight:600;font-size:.83rem;">Observacion</label>
<textarea class="form-control form-control-sm" name="obs_pago" id="obs_pago" rows="2" placeholder="Notas del pago (opcional)..." maxlength="150"></textarea>
</div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
<button type="submit" class="btn btn-sm font-weight-bold" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);color:#fff;"><i class="fas fa-check mr-1"></i>Registrar Pago</button>
</div>
</form>
</div></div></div>

<?php include $ruta_base . 'includes/footer.php'; ?>
<script src="js/historial_ventas.js"></script>


