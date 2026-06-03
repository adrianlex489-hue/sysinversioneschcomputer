<?php
// public/logout.php | SysInversiones CH Computer 2026
session_start();

// Guardar nombre del usuario en cookie temporal para mostrar alerta en login
$nombre = $_SESSION['nombre_completo'] ?? '';
if ($nombre) {
    $data = json_encode(['nombre' => $nombre]);
    setcookie('swal_logout', $data, time() + 30, '/', '', false, true);
}

// Eliminar todas las variables de sesión
$_SESSION = [];

// Destruir la cookie de sesión
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// Redirigir al login
header('Location: login.php');
exit;
