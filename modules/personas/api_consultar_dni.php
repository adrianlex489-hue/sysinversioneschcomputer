<?php
/**
 * api_consultar_dni.php — Endpoint AJAX para consultar DNI
 * Método: POST  |  Parámetro: dni
 * Respuesta: JSON
 */
header('Content-Type: application/json; charset=utf-8');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'includes/api_dni.php';

// ── Token de la API ──────────────────────────────────────────────────────────
// Reemplaza este valor con tu token real de miapi.cloud
define('API_DNI_TOKEN', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjo0MTUsImV4cCI6MTc2NTc0NjcwM30.HX2wdiyrwQ55YfmBIlnsxqY3mhEW8_gqpl1V598c1NM');

$dni = trim($_POST['dni'] ?? '');

if (empty($dni)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'El campo DNI es requerido.']);
    exit;
}

$api      = new APIDni(API_DNI_TOKEN);
$response = $api->consultar($dni);

if (!$response['success']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $response['error']]);
    exit;
}

$datos = APIDni::formatear_datos($response);

if (!$datos) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No se pudieron extraer los datos del DNI.']);
    exit;
}

echo json_encode(['success' => true, 'datos' => $datos]);
exit;
