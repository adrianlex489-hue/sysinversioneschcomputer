<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'bdbotica');
define('DB_USER', 'root');
define('DB_PASS', '');

$opciones_pdo = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $conexion = new PDO($dsn, DB_USER, DB_PASS, $opciones_pdo);

} catch (PDOException $e) {
    // Durante el desarrollo mejor mostrar el error real:
    die("Error de conexión: " . $e->getMessage());
}
?>
