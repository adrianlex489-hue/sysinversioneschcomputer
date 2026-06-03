<?php
// ============================================================
// conf/permisos.php | SysInversiones CH Computer
// Helper para verificar permisos granulares por usuario.
// Incluir DESPUÉS de database.php y verificar_acceso.php.
// ============================================================

/**
 * Catálogo de módulos del sistema con su etiqueta, ícono y grupo.
 * Clave = identificador interno usado en la tabla permisos_usuario.
 */
function catalogoModulos(): array {
    return [
        // Servicio Técnico
        'servicios'          => ['label' => 'Recepción de Equipos',  'icon' => 'fas fa-plus-circle',     'grupo' => 'Servicio Técnico'],
        'taller'             => ['label' => 'Taller Técnico',        'icon' => 'fas fa-tools',           'grupo' => 'Servicio Técnico'],
        'cobro_servicio'     => ['label' => 'Cobro de Servicios',    'icon' => 'fas fa-hand-holding-usd','grupo' => 'Servicio Técnico'],
        // Caja
        'caja'               => ['label' => 'Gestión de Caja',       'icon' => 'fas fa-cash-register',   'grupo' => 'Caja'],
        'historial_caja'     => ['label' => 'Historial de Caja',     'icon' => 'fas fa-history',         'grupo' => 'Caja'],
        // Ventas
        'ventas'             => ['label' => 'Nueva Venta',           'icon' => 'fas fa-shopping-cart',   'grupo' => 'Ventas'],
        'cobro_ventas'       => ['label' => 'Cobro de Créditos',     'icon' => 'fas fa-file-invoice-dollar','grupo' => 'Ventas'],
        'historial_ventas'   => ['label' => 'Historial de Ventas',   'icon' => 'fas fa-history',         'grupo' => 'Ventas'],
        // Compras
        'compras'            => ['label' => 'Nueva Compra',          'icon' => 'fas fa-truck-loading',   'grupo' => 'Compras'],
        'cobro_compras'      => ['label' => 'Pago de Créditos',      'icon' => 'fas fa-file-invoice',    'grupo' => 'Compras'],
        'historial_compras'  => ['label' => 'Historial de Compras',  'icon' => 'fas fa-history',         'grupo' => 'Compras'],
        // Inventario
        'inventario'         => ['label' => 'Stock / Inventario',    'icon' => 'fas fa-boxes',           'grupo' => 'Inventario'],
        // Catálogos
        'productos'          => ['label' => 'Productos',             'icon' => 'fas fa-laptop',          'grupo' => 'Catálogos'],
        'categorias'         => ['label' => 'Categorías',            'icon' => 'fas fa-tags',            'grupo' => 'Catálogos'],
        'catalogo_servicios' => ['label' => 'Servicios Técnicos',    'icon' => 'fas fa-wrench',          'grupo' => 'Catálogos'],
        // Personas
        'clientes'           => ['label' => 'Clientes',              'icon' => 'fas fa-users',           'grupo' => 'Personas'],
        'proveedores'        => ['label' => 'Proveedores',           'icon' => 'fas fa-truck',           'grupo' => 'Personas'],
        // Empresa
        'empresa'            => ['label' => 'Mi Empresa',            'icon' => 'fas fa-building',        'grupo' => 'Empresa'],
    ];
}

/**
 * Carga todos los permisos de un usuario desde la BD.
 * Retorna array asociativo: ['ventas' => true, 'compras' => false, ...]
 * Si el usuario no tiene ningún permiso configurado, se asume acceso total (compatibilidad).
 */
function cargarPermisos(PDO $pdo, int $id_usuario): array {
    static $cache_permisos = [];
    if (isset($cache_permisos[$id_usuario])) {
        return $cache_permisos[$id_usuario];
    }

    // Acceso total por defecto (tabla no existe o sin permisos configurados)
    $acceso_total = [];
    foreach (array_keys(catalogoModulos()) as $mod) {
        $acceso_total[$mod] = true;
    }

    try {
        // Verificar primero si la tabla existe
        $check = $pdo->query("SHOW TABLES LIKE 'permisos_usuario'")->fetchColumn();
        if (!$check) {
            $cache_permisos[$id_usuario] = $acceso_total;
            return $acceso_total;
        }

        $st = $pdo->prepare("SELECT modulo, permitido FROM permisos_usuario WHERE id_usuario = ?");
        $st->execute([$id_usuario]);
        $rows = $st->fetchAll(PDO::FETCH_KEY_PAIR);

        // Sin permisos configurados → acceso total
        if (empty($rows)) {
            $cache_permisos[$id_usuario] = $acceso_total;
            return $acceso_total;
        }

        $permisos = [];
        foreach (array_keys(catalogoModulos()) as $mod) {
            // FETCH_KEY_PAIR devuelve strings — usar intval para evitar (bool)'0' === true
            $permisos[$mod] = isset($rows[$mod]) ? (intval($rows[$mod]) === 1) : true;
        }
        
        $cache_permisos[$id_usuario] = $permisos;
        return $permisos;

    } catch (PDOException $e) {
        return $acceso_total;
    }
}

/**
 * Verifica si el usuario actual tiene permiso para un módulo específico.
 * El Administrador siempre tiene acceso total, sin importar los permisos configurados.
 * Redirige al dashboard si no tiene permiso.
 */
function verificarPermiso(PDO $pdo, string $modulo): void {
    $id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
    $id_rol     = (int)($_SESSION['id_rol']     ?? 0);

    // Administrador siempre tiene acceso total
    if ($id_rol === 1) return;

    $permisos = cargarPermisos($pdo, $id_usuario);

    if (empty($permisos[$modulo])) {
        header('Location: /sysinversioneschcomputer/public/dashboard.php?error=sin_permiso&modulo=' . urlencode($modulo));
        exit;
    }
}

/**
 * Helper para verificar permiso en UI (sidebar, dashboard, etc).
 */
function checkP(string $modulo, bool $es_admin, array $permisos): bool {
    if ($es_admin) return true;
    return isset($permisos[$modulo]) && $permisos[$modulo] === true;
}

