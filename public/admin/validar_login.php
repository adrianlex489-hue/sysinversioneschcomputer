<?php
session_start();
include '../conexion.php'; 

// Inicializar o incrementar el contador de intentos
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['usuario'];
    $password_ingresada = $_POST['password'];

    try {
        // La consulta ha sido corregida para usar 'clave' en lugar de 'password'
        $sql = "SELECT id, username, clave, rol FROM usuario WHERE username = ? AND estado = 1 LIMIT 1";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$username]);
        $usuario_db = $stmt->fetch(PDO::FETCH_ASSOC);

        // Se usa 'clave' en lugar de 'password' para verificar
        if ($usuario_db && password_verify($password_ingresada, $usuario_db['clave'])) {
            // Login exitoso: Reiniciar el contador de intentos
            $_SESSION['login_attempts'] = 0;
            $_SESSION['usuario_id'] = $usuario_db['id'];
            $_SESSION['usuario_nombre'] = $usuario_db['username'];
            $_SESSION['rol'] = $usuario_db['rol'];
            header("Location: principal.php");
            exit;
        } else {
            // Login fallido: Incrementar el contador de intentos
            $_SESSION['login_attempts']++;
            
            if ($_SESSION['login_attempts'] >= 3) {
                session_destroy();
                header("Location: login.php?error=demasiados_intentos");
                exit;
            } else {
                $intentos_restantes = 3 - $_SESSION['login_attempts'];
                header("Location: login.php?error=Usuario o contraseña incorrectos. Intentos restantes: " . $intentos_restantes);
                exit;
            }
        }
    } catch (PDOException $e) {
        header("Location: login.php?error=Error en el servidor. Intente más tarde.");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}
?>