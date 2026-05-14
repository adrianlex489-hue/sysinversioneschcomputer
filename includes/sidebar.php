<?php
// includes/sidebar.php | Botica 2026
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
    <a href="/botica-2026/public/dashboard.php" class="brand-link" style="display:flex;align-items:center;padding:10px 14px;gap:10px;">
        <img src="/botica-2026/public/assets/img/logo.jpg"
             alt="Botica 2026"
             style="height:38px;width:38px;object-fit:cover;border-radius:8px;border:2px solid rgba(255,255,255,.25);flex-shrink:0;">
        <span class="brand-text font-weight-bold" style="color:#fff;font-size:.95rem;letter-spacing:.5px;line-height:1.2;">
            BOTICA<br><span style="font-weight:300;font-size:.78rem;opacity:.8;letter-spacing:1px;">2026</span>
        </span>
    </a>

    <div class="sidebar">
        <!-- Panel de usuario -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <div style="width:35px;height:35px;background:#27ae60;border-radius:50%;display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-user-nurse text-white" style="font-size:.9rem;"></i>
                </div>
            </div>
            <div class="info">
                <a href="#" class="d-block font-weight-bold">
                    <?= htmlspecialchars($_SESSION['nombre_completo'] ?? 'Usuario') ?>
                </a>
                <small style="color:#a5d6a7;font-size:.75rem;">
                    <?php echo $rol_id == 1 ? 'Administrador' : ($rol_id == 2 ? 'Cajero' : 'Trabajador'); ?>
                </small>
            </div>
        </div>

        <!-- Menú -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="/botica-2026/public/dashboard.php" class="nav-link <?= esActivo('dashboard.php', $url_actual) ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <!-- Caja -->
                <?php if (checkP('caja', $es_administrador, $permisos)): ?>
                <li class="nav-item">
                    <a href="/botica-2026/modules/caja/caja.php" class="nav-link <?= esActivo('/caja/caja.php', $url_actual) ?>">
                        <i class="nav-icon fas fa-cash-register"></i>
                        <p>Caja</p>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Historial Caja (desplegable) -->
                <?php if (checkP('historial_caja', $es_administrador, $permisos)): ?>
                <li class="nav-item has-treeview <?= menuAbierto(['/caja/historial/'], $url_actual) ?>">
                    <a href="#" class="nav-link <?= padreActivo(['/caja/historial/'], $url_actual) ?>">
                        <i class="nav-icon fas fa-history"></i>
                        <p>Historial Caja <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="/botica-2026/modules/caja/historial/historial_caja.php" class="nav-link <?= esActivo('historial_caja.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Ver Historial</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Nueva Venta -->
                <?php if (checkP('ventas', $es_administrador, $permisos)): ?>
                <li class="nav-item">
                    <a href="/botica-2026/modules/transacciones/ventas.php" class="nav-link <?= esActivo('/transacciones/ventas.php', $url_actual) ?>">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <p>Nueva Venta</p>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Nueva Compra -->
                <?php if (checkP('compras', $es_administrador, $permisos)): ?>
                <li class="nav-item">
                    <a href="/botica-2026/modules/transacciones/compras.php" class="nav-link <?= esActivo('/transacciones/compras.php', $url_actual) ?>">
                        <i class="nav-icon fas fa-truck-loading"></i>
                        <p>Nueva Compra</p>
                    </a>
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
                            <a href="/botica-2026/modules/transacciones/historial/historial_ventas.php" class="nav-link <?= esActivo('historial_ventas.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Historial Ventas</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (checkP('historial_compras', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/botica-2026/modules/transacciones/historial/historial_compras.php" class="nav-link <?= esActivo('historial_compras.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Historial Compras</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Inventario (desplegable) -->
                <?php if (checkP('inventario', $es_administrador, $permisos) || checkP('lotes', $es_administrador, $permisos)): ?>
                <li class="nav-item has-treeview <?= menuAbierto(['/Inventario/', '/Lotes/'], $url_actual) ?>">
                    <a href="#" class="nav-link <?= padreActivo(['/Inventario/', '/Lotes/'], $url_actual) ?>">
                        <i class="nav-icon fas fa-boxes"></i>
                        <p>Inventario <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (checkP('inventario', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/botica-2026/modules/Inventario/inventario.php" class="nav-link <?= esActivo('inventario.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Stock General</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (checkP('lotes', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/botica-2026/modules/Lotes/lotes.php" class="nav-link <?= esActivo('lotes.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Vencimientos</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Catálogos (desplegable) -->
                <?php if (checkP('productos', $es_administrador, $permisos) || checkP('categorias', $es_administrador, $permisos) || checkP('unidades', $es_administrador, $permisos)): ?>
                <li class="nav-item has-treeview <?= menuAbierto(['/catalogos/'], $url_actual) ?>">
                    <a href="#" class="nav-link <?= padreActivo(['/catalogos/'], $url_actual) ?>">
                        <i class="nav-icon fas fa-capsules"></i>
                        <p>Catálogos <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (checkP('productos', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/botica-2026/modules/catalogos/productos.php" class="nav-link <?= esActivo('productos.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Productos</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (checkP('categorias', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/botica-2026/modules/catalogos/categorias.php" class="nav-link <?= esActivo('categorias.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Categorías</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (checkP('unidades', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/botica-2026/modules/catalogos/unidades.php" class="nav-link <?= esActivo('unidades.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Unidades</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Mi Empresa -->
                <?php if (checkP('empresa', $es_administrador, $permisos)): ?>
                <li class="nav-item">
                    <a href="/botica-2026/modules/Comprobantes/empresa.php" class="nav-link <?= esActivo('empresa.php', $url_actual) ?>">
                        <i class="nav-icon fas fa-building"></i>
                        <p>Mi Empresa</p>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Gestión Administrativa (desplegable) — solo admin -->
                <?php if ($es_administrador || checkP('clientes', $es_administrador, $permisos) || checkP('proveedores', $es_administrador, $permisos)): ?>
                <li class="nav-item has-treeview <?= menuAbierto(['/personas/'], $url_actual) ?>">
                    <a href="#" class="nav-link <?= padreActivo(['/personas/'], $url_actual) ?>">
                        <i class="nav-icon fas fa-users-cog"></i>
                        <p>Gestión Administrativa <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (checkP('clientes', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/botica-2026/modules/personas/clientes.php" class="nav-link <?= esActivo('clientes.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Clientes</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (checkP('proveedores', $es_administrador, $permisos)): ?>
                        <li class="nav-item">
                            <a href="/botica-2026/modules/personas/proveedores.php" class="nav-link <?= esActivo('proveedores.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Proveedores</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($es_administrador): ?>
                        <li class="nav-item">
                            <a href="/botica-2026/modules/personas/usuarios.php" class="nav-link <?= esActivo('usuarios.php', $url_actual) ?>">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Usuarios y Accesos</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

            </ul>
        </nav>
    </div>
</aside>

<style>
    /* ── SIDEBAR — Diseño Premium ── */
    .main-sidebar,
    .main-sidebar .sidebar {
        background: linear-gradient(180deg, #0d3b26 0%, #1a5c38 100%) !important;
    }
    .main-sidebar {
        position: fixed !important;
        top: 0; left: 0; bottom: 0;
        width: 250px !important;
        height: 100vh !important;
        overflow-y: auto; overflow-x: hidden;
        z-index: 1038;
        scrollbar-width: none;
        -ms-overflow-style: none;
        transition: width .3s ease-in-out, margin-left .3s ease-in-out !important;
    }
    .main-sidebar::-webkit-scrollbar { display: none; }

    .brand-link {
        background: rgba(0,0,0,0.25) !important;
        border-bottom: 1px solid rgba(255,255,255,0.1) !important;
    }

    .user-panel { border-bottom: 1px solid rgba(255,255,255,0.1) !important; }
    .user-panel .info a  { color: #e8f5e9 !important; font-weight: 600; }
    .user-panel .info small { color: #a5d6a7 !important; }

    /* Ítems del menú */
    .main-sidebar .nav-sidebar .nav-link {
        color: #c8e6c9 !important;
        border-radius: 6px;
        margin: 2px 8px;
        transition: all .2s;
        padding: 8px 10px;
    }
    .main-sidebar .nav-sidebar .nav-link:hover,
    .main-sidebar .nav-sidebar .nav-link.active {
        background: #27ae60 !important;
        color: #fff !important;
    }

    /* Submenús */
    .main-sidebar .nav-sidebar .nav-treeview .nav-link {
        background: rgba(0,0,0,0.15) !important;
        color: #a5d6a7 !important;
        font-size: 0.88rem;
        padding-left: 2.5rem !important;
        margin: 1px 8px;
    }
    .main-sidebar .nav-sidebar .nav-treeview .nav-link:hover {
        background: rgba(39,174,96,0.4) !important;
        color: #fff !important;
    }
    .main-sidebar .nav-sidebar .nav-treeview .nav-link.active {
        background: #27ae60 !important;
        color: #fff !important;
        font-weight: 700;
        box-shadow: inset 3px 0 0 #fff;
    }
    .main-sidebar .nav-sidebar .nav-item.has-treeview > .nav-link.active {
        background: rgba(39,174,96,0.25) !important;
        color: #fff !important;
    }

    /* Íconos */
    .main-sidebar .nav-icon {
        width: 20px;
        color: #81c784 !important;
        margin-right: 8px;
    }
    .main-sidebar .nav-link:hover .nav-icon,
    .main-sidebar .nav-link.active .nav-icon { color: #fff !important; }

    .main-sidebar .nav-sidebar .nav-link .right { color: #a5d6a7 !important; }
    .main-sidebar .nav-sidebar .nav-link p { color: inherit; font-weight: 600; font-size: .9rem; }
</style>
