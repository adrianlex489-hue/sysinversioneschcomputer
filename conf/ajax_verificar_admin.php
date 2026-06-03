<?php
// ============================================================
// conf/ajax_verificar_admin.php
// Verifica que la contraseña ingresada corresponde a un
// administrador activo del sistema.
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

// Solo peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido.']);
    exit;
}

// Sesión activa requerida
if (empty($_SESSION['id_usuario'])) {
    echo json_encode(['ok' => false, 'msg' => 'Sesión no iniciada.']);
    exit;
}

$clave = trim($_POST['clave'] ?? '');
if ($clave === '') {
    echo json_encode(['ok' => false, 'msg' => 'Ingresa la contraseña.']);
    exit;
}

require_once __DIR__ . '/database.php';

try {
    // Buscar cualquier administrador activo (id_rol = 1)
    $stmt = $pdo->prepare(
        "SELECT clave FROM usuarios WHERE id_rol = 1 AND estado = 1 LIMIT 1"
    );
    $stmt->execute();
    $hash = $stmt->fetchColumn();

    if ($hash && password_verify($clave, $hash)) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Contraseña incorrecta.']);
    }
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error interno.']);
}
exit;
