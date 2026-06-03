<?php
// ============================================================
// modules/personas/usuarios.php | SysInversiones CH Computer
// ============================================================

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';
require_once $ruta_base . 'conf/auditoria.php';

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

    $modulos    = catalogoModulos();
    $permitidos = $_POST['permisos'] ?? [];

    try {
        // Leer permisos anteriores para auditoría
        $stPerm = $pdo->prepare("SELECT modulo, permitido FROM permisos_usuario WHERE id_usuario = ?");
        $stPerm->execute([$id]);
        $perms_antes = $stPerm->fetchAll(PDO::FETCH_KEY_PAIR);

        // Borrar permisos anteriores y reinsertar
        $pdo->prepare("DELETE FROM permisos_usuario WHERE id_usuario = ?")->execute([$id]);
        $st = $pdo->prepare("INSERT INTO permisos_usuario (id_usuario, modulo, permitido) VALUES (?, ?, ?)");
        foreach (array_keys($modulos) as $mod) {
            $val = in_array($mod, $permitidos) ? 1 : 0;
            $st->execute([$id, $mod, $val]);
        }

        // Detectar módulos que cambiaron
        $cambios = [];
        foreach (array_keys($modulos) as $mod) {
            $antes  = isset($perms_antes[$mod]) ? (int)$perms_antes[$mod] : 1;
            $nuevo  = in_array($mod, $permitidos) ? 1 : 0;
            if ($antes !== $nuevo) {
                $cambios[] = $mod . ': ' . ($antes ? 'ON' : 'OFF') . ' → ' . ($nuevo ? 'ON' : 'OFF');
            }
        }
        if (!empty($cambios)) {
            // Obtener nombre del usuario afectado
            $stNom = $pdo->prepare("SELECT username FROM usuarios WHERE id_usuario = ?");
            $stNom->execute([$id]);
            $uname = $stNom->fetchColumn() ?? "ID:$id";
            registrarAuditoria($pdo, 'usuarios', 'permisos', 'permisos_usuario', $id,
                "Permisos modificados para @$uname — " . implode(', ', $cambios));
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

            $hash = password_hash($clave, PASSWORD_DEFAULT);
            $sql = "INSERT INTO usuarios (id_rol, username, clave, nombre_completo, estado, fecha_registro) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $pdo->prepare($sql)->execute([$id_rol, $username, $hash, $nombre_completo, $estado]);
            $id_nuevo_usr = (int)$pdo->lastInsertId();
            // Obtener nombre del rol
            $stRolNom = $pdo->prepare("SELECT nombre FROM roles WHERE id_rol = ?");
            $stRolNom->execute([$id_rol]);
            $nombre_rol = $stRolNom->fetchColumn() ?? "ID:$id_rol";
            registrarAuditoria($pdo, 'usuarios', 'crear', 'usuarios', $id_nuevo_usr,
                "Usuario creado: @$username ($nombre_completo) — Rol: $nombre_rol — Estado: " . ($estado ? 'Activo' : 'Inactivo'));
            redirigir('success', '¡Registrado!', "El usuario $username fue creado exitosamente.");

        } elseif ($accion === 'actualizar' && $id_usuario > 0) {
            if ($id_rol === 0) {
                redirigir('warning', 'Campos Incompletos', 'El Rol es obligatorio.');
            }

            // Leer datos anteriores para auditoría
            $stUsr = $pdo->prepare("SELECT id_rol, nombre_completo, estado FROM usuarios WHERE id_usuario = ?");
            $stUsr->execute([$id_usuario]);
            $usr_antes = $stUsr->fetch(PDO::FETCH_ASSOC);

            $params = [$id_rol, $nombre_completo, $estado];
            $sql_fields = "id_rol = ?, nombre_completo = ?, estado = ?";
            $cambio_clave = false;

            // Actualizar contraseña solo si se envió una nueva
            if (!empty($clave)) {
                if (strlen($clave) < 6) {
                    redirigir('warning', 'Clave Débil', 'La nueva contraseña debe tener al menos 6 caracteres.');
                }
                $hash = password_hash($clave, PASSWORD_DEFAULT);
                $sql_fields .= ", clave = ?";
                $params[] = $hash;
                $cambio_clave = true;
            }

            $params[] = $id_usuario;
            $sql = "UPDATE usuarios SET {$sql_fields} WHERE id_usuario = ?";
            $pdo->prepare($sql)->execute($params);

            // Auditoría: registrar cambios relevantes
            if ($usr_antes) {
                if ((int)$usr_antes['id_rol'] !== $id_rol) {
                    $stRolAntes = $pdo->prepare("SELECT nombre FROM roles WHERE id_rol = ?");
                    $stRolAntes->execute([$usr_antes['id_rol']]);
                    $rol_antes_nom = $stRolAntes->fetchColumn() ?? "ID:{$usr_antes['id_rol']}";
                    $stRolNuevo = $pdo->prepare("SELECT nombre FROM roles WHERE id_rol = ?");
                    $stRolNuevo->execute([$id_rol]);
                    $rol_nuevo_nom = $stRolNuevo->fetchColumn() ?? "ID:$id_rol";
                    registrarAuditoria($pdo, 'usuarios', 'cambio_rol', 'usuarios', $id_usuario,
                        "Cambio de rol para @$username: $rol_antes_nom → $rol_nuevo_nom",
                        'id_rol', $usr_antes['id_rol'], $id_rol);
                }
                if ((int)$usr_antes['estado'] !== $estado) {
                    registrarAuditoria($pdo, 'usuarios', 'editar', 'usuarios', $id_usuario,
                        "Cambio de estado para @$username",
                        'estado', $usr_antes['estado'] ? 'Activo' : 'Inactivo', $estado ? 'Activo' : 'Inactivo');
                }
                if ($cambio_clave) {
                    registrarAuditoria($pdo, 'usuarios', 'editar', 'usuarios', $id_usuario,
                        "Contraseña cambiada para @$username");
                }
            }
            redirigir('success', '¡Actualizado!', "Los datos del usuario fueron actualizados correctamente.");

        } elseif ($accion === 'desactivar' && $id_usuario > 0) {
            if ($id_usuario === ($_SESSION['id_usuario'] ?? 0)) {
                redirigir('error', 'Acción Denegada', 'No puedes desactivar tu propia cuenta activa.');
            }
            $stUname = $pdo->prepare("SELECT username FROM usuarios WHERE id_usuario = ?");
            $stUname->execute([$id_usuario]);
            $uname_des = $stUname->fetchColumn() ?? "ID:$id_usuario";
            $pdo->prepare("UPDATE usuarios SET estado=0 WHERE id_usuario=?")->execute([$id_usuario]);
            registrarAuditoria($pdo, 'usuarios', 'eliminar', 'usuarios', $id_usuario,
                "Usuario desactivado: @$uname_des", 'estado', 'Activo', 'Inactivo');
            redirigir('info', 'Usuario Desactivado', "El usuario ha sido movido a la lista de inactivos.");

        } elseif ($accion === 'reactivar' && $id_usuario > 0) {
            $stUname = $pdo->prepare("SELECT username FROM usuarios WHERE id_usuario = ?");
            $stUname->execute([$id_usuario]);
            $uname_rea = $stUname->fetchColumn() ?? "ID:$id_usuario";
            $pdo->prepare("UPDATE usuarios SET estado=1 WHERE id_usuario=?")->execute([$id_usuario]);
            registrarAuditoria($pdo, 'usuarios', 'crear', 'usuarios', $id_usuario,
                "Usuario reactivado: @$uname_rea", 'estado', 'Inactivo', 'Activo');
            redirigir('success', '¡Reactivado!', "El usuario fue reactivado y volvió a la lista de activos.");

        } elseif ($accion === 'eliminar_permanente' && $id_usuario > 0) {
            if ($id_usuario === ($_SESSION['id_usuario'] ?? 0)) {
                redirigir('error', 'Acción Denegada', 'No puedes eliminar tu propia cuenta.');
            }
            $stUname = $pdo->prepare("SELECT username, nombre_completo FROM usuarios WHERE id_usuario = ?");
            $stUname->execute([$id_usuario]);
            $usr_del = $stUname->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare("DELETE FROM usuarios WHERE id_usuario=?")->execute([$id_usuario]);
            registrarAuditoria($pdo, 'usuarios', 'eliminar', 'usuarios', $id_usuario,
                "Usuario eliminado permanentemente: @" . ($usr_del['username'] ?? '') . " (" . ($usr_del['nombre_completo'] ?? '') . ")");
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
    $sql_base = "SELECT u.id_usuario, u.username, u.nombre_completo, u.estado, u.fecha_registro,
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
<link rel="stylesheet" href="css/usuarios.css?v=<?= time() ?>">

<div class="content-wrapper">

    <!-- ── CABECERA ── -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-botica d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4><i class="fas fa-users-cog mr-2"></i>Gestión de Usuarios</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones CH Computer &rsaquo; Configuración &rsaquo; Usuarios</small>
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
                                                <?php
                                                $rolClass = match((int)$u['id_rol']) {
                                                    1 => 'admin',
                                                    2 => 'asesor',
                                                    3 => 'tecnico',
                                                    default => 'tecnico'
                                                };
                                                ?>
                                                <span class="badge-rol <?= $rolClass ?>">
                                                    <?= htmlspecialchars($u['nombre_rol']) ?>
                                                </span>
                                            </td>
                                            <td><small class="text-muted"><?= date('d/m/Y H:i', strtotime($u['fecha_registro'])) ?></small></td>
                                            <td class="text-center">
                                                <!-- VER -->
                                                <button class="btn btn-sm btn-info btn-ver-usuario" title="Ver detalle"
                                                    data-id="<?= $u['id_usuario'] ?>"
                                                    data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>"
                                                    data-nombre="<?= htmlspecialchars($u['nombre_completo'] ?? '', ENT_QUOTES) ?>"
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
                                            <th>Registro</th>
                                            <th class="text-center" style="width:130px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($usuarios_inactivos as $u): 
                                        $is_self = ($u['id_usuario'] === ($_SESSION['id_usuario'] ?? 0));
                                    ?>
                                        <tr class="row-inactivo">
                                            <td class="text-muted"><?= $u['id_usuario'] ?></td>
                                            <td class="text-muted">@<?= htmlspecialchars($u['username']) ?></td>
                                            <td class="text-muted"><?= htmlspecialchars($u['nombre_completo'] ?? '—') ?></td>
                                            <td>
                                                <?php
                                                $rolClass = match((int)$u['id_rol']) {
                                                    1 => 'admin',
                                                    2 => 'asesor',
                                                    3 => 'tecnico',
                                                    default => 'tecnico'
                                                };
                                                ?>
                                                <span class="badge-rol <?= $rolClass ?>" style="opacity:0.6">
                                                    <?= htmlspecialchars($u['nombre_rol']) ?>
                                                </span>
                                            </td>
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
            <div class="modal-header modal-header-botica">
                <h5 class="modal-title"><i class="fas fa-user-edit mr-2"></i>Editar Usuario</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-botica-primary btn-sm"><i class="fas fa-save mr-1"></i>Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL GESTIÓN DE PERMISOS — TECH DESIGN
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalPermisos" tabindex="-1" aria-hidden="true" style="z-index:1070;">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content" id="permisosModalContent">

    <!-- ── HEADER TECH ── -->
    <div class="perm-header">
        <!-- Grid animado de fondo -->
        <div class="perm-grid-bg"></div>
        <!-- Orbes de luz -->
        <div class="perm-orb perm-orb1"></div>
        <div class="perm-orb perm-orb2"></div>

        <button type="button" class="perm-close-btn" data-dismiss="modal">&times;</button>

        <div class="perm-header-content">
            <!-- Icono shield con anillo pulsante -->
            <div class="perm-shield-wrap">
                <div class="perm-shield-ring"></div>
                <div class="perm-shield-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
            </div>
            <div class="perm-header-info">
                <div class="perm-header-label">CONTROL DE ACCESO</div>
                <div class="perm-header-name" id="permisosNombreUsuario"></div>
                <div class="perm-header-meta">
                    <span class="perm-badge-user"><i class="fas fa-at"></i> <span id="permisosUsernameUsuario"></span></span>
                    <span class="perm-badge-info"><i class="fas fa-info-circle"></i> Admin siempre tiene acceso total</span>
                </div>
            </div>
            <!-- Contador de permisos activos -->
            <div class="perm-counter">
                <div class="perm-counter-num" id="permisosContador">0</div>
                <div class="perm-counter-label">activos</div>
            </div>
        </div>
    </div>

    <!-- ── TOOLBAR ── -->
    <div class="perm-toolbar">
        <div class="perm-toolbar-left">
            <i class="fas fa-sliders-h" style="color:#60a5fa;margin-right:6px;"></i>
            <span>Acceso rápido:</span>
        </div>
        <div class="perm-toolbar-right">
            <button type="button" id="btnPermisosAll" class="perm-btn-quick perm-btn-allow">
                <i class="fas fa-check-double"></i> Permitir todo
            </button>
            <button type="button" id="btnPermisosNone" class="perm-btn-quick perm-btn-deny">
                <i class="fas fa-ban"></i> Denegar todo
            </button>
        </div>
    </div>

    <!-- ── BODY ── -->
    <div class="perm-body" id="permisosBody">
        <div class="perm-loading">
            <div class="perm-loading-ring"></div>
            <span>Cargando módulos del sistema...</span>
        </div>
    </div>

    <!-- ── FOOTER ── -->
    <div class="perm-footer">
        <button type="button" class="perm-btn-cancel" data-dismiss="modal">
            <i class="fas fa-times"></i> Cancelar
        </button>
        <button type="button" id="btnGuardarPermisos" class="perm-btn-save">
            <i class="fas fa-satellite-dish"></i>
            <span id="btnGuardarPermisosText">Aplicar Permisos</span>
        </button>
    </div>

</div>
</div>
</div>

<!-- ── ESTILOS TECH DEL MODAL ── -->
<style>
/* ── Modal container ── */
#permisosModalContent {
    background: #0d1117;
    border: 1px solid rgba(37,99,235,.3);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 0 0 1px rgba(37,99,235,.15), 0 30px 80px rgba(0,0,0,.7), 0 0 60px rgba(37,99,235,.08);
}

/* ── Header ── */
.perm-header {
    position: relative;
    background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 60%, #1d4ed8 100%);
    padding: 24px 24px 20px;
    overflow: hidden;
}
.perm-grid-bg {
    position: absolute; inset: 0;
    pointer-events: none;
    background-image:
        linear-gradient(rgba(37,99,235,.1) 1px, transparent 1px),
        linear-gradient(90deg, rgba(37,99,235,.1) 1px, transparent 1px);
    background-size: 28px 28px;
    animation: permGridMove 20s linear infinite;
}
@keyframes permGridMove { from{background-position:0 0} to{background-position:28px 28px} }

.perm-orb {
    position: absolute; border-radius: 50%;
    background: radial-gradient(circle, rgba(37,99,235,.3), transparent 70%);
    animation: permOrbPulse 6s ease-in-out infinite;
    pointer-events: none;
}
.perm-orb1 { width:300px; height:300px; top:-120px; right:-80px; }
.perm-orb2 { width:200px; height:200px; bottom:-80px; left:-60px; animation-delay:3s; }
@keyframes permOrbPulse { 0%,100%{opacity:.4;transform:scale(1)} 50%{opacity:.8;transform:scale(1.1)} }

.perm-close-btn {
    position: absolute; top: 14px; right: 18px;
    background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15);
    color: #fff; border-radius: 8px; width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; cursor: pointer; z-index: 10;
    transition: background .2s, border-color .2s;
}
.perm-close-btn:hover { background: rgba(239,68,68,.3); border-color: rgba(239,68,68,.5); }

.perm-header-content {
    position: relative; z-index: 2;
    display: flex; align-items: center; gap: 16px;
}

/* Shield icon */
.perm-shield-wrap {
    position: relative; width: 56px; height: 56px; flex-shrink: 0;
}
.perm-shield-ring {
    position: absolute; inset: -6px; border-radius: 50%;
    border: 2px solid rgba(96,165,250,.4);
    animation: permRingPulse 2s ease-in-out infinite;
}
@keyframes permRingPulse { 0%,100%{transform:scale(1);opacity:.5} 50%{transform:scale(1.15);opacity:1} }
.perm-shield-icon {
    width: 56px; height: 56px; border-radius: 14px;
    background: linear-gradient(135deg, rgba(37,99,235,.4), rgba(14,165,233,.3));
    border: 1px solid rgba(96,165,250,.4);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 0 20px rgba(37,99,235,.4);
}
.perm-shield-icon i { color: #93c5fd; font-size: 1.5rem; }

/* Header info */
.perm-header-info { flex: 1; }
.perm-header-label {
    font-size: .65rem; font-weight: 700; letter-spacing: 2px;
    color: rgba(147,197,253,.7); text-transform: uppercase; margin-bottom: 3px;
}
.perm-header-name {
    font-size: 1.1rem; font-weight: 800; color: #fff; line-height: 1.2;
}
.perm-header-meta { display: flex; gap: 10px; margin-top: 6px; flex-wrap: wrap; }
.perm-badge-user, .perm-badge-info {
    font-size: .72rem; padding: 2px 10px; border-radius: 20px;
    display: inline-flex; align-items: center; gap: 4px;
}
.perm-badge-user {
    background: rgba(37,99,235,.25); border: 1px solid rgba(96,165,250,.3);
    color: #93c5fd; font-family: monospace; font-weight: 600;
}
.perm-badge-info {
    background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12);
    color: rgba(255,255,255,.55);
}

/* Contador */
.perm-counter {
    text-align: center; background: rgba(37,99,235,.2);
    border: 1px solid rgba(96,165,250,.3); border-radius: 12px;
    padding: 10px 18px; flex-shrink: 0;
    box-shadow: 0 0 20px rgba(37,99,235,.2);
}
.perm-counter-num {
    font-size: 1.8rem; font-weight: 800; color: #60a5fa;
    line-height: 1; font-family: 'Courier New', monospace;
    text-shadow: 0 0 12px rgba(96,165,250,.6);
}
.perm-counter-label { font-size: .65rem; color: rgba(147,197,253,.7); text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }

/* ── Toolbar ── */
.perm-toolbar {
    background: #161b27;
    border-bottom: 1px solid rgba(37,99,235,.2);
    padding: 10px 20px;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;
}
.perm-toolbar-left { font-size: .78rem; font-weight: 600; color: #64748b; display: flex; align-items: center; }
.perm-toolbar-right { display: flex; gap: 8px; }
.perm-btn-quick {
    font-size: .78rem; font-weight: 700; padding: 5px 14px;
    border-radius: 8px; border: 1px solid; cursor: pointer;
    display: flex; align-items: center; gap: 5px;
    transition: all .2s;
}
.perm-btn-allow {
    background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.35);
    color: #34d399;
}
.perm-btn-allow:hover { background: rgba(16,185,129,.2); box-shadow: 0 0 12px rgba(16,185,129,.2); }
.perm-btn-deny {
    background: rgba(239,68,68,.1); border-color: rgba(239,68,68,.35);
    color: #f87171;
}
.perm-btn-deny:hover { background: rgba(239,68,68,.2); box-shadow: 0 0 12px rgba(239,68,68,.2); }

/* ── Body ── */
.perm-body {
    background: #0d1117;
    padding: 20px 22px;
    max-height: 55vh;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(37,99,235,.4) transparent;
}
.perm-body::-webkit-scrollbar { width: 5px; }
.perm-body::-webkit-scrollbar-track { background: transparent; }
.perm-body::-webkit-scrollbar-thumb { background: rgba(37,99,235,.4); border-radius: 99px; }

/* Loading */
.perm-loading {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; padding: 40px; gap: 14px; color: #475569;
}
.perm-loading-ring {
    width: 40px; height: 40px; border-radius: 50%;
    border: 3px solid rgba(37,99,235,.15);
    border-top-color: #2563eb;
    animation: permSpin .8s linear infinite;
}
@keyframes permSpin { to { transform: rotate(360deg); } }

/* Grupo */
.perm-grupo { margin-bottom: 22px; }
.perm-grupo-header {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 14px; border-radius: 8px; margin-bottom: 12px;
    position: relative; overflow: hidden;
}
.perm-grupo-header::before {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(90deg, rgba(255,255,255,.06), transparent);
}
.perm-grupo-header-icon {
    width: 28px; height: 28px; border-radius: 7px;
    background: rgba(255,255,255,.12);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.perm-grupo-header-icon i { font-size: .8rem; color: #fff; }
.perm-grupo-title {
    font-size: .8rem; font-weight: 800; color: #fff;
    text-transform: uppercase; letter-spacing: 1.5px; flex: 1;
}
.perm-grupo-count {
    font-size: .7rem; font-weight: 700; padding: 2px 8px;
    border-radius: 20px; background: rgba(255,255,255,.15);
    color: rgba(255,255,255,.8);
}

/* Cards de módulo con toggle switch */
.perm-modulos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }

.perm-modulo-card {
    background: #161b27;
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 10px;
    padding: 14px 16px;
    display: flex; align-items: center; gap: 12px;
    cursor: pointer;
    transition: all .2s;
    position: relative; overflow: hidden;
    min-height: 56px;
}
.perm-modulo-card::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0;
    width: 3px; border-radius: 0 2px 2px 0;
    background: transparent;
    transition: background .2s;
}
.perm-modulo-card:hover {
    border-color: rgba(37,99,235,.3);
    background: #1a2035;
    transform: translateY(-1px);
}
.perm-modulo-card.is-active {
    border-color: rgba(37,99,235,.4);
    background: rgba(37,99,235,.08);
    box-shadow: 0 0 16px rgba(37,99,235,.1);
}
.perm-modulo-card.is-active::before { background: #2563eb; }

.perm-modulo-icon {
    width: 34px; height: 34px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: all .2s;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.08);
}
.perm-modulo-card.is-active .perm-modulo-icon {
    box-shadow: 0 0 12px rgba(37,99,235,.3);
}
.perm-modulo-icon i { font-size: .85rem; color: #64748b; transition: color .2s; }
.perm-modulo-card.is-active .perm-modulo-icon i { color: #60a5fa; }

.perm-modulo-label {
    flex: 1; font-size: .8rem; font-weight: 600;
    color: #64748b; transition: color .2s; line-height: 1.3;
}
.perm-modulo-card.is-active .perm-modulo-label { color: #e2e8f0; }

/* Toggle switch */
.perm-toggle {
    position: relative; width: 36px; height: 20px; flex-shrink: 0;
}
.perm-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
.perm-toggle-slider {
    position: absolute; inset: 0; border-radius: 20px;
    background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.12);
    cursor: pointer; transition: all .25s;
}
.perm-toggle-slider::before {
    content: ''; position: absolute;
    width: 14px; height: 14px; border-radius: 50%;
    left: 2px; top: 50%; transform: translateY(-50%);
    background: #475569; transition: all .25s;
    box-shadow: 0 1px 4px rgba(0,0,0,.4);
}
.perm-toggle input:checked + .perm-toggle-slider {
    background: rgba(37,99,235,.4);
    border-color: rgba(37,99,235,.6);
    box-shadow: 0 0 8px rgba(37,99,235,.3);
}
.perm-toggle input:checked + .perm-toggle-slider::before {
    transform: translateX(16px) translateY(-50%);
    background: #60a5fa;
    box-shadow: 0 0 6px rgba(96,165,250,.6);
}

/* ── Footer ── */
.perm-footer {
    background: #161b27;
    border-top: 1px solid rgba(37,99,235,.2);
    padding: 14px 22px;
    display: flex; justify-content: flex-end; gap: 10px; align-items: center;
}
.perm-btn-cancel {
    background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.12);
    color: #94a3b8; border-radius: 9px; padding: 8px 18px;
    font-size: .85rem; font-weight: 600; cursor: pointer;
    display: flex; align-items: center; gap: 6px;
    transition: all .2s;
}
.perm-btn-cancel:hover { background: rgba(255,255,255,.1); color: #e2e8f0; }
.perm-btn-save {
    background: linear-gradient(135deg, #1a3a6b, #2563eb);
    border: 1px solid rgba(96,165,250,.3);
    color: #fff; border-radius: 9px; padding: 8px 22px;
    font-size: .85rem; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; gap: 7px;
    box-shadow: 0 4px 16px rgba(37,99,235,.3);
    transition: all .2s; position: relative; overflow: hidden;
}
.perm-btn-save::after {
    content: ''; position: absolute; top: 0; left: -100%; width: 60%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.15), transparent);
    transform: skewX(-20deg);
    animation: permShimmer 3s ease infinite;
}
@keyframes permShimmer { 0%{left:-100%} 100%{left:200%} }
.perm-btn-save:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(37,99,235,.45); }
.perm-btn-save:disabled { opacity: .6; pointer-events: none; }
</style>

<?php include $ruta_base . 'includes/footer.php'; ?>

<!-- JS del módulo -->
<script src="js/usuarios.js"></script>

<script>
// ── GESTIÓN DE PERMISOS — TECH UI ────────────────────────────────────────────
(function () {

    var idUsuarioPermisos = 0;

    // Iconos de grupo para el header de sección
    var grupoIconos = {
        'Servicio Técnico': 'fas fa-tools',
        'Caja':             'fas fa-cash-register',
        'Ventas':           'fas fa-shopping-cart',
        'Compras':          'fas fa-truck-loading',
        'Inventario':       'fas fa-boxes',
        'Catálogos':        'fas fa-laptop',
        'Personas':         'fas fa-users',
        'Empresa':          'fas fa-building'
    };

    // Colores de acento por grupo (para el header y el glow del icono)
    var coloresGrupo = {
        'Servicio Técnico': { bg: 'rgba(124,58,237,.15)', border: 'rgba(124,58,237,.4)',  accent: '#a78bfa' },
        'Caja':             { bg: 'rgba(245,158,11,.12)', border: 'rgba(245,158,11,.35)', accent: '#fbbf24' },
        'Ventas':           { bg: 'rgba(37,99,235,.15)',  border: 'rgba(37,99,235,.4)',   accent: '#60a5fa' },
        'Compras':          { bg: 'rgba(14,165,233,.12)', border: 'rgba(14,165,233,.35)', accent: '#38bdf8' },
        'Inventario':       { bg: 'rgba(13,148,136,.12)', border: 'rgba(13,148,136,.35)', accent: '#2dd4bf' },
        'Catálogos':        { bg: 'rgba(99,102,241,.12)', border: 'rgba(99,102,241,.35)', accent: '#818cf8' },
        'Personas':         { bg: 'rgba(236,72,153,.12)', border: 'rgba(236,72,153,.35)', accent: '#f472b6' },
        'Empresa':          { bg: 'rgba(107,114,128,.12)',border: 'rgba(107,114,128,.35)','accent': '#9ca3af' }
    };

    // Catálogo de módulos con grupos
    var modulos = {
        'servicios':          { label: 'Recepción de Equipos',  icon: 'fas fa-plus-circle',         grupo: 'Servicio Técnico' },
        'taller':             { label: 'Taller Técnico',        icon: 'fas fa-tools',               grupo: 'Servicio Técnico' },
        'cobro_servicio':     { label: 'Cobro de Servicios',    icon: 'fas fa-hand-holding-usd',    grupo: 'Servicio Técnico' },
        'caja':               { label: 'Gestión de Caja',       icon: 'fas fa-cash-register',       grupo: 'Caja' },
        'historial_caja':     { label: 'Historial de Caja',     icon: 'fas fa-history',             grupo: 'Caja' },
        'ventas':             { label: 'Nueva Venta',           icon: 'fas fa-shopping-cart',       grupo: 'Ventas' },
        'cobro_ventas':       { label: 'Cobro de Créditos',     icon: 'fas fa-file-invoice-dollar', grupo: 'Ventas' },
        'historial_ventas':   { label: 'Historial de Ventas',   icon: 'fas fa-history',             grupo: 'Ventas' },
        'compras':            { label: 'Nueva Compra',          icon: 'fas fa-truck-loading',       grupo: 'Compras' },
        'cobro_compras':      { label: 'Pago de Créditos',      icon: 'fas fa-file-invoice',        grupo: 'Compras' },
        'historial_compras':  { label: 'Historial de Compras',  icon: 'fas fa-history',             grupo: 'Compras' },
        'inventario':         { label: 'Stock / Inventario',    icon: 'fas fa-boxes',               grupo: 'Inventario' },
        'productos':          { label: 'Productos',             icon: 'fas fa-laptop',              grupo: 'Catálogos' },
        'categorias':         { label: 'Categorías',            icon: 'fas fa-tags',                grupo: 'Catálogos' },
        'catalogo_servicios': { label: 'Servicios Técnicos',    icon: 'fas fa-wrench',              grupo: 'Catálogos' },
        'clientes':           { label: 'Clientes',              icon: 'fas fa-users',               grupo: 'Personas' },
        'proveedores':        { label: 'Proveedores',           icon: 'fas fa-truck',               grupo: 'Personas' },
        'empresa':            { label: 'Mi Empresa',            icon: 'fas fa-building',            grupo: 'Empresa' }
    };

    // ── Actualizar contador ──────────────────────────────────────────────────
    function actualizarContador() {
        var total = $('.perm-toggle input:checked').length;
        $('#permisosContador').text(total);
    }

    // ── Abrir modal ──────────────────────────────────────────────────────────
    $(document).on('click', '.btn-permisos-usuario', function () {
        idUsuarioPermisos = $(this).data('id');
        var nombre   = $(this).data('nombre');
        var username = $(this).data('username');

        $('#permisosNombreUsuario').text(nombre);
        $('#permisosUsernameUsuario').text(username);
        $('#permisosContador').text('—');
        $('#permisosBody').html(
            '<div class="perm-loading"><div class="perm-loading-ring"></div><span>Cargando módulos del sistema...</span></div>'
        );
        $('#modalPermisos').modal('show');

        $.get('usuarios.php', { accion: 'get_permisos', id_usuario: idUsuarioPermisos }, function (res) {
            if (!res.ok) {
                $('#permisosBody').html('<div style="color:#f87171;text-align:center;padding:30px;"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>Error al cargar permisos.</div>');
                return;
            }
            renderPermisos(res.permisos);
        }, 'json').fail(function () {
            $('#permisosBody').html('<div style="color:#f87171;text-align:center;padding:30px;"><i class="fas fa-wifi fa-2x mb-2"></i><br>Error de conexión.</div>');
        });
    });

    // ── Renderizar módulos con toggles ───────────────────────────────────────
    function renderPermisos(permisos) {
        // Agrupar
        var grupos = {};
        $.each(modulos, function (key, mod) {
            if (!grupos[mod.grupo]) grupos[mod.grupo] = [];
            grupos[mod.grupo].push({ key: key, label: mod.label, icon: mod.icon });
        });

        var html = '';
        $.each(grupos, function (grupo, items) {
            var c = coloresGrupo[grupo] || { bg:'rgba(255,255,255,.05)', border:'rgba(255,255,255,.1)', accent:'#94a3b8' };
            var gIcon = grupoIconos[grupo] || 'fas fa-circle';
            var activosGrupo = items.filter(function(i){ return permisos[i.key]; }).length;

            html += '<div class="perm-grupo">';
            // Header del grupo
            html += '<div class="perm-grupo-header" style="background:' + c.bg + ';border:1px solid ' + c.border + ';">';
            html += '<div class="perm-grupo-header-icon" style="background:' + c.bg + ';border:1px solid ' + c.border + ';">';
            html += '<i class="' + gIcon + '" style="color:' + c.accent + ';"></i></div>';
            html += '<span class="perm-grupo-title" style="color:' + c.accent + ';">' + grupo + '</span>';
            html += '<span class="perm-grupo-count" style="background:' + c.bg + ';border:1px solid ' + c.border + ';color:' + c.accent + ';">' + activosGrupo + '/' + items.length + '</span>';
            html += '</div>';

            // Grid de módulos
            html += '<div class="perm-modulos-grid">';
            $.each(items, function (i, item) {
                var isActive = !!permisos[item.key];
                var activeClass = isActive ? ' is-active' : '';
                html += '<div class="perm-modulo-card' + activeClass + '" data-modulo="' + item.key + '" data-grupo="' + grupo + '">';
                html += '<div class="perm-modulo-icon" style="' + (isActive ? 'background:' + c.bg + ';border-color:' + c.border + ';' : '') + '">';
                html += '<i class="' + item.icon + '" style="' + (isActive ? 'color:' + c.accent + ';' : '') + '"></i></div>';
                html += '<span class="perm-modulo-label">' + item.label + '</span>';
                html += '<label class="perm-toggle">';
                html += '<input type="checkbox" class="permiso-check" data-modulo="' + item.key + '" ' + (isActive ? 'checked' : '') + '>';
                html += '<span class="perm-toggle-slider" style="' + (isActive ? 'background:' + c.bg + ';border-color:' + c.border + ';' : '') + '"></span>';
                html += '</label>';
                html += '</div>';
            });
            html += '</div></div>';
        });

        $('#permisosBody').html(html);
        actualizarContador();
    }

    // ── Click en card (toggle) ───────────────────────────────────────────────
    $(document).on('click', '.perm-modulo-card', function (e) {
        // Evitar doble disparo si se hizo click en el label, el slider o el checkbox
        if ($(e.target).closest('.perm-toggle').length) return;
        var $chk = $(this).find('.permiso-check');
        $chk.prop('checked', !$chk.prop('checked')).trigger('change');
    });

    // ── Cambio de toggle ─────────────────────────────────────────────────────
    $(document).on('change', '.permiso-check', function () {
        var modulo = $(this).data('modulo');
        var grupo  = modulos[modulo] ? modulos[modulo].grupo : '';
        var c      = coloresGrupo[grupo] || { bg:'rgba(255,255,255,.05)', border:'rgba(255,255,255,.1)', accent:'#94a3b8' };
        var $card  = $(this).closest('.perm-modulo-card');
        var $icon  = $card.find('.perm-modulo-icon');
        var $iTag  = $icon.find('i');
        var $slider = $(this).next('.perm-toggle-slider');

        if ($(this).is(':checked')) {
            $card.addClass('is-active');
            $icon.css({ 'background': c.bg, 'border-color': c.border, 'box-shadow': '0 0 12px ' + c.bg });
            $iTag.css('color', c.accent);
            $slider.css({ 'background': c.bg, 'border-color': c.border, 'box-shadow': '0 0 8px ' + c.bg });
        } else {
            $card.removeClass('is-active');
            $icon.css({ 'background': 'rgba(255,255,255,.05)', 'border-color': 'rgba(255,255,255,.08)', 'box-shadow': 'none' });
            $iTag.css('color', '#64748b');
            $slider.css({ 'background': 'rgba(255,255,255,.1)', 'border-color': 'rgba(255,255,255,.12)', 'box-shadow': 'none' });
        }

        // Actualizar contador del grupo
        var $grupo = $card.closest('.perm-grupo');
        var total  = $grupo.find('.permiso-check').length;
        var activos = $grupo.find('.permiso-check:checked').length;
        $grupo.find('.perm-grupo-count').text(activos + '/' + total);

        actualizarContador();
    });

    // ── Permitir / Denegar todo ──────────────────────────────────────────────
    $('#btnPermisosAll').on('click', function () {
        $('.permiso-check').prop('checked', true).trigger('change');
    });
    $('#btnPermisosNone').on('click', function () {
        $('.permiso-check').prop('checked', false).trigger('change');
    });

    // ── Guardar permisos ─────────────────────────────────────────────────────
    $('#btnGuardarPermisos').on('click', function () {
        var $btn  = $(this).prop('disabled', true);
        var $text = $('#btnGuardarPermisosText');
        $text.text('Aplicando...');
        $btn.find('i').removeClass('fa-satellite-dish').addClass('fa-spinner fa-spin');

        var permitidos = [];
        $('.permiso-check:checked').each(function () {
            permitidos.push($(this).data('modulo'));
        });

        $.post('usuarios.php', {
            accion:       'guardar_permisos',
            id_usuario:   idUsuarioPermisos,
            'permisos[]': permitidos
        }, function (res) {
            $btn.prop('disabled', false);
            $text.text('Aplicar Permisos');
            $btn.find('i').removeClass('fa-spinner fa-spin').addClass('fa-satellite-dish');

            if (res.ok) {
                $('#modalPermisos').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Permisos aplicados',
                    text: 'Los permisos del usuario fueron actualizados correctamente.',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                    background: '#0d1117',
                    color: '#e2e8f0'
                });
            } else {
                Swal.fire({
                    icon: 'error', title: 'Error',
                    text: res.msg || 'No se pudieron guardar los permisos.',
                    confirmButtonColor: '#2563eb',
                    background: '#0d1117', color: '#e2e8f0'
                });
            }
        }, 'json').fail(function () {
            $btn.prop('disabled', false);
            $text.text('Aplicar Permisos');
            $btn.find('i').removeClass('fa-spinner fa-spin').addClass('fa-satellite-dish');
            Swal.fire({
                icon: 'error', title: 'Error de conexión',
                text: 'No se pudo conectar con el servidor.',
                confirmButtonColor: '#2563eb',
                background: '#0d1117', color: '#e2e8f0'
            });
        });
    });

    // ── Limpiar al cerrar ────────────────────────────────────────────────────
    $('#modalPermisos').on('hidden.bs.modal', function () {
        idUsuarioPermisos = 0;
        $('#permisosBody').html('');
        $(document).off('change', '.permiso-check');
        $(document).off('click', '.perm-modulo-card');
    });

})();
</script>
