<?php
// ============================================================
// modules/personas/proveedores.php | Botica 2026
// Campos BD: id_proveedor, razon_social, ruc, telefono,
//            email, direccion, contacto, estado, fecha_registro
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
verificarPermiso($pdo, 'proveedores');

// ── Patrón PRG: leer alertas de sesión ───────────────────────────────────────
$swal = null;
if (isset($_SESSION['swal_prov'])) {
    $swal = $_SESSION['swal_prov'];
    unset($_SESSION['swal_prov']);
}

// ── Helper: guardar alerta en sesión y redirigir ──────────────────────────────
function redirigirProv(string $icon, string $title, string $text): void {
    $_SESSION['swal_prov'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: proveedores.php');
    exit;
}

// ── CRUD ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion       = $_POST['accion']       ?? '';
    $id_proveedor = (int)($_POST['id_proveedor'] ?? 0);

    $razon_social = strtoupper(trim($_POST['razon_social'] ?? ''));
    $ruc          = trim($_POST['ruc']       ?? '');
    $telefono     = trim($_POST['telefono']  ?? '');
    $email        = trim($_POST['email']     ?? '') ?: null;
    $direccion    = trim($_POST['direccion'] ?? '');
    $contacto     = trim($_POST['contacto']  ?? '');
    $estado       = isset($_POST['estado']) ? (int)$_POST['estado'] : 1;

    try {
        if ($accion === 'crear') {
            if (empty($razon_social) || empty($ruc)) {
                redirigirProv('warning', 'Campos incompletos', 'Razón social y RUC son obligatorios.');
            }
            $sql = "INSERT INTO proveedores
                        (razon_social, ruc, telefono, email, direccion, contacto, estado)
                    VALUES (?,?,?,?,?,?,1)";
            $pdo->prepare($sql)->execute([
                $razon_social, $ruc, $telefono, $email, $direccion, $contacto
            ]);
            redirigirProv('success', '¡Registrado!', "El proveedor $razon_social fue registrado correctamente.");

        } elseif ($accion === 'actualizar' && $id_proveedor > 0) {
            $sql = "UPDATE proveedores
                    SET razon_social=?, ruc=?, telefono=?, email=?,
                        direccion=?, contacto=?, estado=?
                    WHERE id_proveedor=?";
            $pdo->prepare($sql)->execute([
                $razon_social, $ruc, $telefono, $email,
                $direccion, $contacto, $estado, $id_proveedor
            ]);
            redirigirProv('success', '¡Actualizado!', "Los datos del proveedor fueron actualizados correctamente.");

        } elseif ($accion === 'desactivar' && $id_proveedor > 0) {
            $pdo->prepare("UPDATE proveedores SET estado=0 WHERE id_proveedor=?")
                ->execute([$id_proveedor]);
            redirigirProv('info', 'Proveedor desactivado', "El proveedor fue desactivado y movido a la lista de inactivos.");

        } elseif ($accion === 'reactivar' && $id_proveedor > 0) {
            $pdo->prepare("UPDATE proveedores SET estado=1 WHERE id_proveedor=?")
                ->execute([$id_proveedor]);
            redirigirProv('success', '¡Reactivado!', "El proveedor fue reactivado y volvió a la lista de activos.");

        } elseif ($accion === 'eliminar_permanente' && $id_proveedor > 0) {
            // Verificar si tiene compras asociadas
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM compras WHERE id_proveedor=?");
            $stmt->execute([$id_proveedor]);
            if ($stmt->fetchColumn() > 0) {
                redirigirProv('error', 'No se puede eliminar', "Este proveedor tiene compras registradas. Use Desactivar en su lugar.");
            }
            $pdo->prepare("DELETE FROM proveedores WHERE id_proveedor=?")->execute([$id_proveedor]);
            redirigirProv('success', 'Eliminado', "El proveedor fue eliminado permanentemente de la base de datos.");
        }

    } catch (PDOException $e) {
        $msg = ($e->getCode() == 23000)
            ? 'El RUC o correo ya está registrado en el sistema.'
            : 'Error de base de datos: ' . $e->getMessage();
        redirigirProv('error', 'Error', $msg);
    }
}

// ── DATOS ────────────────────────────────────────────────────────────────────
$proveedores_activos   = [];
$proveedores_inactivos = [];

try {
    $proveedores_activos   = $pdo->query("SELECT * FROM proveedores WHERE estado=1 ORDER BY id_proveedor ASC")->fetchAll();
    $proveedores_inactivos = $pdo->query("SELECT * FROM proveedores WHERE estado=0 ORDER BY id_proveedor ASC")->fetchAll();
} catch (PDOException $e) {
    $swal = ['icon' => 'error', 'title' => 'Error', 'text' => 'Error al cargar proveedores: ' . $e->getMessage()];
}

$total_activos   = count($proveedores_activos);
$total_inactivos = count($proveedores_inactivos);

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>

<!-- CSS del módulo -->
<link rel="stylesheet" href="css/proveedores.css">

<div class="content-wrapper">

    <!-- ── CABECERA ── -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-prov d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4><i class="fas fa-truck mr-2"></i>Gestión de Proveedores</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>Botica 2026 &rsaquo; Personas &rsaquo; Proveedores</small>
                </div>
                <button class="btn btn-light font-weight-bold" data-toggle="modal" data-target="#modalCrearProveedor">
                    <i class="fas fa-plus-circle mr-1"></i> Nuevo Proveedor
                </button>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- ── STATS ── -->
            <div class="row mb-4">
                <div class="col-md-4 col-6 mb-2">
                    <div class="stat-mini" style="background:linear-gradient(135deg,#1a5276,#2980b9);">
                        <i class="fas fa-truck"></i>
                        <div>
                            <div class="stat-value"><?= $total_activos ?></div>
                            <div class="stat-label">Proveedores activos</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6 mb-2">
                    <div class="stat-mini" style="background:linear-gradient(135deg,#922b21,#e74c3c);">
                        <i class="fas fa-ban"></i>
                        <div>
                            <div class="stat-value"><?= $total_inactivos ?></div>
                            <div class="stat-label">Proveedores inactivos</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-6 mb-2">
                    <div class="stat-mini" style="background:linear-gradient(135deg,#0e6655,#1abc9c);">
                        <i class="fas fa-building"></i>
                        <div>
                            <div class="stat-value"><?= $total_activos + $total_inactivos ?></div>
                            <div class="stat-label">Total registrados</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── ALERTA SWAL ── -->
            <?php if ($swal): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon:              '<?= $swal['icon'] ?>',
                    title:             '<?= addslashes($swal['title']) ?>',
                    text:              '<?= addslashes($swal['text']) ?>',
                    confirmButtonColor:'#1a5276',
                    timer:             <?= in_array($swal['icon'], ['success','info']) ? 3000 : 0 ?>,
                    timerProgressBar:  <?= in_array($swal['icon'], ['success','info']) ? 'true' : 'false' ?>,
                    showConfirmButton:  <?= in_array($swal['icon'], ['success','info']) ? 'false' : 'true' ?>,
                });
            });
            </script>
            <?php endif; ?>

            <!-- ── CARD PRINCIPAL ── -->
            <div class="card">
                <div class="card-header-prov d-flex align-items-center justify-content-between">
                    <h6><i class="fas fa-list mr-2"></i>Listado de Proveedores</h6>
                    <ul class="nav mb-0" style="border:none;">
                        <li class="nav-item">
                            <a class="nav-link active text-white font-weight-bold" data-toggle="pill" href="#tab-activos-prov">
                                <i class="fas fa-check-circle mr-1"></i>Activos
                                <span class="badge badge-light ml-1"><?= $total_activos ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white font-weight-bold" data-toggle="pill" href="#tab-inactivos-prov">
                                <i class="fas fa-ban mr-1"></i>Inactivos
                                <span class="badge badge-light ml-1"><?= $total_inactivos ?></span>
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="card-body">
                    <div class="tab-content">

                        <!-- ACTIVOS -->
                        <div class="tab-pane fade show active" id="tab-activos-prov">
                            <div class="table-responsive">
                                <table id="tablaProveedoresActivos" class="table table-proveedores table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Razón Social</th>
                                            <th>RUC</th>
                                            <th>Teléfono</th>
                                            <th>Email</th>
                                            <th>Contacto</th>
                                            <th>Registro</th>
                                            <th class="text-center" style="width:120px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($proveedores_activos as $p): ?>
                                        <tr>
                                            <td><?= $p['id_proveedor'] ?></td>
                                            <td class="font-weight-bold"><?= htmlspecialchars($p['razon_social']) ?></td>
                                            <td><span class="badge-ruc"><?= htmlspecialchars($p['ruc']) ?></span></td>
                                            <td><?= htmlspecialchars($p['telefono'] ?? '—') ?></td>
                                            <td><?= htmlspecialchars($p['email'] ?? '—') ?></td>
                                            <td><?= htmlspecialchars($p['contacto'] ?? '—') ?></td>
                                            <td><small class="text-muted"><?= date('d/m/Y', strtotime($p['fecha_registro'])) ?></small></td>
                                            <td class="text-center">
                                                <!-- VER -->
                                                <button class="btn btn-sm btn-info btn-ver-proveedor" title="Ver detalle"
                                                    data-id="<?= $p['id_proveedor'] ?>"
                                                    data-razon-social="<?= htmlspecialchars($p['razon_social'], ENT_QUOTES) ?>"
                                                    data-ruc="<?= htmlspecialchars($p['ruc'], ENT_QUOTES) ?>"
                                                    data-telefono="<?= htmlspecialchars($p['telefono'] ?? '', ENT_QUOTES) ?>"
                                                    data-email="<?= htmlspecialchars($p['email'] ?? '', ENT_QUOTES) ?>"
                                                    data-direccion="<?= htmlspecialchars($p['direccion'] ?? '', ENT_QUOTES) ?>"
                                                    data-contacto="<?= htmlspecialchars($p['contacto'] ?? '', ENT_QUOTES) ?>"
                                                    data-estado="<?= $p['estado'] ?>"
                                                    data-fecha="<?= date('d/m/Y H:i', strtotime($p['fecha_registro'])) ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <!-- EDITAR -->
                                                <button class="btn btn-sm btn-warning btn-editar-proveedor" title="Editar"
                                                    data-id="<?= $p['id_proveedor'] ?>"
                                                    data-razon-social="<?= htmlspecialchars($p['razon_social'], ENT_QUOTES) ?>"
                                                    data-ruc="<?= htmlspecialchars($p['ruc'], ENT_QUOTES) ?>"
                                                    data-telefono="<?= htmlspecialchars($p['telefono'] ?? '', ENT_QUOTES) ?>"
                                                    data-email="<?= htmlspecialchars($p['email'] ?? '', ENT_QUOTES) ?>"
                                                    data-direccion="<?= htmlspecialchars($p['direccion'] ?? '', ENT_QUOTES) ?>"
                                                    data-contacto="<?= htmlspecialchars($p['contacto'] ?? '', ENT_QUOTES) ?>"
                                                    data-estado="<?= $p['estado'] ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <!-- ELIMINAR -->
                                                <button class="btn btn-sm btn-danger btn-eliminar-proveedor" title="Eliminar"
                                                    data-id="<?= $p['id_proveedor'] ?>"
                                                    data-nombre="<?= htmlspecialchars($p['razon_social'], ENT_QUOTES) ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- INACTIVOS -->
                        <div class="tab-pane fade" id="tab-inactivos-prov">
                            <div class="table-responsive">
                                <table id="tablaProveedoresInactivos" class="table table-proveedores table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Razón Social</th>
                                            <th>RUC</th>
                                            <th>Teléfono</th>
                                            <th>Registro</th>
                                            <th class="text-center" style="width:120px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($proveedores_inactivos as $p): ?>
                                        <tr class="table-light">
                                            <td><?= $p['id_proveedor'] ?></td>
                                            <td class="text-muted"><?= htmlspecialchars($p['razon_social']) ?></td>
                                            <td><span class="badge-ruc"><?= htmlspecialchars($p['ruc']) ?></span></td>
                                            <td><?= htmlspecialchars($p['telefono'] ?? '—') ?></td>
                                            <td><small class="text-muted"><?= date('d/m/Y', strtotime($p['fecha_registro'])) ?></small></td>
                                            <td class="text-center">
                                                <!-- REACTIVAR -->
                                                <button class="btn btn-sm btn-success btn-reactivar-proveedor" title="Reactivar"
                                                    data-id="<?= $p['id_proveedor'] ?>"
                                                    data-nombre="<?= htmlspecialchars($p['razon_social'], ENT_QUOTES) ?>">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                                <!-- ELIMINAR PERMANENTE -->
                                                <button class="btn btn-sm btn-danger btn-eliminar-permanente-prov" title="Eliminar permanentemente"
                                                    data-id="<?= $p['id_proveedor'] ?>"
                                                    data-nombre="<?= htmlspecialchars($p['razon_social'], ENT_QUOTES) ?>">
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
     MODAL VER PROVEEDOR
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalVerProveedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">

            <!-- Cabecera con avatar -->
            <div style="background:linear-gradient(135deg,#1a5276 0%,#2980b9 100%);padding:28px 24px 20px;text-align:center;position:relative;">
                <button type="button" class="close" data-dismiss="modal"
                    style="position:absolute;top:12px;right:16px;color:#fff;opacity:.8;font-size:1.4rem;">&times;</button>
                <div style="width:72px;height:72px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                    <i class="fas fa-building" style="font-size:2rem;color:#fff;"></i>
                </div>
                <h5 id="ver_razon_social" style="color:#fff;font-weight:700;margin:0;font-size:1.05rem;line-height:1.3;"></h5>
                <div class="mt-2">
                    <span id="ver_ruc" style="background:rgba(255,255,255,.25);color:#fff;font-size:.78rem;padding:4px 12px;border-radius:20px;font-family:monospace;font-weight:600;display:inline-block;"></span>
                </div>
            </div>

            <!-- Cuerpo con campos -->
            <div class="modal-body" style="padding:20px 24px;">
                <div class="row">

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

                    <div class="col-6 mb-3">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <div style="width:34px;height:34px;background:#e8f5e9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-user-tie" style="color:#1a7a4a;font-size:.85rem;"></i>
                            </div>
                            <div>
                                <div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Contacto</div>
                                <div id="ver_contacto" style="font-size:.9rem;color:#2d3436;"></div>
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
                                <div id="ver_estado" style="font-size:.9rem;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mb-1">
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
     MODAL CREAR PROVEEDOR
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCrearProveedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-prov">
                <h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i>Registrar Nuevo Proveedor</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="crear">
                <div class="modal-body">

                    <!-- RUC + Consulta SUNAT -->
                    <div class="form-group">
                        <label class="form-label-prov"><i class="fas fa-building mr-1 text-muted"></i>RUC <span class="text-danger">*</span></label>
                        <div class="input-group input-ruc-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-hashtag"></i></span></div>
                            <input type="text" class="form-control form-control-sm" name="ruc"
                                   id="crear_ruc" maxlength="11" placeholder="20123456789" required>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-sunat btn-sm" id="btn_sunat_crear">
                                    <i class="fas fa-search mr-1"></i> Consultar SUNAT
                                </button>
                            </div>
                        </div>
                        <div class="sunat-result mt-2" id="sunat_result_crear">
                            <i class="fas fa-check-circle text-success mr-1"></i>
                            <span class="sunat-razon"></span><br>
                            <span class="sunat-estado text-muted"></span>
                        </div>
                    </div>

                    <!-- Razón Social -->
                    <div class="form-group">
                        <label class="form-label-prov"><i class="fas fa-building mr-1 text-muted"></i>Razón Social <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-industry"></i></span></div>
                            <input type="text" class="form-control" name="razon_social"
                                   id="crear_razon_social" maxlength="150" required placeholder="LABORATORIOS S.A.C.">
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4">
                            <label class="form-label-prov"><i class="fas fa-phone-alt mr-1 text-muted"></i>Teléfono</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-phone"></i></span></div>
                                <input type="text" class="form-control" name="telefono"
                                       maxlength="20" placeholder="01-234-5678">
                            </div>
                        </div>
                        <div class="form-group col-md-8">
                            <label class="form-label-prov"><i class="fas fa-envelope mr-1 text-muted"></i>Email</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-at"></i></span></div>
                                <input type="email" class="form-control" name="email"
                                       maxlength="100" placeholder="ventas@proveedor.com">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label-prov"><i class="fas fa-map-marker-alt mr-1 text-muted"></i>Dirección</label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-map-pin"></i></span></div>
                            <input type="text" class="form-control" name="direccion"
                                   id="crear_direccion" maxlength="150" placeholder="Av. Industrial 456, Lima">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label-prov"><i class="fas fa-user-tie mr-1 text-muted"></i>Persona de Contacto</label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                            <input type="text" class="form-control" name="contacto"
                                   maxlength="100" placeholder="Nombre del representante">
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-prov-primary btn-sm">
                        <i class="fas fa-save mr-1"></i>Guardar Proveedor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL EDITAR PROVEEDOR
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditarProveedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#a04000,#e67e22);color:#fff;border-radius:10px 10px 0 0;">
                <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Editar Proveedor</h5>
                <button type="button" class="close" style="color:#fff;" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="actualizar">
                <input type="hidden" name="id_proveedor" id="editar_id_proveedor">
                <div class="modal-body">

                    <!-- RUC + Consulta SUNAT -->
                    <div class="form-group">
                        <label class="form-label-prov"><i class="fas fa-building mr-1 text-muted"></i>RUC <span class="text-danger">*</span></label>
                        <div class="input-group input-ruc-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-hashtag"></i></span></div>
                            <input type="text" class="form-control form-control-sm" name="ruc"
                                   id="editar_ruc" maxlength="11" required>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-sunat btn-sm" id="btn_sunat_editar">
                                    <i class="fas fa-search mr-1"></i> Consultar SUNAT
                                </button>
                            </div>
                        </div>
                        <div class="sunat-result mt-2" id="sunat_result_editar">
                            <i class="fas fa-check-circle text-success mr-1"></i>
                            <span class="sunat-razon"></span><br>
                            <span class="sunat-estado text-muted"></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label-prov"><i class="fas fa-building mr-1 text-muted"></i>Razón Social <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-industry"></i></span></div>
                            <input type="text" class="form-control" name="razon_social"
                                   id="editar_razon_social" maxlength="150" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group col-md-4">
                            <label class="form-label-prov"><i class="fas fa-phone-alt mr-1 text-muted"></i>Teléfono</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-phone"></i></span></div>
                                <input type="text" class="form-control" name="telefono"
                                       id="editar_telefono" maxlength="20">
                            </div>
                        </div>
                        <div class="form-group col-md-5">
                            <label class="form-label-prov"><i class="fas fa-envelope mr-1 text-muted"></i>Email</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-at"></i></span></div>
                                <input type="email" class="form-control" name="email"
                                       id="editar_email" maxlength="100">
                            </div>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="form-label-prov"><i class="fas fa-toggle-on mr-1 text-muted"></i>Estado</label>
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
                        <label class="form-label-prov"><i class="fas fa-map-marker-alt mr-1 text-muted"></i>Dirección</label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-map-pin"></i></span></div>
                            <input type="text" class="form-control" name="direccion"
                                   id="editar_direccion" maxlength="150">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label-prov"><i class="fas fa-user-tie mr-1 text-muted"></i>Persona de Contacto</label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                            <input type="text" class="form-control" name="contacto"
                                   id="editar_contacto" maxlength="100">
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
<script src="js/proveedores.js"></script>
