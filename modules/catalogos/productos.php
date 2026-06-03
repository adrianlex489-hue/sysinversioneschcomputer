<?php
// ============================================================
// modules/catalogos/productos.php | SysInversiones CH Computer
// Imágenes: campo `imagenes` TEXT en tabla productos
// Formato: rutas separadas por coma (máx. 7)
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';
require_once $ruta_base . 'conf/auditoria.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexión BD no disponible.');
if (!defined('ROL_ADMINISTRADOR'))   define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR'))          define('ROL_ASESOR', 2);
if (!defined('ROL_TECNICO'))         define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR, ROL_TECNICO]);
verificarPermiso($pdo, 'productos');

// ── Configuración uploads ─────────────────────────────────────────────────────
define('UPLOAD_DIR', $ruta_base . 'public/uploads/productos/');
define('UPLOAD_URL', '/sysinversioneschcomputer/public/uploads/productos/');
define('MAX_IMGS',   7);
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

    $codigo          = strtoupper(trim($_POST['codigo']          ?? ''));
    $nombre_producto = strtoupper(trim($_POST['nombre_producto'] ?? ''));
    $marca           = trim($_POST['marca']                      ?? '') ?: null;
    $modelo          = trim($_POST['modelo']                     ?? '') ?: null;
    $descripcion     = trim($_POST['descripcion']                ?? '') ?: null;
    $id_categoria    = (int)($_POST['id_categoria']              ?? 0);
    $id_proveedor    = ($_POST['id_proveedor'] ?? '') !== '' ? (int)$_POST['id_proveedor'] : null;
    $stock_minimo    = (int)($_POST['stock_minimo']              ?? 5);
    $stock_maximo    = (int)($_POST['stock_maximo']              ?? 100);
    $precio_compra   = (float)($_POST['precio_compra']           ?? 0);
    $precio_venta    = (float)($_POST['precio_venta']            ?? 0);
    $estado          = (int)($_POST['estado']                    ?? 1);

    try {
        // ── CREAR ─────────────────────────────────────────────────────────────
        if ($accion === 'crear') {
            if (empty($nombre_producto) || !$id_categoria) {
                redirigirProd('warning', 'Campos incompletos', 'Nombre y Categoría son obligatorios.');
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
                        (codigo, nombre_producto, marca, modelo, descripcion,
                         id_categoria, id_proveedor, stock_minimo, stock_maximo,
                         precio_compra, precio_venta, stock, imagenes, estado, fecha_registro)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,0,?,1,NOW())";
            $pdo->prepare($sql)->execute([
                $codigo, $nombre_producto, $marca, $modelo, $descripcion,
                $id_categoria, $id_proveedor, $stock_minimo, $stock_maximo,
                $precio_compra, $precio_venta, $imagenes_csv
            ]);
            $id_nuevo = (int)$pdo->lastInsertId();
            registrarAuditoria($pdo, 'productos', 'crear', 'productos', $id_nuevo,
                "Producto creado: $nombre_producto (Cód: $codigo) — P.Venta: S/. " . number_format($precio_venta, 2));
            redirigirProd('success', '¡Registrado!', "El producto $nombre_producto fue registrado correctamente.");

        // ── ACTUALIZAR ────────────────────────────────────────────────────────
        } elseif ($accion === 'actualizar' && $id_producto > 0) {

            // Obtener datos anteriores para auditoría
            $stAntes = $pdo->prepare("SELECT nombre_producto, precio_compra, precio_venta, stock_minimo, stock_maximo, codigo, imagenes FROM productos WHERE id_producto=?");
            $stAntes->execute([$id_producto]);
            $prod_antes = $stAntes->fetch(PDO::FETCH_ASSOC);

            // Obtener rutas actuales del producto
            $rutas_actuales = parsearImagenes($prod_antes['imagenes'] ?? '');

            // Eliminar imágenes marcadas para borrar
            $eliminar = $_POST['eliminar_imagen'] ?? [];
            foreach ($eliminar as $url) {
                eliminarArchivo($url);
                $rutas_actuales = array_values(array_filter($rutas_actuales, fn($r) => $r !== $url));
            }

            // Respetar el orden enviado por el JS (drag & drop)
            if (!empty($_POST['imagenes_orden'])) {
                $orden_enviado = array_values(array_filter(
                    array_map('trim', $_POST['imagenes_orden']),
                    fn($u) => in_array($u, $rutas_actuales)
                ));
                // Agregar al final cualquier imagen que no esté en el orden (por seguridad)
                foreach ($rutas_actuales as $r) {
                    if (!in_array($r, $orden_enviado)) $orden_enviado[] = $r;
                }
                $rutas_actuales = $orden_enviado;
            }

            // Subir nuevas imágenes (se agregan al final)
            if (!empty($_FILES['imagenes']['name'][0])) {
                $rutas_actuales = subirArchivos($_FILES['imagenes'], $rutas_actuales);
            }

            $imagenes_csv = implode(',', $rutas_actuales) ?: null;

            $sql = "UPDATE productos SET
                        codigo=?, nombre_producto=?, marca=?, modelo=?,
                        descripcion=?, id_categoria=?, id_proveedor=?,
                        stock_minimo=?, stock_maximo=?,
                        precio_compra=?, precio_venta=?,
                        imagenes=?, estado=?
                    WHERE id_producto=?";
            $pdo->prepare($sql)->execute([
                $codigo, $nombre_producto, $marca, $modelo,
                $descripcion, $id_categoria, $id_proveedor,
                $stock_minimo, $stock_maximo,
                $precio_compra, $precio_venta,
                $imagenes_csv, $estado, $id_producto
            ]);

            // Auditoría: registrar campos que cambiaron
            if ($prod_antes) {
                $campos_audit = [
                    'precio_venta'  => ['antes' => $prod_antes['precio_venta'],  'nuevo' => $precio_venta],
                    'precio_compra' => ['antes' => $prod_antes['precio_compra'], 'nuevo' => $precio_compra],
                    'stock_minimo'  => ['antes' => $prod_antes['stock_minimo'],  'nuevo' => $stock_minimo],
                    'stock_maximo'  => ['antes' => $prod_antes['stock_maximo'],  'nuevo' => $stock_maximo],
                    'nombre'        => ['antes' => $prod_antes['nombre_producto'],'nuevo' => $nombre_producto],
                ];
                foreach ($campos_audit as $campo => $vals) {
                    if ((string)$vals['antes'] !== (string)$vals['nuevo']) {
                        registrarAuditoria($pdo, 'productos', 'editar', 'productos', $id_producto,
                            "Producto '{$nombre_producto}' — cambio en $campo",
                            $campo, $vals['antes'], $vals['nuevo']);
                    }
                }
            }
            redirigirProd('success', '¡Actualizado!', "El producto fue actualizado correctamente.");

        // ── DESACTIVAR ────────────────────────────────────────────────────────
        } elseif ($accion === 'desactivar' && $id_producto > 0) {
            $stNom = $pdo->prepare("SELECT nombre_producto FROM productos WHERE id_producto=?");
            $stNom->execute([$id_producto]);
            $nom = $stNom->fetchColumn();
            $pdo->prepare("UPDATE productos SET estado=0 WHERE id_producto=?")->execute([$id_producto]);
            registrarAuditoria($pdo, 'productos', 'eliminar', 'productos', $id_producto,
                "Producto desactivado: $nom", 'estado', '1', '0');
            redirigirProd('info', 'Desactivado', "El producto fue desactivado y movido a inactivos.");

        // ── REACTIVAR ─────────────────────────────────────────────────────────
        } elseif ($accion === 'reactivar' && $id_producto > 0) {
            $stNom = $pdo->prepare("SELECT nombre_producto FROM productos WHERE id_producto=?");
            $stNom->execute([$id_producto]);
            $nom = $stNom->fetchColumn();
            $pdo->prepare("UPDATE productos SET estado=1 WHERE id_producto=?")->execute([$id_producto]);
            registrarAuditoria($pdo, 'productos', 'crear', 'productos', $id_producto,
                "Producto reactivado: $nom", 'estado', '0', '1');
            redirigirProd('success', '¡Reactivado!', "El producto fue reactivado correctamente.");

        // ── ELIMINAR PERMANENTE ───────────────────────────────────────────────
        } elseif ($accion === 'eliminar_permanente' && $id_producto > 0) {
            $stmt = $pdo->prepare("SELECT nombre_producto, imagenes FROM productos WHERE id_producto=?");
            $stmt->execute([$id_producto]);
            $prod_del = $stmt->fetch(PDO::FETCH_ASSOC);
            $rutas = parsearImagenes($prod_del['imagenes'] ?? '');
            foreach ($rutas as $url) eliminarArchivo($url);

            $pdo->prepare("DELETE FROM productos WHERE id_producto=?")->execute([$id_producto]);
            registrarAuditoria($pdo, 'productos', 'eliminar', 'productos', $id_producto,
                "Producto eliminado permanentemente: " . ($prod_del['nombre_producto'] ?? ''));
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
$categorias = $proveedores = [];

try {
    $sql_base = "SELECT p.*, c.nombre_categoria,
                        pr.razon_social AS nombre_proveedor
                 FROM productos p
                 LEFT JOIN categorias  c  ON p.id_categoria = c.id_categoria
                 LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor";

    $productos_activos   = $pdo->query($sql_base . "
        WHERE p.estado=1
        ORDER BY
            CASE
                WHEN p.stock <= 0                          THEN 3
                WHEN p.stock_minimo IS NOT NULL
                     AND p.stock <= p.stock_minimo         THEN 2
                ELSE                                            1
            END ASC,
            p.stock DESC,
            p.nombre_producto ASC
    ")->fetchAll();
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
<link rel="stylesheet" href="css/productos.css?v=<?= time() ?>">

<div class="content-wrapper">

    <!-- ── CABECERA ── -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-prod d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4><i class="fas fa-box-open mr-2"></i>Gestión de Productos</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones CH Computer &rsaquo; Catálogos &rsaquo; Productos</small>
                </div>
                <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
                    <!-- Botones de exportación -->
                    <div class="prod-export-group">
                        <button id="btn-exportar-csv-prod" class="prod-export-btn prod-export-csv" title="Exportar a CSV">
                            <i class="fas fa-file-csv"></i>
                            <span>CSV</span>
                        </button>
                        <button id="btn-exportar-excel-prod" class="prod-export-btn prod-export-excel" title="Exportar a Excel">
                            <i class="fas fa-file-excel"></i>
                            <span>Excel</span>
                        </button>
                        <button id="btn-exportar-pdf-prod" class="prod-export-btn prod-export-pdf" title="Exportar a PDF">
                            <i class="fas fa-file-pdf"></i>
                            <span>PDF</span>
                        </button>
                    </div>
                    <button class="btn btn-light font-weight-bold" data-toggle="modal" data-target="#modalCrearProducto">
                        <i class="fas fa-plus-circle mr-1"></i> Nuevo Producto
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- ── STATS ── -->
            <div class="row mb-4">
                <div class="col-md-3 col-6 mb-2">
                    <div class="stat-mini" style="background:linear-gradient(135deg,#1a5276,#2980b9);">
                        <i class="fas fa-boxes"></i>
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
                        <!-- Búsqueda rápida + botón filtros -->
                        <div class="input-group input-group-sm" style="width:220px;">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            </div>
                            <input type="text" id="buscarGrid" class="form-control" placeholder="Buscar...">
                            <div class="input-group-append">
                                <button class="btn btn-warning btn-sm" id="btnAbrirFiltros" title="Filtros avanzados"
                                    style="border-radius:0 6px 6px 0;font-size:.78rem;font-weight:700;padding:0 10px;">
                                    <i class="fas fa-sliders-h mr-1"></i>Filtros
                                    <span id="filtros-activos-badge" class="badge badge-danger ml-1" style="display:none;font-size:.65rem;">0</span>
                                </button>
                            </div>
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
                    <!-- Indicador de filtros activos -->
                    <div id="filtros-activos-bar" style="background:#eaf4fb;border-radius:8px;padding:8px 14px;margin-bottom:12px;align-items:center;justify-content:space-between;font-size:.82rem;border:1px solid #aed6f1;display:none;">
                        <span><i class="fas fa-filter mr-1 text-primary"></i> Filtros activos — <strong id="filtros-resultado"></strong></span>
                        <button id="btnQuitarFiltros" type="button" style="background:none;border:none;color:#e74c3c;font-size:.78rem;font-weight:700;cursor:pointer;padding:0;">
                            <i class="fas fa-times mr-1"></i>Quitar filtros
                        </button>
                    </div>
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
                                         data-search="<?= htmlspecialchars($p['nombre_producto'].' '.($p['marca']??'').' '.($p['nombre_categoria']??''), ENT_QUOTES) ?>"
                                         data-categoria-id="<?= $p['id_categoria'] ?>"
                                         data-stock="<?= $p['stock'] ?>"
                                         data-stock-min="<?= $p['stock_minimo'] ?>"
                                         data-precio="<?= $p['precio_venta'] ?>">
                                        <div class="card producto-card" data-imgs="<?= $imgs_attr ?>">
                                            <!-- Contenedor imagen con botones flotantes -->
                                            <div class="card-img-wrap">
                                                <?php if ($img_src): ?>
                                                    <img src="<?= htmlspecialchars($img_src) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['nombre_producto']) ?>">
                                                <?php else: ?>
                                                    <div class="img-placeholder"><i class="fas fa-laptop"></i></div>
                                                <?php endif; ?>
                                                <!-- Botones flotantes sobre la imagen -->
                                                <div class="card-img-actions">
                                                    <?php if (!empty($p['imgs'])): ?>
                                                    <button class="card-img-btn btn-grid-zoom" title="Ver imágenes en grande">
                                                        <i class="fas fa-search-plus"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <span class="badge-categoria"><?= htmlspecialchars($p['nombre_categoria'] ?? '—') ?></span>
                                                </div>
                                                <h6 class="font-weight-bold mb-1" style="font-size:.88rem;line-height:1.3;" title="<?= htmlspecialchars($p['nombre_producto']) ?>">
                                                    <?= htmlspecialchars(mb_strimwidth($p['nombre_producto'], 0, 45, '...')) ?>
                                                </h6>
                                                <div class="text-muted" style="font-size:.75rem;">
                                                    <?= htmlspecialchars(trim(($p['marca']??'').' '.($p['modelo']??'')) ?: '—') ?>
                                                </div>
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
                                                        data-marca="<?= htmlspecialchars($p['marca']??'',ENT_QUOTES) ?>"
                                                        data-modelo="<?= htmlspecialchars($p['modelo']??'',ENT_QUOTES) ?>"
                                                        data-categoria="<?= htmlspecialchars($p['nombre_categoria']??'',ENT_QUOTES) ?>"
                                                        data-proveedor="<?= htmlspecialchars($p['nombre_proveedor']??'',ENT_QUOTES) ?>"
                                                        data-descripcion="<?= htmlspecialchars($p['descripcion']??'',ENT_QUOTES) ?>"
                                                        data-precio-venta="<?= $p['precio_venta'] ?>"
                                                        data-precio-compra="<?= $p['precio_compra'] ?>"
                                                        data-stock="<?= $p['stock'] ?>"
                                                        data-stock-min="<?= $p['stock_minimo'] ?>"
                                                        data-stock-max="<?= $p['stock_maximo'] ?>"
                                                        data-estado="<?= $p['estado'] ?>"
                                                        data-imagenes="<?= $imgs_attr ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-warning btn-editar-producto" title="Editar"
                                                        data-id="<?= $p['id_producto'] ?>"
                                                        data-codigo="<?= htmlspecialchars($p['codigo'],ENT_QUOTES) ?>"
                                                        data-nombre="<?= htmlspecialchars($p['nombre_producto'],ENT_QUOTES) ?>"
                                                        data-marca="<?= htmlspecialchars($p['marca']??'',ENT_QUOTES) ?>"
                                                        data-modelo="<?= htmlspecialchars($p['modelo']??'',ENT_QUOTES) ?>"
                                                        data-descripcion="<?= htmlspecialchars($p['descripcion']??'',ENT_QUOTES) ?>"
                                                        data-id-categoria="<?= $p['id_categoria'] ?>"
                                                        data-categoria="<?= htmlspecialchars($p['nombre_categoria']??'',ENT_QUOTES) ?>"
                                                        data-id-proveedor="<?= $p['id_proveedor']??'' ?>"
                                                        data-proveedor="<?= htmlspecialchars($p['nombre_proveedor']??'',ENT_QUOTES) ?>"
                                                        data-stock-min="<?= $p['stock_minimo'] ?>"
                                                        data-stock-max="<?= $p['stock_maximo'] ?>"
                                                        data-precio-compra="<?= $p['precio_compra'] ?>"
                                                        data-precio-venta="<?= $p['precio_venta'] ?>"
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
                                                <th style="width:58px;">Img</th>
                                                <th style="width:130px;">Código</th>
                                                <th>Nombre</th>
                                                <th style="width:110px;">Categoría</th>
                                                <th style="width:140px;">Marca / Modelo</th>
                                                <th style="width:110px;">Proveedor</th>
                                                <th class="text-right" style="width:90px;">P.Venta</th>
                                                <th class="text-right" style="width:90px;">P.Costo</th>
                                                <th class="text-center" style="width:80px;">Stock</th>
                                                <th class="text-center" style="width:110px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($productos_activos as $p):
                                            $imgs_attr  = htmlspecialchars(json_encode($p['imgs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                                            $stock      = (int)$p['stock'];
                                            $smin       = (int)$p['stock_minimo'];
                                            $row_class  = $stock <= 0 ? 'tr-agotado' : ($stock <= $smin ? 'tr-bajo' : '');
                                            $tiene_imgs = !empty($p['imgs']);
                                        ?>
                                            <tr class="<?= $row_class ?>">
                                                <!-- Imagen con zoom -->
                                                <td class="text-center td-img-lista">
                                                    <?php if ($p['img_principal']): ?>
                                                        <div class="lista-img-wrap <?= $tiene_imgs ? 'has-imgs' : '' ?>"
                                                             <?= $tiene_imgs ? 'data-imgs="'.$imgs_attr.'"' : '' ?>>
                                                            <img src="<?= htmlspecialchars($p['img_principal']) ?>"
                                                                 class="lista-thumb" alt="<?= htmlspecialchars($p['nombre_producto']) ?>">
                                                            <?php if ($tiene_imgs): ?>
                                                            <div class="lista-zoom-overlay">
                                                                <i class="fas fa-search-plus"></i>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="lista-img-placeholder">
                                                            <i class="fas fa-laptop"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <!-- Código con copia -->
                                                <td>
                                                    <span class="codigo-copiable" data-codigo="<?= htmlspecialchars($p['codigo'],ENT_QUOTES) ?>"
                                                          title="Clic para copiar">
                                                        <code><?= htmlspecialchars($p['codigo']) ?></code>
                                                        <i class="fas fa-copy codigo-copy-icon"></i>
                                                    </span>
                                                </td>
                                                <td class="font-weight-bold td-nombre-lista">
                                                    <?= htmlspecialchars($p['nombre_producto']) ?>
                                                </td>
                                                <td>
                                                    <span class="badge-categoria"><?= htmlspecialchars($p['nombre_categoria']??'—') ?></span>
                                                </td>
                                                <td class="text-muted" style="font-size:.82rem;">
                                                    <?= htmlspecialchars(trim(($p['marca']??'').' '.($p['modelo']??'')) ?: '—') ?>
                                                </td>
                                                <td class="text-muted" style="font-size:.82rem;">
                                                    <?= htmlspecialchars($p['nombre_proveedor'] ?? '—') ?>
                                                </td>
                                                <td class="text-right font-weight-bold" style="color:var(--prod-verde);">
                                                    S/. <?= number_format($p['precio_venta'],2) ?>
                                                </td>
                                                <td class="text-right" style="font-size:.82rem;color:#888;">
                                                    S/. <?= number_format($p['precio_compra'],2) ?>
                                                </td>
                                                <td class="text-center"><?= badgeStock($stock, $smin) ?></td>
                                                <td class="text-center" style="white-space:nowrap;">
                                                    <button class="btn btn-sm btn-info btn-ver-producto" title="Ver detalle"
                                                        data-id="<?= $p['id_producto'] ?>"
                                                        data-nombre="<?= htmlspecialchars($p['nombre_producto'],ENT_QUOTES) ?>"
                                                        data-codigo="<?= htmlspecialchars($p['codigo'],ENT_QUOTES) ?>"
                                                        data-marca="<?= htmlspecialchars($p['marca']??'',ENT_QUOTES) ?>"
                                                        data-modelo="<?= htmlspecialchars($p['modelo']??'',ENT_QUOTES) ?>"
                                                        data-categoria="<?= htmlspecialchars($p['nombre_categoria']??'',ENT_QUOTES) ?>"
                                                        data-proveedor="<?= htmlspecialchars($p['nombre_proveedor']??'',ENT_QUOTES) ?>"
                                                        data-descripcion="<?= htmlspecialchars($p['descripcion']??'',ENT_QUOTES) ?>"
                                                        data-precio-venta="<?= $p['precio_venta'] ?>"
                                                        data-precio-compra="<?= $p['precio_compra'] ?>"
                                                        data-stock="<?= $p['stock'] ?>"
                                                        data-stock-min="<?= $p['stock_minimo'] ?>"
                                                        data-stock-max="<?= $p['stock_maximo'] ?>"
                                                        data-estado="<?= $p['estado'] ?>"
                                                        data-imagenes="<?= $imgs_attr ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning btn-editar-producto" title="Editar"
                                                        data-id="<?= $p['id_producto'] ?>"
                                                        data-codigo="<?= htmlspecialchars($p['codigo'],ENT_QUOTES) ?>"
                                                        data-nombre="<?= htmlspecialchars($p['nombre_producto'],ENT_QUOTES) ?>"
                                                        data-marca="<?= htmlspecialchars($p['marca']??'',ENT_QUOTES) ?>"
                                                        data-modelo="<?= htmlspecialchars($p['modelo']??'',ENT_QUOTES) ?>"
                                                        data-descripcion="<?= htmlspecialchars($p['descripcion']??'',ENT_QUOTES) ?>"
                                                        data-id-categoria="<?= $p['id_categoria'] ?>"
                                                        data-categoria="<?= htmlspecialchars($p['nombre_categoria']??'',ENT_QUOTES) ?>"
                                                        data-id-proveedor="<?= $p['id_proveedor']??'' ?>"
                                                        data-proveedor="<?= htmlspecialchars($p['nombre_proveedor']??'',ENT_QUOTES) ?>"
                                                        data-stock-min="<?= $p['stock_minimo'] ?>"
                                                        data-stock-max="<?= $p['stock_maximo'] ?>"
                                                        data-precio-compra="<?= $p['precio_compra'] ?>"
                                                        data-precio-venta="<?= $p['precio_venta'] ?>"
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

                            <!-- VISTA GRID INACTIVOS -->
                            <div id="vistaGridInactivos">
                                <div class="row">
                                <?php foreach ($productos_inactivos as $p):
                                    $img_src_i = $p['img_principal'] ?? null;
                                    $stock_i   = (int)$p['stock'];
                                    $smin_i    = (int)$p['stock_minimo'];
                                    $imgs_attr_i = htmlspecialchars(json_encode($p['imgs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <div class="col-xl-3 col-lg-4 col-md-6 mb-4 producto-grid-item-inactivo"
                                         data-search="<?= htmlspecialchars($p['nombre_producto'].' '.($p['marca']??'').' '.($p['nombre_categoria']??''), ENT_QUOTES) ?>">
                                        <div class="card producto-card producto-card-inactivo" data-imgs="<?= $imgs_attr_i ?>">
                                            <!-- Imagen con botones flotantes -->
                                            <div class="card-img-wrap">
                                                <?php if ($img_src_i): ?>
                                                    <img src="<?= htmlspecialchars($img_src_i) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['nombre_producto']) ?>">
                                                <?php else: ?>
                                                    <div class="img-placeholder"><i class="fas fa-laptop"></i></div>
                                                <?php endif; ?>
                                                <!-- Badge inactivo sobre imagen -->
                                                <div class="badge-inactivo-overlay">
                                                    <i class="fas fa-ban mr-1"></i>Inactivo
                                                </div>
                                                <!-- Botones flotantes -->
                                                <div class="card-img-actions">
                                                    <?php if (!empty($p['imgs'])): ?>
                                                    <button class="card-img-btn btn-grid-zoom" title="Ver imágenes en grande">
                                                        <i class="fas fa-search-plus"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <span class="badge-categoria" style="opacity:.7;"><?= htmlspecialchars($p['nombre_categoria'] ?? '—') ?></span>
                                                </div>
                                                <h6 class="font-weight-bold mb-1" style="font-size:.88rem;line-height:1.3;color:#888;" title="<?= htmlspecialchars($p['nombre_producto']) ?>">
                                                    <?= htmlspecialchars(mb_strimwidth($p['nombre_producto'], 0, 45, '...')) ?>
                                                </h6>
                                                <div class="text-muted" style="font-size:.75rem;">
                                                    <?= htmlspecialchars(trim(($p['marca']??'').' '.($p['modelo']??'')) ?: '—') ?>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <div>
                                                        <div class="precio-venta" style="color:#888;">S/. <?= number_format($p['precio_venta'],2) ?></div>
                                                        <div class="precio-compra">Costo: S/. <?= number_format($p['precio_compra'],2) ?></div>
                                                    </div>
                                                    <?= badgeStock($stock_i, $smin_i) ?>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-light p-2">
                                                <div class="btn-group btn-group-sm w-100">
                                                    <button class="btn btn-info btn-ver-producto" title="Ver detalle"
                                                        data-id="<?= $p['id_producto'] ?>"
                                                        data-nombre="<?= htmlspecialchars($p['nombre_producto'],ENT_QUOTES) ?>"
                                                        data-codigo="<?= htmlspecialchars($p['codigo'],ENT_QUOTES) ?>"
                                                        data-marca="<?= htmlspecialchars($p['marca']??'',ENT_QUOTES) ?>"
                                                        data-modelo="<?= htmlspecialchars($p['modelo']??'',ENT_QUOTES) ?>"
                                                        data-categoria="<?= htmlspecialchars($p['nombre_categoria']??'',ENT_QUOTES) ?>"
                                                        data-proveedor="<?= htmlspecialchars($p['nombre_proveedor']??'',ENT_QUOTES) ?>"
                                                        data-descripcion="<?= htmlspecialchars($p['descripcion']??'',ENT_QUOTES) ?>"
                                                        data-precio-venta="<?= $p['precio_venta'] ?>"
                                                        data-precio-compra="<?= $p['precio_compra'] ?>"
                                                        data-stock="<?= $p['stock'] ?>"
                                                        data-stock-min="<?= $p['stock_minimo'] ?>"
                                                        data-stock-max="<?= $p['stock_maximo'] ?>"
                                                        data-estado="<?= $p['estado'] ?>"
                                                        data-imagenes="<?= $imgs_attr_i ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-success btn-reactivar-producto" title="Reactivar"
                                                        data-id="<?= $p['id_producto'] ?>"
                                                        data-nombre="<?= htmlspecialchars($p['nombre_producto'],ENT_QUOTES) ?>">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                    <button class="btn btn-danger btn-eliminar-producto" title="Eliminar permanente"
                                                        data-id="<?= $p['id_producto'] ?>"
                                                        data-nombre="<?= htmlspecialchars($p['nombre_producto'],ENT_QUOTES) ?>">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($productos_inactivos)): ?>
                                    <div class="col-12 text-center py-5 text-muted">
                                        <i class="fas fa-check-circle" style="font-size:3rem;opacity:.2;display:block;margin-bottom:12px;"></i>
                                        No hay productos inactivos
                                    </div>
                                <?php endif; ?>
                                </div>
                            </div>

                            <!-- VISTA LISTA INACTIVOS -->
                            <div id="vistaListaInactivos" style="display:none;">
                                <div class="table-responsive">
                                    <table id="tablaProductosInactivos" class="table table-productos table-bordered table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th style="width:58px;">Img</th>
                                                <th style="width:130px;">Código</th>
                                                <th>Nombre</th>
                                                <th style="width:110px;">Categoría</th>
                                                <th style="width:140px;">Marca / Modelo</th>
                                                <th style="width:110px;">Proveedor</th>
                                                <th class="text-right" style="width:90px;">P.Venta</th>
                                                <th class="text-right" style="width:90px;">P.Costo</th>
                                                <th class="text-center" style="width:80px;">Stock</th>
                                                <th class="text-center" style="width:120px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($productos_inactivos as $p):
                                            $imgs_attr_i  = htmlspecialchars(json_encode($p['imgs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                                            $tiene_imgs_i = !empty($p['imgs']);
                                        ?>
                                            <tr class="tr-inactivo">
                                                <td class="text-center td-img-lista">
                                                    <?php if ($p['img_principal']): ?>
                                                        <div class="lista-img-wrap <?= $tiene_imgs_i ? 'has-imgs' : '' ?>"
                                                             <?= $tiene_imgs_i ? 'data-imgs="'.$imgs_attr_i.'"' : '' ?>>
                                                            <img src="<?= htmlspecialchars($p['img_principal']) ?>" class="lista-thumb" alt="">
                                                            <?php if ($tiene_imgs_i): ?>
                                                            <div class="lista-zoom-overlay"><i class="fas fa-search-plus"></i></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="lista-img-placeholder"><i class="fas fa-laptop"></i></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="codigo-copiable" data-codigo="<?= htmlspecialchars($p['codigo'],ENT_QUOTES) ?>" title="Clic para copiar">
                                                        <code><?= htmlspecialchars($p['codigo']) ?></code>
                                                        <i class="fas fa-copy codigo-copy-icon"></i>
                                                    </span>
                                                </td>
                                                <td class="font-weight-bold td-nombre-lista"><?= htmlspecialchars($p['nombre_producto']) ?></td>
                                                <td><span class="badge-categoria"><?= htmlspecialchars($p['nombre_categoria']??'—') ?></span></td>
                                                <td class="text-muted" style="font-size:.82rem;"><?= htmlspecialchars(trim(($p['marca']??'').' '.($p['modelo']??'')) ?: '—') ?></td>
                                                <td class="text-muted" style="font-size:.82rem;"><?= htmlspecialchars($p['nombre_proveedor'] ?? '—') ?></td>
                                                <td class="text-right" style="font-size:.88rem;color:#888;">S/. <?= number_format($p['precio_venta'],2) ?></td>
                                                <td class="text-right" style="font-size:.82rem;color:#aaa;">S/. <?= number_format($p['precio_compra'],2) ?></td>
                                                <td class="text-center"><?= badgeStock((int)$p['stock'],(int)$p['stock_minimo']) ?></td>
                                                <td class="text-center" style="white-space:nowrap;">
                                                    <button class="btn btn-sm btn-info btn-ver-producto" title="Ver detalle"
                                                        data-id="<?= $p['id_producto'] ?>"
                                                        data-nombre="<?= htmlspecialchars($p['nombre_producto'],ENT_QUOTES) ?>"
                                                        data-codigo="<?= htmlspecialchars($p['codigo'],ENT_QUOTES) ?>"
                                                        data-marca="<?= htmlspecialchars($p['marca']??'',ENT_QUOTES) ?>"
                                                        data-modelo="<?= htmlspecialchars($p['modelo']??'',ENT_QUOTES) ?>"
                                                        data-categoria="<?= htmlspecialchars($p['nombre_categoria']??'',ENT_QUOTES) ?>"
                                                        data-proveedor="<?= htmlspecialchars($p['nombre_proveedor']??'',ENT_QUOTES) ?>"
                                                        data-descripcion="<?= htmlspecialchars($p['descripcion']??'',ENT_QUOTES) ?>"
                                                        data-precio-venta="<?= $p['precio_venta'] ?>"
                                                        data-precio-compra="<?= $p['precio_compra'] ?>"
                                                        data-stock="<?= $p['stock'] ?>"
                                                        data-stock-min="<?= $p['stock_minimo'] ?>"
                                                        data-stock-max="<?= $p['stock_maximo'] ?>"
                                                        data-estado="<?= $p['estado'] ?>"
                                                        data-imagenes="<?= $imgs_attr_i ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
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
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL VER PRODUCTO
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalVerProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;">

            <!-- Cabecera modal Ver Producto -->
            <div style="background:linear-gradient(135deg,#1a5276,#2980b9);padding:20px 24px;position:relative;">
                <button type="button" class="close" data-dismiss="modal"
                    style="position:absolute;top:12px;right:16px;color:#fff;opacity:.8;font-size:1.4rem;">&times;</button>
                <div class="d-flex align-items-center gap-3">
                    <div style="width:56px;height:56px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-laptop" style="font-size:1.6rem;color:#fff;"></i>
                    </div>
                    <div>
                        <h5 id="ver_nombre_producto" style="color:#fff;font-weight:700;margin:0;font-size:1.05rem;"></h5>
                        <div class="mt-1 d-flex gap-2 flex-wrap">
                            <span style="background:rgba(255,255,255,.2);color:#fff;font-size:.75rem;padding:2px 10px;border-radius:20px;font-family:monospace;">
                                <i class="fas fa-barcode mr-1"></i><span id="ver_codigo_badge"></span>
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
                                    <div class="info-icon" style="background:#e8f5e9;"><i class="fas fa-trademark" style="color:#1a7a4a;font-size:.8rem;"></i></div>
                                    <div><div class="info-label">Marca</div><div id="ver_marca" class="info-value"></div></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-row">
                                    <div class="info-icon" style="background:#e3f2fd;"><i class="fas fa-box" style="color:#1a5276;font-size:.8rem;"></i></div>
                                    <div><div class="info-label">Modelo</div><div id="ver_modelo" class="info-value"></div></div>
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
                                <div class="form-group col-md-6">
                                    <label class="form-label-prod"><i class="fas fa-barcode mr-1 text-muted"></i>Código</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-tag"></i></span></div>
                                        <input type="text" class="form-control" name="codigo" placeholder="PRD-001" maxlength="50">
                                    </div>
                                    <small class="text-muted">Vacío = autogenerado</small>
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="form-label-prod"><i class="fas fa-toggle-on mr-1 text-muted"></i>Estado</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-circle"></i></span></div>
                                        <select class="form-control" name="estado">
                                            <option value="1">✅ Activo</option>
                                            <option value="0">❌ Inactivo</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label-prod"><i class="fas fa-laptop mr-1 text-muted"></i>Nombre del Producto <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-box"></i></span></div>
                                    <input type="text" class="form-control" name="nombre_producto" required maxlength="150" placeholder="Ej: Laptop HP 15.6 Intel Core i5">
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label class="form-label-prod"><i class="fas fa-trademark mr-1 text-muted"></i>Marca</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-tag"></i></span></div>
                                        <input type="text" class="form-control" name="marca" maxlength="50" placeholder="Ej: HP, Lenovo, Asus">
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="form-label-prod"><i class="fas fa-cube mr-1 text-muted"></i>Modelo</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-barcode"></i></span></div>
                                        <input type="text" class="form-control" name="modelo" maxlength="100" placeholder="Ej: 15-fd0350la">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label class="form-label-prod"><i class="fas fa-tags mr-1 text-muted"></i>Categoría <span class="text-danger">*</span></label>
                                    <input type="hidden" name="id_categoria" id="crear_id_categoria" required>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-layer-group"></i></span></div>
                                        <input type="text" class="form-control" id="crear_nombre_categoria" placeholder="Seleccione categoría..." readonly style="background:#fff;cursor:pointer;" onclick="abrirModalSelector('categoria','crear')">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="abrirModalSelector('categoria','crear')"><i class="fas fa-search"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="form-label-prod"><i class="fas fa-truck mr-1 text-muted"></i>Proveedor</label>
                                    <input type="hidden" name="id_proveedor" id="crear_id_proveedor">
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-building"></i></span></div>
                                        <input type="text" class="form-control" id="crear_nombre_proveedor" placeholder="Sin proveedor (opcional)" readonly style="background:#fff;cursor:pointer;" onclick="abrirModalSelector('proveedor','crear')">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="abrirModalSelector('proveedor','crear')"><i class="fas fa-search"></i></button>
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="limpiarSelector('proveedor','crear')"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-3">
                                    <label class="form-label-prod">Stock Mínimo</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-exclamation-triangle text-warning"></i></span></div>
                                        <input type="number" class="form-control" name="stock_minimo" value="5" min="0">
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="form-label-prod">Stock Máximo</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-warehouse"></i></span></div>
                                        <input type="number" class="form-control" name="stock_maximo" value="100" min="0">
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="form-label-prod">Precio Compra</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
                                        <input type="number" step="0.01" class="form-control" name="precio_compra" value="0.00" min="0">
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="form-label-prod">Precio Venta <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
                                        <input type="number" step="0.01" class="form-control" name="precio_venta" value="0.00" min="0" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label-prod"><i class="fas fa-align-left mr-1 text-muted"></i>Descripción</label>
                                <textarea class="form-control form-control-sm" name="descripcion" rows="2" placeholder="Descripción breve del producto..."></textarea>
                            </div>
                        </div>

                        <!-- Columna derecha: Imágenes -->
                        <div class="col-md-4">
                            <h6 class="font-weight-bold text-muted mb-3"><i class="fas fa-images mr-1"></i>Imágenes del Producto <small class="text-muted">(máx. 7)</small></h6>
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
                        <!-- Columna izquierda -->
                        <div class="col-md-8">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label class="form-label-prod"><i class="fas fa-tag mr-1 text-muted"></i>Código <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-tag"></i></span></div>
                                        <input type="text" class="form-control" name="codigo" id="editar_codigo" maxlength="50" required>
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="form-label-prod"><i class="fas fa-toggle-on mr-1 text-muted"></i>Estado</label>
                                    <select class="form-control form-control-sm" name="estado" id="editar_estado">
                                        <option value="1">✅ Activo</option>
                                        <option value="0">❌ Inactivo</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label-prod"><i class="fas fa-laptop mr-1 text-muted"></i>Nombre del Producto <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-laptop"></i></span></div>
                                    <input type="text" class="form-control" name="nombre_producto" id="editar_nombre_producto" maxlength="150" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label class="form-label-prod"><i class="fas fa-trademark mr-1 text-muted"></i>Marca</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-trademark"></i></span></div>
                                        <input type="text" class="form-control" name="marca" id="editar_marca" maxlength="50" placeholder="Ej: HP, Lenovo, Redragon">
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="form-label-prod"><i class="fas fa-cube mr-1 text-muted"></i>Modelo</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-cube"></i></span></div>
                                        <input type="text" class="form-control" name="modelo" id="editar_modelo" maxlength="100" placeholder="Ej: 15-fd0350la">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label class="form-label-prod"><i class="fas fa-tags mr-1 text-muted"></i>Categoría <span class="text-danger">*</span></label>
                                    <input type="hidden" name="id_categoria" id="editar_id_categoria" required>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-layer-group"></i></span></div>
                                        <input type="text" class="form-control" id="editar_nombre_categoria" placeholder="Seleccione categoría..." readonly style="background:#fff;cursor:pointer;" onclick="abrirModalSelector('categoria','editar')">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="abrirModalSelector('categoria','editar')"><i class="fas fa-search"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group col-md-6">
                                    <label class="form-label-prod"><i class="fas fa-truck mr-1 text-muted"></i>Proveedor</label>
                                    <input type="hidden" name="id_proveedor" id="editar_id_proveedor">
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-building"></i></span></div>
                                        <input type="text" class="form-control" id="editar_nombre_proveedor" placeholder="Sin proveedor (opcional)" readonly style="background:#fff;cursor:pointer;" onclick="abrirModalSelector('proveedor','editar')">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="abrirModalSelector('proveedor','editar')"><i class="fas fa-search"></i></button>
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="limpiarSelector('proveedor','editar')"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-3">
                                    <label class="form-label-prod"><i class="fas fa-exclamation-triangle mr-1 text-warning"></i>Stock Mín.</label>
                                    <input type="number" class="form-control form-control-sm" name="stock_minimo" id="editar_stock_minimo" min="0">
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="form-label-prod"><i class="fas fa-warehouse mr-1 text-muted"></i>Stock Máx.</label>
                                    <input type="number" class="form-control form-control-sm" name="stock_maximo" id="editar_stock_maximo" min="0">
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="form-label-prod">P. Compra</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
                                        <input type="number" step="0.01" class="form-control" name="precio_compra" id="editar_precio_compra" min="0">
                                    </div>
                                </div>
                                <div class="form-group col-md-3">
                                    <label class="form-label-prod">P. Venta <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
                                        <input type="number" step="0.01" class="form-control" name="precio_venta" id="editar_precio_venta" min="0" required>
                                    </div>
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
                                <small class="text-muted">Máx. 7 imágenes en total</small>
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
<script src="js/productos.js?v=<?= time() ?>"></script>

<!-- ══════════════════════════════════════════════════════════
     MODAL FILTROS AVANZADOS
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalFiltrosProductos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" style="max-width:480px;">
        <div class="modal-content" style="border:none;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.2);">

            <!-- Header -->
            <div style="background:linear-gradient(135deg,#1a3a5c,#1a5276,#2471a3);padding:20px 22px;position:relative;overflow:hidden;">
                <div style="position:absolute;top:-25px;right:-25px;width:110px;height:110px;border-radius:50%;background:rgba(255,255,255,.07);pointer-events:none;"></div>
                <button type="button" data-dismiss="modal"
                    style="position:absolute;top:12px;right:14px;background:rgba(255,255,255,.12);border:none;color:#fff;width:28px;height:28px;border-radius:50%;font-size:.8rem;display:flex;align-items:center;justify-content:center;cursor:pointer;"
                    onmouseover="this.style.background='rgba(231,76,60,.75)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">
                    <i class="fas fa-times"></i>
                </button>
                <div style="display:flex;align-items:center;gap:12px;position:relative;z-index:2;">
                    <div style="width:44px;height:44px;background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.25);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-sliders-h" style="font-size:1.1rem;color:#fff;"></i>
                    </div>
                    <div>
                        <div style="font-size:.65rem;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:1.8px;font-weight:700;">Filtros avanzados</div>
                        <h6 style="color:#fff;font-weight:800;margin:0;font-size:1rem;">Buscar Productos</h6>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div style="padding:20px;background:#f4f7fb;">

                <!-- Texto libre -->
                <div style="background:#fff;border-radius:12px;padding:14px 16px;margin-bottom:12px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,.04);">
                    <label style="font-size:.68rem;font-weight:800;color:#2980b9;text-transform:uppercase;letter-spacing:1.2px;display:block;margin-bottom:8px;">
                        <i class="fas fa-keyboard mr-1"></i>Nombre / Código / Marca
                    </label>
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
                        <input type="text" id="filtro_texto" class="form-control" placeholder="Ej: Laptop HP, PRD-001, Redragon...">
                    </div>
                </div>

                <!-- Categoría -->
                <div style="background:#fff;border-radius:12px;padding:14px 16px;margin-bottom:12px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,.04);">
                    <label style="font-size:.68rem;font-weight:800;color:#2980b9;text-transform:uppercase;letter-spacing:1.2px;display:block;margin-bottom:8px;">
                        <i class="fas fa-layer-group mr-1"></i>Categoría
                    </label>
                    <select id="filtro_categoria" class="form-control form-control-sm">
                        <option value="">— Todas las categorías —</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id_categoria'] ?>"><?= htmlspecialchars($cat['nombre_categoria']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Stock -->
                <div style="background:#fff;border-radius:12px;padding:14px 16px;margin-bottom:12px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,.04);">
                    <label style="font-size:.68rem;font-weight:800;color:#2980b9;text-transform:uppercase;letter-spacing:1.2px;display:block;margin-bottom:8px;">
                        <i class="fas fa-warehouse mr-1"></i>Estado de Stock
                    </label>
                    <div class="d-flex flex-wrap" style="gap:8px;">
                        <label class="filtro-stock-chip" data-val="">
                            <input type="radio" name="filtro_stock" value="" checked style="display:none;">
                            <i class="fas fa-border-all mr-1"></i>Todos
                        </label>
                        <label class="filtro-stock-chip" data-val="ok">
                            <input type="radio" name="filtro_stock" value="ok" style="display:none;">
                            <i class="fas fa-check-circle mr-1"></i>Normal
                        </label>
                        <label class="filtro-stock-chip" data-val="bajo">
                            <input type="radio" name="filtro_stock" value="bajo" style="display:none;">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Stock bajo
                        </label>
                        <label class="filtro-stock-chip" data-val="agotado">
                            <input type="radio" name="filtro_stock" value="agotado" style="display:none;">
                            <i class="fas fa-times-circle mr-1"></i>Agotado
                        </label>
                    </div>
                </div>

                <!-- Rango de precio -->
                <div style="background:#fff;border-radius:12px;padding:14px 16px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,.04);">
                    <label style="font-size:.68rem;font-weight:800;color:#2980b9;text-transform:uppercase;letter-spacing:1.2px;display:block;margin-bottom:8px;">
                        <i class="fas fa-tag mr-1"></i>Rango de Precio Venta (S/.)
                    </label>
                    <div class="d-flex align-items-center" style="gap:10px;">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
                            <input type="number" id="filtro_precio_min" class="form-control" placeholder="Mín" min="0" step="0.01">
                        </div>
                        <span style="color:#999;font-weight:700;">—</span>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
                            <input type="number" id="filtro_precio_max" class="form-control" placeholder="Máx" min="0" step="0.01">
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <div style="padding:12px 20px;background:#fff;border-top:1px solid #eef1f5;display:flex;justify-content:space-between;align-items:center;">
                <button id="btnLimpiarFiltros" type="button"
                    style="background:transparent;border:1px solid #dee2e6;color:#6c757d;font-size:.8rem;font-weight:600;padding:7px 16px;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;"
                    onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='transparent'">
                    <i class="fas fa-undo"></i> Limpiar
                </button>
                <button id="btnAplicarFiltros" type="button" data-dismiss="modal"
                    style="background:linear-gradient(135deg,#1a5276,#2980b9);border:none;color:#fff;font-size:.82rem;font-weight:700;padding:8px 22px;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:8px;box-shadow:0 3px 10px rgba(26,82,118,.3);">
                    <i class="fas fa-check"></i> Aplicar filtros
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     LIGHTBOX — Zoom de imágenes (solo modal Editar)
══════════════════════════════════════════════════════════ -->
<div id="imgLightbox" role="dialog" aria-modal="true" aria-label="Vista ampliada de imagen">
    <button class="lb-close" id="lbClose" title="Cerrar (Esc)"><i class="fas fa-times"></i></button>
    <button class="lb-prev hidden" id="lbPrev" title="Anterior"><i class="fas fa-chevron-left"></i></button>
    <button class="lb-next hidden" id="lbNext" title="Siguiente"><i class="fas fa-chevron-right"></i></button>
    <div class="lb-img-wrap">
        <img id="lbMainImg" src="" alt="Imagen ampliada">
    </div>
    <div class="lb-counter" id="lbCounter"></div>
    <div class="lb-thumbs" id="lbThumbs"></div>
</div>


