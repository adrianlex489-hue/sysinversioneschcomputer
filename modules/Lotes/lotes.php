<?php
// ============================================================
// modules/Lotes/lotes.php | Botica 2026
// Visualización y gestión de lotes / vencimientos
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
verificarPermiso($pdo, 'lotes');

// ── Patrón PRG ────────────────────────────────────────────────────────────────
$swal = null;
if (isset($_SESSION['swal_lotes'])) { $swal = $_SESSION['swal_lotes']; unset($_SESSION['swal_lotes']); }

function redirigirLotes(string $icon, string $title, string $text): void {
    $_SESSION['swal_lotes'] = compact('icon', 'title', 'text');
    header('Location: lotes.php'); exit;
}

// ── AJAX: detalle de lote ─────────────────────────────────────────────────────
if (isset($_GET['accion']) && $_GET['accion'] === 'detalle_ajax') {
    $id = (int)($_GET['id_lote'] ?? 0);
    try {
        $stmt = $pdo->prepare("
            SELECT l.*, p.nombre_producto, p.codigo AS cod_producto,
                   p.laboratorio, p.presentacion, c.nombre_categoria,
                   u.nombre_unidad
            FROM lotes l
            JOIN productos p ON l.id_producto = p.id_producto
            LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
            LEFT JOIN unidades u   ON p.id_unidad    = u.id_unidad
            WHERE l.id_lote = ?
        ");
        $stmt->execute([$id]);
        $lote = $stmt->fetch();
        if (!$lote) { echo '<div class="alert alert-warning">Lote no encontrado.</div>'; exit; }

        // Compras que originaron este lote
        $stC = $pdo->prepare("
            SELECT dc.cantidad, dc.precio_compra, c.fecha, c.numero_comprobante,
                   c.tipo_comprobante, pr.razon_social
            FROM detalle_compra dc
            JOIN compras c    ON dc.id_compra   = c.id_compra
            JOIN proveedores pr ON c.id_proveedor = pr.id_proveedor
            WHERE dc.id_lote = ?
            ORDER BY c.fecha DESC
        ");
        $stC->execute([$id]);
        $compras_lote = $stC->fetchAll();

        $hoy  = new DateTime();
        $fv   = new DateTime($lote['fecha_vencimiento']);
        $dias = (int)$hoy->diff($fv)->days * ($fv >= $hoy ? 1 : -1);

        if ($dias < 0)       { $estado_v = 'vencido';      $color_v = '#e74c3c'; }
        elseif ($dias <= 30) { $estado_v = 'critico';      $color_v = '#e74c3c'; }
        elseif ($dias <= 90) { $estado_v = 'por vencer';   $color_v = '#e67e22'; }
        else                 { $estado_v = 'vigente';      $color_v = '#27ae60'; }

        $pct = $lote['stock_inicial'] > 0
            ? round(($lote['stock_actual'] / $lote['stock_inicial']) * 100)
            : 0;

        echo '<div class="row mb-2">';
        echo '<div class="col-md-6">';
        echo '<small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-pills mr-1"></i>Producto</small>';
        echo '<strong style="font-size:.95rem;">'.htmlspecialchars($lote['nombre_producto']).'</strong>';
        echo '<div style="font-size:.78rem;color:#999;">'.htmlspecialchars($lote['cod_producto']).' &bull; '.htmlspecialchars($lote['laboratorio']??'—').'</div>';
        echo '</div>';
        echo '<div class="col-md-3">';
        echo '<small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-tag mr-1"></i>Código de Lote</small>';
        echo '<code style="background:#e0f7fa;color:#117a8b;padding:4px 10px;border-radius:6px;font-size:.9rem;font-weight:700;">'.htmlspecialchars($lote['codigo_lote']).'</code>';
        echo '</div>';
        echo '<div class="col-md-3">';
        echo '<small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-circle mr-1"></i>Estado</small>';
        echo '<span style="background:'.($dias<0?'#f8d7da':($dias<=90?'#fff3cd':'#d4edda')).';color:'.$color_v.';padding:4px 12px;border-radius:20px;font-size:.8rem;font-weight:700;">'.strtoupper($estado_v).'</span>';
        echo '</div>';
        echo '</div>';

        echo '<div class="row mb-3 pt-2" style="border-top:1px solid #f0f0f0;">';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-calendar-times mr-1"></i>Vencimiento</small>';
        echo '<strong style="color:'.$color_v.';">'.date('d/m/Y', strtotime($lote['fecha_vencimiento'])).'</strong>';
        if ($dias >= 0) echo '<div style="font-size:.75rem;color:#999;"><i class="fas fa-hourglass-half mr-1"></i>'.$dias.' días restantes</div>';
        else echo '<div style="font-size:.75rem;color:#e74c3c;"><i class="fas fa-exclamation-triangle mr-1"></i>Venció hace '.abs($dias).' días</div>';
        echo '</div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-boxes mr-1"></i>Stock Inicial</small>';
        echo '<strong>'.$lote['stock_inicial'].' uds</strong></div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-cubes mr-1"></i>Stock Actual</small>';
        echo '<strong style="color:'.($lote['stock_actual']<=0?'#e74c3c':($lote['stock_actual']<=5?'#e67e22':'#27ae60')).';">'.$lote['stock_actual'].' uds</strong></div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-calendar-plus mr-1"></i>Registrado</small>';
        echo '<span style="font-size:.82rem;">'.date('d/m/Y', strtotime($lote['fecha_registro'])).'</span></div>';
        echo '</div>';

        // Barra de consumo
        echo '<div class="mb-3">';
        echo '<div style="display:flex;justify-content:space-between;font-size:.78rem;color:#666;margin-bottom:4px;">';
        echo '<span><i class="fas fa-chart-bar mr-1"></i>Consumo del lote</span>';
        echo '<span><strong>'.(100-$pct).'%</strong> consumido</span>';
        echo '</div>';
        echo '<div style="background:#e9ecef;border-radius:10px;height:10px;overflow:hidden;">';
        $bar_color = $pct > 50 ? '#27ae60' : ($pct > 20 ? '#e67e22' : '#e74c3c');
        echo '<div style="width:'.$pct.'%;background:'.$bar_color.';height:100%;border-radius:10px;transition:width .5s;"></div>';
        echo '</div>';
        echo '<div style="font-size:.75rem;color:#999;margin-top:3px;">'.$lote['stock_actual'].' de '.$lote['stock_inicial'].' unidades disponibles</div>';
        echo '</div>';

        // Compras origen
        if (!empty($compras_lote)) {
            echo '<h6 style="font-size:.82rem;font-weight:700;color:#495057;text-transform:uppercase;margin-bottom:8px;"><i class="fas fa-truck mr-1"></i>Compras que originaron este lote</h6>';
            echo '<div class="table-responsive"><table class="table table-sm table-bordered" style="font-size:.82rem;">';
            echo '<thead style="background:#1a5276;color:#fff;"><tr><th><i class="fas fa-calendar-alt mr-1"></i>Fecha</th><th><i class="fas fa-building mr-1"></i>Proveedor</th><th><i class="fas fa-file-invoice mr-1"></i>Comprobante</th><th class="text-center"><i class="fas fa-sort-numeric-up mr-1"></i>Cant.</th><th class="text-right"><i class="fas fa-dollar-sign mr-1"></i>P. Compra</th></tr></thead><tbody>';
            foreach ($compras_lote as $cl) {
                echo '<tr>';
                echo '<td>'.date('d/m/Y', strtotime($cl['fecha'])).'</td>';
                echo '<td>'.htmlspecialchars($cl['razon_social']).'</td>';
                echo '<td><span style="font-size:.78rem;">'.htmlspecialchars(ucfirst($cl['tipo_comprobante'])).'</span>';
                if ($cl['numero_comprobante']) echo ' <code style="font-size:.75rem;">'.htmlspecialchars($cl['numero_comprobante']).'</code>';
                echo '</td>';
                echo '<td class="text-center font-weight-bold">'.$cl['cantidad'].'</td>';
                echo '<td class="text-right">S/. '.number_format($cl['precio_compra'],2).'</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Error: '.htmlspecialchars($e->getMessage()).'</div>';
    }
    exit;
}

// ── DATOS ─────────────────────────────────────────────────────────────────────
$lotes = [];
$stats = ['total'=>0,'vigentes'=>0,'por_vencer'=>0,'vencidos'=>0,'agotados'=>0];

try {
    $lotes = $pdo->query("
        SELECT l.*,
               p.nombre_producto, p.codigo AS cod_producto,
               p.laboratorio, p.presentacion,
               c.nombre_categoria,
               u.nombre_unidad,
               DATEDIFF(l.fecha_vencimiento, CURDATE()) AS dias_para_vencer
        FROM lotes l
        JOIN productos p ON l.id_producto = p.id_producto
        LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
        LEFT JOIN unidades u   ON p.id_unidad    = u.id_unidad
        ORDER BY l.fecha_vencimiento ASC
    ")->fetchAll();

    foreach ($lotes as $l) {
        $stats['total']++;
        $d = (int)$l['dias_para_vencer'];
        if ($l['stock_actual'] <= 0)  $stats['agotados']++;
        if ($d < 0)                   $stats['vencidos']++;
        elseif ($d <= 90)             $stats['por_vencer']++;
        else                          $stats['vigentes']++;
    }
} catch (PDOException $e) {
    $swal = ['icon'=>'error','title'=>'Error','text'=>'Error al cargar datos: '.$e->getMessage()];
}

// ── Helper: badge vencimiento ─────────────────────────────────────────────────
function badgeVencimiento(int $dias): string {
    if ($dias < 0)       return '<span class="badge-lote-vencido"><i class="fas fa-times-circle mr-1"></i>VENCIDO</span>';
    if ($dias <= 30)     return '<span class="badge-lote-critico"><i class="fas fa-exclamation-circle mr-1"></i>'.$dias.'d</span>';
    if ($dias <= 90)     return '<span class="badge-lote-pronto"><i class="fas fa-clock mr-1"></i>'.$dias.'d</span>';
    return '<span class="badge-lote-vigente"><i class="fas fa-check-circle mr-1"></i>'.$dias.'d</span>';
}

// ── Helper: badge stock ───────────────────────────────────────────────────────
function badgeStockLote(int $stock): string {
    if ($stock <= 0)  return '<span class="badge-stock-agotado">Agotado</span>';
    if ($stock <= 5)  return '<span class="badge-stock-bajo">'.$stock.' uds</span>';
    return '<span class="badge-stock-ok">'.$stock.' uds</span>';
}

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<link rel="stylesheet" href="css/lotes.css">

<div class="content-wrapper">

    <!-- ── CABECERA ── -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-lotes d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4><i class="fas fa-boxes mr-2"></i>Lotes y Vencimientos</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>Botica 2026 &rsaquo; Inventario &rsaquo; Lotes / Vencimientos</small>
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
                    <div class="stat-mini-lotes" style="background:linear-gradient(135deg,#1a5276,#2980b9);">
                        <i class="fas fa-layer-group"></i>
                        <div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total lotes</div></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-mini-lotes" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);">
                        <i class="fas fa-check-circle"></i>
                        <div><div class="stat-value"><?= $stats['vigentes'] ?></div><div class="stat-label">Vigentes</div></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-mini-lotes" style="background:linear-gradient(135deg,#7d6608,#f39c12);">
                        <i class="fas fa-clock"></i>
                        <div><div class="stat-value"><?= $stats['por_vencer'] ?></div><div class="stat-label">Por vencer (&le;90d)</div></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-mini-lotes" style="background:linear-gradient(135deg,#922b21,#e74c3c);">
                        <i class="fas fa-times-circle"></i>
                        <div><div class="stat-value"><?= $stats['vencidos'] ?></div><div class="stat-label">Vencidos</div></div>
                    </div>
                </div>
            </div>

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

            <!-- ── ALERTAS VISUALES ── -->
            <?php if ($stats['vencidos'] > 0): ?>
            <div class="alerta-banner alerta-vencidos mb-3">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <strong><?= $stats['vencidos'] ?> lote(s) vencido(s)</strong> — Revisa y retira del inventario los productos vencidos.
            </div>
            <?php endif; ?>
            <?php if ($stats['por_vencer'] > 0): ?>
            <div class="alerta-banner alerta-pronto mb-3">
                <i class="fas fa-clock mr-2"></i>
                <strong><?= $stats['por_vencer'] ?> lote(s) por vencer</strong> en los próximos 90 días — Prioriza su venta.
            </div>
            <?php endif; ?>

            <!-- ── FILTROS RÁPIDOS ── -->
            <div class="filtros-card d-flex align-items-center gap-3 flex-wrap mb-3">
                <span style="font-weight:700;font-size:.85rem;color:#495057;"><i class="fas fa-filter mr-1"></i>Filtrar:</span>
                <button class="btn-filtro-lote active" data-filtro="todos">Todos <span class="badge-count"><?= $stats['total'] ?></span></button>
                <button class="btn-filtro-lote" data-filtro="vigente">Vigentes <span class="badge-count"><?= $stats['vigentes'] ?></span></button>
                <button class="btn-filtro-lote filtro-pronto" data-filtro="pronto">Por vencer <span class="badge-count"><?= $stats['por_vencer'] ?></span></button>
                <button class="btn-filtro-lote filtro-vencido" data-filtro="vencido">Vencidos <span class="badge-count"><?= $stats['vencidos'] ?></span></button>
                <button class="btn-filtro-lote filtro-agotado" data-filtro="agotado">Agotados <span class="badge-count"><?= $stats['agotados'] ?></span></button>
            </div>

            <!-- ── CARD PRINCIPAL ── -->
            <div class="card">
                <div class="card-header-lotes d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="mb-0"><i class="fas fa-list mr-2"></i>Registro de Lotes</h6>
                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group input-group-sm" style="width:240px;">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            </div>
                            <input type="text" id="buscarLote" class="form-control" placeholder="Buscar producto, lote...">
                        </div>
                        <span id="contadorLotes" class="badge badge-light" style="font-size:.8rem;"><?= count($lotes) ?> lotes</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="tablaLotes" class="table table-lotes table-bordered table-hover table-sm mb-0">
                            <thead>
                                <tr>
                                    <th style="width:55px;" class="text-center">#</th>
                                    <th>Producto</th>
                                    <th style="width:140px;">Código Lote</th>
                                    <th style="width:120px;" class="text-center">Vencimiento</th>
                                    <th style="width:110px;" class="text-center">Estado</th>
                                    <th style="width:90px;" class="text-center">Stock Ini.</th>
                                    <th style="width:100px;" class="text-center">Stock Act.</th>
                                    <th style="width:110px;" class="text-center">Consumo</th>
                                    <th style="width:120px;" class="text-center">Registrado</th>
                                    <th style="width:70px;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($lotes as $i => $l):
                                $dias = (int)$l['dias_para_vencer'];
                                $pct  = $l['stock_inicial'] > 0
                                    ? round(($l['stock_actual'] / $l['stock_inicial']) * 100)
                                    : 0;

                                if ($dias < 0)       { $estado_clase = 'fila-vencida';   $data_filtro = 'vencido'; }
                                elseif ($dias <= 30) { $estado_clase = 'fila-critica';   $data_filtro = 'pronto'; }
                                elseif ($dias <= 90) { $estado_clase = 'fila-pronto';    $data_filtro = 'pronto'; }
                                else                 { $estado_clase = '';               $data_filtro = 'vigente'; }

                                if ($l['stock_actual'] <= 0) $data_filtro = 'agotado';

                                $bar_color = $pct > 50 ? '#27ae60' : ($pct > 20 ? '#e67e22' : '#e74c3c');
                            ?>
                                <tr class="fila-lote <?= $estado_clase ?>"
                                    data-filtro="<?= $data_filtro ?>"
                                    data-search="<?= htmlspecialchars(strtolower($l['nombre_producto'].' '.$l['cod_producto'].' '.$l['codigo_lote'].' '.$l['laboratorio']), ENT_QUOTES) ?>">
                                    <td class="text-center">
                                        <div class="num-fila-lote"><?= $i + 1 ?></div>
                                    </td>
                                    <td>
                                        <div class="font-weight-bold" style="font-size:.88rem;">
                                            <?= htmlspecialchars(mb_strimwidth($l['nombre_producto'], 0, 50, '...')) ?>
                                        </div>
                                        <div style="font-size:.75rem;color:#999;">
                                            <?= htmlspecialchars($l['cod_producto']) ?>
                                            <?php if ($l['laboratorio']): ?>
                                                &bull; <?= htmlspecialchars($l['laboratorio']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge-categoria-lote"><?= htmlspecialchars($l['nombre_categoria']??'—') ?></span>
                                    </td>
                                    <td class="text-center">
                                        <code class="codigo-lote"><?= htmlspecialchars($l['codigo_lote']) ?></code>
                                    </td>
                                    <td class="text-center">
                                        <div style="font-weight:700;font-size:.85rem;color:<?= $dias<0?'#e74c3c':($dias<=90?'#e67e22':'#2d3436') ?>;">
                                            <?= date('d/m/Y', strtotime($l['fecha_vencimiento'])) ?>
                                        </div>
                                        <?= badgeVencimiento($dias) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        if ($dias < 0)       echo '<span class="badge-lote-vencido">VENCIDO</span>';
                                        elseif ($dias <= 30) echo '<span class="badge-lote-critico">CRÍTICO</span>';
                                        elseif ($dias <= 90) echo '<span class="badge-lote-pronto">POR VENCER</span>';
                                        else                 echo '<span class="badge-lote-vigente">VIGENTE</span>';
                                        ?>
                                    </td>
                                    <td class="text-center" style="font-weight:600;"><?= $l['stock_inicial'] ?></td>
                                    <td class="text-center">
                                        <?= badgeStockLote((int)$l['stock_actual']) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="barra-consumo-mini">
                                            <div class="barra-fill" style="width:<?= $pct ?>%;background:<?= $bar_color ?>;"></div>
                                        </div>
                                        <div style="font-size:.72rem;color:#999;margin-top:2px;"><?= $pct ?>%</div>
                                    </td>
                                    <td class="text-center" style="font-size:.8rem;color:#666;">
                                        <?= date('d/m/Y', strtotime($l['fecha_registro'])) ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-info btn-ver-lote" title="Ver detalle"
                                            data-id="<?= $l['id_lote'] ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($lotes)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5 text-muted">
                                        <i class="fas fa-box-open fa-3x mb-3 d-block" style="opacity:.2;"></i>
                                        No hay lotes registrados. Registra una compra para crear lotes automáticamente.
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

<!-- ── MODAL VER DETALLE LOTE ── -->
<div class="modal fade" id="modalVerLote" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#1a5276,#2980b9);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
                <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-boxes mr-2"></i>Detalle del Lote</h6>
                <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <!-- Contenido AJAX -->
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<?php include $ruta_base . 'includes/footer.php'; ?>
<script src="js/lotes.js"></script>


