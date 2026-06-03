<?php
// ============================================================
// modules/servicios/ajax_buscar_cliente.php | SysInversiones 2026
// Busca por DNI (8 dígitos) → clientes_natural
//         o RUC (11 dígitos) → clientes_empresa
// POST: documento, guardar (0|1)
// ============================================================
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']); exit;
}

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'includes/api_dni.php';
require_once $ruta_base . 'includes/api_ruc.php';

if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_TECNICO'))       define('ROL_TECNICO', 3);
if (!defined('ROL_ASESOR_COMERCIAL'))        define('ROL_ASESOR_COMERCIAL', 2);
verificar_acceso([ROL_ADMINISTRADOR, ROL_TECNICO, ROL_ASESOR_COMERCIAL]);

define('API_TOKEN', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjo0MTUsImV4cCI6MTc2NTc0NjcwM30.HX2wdiyrwQ55YfmBIlnsxqY3mhEW8_gqpl1V598c1NM');

$documento = trim($_POST['documento'] ?? $_POST['dni'] ?? '');
$guardar   = (int)($_POST['guardar'] ?? 0);

if (empty($documento)) {
    echo json_encode(['success' => false, 'error' => 'Ingresa un número de documento.']); exit;
}

$longitud = strlen($documento);

if ($longitud === 8 && ctype_digit($documento)) {
    buscarPorDni($pdo, $documento, $guardar);
} elseif ($longitud === 11 && ctype_digit($documento)) {
    buscarPorRuc($pdo, $documento, $guardar);
} else {
    echo json_encode(['success' => false, 'error' => 'Ingresa un DNI (8 dígitos) o RUC (11 dígitos) válido.']); exit;
}

// ══════════════════════════════════════════════════════════════════════════════
function buscarPorDni(PDO $pdo, string $dni, int $guardar): void {
    try {
        $st = $pdo->prepare("
            SELECT id_cliente_natural AS id_cliente,
                   CONCAT(COALESCE(nombres,''),' ',COALESCE(apellido_paterno,''),' ',COALESCE(apellido_materno,'')) AS nombre_completo,
                   documento_identidad, telefono
            FROM clientes_natural
            WHERE documento_identidad = ? AND estado_cliente = 1
            LIMIT 1
        ");
        $st->execute([$dni]);
        $existe = $st->fetch();
        if ($existe) {
            echo json_encode([
                'success'   => true, 'ya_existe' => true, 'tipo' => 'natural',
                'datos'     => [
                    'id_cliente'      => $existe['id_cliente'],
                    'nombre_completo' => trim($existe['nombre_completo']),
                    'dni'             => $existe['documento_identidad'],
                    'telefono'        => $existe['telefono'] ?? '',
                    'tipo_cliente'    => 'natural',
                ]
            ]); return;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error BD: ' . $e->getMessage()]); return;
    }

    $api      = new APIDni(API_TOKEN);
    $response = $api->consultar($dni);
    if (!$response['success']) {
        echo json_encode(['success' => false, 'error' => $response['error'] ?? 'DNI no encontrado en RENIEC.']); return;
    }
    $datos = APIDni::formatear_datos($response);
    if (!$datos) {
        echo json_encode(['success' => false, 'error' => 'No se pudieron extraer los datos de la API.']); return;
    }

    $nombre_completo  = trim($datos['nombres'] . ' ' . $datos['apellido_paterno'] . ' ' . $datos['apellido_materno']);
    $id_cliente_nuevo = null;

    if ($guardar === 1) {
        try {
            $pdo->prepare("
                INSERT INTO clientes_natural
                    (nombres, apellido_paterno, apellido_materno, tipo_documento,
                     documento_identidad, direccion, estado_cliente, fecha_registro)
                VALUES (?, ?, ?, 'DNI', ?, ?, 1, NOW())
            ")->execute([$datos['nombres'], $datos['apellido_paterno'], $datos['apellido_materno'],
                         $datos['dni'], $datos['direccion'] ?? null]);
            $id_cliente_nuevo = (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $st2 = $pdo->prepare("SELECT id_cliente_natural FROM clientes_natural WHERE documento_identidad=? LIMIT 1");
                $st2->execute([$datos['dni']]);
                $id_cliente_nuevo = (int)($st2->fetchColumn() ?: 0);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al guardar: ' . $e->getMessage()]); return;
            }
        }
    }

    echo json_encode([
        'success' => true, 'ya_existe' => false, 'guardado' => $guardar === 1,
        'tipo' => 'natural', 'id_cliente' => $id_cliente_nuevo,
        'datos' => [
            'id_cliente'       => $id_cliente_nuevo,
            'nombre_completo'  => $nombre_completo,
            'nombres'          => $datos['nombres'],
            'apellido_paterno' => $datos['apellido_paterno'],
            'apellido_materno' => $datos['apellido_materno'],
            'dni'              => $datos['dni'],
            'documento'        => $datos['dni'],
            'tipo_documento'   => 'DNI',
            'tipo_cliente'     => 'natural',
            'direccion'        => $datos['direccion'] ?? '',
        ]
    ]);
}

// ══════════════════════════════════════════════════════════════════════════════
function buscarPorRuc(PDO $pdo, string $ruc, int $guardar): void {
    try {
        $st = $pdo->prepare("
            SELECT id_cliente_empresa AS id_cliente,
                   razon_social AS nombre_completo,
                   ruc AS documento_identidad, telefono
            FROM clientes_empresa
            WHERE ruc = ? AND estado_cliente = 1
            LIMIT 1
        ");
        $st->execute([$ruc]);
        $existe = $st->fetch();
        if ($existe) {
            echo json_encode([
                'success'   => true, 'ya_existe' => true, 'tipo' => 'empresa',
                'datos'     => [
                    'id_cliente'      => $existe['id_cliente'],
                    'nombre_completo' => trim($existe['nombre_completo']),
                    'ruc'             => $existe['documento_identidad'],
                    'documento'       => $existe['documento_identidad'],
                    'tipo_cliente'    => 'empresa',
                ]
            ]); return;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error BD: ' . $e->getMessage()]); return;
    }

    $api      = new APIRuc(API_TOKEN);
    $response = $api->consultar($ruc);
    if (!$response['success']) {
        echo json_encode(['success' => false, 'error' => $response['error'] ?? 'RUC no encontrado en SUNAT.']); return;
    }
    $datos = APIRuc::formatear_datos($response);
    if (!$datos) {
        echo json_encode(['success' => false, 'error' => 'No se pudieron extraer los datos de la API.']); return;
    }

    $id_cliente_nuevo = null;

    if ($guardar === 1) {
        try {
            $pdo->prepare("
                INSERT INTO clientes_empresa
                    (razon_social, ruc, direccion, estado_cliente, fecha_registro)
                VALUES (?, ?, ?, 1, NOW())
            ")->execute([$datos['razon_social'], $datos['ruc'], $datos['direccion'] ?? null]);
            $id_cliente_nuevo = (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $st2 = $pdo->prepare("SELECT id_cliente_empresa FROM clientes_empresa WHERE ruc=? LIMIT 1");
                $st2->execute([$datos['ruc']]);
                $id_cliente_nuevo = (int)($st2->fetchColumn() ?: 0);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al guardar: ' . $e->getMessage()]); return;
            }
        }
    }

    echo json_encode([
        'success' => true, 'ya_existe' => false, 'guardado' => $guardar === 1,
        'tipo' => 'empresa', 'id_cliente' => $id_cliente_nuevo,
        'datos' => [
            'id_cliente'      => $id_cliente_nuevo,
            'nombre_completo' => $datos['razon_social'],
            'razon_social'    => $datos['razon_social'],
            'ruc'             => $datos['ruc'],
            'documento'       => $datos['ruc'],
            'tipo_documento'  => 'RUC',
            'tipo_cliente'    => 'empresa',
            'direccion'       => $datos['direccion'] ?? '',
            'estado_sunat'    => $datos['estado_sunat'] ?? '',
        ]
    ]);
}
