<?php
session_start();

// Si el usuario ya inició sesión, enviarlo al dashboard
if (isset($_SESSION['usuario_id'])) {
    header("Location: public/dashboard.php");
    exit;
}

// Si no ha iniciado sesión, enviarlo al login
header("Location: public/login.php");
exit;
