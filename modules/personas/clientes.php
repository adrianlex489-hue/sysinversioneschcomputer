<?php
// ============================================================
// modules/personas/clientes.php | SysInversiones CH Computer
// Dos vistas: Personas Naturales (clientes_natural)
//             Empresas/Jurídicas  (clientes_empresa)
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
verificarPermiso($pdo, 'clientes');

// ── Alerta sesión ─────────────────────────────────────────────────────────────
$swal = null;
if (isset($_SESSION['swal_cli'])) { $swal = $_SESSION['swal_cli']; unset($_SESSION['swal_cli']); }

function redirigirCli(string $icon, string $title, string $text, string $tab = 'natural'): void {
    $_SESSION['swal_cli'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: clientes.php?tab=' . $tab);
    exit;
}

// ── Tab activo (para mantener la vista tras POST) ─────────────────────────────
$tab_activo = $_GET['tab'] ?? 'natural';
if (!in_array($tab_activo, ['natural', 'empresa'])) $tab_activo = 'natural';

// ══════════════════════════════════════════════════════════════════════════════
// CRUD
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $tabla  = ($_POST['tabla'] ?? 'natural') === 'empresa' ? 'empresa' : 'natural';
    $tab_r  = $tabla; // para redirigir al tab correcto

    // ── Campos persona natural ────────────────────────────────────────────────
    $nombres          = strtoupper(trim($_POST['nombres']          ?? ''));
    $apellido_paterno = strtoupper(trim($_POST['apellido_paterno'] ?? ''));
    $apellido_materno = strtoupper(trim($_POST['apellido_materno'] ?? ''));
    $tipo_documento   = trim($_POST['tipo_documento'] ?? 'DNI');
    $dni              = trim($_POST['documento_identidad'] ?? '');

    // ── Campos empresa ────────────────────────────────────────────────────────
    $razon_social = strtoupper(trim($_POST['razon_social'] ?? ''));
    $ruc          = trim($_POST['ruc'] ?? '');

    // ── Comunes ───────────────────────────────────────────────────────────────
    $telefono       = trim($_POST['telefono']  ?? '');
    $email          = trim($_POST['email']     ?? '') ?: null;
    $direccion      = trim($_POST['direccion'] ?? '');
    $estado_cliente = isset($_POST['estado_cliente']) ? (int)$_POST['estado_cliente'] : 1;

    // ID según tabla
    $id_campo = ($tabla === 'empresa') ? 'id_cliente_empresa' : 'id_cliente_natural';
    $id_val   = (int)($_POST['id_cliente'] ?? 0);

    try {
        // ── CREAR ─────────────────────────────────────────────────────────────
        if ($accion === 'crear') {
            if ($tabla === 'natural') {
                if (empty($nombres) || empty($apellido_paterno) || empty($dni))
                    redirigirCli('warning', 'Campos incompletos', 'Nombres, apellido paterno y DNI son obligatorios.', $tab_r);
                $pdo->prepare("INSERT INTO clientes_natural
                    (nombres, apellido_paterno, apellido_materno, tipo_documento, documento_identidad,
                     telefono, email, direccion, estado_cliente, fecha_registro)
                    VALUES (?,?,?,?,?,?,?,?,?,NOW())")
                ->execute([$nombres, $apellido_paterno, $apellido_materno ?: null,
                           $tipo_documento, $dni, $telefono, $email, $direccion, $estado_cliente]);
                redirigirCli('success', '¡Registrado!', "El cliente \"$nombres $apellido_paterno\" fue registrado.", $tab_r);
            } else {
                if (empty($razon_social) || empty($ruc))
                    redirigirCli('warning', 'Campos incompletos', 'Razón social y RUC son obligatorios.', $tab_r);
                $pdo->prepare("INSERT INTO clientes_empresa
                    (razon_social, ruc, telefono, email, direccion, estado_cliente, fecha_registro)
                    VALUES (?,?,?,?,?,?,NOW())")
                ->execute([$razon_social, $ruc, $telefono, $email, $direccion, $estado_cliente]);
                redirigirCli('success', '¡Registrado!', "La empresa \"$razon_social\" fue registrada.", $tab_r);
            }

        // ── ACTUALIZAR ────────────────────────────────────────────────────────
        } elseif ($accion === 'actualizar' && $id_val > 0) {
            if ($tabla === 'natural') {
                $pdo->prepare("UPDATE clientes_natural
                    SET nombres=?, apellido_paterno=?, apellido_materno=?, tipo_documento=?,
                        documento_identidad=?, telefono=?, email=?, direccion=?, estado_cliente=?
                    WHERE id_cliente_natural=?")
                ->execute([$nombres, $apellido_paterno, $apellido_materno ?: null,
                           $tipo_documento, $dni, $telefono, $email, $direccion,
                           $estado_cliente, $id_val]);
            } else {
                $pdo->prepare("UPDATE clientes_empresa
                    SET razon_social=?, ruc=?, telefono=?, email=?, direccion=?, estado_cliente=?
                    WHERE id_cliente_empresa=?")
                ->execute([$razon_social, $ruc, $telefono, $email, $direccion,
                           $estado_cliente, $id_val]);
            }
            redirigirCli('success', '¡Actualizado!', 'Los datos fueron actualizados correctamente.', $tab_r);

        // ── DESACTIVAR ────────────────────────────────────────────────────────
        } elseif ($accion === 'desactivar' && $id_val > 0) {
            $t = ($tabla === 'empresa') ? 'clientes_empresa' : 'clientes_natural';
            $c = ($tabla === 'empresa') ? 'id_cliente_empresa' : 'id_cliente_natural';
            $pdo->prepare("UPDATE $t SET estado_cliente=0 WHERE $c=?")->execute([$id_val]);
            redirigirCli('info', 'Desactivado', 'El cliente fue movido a inactivos.', $tab_r);

        // ── REACTIVAR ─────────────────────────────────────────────────────────
        } elseif ($accion === 'reactivar' && $id_val > 0) {
            $t = ($tabla === 'empresa') ? 'clientes_empresa' : 'clientes_natural';
            $c = ($tabla === 'empresa') ? 'id_cliente_empresa' : 'id_cliente_natural';
            $pdo->prepare("UPDATE $t SET estado_cliente=1 WHERE $c=?")->execute([$id_val]);
            redirigirCli('success', '¡Reactivado!', 'El cliente volvió a la lista de activos.', $tab_r);

        // ── ELIMINAR PERMANENTE ───────────────────────────────────────────────
        } elseif ($accion === 'eliminar_permanente' && $id_val > 0) {
            // Verificar si tiene ventas u órdenes
            $col_tipo = ($tabla === 'empresa') ? 'empresa' : 'natural';
            $stV = $pdo->prepare("SELECT COUNT(*) FROM ventas WHERE id_cliente=? AND tipo_cliente=?");
            $stV->execute([$id_val, $col_tipo]);
            if ($stV->fetchColumn() > 0)
                redirigirCli('error', 'No se puede eliminar', 'Este cliente tiene ventas registradas. Use Desactivar.', $tab_r);

            $stO = $pdo->prepare("SELECT COUNT(*) FROM ordenes_servicio WHERE id_cliente=? AND tipo_cliente=?");
            $stO->execute([$id_val, $col_tipo]);
            if ($stO->fetchColumn() > 0)
                redirigirCli('error', 'No se puede eliminar', 'Este cliente tiene órdenes de servicio. Use Desactivar.', $tab_r);

            $t = ($tabla === 'empresa') ? 'clientes_empresa' : 'clientes_natural';
            $c = ($tabla === 'empresa') ? 'id_cliente_empresa' : 'id_cliente_natural';
            $pdo->prepare("DELETE FROM $t WHERE $c=?")->execute([$id_val]);
            redirigirCli('success', 'Eliminado', 'El cliente fue eliminado permanentemente.', $tab_r);
        }

    } catch (PDOException $e) {
        $msg = ($e->getCode() == 23000)
            ? 'El documento ya está registrado en el sistema.'
            : 'Error de base de datos: ' . $e->getMessage();
        redirigirCli('error', 'Error', $msg, $tab_r);
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// DATOS
// ══════════════════════════════════════════════════════════════════════════════
$nat_activos = $nat_inactivos = $emp_activos = $emp_inactivos = [];
try {
    $nat_activos   = $pdo->query("SELECT * FROM clientes_natural WHERE estado_cliente=1 ORDER BY apellido_paterno ASC")->fetchAll();
    $nat_inactivos = $pdo->query("SELECT * FROM clientes_natural WHERE estado_cliente=0 ORDER BY apellido_paterno ASC")->fetchAll();
    $emp_activos   = $pdo->query("SELECT * FROM clientes_empresa WHERE estado_cliente=1 ORDER BY razon_social ASC")->fetchAll();
    $emp_inactivos = $pdo->query("SELECT * FROM clientes_empresa WHERE estado_cliente=0 ORDER BY razon_social ASC")->fetchAll();
} catch (PDOException $e) {
    $swal = ['icon'=>'error','title'=>'Error','text'=>'Error al cargar datos: '.$e->getMessage()];
}

$total_nat = count($nat_activos) + count($nat_inactivos);
$total_emp = count($emp_activos) + count($emp_inactivos);

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<link rel="stylesheet" href="css/clientes.css?v=<?= time() ?>">
<style>
/* ── Tabs principales (Natural / Empresa) ── */
.tabs-tipo-cliente { display:flex; gap:0; margin-bottom:0; border-bottom:none; }
.tab-tipo-btn {
    flex:1; padding:13px 20px; font-size:.92rem; font-weight:700;
    border:none; cursor:pointer; transition:all .25s;
    display:flex; align-items:center; justify-content:center; gap:8px;
}
.tab-tipo-btn.natural  { background:linear-gradient(135deg,#1a5276,#2563eb); color:#fff; border-radius:10px 0 0 0; }
.tab-tipo-btn.empresa  { background:#e9ecef; color:#6b7280; border-radius:0 10px 0 0; }
.tab-tipo-btn.natural.inactivo { background:#e9ecef; color:#6b7280; border-radius:10px 0 0 0; }
.tab-tipo-btn.empresa.activo   { background:linear-gradient(135deg,#7b341e,#c05621); color:#fff; border-radius:0 10px 0 0; }
.tab-tipo-btn .badge-tab { background:rgba(255,255,255,.25); color:#fff; border-radius:20px; padding:2px 8px; font-size:.75rem; }
.tab-tipo-btn.inactivo .badge-tab { background:rgba(0,0,0,.1); color:#6b7280; }
/* Paneles */
.panel-tipo { display:none; }
.panel-tipo.activo { display:block; }
/* Card header por tipo */
.card-header-nat { background:linear-gradient(90deg,#1a5276,#2563eb)!important; color:#fff!important; border-radius:0 0 0 0!important; padding:12px 18px!important; }
.card-header-emp { background:linear-gradient(90deg,#7b341e,#c05621)!important; color:#fff!important; border-radius:0 0 0 0!important; padding:12px 18px!important; }
.card-header-nat h6, .card-header-emp h6 { margin:0!important; font-weight:700!important; font-size:.95rem!important; color:#fff!important; }
.card-header-nat .nav-link, .card-header-emp .nav-link { color:rgba(255,255,255,.8)!important; font-weight:600; border:none!important; }
.card-header-nat .nav-link.active { color:#fff!important; background:rgba(255,255,255,.2)!important; border-radius:5px; }
.card-header-emp .nav-link.active { color:#fff!important; background:rgba(255,255,255,.2)!important; border-radius:5px; }
/* Badge RUC */
.badge-ruc { background:#fde8d8; color:#c05621; border:1px solid #f5cba7; font-size:.78rem; padding:3px 8px; border-radius:20px; font-weight:600; font-family:monospace; display:inline-block; white-space:nowrap; }
/* Modal persona natural — iconos azul */
#modalCrearNatural .input-group-text, #modalEditarNatural .input-group-text {
    background:#1a5276!important; border-color:#1a5276!important; color:#fff!important; min-width:34px; justify-content:center;
}
#modalCrearNatural .form-control:focus, #modalCrearNatural .form-control:focus,
#modalEditarNatural .form-control:focus, #modalEditarNatural select:focus {
    border-color:#2563eb!important; box-shadow:0 0 0 .2rem rgba(37,99,235,.18)!important;
}
/* Modal empresa — iconos naranja */
#modalCrearEmpresa .modal-header, #modalEditarEmpresa .modal-header { background:linear-gradient(135deg,#7b341e,#c05621)!important; color:#fff!important; border-radius:10px 10px 0 0!important; border-bottom:none!important; }
#modalCrearEmpresa .modal-header .modal-title, #modalEditarEmpresa .modal-header .modal-title { color:#fff!important; }
#modalCrearEmpresa .modal-header .close, #modalEditarEmpresa .modal-header .close { color:#fff!important; opacity:.8!important; text-shadow:none!important; }
#modalCrearEmpresa .input-group-text, #modalEditarEmpresa .input-group-text {
    background:#c05621!important; border-color:#c05621!important; color:#fff!important; min-width:34px; justify-content:center;
}
#modalCrearEmpresa .form-control:focus, #modalEditarEmpresa .form-control:focus { border-color:#c05621!important; box-shadow:0 0 0 .2rem rgba(192,86,33,.2)!important; }
.btn-emp-primary { background:linear-gradient(135deg,#7b341e,#c05621)!important; color:#fff!important; border:none!important; border-radius:7px; font-weight:600; }
.btn-emp-primary:hover { background:linear-gradient(135deg,#6b2d1a,#a84d1e)!important; }
/* Stats empresa */
.stat-mini.bg-orange { background:linear-gradient(135deg,#c05621,#e67e22)!important; }</style>

<div class="content-wrapper">
    <!-- CABECERA -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-cli d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4><i class="fas fa-users mr-2"></i>Gestión de Clientes</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones &rsaquo; Personas &rsaquo; Clientes</small>
                </div>
                <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
                    <!-- Botones de exportación -->
                    <div class="cli-export-group">
                        <button id="btn-exportar-csv" class="cli-export-btn cli-export-csv" title="Exportar a CSV">
                            <i class="fas fa-file-csv"></i>
                            <span>CSV</span>
                        </button>
                        <button id="btn-exportar-excel" class="cli-export-btn cli-export-excel" title="Exportar a Excel">
                            <i class="fas fa-file-excel"></i>
                            <span>Excel</span>
                        </button>
                        <button id="btn-exportar-pdf" class="cli-export-btn cli-export-pdf" title="Exportar a PDF">
                            <i class="fas fa-file-pdf"></i>
                            <span>PDF</span>
                        </button>
                    </div>
                    <!-- Botones de importación -->
                    <div class="dropdown">
                        <button class="btn btn-light font-weight-bold dropdown-toggle" type="button" id="dropImportar" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-file-import mr-1"></i> Importar Excel
                        </button>
                        <div class="dropdown-menu dropdown-menu-right shadow-sm" aria-labelledby="dropImportar" style="min-width:230px;">
                            <h6 class="dropdown-header"><i class="fas fa-upload mr-1"></i>Importar clientes</h6>
                            <a class="dropdown-item" href="#" id="btnImportarNatural">
                                <i class="fas fa-user text-primary mr-2"></i>Personas Naturales
                            </a>
                            <a class="dropdown-item" href="#" id="btnImportarEmpresa">
                                <i class="fas fa-building text-warning mr-2"></i>Personas Jurídicas
                            </a>
                            <div class="dropdown-divider"></div>
                            <h6 class="dropdown-header"><i class="fas fa-download mr-1"></i>Descargar plantilla</h6>
                            <a class="dropdown-item" href="ajax_clientes_plantilla.php?tipo=natural" target="_blank">
                                <i class="fas fa-file-excel text-success mr-2"></i>Plantilla Naturales
                            </a>
                            <a class="dropdown-item" href="ajax_clientes_plantilla.php?tipo=empresa" target="_blank">
                                <i class="fas fa-file-excel text-success mr-2"></i>Plantilla Jurídicas
                            </a>
                        </div>
                    </div>
                    <!-- Botones de creación -->
                    <button class="btn btn-light font-weight-bold mr-1" id="btnNuevoNatural" data-toggle="modal" data-target="#modalCrearNatural">
                        <i class="fas fa-user-plus mr-1"></i> Nueva Persona Natural
                    </button>
                    <button class="btn btn-light font-weight-bold" id="btnNuevoEmpresa" data-toggle="modal" data-target="#modalCrearEmpresa">
                        <i class="fas fa-building mr-1"></i> Nueva Empresa
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- STATS — 5 tarjetas en una sola fila -->
            <div class="row mb-4 stats-clientes-row">
                <div class="col mb-2">
                    <div class="stat-mini stat-nat-activo">
                        <i class="fas fa-user-check"></i>
                        <div>
                            <div class="stat-value"><?= count($nat_activos) ?></div>
                            <div class="stat-label">Naturales activos</div>
                        </div>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="stat-mini stat-nat-inactivo">
                        <i class="fas fa-user-slash"></i>
                        <div>
                            <div class="stat-value"><?= count($nat_inactivos) ?></div>
                            <div class="stat-label">Naturales inactivos</div>
                        </div>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="stat-mini stat-jur-activo">
                        <i class="fas fa-building"></i>
                        <div>
                            <div class="stat-value"><?= count($emp_activos) ?></div>
                            <div class="stat-label">Jurídicos activos</div>
                        </div>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="stat-mini stat-jur-inactivo">
                        <i class="fas fa-store-slash"></i>
                        <div>
                            <div class="stat-value"><?= count($emp_inactivos) ?></div>
                            <div class="stat-label">Jurídicos inactivos</div>
                        </div>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="stat-mini stat-total">
                        <i class="fas fa-users"></i>
                        <div>
                            <div class="stat-value"><?= $total_nat + $total_emp ?></div>
                            <div class="stat-label">Total registrados</div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($swal): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon: '<?= $swal['icon'] ?>', title: '<?= addslashes($swal['title']) ?>',
                    text: '<?= addslashes($swal['text']) ?>', confirmButtonColor:'#1a7a4a',
                    timer: <?= in_array($swal['icon'],['success','info']) ? 3000 : 0 ?>,
                    timerProgressBar: <?= in_array($swal['icon'],['success','info']) ? 'true' : 'false' ?>,
                    showConfirmButton: <?= in_array($swal['icon'],['success','info']) ? 'false' : 'true' ?>,
                });
            });
            </script>
            <?php endif; ?>

            <!-- CARD CON TABS PRINCIPALES -->
            <div class="card shadow-sm" style="border-radius:10px;overflow:hidden;">

                <!-- Tabs tipo -->
                <div class="tabs-tipo-cliente">
                    <button class="tab-tipo-btn natural <?= $tab_activo==='natural' ? '' : 'inactivo' ?>" id="btnTabNatural">
                        <i class="fas fa-user"></i> Personas Naturales
                        <span class="badge-tab"><?= $total_nat ?></span>
                    </button>
                    <button class="tab-tipo-btn empresa <?= $tab_activo==='empresa' ? 'activo' : '' ?>" id="btnTabEmpresa">
                        <i class="fas fa-building"></i> Empresas / Jurídicas
                        <span class="badge-tab"><?= $total_emp ?></span>
                    </button>
                </div>

                <!-- ══════════════════════════════════════════════════════
                     PANEL: PERSONAS NATURALES
                ══════════════════════════════════════════════════════ -->
                <div class="panel-tipo <?= $tab_activo==='natural' ? 'activo' : '' ?>" id="panelNatural">
                    <div class="card-header-nat d-flex align-items-center justify-content-between">
                        <h6><i class="fas fa-id-card mr-2"></i>Personas Naturales — DNI</h6>
                        <ul class="nav nav-tabs card-header-tabs ml-auto" id="tabsNat" style="border-bottom:none;">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#nat-activos">
                                    <i class="fas fa-check-circle mr-1"></i>Activos
                                    <span class="badge badge-light ml-1"><?= count($nat_activos) ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#nat-inactivos">
                                    <i class="fas fa-ban mr-1"></i>Inactivos
                                    <span class="badge badge-light ml-1"><?= count($nat_inactivos) ?></span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">

                            <!-- Naturales Activos -->
                            <div class="tab-pane fade show active" id="nat-activos">
                                <div class="table-responsive">
                                    <table id="tblNatActivos" class="table table-clientes table-bordered table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Apellidos y Nombres</th>
                                                <th>Documento</th>
                                                <th>Teléfono</th>
                                                <th>Email</th>
                                                <th>Dirección</th>
                                                <th>Registro</th>
                                                <th class="text-center" style="width:110px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($nat_activos as $c): ?>
                                            <tr>
                                                <td><?= $c['id_cliente_natural'] ?></td>
                                                <td class="font-weight-bold">
                                                    <i class="fas fa-user text-primary mr-1"></i>
                                                    <?= htmlspecialchars(trim($c['apellido_paterno'].' '.($c['apellido_materno']??'').', '.$c['nombres'])) ?>
                                                </td>
                                                <td><span class="badge-dni"><?= htmlspecialchars($c['tipo_documento']) ?>: <?= htmlspecialchars($c['documento_identidad']) ?></span></td>
                                                <td><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
                                                <td><?= htmlspecialchars($c['email'] ?? '—') ?></td>
                                                <td><small><?= htmlspecialchars($c['direccion'] ?? '—') ?></small></td>
                                                <td><small class="text-muted"><?= date('d/m/Y', strtotime($c['fecha_registro'])) ?></small></td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-info btn-ver-nat" title="Ver"
                                                        data-id="<?= $c['id_cliente_natural'] ?>"
                                                        data-nombres="<?= htmlspecialchars($c['nombres']) ?>"
                                                        data-ap="<?= htmlspecialchars($c['apellido_paterno']) ?>"
                                                        data-am="<?= htmlspecialchars($c['apellido_materno'] ?? '') ?>"
                                                        data-doc="<?= htmlspecialchars($c['tipo_documento'].': '.$c['documento_identidad']) ?>"
                                                        data-tel="<?= htmlspecialchars($c['telefono'] ?? '') ?>"
                                                        data-email="<?= htmlspecialchars($c['email'] ?? '') ?>"
                                                        data-dir="<?= htmlspecialchars($c['direccion'] ?? '') ?>"
                                                        data-fecha="<?= date('d/m/Y H:i', strtotime($c['fecha_registro'])) ?>"
                                                        data-estado="<?= $c['estado_cliente'] ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning btn-editar-nat" title="Editar"
                                                        data-id="<?= $c['id_cliente_natural'] ?>"
                                                        data-nombres="<?= htmlspecialchars($c['nombres']) ?>"
                                                        data-ap="<?= htmlspecialchars($c['apellido_paterno']) ?>"
                                                        data-am="<?= htmlspecialchars($c['apellido_materno'] ?? '') ?>"
                                                        data-tipodoc="<?= htmlspecialchars($c['tipo_documento']) ?>"
                                                        data-doc="<?= htmlspecialchars($c['documento_identidad']) ?>"
                                                        data-tel="<?= htmlspecialchars($c['telefono'] ?? '') ?>"
                                                        data-email="<?= htmlspecialchars($c['email'] ?? '') ?>"
                                                        data-dir="<?= htmlspecialchars($c['direccion'] ?? '') ?>"
                                                        data-estado="<?= $c['estado_cliente'] ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger btn-del-nat" title="Desactivar/Eliminar"
                                                        data-id="<?= $c['id_cliente_natural'] ?>"
                                                        data-nombre="<?= htmlspecialchars($c['nombres'].' '.$c['apellido_paterno']) ?>">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Naturales Inactivos -->
                            <div class="tab-pane fade" id="nat-inactivos">
                                <div class="table-responsive">
                                    <table id="tblNatInactivos" class="table table-clientes table-bordered table-hover table-sm">
                                        <thead>
                                            <tr><th>#</th><th>Apellidos y Nombres</th><th>Documento</th><th>Teléfono</th><th>Registro</th><th class="text-center" style="width:100px;">Acciones</th></tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($nat_inactivos as $c): ?>
                                            <tr class="row-inactivo">
                                                <td><?= $c['id_cliente_natural'] ?></td>
                                                <td class="text-muted"><?= htmlspecialchars($c['apellido_paterno'].' '.($c['apellido_materno']??'').', '.$c['nombres']) ?></td>
                                                <td><span class="badge-dni"><?= htmlspecialchars($c['tipo_documento'].': '.$c['documento_identidad']) ?></span></td>
                                                <td><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
                                                <td><small class="text-muted"><?= date('d/m/Y', strtotime($c['fecha_registro'])) ?></small></td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-success btn-react-nat" title="Reactivar"
                                                        data-id="<?= $c['id_cliente_natural'] ?>"
                                                        data-nombre="<?= htmlspecialchars($c['nombres'].' '.$c['apellido_paterno']) ?>">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger btn-perm-nat" title="Eliminar permanente"
                                                        data-id="<?= $c['id_cliente_natural'] ?>"
                                                        data-nombre="<?= htmlspecialchars($c['nombres'].' '.$c['apellido_paterno']) ?>">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div><!-- /tab-content nat -->
                    </div>
                </div><!-- /panelNatural -->

                <!-- ══════════════════════════════════════════════════════
                     PANEL: EMPRESAS / JURÍDICAS
                ══════════════════════════════════════════════════════ -->
                <div class="panel-tipo <?= $tab_activo==='empresa' ? 'activo' : '' ?>" id="panelEmpresa">
                    <div class="card-header-emp d-flex align-items-center justify-content-between">
                        <h6><i class="fas fa-building mr-2"></i>Empresas — RUC</h6>
                        <ul class="nav nav-tabs card-header-tabs ml-auto" id="tabsEmp" style="border-bottom:none;">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#emp-activos">
                                    <i class="fas fa-check-circle mr-1"></i>Activos
                                    <span class="badge badge-light ml-1"><?= count($emp_activos) ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#emp-inactivos">
                                    <i class="fas fa-ban mr-1"></i>Inactivos
                                    <span class="badge badge-light ml-1"><?= count($emp_inactivos) ?></span>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">

                            <!-- Empresas Activas -->
                            <div class="tab-pane fade show active" id="emp-activos">
                                <div class="table-responsive">
                                    <table id="tblEmpActivos" class="table table-clientes table-bordered table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Razón Social</th>
                                                <th>RUC</th>
                                                <th>Teléfono</th>
                                                <th>Email</th>
                                                <th>Dirección</th>
                                                <th>Registro</th>
                                                <th class="text-center" style="width:110px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($emp_activos as $c): ?>
                                            <tr>
                                                <td><?= $c['id_cliente_empresa'] ?></td>
                                                <td class="font-weight-bold">
                                                    <i class="fas fa-building text-warning mr-1"></i>
                                                    <?= htmlspecialchars($c['razon_social']) ?>
                                                </td>
                                                <td><span class="badge-ruc">RUC: <?= htmlspecialchars($c['ruc']) ?></span></td>
                                                <td><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
                                                <td><?= htmlspecialchars($c['email'] ?? '—') ?></td>
                                                <td><small><?= htmlspecialchars($c['direccion'] ?? '—') ?></small></td>
                                                <td><small class="text-muted"><?= date('d/m/Y', strtotime($c['fecha_registro'])) ?></small></td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-info btn-ver-emp" title="Ver"
                                                        data-id="<?= $c['id_cliente_empresa'] ?>"
                                                        data-rs="<?= htmlspecialchars($c['razon_social']) ?>"
                                                        data-ruc="<?= htmlspecialchars($c['ruc']) ?>"
                                                        data-tel="<?= htmlspecialchars($c['telefono'] ?? '') ?>"
                                                        data-email="<?= htmlspecialchars($c['email'] ?? '') ?>"
                                                        data-dir="<?= htmlspecialchars($c['direccion'] ?? '') ?>"
                                                        data-fecha="<?= date('d/m/Y H:i', strtotime($c['fecha_registro'])) ?>"
                                                        data-estado="<?= $c['estado_cliente'] ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning btn-editar-emp" title="Editar"
                                                        data-id="<?= $c['id_cliente_empresa'] ?>"
                                                        data-rs="<?= htmlspecialchars($c['razon_social']) ?>"
                                                        data-ruc="<?= htmlspecialchars($c['ruc']) ?>"
                                                        data-tel="<?= htmlspecialchars($c['telefono'] ?? '') ?>"
                                                        data-email="<?= htmlspecialchars($c['email'] ?? '') ?>"
                                                        data-dir="<?= htmlspecialchars($c['direccion'] ?? '') ?>"
                                                        data-estado="<?= $c['estado_cliente'] ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger btn-del-emp" title="Desactivar/Eliminar"
                                                        data-id="<?= $c['id_cliente_empresa'] ?>"
                                                        data-nombre="<?= htmlspecialchars($c['razon_social']) ?>">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Empresas Inactivas -->
                            <div class="tab-pane fade" id="emp-inactivos">
                                <div class="table-responsive">
                                    <table id="tblEmpInactivos" class="table table-clientes table-bordered table-hover table-sm">
                                        <thead>
                                            <tr><th>#</th><th>Razón Social</th><th>RUC</th><th>Teléfono</th><th>Registro</th><th class="text-center" style="width:100px;">Acciones</th></tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($emp_inactivos as $c): ?>
                                            <tr class="row-inactivo">
                                                <td><?= $c['id_cliente_empresa'] ?></td>
                                                <td class="text-muted"><?= htmlspecialchars($c['razon_social']) ?></td>
                                                <td><span class="badge-ruc">RUC: <?= htmlspecialchars($c['ruc']) ?></span></td>
                                                <td><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
                                                <td><small class="text-muted"><?= date('d/m/Y', strtotime($c['fecha_registro'])) ?></small></td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-success btn-react-emp" title="Reactivar"
                                                        data-id="<?= $c['id_cliente_empresa'] ?>"
                                                        data-nombre="<?= htmlspecialchars($c['razon_social']) ?>">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger btn-perm-emp" title="Eliminar permanente"
                                                        data-id="<?= $c['id_cliente_empresa'] ?>"
                                                        data-nombre="<?= htmlspecialchars($c['razon_social']) ?>">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div><!-- /tab-content emp -->
                    </div>
                </div><!-- /panelEmpresa -->

            </div><!-- /card -->
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL VER PERSONA NATURAL
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalVerNatural" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">
            <div style="background:linear-gradient(135deg,#1a5276,#2563eb);padding:28px 24px 20px;text-align:center;position:relative;">
                <button type="button" class="close" data-dismiss="modal" style="position:absolute;top:12px;right:16px;color:#fff;opacity:.8;font-size:1.4rem;">&times;</button>
                <div style="width:72px;height:72px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                    <i class="fas fa-user" style="font-size:2rem;color:#fff;"></i>
                </div>
                <h5 id="vn_nombre" style="color:#fff;font-weight:700;margin:0;font-size:1.05rem;"></h5>
                <div class="mt-2"><span id="vn_doc" style="background:rgba(255,255,255,.25);color:#fff;font-size:.78rem;padding:4px 12px;border-radius:20px;font-family:monospace;font-weight:600;display:inline-block;"></span></div>
            </div>
            <div class="modal-body" style="padding:20px 24px;">
                <div class="row">
                    <div class="col-6 mb-3"><div style="display:flex;align-items:flex-start;gap:10px;"><div style="width:34px;height:34px;background:#e8f5e9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-phone-alt" style="color:#1a7a4a;font-size:.85rem;"></i></div><div><div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Teléfono</div><div id="vn_tel" style="font-size:.9rem;color:#2d3436;"></div></div></div></div>
                    <div class="col-6 mb-3"><div style="display:flex;align-items:flex-start;gap:10px;"><div style="width:34px;height:34px;background:#e8f5e9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-envelope" style="color:#1a7a4a;font-size:.85rem;"></i></div><div><div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Email</div><div id="vn_email" style="font-size:.9rem;color:#2d3436;word-break:break-all;"></div></div></div></div>
                    <div class="col-12 mb-3"><div style="display:flex;align-items:flex-start;gap:10px;"><div style="width:34px;height:34px;background:#fff3e0;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-map-marker-alt" style="color:#e67e22;font-size:.85rem;"></i></div><div><div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Dirección</div><div id="vn_dir" style="font-size:.9rem;color:#2d3436;"></div></div></div></div>
                    <div class="col-6 mb-3"><div style="display:flex;align-items:flex-start;gap:10px;"><div style="width:34px;height:34px;background:#f3e5f5;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-calendar-alt" style="color:#8e44ad;font-size:.85rem;"></i></div><div><div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Fecha Registro</div><div id="vn_fecha" style="font-size:.9rem;color:#2d3436;"></div></div></div></div>
                    <div class="col-6 mb-3"><div style="display:flex;align-items:flex-start;gap:10px;"><div style="width:34px;height:34px;background:#e8f5e9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-toggle-on" style="color:#1a7a4a;font-size:.85rem;"></i></div><div><div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Estado</div><div id="vn_estado" style="font-size:.9rem;"></div></div></div></div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL VER EMPRESA
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalVerEmpresa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content" style="border-radius:14px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">
            <div style="background:linear-gradient(135deg,#7b341e,#c05621);padding:28px 24px 20px;text-align:center;position:relative;">
                <button type="button" class="close" data-dismiss="modal" style="position:absolute;top:12px;right:16px;color:#fff;opacity:.8;font-size:1.4rem;">&times;</button>
                <div style="width:72px;height:72px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
                    <i class="fas fa-building" style="font-size:2rem;color:#fff;"></i>
                </div>
                <h5 id="ve_rs" style="color:#fff;font-weight:700;margin:0;font-size:1.05rem;"></h5>
                <div class="mt-2"><span id="ve_ruc" style="background:rgba(255,255,255,.25);color:#fff;font-size:.78rem;padding:4px 12px;border-radius:20px;font-family:monospace;font-weight:600;display:inline-block;"></span></div>
            </div>
            <div class="modal-body" style="padding:20px 24px;">
                <div class="row">
                    <div class="col-6 mb-3"><div style="display:flex;align-items:flex-start;gap:10px;"><div style="width:34px;height:34px;background:#fde8d8;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-phone-alt" style="color:#c05621;font-size:.85rem;"></i></div><div><div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Teléfono</div><div id="ve_tel" style="font-size:.9rem;color:#2d3436;"></div></div></div></div>
                    <div class="col-6 mb-3"><div style="display:flex;align-items:flex-start;gap:10px;"><div style="width:34px;height:34px;background:#fde8d8;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-envelope" style="color:#c05621;font-size:.85rem;"></i></div><div><div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Email</div><div id="ve_email" style="font-size:.9rem;color:#2d3436;word-break:break-all;"></div></div></div></div>
                    <div class="col-12 mb-3"><div style="display:flex;align-items:flex-start;gap:10px;"><div style="width:34px;height:34px;background:#fff3e0;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-map-marker-alt" style="color:#e67e22;font-size:.85rem;"></i></div><div><div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Dirección</div><div id="ve_dir" style="font-size:.9rem;color:#2d3436;"></div></div></div></div>
                    <div class="col-6 mb-3"><div style="display:flex;align-items:flex-start;gap:10px;"><div style="width:34px;height:34px;background:#f3e5f5;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-calendar-alt" style="color:#8e44ad;font-size:.85rem;"></i></div><div><div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Fecha Registro</div><div id="ve_fecha" style="font-size:.9rem;color:#2d3436;"></div></div></div></div>
                    <div class="col-6 mb-3"><div style="display:flex;align-items:flex-start;gap:10px;"><div style="width:34px;height:34px;background:#fde8d8;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-toggle-on" style="color:#c05621;font-size:.85rem;"></i></div><div><div style="font-size:.72rem;color:#999;text-transform:uppercase;letter-spacing:.5px;font-weight:600;">Estado</div><div id="ve_estado" style="font-size:.9rem;"></div></div></div></div>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL CREAR PERSONA NATURAL
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCrearNatural" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-cli">
                <h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i>Registrar Persona Natural</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="crear">
                <input type="hidden" name="tabla"  value="natural">
                <div class="modal-body">
                    <!-- Consulta RENIEC -->
                    <div class="row mb-3">
                        <div class="col-md-5">
                            <label class="form-label-cli"><i class="fas fa-id-card text-primary mr-1"></i>Tipo Documento</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                </div>
                                <select name="tipo_documento" id="cn_tipodoc" class="form-control form-control-sm">
                                    <option value="DNI">DNI</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label-cli"><i class="fas fa-hashtag text-primary mr-1"></i>Número de Documento</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-fingerprint"></i></span>
                                </div>
                                <input type="text" name="documento_identidad" id="cn_doc" class="form-control" placeholder="Ej: 12345678" maxlength="20">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-reniec btn-sm" id="btn_reniec_cn">
                                        <i class="fas fa-search mr-1"></i>RENIEC
                                    </button>
                                </div>
                            </div>
                            <div id="reniec_result_cn" class="reniec-result mt-2">
                                <div class="reniec-nombre" id="rr_cn_nombre"></div>
                                <div class="reniec-info" id="rr_cn_info"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-user text-primary mr-1"></i>Nombres <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                </div>
                                <input type="text" name="nombres" id="cn_nombres" class="form-control" placeholder="Nombres" required>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-user-tag text-primary mr-1"></i>Apellido Paterno <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                </div>
                                <input type="text" name="apellido_paterno" id="cn_ap" class="form-control" placeholder="Apellido paterno" required>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-user-tag text-secondary mr-1"></i>Apellido Materno</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                </div>
                                <input type="text" name="apellido_materno" id="cn_am" class="form-control" placeholder="Apellido materno">
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label-cli"><i class="fas fa-phone-alt text-success mr-1"></i>Teléfono</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-phone-alt"></i></span>
                                </div>
                                <input type="text" name="telefono" class="form-control" placeholder="Ej: 987654321" maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label-cli"><i class="fas fa-envelope text-info mr-1"></i>Email</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                </div>
                                <input type="email" name="email" class="form-control" placeholder="correo@ejemplo.com">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-map-marker-alt text-danger mr-1"></i>Dirección</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                </div>
                                <input type="text" name="direccion" id="cn_dir" class="form-control" placeholder="Dirección">
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label-cli"><i class="fas fa-toggle-on text-success mr-1"></i>Estado</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-toggle-on"></i></span>
                                </div>
                                <select name="estado_cliente" class="form-control form-control-sm">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
                    <button type="submit" class="btn btn-cli-primary btn-sm"><i class="fas fa-save mr-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL EDITAR PERSONA NATURAL
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditarNatural" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#b45309,#d97706)!important;color:#fff!important;border-radius:10px 10px 0 0!important;border-bottom:none!important;">
                <h5 class="modal-title" style="color:#fff!important;"><i class="fas fa-user-edit mr-2"></i>Editar Persona Natural</h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff!important;opacity:.8!important;text-shadow:none!important;"><span>&times;</span></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion"     value="actualizar">
                <input type="hidden" name="tabla"      value="natural">
                <input type="hidden" name="id_cliente" id="en_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-id-card text-primary mr-1"></i>Tipo Documento</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                </div>
                                <select name="tipo_documento" id="en_tipodoc" class="form-control form-control-sm">
                                    <option value="DNI">DNI</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-fingerprint text-primary mr-1"></i>Número de Documento</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-fingerprint"></i></span>
                                </div>
                                <input type="text" name="documento_identidad" id="en_doc" class="form-control" maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-toggle-on text-success mr-1"></i>Estado</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-toggle-on"></i></span>
                                </div>
                                <select name="estado_cliente" id="en_estado" class="form-control form-control-sm">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-user text-primary mr-1"></i>Nombres <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                </div>
                                <input type="text" name="nombres" id="en_nombres" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-user-tag text-primary mr-1"></i>Apellido Paterno <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                </div>
                                <input type="text" name="apellido_paterno" id="en_ap" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-user-tag text-secondary mr-1"></i>Apellido Materno</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                </div>
                                <input type="text" name="apellido_materno" id="en_am" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-phone-alt text-success mr-1"></i>Teléfono</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-phone-alt"></i></span>
                                </div>
                                <input type="text" name="telefono" id="en_tel" class="form-control" maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-envelope text-info mr-1"></i>Email</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                </div>
                                <input type="email" name="email" id="en_email" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-map-marker-alt text-danger mr-1"></i>Dirección</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                </div>
                                <input type="text" name="direccion" id="en_dir" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
                    <button type="submit" class="btn btn-cli-primary btn-sm"><i class="fas fa-save mr-1"></i>Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL CREAR EMPRESA
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalCrearEmpresa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-building mr-2"></i>Registrar Empresa / Persona Jurídica</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion" value="crear">
                <input type="hidden" name="tabla"  value="empresa">
                <div class="modal-body">
                    <!-- Consulta SUNAT -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label-cli"><i class="fas fa-barcode text-warning mr-1"></i>RUC <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                </div>
                                <input type="text" name="ruc" id="ce_ruc" class="form-control" placeholder="11 dígitos" maxlength="11">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-emp-primary btn-sm" id="btn_sunat_ce">
                                        <i class="fas fa-search mr-1"></i>SUNAT
                                    </button>
                                </div>
                            </div>
                            <div id="sunat_result_ce" class="reniec-result mt-2" style="background:#fde8d8;border-color:#f5cba7;">
                                <div class="reniec-nombre" id="sr_ce_nombre" style="color:#c05621;"></div>
                                <div class="reniec-info" id="sr_ce_info"></div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label-cli"><i class="fas fa-building text-warning mr-1"></i>Razón Social <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-building"></i></span>
                                </div>
                                <input type="text" name="razon_social" id="ce_rs" class="form-control" placeholder="Razón social de la empresa" required>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label-cli"><i class="fas fa-phone-alt text-success mr-1"></i>Teléfono</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-phone-alt"></i></span>
                                </div>
                                <input type="text" name="telefono" class="form-control" placeholder="Ej: 074-123456" maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label-cli"><i class="fas fa-envelope text-info mr-1"></i>Email</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                </div>
                                <input type="email" name="email" class="form-control" placeholder="correo@empresa.com">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-map-marker-alt text-danger mr-1"></i>Dirección</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                </div>
                                <input type="text" name="direccion" id="ce_dir" class="form-control" placeholder="Dirección fiscal">
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label-cli"><i class="fas fa-toggle-on text-success mr-1"></i>Estado</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-toggle-on"></i></span>
                                </div>
                                <select name="estado_cliente" class="form-control form-control-sm">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
                    <button type="submit" class="btn btn-emp-primary btn-sm"><i class="fas fa-save mr-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL EDITAR EMPRESA
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditarEmpresa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-building mr-2"></i>Editar Empresa</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST">
                <input type="hidden" name="accion"     value="actualizar">
                <input type="hidden" name="tabla"      value="empresa">
                <input type="hidden" name="id_cliente" id="ee_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label-cli"><i class="fas fa-barcode text-warning mr-1"></i>RUC <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                </div>
                                <input type="text" name="ruc" id="ee_ruc" class="form-control" maxlength="11" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label-cli"><i class="fas fa-toggle-on text-success mr-1"></i>Estado</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-toggle-on"></i></span>
                                </div>
                                <select name="estado_cliente" id="ee_estado" class="form-control form-control-sm">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label-cli"><i class="fas fa-building text-warning mr-1"></i>Razón Social <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-building"></i></span>
                                </div>
                                <input type="text" name="razon_social" id="ee_rs" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-phone-alt text-success mr-1"></i>Teléfono</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-phone-alt"></i></span>
                                </div>
                                <input type="text" name="telefono" id="ee_tel" class="form-control" maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-envelope text-info mr-1"></i>Email</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                </div>
                                <input type="email" name="email" id="ee_email" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-cli"><i class="fas fa-map-marker-alt text-danger mr-1"></i>Dirección</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                </div>
                                <input type="text" name="direccion" id="ee_dir" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
                    <button type="submit" class="btn btn-emp-primary btn-sm"><i class="fas fa-save mr-1"></i>Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL IMPORTAR CLIENTES (Natural / Empresa)
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalImportarClientes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:12px;overflow:hidden;border:none;box-shadow:0 20px 60px rgba(0,0,0,.2);">

            <!-- Header dinámico según tipo -->
            <div class="modal-header" id="importModalHeader" style="border-bottom:none;padding:18px 24px;">
                <h5 class="modal-title font-weight-bold" id="importModalTitle">
                    <i class="fas fa-file-import mr-2"></i>Importar Clientes
                </h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:.8;text-shadow:none;">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body" style="padding:24px;">

                <!-- Paso 1: Selección de archivo -->
                <div id="importStep1">
                    <div class="alert alert-info border-0 mb-3" style="border-radius:8px;font-size:.88rem;">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Formatos aceptados:</strong> .xlsx, .xls, .csv &nbsp;|&nbsp;
                        <strong>Tamaño máximo:</strong> 5 MB &nbsp;|&nbsp;
                        Los registros duplicados (mismo documento/RUC) serán omitidos automáticamente.
                    </div>

                    <!-- Zona de arrastre -->
                    <div id="importDropZone" style="
                        border: 2px dashed #cbd5e1;
                        border-radius: 12px;
                        padding: 40px 20px;
                        text-align: center;
                        cursor: pointer;
                        transition: all .25s;
                        background: #f8fafc;
                    ">
                        <i class="fas fa-cloud-upload-alt fa-3x mb-3" id="importDropIcon" style="color:#94a3b8;"></i>
                        <p class="mb-1 font-weight-bold" style="color:#475569;">Arrastra tu archivo aquí</p>
                        <p class="mb-3" style="color:#94a3b8;font-size:.85rem;">o haz clic para seleccionar</p>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="importBtnSeleccionar">
                            <i class="fas fa-folder-open mr-1"></i>Seleccionar archivo
                        </button>
                    </div>

                    <!-- Archivo seleccionado -->
                    <div id="importFileInfo" class="mt-3" style="display:none;">
                        <div class="d-flex align-items-center p-3" style="background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;">
                            <i class="fas fa-file-excel fa-2x text-success mr-3"></i>
                            <div class="flex-grow-1">
                                <div class="font-weight-bold" id="importFileName" style="color:#166534;"></div>
                                <small class="text-muted" id="importFileSize"></small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="importBtnQuitarArchivo">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Enlace plantilla -->
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            <i class="fas fa-question-circle mr-1"></i>
                            ¿No tienes el formato correcto?
                            <a href="#" id="importLinkPlantilla" class="font-weight-bold">
                                <i class="fas fa-download mr-1"></i>Descargar plantilla Excel
                            </a>
                        </small>
                    </div>
                </div>

                <!-- Paso 2: Progreso -->
                <div id="importStep2" style="display:none;text-align:center;padding:20px 0;">
                    <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem;" role="status"></div>
                    <p class="font-weight-bold mb-1">Procesando archivo...</p>
                    <p class="text-muted" style="font-size:.85rem;">Por favor espera mientras se importan los registros.</p>
                </div>

                <!-- Paso 3: Resultado -->
                <div id="importStep3" style="display:none;">
                    <div id="importResultado"></div>
                </div>

            </div>

            <div class="modal-footer" style="border-top:1px solid #e2e8f0;padding:14px 24px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" id="importBtnCerrar">
                    <i class="fas fa-times mr-1"></i>Cerrar
                </button>
                <button type="button" class="btn btn-sm" id="importBtnProcesar" style="display:none;">
                    <i class="fas fa-upload mr-1"></i>Importar ahora
                </button>
                <button type="button" class="btn btn-sm btn-success" id="importBtnRecargar" style="display:none;">
                    <i class="fas fa-sync mr-1"></i>Recargar página
                </button>
            </div>

        </div>
    </div>
</div>

<!-- Input file FUERA del modal para evitar bloqueo de Bootstrap enforceFocus -->
<input type="file" id="importFileInput" accept=".xlsx,.xls,.csv" style="display:none;position:fixed;top:-9999px;left:-9999px;">

<?php
$extra_js = '<script src="js/clientes.js?v=' . time() . '"></script>';
include $ruta_base . 'includes/footer.php';
?>
