<?php
define('DB_HOST', 'localhost'); 
define('DB_NAME', 'bdbotica'); 
define('DB_USER', 'root');     
define('DB_PASS', '');     

// ── Zona horaria Perú (UTC-5) ─────────────────────────────────────────────────
date_default_timezone_set('America/Lima');

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // Sincronizar zona horaria de MySQL con PHP (UTC-5 = Perú)
    $pdo->exec("SET time_zone = '-05:00'");
} catch (\PDOException $e) {
    http_response_code(500);
    die("Error de conexión a la base de datos: " . $e->getMessage());
}