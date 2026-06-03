<?php
// ============================================================
// modules/personas/api_consultar_ruc.php | SysInversiones 2026
// Consulta RUC via API SUNAT (miapi.cloud)
// POST: ruc
// ============================================================
ob_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']); exit;
}

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'includes/api_ruc.php';

ob_end_clean();

define('API_RUC_TOKEN', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjo0MTUsImV4cCI6MTc2NTc0NjcwM30.HX2wdiyrwQ55YfmBIlnsxqY3mhEW8_gqpl1V598c1NM');

$ruc = trim($_POST['ruc'] ?? '');

if (!preg_match('/^\d{11}$/', $ruc)) {
    echo json_encode(['success' => false, 'error' => 'El RUC debe tener exactamente 11 dígitos.']); exit;
}

$api      = new APIRuc(API_RUC_TOKEN);
$response = $api->consultar($ruc);

if (!$response['success']) {
    echo json_encode(['success' => false, 'error' => $response['error'] ?? 'RUC no encontrado en SUNAT.']); exit;
}

$datos = APIRuc::formatear_datos($response);

if (!$datos) {
    echo json_encode(['success' => false, 'error' => 'No se pudieron extraer los datos de la API.']); exit;
}

echo json_encode([
    'success' => true,
    'datos'   => [
        'razon_social' => $datos['razon_social'],
        'ruc'          => $datos['ruc'],
        'direccion'    => $datos['direccion'] ?? '',
        'estado_sunat' => $datos['estado_sunat'] ?? '',
        'condicion'    => $datos['condicion'] ?? '',
    ]
]);
