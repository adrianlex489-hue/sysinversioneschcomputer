<?php
// ============================================================
// modules/personas/usuarios.php | Botica 2026
// ============================================================

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexión BD no disponible.');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
verificar_acceso([ROL_ADMINISTRADOR]);

// ── Patrón PRG: leer alertas de sesión ───────────────────────────────────────
$swal = null;
if (isset($_SESSION['swal'])) {
    $swal = $_SESSION['swal'];
    unset($_SESSION['swal']);
}

// ── Helper: guardar alerta en sesión y redirigir ──────────────────────────────
function redirigir(string $icon, string $title, string $text): void {
    $_SESSION['swal'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: usuarios.php');
    exit;
}

// ── AJAX: obtener permisos de un usuario ──────────────────────────────────────
if (isset($_GET['accion']) && $_GET['accion'] === 'get_permisos') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id_usuario'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false]); exit; }
    $permisos = cargarPermisos($pdo, $id);
    echo json_encode(['ok' => true, 'permisos' => $permisos]);
    exit;
}

// ── AJAX: guardar permisos de un usuario ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_permisos') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id_usuario'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'msg' => 'ID inválido.']); exit; }

    $modulos   = catalogoModulos();
    $permitidos = $_POST['permisos'] ?? [];

    try {
        // Borrar permisos anteriores y reinsertar
        $pdo->prepare("DELETE FROM permisos_usuario WHERE id_usuario = ?")->execute([$id]);
        $st = $pdo->prepare("INSERT INTO permisos_usuario (id_usuario, modulo, permitido) VALUES (?, ?, ?)");
        foreach (array_keys($modulos) as $mod) {
            $val = in_array($mod, $permitidos) ? 1 : 0;
            $st->execute([$id, $mod, $val]);
        }
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── Recuperar Roles para Formularios ─────────────────────────────────────────
$roles = [];
try {
    $roles = $pdo->query("SELECT id_rol, nombre FROM roles ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar roles: " . $e->getMessage());
}

// ── CRUD ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion     = $_POST['accion']     ?? '';
    $id_usuario = (int)($_POST['id_usuario'] ?? 0);

    // Sanitizar entradas comunes
    $username        = trim($_POST['username'] ?? '');
    $id_rol          = (int)($_POST['id_rol'] ?? 0);
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $email           = trim($_POST['email'] ?? '') ?: null;
    $estado          = isset($_POST['estado']) ? (int)$_POST['estado'] : 1;
    $clave           = $_POST['clave'] ?? '';

    try {
        if ($accion === 'crear') {
            if (empty($username) || empty($clave) || $id_rol === 0) {
                redirigir('warning', 'Campos Incompletos', 'Usuario, Clave y Rol son obligatorios.');
            }
            if (strlen($clave) < 6) {
                redirigir('warning', 'Clave Débil', 'La contraseña debe tener al menos 6 caracteres.');
            }
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                redirigir('warning', 'Email Inválido', 'El formato del email es incorrecto.');
            }

            $hash = password_hash($clave, PASSWORD_DEFAULT);
            $sql = "INSERT INTO usuarios (id_rol, username, clave, nombre_completo, email, estado) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$id_rol, $username, $hash, $nombre_completo, $email, $estado]);
            redirigir('success', '¡Registrado!', "El usuario $username fue creado exitosamente.");

        } elseif ($accion === 'actualizar' && $id_usuario > 0) {
            if ($id_rol === 0) {
                redirigir('warning', 'Campos Incompletos', 'El Rol es obligatorio.');
            }
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                redirigir('warning', 'Email Inválido', 'El formato del email es incorrecto.');
            }

            $params = [$id_rol, $nombre_completo, $email, $estado];
            $sql_fields = "id_rol = ?, nombre_completo = ?, email = ?, estado = ?";

            // Actualizar contraseña solo si se envió una nueva
            if (!empty($clave)) {
                if (strlen($clave) < 6) {
                    redirigir('warning', 'Clave Débil', 'La nueva contraseña debe tener al menos 6 caracteres.');
                }
                $hash = password_hash($clave, PASSWORD_DEFAULT);
                $sql_fields .= ", clave = ?";
                $params[] = $hash;
            }

            $params[] = $id_usuario;
            $sql = "UPDATE usuarios SET {$sql_fields} WHERE id_usuario = ?";
            $pdo->prepare($sql)->execute($params);
            
            redirigir('success', '¡Actualizado!', "Los datos del usuario fueron actualizados correctamente.");

        } elseif ($accion === 'desactivar' && $id_usuario > 0) {
            if ($id_usuario === ($_SESSION['id_usuario'] ?? 0)) {
                redirigir('error', 'Acción Denegada', 'No puedes desactivar tu propia cuenta activa.');
            }
            $pdo->prepare("UPDATE usuarios SET estado=0 WHERE id_usuario=?")->execute([$id_usuario]);
            redirigir('info', 'Usuario Desactivado', "El usuario ha sido movido a la lista de inactivos.");

        } elseif ($accion === 'reactivar' && $id_usuario > 0) {
            $pdo->prepare("UPDATE usuarios SET estado=1 WHERE id_usuario=?")->execute([$id_usuario]);
            redirigir('success', '¡Reactivado!', "El usuario fue reactivado y volvió a la lista de activos.");

        } elseif ($accion === 'eliminar_permanente' && $id_usuario > 0) {
            if ($id_usuario === ($_SESSION['id_usuario'] ?? 0)) {
                redirigir('error', 'Acción Denegada', 'No puedes eliminar tu propia cuenta.');
            }
            $pdo->prepare("DELETE FROM usuarios WHERE id_usuario=?")->execute([$id_usuario]);
            redirigir('success', 'Eliminado', "El usuario ha sido eliminado permanentemente de la base de datos.");
        }

    } catch (PDOException $e) {
        $msg = ($e->getCode() == 23000) 
            ? 'El nombre de usuario o correo ya está registrado.' 
            : 'Error de base de datos: ' . $e->getMessage();
        redirigir('error', 'Error', $msg);
    }
}

// ── DATOS ────────────────────────────────────────────────────────────────────
$usuarios_activos   = [];
$usuarios_inactivos = [];
$total_activos      = 0;
$total_inactivos    = 0;

try {
    $sql_base = "SELECT u.id_usuario, u.username, u.nombre_completo, u.email, u.estado, u.fecha_registro,
                        u.id_rol, r.nombre AS nombre_rol
                 FROM usuarios u 
                 JOIN roles r ON u.id_rol = r.id_rol ";
                 
    $usuarios_activos   = $pdo->query($sql_base . "WHERE u.estado=1 ORDER BY u.username")->fetchAll(PDO::FETCH_ASSOC);
    $usuarios_inactivos = $pdo->query($sql_base . "WHERE u.estado=0 ORDER BY u.username")->fetchAll(PDO::FETCH_ASSOC);
    
    $total_activos      = count($usuarios_activos);
    $total_inactivos    = count($usuarios_inactivos);
} catch (PDOException $e) {
    die('Error al cargar usuarios: ' . $e->getMessage());
}

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>

<!-- CSS del módulo -->
<link rel="stylesheet" href="css/usuarios.css">

<div class="content-wrapper">

    <!-- ── CABECERA ── -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-botica d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4><i class="fas fa-users-cog mr-2"></i>Gestión de Usuarios</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>Botica 2026 &rsaquo; Configuración &rsaquo; Usuarios</small>
                </div>
                <button class="btn btn-light font-weight-bold" data-toggle="modal" data-target="#modalCrearUsuario">
                    <i class="fas fa-user-plus mr-1"></i> Nuevo Usuario
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
                        <i class="fas fa-user-check"></i>
                        <div>
                            <div class="stat-value"><?= $total_activos ?></div>
                            <div class="stat-label">Usuarios activos</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6 mb-2">
                    <div class="stat-mini" style="background:linear-gradient(135deg,#922b21,#e74c3c);">
                        <i class="fas fa-user-times"></i>
                        <div>
                            <div class="stat-value"><?= $total_inactivos ?></div>
                            <div class="stat-label">Usuarios inactivos</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6 mb-2">
                    <div class="stat-mini" style="background:linear-gradient(135deg,#1a5276,#2980b9);">
                        <i class="fas fa-users"></i>
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
                    <h6><i class="fas fa-list mr-2"></i>Listado de Cuentas</h6>
                    <ul class="nav nav-tabs-botica mb-0" id="tabUsuarios" role="tablist" style="border:none;">
                        <li class="nav-item">
                            <a class="nav-link active text-white" id="tab-activos-link" data-toggle="pill"
                               href="#tab-activos" role="tab">
                                <i class="fas fa-check-circle mr-1"></i>Activos
                                <span class="badge badge-light ml-1"><?= $total_activos ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" id="tab-inactivos-link" data-toggle="pill"
                               href="#tab-inactivos" role="tab">
                                <i class="fas fa-times-circle mr-1"></i>Inactivos
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
                                <table id="tablaUsuariosActivos" class="table table-usuarios table-bordered table-sm">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Usuario</th>
                                            <th>Nombre Completo</th>
                                            <th>Rol</th>
                                            <th>Email</th>
                                            <th>Registro</th>
                                            <th class="text-center" style="width:130px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($usuarios_activos as $u): 
                                        $is_self = ($u['id_usuario'] === ($_SESSION['id_usuario'] ?? 0));
                                    ?>
                                        <tr>
                                            <td><?= $u['id_usuario'] ?></td>
                                            <td class="font-weight-bold text-primary">@<?= htmlspecialchars($u['username']) ?></td>
                                            <td><?= htmlspecialchars($u['nombre_completo'] ?? '—') ?></td>
                                            <td>
                                                <span class="badge-rol <?= ($u['id_rol']==1) ? 'admin' : 'trabajador' ?>">
                                                    <?= htmlspecialchars($u['nombre_rol']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($u['email'] ?? '—') ?></td>
                                            <td><small class="text-muted"><?= date('d/m/Y H:i', strtotime($u['fecha_registro'])) ?></small></td>
                                            <td class="text-center">
                                                <!-- VER -->
                                                <button class="btn btn-sm btn-info btn-ver-usuario" title="Ver detalle"
                                                    data-id="<?= $u['id_usuario'] ?>"
                                                    data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>"
                                                    data-nombre="<?= htmlspecialchars($u['nombre_completo'] ?? '', ENT_QUOTES) ?>"
                                                    data-email="<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES) ?>"
                                                    data-rol="<?= $u['id_rol'] ?>"
                                                    data-nombre_rol="<?= htmlspecialchars($u['nombre_rol'], ENT_QUOTES) ?>"
                                                    data-fecha="<?= date('d/m/Y H:i', strtotime($u['fecha_registro'])) ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <!-- EDITAR -->
                                                <button class="btn btn-sm btn-warning btn-editar-usuario" title="Editar"
                                                    data-id="<?= $u['id_usuario'] ?>"
                                                    data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>"
                                                    data-nombre="<?= htmlspecialchars($u['nombre_completo'] ?? '', ENT_QUOTES) ?>"
                                                    data-email="<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES) ?>"
                                                    data-rol="<?= $u['id_rol'] ?>"
                                                    data-estado="<?= $u['estado'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <!-- PERMISOS (solo para no-administradores) -->
                                                <?php if ($u['id_rol'] != ROL_ADMINISTRADOR): ?>
                                                <button class="btn btn-sm btn-permisos btn-permisos-usuario" title="Gestionar permisos"
                                                    data-id="<?= $u['id_usuario'] ?>"
                                                    data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>"
                                                    data-nombre="<?= htmlspecialchars($u['nombre_completo'] ?? $u['username'], ENT_QUOTES) ?>"
                                                    style="background:linear-gradient(135deg,#6c3483,#9b59b6);color:#fff;border:none;">
                                                    <i class="fas fa-shield-alt"></i>
                                                </button>
                                                <?php endif; ?>
                                                <!-- DESACTIVAR (Eliminado Lógico) -->
                                                <button class="btn btn-sm btn-danger btn-desactivar-usuario" title="Desactivar"
                                                    data-id="<?= $u['id_usuario'] ?>"
                                                    data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>"
                                                    data-self="<?= $is_self ? '1' : '0' ?>"
                                                    <?= $is_self ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : '' ?>>
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- INACTIVOS -->
                        <div class="tab-pane fade" id="tab-inactivos" role="tabpanel">
                            <div class="table-responsive">
                                <table id="tablaUsuariosInactivos" class="table table-usuarios table-bordered table-sm">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Usuario</th>
                                            <th>Nombre Completo</th>
                                            <th>Rol</th>
                                            <th>Email</th>
                                            <th>Registro</th>
                                            <th class="text-center" style="width:130px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($usuarios_inactivos as $u): 
                                        $is_self = ($u['id_usuario'] === ($_SESSION['id_usuario'] ?? 0));
                                    ?>
                                        <tr class="table-light">
                                            <td class="text-muted"><?= $u['id_usuario'] ?></td>
                                            <td class="text-muted">@<?= htmlspecialchars($u['username']) ?></td>
                                            <td class="text-muted"><?= htmlspecialchars($u['nombre_completo'] ?? '—') ?></td>
                                            <td>
                                                <span class="badge-rol <?= ($u['id_rol']==1) ? 'admin' : 'trabajador' ?>" style="opacity:0.6">
                                                    <?= htmlspecialchars($u['nombre_rol']) ?>
                                                </span>
                                            </td>
                                            <td class="text-muted"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
                                            <td><small class="text-muted"><?= date('d/m/Y H:i', strtotime($u['fecha_registro'])) ?></small></td>
                                            <td class="text-center">
                                                <!-- REACTIVAR -->
                                                <button class="btn btn-sm btn-success btn-reactivar-usuario" title="Reactivar usuario"
                                                    data-id="<?= $u['id_usuario'] ?>"
                                                    data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <!-- ELIMINAR PERMANENTE -->
                                                <button class="btn btn-sm btn-danger btn-eliminar-permanente" title="Eliminar permanentemente"
                                                    data-id="<?= $u['id_usuario'] ?>"
                                                    data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>"
                                                    data-self="<?= $is_self ? '1' : '0' ?>"
                                                    <?= $is_self ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : '' ?>>
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
     MODAL VER USUARIO
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalVerUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">

            <!-- Cabecera con avatar -->
            <div style="background:linear-gradient(135deg,#1a7a4a 0%,#27ae60 100%);padding:28px 24px 20px;text-align:center;position:relative;">
                <button type="button" class="close" data-dismiss="modal"
                    style="position:absolute;top:12px;right:16px;color:#fff;opacity:.8;font-size:1.4rem;">&times;</button>
                <div style="width:72px;height:72px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                    <i class="fas fa-user-shield" style="font-size:2rem;color:#fff;"></i>
                </div>
                <h5 id="ver_nombre_completo" style="color:#fff;font-weight:700;margin:0;font-size:1.1rem;"></h5>
                <div class="mt-2">
                    <span style="background:rgba(255,255,255,.25);color:#fff;font-size:.78rem;padding:4px 10px;border-radius:20px;font-family:monospace;font-weight:600;">
                        @<span id="ver_username_badge"></span>
                    </span>
                </div>
            </div>

            <!-- Cuerpo con campos -->
            <div class="modal-body" style="padding:20px 24px;">
                <div class="row">

                    <div class="col-6 mb-3">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <div style="width:34px;height:34px;background:#e8f5e9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-hashtag" style="color:#1a7a4a;font-size:.85rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">ID</div>
                                <div id="ver_id_usuario" style="font-size:.9rem;font-weight:700;color:#2d3436;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 mb-3">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <div style="width:34px;height:34px;background:#fde8e8;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-shield-alt" style="color:#c0392b;font-size:.85rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Rol</div>
                                <div id="ver_rol" style="font-size:.9rem;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mb-3">
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

                    <div class="col-6 mb-3">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <div style="width:34px;height:34px;background:#fce4ec;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-toggle-on" style="color:#c0392b;font-size:.85rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Estado</div>
                                <div id="ver_estado_usuario" style="font-size:.9rem;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 mb-3">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <div style="width:34px;height:34px;background:#f3e5f5;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-calendar-alt" style="color:#8e44ad;font-size:.85rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Registro</div>
                                <div id="ver_fecha" style="font-size:.9rem;color:#2d3436;"></div>
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
     MODAL CREAR USUARIO
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header modal-header-botica">
                <h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i>Registrar Nuevo Usuario</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label class="form-label-botica"><i class="fas fa-at mr-1 text-muted"></i>Usuario <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                                <input type="text" class="form-control" name="username" required maxlength="50" placeholder="Ej. jperez">
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="form-label-botica"><i class="fas fa-lock mr-1 text-muted"></i>Contraseña <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-key"></i></span></div>
                                <input type="password" class="form-control" name="clave" required minlength="6" placeholder="Mínimo 6 caracteres">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label class="form-label-botica"><i class="fas fa-shield-alt mr-1 text-muted"></i>Rol <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user-tag"></i></span></div>
                                <select class="form-control" name="id_rol" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach($roles as $rol): ?>
                                        <option value="<?= $rol['id_rol'] ?>"><?= htmlspecialchars($rol['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="form-label-botica"><i class="fas fa-toggle-on mr-1 text-muted"></i>Estado</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-circle"></i></span></div>
                                <select class="form-control" name="estado">
                                    <option value="1" selected>✅ Activo</option>
                                    <option value="0">❌ Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label-botica"><i class="fas fa-id-card mr-1 text-muted"></i>Nombre Completo</label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user-circle"></i></span></div>
                            <input type="text" class="form-control" name="nombre_completo" maxlength="100" placeholder="Juan Pérez">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label-botica"><i class="fas fa-envelope mr-1 text-muted"></i>Email</label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-at"></i></span></div>
                            <input type="email" class="form-control" name="email" maxlength="100" placeholder="juan@ejemplo.com">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-botica-primary btn-sm"><i class="fas fa-save mr-1"></i>Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL EDITAR USUARIO
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#e67e22,#d35400);color:#fff;border-radius:10px 10px 0 0;">
                <h5 class="modal-title"><i class="fas fa-user-edit mr-2"></i>Editar Usuario</h5>
                <button type="button" class="close" style="color:#fff;" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="actualizar">
                <input type="hidden" name="id_usuario" id="editar_id_usuario">
                <div class="modal-body">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label class="form-label-botica"><i class="fas fa-at mr-1 text-muted"></i>Usuario</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                                <input type="text" class="form-control" id="editar_username" readonly style="background:#f8f9fa;" title="No se puede modificar el username">
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="form-label-botica"><i class="fas fa-lock mr-1 text-muted"></i>Nueva Contraseña</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-key"></i></span></div>
                                <input type="password" class="form-control" name="clave" id="editar_clave" minlength="6" placeholder="Vacío = sin cambios">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label class="form-label-botica"><i class="fas fa-shield-alt mr-1 text-muted"></i>Rol <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user-tag"></i></span></div>
                                <select class="form-control" name="id_rol" id="editar_id_rol" required>
                                    <?php foreach($roles as $rol): ?>
                                        <option value="<?= $rol['id_rol'] ?>"><?= htmlspecialchars($rol['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <label class="form-label-botica"><i class="fas fa-toggle-on mr-1 text-muted"></i>Estado</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-circle"></i></span></div>
                                <select class="form-control" name="estado" id="editar_estado">
                                    <option value="1">✅ Activo</option>
                                    <option value="0">❌ Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label-botica"><i class="fas fa-id-card mr-1 text-muted"></i>Nombre Completo</label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user-circle"></i></span></div>
                            <input type="text" class="form-control" name="nombre_completo" id="editar_nombre_completo" maxlength="100">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label-botica"><i class="fas fa-envelope mr-1 text-muted"></i>Email</label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-at"></i></span></div>
                            <input type="email" class="form-control" name="email" id="editar_email" maxlength="100">
                        </div>
                    </div>           </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm text-white" style="background:linear-gradient(135deg,#e67e22,#d35400);font-weight:600;"><i class="fas fa-save mr-1"></i>Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL GESTIÓN DE PERMISOS
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalPermisos" tabindex="-1" aria-hidden="true" style="z-index:1070;">
<div class="modal-dialog modal-lg">
<div class="modal-content" style="border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25);">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#6c3483,#9b59b6);padding:20px 24px;position:relative;">
        <button type="button" class="close" data-dismiss="modal"
            style="position:absolute;top:14px;right:18px;color:#fff;opacity:.8;font-size:1.4rem;">&times;</button>
        <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-shield-alt" style="font-size:1.3rem;color:#fff;"></i>
            </div>
            <div>
                <h5 style="color:#fff;font-weight:700;margin:0;font-size:1.05rem;">
                    Permisos de: <span id="permisosNombreUsuario"></span>
                </h5>
                <small style="color:rgba(255,255,255,.8);">
                    <i class="fas fa-at mr-1"></i><span id="permisosUsernameUsuario"></span>
                    &nbsp;·&nbsp; El Administrador siempre tiene acceso total.
                </small>
            </div>
        </div>
    </div>

    <!-- Acciones rápidas -->
    <div style="background:#f8f9fa;padding:10px 20px;border-bottom:1px solid #e9ecef;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <span style="font-size:.8rem;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:.4px;">Selección rápida:</span>
        <button type="button" id="btnPermisosAll" class="btn btn-sm btn-success" style="border-radius:6px;font-size:.8rem;">
            <i class="fas fa-check-double mr-1"></i>Permitir todo
        </button>
        <button type="button" id="btnPermisosNone" class="btn btn-sm btn-danger" style="border-radius:6px;font-size:.8rem;">
            <i class="fas fa-times mr-1"></i>Denegar todo
        </button>
    </div>

    <!-- Body con checkboxes agrupados -->
    <div class="modal-body" style="padding:20px 24px;max-height:60vh;overflow-y:auto;" id="permisosBody">
        <div class="text-center py-4">
            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
            <p class="mt-2 text-muted">Cargando permisos...</p>
        </div>
    </div>

    <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:14px 20px;">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i>Cancelar
        </button>
        <button type="button" id="btnGuardarPermisos" class="btn btn-sm font-weight-bold"
            style="background:linear-gradient(135deg,#6c3483,#9b59b6);color:#fff;border:none;border-radius:8px;padding:7px 20px;">
            <i class="fas fa-save mr-1"></i>Guardar Permisos
        </button>
    </div>
</div>
</div>
</div>

<?php include $ruta_base . 'includes/footer.php'; ?>

<!-- JS del módulo -->
<script src="js/usuarios.js"></script>

<script>
// ── GESTIÓN DE PERMISOS ───────────────────────────────────────────────────────
(function () {

    var idUsuarioPermisos = 0;

    // Catálogo de módulos con grupos (debe coincidir con conf/permisos.php)
    var modulos = {
        'ventas':            { label: 'Nueva Venta',          icon: 'fas fa-cash-register',  grupo: 'Transacciones' },
        'compras':           { label: 'Nueva Compra',         icon: 'fas fa-truck-loading',  grupo: 'Transacciones' },
        'historial_ventas':  { label: 'Historial de Ventas',  icon: 'fas fa-history',        grupo: 'Transacciones' },
        'historial_compras': { label: 'Historial de Compras', icon: 'fas fa-history',        grupo: 'Transacciones' },
        'caja':              { label: 'Gestión de Caja',      icon: 'fas fa-cash-register',  grupo: 'Caja' },
        'historial_caja':    { label: 'Historial de Caja',    icon: 'fas fa-history',        grupo: 'Caja' },
        'inventario':        { label: 'Stock General',        icon: 'fas fa-boxes',          grupo: 'Inventario' },
        'lotes':             { label: 'Lotes / Vencimientos', icon: 'fas fa-calendar-times', grupo: 'Inventario' },
        'productos':         { label: 'Productos',            icon: 'fas fa-capsules',       grupo: 'Catálogos' },
        'categorias':        { label: 'Categorías',           icon: 'fas fa-tags',           grupo: 'Catálogos' },
        'unidades':          { label: 'Unidades',             icon: 'fas fa-ruler-combined', grupo: 'Catálogos' },
        'clientes':          { label: 'Clientes',             icon: 'fas fa-users',          grupo: 'Personas' },
        'proveedores':       { label: 'Proveedores',          icon: 'fas fa-truck',          grupo: 'Personas' },
        'empresa':           { label: 'Mi Empresa',           icon: 'fas fa-building',       grupo: 'Empresa' }
    };

    var coloresGrupo = {
        'Transacciones': '#1a7a4a',
        'Caja':          '#e67e22',
        'Inventario':    '#117a8b',
        'Catálogos':     '#1a5276',
        'Personas':      '#6c3483',
        'Empresa':       '#0d3b26'
    };

    // Abrir modal y cargar permisos
    $(document).on('click', '.btn-permisos-usuario', function () {
        idUsuarioPermisos = $(this).data('id');
        var nombre   = $(this).data('nombre');
        var username = $(this).data('username');

        $('#permisosNombreUsuario').text(nombre);
        $('#permisosUsernameUsuario').text('@' + username);
        $('#permisosBody').html(
            '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i>' +
            '<p class="mt-2 text-muted">Cargando permisos...</p></div>'
        );
        $('#modalPermisos').modal('show');

        $.get('usuarios.php', { accion: 'get_permisos', id_usuario: idUsuarioPermisos }, function (res) {
            if (!res.ok) {
                $('#permisosBody').html('<div class="alert alert-danger">Error al cargar permisos.</div>');
                return;
            }
            renderPermisos(res.permisos);
        }, 'json').fail(function () {
            $('#permisosBody').html('<div class="alert alert-danger">Error de conexión.</div>');
        });
    });

    // Renderizar checkboxes agrupados
    function renderPermisos(permisos) {
        // Agrupar módulos
        var grupos = {};
        $.each(modulos, function (key, mod) {
            if (!grupos[mod.grupo]) grupos[mod.grupo] = [];
            grupos[mod.grupo].push({ key: key, label: mod.label, icon: mod.icon });
        });

        var html = '';
        $.each(grupos, function (grupo, items) {
            var color = coloresGrupo[grupo] || '#495057';
            html += '<div class="permiso-grupo mb-4">';
            html += '<div class="permiso-grupo-header" style="background:' + color + ';color:#fff;padding:8px 14px;border-radius:8px;margin-bottom:10px;font-weight:700;font-size:.85rem;">';
            html += '<i class="fas fa-layer-group mr-2"></i>' + grupo;
            html += '</div>';
            html += '<div class="row">';
            $.each(items, function (i, item) {
                var checked = permisos[item.key] ? 'checked' : '';
                var cardStyle = permisos[item.key]
                    ? 'border:2px solid ' + color + ';background:#f8fff8;'
                    : 'border:2px solid #dee2e6;background:#fff;';
                html += '<div class="col-md-6 col-lg-4 mb-2">';
                html += '<label class="permiso-card" style="' + cardStyle + 'cursor:pointer;border-radius:10px;padding:12px 14px;display:flex;align-items:center;gap:10px;transition:all .15s;margin:0;width:100%;">';
                html += '<input type="checkbox" class="permiso-check" data-modulo="' + item.key + '" ' + checked + ' style="width:18px;height:18px;cursor:pointer;accent-color:' + color + ';">';
                html += '<div style="flex:1;">';
                html += '<div style="font-size:.85rem;font-weight:700;color:#2d3436;">';
                html += '<i class="' + item.icon + ' mr-1" style="color:' + color + ';font-size:.8rem;"></i>' + item.label;
                html += '</div></div>';
                html += '</label>';
                html += '</div>';
            });
            html += '</div></div>';
        });

        $('#permisosBody').html(html);

        // Actualizar estilo al cambiar checkbox
        $(document).on('change', '.permiso-check', function () {
            var $label = $(this).closest('.permiso-card');
            var modulo = $(this).data('modulo');
            var grupo  = modulos[modulo] ? modulos[modulo].grupo : '';
            var color  = coloresGrupo[grupo] || '#495057';
            if ($(this).is(':checked')) {
                $label.css({ 'border-color': color, 'background': '#f8fff8' });
            } else {
                $label.css({ 'border-color': '#dee2e6', 'background': '#fff' });
            }
        });
    }

    // Seleccionar / deseleccionar todo
    $('#btnPermisosAll').on('click', function () {
        $('.permiso-check').prop('checked', true).trigger('change');
    });
    $('#btnPermisosNone').on('click', function () {
        $('.permiso-check').prop('checked', false).trigger('change');
    });

    // Guardar permisos
    $('#btnGuardarPermisos').on('click', function () {
        var $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Guardando...');
        var permitidos = [];
        $('.permiso-check:checked').each(function () {
            permitidos.push($(this).data('modulo'));
        });

        $.post('usuarios.php', {
            accion:      'guardar_permisos',
            id_usuario:  idUsuarioPermisos,
            'permisos[]': permitidos
        }, function (res) {
            $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Guardar Permisos');
            if (res.ok) {
                $('#modalPermisos').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Permisos guardados',
                    text: 'Los permisos del usuario fueron actualizados correctamente.',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.msg || 'No se pudieron guardar los permisos.', confirmButtonColor: '#6c3483' });
            }
        }, 'json').fail(function () {
            $btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Guardar Permisos');
            Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor.', confirmButtonColor: '#6c3483' });
        });
    });

    // Limpiar al cerrar
    $('#modalPermisos').on('hidden.bs.modal', function () {
        idUsuarioPermisos = 0;
        $('#permisosBody').html('');
        $(document).off('change', '.permiso-check');
    });

})();
</script>
