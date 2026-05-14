<?php 
if (!isset($ruta_base)) {
    $ruta_base = '../'; 
}

$ruta_logout = $ruta_base . 'logout.php';
$username = $_SESSION['username'] ?? 'usuarios';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Botica 2026</title>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body { font-family: 'Inter', sans-serif; }

        /* ── TRANSICIÓN SUAVE DEL SIDEBAR ── */
        .main-sidebar,
        .main-sidebar .sidebar {
            transition: width .3s ease-in-out, margin-left .3s ease-in-out !important;
        }
        .content-wrapper,
        .main-footer {
            transition: margin-left .3s ease-in-out !important;
        }

        /* ── SIDEBAR COLAPSADO: ocultar texto, mantener íconos centrados ── */
        .sidebar-collapse .main-sidebar {
            width: 4.6rem !important;
        }
        .sidebar-collapse .main-sidebar .brand-text,
        .sidebar-collapse .main-sidebar .nav-sidebar .nav-link p,
        .sidebar-collapse .main-sidebar .user-panel .info,
        .sidebar-collapse .main-sidebar .nav-sidebar .nav-link .right {
            display: none !important;
            opacity: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
        }
        .sidebar-collapse .main-sidebar .nav-sidebar .nav-link {
            padding: 8px !important;
            display: flex !important;
            justify-content: center !important;
        }
        .sidebar-collapse .main-sidebar .nav-icon {
            margin-right: 0 !important;
        }
        .sidebar-collapse .main-sidebar .brand-link {
            justify-content: center !important;
            padding: 10px !important;
        }
        .sidebar-collapse .main-sidebar .brand-link img {
            margin: 0 auto !important;
        }
        .sidebar-collapse .main-sidebar .user-panel {
            justify-content: center !important;
        }

        /* ── CONTENT SE DESPLAZA CORRECTAMENTE ── */
        .sidebar-collapse .content-wrapper,
        .sidebar-collapse .main-footer {
            margin-left: 4.6rem !important;
        }

        /* ── NAVBAR ── */
        .main-header {
            border-bottom: 3px solid #1a7a4a;
            z-index: 1039;
        }
        .main-header .nav-link[data-widget="pushmenu"] {
            font-size: 1.1rem;
            color: #495057;
            transition: color .2s;
        }
        .main-header .nav-link[data-widget="pushmenu"]:hover {
            color: #1a7a4a;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
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
