<?php
// ============================================================
// modules/personas/clientes.php | Botica 2026
// Campos BD: id_cliente, nombres, apellido_paterno,
//            apellido_materno, tipo_documento, dni,
//            telefono, email, direccion, estado_cliente
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
verificarPermiso($pdo, 'clientes');

// ── Patrón PRG: leer alertas de sesión ───────────────────────────────────────
$swal = null;
if (isset($_SESSION['swal'])) {
    $swal = $_SESSION['swal'];
    unset($_SESSION['swal']);
}

// ── Helper: guardar alerta en sesión y redirigir ──────────────────────────────
function redirigir(string $icon, string $title, string $text): void {
    $_SESSION['swal'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: clientes.php');
    exit;
}

// ── CRUD ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion     = $_POST['accion']     ?? '';
    $id_cliente = (int)($_POST['id_cliente'] ?? 0);

    // Sanitizar entradas
    $nombres          = strtoupper(trim($_POST['nombres']          ?? ''));
    $apellido_paterno = strtoupper(trim($_POST['apellido_paterno'] ?? ''));
    $apellido_materno = strtoupper(trim($_POST['apellido_materno'] ?? ''));
    $tipo_documento   = trim($_POST['tipo_documento'] ?? 'DNI');
    $estado_cliente   = isset($_POST['estado_cliente']) ? (int)$_POST['estado_cliente'] : 1;
    $dni              = trim($_POST['dni']       ?? '');
    $telefono         = trim($_POST['telefono']  ?? '');
    $email            = trim($_POST['email']     ?? '') ?: null;
    $direccion        = trim($_POST['direccion'] ?? '');

    try {
        if ($accion === 'crear') {
            if (empty($nombres) || empty($apellido_paterno) || empty($dni)) {
                redirigir('warning', 'Campos incompletos', 'Nombres, apellido paterno y DNI son obligatorios.');
            }
            $sql = "INSERT INTO clientes
                        (nombres, apellido_paterno, apellido_materno, tipo_documento,
                         dni, telefono, email, direccion, estado_cliente)
                    VALUES (?,?,?,?,?,?,?,?,1)";
            $pdo->prepare($sql)->execute([
                $nombres, $apellido_paterno, $apellido_materno,
                $tipo_documento, $dni, $telefono, $email, $direccion
            ]);
            redirigir('success', '¡Registrado!', "El cliente $nombres $apellido_paterno fue registrado correctamente.");

        } elseif ($accion === 'actualizar' && $id_cliente > 0) {
            $sql = "UPDATE clientes
                    SET nombres=?, apellido_paterno=?, apellido_materno=?,
                        tipo_documento=?, dni=?, telefono=?, email=?, direccion=?,
                        estado_cliente=?
                    WHERE id_cliente=?";
            $pdo->prepare($sql)->execute([
                $nombres, $apellido_paterno, $apellido_materno,
                $tipo_documento, $dni, $telefono, $email, $direccion,
                $estado_cliente, $id_cliente
            ]);
            redirigir('success', '¡Actualizado!', "Los datos del cliente fueron actualizados correctamente.");

        } elseif ($accion === 'desactivar' && $id_cliente > 0) {
            $pdo->prepare("UPDATE clientes SET estado_cliente=0 WHERE id_cliente=?")
                ->execute([$id_cliente]);
            redirigir('info', 'Cliente desactivado', "El cliente fue desactivado y movido a la lista de inactivos.");

        } elseif ($accion === 'reactivar' && $id_cliente > 0) {
            $pdo->prepare("UPDATE clientes SET estado_cliente=1 WHERE id_cliente=?")
                ->execute([$id_cliente]);
            redirigir('success', '¡Reactivado!', "El cliente fue reactivado y volvió a la lista de activos.");

        } elseif ($accion === 'eliminar_permanente' && $id_cliente > 0) {
            $tiene_ventas = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE id_cliente=?");
            $tiene_ventas->execute([$id_cliente]);
            if ($tiene_ventas->fetchColumn() > 0) {
                redirigir('error', 'No se puede eliminar', "Este cliente tiene ventas registradas. Use Desactivar en su lugar.");
            }
            $pdo->prepare("DELETE FROM clientes WHERE id_cliente=?")->execute([$id_cliente]);
            redirigir('success', 'Eliminado', "El cliente fue eliminado permanentemente de la base de datos.");
        }

    } catch (PDOException $e) {
        $msg = ($e->getCode() == 23000)
            ? 'El DNI o correo ya está registrado en el sistema.'
            : 'Error de base de datos: ' . $e->getMessage();
        redirigir('error', 'Error', $msg);
    }
}

// ── DATOS ────────────────────────────────────────────────────────────────────
$clientes_activos   = [];
$clientes_inactivos = [];
$total_activos      = 0;
$total_inactivos    = 0;

try {
    $clientes_activos   = $pdo->query("SELECT * FROM clientes WHERE estado_cliente=1 ORDER BY apellido_paterno,nombres")->fetchAll();
    $clientes_inactivos = $pdo->query("SELECT * FROM clientes WHERE estado_cliente=0 ORDER BY apellido_paterno,nombres")->fetchAll();
    $total_activos      = count($clientes_activos);
    $total_inactivos    = count($clientes_inactivos);
} catch (PDOException $e) {
    $error = 'Error al cargar clientes: ' . $e->getMessage();
}

$tipos_doc = ['DNI', 'RUC', 'CE', 'Pasaporte'];

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>

<!-- CSS del módulo -->
<link rel="stylesheet" href="css/clientes.css">

<div class="content-wrapper">

    <!-- ── CABECERA ── -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-botica d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4><i class="fas fa-users mr-2"></i>Gestión de Clientes</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>Botica 2026 &rsaquo; Personas &rsaquo; Clientes</small>
                </div>
                <button class="btn btn-light font-weight-bold" data-toggle="modal" data-target="#modalCrearCliente">
                    <i class="fas fa-user-plus mr-1"></i> Nuevo Cliente
                </button>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- ── STATS ── -->
            <div class="row mb-4">
                <div class="col-md-4 col-6 mb-2">
                    <div class="stat-mini" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);">
                        <i class="fas fa-users"></i>
                        <div>
                            <div class="stat-value"><?= $total_activos ?></div>
                            <div class="stat-label">Clientes activos</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6 mb-2">
                    <div class="stat-mini" style="background:linear-gradient(135deg,#922b21,#e74c3c);">
                        <i class="fas fa-user-slash"></i>
                        <div>
                            <div class="stat-value"><?= $total_inactivos ?></div>
                            <div class="stat-label">Clientes inactivos</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6 mb-2">
                    <div class="stat-mini" style="background:linear-gradient(135deg,#1a5276,#2980b9);">
                        <i class="fas fa-id-card"></i>
                        <div>
                            <div class="stat-value"><?= $total_activos + $total_inactivos ?></div>
                            <div class="stat-label">Total registrados</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── ALERTAS SweetAlert2 desde sesión ── -->
            <?php if ($swal): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon:              '<?= $swal['icon'] ?>',
                    title:             '<?= addslashes($swal['title']) ?>',
                    text:              '<?= addslashes($swal['text']) ?>',
                    confirmButtonColor:'#1a7a4a',
                    timer:             <?= in_array($swal['icon'], ['success','info']) ? 3000 : 0 ?>,
                    timerProgressBar:  <?= in_array($swal['icon'], ['success','info']) ? 'true' : 'false' ?>,
                    showConfirmButton:  <?= in_array($swal['icon'], ['success','info']) ? 'false' : 'true' ?>,
                });
            });
            </script>
            <?php endif; ?>

            <!-- ── CARD PRINCIPAL ── -->
            <div class="card">
                <div class="card-header-botica d-flex align-items-center justify-content-between">
                    <h6><i class="fas fa-list mr-2"></i>Listado de Clientes</h6>
                    <ul class="nav nav-tabs-botica mb-0" id="tabClientes" role="tablist" style="border:none;">
                        <li class="nav-item">
                            <a class="nav-link active text-white" id="tab-activos-link" data-toggle="pill"
                               href="#tab-activos" role="tab">
                                <i class="fas fa-user-check mr-1"></i>Activos
                                <span class="badge badge-light ml-1"><?= $total_activos ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" id="tab-inactivos-link" data-toggle="pill"
                               href="#tab-inactivos" role="tab">
                                <i class="fas fa-user-slash mr-1"></i>Inactivos
                                <span class="badge badge-light ml-1"><?= $total_inactivos ?></span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="card-body">
                    <div class="tab-content">

                        <!-- ACTIVOS -->
                        <div class="tab-pane fade show active" id="tab-activos" role="tabpanel">
                            <div class="table-responsive">
                                <table id="tablaClientesActivos" class="table table-clientes table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Apellidos y Nombres</th>
                                            <th>Tipo Doc.</th>
                                            <th>DNI / Doc.</th>
                                            <th>Teléfono</th>
                                            <th>Email</th>
                                            <th>Dirección</th>
                                            <th>Registro</th>
                                            <th class="text-center" style="width:130px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($clientes_activos as $c): ?>
                                        <tr>
                                            <td><?= $c['id_cliente'] ?></td>
                                            <td class="font-weight-bold">
                                                <?= htmlspecialchars($c['apellido_paterno'] . ' ' . ($c['apellido_materno'] ?? '') . ', ' . $c['nombres']) ?>
                                            </td>
                                            <td><span class="badge badge-secondary"><?= htmlspecialchars($c['tipo_documento'] ?? 'DNI') ?></span></td>
                                            <td><span class="badge-dni"><?= htmlspecialchars($c['dni']) ?></span></td>
                                            <td><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
                                            <td><?= htmlspecialchars($c['email'] ?? '—') ?></td>
                                            <td style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                                                title="<?= htmlspecialchars($c['direccion'] ?? '') ?>">
                                                <?= htmlspecialchars($c['direccion'] ?? '—') ?>
                                            </td>
                                            <td><small class="text-muted"><?= date('d/m/Y', strtotime($c['fecha_registro'])) ?></small></td>
                                            <td class="text-center">
                                                <!-- VER -->
                                                <button class="btn btn-sm btn-info btn-ver-cliente"
                                                    title="Ver detalle"
                                                    data-id="<?= $c['id_cliente'] ?>"
                                                    data-nombres="<?= htmlspecialchars($c['nombres'], ENT_QUOTES) ?>"
                                                    data-apellido-paterno="<?= htmlspecialchars($c['apellido_paterno'], ENT_QUOTES) ?>"
                                                    data-apellido-materno="<?= htmlspecialchars($c['apellido_materno'] ?? '', ENT_QUOTES) ?>"
                                                    data-tipo-documento="<?= htmlspecialchars($c['tipo_documento'] ?? 'DNI', ENT_QUOTES) ?>"
                                                    data-dni="<?= htmlspecialchars($c['dni'], ENT_QUOTES) ?>"
                                                    data-telefono="<?= htmlspecialchars($c['telefono'] ?? '', ENT_QUOTES) ?>"
                                                    data-email="<?= htmlspecialchars($c['email'] ?? '', ENT_QUOTES) ?>"
                                                    data-direccion="<?= htmlspecialchars($c['direccion'] ?? '', ENT_QUOTES) ?>"
                                                    data-estado="<?= $c['estado_cliente'] ?>"
                                                    data-fecha="<?= date('d/m/Y H:i', strtotime($c['fecha_registro'])) ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <!-- EDITAR -->
                                                <button class="btn btn-sm btn-warning btn-editar-cliente"
                                                    title="Editar"
                                                    data-id="<?= $c['id_cliente'] ?>"
                                                    data-nombres="<?= htmlspecialchars($c['nombres'], ENT_QUOTES) ?>"
                                                    data-apellido-paterno="<?= htmlspecialchars($c['apellido_paterno'], ENT_QUOTES) ?>"
                                                    data-apellido-materno="<?= htmlspecialchars($c['apellido_materno'] ?? '', ENT_QUOTES) ?>"
                                                    data-tipo-documento="<?= htmlspecialchars($c['tipo_documento'] ?? 'DNI', ENT_QUOTES) ?>"
                                                    data-dni="<?= htmlspecialchars($c['dni'], ENT_QUOTES) ?>"
                                                    data-telefono="<?= htmlspecialchars($c['telefono'] ?? '', ENT_QUOTES) ?>"
                                                    data-email="<?= htmlspecialchars($c['email'] ?? '', ENT_QUOTES) ?>"
                                                    data-direccion="<?= htmlspecialchars($c['direccion'] ?? '', ENT_QUOTES) ?>"
                                                    data-estado="<?= $c['estado_cliente'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <!-- ELIMINAR (solo si no es cliente general) -->
                                                <?php if ($c['id_cliente'] != 1): ?>
                                                <button class="btn btn-sm btn-danger btn-eliminar-cliente"
                                                    title="Eliminar"
                                                    data-id="<?= $c['id_cliente'] ?>"
                                                    data-nombre="<?= htmlspecialchars($c['nombres'] . ' ' . $c['apellido_paterno'], ENT_QUOTES) ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- INACTIVOS -->
                        <!-- INACTIVOS -->
                        <div class="tab-pane fade" id="tab-inactivos" role="tabpanel">
                            <div class="table-responsive">
                                <table id="tablaClientesInactivos" class="table table-clientes table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Apellidos y Nombres</th>
                                            <th>Tipo Doc.</th>
                                            <th>DNI / Doc.</th>
                                            <th>Teléfono</th>
                                            <th>Registro</th>
                                            <th class="text-center" style="width:130px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($clientes_inactivos as $c): ?>
                                        <tr class="table-light">
                                            <td><?= $c['id_cliente'] ?></td>
                                            <td class="text-muted"><?= htmlspecialchars($c['apellido_paterno'] . ' ' . ($c['apellido_materno'] ?? '') . ', ' . $c['nombres']) ?></td>
                                            <td><span class="badge badge-secondary"><?= htmlspecialchars($c['tipo_documento'] ?? 'DNI') ?></span></td>
                                            <td><span class="badge-dni"><?= htmlspecialchars($c['dni']) ?></span></td>
                                            <td><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
                                            <td><small class="text-muted"><?= date('d/m/Y', strtotime($c['fecha_registro'])) ?></small></td>
                                            <td class="text-center">
                                                <!-- REACTIVAR -->
                                                <button class="btn btn-sm btn-success btn-reactivar-cliente"
                                                    title="Reactivar cliente"
                                                    data-id="<?= $c['id_cliente'] ?>"
                                                    data-nombre="<?= htmlspecialchars($c['nombres'] . ' ' . $c['apellido_paterno'], ENT_QUOTES) ?>">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                                <!-- ELIMINAR PERMANENTE -->
                                                <button class="btn btn-sm btn-danger btn-eliminar-permanente"
                                                    title="Eliminar permanentemente"
                                                    data-id="<?= $c['id_cliente'] ?>"
                                                    data-nombre="<?= htmlspecialchars($c['nombres'] . ' ' . $c['apellido_paterno'], ENT_QUOTES) ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div><!-- /tab-content -->
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL VER CLIENTE
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalVerCliente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">

            <!-- Cabecera con avatar -->
            <div style="background:linear-gradient(135deg,#1a7a4a 0%,#27ae60 100%);padding:28px 24px 20px;text-align:center;position:relative;">
                <button type="button" class="close" data-dismiss="modal"
                    style="position:absolute;top:12px;right:16px;color:#fff;opacity:.8;font-size:1.4rem;">&times;</button>
                <div style="width:72px;height:72px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                    <i class="fas fa-user" style="font-size:2rem;color:#fff;"></i>
                </div>
                <h5 id="ver_nombre_completo" style="color:#fff;font-weight:700;margin:0;font-size:1.1rem;"></h5>
                <div class="mt-2">
                    <span id="ver_badge_tipo" class="badge" style="background:rgba(255,255,255,.25);color:#fff;font-size:.78rem;padding:4px 10px;border-radius:20px;"></span>
                    <span id="ver_badge_dni" class="badge ml-1" style="background:rgba(255,255,255,.25);color:#fff;font-size:.78rem;padding:4px 10px;border-radius:20px;font-family:monospace;"></span>
                </div>
            </div>

            <!-- Cuerpo con campos -->
            <div class="modal-body" style="padding:20px 24px;">
                <div class="row">

                    <div class="col-6 mb-3">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <div style="width:34px;height:34px;background:#e8f5e9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-id-card" style="color:#1a7a4a;font-size:.85rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Nombres</div>
                                <div id="ver_nombres" style="font-size:.9rem;font-weight:600;color:#2d3436;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 mb-3">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <div style="width:34px;height:34px;background:#e8f5e9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-user-tag" style="color:#1a7a4a;font-size:.85rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Apellidos</div>
                                <div id="ver_apellidos" style="font-size:.9rem;font-weight:600;color:#2d3436;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 mb-3">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <div style="width:34px;height:34px;background:#e3f2fd;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-phone-alt" style="color:#1a5276;font-size:.85rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Teléfono</div>
                                <div id="ver_telefono" style="font-size:.9rem;color:#2d3436;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 mb-3">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <div style="width:34px;height:34px;background:#e3f2fd;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-envelope" style="color:#1a5276;font-size:.85rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Email</div>
                                <div id="ver_email" style="font-size:.9rem;color:#2d3436;word-break:break-all;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mb-3">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <div style="width:34px;height:34px;background:#fff3e0;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-map-marker-alt" style="color:#e67e22;font-size:.85rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Dirección</div>
                                <div id="ver_direccion" style="font-size:.9rem;color:#2d3436;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 mb-1">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <div style="width:34px;height:34px;background:#f3e5f5;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-calendar-alt" style="color:#8e44ad;font-size:.85rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Fecha Registro</div>
                                <div id="ver_fecha" style="font-size:.9rem;color:#2d3436;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 mb-1">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <div style="width:34px;height:34px;background:#fce4ec;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-toggle-on" style="color:#c0392b;font-size:.85rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Estado</div>
                                <div id="ver_estado_cliente" style="font-size:.9rem;"></div>
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
     MODAL CREAR CLIENTE
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCrearCliente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-botica">
                <h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i>Registrar Nuevo Cliente</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body">

                    <!-- Tipo documento + DNI + RENIEC -->
                    <div class="row">
                        <div class="form-group col-md-3">
                            <label class="form-label-botica"><i class="fas fa-id-card mr-1 text-muted"></i>Tipo Documento</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-file-alt"></i></span></div>
                                <select class="form-control" name="tipo_documento" id="crear_tipo_documento">
                                    <?php foreach ($tipos_doc as $td): ?>
                                        <option value="<?= $td ?>"><?= $td ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group col-md-9" id="crear_dni_group">
                            <label class="form-label-botica"><i class="fas fa-fingerprint mr-1 text-muted"></i>Número de Documento <span class="text-danger">*</span></label>
                            <div class="input-group input-dni-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-id-badge"></i></span></div>
                                <input type="text" class="form-control form-control-sm" name="dni"
                                       id="crear_dni" maxlength="8" placeholder="12345678" required>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-reniec btn-sm" id="btn_reniec_crear">
                                        <i class="fas fa-search mr-1"></i> Consultar RENIEC
                                    </button>
                                </div>
                            </div>
                            <!-- Resultado RENIEC -->
                            <div class="reniec-result mt-2" id="reniec_result_crear">
                                <i class="fas fa-check-circle text-success mr-1"></i>
                                <span class="reniec-nombre"></span>
                                <small class="text-muted ml-2">— Datos cargados desde RENIEC</small>
                            </div>
                        </div>
                    </div>

                    <!-- Nombres -->
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label class="form-label-botica"><i class="fas fa-user mr-1 text-muted"></i>Nombres <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                                <input type="text" class="form-control" name="nombres"
                                       id="crear_nombres" maxlength="100" required placeholder="JUAN CARLOS">
                            </div>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="form-label-botica"><i class="fas fa-user-tag mr-1 text-muted"></i>Apellido Paterno <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user-tag"></i></span></div>
                                <input type="text" class="form-control" name="apellido_paterno"
                                       id="crear_apellido_paterno" maxlength="100" required placeholder="PÉREZ">
                            </div>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="form-label-botica"><i class="fas fa-user-tag mr-1 text-muted"></i>Apellido Materno</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user-tag"></i></span></div>
                                <input type="text" class="form-control" name="apellido_materno"
                                       id="crear_apellido_materno" maxlength="100" placeholder="GARCÍA">
                            </div>
                        </div>
                    </div>

                    <!-- Contacto -->
                    <div class="row">
                        <div class="form-group col-md-4">
                            <label class="form-label-botica"><i class="fas fa-phone-alt mr-1 text-muted"></i>Teléfono</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-phone"></i></span></div>
                                <input type="text" class="form-control" name="telefono"
                                       maxlength="20" placeholder="999 999 999">
                            </div>
                        </div>
                        <div class="form-group col-md-8">
                            <label class="form-label-botica"><i class="fas fa-envelope mr-1 text-muted"></i>Email</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-at"></i></span></div>
                                <input type="email" class="form-control" name="email"
                                       maxlength="100" placeholder="correo@ejemplo.com">
                            </div>
                        </div>
                    </div>

                    <!-- Dirección -->
                    <div class="form-group">
                        <label class="form-label-botica"><i class="fas fa-map-marker-alt mr-1 text-muted"></i>Dirección</label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-map-pin"></i></span></div>
                            <input type="text" class="form-control" name="direccion"
                                   id="crear_direccion" maxlength="150" placeholder="Av. Los Pinos 123, Lima">
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-botica-primary btn-sm">
                        <i class="fas fa-save mr-1"></i>Guardar Cliente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL EDITAR CLIENTE
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditarCliente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#a04000,#e67e22);color:#fff;border-radius:10px 10px 0 0;">
                <h5 class="modal-title"><i class="fas fa-user-edit mr-2"></i>Editar Cliente</h5>
                <button type="button" class="close" style="color:#fff;" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="actualizar">
                <input type="hidden" name="id_cliente" id="editar_id_cliente">
                <div class="modal-body">

                    <div class="row">
                        <div class="form-group col-md-3">
                            <label class="form-label-botica"><i class="fas fa-id-card mr-1 text-muted"></i>Tipo Documento</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-file-alt"></i></span></div>
                                <select class="form-control" name="tipo_documento" id="editar_tipo_documento">
                                    <?php foreach ($tipos_doc as $td): ?>
                                        <option value="<?= $td ?>"><?= $td ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group col-md-9" id="editar_dni_group">
                            <label class="form-label-botica"><i class="fas fa-fingerprint mr-1 text-muted"></i>Número de Documento <span class="text-danger">*</span></label>
                            <div class="input-group input-dni-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-id-badge"></i></span></div>
                                <input type="text" class="form-control form-control-sm" name="dni"
                                       id="editar_dni" maxlength="8" required>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-reniec btn-sm" id="btn_reniec_editar">
                                        <i class="fas fa-search mr-1"></i> Consultar RENIEC
                                    </button>
                                </div>
                            </div>
                            <div class="reniec-result mt-2" id="reniec_result_editar">
                                <i class="fas fa-check-circle text-success mr-1"></i>
                                <span class="reniec-nombre"></span>
                                <small class="text-muted ml-2">— Datos actualizados desde RENIEC</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4">
                            <label class="form-label-botica"><i class="fas fa-user mr-1 text-muted"></i>Nombres <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                                <input type="text" class="form-control" name="nombres"
                                       id="editar_nombres" maxlength="100" required>
                            </div>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="form-label-botica"><i class="fas fa-user-tag mr-1 text-muted"></i>Apellido Paterno <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user-tag"></i></span></div>
                                <input type="text" class="form-control" name="apellido_paterno"
                                       id="editar_apellido_paterno" maxlength="100" required>
                            </div>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="form-label-botica"><i class="fas fa-user-tag mr-1 text-muted"></i>Apellido Materno</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user-tag"></i></span></div>
                                <input type="text" class="form-control" name="apellido_materno"
                                       id="editar_apellido_materno" maxlength="100">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4">
                            <label class="form-label-botica"><i class="fas fa-phone-alt mr-1 text-muted"></i>Teléfono</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-phone"></i></span></div>
                                <input type="text" class="form-control" name="telefono"
                                       id="editar_telefono" maxlength="20">
                            </div>
                        </div>
                        <div class="form-group col-md-5">
                            <label class="form-label-botica"><i class="fas fa-envelope mr-1 text-muted"></i>Email</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-at"></i></span></div>
                                <input type="email" class="form-control" name="email"
                                       id="editar_email" maxlength="100">
                            </div>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="form-label-botica"><i class="fas fa-toggle-on mr-1 text-muted"></i>Estado</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-circle"></i></span></div>
                                <select class="form-control" name="estado_cliente" id="editar_estado_cliente">
                                    <option value="1">✅ Activo</option>
                                    <option value="0">❌ Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label-botica"><i class="fas fa-map-marker-alt mr-1 text-muted"></i>Dirección</label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-map-pin"></i></span></div>
                            <input type="text" class="form-control" name="direccion"
                                   id="editar_direccion" maxlength="150">
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#a04000,#e67e22);color:#fff;font-weight:600;">
                        <i class="fas fa-save mr-1"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include $ruta_base . 'includes/footer.php'; ?>

<!-- JS del módulo -->
<script src="js/clientes.js"></script>


