<?php
// ============================================================
// ajax_buscar_dni.php | Botica 2026
// Consulta DNI via API y opcionalmente guarda el cliente en BD
// POST: dni, guardar (0|1)
// ============================================================
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']); exit;
}

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'includes/api_dni.php';

if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_CAJERO'))        define('ROL_CAJERO', 2);
if (!defined('ROL_TRABAJADOR'))    define('ROL_TRABAJADOR', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_CAJERO, ROL_TRABAJADOR]);

define('API_DNI_TOKEN', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjo0MTUsImV4cCI6MTc2NTc0NjcwM30.HX2wdiyrwQ55YfmBIlnsxqY3mhEW8_gqpl1V598c1NM');

$dni     = trim($_POST['dni']     ?? '');
$guardar = (int)($_POST['guardar'] ?? 0);

if (empty($dni)) {
    echo json_encode(['success' => false, 'error' => 'Ingresa un DNI.']); exit;
}

// Verificar si ya existe en BD
try {
    $stEx = $pdo->prepare("SELECT id_cliente, CONCAT(nombres,' ',apellido_paterno,' ',COALESCE(apellido_materno,'')) AS nombre_completo, dni, telefono FROM clientes WHERE dni=? AND estado_cliente=1 LIMIT 1");
    $stEx->execute([$dni]);
    $existe = $stEx->fetch();
    if ($existe) {
        echo json_encode([
            'success'  => true,
            'ya_existe'=> true,
            'datos'    => [
                'id_cliente'     => $existe['id_cliente'],
                'nombre_completo'=> trim($existe['nombre_completo']),
                'dni'            => $existe['dni'],
                'telefono'       => $existe['telefono'] ?? '',
            ]
        ]); exit;
    }
} catch (PDOException $e) {}

// Consultar API
$api      = new APIDni(API_DNI_TOKEN);
$response = $api->consultar($dni);

if (!$response['success']) {
    echo json_encode(['success' => false, 'error' => $response['error'] ?? 'No se encontró el DNI.']); exit;
}

$datos = APIDni::formatear_datos($response);
if (!$datos) {
    echo json_encode(['success' => false, 'error' => 'No se pudieron extraer los datos.']); exit;
}

$nombre_completo = trim($datos['nombres'] . ' ' . $datos['apellido_paterno'] . ' ' . $datos['apellido_materno']);
$id_cliente_nuevo = null;

// Guardar en BD si se solicitó
if ($guardar === 1) {
    try {
        $pdo->prepare("INSERT INTO clientes (nombres, apellido_paterno, apellido_materno, dni, direccion, estado_cliente) VALUES (?,?,?,?,?,1)")
            ->execute([$datos['nombres'], $datos['apellido_paterno'], $datos['apellido_materno'], $datos['dni'], $datos['direccion'] ?? null]);
        $id_cliente_nuevo = (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        // Si ya existe por DNI duplicado, obtener el existente
        if ($e->getCode() == 23000) {
            $stEx2 = $pdo->prepare("SELECT id_cliente FROM clientes WHERE dni=? LIMIT 1");
            $stEx2->execute([$datos['dni']]);
            $id_cliente_nuevo = (int)($stEx2->fetchColumn() ?: 0);
        }
    }
}

echo json_encode([
    'success'        => true,
    'ya_existe'      => false,
    'guardado'       => $guardar === 1,
    'id_cliente'     => $id_cliente_nuevo,
    'datos'          => [
        'id_cliente'     => $id_cliente_nuevo,
        'nombre_completo'=> $nombre_completo,
        'nombres'        => $datos['nombres'],
        'apellido_paterno'=> $datos['apellido_paterno'],
        'apellido_materno'=> $datos['apellido_materno'],
        'dni'            => $datos['dni'],
        'direccion'      => $datos['direccion'] ?? '',
    ]
]);
