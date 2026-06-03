<?php
// includes/sidebar.php | SysInversiones CH Computer
// Menú lateral dinámico basado en roles y permisos granulares.

require_once dirname(__DIR__) . '/conf/permisos.php';

$id_usuario_actual = $_SESSION['id_usuario'] ?? 0;
$rol_id            = $_SESSION['id_rol']      ?? 0;
$es_administrador  = ($rol_id == 1);

$permisos = ($id_usuario_actual && isset($pdo)) ? cargarPermisos($pdo, (int)$id_usuario_actual) : [];

$url_actual = $_SERVER['REQUEST_URI'] ?? '';

if (!function_exists('esActivo')) {
    function esActivo(string $ruta, string $url): string {
        $url_limpia = strtok($url, '?');
        return (substr($url_limpia, -strlen($ruta)) === $ruta || strpos($url_limpia, $ruta) !== false) ? 'active' : '';
    }
}
if (!function_exists('menuAbierto')) {
    function menuAbierto(array $rutas, string $url): string {
        $url_limpia = strtok($url, '?');
        foreach ($rutas as $ruta) {
            if (strpos($url_limpia, $ruta) !== false) return 'menu-open';
        }
        return '';
    }
}
if (!function_exists('padreActivo')) {
    function padreActivo(array $rutas, string $url): string {
        $url_limpia = strtok($url, '?');
        foreach ($rutas as $ruta) {
            if (strpos($url_limpia, $ruta) !== false) return 'active';
        }
        return '';
    }
}
?>

<aside class="main-sidebar elevation-4">
    <!-- Logo -->
    <a href="/sysinversioneschcomputer/public/dashboard.php" class="brand-link" style="display:flex;align-items:center;padding:10px 14px;gap:10px;">
        <img src="/sysinversioneschcomputer/Logo/logo.jpg"
             alt="SysInversiones CH Computer"
             style="height:38px;width:38px;object-fit:cover;border-radius:8px;border:2px solid rgba(255,255,255,.25);flex-shrink:0;">
        <span class="brand-text font-weight-bold" style="color:#fff;font-size:.88rem;letter-spacing:.3px;line-height:1.3;">
            SysInversiones<br><span style="font-weight:300;font-size:.75rem;opacity:.8;letter-spacing:.5px;">CH Computer</span>
        </span>
    </a>

    <div class="sidebar">
        <!-- Panel de usuario -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex align-items-center">
            <div class="image" style="flex-shrink:0;">
                <div style="width:32px;height:32px;background:#2563eb;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-user-tie text-white" style="font-size:.8rem;"></i>
                </div>
            </div>
            <div class="info" style="min-width:0;overflow:hidden;padding-left:8px;">
                <a href="#" class="d-block font-weight-bold" title="<?= htmlspecialchars($_SESSION['nombre_completo'] ?? 'Usuario') ?>" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:.78rem;line-height:1.3;">
                    <?= htmlspecialchars($_SESSION['nombre_completo'] ?? 'Usuario') ?>
                </a>
                <small style="color:#93c5fd;font-size:.72rem;">
                    <?php
                    $nombres_rol = [1 => 'Administrador', 2 => 'Asesor Comercial', 3 => 'Técnico'];
                    echo $nombres_rol[$rol_id] ?? 'Usuario';
                    ?>
                </small>
            </div>
        </div>

        <!-- Menú -->
        <nav class="mt-1">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="/sysinversioneschcomputer/public/dashboard.php" class="nav-link <?= esActivo('dashboard.php', $url_actual) ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <!-- Servicio Técnico (desplegable) -->
                <?php if (checkP('servicios', $es_administrador, $permisos) || checkP('taller', $es_administrador, $permisos) || checkP('cobro_servicio', $es_administrador, $permisos) || $es_administrador): ?>
                <li class="nav-item has-treeview <?= menuAbierto(['/servicios/'], $url_actual) ?>">
                    <a href="#" class="nav-link <?= padreActivo(['/servicios/'], $url_actual) ?>">
                        <i class="nav-icon fas fa-tools"></i>
                        <p>Servicio Técnico <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (checkP('servicios', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/servicios/recepcion.php" class="nav-link <?= esActivo('recepcion.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Recepción de Equipos</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (checkP('taller', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/servicios/taller.php" class="nav-link <?= esActivo('taller.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Taller Técnico</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (checkP('cobro_servicio', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/servicios/cobro_servicio.php" class="nav-link <?= esActivo('cobro_servicio.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Cobro de Servicios</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Caja -->
                <?php if (checkP('caja', $es_administrador, $permisos) || checkP('historial_caja', $es_administrador, $permisos)): ?>
                <li class="nav-item has-treeview <?= menuAbierto(['/Caja/'], $url_actual) ?>">
                    <a href="#" class="nav-link <?= padreActivo(['/Caja/'], $url_actual) ?>">
                        <i class="nav-icon fas fa-cash-register"></i>
                        <p>Caja <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (checkP('caja', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/Caja/caja.php" class="nav-link <?= esActivo('caja.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Gestión de Caja</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/Caja/resumen_caja.php" class="nav-link <?= esActivo('resumen_caja.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Resumen del Día</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/Caja/movimientos_caja.php" class="nav-link <?= esActivo('movimientos_caja.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Movimientos</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (checkP('historial_caja', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/Caja/historial_caja.php" class="nav-link <?= esActivo('historial_caja.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Historial de Cajas</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/Caja/reporte_cajas.php" class="nav-link <?= esActivo('reporte_cajas.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Reporte de Cajas</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Ventas (desplegable) -->
                <?php if (checkP('ventas', $es_administrador, $permisos) || checkP('cobro_ventas', $es_administrador, $permisos)): ?>
                <li class="nav-item has-treeview <?= menuAbierto(['/transacciones/ventas.php','/transacciones/cobro_ventas.php'], $url_actual) ?>">
                    <a href="#" class="nav-link <?= padreActivo(['/transacciones/ventas.php','/transacciones/cobro_ventas.php'], $url_actual) ?>">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <p>Ventas <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (checkP('ventas', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/transacciones/ventas.php" class="nav-link <?= esActivo('/transacciones/ventas.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Nueva Venta</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (checkP('cobro_ventas', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/transacciones/cobro_ventas.php" class="nav-link <?= esActivo('cobro_ventas.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Cobro de Créditos</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Compras (desplegable) -->
                <?php if (checkP('compras', $es_administrador, $permisos) || checkP('cobro_compras', $es_administrador, $permisos)): ?>
                <li class="nav-item has-treeview <?= menuAbierto(['/transacciones/compras.php','/transacciones/cobro_compras.php'], $url_actual) ?>">
                    <a href="#" class="nav-link <?= padreActivo(['/transacciones/compras.php','/transacciones/cobro_compras.php'], $url_actual) ?>">
                        <i class="nav-icon fas fa-truck-loading"></i>
                        <p>Compras <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (checkP('compras', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/transacciones/compras.php" class="nav-link <?= esActivo('/transacciones/compras.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Nueva Compra</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (checkP('cobro_compras', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/transacciones/cobro_compras.php" class="nav-link <?= esActivo('cobro_compras.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Pago de Créditos</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Movimientos (desplegable) -->
                <?php if (checkP('historial_ventas', $es_administrador, $permisos) || checkP('historial_compras', $es_administrador, $permisos)): ?>
                <li class="nav-item has-treeview <?= menuAbierto(['/transacciones/historial/'], $url_actual) ?>">
                    <a href="#" class="nav-link <?= padreActivo(['/transacciones/historial/'], $url_actual) ?>">
                        <i class="nav-icon fas fa-exchange-alt"></i>
                        <p>Movimientos <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (checkP('historial_ventas', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/transacciones/historial/historial_ventas.php" class="nav-link <?= esActivo('historial_ventas.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Historial Ventas</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (checkP('historial_compras', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/transacciones/historial/historial_compras.php" class="nav-link <?= esActivo('historial_compras.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Historial Compras</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Inventario -->
                <?php if (checkP('inventario', $es_administrador, $permisos)): ?>
                <li class="nav-item">
                    <a href="/sysinversioneschcomputer/modules/inventario/inventario.php" class="nav-link <?= esActivo('inventario.php', $url_actual) ?>">
                        <i class="nav-icon fas fa-boxes"></i>
                        <p>Inventario</p>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Catálogos (desplegable) -->
                <?php if (checkP('productos', $es_administrador, $permisos) || checkP('categorias', $es_administrador, $permisos) || checkP('catalogo_servicios', $es_administrador, $permisos)): ?>
                <li class="nav-item has-treeview <?= menuAbierto(['/catalogos/'], $url_actual) ?>">
                    <a href="#" class="nav-link <?= padreActivo(['/catalogos/'], $url_actual) ?>">
                        <i class="nav-icon fas fa-laptop"></i>
                        <p>Catálogos <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (checkP('productos', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/catalogos/productos.php" class="nav-link <?= esActivo('productos.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Productos</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (checkP('categorias', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/catalogos/categorias.php" class="nav-link <?= esActivo('categorias.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Categorías</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (checkP('catalogo_servicios', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/catalogos/catalogo_servicios.php" class="nav-link <?= esActivo('catalogo_servicios.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Servicios Técnicos</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Mi Empresa -->
                <?php if (checkP('empresa', $es_administrador, $permisos)): ?>
                <li class="nav-item">
                    <a href="/sysinversioneschcomputer/modules/configuracion_empresa/empresa.php" class="nav-link <?= esActivo('empresa.php', $url_actual) ?>">
                        <i class="nav-icon fas fa-building"></i>
                        <p>Mi Empresa</p>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Gestión Administrativa (desplegable) -->
                <?php if ($es_administrador || checkP('clientes', $es_administrador, $permisos) || checkP('proveedores', $es_administrador, $permisos)): ?>
                <li class="nav-item has-treeview <?= menuAbierto(['/personas/'], $url_actual) ?>">
                    <a href="#" class="nav-link <?= padreActivo(['/personas/'], $url_actual) ?>">
                        <i class="nav-icon fas fa-users-cog"></i>
                        <p>Gestión Administrativa <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (checkP('clientes', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/personas/clientes.php" class="nav-link <?= esActivo('clientes.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Clientes</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (checkP('proveedores', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/personas/proveedores.php" class="nav-link <?= esActivo('proveedores.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Proveedores</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($es_administrador): ?>
                        <li class="nav-item">
                            <a href="/sysinversioneschcomputer/modules/personas/usuarios.php" class="nav-link <?= esActivo('usuarios.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Usuarios y Accesos</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Auditoría — solo Administrador -->
                <?php if ($es_administrador): ?>
                <li class="nav-item">
                    <a href="/sysinversioneschcomputer/modules/auditoria/auditoria.php" class="nav-link <?= esActivo('auditoria.php', $url_actual) ?>">
                        <i class="nav-icon fas fa-shield-alt"></i>
                        <p>Auditoría</p>
                    </a>
                </li>
                <?php endif; ?>

            </ul>
        </nav>
    </div>
</aside>

<style>
    /* ── SIDEBAR — Diseño Tech ── */
    .main-sidebar,
    .main-sidebar .sidebar {
        background: linear-gradient(180deg, #0f172a 0%, #1e3a8a 100%) !important;
    }
    .main-sidebar {
        position: fixed !important;
        top: 0; left: 0; bottom: 0;
        width: 250px !important;
        height: 100vh !important;
        overflow-y: auto; overflow-x: hidden;
        z-index: 1035;
        scrollbar-width: none;
        -ms-overflow-style: none;
        transition: width .3s ease-in-out, margin-left .3s ease-in-out !important;
    }
    .main-sidebar::-webkit-scrollbar { display: none; }

    .brand-link {
        background: rgba(0,0,0,0.3) !important;
        border-bottom: 1px solid rgba(255,255,255,0.08) !important;
    }

    .user-panel { border-bottom: 1px solid rgba(255,255,255,0.08) !important; }
    .user-panel .info { min-width: 0; overflow: hidden; }
    .user-panel .info a  { color: #e0f2fe !important; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
    .user-panel .info small { color: #93c5fd !important; }

    /* Ítems del menú */
    .main-sidebar .nav-sidebar .nav-link {
        color: #bfdbfe !important;
        border-radius: 6px;
        margin: 2px 8px;
        transition: all .2s;
        padding: 8px 10px;
    }
    .main-sidebar .nav-sidebar .nav-link:hover,
    .main-sidebar .nav-sidebar .nav-link.active {
        background: #2563eb !important;
        color: #fff !important;
    }

    /* Submenús */
    .main-sidebar .nav-sidebar .nav-treeview .nav-link {
        background: rgba(0,0,0,0.2) !important;
        color: #93c5fd !important;
        font-size: 0.88rem;
        padding-left: 2.5rem !important;
        margin: 1px 8px;
    }
    .main-sidebar .nav-sidebar .nav-treeview .nav-link:hover {
        background: rgba(37,99,235,0.4) !important;
        color: #fff !important;
    }
    .main-sidebar .nav-sidebar .nav-treeview .nav-link.active {
        background: #2563eb !important;
        color: #fff !important;
        font-weight: 700;
        box-shadow: inset 3px 0 0 #93c5fd;
    }
    .main-sidebar .nav-sidebar .nav-item.has-treeview > .nav-link.active {
        background: rgba(37,99,235,0.25) !important;
        color: #fff !important;
    }

    /* Íconos */
    .main-sidebar .nav-icon {
        width: 20px;
        color: #60a5fa !important;
        margin-right: 8px;
    }
    .main-sidebar .nav-link:hover .nav-icon,
    .main-sidebar .nav-link.active .nav-icon { color: #fff !important; }

    .main-sidebar .nav-sidebar .nav-link .right { color: #93c5fd !important; }
    .main-sidebar .nav-sidebar .nav-link p { color: inherit; font-weight: 600; font-size: .9rem; }

    /* Ocultar solo cuando está colapsado */
    .sidebar-collapse .main-sidebar .nav-sidebar .nav-link p,
    .sidebar-collapse .main-sidebar .nav-sidebar .nav-link .right,
    .sidebar-collapse .main-sidebar .user-panel .info,
    .sidebar-collapse .main-sidebar .brand-text { display: none !important; }
</style> 