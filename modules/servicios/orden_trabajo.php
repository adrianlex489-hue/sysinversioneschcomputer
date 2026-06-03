<?php
// ============================================================
// modules/servicios/orden_trabajo.php | SysInversiones CH Computer 2026
// Página completa de trabajo de una orden — layout secciones verticales
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexión BD no disponible.');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_TECNICO'))       define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_TECNICO]);
verificarPermiso($pdo, 'servicios');

$id_usuario_sesion = (int)($_SESSION['id_usuario'] ?? 0);
$rol_sesion        = (int)($_SESSION['id_rol']     ?? 0);
$es_admin          = ($rol_sesion === ROL_ADMINISTRADOR);

$id_orden = (int)($_GET['id'] ?? 0);
if (!$id_orden) { header('Location: taller.php'); exit; }

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
           CASE o.tipo_cliente WHEN 'empresa' THEN ce.email    ELSE cn.email    END AS cliente_email,
           CASE o.tipo_cliente WHEN 'empresa' THEN ce.direccion ELSE cn.direccion END AS cliente_direccion,
           e.tipo AS equipo_tipo, e.marca, e.modelo, e.numero_serie,
           e.accesorios, e.estado_fisico, e.contrasena_equipo, e.fotos_ingreso,
           u.nombre_completo AS recepcionado_por,
           t.nombre_completo AS tecnico_nombre
    FROM ordenes_servicio o
    LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = o.id_cliente AND o.tipo_cliente = 'natural'
    LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = o.id_cliente AND o.tipo_cliente = 'empresa'
    JOIN equipos  e  ON e.id_equipo  = o.id_equipo
    LEFT JOIN usuarios u ON u.id_usuario = o.id_usuario
    LEFT JOIN usuarios t ON t.id_usuario = o.id_tecnico
    WHERE o.id_orden = ?
");
$stmt->execute([$id_orden]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$orden) { header('Location: taller.php'); exit; }
if (!$es_admin && $orden['id_tecnico'] != $id_usuario_sesion) { header('Location: taller.php'); exit; }

$detalle = $pdo->prepare("SELECT d.*, s.nombre AS nombre_servicio FROM detalle_orden d LEFT JOIN servicios s ON s.id_servicio = d.id_servicio WHERE d.id_orden = ? ORDER BY d.id ASC");
$detalle->execute([$id_orden]);
$servicios_aplicados = $detalle->fetchAll(PDO::FETCH_ASSOC);

$hist = $pdo->prepare("SELECT h.*, u.nombre_completo AS usuario_nombre FROM servicio_historial h LEFT JOIN usuarios u ON u.id_usuario = h.id_usuario WHERE h.id_orden = ? ORDER BY h.fecha DESC");
$hist->execute([$id_orden]);
$historial = $hist->fetchAll(PDO::FETCH_ASSOC);

$catalogo = $pdo->query("SELECT s.id_servicio, s.nombre, s.tipo, s.precio_base, GROUP_CONCAT(st.tipo_equipo SEPARATOR ',') AS tipos_equipo FROM servicios s LEFT JOIN servicio_tipos st ON st.id_servicio = s.id_servicio WHERE s.estado = 1 GROUP BY s.id_servicio ORDER BY s.nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

$fotos_taller  = [];
if (!empty($orden['fotos_taller']))  { $d = json_decode($orden['fotos_taller'],  true); if (is_array($d)) $fotos_taller  = $d; }
$fotos_ingreso = [];
if (!empty($orden['fotos_ingreso'])) { $d = json_decode($orden['fotos_ingreso'], true); if (is_array($d)) $fotos_ingreso = $d; }

$swal = null;
if (isset($_SESSION['swal_ot'])) { $swal = $_SESSION['swal_ot']; unset($_SESSION['swal_ot']); }

function redirigirOT(int $id, string $icon, string $title, string $text): void {
    $_SESSION['swal_ot'] = compact('icon','title','text');
    header("Location: orden_trabajo.php?id=$id"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diagnostico   = trim($_POST['diagnostico']             ?? '');
    $solucion      = trim($_POST['solucion']                ?? '');
    $componentes   = trim($_POST['descripcion_componentes'] ?? '');
    $contrasena    = trim($_POST['contrasena_equipo']       ?? '') ?: null;
    $nuevo_estado  = $_POST['nuevo_estado']                 ?? '';
    $nota_cambio   = trim($_POST['nota_cambio']             ?? '');
    $servicios_ids = $_POST['servicios_ids']                ?? [];
    $servicios_pre = $_POST['servicios_precios']            ?? [];
    $servicios_can = $_POST['servicios_cantidades']         ?? [];

    if (!in_array($nuevo_estado, ['diagnostico','en_proceso','listo','entregado']))
        redirigirOT($id_orden, 'warning', 'Estado inválido', 'Selecciona un estado válido.');

    try {
        $pdo->beginTransaction();
        $ea = $pdo->prepare("SELECT estado, id_equipo FROM ordenes_servicio WHERE id_orden=?");
        $ea->execute([$id_orden]); $ord_actual = $ea->fetch();

        $pdo->prepare("UPDATE ordenes_servicio SET diagnostico=?, solucion=?, descripcion_componentes=?, estado=? WHERE id_orden=?")
            ->execute([$diagnostico ?: null, $solucion ?: null, $componentes ?: null, $nuevo_estado, $id_orden]);

        if ($contrasena !== null && $ord_actual['id_equipo'])
            $pdo->prepare("UPDATE equipos SET contrasena_equipo=? WHERE id_equipo=?")->execute([$contrasena, $ord_actual['id_equipo']]);

        if ($nuevo_estado !== $ord_actual['estado']) {
            $desc = $nota_cambio ?: 'Estado actualizado a: ' . ucfirst(str_replace('_',' ',$nuevo_estado));
            $pdo->prepare("INSERT INTO servicio_historial (id_orden, id_usuario, estado, descripcion, fecha) VALUES (?,?,?,?,NOW())")
                ->execute([$id_orden, $id_usuario_sesion, $nuevo_estado, $desc]);
        }

        $pdo->prepare("DELETE FROM detalle_orden WHERE id_orden=?")->execute([$id_orden]);
        $costo_total = 0;
        if (!empty($servicios_ids)) {
            $stDet = $pdo->prepare("INSERT INTO detalle_orden (id_orden, id_servicio, descripcion, precio, cantidad, subtotal) VALUES (?,?,?,?,?,?)");
            foreach ($servicios_ids as $i => $id_srv) {
                $id_srv = (int)$id_srv; $precio = (float)($servicios_pre[$i] ?? 0);
                $cant = max(1,(int)($servicios_can[$i] ?? 1)); $sub = $precio * $cant; $costo_total += $sub;
                $nom = $pdo->prepare("SELECT nombre FROM servicios WHERE id_servicio=?"); $nom->execute([$id_srv]);
                $stDet->execute([$id_orden, $id_srv, $nom->fetchColumn() ?: '', $precio, $cant, $sub]);
            }
        }
        $pdo->prepare("UPDATE ordenes_servicio SET costo_total=? WHERE id_orden=?")->execute([$costo_total ?: null, $id_orden]);
        $pdo->commit();
        redirigirOT($id_orden, 'success', '¡Guardado!', 'El trabajo fue actualizado correctamente.');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirigirOT($id_orden, 'error', 'Error', $e->getMessage());
    }
}

$num_orden = 'ORD-' . str_pad($id_orden, 6, '0', STR_PAD_LEFT);
$estado_cfg = [
    'recibido'    => ['label'=>'Recibido',         'class'=>'ot-estado-recibido',    'icon'=>'fa-inbox'],
    'diagnostico' => ['label'=>'En Diagnóstico',   'class'=>'ot-estado-diagnostico', 'icon'=>'fa-search'],
    'en_proceso'  => ['label'=>'En Proceso',        'class'=>'ot-estado-proceso',     'icon'=>'fa-cog'],
    'listo'       => ['label'=>'Listo p/ Entrega',  'class'=>'ot-estado-listo',       'icon'=>'fa-check-circle'],
    'entregado'   => ['label'=>'Entregado',          'class'=>'ot-estado-entregado',   'icon'=>'fa-box'],
    'cancelado'   => ['label'=>'Cancelado',          'class'=>'ot-estado-cancelado',   'icon'=>'fa-times-circle'],
];
$prio_cfg = [
    'alta'   => ['label'=>'Alta',   'class'=>'ot-prio-alta'],
    'normal' => ['label'=>'Normal', 'class'=>'ot-prio-normal'],
    'media'  => ['label'=>'Media',  'class'=>'ot-prio-media'],
    'baja'   => ['label'=>'Baja',   'class'=>'ot-prio-baja'],
];
$est  = $estado_cfg[$orden['estado']]    ?? $estado_cfg['recibido'];
$prio = $prio_cfg[$orden['prioridad']]   ?? $prio_cfg['normal'];
$costo_total_actual = array_sum(array_column($servicios_aplicados, 'subtotal'));

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<link rel="stylesheet" href="css/orden_trabajo.css?v=<?= time() ?>">

<div class="content-wrapper ot-wrapper">

    <!-- TOPBAR (no sticky) -->
    <div class="ot-topbar">
        <div class="ot-topbar-left">
            <a href="taller.php" class="ot-btn-back"><i class="fas fa-arrow-left"></i></a>
            <div class="ot-topbar-icon"><i class="fas fa-tools"></i></div>
            <div class="ot-topbar-info">
                <div class="ot-topbar-title">
                    Orden de Trabajo <span class="ot-num-badge"><?= $num_orden ?></span>
                </div>
                <div class="ot-topbar-sub">
                    <?= htmlspecialchars($orden['equipo_tipo'].' '.$orden['marca'].($orden['modelo'] ? ' — '.$orden['modelo'] : '')) ?>
                    &nbsp;·&nbsp; <?= htmlspecialchars($orden['cliente_nombre']) ?>
                </div>
            </div>
        </div>
        <div class="ot-topbar-right">
            <span class="ot-badge-prio <?= $prio['class'] ?>">
                <?= $prio['label']==='Alta'?'🔴':($prio['label']==='Baja'?'🟢':'🟡') ?> <?= $prio['label'] ?>
            </span>
            <span class="ot-badge-estado <?= $est['class'] ?>">
                <i class="fas <?= $est['icon'] ?> mr-1"></i><?= $est['label'] ?>
            </span>
            <a href="orden_trabajo_pdf.php?id=<?= $id_orden ?>" target="_blank" class="ot-btn-pdf">
                <i class="fas fa-file-pdf mr-1"></i>PDF
            </a>
        </div>
    </div>

    <!-- CONTENEDOR CENTRAL -->
    <div class="ot-page-container">

        <?php if ($swal): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            Swal.fire({
                icon: '<?= $swal['icon'] ?>', title: '<?= addslashes($swal['title']) ?>',
                text: '<?= addslashes($swal['text']) ?>', confirmButtonColor: '#059669',
                timer: <?= in_array($swal['icon'],['success','info']) ? 3000 : 0 ?>,
                timerProgressBar: <?= in_array($swal['icon'],['success','info']) ? 'true' : 'false' ?>,
                showConfirmButton: <?= in_array($swal['icon'],['success','info']) ? 'false' : 'true' ?>,
            });
        });
        </script>
        <?php endif; ?>

        <form method="POST" id="formOT">
            <input type="hidden" name="accion" value="guardar_trabajo">
            <input type="hidden" name="id_orden" value="<?= $id_orden ?>">

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- 1) INFORMACIÓN DEL EQUIPO Y CLIENTE                    -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="ot-section">
                <div class="ot-section-header">
                    <span class="ot-section-num">1</span>
                    <span class="ot-section-title">Información del Equipo y Cliente</span>
                </div>
                <div class="ot-card">
                    <div class="ot-card-header ot-ch-blue">
                        <i class="fas fa-laptop mr-2"></i>Ficha del Equipo y Cliente
                    </div>
                    <div class="ot-card-body">
                        <div class="ot-ficha-grid">
                            <div class="ot-ficha-item">
                                <span class="ot-ficha-lbl"><i class="fas fa-user mr-1"></i>Cliente</span>
                                <span class="ot-ficha-val fw-bold"><?= htmlspecialchars($orden['cliente_nombre']) ?></span>
                            </div>
                            <div class="ot-ficha-item">
                                <span class="ot-ficha-lbl"><i class="fas fa-phone mr-1"></i>Teléfono</span>
                                <span class="ot-ficha-val"><?= htmlspecialchars($orden['telefono'] ?? '—') ?></span>
                            </div>
                            <div class="ot-ficha-item">
                                <span class="ot-ficha-lbl"><i class="fas fa-laptop mr-1"></i>Equipo</span>
                                <span class="ot-ficha-val fw-bold"><?= htmlspecialchars($orden['equipo_tipo'].' '.$orden['marca']) ?></span>
                            </div>
                            <div class="ot-ficha-item">
                                <span class="ot-ficha-lbl"><i class="fas fa-hashtag mr-1"></i>Modelo / Serie</span>
                                <span class="ot-ficha-val"><?= htmlspecialchars(($orden['modelo'] ?? '—').' / '.($orden['numero_serie'] ?? '—')) ?></span>
                            </div>
                            <div class="ot-ficha-item">
                                <span class="ot-ficha-lbl"><i class="fas fa-plug mr-1"></i>Accesorios</span>
                                <span class="ot-ficha-val"><?= htmlspecialchars($orden['accesorios'] ?? '—') ?></span>
                            </div>
                            <div class="ot-ficha-item">
                                <span class="ot-ficha-lbl"><i class="fas fa-eye mr-1"></i>Estado Físico</span>
                                <span class="ot-ficha-val"><?= htmlspecialchars($orden['estado_fisico'] ?? '—') ?></span>
                            </div>
                            <div class="ot-ficha-item">
                                <span class="ot-ficha-lbl"><i class="fas fa-calendar-plus mr-1"></i>Recepción</span>
                                <span class="ot-ficha-val"><?= date('d/m/Y H:i', strtotime($orden['fecha_recepcion'])) ?></span>
                            </div>
                            <div class="ot-ficha-item">
                                <span class="ot-ficha-lbl"><i class="fas fa-calendar-check mr-1"></i>Entrega Est.</span>
                                <?php $vencida = $orden['fecha_entrega_estimada'] && new DateTime($orden['fecha_entrega_estimada']) < new DateTime(); ?>
                                <span class="ot-ficha-val <?= $vencida ? 'text-danger fw-bold' : '' ?>">
                                    <?= $orden['fecha_entrega_estimada'] ? date('d/m/Y', strtotime($orden['fecha_entrega_estimada'])) : '—' ?>
                                    <?= $vencida ? ' <i class="fas fa-exclamation-triangle"></i>' : '' ?>
                                </span>
                            </div>
                        </div>

                        <div class="ot-problema-box mt-3">
                            <div class="ot-problema-label"><i class="fas fa-exclamation-triangle mr-1"></i>Problema Reportado</div>
                            <div class="ot-problema-texto"><?= htmlspecialchars($orden['problema_reportado'] ?? '—') ?></div>
                        </div>

                        <div class="mt-3">
                            <label class="ot-label"><i class="fas fa-key mr-1" style="color:#2563eb;"></i>Contraseña del Equipo <small class="text-muted">(opcional)</small></label>
                            <div class="ot-input-group">
                                <span class="ot-ig-icon"><i class="fas fa-lock"></i></span>
                                <input type="text" name="contrasena_equipo" class="form-control form-control-sm"
                                    value="<?= htmlspecialchars($orden['contrasena_equipo'] ?? '') ?>"
                                    placeholder="Contraseña del equipo" maxlength="100" autocomplete="off">
                            </div>
                        </div>

                        <?php if (!empty($fotos_ingreso)): ?>
                        <div class="mt-3">
                            <label class="ot-label"><i class="fas fa-images mr-1" style="color:#7c3aed;"></i>Fotos de Ingreso</label>
                            <div class="ot-fotos-ingreso-grid">
                                <?php foreach ($fotos_ingreso as $fi): ?>
                                <div class="ot-foto-ingreso-thumb" onclick="abrirLightbox('/sysinversioneschcomputer/<?= htmlspecialchars($fi) ?>')">
                                    <img src="/sysinversioneschcomputer/<?= htmlspecialchars($fi) ?>" alt="Foto ingreso" loading="lazy">
                                    <div class="ot-foto-overlay"><i class="fas fa-expand"></i></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- 2) TRABAJO TÉCNICO                                      -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="ot-section">
                <div class="ot-section-header">
                    <span class="ot-section-num">2</span>
                    <span class="ot-section-title">Trabajo Técnico</span>
                </div>
                <div class="ot-card">
                    <div class="ot-card-header ot-ch-purple">
                        <i class="fas fa-wrench mr-2"></i>Diagnóstico y Solución
                    </div>
                    <div class="ot-card-body">
                        <div class="mb-3">
                            <label class="ot-label"><i class="fas fa-microchip mr-1" style="color:#7c3aed;"></i>Componentes / Repuestos</label>
                            <textarea name="descripcion_componentes" class="form-control form-control-sm ot-textarea" rows="2"
                                placeholder="Ej: RAM 8GB DDR4, SSD 256GB, Pasta térmica..."><?= htmlspecialchars($orden['descripcion_componentes'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="ot-label"><i class="fas fa-search mr-1" style="color:#f59e0b;"></i>Diagnóstico</label>
                            <textarea name="diagnostico" class="form-control form-control-sm ot-textarea" rows="4"
                                placeholder="Describe qué encontraste al revisar el equipo..."><?= htmlspecialchars($orden['diagnostico'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="ot-label"><i class="fas fa-check-circle mr-1" style="color:#10b981;"></i>Solución Aplicada</label>
                            <textarea name="solucion" class="form-control form-control-sm ot-textarea" rows="4"
                                placeholder="Describe qué hiciste para resolver el problema..."><?= htmlspecialchars($orden['solucion'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- 3) SERVICIOS APLICADOS                                  -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="ot-section">
                <div class="ot-section-header">
                    <span class="ot-section-num">3</span>
                    <span class="ot-section-title">Servicios Aplicados</span>
                </div>
                <div class="ot-card">
                    <div class="ot-card-header ot-ch-green">
                        <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">
                            <span><i class="fas fa-clipboard-list mr-2"></i>Servicios de esta Orden</span>
                            <button type="button" class="ot-btn-catalogo" id="btnAbrirCatalogo">
                                <i class="fas fa-plus mr-1"></i>Agregar del Catálogo
                            </button>
                        </div>
                    </div>
                    <div class="ot-card-body">
                        <div id="otServiciosLista">
                            <?php if (empty($servicios_aplicados)): ?>
                            <div class="ot-empty-srv" id="otSinServicios">
                                <i class="fas fa-clipboard-list fa-2x mb-2 d-block" style="opacity:.25;"></i>
                                <p class="mb-0 fw-bold">Sin servicios agregados</p>
                                <small>Haz clic en "Agregar del Catálogo" para añadir servicios</small>
                            </div>
                            <?php else: ?>
                            <div id="otSinServicios" style="display:none;" class="ot-empty-srv">
                                <i class="fas fa-clipboard-list fa-2x mb-2 d-block" style="opacity:.25;"></i>
                                <p class="mb-0 fw-bold">Sin servicios agregados</p>
                                <small>Haz clic en "Agregar del Catálogo"</small>
                            </div>
                            <?php foreach ($servicios_aplicados as $idx => $srv): ?>
                            <div class="ot-srv-item" data-id="<?= $srv['id_servicio'] ?>" data-idx="<?= $idx ?>">
                                <div class="ot-srv-item-header">
                                    <span class="ot-srv-nombre"><?= htmlspecialchars($srv['descripcion'] ?: $srv['nombre_servicio']) ?></span>
                                    <button type="button" class="ot-srv-del" data-idx="<?= $idx ?>" title="Quitar servicio"><i class="fas fa-times"></i></button>
                                </div>
                                <div class="ot-srv-item-footer">
                                    <div class="ot-srv-controls">
                                        <button type="button" class="ot-qty-btn ot-cant-dec" data-idx="<?= $idx ?>"><i class="fas fa-minus"></i></button>
                                        <span class="ot-qty-display" id="otQty_<?= $idx ?>"><?= (int)$srv['cantidad'] ?></span>
                                        <button type="button" class="ot-qty-btn ot-cant-inc" data-idx="<?= $idx ?>"><i class="fas fa-plus"></i></button>
                                        <button type="button" class="ot-precio-chip ot-btn-editar-precio" data-idx="<?= $idx ?>" title="Editar precio">
                                            <i class="fas fa-tag"></i> S/. <span class="ot-precio-lbl" id="otPrecioLbl_<?= $idx ?>"><?= number_format($srv['precio'], 2) ?></span>
                                            <i class="fas fa-pencil-alt" style="font-size:.6rem;opacity:.6;"></i>
                                        </button>
                                    </div>
                                    <span class="ot-srv-sub">S/. <span class="ot-sub-val" id="otSub_<?= $idx ?>"><?= number_format($srv['subtotal'], 2) ?></span></span>
                                </div>
                                <input type="hidden" name="servicios_ids[]" value="<?= $srv['id_servicio'] ?>">
                                <input type="hidden" name="servicios_precios[]" class="h-precio" value="<?= $srv['precio'] ?>">
                                <input type="hidden" name="servicios_cantidades[]" class="h-cant" value="<?= $srv['cantidad'] ?>">
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="ot-total-box mt-3">
                            <span><i class="fas fa-receipt mr-1"></i>Total Estimado</span>
                            <span class="ot-total-val" id="otTotalVal">S/. <?= number_format($costo_total_actual, 2) ?></span>
                        </div>
                        <div id="otResumenServicios" style="display:none;"></div>
                        <span id="otTotalResumen" style="display:none;">S/. <?= number_format($costo_total_actual, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- 4) COTIZACIONES DE REPUESTOS                            -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="ot-section">
                <div class="ot-section-header">
                    <span class="ot-section-num">4</span>
                    <span class="ot-section-title">Cotizaciones de Repuestos</span>
                </div>
                <div class="ot-card">
                    <div class="ot-card-header" style="background:linear-gradient(90deg,#92400e,#d97706);">
                        <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">
                            <span><i class="fas fa-tags mr-2"></i>Repuestos Cotizados
                                <span id="cotBadge" style="background:rgba(255,255,255,.25);color:#fff;font-size:.62rem;font-weight:700;padding:1px 7px;border-radius:10px;margin-left:6px;display:none;">0</span>
                            </span>
                            <button type="button" id="btnNuevaCotizacion"
                                style="background:rgba(255,255,255,.2);border:1.5px solid rgba(255,255,255,.4);color:#fff;border-radius:7px;font-size:.76rem;font-weight:600;padding:5px 13px;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                                <i class="fas fa-plus"></i> Nueva Cotización
                            </button>
                        </div>
                    </div>
                    <div class="ot-card-body">

                        <!-- Lista de cotizaciones -->
                        <div id="cotLista"></div>

                        <!-- Estado vacío -->
                        <div id="cotVacia" class="ot-empty-srv">
                            <i class="fas fa-tags fa-2x mb-2 d-block" style="opacity:.25;"></i>
                            <p class="mb-0 fw-bold">Sin repuestos cotizados</p>
                            <small>Agrega los repuestos que necesita el equipo para cotizarlos al cliente</small>
                        </div>

                        <!-- Total cotizaciones -->
                        <div id="cotTotalBox" class="mt-3" style="display:none;">
                            <div style="background:linear-gradient(135deg,#92400e,#d97706);color:#fff;border-radius:8px;padding:10px 16px;display:flex;justify-content:space-between;align-items:center;font-size:.84rem;font-weight:600;">
                                <span><i class="fas fa-calculator mr-1"></i>Total Repuestos (aprobados)</span>
                                <span id="cotTotalVal" style="font-size:1.1rem;font-weight:800;">S/. 0.00</span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- 5) FOTOS DEL TALLER                                     -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="ot-section">
                <div class="ot-section-header">
                    <span class="ot-section-num">5</span>
                    <span class="ot-section-title">Fotos del Taller</span>
                </div>
                <div class="ot-card">
                    <div class="ot-card-header ot-ch-indigo">
                        <div style="display:flex;align-items:center;justify-content:space-between;width:100%;">
                            <span><i class="fas fa-camera mr-2"></i>Fotos del Proceso
                                <span class="ot-foto-badge" id="otFotoBadge" style="display:none;">0</span>
                            </span>
                            <button type="button" class="ot-btn-foto-add" id="btnSeleccionarFotos">
                                <i class="fas fa-upload mr-1"></i>Subir Fotos
                            </button>
                            <input type="file" id="otFotoInput" accept="image/jpeg,image/png,image/webp,image/gif" multiple style="display:none;">
                        </div>
                    </div>
                    <div class="ot-card-body">
                        <div class="ot-dropzone" id="otDropzone">
                            <i class="fas fa-cloud-upload-alt" style="font-size:2rem;color:#93c5fd;margin-bottom:10px;display:block;"></i>
                            <p style="margin:0;font-size:.84rem;color:#64748b;font-weight:600;">Arrastra fotos aquí o haz clic en "Subir Fotos"</p>
                            <p style="margin:5px 0 0;font-size:.74rem;color:#94a3b8;">JPG, PNG, WEBP · Máx. 5 MB por foto · Hasta 10 fotos</p>
                        </div>
                        <div id="otFotoProgress" style="display:none;margin-top:12px;">
                            <div style="display:flex;justify-content:space-between;font-size:.74rem;color:#64748b;margin-bottom:4px;">
                                <span id="otFotoProgressLabel">Subiendo...</span>
                                <span id="otFotoProgressPct">0%</span>
                            </div>
                            <div style="height:6px;background:#e2e8f0;border-radius:10px;overflow:hidden;">
                                <div id="otFotoProgressBar" style="height:100%;background:linear-gradient(90deg,#2563eb,#059669);border-radius:10px;width:0%;transition:width .3s;"></div>
                            </div>
                        </div>
                        <div class="ot-foto-galeria mt-3" id="otFotoGaleria"></div>
                        <div id="otFotoVacia" class="ot-empty-srv mt-3" style="<?= empty($fotos_taller) ? '' : 'display:none;' ?>">
                            <i class="fas fa-images fa-2x mb-2 d-block" style="opacity:.25;"></i>
                            <p class="mb-0 fw-bold">Sin fotos aún</p>
                            <small>Las fotos del proceso técnico aparecerán aquí</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- 6) ESTADO Y GUARDAR                                     -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="ot-section">
                <div class="ot-section-header">
                    <span class="ot-section-num">6</span>
                    <span class="ot-section-title">Estado y Guardar</span>
                </div>
                <div class="ot-card">
                    <div class="ot-card-header ot-ch-green-dark">
                        <i class="fas fa-exchange-alt mr-2"></i>Actualizar Estado de la Orden
                    </div>
                    <div class="ot-card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="ot-label">Nuevo Estado</label>
                                <select name="nuevo_estado" id="otNuevoEstado" class="form-control form-control-sm ot-select">
                                    <option value="diagnostico" <?= $orden['estado']==='diagnostico'?'selected':'' ?>>🔍 En Diagnóstico</option>
                                    <option value="en_proceso"  <?= $orden['estado']==='en_proceso' ?'selected':'' ?>>⚙️ En Proceso</option>
                                    <option value="listo"       <?= $orden['estado']==='listo'      ?'selected':'' ?>>✅ Listo para Entrega</option>
                                    <option value="entregado"   <?= $orden['estado']==='entregado'  ?'selected':'' ?>>📦 Entregado</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="ot-label">Nota del cambio <small class="text-muted">(opcional)</small></label>
                                <input type="text" name="nota_cambio" class="form-control form-control-sm"
                                    placeholder="Ej: Esperando repuesto, cliente notificado..." maxlength="255">
                            </div>
                        </div>
                        <button type="submit" class="ot-btn-guardar" id="btnGuardarOT">
                            <i class="fas fa-save mr-2"></i>Guardar Trabajo
                        </button>
                        <a href="taller.php" class="ot-btn-cancelar mt-2">
                            <i class="fas fa-arrow-left mr-1"></i>← Volver al Taller
                        </a>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════ -->
            <!-- 7) HISTORIAL DE ESTADOS                                 -->
            <!-- ═══════════════════════════════════════════════════════ -->
            <div class="ot-section">
                <div class="ot-section-header">
                    <span class="ot-section-num">7</span>
                    <span class="ot-section-title">Historial de Estados</span>
                </div>
                <div class="ot-card">
                    <div class="ot-card-header ot-ch-slate">
                        <i class="fas fa-history mr-2"></i>Registro de Cambios
                    </div>
                    <div class="ot-card-body p-0">
                        <div class="ot-historial">
                            <?php if (empty($historial)): ?>
                            <div class="text-center text-muted py-4" style="font-size:.84rem;">
                                <i class="fas fa-history fa-2x mb-2 d-block" style="opacity:.2;"></i>
                                Sin registros de cambios aún
                            </div>
                            <?php else: ?>
                            <?php foreach ($historial as $h): ?>
                            <div class="ot-hist-item">
                                <div class="ot-hist-timeline">
                                    <div class="ot-hist-dot ot-hist-dot-<?= $h['estado'] ?>"></div>
                                    <div class="ot-hist-line"></div>
                                </div>
                                <div class="ot-hist-content">
                                    <div class="ot-hist-estado"><?= ucfirst(str_replace('_',' ',$h['estado'])) ?></div>
                                    <div class="ot-hist-desc"><?= htmlspecialchars($h['descripcion'] ?? '') ?></div>
                                    <div class="ot-hist-meta">
                                        <i class="fas fa-user mr-1"></i><?= htmlspecialchars($h['usuario_nombre'] ?? '—') ?>
                                        &nbsp;·&nbsp;
                                        <i class="fas fa-clock mr-1"></i><?= date('d/m/Y H:i', strtotime($h['fecha'])) ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </form>
    </div><!-- /ot-page-container -->
</div><!-- /ot-wrapper -->

<!-- ══ MODAL: NUEVA COTIZACIÓN DE REPUESTO ════════════════════════════════ -->
<div class="modal fade" id="modalNuevaCotizacion" tabindex="-1">
    <div class="modal-dialog" style="max-width:500px;">
        <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.2);">
            <div style="background:linear-gradient(135deg,#92400e,#d97706);padding:16px 20px;display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-tags" style="color:#fff;"></i>
                </div>
                <div style="flex:1;">
                    <div style="color:#fff;font-weight:700;font-size:.95rem;">Nueva Cotización de Repuesto</div>
                    <div style="color:rgba(255,255,255,.75);font-size:.75rem;">Orden <?= $num_orden ?></div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="font-size:1.2rem;"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:20px;">

                <!-- Tipo: producto del catálogo o descripción libre -->
                <div class="mb-3">
                    <label style="font-size:.78rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:8px;">
                        Tipo de repuesto
                    </label>
                    <div style="display:flex;gap:0;border:1.5px solid #d1d5db;border-radius:8px;overflow:hidden;">
                        <button type="button" class="cot-tipo-btn activo" id="cotTipoLibre"
                            style="flex:1;padding:8px;border:none;background:#f8fafc;font-size:.82rem;font-weight:600;color:#475569;cursor:pointer;transition:all .15s;">
                            <i class="fas fa-pencil-alt mr-1"></i>Descripción libre
                        </button>
                        <button type="button" class="cot-tipo-btn" id="cotTipoCatalogo"
                            style="flex:1;padding:8px;border:none;border-left:1.5px solid #d1d5db;background:#f8fafc;font-size:.82rem;font-weight:600;color:#475569;cursor:pointer;transition:all .15s;">
                            <i class="fas fa-box mr-1"></i>Del inventario
                        </button>
                    </div>
                </div>

                <!-- Descripción libre -->
                <div id="cotBloqueLibre" class="mb-3">
                    <label class="ot-label"><i class="fas fa-tag mr-1" style="color:#d97706;"></i>Descripción del repuesto <span class="text-danger">*</span></label>
                    <input type="text" id="cotDescripcion" class="form-control form-control-sm"
                        placeholder="Ej: Teclado HP 15-FD0350LA, Batería 45Wh..." maxlength="255">
                </div>

                <!-- Producto del inventario -->
                <div id="cotBloqueProducto" class="mb-3" style="display:none;">
                    <label class="ot-label"><i class="fas fa-box mr-1" style="color:#d97706;"></i>Producto del inventario</label>
                    <div style="display:flex;gap:8px;">
                        <div id="cotCampoProducto" style="flex:1;border:1.5px solid #d1d5db;border-radius:7px;padding:6px 10px;cursor:pointer;font-size:.84rem;color:#94a3b8;background:#f8fafc;transition:border-color .15s;">
                            <i class="fas fa-search mr-1"></i><span id="cotProductoTexto">Seleccionar producto...</span>
                        </div>
                        <button type="button" id="btnLimpiarCotProducto" class="btn btn-sm btn-outline-danger" style="display:none;" title="Quitar"><i class="fas fa-times"></i></button>
                    </div>
                    <input type="hidden" id="cotIdProducto" value="">
                </div>

                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="ot-label"><i class="fas fa-sort-numeric-up mr-1" style="color:#2563eb;"></i>Cantidad</label>
                        <input type="number" id="cotCantidad" class="form-control form-control-sm" value="1" min="1" max="999">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="ot-label"><i class="fas fa-dollar-sign mr-1" style="color:#059669;"></i>Precio Unitario (S/.)</label>
                        <input type="number" id="cotPrecio" class="form-control form-control-sm" value="" min="0.01" step="0.50" placeholder="0.00">
                    </div>
                </div>

                <!-- Preview subtotal -->
                <div style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #fde68a;border-radius:8px;padding:10px 14px;display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <span style="font-size:.8rem;color:#92400e;font-weight:600;"><i class="fas fa-calculator mr-1"></i>Subtotal estimado</span>
                    <span id="cotSubtotalPreview" style="font-size:1.05rem;font-weight:800;color:#92400e;">S/. 0.00</span>
                </div>

                <div class="mb-2">
                    <label class="ot-label"><i class="fas fa-sticky-note mr-1" style="color:#64748b;"></i>Nota <small class="text-muted">(opcional)</small></label>
                    <input type="text" id="cotNota" class="form-control form-control-sm"
                        placeholder="Ej: Hay que pedirlo al proveedor, plazo 3 días..." maxlength="255">
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;gap:8px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
                <button type="button" id="btnGuardarCotizacion" class="btn btn-sm"
                    style="background:linear-gradient(135deg,#92400e,#d97706);color:#fff;border:none;font-weight:600;padding:.35rem .9rem;border-radius:6px;">
                    <i class="fas fa-save mr-1"></i>Guardar Cotización
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══ MODAL: SELECTOR DE PRODUCTO PARA COTIZACIÓN ═══════════════════════ -->
<div class="modal fade" id="modalCotProducto" tabindex="-1" style="z-index:1065;">
    <div class="modal-dialog modal-md" style="max-width:560px;">
        <div class="modal-content" style="border:none;border-radius:12px;overflow:hidden;box-shadow:0 15px 50px rgba(0,0,0,.2);">
            <div style="background:linear-gradient(135deg,#1e40af,#2563eb);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;">
                <div style="color:#fff;font-weight:700;font-size:.9rem;"><i class="fas fa-box mr-2"></i>Seleccionar Producto</div>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div style="padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend"><span class="input-group-text" style="background:#2563eb;border-color:#2563eb;color:#fff;"><i class="fas fa-search"></i></span></div>
                    <input type="text" id="cotBuscarProducto" class="form-control" placeholder="Buscar producto...">
                </div>
            </div>
            <div style="max-height:380px;overflow-y:auto;padding:10px 14px;" id="cotListaProductos">
                <?php
                // Cargar productos para el selector de cotización
                try {
                    $prods_cot = $pdo->query("SELECT p.id_producto, p.nombre_producto, p.codigo, p.precio_compra, c.nombre_categoria FROM productos p LEFT JOIN categorias c ON p.id_categoria=c.id_categoria WHERE p.estado=1 ORDER BY p.nombre_producto ASC")->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) { $prods_cot = []; }
                foreach ($prods_cot as $pr):
                ?>
                <div class="cot-prod-item" style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;transition:background .15s;border:1px solid transparent;margin-bottom:4px;"
                     data-id="<?= $pr['id_producto'] ?>"
                     data-nombre="<?= htmlspecialchars($pr['nombre_producto'], ENT_QUOTES) ?>"
                     data-precio="<?= $pr['precio_compra'] ?>"
                     data-codigo="<?= htmlspecialchars($pr['codigo'] ?? '', ENT_QUOTES) ?>">
                    <div style="width:36px;height:36px;background:#dbeafe;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-box" style="color:#1e40af;font-size:.8rem;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:.84rem;font-weight:700;color:#1e293b;"><?= htmlspecialchars($pr['nombre_producto']) ?></div>
                        <div style="font-size:.72rem;color:#94a3b8;"><?= htmlspecialchars($pr['codigo'] ?? '') ?> · <?= htmlspecialchars($pr['nombre_categoria'] ?? '—') ?></div>
                    </div>
                    <div style="font-size:.84rem;font-weight:800;color:#065f46;white-space:nowrap;">S/. <?= number_format($pr['precio_compra'], 2) ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($prods_cot)): ?>
                <div class="text-center text-muted py-4" style="font-size:.84rem;">No hay productos en el inventario.</div>
                <?php endif; ?>
            </div>
            <div id="cotSinProd" style="display:none;" class="text-center text-muted py-4" style="font-size:.84rem;">
                <i class="fas fa-search fa-2x mb-2 d-block" style="opacity:.3;"></i>Sin resultados.
            </div>
        </div>
    </div>
</div>

<!-- ══ MODAL: CATÁLOGO DE SERVICIOS ══════════════════════════════════════ --><div class="modal fade" id="modalCatalogo" tabindex="-1">
    <div class="modal-dialog modal-lg" style="max-width:740px;">
        <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.2);">
            <div style="background:linear-gradient(135deg,#1e40af,#2563eb);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:8px;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-clipboard-list" style="color:#fff;"></i>
                    </div>
                    <div>
                        <div style="color:#fff;font-weight:700;font-size:.95rem;">Catálogo de Servicios</div>
                        <div style="color:rgba(255,255,255,.75);font-size:.75rem;"><span id="catContador"><?= count($catalogo) ?></span> servicios disponibles</div>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="font-size:1.2rem;"><span>&times;</span></button>
            </div>
            <div style="padding:14px 18px;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                <div class="row">
                    <div class="col-md-7 pr-2">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span class="input-group-text" style="background:#2563eb;border-color:#2563eb;color:#fff;"><i class="fas fa-search"></i></span></div>
                            <input type="text" id="catBuscar" class="form-control" placeholder="Buscar servicio...">
                        </div>
                    </div>
                    <div class="col-md-5 pl-1">
                        <select id="catFiltroTipo" class="form-control form-control-sm">
                            <option value="">Todos los tipos</option>
                            <option value="catalogo">📚 Precio fijo</option>
                            <option value="personalizado">🔧 Precio variable</option>
                        </select>
                    </div>
                </div>
            </div>
            <div style="max-height:420px;overflow-y:auto;padding:14px 18px;">
                <div class="row" id="catLista">
                    <?php foreach ($catalogo as $srv): ?>
                    <div class="col-md-6 mb-2 cat-item"
                         data-id="<?= $srv['id_servicio'] ?>"
                         data-nombre="<?= htmlspecialchars($srv['nombre'], ENT_QUOTES) ?>"
                         data-precio="<?= $srv['precio_base'] ?>"
                         data-tipo="<?= $srv['tipo'] ?>">
                        <div class="ot-cat-card">
                            <div class="ot-cat-card-body">
                                <div class="ot-cat-nombre"><?= htmlspecialchars($srv['nombre']) ?></div>
                                <div class="d-flex align-items-center justify-content-between mt-1">
                                    <span class="ot-cat-precio">S/. <?= number_format($srv['precio_base'],2) ?></span>
                                    <span class="ot-cat-tipo ot-cat-tipo-<?= $srv['tipo'] ?>"><?= $srv['tipo']==='catalogo'?'Fijo':'Variable' ?></span>
                                </div>
                                <?php if ($srv['tipos_equipo']): ?>
                                <div class="ot-cat-chips mt-1">
                                    <?php foreach (explode(',', $srv['tipos_equipo']) as $te): ?>
                                    <span class="ot-cat-chip"><?= trim(htmlspecialchars($te)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="ot-cat-add-btn cat-btn-agregar" title="Agregar servicio">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div id="catSinResultados" style="display:none;" class="text-center text-muted py-4">
                    <i class="fas fa-search fa-2x mb-2 d-block" style="opacity:.3;"></i>Sin resultados para tu búsqueda.
                </div>
            </div>
            <div style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:10px 18px;display:flex;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ══ MODAL: EDITAR PRECIO / CANTIDAD ═══════════════════════════════════ -->
<div class="modal fade" id="modalEditarSrv" tabindex="-1" style="z-index:1060;">
    <div class="modal-dialog modal-sm" style="max-width:340px;">
        <div class="modal-content" style="border:none;border-radius:14px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25);">
            <div style="background:linear-gradient(135deg,#064e3b,#059669);padding:16px 20px;display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-edit" style="color:#fff;font-size:.9rem;"></i>
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="color:#fff;font-weight:700;font-size:.9rem;">Editar Servicio</div>
                    <div id="editSrvNombre" style="color:rgba(255,255,255,.8);font-size:.75rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" style="font-size:1.1rem;margin:0;"><span>&times;</span></button>
            </div>
            <div class="modal-body" style="padding:20px;">
                <input type="hidden" id="editSrvTarget">
                <div class="mb-4">
                    <label style="font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:8px;">
                        <i class="fas fa-tag mr-1" style="color:#059669;"></i>Precio Unitario (S/.)
                    </label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <button type="button" id="editPrecioDec" class="ot-edit-btn"><i class="fas fa-minus" style="font-size:.65rem;"></i></button>
                        <input type="number" id="editPrecioVal" min="0" step="0.50"
                            style="flex:1;text-align:center;font-size:1.2rem;font-weight:800;color:#064e3b;border:2px solid #059669;border-radius:8px;padding:6px 8px;outline:none;">
                        <button type="button" id="editPrecioInc" class="ot-edit-btn"><i class="fas fa-plus" style="font-size:.65rem;"></i></button>
                    </div>
                </div>
                <div class="mb-4">
                    <label style="font-size:.72rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:8px;">
                        <i class="fas fa-sort-numeric-up mr-1" style="color:#2563eb;"></i>Cantidad
                    </label>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <button type="button" id="editCantDec" class="ot-edit-btn"><i class="fas fa-minus" style="font-size:.65rem;"></i></button>
                        <input type="number" id="editCantVal" min="1" max="99"
                            style="flex:1;text-align:center;font-size:1.2rem;font-weight:800;color:#1e40af;border:2px solid #2563eb;border-radius:8px;padding:6px 8px;outline:none;">
                        <button type="button" id="editCantInc" class="ot-edit-btn"><i class="fas fa-plus" style="font-size:.65rem;"></i></button>
                    </div>
                </div>
                <div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;border-radius:10px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:.8rem;color:#065f46;font-weight:600;"><i class="fas fa-calculator mr-1"></i>Subtotal</span>
                    <span id="editSubtotal" style="font-size:1.1rem;font-weight:800;color:#064e3b;">S/. 0.00</span>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;gap:8px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
                <button type="button" id="btnAplicarEdicion" class="btn btn-sm" style="background:linear-gradient(135deg,#064e3b,#059669);color:#fff;border:none;font-weight:600;padding:.35rem .9rem;border-radius:6px;">
                    <i class="fas fa-check mr-1"></i>Aplicar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ══ LIGHTBOX ══════════════════════════════════════════════════════════ -->
<div id="otLightbox" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:2000;align-items:center;justify-content:center;flex-direction:column;">
    <button id="otLbClose" style="position:absolute;top:16px;right:20px;background:rgba(255,255,255,.15);border:none;color:#fff;width:40px;height:40px;border-radius:50%;font-size:1.2rem;cursor:pointer;display:flex;align-items:center;justify-content:center;"><i class="fas fa-times"></i></button>
    <button id="otLbPrev" style="position:absolute;left:16px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:none;color:#fff;width:44px;height:44px;border-radius:50%;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;"><i class="fas fa-chevron-left"></i></button>
    <img id="otLbImg" src="" alt="" style="max-width:90vw;max-height:85vh;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,.5);object-fit:contain;">
    <div id="otLbCaption" style="color:rgba(255,255,255,.7);font-size:.8rem;margin-top:12px;text-align:center;"></div>
    <button id="otLbNext" style="position:absolute;right:16px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:none;color:#fff;width:44px;height:44px;border-radius:50%;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;"><i class="fas fa-chevron-right"></i></button>
</div>

<?php
$extra_js = '<script src="js/orden_trabajo.js?v=' . time() . '"></script>';
include $ruta_base . 'includes/footer.php';
?>
