<?php
// ============================================================
// modules/catalogos/categorias.php | SysInversiones CH Computer
// Gestión de Categorías con estado activo/inactivo
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexión BD no disponible.');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR_COMERCIAL'))        define('ROL_ASESOR_COMERCIAL', 2);
if (!defined('ROL_TECNICO'))    define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);
verificarPermiso($pdo, 'categorias');

// ── Patrón PRG ────────────────────────────────────────────────────────────────
$swal = null;
if (isset($_SESSION['swal_cat'])) { $swal = $_SESSION['swal_cat']; unset($_SESSION['swal_cat']); }

function redirigirCat(string $icon, string $title, string $text): void {
    $_SESSION['swal_cat'] = compact('icon', 'title', 'text');
    header('Location: categorias.php'); exit;
}

// ── CRUD ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion         = $_POST['accion']         ?? '';
    $id_categoria   = (int)($_POST['id_categoria'] ?? 0);
    $nombre         = strtoupper(trim($_POST['nombre_categoria'] ?? ''));
    $descripcion    = trim($_POST['descripcion']    ?? '') ?: null;
    $estado         = (int)($_POST['estado']        ?? 1);

    try {
        // ── CREAR ─────────────────────────────────────────────────────────────
        if ($accion === 'crear') {
            if (empty($nombre)) {
                redirigirCat('warning', 'Campo incompleto', 'El nombre de la categoría es obligatorio.');
            }
            $sql = "INSERT INTO categorias (nombre_categoria, descripcion, estado, fecha_registro) VALUES (?, ?, 1, NOW())";
            $pdo->prepare($sql)->execute([$nombre, $descripcion]);
            redirigirCat('success', '¡Registrada!', "La categoría $nombre fue registrada correctamente.");

        // ── ACTUALIZAR ────────────────────────────────────────────────────────
        } elseif ($accion === 'actualizar' && $id_categoria > 0) {
            if (empty($nombre)) {
                redirigirCat('warning', 'Campo incompleto', 'El nombre de la categoría es obligatorio.');
            }
            $sql = "UPDATE categorias SET nombre_categoria=?, descripcion=?, estado=? WHERE id_categoria=?";
            $pdo->prepare($sql)->execute([$nombre, $descripcion, $estado, $id_categoria]);
            redirigirCat('success', '¡Actualizada!', "La categoría fue actualizada correctamente.");

        // ── DESACTIVAR ────────────────────────────────────────────────────────
        } elseif ($accion === 'desactivar' && $id_categoria > 0) {
            $pdo->prepare("UPDATE categorias SET estado=0 WHERE id_categoria=?")->execute([$id_categoria]);
            redirigirCat('info', 'Desactivada', "La categoría fue desactivada y movida a inactivas.");

        // ── REACTIVAR ─────────────────────────────────────────────────────────
        } elseif ($accion === 'reactivar' && $id_categoria > 0) {
            $pdo->prepare("UPDATE categorias SET estado=1 WHERE id_categoria=?")->execute([$id_categoria]);
            redirigirCat('success', '¡Reactivada!', "La categoría fue reactivada correctamente.");

        // ── ELIMINAR PERMANENTE ───────────────────────────────────────────────
        } elseif ($accion === 'eliminar' && $id_categoria > 0) {
            $pdo->prepare("DELETE FROM categorias WHERE id_categoria=?")->execute([$id_categoria]);
            redirigirCat('success', 'Eliminada', "La categoría fue eliminada permanentemente.");
        }

    } catch (PDOException $e) {
        $msg = ($e->getCode() == 23000)
            ? 'El nombre de la categoría ya está registrado o tiene productos asociados.'
            : 'Error: ' . $e->getMessage();
        redirigirCat('error', 'Error', $msg);
    }
}

// ── DATOS ─────────────────────────────────────────────────────────────────────
$categorias_activas = $categorias_inactivas = [];

try {
    $sql_base = "SELECT c.id_categoria, c.nombre_categoria, c.descripcion, c.estado, c.fecha_registro,
                    COUNT(p.id_producto) AS total_productos
                 FROM categorias c
                 LEFT JOIN productos p ON c.id_categoria = p.id_categoria AND p.estado = 1";

    $categorias_activas   = $pdo->query($sql_base . " WHERE c.estado=1 GROUP BY c.id_categoria ORDER BY c.nombre_categoria")->fetchAll();
    $categorias_inactivas = $pdo->query($sql_base . " WHERE c.estado=0 GROUP BY c.id_categoria ORDER BY c.nombre_categoria")->fetchAll();

} catch (PDOException $e) {
    $swal = ['icon' => 'error', 'title' => 'Error', 'text' => 'Error al cargar datos: ' . $e->getMessage()];
}

$total_activas   = count($categorias_activas);
$total_inactivas = count($categorias_inactivas);
$total_productos = array_sum(array_column($categorias_activas, 'total_productos'));

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>

<!-- CSS del módulo -->
<link rel="stylesheet" href="css/categorias.css?v=<?= time() ?>">

<div class="content-wrapper">

    <!-- ── CABECERA ── -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-cat d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4><i class="fas fa-tags mr-2"></i>Gestión de Categorías</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones CH Computer &rsaquo; Catálogos &rsaquo; Categorías</small>
                </div>
                <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
                    <!-- Botones de exportación -->
                    <div class="cat-export-group">
                        <button id="btn-exportar-csv-cat" class="cat-export-btn cat-export-csv" title="Exportar a CSV">
                            <i class="fas fa-file-csv"></i>
                            <span>CSV</span>
                        </button>
                        <button id="btn-exportar-excel-cat" class="cat-export-btn cat-export-excel" title="Exportar a Excel">
                            <i class="fas fa-file-excel"></i>
                            <span>Excel</span>
                        </button>
                        <button id="btn-exportar-pdf-cat" class="cat-export-btn cat-export-pdf" title="Exportar a PDF">
                            <i class="fas fa-file-pdf"></i>
                            <span>PDF</span>
                        </button>
                    </div>
                    <button class="btn btn-light font-weight-bold" data-toggle="modal" data-target="#modalCrearCategoria">
                        <i class="fas fa-plus-circle mr-1"></i> Nueva Categoría
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- ── STATS ── -->
            <div class="row mb-4">
                <div class="col-md-4 col-6 mb-2">
                    <div class="stat-mini-cat" style="background:linear-gradient(135deg,#1a5276,#2980b9);">
                        <i class="fas fa-tags"></i>
                        <div>
                            <div class="stat-value"><?= $total_activas ?></div>
                            <div class="stat-label">Categorías activas</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6 mb-2">
                    <div class="stat-mini-cat" style="background:linear-gradient(135deg,#922b21,#e74c3c);">
                        <i class="fas fa-ban"></i>
                        <div>
                            <div class="stat-value"><?= $total_inactivas ?></div>
                            <div class="stat-label">Inactivas</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6 mb-2">
                    <div class="stat-mini-cat" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);">
                        <i class="fas fa-boxes"></i>
                        <div>
                            <div class="stat-value"><?= $total_productos ?></div>
                            <div class="stat-label">Productos asociados</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── ALERTA SWAL ── -->
            <?php if ($swal): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon: '<?= $swal['icon'] ?>',
                    title: '<?= addslashes($swal['title']) ?>',
                    text: '<?= addslashes($swal['text']) ?>',
                    confirmButtonColor: '#1a5276',
                    timer: <?= in_array($swal['icon'],['success','info']) ? 3000 : 0 ?>,
                    timerProgressBar: <?= in_array($swal['icon'],['success','info']) ? 'true' : 'false' ?>,
                    showConfirmButton: <?= in_array($swal['icon'],['success','info']) ? 'false' : 'true' ?>,
                });
            });
            </script>
            <?php endif; ?>

            <!-- ── CARD PRINCIPAL ── -->
            <div class="card">
                <div class="card-header-cat d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="mb-0"><i class="fas fa-list mr-2"></i>Catálogo de Categorías</h6>
                    <ul class="nav mb-0" style="border:none;">
                        <li class="nav-item">
                            <a class="nav-link active text-white font-weight-bold py-1 px-2" data-toggle="pill" href="#tab-activas-cat">
                                Activas <span class="badge badge-light ml-1"><?= $total_activas ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white font-weight-bold py-1 px-2" data-toggle="pill" href="#tab-inactivas-cat">
                                Inactivas <span class="badge badge-light ml-1"><?= $total_inactivas ?></span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="card-body">
                    <div class="tab-content">

                        <!-- ── TAB ACTIVAS ── -->
                        <div class="tab-pane fade show active" id="tab-activas-cat">
                            <div class="table-responsive">
                                <table id="tablaCategorias" class="table table-categorias table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th style="width:50px;">#</th>
                                            <th>Categoría</th>
                                            <th style="width:120px;" class="text-center">Productos</th>
                                            <th style="width:100px;" class="text-center">Estado</th>
                                            <th style="width:140px;" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php $num = 1; foreach ($categorias_activas as $c): ?>
                                        <tr>
                                            <td class="text-center"><div class="num-fila"><?= $num++ ?></div></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="cat-icon"><i class="fas fa-tag"></i></div>
                                                    <div>
                                                        <div class="cat-nombre"><?= htmlspecialchars($c['nombre_categoria']) ?></div>
                                                        <?php if (!empty($c['descripcion'])): ?>
                                                            <div class="cat-descripcion"><?= htmlspecialchars($c['descripcion']) ?></div>
                                                        <?php else: ?>
                                                            <div class="cat-descripcion" style="font-style:italic;color:#ccc;">Sin descripción</div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge-productos">
                                                    <i class="fas fa-box mr-1"></i><?= $c['total_productos'] ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge-activo"><i class="fas fa-check-circle mr-1"></i>Activa</span>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-info btn-ver-categoria" title="Ver detalle"
                                                    data-id="<?= $c['id_categoria'] ?>"
                                                    data-nombre="<?= htmlspecialchars($c['nombre_categoria'],ENT_QUOTES) ?>"
                                                    data-descripcion="<?= htmlspecialchars($c['descripcion']??'',ENT_QUOTES) ?>"
                                                    data-productos="<?= $c['total_productos'] ?>"
                                                    data-fecha="<?= date('d/m/Y H:i', strtotime($c['fecha_registro'])) ?>"
                                                    data-estado="<?= $c['estado'] ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning btn-editar-categoria" title="Editar"
                                                    data-id="<?= $c['id_categoria'] ?>"
                                                    data-nombre="<?= htmlspecialchars($c['nombre_categoria'],ENT_QUOTES) ?>"
                                                    data-descripcion="<?= htmlspecialchars($c['descripcion']??'',ENT_QUOTES) ?>"
                                                    data-estado="<?= $c['estado'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger btn-desactivar-categoria" title="Desactivar"
                                                    data-id="<?= $c['id_categoria'] ?>"
                                                    data-nombre="<?= htmlspecialchars($c['nombre_categoria'],ENT_QUOTES) ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ── TAB INACTIVAS ── -->
                        <div class="tab-pane fade" id="tab-inactivas-cat">
                            <div class="table-responsive">
                                <table class="table table-categorias table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th style="width:50px;">#</th>
                                            <th>Categoría</th>
                                            <th style="width:120px;" class="text-center">Productos</th>
                                            <th style="width:100px;" class="text-center">Estado</th>
                                            <th style="width:140px;" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php $num = 1; foreach ($categorias_inactivas as $c): ?>
                                        <tr class="table-light text-muted">
                                            <td class="text-center"><div class="num-fila"><?= $num++ ?></div></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="cat-icon"><i class="fas fa-tag"></i></div>
                                                    <div>
                                                        <div class="cat-nombre"><?= htmlspecialchars($c['nombre_categoria']) ?></div>
                                                        <?php if (!empty($c['descripcion'])): ?>
                                                            <div class="cat-descripcion"><?= htmlspecialchars($c['descripcion']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge-productos">
                                                    <i class="fas fa-box mr-1"></i><?= $c['total_productos'] ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge-inactivo"><i class="fas fa-times-circle mr-1"></i>Inactiva</span>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-info btn-ver-categoria" title="Ver detalle"
                                                    data-id="<?= $c['id_categoria'] ?>"
                                                    data-nombre="<?= htmlspecialchars($c['nombre_categoria'],ENT_QUOTES) ?>"
                                                    data-descripcion="<?= htmlspecialchars($c['descripcion']??'',ENT_QUOTES) ?>"
                                                    data-productos="<?= $c['total_productos'] ?>"
                                                    data-fecha="<?= date('d/m/Y H:i', strtotime($c['fecha_registro'])) ?>"
                                                    data-estado="<?= $c['estado'] ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-success btn-reactivar-categoria" title="Reactivar"
                                                    data-id="<?= $c['id_categoria'] ?>"
                                                    data-nombre="<?= htmlspecialchars($c['nombre_categoria'],ENT_QUOTES) ?>">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger btn-eliminar-categoria" title="Eliminar permanente"
                                                    data-id="<?= $c['id_categoria'] ?>"
                                                    data-nombre="<?= htmlspecialchars($c['nombre_categoria'],ENT_QUOTES) ?>">
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
     MODAL CREAR CATEGORÍA
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCrearCategoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;border-radius:12px 12px 0 0;">
                <h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i>Registrar Nueva Categoría</h5>
                <button type="button" class="close" style="color:#fff;" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label-cat"><i class="fas fa-tag mr-1 text-muted"></i>Nombre de la Categoría <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-tags"></i></span></div>
                            <input type="text" class="form-control" name="nombre_categoria" required maxlength="100" placeholder="Ej: LAPTOPS, PERIFÉRICOS, ACCESORIOS">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label-cat"><i class="fas fa-align-left mr-1 text-muted"></i>Descripción</label>
                        <textarea class="form-control form-control-sm" name="descripcion" rows="2" maxlength="150" placeholder="Descripción breve de la categoría (opcional)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
                    <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;font-weight:600;">
                        <i class="fas fa-save mr-1"></i>Guardar Categoría
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL EDITAR CATEGORÍA
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditarCategoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#a04000,#e67e22);color:#fff;border-radius:12px 12px 0 0;">
                <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Editar Categoría</h5>
                <button type="button" class="close" style="color:#fff;" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="actualizar">
                <input type="hidden" name="id_categoria" id="editar_id_categoria">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label-cat"><i class="fas fa-tag mr-1 text-muted"></i>Nombre de la Categoría <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-tags"></i></span></div>
                            <input type="text" class="form-control" name="nombre_categoria" id="editar_nombre_categoria" required maxlength="100">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label-cat"><i class="fas fa-align-left mr-1 text-muted"></i>Descripción</label>
                        <textarea class="form-control form-control-sm" name="descripcion" id="editar_descripcion" rows="2" maxlength="150"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label-cat"><i class="fas fa-toggle-on mr-1 text-muted"></i>Estado</label>
                        <select class="form-control form-control-sm" name="estado" id="editar_estado">
                            <option value="1">✅ Activa</option>
                            <option value="0">❌ Inactiva</option>
                        </select>
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
     MODAL VER CATEGORÍA
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalVerCategoria" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" style="max-width:460px;">
        <div class="modal-content" style="border:none;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.22);">

            <!-- HEADER -->
            <div style="background:linear-gradient(135deg,#1a3a5c 0%,#1a5276 50%,#2471a3 100%);padding:24px 22px 20px;position:relative;overflow:hidden;">
                <!-- Decoración geométrica -->
                <div style="position:absolute;top:-30px;right:-30px;width:130px;height:130px;border-radius:50%;background:rgba(255,255,255,.07);pointer-events:none;"></div>
                <div style="position:absolute;bottom:-20px;right:60px;width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,.05);pointer-events:none;"></div>
                <div style="position:absolute;top:10px;left:45%;width:40px;height:40px;border-radius:50%;background:rgba(41,128,185,.35);pointer-events:none;"></div>

                <!-- Botón cerrar -->
                <button type="button" data-dismiss="modal"
                    style="position:absolute;top:12px;right:14px;background:rgba(255,255,255,.12);border:none;color:#fff;width:30px;height:30px;border-radius:50%;font-size:.85rem;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .2s;z-index:3;"
                    onmouseover="this.style.background='rgba(231,76,60,.75)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">
                    <i class="fas fa-times"></i>
                </button>

                <div style="display:flex;align-items:center;gap:14px;position:relative;z-index:2;">
                    <!-- Ícono -->
                    <div style="width:54px;height:54px;background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.25);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-layer-group" style="font-size:1.4rem;color:#fff;"></i>
                    </div>
                    <div>
                        <div style="font-size:.68rem;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:1.8px;font-weight:700;margin-bottom:5px;">
                            <i class="fas fa-sitemap mr-1"></i>Categoría de Producto
                        </div>
                        <h5 id="ver_cat_nombre" style="color:#fff;font-weight:800;margin:0;font-size:1.15rem;letter-spacing:-.2px;line-height:1.2;"></h5>
                        <div style="margin-top:8px;">
                            <span id="ver_cat_badge_estado" style="font-size:.7rem;font-weight:700;padding:3px 12px;border-radius:20px;letter-spacing:.3px;"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BODY -->
            <div style="padding:20px;background:#f4f7fb;">

                <!-- Descripción -->
                <div style="background:#fff;border-radius:12px;padding:14px 16px;margin-bottom:14px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,.05);">
                    <div style="font-size:.65rem;font-weight:800;color:#2980b9;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:6px;">
                        <i class="fas fa-align-left mr-1"></i>Descripción
                    </div>
                    <div id="ver_cat_descripcion" style="font-size:.9rem;color:#2d3436;font-weight:500;line-height:1.55;"></div>
                </div>

                <!-- Stats -->
                <div class="row" style="margin:0 -5px;">
                    <!-- Productos -->
                    <div class="col-6" style="padding:0 5px;">
                        <div style="background:#fff;border-radius:12px;padding:14px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,.05);display:flex;align-items:center;gap:12px;">
                            <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#e8f5e9,#c8e6c9);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-boxes" style="color:#1a7a4a;font-size:.95rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.62rem;font-weight:800;color:#999;text-transform:uppercase;letter-spacing:.8px;margin-bottom:3px;">Productos</div>
                                <div id="ver_cat_productos" style="font-size:1.1rem;font-weight:800;color:#1a7a4a;line-height:1;"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Fecha -->
                    <div class="col-6" style="padding:0 5px;">
                        <div style="background:#fff;border-radius:12px;padding:14px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,.05);display:flex;align-items:center;gap:12px;">
                            <div style="width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,#e3f2fd,#bbdefb);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-calendar-check" style="color:#1a5276;font-size:.95rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.62rem;font-weight:800;color:#999;text-transform:uppercase;letter-spacing:.8px;margin-bottom:3px;">Registrada</div>
                                <div id="ver_cat_fecha" style="font-size:.82rem;font-weight:700;color:#1a5276;line-height:1.3;"></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- FOOTER -->
            <div style="padding:12px 20px;background:#fff;border-top:1px solid #eef1f5;display:flex;justify-content:flex-end;">
                <button type="button" data-dismiss="modal"
                    style="background:#f1f3f5;border:1px solid #dee2e6;color:#495057;font-size:.82rem;font-weight:600;padding:7px 20px;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:all .15s;"
                    onmouseover="this.style.background='#e74c3c';this.style.borderColor='#e74c3c';this.style.color='#fff'"
                    onmouseout="this.style.background='#f1f3f5';this.style.borderColor='#dee2e6';this.style.color='#495057'">
                    <i class="fas fa-times"></i> Cerrar
                </button>
            </div>

        </div>
    </div>
</div>
<?php include $ruta_base . 'includes/footer.php'; ?>
<script src="js/categorias.js?v=<?= time() ?>"></script>






