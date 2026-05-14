<?php
/**
 * api_consultar_ruc.php — Endpoint AJAX para consultar RUC
 * Método: POST  |  Parámetro: ruc
 * Respuesta: JSON
 */
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'includes/api_ruc.php';

// ── Token de la API ──────────────────────────────────────────────────────────
define('API_RUC_TOKEN', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjo0MTUsImV4cCI6MTc2NTc0NjcwM30.HX2wdiyrwQ55YfmBIlnsxqY3mhEW8_gqpl1V598c1NM');

$ruc = trim($_POST['ruc'] ?? '');

if (empty($ruc)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'El campo RUC es requerido.']);
    exit;
}

$api      = new APIRuc(API_RUC_TOKEN);
$response = $api->consultar($ruc);

if (!$response['success']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $response['error']]);
    exit;
}

$datos = APIRuc::formatear_datos($response);

if (!$datos) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No se pudieron extraer los datos del RUC.']);
    exit;
}

echo json_encode(['success' => true, 'datos' => $datos]);
exit;
