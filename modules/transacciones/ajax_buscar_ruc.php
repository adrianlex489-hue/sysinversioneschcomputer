<?php
// ============================================================
// ajax_buscar_ruc.php | Botica 2026
// Consulta RUC via API y opcionalmente guarda el proveedor en BD
// POST: ruc, guardar (0|1)
// ============================================================
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']); exit;
}

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'includes/api_ruc.php';

if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_CAJERO'))        define('ROL_CAJERO', 2);
if (!defined('ROL_TRABAJADOR'))    define('ROL_TRABAJADOR', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_CAJERO, ROL_TRABAJADOR]);

define('API_RUC_TOKEN', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjo0MTUsImV4cCI6MTc2NTc0NjcwM30.HX2wdiyrwQ55YfmBIlnsxqY3mhEW8_gqpl1V598c1NM');

$ruc     = trim($_POST['ruc']     ?? '');
$guardar = (int)($_POST['guardar'] ?? 0);

if (empty($ruc)) {
    echo json_encode(['success' => false, 'error' => 'Ingresa un RUC.']); exit;
}

// Verificar si ya existe en BD
try {
    $stEx = $pdo->prepare("SELECT id_proveedor, razon_social, ruc, telefono FROM proveedores WHERE ruc=? AND estado=1 LIMIT 1");
    $stEx->execute([$ruc]);
    $existe = $stEx->fetch();
    if ($existe) {
        echo json_encode([
            'success'     => true,
            'ya_existe'   => true,
            'datos'       => [
                'id_proveedor' => $existe['id_proveedor'],
                'razon_social' => $existe['razon_social'],
                'ruc'          => $existe['ruc'],
                'telefono'     => $existe['telefono'] ?? '',
            ]
        ]); exit;
    }
} catch (PDOException $e) {}

// Consultar API
$api      = new APIRuc(API_RUC_TOKEN);
$response = $api->consultar($ruc);

if (!$response['success']) {
    echo json_encode(['success' => false, 'error' => $response['error'] ?? 'No se encontró el RUC.']); exit;
}

$datos = APIRuc::formatear_datos($response);
if (!$datos) {
    echo json_encode(['success' => false, 'error' => 'No se pudieron extraer los datos.']); exit;
}

$id_proveedor_nuevo = null;

// Guardar en BD si se solicitó
if ($guardar === 1) {
    try {
        $pdo->prepare("INSERT INTO proveedores (razon_social, ruc, direccion, estado) VALUES (?,?,?,1)")
            ->execute([$datos['razon_social'], $datos['ruc'], $datos['direccion'] ?? null]);
        $id_proveedor_nuevo = (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $stEx2 = $pdo->prepare("SELECT id_proveedor FROM proveedores WHERE ruc=? LIMIT 1");
            $stEx2->execute([$datos['ruc']]);
            $id_proveedor_nuevo = (int)($stEx2->fetchColumn() ?: 0);
        }
    }
}

echo json_encode([
    'success'           => true,
    'ya_existe'         => false,
    'guardado'          => $guardar === 1,
    'id_proveedor'      => $id_proveedor_nuevo,
    'datos'             => [
        'id_proveedor' => $id_proveedor_nuevo,
        'razon_social' => $datos['razon_social'],
        'ruc'          => $datos['ruc'],
        'direccion'    => $datos['direccion'] ?? '',
    ]
]);
