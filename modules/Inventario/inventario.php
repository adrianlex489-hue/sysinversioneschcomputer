<?php
// modules/Inventario/inventario.php | SysInversiones CH Computer 2026
// Stock General — administración de inventario de repuestos y accesorios
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';
require_once $ruta_base . 'conf/auditoria.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexión BD no disponible.');
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);
verificarPermiso($pdo, 'inventario');

$id_usuario = $_SESSION['id_usuario'] ?? 0;

// ── PRG: leer y limpiar notificación ─────────────────────────────────────────
$swal = null;
if (isset($_SESSION['swal_inv'])) {
    $swal = $_SESSION['swal_inv'];
    unset($_SESSION['swal_inv']);
}

function redirigirInv(string $icon, string $title, string $text): void {
    $_SESSION['swal_inv'] = compact('icon', 'title', 'text');
    header('Location: inventario.php');
    exit;
}

// ── AJAX: detalle del producto + historial de movimientos ────────────────────
if (isset($_GET['accion']) && $_GET['accion'] === 'detalle_ajax') {
    header('Content-Type: text/html; charset=utf-8');
    $id = (int)($_GET['id_producto'] ?? 0);
    try {
        $stmt = $pdo->prepare("
            SELECT p.*,
                   c.nombre_categoria,
                   pr.razon_social AS proveedor
            FROM productos p
            LEFT JOIN categorias  c  ON p.id_categoria = c.id_categoria
            LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
            WHERE p.id_producto = ?
        ");
        $stmt->execute([$id]);
        $prod = $stmt->fetch();
        if (!$prod) {
            echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-2"></i>Producto no encontrado.</div>';
            exit;
        }

        $sc = (int)$prod['stock'];
        $sm = (int)$prod['stock_minimo'];
        $sx = (int)$prod['stock_maximo'];

        // Parsear imágenes
        $imgs = parsearImagenesInv($prod['imagenes'] ?? '');
        $img_principal = $imgs[0] ?? null;
        $imgs_json = htmlspecialchars(json_encode($imgs), ENT_QUOTES);

        echo '<div class="row">';

        // Columna izquierda: galería
        echo '<div class="col-md-5 mb-3">';
        if ($img_principal) {
            echo '<img id="invModalMainImg" src="'.htmlspecialchars($img_principal).'"
                data-imgs="'.$imgs_json.'"
                style="width:100%;height:220px;max-height:220px;object-fit:contain;border-radius:10px;background:#f8f9fa;border:1px solid #dee2e6;display:block;cursor:zoom-in;"
                alt="'.htmlspecialchars($prod['nombre_producto']).'">';
            if (count($imgs) > 1) {
                echo '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">';
                foreach ($imgs as $idx => $url) {
                    $border = $idx === 0 ? '#2980b9' : '#dee2e6';
                    echo '<div class="inv-gal-thumb'.($idx===0?' active':'').'" data-src="'.htmlspecialchars($url).'"
                        style="width:54px;height:54px;border-radius:6px;overflow:hidden;border:2px solid '.$border.';cursor:pointer;flex-shrink:0;">';
                    echo '<img src="'.htmlspecialchars($url).'" style="width:54px;height:54px;object-fit:contain;padding:3px;background:#fff;display:block;" alt="Imagen '.($idx+1).'">';
                    echo '</div>';
                }
                echo '</div>';
            }
        } else {
            echo '<div style="height:220px;background:#f8f9fa;border-radius:10px;display:flex;align-items:center;justify-content:center;border:1px solid #dee2e6;"><div class="text-center text-muted"><i class="fas fa-image" style="font-size:3rem;opacity:.3;"></i><p class="mt-2 mb-0" style="font-size:.85rem;">Sin imágenes</p></div></div>';
        }
        echo '</div>';

        // Columna derecha: info
        echo '<div class="col-md-7">';
        echo '<div class="row">';
        echo '<div class="col-6"><div class="inv-info-row"><div class="inv-info-icon" style="background:#e8f5e9;"><i class="fas fa-trademark" style="color:#1a7a4a;font-size:.8rem;"></i></div><div><div class="inv-info-label">Marca</div><div class="inv-info-value">'.htmlspecialchars($prod['marca'] ?? '—').'</div></div></div></div>';
        echo '<div class="col-6"><div class="inv-info-row"><div class="inv-info-icon" style="background:#e3f2fd;"><i class="fas fa-box" style="color:#1a5276;font-size:.8rem;"></i></div><div><div class="inv-info-label">Modelo</div><div class="inv-info-value">'.htmlspecialchars($prod['modelo'] ?? '—').'</div></div></div></div>';
        echo '<div class="col-6"><div class="inv-info-row"><div class="inv-info-icon" style="background:#f3e5f5;"><i class="fas fa-tags" style="color:#8e44ad;font-size:.8rem;"></i></div><div><div class="inv-info-label">Categoría</div><div class="inv-info-value">'.htmlspecialchars($prod['nombre_categoria'] ?? '—').'</div></div></div></div>';
        echo '<div class="col-6"><div class="inv-info-row"><div class="inv-info-icon" style="background:#e3f2fd;"><i class="fas fa-truck" style="color:#1a5276;font-size:.8rem;"></i></div><div><div class="inv-info-label">Proveedor</div><div class="inv-info-value">'.htmlspecialchars($prod['proveedor'] ?? '—').'</div></div></div></div>';
        echo '</div>';
        echo '<hr class="my-2">';
        $col = $sc <= 0 ? '#e74c3c' : ($sc <= $sm ? '#e67e22' : '#27ae60');
        $stock_label = $sc <= 0 ? 'Agotado' : ($sc <= $sm ? $sc.' uds — Stock bajo' : $sc.' uds');
        echo '<div class="row">';
        echo '<div class="col-4 text-center"><div style="background:#e8f5e9;border-radius:8px;padding:10px;"><div style="font-size:.7rem;color:#999;text-transform:uppercase;font-weight:600;">P. Venta</div><div style="font-size:1.05rem;font-weight:700;color:#1a7a4a;">S/. '.number_format($prod['precio_venta'],2).'</div></div></div>';
        echo '<div class="col-4 text-center"><div style="background:#e3f2fd;border-radius:8px;padding:10px;"><div style="font-size:.7rem;color:#999;text-transform:uppercase;font-weight:600;">P. Compra</div><div style="font-size:1.05rem;font-weight:700;color:#1a5276;">S/. '.number_format($prod['precio_compra'],2).'</div></div></div>';
        echo '<div class="col-4 text-center"><div style="background:#f8f9fa;border-radius:8px;padding:10px;"><div style="font-size:.7rem;color:#999;text-transform:uppercase;font-weight:600;">Stock</div><div style="font-size:.95rem;font-weight:700;color:'.$col.';">'.$stock_label.'</div></div></div>';
        echo '</div>';
        echo '<div class="row mt-2">';
        echo '<div class="col-4 text-center"><div style="font-size:.7rem;color:#999;font-weight:600;">Stock Mín.</div><div style="font-size:.9rem;font-weight:600;color:#e67e22;">'.$sm.' uds</div></div>';
        echo '<div class="col-4 text-center"><div style="font-size:.7rem;color:#999;font-weight:600;">Stock Máx.</div><div style="font-size:.9rem;font-weight:600;color:#8e44ad;">'.$sx.' uds</div></div>';
        echo '<div class="col-4 text-center"><div style="font-size:.7rem;color:#999;font-weight:600;">Código</div><div style="font-size:.8rem;"><code style="background:#e3f2fd;color:#1a5276;padding:1px 5px;border-radius:3px;">'.htmlspecialchars($prod['codigo']).'</code></div></div>';
        echo '</div>';
        if (!empty($prod['descripcion'])) {
            echo '<hr class="my-2">';
            echo '<div style="font-size:.72rem;color:#999;text-transform:uppercase;font-weight:600;letter-spacing:.4px;margin-bottom:4px;"><i class="fas fa-align-left mr-1"></i>Descripción</div>';
            echo '<div style="font-size:.85rem;color:#555;line-height:1.5;">'.htmlspecialchars($prod['descripcion']).'</div>';
        }
        echo '</div>'; // /col-md-7
        echo '</div>'; // /row

        // ── Historial de movimientos ──
        $stMov = $pdo->prepare("
            SELECT m.tipo, m.cantidad, m.descripcion, m.fecha,
                   u.nombre_completo AS usuario
            FROM movimientos_inventario m
            LEFT JOIN usuarios u ON m.id_usuario = u.id_usuario
            WHERE m.id_producto = ?
            ORDER BY m.fecha DESC
            LIMIT 50
        ");
        $stMov->execute([$id]);
        $movimientos = $stMov->fetchAll();
        $total_movs = count($movimientos);
        $movs_visibles = array_slice($movimientos, 0, 20);

        echo '<h6 style="font-size:.82rem;font-weight:700;color:#495057;text-transform:uppercase;margin-bottom:8px;"><i class="fas fa-history mr-1"></i>Últimos movimientos</h6>';
        if (empty($movimientos)) {
            echo '<div class="text-center text-muted py-3" style="font-size:.85rem;">';
            echo '<i class="fas fa-inbox fa-2x d-block mb-2" style="opacity:.2;"></i>Sin movimientos registrados.';
            echo '</div>';
        } else {
            echo '<div class="table-responsive"><table class="table table-sm table-bordered" style="font-size:.82rem;">';
            echo '<thead style="background:#1a5276;color:#fff;"><tr>';
            echo '<th><i class="fas fa-calendar mr-1"></i>Fecha</th>';
            echo '<th class="text-center"><i class="fas fa-exchange-alt mr-1"></i>Tipo</th>';
            echo '<th class="text-center"><i class="fas fa-sort-numeric-up mr-1"></i>Cantidad</th>';
            echo '<th><i class="fas fa-comment mr-1"></i>Descripción</th>';
            echo '<th><i class="fas fa-user mr-1"></i>Usuario</th>';
            echo '</tr></thead><tbody>';
            foreach ($movs_visibles as $mv) {
                $tipo = $mv['tipo'];
                switch ($tipo) {
                    case 'compra':   $bg='#d4edda'; $bc='#155724'; $label='COMPRA';   $icon='fa-cart-plus'; break;
                    case 'venta':    $bg='#cce5ff'; $bc='#004085'; $label='VENTA';    $icon='fa-cash-register'; break;
                    case 'ajuste':   $bg='#fff3cd'; $bc='#856404'; $label='AJUSTE';   $icon='fa-tools'; break;
                    case 'servicio': $bg='#e2d9f3'; $bc='#6c3483'; $label='SERVICIO'; $icon='fa-laptop-code'; break;
                    default:         $bg='#e9ecef'; $bc='#495057'; $label=strtoupper($tipo); $icon='fa-circle'; break;
                }
                echo '<tr>';
                echo '<td style="white-space:nowrap;">'.date('d/m/Y H:i', strtotime($mv['fecha'])).'</td>';
                echo '<td class="text-center"><span style="background:'.$bg.';color:'.$bc.';padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas '.$icon.' mr-1"></i>'.$label.'</span></td>';
                echo '<td class="text-center"><strong>'.intval($mv['cantidad']).'</strong></td>';
                echo '<td>'.htmlspecialchars($mv['descripcion'] ?? '—').'</td>';
                echo '<td>'.htmlspecialchars($mv['usuario'] ?? '—').'</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';

            // Filas ocultas (21-50) con botón "Ver más"
            if ($total_movs > 20) {
                $movs_extra = array_slice($movimientos, 20);
                echo '<div id="invMovsExtra" style="display:none;">';
                echo '<div class="table-responsive"><table class="table table-sm table-bordered" style="font-size:.82rem;"><tbody>';
                foreach ($movs_extra as $mv) {
                    $tipo = $mv['tipo'];
                    switch ($tipo) {
                        case 'compra':   $bg='#d4edda'; $bc='#155724'; $label='COMPRA';   $icon='fa-cart-plus'; break;
                        case 'venta':    $bg='#cce5ff'; $bc='#004085'; $label='VENTA';    $icon='fa-cash-register'; break;
                        case 'ajuste':   $bg='#fff3cd'; $bc='#856404'; $label='AJUSTE';   $icon='fa-tools'; break;
                        case 'servicio': $bg='#e2d9f3'; $bc='#6c3483'; $label='SERVICIO'; $icon='fa-laptop-code'; break;
                        default:         $bg='#e9ecef'; $bc='#495057'; $label=strtoupper($tipo); $icon='fa-circle'; break;
                    }
                    echo '<tr>';
                    echo '<td style="white-space:nowrap;">'.date('d/m/Y H:i', strtotime($mv['fecha'])).'</td>';
                    echo '<td class="text-center"><span style="background:'.$bg.';color:'.$bc.';padding:2px 8px;border-radius:20px;font-size:.72rem;font-weight:700;"><i class="fas '.$icon.' mr-1"></i>'.$label.'</span></td>';
                    echo '<td class="text-center"><strong>'.intval($mv['cantidad']).'</strong></td>';
                    echo '<td>'.htmlspecialchars($mv['descripcion'] ?? '—').'</td>';
                    echo '<td>'.htmlspecialchars($mv['usuario'] ?? '—').'</td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div></div>';
                echo '<div class="text-center mt-2">';
                echo '<button type="button" id="btnVerMasMovs" class="btn btn-sm btn-outline-secondary" style="font-size:.8rem;">';
                echo '<i class="fas fa-chevron-down mr-1"></i>Ver '.count($movs_extra).' movimiento(s) más';
                echo '</button></div>';
            }
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle mr-2"></i>Error: '.htmlspecialchars($e->getMessage()).'</div>';
    }
    exit;
}

// ── POST: reactivar producto ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reactivar_producto') {
    $id_producto = (int)($_POST['id_producto'] ?? 0);
    if (!$id_producto) redirigirInv('warning', 'Error', 'Producto no identificado.');
    try {
        $stP = $pdo->prepare("SELECT nombre_producto FROM productos WHERE id_producto = ?");
        $stP->execute([$id_producto]);
        $prod = $stP->fetch();
        if (!$prod) redirigirInv('warning', 'Error', 'Producto no encontrado.');
        $pdo->prepare("UPDATE productos SET estado = 1 WHERE id_producto = ?")->execute([$id_producto]);
        registrarAuditoria($pdo, 'inventario', 'editar', 'productos', $id_producto,
            "Producto reactivado desde inventario — {$prod['nombre_producto']}", 'estado', 0, 1);
        redirigirInv('success', 'Producto reactivado', '"'.$prod['nombre_producto'].'" fue reactivado y ya aparece en el inventario activo.');
    } catch (PDOException $e) {
        redirigirInv('error', 'Error al reactivar', $e->getMessage());
    }
}

// ── POST: ajuste de stock ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'ajustar_stock') {
    $id_producto = (int)($_POST['id_producto'] ?? 0);
    $tipo        = $_POST['tipo_ajuste'] ?? 'entrada';
    $cantidad    = (int)($_POST['cantidad'] ?? 0);
    $motivo      = trim($_POST['motivo'] ?? '') ?: null;

    if (!$id_producto) redirigirInv('warning', 'Error', 'Producto no identificado.');
    if ($cantidad <= 0) redirigirInv('warning', 'Cantidad inválida', 'La cantidad debe ser mayor a 0.');
    if (!in_array($tipo, ['entrada', 'salida', 'correccion'])) redirigirInv('warning', 'Tipo inválido', 'Tipo de ajuste no reconocido.');

    try {
        $pdo->beginTransaction();

        $stP = $pdo->prepare("SELECT stock, nombre_producto FROM productos WHERE id_producto = ?");
        $stP->execute([$id_producto]);
        $prod = $stP->fetch();
        if (!$prod) throw new Exception('Producto no encontrado.');

        $stock_actual = (int)$prod['stock'];

        if ($tipo === 'entrada') {
            $nuevo_stock = $stock_actual + $cantidad;
        } elseif ($tipo === 'salida') {
            if ($cantidad > $stock_actual) {
                throw new Exception('La cantidad a retirar ('.$cantidad.') supera el stock actual ('.$stock_actual.').');
            }
            $nuevo_stock = $stock_actual - $cantidad;
        } else {
            // corrección directa
            $nuevo_stock = $cantidad;
        }

        // Actualizar stock en productos
        $pdo->prepare("UPDATE productos SET stock = ? WHERE id_producto = ?")
            ->execute([$nuevo_stock, $id_producto]);

        // Registrar en movimientos_inventario (tipo='ajuste')
        $desc_mov = $motivo ?? ('Ajuste de stock: '.$tipo);
        $pdo->prepare("
            INSERT INTO movimientos_inventario (id_producto, id_usuario, tipo, cantidad, descripcion, fecha)
            VALUES (?, ?, 'ajuste', ?, ?, NOW())
        ")->execute([$id_producto, $id_usuario, $cantidad, $desc_mov]);

        $pdo->commit();

        // Auditoría del ajuste de stock
        $tipoLabel = $tipo === 'entrada' ? 'Entrada' : ($tipo === 'salida' ? 'Salida' : 'Corrección');
        registrarAuditoria($pdo, 'inventario', 'ajuste', 'productos', $id_producto,
            "$tipoLabel de stock — {$prod['nombre_producto']} — Motivo: " . ($motivo ?? 'sin motivo'),
            'stock', $stock_actual, $nuevo_stock);

        $tipoLabel = $tipo === 'entrada' ? 'Entrada' : ($tipo === 'salida' ? 'Salida' : 'Corrección');
        redirigirInv(
            'success',
            'Ajuste realizado',
            $tipoLabel.' de '.$cantidad.' uds aplicada a "'.htmlspecialchars($prod['nombre_producto']).'". Stock actualizado: '.$nuevo_stock.' uds.'
        );
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirigirInv('error', 'Error al ajustar', $e->getMessage());
    }
}

// ── QUERY PRINCIPAL ───────────────────────────────────────────────────────────
$productos = [];
$stats = ['total' => 0, 'ok' => 0, 'bajo' => 0, 'agotado' => 0, 'exceso' => 0, 'valor_total' => 0.0];
$categorias_filtro = [];
$productos_inactivos = [];

try {
    // Categorías para el filtro de la vista principal
    $categorias_filtro = $pdo->query("
        SELECT id_categoria, nombre_categoria
        FROM categorias WHERE estado=1 ORDER BY nombre_categoria ASC
    ")->fetchAll();

    $productos = $pdo->query("
        SELECT p.*,
               c.nombre_categoria,
               pr.razon_social AS proveedor
        FROM productos p
        LEFT JOIN categorias  c  ON p.id_categoria = c.id_categoria
        LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
        WHERE p.estado = 1
        ORDER BY
            CASE
                WHEN p.stock <= 0              THEN 3
                WHEN p.stock <= p.stock_minimo THEN 2
                ELSE 1
            END ASC,
            p.stock DESC
    ")->fetchAll();

    // Productos inactivos (para la pestaña de inactivos)
    $productos_inactivos = $pdo->query("
        SELECT p.id_producto, p.codigo, p.nombre_producto, p.marca, p.modelo,
               p.stock, p.precio_venta, p.precio_compra,
               c.nombre_categoria, pr.razon_social AS proveedor
        FROM productos p
        LEFT JOIN categorias  c  ON p.id_categoria = c.id_categoria
        LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
        WHERE p.estado = 0
        ORDER BY p.nombre_producto ASC
    ")->fetchAll();

    foreach ($productos as $p) {
        $stats['total']++;
        $stats['valor_total'] += (float)$p['stock'] * (float)$p['precio_compra'];
        $s = (int)$p['stock'];
        $mn = (int)$p['stock_minimo'];
        $mx = (int)$p['stock_maximo'];
        if ($s <= 0)          $stats['agotado']++;
        elseif ($s <= $mn)    $stats['bajo']++;
        elseif ($mx > 0 && $s > $mx) $stats['exceso']++;
        else                  $stats['ok']++;
    }
} catch (PDOException $e) {
    $swal = ['icon' => 'error', 'title' => 'Error BD', 'text' => 'Error al cargar datos: '.$e->getMessage()];
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function parsearImagenesInv(?string $campo): array {
    if (empty($campo)) return [];
    return array_values(array_filter(array_map('trim', explode(',', $campo))));
}

function badgeStockInv(int $stock, int $min, int $max): string {
    if ($stock <= 0)
        return '<span class="badge-inv-agotado"><i class="fas fa-times-circle mr-1"></i>Agotado</span>';
    if ($stock <= $min)
        return '<span class="badge-inv-bajo"><i class="fas fa-exclamation-triangle mr-1"></i>'.$stock.' uds</span>';
    if ($max > 0 && $stock > $max)
        return '<span class="badge-inv-exceso"><i class="fas fa-arrow-up mr-1"></i>'.$stock.' uds</span>';
    return '<span class="badge-inv-ok"><i class="fas fa-check-circle mr-1"></i>'.$stock.' uds</span>';
}

function pctStock(int $stock, int $max): int {
    if ($max <= 0) return 0;
    return min(100, (int)round(($stock / $max) * 100));
}

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>

<link rel="stylesheet" href="css/inventario.css?v=<?= time() ?>">

<div class="content-wrapper">

    <!-- ── CABECERA ── -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-inv d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4><i class="fas fa-laptop mr-2"></i>Stock General</h4>
                    <small>
                        <i class="fas fa-map-marker-alt mr-1"></i>
                        SysInversiones &rsaquo; Inventario &rsaquo; Stock General
                    </small>
                </div>
                <a href="/sysinversioneschcomputer/modules/transacciones/compras.php"
                   class="btn btn-light font-weight-bold btn-sm">
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
                        <i class="fas fa-microchip"></i>
                        <div>
                            <div class="stat-value"><?= $stats['total'] ?></div>
                            <div class="stat-label">Productos activos</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-mini-inv" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <div class="stat-value"><?= $stats['ok'] ?></div>
                            <div class="stat-label">Stock normal</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-mini-inv" style="background:linear-gradient(135deg,#7d6608,#f39c12);">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <div class="stat-value"><?= $stats['bajo'] ?></div>
                            <div class="stat-label">Stock bajo</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-mini-inv" style="background:linear-gradient(135deg,#922b21,#e74c3c);">
                        <i class="fas fa-times-circle"></i>
                        <div>
                            <div class="stat-value"><?= $stats['agotado'] ?></div>
                            <div class="stat-label">Agotados</div>
                        </div>
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
                            <i class="fas fa-arrow-up mr-1"></i>
                            <strong><?= $stats['exceso'] ?></strong> producto(s) sobre stock máximo
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── ALERTAS BANNER ── -->
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

            <!-- ── SWAL PRG ── -->
            <?php if ($swal): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon:             '<?= $swal['icon'] ?>',
                    title:            '<?= addslashes($swal['title']) ?>',
                    text:             '<?= addslashes($swal['text']) ?>',
                    confirmButtonColor: '#1a5276',
                    timer:            <?= in_array($swal['icon'], ['success', 'info']) ? 3500 : 0 ?>,
                    timerProgressBar: <?= in_array($swal['icon'], ['success', 'info']) ? 'true' : 'false' ?>,
                    showConfirmButton: <?= in_array($swal['icon'], ['success', 'info']) ? 'false' : 'true' ?>,
                });
            });
            </script>
            <?php endif; ?>

            <!-- ── FILTROS RÁPIDOS ── -->
            <div class="filtros-card-inv d-flex align-items-center flex-wrap mb-3" style="gap:8px;">
                <span style="font-weight:700;font-size:.85rem;color:#495057;"><i class="fas fa-filter mr-1"></i>Filtrar:</span>
                <button class="btn-filtro-inv active" data-filtro="todos">
                    Todos <span class="badge-count-inv"><?= $stats['total'] ?></span>
                </button>
                <button class="btn-filtro-inv" data-filtro="ok">
                    Normal <span class="badge-count-inv"><?= $stats['ok'] ?></span>
                </button>
                <button class="btn-filtro-inv filtro-bajo" data-filtro="bajo">
                    Stock bajo <span class="badge-count-inv"><?= $stats['bajo'] ?></span>
                </button>
                <button class="btn-filtro-inv filtro-agotado" data-filtro="agotado">
                    Agotados <span class="badge-count-inv"><?= $stats['agotado'] ?></span>
                </button>
                <?php if ($stats['exceso'] > 0): ?>
                <button class="btn-filtro-inv filtro-exceso" data-filtro="exceso">
                    Exceso <span class="badge-count-inv"><?= $stats['exceso'] ?></span>
                </button>
                <?php endif; ?>
                <?php if (!empty($categorias_filtro)): ?>
                <div style="margin-left:auto;">
                    <button type="button" id="btnFiltroCategoria"
                            class="btn-filtro-cat-inv"
                            title="Filtrar por categoría">
                        <i class="fas fa-layer-group mr-1"></i>
                        <span id="lblFiltroCategoria">Todas las categorías</span>
                        <i class="fas fa-chevron-down ml-1" style="font-size:.7rem;opacity:.7;"></i>
                    </button>
                    <!-- valor oculto que usa el JS para filtrar -->
                    <input type="hidden" id="filtroCategoriaInv" value="">
                </div>
                <?php endif; ?>
            </div>

            <!-- ── CARD PRINCIPAL ── -->
            <div class="card">
                <div class="card-header-inv d-flex align-items-center justify-content-between flex-wrap" style="gap:8px;">
                    <h6 class="mb-0"><i class="fas fa-tools mr-2"></i>Inventario de Productos</h6>
                    <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
                        <div class="input-group input-group-sm" style="width:240px;">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            </div>
                            <input type="text" id="buscarInventario" class="form-control"
                                   placeholder="Buscar producto, código, marca...">
                        </div>
                        <span id="contadorInventario" class="badge badge-light" style="font-size:.8rem;">
                            <?= count($productos) ?> productos
                        </span>
                        <button type="button" id="btnExportarInv"
                            style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.4);border-radius:6px;font-size:.8rem;font-weight:600;padding:4px 12px;cursor:pointer;transition:background .2s;">
                            <i class="fas fa-download mr-1"></i>Exportar
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="tablaInventario" class="table table-inv table-bordered table-hover table-sm mb-0">
                            <thead>
                                <tr>
                                    <th style="width:50px;" class="text-center">#</th>
                                    <th style="width:58px;min-width:58px;max-width:58px;" class="text-center"><i class="fas fa-image mr-1"></i>Img</th>
                                    <th><i class="fas fa-barcode mr-1"></i>Código</th>
                                    <th><i class="fas fa-laptop mr-1"></i>Producto / Marca</th>
                                    <th><i class="fas fa-tag mr-1"></i>Categoría</th>
                                    <th><i class="fas fa-truck mr-1"></i>Proveedor</th>
                                    <th style="width:115px;" class="text-center"><i class="fas fa-cubes mr-1"></i>Stock</th>
                                    <th style="width:100px;" class="text-center"><i class="fas fa-chart-bar mr-1"></i>Nivel</th>
                                    <th style="width:70px;" class="text-center"><i class="fas fa-arrow-down mr-1"></i>Mín.</th>
                                    <th style="width:70px;" class="text-center"><i class="fas fa-arrow-up mr-1"></i>Máx.</th>
                                    <th style="width:95px;" class="text-right"><i class="fas fa-dollar-sign mr-1"></i>P.Venta</th>
                                    <th style="width:120px;" class="text-center"><i class="fas fa-cogs mr-1"></i>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($productos as $i => $p):
                                $stock = (int)$p['stock'];
                                $smin  = (int)$p['stock_minimo'];
                                $smax  = (int)$p['stock_maximo'];
                                $pct   = pctStock($stock, $smax);

                                if ($stock <= 0) {
                                    $fila_clase = 'fila-agotado'; $data_filtro = 'agotado'; $bar_color = '#e74c3c';
                                } elseif ($stock <= $smin) {
                                    $fila_clase = 'fila-critico'; $data_filtro = 'bajo';    $bar_color = '#e67e22';
                                } elseif ($smax > 0 && $stock > $smax) {
                                    $fila_clase = 'fila-exceso';  $data_filtro = 'exceso';  $bar_color = '#8e44ad';
                                } else {
                                    $fila_clase = '';             $data_filtro = 'ok';      $bar_color = '#27ae60';
                                }

                                $data_search = strtolower(
                                    $p['nombre_producto'].' '.
                                    $p['codigo'].' '.
                                    ($p['marca'] ?? '').' '.
                                    ($p['modelo'] ?? '').' '.
                                    ($p['nombre_categoria'] ?? '').' '.
                                    ($p['proveedor'] ?? '')
                                );
                                $imgs_p       = parsearImagenesInv($p['imagenes'] ?? '');
                                $img_thumb    = $imgs_p[0] ?? null;
                            ?>
                                <tr class="fila-inv <?= $fila_clase ?>"
                                    data-filtro="<?= $data_filtro ?>"
                                    data-search="<?= htmlspecialchars($data_search, ENT_QUOTES) ?>"
                                    data-categoria="<?= htmlspecialchars(strtolower($p['nombre_categoria'] ?? ''), ENT_QUOTES) ?>"
                                    data-imgs="<?= htmlspecialchars(json_encode($imgs_p), ENT_QUOTES) ?>">
                                    <td class="text-center">
                                        <div class="num-fila-inv"><?= $i + 1 ?></div>
                                    </td>
                                    <td class="text-center" style="padding:6px!important;width:58px;min-width:58px;max-width:58px;">
                                        <?php if ($img_thumb): ?>
                                            <div class="inv-lista-img-wrap"
                                                 data-imgs="<?= htmlspecialchars(json_encode($imgs_p), ENT_QUOTES) ?>"
                                                 style="position:relative;width:44px;height:44px;margin:auto;border-radius:8px;overflow:hidden;border:2px solid #dee2e6;background:#f8f9fa;cursor:zoom-in;">
                                                <img src="<?= htmlspecialchars($img_thumb) ?>"
                                                     style="width:44px;height:44px;object-fit:contain;padding:3px;display:block;"
                                                     alt="<?= htmlspecialchars($p['nombre_producto']) ?>">
                                                <div class="inv-lista-zoom-overlay">
                                                    <i class="fas fa-search-plus"></i>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div style="width:44px;height:44px;background:linear-gradient(135deg,#e8f4fd,#d6eaf8);border-radius:8px;display:flex;align-items:center;justify-content:center;margin:auto;border:2px solid #dee2e6;">
                                                <i class="fas fa-laptop" style="font-size:.9rem;color:#aed6f1;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code style="font-size:.8rem;color:#1a5276;background:#e3f2fd;padding:2px 6px;border-radius:4px;">
                                            <?= htmlspecialchars($p['codigo']) ?>
                                        </code>
                                    </td>
                                    <td>
                                        <div class="font-weight-bold" style="font-size:.88rem;">
                                            <?= htmlspecialchars(mb_strimwidth($p['nombre_producto'], 0, 50, '...')) ?>
                                        </div>
                                        <?php if (!empty($p['marca']) || !empty($p['modelo'])): ?>
                                        <div style="font-size:.75rem;color:#999;">
                                            <?php if (!empty($p['marca'])): ?>
                                                <i class="fas fa-trademark mr-1"></i><?= htmlspecialchars($p['marca']) ?>
                                            <?php endif; ?>
                                            <?php if (!empty($p['modelo'])): ?>
                                                &bull; <?= htmlspecialchars($p['modelo']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-cat-inv">
                                            <?= htmlspecialchars($p['nombre_categoria'] ?? '—') ?>
                                        </span>
                                    </td>
                                    <td style="font-size:.82rem;color:#555;max-width:130px;">
                                        <?php if (!empty($p['proveedor'])): ?>
                                        <span title="<?= htmlspecialchars($p['proveedor']) ?>">
                                            <i class="fas fa-truck mr-1 text-muted" style="font-size:.7rem;"></i>
                                            <?= htmlspecialchars(mb_strimwidth($p['proveedor'], 0, 22, '…')) ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?= badgeStockInv($stock, $smin, $smax) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="barra-stock-wrap">
                                            <div class="barra-stock-fill"
                                                 style="width:<?= $pct ?>%;background:<?= $bar_color ?>;"></div>
                                        </div>
                                        <div style="font-size:.7rem;color:#999;margin-top:2px;"><?= $pct ?>%</div>
                                    </td>
                                    <td class="text-center" style="font-size:.85rem;color:#e67e22;font-weight:600;">
                                        <?= $smin ?>
                                    </td>
                                    <td class="text-center" style="font-size:.85rem;color:#8e44ad;font-weight:600;">
                                        <?= $smax ?>
                                    </td>
                                    <td class="text-right" style="font-weight:700;color:#1a7a4a;font-size:.88rem;">
                                        S/. <?= number_format($p['precio_venta'], 2) ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-info btn-ver-producto-inv mr-1"
                                                data-id="<?= $p['id_producto'] ?>"
                                                title="Ver detalle"
                                                style="padding:3px 7px;">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning btn-ajustar-stock"
                                                data-id="<?= $p['id_producto'] ?>"
                                                data-nombre="<?= htmlspecialchars($p['nombre_producto'], ENT_QUOTES) ?>"
                                                data-stock="<?= $stock ?>"
                                                title="Ajustar stock"
                                                style="padding:3px 7px;">
                                            <i class="fas fa-sliders-h"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div><!-- /card -->

            <!-- ── PRODUCTOS INACTIVOS ── -->
            <?php if (!empty($productos_inactivos)): ?>
            <div class="card mt-4">
                <div class="card-header-inv d-flex align-items-center justify-content-between"
                     style="background:linear-gradient(90deg,#555,#777);">
                    <h6 class="mb-0">
                        <i class="fas fa-archive mr-2"></i>Productos Inactivos
                        <span class="badge badge-light ml-2" style="font-size:.78rem;"><?= count($productos_inactivos) ?></span>
                    </h6>
                    <button class="btn btn-sm" id="btnToggleInactivos"
                            style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.4);border-radius:6px;font-size:.8rem;font-weight:600;padding:4px 12px;">
                        <i class="fas fa-eye mr-1"></i>Mostrar
                    </button>
                </div>
                <div id="tablaInactivosWrap" style="display:none;">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-inv table-bordered table-sm mb-0" style="opacity:.85;">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:50px;">#</th>
                                    <th><i class="fas fa-barcode mr-1"></i>Código</th>
                                    <th><i class="fas fa-laptop mr-1"></i>Producto</th>
                                    <th><i class="fas fa-tag mr-1"></i>Categoría</th>
                                    <th><i class="fas fa-truck mr-1"></i>Proveedor</th>
                                    <th class="text-center"><i class="fas fa-cubes mr-1"></i>Stock</th>
                                    <th class="text-right"><i class="fas fa-dollar-sign mr-1"></i>P.Venta</th>
                                    <th class="text-center"><i class="fas fa-cogs mr-1"></i>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($productos_inactivos as $j => $pi): ?>
                                <tr style="background:#f8f9fa;">
                                    <td class="text-center"><div class="num-fila-inv" style="background:#e9ecef;color:#888;"><?= $j+1 ?></div></td>
                                    <td><code style="font-size:.8rem;color:#888;background:#e9ecef;padding:2px 6px;border-radius:4px;"><?= htmlspecialchars($pi['codigo']) ?></code></td>
                                    <td>
                                        <div class="font-weight-bold" style="font-size:.88rem;color:#666;"><?= htmlspecialchars(mb_strimwidth($pi['nombre_producto'], 0, 50, '...')) ?></div>
                                        <?php if (!empty($pi['marca'])): ?>
                                        <div style="font-size:.75rem;color:#aaa;"><i class="fas fa-trademark mr-1"></i><?= htmlspecialchars($pi['marca']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge-cat-inv" style="background:#e9ecef;color:#888;"><?= htmlspecialchars($pi['nombre_categoria'] ?? '—') ?></span></td>
                                    <td style="font-size:.82rem;color:#aaa;"><?= htmlspecialchars(mb_strimwidth($pi['proveedor'] ?? '—', 0, 22, '…')) ?></td>
                                    <td class="text-center" style="font-size:.85rem;color:#aaa;font-weight:600;"><?= (int)$pi['stock'] ?> uds</td>
                                    <td class="text-right" style="font-size:.85rem;color:#aaa;">S/. <?= number_format($pi['precio_venta'], 2) ?></td>
                                    <td class="text-center">
                                        <form method="POST" action="inventario.php" style="display:inline;">
                                            <input type="hidden" name="accion" value="reactivar_producto">
                                            <input type="hidden" name="id_producto" value="<?= $pi['id_producto'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success"
                                                    title="Reactivar producto"
                                                    style="padding:3px 10px;font-size:.78rem;"
                                                    onclick="return confirm('¿Reactivar el producto \'<?= htmlspecialchars(addslashes($pi['nombre_producto'])) ?>\'?')">
                                                <i class="fas fa-redo mr-1"></i>Reactivar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /container-fluid -->
    </div><!-- /content -->

</div><!-- /content-wrapper -->

<!-- ══════════════════════════════════════════════════════════
     MODAL: Ver detalle del producto
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalVerProductoInv" tabindex="-1" role="dialog" aria-labelledby="modalVerProductoInvLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(90deg,#1a5276,#2980b9);color:#fff;border-radius:14px 14px 0 0;">
                <h5 class="modal-title" id="modalVerProductoInvLabel">
                    <i class="fas fa-microchip mr-2"></i>Detalle del Producto
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="min-height:200px;">
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                    <p class="mt-3 text-muted">Cargando detalle...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: Ajustar stock
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalAjustarStock" tabindex="-1" role="dialog" aria-labelledby="modalAjustarStockLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(90deg,#1a5276,#2980b9);color:#fff;border-radius:14px 14px 0 0;">
                <h5 class="modal-title" id="modalAjustarStockLabel">
                    <i class="fas fa-sliders-h mr-2"></i>Ajustar Stock
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formAjustarStock" method="POST" action="inventario.php">
                <input type="hidden" name="accion" value="ajustar_stock">
                <input type="hidden" name="id_producto" id="ajuste_id_producto">
                <input type="hidden" name="tipo_ajuste" id="ajuste_tipo" value="entrada">
                <div class="modal-body">

                    <!-- Producto info -->
                    <div class="mb-3 p-3" style="background:#f0f8ff;border-radius:8px;border-left:4px solid #2980b9;">
                        <div style="font-size:.78rem;color:#666;text-transform:uppercase;font-weight:600;letter-spacing:.4px;">
                            <i class="fas fa-laptop mr-1"></i>Producto
                        </div>
                        <div class="font-weight-bold" id="ajuste_nombre_producto" style="font-size:.95rem;"></div>
                        <div style="font-size:.85rem;color:#555;margin-top:4px;">
                            Stock actual: <strong id="ajuste_stock_actual" style="color:#1a5276;"></strong>
                        </div>
                    </div>

                    <!-- Tipo de ajuste -->
                    <div class="form-group">
                        <label style="font-weight:700;font-size:.85rem;color:#495057;">
                            <i class="fas fa-exchange-alt mr-1"></i>Tipo de ajuste
                        </label>
                        <div class="tipo-ajuste-group">
                            <button type="button" class="btn-tipo-ajuste entrada activo" data-tipo="entrada">
                                <i class="fas fa-plus-circle d-block mb-1" style="font-size:1.1rem;"></i>
                                Entrada
                            </button>
                            <button type="button" class="btn-tipo-ajuste salida" data-tipo="salida">
                                <i class="fas fa-minus-circle d-block mb-1" style="font-size:1.1rem;"></i>
                                Salida
                            </button>
                            <button type="button" class="btn-tipo-ajuste correccion" data-tipo="correccion">
                                <i class="fas fa-edit d-block mb-1" style="font-size:1.1rem;"></i>
                                Corrección
                            </button>
                        </div>
                    </div>

                    <!-- Cantidad -->
                    <div class="form-group">
                        <label for="ajuste_cantidad" style="font-weight:700;font-size:.85rem;color:#495057;">
                            <i class="fas fa-sort-numeric-up mr-1"></i>Cantidad
                        </label>
                        <input type="number" id="ajuste_cantidad" name="cantidad"
                               class="form-control" min="1" placeholder="Ingresa la cantidad..." required>
                        <div id="preview_nuevo_stock" class="mt-2" style="display:none;font-size:.85rem;color:#555;">
                            Nuevo stock estimado: <span></span>
                        </div>
                    </div>

                    <!-- Motivo -->
                    <div class="form-group">
                        <label for="ajuste_motivo" style="font-weight:700;font-size:.85rem;color:#495057;">
                            <i class="fas fa-comment mr-1"></i>Motivo / Descripción
                        </label>
                        <textarea id="ajuste_motivo" name="motivo" class="form-control" rows="2"
                                  placeholder="Ej: Recepción de mercadería, corrección de inventario..." required></textarea>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancelar
                    </button>
                    <button type="submit" id="btnSubmitAjuste" class="btn btn-primary btn-sm"
                            style="background:#1a5276;border-color:#1a5276;">
                        <i class="fas fa-save mr-1"></i>Guardar Ajuste
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ MODAL FILTRO POR CATEGORÍA ══ -->
<div class="modal fade" id="modalFiltroCategoria" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
<div class="modal-content" style="border:none;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.22);">
    <div style="background:linear-gradient(135deg,#1a5276,#2980b9);padding:18px 22px;display:flex;align-items:center;justify-content:space-between;">
        <div>
            <h6 style="color:#fff;font-weight:700;margin:0;font-size:1rem;">
                <i class="fas fa-layer-group mr-2"></i>Filtrar por Categoría
            </h6>
            <small style="color:rgba(255,255,255,.75);font-size:.78rem;">Selecciona una categoría para filtrar el inventario</small>
        </div>
        <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.4rem;line-height:1;" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <div style="padding:14px 20px 0;background:#f8f9fa;border-bottom:1px solid #e9ecef;">
        <div class="input-group input-group-sm">
            <div class="input-group-prepend">
                <span class="input-group-text" style="background:#fff;border-right:none;">
                    <i class="fas fa-search text-muted" style="font-size:.8rem;"></i>
                </span>
            </div>
            <input type="text" id="buscarCategoriaModal" class="form-control"
                   placeholder="Buscar categoría..." style="border-left:none;font-size:.85rem;">
        </div>
        <div style="padding:8px 0 10px;font-size:.75rem;color:#999;">
            <span id="contadorCatModal"></span>
        </div>
    </div>
    <div class="modal-body" style="padding:0;max-height:360px;overflow-y:auto;">
        <div class="cat-modal-item cat-modal-todas active" data-cat="" data-nombre="Todas las categorías">
            <div class="cat-modal-icon" style="background:linear-gradient(135deg,#1a5276,#2980b9);">
                <i class="fas fa-th-large"></i>
            </div>
            <div class="cat-modal-info">
                <div class="cat-modal-nombre">Todas las categorías</div>
                <div class="cat-modal-sub">Mostrar todo el inventario</div>
            </div>
            <div class="cat-modal-check"><i class="fas fa-check-circle"></i></div>
        </div>
        <?php
        $cat_colores = [
            ['#1a7a4a','#27ae60'], ['#1a5276','#2980b9'], ['#7d6608','#f39c12'],
            ['#6c3483','#8e44ad'], ['#117a8b','#17a2b8'], ['#922b21','#e74c3c'],
            ['#1a5276','#117a8b'], ['#4a235a','#8e44ad'], ['#1e8449','#27ae60'],
            ['#784212','#e67e22'],
        ];
        foreach ($categorias_filtro as $ci => $cat):
            $col = $cat_colores[$ci % count($cat_colores)];
            $cnt = 0;
            foreach ($productos as $pp) {
                if (strtolower($pp['nombre_categoria'] ?? '') === strtolower($cat['nombre_categoria'])) $cnt++;
            }
        ?>
        <div class="cat-modal-item"
             data-cat="<?= htmlspecialchars(strtolower($cat['nombre_categoria']), ENT_QUOTES) ?>"
             data-nombre="<?= htmlspecialchars($cat['nombre_categoria'], ENT_QUOTES) ?>">
            <div class="cat-modal-icon" style="background:linear-gradient(135deg,<?= $col[0] ?>,<?= $col[1] ?>);">
                <i class="fas fa-tag"></i>
            </div>
            <div class="cat-modal-info">
                <div class="cat-modal-nombre"><?= htmlspecialchars($cat['nombre_categoria']) ?></div>
                <div class="cat-modal-sub"><?= $cnt ?> producto<?= $cnt !== 1 ? 's' : '' ?> en inventario</div>
            </div>
            <div class="cat-modal-check"><i class="fas fa-check-circle"></i></div>
        </div>
        <?php endforeach; ?>
    </div>
    <div style="padding:12px 20px;background:#f8f9fa;border-top:1px solid #e9ecef;display:flex;align-items:center;justify-content:space-between;">
        <button type="button" id="btnLimpiarCategoria"
                style="background:none;border:none;color:#e74c3c;font-size:.82rem;font-weight:600;cursor:pointer;padding:0;">
            <i class="fas fa-times-circle mr-1"></i>Limpiar filtro
        </button>
        <button type="button" class="btn btn-sm" data-dismiss="modal"
                style="background:#1a5276;color:#fff;border-radius:8px;font-weight:600;padding:6px 18px;">
            <i class="fas fa-check mr-1"></i>Aplicar
        </button>
    </div>
</div>
</div>
</div>

<!-- ══ LIGHTBOX ZOOM — igual que productos ══ -->
<div id="invImgLightbox" role="dialog" aria-modal="true" aria-label="Vista ampliada">
    <button class="inv-lb-close" id="invLbClose" title="Cerrar (Esc)"><i class="fas fa-times"></i></button>
    <button class="inv-lb-prev hidden" id="invLbPrev" title="Anterior"><i class="fas fa-chevron-left"></i></button>
    <button class="inv-lb-next hidden" id="invLbNext" title="Siguiente"><i class="fas fa-chevron-right"></i></button>
    <div class="inv-lb-img-wrap">
        <img id="invLbMainImg" src="" alt="Imagen ampliada">
    </div>
    <div class="inv-lb-counter" id="invLbCounter"></div>
    <div class="inv-lb-thumbs" id="invLbThumbs"></div>
</div>

<?php
// Obtener categorías para el filtro del modal de exportación
$categorias_export = [];
try {
    $categorias_export = $pdo->query("SELECT id_categoria, nombre_categoria FROM categorias WHERE estado=1 ORDER BY nombre_categoria ASC")->fetchAll();
} catch (PDOException $e) {}
?>

<!-- ══ MODAL EXPORTAR INVENTARIO ══ -->
<div class="modal fade" id="modalExportarInv" tabindex="-1" aria-hidden="true">
<div class="modal-dialog" style="max-width:440px;">
<div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a5276,#117a8b);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
    <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-download mr-2"></i>Exportar Inventario</h6>
    <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-4">

    <!-- Filtro estado de stock -->
    <div class="form-group mb-3">
        <label style="font-weight:600;font-size:.83rem;color:#495057;"><i class="fas fa-cubes mr-1 text-muted"></i>Estado de Stock</label>
        <select id="inv_exp_stock" class="form-control form-control-sm">
            <option value="all">Todos</option>
            <option value="ok">Normal</option>
            <option value="bajo">Stock bajo</option>
            <option value="agotado">Agotados</option>
            <option value="exceso">Exceso</option>
        </select>
    </div>

    <!-- Filtro categoría -->
    <div class="form-group mb-3">
        <label style="font-weight:600;font-size:.83rem;color:#495057;"><i class="fas fa-tag mr-1 text-muted"></i>Categoría</label>
        <select id="inv_exp_categoria" class="form-control form-control-sm">
            <option value="all">Todas las categorías</option>
            <?php foreach ($categorias_export as $cat): ?>
            <option value="<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nombre_categoria']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Botones de formato -->
    <div style="background:#f8f9fa;border-radius:10px;padding:14px;border:1px solid #e9ecef;">
        <p style="font-weight:600;font-size:.82rem;color:#495057;margin-bottom:10px;"><i class="fas fa-file-export mr-1"></i>Selecciona el formato:</p>
        <div class="d-flex" style="gap:8px;">
            <button type="button" id="inv_btn_csv"
                style="flex:1;background:linear-gradient(135deg,#1a7a4a,#27ae60);color:#fff;border:none;border-radius:8px;padding:10px 8px;font-weight:700;font-size:.82rem;cursor:pointer;">
                <i class="fas fa-file-csv d-block mb-1" style="font-size:1.3rem;"></i>CSV
            </button>
            <button type="button" id="inv_btn_excel"
                style="flex:1;background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;border:none;border-radius:8px;padding:10px 8px;font-weight:700;font-size:.82rem;cursor:pointer;">
                <i class="fas fa-file-excel d-block mb-1" style="font-size:1.3rem;"></i>Excel
            </button>
            <button type="button" id="inv_btn_pdf"
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
$extra_js = '<script src="js/inventario.js?v=' . time() . '"></script>';
include $ruta_base . 'includes/footer.php';
?>