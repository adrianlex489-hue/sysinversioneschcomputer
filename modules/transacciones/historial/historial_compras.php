<?php
// ============================================================
// modules/transacciones/historial/historial_compras.php
// Historial de Compras | SysInversiones 2026
// BD: compras + detalle_compra + pagos_compra + cuotas_compra
// ============================================================
$ruta_base = '../../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexion BD no disponible.');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR_COMERCIAL'))        define('ROL_ASESOR_COMERCIAL', 2);
if (!defined('ROL_TECNICO'))    define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);
verificarPermiso($pdo, 'historial_compras');

$id_usuario = $_SESSION['id_usuario'] ?? 0;

$swal = null;
if (isset($_SESSION['swal_hist'])) { $swal = $_SESSION['swal_hist']; unset($_SESSION['swal_hist']); }

function redirigirHist(string $icon, string $title, string $text): void {
    $_SESSION['swal_hist'] = compact('icon', 'title', 'text');
    header('Location: historial_compras.php'); exit;
}

// Actualizar cuotas vencidas
try {
    $pdo->exec("UPDATE cuotas_compra SET estado='vencido' WHERE estado='pendiente' AND fecha_vencimiento < CURDATE()");
} catch (PDOException $e) {}

// -- AJAX: cuotas de una compra (para modal de pago) --------------------------
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

// -- AJAX: detalle de compra ---------------------------------------------------
if (isset($_GET['accion']) && $_GET['accion'] === 'detalle_ajax') {
    $id = (int)($_GET['id_compra'] ?? 0);
    try {
        $stmt = $pdo->prepare(
            "SELECT c.*, p.razon_social, u.nombre_completo
             FROM compras c
             JOIN proveedores p ON c.id_proveedor = p.id_proveedor
             JOIN usuarios    u ON c.id_usuario   = u.id_usuario
             WHERE c.id_compra = ?"
        );
        $stmt->execute([$id]);
        $cab = $stmt->fetch();
        if (!$cab) { echo '<div class="alert alert-warning">Compra no encontrada.</div>'; exit; }

        $det = $pdo->prepare(
            "SELECT dc.*, pr.nombre_producto, pr.codigo
             FROM detalle_compra dc
             JOIN productos pr ON dc.id_producto = pr.id_producto
             WHERE dc.id_compra = ?"
        );
        $det->execute([$id]);
        $items = $det->fetchAll();

        $cuotas = []; $pagos = [];
        try {
            $stC = $pdo->prepare("SELECT * FROM cuotas_compra WHERE id_compra=? ORDER BY numero_cuota");
            $stC->execute([$id]); $cuotas = $stC->fetchAll();
        } catch (PDOException $e) {}
        try {
            $stP = $pdo->prepare(
                "SELECT pc.*, u.nombre_completo AS registrado_por
                 FROM pagos_compra pc
                 LEFT JOIN usuarios u ON pc.id_usuario = u.id_usuario
                 WHERE pc.id_compra = ? ORDER BY pc.fecha"
            );
            $stP->execute([$id]); $pagos = $stP->fetchAll();
        } catch (PDOException $e) {
            // Si id_usuario no existe a�n en pagos_compra
            $stP2 = $pdo->prepare("SELECT * FROM pagos_compra WHERE id_compra=? ORDER BY fecha");
            $stP2->execute([$id]); $pagos = $stP2->fetchAll();
        }

        $tipoLabel = ['ticket' => 'Ticket de Compra', 'nota' => 'Nota de Compra'];
        $bMap = ['pagado'=>'badge-hist-pagado','pendiente'=>'badge-hist-pendiente','anulado'=>'badge-hist-anulado'];

        // Cabecera
        echo '<div class="row mb-2">';
        echo '<div class="col-md-5"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-building mr-1"></i>Proveedor</small><strong>'.htmlspecialchars($cab['razon_social']).'</strong></div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-file-invoice mr-1"></i>Comprobante</small><strong>'.htmlspecialchars($tipoLabel[$cab['tipo_comprobante']] ?? ucfirst($cab['tipo_comprobante'])).'</strong><br><code style="font-size:.8rem;">'.htmlspecialchars($cab['numero_comprobante'] ?? '---').'</code></div>';
        echo '<div class="col-md-2"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-credit-card mr-1"></i>T. Pago</small>';
        echo $cab['tipo_pago']==='credito'
            ? '<span class="badge-credito"><i class="fas fa-calendar-alt mr-1"></i>CR�DITO</span>'
            : '<span class="badge-contado"><i class="fas fa-money-bill-wave mr-1"></i>CONTADO</span>';
        echo '</div>';
        echo '<div class="col-md-2"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-circle mr-1"></i>Estado</small><span class="'.($bMap[$cab['estado']]??'').'">'.strtoupper($cab['estado']).'</span></div>';
        echo '</div>';
        echo '<div class="row mb-3 pt-2" style="border-top:1px solid #f0f0f0;">';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-calendar-alt mr-1"></i>Fecha</small>'.date('d/m/Y H:i', strtotime($cab['fecha'])).'</div>';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-user mr-1"></i>Registrado por</small>'.htmlspecialchars($cab['nombre_completo']).'</div>';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-money-bill-wave mr-1"></i>M�todo de pago</small>'.ucfirst($cab['metodo_pago']).'</div>';
        echo '</div>';

        // Productos
        echo '<h6 class="font-weight-bold text-muted mb-2" style="font-size:.82rem;"><i class="fas fa-boxes mr-1"></i>PRODUCTOS</h6>';
        echo '<div class="table-responsive mb-3"><table class="table table-sm table-bordered" style="font-size:.83rem;">';
        echo '<thead style="background:#1a5276;color:#fff;"><tr><th>Producto</th><th class="text-center">Cant.</th><th class="text-right">P.Compra</th><th class="text-right">Descuento</th><th class="text-right">Subtotal</th></tr></thead><tbody>';
        foreach ($items as $it) {
            echo '<tr>';
            echo '<td><strong>'.htmlspecialchars($it['nombre_producto']).'</strong><br><small class="text-muted"><code style="font-size:.72rem;">'.htmlspecialchars($it['codigo']).'</code></small></td>';
            echo '<td class="text-center">'.$it['cantidad'].'</td>';
            echo '<td class="text-right">S/. '.number_format($it['precio_compra'],2).'</td>';
            echo '<td class="text-right">'.($it['descuento']>0 ? 'S/. '.number_format($it['descuento'],2) : '---').'</td>';
            echo '<td class="text-right font-weight-bold">S/. '.number_format($it['subtotal'],2).'</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';

        // Totales
        echo '<div class="d-flex justify-content-end mb-3">';
        echo '<div style="background:#f8f9fa;border-radius:10px;padding:14px 20px;border:1px solid #e9ecef;min-width:260px;">';
        echo '<div style="display:flex;justify-content:space-between;padding:5px 0;font-size:.87rem;color:#555;border-bottom:1px solid #e9ecef;"><span><i class="fas fa-receipt mr-2 text-muted"></i>Subtotal</span><strong style="font-family:monospace;">S/. '.number_format($cab['subtotal'],2).'</strong></div>';
        if ($cab['descuento'] > 0) {
            echo '<div style="display:flex;justify-content:space-between;padding:5px 0;font-size:.87rem;color:#555;border-bottom:1px solid #e9ecef;"><span><i class="fas fa-tag mr-2 text-muted"></i>Descuento</span><strong style="font-family:monospace;color:#e74c3c;">- S/. '.number_format($cab['descuento'],2).'</strong></div>';
        }
        if ($cab['aplica_igv']) {
            echo '<div style="display:flex;justify-content:space-between;padding:5px 0;font-size:.87rem;color:#555;border-bottom:1px solid #e9ecef;"><span><i class="fas fa-percentage mr-2 text-muted"></i>IGV 18%</span><strong style="font-family:monospace;">S/. '.number_format($cab['igv'],2).'</strong></div>';
        }
        echo '<div style="display:flex;justify-content:space-between;padding:8px 0 3px;font-size:1rem;font-weight:700;"><span style="color:#1a5276;"><i class="fas fa-check-circle mr-2"></i>TOTAL</span><span style="color:#1a7a4a;font-size:1.15rem;font-family:monospace;">S/. '.number_format($cab['total'],2).'</span></div>';
        if (isset($cab['saldo_pendiente']) && $cab['saldo_pendiente'] > 0) {
            echo '<div style="display:flex;justify-content:space-between;padding:5px 0;font-size:.87rem;border-top:1px solid #ffe0b2;margin-top:4px;"><span style="color:#e67e22;font-weight:600;"><i class="fas fa-exclamation-circle mr-2"></i>Saldo pendiente</span><strong style="font-family:monospace;color:#e67e22;">S/. '.number_format($cab['saldo_pendiente'],2).'</strong></div>';
        }
        echo '</div></div>';

        // Cuotas
        if ($cab['tipo_pago'] === 'credito' && !empty($cuotas)) {
            $eC = ['pendiente'=>'badge-hist-pendiente','pagado'=>'badge-hist-pagado','vencido'=>'badge-hist-anulado'];
            echo '<h6 class="font-weight-bold text-muted mb-2" style="font-size:.82rem;"><i class="fas fa-calendar-alt mr-1"></i>CRONOGRAMA DE CUOTAS</h6>';
            echo '<div class="table-responsive mb-3"><table class="table table-sm table-bordered" style="font-size:.83rem;">';
            echo '<thead style="background:#e67e22;color:#fff;"><tr><th class="text-center">Cuota</th><th class="text-right">Monto</th><th class="text-center">Vencimiento</th><th class="text-center">Estado</th></tr></thead><tbody>';
            foreach ($cuotas as $cq) {
                echo '<tr>';
                echo '<td class="text-center font-weight-bold">'.$cq['numero_cuota'].' / '.count($cuotas).'</td>';
                echo '<td class="text-right font-weight-bold">S/. '.number_format($cq['monto_cuota'],2).'</td>';
                echo '<td class="text-center">'.date('d/m/Y', strtotime($cq['fecha_vencimiento'])).'</td>';
                echo '<td class="text-center"><span class="'.($eC[$cq['estado']]??'').'">'.strtoupper($cq['estado']).'</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }

        // Pagos realizados
        if (!empty($pagos)) {
            echo '<h6 class="font-weight-bold text-muted mb-2 mt-2" style="font-size:.82rem;"><i class="fas fa-money-bill-wave mr-1"></i>PAGOS REALIZADOS</h6>';
            echo '<div class="table-responsive"><table class="table table-sm table-bordered" style="font-size:.83rem;">';
            echo '<thead style="background:#1a7a4a;color:#fff;"><tr><th>Fecha</th><th>M�todo</th><th class="text-right">Monto</th><th>Registrado por</th></tr></thead><tbody>';
            foreach ($pagos as $pg) {
                echo '<tr>';
                echo '<td>'.date('d/m/Y H:i', strtotime($pg['fecha'])).'</td>';
                echo '<td>'.strtoupper($pg['metodo_pago']).'</td>';
                echo '<td class="text-right font-weight-bold text-success">S/. '.number_format($pg['monto'],2).'</td>';
                echo '<td>'.htmlspecialchars($pg['registrado_por'] ?? $pg['nombre_completo'] ?? '---').'</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }

    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Error: '.htmlspecialchars($e->getMessage()).'</div>';
    }
    exit;
}

// -- POST: registrar pago / anular ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // REGISTRAR PAGO (cr�dito)
    if ($accion === 'registrar_pago') {
        $id_compra   = (int)($_POST['id_compra']       ?? 0);
        $monto       = (float)($_POST['monto_pago']    ?? 0);
        $metodo_pago = $_POST['metodo_pago_abono']     ?? 'efectivo';
        $observacion = trim($_POST['obs_pago']          ?? '') ?: null;
        if (!$id_compra) redirigirHist('warning','Error','Compra no identificada.');
        if ($monto <= 0) redirigirHist('warning','Monto inv�lido','El monto debe ser mayor a 0.');
        try {
            $pdo->beginTransaction();
            $stC = $pdo->prepare("SELECT total, saldo_pendiente, estado FROM compras WHERE id_compra=?");
            $stC->execute([$id_compra]); $comp = $stC->fetch();
            if (!$comp || $comp['estado'] === 'anulado') throw new Exception('La compra no existe o est� anulada.');
            if ($comp['saldo_pendiente'] <= 0) throw new Exception('Esta compra ya est� completamente pagada.');
            if ($monto > $comp['saldo_pendiente'] + 0.01) throw new Exception('El monto (S/. '.number_format($monto,2).') supera el saldo pendiente (S/. '.number_format($comp['saldo_pendiente'],2).').');

            $pdo->prepare("INSERT INTO pagos_compra (id_compra, id_usuario, metodo_pago, monto, observacion) VALUES (?,?,?,?,?)")
                ->execute([$id_compra, $id_usuario, $metodo_pago, $monto, $observacion]);

            $nuevo_saldo  = round($comp['saldo_pendiente'] - $monto, 2);
            $nuevo_estado = ($nuevo_saldo <= 0) ? 'pagado' : 'pendiente';
            $pdo->prepare("UPDATE compras SET saldo_pendiente=?, estado=? WHERE id_compra=?")
                ->execute([$nuevo_saldo, $nuevo_estado, $id_compra]);

            if ($nuevo_saldo <= 0) {
                try { $pdo->prepare("UPDATE cuotas_compra SET estado='pagado' WHERE id_compra=? AND estado!='pagado'")->execute([$id_compra]); } catch(PDOException $e){}
            } else {
                $totalPagado = round($comp['total'] - $nuevo_saldo, 2);
                try {
                    $stCq = $pdo->prepare("SELECT id_cuota, monto_cuota FROM cuotas_compra WHERE id_compra=? ORDER BY numero_cuota");
                    $stCq->execute([$id_compra]);
                    $acum = 0;
                    foreach ($stCq->fetchAll() as $cq) {
                        $acum += $cq['monto_cuota'];
                        if ($acum <= $totalPagado) $pdo->prepare("UPDATE cuotas_compra SET estado='pagado' WHERE id_cuota=?")->execute([$cq['id_cuota']]);
                    }
                } catch(PDOException $e){}
            }

            // -- Registrar en caja si hay una abierta --------------------------
            $caja_hc = $pdo->query("SELECT id_caja FROM caja WHERE estado='abierta' ORDER BY fecha_apertura DESC LIMIT 1")->fetch();
            if ($caja_hc) {
                $num_comp = 'COMP-' . str_pad($id_compra, 6, '0', STR_PAD_LEFT);
                $pdo->prepare("INSERT INTO movimientos_caja (id_caja,tipo_referencia,id_referencia,id_usuario,tipo,descripcion,monto,metodo_pago) VALUES (?,'compra',?,?,'egreso',?,?,?)")
                    ->execute([$caja_hc['id_caja'], $id_compra, $id_usuario, "Pago crédito proveedor {$num_comp}", $monto, $metodo_pago]);
            }

            $pdo->commit();
            $msg = $nuevo_saldo <= 0
                ? "Compra #$id_compra completamente pagada."
                : "Pago de S/. ".number_format($monto,2)." registrado. Saldo restante: S/. ".number_format($nuevo_saldo,2).".";
            redirigirHist('success','Pago registrado!', $msg);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            redirigirHist('error','Error al registrar pago', $e->getMessage());
        }

    // ANULAR COMPRA
    } elseif ($accion === 'anular') {
        $id_compra = (int)($_POST['id_compra'] ?? 0);
        if (!$id_compra) redirigirHist('warning','ID inv�lido','No se pudo identificar la compra.');
        try {
            $pdo->beginTransaction();
            $stC = $pdo->prepare("SELECT estado FROM compras WHERE id_compra=?");
            $stC->execute([$id_compra]); $comp = $stC->fetch();
            if (!$comp || $comp['estado'] === 'anulado') {
                $pdo->rollBack();
                redirigirHist('warning','No se puede anular','La compra ya est� anulada o no existe.');
            }
            // Revertir stock
            $det = $pdo->prepare("SELECT id_producto, cantidad FROM detalle_compra WHERE id_compra=?");
            $det->execute([$id_compra]);
            foreach ($det->fetchAll() as $d) {
                $pdo->prepare("UPDATE productos SET stock=GREATEST(0, stock-?) WHERE id_producto=?")
                    ->execute([$d['cantidad'], $d['id_producto']]);
            }
            $pdo->prepare("UPDATE compras SET estado='anulado', saldo_pendiente=0 WHERE id_compra=?")->execute([$id_compra]);
            try { $pdo->prepare("UPDATE cuotas_compra SET estado='pagado' WHERE id_compra=?")->execute([$id_compra]); } catch(PDOException $e){}
            $pdo->commit();
            redirigirHist('info','Compra anulada',"La compra #$id_compra fue anulada y el stock fue revertido.");
        } catch (PDOException $e) {
            $pdo->rollBack();
            redirigirHist('error','Error al anular','Error: '.$e->getMessage());
        }
    }
}

// -- CARGAR DATOS --------------------------------------------------------------
$compras = [];
$stats   = ['total'=>0,'monto'=>0,'pendientes'=>0,'anuladas'=>0];
try {
    $compras = $pdo->query(
        "SELECT c.id_compra, c.fecha, c.tipo_comprobante, c.numero_comprobante,
                c.total, c.tipo_pago, c.metodo_pago, c.estado,
                COALESCE(c.saldo_pendiente, 0) AS saldo_pendiente,
                p.razon_social, u.nombre_completo,
                (SELECT COUNT(*) FROM cuotas_compra cc WHERE cc.id_compra=c.id_compra AND cc.estado='pendiente') AS cuotas_pendientes,
                (SELECT COUNT(*) FROM cuotas_compra cc WHERE cc.id_compra=c.id_compra AND cc.estado='vencido')   AS cuotas_vencidas,
                (SELECT COUNT(*) FROM cuotas_compra cc WHERE cc.id_compra=c.id_compra)                           AS total_cuotas
         FROM compras c
         JOIN proveedores p ON c.id_proveedor = p.id_proveedor
         JOIN usuarios    u ON c.id_usuario   = u.id_usuario
         ORDER BY c.fecha DESC"
    )->fetchAll();

    $stats['total']     = count($compras);
    $stats['monto']     = array_sum(array_column(array_filter($compras, fn($c) => $c['estado'] !== 'anulado'), 'total'));
    $stats['pendientes']= count(array_filter($compras, fn($c) => $c['estado'] === 'pendiente'));
    $stats['anuladas']  = count(array_filter($compras, fn($c) => $c['estado'] === 'anulado'));
} catch (PDOException $e) {
    $swal = ['icon'=>'error','title'=>'Error BD','text'=>$e->getMessage()];
}

$tipoLabel = ['ticket'=>'Ticket','nota'=>'Nota'];

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<link rel="stylesheet" href="css/historial_compras.css">

<div class="content-wrapper">
<div class="content-header"><div class="container-fluid">
<div class="page-header-hist d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h4><i class="fas fa-history mr-2"></i>Historial de Compras</h4>
        <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones &rsaquo; Transacciones &rsaquo; Historial de Compras</small>
    </div>
    <a href="../compras.php" class="btn btn-light font-weight-bold btn-sm">
        <i class="fas fa-plus-circle mr-1"></i>Nueva Compra
    </a>
</div>
</div></div>

<div class="content"><div class="container-fluid">

<?php if ($swal): ?>
<script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({icon:'<?= $swal['icon'] ?>',title:'<?= addslashes($swal['title']) ?>',text:'<?= addslashes($swal['text']) ?>',confirmButtonColor:'#1a5276',timer:<?= in_array($swal['icon'],['success','info'])?3500:0 ?>,timerProgressBar:<?= in_array($swal['icon'],['success','info'])?'true':'false' ?>,showConfirmButton:<?= in_array($swal['icon'],['success','info'])?'false':'true' ?>,});});</script>
<?php endif; ?>

<!-- STATS -->
<div class="row mb-4">
    <div class="col-md-3 col-6 mb-2">
        <div class="stat-mini-hist" style="background:linear-gradient(135deg,#1a5276,#2980b9);">
            <i class="fas fa-shopping-cart"></i>
            <div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total compras</div></div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="stat-mini-hist" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);">
            <i class="fas fa-money-bill-wave"></i>
            <div><div class="stat-value">S/. <?= number_format($stats['monto'],2) ?></div><div class="stat-label">Monto total</div></div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="stat-mini-hist" style="background:linear-gradient(135deg,#7d6608,#f39c12);">
            <i class="fas fa-clock"></i>
            <div><div class="stat-value"><?= $stats['pendientes'] ?></div><div class="stat-label">Pendientes de pago</div></div>
        </div>
    </div>
    <div class="col-md-3 col-6 mb-2">
        <div class="stat-mini-hist" style="background:linear-gradient(135deg,#922b21,#e74c3c);">
            <i class="fas fa-ban"></i>
            <div><div class="stat-value"><?= $stats['anuladas'] ?></div><div class="stat-label">Anuladas</div></div>
        </div>
    </div>
</div>

<!-- FILTROS -->
<div class="filtros-card d-flex align-items-center flex-wrap" style="gap:8px;">
    <span style="font-weight:700;font-size:.85rem;color:#495057;"><i class="fas fa-filter mr-1"></i>Filtrar:</span>
    <button class="btn btn-sm btn-filtro-estado active" data-estado="todos"     style="border-radius:20px;border:2px solid #1a5276;background:#1a5276;color:#fff;font-weight:600;padding:4px 14px;">Todos</button>
    <button class="btn btn-sm btn-filtro-estado"        data-estado="pagado"    style="border-radius:20px;border:2px solid #27ae60;color:#27ae60;font-weight:600;padding:4px 14px;">Pagados</button>
    <button class="btn btn-sm btn-filtro-estado"        data-estado="pendiente" style="border-radius:20px;border:2px solid #e67e22;color:#e67e22;font-weight:600;padding:4px 14px;">Pendientes</button>
    <button class="btn btn-sm btn-filtro-estado"        data-estado="anulado"   style="border-radius:20px;border:2px solid #e74c3c;color:#e74c3c;font-weight:600;padding:4px 14px;">Anulados</button>
</div>

<!-- TABLA -->
<div class="card mt-3">
<div class="card-header-hist d-flex align-items-center justify-content-between flex-wrap" style="gap:8px;">
    <h6 class="mb-0"><i class="fas fa-list mr-2"></i>Registro de Compras</h6>
    <div class="d-flex align-items-center" style="gap:6px;">
        <span class="badge badge-light mr-2"><?= count($compras) ?> registros</span>
        <button type="button" id="btnExportarHCmp"
            style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.4);border-radius:6px;font-size:.8rem;font-weight:600;padding:4px 12px;cursor:pointer;transition:background .2s;">
            <i class="fas fa-download mr-1"></i>Exportar
        </button>
    </div>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table id="tablaHistorial" class="table table-hist table-bordered table-hover table-sm mb-0">
<thead><tr>
    <th style="width:55px;">#</th>
    <th style="width:130px;">Fecha</th>
    <th>Proveedor</th>
    <th style="width:140px;">Comprobante</th>
    <th style="width:100px;" class="text-center">T. Pago</th>
    <th style="width:90px;"  class="text-center">M�todo</th>
    <th style="width:120px;" class="text-right">Total</th>
    <th style="width:100px;" class="text-center">Estado</th>
    <th style="width:120px;" class="text-center">Acciones</th>
</tr></thead>
<tbody>
<?php foreach ($compras as $c):
    $bMap = ['pagado'=>'badge-hist-pagado','pendiente'=>'badge-hist-pendiente','anulado'=>'badge-hist-anulado'];
    $iMap = ['pagado'=>'fa-check-circle','pendiente'=>'fa-clock','anulado'=>'fa-ban'];
?>
<tr class="fila-estado-<?= $c['estado'] ?> <?= $c['estado']==='anulado'?'anulada':'' ?>">
    <td class="text-center font-weight-bold" style="color:#1a5276;">#<?= $c['id_compra'] ?></td>
    <td style="font-size:.82rem;"><?= date('d/m/Y H:i', strtotime($c['fecha'])) ?></td>
    <td>
        <div style="font-weight:600;font-size:.88rem;"><?= htmlspecialchars($c['razon_social']) ?></div>
        <div style="font-size:.75rem;color:#999;"><i class="fas fa-user mr-1"></i><?= htmlspecialchars($c['nombre_completo']) ?></div>
    </td>
    <td style="font-size:.82rem;">
        <span style="font-weight:600;"><?= htmlspecialchars($tipoLabel[$c['tipo_comprobante']] ?? ucfirst($c['tipo_comprobante'])) ?></span>
        <?php if ($c['numero_comprobante']): ?>
        <br><code style="font-size:.75rem;background:#e3f2fd;color:#1a5276;padding:1px 5px;border-radius:3px;"><?= htmlspecialchars($c['numero_comprobante']) ?></code>
        <?php endif; ?>
    </td>
    <td class="text-center">
        <?php if ($c['tipo_pago'] === 'credito'): ?>
            <span class="badge-credito"><i class="fas fa-calendar-alt mr-1"></i>CR�DITO</span>
            <?php if ($c['cuotas_vencidas'] > 0): ?>
                <div style="font-size:.7rem;color:#e74c3c;font-weight:600;margin-top:2px;"><i class="fas fa-exclamation-triangle mr-1"></i><?= $c['cuotas_vencidas'] ?> vencida(s)</div>
            <?php elseif ($c['cuotas_pendientes'] > 0): ?>
                <div style="font-size:.7rem;color:#e67e22;font-weight:600;margin-top:2px;"><?= $c['cuotas_pendientes'] ?>/<?= $c['total_cuotas'] ?> pendiente(s)</div>
            <?php endif; ?>
        <?php else: ?>
            <span class="badge-contado"><i class="fas fa-money-bill-wave mr-1"></i>CONTADO</span>
        <?php endif; ?>
    </td>
    <td class="text-center" style="font-size:.82rem;text-transform:capitalize;"><?= htmlspecialchars($c['metodo_pago']) ?></td>
    <td class="text-right font-weight-bold" style="color:#1a7a4a;">
        S/. <?= number_format($c['total'],2) ?>
        <?php if ($c['saldo_pendiente'] > 0): ?>
        <div style="font-size:.72rem;color:#e67e22;font-weight:600;">Saldo: S/. <?= number_format($c['saldo_pendiente'],2) ?></div>
        <?php endif; ?>
    </td>
    <td class="text-center">
        <span class="<?= $bMap[$c['estado']] ?? '' ?>">
            <i class="fas <?= $iMap[$c['estado']] ?? '' ?> mr-1"></i><?= strtoupper($c['estado']) ?>
        </span>
    </td>
    <td class="text-center" style="white-space:nowrap;">
        <button class="btn btn-sm btn-info btn-ver-compra" title="Ver detalle" data-id="<?= $c['id_compra'] ?>">
            <i class="fas fa-eye"></i>
        </button>
        <button class="btn btn-sm btn-ver-ticket" title="Ver comprobante PDF"
            data-id="<?= $c['id_compra'] ?>"
            data-tipo="<?= htmlspecialchars($c['tipo_comprobante'], ENT_QUOTES) ?>"
            data-entidad="compra"
            data-numero="<?= htmlspecialchars($c['numero_comprobante'] ?? '#'.$c['id_compra'], ENT_QUOTES) ?>"
            style="background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;border:none;border-radius:4px;padding:2px 7px;">
            <i class="fas fa-file-pdf"></i>
        </button>
        <?php if ($c['estado'] === 'pendiente' && $c['tipo_pago'] === 'credito'): ?>
        <button class="btn btn-sm btn-success btn-pagar-compra" title="Registrar pago"
            data-id="<?= $c['id_compra'] ?>"
            data-saldo="<?= $c['saldo_pendiente'] ?>"
            data-proveedor="<?= htmlspecialchars($c['razon_social'], ENT_QUOTES) ?>"
            data-numero="<?= htmlspecialchars(($tipoLabel[$c['tipo_comprobante']]??ucfirst($c['tipo_comprobante'])).' '.($c['numero_comprobante']??'#'.$c['id_compra']), ENT_QUOTES) ?>">
            <i class="fas fa-dollar-sign"></i>
        </button>
        <?php endif; ?>
        <?php if ($c['estado'] !== 'anulado'): ?>
        <button class="btn btn-sm btn-danger btn-anular-compra" title="Anular compra"
            data-id="<?= $c['id_compra'] ?>"
            data-numero="<?= htmlspecialchars(($tipoLabel[$c['tipo_comprobante']]??ucfirst($c['tipo_comprobante'])).' '.($c['numero_comprobante']??'#'.$c['id_compra']), ENT_QUOTES) ?>">
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

</div></div><!-- /content /container-fluid -->
</div><!-- /content-wrapper -->

<!-- -- MODAL VER DETALLE -- -->
<div class="modal fade" id="modalVerCompra" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg"><div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a5276,#2980b9);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
    <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-file-invoice mr-2"></i>Detalle de Compra</h6>
    <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-4">
    <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cerrar</button>
</div>
</div></div></div>

<!-- -- MODAL REGISTRAR PAGO -- -->
<div class="modal fade" id="modalPagarCompra" tabindex="-1" aria-hidden="true">
<div class="modal-dialog"><div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a7a4a,#27ae60);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
    <div>
        <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-dollar-sign mr-2"></i>Registrar Pago</h6>
        <small id="pago_proveedor_label" style="color:rgba(255,255,255,.85);font-size:.82rem;"></small>
    </div>
    <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<form method="POST" id="formRegistrarPago">
<input type="hidden" name="accion" value="registrar_pago">
<input type="hidden" name="id_compra" id="pago_id_compra">

<!-- Saldo resumen -->
<div style="padding:12px 20px 0;">
    <div class="saldo-alerta">
        <div style="font-size:.8rem;color:#999;text-transform:uppercase;font-weight:600;">Saldo pendiente</div>
        <div id="pago_saldo_display" style="font-size:1.4rem;font-weight:700;color:#e67e22;"></div>
    </div>
</div>

<!-- Cuotas -->
<div style="padding:10px 20px 0;">
    <div id="hcomp_cuotas_loading" style="display:none;text-align:center;padding:10px 0;">
        <i class="fas fa-spinner fa-spin text-muted"></i>
        <span class="text-muted ml-2" style="font-size:.85rem;">Cargando cuotas...</span>
    </div>
    <div id="hcomp_cuotas_lista"></div>
</div>

<div class="modal-body p-4" style="padding-top:12px!important;">
    <div class="form-group">
        <label style="font-weight:600;font-size:.83rem;">Monto a pagar <span class="text-danger">*</span></label>
        <div class="input-group input-group-sm">
            <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
            <input type="number" step="0.01" min="0.01" class="form-control" name="monto_pago" id="monto_pago" placeholder="0.00">
        </div>
        <small id="hcomp_monto_hint" class="text-muted"></small>
    </div>
    <div class="form-group">
        <label style="font-weight:600;font-size:.83rem;">M�todo de Pago</label>
        <div class="d-flex flex-wrap" style="gap:6px;">
            <button type="button" class="btn-metodo-pago" data-metodo="efectivo"><i class="fas fa-money-bill-wave mr-1"></i>Efectivo</button>
            <button type="button" class="btn-metodo-pago" data-metodo="yape"><i class="fas fa-mobile-alt mr-1"></i>Yape</button>
            <button type="button" class="btn-metodo-pago" data-metodo="transferencia"><i class="fas fa-university mr-1"></i>Transferencia</button>
        </div>
        <input type="hidden" name="metodo_pago_abono" id="metodo_pago_abono" value="efectivo">
    </div>
    <div class="form-group mb-0">
        <label style="font-weight:600;font-size:.83rem;">Observaci�n</label>
        <textarea class="form-control form-control-sm" name="obs_pago" id="obs_pago" rows="2" placeholder="Notas del pago (opcional)..." maxlength="200"></textarea>
    </div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
    <button type="submit" class="btn btn-sm font-weight-bold" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);color:#fff;"><i class="fas fa-check mr-1"></i>Registrar Pago</button>
</div>
</form>
</div></div></div>

<!-- -- MODAL TICKET PDF -- -->
<div class="modal fade" id="modalTicketPDF" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-xl" style="max-width:860px;">
<div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a5276,#2980b9);padding:14px 20px;display:flex;align-items:center;justify-content:space-between;">
    <div>
        <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-file-pdf mr-2"></i>Comprobante de Compra</h6>
        <small id="ticketPdfNumero" style="color:rgba(255,255,255,.85);font-size:.82rem;"></small>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <a id="btnDescargarTicket" href="#" target="_blank"
           class="btn btn-sm font-weight-bold"
           style="background:#27ae60;color:#fff;border:none;border-radius:6px;padding:6px 14px;">
            <i class="fas fa-download mr-1"></i>Descargar PDF
        </a>
        <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
    </div>
</div>
<div class="modal-body p-0" style="background:#525659;">
    <div id="ticketPdfCargando" class="text-center py-5" style="color:#fff;">
        <i class="fas fa-spinner fa-spin fa-2x mb-3 d-block"></i>
        <span>Generando comprobante...</span>
    </div>
    <iframe id="ticketPdfFrame"
            src=""
            style="width:100%;height:75vh;border:none;display:none;"
            onload="document.getElementById('ticketPdfCargando').style.display='none';this.style.display='block';">
    </iframe>
</div>
</div></div></div>

<!-- -- MODAL EXPORTAR HISTORIAL COMPRAS -- -->
<div class="modal fade" id="modalExportarHCmp" tabindex="-1" aria-hidden="true">
<div class="modal-dialog" style="max-width:440px;">
<div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a5276,#2980b9);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
    <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-download mr-2"></i>Exportar Historial de Compras</h6>
    <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-4">

    <!-- Filtro estado -->
    <div class="form-group mb-3">
        <label style="font-weight:600;font-size:.83rem;color:#495057;"><i class="fas fa-circle mr-1 text-muted"></i>Estado</label>
        <select id="hcmp_exp_estado" class="form-control form-control-sm">
            <option value="all">Todos los estados</option>
            <option value="pagado">Pagadas</option>
            <option value="pendiente">Pendientes</option>
            <option value="anulado">Anuladas</option>
        </select>
    </div>

    <!-- Filtro tipo pago -->
    <div class="form-group mb-3">
        <label style="font-weight:600;font-size:.83rem;color:#495057;"><i class="fas fa-credit-card mr-1 text-muted"></i>Tipo de Pago</label>
        <select id="hcmp_exp_pago" class="form-control form-control-sm">
            <option value="all">Todos</option>
            <option value="contado">Contado</option>
            <option value="credito">Cr�dito</option>
        </select>
    </div>

    <!-- Rango de fechas -->
    <div class="row mb-3">
        <div class="col-6">
            <label style="font-weight:600;font-size:.83rem;color:#495057;"><i class="fas fa-calendar-alt mr-1 text-muted"></i>Desde</label>
            <input type="date" id="hcmp_exp_desde" class="form-control form-control-sm">
        </div>
        <div class="col-6">
            <label style="font-weight:600;font-size:.83rem;color:#495057;"><i class="fas fa-calendar-alt mr-1 text-muted"></i>Hasta</label>
            <input type="date" id="hcmp_exp_hasta" class="form-control form-control-sm">
        </div>
    </div>

    <!-- Botones de formato -->
    <div style="background:#f8f9fa;border-radius:10px;padding:14px;border:1px solid #e9ecef;">
        <p style="font-weight:600;font-size:.82rem;color:#495057;margin-bottom:10px;"><i class="fas fa-file-export mr-1"></i>Selecciona el formato:</p>
        <div class="d-flex" style="gap:8px;">
            <button type="button" id="hcmp_btn_csv"
                style="flex:1;background:linear-gradient(135deg,#1a7a4a,#27ae60);color:#fff;border:none;border-radius:8px;padding:10px 8px;font-weight:700;font-size:.82rem;cursor:pointer;">
                <i class="fas fa-file-csv d-block mb-1" style="font-size:1.3rem;"></i>CSV
            </button>
            <button type="button" id="hcmp_btn_excel"
                style="flex:1;background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;border:none;border-radius:8px;padding:10px 8px;font-weight:700;font-size:.82rem;cursor:pointer;">
                <i class="fas fa-file-excel d-block mb-1" style="font-size:1.3rem;"></i>Excel
            </button>
            <button type="button" id="hcmp_btn_pdf"
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

<?php
$extra_js = '<script src="js/historial_compras.js?v=' . time() . '"></script>';
include $ruta_base . 'includes/footer.php';
?>