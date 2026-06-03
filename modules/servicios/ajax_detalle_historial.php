<?php
// ajax_detalle_historial.php — Detalle completo de orden para modal historial
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_TECNICO'))       define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_TECNICO]);

header('Content-Type: application/json; charset=utf-8');

$id_orden = (int)($_GET['id'] ?? 0);
if (!$id_orden) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

try {
    // Datos principales de la orden
    $stmt = $pdo->prepare("
        SELECT o.*,
               CASE o.tipo_cliente
                   WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
                   ELSE TRIM(CONCAT_WS(', ',
                       NULLIF(TRIM(CONCAT_WS(' ', cn.apellido_paterno, cn.apellido_materno)),''),
                       NULLIF(TRIM(cn.nombres),'')
                   ))
               END AS cliente_nombre,
               CASE o.tipo_cliente WHEN 'empresa' THEN ce.telefono    ELSE cn.telefono    END AS telefono,
               CASE o.tipo_cliente WHEN 'empresa' THEN ce.email       ELSE cn.email       END AS cliente_email,
               CASE o.tipo_cliente WHEN 'empresa' THEN ce.ruc         ELSE cn.documento_identidad END AS documento,
               e.tipo AS equipo_tipo, e.marca, e.modelo, e.numero_serie,
               e.accesorios, e.estado_fisico, e.contrasena_equipo, e.fotos_ingreso,
               o.fotos_taller,
               u.nombre_completo AS recepcionado_por,
               t.nombre_completo AS tecnico_nombre
        FROM ordenes_servicio o
        LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = o.id_cliente AND o.tipo_cliente = 'natural'
        LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = o.id_cliente AND o.tipo_cliente = 'empresa'
        JOIN  equipos   e ON e.id_equipo  = o.id_equipo
        LEFT JOIN usuarios u ON u.id_usuario = o.id_usuario
        LEFT JOIN usuarios t ON t.id_usuario = o.id_tecnico
        WHERE o.id_orden = ?
    ");
    $stmt->execute([$id_orden]);
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$orden) { echo json_encode(['ok'=>false,'msg'=>'Orden no encontrada']); exit; }

    // Servicios aplicados
    $stDet = $pdo->prepare("SELECT d.*, s.nombre AS nombre_servicio, s.tipo AS tipo_servicio FROM detalle_orden d LEFT JOIN servicios s ON s.id_servicio = d.id_servicio WHERE d.id_orden = ? ORDER BY d.id ASC");
    $stDet->execute([$id_orden]);
    $servicios = $stDet->fetchAll(PDO::FETCH_ASSOC);

    // Cotizaciones de repuestos
    $stCot = $pdo->prepare("
        SELECT oc.*,
               p.nombre_producto,
               p.codigo AS codigo_producto
        FROM orden_cotizaciones oc
        LEFT JOIN productos p ON p.id_producto = oc.id_producto
        WHERE oc.id_orden = ?
        ORDER BY oc.fecha_cotizacion ASC
    ");
    $stCot->execute([$id_orden]);
    $cotizaciones = $stCot->fetchAll(PDO::FETCH_ASSOC);

    // Historial de estados
    $stHist = $pdo->prepare("SELECT h.*, u.nombre_completo AS usuario_nombre FROM servicio_historial h LEFT JOIN usuarios u ON u.id_usuario = h.id_usuario WHERE h.id_orden = ? ORDER BY h.fecha ASC");
    $stHist->execute([$id_orden]);
    $historial = $stHist->fetchAll(PDO::FETCH_ASSOC);

    // Fotos
    $fotos_ingreso = [];
    $fotos_taller  = [];
    if (!empty($orden['fotos_ingreso'])) { $d = json_decode($orden['fotos_ingreso'], true); if (is_array($d)) $fotos_ingreso = $d; }
    if (!empty($orden['fotos_taller']))  { $d = json_decode($orden['fotos_taller'],  true); if (is_array($d)) $fotos_taller  = $d; }

    $costo_servicios = array_sum(array_column($servicios, 'subtotal'));

    // Sumar cotizaciones no rechazadas al costo total
    $total_cotizaciones = 0;
    foreach ($cotizaciones as $c) {
        if ($c['estado'] !== 'rechazado') {
            $total_cotizaciones += (float)$c['subtotal'];
        }
    }

    $costo_total = $costo_servicios + $total_cotizaciones;

    echo json_encode([
        'ok'                 => true,
        'orden'              => $orden,
        'servicios'          => $servicios,
        'cotizaciones'       => $cotizaciones,
        'historial'          => $historial,
        'fotos_ingreso'      => $fotos_ingreso,
        'fotos_taller'       => $fotos_taller,
        'costo_servicios'    => $costo_servicios,
        'total_cotizaciones' => $total_cotizaciones,
        'costo_total'        => $costo_total,
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
