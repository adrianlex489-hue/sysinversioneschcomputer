<?php
// Asegura que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Definición de roles — deben coincidir con la tabla `roles` de la BD ──────
// id_rol=1 → Administrador
// id_rol=2 → Asesor Comercial
// id_rol=3 → Técnico
define('ROL_ADMINISTRADOR',    1);
define('ROL_ASESOR_COMERCIAL', 2);
define('ROL_TECNICO',          3);

// Variables globales para uso en vistas (sidebar, dashboard, etc.)
$rol_actual          = $_SESSION['id_rol'] ?? 0;
$es_administrador    = ($rol_actual === ROL_ADMINISTRADOR);
$es_asesor_comercial = ($rol_actual === ROL_ASESOR_COMERCIAL);
$es_tecnico          = ($rol_actual === ROL_TECNICO);

/**
 * Verifica si el usuario tiene permiso para acceder a la página.
 *
 * @param array $roles_permitidos Array con los IDs de roles permitidos, ejemplo: [1, 2]
 *
 * Si no está autenticado redirige al login.
 * Si no tiene el rol requerido redirige al dashboard con mensaje de error.
 */
function verificar_acceso(array $roles_permitidos): void {
    if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['id_rol'])) {
        header('Location: /sysinversioneschcomputer/public/login.php');
        exit;
    }

    $rol_usuario = (int)$_SESSION['id_rol'];

    if (!in_array($rol_usuario, $roles_permitidos, true)) {
        header('Location: /sysinversioneschcomputer/public/dashboard.php?error=acceso_denegado');
        exit;
    }
}
