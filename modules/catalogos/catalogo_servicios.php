<?php
// ============================================================
// modules/servicios/catalogo_servicios.php | SysInversiones CH Computer 2026
// Catálogo de Servicios Técnicos — CRUD completo
// Tablas: servicios, servicio_tipos
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexión BD no disponible.');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_TECNICO'))       define('ROL_TECNICO', 3);
if (!defined('ROL_ASESOR_COMERCIAL'))        define('ROL_ASESOR_COMERCIAL', 2);
verificar_acceso([ROL_ADMINISTRADOR, ROL_TECNICO, ROL_ASESOR_COMERCIAL]);
verificarPermiso($pdo, 'catalogo_servicios');

$id_usuario_sesion = $_SESSION['id_usuario'] ?? 0;

// ── Patrón PRG ────────────────────────────────────────────────────────────────
$swal = null;
if (isset($_SESSION['swal_srv'])) { $swal = $_SESSION['swal_srv']; unset($_SESSION['swal_srv']); }

function redirigirSrv(string $icon, string $title, string $text): void {
    $_SESSION['swal_srv'] = compact('icon', 'title', 'text');
    header('Location: catalogo_servicios.php'); exit;
}

$tipos_equipo_disponibles = ['Laptop','PC','All-in-One','Tablet','Impresora','Monitor','Otro'];

// ── CRUD ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion      = $_POST['accion'] ?? '';
    $id_servicio = (int)($_POST['id_servicio'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $tipo        = $_POST['tipo'] ?? 'catalogo';
    $descripcion = trim($_POST['descripcion'] ?? '') ?: null;
    $precio_base = (float)($_POST['precio_base'] ?? 0);
    $estado      = (int)($_POST['estado'] ?? 1);
    $tipos_eq    = $_POST['tipos_equipo'] ?? [];

    try {
        // ── CREAR ─────────────────────────────────────────────────────────────
        if ($accion === 'crear') {
            if (empty($nombre)) redirigirSrv('warning', 'Campo requerido', 'El nombre del servicio es obligatorio.');
            if ($tipo !== 'personalizado' && $precio_base <= 0) redirigirSrv('warning', 'Precio inválido', 'El precio base debe ser mayor a 0.');

            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO servicios (nombre, tipo, descripcion, precio_base, estado) VALUES (?,?,?,?,1)")
                ->execute([$nombre, $tipo, $descripcion, $precio_base]);
            $id_nuevo = (int)$pdo->lastInsertId();

            if (!empty($tipos_eq)) {
                $stTipo = $pdo->prepare("INSERT INTO servicio_tipos (id_servicio, tipo_equipo) VALUES (?,?)");
                foreach ($tipos_eq as $te) {
                    $stTipo->execute([$id_nuevo, $te]);
                }
            }
            $pdo->commit();
            redirigirSrv('success', '¡Servicio registrado!', "\"$nombre\" fue agregado al catálogo.");

        // ── ACTUALIZAR ────────────────────────────────────────────────────────
        } elseif ($accion === 'actualizar' && $id_servicio > 0) {
            if (empty($nombre)) redirigirSrv('warning', 'Campo requerido', 'El nombre del servicio es obligatorio.');

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE servicios SET nombre=?, tipo=?, descripcion=?, precio_base=?, estado=? WHERE id_servicio=?")
                ->execute([$nombre, $tipo, $descripcion, $precio_base, $estado, $id_servicio]);

            // Reemplazar tipos de equipo
            $pdo->prepare("DELETE FROM servicio_tipos WHERE id_servicio=?")->execute([$id_servicio]);
            if (!empty($tipos_eq)) {
                $stTipo = $pdo->prepare("INSERT INTO servicio_tipos (id_servicio, tipo_equipo) VALUES (?,?)");
                foreach ($tipos_eq as $te) {
                    $stTipo->execute([$id_servicio, $te]);
                }
            }
            $pdo->commit();
            redirigirSrv('success', '¡Actualizado!', "El servicio fue actualizado correctamente.");

        // ── DESACTIVAR ────────────────────────────────────────────────────────
        } elseif ($accion === 'desactivar' && $id_servicio > 0) {
            $pdo->prepare("UPDATE servicios SET estado=0 WHERE id_servicio=?")->execute([$id_servicio]);
            redirigirSrv('info', 'Desactivado', 'El servicio fue desactivado.');

        // ── REACTIVAR ─────────────────────────────────────────────────────────
        } elseif ($accion === 'reactivar' && $id_servicio > 0) {
            $pdo->prepare("UPDATE servicios SET estado=1 WHERE id_servicio=?")->execute([$id_servicio]);
            redirigirSrv('success', '¡Reactivado!', 'El servicio fue reactivado.');

        // ── ELIMINAR ──────────────────────────────────────────────────────────
        } elseif ($accion === 'eliminar' && $id_servicio > 0) {
            // Verificar si tiene órdenes asociadas
            $enUso = $pdo->prepare("SELECT COUNT(*) FROM detalle_orden WHERE id_servicio=?");
            $enUso->execute([$id_servicio]);
            if ($enUso->fetchColumn() > 0) {
                redirigirSrv('warning', 'No se puede eliminar', 'Este servicio ya fue usado en órdenes de trabajo. Puedes desactivarlo en su lugar.');
            }
            $pdo->prepare("DELETE FROM servicio_tipos WHERE id_servicio=?")->execute([$id_servicio]);
            $pdo->prepare("DELETE FROM servicios WHERE id_servicio=?")->execute([$id_servicio]);
            redirigirSrv('success', 'Eliminado', 'El servicio fue eliminado permanentemente.');
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirigirSrv('error', 'Error', $e->getMessage());
    }
}

// ── DATOS ─────────────────────────────────────────────────────────────────────
$servicios_activos   = $pdo->query("
    SELECT s.*,
           GROUP_CONCAT(st.tipo_equipo ORDER BY st.tipo_equipo SEPARATOR ', ') AS tipos_equipo,
           (SELECT COUNT(*) FROM detalle_orden d WHERE d.id_servicio = s.id_servicio) AS veces_usado
    FROM servicios s
    LEFT JOIN servicio_tipos st ON st.id_servicio = s.id_servicio
    WHERE s.estado = 1
    GROUP BY s.id_servicio
    ORDER BY s.nombre ASC
")->fetchAll();

$servicios_inactivos = $pdo->query("
    SELECT s.*,
           GROUP_CONCAT(st.tipo_equipo ORDER BY st.tipo_equipo SEPARATOR ', ') AS tipos_equipo,
           (SELECT COUNT(*) FROM detalle_orden d WHERE d.id_servicio = s.id_servicio) AS veces_usado
    FROM servicios s
    LEFT JOIN servicio_tipos st ON st.id_servicio = s.id_servicio
    WHERE s.estado = 0
    GROUP BY s.id_servicio
    ORDER BY s.nombre ASC
")->fetchAll();

$total_activos   = count($servicios_activos);
$total_inactivos = count($servicios_inactivos);
$precio_promedio = $total_activos > 0
    ? array_sum(array_column($servicios_activos, 'precio_base')) / $total_activos
    : 0;

$iconos_tipo = [
    'Laptop'    => 'fas fa-laptop',
    'PC'        => 'fas fa-desktop',
    'All-in-One'=> 'fas fa-tv',
    'Tablet'    => 'fas fa-tablet-alt',
    'Impresora' => 'fas fa-print',
    'Monitor'   => 'fas fa-desktop',
    'Otro'      => 'fas fa-tools',
];

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>

<link rel="stylesheet" href="css/catalogo_servicios.css?v=<?= time() ?>">

<div class="content-wrapper">

    <!-- ── CABECERA ── -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-srv d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4><i class="fas fa-tools mr-2"></i>Catálogo de Servicios</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones CH Computer &rsaquo; Servicio Técnico &rsaquo; Catálogo</small>
                </div>
                <div class="d-flex" style="gap:8px;flex-wrap:wrap;">
                    <button class="btn btn-light font-weight-bold" data-toggle="modal" data-target="#modalFiltroEquipo">
                        <i class="fas fa-filter mr-1"></i> Ver por Equipo
                    </button>
                    <button class="btn btn-light font-weight-bold" data-toggle="modal" data-target="#modalCrearServicio">
                        <i class="fas fa-plus-circle mr-1"></i> Nuevo Servicio
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- ── STATS ── -->
            <div class="row mb-4">
                <div class="col-6 col-md-3 mb-2">
                    <div class="srv-stat" style="background:linear-gradient(135deg,#1a3a6b,#2563eb);">
                        <i class="fas fa-tools"></i>
                        <div>
                            <div class="srv-stat-val"><?= $total_activos ?></div>
                            <div class="srv-stat-lbl">Servicios activos</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="srv-stat" style="background:linear-gradient(135deg,#7c3aed,#a855f7);">
                        <i class="fas fa-ban"></i>
                        <div>
                            <div class="srv-stat-val"><?= $total_inactivos ?></div>
                            <div class="srv-stat-lbl">Inactivos</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="srv-stat" style="background:linear-gradient(135deg,#065f46,#10b981);">
                        <i class="fas fa-tag"></i>
                        <div>
                            <div class="srv-stat-val">S/. <?= number_format($precio_promedio, 2) ?></div>
                            <div class="srv-stat-lbl">Precio promedio</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="srv-stat" style="background:linear-gradient(135deg,#92400e,#f59e0b);">
                        <i class="fas fa-clipboard-list"></i>
                        <div>
                            <div class="srv-stat-val"><?= $total_activos + $total_inactivos ?></div>
                            <div class="srv-stat-lbl">Total registrados</div>
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
                    confirmButtonColor: '#2563eb',
                    timer: <?= in_array($swal['icon'],['success','info']) ? 3000 : 0 ?>,
                    timerProgressBar: <?= in_array($swal['icon'],['success','info']) ? 'true' : 'false' ?>,
                    showConfirmButton: <?= in_array($swal['icon'],['success','info']) ? 'false' : 'true' ?>,
                });
            });
            </script>
            <?php endif; ?>

            <!-- ── CARD PRINCIPAL ── -->
            <div class="card shadow-sm">
                <div class="srv-card-header d-flex align-items-center justify-content-between flex-wrap" style="gap:8px;">
                    <h6 class="m-0 text-white"><i class="fas fa-list mr-2"></i>Servicios Registrados</h6>
                    <div class="d-flex align-items-center" style="gap:8px;">
                        <button type="button" id="btnExportarSrv"
                            style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.4);border-radius:6px;font-size:.8rem;font-weight:600;padding:4px 12px;cursor:pointer;transition:background .2s;">
                            <i class="fas fa-download mr-1"></i>Exportar
                        </button>
                        <ul class="nav nav-tabs card-header-tabs" id="srvTab" role="tablist" style="border-bottom:none;">
                            <li class="nav-item">
                                <a class="nav-link active text-white font-weight-bold" data-toggle="tab" href="#tab-activos-srv" role="tab">
                                    <i class="fas fa-check-circle mr-1"></i>Activos
                                    <span class="badge badge-light ml-1"><?= $total_activos ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-white font-weight-bold" data-toggle="tab" href="#tab-inactivos-srv" role="tab">
                                    <i class="fas fa-archive mr-1"></i>Inactivos
                                    <span class="badge badge-light ml-1"><?= $total_inactivos ?></span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="card-body">
                    <div class="tab-content">

                        <!-- TAB ACTIVOS -->
                        <div class="tab-pane fade show active" id="tab-activos-srv">
                            <div class="table-responsive">
                                <table id="tablaServicios" class="table table-srv table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Servicio</th>
                                            <th>Tipo</th>
                                            <th>Equipos Compatibles</th>
                                            <th class="text-right">Precio Base</th>
                                            <th class="text-center">Usado en Órdenes</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php $n = 1; foreach ($servicios_activos as $s): ?>
                                        <tr>
                                            <td class="text-center"><span class="srv-num"><?= $n++ ?></span></td>
                                            <td>
                                                <div class="srv-nombre"><?= htmlspecialchars($s['nombre']) ?></div>
                                                <?php if ($s['descripcion']): ?>
                                                <small class="text-muted"><?= htmlspecialchars(mb_substr($s['descripcion'], 0, 60)) ?><?= mb_strlen($s['descripcion']) > 60 ? '...' : '' ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="srv-badge-tipo srv-tipo-<?= $s['tipo'] ?>">
                                                    <?= $s['tipo'] === 'catalogo' ? '<i class="fas fa-book mr-1"></i>Catálogo' : '<i class="fas fa-wrench mr-1"></i>Personalizado' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($s['tipos_equipo']): ?>
                                                    <?php foreach (explode(', ', $s['tipos_equipo']) as $te): ?>
                                                    <span class="srv-badge-equipo"><?= htmlspecialchars($te) ?></span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size:.8rem;">Todos</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-right">
                                                <span class="srv-precio">S/. <?= number_format($s['precio_base'], 2) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-<?= $s['veces_usado'] > 0 ? 'primary' : 'secondary' ?>">
                                                    <?= $s['veces_usado'] ?> <?= $s['veces_usado'] == 1 ? 'vez' : 'veces' ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-info btn-ver-servicio" title="Ver detalle"
                                                    data-id="<?= $s['id_servicio'] ?>"
                                                    data-nombre="<?= htmlspecialchars($s['nombre'], ENT_QUOTES) ?>"
                                                    data-tipo="<?= $s['tipo'] ?>"
                                                    data-descripcion="<?= htmlspecialchars($s['descripcion'] ?? '', ENT_QUOTES) ?>"
                                                    data-precio="<?= number_format($s['precio_base'], 2) ?>"
                                                    data-tipos-equipo="<?= htmlspecialchars($s['tipos_equipo'] ?? '', ENT_QUOTES) ?>"
                                                    data-usado="<?= $s['veces_usado'] ?>"
                                                    data-estado="1">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning btn-editar-servicio" title="Editar"
                                                    data-id="<?= $s['id_servicio'] ?>"
                                                    data-nombre="<?= htmlspecialchars($s['nombre'], ENT_QUOTES) ?>"
                                                    data-tipo="<?= $s['tipo'] ?>"
                                                    data-descripcion="<?= htmlspecialchars($s['descripcion'] ?? '', ENT_QUOTES) ?>"
                                                    data-precio="<?= $s['precio_base'] ?>"
                                                    data-tipos-equipo="<?= htmlspecialchars($s['tipos_equipo'] ?? '', ENT_QUOTES) ?>"
                                                    data-estado="1">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-secondary btn-desactivar-servicio" title="Desactivar"
                                                    data-id="<?= $s['id_servicio'] ?>"
                                                    data-nombre="<?= htmlspecialchars($s['nombre'], ENT_QUOTES) ?>">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($servicios_activos)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-tools fa-2x mb-2 d-block" style="opacity:.3;"></i>
                                        No hay servicios activos. Crea el primero.
                                    </td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB INACTIVOS -->
                        <div class="tab-pane fade" id="tab-inactivos-srv">
                            <div class="table-responsive">
                                <table class="table table-srv table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Servicio</th>
                                            <th>Tipo</th>
                                            <th class="text-right">Precio Base</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php $n = 1; foreach ($servicios_inactivos as $s): ?>
                                        <tr class="table-light text-muted">
                                            <td class="text-center"><span class="srv-num"><?= $n++ ?></span></td>
                                            <td>
                                                <div class="srv-nombre" style="opacity:.7;"><?= htmlspecialchars($s['nombre']) ?></div>
                                                <?php if ($s['descripcion']): ?>
                                                <small class="text-muted"><?= htmlspecialchars(mb_substr($s['descripcion'], 0, 60)) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="srv-badge-tipo srv-tipo-<?= $s['tipo'] ?>" style="opacity:.7;">
                                                    <?= $s['tipo'] === 'catalogo' ? 'Catálogo' : 'Personalizado' ?>
                                                </span>
                                            </td>
                                            <td class="text-right">
                                                <span class="srv-precio" style="opacity:.7;">S/. <?= number_format($s['precio_base'], 2) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-success btn-reactivar-servicio" title="Reactivar"
                                                    data-id="<?= $s['id_servicio'] ?>"
                                                    data-nombre="<?= htmlspecialchars($s['nombre'], ENT_QUOTES) ?>">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger btn-eliminar-servicio" title="Eliminar"
                                                    data-id="<?= $s['id_servicio'] ?>"
                                                    data-nombre="<?= htmlspecialchars($s['nombre'], ENT_QUOTES) ?>"
                                                    data-usado="<?= $s['veces_usado'] ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($servicios_inactivos)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-3">No hay servicios inactivos.</td></tr>
                                    <?php endif; ?>
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

<?php
$tipos_json = json_encode($tipos_equipo_disponibles);

// Agrupar servicios activos por tipo de equipo para el modal filtro
$servicios_por_tipo = [];
foreach ($tipos_equipo_disponibles as $te) {
    $servicios_por_tipo[$te] = [];
}
// Servicios que aplican a TODOS (sin tipo asignado)
$servicios_todos = [];
foreach ($servicios_activos as $s) {
    if (empty($s['tipos_equipo'])) {
        $servicios_todos[] = $s;
    } else {
        foreach (explode(', ', $s['tipos_equipo']) as $te) {
            $te = trim($te);
            if (isset($servicios_por_tipo[$te])) {
                $servicios_por_tipo[$te][] = $s;
            }
        }
    }
}
// Agregar "todos" a cada tipo
foreach ($tipos_equipo_disponibles as $te) {
    foreach ($servicios_todos as $s) {
        $servicios_por_tipo[$te][] = $s;
    }
}
?>

<!-- ══════════════════════════════════════════════════════════
     MODAL: CREAR SERVICIO
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCrearServicio" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="max-width:700px;">
        <div class="modal-content srv-modal">

            <!-- Header con ícono grande -->
            <div class="srv-modal-header-nuevo">
                <div class="srv-modal-header-icon">
                    <i class="fas fa-tools"></i>
                </div>
                <div>
                    <h5 class="modal-title mb-0">Nuevo Servicio</h5>
                    <small style="opacity:.8;font-size:.78rem;">Completa los datos del servicio técnico</small>
                </div>
                <button type="button" class="close text-white ml-auto" data-dismiss="modal" style="font-size:1.3rem;"><span>&times;</span></button>
            </div>

            <form method="POST" id="formCrearServicio">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body p-4">

                    <!-- Sección: Información básica -->
                    <div class="srv-form-section">
                        <div class="srv-form-section-title">
                            <i class="fas fa-info-circle"></i> Información del Servicio
                        </div>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="srv-label">Nombre del Servicio <span class="text-danger">*</span></label>
                                <div class="srv-input-group">
                                    <span class="srv-ig-icon"><i class="fas fa-tools"></i></span>
                                    <input type="text" name="nombre" class="form-control form-control-sm"
                                           placeholder="Ej: Formateo completo, Cambio de pasta térmica, Instalación de Windows..."
                                           required maxlength="100">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="srv-label">Precio Base (S/.) <span class="text-danger">*</span></label>
                                <div class="srv-input-group">
                                    <span class="srv-ig-icon" style="background:#065f46;"><i class="fas fa-tag"></i></span>
                                    <input type="number" name="precio_base" class="form-control form-control-sm"
                                           placeholder="0.00" min="0" step="0.50" required
                                           style="font-size:1rem;font-weight:700;color:#065f46;">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="srv-label">Tipo de Servicio</label>
                                <div class="srv-tipo-selector">
                                    <label class="srv-tipo-radio-label">
                                        <input type="radio" name="tipo" value="catalogo" checked>
                                        <span class="srv-tipo-radio-btn">
                                            <i class="fas fa-book"></i>
                                            Catálogo
                                            <small>Precio fijo</small>
                                        </span>
                                    </label>
                                    <label class="srv-tipo-radio-label">
                                        <input type="radio" name="tipo" value="personalizado">
                                        <span class="srv-tipo-radio-btn">
                                            <i class="fas fa-wrench"></i>
                                            Personalizado
                                            <small>Precio variable</small>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-12 mb-1">
                                <label class="srv-label">Descripción <span class="text-muted font-weight-normal" style="font-size:.75rem;">(opcional)</span></label>
                                <textarea name="descripcion" class="form-control form-control-sm" rows="2"
                                    placeholder="Describe brevemente en qué consiste el servicio..." maxlength="255"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Sección: Equipos compatibles -->
                    <div class="srv-form-section mt-3">
                        <div class="srv-form-section-title">
                            <i class="fas fa-laptop"></i> Equipos Compatibles
                            <span class="text-muted font-weight-normal" style="font-size:.75rem;margin-left:6px;">— dejar vacío = aplica a todos</span>
                        </div>
                        <div class="srv-tipos-grid" id="tiposCrear">
                            <?php foreach ($tipos_equipo_disponibles as $te):
                                $icono_te = $iconos_tipo[$te] ?? 'fas fa-tools';
                            ?>
                            <label class="srv-tipo-chip">
                                <input type="checkbox" name="tipos_equipo[]" value="<?= $te ?>">
                                <i class="<?= $icono_te ?>"></i>
                                <span><?= $te ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
                <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:12px 20px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-srv-primary btn-sm px-4">
                        <i class="fas fa-save mr-1"></i>Guardar Servicio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: EDITAR SERVICIO
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditarServicio" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="max-width:680px;">
        <div class="modal-content srv-modal">
            <div class="modal-header srv-modal-header-edit">
                <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Editar Servicio</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" id="formEditarServicio">
                <input type="hidden" name="accion" value="actualizar">
                <input type="hidden" name="id_servicio" id="edit_id_servicio">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="srv-label">Nombre del Servicio <span class="text-danger">*</span></label>
                            <div class="srv-input-group">
                                <span class="srv-ig-icon"><i class="fas fa-tools"></i></span>
                                <input type="text" name="nombre" id="edit_nombre" class="form-control form-control-sm" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="srv-label">Tipo</label>
                            <div class="srv-input-group">
                                <span class="srv-ig-icon"><i class="fas fa-layer-group"></i></span>
                                <select name="tipo" id="edit_tipo" class="form-control form-control-sm">
                                    <option value="catalogo">Catálogo</option>
                                    <option value="personalizado">Personalizado</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="srv-label">Precio Base (S/.)</label>
                            <div class="srv-input-group">
                                <span class="srv-ig-icon"><i class="fas fa-tag"></i></span>
                                <input type="number" name="precio_base" id="edit_precio" class="form-control form-control-sm" min="0" step="0.50">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="srv-label">Estado</label>
                            <div class="srv-input-group">
                                <span class="srv-ig-icon"><i class="fas fa-toggle-on"></i></span>
                                <select name="estado" id="edit_estado" class="form-control form-control-sm">
                                    <option value="1">✅ Activo</option>
                                    <option value="0">❌ Inactivo</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="srv-label">Descripción</label>
                            <textarea name="descripcion" id="edit_descripcion" class="form-control form-control-sm" rows="2" maxlength="255"></textarea>
                        </div>
                        <div class="col-12 mb-2">
                            <label class="srv-label">
                                <i class="fas fa-laptop mr-1"></i>Equipos Compatibles
                            </label>
                            <div class="srv-tipos-grid" id="tiposEditar">
                                <?php foreach ($tipos_equipo_disponibles as $te): ?>
                                <label class="srv-tipo-chip">
                                    <input type="checkbox" name="tipos_equipo[]" value="<?= $te ?>" class="edit-tipo-check">
                                    <span><?= $te ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-srv-edit btn-sm">
                        <i class="fas fa-save mr-1"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: VER DETALLE
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalVerServicio" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document" style="max-width:500px;">
        <div class="modal-content" style="border:none;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.2);">

            <!-- Header con precio destacado -->
            <div class="srv-ver-header">
                <div style="position:absolute;top:-20px;right:-20px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.06);pointer-events:none;"></div>
                <div style="position:absolute;bottom:-30px;left:30px;width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,.04);pointer-events:none;"></div>
                <button type="button" data-dismiss="modal"
                    style="position:absolute;top:12px;right:14px;background:rgba(255,255,255,.15);border:none;color:#fff;width:30px;height:30px;border-radius:50%;font-size:.85rem;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:3;">
                    <i class="fas fa-times"></i>
                </button>
                <div style="display:flex;align-items:center;gap:14px;position:relative;z-index:2;">
                    <div class="srv-ver-icono">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:.68rem;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:1.5px;font-weight:700;margin-bottom:4px;">
                            Servicio Técnico
                        </div>
                        <div class="srv-ver-nombre" id="ver_nombre"></div>
                        <div class="mt-2 d-flex" style="gap:6px;flex-wrap:wrap;">
                            <span id="ver_badge_tipo"></span>
                            <span id="ver_badge_estado"></span>
                        </div>
                    </div>
                    <div class="srv-ver-precio-badge">
                        <div style="font-size:.65rem;opacity:.8;text-transform:uppercase;letter-spacing:.5px;">Precio</div>
                        <div id="ver_precio" style="font-size:1.3rem;font-weight:800;line-height:1.1;"></div>
                    </div>
                </div>
            </div>

            <!-- Body -->
            <div style="padding:20px;background:#f4f7fb;">

                <!-- Equipos compatibles -->
                <div class="srv-ver-card mb-3">
                    <div class="srv-ver-card-title">
                        <i class="fas fa-laptop mr-1"></i>Equipos Compatibles
                    </div>
                    <div id="ver_tipos" style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;"></div>
                </div>

                <!-- Stats fila -->
                <div class="row mb-3" style="margin:0 -5px;">
                    <div class="col-6" style="padding:0 5px;">
                        <div class="srv-ver-stat">
                            <i class="fas fa-clipboard-list" style="color:#2563eb;font-size:1.2rem;"></i>
                            <div>
                                <div class="srv-ver-stat-val" id="ver_usado">0</div>
                                <div class="srv-ver-stat-lbl">Usado en órdenes</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6" style="padding:0 5px;">
                        <div class="srv-ver-stat">
                            <i class="fas fa-layer-group" style="color:#7c3aed;font-size:1.2rem;"></i>
                            <div>
                                <div class="srv-ver-stat-val" id="ver_tipo_texto" style="font-size:.85rem;"></div>
                                <div class="srv-ver-stat-lbl">Tipo de servicio</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Descripción -->
                <div id="ver_desc_wrap">
                    <div class="srv-ver-card">
                        <div class="srv-ver-card-title">
                            <i class="fas fa-align-left mr-1"></i>Descripción
                        </div>
                        <div class="srv-descripcion-box mt-2" id="ver_descripcion"></div>
                    </div>
                </div>

            </div>

            <div style="padding:12px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: FILTRO POR TIPO DE EQUIPO
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalFiltroEquipo" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="max-width:720px;">
        <div class="modal-content srv-modal">
            <div class="modal-header srv-modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-filter mr-2"></i>Servicios por Tipo de Equipo
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0">

                <!-- Chips de tipos de equipo -->
                <div class="filtro-equipo-chips">
                    <?php
                    foreach ($tipos_equipo_disponibles as $i => $te):
                        $total_te = count($servicios_por_tipo[$te] ?? []);
                        $icono    = $iconos_tipo[$te] ?? 'fas fa-tools';
                    ?>
                    <button class="filtro-equipo-chip <?= $i === 0 ? 'activo' : '' ?>"
                            data-tipo="<?= htmlspecialchars($te) ?>">
                        <i class="<?= $icono ?> mr-1"></i>
                        <?= htmlspecialchars($te) ?>
                        <span class="filtro-chip-count"><?= $total_te ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- Título del tipo seleccionado -->
                <div class="filtro-equipo-titulo">
                    <i id="filtroIconoActivo" class="fas fa-laptop mr-2"></i>
                    Servicios para <strong id="filtroTipoActivo"><?= $tipos_equipo_disponibles[0] ?></strong>
                    <span class="filtro-total-badge" id="filtroTotalActivo">0</span>
                </div>

                <!-- Lista de servicios -->
                <div class="filtro-servicios-lista" id="filtroListaServicios">
                    <?php foreach ($tipos_equipo_disponibles as $te):
                        $lista = $servicios_por_tipo[$te] ?? [];
                    ?>
                    <div class="filtro-tipo-panel" data-panel="<?= htmlspecialchars($te) ?>"
                         style="<?= $te !== $tipos_equipo_disponibles[0] ? 'display:none;' : '' ?>">
                        <?php if (empty($lista)): ?>
                        <div class="filtro-vacio">
                            <i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>
                            No hay servicios registrados para este tipo de equipo.
                        </div>
                        <?php else: ?>
                        <div class="filtro-grid">
                            <?php foreach ($lista as $s): ?>
                            <div class="filtro-srv-card">
                                <div class="filtro-srv-header">
                                    <div class="filtro-srv-nombre"><?= htmlspecialchars($s['nombre']) ?></div>
                                    <span class="filtro-srv-precio">S/. <?= number_format($s['precio_base'], 2) ?></span>
                                </div>
                                <?php if ($s['descripcion']): ?>
                                <div class="filtro-srv-desc"><?= htmlspecialchars(mb_substr($s['descripcion'], 0, 80)) ?><?= mb_strlen($s['descripcion']) > 80 ? '...' : '' ?></div>
                                <?php endif; ?>
                                <div class="filtro-srv-footer">
                                    <span class="srv-badge-tipo srv-tipo-<?= $s['tipo'] ?>" style="font-size:.68rem;">
                                        <?= $s['tipo'] === 'catalogo' ? 'Catálogo' : 'Personalizado' ?>
                                    </span>
                                    <?php if (empty($s['tipos_equipo'])): ?>
                                    <span style="font-size:.68rem;color:#64748b;"><i class="fas fa-globe mr-1"></i>Todos los equipos</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

            </div>
            <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:10px 18px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══ MODAL EXPORTAR CATÁLOGO DE SERVICIOS ══ -->
<div class="modal fade" id="modalExportarSrv" tabindex="-1" aria-hidden="true">
<div class="modal-dialog" style="max-width:440px;">
<div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a3a6b,#2563eb);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
    <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-download mr-2"></i>Exportar Catálogo de Servicios</h6>
    <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-4">

    <!-- Filtro estado -->
    <div class="form-group mb-3">
        <label style="font-weight:600;font-size:.83rem;color:#495057;"><i class="fas fa-circle mr-1 text-muted"></i>Estado</label>
        <select id="srv_exp_estado" class="form-control form-control-sm">
            <option value="all">Todos (activos e inactivos)</option>
            <option value="activo">Solo activos</option>
            <option value="inactivo">Solo inactivos</option>
        </select>
    </div>

    <!-- Filtro tipo de servicio -->
    <div class="form-group mb-3">
        <label style="font-weight:600;font-size:.83rem;color:#495057;"><i class="fas fa-tag mr-1 text-muted"></i>Tipo de Servicio</label>
        <select id="srv_exp_tipo" class="form-control form-control-sm">
            <option value="all">Todos los tipos</option>
            <option value="catalogo">Catálogo (precio fijo)</option>
            <option value="personalizado">Personalizado (precio variable)</option>
        </select>
    </div>

    <!-- Filtro tipo de equipo -->
    <div class="form-group mb-3">
        <label style="font-weight:600;font-size:.83rem;color:#495057;"><i class="fas fa-laptop mr-1 text-muted"></i>Tipo de Equipo</label>
        <select id="srv_exp_equipo" class="form-control form-control-sm">
            <option value="all">Todos los equipos</option>
            <option value="Laptop">Laptop</option>
            <option value="PC">PC</option>
            <option value="All-in-One">All-in-One</option>
            <option value="Tablet">Tablet</option>
            <option value="Impresora">Impresora</option>
            <option value="Monitor">Monitor</option>
            <option value="Otro">Otro</option>
        </select>
    </div>

    <!-- Botones de formato -->
    <div style="background:#f8f9fa;border-radius:10px;padding:14px;border:1px solid #e9ecef;">
        <p style="font-weight:600;font-size:.82rem;color:#495057;margin-bottom:10px;"><i class="fas fa-file-export mr-1"></i>Selecciona el formato:</p>
        <div class="d-flex" style="gap:8px;">
            <button type="button" id="srv_btn_csv"
                style="flex:1;background:linear-gradient(135deg,#065f46,#10b981);color:#fff;border:none;border-radius:8px;padding:10px 8px;font-weight:700;font-size:.82rem;cursor:pointer;">
                <i class="fas fa-file-csv d-block mb-1" style="font-size:1.3rem;"></i>CSV
            </button>
            <button type="button" id="srv_btn_excel"
                style="flex:1;background:linear-gradient(135deg,#1a3a6b,#2563eb);color:#fff;border:none;border-radius:8px;padding:10px 8px;font-weight:700;font-size:.82rem;cursor:pointer;">
                <i class="fas fa-file-excel d-block mb-1" style="font-size:1.3rem;"></i>Excel
            </button>
            <button type="button" id="srv_btn_pdf"
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
$extra_js = '<script src="js/catalogo_servicios.js?v=' . time() . '"></script>';
include $ruta_base . 'includes/footer.php';
?>