<?php
// ============================================================
// modules/catalogos/unidades.php | Botica 2026
// Gestión de Unidades de Medida con estado activo/inactivo
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
verificarPermiso($pdo, 'unidades');

// ── Patrón PRG ────────────────────────────────────────────────────────────────
$swal = null;
if (isset($_SESSION['swal_uni'])) { $swal = $_SESSION['swal_uni']; unset($_SESSION['swal_uni']); }

function redirigirUni(string $icon, string $title, string $text): void {
    $_SESSION['swal_uni'] = compact('icon', 'title', 'text');
    header('Location: unidades.php'); exit;
}

// ── CRUD ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion      = $_POST['accion']      ?? '';
    $id_unidad   = (int)($_POST['id_unidad']   ?? 0);
    $nombre      = strtoupper(trim($_POST['nombre_unidad']  ?? ''));
    $abreviatura = strtoupper(trim($_POST['abreviatura']    ?? ''));
    $estado      = (int)($_POST['estado'] ?? 1);

    try {
        // ── CREAR ─────────────────────────────────────────────────────────────
        if ($accion === 'crear') {
            if (empty($nombre) || empty($abreviatura)) {
                redirigirUni('warning', 'Campos incompletos', 'El nombre y la abreviatura son obligatorios.');
            }
            $pdo->prepare("INSERT INTO unidades (nombre_unidad, abreviatura, estado) VALUES (?, ?, 1)")
                ->execute([$nombre, $abreviatura]);
            redirigirUni('success', '¡Registrada!', "La unidad $nombre fue registrada correctamente.");

        // ── ACTUALIZAR ────────────────────────────────────────────────────────
        } elseif ($accion === 'actualizar' && $id_unidad > 0) {
            if (empty($nombre) || empty($abreviatura)) {
                redirigirUni('warning', 'Campos incompletos', 'El nombre y la abreviatura son obligatorios.');
            }
            $pdo->prepare("UPDATE unidades SET nombre_unidad=?, abreviatura=?, estado=? WHERE id_unidad=?")
                ->execute([$nombre, $abreviatura, $estado, $id_unidad]);
            redirigirUni('success', '¡Actualizada!', "La unidad fue actualizada correctamente.");

        // ── DESACTIVAR ────────────────────────────────────────────────────────
        } elseif ($accion === 'desactivar' && $id_unidad > 0) {
            $pdo->prepare("UPDATE unidades SET estado=0 WHERE id_unidad=?")->execute([$id_unidad]);
            redirigirUni('info', 'Desactivada', "La unidad fue desactivada y movida a inactivas.");

        // ── REACTIVAR ─────────────────────────────────────────────────────────
        } elseif ($accion === 'reactivar' && $id_unidad > 0) {
            $pdo->prepare("UPDATE unidades SET estado=1 WHERE id_unidad=?")->execute([$id_unidad]);
            redirigirUni('success', '¡Reactivada!', "La unidad fue reactivada correctamente.");

        // ── ELIMINAR PERMANENTE ───────────────────────────────────────────────
        } elseif ($accion === 'eliminar' && $id_unidad > 0) {
            $pdo->prepare("DELETE FROM unidades WHERE id_unidad=?")->execute([$id_unidad]);
            redirigirUni('success', 'Eliminada', "La unidad fue eliminada permanentemente.");
        }

    } catch (PDOException $e) {
        $msg = ($e->getCode() == 23000)
            ? 'La abreviatura o nombre ya está registrado, o la unidad tiene productos asociados.'
            : 'Error: ' . $e->getMessage();
        redirigirUni('error', 'Error', $msg);
    }
}

// ── DATOS ─────────────────────────────────────────────────────────────────────
$unidades_activas = $unidades_inactivas = [];

try {
    $sql_base = "SELECT u.id_unidad, u.nombre_unidad, u.abreviatura, u.estado,
                    COUNT(p.id_producto) AS total_productos
                 FROM unidades u
                 LEFT JOIN productos p ON u.id_unidad = p.id_unidad AND p.estado = 1";

    $unidades_activas   = $pdo->query($sql_base . " WHERE u.estado=1 GROUP BY u.id_unidad ORDER BY u.nombre_unidad")->fetchAll();
    $unidades_inactivas = $pdo->query($sql_base . " WHERE u.estado=0 GROUP BY u.id_unidad ORDER BY u.nombre_unidad")->fetchAll();

} catch (PDOException $e) {
    $swal = ['icon' => 'error', 'title' => 'Error', 'text' => 'Error al cargar datos: ' . $e->getMessage()];
}

$total_activas   = count($unidades_activas);
$total_inactivas = count($unidades_inactivas);
$total_productos = array_sum(array_column($unidades_activas, 'total_productos'));

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>

<!-- CSS del módulo -->
<link rel="stylesheet" href="css/unidades.css">

<div class="content-wrapper">

    <!-- ── CABECERA ── -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-uni d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4><i class="fas fa-ruler-combined mr-2"></i>Gestión de Unidades</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>Botica 2026 &rsaquo; Catálogos &rsaquo; Unidades</small>
                </div>
                <button class="btn btn-light font-weight-bold" data-toggle="modal" data-target="#modalCrearUnidad">
                    <i class="fas fa-plus-circle mr-1"></i> Nueva Unidad
                </button>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- ── STATS ── -->
            <div class="row mb-4">
                <div class="col-md-4 col-6 mb-2">
                    <div class="stat-mini-uni" style="background:linear-gradient(135deg,#1a5276,#117a8b);">
                        <i class="fas fa-ruler-combined"></i>
                        <div>
                            <div class="stat-value"><?= $total_activas ?></div>
                            <div class="stat-label">Unidades activas</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6 mb-2">
                    <div class="stat-mini-uni" style="background:linear-gradient(135deg,#922b21,#e74c3c);">
                        <i class="fas fa-ban"></i>
                        <div>
                            <div class="stat-value"><?= $total_inactivas ?></div>
                            <div class="stat-label">Inactivas</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6 mb-2">
                    <div class="stat-mini-uni" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);">
                        <i class="fas fa-capsules"></i>
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
                    confirmButtonColor: '#117a8b',
                    timer: <?= in_array($swal['icon'],['success','info']) ? 3000 : 0 ?>,
                    timerProgressBar: <?= in_array($swal['icon'],['success','info']) ? 'true' : 'false' ?>,
                    showConfirmButton: <?= in_array($swal['icon'],['success','info']) ? 'false' : 'true' ?>,
                });
            });
            </script>
            <?php endif; ?>

            <!-- ── CARD PRINCIPAL ── -->
            <div class="card">
                <div class="card-header-uni d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="mb-0"><i class="fas fa-list mr-2"></i>Catálogo de Unidades de Medida</h6>
                    <ul class="nav mb-0" style="border:none;">
                        <li class="nav-item">
                            <a class="nav-link active text-white font-weight-bold py-1 px-2" data-toggle="pill" href="#tab-activas-uni">
                                Activas <span class="badge badge-light ml-1"><?= $total_activas ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white font-weight-bold py-1 px-2" data-toggle="pill" href="#tab-inactivas-uni">
                                Inactivas <span class="badge badge-light ml-1"><?= $total_inactivas ?></span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="card-body">
                    <div class="tab-content">

                        <!-- ── TAB ACTIVAS ── -->
                        <div class="tab-pane fade show active" id="tab-activas-uni">
                            <div class="table-responsive">
                                <table id="tablaUnidades" class="table table-unidades table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th style="width:50px;">#</th>
                                            <th>Unidad</th>
                                            <th style="width:110px;" class="text-center">Abreviatura</th>
                                            <th style="width:120px;" class="text-center">Productos</th>
                                            <th style="width:100px;" class="text-center">Estado</th>
                                            <th style="width:150px;" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php $num = 1; foreach ($unidades_activas as $u): ?>
                                        <tr>
                                            <td class="text-center"><div class="num-fila"><?= $num++ ?></div></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="uni-icon"><i class="fas fa-balance-scale"></i></div>
                                                    <div class="uni-nombre"><?= htmlspecialchars($u['nombre_unidad']) ?></div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge-abrev"><?= htmlspecialchars($u['abreviatura']) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge-productos">
                                                    <i class="fas fa-capsules mr-1"></i><?= $u['total_productos'] ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge-activo"><i class="fas fa-check-circle mr-1"></i>Activa</span>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-info btn-ver-unidad" title="Ver detalle"
                                                    data-id="<?= $u['id_unidad'] ?>"
                                                    data-nombre="<?= htmlspecialchars($u['nombre_unidad'],ENT_QUOTES) ?>"
                                                    data-abrev="<?= htmlspecialchars($u['abreviatura'],ENT_QUOTES) ?>"
                                                    data-productos="<?= $u['total_productos'] ?>"
                                                    data-estado="<?= $u['estado'] ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning btn-editar-unidad" title="Editar"
                                                    data-id="<?= $u['id_unidad'] ?>"
                                                    data-nombre="<?= htmlspecialchars($u['nombre_unidad'],ENT_QUOTES) ?>"
                                                    data-abrev="<?= htmlspecialchars($u['abreviatura'],ENT_QUOTES) ?>"
                                                    data-estado="<?= $u['estado'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger btn-desactivar-unidad" title="Desactivar"
                                                    data-id="<?= $u['id_unidad'] ?>"
                                                    data-nombre="<?= htmlspecialchars($u['nombre_unidad'],ENT_QUOTES) ?>">
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
                        <div class="tab-pane fade" id="tab-inactivas-uni">
                            <div class="table-responsive">
                                <table class="table table-unidades table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th style="width:50px;">#</th>
                                            <th>Unidad</th>
                                            <th style="width:110px;" class="text-center">Abreviatura</th>
                                            <th style="width:120px;" class="text-center">Productos</th>
                                            <th style="width:100px;" class="text-center">Estado</th>
                                            <th style="width:150px;" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php $num = 1; foreach ($unidades_inactivas as $u): ?>
                                        <tr class="table-light text-muted">
                                            <td class="text-center"><div class="num-fila"><?= $num++ ?></div></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="uni-icon"><i class="fas fa-balance-scale"></i></div>
                                                    <div class="uni-nombre"><?= htmlspecialchars($u['nombre_unidad']) ?></div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge-abrev"><?= htmlspecialchars($u['abreviatura']) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge-productos">
                                                    <i class="fas fa-capsules mr-1"></i><?= $u['total_productos'] ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge-inactivo"><i class="fas fa-times-circle mr-1"></i>Inactiva</span>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-info btn-ver-unidad" title="Ver detalle"
                                                    data-id="<?= $u['id_unidad'] ?>"
                                                    data-nombre="<?= htmlspecialchars($u['nombre_unidad'],ENT_QUOTES) ?>"
                                                    data-abrev="<?= htmlspecialchars($u['abreviatura'],ENT_QUOTES) ?>"
                                                    data-productos="<?= $u['total_productos'] ?>"
                                                    data-estado="<?= $u['estado'] ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-success btn-reactivar-unidad" title="Reactivar"
                                                    data-id="<?= $u['id_unidad'] ?>"
                                                    data-nombre="<?= htmlspecialchars($u['nombre_unidad'],ENT_QUOTES) ?>">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger btn-eliminar-unidad" title="Eliminar permanente"
                                                    data-id="<?= $u['id_unidad'] ?>"
                                                    data-nombre="<?= htmlspecialchars($u['nombre_unidad'],ENT_QUOTES) ?>">
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
     MODAL VER UNIDAD
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalVerUnidad" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;">

            <div style="background:linear-gradient(135deg,#1a5276,#117a8b);padding:20px 24px;position:relative;">
                <button type="button" class="close" data-dismiss="modal"
                    style="position:absolute;top:12px;right:16px;color:#fff;opacity:.8;font-size:1.4rem;">&times;</button>
                <div class="d-flex align-items-center gap-3">
                    <div style="width:52px;height:52px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-ruler-combined" style="font-size:1.5rem;color:#fff;"></i>
                    </div>
                    <div>
                        <h5 id="ver_uni_nombre" style="color:#fff;font-weight:700;margin:0;font-size:1.05rem;"></h5>
                        <div class="mt-1 d-flex gap-2 align-items-center flex-wrap">
                            <span id="ver_uni_abrev_badge" style="background:rgba(255,255,255,.25);color:#fff;font-size:.8rem;padding:2px 10px;border-radius:6px;font-family:monospace;font-weight:700;letter-spacing:1px;"></span>
                            <span id="ver_uni_badge_estado" style="font-size:.75rem;padding:2px 10px;border-radius:20px;"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-body" style="padding:20px 24px;">
                <div class="row">
                    <div class="col-6">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div style="width:34px;height:34px;background:#e0f7fa;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-tag" style="color:#117a8b;font-size:.8rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.7rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Abreviatura</div>
                                <div id="ver_uni_abrev" style="font-size:1.1rem;color:#117a8b;font-weight:700;font-family:monospace;margin-top:2px;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div style="width:34px;height:34px;background:#e8f5e9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-capsules" style="color:#1a7a4a;font-size:.8rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.7rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Productos activos</div>
                                <div id="ver_uni_productos" style="font-size:1.1rem;color:#1a7a4a;font-weight:700;margin-top:2px;"></div>
                            </div>
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
     MODAL CREAR UNIDAD
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCrearUnidad" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#1a5276,#117a8b);color:#fff;border-radius:12px 12px 0 0;">
                <h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i>Registrar Nueva Unidad</h5>
                <button type="button" class="close" style="color:#fff;" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label-uni"><i class="fas fa-ruler-combined mr-1 text-muted"></i>Nombre de la Unidad <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-balance-scale"></i></span></div>
                            <input type="text" class="form-control" name="nombre_unidad" required maxlength="50" placeholder="Ej: UNIDAD, CAJA, FRASCO">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label-uni"><i class="fas fa-tag mr-1 text-muted"></i>Abreviatura <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-font"></i></span></div>
                            <input type="text" class="form-control" name="abreviatura" required maxlength="10" placeholder="Ej: UND, CJ, FCO" style="text-transform:uppercase;">
                        </div>
                        <small class="text-muted">Máximo 10 caracteres</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
                    <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#1a5276,#117a8b);color:#fff;font-weight:600;">
                        <i class="fas fa-save mr-1"></i>Guardar Unidad
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL EDITAR UNIDAD
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditarUnidad" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#a04000,#e67e22);color:#fff;border-radius:12px 12px 0 0;">
                <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Editar Unidad</h5>
                <button type="button" class="close" style="color:#fff;" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="actualizar">
                <input type="hidden" name="id_unidad" id="editar_id_unidad">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label-uni"><i class="fas fa-ruler-combined mr-1 text-muted"></i>Nombre de la Unidad <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-balance-scale"></i></span></div>
                            <input type="text" class="form-control" name="nombre_unidad" id="editar_nombre_unidad" required maxlength="50">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label-uni"><i class="fas fa-tag mr-1 text-muted"></i>Abreviatura <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-font"></i></span></div>
                            <input type="text" class="form-control" name="abreviatura" id="editar_abreviatura" required maxlength="10" style="text-transform:uppercase;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label-uni"><i class="fas fa-toggle-on mr-1 text-muted"></i>Estado</label>
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

<?php include $ruta_base . 'includes/footer.php'; ?>
<script src="js/unidades.js"></script>


