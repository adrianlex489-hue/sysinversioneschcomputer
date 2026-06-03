<?php
// ============================================================
// ajax_detalle_orden.php | SysInversiones CH Computer 2026
// Devuelve los servicios aplicados a una orden (detalle_orden)
// ============================================================
header('Content-Type: application/json; charset=utf-8');

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';

if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_TECNICO'))       define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_TECNICO]);

$id_orden = (int)($_GET['id_orden'] ?? 0);
if (!$id_orden) {
    echo json_encode(['success' => false, 'error' => 'ID de orden requerido.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT d.id, d.id_servicio, d.descripcion, d.precio, d.cantidad, d.subtotal,
               s.nombre AS nombre_servicio
        FROM detalle_orden d
        LEFT JOIN servicios s ON s.id_servicio = d.id_servicio
        WHERE d.id_orden = ?
        ORDER BY d.id ASC
    ");
    $stmt->execute([$id_orden]);
    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'detalle' => $detalle]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
