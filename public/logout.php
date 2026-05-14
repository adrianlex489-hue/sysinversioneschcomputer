<?php
session_start();

// 1. Eliminar todas las variables de sesión
$_SESSION = array();

// 2. Destruir la sesión por completo
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// 3. Redirigir al formulario de login
header("Location: login.php");
exit;
?>