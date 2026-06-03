<?php
// ============================================================
// modules/Caja/ajax_detalle_movimiento.php | SysInversiones 2026
// Detalle enriquecido de un movimiento de caja (con productos)
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
if (!isset($pdo)) { echo '<p class="text-danger p-3">Sin conexión BD.</p>'; exit; }

$id_mov = (int)($_GET['id_movimiento']  ?? 0);
$ref    = trim($_GET['tipo_referencia'] ?? '');
$ref_id = (int)($_GET['id_referencia'] ?? 0);

if (!$id_mov) { echo '<p class="text-warning p-3">Movimiento no identificado.</p>'; exit; }

// ── Movimiento base ───────────────────────────────────────────────────────────
try {
    $stM = $pdo->prepare("
        SELECT m.*, u.nombre_completo AS cajero, c.nombre AS nombre_caja
        FROM movimientos_caja m
        LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
        LEFT JOIN caja c ON c.id_caja = m.id_caja
        WHERE m.id_movimiento = ?
    ");
    $stM->execute([$id_mov]);
    $mov = $stM->fetch();
} catch (PDOException $e) { echo '<p class="text-danger p-3">Error BD.</p>'; exit; }
if (!$mov) { echo '<p class="text-warning p-3">Movimiento no encontrado.</p>'; exit; }

$es_ing      = ($mov['tipo'] === 'ingreso');
$color_monto = $es_ing ? '#059669' : '#dc2626';
$bg_monto    = $es_ing ? '#dcfce7' : '#fee2e2';
$signo       = $es_ing ? '+' : '−';
$icon_bg     = $es_ing ? 'linear-gradient(135deg,#047857,#10b981)' : 'linear-gradient(135deg,#b91c1c,#ef4444)';
$icon_fa     = $es_ing ? 'fa-arrow-up' : 'fa-arrow-down';

$ref_cfg = [
    'venta'    => ['label'=>'Venta de producto',  'icon'=>'fa-shopping-cart',    'color'=>'#0ea5e9', 'bg'=>'#e0f2fe'],
    'compra'   => ['label'=>'Compra de producto', 'icon'=>'fa-truck-loading',    'color'=>'#7c3aed', 'bg'=>'#ede9fe'],
    'servicio' => ['label'=>'Cobro de servicio',  'icon'=>'fa-tools',            'color'=>'#059669', 'bg'=>'#dcfce7'],
    'manual'   => ['label'=>'Movimiento manual',  'icon'=>'fa-hand-holding-usd', 'color'=>'#d97706', 'bg'=>'#fef3c7'],
];
$rc = $ref_cfg[$ref] ?? ['label'=>ucfirst($ref),'icon'=>'fa-circle','color'=>'#64748b','bg'=>'#f1f5f9'];
$metodo_icons = ['efectivo'=>'fa-money-bill-wave','yape'=>'fa-mobile-alt','transferencia'=>'fa-university'];
$metodo_icon  = $metodo_icons[$mov['metodo_pago']] ?? 'fa-credit-card';

// ── Datos enriquecidos por tipo ───────────────────────────────────────────────
$venta = $compra = $orden = null;
$items_venta = $items_compra = $servicios_orden = $cotizaciones_orden = [];
$total_servicios_ord = $total_cotizaciones_ord = $total_real_ord = 0;

if ($ref === 'venta' && $ref_id) {
    try {
        $st = $pdo->prepare("
            SELECT v.*,
                CASE v.tipo_cliente
                    WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
                    ELSE TRIM(CONCAT_WS(' ', cn.nombres, cn.apellido_paterno, cn.apellido_materno))
                END AS cliente_nombre,
                CASE v.tipo_cliente WHEN 'empresa' THEN ce.ruc ELSE cn.documento_identidad END AS documento,
                CASE v.tipo_cliente WHEN 'empresa' THEN 'RUC' ELSE cn.tipo_documento END AS tipo_doc,
                CASE v.tipo_cliente WHEN 'empresa' THEN ce.telefono ELSE cn.telefono END AS telefono,
                u.nombre_completo AS vendedor
            FROM ventas v
            LEFT JOIN clientes_natural cn ON cn.id_cliente_natural=v.id_cliente AND v.tipo_cliente='natural'
            LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa=v.id_cliente AND v.tipo_cliente='empresa'
            LEFT JOIN usuarios u ON u.id_usuario=v.id_usuario
            WHERE v.id_venta=?
        ");
        $st->execute([$ref_id]); $venta = $st->fetch();
        $stI = $pdo->prepare("
            SELECT dv.cantidad, dv.precio_unitario, dv.descuento, dv.subtotal,
                   p.nombre_producto, p.codigo, p.marca
            FROM detalle_venta dv
            JOIN productos p ON p.id_producto=dv.id_producto
            WHERE dv.id_venta=?
        ");
        $stI->execute([$ref_id]); $items_venta = $stI->fetchAll();
    } catch (PDOException $e) {}

} elseif ($ref === 'compra' && $ref_id) {
    try {
        $st = $pdo->prepare("
            SELECT c.*, p.razon_social AS proveedor_nombre, p.ruc AS proveedor_ruc,
                   p.telefono AS proveedor_tel, u.nombre_completo AS comprador
            FROM compras c
            LEFT JOIN proveedores p ON p.id_proveedor=c.id_proveedor
            LEFT JOIN usuarios u ON u.id_usuario=c.id_usuario
            WHERE c.id_compra=?
        ");
        $st->execute([$ref_id]); $compra = $st->fetch();
        $stI = $pdo->prepare("
            SELECT dc.cantidad, dc.precio_compra, dc.descuento, dc.subtotal,
                   p.nombre_producto, p.codigo, p.marca
            FROM detalle_compra dc
            JOIN productos p ON p.id_producto=dc.id_producto
            WHERE dc.id_compra=?
        ");
        $stI->execute([$ref_id]); $items_compra = $stI->fetchAll();
    } catch (PDOException $e) {}

} elseif ($ref === 'servicio' && $ref_id) {
    try {
        $st = $pdo->prepare("
            SELECT o.*,
                CASE o.tipo_cliente
                    WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
                    ELSE TRIM(CONCAT_WS(' ', cn.nombres, cn.apellido_paterno, cn.apellido_materno))
                END AS cliente_nombre,
                CASE o.tipo_cliente WHEN 'empresa' THEN ce.ruc ELSE cn.documento_identidad END AS documento,
                CASE o.tipo_cliente WHEN 'empresa' THEN ce.telefono ELSE cn.telefono END AS telefono,
                e.tipo AS equipo_tipo, e.marca AS equipo_marca, e.modelo AS equipo_modelo,
                e.numero_serie,
                u.nombre_completo AS recepcionado_por,
                t.nombre_completo AS tecnico
            FROM ordenes_servicio o
            LEFT JOIN clientes_natural cn ON cn.id_cliente_natural=o.id_cliente AND o.tipo_cliente='natural'
            LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa=o.id_cliente AND o.tipo_cliente='empresa'
            LEFT JOIN equipos e ON e.id_equipo=o.id_equipo
            LEFT JOIN usuarios u ON u.id_usuario=o.id_usuario
            LEFT JOIN usuarios t ON t.id_usuario=o.id_tecnico
            WHERE o.id_orden=?
        ");
        $st->execute([$ref_id]); $orden = $st->fetch();
        $stS = $pdo->prepare("
            SELECT ds.cantidad, ds.precio, ds.subtotal, ds.descripcion,
                   s.nombre AS nombre_servicio
            FROM detalle_orden ds
            JOIN servicios s ON s.id_servicio=ds.id_servicio
            WHERE ds.id_orden=?
        ");
        $stS->execute([$ref_id]); $servicios_orden = $stS->fetchAll();

        // Cotizaciones de repuestos (todas excepto rechazadas)
        $stC = $pdo->prepare("
            SELECT oc.descripcion, oc.cantidad, oc.precio_unitario, oc.subtotal, oc.estado,
                   p.nombre_producto, p.codigo
            FROM orden_cotizaciones oc
            LEFT JOIN productos p ON p.id_producto = oc.id_producto
            WHERE oc.id_orden = ? AND oc.estado != 'rechazado'
            ORDER BY oc.fecha_cotizacion ASC
        ");
        $stC->execute([$ref_id]); $cotizaciones_orden = $stC->fetchAll();

        // Calcular total real
        $total_servicios_ord   = array_sum(array_column($servicios_orden, 'subtotal'));
        $total_cotizaciones_ord = array_sum(array_column($cotizaciones_orden, 'subtotal'));
        $total_real_ord        = $total_servicios_ord + $total_cotizaciones_ord;

    } catch (PDOException $e) {}
}

// ── helpers ───────────────────────────────────────────────────────────────────
function badge(string $text, string $color, string $bg): string {
    return "<span style='background:{$bg};color:{$color};border-radius:20px;padding:2px 12px;font-size:.72rem;font-weight:700;display:inline-block;'>{$text}</span>";
}
function field(string $lbl, string $val, string $extra = ''): string {
    return "<div class='mdd-field {$extra}'><div class='mdd-lbl'>{$lbl}</div><div class='mdd-val'>{$val}</div></div>";
}
$estado_color = ['pagado'=>['#059669','#dcfce7'],'pendiente'=>['#d97706','#fef3c7'],'anulado'=>['#dc2626','#fee2e2']];
$ep_color     = ['pagado'=>['#059669','#dcfce7'],'pendiente'=>['#d97706','#fef3c7'],'sin_cobro'=>['#64748b','#f1f5f9']];
$comp_label   = ['ticket'=>'Ticket','nota'=>'Nota','boleta'=>'Boleta','factura'=>'Factura'];
$metodo_label = ['efectivo'=>'Efectivo','yape'=>'Yape','transferencia'=>'Transferencia'];
?>
<style>
.mdd-wrap { font-family:'Segoe UI',sans-serif; }
/* ── Banner superior ── */
.mdd-banner {
    display:flex; align-items:center; gap:16px;
    padding:20px 24px 18px; border-bottom:2px solid #f1f5f9;
    background:linear-gradient(135deg,#f8fafc 0%,#fff 100%);
}
.mdd-banner-icon {
    width:56px; height:56px; border-radius:16px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-size:1.4rem; color:#fff;
    box-shadow:0 4px 14px rgba(0,0,0,.18);
}
.mdd-banner-desc { flex:1; min-width:0; }
.mdd-banner-title { font-size:.98rem; font-weight:700; color:#0f172a; margin:0 0 4px; line-height:1.3; }
.mdd-banner-badges { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
.mdd-banner-monto {
    text-align:right; flex-shrink:0;
    font-size:1.55rem; font-weight:800; font-family:monospace; letter-spacing:-.5px;
}
/* ── Grid de campos ── */
.mdd-grid {
    display:grid; grid-template-columns:1fr 1fr; gap:0;
    padding:4px 24px 8px; background:#fff;
}
.mdd-field { padding:11px 0; border-bottom:1px solid #f1f5f9; }
.mdd-field.odd  { padding-right:20px; border-right:1px solid #f1f5f9; }
.mdd-field.even { padding-left:20px; }
.mdd-lbl { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8; margin-bottom:3px; }
.mdd-val { font-size:.87rem; font-weight:600; color:#1e293b; }
/* ── Sección con tabla ── */
.mdd-section { margin:16px 24px 4px; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; }
.mdd-section-hdr {
    padding:10px 16px; font-size:.73rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.6px; color:#fff;
    display:flex; align-items:center; gap:8px;
}
.mdd-section-body { padding:14px 16px; background:#fff; }
/* ── Tabla de productos ── */
.mdd-table { width:100%; border-collapse:collapse; font-size:.8rem; }
.mdd-table thead th {
    background:#f8fafc; color:#64748b; font-weight:700; font-size:.68rem;
    text-transform:uppercase; letter-spacing:.4px; padding:7px 10px;
    border-bottom:2px solid #e2e8f0; text-align:left;
}
.mdd-table thead th.r { text-align:right; }
.mdd-table tbody td { padding:8px 10px; border-bottom:1px solid #f1f5f9; color:#1e293b; vertical-align:middle; }
.mdd-table tbody td.r { text-align:right; font-family:monospace; font-weight:700; }
.mdd-table tbody tr:last-child td { border-bottom:none; }
.mdd-table tfoot td { padding:8px 10px; font-weight:700; font-size:.82rem; }
/* ── Totales ── */
.mdd-totales { background:#f8fafc; border-top:2px solid #e2e8f0; padding:10px 16px; }
.mdd-total-row { display:flex; justify-content:space-between; padding:3px 0; font-size:.82rem; color:#475569; }
.mdd-total-row.main { font-size:.95rem; font-weight:800; color:#0f172a; padding-top:6px; border-top:1px solid #e2e8f0; margin-top:4px; }
/* ── Observación ── */
.mdd-obs { margin:12px 24px 4px; background:#fffbeb; border-left:4px solid #f59e0b; border-radius:0 8px 8px 0; padding:10px 14px; font-size:.83rem; color:#78350f; }
/* ── Info equipo ── */
.mdd-equipo-card {
    display:flex; align-items:center; gap:12px;
    background:linear-gradient(135deg,#0f172a,#1e3a5f);
    border-radius:10px; padding:12px 16px; margin-bottom:12px; color:#fff;
}
.mdd-equipo-icon { font-size:1.6rem; opacity:.8; }
.mdd-equipo-name { font-size:.92rem; font-weight:700; }
.mdd-equipo-sub  { font-size:.75rem; color:#94a3b8; margin-top:2px; }
</style>

<div class="mdd-wrap">
<!-- ── BANNER ── -->
<div class="mdd-banner">
    <div class="mdd-banner-icon" style="background:<?= $icon_bg ?>;">
        <i class="fas <?= $icon_fa ?>"></i>
    </div>
    <div class="mdd-banner-desc">
        <div class="mdd-banner-title"><?= htmlspecialchars($mov['descripcion']) ?></div>
        <div class="mdd-banner-badges">
            <span style="background:<?= $rc['bg'] ?>;color:<?= $rc['color'] ?>;border-radius:20px;padding:2px 12px;font-size:.72rem;font-weight:700;">
                <i class="fas <?= $rc['icon'] ?> mr-1"></i><?= $rc['label'] ?>
            </span>
            <?php if ($ref !== 'manual' && $ref_id): ?>
            <span style="background:#f1f5f9;color:#64748b;border-radius:20px;padding:2px 10px;font-size:.72rem;font-weight:700;">#<?= $ref_id ?></span>
            <?php endif; ?>
            <span style="background:<?= $bg_monto ?>;color:<?= $color_monto ?>;border-radius:20px;padding:2px 12px;font-size:.72rem;font-weight:700;">
                <?= strtoupper($mov['tipo']) ?>
            </span>
        </div>
    </div>
    <div class="mdd-banner-monto" style="color:<?= $color_monto ?>;">
        <?= $signo ?> S/. <?= number_format($mov['monto'], 2) ?>
    </div>
</div>

<!-- ── GRID MOVIMIENTO ── -->
<div class="mdd-grid">
    <?= field('<i class="fas fa-calendar-alt mr-1"></i>Fecha y hora', date('d/m/Y H:i:s', strtotime($mov['fecha'])), 'odd') ?>
    <?= field('<i class="fas fa-'.htmlspecialchars($metodo_icon).' mr-1"></i>Método de pago', '<i class="fas '.htmlspecialchars($metodo_icon).' mr-1" style="color:#64748b;"></i>'.ucfirst(htmlspecialchars($mov['metodo_pago'])), 'even') ?>
    <?= field('<i class="fas fa-store mr-1"></i>Caja', htmlspecialchars($mov['nombre_caja'] ?? '—'), 'odd') ?>
    <?= field('<i class="fas fa-user-tie mr-1"></i>Registrado por', htmlspecialchars($mov['cajero'] ?? '—'), 'even') ?>
    <?= field('<i class="fas fa-hashtag mr-1"></i>ID Movimiento', '<span style="color:#94a3b8;">#'.htmlspecialchars($mov['id_movimiento']).'</span>', 'odd') ?>
    <?= field('<i class="fas fa-clock mr-1"></i>Hora exacta', date('H:i:s', strtotime($mov['fecha'])), 'even') ?>
</div>

<?php if ($mov['observacion']): ?>
<div class="mdd-obs"><i class="fas fa-comment-alt mr-2"></i><strong>Observación:</strong> <?= htmlspecialchars($mov['observacion']) ?></div>
<?php endif; ?>

<?php /* ══════════════ VENTA ══════════════ */ if ($ref === 'venta' && $venta): ?>
<?php
$ec = $estado_color[$venta['estado']] ?? ['#64748b','#f1f5f9'];
$comp_lbl = ($comp_label[$venta['tipo_comprobante']] ?? ucfirst($venta['tipo_comprobante'])) . ' ' . ($venta['numero_comprobante'] ?? '');
?>
<!-- Info cliente + comprobante -->
<div class="mdd-section">
    <div class="mdd-section-hdr" style="background:linear-gradient(135deg,#0369a1,#0ea5e9);">
        <i class="fas fa-user-circle"></i> Datos del Cliente y Comprobante
    </div>
    <div class="mdd-section-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;">
            <?= field('Cliente', '<strong>'.htmlspecialchars($venta['cliente_nombre'] ?? '—').'</strong>') ?>
            <?= field('Comprobante', htmlspecialchars(trim($comp_lbl))) ?>
            <?= field('Documento', htmlspecialchars(($venta['tipo_doc'] ?? 'DNI').': '.($venta['documento'] ?? '—'))) ?>
            <?= field('Teléfono', htmlspecialchars($venta['telefono'] ?? '—')) ?>
            <?= field('Tipo de pago', badge(strtoupper($venta['tipo_pago'] ?? '—'), '#0369a1', '#e0f2fe')) ?>
            <?= field('Estado', badge(strtoupper($venta['estado'] ?? '—'), $ec[0], $ec[1])) ?>
            <?= field('Vendedor', htmlspecialchars($venta['vendedor'] ?? '—')) ?>
            <?= field('Fecha venta', date('d/m/Y H:i', strtotime($venta['fecha'] ?? 'now'))) ?>
        </div>
    </div>
</div>
<!-- Productos vendidos -->
<?php if (!empty($items_venta)): ?>
<div class="mdd-section">
    <div class="mdd-section-hdr" style="background:linear-gradient(135deg,#047857,#10b981);">
        <i class="fas fa-boxes"></i> Productos Vendidos
    </div>
    <div class="mdd-section-body" style="padding:0;">
        <table class="mdd-table">
            <thead><tr>
                <th>Producto</th><th>Código</th>
                <th class="r">Cant.</th><th class="r">P. Unit.</th>
                <th class="r">Desc.</th><th class="r">Subtotal</th>
            </tr></thead>
            <tbody>
            <?php foreach ($items_venta as $it): ?>
            <tr>
                <td><strong><?= htmlspecialchars($it['nombre_producto']) ?></strong><?php if ($it['marca']): ?><br><small style="color:#94a3b8;"><?= htmlspecialchars($it['marca']) ?></small><?php endif; ?></td>
                <td><span style="background:#f1f5f9;color:#475569;border-radius:4px;padding:1px 6px;font-size:.72rem;"><?= htmlspecialchars($it['codigo']) ?></span></td>
                <td class="r"><?= $it['cantidad'] ?></td>
                <td class="r">S/. <?= number_format($it['precio_unitario'],2) ?></td>
                <td class="r"><?= $it['descuento'] > 0 ? '− S/. '.number_format($it['descuento'],2) : '—' ?></td>
                <td class="r" style="color:#059669;">S/. <?= number_format($it['subtotal'],2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="mdd-totales">
            <?php
            // Suma real de los ítems (siempre se muestra como punto de partida)
            $suma_items_venta = array_sum(array_column($items_venta, 'subtotal'));
            $desc_venta       = (float)($venta['descuento'] ?? 0);
            $igv_venta        = (float)($venta['igv']       ?? 0);
            $aplica_igv_venta = !empty($venta['aplica_igv']) && $igv_venta > 0;
            $total_venta      = (float)($venta['total']     ?? 0);
            ?>
            <div class="mdd-total-row">
                <span>Suma de productos</span>
                <span>S/. <?= number_format($suma_items_venta, 2) ?></span>
            </div>
            <?php if ($desc_venta > 0): ?>
            <div class="mdd-total-row">
                <span><i class="fas fa-tag mr-1" style="color:#dc2626;"></i>Descuento aplicado</span>
                <span style="color:#dc2626;">− S/. <?= number_format($desc_venta, 2) ?></span>
            </div>
            <div class="mdd-total-row">
                <span>Subtotal (después de descuento)</span>
                <span>S/. <?= number_format($suma_items_venta - $desc_venta, 2) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($aplica_igv_venta): ?>
            <div class="mdd-total-row">
                <span>Base imponible</span>
                <span>S/. <?= number_format($total_venta - $igv_venta, 2) ?></span>
            </div>
            <div class="mdd-total-row">
                <span><i class="fas fa-percent mr-1" style="color:#d97706;"></i>IGV (18%)</span>
                <span style="color:#d97706;">+ S/. <?= number_format($igv_venta, 2) ?></span>
            </div>
            <?php else: ?>
            <div class="mdd-total-row" style="font-size:.75rem;color:#94a3b8;">
                <span><i class="fas fa-info-circle mr-1"></i>Sin IGV</span>
                <span>—</span>
            </div>
            <?php endif; ?>
            <div class="mdd-total-row main">
                <span><i class="fas fa-check-circle mr-1" style="color:#059669;"></i>TOTAL COBRADO</span>
                <span style="color:#059669;">S/. <?= number_format($total_venta, 2) ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; /* fin venta */ ?>

<?php /* ══════════════ COMPRA ══════════════ */ if ($ref === 'compra' && $compra): ?>
<?php
$ec = $estado_color[$compra['estado']] ?? ['#64748b','#f1f5f9'];
$comp_lbl = ($comp_label[$compra['tipo_comprobante']] ?? ucfirst($compra['tipo_comprobante'])) . ' ' . ($compra['numero_comprobante'] ?? '');
?>
<!-- Info proveedor -->
<div class="mdd-section">
    <div class="mdd-section-hdr" style="background:linear-gradient(135deg,#5b21b6,#7c3aed);">
        <i class="fas fa-building"></i> Datos del Proveedor y Comprobante
    </div>
    <div class="mdd-section-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;">
            <?= field('Proveedor', '<strong>'.htmlspecialchars($compra['proveedor_nombre'] ?? '—').'</strong>') ?>
            <?= field('Comprobante', htmlspecialchars(trim($comp_lbl))) ?>
            <?= field('RUC Proveedor', htmlspecialchars($compra['proveedor_ruc'] ?? '—')) ?>
            <?= field('Teléfono', htmlspecialchars($compra['proveedor_tel'] ?? '—')) ?>
            <?= field('Tipo de pago', badge(strtoupper($compra['tipo_pago'] ?? '—'), '#5b21b6', '#ede9fe')) ?>
            <?= field('Estado', badge(strtoupper($compra['estado'] ?? '—'), $ec[0], $ec[1])) ?>
            <?= field('Registrado por', htmlspecialchars($compra['comprador'] ?? '—')) ?>
            <?= field('Fecha compra', date('d/m/Y H:i', strtotime($compra['fecha'] ?? 'now'))) ?>
        </div>
    </div>
</div>
<!-- Productos comprados -->
<?php if (!empty($items_compra)): ?>
<div class="mdd-section">
    <div class="mdd-section-hdr" style="background:linear-gradient(135deg,#5b21b6,#7c3aed);">
        <i class="fas fa-boxes"></i> Productos Comprados
    </div>
    <div class="mdd-section-body" style="padding:0;">
        <table class="mdd-table">
            <thead><tr>
                <th>Producto</th><th>Código</th>
                <th class="r">Cant.</th><th class="r">P. Compra</th>
                <th class="r">Desc.</th><th class="r">Subtotal</th>
            </tr></thead>
            <tbody>
            <?php foreach ($items_compra as $it): ?>
            <tr>
                <td><strong><?= htmlspecialchars($it['nombre_producto']) ?></strong><?php if ($it['marca']): ?><br><small style="color:#94a3b8;"><?= htmlspecialchars($it['marca']) ?></small><?php endif; ?></td>
                <td><span style="background:#f1f5f9;color:#475569;border-radius:4px;padding:1px 6px;font-size:.72rem;"><?= htmlspecialchars($it['codigo']) ?></span></td>
                <td class="r"><?= $it['cantidad'] ?></td>
                <td class="r">S/. <?= number_format($it['precio_compra'],2) ?></td>
                <td class="r"><?= $it['descuento'] > 0 ? '− S/. '.number_format($it['descuento'],2) : '—' ?></td>
                <td class="r" style="color:#7c3aed;">S/. <?= number_format($it['subtotal'],2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="mdd-totales">
            <?php
            // Suma real de los ítems
            $suma_items_compra = array_sum(array_column($items_compra, 'subtotal'));
            $desc_compra       = (float)($compra['descuento'] ?? 0);
            $igv_compra        = (float)($compra['igv']       ?? 0);
            $aplica_igv_compra = !empty($compra['aplica_igv']) && $igv_compra > 0;
            $total_compra      = (float)($compra['total']     ?? 0);
            ?>
            <div class="mdd-total-row">
                <span>Suma de productos</span>
                <span>S/. <?= number_format($suma_items_compra, 2) ?></span>
            </div>
            <?php if ($desc_compra > 0): ?>
            <div class="mdd-total-row">
                <span><i class="fas fa-tag mr-1" style="color:#dc2626;"></i>Descuento aplicado</span>
                <span style="color:#dc2626;">− S/. <?= number_format($desc_compra, 2) ?></span>
            </div>
            <div class="mdd-total-row">
                <span>Subtotal (después de descuento)</span>
                <span>S/. <?= number_format($suma_items_compra - $desc_compra, 2) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($aplica_igv_compra): ?>
            <div class="mdd-total-row">
                <span>Base imponible</span>
                <span>S/. <?= number_format($total_compra - $igv_compra, 2) ?></span>
            </div>
            <div class="mdd-total-row">
                <span><i class="fas fa-percent mr-1" style="color:#d97706;"></i>IGV (18%)</span>
                <span style="color:#d97706;">+ S/. <?= number_format($igv_compra, 2) ?></span>
            </div>
            <?php else: ?>
            <div class="mdd-total-row" style="font-size:.75rem;color:#94a3b8;">
                <span><i class="fas fa-info-circle mr-1"></i>Sin IGV</span>
                <span>—</span>
            </div>
            <?php endif; ?>
            <div class="mdd-total-row main">
                <span><i class="fas fa-check-circle mr-1" style="color:#7c3aed;"></i>TOTAL PAGADO</span>
                <span style="color:#7c3aed;">S/. <?= number_format($total_compra, 2) ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; /* fin compra */ ?>

<?php /* ══════════════ SERVICIO ══════════════ */ if ($ref === 'servicio' && $orden): ?>
<?php
$ep = $ep_color[$orden['estado_pago']] ?? ['#64748b','#f1f5f9'];
$num_ord = 'ORD-' . str_pad($ref_id, 6, '0', STR_PAD_LEFT);
$estado_ord_label = [
    'recibido'=>'Recibido','diagnostico'=>'Diagnóstico','en_proceso'=>'En proceso',
    'listo'=>'Listo','entregado'=>'Entregado','cancelado'=>'Cancelado'
];
$estado_ord_color = [
    'recibido'=>['#0369a1','#e0f2fe'],'diagnostico'=>['#d97706','#fef3c7'],
    'en_proceso'=>['#7c3aed','#ede9fe'],'listo'=>['#059669','#dcfce7'],
    'entregado'=>['#0f172a','#f1f5f9'],'cancelado'=>['#dc2626','#fee2e2']
];
$eo = $estado_ord_color[$orden['estado']] ?? ['#64748b','#f1f5f9'];
?>
<!-- Tarjeta equipo -->
<div style="margin:16px 24px 0;">
    <div class="mdd-equipo-card">
        <div class="mdd-equipo-icon"><i class="fas fa-laptop"></i></div>
        <div>
            <div class="mdd-equipo-name"><?= htmlspecialchars(trim(($orden['equipo_tipo']??'').' '.($orden['equipo_marca']??'').' '.($orden['equipo_modelo']??''))) ?></div>
            <div class="mdd-equipo-sub">
                <?php if ($orden['numero_serie']): ?>
                <i class="fas fa-barcode mr-1"></i>S/N: <?= htmlspecialchars($orden['numero_serie']) ?>
                <?php endif; ?>
                &nbsp;·&nbsp; Orden: <strong><?= $num_ord ?></strong>
            </div>
        </div>
        <div style="margin-left:auto;text-align:right;">
            <?= badge($estado_ord_label[$orden['estado']] ?? strtoupper($orden['estado']), $eo[0], $eo[1]) ?>
            <div style="margin-top:6px;"><?= badge('Pago: '.strtoupper($orden['estado_pago']), $ep[0], $ep[1]) ?></div>
        </div>
    </div>
</div>

<!-- Info cliente + técnico -->
<div class="mdd-section">
    <div class="mdd-section-hdr" style="background:linear-gradient(135deg,#047857,#10b981);">
        <i class="fas fa-user-circle"></i> Datos del Cliente y Técnico
    </div>
    <div class="mdd-section-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;">
            <?= field('Cliente', '<strong>'.htmlspecialchars($orden['cliente_nombre'] ?? '—').'</strong>') ?>
            <?= field('Documento', htmlspecialchars($orden['documento'] ?? '—')) ?>
            <?= field('Teléfono', htmlspecialchars($orden['telefono'] ?? '—')) ?>
            <?= field('Técnico asignado', htmlspecialchars($orden['tecnico'] ?? 'Sin asignar')) ?>
            <?= field('Recepcionado por', htmlspecialchars($orden['recepcionado_por'] ?? '—')) ?>
            <?= field('Fecha recepción', date('d/m/Y H:i', strtotime($orden['fecha_recepcion'] ?? 'now'))) ?>
            <?= field('Costo total', '<span style="color:#059669;font-size:1rem;font-weight:800;">S/. '.number_format($total_real_ord > 0 ? $total_real_ord : ($orden['costo_total'] ?? 0), 2).'</span>') ?>
            <?= field('Saldo pendiente', '<span style="color:'.($orden['saldo_pendiente'] > 0 ? '#dc2626' : '#059669').';">S/. '.number_format($orden['saldo_pendiente'] ?? 0, 2).'</span>') ?>
        </div>
        <?php if ($orden['problema_reportado']): ?>
        <div style="margin-top:10px;padding:10px 12px;background:#f8fafc;border-radius:8px;border-left:3px solid #0ea5e9;">
            <div class="mdd-lbl" style="margin-bottom:4px;"><i class="fas fa-exclamation-circle mr-1"></i>Problema reportado</div>
            <div style="font-size:.83rem;color:#334155;"><?= htmlspecialchars($orden['problema_reportado']) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($orden['diagnostico']): ?>
        <div style="margin-top:8px;padding:10px 12px;background:#f8fafc;border-radius:8px;border-left:3px solid #7c3aed;">
            <div class="mdd-lbl" style="margin-bottom:4px;"><i class="fas fa-stethoscope mr-1"></i>Diagnóstico</div>
            <div style="font-size:.83rem;color:#334155;"><?= htmlspecialchars($orden['diagnostico']) ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Servicios realizados -->
<?php if (!empty($servicios_orden)): ?>
<div class="mdd-section">
    <div class="mdd-section-hdr" style="background:linear-gradient(135deg,#047857,#10b981);">
        <i class="fas fa-tools"></i> Servicios Realizados
    </div>
    <div class="mdd-section-body" style="padding:0;">
        <table class="mdd-table">
            <thead><tr>
                <th>Servicio</th><th>Descripción</th>
                <th class="r">Cant.</th><th class="r">Precio</th><th class="r">Subtotal</th>
            </tr></thead>
            <tbody>
            <?php foreach ($servicios_orden as $sv): ?>
            <tr>
                <td><strong><?= htmlspecialchars($sv['nombre_servicio']) ?></strong></td>
                <td style="color:#64748b;font-size:.78rem;"><?= htmlspecialchars($sv['descripcion'] ?? '—') ?></td>
                <td class="r"><?= $sv['cantidad'] ?></td>
                <td class="r">S/. <?= number_format($sv['precio'],2) ?></td>
                <td class="r" style="color:#059669;">S/. <?= number_format($sv['subtotal'],2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!empty($cotizaciones_orden)): ?>
        <div class="mdd-totales" style="border-top:1px solid #e2e8f0;">
            <div class="mdd-total-row"><span>Subtotal servicios</span><span>S/. <?= number_format($total_servicios_ord, 2) ?></span></div>
        </div>
        <?php else: ?>
        <div class="mdd-totales">
            <div class="mdd-total-row main">
                <span><i class="fas fa-check-circle mr-1" style="color:#059669;"></i>TOTAL COBRADO</span>
                <span style="color:#059669;">S/. <?= number_format($total_real_ord > 0 ? $total_real_ord : ($orden['costo_total'] ?? 0), 2) ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Cotizaciones de repuestos -->
<?php if (!empty($cotizaciones_orden)): ?>
<?php
$estado_cot_label = [
    'cotizado'        => ['Cotizado',      '#1d4ed8','#dbeafe'],
    'aprobado'        => ['Aprobado',      '#166534','#dcfce7'],
    'comprado'        => ['Comprado',      '#0369a1','#e0f2fe'],
    'pendiente_compra'=> ['Pend. compra',  '#854d0e','#fef9c3'],
    'completado'      => ['Completado',    '#065f46','#d1fae5'],
];
?>
<div class="mdd-section">
    <div class="mdd-section-hdr" style="background:linear-gradient(135deg,#5b21b6,#7c3aed);">
        <i class="fas fa-cogs"></i> Cotizaciones de Repuestos
        <span style="margin-left:auto;background:rgba(255,255,255,.2);border-radius:20px;padding:1px 10px;font-size:.72rem;"><?= count($cotizaciones_orden) ?></span>
    </div>
    <div class="mdd-section-body" style="padding:0;">
        <table class="mdd-table">
            <thead><tr>
                <th>Repuesto / Descripción</th><th>Estado</th>
                <th class="r">Cant.</th><th class="r">P. Unit.</th><th class="r">Subtotal</th>
            </tr></thead>
            <tbody>
            <?php foreach ($cotizaciones_orden as $cot): ?>
            <?php
            $nombre_cot = $cot['nombre_producto'] ?: $cot['descripcion'];
            $ec_cot = $estado_cot_label[$cot['estado']] ?? [ucfirst($cot['estado']), '#64748b', '#f1f5f9'];
            ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($nombre_cot) ?></strong>
                    <?php if ($cot['codigo']): ?>
                    <br><span style="background:#f1f5f9;color:#475569;border-radius:4px;padding:1px 6px;font-size:.72rem;"><?= htmlspecialchars($cot['codigo']) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= badge($ec_cot[0], $ec_cot[1], $ec_cot[2]) ?></td>
                <td class="r"><?= $cot['cantidad'] ?></td>
                <td class="r">S/. <?= number_format($cot['precio_unitario'],2) ?></td>
                <td class="r" style="color:#7c3aed;">S/. <?= number_format($cot['subtotal'],2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="mdd-totales">
            <?php if (!empty($servicios_orden)): ?>
            <div class="mdd-total-row"><span>Subtotal servicios</span><span>S/. <?= number_format($total_servicios_ord, 2) ?></span></div>
            <div class="mdd-total-row"><span>Subtotal repuestos</span><span>S/. <?= number_format($total_cotizaciones_ord, 2) ?></span></div>
            <?php else: ?>
            <div class="mdd-total-row"><span>Subtotal repuestos</span><span>S/. <?= number_format($total_cotizaciones_ord, 2) ?></span></div>
            <?php endif; ?>
            <div class="mdd-total-row main">
                <span><i class="fas fa-check-circle mr-1" style="color:#059669;"></i>TOTAL COBRADO</span>
                <span style="color:#059669;">S/. <?= number_format($total_real_ord > 0 ? $total_real_ord : ($orden['costo_total'] ?? 0), 2) ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; /* fin servicio */ ?>

<?php /* ══════════════ MANUAL ══════════════ */ if ($ref === 'manual'): ?>
<div class="mdd-section">
    <div class="mdd-section-hdr" style="background:linear-gradient(135deg,#92400e,#d97706);">
        <i class="fas fa-hand-holding-usd"></i> Movimiento Manual
    </div>
    <div class="mdd-section-body">
        <div style="display:flex;align-items:center;gap:12px;padding:8px 0;">
            <div style="width:44px;height:44px;border-radius:12px;background:<?= $es_ing ? 'linear-gradient(135deg,#047857,#10b981)' : 'linear-gradient(135deg,#b91c1c,#ef4444)' ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.1rem;flex-shrink:0;">
                <i class="fas <?= $es_ing ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
            </div>
            <div>
                <div style="font-size:.88rem;font-weight:700;color:#1e293b;"><?= htmlspecialchars($mov['descripcion']) ?></div>
                <div style="font-size:.75rem;color:#64748b;margin-top:2px;">
                    Registrado por <strong><?= htmlspecialchars($mov['cajero'] ?? '—') ?></strong>
                    · <?= date('d/m/Y H:i', strtotime($mov['fecha'])) ?>
                </div>
            </div>
            <div style="margin-left:auto;font-size:1.2rem;font-weight:800;font-family:monospace;color:<?= $color_monto ?>;">
                <?= $signo ?> S/. <?= number_format($mov['monto'], 2) ?>
            </div>
        </div>
        <div style="margin-top:10px;padding:10px 12px;background:#fffbeb;border-radius:8px;border-left:3px solid #f59e0b;font-size:.8rem;color:#78350f;">
            <i class="fas fa-info-circle mr-2"></i>
            Este movimiento fue registrado manualmente por el cajero. No está vinculado a ninguna venta, compra ni servicio.
        </div>
    </div>
</div>
<?php endif; /* fin manual */ ?>

<!-- Espaciado inferior -->
<div style="height:16px;"></div>
</div><!-- /mdd-wrap -->
