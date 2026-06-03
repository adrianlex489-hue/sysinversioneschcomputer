<?php
// ============================================================
// modules/servicios/recepcion.php | SysInversiones CH Computer
// Recepción de equipos para servicio técnico
// Tablas: equipos, ordenes_servicio, clientes, servicio_historial
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
verificarPermiso($pdo, 'servicios');

$id_usuario_sesion = $_SESSION['id_usuario'] ?? 0;

// ── Directorio de uploads ─────────────────────────────────────────────────────
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/sysinversioneschcomputer/uploads/equipos/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// ── Helper: alerta + redirect ─────────────────────────────────────────────────
$swal = null;
if (isset($_SESSION['swal_rec'])) { $swal = $_SESSION['swal_rec']; unset($_SESSION['swal_rec']); }

function redirigirRec(string $icon, string $title, string $text): void {
    $_SESSION['swal_rec'] = ['icon' => $icon, 'title' => $title, 'text' => $text];
    header('Location: recepcion.php');
    exit;
}

// ── Subir fotos ───────────────────────────────────────────────────────────────
function subirFotos(array $files, int $id_equipo): array {
    global $upload_dir;
    $rutas = [];
    $permitidos = ['image/jpeg','image/png','image/webp','image/gif'];
    foreach ($files['tmp_name'] as $i => $tmp) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        if (!in_array($files['type'][$i], $permitidos)) continue;
        if ($files['size'][$i] > 5 * 1024 * 1024) continue;
        $ext    = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $nombre = 'equipo_' . $id_equipo . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
        if (move_uploaded_file($tmp, $upload_dir . $nombre)) {
            $rutas[] = 'uploads/equipos/' . $nombre;
        }
    }
    return $rutas;
}

// ── CRUD ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // ── CREAR RECEPCIÓN (equipo + orden) ──────────────────────────────────────
    if ($accion === 'crear_recepcion') {
        $id_cliente         = (int)($_POST['id_cliente'] ?? 0);
        $tipo_cliente       = ($_POST['tipo_cliente'] ?? 'natural') === 'empresa' ? 'empresa' : 'natural';
        $tipo_equipo        = trim($_POST['tipo_equipo'] ?? '');
        $marca              = strtoupper(trim($_POST['marca'] ?? ''));
        $modelo             = strtoupper(trim($_POST['modelo'] ?? ''));
        $numero_serie       = strtoupper(trim($_POST['numero_serie'] ?? ''));
        $accesorios         = trim($_POST['accesorios'] ?? '');
        $estado_fisico      = trim($_POST['estado_fisico'] ?? '');
        $contrasena_equipo  = trim($_POST['contrasena_equipo'] ?? '') ?: null;
        $problema_reportado = trim($_POST['problema_reportado'] ?? '');
        $prioridad          = $_POST['prioridad'] ?? 'normal';
        $fecha_entrega_est  = trim($_POST['fecha_entrega_estimada'] ?? '') ?: null;
        $observacion        = trim($_POST['observacion'] ?? '');

        if (!$id_cliente || !$tipo_equipo || !$marca) {
            redirigirRec('warning', 'Campos incompletos', 'Cliente, tipo de equipo y marca son obligatorios.');
        }

        try {
            $pdo->beginTransaction();

            // 1. Insertar equipo
            $stmt = $pdo->prepare("INSERT INTO equipos (id_cliente, tipo_cliente, tipo, marca, modelo, numero_serie, accesorios, estado_fisico, contrasena_equipo, fotos_ingreso, estado)
                                   VALUES (?,?,?,?,?,?,?,?,?,?,1)");
            $stmt->execute([$id_cliente, $tipo_cliente, $tipo_equipo, $marca, $modelo, $numero_serie, $accesorios, $estado_fisico, $contrasena_equipo, null]);
            $id_equipo = (int)$pdo->lastInsertId();

            // 2. Subir fotos si las hay
            $fotos_json = null;
            if (!empty($_FILES['fotos_ingreso']['tmp_name'][0])) {
                $rutas = subirFotos($_FILES['fotos_ingreso'], $id_equipo);
                if ($rutas) {
                    $fotos_json = json_encode($rutas);
                    $pdo->prepare("UPDATE equipos SET fotos_ingreso=? WHERE id_equipo=?")->execute([$fotos_json, $id_equipo]);
                }
            }

            // 3. Crear orden de servicio
            $stmt2 = $pdo->prepare("INSERT INTO ordenes_servicio
                (id_cliente, tipo_cliente, id_usuario, id_equipo, fecha_recepcion, fecha_entrega_estimada,
                 problema_reportado, observacion, prioridad, estado)
                VALUES (?,?,?,?,NOW(),?,?,?,?,'recibido')");
            $stmt2->execute([$id_cliente, $tipo_cliente, $id_usuario_sesion, $id_equipo, $fecha_entrega_est,
                             $problema_reportado, $observacion, $prioridad]);
            $id_orden = (int)$pdo->lastInsertId();

            // 4. Registrar en historial
            $pdo->prepare("INSERT INTO servicio_historial (id_orden, id_usuario, estado, descripcion, fecha)
                           VALUES (?,?,?,?,NOW())")
                ->execute([$id_orden, $id_usuario_sesion, 'recibido', 'Equipo recibido en recepción. ' . $problema_reportado]);

            $pdo->commit();
            redirigirRec('success', '¡Recepción registrada!', 'Orden #ORD-' . str_pad($id_orden, 6, '0', STR_PAD_LEFT) . ' creada correctamente.');

        } catch (PDOException $e) {
            $pdo->rollBack();
            redirigirRec('error', 'Error al registrar', $e->getMessage());
        }
    }

    // ── ACTUALIZAR ESTADO DE ORDEN ────────────────────────────────────────────
    if ($accion === 'cambiar_estado') {
        $id_orden     = (int)($_POST['id_orden'] ?? 0);
        $nuevo_estado = $_POST['nuevo_estado'] ?? '';
        $descripcion  = trim($_POST['descripcion_cambio'] ?? '');
        $estados_validos = ['recibido','diagnostico','en_proceso','listo','entregado','cancelado'];

        if ($id_orden && in_array($nuevo_estado, $estados_validos)) {
            try {
                $pdo->prepare("UPDATE ordenes_servicio SET estado=? WHERE id_orden=?")->execute([$nuevo_estado, $id_orden]);
                $pdo->prepare("INSERT INTO servicio_historial (id_orden, id_usuario, estado, descripcion, fecha) VALUES (?,?,?,?,NOW())")
                    ->execute([$id_orden, $id_usuario_sesion, $nuevo_estado, $descripcion ?: 'Estado actualizado.']);
                redirigirRec('success', 'Estado actualizado', 'La orden fue actualizada a: ' . ucfirst(str_replace('_', ' ', $nuevo_estado)));
            } catch (PDOException $e) {
                redirigirRec('error', 'Error', $e->getMessage());
            }
        }
    }

    // ── CANCELAR ORDEN ────────────────────────────────────────────────────────
    if ($accion === 'cancelar_orden') {
        $id_orden = (int)($_POST['id_orden'] ?? 0);
        if ($id_orden) {
            try {
                $pdo->prepare("UPDATE ordenes_servicio SET estado='cancelado' WHERE id_orden=?")->execute([$id_orden]);
                $pdo->prepare("INSERT INTO servicio_historial (id_orden, id_usuario, estado, descripcion, fecha) VALUES (?,?,?,?,NOW())")
                    ->execute([$id_orden, $id_usuario_sesion, 'cancelado', 'Orden cancelada por el operador.']);
                redirigirRec('info', 'Orden cancelada', 'La orden fue cancelada.');
            } catch (PDOException $e) {
                redirigirRec('error', 'Error', $e->getMessage());
            }
        }
    }
}

// ── DATOS ─────────────────────────────────────────────────────────────────────
// Clientes para el selector del modal (naturales + empresas)
$clientes_nat = $pdo->query("SELECT id_cliente_natural AS id_cliente, 'natural' AS tipo_cliente,
    CONCAT(apellido_paterno,' ',COALESCE(apellido_materno,''),', ',nombres) AS nombre_display,
    tipo_documento, documento_identidad
    FROM clientes_natural WHERE estado_cliente=1 ORDER BY apellido_paterno ASC")->fetchAll();
$clientes_emp = $pdo->query("SELECT id_cliente_empresa AS id_cliente, 'empresa' AS tipo_cliente,
    razon_social AS nombre_display, 'RUC' AS tipo_documento, ruc AS documento_identidad
    FROM clientes_empresa WHERE estado_cliente=1 ORDER BY razon_social ASC")->fetchAll();
$clientes = array_merge($clientes_nat, $clientes_emp);

// Órdenes activas (no entregadas ni canceladas)
$ordenes_activas = $pdo->query("
    SELECT o.*,
           CASE o.tipo_cliente
               WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
               ELSE TRIM(CONCAT_WS(', ',
                   NULLIF(TRIM(CONCAT_WS(' ', cn.apellido_paterno, cn.apellido_materno)),''),
                   NULLIF(TRIM(cn.nombres),'')
               ))
           END AS cliente_nombre,
           o.tipo_cliente,
           CASE o.tipo_cliente WHEN 'empresa' THEN ce.ruc ELSE cn.documento_identidad END AS documento_identidad,
           CASE o.tipo_cliente WHEN 'empresa' THEN 'RUC' ELSE cn.tipo_documento END AS tipo_documento,
           CASE o.tipo_cliente WHEN 'empresa' THEN ce.telefono ELSE cn.telefono END AS telefono,
           e.tipo AS equipo_tipo, e.marca, e.modelo, e.numero_serie, e.accesorios, e.estado_fisico, e.fotos_ingreso, e.contrasena_equipo,
           u.nombre_completo AS recepcionado_por
    FROM ordenes_servicio o
    LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = o.id_cliente AND o.tipo_cliente = 'natural'
    LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = o.id_cliente AND o.tipo_cliente = 'empresa'
    JOIN equipos e ON e.id_equipo = o.id_equipo
    LEFT JOIN usuarios u ON u.id_usuario = o.id_usuario
    WHERE o.estado NOT IN ('entregado','cancelado')
    ORDER BY FIELD(o.prioridad,'alta','media','baja'), o.fecha_recepcion DESC
")->fetchAll();

// Órdenes históricas
$ordenes_historial = $pdo->query("
    SELECT o.*,
           CASE o.tipo_cliente
               WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
               ELSE TRIM(CONCAT_WS(', ',
                   NULLIF(TRIM(CONCAT_WS(' ', cn.apellido_paterno, cn.apellido_materno)),''),
                   NULLIF(TRIM(cn.nombres),'')
               ))
           END AS cliente_nombre,
           o.tipo_cliente,
           CASE o.tipo_cliente WHEN 'empresa' THEN ce.ruc ELSE cn.documento_identidad END AS documento_identidad,
           CASE o.tipo_cliente WHEN 'empresa' THEN 'RUC' ELSE cn.tipo_documento END AS tipo_documento,
           e.tipo AS equipo_tipo, e.marca, e.modelo, e.numero_serie
    FROM ordenes_servicio o
    LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = o.id_cliente AND o.tipo_cliente = 'natural'
    LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = o.id_cliente AND o.tipo_cliente = 'empresa'
    JOIN equipos e ON e.id_equipo = o.id_equipo
    WHERE o.estado IN ('entregado','cancelado')
    ORDER BY o.fecha_recepcion DESC
    LIMIT 100
")->fetchAll();

// Contadores por estado
$contadores = [];
$res = $pdo->query("SELECT estado, COUNT(*) AS total FROM ordenes_servicio WHERE estado NOT IN ('entregado','cancelado') GROUP BY estado");
foreach ($res->fetchAll() as $row) $contadores[$row['estado']] = $row['total'];
$total_activas = array_sum($contadores);
$total_hist    = count($ordenes_historial);

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>

<link rel="stylesheet" href="css/recepcion.css?v=<?= time() ?>">

<div class="content-wrapper">

    <!-- ── CABECERA ── -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-rec d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4><i class="fas fa-clipboard-list mr-2"></i>Recepción de Equipos</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones CH Computer &rsaquo; Servicio Técnico &rsaquo; Recepción</small>
                </div>
                <button class="btn btn-light font-weight-bold" data-toggle="modal" data-target="#modalNuevaRecepcion">
                    <i class="fas fa-plus mr-1"></i> Nueva Recepción
                </button>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- ── ALERTA SWAL ── -->
            <?php if ($swal): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon: '<?= $swal['icon'] ?>',
                    title: '<?= addslashes($swal['title']) ?>',
                    text: '<?= addslashes($swal['text']) ?>',
                    confirmButtonColor: '#2563eb',
                    timer: <?= in_array($swal['icon'], ['success','info']) ? 3500 : 0 ?>,
                    timerProgressBar: <?= in_array($swal['icon'], ['success','info']) ? 'true' : 'false' ?>,
                    showConfirmButton: <?= in_array($swal['icon'], ['success','info']) ? 'false' : 'true' ?>,
                });
            });
            </script>
            <?php endif; ?>

            <!-- ── STATS ── -->
            <div class="row mb-4">
                <div class="col-6 col-md-2 mb-2">
                    <div class="stat-mini" style="background:#1e40af;">
                        <i class="fas fa-inbox"></i>
                        <div>
                            <div class="stat-value"><?= $contadores['recibido'] ?? 0 ?></div>
                            <div class="stat-label">Recibidos</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2 mb-2">
                    <div class="stat-mini" style="background:#b45309;">
                        <i class="fas fa-search"></i>
                        <div>
                            <div class="stat-value"><?= $contadores['diagnostico'] ?? 0 ?></div>
                            <div class="stat-label">Diagnóstico</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2 mb-2">
                    <div class="stat-mini" style="background:#c2410c;">
                        <i class="fas fa-tools"></i>
                        <div>
                            <div class="stat-value"><?= $contadores['en_proceso'] ?? 0 ?></div>
                            <div class="stat-label">En Proceso</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2 mb-2">
                    <div class="stat-mini" style="background:#166534;">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <div class="stat-value"><?= $contadores['listo'] ?? 0 ?></div>
                            <div class="stat-label">Listos</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2 mb-2">
                    <div class="stat-mini" style="background:#374151;">
                        <i class="fas fa-history"></i>
                        <div>
                            <div class="stat-value"><?= $total_hist ?></div>
                            <div class="stat-label">Historial</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-2 mb-2">
                    <div class="stat-mini" style="background:#2563eb;">
                        <i class="fas fa-list-alt"></i>
                        <div>
                            <div class="stat-value"><?= $total_activas ?></div>
                            <div class="stat-label">Total Activas</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── CARD PRINCIPAL ── -->
            <div class="card shadow-sm">
                <div class="card-header-rec d-flex align-items-center justify-content-between">
                    <h6 class="m-0 text-white"><i class="fas fa-list mr-2"></i>Órdenes de Servicio</h6>
                    <ul class="nav nav-tabs card-header-tabs ml-auto" id="recTab" role="tablist" style="border-bottom:none;">
                        <li class="nav-item">
                            <a class="nav-link active text-white font-weight-bold" data-toggle="tab" href="#tab-activas" role="tab">
                                <i class="fas fa-clock mr-1"></i> Activas <span class="badge badge-light ml-1"><?= $total_activas ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white font-weight-bold" data-toggle="tab" href="#tab-historial" role="tab">
                                <i class="fas fa-archive mr-1"></i> Historial <span class="badge badge-light ml-1"><?= $total_hist ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">

                        <!-- ACTIVAS -->
                        <div class="tab-pane fade show active" id="tab-activas">
                            <div class="table-responsive">
                                <table id="tablaActivas" class="table table-rec table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Orden</th>
                                            <th>Cliente</th>
                                            <th>Equipo</th>
                                            <th>Problema</th>
                                            <th>Prioridad</th>
                                            <th>Estado</th>
                                            <th>Recepción</th>
                                            <th>Entrega Est.</th>
                                            <th class="text-center" style="width:110px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($ordenes_activas as $o): ?>
                                        <?php
                                        $fotos       = $o['fotos_ingreso'] ? json_decode($o['fotos_ingreso'], true) : [];
                                        $primera_foto = $fotos[0] ?? null;
                                        ?>
                                        <tr>
                                            <td><span class="num-orden">ORD-<?= str_pad($o['id_orden'],6,'0',STR_PAD_LEFT) ?></span></td>
                                            <td>
                                                <div class="font-weight-bold" style="font-size:.85rem;"><?= htmlspecialchars($o['cliente_nombre']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($o['tipo_documento']) ?>: <?= htmlspecialchars($o['documento_identidad']) ?></small>
                                            </td>
                                            <td>
                                                <div>
                                                    <div style="font-size:.85rem;font-weight:600;"><?= htmlspecialchars($o['equipo_tipo']) ?> <?= htmlspecialchars($o['marca']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($o['modelo'] ?: '—') ?></small>
                                                </div>
                                            </td>
                                            <td><small><?= htmlspecialchars(mb_substr($o['problema_reportado'] ?? '—', 0, 60)) ?><?= mb_strlen($o['problema_reportado'] ?? '') > 60 ? '...' : '' ?></small></td>
                                            <td>
                                                <span class="badge-prioridad badge-prioridad-<?= $o['prioridad'] ?>">
                                                    <?= ucfirst($o['prioridad']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge-estado badge-<?= $o['estado'] ?>">
                                                    <?= ucfirst(str_replace('_',' ',$o['estado'])) ?>
                                                </span>
                                            </td>
                                            <td><small><?= date('d/m/Y H:i', strtotime($o['fecha_recepcion'])) ?></small></td>
                                            <td>
                                                <?php if ($o['fecha_entrega_estimada']): ?>
                                                    <?php
                                                    $hoy    = new DateTime();
                                                    $entrega = new DateTime($o['fecha_entrega_estimada']);
                                                    $vencida = $entrega < $hoy;
                                                    ?>
                                                    <small class="<?= $vencida ? 'text-danger font-weight-bold' : 'text-muted' ?>">
                                                        <?= $vencida ? '<i class="fas fa-exclamation-triangle mr-1"></i>' : '' ?>
                                                        <?= date('d/m/Y', strtotime($o['fecha_entrega_estimada'])) ?>
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-muted">—</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-info btn-ver-orden" title="Ver detalle"
                                                    data-id="<?= $o['id_orden'] ?>"
                                                    data-cliente="<?= htmlspecialchars($o['cliente_nombre']) ?>"
                                                    data-doc="<?= htmlspecialchars($o['tipo_documento'].': '.$o['documento_identidad']) ?>"
                                                    data-telefono="<?= htmlspecialchars($o['telefono'] ?? '') ?>"
                                                    data-tipo="<?= htmlspecialchars($o['equipo_tipo']) ?>"
                                                    data-marca="<?= htmlspecialchars($o['marca']) ?>"
                                                    data-modelo="<?= htmlspecialchars($o['modelo'] ?? '') ?>"
                                                    data-serie="<?= htmlspecialchars($o['numero_serie'] ?? '') ?>"
                                                    data-accesorios="<?= htmlspecialchars($o['accesorios'] ?? '') ?>"
                                                    data-estado-fisico="<?= htmlspecialchars($o['estado_fisico'] ?? '') ?>"
                                                    data-problema="<?= htmlspecialchars($o['problema_reportado'] ?? '') ?>"
                                                    data-prioridad="<?= $o['prioridad'] ?>"
                                                    data-estado="<?= $o['estado'] ?>"
                                                    data-fecha-rec="<?= date('d/m/Y H:i', strtotime($o['fecha_recepcion'])) ?>"
                                                    data-fecha-est="<?= $o['fecha_entrega_estimada'] ? date('d/m/Y', strtotime($o['fecha_entrega_estimada'])) : '' ?>"
                                                    data-fotos="<?= htmlspecialchars($o['fotos_ingreso'] ?? '[]', ENT_QUOTES) ?>"
                                                    data-tecnico="<?= htmlspecialchars($o['recepcionado_por'] ?? '', ENT_QUOTES) ?>"
                                                    data-contrasena="<?= htmlspecialchars($o['contrasena_equipo'] ?? '', ENT_QUOTES) ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <!-- Imprimir PDF -->
                                                <a href="orden_trabajo_pdf.php?id_orden=<?= $o['id_orden'] ?>"
                                                   target="_blank"
                                                   class="btn btn-sm btn-success"
                                                   title="Imprimir / Ver PDF">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                                <!-- Descargar PDF -->
                                                <a href="orden_trabajo_pdf.php?id_orden=<?= $o['id_orden'] ?>&download=1"
                                                   class="btn btn-sm btn-outline-success"
                                                   title="Descargar PDF">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                                <button class="btn btn-sm btn-warning btn-cambiar-estado" title="Cambiar estado"
                                                    data-id="<?= $o['id_orden'] ?>"
                                                    data-estado="<?= $o['estado'] ?>"
                                                    data-orden="ORD-<?= str_pad($o['id_orden'],6,'0',STR_PAD_LEFT) ?>">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger btn-cancelar-orden" title="Cancelar orden"
                                                    data-id="<?= $o['id_orden'] ?>"
                                                    data-orden="ORD-<?= str_pad($o['id_orden'],6,'0',STR_PAD_LEFT) ?>">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- HISTORIAL -->
                        <div class="tab-pane fade" id="tab-historial">
                            <div class="table-responsive">
                                <table id="tablaHistorial" class="table table-rec table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Orden</th>
                                            <th>Cliente</th>
                                            <th>Equipo</th>
                                            <th>Problema</th>
                                            <th>Estado Final</th>
                                            <th>Recepción</th>
                                            <th class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($ordenes_historial as $o): ?>
                                        <tr class="table-light">
                                            <td><span class="num-orden">ORD-<?= str_pad($o['id_orden'],6,'0',STR_PAD_LEFT) ?></span></td>
                                            <td>
                                                <div style="font-size:.85rem;"><?= htmlspecialchars($o['cliente_nombre']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($o['tipo_documento']) ?>: <?= htmlspecialchars($o['documento_identidad']) ?></small>
                                            </td>
                                            <td>
                                                <div style="font-size:.85rem;font-weight:600;"><?= htmlspecialchars($o['equipo_tipo']) ?> <?= htmlspecialchars($o['marca']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($o['modelo'] ?: '—') ?></small>
                                            </td>
                                            <td><small><?= htmlspecialchars(mb_substr($o['problema_reportado'] ?? '—', 0, 60)) ?></small></td>
                                            <td>
                                                <span class="badge-estado badge-<?= $o['estado'] ?>">
                                                    <?= ucfirst(str_replace('_',' ',$o['estado'])) ?>
                                                </span>
                                            </td>
                                            <td><small><?= date('d/m/Y', strtotime($o['fecha_recepcion'])) ?></small></td>
                                            <td class="text-center">
                                                <button type="button"
                                                   class="btn btn-sm btn-outline-primary btn-ver-historial"
                                                   data-id="<?= $o['id_orden'] ?>"
                                                   title="Ver detalle completo"
                                                   style="border-radius:6px;padding:4px 10px;">
                                                    <i class="fas fa-eye"></i>
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
     MODAL: NUEVA RECEPCIÓN  (diseño horizontal 2 columnas)
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalNuevaRecepcion" tabindex="-1" role="dialog" aria-labelledby="tituloNuevaRec">
    <div class="modal-dialog modal-xl" role="document" style="max-width:1050px;">
        <div class="modal-content modal-rec-nuevo">

            <!-- HEADER -->
            <div class="modal-header modal-header-rec" style="padding:14px 22px;">
                <h5 class="modal-title" id="tituloNuevaRec" style="font-size:1rem;">
                    <i class="fas fa-clipboard-plus mr-2"></i>Nueva Recepción de Equipo
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form method="POST" enctype="multipart/form-data" id="formNuevaRecepcion">
                <input type="hidden" name="accion" value="crear_recepcion">
                <input type="hidden" name="id_cliente"   id="hiddenIdCliente"   value="">
                <input type="hidden" name="tipo_cliente" id="hiddenTipoCliente" value="natural">

                <div class="modal-body p-0">
                <div class="row no-gutters">

                    <!-- ══ COLUMNA IZQUIERDA ══ -->
                    <div class="col-md-8 p-4" style="border-right:1px solid #e9ecef; overflow-y:auto; max-height:78vh;">

                        <!-- CLIENTE -->
                        <div class="rec-section-title">
                            <i class="fas fa-user-circle"></i> Datos del Cliente
                        </div>

                        <div class="d-flex mb-3" style="gap:8px;">
                            <div id="campoClienteRec" class="rec-campo-selector flex-grow-1">
                                <span class="rec-placeholder">Haz clic para seleccionar cliente...</span>
                                <span class="rec-selected" style="display:none;"></span>
                                <i class="fas fa-search" style="font-size:.8rem;color:#aaa;"></i>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger rec-btn-limpiar-cli"
                                    id="btnLimpiarClienteRec" title="Quitar cliente" style="display:none;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <!-- EQUIPO -->
                        <div class="rec-section-title mt-1">
                            <i class="fas fa-laptop"></i> Datos del Equipo
                        </div>

                        <!-- Fila 1: Tipo | Marca | Modelo -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="rec-label">Tipo <span class="text-danger">*</span></label>
                                <div class="rec-input-group">
                                    <span class="rec-ig-icon"><i class="fas fa-desktop"></i></span>
                                    <select name="tipo_equipo" class="form-control form-control-sm" required>
                                        <option value="">— Tipo —</option>
                                        <option value="Laptop">Laptop</option>
                                        <option value="PC">PC Escritorio</option>
                                        <option value="All-in-One">All-in-One</option>
                                        <option value="Tablet">Tablet</option>
                                        <option value="Impresora">Impresora</option>
                                        <option value="Monitor">Monitor</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="rec-label">Marca <span class="text-danger">*</span></label>
                                <div class="rec-input-group">
                                    <span class="rec-ig-icon"><i class="fas fa-tag"></i></span>
                                    <input type="text" name="marca" class="form-control form-control-sm" placeholder="HP, Lenovo, Dell..." required maxlength="50">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="rec-label">Modelo</label>
                                <div class="rec-input-group">
                                    <span class="rec-ig-icon"><i class="fas fa-barcode"></i></span>
                                    <input type="text" name="modelo" class="form-control form-control-sm" placeholder="Ej: HST-Q60C" maxlength="100">
                                </div>
                            </div>
                        </div>

                        <!-- Fila 2: N° Serie | Contraseña -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="rec-label">N° Serie / IMEI</label>
                                <div class="rec-input-group">
                                    <span class="rec-ig-icon"><i class="fas fa-hashtag"></i></span>
                                    <input type="text" name="numero_serie" class="form-control form-control-sm" placeholder="S/N del equipo" maxlength="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="rec-label">
                                    <i class="fas fa-key mr-1" style="color:#2563eb;"></i>Contraseña del Equipo
                                    <span class="text-muted font-weight-normal" style="font-size:.72rem;">(opcional)</span>
                                </label>
                                <div class="rec-input-group">
                                    <span class="rec-ig-icon"><i class="fas fa-lock"></i></span>
                                    <input type="text" name="contrasena_equipo" class="form-control form-control-sm"
                                           placeholder="Vacío si no aplica o se desconoce" maxlength="100" autocomplete="off">
                                </div>
                            </div>
                        </div>

                        <!-- Fila 3: Accesorios (textarea) -->
                        <div class="mb-3">
                            <label class="rec-label">Accesorios que Ingresa</label>
                            <textarea name="accesorios" class="form-control form-control-sm" rows="2"
                                placeholder="Ej: Cargador original, mouse inalámbrico, funda negra, cable HDMI..."></textarea>
                        </div>

                        <!-- Fila 4: Estado físico (textarea) -->
                        <div class="mb-3">
                            <label class="rec-label">Estado Físico del Equipo</label>
                            <textarea name="estado_fisico" class="form-control form-control-sm" rows="2"
                                placeholder="Rayones, golpes, pantalla rota, bisagra floja, etc."></textarea>
                        </div>

                        <!-- SERVICIO -->
                        <div class="rec-section-title mt-1">
                            <i class="fas fa-tools"></i> Datos del Servicio
                        </div>

                        <!-- Problema -->
                        <div class="mb-3">
                            <label class="rec-label">Problema Reportado por el Cliente</label>
                            <textarea name="problema_reportado" class="form-control form-control-sm" rows="3"
                                placeholder="Describa el problema que reporta el cliente..."></textarea>
                        </div>

                        <!-- Fila: Prioridad | Entrega | Observaciones -->
                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label class="rec-label">Prioridad</label>
                                <div class="rec-input-group">
                                    <span class="rec-ig-icon"><i class="fas fa-flag"></i></span>
                                    <select name="prioridad" class="form-control form-control-sm">
                                        <option value="baja">Baja</option>
                                        <option value="normal" selected>Normal</option>
                                        <option value="alta">Alta</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="rec-label">Entrega Estimada</label>
                                <div class="rec-input-group">
                                    <span class="rec-ig-icon"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" name="fecha_entrega_estimada" class="form-control form-control-sm" min="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="rec-label">Observaciones</label>
                                <div class="rec-input-group">
                                    <span class="rec-ig-icon"><i class="fas fa-comment-alt"></i></span>
                                    <input type="text" name="observacion" class="form-control form-control-sm" placeholder="Notas adicionales..." maxlength="255">
                                </div>
                            </div>
                        </div>

                    </div><!-- /col izquierda -->

                    <!-- ══ COLUMNA DERECHA: FOTOS ══ -->
                    <div class="col-md-4 p-4 d-flex flex-column" style="background:#fafbfc;">
                        <div class="rec-section-title">
                            <i class="fas fa-images"></i> Fotos del Equipo
                            <small class="text-muted font-weight-normal ml-1">(máx. 5)</small>
                        </div>

                        <!-- Zona upload compacta -->
                        <div class="zona-fotos-compact" id="zonaFotos"
                             onclick="document.getElementById('inputFotos').click()">
                            <i class="fas fa-camera" style="font-size:1.6rem;color:#2563eb;"></i>
                            <p class="mb-0 mt-1" style="font-size:.78rem;color:#64748b;text-align:center;">
                                Arrastra o haz clic para seleccionar
                            </p>
                            <small class="text-muted" style="font-size:.7rem;">JPG, PNG, WEBP — 5MB c/u</small>
                        </div>
                        <input type="file" name="fotos_ingreso[]" id="inputFotos" multiple accept="image/*" style="display:none;">

                        <div class="preview-fotos mt-2" id="previewFotos"></div>

                        <!-- Resumen -->
                        <div class="rec-info-box mt-auto" style="margin-top:16px !important;">
                            <div class="rec-info-row">
                                <span><i class="fas fa-user mr-1 text-primary"></i>Cliente</span>
                                <span id="resumenCliente" class="text-muted">—</span>
                            </div>
                            <div class="rec-info-row">
                                <span><i class="fas fa-laptop mr-1 text-primary"></i>Equipo</span>
                                <span id="resumenEquipo" class="text-muted">—</span>
                            </div>
                            <div class="rec-info-row">
                                <span><i class="fas fa-calendar mr-1 text-primary"></i>Fecha</span>
                                <span class="text-muted"><?= date('d/m/Y H:i') ?></span>
                            </div>
                        </div>
                    </div><!-- /col derecha -->

                </div><!-- /row -->
                </div><!-- /modal-body -->

                <div class="modal-footer" style="padding:12px 22px;">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-rec-primary btn-sm" id="btnGuardarRecepcion">
                        <i class="fas fa-save mr-1"></i> Registrar Recepción
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: SELECTOR DE CLIENTES
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalSelectorClienteRec" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border:none;border-radius:14px;box-shadow:0 15px 50px rgba(0,0,0,.2);overflow:hidden;">
            <div class="modal-header modal-header-rec">
                <h5 class="modal-title" style="font-size:.95rem;">
                    <i class="fas fa-user mr-2"></i>Seleccionar Cliente
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <!-- Barra búsqueda + botón DNI -->
                <div class="d-flex" style="gap:8px;margin-bottom:12px;">
                    <div class="input-group input-group-sm flex-grow-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" class="form-control" id="buscarClienteRec"
                               placeholder="Buscar por nombre o documento...">
                    </div>
                    <button type="button" id="btnAbrirDniRec" class="btn btn-sm btn-outline-primary" style="white-space:nowrap;">
                        <i class="fas fa-id-card mr-1"></i>Búsqueda rápida del cliente
                    </button>
                </div>
                <small class="text-muted d-block mb-2">
                    <i class="fas fa-users mr-1"></i>
                    <span id="contClientesRec"><?= count($clientes) ?></span> clientes activos
                </small>

                <!-- Lista de clientes -->
                <div id="listaClientesRec" style="max-height:380px;overflow-y:auto;">
                    <?php foreach ($clientes as $c): 
                        $es_empresa = ($c['tipo_cliente'] ?? 'natural') === 'empresa';
                        $nombre_cli = $c['nombre_display'] ?? '';
                    ?>
                    <div class="rec-item-selector"
                         data-id="<?= $c['id_cliente'] ?>"
                         data-nombre="<?= htmlspecialchars($nombre_cli) ?>"
                         data-doc="<?= htmlspecialchars($c['documento_identidad'] ?? '') ?>"
                         data-tipo-persona="<?= $es_empresa ? 'juridica' : 'natural' ?>"
                         data-tipo-cliente="<?= $c['tipo_cliente'] ?>">
                        <div class="d-flex align-items-center" style="gap:12px;">
                            <div class="rec-item-avatar <?= $es_empresa ? 'rec-item-avatar-empresa' : '' ?>">
                                <i class="fas <?= $es_empresa ? 'fa-building' : 'fa-user' ?>"></i>
                            </div>
                            <div>
                                <div class="rec-item-nombre">
                                    <?= htmlspecialchars($nombre_cli) ?>
                                </div>
                                <div class="rec-item-sub">
                                    <i class="fas fa-id-card mr-1"></i><?= htmlspecialchars($c['tipo_documento'] ?? 'DNI') ?>: <?= htmlspecialchars($c['documento_identidad'] ?? '---') ?>
                                    <?php if ($es_empresa): ?>
                                        &nbsp;<span style="background:#dbeafe;color:#1e40af;padding:1px 6px;border-radius:10px;font-size:.7rem;font-weight:700;">EMPRESA</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($clientes)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-users fa-2x mb-2 d-block" style="opacity:.3;"></i>
                        No hay clientes registrados.
                    </div>
                    <?php endif; ?>
                </div>

                <div id="sinResultadosCliRec" style="display:none;" class="text-center text-muted py-4">
                    <i class="fas fa-search fa-2x mb-2 d-block" style="opacity:.3;"></i>
                    Sin resultados para tu búsqueda.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: BUSCAR CLIENTE POR DNI / RUC
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDniRec" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document" style="max-width:440px;">
        <div class="modal-content" style="border-radius:12px;border:none;box-shadow:0 10px 40px rgba(0,0,0,.15);">
            <div class="modal-header modal-header-rec" style="border-radius:12px 12px 0 0;">
                <h5 class="modal-title" id="dniRecModalLabel" style="font-size:.95rem;">
                    <i class="fas fa-id-card mr-2"></i>Buscar por DNI / RUC
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-4">

                <!-- Tabs DNI / RUC -->
                <div class="rec-doc-tabs mb-3">
                    <button type="button" class="rec-doc-tab activo" id="tabDniRec" data-modo="dni">
                        <i class="fas fa-user mr-1"></i>DNI — Persona Natural
                    </button>
                    <button type="button" class="rec-doc-tab" id="tabRucRec" data-modo="ruc">
                        <i class="fas fa-building mr-1"></i>RUC — Empresa
                    </button>
                </div>

                <!-- Input documento -->
                <div class="rec-panel-api mb-3">
                    <label id="dniRecApiLabel" style="font-size:.82rem;font-weight:600;color:#1e40af;margin-bottom:6px;">
                        <i class="fas fa-search mr-1"></i>Consultar RENIEC
                    </label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="inputDniRec"
                               placeholder="Ingresa 8 dígitos..." maxlength="11"
                               style="font-family:monospace;font-size:1rem;font-weight:700;letter-spacing:2px;">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-sm font-weight-bold" id="btnConsultarDniRec"
                                    style="background:#1e40af;color:#fff;border-radius:0 6px 6px 0;">
                                <i class="fas fa-search mr-1"></i>Buscar
                            </button>
                        </div>
                    </div>
                    <small id="dniRecHint" class="text-muted mt-1 d-block">Ingresa los 8 dígitos del DNI</small>
                </div>

                <!-- Estado / spinner -->
                <div id="dniRecEstado" style="display:none;"></div>

                <!-- Resultado -->
                <div id="dniRecResultado" style="display:none;">
                    <div class="rec-resultado-card">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="rec-resultado-nombre" id="dniRecNombre"></div>
                            <div id="dniRecBadge"></div>
                        </div>
                        <div class="rec-resultado-dato">
                            <i class="fas fa-id-card mr-1"></i><strong id="dniRecNumero"></strong>
                        </div>
                        <div class="rec-resultado-dato" id="dniRecDirRow" style="display:none;">
                            <i class="fas fa-map-marker-alt mr-1"></i><span id="dniRecDir"></span>
                        </div>
                    </div>
                    <div id="dniRecOpciones" class="mt-3"></div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: VER DETALLE DE ORDEN
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade modal-rec" id="modalVerOrden" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="max-width:780px;">
        <div class="modal-content det-orden-modal">

            <!-- HEADER con número de orden destacado -->
            <div class="modal-header modal-header-rec" style="padding:14px 22px;">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="fas fa-clipboard-check mr-2"></i>Detalle de Orden
                    </h5>
                    <span id="detOrdenNum" class="det-num-orden-badge"></span>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <div class="modal-body p-0" style="max-height:75vh;overflow-y:auto;">

                <!-- ── BANDA DE ESTADO (prioridad + estado + fechas) ── -->
                <div class="det-banda-estado">
                    <div class="det-banda-item">
                        <span class="det-banda-label"><i class="fas fa-flag mr-1"></i>Prioridad</span>
                        <div id="detPrioridad"></div>
                    </div>
                    <div class="det-banda-sep"></div>
                    <div class="det-banda-item">
                        <span class="det-banda-label"><i class="fas fa-circle mr-1"></i>Estado</span>
                        <div id="detEstado"></div>
                    </div>
                    <div class="det-banda-sep"></div>
                    <div class="det-banda-item">
                        <span class="det-banda-label"><i class="fas fa-calendar-plus mr-1"></i>Recepción</span>
                        <span class="det-banda-valor" id="detFechaRec">—</span>
                    </div>
                    <div class="det-banda-sep"></div>
                    <div class="det-banda-item">
                        <span class="det-banda-label"><i class="fas fa-calendar-check mr-1"></i>Entrega Est.</span>
                        <span class="det-banda-valor" id="detFechaEst">—</span>
                    </div>
                    <div class="det-banda-sep"></div>
                    <div class="det-banda-item">
                        <span class="det-banda-label"><i class="fas fa-user-tie mr-1"></i>Recepcionado por</span>
                        <span class="det-banda-valor" id="detTecnico">—</span>
                    </div>
                </div>

                <div class="p-4">
                    <!-- ── FILA: CLIENTE + EQUIPO ── -->
                    <div class="row mb-3">
                        <!-- Cliente -->
                        <div class="col-md-5">
                            <div class="det-ficha det-ficha-cliente">
                                <div class="det-ficha-header">
                                    <i class="fas fa-user-circle"></i> Cliente
                                </div>
                                <div class="det-ficha-body">
                                    <div class="det-ficha-row">
                                        <span class="det-ficha-lbl">Nombre</span>
                                        <span class="det-ficha-val font-weight-bold" id="detCliente"></span>
                                    </div>
                                    <div class="det-ficha-row">
                                        <span class="det-ficha-lbl">Documento</span>
                                        <span class="det-ficha-val" id="detDoc"></span>
                                    </div>
                                    <div class="det-ficha-row">
                                        <span class="det-ficha-lbl"><i class="fas fa-phone mr-1"></i>Teléfono</span>
                                        <span class="det-ficha-val" id="detTelefono"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Equipo -->
                        <div class="col-md-7">
                            <div class="det-ficha det-ficha-equipo">
                                <div class="det-ficha-header">
                                    <i class="fas fa-laptop"></i> Equipo
                                </div>
                                <div class="det-ficha-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="det-ficha-row">
                                                <span class="det-ficha-lbl">Tipo</span>
                                                <span class="det-ficha-val" id="detTipo"></span>
                                            </div>
                                            <div class="det-ficha-row">
                                                <span class="det-ficha-lbl">Marca</span>
                                                <span class="det-ficha-val font-weight-bold" id="detMarca"></span>
                                            </div>
                                            <div class="det-ficha-row">
                                                <span class="det-ficha-lbl">Modelo</span>
                                                <span class="det-ficha-val" id="detModelo"></span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="det-ficha-row">
                                                <span class="det-ficha-lbl">Serie / IMEI</span>
                                                <span class="det-ficha-val" id="detSerie"></span>
                                            </div>
                                            <div class="det-ficha-row">
                                                <span class="det-ficha-lbl">Accesorios</span>
                                                <span class="det-ficha-val" id="detAccesorios"></span>
                                            </div>
                                            <div class="det-ficha-row">
                                                <span class="det-ficha-lbl"><i class="fas fa-key mr-1" style="color:#2563eb;"></i>Contraseña</span>
                                                <span class="det-ficha-val" id="detContrasena">—</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── ESTADO FÍSICO + PROBLEMA ── -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="det-texto-box">
                                <div class="det-texto-label">
                                    <i class="fas fa-eye mr-1"></i>Estado Físico al Ingreso
                                </div>
                                <div class="det-texto-contenido" id="detEstadoFisico">—</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="det-texto-box det-texto-box-problema">
                                <div class="det-texto-label">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>Problema Reportado
                                </div>
                                <div class="det-texto-contenido" id="detProblema">—</div>
                            </div>
                        </div>
                    </div>

                    <!-- ── FOTOS ── -->
                    <div id="seccionFotos" style="display:none;">
                        <div class="det-fotos-seccion">
                            <div class="det-texto-label mb-2">
                                <i class="fas fa-images mr-1"></i>Fotos del Equipo al Ingreso
                            </div>
                            <div class="fotos-existentes" id="detFotos"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="padding:12px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cerrar
                </button>
                <a id="btnDescargarPdfDetalle" href="#" target="_blank"
                   class="btn btn-sm btn-outline-success font-weight-bold">
                    <i class="fas fa-file-pdf mr-1"></i>Descargar PDF
                </a>
                <a id="btnImprimirPdfDetalle" href="#" target="_blank"
                   class="btn btn-sm btn-success font-weight-bold">
                    <i class="fas fa-print mr-1"></i>Imprimir
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: CAMBIAR ESTADO
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade modal-rec" id="modalCambiarEstado" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header modal-header-rec">
                <h5 class="modal-title"><i class="fas fa-exchange-alt mr-2"></i>Cambiar Estado</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" id="formCambiarEstado">
                <input type="hidden" name="accion" value="cambiar_estado">
                <input type="hidden" name="id_orden" id="ceIdOrden">
                <div class="modal-body">
                    <p class="text-muted mb-3" style="font-size:.88rem;">
                        Orden: <strong id="ceOrdenNum"></strong>
                    </p>
                    <div class="mb-3">
                        <label class="form-label-rec">Nuevo Estado <span class="text-danger">*</span></label>
                        <select name="nuevo_estado" id="ceNuevoEstado" class="form-control" required>
                            <option value="recibido">Recibido</option>
                            <option value="diagnostico">En Diagnóstico</option>
                            <option value="en_proceso">En Proceso</option>
                            <option value="listo">Listo para Entrega</option>
                            <option value="entregado">Entregado</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label-rec">Descripción / Nota</label>
                        <textarea name="descripcion_cambio" class="form-control" rows="3"
                            placeholder="Ej: Se realizó diagnóstico, falla en disco duro..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-rec-primary btn-sm">
                        <i class="fas fa-check mr-1"></i> Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: VER FOTO AMPLIADA (visor con navegación)
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalFotoAmpliada" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width:720px;">
        <div class="modal-content" style="background:#111;border:none;border-radius:12px;overflow:hidden;">
            <!-- Header -->
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;background:#1a1a1a;">
                <span style="color:#fff;font-size:.85rem;font-weight:600;">
                    <i class="fas fa-images mr-2" style="color:#60a5fa;"></i>
                    Fotos del Equipo — <span id="fotoContador" style="color:#93c5fd;">1 / 1</span>
                </span>
                <button type="button" onclick="$('#modalFotoAmpliada').modal('hide')"
                        style="background:none;border:none;color:#fff;font-size:1.3rem;cursor:pointer;opacity:.7;line-height:1;">
                    &times;
                </button>
            </div>
            <!-- Imagen principal -->
            <div style="position:relative;background:#000;min-height:380px;display:flex;align-items:center;justify-content:center;">
                <button id="fotoAnterior" onclick="navegarFoto(-1)"
                        style="position:absolute;left:10px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:none;color:#fff;width:40px;height:40px;border-radius:50%;font-size:1.1rem;cursor:pointer;z-index:10;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <img id="fotoAmpliadaSrc" src="" alt="Foto equipo"
                     style="max-width:100%;max-height:420px;object-fit:contain;display:block;">
                <button id="fotoSiguiente" onclick="navegarFoto(1)"
                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:none;color:#fff;width:40px;height:40px;border-radius:50%;font-size:1.1rem;cursor:pointer;z-index:10;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <!-- Miniaturas -->
            <div id="fotoMiniaturas" style="display:flex;gap:8px;padding:10px 14px;background:#1a1a1a;overflow-x:auto;justify-content:center;flex-wrap:wrap;"></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: TICKET IMPRIMIBLE
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalTicketImprimible" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width:420px;">
        <div class="modal-content" style="border-radius:12px;border:none;box-shadow:0 10px 40px rgba(0,0,0,.2);">
            <div class="modal-header modal-header-rec" style="padding:12px 18px;">
                <h5 class="modal-title" style="font-size:.95rem;">
                    <i class="fas fa-print mr-2"></i>Orden de Trabajo
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-3">
                <div id="ticketContenido" style="font-family:'Courier New',monospace;font-size:.78rem;color:#111;background:#fff;padding:12px;border:1px solid #e2e8f0;border-radius:8px;">
                    <!-- Cabecera empresa -->
                    <div style="text-align:center;margin-bottom:10px;">
                        <?php
                        $emp = [];
                        try { $emp = $pdo->query("SELECT * FROM empresa LIMIT 1")->fetch() ?: []; } catch(Exception $e){}
                        ?>
                        <?php if (!empty($emp['logo'])): ?>
                        <img src="<?= htmlspecialchars($emp['logo']) ?>" alt="Logo" style="max-height:50px;max-width:160px;object-fit:contain;margin-bottom:6px;display:block;margin-left:auto;margin-right:auto;">
                        <?php endif; ?>
                        <div style="font-weight:700;font-size:.9rem;text-transform:uppercase;">
                            <?= htmlspecialchars($emp['razon_social'] ?? 'INVERSIONES CH COMPUTER S.R.L.') ?>
                        </div>
                        <div><?= htmlspecialchars($emp['direccion'] ?? '') ?></div>
                        <div><?= htmlspecialchars($emp['telefono'] ?? '') ?></div>
                        <?php if (!empty($emp['web'])): ?><div><?= htmlspecialchars($emp['web']) ?></div><?php endif; ?>
                        <?php if (!empty($emp['email'])): ?><div><?= htmlspecialchars($emp['email']) ?></div><?php endif; ?>
                        <div id="tickFechaImpresion" style="margin-top:4px;"></div>
                    </div>
                    <div style="border-top:1px dashed #999;margin:6px 0;"></div>
                    <div style="text-align:center;font-weight:700;font-size:1rem;margin:6px 0;">ORDEN DE TRABAJO</div>
                    <div style="text-align:center;font-weight:700;font-size:1.1rem;margin-bottom:8px;">
                        N° <span id="tickNumOrden"></span>
                    </div>
                    <div style="border-top:1px dashed #999;margin:6px 0;"></div>
                    <div style="font-weight:700;margin:6px 0 3px;">INFORMACIÓN DEL CLIENTE</div>
                    <div>Cliente: <span id="tickCliente"></span></div>
                    <div>Teléfono: <span id="tickTelefono"></span></div>
                    <div style="border-top:1px dashed #999;margin:6px 0;"></div>
                    <div style="font-weight:700;margin:6px 0 3px;">INFORMACIÓN DE LA ORDEN</div>
                    <div>Fecha/hora ingreso: <span id="tickFechaRec"></span></div>
                    <div>Prioridad: <span id="tickPrioridad" style="font-weight:700;text-transform:uppercase;"></span></div>
                    <div>Fecha Prometida: <span id="tickFechaEst"></span></div>
                    <div style="margin-top:3px;">Desperfecto: <span id="tickProblema"></span></div>
                    <div style="border-top:1px dashed #999;margin:6px 0;"></div>
                    <div style="font-weight:700;margin:6px 0 3px;">INFORMACIÓN DEL EQUIPO</div>
                    <div>Serie/IMEI: <span id="tickSerie"></span></div>
                    <div>Marca y modelo: <span id="tickEquipo"></span></div>
                    <div style="border-top:1px dashed #999;margin:6px 0;"></div>
                    <div style="text-align:center;font-size:.72rem;margin-top:8px;color:#555;">
                        <?= htmlspecialchars($emp['pie_comprobante'] ?? 'SERVICIO TÉCNICO ESPECIALIZADO') ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding:10px 16px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cerrar
                </button>
                <button type="button" class="btn btn-success btn-sm font-weight-bold" id="btnConfirmarImpresion">
                    <i class="fas fa-print mr-1"></i>Imprimir
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL: VER DETALLE HISTORIAL ORDEN
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDetalleHistorial" tabindex="-1" role="dialog" aria-labelledby="tituloDetalleHist">
    <div class="modal-dialog modal-xl" role="document" style="max-width:960px;">
        <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25);">

            <!-- HEADER -->
            <div class="modal-header" id="mdhHeaderHist"
                 style="background:linear-gradient(135deg,#1a3a5c,#1a5276);color:#fff;padding:18px 24px;border:none;">
                <div class="d-flex align-items-center" style="gap:12px;">
                    <div style="width:42px;height:42px;background:rgba(255,255,255,.15);border-radius:10px;
                                display:flex;align-items:center;justify-content:center;font-size:1.2rem;">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div>
                        <h5 class="m-0 font-weight-bold" id="tituloDetalleHist">Detalle de Orden</h5>
                        <small style="opacity:.8;" id="mdhSubtitulo">Cargando...</small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="opacity:.8;font-size:1.4rem;">
                    <span>&times;</span>
                </button>
            </div>

            <!-- BODY -->
            <div class="modal-body p-0" id="mdhCuerpo">
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x" style="color:#1a5276;"></i>
                    <p class="mt-3 text-muted">Cargando información...</p>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer" style="background:#f8f9fa;border-top:1px solid #e9ecef;padding:12px 20px;">
                <a id="mdhBtnVerCompleto" href="#" target="_blank"
                   class="btn btn-sm font-weight-bold"
                   style="background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;border-radius:8px;padding:8px 18px;">
                    <i class="fas fa-external-link-alt mr-1"></i>Ver orden completa
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cerrar
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     FORM OCULTO: CANCELAR ORDEN
     ══════════════════════════════════════════════════════════ -->
    <input type="hidden" name="accion" value="cancelar_orden">
    <input type="hidden" name="id_orden" id="cancelIdOrden">
</form>

<?php
$extra_js  = '<script src="js/recepcion.js?v=' . time() . '"></script>';
$extra_js .= '<script src="js/recepcion_historial.js?v=' . time() . '"></script>';
include $ruta_base . 'includes/footer.php';
?>