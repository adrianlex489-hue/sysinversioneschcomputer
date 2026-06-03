<?php
/**
 * api_consultar_dni.php — Endpoint AJAX para consultar DNI
 * Método: POST  |  Parámetro: dni
 * Respuesta: JSON
 */
ob_start(); // Capturar cualquier output accidental antes del header

header('Content-Type: application/json; charset=utf-8');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'includes/api_dni.php';

ob_end_clean(); // Limpiar cualquier output de los requires

define('API_DNI_TOKEN', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjo0MTUsImV4cCI6MTc2NTc0NjcwM30.HX2wdiyrwQ55YfmBIlnsxqY3mhEW8_gqpl1V598c1NM');

$dni = trim($_POST['dni'] ?? '');

if (empty($dni)) {
    echo json_encode(['success' => false, 'error' => 'El campo DNI es requerido.']);
    exit;
}

$api      = new APIDni(API_DNI_TOKEN);
$response = $api->consultar($dni);

if (!$response['success']) {
    echo json_encode(['success' => false, 'error' => $response['error'] ?? 'DNI no encontrado en RENIEC.']);
    exit;
}

$datos = APIDni::formatear_datos($response);

if (!$datos) {
    echo json_encode(['success' => false, 'error' => 'No se pudieron extraer los datos del DNI.']);
    exit;
}

echo json_encode(['success' => true, 'datos' => $datos]);
exit;
