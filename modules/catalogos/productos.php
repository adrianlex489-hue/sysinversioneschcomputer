<?php
// ============================================================
// modules/catalogos/productos.php | Botica 2026
// Imágenes: campo `imagenes` TEXT en tabla productos
// Formato: rutas separadas por coma (máx. 4)
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
verificarPermiso($pdo, 'productos');

// ── Configuración uploads ─────────────────────────────────────────────────────
define('UPLOAD_DIR', $ruta_base . 'public/uploads/productos/');
define('UPLOAD_URL', '/botica-2026/public/uploads/productos/');
define('MAX_IMGS',   4);
define('MAX_MB',     2);

// ── Patrón PRG ────────────────────────────────────────────────────────────────
$swal = null;
if (isset($_SESSION['swal_prod'])) { $swal = $_SESSION['swal_prod']; unset($_SESSION['swal_prod']); }

function redirigirProd(string $icon, string $title, string $text): void {
    $_SESSION['swal_prod'] = compact('icon', 'title', 'text');
    header('Location: productos.php'); exit;
}

// ── Helper: parsear campo imagenes (string CSV → array de rutas) ──────────────
function parsearImagenes(?string $campo): array {
    if (empty($campo)) return [];
    return array_values(array_filter(array_map('trim', explode(',', $campo))));
}

// ── Helper: subir archivos y devolver rutas nuevas ────────────────────────────
function subirArchivos(array $files, array $rutas_actuales): array {
    $exts_ok = ['jpg', 'jpeg', 'png', 'webp'];
    $rutas   = $rutas_actuales;

    foreach ($files['tmp_name'] as $i => $tmp) {
        if (count($rutas) >= MAX_IMGS) break;
        if ($files['error'][$i] !== UPLOAD_ERR_OK || empty($tmp)) continue;

        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $exts_ok)) continue;
        if ($files['size'][$i] > MAX_MB * 1024 * 1024) continue;

        $nombre = 'prod_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($tmp, UPLOAD_DIR . $nombre)) {
            $rutas[] = UPLOAD_URL . $nombre;
        }
    }
    return $rutas;
}

// ── Helper: eliminar archivo físico ──────────────────────────────────────────
function eliminarArchivo(string $url): void {
    $path = str_replace(UPLOAD_URL, UPLOAD_DIR, $url);
    if (file_exists($path)) unlink($path);
}

// ── CRUD ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion      = $_POST['accion']      ?? '';
    $id_producto = (int)($_POST['id_producto'] ?? 0);

    $codigo             = strtoupper(trim($_POST['codigo']          ?? ''));
    $codigo_barra       = trim($_POST['codigo_barra']               ?? '') ?: null;
    $nombre_producto    = strtoupper(trim($_POST['nombre_producto'] ?? ''));
    $laboratorio        = trim($_POST['laboratorio']                ?? '') ?: null;
    $presentacion       = trim($_POST['presentacion']               ?? '') ?: null;
    $concentracion      = trim($_POST['concentracion']              ?? '') ?: null;
    $descripcion        = trim($_POST['descripcion']                ?? '') ?: null;
    $id_categoria       = (int)($_POST['id_categoria']              ?? 0);
    $id_unidad          = (int)($_POST['id_unidad']                 ?? 0);
    $id_proveedor       = ($_POST['id_proveedor'] ?? '') !== '' ? (int)$_POST['id_proveedor'] : null;
    $stock_minimo       = (int)($_POST['stock_minimo']              ?? 5);
    $stock_maximo       = (int)($_POST['stock_maximo']              ?? 100);
    $precio_compra      = (float)($_POST['precio_compra']           ?? 0);
    $precio_venta       = (float)($_POST['precio_venta']            ?? 0);
    $requiere_receta    = (int)($_POST['requiere_receta']           ?? 0);
    $registro_sanitario = trim($_POST['registro_sanitario']         ?? '') ?: null;
    $estado             = (int)($_POST['estado']                    ?? 1);

    try {
        // ── CREAR ─────────────────────────────────────────────────────────────
        if ($accion === 'crear') {
            if (empty($nombre_producto) || !$id_categoria || !$id_unidad) {
                redirigirProd('warning', 'Campos incompletos', 'Nombre, Categoría y Unidad son obligatorios.');
            }
            if (empty($codigo)) {
                $codigo = 'PRD-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            }

            // Subir imágenes y construir CSV
            $rutas = [];
            if (!empty($_FILES['imagenes']['name'][0])) {
                $rutas = subirArchivos($_FILES['imagenes'], []);
            }
            $imagenes_csv = implode(',', $rutas) ?: null;

            $sql = "INSERT INTO productos
                        (codigo, codigo_barra, nombre_producto, laboratorio, presentacion,
                         concentracion, descripcion, id_categoria, id_unidad, id_proveedor,
                         stock_minimo, stock_maximo, precio_compra, precio_venta,
                         requiere_receta, registro_sanitario, imagenes, estado)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)";
            $pdo->prepare($sql)->execute([
                $codigo, $codigo_barra, $nombre_producto, $laboratorio, $presentacion,
                $concentracion, $descripcion, $id_categoria, $id_unidad, $id_proveedor,
                $stock_minimo, $stock_maximo, $precio_compra, $precio_venta,
                $requiere_receta, $registro_sanitario, $imagenes_csv
            ]);
            redirigirProd('success', '¡Registrado!', "El producto $nombre_producto fue registrado correctamente.");

        // ── ACTUALIZAR ────────────────────────────────────────────────────────
        } elseif ($accion === 'actualizar' && $id_producto > 0) {

            // Obtener rutas actuales del producto
            $stmt = $pdo->prepare("SELECT imagenes FROM productos WHERE id_producto=?");
            $stmt->execute([$id_producto]);
            $rutas_actuales = parsearImagenes($stmt->fetchColumn());

            // Eliminar imágenes marcadas para borrar
            $eliminar = $_POST['eliminar_imagen'] ?? [];
            foreach ($eliminar as $url) {
                eliminarArchivo($url);
                $rutas_actuales = array_values(array_filter($rutas_actuales, fn($r) => $r !== $url));
            }

            // Subir nuevas imágenes
            if (!empty($_FILES['imagenes']['name'][0])) {
                $rutas_actuales = subirArchivos($_FILES['imagenes'], $rutas_actuales);
            }

            $imagenes_csv = implode(',', $rutas_actuales) ?: null;

            $sql = "UPDATE productos SET
                        codigo=?, codigo_barra=?, nombre_producto=?, laboratorio=?,
                        presentacion=?, concentracion=?, descripcion=?, id_categoria=?,
                        id_unidad=?, id_proveedor=?, stock_minimo=?, stock_maximo=?,
                        precio_compra=?, precio_venta=?, requiere_receta=?,
                        registro_sanitario=?, imagenes=?, estado=?
                    WHERE id_producto=?";
            $pdo->prepare($sql)->execute([
                $codigo, $codigo_barra, $nombre_producto, $laboratorio,
                $presentacion, $concentracion, $descripcion, $id_categoria,
                $id_unidad, $id_proveedor, $stock_minimo, $stock_maximo,
                $precio_compra, $precio_venta, $requiere_receta,
                $registro_sanitario, $imagenes_csv, $estado, $id_producto
            ]);
            redirigirProd('success', '¡Actualizado!', "El producto fue actualizado correctamente.");

        // ── DESACTIVAR ────────────────────────────────────────────────────────
        } elseif ($accion === 'desactivar' && $id_producto > 0) {
            $pdo->prepare("UPDATE productos SET estado=0 WHERE id_producto=?")->execute([$id_producto]);
            redirigirProd('info', 'Desactivado', "El producto fue desactivado y movido a inactivos.");

        // ── REACTIVAR ─────────────────────────────────────────────────────────
        } elseif ($accion === 'reactivar' && $id_producto > 0) {
            $pdo->prepare("UPDATE productos SET estado=1 WHERE id_producto=?")->execute([$id_producto]);
            redirigirProd('success', '¡Reactivado!', "El producto fue reactivado correctamente.");

        // ── ELIMINAR PERMANENTE ───────────────────────────────────────────────
        } elseif ($accion === 'eliminar_permanente' && $id_producto > 0) {
            $stmt = $pdo->prepare("SELECT imagenes FROM productos WHERE id_producto=?");
            $stmt->execute([$id_producto]);
            $rutas = parsearImagenes($stmt->fetchColumn());
            foreach ($rutas as $url) eliminarArchivo($url);

            $pdo->prepare("DELETE FROM productos WHERE id_producto=?")->execute([$id_producto]);
            redirigirProd('success', 'Eliminado', "El producto fue eliminado permanentemente.");
        }

    } catch (PDOException $e) {
        $msg = ($e->getCode() == 23000)
            ? 'El código o código de barra ya está registrado.'
            : 'Error: ' . $e->getMessage();
        redirigirProd('error', 'Error', $msg);
    }
}

// ── DATOS ─────────────────────────────────────────────────────────────────────
$productos_activos = $productos_inactivos = [];
$categorias = $unidades = $proveedores = [];

try {
    $sql_base = "SELECT p.*,
                    c.nombre_categoria,
                    u.nombre_unidad,
                    pr.razon_social AS nombre_proveedor
                 FROM productos p
                 LEFT JOIN categorias  c  ON p.id_categoria  = c.id_categoria
                 LEFT JOIN unidades    u  ON p.id_unidad     = u.id_unidad
                 LEFT JOIN proveedores pr ON p.id_proveedor  = pr.id_proveedor";

    $productos_activos   = $pdo->query($sql_base . " WHERE p.estado=1 ORDER BY p.nombre_producto")->fetchAll();
    $productos_inactivos = $pdo->query($sql_base . " WHERE p.estado=0 ORDER BY p.nombre_producto")->fetchAll();

    // Parsear imágenes de cada producto
    foreach ([&$productos_activos, &$productos_inactivos] as &$lista) {
        foreach ($lista as &$p) {
            $p['imgs'] = parsearImagenes($p['imagenes'] ?? '');
            $p['img_principal'] = $p['imgs'][0] ?? null;
        }
    }
    unset($lista, $p);

    $categorias  = $pdo->query("SELECT id_categoria, nombre_categoria, descripcion FROM categorias WHERE estado=1 ORDER BY nombre_categoria")->fetchAll();
    $unidades    = $pdo->query("SELECT id_unidad, nombre_unidad FROM unidades WHERE estado=1 ORDER BY nombre_unidad")->fetchAll();
    $proveedores = $pdo->query("SELECT id_proveedor, razon_social, telefono, direccion FROM proveedores WHERE estado=1 ORDER BY razon_social")->fetchAll();

} catch (PDOException $e) {
    $swal = ['icon' => 'error', 'title' => 'Error', 'text' => 'Error al cargar datos: ' . $e->getMessage()];
}

$total_activos   = count($productos_activos);
$total_inactivos = count($productos_inactivos);
$stock_bajo  = count(array_filter($productos_activos, fn($p) => $p['stock'] > 0 && $p['stock'] <= $p['stock_minimo']));
$agotados    = count(array_filter($productos_activos, fn($p) => $p['stock'] <= 0));

// ── Helper: badge stock ───────────────────────────────────────────────────────
function badgeStock(int $stock, int $min): string {
    if ($stock <= 0)    return '<span class="stock-badge stock-critico">Agotado</span>';
    if ($stock <= $min) return '<span class="stock-badge stock-bajo">' . $stock . ' uds</span>';
    return '<span class="stock-badge stock-ok">' . $stock . ' uds</span>';
}

// ── Helper: data-imagenes para JS (array de URLs) ─────────────────────────────
function dataImagenes(array $imgs): string {
    return htmlspecialchars(json_encode($imgs), ENT_QUOTES);
}

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>

<!-- CSS del módulo -->
<link rel="stylesheet" href="css/productos.css">

<div class="content-wrapper">

    <!-- ── CABECERA ── -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-prod d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4><i class="fas fa-capsules mr-2"></i>Gestión de Productos</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>Botica 2026 &rsaquo; Catálogos &rsaquo; Productos</small>
                </div>
                <button class="btn btn-light font-weight-bold" data-toggle="modal" data-target="#modalCrearProducto">
                    <i class="fas fa-plus-circle mr-1"></i> Nuevo Producto
                </button>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- ── STATS ── -->
            <div class="row mb-4">
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-mini" style="background:linear-gradient(135deg,#1a5276,#2980b9);">
                        <i class="fas fa-capsules"></i>
                        <div><div class="stat-value"><?= $total_activos ?></div><div class="stat-label">Productos activos</div></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-mini" style="background:linear-gradient(135deg,#922b21,#e74c3c);">
                        <i class="fas fa-ban"></i>
                        <div><div class="stat-value"><?= $total_inactivos ?></div><div class="stat-label">Inactivos</div></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <?php
                    $stock_bajo = count(array_filter($productos_activos, fn($p) => $p['stock'] <= $p['stock_minimo'] && $p['stock'] > 0));
                    ?>
                    <div class="stat-mini" style="background:linear-gradient(135deg,#7d6608,#f39c12);">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div><div class="stat-value"><?= $stock_bajo ?></div><div class="stat-label">Stock bajo</div></div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-2">
                    <?php $agotados = count(array_filter($productos_activos, fn($p) => $p['stock'] <= 0)); ?>
                    <div class="stat-mini" style="background:linear-gradient(135deg,#6c3483,#9b59b6);">
                        <i class="fas fa-times-circle"></i>
                        <div><div class="stat-value"><?= $agotados ?></div><div class="stat-label">Agotados</div></div>
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
                    timer: <?= in_array($swal['icon'],['success','info']) ? 3000 : 0 ?>,
                    timerProgressBar: <?= in_array($swal['icon'],['success','info']) ? 'true' : 'false' ?>,
                    showConfirmButton: <?= in_array($swal['icon'],['success','info']) ? 'false' : 'true' ?>,
                });
            });
            </script>
            <?php endif; ?>

            <!-- ── CARD PRINCIPAL ── -->
            <div class="card">
                <div class="card-header-prod d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="mb-0"><i class="fas fa-list mr-2"></i>Catálogo de Productos</h6>
                    <div class="d-flex align-items-center gap-2">
                        <!-- Búsqueda grid -->
                        <div class="input-group input-group-sm mr-2" style="width:220px;">
                            <div class="input-group-prepend"><span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span></div>
                            <input type="text" id="buscarGrid" class="form-control" placeholder="Buscar...">
                        </div>
                        <!-- Toggle vista -->
                        <div class="btn-group btn-group-sm">
                            <button id="btnVistaGrid"  class="btn btn-outline-light btn-vista" title="Vista tarjetas"><i class="fas fa-th-large"></i></button>
                            <button id="btnVistaLista" class="btn btn-outline-light btn-vista" title="Vista lista"><i class="fas fa-list"></i></button>
                        </div>
                        <!-- Tabs -->
                        <ul class="nav mb-0 ml-2" style="border:none;">
                            <li class="nav-item">
                                <a class="nav-link active text-white font-weight-bold py-1 px-2" data-toggle="pill" href="#tab-activos-prod">
                                    Activos <span class="badge badge-light ml-1"><?= $total_activos ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-white font-weight-bold py-1 px-2" data-toggle="pill" href="#tab-inactivos-prod">
                                    Inactivos <span class="badge badge-light ml-1"><?= $total_inactivos ?></span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="card-body">
                    <div class="tab-content">

                        <!-- ── TAB ACTIVOS ── -->
                        <div class="tab-pane fade show active" id="tab-activos-prod">

                            <!-- VISTA GRID -->
                            <div id="vistaGrid">
                                <div class="row">
                                <?php foreach ($productos_activos as $p):
                                    $img_src = $p['img_principal'] ?? null;
                                    $stock = (int)$p['stock'];
                                    $smin  = (int)$p['stock_minimo'];
                                    // JSON seguro para atributos HTML
                                    $imgs_attr = htmlspecialchars(json_encode($p['imgs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <div class="col-xl-3 col-lg-4 col-md-6 mb-4 producto-grid-item"
                                         data-search="<?= htmlspecialchars($p['nombre_producto'].' '.$p['laboratorio'].' '.$p['nombre_categoria'], ENT_QUOTES) ?>">
                                        <div class="card producto-card" data-imgs="<?= $imgs_attr ?>"><?php if ($img_src): ?>
                                                <img src="<?= htmlspecialchars($img_src) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['nombre_producto']) ?>">
                                            <?php else: ?>
                                                <div class="img-placeholder"><i class="fas fa-pills"></i></div>
                                            <?php endif; ?>
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <span class="badge-categoria"><?= htmlspecialchars($p['nombre_categoria'] ?? '—') ?></span>
                                                    <?php if ($p['requiere_receta']): ?>
                                                        <span class="badge-receta"><i class="fas fa-prescription mr-1"></i>Receta</span>
                                                    <?php endif; ?>
                                                </div>
                                                <h6 class="font-weight-bold mb-1" style="font-size:.88rem;line-height:1.3;" title="<?= htmlspecialchars($p['nombre_producto']) ?>">
                                                    <?= htmlspecialchars(mb_strimwidth($p['nombre_producto'], 0, 45, '...')) ?>
                                                </h6>
                                                <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($p['laboratorio'] ?? '—') ?></div>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <div>
                                                        <div class="precio-venta">S/. <?= number_format($p['precio_venta'],2) ?></div>
                                                        <div class="precio-compra">Costo: S/. <?= number_format($p['precio_compra'],2) ?></div>
                                                    </div>
                                                    <?= badgeStock($stock, $smin) ?>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-light p-2">
                                                <div class="btn-group btn-group-sm w-100">
                                                    <button class="btn btn-info btn-ver-producto" title="Ver detalle"
                                                        data-id="<?= $p['id_producto'] ?>"
                                                        data-nombre="<?= htmlspecialchars($p['nombre_producto'],ENT_QUOTES) ?>"
                                                        data-codigo="<?= htmlspecialchars($p['codigo'],ENT_QUOTES) ?>"
                                                        data-laboratorio="<?= htmlspecialchars($p['laboratorio']??'',ENT_QUOTES) ?>"
                                                        data-presentacion="<?= htmlspecialchars($p['presentacion']??'',ENT_QUOTES) ?>"
                                                        data-concentracion="<?= htmlspecialchars($p['concentracion']??'',ENT_QUOTES) ?>"
                                                        data-categoria="<?= htmlspecialchars($p['nombre_categoria']??'',ENT_QUOTES) ?>"
                                                        data-unidad="<?= htmlspecialchars($p['nombre_unidad']??'',ENT_QUOTES) ?>"
                                                        data-proveedor="<?= htmlspecialchars($p['nombre_proveedor']??'',ENT_QUOTES) ?>"
                                                        data-registro="<?= htmlspecialchars($p['registro_sanitario']??'',ENT_QUOTES) ?>"
                                                        data-descripcion="<?= htmlspecialchars($p['descripcion']??'',ENT_QUOTES) ?>"
                                                        data-precio-venta="<?= $p['precio_venta'] ?>"
                                                        data-precio-compra="<?= $p['precio_compra'] ?>"
                                                        data-stock="<?= $p['stock'] ?>"
                                                        data-stock-min="<?= $p['stock_minimo'] ?>"
                                                        data-stock-max="<?= $p['stock_maximo'] ?>"
                                                        data-receta="<?= $p['requiere_receta'] ?>"
                                                        data-estado="<?= $p['estado'] ?>"
                                                        data-imagenes="<?= $imgs_attr ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-warning btn-editar-producto" title="Editar"
                                                        data-id="<?= $p['id_producto'] ?>"
                                                        data-codigo="<?= htmlspecialchars($p['codigo'],ENT_QUOTES) ?>"
                                                        data-codigo-barra="<?= htmlspecialchars($p['codigo_barra']??'',ENT_QUOTES) ?>"
                                                        data-nombre="<?= htmlspecialchars($p['nombre_producto'],ENT_QUOTES) ?>"
                                                        data-laboratorio="<?= htmlspecialchars($p['laboratorio']??'',ENT_QUOTES) ?>"
                                                        data-presentacion="<?= htmlspecialchars($p['presentacion']??'',ENT_QUOTES) ?>"
                                                        data-concentracion="<?= htmlspecialchars($p['concentracion']??'',ENT_QUOTES) ?>"
                                                        data-descripcion="<?= htmlspecialchars($p['descripcion']??'',ENT_QUOTES) ?>"
                                                        data-id-categoria="<?= $p['id_categoria'] ?>"
                                                        data-categoria="<?= htmlspecialchars($p['nombre_categoria']??'',ENT_QUOTES) ?>"
                                                        data-id-unidad="<?= $p['id_unidad'] ?>"
                                                        data-unidad="<?= htmlspecialchars($p['nombre_unidad']??'',ENT_QUOTES) ?>"
                                                        data-id-proveedor="<?= $p['id_proveedor']??'' ?>"
                                                        data-proveedor="<?= htmlspecialchars($p['nombre_proveedor']??'',ENT_QUOTES) ?>"
                                                        data-stock-min="<?= $p['stock_minimo'] ?>"
                                                        data-stock-max="<?= $p['stock_maximo'] ?>"
                                                        data-precio-compra="<?= $p['precio_compra'] ?>"
                                                        data-precio-venta="<?= $p['precio_venta'] ?>"
                                                        data-receta="<?= $p['requiere_receta'] ?>"
                                                        data-registro="<?= htmlspecialchars($p['registro_sanitario']??'',ENT_QUOTES) ?>"
                                                        data-estado="<?= $p['estado'] ?>"
                                                        data-imagenes="<?= $imgs_attr ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-danger btn-desactivar-producto" title="Desactivar"
                                                        data-id="<?= $p['id_producto'] ?>"
                                                        data-nombre="<?= htmlspecialchars($p['nombre_producto'],ENT_QUOTES) ?>">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- VISTA LISTA -->
                            <div id="vistaLista" style="display:none;">
                                <div class="table-responsive">
                                    <table id="tablaProductos" class="table table-productos table-bordered table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th style="width:50px;">Img</th>
                                                <th>Código</th>
                                                <th>Nombre</th>
                                                <th>Categoría</th>
                                                <th>Laboratorio</th>
                                                <th class="text-right">P.Venta</th>
                                                <th class="text-center">Stock</th>
                                                <th class="text-center">Receta</th>
                                                <th class="text-center" style="width:120px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($productos_activos as $p):
                                            $imgs_attr = htmlspecialchars(json_encode($p['imgs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                                        ?>
                                            <tr>
                                                <td class="text-center">
                                                    <?php if ($p['img_principal']): ?>
                                                        <img src="<?= htmlspecialchars($p['img_principal']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;">
                                                    <?php else: ?>
                                                        <div style="width:40px;height:40px;background:#e8f4fd;border-radius:6px;display:flex;align-items:center;justify-content:center;margin:auto;">
                                                            <i class="fas fa-pills text-muted" style="font-size:.8rem;"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><code><?= htmlspecialchars($p['codigo']) ?></code></td>
                                                <td class="font-weight-bold"><?= htmlspecialchars($p['nombre_producto']) ?></td>
                                                <td><span class="badge-categoria"><?= htmlspecialchars($p['nombre_categoria']??'—') ?></span></td>
                                                <td><?= htmlspecialchars($p['laboratorio']??'—') ?></td>
                                                <td class="text-right font-weight-bold text-success">S/. <?= number_format($p['precio_venta'],2) ?></td>
                                                <td class="text-center"><?= badgeStock((int)$p['stock'],(int)$p['stock_minimo']) ?></td>
                                                <td class="text-center">
                                                    <?= $p['requiere_receta'] ? '<span class="badge-receta"><i class="fas fa-prescription"></i></span>' : '<span class="text-muted">—</span>' ?>
                                                </td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-info btn-ver-producto" title="Ver"
                                                        data-id="<?= $p['id_producto'] ?>"
                                                        data-nombre="<?= htmlspecialchars($p['nombre_producto'],ENT_QUOTES) ?>"
                                                        data-codigo="<?= htmlspecialchars($p['codigo'],ENT_QUOTES) ?>"
                                                        data-laboratorio="<?= htmlspecialchars($p['laboratorio']??'',ENT_QUOTES) ?>"
                                                        data-presentacion="<?= htmlspecialchars($p['presentacion']??'',ENT_QUOTES) ?>"
                                                        data-concentracion="<?= htmlspecialchars($p['concentracion']??'',ENT_QUOTES) ?>"
                                                        data-categoria="<?= htmlspecialchars($p['nombre_categoria']??'',ENT_QUOTES) ?>"
                                                        data-unidad="<?= htmlspecialchars($p['nombre_unidad']??'',ENT_QUOTES) ?>"
                                                        data-proveedor="<?= htmlspecialchars($p['nombre_proveedor']??'',ENT_QUOTES) ?>"
                                                        data-registro="<?= htmlspecialchars($p['registro_sanitario']??'',ENT_QUOTES) ?>"
                                                        data-descripcion="<?= htmlspecialchars($p['descripcion']??'',ENT_QUOTES) ?>"
                                                        data-precio-venta="<?= $p['precio_venta'] ?>"
                                                        data-precio-compra="<?= $p['precio_compra'] ?>"
                                                        data-stock="<?= $p['stock'] ?>"
                                                        data-stock-min="<?= $p['stock_minimo'] ?>"
                                                        data-stock-max="<?= $p['stock_maximo'] ?>"
                                                        data-receta="<?= $p['requiere_receta'] ?>"
                                                        data-estado="<?= $p['estado'] ?>"
                                                        data-imagenes="<?= $imgs_attr ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning btn-editar-producto" title="Editar"
                                                        data-id="<?= $p['id_producto'] ?>"
                                                        data-codigo="<?= htmlspecialchars($p['codigo'],ENT_QUOTES) ?>"
                                                        data-codigo-barra="<?= htmlspecialchars($p['codigo_barra']??'',ENT_QUOTES) ?>"
                                                        data-nombre="<?= htmlspecialchars($p['nombre_producto'],ENT_QUOTES) ?>"
                                                        data-laboratorio="<?= htmlspecialchars($p['laboratorio']??'',ENT_QUOTES) ?>"
                                                        data-presentacion="<?= htmlspecialchars($p['presentacion']??'',ENT_QUOTES) ?>"
                                                        data-concentracion="<?= htmlspecialchars($p['concentracion']??'',ENT_QUOTES) ?>"
                                                        data-descripcion="<?= htmlspecialchars($p['descripcion']??'',ENT_QUOTES) ?>"
                                                        data-id-categoria="<?= $p['id_categoria'] ?>"
                                                        data-categoria="<?= htmlspecialchars($p['nombre_categoria']??'',ENT_QUOTES) ?>"
                                                        data-id-unidad="<?= $p['id_unidad'] ?>"
                                                        data-unidad="<?= htmlspecialchars($p['nombre_unidad']??'',ENT_QUOTES) ?>"
                                                        data-id-proveedor="<?= $p['id_proveedor']??'' ?>"
                                                        data-proveedor="<?= htmlspecialchars($p['nombre_proveedor']??'',ENT_QUOTES) ?>"
                                                        data-stock-min="<?= $p['stock_minimo'] ?>"
                                                        data-stock-max="<?= $p['stock_maximo'] ?>"
                                                        data-precio-compra="<?= $p['precio_compra'] ?>"
                                                        data-precio-venta="<?= $p['precio_venta'] ?>"
                                                        data-receta="<?= $p['requiere_receta'] ?>"
                                                        data-registro="<?= htmlspecialchars($p['registro_sanitario']??'',ENT_QUOTES) ?>"
                                                        data-estado="<?= $p['estado'] ?>"
                                                        data-imagenes="<?= $imgs_attr ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger btn-desactivar-producto" title="Desactivar"
                                                        data-id="<?= $p['id_producto'] ?>"
                                                        data-nombre="<?= htmlspecialchars($p['nombre_producto'],ENT_QUOTES) ?>">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- ── TAB INACTIVOS ── -->
                        <div class="tab-pane fade" id="tab-inactivos-prod">
                            <div class="table-responsive">
                                <table class="table table-productos table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Código</th><th>Nombre</th><th>Categoría</th>
                                            <th class="text-right">P.Venta</th>
                                            <th class="text-center" style="width:120px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($productos_inactivos as $p): ?>
                                        <tr class="table-light text-muted">
                                            <td><code><?= htmlspecialchars($p['codigo']) ?></code></td>
                                            <td><?= htmlspecialchars($p['nombre_producto']) ?></td>
                                            <td><?= htmlspecialchars($p['nombre_categoria']??'—') ?></td>
                                            <td class="text-right">S/. <?= number_format($p['precio_venta'],2) ?></td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-success btn-reactivar-producto" title="Reactivar"
                                                    data-id="<?= $p['id_producto'] ?>"
                                                    data-nombre="<?= htmlspecialchars($p['nombre_producto'],ENT_QUOTES) ?>">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger btn-eliminar-producto" title="Eliminar permanente"
                                                    data-id="<?= $p['id_producto'] ?>"
                                                    data-nombre="<?= htmlspecialchars($p['nombre_producto'],ENT_QUOTES) ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL VER PRODUCTO
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalVerProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;">

            <!-- Cabecera azul farmacia -->
            <div style="background:linear-gradient(135deg,#1a5276,#2980b9);padding:20px 24px;position:relative;">
                <button type="button" class="close" data-dismiss="modal"
                    style="position:absolute;top:12px;right:16px;color:#fff;opacity:.8;font-size:1.4rem;">&times;</button>
                <div class="d-flex align-items-center gap-3">
                    <div style="width:56px;height:56px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-pills" style="font-size:1.6rem;color:#fff;"></i>
                    </div>
                    <div>
                        <h5 id="ver_nombre_producto" style="color:#fff;font-weight:700;margin:0;font-size:1.05rem;"></h5>
                        <div class="mt-1 d-flex gap-2 flex-wrap">
                            <span style="background:rgba(255,255,255,.2);color:#fff;font-size:.75rem;padding:2px 10px;border-radius:20px;font-family:monospace;">
                                <i class="fas fa-barcode mr-1"></i><span id="ver_codigo_badge"></span>
                            </span>
                            <span id="ver_badge_receta" style="background:#fde8e8;color:#c0392b;font-size:.75rem;padding:2px 10px;border-radius:20px;display:none;">
                                <i class="fas fa-prescription mr-1"></i>Requiere Receta
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-body" style="padding:20px 24px;">
                <div class="row">

                    <!-- Columna izquierda: Galería -->
                    <div class="col-md-5 mb-3">
                        <img id="ver_main_img" src="" alt="Producto" class="modal-prod-main-img mb-2">
                        <div id="ver_no_img" style="height:220px;background:#f8f9fa;border-radius:10px;display:flex;align-items:center;justify-content:center;border:1px solid #dee2e6;">
                            <div class="text-center text-muted">
                                <i class="fas fa-image" style="font-size:3rem;opacity:.3;"></i>
                                <p class="mt-2 mb-0" style="font-size:.85rem;">Sin imágenes</p>
                            </div>
                        </div>
                        <div id="ver_gallery" class="modal-prod-gallery mt-2"></div>
                    </div>

                    <!-- Columna derecha: Info -->
                    <div class="col-md-7">

                        <div class="row">
                            <div class="col-6">
                                <div class="info-row">
                                    <div class="info-icon" style="background:#e8f5e9;"><i class="fas fa-flask" style="color:#1a7a4a;font-size:.8rem;"></i></div>
                                    <div><div class="info-label">Laboratorio</div><div id="ver_laboratorio" class="info-value"></div></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-row">
                                    <div class="info-icon" style="background:#e3f2fd;"><i class="fas fa-box" style="color:#1a5276;font-size:.8rem;"></i></div>
                                    <div><div class="info-label">Presentación</div><div id="ver_presentacion" class="info-value"></div></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-row">
                                    <div class="info-icon" style="background:#fff3e0;"><i class="fas fa-tint" style="color:#e67e22;font-size:.8rem;"></i></div>
                                    <div><div class="info-label">Concentración</div><div id="ver_concentracion" class="info-value"></div></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-row">
                                    <div class="info-icon" style="background:#f3e5f5;"><i class="fas fa-tags" style="color:#8e44ad;font-size:.8rem;"></i></div>
                                    <div><div class="info-label">Categoría</div><div id="ver_categoria" class="info-value"></div></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-row">
                                    <div class="info-icon" style="background:#e8f5e9;"><i class="fas fa-ruler" style="color:#1a7a4a;font-size:.8rem;"></i></div>
                                    <div><div class="info-label">Unidad</div><div id="ver_unidad" class="info-value"></div></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-row">
                                    <div class="info-icon" style="background:#e3f2fd;"><i class="fas fa-truck" style="color:#1a5276;font-size:.8rem;"></i></div>
                                    <div><div class="info-label">Proveedor</div><div id="ver_proveedor" class="info-value"></div></div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-2">

                        <!-- Precios y Stock -->
                        <div class="row">
                            <div class="col-4 text-center">
                                <div style="background:#e8f5e9;border-radius:8px;padding:10px;">
                                    <div style="font-size:.7rem;color:#999;text-transform:uppercase;font-weight:600;">P. Venta</div>
                                    <div id="ver_precio_venta" style="font-size:1.1rem;font-weight:700;color:#1a7a4a;"></div>
                                </div>
                            </div>
                            <div class="col-4 text-center">
                                <div style="background:#e3f2fd;border-radius:8px;padding:10px;">
                                    <div style="font-size:.7rem;color:#999;text-transform:uppercase;font-weight:600;">P. Compra</div>
                                    <div id="ver_precio_compra" style="font-size:1.1rem;font-weight:700;color:#1a5276;"></div>
                                </div>
                            </div>
                            <div class="col-4 text-center">
                                <div style="background:#f8f9fa;border-radius:8px;padding:10px;">
                                    <div style="font-size:.7rem;color:#999;text-transform:uppercase;font-weight:600;">Stock</div>
                                    <div id="ver_stock"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-4 text-center">
                                <div style="font-size:.7rem;color:#999;font-weight:600;">Stock Mín.</div>
                                <div id="ver_stock_minimo" style="font-size:.9rem;font-weight:600;"></div>
                            </div>
                            <div class="col-4 text-center">
                                <div style="font-size:.7rem;color:#999;font-weight:600;">Stock Máx.</div>
                                <div id="ver_stock_maximo" style="font-size:.9rem;font-weight:600;"></div>
                            </div>
                            <div class="col-4 text-center">
                                <div style="font-size:.7rem;color:#999;font-weight:600;">Estado</div>
                                <div id="ver_estado_prod"></div>
                            </div>
                        </div>

                        <hr class="my-2">
                        <div class="info-row">
                            <div class="info-icon" style="background:#fce4ec;"><i class="fas fa-shield-alt" style="color:#c0392b;font-size:.8rem;"></i></div>
                            <div><div class="info-label">Registro Sanitario</div><div id="ver_registro_sanitario" class="info-value"></div></div>
                        </div>
                        <div class="info-row mb-0">
                            <div class="info-icon" style="background:#f8f9fa;"><i class="fas fa-align-left" style="color:#666;font-size:.8rem;"></i></div>
                            <div><div class="info-label">Descripción</div><div id="ver_descripcion" class="info-value" style="font-size:.85rem;color:#666;"></div></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL CREAR PRODUCTO
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCrearProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;border-radius:12px 12px 0 0;">
                <h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i>Registrar Nuevo Producto</h5>
                <button type="button" class="close" style="color:#fff;" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body">
                    <div class="row">
                        <!-- Columna izquierda -->
                        <div class="col-md-8">
                            <h6 class="font-weight-bold text-muted mb-3"><i class="fas fa-info-circle mr-1"></i>Información del Producto</h6>
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-barcode mr-1 text-muted"></i>Código <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-tag"></i></span></div>
                                        <input type="text" class="form-control" name="codigo" placeholder="PRD-001" maxlength="50">
                                    </div>
                                    <small class="text-muted">Vacío = autogenerado</small>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-barcode mr-1 text-muted"></i>Código de Barra</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-barcode"></i></span></div>
                                        <input type="text" class="form-control" name="codigo_barra" id="crear_codigo_barra" maxlength="100" placeholder="Autogenerado o manual">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn_generar_barcode" title="Generar código de barras automático">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <small class="text-muted">Vacío = autogenerado al guardar</small>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-shield-alt mr-1 text-muted"></i>Registro Sanitario</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-certificate"></i></span></div>
                                        <input type="text" class="form-control" name="registro_sanitario" maxlength="100" placeholder="E.F.A. N° 12345">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label-prod"><i class="fas fa-pills mr-1 text-muted"></i>Nombre del Producto <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-capsules"></i></span></div>
                                    <input type="text" class="form-control" name="nombre_producto" required maxlength="150" placeholder="Ej: Paracetamol 500mg Tabletas">
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-flask mr-1 text-muted"></i>Laboratorio</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-industry"></i></span></div>
                                        <input type="text" class="form-control" name="laboratorio" maxlength="100" placeholder="Ej: Bayer">
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-box mr-1 text-muted"></i>Presentación</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-box-open"></i></span></div>
                                        <input type="text" class="form-control" name="presentacion" maxlength="100" placeholder="Ej: Caja x 100 tab.">
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-tint mr-1 text-muted"></i>Concentración</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-percent"></i></span></div>
                                        <input type="text" class="form-control" name="concentracion" maxlength="100" placeholder="Ej: 500mg">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-tags mr-1 text-muted"></i>Categoría <span class="text-danger">*</span></label>
                                    <input type="hidden" name="id_categoria" id="crear_id_categoria" required>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-layer-group"></i></span></div>
                                        <input type="text" class="form-control" id="crear_nombre_categoria" placeholder="Seleccione categoría..." readonly style="background:#fff;cursor:pointer;" onclick="abrirModalSelector('categoria','crear')">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="abrirModalSelector('categoria','crear')" title="Seleccionar categoría">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-ruler mr-1 text-muted"></i>Unidad <span class="text-danger">*</span></label>
                                    <input type="hidden" name="id_unidad" id="crear_id_unidad" required>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-balance-scale"></i></span></div>
                                        <input type="text" class="form-control" id="crear_nombre_unidad" placeholder="Seleccione unidad..." readonly style="background:#fff;cursor:pointer;" onclick="abrirModalSelector('unidad','crear')">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="abrirModalSelector('unidad','crear')" title="Seleccionar unidad">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-truck mr-1 text-muted"></i>Proveedor</label>
                                    <input type="hidden" name="id_proveedor" id="crear_id_proveedor">
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-building"></i></span></div>
                                        <input type="text" class="form-control" id="crear_nombre_proveedor" placeholder="Sin proveedor (opcional)" readonly style="background:#fff;cursor:pointer;" onclick="abrirModalSelector('proveedor','crear')">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="abrirModalSelector('proveedor','crear')" title="Seleccionar proveedor">
                                                <i class="fas fa-search"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="limpiarSelector('proveedor','crear')" title="Quitar proveedor">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-3">
                                    <label class="form-label-prod"><i class="fas fa-arrow-down mr-1 text-muted"></i>Stock Mínimo</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-exclamation-triangle text-warning"></i></span></div>
                                        <input type="number" class="form-control" name="stock_minimo" value="5" min="0">
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="form-label-prod"><i class="fas fa-arrow-up mr-1 text-muted"></i>Stock Máximo</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-warehouse"></i></span></div>
                                        <input type="number" class="form-control" name="stock_maximo" value="100" min="0">
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="form-label-prod"><i class="fas fa-shopping-cart mr-1 text-muted"></i>Precio Compra</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
                                        <input type="number" step="0.01" class="form-control" name="precio_compra" value="0.00" min="0">
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="form-label-prod"><i class="fas fa-tag mr-1 text-muted"></i>Precio Venta <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
                                        <input type="number" step="0.01" class="form-control" name="precio_venta" value="0.00" min="0" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label class="form-label-prod"><i class="fas fa-prescription mr-1 text-muted"></i>¿Requiere Receta?</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-file-medical"></i></span></div>
                                        <select class="form-control" name="requiere_receta">
                                            <option value="0">No requiere receta</option>
                                            <option value="1">Sí requiere receta médica</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="form-label-prod"><i class="fas fa-align-left mr-1 text-muted"></i>Descripción</label>
                                    <textarea class="form-control form-control-sm" name="descripcion" rows="2" placeholder="Descripción breve del producto..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Columna derecha: Imágenes -->
                        <div class="col-md-4">
                            <h6 class="font-weight-bold text-muted mb-3"><i class="fas fa-images mr-1"></i>Imágenes del Producto <small class="text-muted">(máx. 4)</small></h6>
                            <div class="upload-area" id="crear_upload_area">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Arrastra imágenes aquí o haz clic para seleccionar</p>
                                <small class="text-muted">JPG, PNG, WEBP — máx. 2MB c/u</small>
                            </div>
                            <input type="file" name="imagenes[]" id="crear_imagenes" multiple accept="image/*" style="display:none;">
                            <div class="preview-container" id="crear_preview_nuevas"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
                    <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;font-weight:600;">
                        <i class="fas fa-save mr-1"></i>Guardar Producto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL EDITAR PRODUCTO
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditarProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#a04000,#e67e22);color:#fff;border-radius:12px 12px 0 0;">
                <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Editar Producto</h5>
                <button type="button" class="close" style="color:#fff;" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="formEditarProducto">
                <input type="hidden" name="accion" value="actualizar">
                <input type="hidden" name="id_producto" id="editar_id_producto">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-tag mr-1 text-muted"></i>Código <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-tag"></i></span></div>
                                        <input type="text" class="form-control" name="codigo" id="editar_codigo" maxlength="50" required>
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-barcode mr-1 text-muted"></i>Código de Barra</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-barcode"></i></span></div>
                                        <input type="text" class="form-control" name="codigo_barra" id="editar_codigo_barra" maxlength="100">
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-certificate mr-1 text-muted"></i>Registro Sanitario</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-shield-alt"></i></span></div>
                                        <input type="text" class="form-control" name="registro_sanitario" id="editar_registro_sanitario" maxlength="100">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label-prod"><i class="fas fa-capsules mr-1 text-muted"></i>Nombre del Producto <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-pills"></i></span></div>
                                    <input type="text" class="form-control" name="nombre_producto" id="editar_nombre_producto" maxlength="150" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-flask mr-1 text-muted"></i>Laboratorio</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-industry"></i></span></div>
                                        <input type="text" class="form-control" name="laboratorio" id="editar_laboratorio" maxlength="100">
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-box mr-1 text-muted"></i>Presentación</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-box-open"></i></span></div>
                                        <input type="text" class="form-control" name="presentacion" id="editar_presentacion" maxlength="100">
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-tint mr-1 text-muted"></i>Concentración</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-percent"></i></span></div>
                                        <input type="text" class="form-control" name="concentracion" id="editar_concentracion" maxlength="100">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-tags mr-1 text-muted"></i>Categoría <span class="text-danger">*</span></label>
                                    <input type="hidden" name="id_categoria" id="editar_id_categoria" required>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-layer-group"></i></span></div>
                                        <input type="text" class="form-control" id="editar_nombre_categoria" placeholder="Seleccione categoría..." readonly style="background:#fff;cursor:pointer;" onclick="abrirModalSelector('categoria','editar')">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="abrirModalSelector('categoria','editar')" title="Seleccionar categoría">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-ruler mr-1 text-muted"></i>Unidad <span class="text-danger">*</span></label>
                                    <input type="hidden" name="id_unidad" id="editar_id_unidad" required>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-balance-scale"></i></span></div>
                                        <input type="text" class="form-control" id="editar_nombre_unidad" placeholder="Seleccione unidad..." readonly style="background:#fff;cursor:pointer;" onclick="abrirModalSelector('unidad','editar')">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="abrirModalSelector('unidad','editar')" title="Seleccionar unidad">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group col-md-4">
                                    <label class="form-label-prod"><i class="fas fa-truck mr-1 text-muted"></i>Proveedor</label>
                                    <input type="hidden" name="id_proveedor" id="editar_id_proveedor">
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-building"></i></span></div>
                                        <input type="text" class="form-control" id="editar_nombre_proveedor" placeholder="Sin proveedor (opcional)" readonly style="background:#fff;cursor:pointer;" onclick="abrirModalSelector('proveedor','editar')">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="abrirModalSelector('proveedor','editar')" title="Seleccionar proveedor">
                                                <i class="fas fa-search"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="limpiarSelector('proveedor','editar')" title="Quitar proveedor">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-2">
                                    <label class="form-label-prod"><i class="fas fa-exclamation-triangle mr-1 text-warning"></i>Stock Mín.</label>
                                    <input type="number" class="form-control form-control-sm" name="stock_minimo" id="editar_stock_minimo" min="0">
                                </div>
                                <div class="form-group col-md-2">
                                    <label class="form-label-prod"><i class="fas fa-warehouse mr-1 text-muted"></i>Stock Máx.</label>
                                    <input type="number" class="form-control form-control-sm" name="stock_maximo" id="editar_stock_maximo" min="0">
                                </div>
                                <div class="form-group col-md-2">
                                    <label class="form-label-prod">P. Compra</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
                                        <input type="number" step="0.01" class="form-control" name="precio_compra" id="editar_precio_compra" min="0">
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label class="form-label-prod">P. Venta <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
                                        <input type="number" step="0.01" class="form-control" name="precio_venta" id="editar_precio_venta" min="0" required>
                                    </div>
                                </div>
                                <div class="form-group col-md-2">
                                    <label class="form-label-prod"><i class="fas fa-prescription mr-1 text-muted"></i>Receta</label>
                                    <select class="form-control form-control-sm" name="requiere_receta" id="editar_requiere_receta">
                                        <option value="0">No</option>
                                        <option value="1">Sí</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label class="form-label-prod"><i class="fas fa-toggle-on mr-1 text-muted"></i>Estado</label>
                                    <select class="form-control form-control-sm" name="estado" id="editar_estado">
                                        <option value="1">✅ Activo</option>
                                        <option value="0">❌ Inactivo</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label-prod"><i class="fas fa-align-left mr-1 text-muted"></i>Descripción</label>
                                <textarea class="form-control form-control-sm" name="descripcion" id="editar_descripcion" rows="2"></textarea>
                            </div>
                        </div>

                        <!-- Imágenes editar -->
                        <div class="col-md-4">
                            <h6 class="font-weight-bold text-muted mb-2"><i class="fas fa-images mr-1"></i>Imágenes actuales</h6>
                            <div class="preview-container mb-3" id="editar_imagenes_actuales"></div>
                            <h6 class="font-weight-bold text-muted mb-2"><i class="fas fa-plus mr-1"></i>Agregar nuevas</h6>
                            <div class="upload-area" id="editar_upload_area">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Arrastra o haz clic para agregar</p>
                                <small class="text-muted">Máx. 4 imágenes en total</small>
                            </div>
                            <input type="file" name="imagenes[]" id="editar_imagenes" multiple accept="image/*" style="display:none;">
                            <div class="preview-container mt-2" id="editar_preview_nuevas"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
                    <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#a04000,#e67e22);color:#fff;font-weight:600;">
                        <i class="fas fa-save mr-1"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL SELECTOR — CATEGORÍA
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSelectorCategoria" tabindex="-1" aria-hidden="true" style="z-index:1060;">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">

            <div style="background:linear-gradient(135deg,#1a7a4a,#27ae60);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <h6 style="color:#fff;font-weight:700;margin:0;font-size:1rem;">
                        <i class="fas fa-layer-group mr-2"></i>Seleccionar Categoría
                    </h6>
                    <small style="color:rgba(255,255,255,.75);font-size:.75rem;">Haz clic en una categoría para seleccionarla</small>
                </div>
                <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <div class="modal-body p-3">
                <!-- Buscador -->
                <div class="input-group mb-3" style="box-shadow:0 2px 8px rgba(0,0,0,.08);border-radius:8px;overflow:hidden;">
                    <div class="input-group-prepend">
                        <span class="input-group-text" style="background:#f8f9fa;border:none;border-right:1px solid #dee2e6;">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                    </div>
                    <input type="text" id="buscarCategoria" class="form-control"
                           placeholder="Escribe para filtrar categorías..."
                           style="border:none;font-size:.9rem;">
                    <div class="input-group-append">
                        <button type="button" id="btnLimpiarBusqCat" class="btn btn-light" style="border:none;border-left:1px solid #dee2e6;" title="Limpiar">
                            <i class="fas fa-times text-muted" style="font-size:.8rem;"></i>
                        </button>
                    </div>
                </div>

                <!-- Contador resultados -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-muted"><span id="contCategorias"><?= count($categorias) ?></span> categorías disponibles</small>
                    <small class="text-muted">Selecciona una</small>
                </div>

                <!-- Lista -->
                <div style="max-height:380px;overflow-y:auto;padding-right:2px;">
                    <div id="listaCategorias">
                        <?php foreach($categorias as $cat): ?>
                        <div class="item-categoria"
                            data-id="<?= $cat['id_categoria'] ?>"
                            data-nombre="<?= htmlspecialchars($cat['nombre_categoria'], ENT_QUOTES) ?>"
                            data-descripcion="<?= htmlspecialchars($cat['descripcion'] ?? '', ENT_QUOTES) ?>"
                            style="border:1px solid #e9ecef;border-left:4px solid #27ae60;border-radius:8px;padding:12px 14px;margin-bottom:8px;cursor:pointer;transition:all .15s;background:#fff;">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div style="width:36px;height:36px;background:#e8f5e9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-right:12px;">
                                        <i class="fas fa-tag" style="color:#1a7a4a;font-size:.85rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight:700;font-size:.9rem;color:#2d3436;"><?= htmlspecialchars($cat['nombre_categoria']) ?></div>
                                        <?php if (!empty($cat['descripcion'])): ?>
                                        <div style="font-size:.75rem;color:#999;margin-top:2px;">
                                            <i class="fas fa-info-circle mr-1"></i><?= htmlspecialchars($cat['descripcion']) ?>
                                        </div>
                                        <?php else: ?>
                                        <div style="font-size:.75rem;color:#ccc;margin-top:2px;font-style:italic;">Sin descripción</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right text-muted" style="font-size:.75rem;opacity:.5;"></i>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div id="sinResultadosCat" style="display:none;text-align:center;padding:30px;color:#999;">
                            <i class="fas fa-search" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px;"></i>
                            No se encontraron categorías
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:10px 16px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL SELECTOR — UNIDAD
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSelectorUnidad" tabindex="-1" aria-hidden="true" style="z-index:1060;">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">

            <div style="background:linear-gradient(135deg,#1a5276,#117a8b);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <h6 style="color:#fff;font-weight:700;margin:0;font-size:1rem;">
                        <i class="fas fa-balance-scale mr-2"></i>Seleccionar Unidad
                    </h6>
                    <small style="color:rgba(255,255,255,.75);font-size:.75rem;">Haz clic en una unidad para seleccionarla</small>
                </div>
                <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <div class="modal-body p-3">
                <!-- Buscador -->
                <div class="input-group mb-3" style="box-shadow:0 2px 8px rgba(0,0,0,.08);border-radius:8px;overflow:hidden;">
                    <div class="input-group-prepend">
                        <span class="input-group-text" style="background:#f8f9fa;border:none;border-right:1px solid #dee2e6;">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                    </div>
                    <input type="text" id="buscarUnidad" class="form-control"
                           placeholder="Escribe para filtrar unidades..."
                           style="border:none;font-size:.9rem;">
                    <div class="input-group-append">
                        <button type="button" id="btnLimpiarBusqUni" class="btn btn-light" style="border:none;border-left:1px solid #dee2e6;" title="Limpiar">
                            <i class="fas fa-times text-muted" style="font-size:.8rem;"></i>
                        </button>
                    </div>
                </div>

                <!-- Contador resultados -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-muted"><span id="contUnidades"><?= count($unidades) ?></span> unidades disponibles</small>
                    <small class="text-muted">Selecciona una</small>
                </div>

                <!-- Lista -->
                <div style="max-height:380px;overflow-y:auto;padding-right:2px;">
                    <div id="listaUnidades">
                        <?php foreach($unidades as $uni): ?>
                        <div class="item-unidad"
                            data-id="<?= $uni['id_unidad'] ?>"
                            data-nombre="<?= htmlspecialchars($uni['nombre_unidad'], ENT_QUOTES) ?>"
                            style="border:1px solid #e9ecef;border-left:4px solid #117a8b;border-radius:8px;padding:12px 14px;margin-bottom:8px;cursor:pointer;transition:all .15s;background:#fff;">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <div style="width:36px;height:36px;background:#e3f2fd;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-right:12px;">
                                        <i class="fas fa-balance-scale" style="color:#117a8b;font-size:.85rem;"></i>
                                    </div>
                                    <div style="font-weight:700;font-size:.9rem;color:#2d3436;"><?= htmlspecialchars($uni['nombre_unidad']) ?></div>
                                </div>
                                <i class="fas fa-chevron-right text-muted" style="font-size:.75rem;opacity:.5;"></i>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div id="sinResultadosUni" style="display:none;text-align:center;padding:30px;color:#999;">
                            <i class="fas fa-search" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px;"></i>
                            No se encontraron unidades
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:10px 16px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL SELECTOR — PROVEEDOR
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSelectorProveedor" tabindex="-1" aria-hidden="true" style="z-index:1060;">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">

            <div style="background:linear-gradient(135deg,#1a5276,#2980b9);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <h6 style="color:#fff;font-weight:700;margin:0;font-size:1rem;">
                        <i class="fas fa-building mr-2"></i>Seleccionar Proveedor
                    </h6>
                    <small style="color:rgba(255,255,255,.75);font-size:.75rem;">Haz clic en un proveedor para seleccionarlo</small>
                </div>
                <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <div class="modal-body p-3">
                <!-- Buscador -->
                <div class="input-group mb-3" style="box-shadow:0 2px 8px rgba(0,0,0,.08);border-radius:8px;overflow:hidden;">
                    <div class="input-group-prepend">
                        <span class="input-group-text" style="background:#f8f9fa;border:none;border-right:1px solid #dee2e6;">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                    </div>
                    <input type="text" id="buscarProveedor" class="form-control"
                           placeholder="Buscar por nombre, teléfono o dirección..."
                           style="border:none;font-size:.9rem;">
                    <div class="input-group-append">
                        <button type="button" id="btnLimpiarBusqProv" class="btn btn-light" style="border:none;border-left:1px solid #dee2e6;" title="Limpiar">
                            <i class="fas fa-times text-muted" style="font-size:.8rem;"></i>
                        </button>
                    </div>
                </div>

                <!-- Contador resultados -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-muted"><span id="contProveedores"><?= count($proveedores) ?></span> proveedores disponibles</small>
                    <small class="text-muted">Selecciona uno</small>
                </div>

                <!-- Lista -->
                <div style="max-height:380px;overflow-y:auto;padding-right:2px;">
                    <div id="listaProveedores">
                        <?php foreach($proveedores as $prov): ?>
                        <div class="item-proveedor"
                            data-id="<?= $prov['id_proveedor'] ?>"
                            data-nombre="<?= htmlspecialchars($prov['razon_social'], ENT_QUOTES) ?>"
                            data-telefono="<?= htmlspecialchars($prov['telefono'] ?? '', ENT_QUOTES) ?>"
                            data-direccion="<?= htmlspecialchars($prov['direccion'] ?? '', ENT_QUOTES) ?>"
                            style="border:1px solid #e9ecef;border-left:4px solid #2980b9;border-radius:8px;padding:12px 14px;margin-bottom:8px;cursor:pointer;transition:all .15s;background:#fff;">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center" style="flex:1;min-width:0;">
                                    <div style="width:36px;height:36px;background:#e3f2fd;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-right:12px;">
                                        <i class="fas fa-industry" style="color:#1a5276;font-size:.85rem;"></i>
                                    </div>
                                    <div style="min-width:0;">
                                        <div style="font-weight:700;font-size:.9rem;color:#2d3436;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                            <?= htmlspecialchars($prov['razon_social']) ?>
                                        </div>
                                        <div class="d-flex flex-wrap mt-1" style="gap:8px;">
                                            <?php if (!empty($prov['telefono'])): ?>
                                            <span style="font-size:.75rem;color:#555;">
                                                <i class="fas fa-phone-alt mr-1" style="color:#2980b9;"></i><?= htmlspecialchars($prov['telefono']) ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if (!empty($prov['direccion'])): ?>
                                            <span style="font-size:.75rem;color:#555;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;">
                                                <i class="fas fa-map-marker-alt mr-1" style="color:#e67e22;"></i><?= htmlspecialchars($prov['direccion']) ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if (empty($prov['telefono']) && empty($prov['direccion'])): ?>
                                            <span style="font-size:.75rem;color:#ccc;font-style:italic;">Sin datos de contacto</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right text-muted ml-2" style="font-size:.75rem;opacity:.5;flex-shrink:0;"></i>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div id="sinResultadosProv" style="display:none;text-align:center;padding:30px;color:#999;">
                            <i class="fas fa-search" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px;"></i>
                            No se encontraron proveedores
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:10px 16px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cancelar
                </button>
            </div>
        </div>
    </div>
</div>

<?php include $ruta_base . 'includes/footer.php'; ?>
<script src="js/productos.js"></script>


