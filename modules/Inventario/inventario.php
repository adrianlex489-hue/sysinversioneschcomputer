<?php
// ============================================================
// modules/Inventario/inventario.php | Botica 2026
// Stock General — administracion de stock por lotes
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexion BD no disponible.');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_CAJERO'))        define('ROL_CAJERO', 2);
if (!defined('ROL_TRABAJADOR'))    define('ROL_TRABAJADOR', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_CAJERO, ROL_TRABAJADOR]);
verificarPermiso($pdo, 'inventario');

$id_usuario = $_SESSION['id_usuario'] ?? 0;

$swal = null;
if (isset($_SESSION['swal_inv'])) { $swal = $_SESSION['swal_inv']; unset($_SESSION['swal_inv']); }

function redirigirInv(string $icon, string $title, string $text): void {
    $_SESSION['swal_inv'] = compact('icon', 'title', 'text');
    header('Location: inventario.php'); exit;
}

// ── AJAX: detalle de producto con sus lotes ───────────────────────────────────
if (isset($_GET['accion']) && $_GET['accion'] === 'detalle_ajax') {
    $id = (int)($_GET['id_producto'] ?? 0);
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.nombre_categoria, u.nombre_unidad, pr.razon_social AS proveedor
            FROM productos p
            LEFT JOIN categorias  c  ON p.id_categoria = c.id_categoria
            LEFT JOIN unidades    u  ON p.id_unidad    = u.id_unidad
            LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
            WHERE p.id_producto = ?
        ");
        $stmt->execute([$id]);
        $prod = $stmt->fetch();
        if (!$prod) { echo '<div class="alert alert-warning">Producto no encontrado.</div>'; exit; }

        $lotes = $pdo->prepare("
            SELECT *, DATEDIFF(fecha_vencimiento, CURDATE()) AS dias_vencer
            FROM lotes WHERE id_producto = ? ORDER BY fecha_vencimiento ASC
        ");
        $lotes->execute([$id]);
        $lista_lotes = $lotes->fetchAll();

        // Cabecera producto
        echo '<div class="row mb-2">';
        echo '<div class="col-md-6"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-pills mr-1"></i>Producto</small>';
        echo '<strong style="font-size:.95rem;">'.htmlspecialchars($prod['nombre_producto']).'</strong>';
        echo '<div style="font-size:.78rem;color:#999;">'.htmlspecialchars($prod['codigo']).' &bull; '.htmlspecialchars($prod['laboratorio']??'—').'</div>';
        echo '<span style="background:#e3f2fd;color:#1a5276;padding:2px 8px;border-radius:10px;font-size:.7rem;font-weight:600;">'.htmlspecialchars($prod['nombre_categoria']??'—').'</span></div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-cubes mr-1"></i>Stock Total</small>';
        $sc = (int)$prod['stock']; $sm = (int)$prod['stock_minimo']; $sx = (int)$prod['stock_maximo'];
        $col = $sc <= 0 ? '#e74c3c' : ($sc <= $sm ? '#e67e22' : '#27ae60');
        echo '<strong style="font-size:1.3rem;color:'.$col.';">'.$sc.'</strong><span style="color:#999;font-size:.8rem;"> uds</span></div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-sliders-h mr-1"></i>Rango Stock</small>';
        echo '<span style="font-size:.82rem;">Mín: <strong>'.$sm.'</strong> &nbsp; Máx: <strong>'.$sx.'</strong></span></div>';
        echo '</div>';

        echo '<div class="row mb-3 pt-2" style="border-top:1px solid #f0f0f0;">';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-tag mr-1"></i>P. Compra</small>';
        echo '<strong>S/. '.number_format($prod['precio_compra'],2).'</strong></div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-dollar-sign mr-1"></i>P. Venta</small>';
        echo '<strong style="color:#1a7a4a;">S/. '.number_format($prod['precio_venta'],2).'</strong></div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-building mr-1"></i>Proveedor</small>';
        echo '<span style="font-size:.82rem;">'.htmlspecialchars($prod['proveedor']??'—').'</span></div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-prescription mr-1"></i>Receta</small>';
        echo '<span style="font-size:.82rem;">'.($prod['requiere_receta']?'<span style="color:#e74c3c;font-weight:700;">Sí</span>':'No').'</span></div>';
        echo '</div>';

        // Lotes del producto
        echo '<h6 style="font-size:.82rem;font-weight:700;color:#495057;text-transform:uppercase;margin-bottom:8px;"><i class="fas fa-layer-group mr-1"></i>Lotes asociados</h6>';
        if (empty($lista_lotes)) {
            echo '<div class="text-center text-muted py-3" style="font-size:.85rem;"><i class="fas fa-box-open fa-2x d-block mb-2" style="opacity:.2;"></i>Sin lotes registrados para este producto.</div>';
        } else {
            echo '<div class="table-responsive"><table class="table table-sm table-bordered" style="font-size:.82rem;">';
            echo '<thead style="background:#1a5276;color:#fff;"><tr>';
            echo '<th><i class="fas fa-tag mr-1"></i>Código Lote</th>';
            echo '<th class="text-center"><i class="fas fa-calendar-times mr-1"></i>Vencimiento</th>';
            echo '<th class="text-center"><i class="fas fa-boxes mr-1"></i>Stock Ini.</th>';
            echo '<th class="text-center"><i class="fas fa-cubes mr-1"></i>Stock Act.</th>';
            echo '<th class="text-center"><i class="fas fa-chart-bar mr-1"></i>Consumo</th>';
            echo '<th class="text-center"><i class="fas fa-circle mr-1"></i>Estado</th>';
            echo '</tr></thead><tbody>';
            foreach ($lista_lotes as $lt) {
                $d = (int)$lt['dias_vencer'];
                if ($d < 0)       { $ev = 'VENCIDO';    $bc = '#721c24'; $bg = '#f8d7da'; }
                elseif ($d <= 30) { $ev = 'CRÍTICO';    $bc = '#721c24'; $bg = '#f8d7da'; }
                elseif ($d <= 90) { $ev = 'POR VENCER'; $bc = '#856404'; $bg = '#fff3cd'; }
                else              { $ev = 'VIGENTE';    $bc = '#155724'; $bg = '#d4edda'; }
                $pct = $lt['stock_inicial'] > 0 ? round(($lt['stock_actual']/$lt['stock_inicial'])*100) : 0;
                $bc2 = $pct > 50 ? '#27ae60' : ($pct > 20 ? '#e67e22' : '#e74c3c');
                echo '<tr>';
                echo '<td><code style="background:#e0f7fa;color:#117a8b;padding:2px 7px;border-radius:4px;font-weight:700;">'.htmlspecialchars($lt['codigo_lote']).'</code></td>';
                echo '<td class="text-center" style="color:'.($d<0?'#e74c3c':($d<=90?'#e67e22':'#2d3436')).';font-weight:700;">'.date('d/m/Y',strtotime($lt['fecha_vencimiento'])).'</td>';
                echo '<td class="text-center">'.$lt['stock_inicial'].'</td>';
                echo '<td class="text-center"><strong style="color:'.($lt['stock_actual']<=0?'#e74c3c':($lt['stock_actual']<=5?'#e67e22':'#27ae60')).'">'.$lt['stock_actual'].'</strong></td>';
                echo '<td class="text-center"><div style="background:#e9ecef;border-radius:6px;height:7px;width:70px;margin:auto;overflow:hidden;"><div style="width:'.$pct.'%;background:'.$bc2.';height:100%;border-radius:6px;"></div></div><div style="font-size:.7rem;color:#999;">'.$pct.'%</div></td>';
                echo '<td class="text-center"><span style="background:'.$bg.';color:'.$bc.';padding:2px 9px;border-radius:20px;font-size:.72rem;font-weight:700;">'.$ev.'</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Error: '.htmlspecialchars($e->getMessage()).'</div>';
    }
    exit;
}

// ── POST: ajuste de stock ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'ajustar_stock') {
    $id_producto = (int)($_POST['id_producto'] ?? 0);
    $tipo        = $_POST['tipo_ajuste']  ?? 'entrada';
    $cantidad    = (int)($_POST['cantidad']    ?? 0);
    $motivo      = trim($_POST['motivo']       ?? '') ?: null;

    if (!$id_producto) redirigirInv('warning', 'Error', 'Producto no identificado.');
    if ($cantidad <= 0) redirigirInv('warning', 'Cantidad inválida', 'La cantidad debe ser mayor a 0.');

    try {
        $pdo->beginTransaction();

        $stP = $pdo->prepare("SELECT stock, nombre_producto FROM productos WHERE id_producto = ?");
        $stP->execute([$id_producto]);
        $prod = $stP->fetch();
        if (!$prod) throw new Exception('Producto no encontrado.');

        $stock_actual = (int)$prod['stock'];

        if ($tipo === 'entrada') {
            $nuevo_stock = $stock_actual + $cantidad;
            // Sumar al lote más reciente vigente, o al primero disponible
            $stL = $pdo->prepare("SELECT id_lote FROM lotes WHERE id_producto=? AND stock_actual > 0 ORDER BY fecha_vencimiento DESC LIMIT 1");
            $stL->execute([$id_producto]);
            $lote = $stL->fetch();
            if ($lote) {
                $pdo->prepare("UPDATE lotes SET stock_actual = stock_actual + ? WHERE id_lote = ?")->execute([$cantidad, $lote['id_lote']]);
            }
        } elseif ($tipo === 'salida') {
            if ($cantidad > $stock_actual) throw new Exception('La cantidad a retirar ('.$cantidad.') supera el stock actual ('.$stock_actual.').');
            $nuevo_stock = $stock_actual - $cantidad;
            // Descontar del lote más próximo a vencer con stock
            $stL = $pdo->prepare("SELECT id_lote, stock_actual FROM lotes WHERE id_producto=? AND stock_actual > 0 ORDER BY fecha_vencimiento ASC");
            $stL->execute([$id_producto]);
            $restante = $cantidad;
            foreach ($stL->fetchAll() as $lt) {
                if ($restante <= 0) break;
                $desc = min($restante, (int)$lt['stock_actual']);
                $pdo->prepare("UPDATE lotes SET stock_actual = stock_actual - ? WHERE id_lote = ?")->execute([$desc, $lt['id_lote']]);
                $restante -= $desc;
            }
        } else {
            // Corrección directa
            $nuevo_stock = $cantidad;
            $diff = $cantidad - $stock_actual;
            if ($diff > 0) {
                $stL = $pdo->prepare("SELECT id_lote FROM lotes WHERE id_producto=? ORDER BY fecha_vencimiento DESC LIMIT 1");
                $stL->execute([$id_producto]);
                $lote = $stL->fetch();
                if ($lote) $pdo->prepare("UPDATE lotes SET stock_actual = stock_actual + ? WHERE id_lote = ?")->execute([$diff, $lote['id_lote']]);
            } elseif ($diff < 0) {
                $stL = $pdo->prepare("SELECT id_lote, stock_actual FROM lotes WHERE id_producto=? AND stock_actual > 0 ORDER BY fecha_vencimiento ASC");
                $stL->execute([$id_producto]);
                $restante = abs($diff);
                foreach ($stL->fetchAll() as $lt) {
                    if ($restante <= 0) break;
                    $desc = min($restante, (int)$lt['stock_actual']);
                    $pdo->prepare("UPDATE lotes SET stock_actual = stock_actual - ? WHERE id_lote = ?")->execute([$desc, $lt['id_lote']]);
                    $restante -= $desc;
                }
            }
        }

        $pdo->prepare("UPDATE productos SET stock = ? WHERE id_producto = ?")->execute([$nuevo_stock, $id_producto]);
        $pdo->commit();

        $tipoLabel = $tipo === 'entrada' ? 'Entrada' : ($tipo === 'salida' ? 'Salida' : 'Corrección');
        redirigirInv('success', 'Ajuste realizado', $tipoLabel.' de '.$cantidad.' uds aplicada a "'.htmlspecialchars($prod['nombre_producto']).'". Stock actualizado: '.$nuevo_stock.' uds.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirigirInv('error', 'Error al ajustar', $e->getMessage());
    }
}

// ── DATOS ─────────────────────────────────────────────────────────────────────
$productos = [];
$stats = ['total'=>0,'ok'=>0,'bajo'=>0,'agotado'=>0,'exceso'=>0,'valor_total'=>0];

try {
    $productos = $pdo->query("
        SELECT p.*,
               c.nombre_categoria,
               u.nombre_unidad,
               pr.razon_social AS proveedor,
               (SELECT COUNT(*) FROM lotes l WHERE l.id_producto = p.id_producto) AS total_lotes,
               (SELECT COUNT(*) FROM lotes l WHERE l.id_producto = p.id_producto AND DATEDIFF(l.fecha_vencimiento, CURDATE()) <= 90 AND DATEDIFF(l.fecha_vencimiento, CURDATE()) >= 0) AS lotes_por_vencer,
               (SELECT COUNT(*) FROM lotes l WHERE l.id_producto = p.id_producto AND l.fecha_vencimiento < CURDATE()) AS lotes_vencidos
        FROM productos p
        LEFT JOIN categorias  c  ON p.id_categoria = c.id_categoria
        LEFT JOIN unidades    u  ON p.id_unidad    = u.id_unidad
        LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
        WHERE p.estado = 1
        ORDER BY p.nombre_producto ASC
    ")->fetchAll();

    foreach ($productos as $p) {
        $stats['total']++;
        $stats['valor_total'] += $p['stock'] * $p['precio_compra'];
        if ($p['stock'] <= 0)                                    $stats['agotado']++;
        elseif ($p['stock'] <= $p['stock_minimo'])               $stats['bajo']++;
        elseif ($p['stock'] > $p['stock_maximo'])                $stats['exceso']++;
        else                                                     $stats['ok']++;
    }
} catch (PDOException $e) {
    $swal = ['icon'=>'error','title'=>'Error','text'=>'Error al cargar datos: '.$e->getMessage()];
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function badgeStockInv(int $stock, int $min, int $max): string {
    if ($stock <= 0)    return '<span class="badge-inv-agotado"><i class="fas fa-times-circle mr-1"></i>Agotado</span>';
    if ($stock <= $min) return '<span class="badge-inv-bajo"><i class="fas fa-exclamation-triangle mr-1"></i>'.$stock.' uds</span>';
    if ($stock > $max)  return '<span class="badge-inv-exceso"><i class="fas fa-arrow-up mr-1"></i>'.$stock.' uds</span>';
    return '<span class="badge-inv-ok"><i class="fas fa-check-circle mr-1"></i>'.$stock.' uds</span>';
}

function pctStock(int $stock, int $max): int {
    if ($max <= 0) return 0;
    return min(100, (int)round(($stock / $max) * 100));
}

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>

<link rel="stylesheet" href="css/inventario.css">

<div class="content-wrapper">

    <!-- ── CABECERA ── -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-inv d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4><i class="fas fa-warehouse mr-2"></i>Stock General</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>Botica 2026 &rsaquo; Inventario &rsaquo; Stock General</small>
                </div>
                <a href="/botica-2026/modules/transacciones/compras.php" class="btn btn-light font-weight-bold btn-sm">
                    <i class="fas fa-plus-circle mr-1"></i>Nueva Compra
                </a>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- ── STATS ── -->
            <div class="row mb-4">
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-mini-inv" style="background:linear-gradient(135deg,#1a5276,#2980b9);">
                        <i class="fas fa-capsules"></i>
                        <div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Productos activos</div></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-mini-inv" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);">
                        <i class="fas fa-check-circle"></i>
                        <div><div class="stat-value"><?= $stats['ok'] ?></div><div class="stat-label">Stock normal</div></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-mini-inv" style="background:linear-gradient(135deg,#7d6608,#f39c12);">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div><div class="stat-value"><?= $stats['bajo'] ?></div><div class="stat-label">Stock bajo</div></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-mini-inv" style="background:linear-gradient(135deg,#922b21,#e74c3c);">
                        <i class="fas fa-times-circle"></i>
                        <div><div class="stat-value"><?= $stats['agotado'] ?></div><div class="stat-label">Agotados</div></div>
                    </div>
                </div>
            </div>

            <!-- ── VALOR DE INVENTARIO ── -->
            <div class="row mb-3">
                <div class="col-12">
                    <div style="background:linear-gradient(135deg,#1a5276,#117a8b);border-radius:10px;padding:14px 22px;color:#fff;display:flex;align-items:center;justify-content:space-between;box-shadow:0 3px 12px rgba(0,0,0,.12);">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <i class="fas fa-coins" style="font-size:1.6rem;opacity:.8;"></i>
                            <div>
                                <div style="font-size:.78rem;opacity:.85;text-transform:uppercase;font-weight:600;letter-spacing:.4px;">Valor total del inventario (costo)</div>
                                <div style="font-size:1.5rem;font-weight:700;">S/. <?= number_format($stats['valor_total'], 2) ?></div>
                            </div>
                        </div>
                        <?php if ($stats['exceso'] > 0): ?>
                        <div style="background:rgba(255,255,255,.15);border-radius:8px;padding:8px 14px;font-size:.82rem;">
                            <i class="fas fa-arrow-up mr-1"></i><strong><?= $stats['exceso'] ?></strong> producto(s) sobre stock máximo
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── ALERTAS ── -->
            <?php if ($stats['agotado'] > 0): ?>
            <div class="alerta-banner-inv alerta-inv-critico mb-3">
                <i class="fas fa-times-circle"></i>
                <strong><?= $stats['agotado'] ?> producto(s) agotado(s)</strong> — Realiza una compra para reponer el stock.
            </div>
            <?php endif; ?>
            <?php if ($stats['bajo'] > 0): ?>
            <div class="alerta-banner-inv alerta-inv-bajo mb-3">
                <i class="fas fa-exclamation-triangle"></i>
                <strong><?= $stats['bajo'] ?> producto(s) con stock bajo</strong> — Por debajo del mínimo establecido.
            </div>
            <?php endif; ?>

            <!-- ── ALERTA SWAL ── -->
            <?php if ($swal): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon: '<?= $swal['icon'] ?>', title: '<?= addslashes($swal['title']) ?>',
                    text: '<?= addslashes($swal['text']) ?>', confirmButtonColor: '#1a5276',
                    timer: <?= in_array($swal['icon'],['success','info']) ? 3500 : 0 ?>,
                    timerProgressBar: <?= in_array($swal['icon'],['success','info']) ? 'true' : 'false' ?>,
                    showConfirmButton: <?= in_array($swal['icon'],['success','info']) ? 'false' : 'true' ?>,
                });
            });
            </script>
            <?php endif; ?>

            <!-- ── FILTROS ── -->
            <div class="filtros-card-inv d-flex align-items-center gap-3 flex-wrap mb-3">
                <span style="font-weight:700;font-size:.85rem;color:#495057;"><i class="fas fa-filter mr-1"></i>Filtrar:</span>
                <button class="btn-filtro-inv active" data-filtro="todos">Todos <span class="badge-count-inv"><?= $stats['total'] ?></span></button>
                <button class="btn-filtro-inv" data-filtro="ok">Normal <span class="badge-count-inv"><?= $stats['ok'] ?></span></button>
                <button class="btn-filtro-inv filtro-bajo" data-filtro="bajo">Stock bajo <span class="badge-count-inv"><?= $stats['bajo'] ?></span></button>
                <button class="btn-filtro-inv filtro-agotado" data-filtro="agotado">Agotados <span class="badge-count-inv"><?= $stats['agotado'] ?></span></button>
                <?php if ($stats['exceso'] > 0): ?>
                <button class="btn-filtro-inv filtro-exceso" data-filtro="exceso">Exceso <span class="badge-count-inv"><?= $stats['exceso'] ?></span></button>
                <?php endif; ?>
            </div>

            <!-- ── CARD PRINCIPAL ── -->
            <div class="card">
                <div class="card-header-inv d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="mb-0"><i class="fas fa-list mr-2"></i>Inventario de Productos</h6>
                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group input-group-sm" style="width:250px;">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            </div>
                            <input type="text" id="buscarInventario" class="form-control" placeholder="Buscar producto, código...">
                        </div>
                        <span id="contadorInventario" class="badge badge-light" style="font-size:.8rem;"><?= count($productos) ?> productos</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="tablaInventario" class="table table-inv table-bordered table-hover table-sm mb-0">
                            <thead>
                                <tr>
                                    <th style="width:50px;" class="text-center">#</th>
                                    <th><i class="fas fa-barcode mr-1"></i>Código</th>
                                    <th><i class="fas fa-pills mr-1"></i>Producto</th>
                                    <th><i class="fas fa-tag mr-1"></i>Categoría</th>
                                    <th style="width:110px;" class="text-center"><i class="fas fa-cubes mr-1"></i>Stock</th>
                                    <th style="width:100px;" class="text-center"><i class="fas fa-chart-bar mr-1"></i>Nivel</th>
                                    <th style="width:80px;" class="text-center"><i class="fas fa-arrow-down mr-1"></i>Mín.</th>
                                    <th style="width:80px;" class="text-center"><i class="fas fa-arrow-up mr-1"></i>Máx.</th>
                                    <th style="width:90px;" class="text-right"><i class="fas fa-dollar-sign mr-1"></i>P.Venta</th>
                                    <th style="width:70px;" class="text-center"><i class="fas fa-layer-group mr-1"></i>Lotes</th>
                                    <th style="width:100px;" class="text-center"><i class="fas fa-cogs mr-1"></i>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($productos as $i => $p):
                                $stock = (int)$p['stock'];
                                $smin  = (int)$p['stock_minimo'];
                                $smax  = (int)$p['stock_maximo'];
                                $pct   = pctStock($stock, $smax);

                                if ($stock <= 0)        { $fila_clase = 'fila-agotado'; $data_filtro = 'agotado'; $bar_color = '#e74c3c'; }
                                elseif ($stock <= $smin){ $fila_clase = 'fila-critico'; $data_filtro = 'bajo';    $bar_color = '#e67e22'; }
                                elseif ($stock > $smax) { $fila_clase = 'fila-exceso';  $data_filtro = 'exceso';  $bar_color = '#8e44ad'; }
                                else                    { $fila_clase = '';             $data_filtro = 'ok';      $bar_color = '#27ae60'; }
                            ?>
                                <tr class="fila-inv <?= $fila_clase ?>"
                                    data-filtro="<?= $data_filtro ?>"
                                    data-search="<?= htmlspecialchars(strtolower($p['nombre_producto'].' '.$p['codigo'].' '.($p['laboratorio']??'').' '.($p['nombre_categoria']??'')), ENT_QUOTES) ?>">
                                    <td class="text-center">
                                        <div class="num-fila-inv"><?= $i + 1 ?></div>
                                    </td>
                                    <td>
                                        <code style="font-size:.8rem;color:#1a5276;background:#e3f2fd;padding:2px 6px;border-radius:4px;">
                                            <?= htmlspecialchars($p['codigo']) ?>
                                        </code>
                                    </td>
                                    <td>
                                        <div class="font-weight-bold" style="font-size:.88rem;">
                                            <?= htmlspecialchars(mb_strimwidth($p['nombre_producto'], 0, 48, '...')) ?>
                                        </div>
                                        <div style="font-size:.75rem;color:#999;">
                                            <?= htmlspecialchars($p['laboratorio']??'—') ?>
                                            <?php if ($p['requiere_receta']): ?>
                                                &nbsp;<span style="background:#fde8e8;color:#c0392b;padding:1px 6px;border-radius:8px;font-size:.68rem;font-weight:600;"><i class="fas fa-prescription mr-1"></i>Receta</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-cat-inv"><?= htmlspecialchars($p['nombre_categoria']??'—') ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?= badgeStockInv($stock, $smin, $smax) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="barra-stock-wrap">
                                            <div class="barra-stock-fill" style="width:<?= $pct ?>%;background:<?= $bar_color ?>;"></div>
                                        </div>
                                        <div style="font-size:.7rem;color:#999;margin-top:2px;"><?= $pct ?>%</div>
                                    </td>
                                    <td class="text-center" style="font-size:.85rem;color:#e67e22;font-weight:600;"><?= $smin ?></td>
                                    <td class="text-center" style="font-size:.85rem;color:#8e44ad;font-weight:600;"><?= $smax ?></td>
                                    <td class="text-right" style="font-weight:700;color:#1a7a4a;font-size:.88rem;">
                                        S/. <?= number_format($p['precio_venta'], 2) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int)$p['total_lotes'] > 0): ?>
                                            <span style="background:#e3f2fd;color:#1a5276;padding:2px 8px;border-radius:10px;font-size:.78rem;font-weight:700;">
                                                <?= $p['total_lotes'] ?>
                                                <?php if ((int)$p['lotes_vencidos'] > 0): ?>
                                                    <i class="fas fa-exclamation-circle ml-1" style="color:#e74c3c;" title="<?= $p['lotes_vencidos'] ?> lote(s) vencido(s)"></i>
                                                <?php elseif ((int)$p['lotes_por_vencer'] > 0): ?>
                                                    <i class="fas fa-clock ml-1" style="color:#e67e22;" title="<?= $p['lotes_por_vencer'] ?> lote(s) por vencer"></i>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#ccc;font-size:.78rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-info btn-ver-producto-inv" title="Ver detalle y lotes"
                                            data-id="<?= $p['id_producto'] ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-success btn-ajustar-stock" title="Ajustar stock"
                                            data-id="<?= $p['id_producto'] ?>"
                                            data-nombre="<?= htmlspecialchars($p['nombre_producto'], ENT_QUOTES) ?>"
                                            data-stock="<?= $stock ?>">
                                            <i class="fas fa-sliders-h"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($productos)): ?>
                                <tr>
                                    <td colspan="11" class="text-center py-5 text-muted">
                                        <i class="fas fa-box-open fa-3x mb-3 d-block" style="opacity:.2;"></i>
                                        No hay productos activos en el inventario.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ── MODAL VER DETALLE PRODUCTO ── -->
<div class="modal fade" id="modalVerProductoInv" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#1a5276,#2980b9);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
                <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-warehouse mr-2"></i>Detalle de Stock</h6>
                <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-4"><!-- AJAX --></div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL AJUSTAR STOCK ── -->
<div class="modal fade" id="modalAjustarStock" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#1a7a4a,#27ae60);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-sliders-h mr-2"></i>Ajustar Stock</h6>
                    <small id="ajuste_nombre_producto" style="color:rgba(255,255,255,.85);font-size:.82rem;"></small>
                </div>
                <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" id="formAjustarStock">
                <input type="hidden" name="accion" value="ajustar_stock">
                <input type="hidden" name="id_producto" id="ajuste_id_producto">
                <input type="hidden" name="tipo_ajuste" id="ajuste_tipo" value="entrada">
                <div class="modal-body p-4">

                    <!-- Stock actual -->
                    <div style="background:#f8f9fa;border-radius:8px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
                        <span style="font-size:.83rem;color:#666;"><i class="fas fa-cubes mr-2 text-muted"></i>Stock actual</span>
                        <strong id="ajuste_stock_actual" style="font-size:1.1rem;color:#1a5276;"></strong>
                    </div>

                    <!-- Tipo de ajuste -->
                    <div class="form-group">
                        <label style="font-weight:600;font-size:.83rem;"><i class="fas fa-exchange-alt mr-1 text-muted"></i>Tipo de ajuste <span class="text-danger">*</span></label>
                        <div class="tipo-ajuste-group">
                            <button type="button" class="btn-tipo-ajuste entrada activo" data-tipo="entrada">
                                <i class="fas fa-plus-circle d-block mb-1" style="font-size:1.1rem;"></i>Entrada
                            </button>
                            <button type="button" class="btn-tipo-ajuste salida" data-tipo="salida">
                                <i class="fas fa-minus-circle d-block mb-1" style="font-size:1.1rem;"></i>Salida
                            </button>
                            <button type="button" class="btn-tipo-ajuste correccion" data-tipo="correccion">
                                <i class="fas fa-edit d-block mb-1" style="font-size:1.1rem;"></i>Corrección
                            </button>
                        </div>
                        <small class="text-muted" style="font-size:.75rem;">
                            <strong>Entrada:</strong> suma al stock &nbsp;|&nbsp;
                            <strong>Salida:</strong> resta del stock &nbsp;|&nbsp;
                            <strong>Corrección:</strong> establece el valor exacto
                        </small>
                    </div>

                    <!-- Cantidad -->
                    <div class="form-group">
                        <label style="font-weight:600;font-size:.83rem;"><i class="fas fa-sort-numeric-up mr-1 text-muted"></i>Cantidad <span class="text-danger">*</span></label>
                        <input type="number" class="form-control form-control-sm" name="cantidad" id="ajuste_cantidad"
                               min="1" placeholder="Ingresa la cantidad..." autocomplete="off">
                        <div id="preview_nuevo_stock" style="display:none;margin-top:6px;font-size:.82rem;color:#666;">
                            Nuevo stock: <span></span>
                        </div>
                    </div>

                    <!-- Motivo -->
                    <div class="form-group mb-0">
                        <label style="font-weight:600;font-size:.83rem;"><i class="fas fa-comment-alt mr-1 text-muted"></i>Motivo <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" name="motivo" id="ajuste_motivo"
                                  rows="2" placeholder="Ej: Ajuste por inventario físico, merma, devolución..." maxlength="200"></textarea>
                    </div>

                </div>
                <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancelar
                    </button>
                    <button type="submit" id="btnSubmitAjuste" class="btn btn-sm font-weight-bold"
                            style="background:linear-gradient(135deg,#1a7a4a,#27ae60);color:#fff;">
                        <i class="fas fa-check mr-1"></i>Aplicar Ajuste
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include $ruta_base . 'includes/footer.php'; ?>
<script src="js/inventario.js"></script>


