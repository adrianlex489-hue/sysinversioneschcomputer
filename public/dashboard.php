<?php

require_once '../conf/verificar_acceso.php';
require_once '../conf/database.php';

$conexion = $pdo;

// ── Sonido de bienvenida — solo una vez por sesión ───────────────────────────
$reproducir_bienvenida = false;
if (empty($_SESSION['bienvenida_reproducida'])) {
    $reproducir_bienvenida = true;
    $_SESSION['bienvenida_reproducida'] = true;
}

// ── Valores por defecto ──────────────────────────────────────────────────────
$total_usuarios   = 0;
$total_productos  = 0;
$total_clientes   = 0;
$total_ventas     = 0;
$total_ganancias  = 0;
$total_proveedores= 0;
$productos_por_vencer = 0;
$productos_stock_bajo = 0;
$ultimas_ventas   = [];
$meses_labels     = ['Ene','Feb','Mar','Abr','May','Jun'];
$datos_ventas     = [0,0,0,0,0,0];
$nombres_stock    = ['Sin datos'];
$cantidades_stock = [1];

function ejecutarConsulta($conexion, $sql, $params = []) {
    try {
        $stmt = $conexion->prepare($sql);
        $stmt->execute($params);
        $resultado = $stmt->fetchColumn();
        return ($resultado === false || $resultado === null) ? 0 : $resultado;
    } catch (PDOException $e) {
        return 0;
    }
}

try {
    $total_usuarios    = ejecutarConsulta($conexion, "SELECT COUNT(*) FROM usuarios WHERE estado = 1");
    $total_productos   = ejecutarConsulta($conexion, "SELECT COUNT(*) FROM productos WHERE estado = 1");
    $total_clientes    = ejecutarConsulta($conexion, "SELECT COUNT(*) FROM clientes WHERE estado_cliente = 1");
    $total_proveedores = ejecutarConsulta($conexion, "SELECT COUNT(*) FROM proveedores WHERE estado = 1");

    // Productos por vencer en los próximos 90 días
    $productos_por_vencer = ejecutarConsulta($conexion,
        "SELECT COUNT(DISTINCT id_producto) FROM lotes
         WHERE fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
           AND stock_actual > 0");

    // Productos con stock bajo el mínimo
    $productos_stock_bajo = ejecutarConsulta($conexion,
        "SELECT COUNT(*) FROM productos WHERE stock <= stock_minimo AND estado = 1");

    if ($es_administrador) {
        $total_ventas    = ejecutarConsulta($conexion, "SELECT COUNT(*) FROM ventas WHERE estado != 'anulado'");
        $total_ganancias = ejecutarConsulta($conexion, "SELECT COALESCE(SUM(total),0) FROM ventas WHERE estado != 'anulado'");

        // Últimas 5 ventas
        $stmt_uv = $conexion->query("
            SELECT v.id_venta,
                   CONCAT(COALESCE(c.nombres,''),' ',COALESCE(c.apellido_paterno,'')) AS cliente_nombre,
                   v.total,
                   v.tipo_comprobante,
                   v.estado,
                   DATE_FORMAT(v.fecha,'%d/%m/%Y %H:%i') AS fecha_formateada
            FROM ventas v
            LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
            ORDER BY v.fecha DESC LIMIT 5");
        $ultimas_ventas = $stmt_uv->fetchAll(PDO::FETCH_ASSOC);

        // Ventas últimos 6 meses
        $stmt_vm = $conexion->query("
            SELECT MONTH(fecha) AS mes_numero, SUM(total) AS total_ventas
            FROM ventas
            WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
              AND estado != 'anulado'
            GROUP BY MONTH(fecha) ORDER BY mes_numero ASC");
        $ventas_raw = $stmt_vm->fetchAll(PDO::FETCH_ASSOC);

        $meses_esp = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        $meses_labels = [];
        $datos_ventas  = [];
        $mes_actual = (int)date('n');
        for ($i = 5; $i >= 0; $i--) {
            $m = ($mes_actual - $i + 12) % 12;
            if ($m === 0) $m = 12;
            $meses_labels[] = $meses_esp[$m];
            $encontrado = 0;
            foreach ($ventas_raw as $fila) {
                if ((int)$fila['mes_numero'] === $m) { $encontrado = (float)$fila['total_ventas']; break; }
            }
            $datos_ventas[] = $encontrado;
        }
    }

    // Stock por categoría
    $stmt_sc = $conexion->query("
        SELECT cat.nombre_categoria, SUM(l.stock_actual) AS total_stock
        FROM lotes l
        INNER JOIN productos p ON l.id_producto = p.id_producto
        INNER JOIN categorias cat ON p.id_categoria = cat.id_categoria
        WHERE p.estado = 1 AND l.stock_actual > 0
        GROUP BY cat.nombre_categoria ORDER BY total_stock DESC");
    $stock_raw = $stmt_sc->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($stock_raw)) {
        $nombres_stock    = array_column($stock_raw, 'nombre_categoria');
        $cantidades_stock = array_map('intval', array_column($stock_raw, 'total_stock'));
    }

    // Lotes próximos a vencer (tabla alerta)
    $stmt_lv = $conexion->query("
        SELECT p.nombre_producto, l.codigo_lote, l.fecha_vencimiento, l.stock_actual,
               DATEDIFF(l.fecha_vencimiento, CURDATE()) AS dias_restantes
        FROM lotes l
        INNER JOIN productos p ON l.id_producto = p.id_producto
        WHERE l.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
          AND l.stock_actual > 0
        ORDER BY l.fecha_vencimiento ASC LIMIT 5");
    $lotes_por_vencer = $stmt_lv->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log('Error dashboard: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | Botica 2026</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <style>
        :root {
            --botica-verde:      #1a7a4a;
            --botica-verde-claro:#27ae60;
            --botica-celeste:    #2980b9;
            --botica-rojo:       #e74c3c;
            --botica-naranja:    #e67e22;
            --botica-gris:       #f4f6f9;
        }
        body { font-family: 'Inter', sans-serif; background: var(--botica-gris); }

        /* ── NAVBAR ── */
        .main-header { border-bottom: 3px solid var(--botica-verde); }
        .navbar-brand-text { color: var(--botica-verde) !important; font-weight: 700; }

        /* ── TARJETAS KPI ── */
        .kpi-card { border-radius: 12px; padding: 20px; color: #fff; position: relative; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.12); transition: transform .2s; }
        .kpi-card:hover { transform: translateY(-3px); }
        .kpi-card .kpi-icon { position: absolute; right: 15px; top: 15px; font-size: 2.8rem; opacity: .25; }
        .kpi-card .kpi-value { font-size: 2rem; font-weight: 700; }
        .kpi-card .kpi-label { font-size: .85rem; opacity: .9; margin-top: 4px; }
        .kpi-card .kpi-footer { margin-top: 12px; font-size: .8rem; opacity: .85; }
        .kpi-card .kpi-footer a { color: #fff; text-decoration: underline; }
        .kpi-verde   { background: linear-gradient(135deg, #1a7a4a, #27ae60); }
        .kpi-celeste { background: linear-gradient(135deg, #1a5276, #2980b9); }
        .kpi-naranja { background: linear-gradient(135deg, #a04000, #e67e22); }
        .kpi-rojo    { background: linear-gradient(135deg, #922b21, #e74c3c); }
        .kpi-morado  { background: linear-gradient(135deg, #6c3483, #9b59b6); }
        .kpi-teal    { background: linear-gradient(135deg, #0e6655, #1abc9c); }

        /* ── ALERTAS FARMACIA ── */
        .alerta-vencer { background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 8px; padding: 12px 16px; }
        .alerta-stock  { background: #f8d7da; border-left: 4px solid #dc3545; border-radius: 8px; padding: 12px 16px; }

        /* ── CARDS ── */
        .card { border-radius: 10px; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
        .card-header { border-radius: 10px 10px 0 0 !important; }
        .card-header-botica { background: linear-gradient(90deg, #1a7a4a, #27ae60); color: #fff; }
        .card-header-alerta { background: linear-gradient(90deg, #922b21, #e74c3c); color: #fff; }
        .card-header-info   { background: linear-gradient(90deg, #1a5276, #2980b9); color: #fff; }

        /* ── BADGE VENCIMIENTO ── */
        .badge-vence-pronto { background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
        .badge-vence-critico { background: #f8d7da; color: #842029; border: 1px solid #dc3545; }

        /* ── ACCESOS RÁPIDOS ── */
        .acceso-rapido { border-radius: 10px; padding: 18px; text-align: center; color: #fff; cursor: pointer; transition: all .2s; text-decoration: none; display: block; }
        .acceso-rapido:hover { transform: scale(1.04); color: #fff; text-decoration: none; }
        .acceso-rapido i { font-size: 2rem; display: block; margin-bottom: 8px; }
        .acceso-rapido span { font-size: .85rem; font-weight: 600; }

        /* ── SIDEBAR TOGGLE ── */
        .main-sidebar,
        .main-sidebar .sidebar {
            transition: width .3s ease-in-out, margin-left .3s ease-in-out !important;
        }
        .content-wrapper, .main-footer {
            transition: margin-left .3s ease-in-out !important;
        }
        .sidebar-collapse .main-sidebar { width: 4.6rem !important; }
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
        .sidebar-collapse .main-sidebar .nav-icon { margin-right: 0 !important; }
        .sidebar-collapse .main-sidebar .brand-link {
            justify-content: center !important;
            padding: 10px !important;
        }
        .sidebar-collapse .main-sidebar .user-panel { justify-content: center !important; }
        .sidebar-collapse .content-wrapper,
        .sidebar-collapse .main-footer { margin-left: 4.6rem !important; }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- NAVBAR -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <span class="nav-link text-muted">
                    <i class="fas fa-calendar-alt mr-1"></i><?= date('d/m/Y') ?>
                </span>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <?php if ($productos_stock_bajo > 0): ?>
            <li class="nav-item">
                <a class="nav-link" href="/botica-2026/modules/Inventario/inventario.php" title="Productos con stock bajo">
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                    <span class="badge badge-warning navbar-badge"><?= $productos_stock_bajo ?></span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($productos_por_vencer > 0): ?>
            <li class="nav-item">
                <a class="nav-link" href="/botica-2026/modules/Lotes/lotes.php" title="Lotes por vencer">
                    <i class="fas fa-clock text-danger"></i>
                    <span class="badge badge-danger navbar-badge"><?= $productos_por_vencer ?></span>
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="fas fa-user-circle mr-1"></i>
                    <span class="d-none d-sm-inline"><?= htmlspecialchars($_SESSION['nombre_completo'] ?? 'Usuario') ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <span class="dropdown-item-text text-muted small">
                        <?= htmlspecialchars($_SESSION['nombre_completo'] ?? '') ?>
                    </span>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="dropdown-item text-danger">
                        <i class="fas fa-sign-out-alt mr-2"></i> Cerrar Sesión
                    </a>
                </div>
            </li>
        </ul>
    </nav>

    <!-- SIDEBAR -->
    <?php include '../includes/sidebar.php'; ?>

    <div class="content-wrapper">
        <!-- HEADER -->
        <div class="content-header" style="background:#fff;border-bottom:1px solid #e9ecef;">
            <div class="container-fluid">
                <div class="row align-items-center py-1">
                    <div class="col-sm-6">
                        <h4 class="m-0 font-weight-bold" style="color:#1a7a4a;display:flex;align-items:center;gap:10px;">
                            <img src="/botica-2026/public/assets/img/logo.jpg"
                                 alt="Botica 2026"
                                 style="height:36px;width:36px;object-fit:cover;border-radius:8px;border:2px solid #27ae60;">
                            Panel de Control — Botica 2026
                        </h4>
                        <small class="text-muted">Bienvenido, <?= htmlspecialchars($_SESSION['nombre_completo'] ?? 'Usuario') ?></small>
                    </div>
                    <div class="col-sm-6 text-right">
                        <ol class="breadcrumb float-sm-right mb-0" style="background:transparent;">
                            <li class="breadcrumb-item active"><i class="fas fa-home mr-1"></i>Dashboard</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="content" style="padding-top:20px;">
            <div class="container-fluid">

                <!-- ── ALERTAS FARMACIA ── -->
                <?php if ($productos_stock_bajo > 0 || $productos_por_vencer > 0): ?>
                <div class="row mb-3">
                    <?php if ($productos_stock_bajo > 0): ?>
                    <div class="col-md-6 mb-2">
                        <div class="alerta-stock d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger mr-3"></i>
                            <div>
                                <strong><?= $productos_stock_bajo ?> producto(s) con stock bajo el mínimo</strong><br>
                                <small><a href="/botica-2026/modules/Inventario/inventario.php">Ver inventario →</a></small>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($productos_por_vencer > 0): ?>
                    <div class="col-md-6 mb-2">
                        <div class="alerta-vencer d-flex align-items-center">
                            <i class="fas fa-clock fa-2x text-warning mr-3"></i>
                            <div>
                                <strong><?= $productos_por_vencer ?> lote(s) vencen en los próximos 90 días</strong><br>
                                <small><a href="/botica-2026/modules/Lotes/lotes.php">Ver lotes →</a></small>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ── KPIs ── -->
                <div class="row mb-4">
                    <?php if (checkP('productos', $es_administrador, $permisos)): ?>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="kpi-card kpi-verde">
                            <i class="fas fa-capsules kpi-icon"></i>
                            <div class="kpi-value"><?= $total_productos ?></div>
                            <div class="kpi-label">Productos activos</div>
                            <div class="kpi-footer"><a href="/botica-2026/modules/catalogos/productos.php">Ver catálogo →</a></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (checkP('clientes', $es_administrador, $permisos)): ?>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="kpi-card kpi-celeste">
                            <i class="fas fa-users kpi-icon"></i>
                            <div class="kpi-value"><?= $total_clientes ?></div>
                            <div class="kpi-label">Clientes registrados</div>
                            <div class="kpi-footer"><a href="/botica-2026/modules/personas/clientes.php">Ver clientes →</a></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (checkP('proveedores', $es_administrador, $permisos)): ?>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="kpi-card kpi-teal">
                            <i class="fas fa-truck kpi-icon"></i>
                            <div class="kpi-value"><?= $total_proveedores ?></div>
                            <div class="kpi-label">Proveedores activos</div>
                            <div class="kpi-footer"><a href="/botica-2026/modules/personas/proveedores.php">Ver proveedores →</a></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($es_administrador): ?>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="kpi-card kpi-naranja">
                            <i class="fas fa-receipt kpi-icon"></i>
                            <div class="kpi-value"><?= $total_ventas ?></div>
                            <div class="kpi-label">Ventas realizadas</div>
                            <div class="kpi-footer"><a href="/botica-2026/modules/transacciones/historial/historial_ventas.php">Ver historial →</a></div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="kpi-card kpi-verde" style="background:linear-gradient(135deg,#145a32,#1e8449);">
                            <i class="fas fa-soles-sign kpi-icon"></i>
                            <div class="kpi-value" style="font-size:1.4rem;">S/. <?= number_format($total_ganancias, 0) ?></div>
                            <div class="kpi-label">Ingresos totales</div>
                            <div class="kpi-footer"><a href="/botica-2026/modules/transacciones/historial/historial_ventas.php">Ver detalle →</a></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (checkP('lotes', $es_administrador, $permisos)): ?>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="kpi-card kpi-rojo">
                            <i class="fas fa-clock kpi-icon"></i>
                            <div class="kpi-value"><?= $productos_por_vencer ?></div>
                            <div class="kpi-label">Lotes por vencer</div>
                            <div class="kpi-footer"><a href="/botica-2026/modules/Lotes/lotes.php">Ver lotes →</a></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ── ACCESOS RÁPIDOS ── -->
                <div class="row mb-4">
                    <div class="col-12 mb-2">
                        <h6 class="font-weight-bold text-muted"><i class="fas fa-bolt mr-1"></i> ACCESOS RÁPIDOS</h6>
                    </div>
                    <?php if (checkP('ventas', $es_administrador, $permisos)): ?>
                    <div class="col-lg-2 col-md-3 col-4 mb-2">
                        <a href="/botica-2026/modules/transacciones/ventas.php" class="acceso-rapido" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);">
                            <i class="fas fa-cash-register"></i><span>Nueva Venta</span>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if (checkP('compras', $es_administrador, $permisos)): ?>
                    <div class="col-lg-2 col-md-3 col-4 mb-2">
                        <a href="/botica-2026/modules/transacciones/compras.php" class="acceso-rapido" style="background:linear-gradient(135deg,#1a5276,#2980b9);">
                            <i class="fas fa-truck-loading"></i><span>Nueva Compra</span>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if (checkP('lotes', $es_administrador, $permisos)): ?>
                    <div class="col-lg-2 col-md-3 col-4 mb-2">
                        <a href="/botica-2026/modules/Lotes/lotes.php" class="acceso-rapido" style="background:linear-gradient(135deg,#922b21,#e74c3c);">
                            <i class="fas fa-calendar-times"></i><span>Vencimientos</span>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if (checkP('inventario', $es_administrador, $permisos)): ?>
                    <div class="col-lg-2 col-md-3 col-4 mb-2">
                        <a href="/botica-2026/modules/Inventario/inventario.php" class="acceso-rapido" style="background:linear-gradient(135deg,#0e6655,#1abc9c);">
                            <i class="fas fa-boxes"></i><span>Inventario</span>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if (checkP('productos', $es_administrador, $permisos)): ?>
                    <div class="col-lg-2 col-md-3 col-4 mb-2">
                        <a href="/botica-2026/modules/catalogos/productos.php" class="acceso-rapido" style="background:linear-gradient(135deg,#6c3483,#9b59b6);">
                            <i class="fas fa-capsules"></i><span>Productos</span>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if (checkP('clientes', $es_administrador, $permisos)): ?>
                    <div class="col-lg-2 col-md-3 col-4 mb-2">
                        <a href="/botica-2026/modules/personas/clientes.php" class="acceso-rapido" style="background:linear-gradient(135deg,#a04000,#e67e22);">
                            <i class="fas fa-user-plus"></i><span>Clientes</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ── GRÁFICOS ── -->
                <div class="row mb-4">
                    <?php if ($es_administrador): ?>
                    <div class="col-lg-7 mb-3">
                        <div class="card">
                            <div class="card-header card-header-botica">
                                <h6 class="m-0 font-weight-bold"><i class="fas fa-chart-line mr-2"></i>Ventas — Últimos 6 meses</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="lineChartVentas" style="height:240px;max-height:240px;"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="col-lg-<?= $es_administrador ? '5' : '12' ?> mb-3">
                        <div class="card">
                            <div class="card-header card-header-info">
                                <h6 class="m-0 font-weight-bold"><i class="fas fa-chart-pie mr-2"></i>Stock por Categoría</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="pieChartStock" style="height:240px;max-height:240px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── TABLAS INFERIORES ── -->
                <div class="row">
                    <?php if ($es_administrador): ?>
                    <div class="col-lg-7 mb-3">
                        <div class="card">
                            <div class="card-header card-header-botica">
                                <h6 class="m-0 font-weight-bold"><i class="fas fa-receipt mr-2"></i>Últimas 5 Ventas</h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Cliente</th>
                                            <th>Comprobante</th>
                                            <th class="text-right">Total</th>
                                            <th>Fecha</th>
                                            <th class="text-center">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($ultimas_ventas)): ?>
                                        <?php foreach($ultimas_ventas as $v):
                                            $badge = $v['estado'] === 'pagado' ? 'success' : ($v['estado'] === 'anulado' ? 'danger' : 'warning');
                                        ?>
                                        <tr>
                                            <td><span class="badge badge-secondary"><?= $v['id_venta'] ?></span></td>
                                            <td><?= htmlspecialchars(trim($v['cliente_nombre']) ?: 'Cliente General') ?></td>
                                            <td><span class="badge badge-light"><?= ucfirst($v['tipo_comprobante']) ?></span></td>
                                            <td class="text-right font-weight-bold text-success">S/. <?= number_format($v['total'],2) ?></td>
                                            <td><?= $v['fecha_formateada'] ?></td>
                                            <td class="text-center"><span class="badge badge-<?= $badge ?>"><?= ucfirst($v['estado']) ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center text-muted py-3">Sin ventas registradas aún.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Lotes por vencer -->
                    <div class="col-lg-<?= $es_administrador ? '5' : '12' ?> mb-3">
                        <div class="card">
                            <div class="card-header card-header-alerta">
                                <h6 class="m-0 font-weight-bold"><i class="fas fa-calendar-times mr-2"></i>Lotes próximos a vencer</h6>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-sm mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Producto</th>
                                            <th>Lote</th>
                                            <th class="text-center">Stock</th>
                                            <th class="text-center">Días</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($lotes_por_vencer)): ?>
                                        <?php foreach($lotes_por_vencer as $lv):
                                            $dias = (int)$lv['dias_restantes'];
                                            $cls  = $dias <= 30 ? 'badge-vence-critico' : 'badge-vence-pronto';
                                        ?>
                                        <tr>
                                            <td style="font-size:.85rem;"><?= htmlspecialchars($lv['nombre_producto']) ?></td>
                                            <td><small><?= htmlspecialchars($lv['codigo_lote']) ?></small></td>
                                            <td class="text-center"><?= $lv['stock_actual'] ?></td>
                                            <td class="text-center"><span class="badge <?= $cls ?>"><?= $dias ?>d</span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-muted py-3"><i class="fas fa-check-circle text-success mr-1"></i>Sin lotes próximos a vencer.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <footer class="main-footer" style="background:#fff;border-top:2px solid #1a7a4a;">
        <div class="float-right d-none d-sm-inline">
            <img src="/botica-2026/public/assets/img/logo.jpg"
                 alt="Botica 2026"
                 style="height:22px;width:22px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:5px;">
            <strong>Botica 2026</strong> v1.0
        </div>
        <strong>Copyright &copy; <?= date('Y') ?>.</strong> Todos los derechos reservados.
    </footer>

</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
<script>
$(function(){
    const VERDE   = '#1a7a4a';
    const CELESTE = '#2980b9';
    const COLORES = ['#1a7a4a','#2980b9','#e67e22','#9b59b6','#e74c3c','#1abc9c','#f39c12','#3498db'];

    // Gráfico de ventas (solo admin)
    <?php if ($es_administrador): ?>
    new Chart($('#lineChartVentas')[0].getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode($meses_labels) ?>,
            datasets: [{
                label: 'Ventas (S/.)',
                data: <?= json_encode($datos_ventas) ?>,
                borderColor: VERDE,
                backgroundColor: 'rgba(26,122,74,0.15)',
                borderWidth: 3,
                pointBackgroundColor: VERDE,
                pointRadius: 5,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => 'S/. ' + v.toLocaleString('es-PE') }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => 'S/. ' + ctx.parsed.y.toLocaleString('es-PE',{minimumFractionDigits:2}) } }
            }
        }
    });
    <?php endif; ?>

    // Gráfico de stock por categoría
    new Chart($('#pieChartStock')[0].getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($nombres_stock) ?>,
            datasets: [{
                data: <?= json_encode($cantidades_stock) ?>,
                backgroundColor: COLORES,
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 8
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            plugins: {
                legend: { position: 'right', labels: { font: { size: 12 } } },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const total = ctx.dataset.data.reduce((a,b)=>a+b,0);
                            const pct   = ((ctx.parsed/total)*100).toFixed(1);
                            return ctx.label + ': ' + ctx.parsed.toLocaleString('es-PE') + ' uds (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });

    <?php if ($reproducir_bienvenida): ?>
    try {
        const audioBienvenida = new Audio('/botica-2026/public/assets/sonidos/bienvenida.mp3');
        audioBienvenida.volume = 0.7;
        audioBienvenida.play().catch(e => console.log("Autoplay bloqueado por el navegador:", e));
    } catch(err) {
        console.log("Error al reproducir bienvenida:", err);
    }
    <?php endif; ?>

    // ── Alerta: Sin Permiso ───────────────────────────────────────────────────
    <?php if (isset($_GET['error']) && $_GET['error'] === 'sin_permiso'): 
        $mod_key = $_GET['modulo'] ?? '';
        $cat = catalogoModulos();
        $nombre_mod = $cat[$mod_key]['label'] ?? $mod_key;
    ?>
    Swal.fire({
        icon: 'error',
        title: 'Acceso Denegado',
        text: 'No tienes el acceso para poder visualizar el módulo "<?= htmlspecialchars($nombre_mod) ?>". Por favor, contacta al administrador.',
        confirmButtonColor: '#1a7a4a',
        confirmButtonText: 'Entendido'
    });
    <?php endif; ?>

});
</script>
</body>
</html>