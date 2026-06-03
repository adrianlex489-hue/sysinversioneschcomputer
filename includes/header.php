<?php 
if (!isset($ruta_base)) {
    $ruta_base = '../'; 
}

$ruta_logout = $ruta_base . 'logout.php';
$username = $_SESSION['username'] ?? 'usuarios';
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SysInversiones CH Computer</title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="/sysinversioneschcomputer/public/css/theme.css">

    <?php if (!empty($extra_css)): ?>
    <?= $extra_css ?>
    <?php endif; ?>

    <style>
        body { font-family: 'Inter', sans-serif; }

        /* ── TRANSICIÓN SUAVE DEL SIDEBAR ── */
        .main-sidebar, .main-sidebar .sidebar {
            transition: width .3s ease-in-out, margin-left .3s ease-in-out !important;
        }
        .content-wrapper, .main-footer {
            transition: margin-left .3s ease-in-out !important;
        }

        /* ── SIDEBAR COLAPSADO ── */
        .sidebar-collapse .main-sidebar { width: 4.6rem !important; }
        .sidebar-collapse .main-sidebar .brand-text,
        .sidebar-collapse .main-sidebar .nav-sidebar .nav-link p,
        .sidebar-collapse .main-sidebar .user-panel .info,
        .sidebar-collapse .main-sidebar .nav-sidebar .nav-link .right {
            display: none !important; opacity: 0 !important; width: 0 !important; overflow: hidden !important;
        }
        .sidebar-collapse .main-sidebar .nav-sidebar .nav-link {
            padding: 8px !important; display: flex !important; justify-content: center !important;
        }
        .sidebar-collapse .main-sidebar .nav-icon { margin-right: 0 !important; }
        .sidebar-collapse .main-sidebar .brand-link { justify-content: center !important; padding: 10px !important; }
        .sidebar-collapse .main-sidebar .brand-link img { margin: 0 auto !important; }
        .sidebar-collapse .main-sidebar .user-panel { justify-content: center !important; }
        .sidebar-collapse .content-wrapper,
        .sidebar-collapse .main-footer { margin-left: 4.6rem !important; }

        /* ── FIX MODALES ── */
        .modal { overflow-y: auto !important; }
        .modal-dialog { margin: 1.75rem auto !important; }
        .modal-backdrop { z-index: 1040 !important; }
        .modal { z-index: 1050 !important; }

        /* ── FIX SCROLL JUMP ── */
        html { overflow-y: scroll !important; }
        body.modal-open { overflow: hidden !important; padding-right: 0 !important; }

        /* ── FIX FOOTER ── */
        .content-wrapper { overflow-y: visible !important; height: auto !important; min-height: 0 !important; }
        .wrapper { display: flex; flex-direction: column; min-height: 100vh; }
        .main-footer { position: relative !important; bottom: auto !important; z-index: 100; }

        /* ── NAVBAR ── */
        .main-header { border-bottom: 3px solid #1a3a6b; z-index: 1039; }
        .main-header .nav-link[data-widget="pushmenu"] { font-size: 1.1rem; color: #495057; transition: color .2s; }
        .main-header .nav-link[data-widget="pushmenu"]:hover { color: #2563eb; }

        /* ── FORZAR TEMA AZUL ── */
        .bg-success, .badge-success { background-color: #1a5276 !important; }
        .btn-success { background-color: #1a5276 !important; border-color: #1a5276 !important; }
        .btn-success:hover { background-color: #2980b9 !important; border-color: #2980b9 !important; }
        a { color: #1a5276; }
        a:hover { color: #2980b9; }
    </style>

    <!-- Aplicar tema antes del render para evitar flash blanco -->
    <script>(function(){var t=localStorage.getItem('sys_theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
</head>

<body class="hold-transition sidebar-mini layout-fixed skin-blue-light">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
        </ul>
    </nav>
