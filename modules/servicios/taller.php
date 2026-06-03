<?php
// ============================================================
// modules/servicios/taller.php | SysInversiones CH Computer 2026
// Interfaz del Tecnico - Ver, tomar y trabajar ordenes de servicio
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexion BD no disponible.');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_TECNICO'))       define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_TECNICO]);
verificarPermiso($pdo, 'taller');

$id_usuario_sesion = (int)($_SESSION['id_usuario'] ?? 0);
$rol_sesion        = (int)($_SESSION['id_rol']     ?? 0);
$es_admin          = ($rol_sesion === ROL_ADMINISTRADOR);

$swal = null;
if (isset($_SESSION['swal_taller'])) { $swal = $_SESSION['swal_taller']; unset($_SESSION['swal_taller']); }

function redirigirTaller(string $icon, string $title, string $text): void {
    $_SESSION['swal_taller'] = compact('icon', 'title', 'text');
    header('Location: taller.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion   = $_POST['accion']   ?? '';
    $id_orden = (int)($_POST['id_orden'] ?? 0);

    if ($accion === 'tomar_orden' && $id_orden) {
        try {
            $check = $pdo->prepare("SELECT id_tecnico, estado FROM ordenes_servicio WHERE id_orden=?");
            $check->execute([$id_orden]);
            $ord = $check->fetch();
            if (!$ord) redirigirTaller('error', 'Error', 'Orden no encontrada.');
            if ($ord['id_tecnico'] && !$es_admin)
                redirigirTaller('warning', 'No disponible', 'Esta orden ya fue tomada por otro tecnico.');
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE ordenes_servicio SET id_tecnico=?, estado='diagnostico' WHERE id_orden=?")
                ->execute([$id_usuario_sesion, $id_orden]);
            $pdo->prepare("INSERT INTO servicio_historial (id_orden, id_usuario, estado, descripcion, fecha) VALUES (?,?,?,?,NOW())")
                ->execute([$id_orden, $id_usuario_sesion, 'diagnostico', 'Orden tomada por el tecnico. Iniciando diagnostico.']);
            $pdo->commit();
            redirigirTaller('success', 'Orden tomada!', 'La orden ORD-' . str_pad($id_orden,6,'0',STR_PAD_LEFT) . ' esta ahora en tu taller.');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            redirigirTaller('error', 'Error', $e->getMessage());
        }
    }
}

// ── DATOS ─────────────────────────────────────────────────────────────────────
$ordenes_disponibles = $pdo->query("
    SELECT o.*,
           CASE o.tipo_cliente
               WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
               ELSE TRIM(CONCAT_WS(', ',
                   NULLIF(TRIM(CONCAT_WS(' ', cn.apellido_paterno, cn.apellido_materno)),''),
                   NULLIF(TRIM(cn.nombres),'')
               ))
           END AS cliente_nombre,
           o.tipo_cliente,
           CASE o.tipo_cliente WHEN 'empresa' THEN ce.telefono ELSE cn.telefono END AS telefono,
           e.tipo AS equipo_tipo, e.marca, e.modelo, e.numero_serie, e.fotos_ingreso,
           u.nombre_completo AS recepcionado_por
    FROM ordenes_servicio o
    LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = o.id_cliente AND o.tipo_cliente = 'natural'
    LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = o.id_cliente AND o.tipo_cliente = 'empresa'
    JOIN equipos  e ON e.id_equipo  = o.id_equipo
    LEFT JOIN usuarios u ON u.id_usuario = o.id_usuario
    WHERE o.id_tecnico IS NULL AND o.estado = 'recibido'
    ORDER BY FIELD(o.prioridad,'alta','normal','media','baja'), o.fecha_recepcion ASC
")->fetchAll();

if ($es_admin) {
    $mis_ordenes = $pdo->query("
        SELECT o.*,
               CASE o.tipo_cliente
                   WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
                   ELSE TRIM(CONCAT_WS(', ',
                       NULLIF(TRIM(CONCAT_WS(' ', cn.apellido_paterno, cn.apellido_materno)),''),
                       NULLIF(TRIM(cn.nombres),'')
                   ))
               END AS cliente_nombre,
               o.tipo_cliente,
               CASE o.tipo_cliente WHEN 'empresa' THEN ce.telefono ELSE cn.telefono END AS telefono,
               e.tipo AS equipo_tipo, e.marca, e.modelo, e.numero_serie,
               e.fotos_ingreso, e.contrasena_equipo, e.estado_fisico, e.accesorios,
               u.nombre_completo AS recepcionado_por,
               t.nombre_completo AS tecnico_nombre
        FROM ordenes_servicio o
        LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = o.id_cliente AND o.tipo_cliente = 'natural'
        LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = o.id_cliente AND o.tipo_cliente = 'empresa'
        JOIN equipos  e ON e.id_equipo  = o.id_equipo
        LEFT JOIN usuarios u ON u.id_usuario = o.id_usuario
        LEFT JOIN usuarios t ON t.id_usuario = o.id_tecnico
        WHERE o.estado NOT IN ('recibido','entregado','cancelado')
        ORDER BY FIELD(o.prioridad,'alta','normal','media','baja'), o.fecha_recepcion ASC
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT o.*,
               CASE o.tipo_cliente
                   WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
                   ELSE TRIM(CONCAT_WS(', ',
                       NULLIF(TRIM(CONCAT_WS(' ', cn.apellido_paterno, cn.apellido_materno)),''),
                       NULLIF(TRIM(cn.nombres),'')
                   ))
               END AS cliente_nombre,
               o.tipo_cliente,
               CASE o.tipo_cliente WHEN 'empresa' THEN ce.telefono ELSE cn.telefono END AS telefono,
               e.tipo AS equipo_tipo, e.marca, e.modelo, e.numero_serie,
               e.fotos_ingreso, e.contrasena_equipo, e.estado_fisico, e.accesorios,
               u.nombre_completo AS recepcionado_por,
               t.nombre_completo AS tecnico_nombre
        FROM ordenes_servicio o
        LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = o.id_cliente AND o.tipo_cliente = 'natural'
        LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = o.id_cliente AND o.tipo_cliente = 'empresa'
        JOIN equipos  e ON e.id_equipo  = o.id_equipo
        LEFT JOIN usuarios u ON u.id_usuario = o.id_usuario
        LEFT JOIN usuarios t ON t.id_usuario = o.id_tecnico
        WHERE o.id_tecnico = ? AND o.estado NOT IN ('entregado','cancelado')
        ORDER BY FIELD(o.prioridad,'alta','normal','media','baja'), o.fecha_recepcion ASC
    ");
    $stmt->execute([$id_usuario_sesion]);
    $mis_ordenes = $stmt->fetchAll();
}

$total_disponibles = count($ordenes_disponibles);
$total_mis         = count($mis_ordenes);
$total_listas      = count(array_filter($mis_ordenes, fn($o) => $o['estado'] === 'listo'));

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>

<link rel="stylesheet" href="css/taller.css?v=<?= time() ?>">

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="page-header-taller d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4><i class="fas fa-wrench mr-2"></i>Taller Tecnico</h4>
                    <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones CH Computer &rsaquo; Servicio Tecnico &rsaquo; Taller</small>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <?php if ($swal): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon: '<?= $swal['icon'] ?>',
                    title: '<?= addslashes($swal['title']) ?>',
                    text: '<?= addslashes($swal['text']) ?>',
                    confirmButtonColor: '#059669',
                    timer: <?= in_array($swal['icon'],['success','info']) ? 3500 : 0 ?>,
                    timerProgressBar: <?= in_array($swal['icon'],['success','info']) ? 'true' : 'false' ?>,
                    showConfirmButton: <?= in_array($swal['icon'],['success','info']) ? 'false' : 'true' ?>,
                });
            });
            </script>
            <?php endif; ?>

            <!-- STATS -->
            <div class="row mb-4">
                <div class="col-6 col-md-3 mb-2">
                    <div class="taller-stat" style="background:linear-gradient(135deg,#1e40af,#2563eb);">
                        <i class="fas fa-inbox"></i>
                        <div><div class="taller-stat-val"><?= $total_disponibles ?></div><div class="taller-stat-lbl">Disponibles</div></div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="taller-stat" style="background:linear-gradient(135deg,#92400e,#f59e0b);">
                        <i class="fas fa-tools"></i>
                        <div><div class="taller-stat-val"><?= $total_mis ?></div><div class="taller-stat-lbl"><?= $es_admin ? 'En taller' : 'Mis ordenes' ?></div></div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="taller-stat" style="background:linear-gradient(135deg,#065f46,#10b981);">
                        <i class="fas fa-check-circle"></i>
                        <div><div class="taller-stat-val"><?= $total_listas ?></div><div class="taller-stat-lbl">Listas p/ entregar</div></div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <div class="taller-stat" style="background:linear-gradient(135deg,#4c1d95,#7c3aed);">
                        <i class="fas fa-list-alt"></i>
                        <div><div class="taller-stat-val"><?= $total_disponibles + $total_mis ?></div><div class="taller-stat-lbl">Total activas</div></div>
                    </div>
                </div>
            </div>

            <!-- CARD PRINCIPAL -->
            <div class="card shadow-sm">
                <div class="taller-card-header d-flex align-items-center justify-content-between">
                    <h6 class="m-0 text-white"><i class="fas fa-clipboard-list mr-2"></i>Ordenes de Servicio</h6>
                    <ul class="nav nav-tabs card-header-tabs ml-auto" id="tallerTab" role="tablist" style="border-bottom:none;">
                        <li class="nav-item">
                            <a class="nav-link active text-white font-weight-bold" data-toggle="tab" href="#tab-disponibles" role="tab">
                                <i class="fas fa-inbox mr-1"></i>Disponibles <span class="badge badge-light ml-1"><?= $total_disponibles ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white font-weight-bold" data-toggle="tab" href="#tab-mis-ordenes" role="tab">
                                <i class="fas fa-tools mr-1"></i><?= $es_admin ? 'En Taller' : 'Mis Ordenes' ?> <span class="badge badge-light ml-1"><?= $total_mis ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">

                        <!-- TAB DISPONIBLES -->
                        <div class="tab-pane fade show active" id="tab-disponibles">
                            <?php if (empty($ordenes_disponibles)): ?>
                            <div class="text-center py-5">
                                <div style="width:80px;height:80px;background:linear-gradient(135deg,#d1fae5,#a7f3d0);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                                    <i class="fas fa-check-double" style="font-size:2rem;color:#059669;"></i>
                                </div>
                                <h6 style="color:#065f46;font-weight:700;">Todo al dia!</h6>
                                <p class="text-muted" style="font-size:.88rem;">No hay ordenes disponibles en este momento.</p>
                            </div>
                            <?php else: ?>
                            <div class="row">
                                <?php foreach ($ordenes_disponibles as $o): ?>
                                <?php
                                    $prio_icon = $o['prioridad'] === 'alta' ? '🔴' : ($o['prioridad'] === 'baja' ? '🟢' : '🟡');
                                ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="taller-orden-card taller-card-disponible prio-<?= $o['prioridad'] ?>">
                                        <div class="taller-orden-header">
                                            <span class="taller-num-orden">ORD-<?= str_pad($o['id_orden'],6,'0',STR_PAD_LEFT) ?></span>
                                            <span class="badge-prioridad-taller badge-prio-<?= $o['prioridad'] ?>">
                                                <?= $prio_icon ?> <?= ucfirst($o['prioridad']) ?>
                                            </span>
                                        </div>
                                        <div class="taller-orden-body">
                                            <div class="taller-cliente">
                                                <i class="fas fa-user-circle"></i>
                                                <?= htmlspecialchars($o['cliente_nombre']) ?>
                                            </div>
                                            <?php if (!empty($o['telefono'])): ?>
                                            <div style="font-size:.78rem;color:#64748b;margin-bottom:6px;">
                                                <i class="fas fa-phone mr-1" style="color:#059669;"></i><?= htmlspecialchars($o['telefono']) ?>
                                            </div>
                                            <?php endif; ?>
                                            <div class="taller-equipo">
                                                <i class="fas fa-laptop"></i>
                                                <strong><?= htmlspecialchars($o['equipo_tipo'] . ' ' . $o['marca']) ?></strong>
                                                <?php if (!empty($o['modelo'])): ?>
                                                <span class="text-muted"> &mdash; <?= htmlspecialchars($o['modelo']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="taller-problema">
                                                <i class="fas fa-exclamation-triangle" style="color:#f59e0b;flex-shrink:0;margin-top:1px;"></i>
                                                <span><?= htmlspecialchars(mb_substr($o['problema_reportado'] ?? 'Sin descripcion', 0, 90)) ?></span>
                                            </div>
                                            <div class="taller-meta">
                                                <span><i class="fas fa-calendar-plus"></i><?= date('d/m/Y H:i', strtotime($o['fecha_recepcion'])) ?></span>
                                                <?php if (!empty($o['fecha_entrega_estimada'])): ?>
                                                <?php $vencida = new DateTime($o['fecha_entrega_estimada']) < new DateTime(); ?>
                                                <span style="<?= $vencida ? 'background:#fee2e2;color:#991b1b;' : '' ?>">
                                                    <i class="fas fa-calendar-check"></i><?= date('d/m/Y', strtotime($o['fecha_entrega_estimada'])) ?>
                                                    <?= $vencida ? ' <i class="fas fa-exclamation-triangle text-danger"></i>' : '' ?>
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="taller-orden-footer">
                                            <button type="button" class="btn btn-taller-tomar btn-sm btn-block btn-tomar-swal"
                                                data-id="<?= $o['id_orden'] ?>"
                                                data-num="ORD-<?= str_pad($o['id_orden'],6,'0',STR_PAD_LEFT) ?>"
                                                data-cliente="<?= htmlspecialchars($o['cliente_nombre'], ENT_QUOTES) ?>"
                                                data-equipo="<?= htmlspecialchars($o['equipo_tipo'].' '.$o['marca'].(!empty($o['modelo']) ? ' '.$o['modelo'] : ''), ENT_QUOTES) ?>"
                                                data-problema="<?= htmlspecialchars(mb_substr($o['problema_reportado'] ?? '', 0, 80), ENT_QUOTES) ?>"
                                                data-prioridad="<?= $o['prioridad'] ?>">
                                                <i class="fas fa-hand-pointer mr-2"></i>Tomar esta orden
                                            </button>
                                            <form method="POST" id="formTomar_<?= $o['id_orden'] ?>" style="display:none;">
                                                <input type="hidden" name="accion" value="tomar_orden">
                                                <input type="hidden" name="id_orden" value="<?= $o['id_orden'] ?>">
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB MIS ORDENES -->
                        <div class="tab-pane fade" id="tab-mis-ordenes">
                            <?php if (empty($mis_ordenes)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-tools fa-3x mb-3 d-block" style="opacity:.3;"></i>
                                <p>No tienes ordenes asignadas actualmente.</p>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table id="tablaMisOrdenes" class="table table-taller table-bordered table-hover table-sm">
                                    <thead>
                                        <tr>
                                            <th>Orden</th>
                                            <th>Cliente</th>
                                            <th>Equipo</th>
                                            <th>Problema</th>
                                            <th>Prioridad</th>
                                            <th>Estado</th>
                                            <?php if ($es_admin): ?><th>Tecnico</th><?php endif; ?>
                                            <th>Recepcion</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($mis_ordenes as $o): ?>
                                        <tr>
                                            <td>
                                                <span class="taller-num-orden-sm">ORD-<?= str_pad($o['id_orden'],6,'0',STR_PAD_LEFT) ?></span>
                                            </td>
                                            <td>
                                                <div style="font-size:.85rem;font-weight:600;"><?= htmlspecialchars($o['cliente_nombre']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($o['telefono'] ?? '') ?></small>
                                            </td>
                                            <td>
                                                <div style="font-size:.85rem;font-weight:600;">
                                                    <?= htmlspecialchars($o['equipo_tipo'] . ' ' . $o['marca']) ?>
                                                </div>
                                                <small class="text-muted"><?= htmlspecialchars($o['modelo'] ?: '---') ?></small>
                                            </td>
                                            <td>
                                                <small><?= htmlspecialchars(mb_substr($o['problema_reportado'] ?? 'Sin descripcion', 0, 55)) ?><?= mb_strlen($o['problema_reportado'] ?? '') > 55 ? '...' : '' ?></small>
                                            </td>
                                            <td>
                                                <span class="badge-prioridad-taller badge-prio-<?= $o['prioridad'] ?>"><?= ucfirst($o['prioridad']) ?></span>
                                            </td>
                                            <td>
                                                <span class="taller-badge-estado taller-estado-<?= $o['estado'] ?>"><?= ucfirst(str_replace('_',' ',$o['estado'])) ?></span>
                                            </td>
                                            <?php if ($es_admin): ?>
                                            <td><small><?= htmlspecialchars($o['tecnico_nombre'] ?? '---') ?></small></td>
                                            <?php endif; ?>
                                            <td><small><?= date('d/m/Y H:i', strtotime($o['fecha_recepcion'])) ?></small></td>
                                            <td class="text-center">
                                                <a href="orden_trabajo.php?id=<?= $o['id_orden'] ?>"
                                                   class="btn btn-sm btn-success" title="Trabajar orden">
                                                    <i class="fas fa-tools"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
$extra_js = '<script src="js/taller.js?v=' . time() . '"></script>';
include $ruta_base . 'includes/footer.php';
?>
