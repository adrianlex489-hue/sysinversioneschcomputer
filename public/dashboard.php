<?php
require_once '../conf/verificar_acceso.php';
require_once '../conf/database.php';
require_once '../conf/permisos.php';

$conexion = $pdo;
$id_usuario_actual = (int)($_SESSION['id_usuario'] ?? 0);
$permisos = $id_usuario_actual ? cargarPermisos($pdo, $id_usuario_actual) : [];

// ── Defaults ──────────────────────────────────────────────────────────────────
$total_productos = $total_clientes = $total_ventas = $total_ganancias = 0;
$total_ordenes = $ordenes_pendientes = $productos_stock_bajo = 0;
$ventas_hoy = $ingresos_hoy = $ventas_mes = $ingresos_mes = $ventas_pendientes = 0;
$total_compras_mes = $ingresos_servicios_mes = $ordenes_listas = 0;
$caja_saldo = 0; $caja_abierta = false; $caja_nombre = '';
$ultimas_ventas = $ultimas_ordenes = $productos_bajo_stock = [];
$meses_labels = ['Ene','Feb','Mar','Abr','May','Jun'];
$datos_ventas = $datos_compras = [0,0,0,0,0,0];
$nombres_categorias = ['Sin datos']; $cantidades_cat = [1];

function qv($c,$sql,$p=[]){
    try{$s=$c->prepare($sql);$s->execute($p);$r=$s->fetchColumn();return($r===false||$r===null)?0:$r;}
    catch(PDOException $e){return 0;}
}

try {
    $total_productos      = qv($conexion,"SELECT COUNT(*) FROM productos WHERE estado=1");
    $total_clientes       = qv($conexion,"SELECT (SELECT COUNT(*) FROM clientes_natural WHERE estado_cliente=1)+(SELECT COUNT(*) FROM clientes_empresa WHERE estado_cliente=1)");
    $total_ordenes        = qv($conexion,"SELECT COUNT(*) FROM ordenes_servicio");
    $ordenes_pendientes   = qv($conexion,"SELECT COUNT(*) FROM ordenes_servicio WHERE estado IN ('recibido','diagnostico','en_proceso')");
    $ordenes_listas       = qv($conexion,"SELECT COUNT(*) FROM ordenes_servicio WHERE estado='listo'");
    $productos_stock_bajo = qv($conexion,"SELECT COUNT(*) FROM productos WHERE stock<=stock_minimo AND estado=1");

    // Caja activa
    $caja_row = $conexion->query("SELECT id_caja,nombre,monto_inicial FROM caja WHERE estado='abierta' ORDER BY fecha_apertura DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($caja_row) {
        $caja_abierta = true;
        $caja_nombre  = $caja_row['nombre'];
        $ingresos_caja = qv($conexion,"SELECT COALESCE(SUM(monto),0) FROM movimientos_caja WHERE id_caja=? AND tipo='ingreso'",[$caja_row['id_caja']]);
        $egresos_caja  = qv($conexion,"SELECT COALESCE(SUM(monto),0) FROM movimientos_caja WHERE id_caja=? AND tipo='egreso'",[$caja_row['id_caja']]);
        $caja_saldo    = round((float)$caja_row['monto_inicial'] + (float)$ingresos_caja - (float)$egresos_caja, 2);
    }

    if ($es_administrador) {
        $total_ventas         = qv($conexion,"SELECT COUNT(*) FROM ventas WHERE estado!='anulado'");
        $total_ganancias      = qv($conexion,"SELECT COALESCE(SUM(total),0) FROM ventas WHERE estado!='anulado'");
        $ventas_hoy           = qv($conexion,"SELECT COUNT(*) FROM ventas WHERE DATE(fecha)=CURDATE() AND estado!='anulado'");
        $ingresos_hoy         = qv($conexion,"SELECT COALESCE(SUM(total),0) FROM ventas WHERE DATE(fecha)=CURDATE() AND estado!='anulado'");
        $ventas_mes           = qv($conexion,"SELECT COUNT(*) FROM ventas WHERE YEAR(fecha)=YEAR(CURDATE()) AND MONTH(fecha)=MONTH(CURDATE()) AND estado!='anulado'");
        $ingresos_mes         = qv($conexion,"SELECT COALESCE(SUM(total),0) FROM ventas WHERE YEAR(fecha)=YEAR(CURDATE()) AND MONTH(fecha)=MONTH(CURDATE()) AND estado!='anulado'");
        $ventas_pendientes    = qv($conexion,"SELECT COUNT(*) FROM ventas WHERE estado='pendiente'");
        $total_compras_mes    = qv($conexion,"SELECT COALESCE(SUM(total),0) FROM compras WHERE YEAR(fecha)=YEAR(CURDATE()) AND MONTH(fecha)=MONTH(CURDATE()) AND estado!='anulado'");
        $ingresos_servicios_mes = qv($conexion,"SELECT COALESCE(SUM(ps.monto),0) FROM pagos_servicio ps WHERE YEAR(ps.fecha)=YEAR(CURDATE()) AND MONTH(ps.fecha)=MONTH(CURDATE())");

        // Últimas 5 ventas
        $stmt_uv = $conexion->query("SELECT v.id_venta,
            CASE v.tipo_cliente WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
            ELSE CONCAT(COALESCE(cn.nombres,''),' ',COALESCE(cn.apellido_paterno,'')) END AS cliente_nombre,
            v.total,v.tipo_comprobante,v.estado,v.metodo_pago,
            DATE_FORMAT(v.fecha,'%d/%m/%Y %H:%i') AS fecha_formateada
            FROM ventas v
            LEFT JOIN clientes_natural cn ON cn.id_cliente_natural=v.id_cliente AND v.tipo_cliente='natural'
            LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa=v.id_cliente AND v.tipo_cliente='empresa'
            ORDER BY v.fecha DESC LIMIT 5");
        $ultimas_ventas = $stmt_uv->fetchAll(PDO::FETCH_ASSOC);

        // Gráfico 6 meses
        $vr = $conexion->query("SELECT MONTH(fecha) m,SUM(total) t FROM ventas WHERE fecha>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH) AND estado!='anulado' GROUP BY MONTH(fecha)")->fetchAll(PDO::FETCH_ASSOC);
        $cr = $conexion->query("SELECT MONTH(fecha) m,SUM(total) t FROM compras WHERE fecha>=DATE_SUB(CURDATE(),INTERVAL 6 MONTH) AND estado!='anulado' GROUP BY MONTH(fecha)")->fetchAll(PDO::FETCH_ASSOC);
        $me = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        $meses_labels=[]; $datos_ventas=[]; $datos_compras=[];
        $ma=(int)date('n');
        for($i=5;$i>=0;$i--){
            $m=($ma-$i+12)%12; if($m===0)$m=12; $meses_labels[]=$me[$m];
            $vv=0; foreach($vr as $f){if((int)$f['m']===$m){$vv=(float)$f['t'];break;}}
            $cc=0; foreach($cr as $f){if((int)$f['m']===$m){$cc=(float)$f['t'];break;}}
            $datos_ventas[]=$vv; $datos_compras[]=$cc;
        }
    }

    // Últimas 5 órdenes
    $stmt_os = $conexion->query("SELECT os.id_orden,os.estado,os.prioridad,os.estado_pago,os.costo_total,
        DATE_FORMAT(os.fecha_recepcion,'%d/%m/%Y') AS fecha_rec,
        CASE os.tipo_cliente WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
        ELSE CONCAT(COALESCE(cn.nombres,''),' ',COALESCE(cn.apellido_paterno,'')) END AS cliente_nombre,
        e.marca,e.modelo,e.tipo AS tipo_equipo
        FROM ordenes_servicio os
        LEFT JOIN clientes_natural cn ON cn.id_cliente_natural=os.id_cliente AND os.tipo_cliente='natural'
        LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa=os.id_cliente AND os.tipo_cliente='empresa'
        LEFT JOIN equipos e ON os.id_equipo=e.id_equipo
        ORDER BY os.fecha_recepcion DESC LIMIT 5");
    $ultimas_ordenes = $stmt_os->fetchAll(PDO::FETCH_ASSOC);

    // Categorías
    $cr2 = $conexion->query("SELECT cat.nombre_categoria,COUNT(p.id_producto) AS total FROM productos p INNER JOIN categorias cat ON p.id_categoria=cat.id_categoria WHERE p.estado=1 GROUP BY cat.nombre_categoria ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);
    if(!empty($cr2)){$nombres_categorias=array_column($cr2,'nombre_categoria');$cantidades_cat=array_map('intval',array_column($cr2,'total'));}

    // Stock bajo
    $stmt_sb = $conexion->query("SELECT p.nombre_producto,p.stock,p.stock_minimo FROM productos p WHERE p.stock<=p.stock_minimo AND p.estado=1 ORDER BY p.stock ASC LIMIT 8");
    $productos_bajo_stock = $stmt_sb->fetchAll(PDO::FETCH_ASSOC);

} catch(Exception $e){ error_log('Dashboard: '.$e->getMessage()); }

$dias_es  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$meses_es = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$fecha_es = $dias_es[date('w')].', '.date('d').' de '.$meses_es[(int)date('n')].' de '.date('Y');
$nombres_rol = [1=>'Administrador',2=>'Asesor Comercial',3=>'Técnico'];
$rol_nombre  = $nombres_rol[$_SESSION['id_rol']??0] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard | SysInversiones CH Computer</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
<style>
/* ═══════════════════════════════════════════════════════════
   THEME VARIABLES
═══════════════════════════════════════════════════════════ */
:root,[data-theme="dark"]{
  --bg:        #0b0f1a;
  --bg2:       #111827;
  --bg3:       #1a2235;
  --bg4:       #1e2a3a;
  --card:      #111827;
  --card2:     #1a2235;
  --border:    rgba(255,255,255,.07);
  --border2:   rgba(255,255,255,.13);
  --text1:     #f1f5f9;
  --text2:     #94a3b8;
  --text3:     #64748b;
  --text4:     #475569;
  --shadow:    0 4px 24px rgba(0,0,0,.5);
  --shadow2:   0 12px 48px rgba(0,0,0,.6);
  --accent:    #3b82f6;
  --accent-g:  linear-gradient(135deg,#1d4ed8,#3b82f6);
  --hero-bg:   linear-gradient(135deg,#0b0f1a 0%,#111827 100%);
}
[data-theme="light"]{
  --bg:        #f0f4f8;
  --bg2:       #ffffff;
  --bg3:       #f8fafc;
  --bg4:       #f1f5f9;
  --card:      #ffffff;
  --card2:     #f8fafc;
  --border:    rgba(0,0,0,.08);
  --border2:   rgba(0,0,0,.14);
  --text1:     #0f172a;
  --text2:     #475569;
  --text3:     #94a3b8;
  --text4:     #cbd5e1;
  --shadow:    0 2px 12px rgba(0,0,0,.08);
  --shadow2:   0 8px 32px rgba(0,0,0,.12);
  --accent:    #2563eb;
  --accent-g:  linear-gradient(135deg,#1d4ed8,#2563eb);
  --hero-bg:   linear-gradient(135deg,#f0f4f8 0%,#e2e8f0 100%);
}

/* ═══════════════════════════════════════════════════════════
   BASE
═══════════════════════════════════════════════════════════ */
*{box-sizing:border-box;transition:background-color .25s,border-color .25s,color .15s;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text1);min-height:100vh;}
.content-wrapper{background:var(--bg)!important;}
.main-footer{background:var(--bg2)!important;border-top:1px solid var(--border)!important;color:var(--text3)!important;}

/* ═══════════════════════════════════════════════════════════
   NAVBAR
═══════════════════════════════════════════════════════════ */
.main-header{background:var(--bg2)!important;border-bottom:1px solid var(--border)!important;box-shadow:var(--shadow)!important;}
.main-header .nav-link{color:var(--text2)!important;}
.theme-btn{
  width:36px;height:36px;border-radius:10px;border:1px solid var(--border2);
  background:var(--bg3);color:var(--text2);cursor:pointer;
  display:flex;align-items:center;justify-content:center;font-size:.9rem;
  transition:all .2s;
}
.theme-btn:hover{background:var(--accent);color:#fff;border-color:var(--accent);}

/* ═══════════════════════════════════════════════════════════
   SIDEBAR COLLAPSE
═══════════════════════════════════════════════════════════ */
.main-sidebar,.main-sidebar .sidebar{transition:width .3s ease-in-out,margin-left .3s ease-in-out!important;}
.content-wrapper,.main-footer{transition:margin-left .3s ease-in-out!important;}
.sidebar-collapse .main-sidebar{width:4.6rem!important;}
.sidebar-collapse .main-sidebar .brand-text,
.sidebar-collapse .main-sidebar .nav-sidebar .nav-link p,
.sidebar-collapse .main-sidebar .user-panel .info,
.sidebar-collapse .main-sidebar .nav-sidebar .nav-link .right{display:none!important;}
.sidebar-collapse .main-sidebar .nav-sidebar .nav-link{padding:8px!important;display:flex!important;justify-content:center!important;}
.sidebar-collapse .main-sidebar .nav-icon{margin-right:0!important;}
.sidebar-collapse .main-sidebar .brand-link{justify-content:center!important;padding:10px!important;}
.sidebar-collapse .main-sidebar .user-panel{justify-content:center!important;}
.sidebar-collapse .content-wrapper,.sidebar-collapse .main-footer{margin-left:4.6rem!important;}

/* ═══════════════════════════════════════════════════════════
   PAGE HEADER
═══════════════════════════════════════════════════════════ */
.page-hdr{
  background:var(--bg2);border-bottom:1px solid var(--border);
  padding:16px 28px;position:relative;overflow:hidden;
}
.page-hdr::before{
  content:'';position:absolute;top:-80px;right:-80px;width:240px;height:240px;border-radius:50%;
  background:radial-gradient(circle,rgba(59,130,246,.1),transparent 70%);pointer-events:none;
}

/* ═══════════════════════════════════════════════════════════
   HERO CARDS (4 grandes)
═══════════════════════════════════════════════════════════ */
.hero-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;}
@media(max-width:1100px){.hero-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:600px){.hero-grid{grid-template-columns:1fr;}}

.hero-card{
  border-radius:18px;padding:24px 22px 20px;position:relative;overflow:hidden;
  border:1px solid var(--border);box-shadow:var(--shadow);
  transition:transform .25s,box-shadow .25s;
}
.hero-card:hover{transform:translateY(-4px);box-shadow:var(--shadow2);}
.hero-card .hc-glow{
  position:absolute;top:-40px;right:-40px;width:160px;height:160px;border-radius:50%;
  opacity:.12;pointer-events:none;
}
.hero-card .hc-icon{
  width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;
  font-size:1.3rem;margin-bottom:16px;position:relative;z-index:1;
}
.hero-card .hc-val{
  font-size:2.2rem;font-weight:900;line-height:1;letter-spacing:-1px;
  position:relative;z-index:1;
}
.hero-card .hc-val.sm{font-size:1.55rem;letter-spacing:-.5px;}
.hero-card .hc-lbl{font-size:.78rem;font-weight:600;margin-top:6px;opacity:.7;position:relative;z-index:1;}
.hero-card .hc-foot{
  margin-top:16px;padding-top:14px;border-top:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  font-size:.72rem;position:relative;z-index:1;
}
.hero-card .hc-foot a{text-decoration:none;font-weight:600;display:flex;align-items:center;gap:4px;}
.hero-card .hc-foot a:hover{text-decoration:underline;}
.hc-chip{
  display:inline-flex;align-items:center;gap:3px;padding:3px 9px;
  border-radius:20px;font-size:.68rem;font-weight:700;
}

/* Hero card color variants */
.hc-green{background:linear-gradient(135deg,#064e3b,#065f46);}
.hc-green .hc-icon{background:rgba(16,185,129,.2);color:#34d399;}
.hc-green .hc-val,.hc-green .hc-lbl{color:#ecfdf5;}
.hc-green .hc-foot a{color:#6ee7b7;}
.hc-green .hc-glow{background:#10b981;}

.hc-blue{background:linear-gradient(135deg,#1e3a5f,#1d4ed8);}
.hc-blue .hc-icon{background:rgba(59,130,246,.2);color:#93c5fd;}
.hc-blue .hc-val,.hc-blue .hc-lbl{color:#eff6ff;}
.hc-blue .hc-foot a{color:#93c5fd;}
.hc-blue .hc-glow{background:#3b82f6;}

.hc-purple{background:linear-gradient(135deg,#2e1065,#4c1d95);}
.hc-purple .hc-icon{background:rgba(139,92,246,.2);color:#c4b5fd;}
.hc-purple .hc-val,.hc-purple .hc-lbl{color:#f5f3ff;}
.hc-purple .hc-foot a{color:#c4b5fd;}
.hc-purple .hc-glow{background:#8b5cf6;}

.hc-cyan{background:linear-gradient(135deg,#0c3547,#0e7490);}
.hc-cyan .hc-icon{background:rgba(6,182,212,.2);color:#67e8f9;}
.hc-cyan .hc-val,.hc-cyan .hc-lbl{color:#ecfeff;}
.hc-cyan .hc-foot a{color:#67e8f9;}
.hc-cyan .hc-glow{background:#06b6d4;}

/* ═══════════════════════════════════════════════════════════
   METRIC CARDS (fila secundaria)
═══════════════════════════════════════════════════════════ */
.metric-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;}
.metric-card{
  background:var(--card);border:1px solid var(--border);border-radius:14px;
  padding:16px 14px 13px;position:relative;overflow:hidden;
  transition:transform .2s,box-shadow .2s,border-color .2s;
}
.metric-card:hover{transform:translateY(-3px);border-color:var(--border2);box-shadow:var(--shadow);}
.metric-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;border-radius:14px 14px 0 0;}
.mc-blue::before{background:linear-gradient(90deg,#1d4ed8,#3b82f6);}
.mc-cyan::before{background:linear-gradient(90deg,#0e7490,#06b6d4);}
.mc-green::before{background:linear-gradient(90deg,#065f46,#10b981);}
.mc-orange::before{background:linear-gradient(90deg,#92400e,#f59e0b);}
.mc-red::before{background:linear-gradient(90deg,#991b1b,#ef4444);}
.mc-teal::before{background:linear-gradient(90deg,#134e4a,#0d9488);}
.mc-indigo::before{background:linear-gradient(90deg,#312e81,#6366f1);}

.metric-card .mc-icon{
  width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;
  font-size:.9rem;margin-bottom:10px;
}
.mc-blue .mc-icon{background:rgba(59,130,246,.12);color:#60a5fa;}
.mc-cyan .mc-icon{background:rgba(6,182,212,.12);color:#22d3ee;}
.mc-green .mc-icon{background:rgba(16,185,129,.12);color:#34d399;}
.mc-orange .mc-icon{background:rgba(245,158,11,.12);color:#fbbf24;}
.mc-red .mc-icon{background:rgba(239,68,68,.12);color:#f87171;}
.mc-teal .mc-icon{background:rgba(13,148,136,.12);color:#2dd4bf;}
.mc-indigo .mc-icon{background:rgba(99,102,241,.12);color:#818cf8;}

.metric-card .mc-val{font-size:1.6rem;font-weight:800;line-height:1;color:var(--text1);letter-spacing:-.5px;}
.metric-card .mc-val.sm{font-size:1.1rem;}
.metric-card .mc-lbl{font-size:.7rem;color:var(--text2);margin-top:4px;font-weight:500;}
.metric-card .mc-foot{
  margin-top:10px;padding-top:8px;border-top:1px solid var(--border);
  font-size:.68rem;color:var(--text3);display:flex;align-items:center;gap:4px;
}
.metric-card .mc-foot a{color:var(--accent);font-weight:600;text-decoration:none;}
.metric-card .mc-foot a:hover{text-decoration:underline;}

/* ═══════════════════════════════════════════════════════════
   ALERTS
═══════════════════════════════════════════════════════════ */
.dash-alert{
  border-radius:12px;padding:13px 16px;display:flex;align-items:flex-start;gap:12px;
  font-size:.83rem;border:1px solid;
}
.dash-alert.danger{background:rgba(239,68,68,.07);border-color:rgba(239,68,68,.22);color:#fca5a5;}
.dash-alert.warning{background:rgba(245,158,11,.07);border-color:rgba(245,158,11,.22);color:#fcd34d;}
[data-theme="light"] .dash-alert.danger{background:#fef2f2;border-color:#fca5a5;color:#991b1b;}
[data-theme="light"] .dash-alert.warning{background:#fffbeb;border-color:#fcd34d;color:#92400e;}
.dash-alert .ai{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.dash-alert.danger .ai{background:rgba(239,68,68,.15);}
.dash-alert.warning .ai{background:rgba(245,158,11,.15);}
.dash-alert a{font-weight:700;color:inherit;text-decoration:underline;}

/* ═══════════════════════════════════════════════════════════
   SECTION LABEL
═══════════════════════════════════════════════════════════ */
.sec-lbl{
  font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:1.6px;
  color:var(--text3);margin-bottom:14px;display:flex;align-items:center;gap:10px;
}
.sec-lbl::after{content:'';flex:1;height:1px;background:var(--border);}
.sec-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;}

/* ═══════════════════════════════════════════════════════════
   DASH CARDS
═══════════════════════════════════════════════════════════ */
.dc{background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden;transition:border-color .2s;}
.dc:hover{border-color:var(--border2);}
.dc-head{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.dc-head h6{margin:0;font-size:.83rem;font-weight:700;color:var(--text1);display:flex;align-items:center;gap:8px;}
.dc-head .dci{width:26px;height:26px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.75rem;}
.dc-head .vl{font-size:.72rem;color:var(--accent);font-weight:600;text-decoration:none;display:flex;align-items:center;gap:4px;}
.dc-head .vl:hover{text-decoration:underline;}

/* ═══════════════════════════════════════════════════════════
   TABLE
═══════════════════════════════════════════════════════════ */
.dt{width:100%;border-collapse:collapse;font-size:.8rem;}
.dt th{padding:9px 12px;font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text3);border-bottom:1px solid var(--border);background:var(--bg3);}
.dt td{padding:11px 12px;border-bottom:1px solid var(--border);color:var(--text1);vertical-align:middle;}
.dt tr:last-child td{border-bottom:none;}
.dt tr:hover td{background:var(--bg3);}

/* ═══════════════════════════════════════════════════════════
   BADGES
═══════════════════════════════════════════════════════════ */
.bx{display:inline-flex;align-items:center;gap:3px;padding:3px 9px;border-radius:20px;font-size:.68rem;font-weight:700;}
.bx-recibido{background:rgba(59,130,246,.15);color:#93c5fd;}
.bx-diagnostico{background:rgba(245,158,11,.15);color:#fcd34d;}
.bx-en_proceso{background:rgba(99,102,241,.15);color:#a5b4fc;}
.bx-listo{background:rgba(16,185,129,.15);color:#6ee7b7;}
.bx-entregado{background:rgba(100,116,139,.15);color:#94a3b8;}
.bx-cancelado{background:rgba(239,68,68,.15);color:#fca5a5;}
.bx-pagado{background:rgba(16,185,129,.15);color:#6ee7b7;}
.bx-anulado{background:rgba(239,68,68,.15);color:#fca5a5;}
.bx-pendiente{background:rgba(245,158,11,.15);color:#fcd34d;}
.bx-alta{background:rgba(239,68,68,.15);color:#fca5a5;border:1px solid rgba(239,68,68,.3);}
.bx-media{background:rgba(245,158,11,.15);color:#fcd34d;border:1px solid rgba(245,158,11,.3);}
.bx-baja{background:rgba(16,185,129,.15);color:#6ee7b7;border:1px solid rgba(16,185,129,.3);}
.bx-normal{background:rgba(59,130,246,.15);color:#93c5fd;}
[data-theme="light"] .bx-recibido{background:#dbeafe;color:#1e40af;}
[data-theme="light"] .bx-diagnostico{background:#fef3c7;color:#92400e;}
[data-theme="light"] .bx-en_proceso{background:#e0e7ff;color:#3730a3;}
[data-theme="light"] .bx-listo{background:#d1fae5;color:#065f46;}
[data-theme="light"] .bx-entregado{background:#f3f4f6;color:#374151;}
[data-theme="light"] .bx-cancelado{background:#fee2e2;color:#991b1b;}
[data-theme="light"] .bx-pagado{background:#d1fae5;color:#065f46;}
[data-theme="light"] .bx-anulado{background:#fee2e2;color:#991b1b;}
[data-theme="light"] .bx-pendiente{background:#fef3c7;color:#92400e;}
[data-theme="light"] .bx-alta{background:#fee2e2;color:#991b1b;}
[data-theme="light"] .bx-media{background:#fef3c7;color:#92400e;}
[data-theme="light"] .bx-baja{background:#d1fae5;color:#065f46;}
[data-theme="light"] .bx-normal{background:#dbeafe;color:#1e40af;}
</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

<!-- ═══ NAVBAR ═══════════════════════════════════════════════════════════════ -->
<nav class="main-header navbar navbar-expand navbar-dark">
  <ul class="navbar-nav">
    <li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a></li>
  </ul>
  <ul class="navbar-nav ml-auto align-items-center" style="gap:8px;padding-right:8px;">
    <?php if($productos_stock_bajo>0): ?>
    <li class="nav-item">
      <?php if(checkP('inventario',$es_administrador,$permisos)): ?>
      <a class="nav-link" href="/sysinversioneschcomputer/modules/inventario/inventario.php" title="<?= $productos_stock_bajo ?> producto(s) con stock bajo">
      <?php else: ?>
      <a class="nav-link" href="/sysinversioneschcomputer/public/dashboard.php?error=sin_permiso&modulo=inventario" title="Sin acceso al inventario">
      <?php endif; ?>
        <i class="fas fa-exclamation-triangle" style="color:#fbbf24;"></i>
        <span class="badge badge-warning navbar-badge"><?= $productos_stock_bajo ?></span>
      </a>
    </li>
    <?php endif; ?>
    <?php if($ordenes_pendientes>0): ?>
    <li class="nav-item">
      <?php if(checkP('taller',$es_administrador,$permisos)): ?>
      <a class="nav-link" href="/sysinversioneschcomputer/modules/servicios/taller.php" title="<?= $ordenes_pendientes ?> órdenes en proceso">
      <?php else: ?>
      <a class="nav-link" href="/sysinversioneschcomputer/public/dashboard.php?error=sin_permiso&modulo=taller" title="Sin acceso al taller">
      <?php endif; ?>
        <i class="fas fa-tools" style="color:#67e8f9;"></i>
        <span class="badge badge-info navbar-badge"><?= $ordenes_pendientes ?></span>
      </a>
    </li>
    <?php endif; ?>
    <!-- Toggle tema -->
    <li class="nav-item">
      <button class="theme-btn" id="themeToggle" title="Cambiar tema">
        <i class="fas fa-moon" id="themeIcon"></i>
      </button>
    </li>
    <!-- Usuario -->
    <li class="nav-item dropdown">
      <a class="nav-link d-flex align-items-center" data-toggle="dropdown" href="#" style="gap:8px;">
        <div style="width:34px;height:34px;background:linear-gradient(135deg,#1d4ed8,#6366f1);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 2px 10px rgba(99,102,241,.4);">
          <i class="fas fa-user" style="color:#fff;font-size:.72rem;"></i>
        </div>
        <div class="d-none d-lg-block" style="line-height:1.25;max-width:220px;">
          <div style="font-size:.78rem;font-weight:700;color:var(--text1);word-break:break-word;"><?= htmlspecialchars($_SESSION['nombre_completo']??'Usuario') ?></div>
          <div style="font-size:.65rem;color:var(--text3);"><?= $rol_nombre ?></div>
        </div>
      </a>
      <div class="dropdown-menu dropdown-menu-right" style="background:var(--bg2);border:1px solid var(--border2);border-radius:12px;box-shadow:var(--shadow2);min-width:240px;padding:8px;">
        <div style="padding:8px 12px 10px;border-bottom:1px solid var(--border);margin-bottom:6px;">
          <div style="font-size:.82rem;font-weight:700;color:var(--text1);word-break:break-word;"><?= htmlspecialchars($_SESSION['nombre_completo']??'') ?></div>
          <div style="font-size:.7rem;color:var(--text3);"><?= $rol_nombre ?></div>
        </div>
        <a href="logout.php" class="dropdown-item" style="color:#f87171;font-size:.82rem;border-radius:8px;padding:8px 12px;"><i class="fas fa-sign-out-alt mr-2"></i>Cerrar Sesión</a>
      </div>
    </li>
  </ul>
</nav>

<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">

  <!-- ═══ PAGE HEADER ═══════════════════════════════════════════════════════ -->
  <div class="page-hdr">
    <div class="d-flex align-items-center justify-content-between flex-wrap" style="gap:12px;position:relative;z-index:1;">
      <div class="d-flex align-items-center" style="gap:14px;">
        <div style="width:48px;height:48px;background:linear-gradient(135deg,#1d4ed8,#6366f1);border-radius:13px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(99,102,241,.35);flex-shrink:0;">
          <i class="fas fa-tachometer-alt" style="color:#fff;font-size:1.15rem;"></i>
        </div>
        <div>
          <h5 style="margin:0;font-weight:800;font-size:1.05rem;color:var(--text1);letter-spacing:-.3px;">Panel de Control</h5>
          <div style="font-size:.72rem;color:var(--text2);margin-top:3px;display:flex;align-items:center;gap:7px;">
            <span style="width:7px;height:7px;background:#10b981;border-radius:50%;display:inline-block;box-shadow:0 0 6px #10b981;flex-shrink:0;"></span>
            <?= htmlspecialchars($_SESSION['nombre_completo']??'Usuario') ?> &mdash; <?= $fecha_es ?>
          </div>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <?php if($caja_abierta): ?>
        <div style="font-size:.75rem;color:var(--text2);display:flex;align-items:center;gap:6px;background:rgba(16,185,129,.1);padding:6px 12px;border-radius:20px;border:1px solid rgba(16,185,129,.25);">
          <i class="fas fa-cash-register" style="color:#34d399;"></i>
          <span style="color:#34d399;font-weight:700;">Caja abierta</span>
          <span style="color:var(--text2);">S/. <?= number_format($caja_saldo,2) ?></span>
        </div>
        <?php endif; ?>
        <div style="font-size:.8rem;color:var(--text2);display:flex;align-items:center;gap:6px;background:var(--bg3);padding:6px 14px;border-radius:20px;border:1px solid var(--border);">
          <i class="fas fa-clock" style="color:var(--accent);"></i>
          <span id="reloj-header" style="font-family:monospace;font-weight:700;color:var(--text1);"><?= date('H:i:s') ?></span>
        </div>
      </div>
    </div>
  </div>

  <div style="padding:24px 28px;">

    <!-- ═══ ALERTAS ═══════════════════════════════════════════════════════════ -->
    <?php if($productos_stock_bajo>0||$ordenes_pendientes>0||$ordenes_listas>0): ?>
    <div class="row mb-4">
      <?php if($productos_stock_bajo>0): ?>
      <div class="col-md-4 mb-2">
        <div class="dash-alert danger">
          <div class="ai"><i class="fas fa-exclamation-triangle" style="color:#f87171;"></i></div>
          <div style="flex:1;">
            <strong><?= $productos_stock_bajo ?> producto(s) con stock bajo el mínimo.</strong>
            <div style="margin-top:5px;">
              <?php if(checkP('inventario',$es_administrador,$permisos)): ?>
              <a href="/sysinversioneschcomputer/modules/inventario/inventario.php">Ver más →</a>
              <?php else: ?>
              <a href="/sysinversioneschcomputer/public/dashboard.php?error=sin_permiso&modulo=inventario">Ver más →</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <?php if($ordenes_pendientes>0): ?>
      <div class="col-md-4 mb-2">
        <div class="dash-alert warning">
          <div class="ai"><i class="fas fa-tools" style="color:#fbbf24;"></i></div>
          <div style="flex:1;">
            <strong><?= $ordenes_pendientes ?> orden(es) en proceso.</strong>
            <div style="margin-top:5px;">
              <?php if(checkP('taller',$es_administrador,$permisos)): ?>
              <a href="/sysinversioneschcomputer/modules/servicios/taller.php">Ver más →</a>
              <?php else: ?>
              <a href="/sysinversioneschcomputer/public/dashboard.php?error=sin_permiso&modulo=taller">Ver más →</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <?php if($ordenes_listas>0): ?>
      <div class="col-md-4 mb-2">
        <div class="dash-alert" style="background:rgba(16,185,129,.07);border-color:rgba(16,185,129,.22);color:#6ee7b7;">
          <div class="ai" style="background:rgba(16,185,129,.15);"><i class="fas fa-check-circle" style="color:#34d399;"></i></div>
          <div style="flex:1;">
            <strong><?= $ordenes_listas ?> equipo(s) listo(s) para entregar.</strong>
            <div style="margin-top:5px;">
              <?php if(checkP('taller',$es_administrador,$permisos)): ?>
              <a href="/sysinversioneschcomputer/modules/servicios/taller.php" style="color:#6ee7b7;">Ver más →</a>
              <?php else: ?>
              <a href="/sysinversioneschcomputer/public/dashboard.php?error=sin_permiso&modulo=taller" style="color:#6ee7b7;">Ver más →</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ═══ HERO CARDS (4 grandes) ═══════════════════════════════════════════ -->
    <?php if($es_administrador): ?>
    <div class="sec-lbl"><span class="sec-dot" style="background:#10b981;"></span> Resumen Financiero</div>
    <div class="hero-grid mb-4">

      <!-- Ingresos totales -->
      <div class="hero-card hc-green">
        <div class="hc-glow"></div>
        <div class="hc-icon"><i class="fas fa-coins"></i></div>
        <div class="hc-val sm">S/. <?= number_format($total_ganancias,2) ?></div>
        <div class="hc-lbl">Ingresos Totales (Ventas)</div>
        <div class="hc-foot">
          <a href="/sysinversioneschcomputer/modules/transacciones/historial/historial_ventas.php">Ver historial <i class="fas fa-arrow-right"></i></a>
          <?php if($ingresos_hoy>0): ?><span class="hc-chip" style="background:rgba(16,185,129,.25);color:#6ee7b7;">+S/. <?= number_format($ingresos_hoy,2) ?> hoy</span><?php endif; ?>
        </div>
      </div>

      <!-- Ingresos del mes -->
      <div class="hero-card hc-blue">
        <div class="hc-glow"></div>
        <div class="hc-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="hc-val sm">S/. <?= number_format($ingresos_mes,2) ?></div>
        <div class="hc-lbl">Ingresos Este Mes</div>
        <div class="hc-foot">
          <a href="/sysinversioneschcomputer/modules/transacciones/historial/historial_ventas.php">Ver mes <i class="fas fa-arrow-right"></i></a>
          <?php if($ventas_mes>0): ?><span class="hc-chip" style="background:rgba(59,130,246,.25);color:#93c5fd;"><?= $ventas_mes ?> ventas</span><?php endif; ?>
        </div>
      </div>

      <!-- Órdenes de servicio -->
      <div class="hero-card hc-purple">
        <div class="hc-glow"></div>
        <div class="hc-icon"><i class="fas fa-tools"></i></div>
        <div class="hc-val"><?= $total_ordenes ?></div>
        <div class="hc-lbl">Órdenes de Servicio</div>
        <div class="hc-foot">
          <?php if(checkP('taller',$es_administrador,$permisos)): ?>
          <a href="/sysinversioneschcomputer/modules/servicios/taller.php">Ver taller <i class="fas fa-arrow-right"></i></a>
          <?php else: ?><span style="opacity:.6;">Sin acceso</span><?php endif; ?>
          <?php if($ordenes_pendientes>0): ?><span class="hc-chip" style="background:rgba(139,92,246,.25);color:#c4b5fd;"><?= $ordenes_pendientes ?> activas</span><?php endif; ?>
        </div>
      </div>

      <!-- Clientes -->
      <div class="hero-card hc-cyan">
        <div class="hc-glow"></div>
        <div class="hc-icon"><i class="fas fa-users"></i></div>
        <div class="hc-val"><?= $total_clientes ?></div>
        <div class="hc-lbl">Clientes Registrados</div>
        <div class="hc-foot">
          <?php if(checkP('clientes',$es_administrador,$permisos)): ?>
          <a href="/sysinversioneschcomputer/modules/personas/clientes.php">Ver clientes <i class="fas fa-arrow-right"></i></a>
          <?php else: ?><span style="opacity:.6;">Sin acceso</span><?php endif; ?>
        </div>
      </div>

    </div><!-- /hero-grid -->
    <?php else: ?>
    <!-- Hero cards para no-admin -->
    <div class="sec-lbl"><span class="sec-dot" style="background:#8b5cf6;"></span> Resumen Operativo</div>
    <div class="hero-grid mb-4" style="grid-template-columns:repeat(2,1fr);">
      <div class="hero-card hc-purple">
        <div class="hc-glow"></div>
        <div class="hc-icon"><i class="fas fa-tools"></i></div>
        <div class="hc-val"><?= $total_ordenes ?></div>
        <div class="hc-lbl">Órdenes de Servicio</div>
        <div class="hc-foot">
          <?php if(checkP('taller',$es_administrador,$permisos)): ?>
          <a href="/sysinversioneschcomputer/modules/servicios/taller.php">Ver taller <i class="fas fa-arrow-right"></i></a>
          <?php else: ?><span style="opacity:.6;">Sin acceso</span><?php endif; ?>
          <?php if($ordenes_pendientes>0): ?><span class="hc-chip" style="background:rgba(139,92,246,.25);color:#c4b5fd;"><?= $ordenes_pendientes ?> activas</span><?php endif; ?>
        </div>
      </div>
      <div class="hero-card hc-cyan">
        <div class="hc-glow"></div>
        <div class="hc-icon"><i class="fas fa-users"></i></div>
        <div class="hc-val"><?= $total_clientes ?></div>
        <div class="hc-lbl">Clientes Registrados</div>
        <div class="hc-foot">
          <?php if(checkP('clientes',$es_administrador,$permisos)): ?>
          <a href="/sysinversioneschcomputer/modules/personas/clientes.php">Ver clientes <i class="fas fa-arrow-right"></i></a>
          <?php else: ?><span style="opacity:.6;">Sin acceso</span><?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ═══ METRIC CARDS (fila secundaria) ═══════════════════════════════════ -->
    <div class="sec-lbl"><span class="sec-dot" style="background:#3b82f6;"></span> Indicadores Operativos</div>
    <div class="metric-grid mb-4">

      <?php if(checkP('productos',$es_administrador,$permisos)): ?>
      <div class="metric-card mc-blue">
        <div class="mc-icon"><i class="fas fa-laptop"></i></div>
        <div class="mc-val"><?= $total_productos ?></div>
        <div class="mc-lbl">Productos activos</div>
        <div class="mc-foot">
          <a href="/sysinversioneschcomputer/modules/catalogos/productos.php">Ver catálogo</a>
          <?php if($productos_stock_bajo>0): ?><span style="margin-left:auto;background:rgba(239,68,68,.12);color:#f87171;padding:2px 7px;border-radius:20px;font-size:.65rem;font-weight:700;"><i class="fas fa-arrow-down"></i> <?= $productos_stock_bajo ?></span><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="metric-card mc-orange">
        <div class="mc-icon"><i class="fas fa-hourglass-half"></i></div>
        <div class="mc-val"><?= $ordenes_pendientes ?></div>
        <div class="mc-lbl">Órdenes en proceso</div>
        <div class="mc-foot">
          <?php if(checkP('taller',$es_administrador,$permisos)): ?>
          <a href="/sysinversioneschcomputer/modules/servicios/taller.php">Ver pendientes</a>
          <?php else: ?><span>Sin acceso</span><?php endif; ?>
        </div>
      </div>

      <?php if($es_administrador): ?>
      <div class="metric-card mc-teal">
        <div class="mc-icon"><i class="fas fa-receipt"></i></div>
        <div class="mc-val"><?= $total_ventas ?></div>
        <div class="mc-lbl">Ventas totales</div>
        <div class="mc-foot">
          <a href="/sysinversioneschcomputer/modules/transacciones/historial/historial_ventas.php">Historial</a>
          <?php if($ventas_hoy>0): ?><span style="margin-left:auto;background:rgba(13,148,136,.12);color:#2dd4bf;padding:2px 7px;border-radius:20px;font-size:.65rem;font-weight:700;">+<?= $ventas_hoy ?> hoy</span><?php endif; ?>
        </div>
      </div>

      <?php if($ventas_pendientes>0): ?>
      <div class="metric-card mc-red">
        <div class="mc-icon"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="mc-val"><?= $ventas_pendientes ?></div>
        <div class="mc-lbl">Cobros pendientes</div>
        <div class="mc-foot"><a href="/sysinversioneschcomputer/modules/transacciones/cobro_ventas.php">Ir a cobros</a></div>
      </div>
      <?php endif; ?>

      <div class="metric-card mc-indigo">
        <div class="mc-icon"><i class="fas fa-truck-loading"></i></div>
        <div class="mc-val sm">S/. <?= number_format($total_compras_mes,2) ?></div>
        <div class="mc-lbl">Compras este mes</div>
        <div class="mc-foot"><a href="/sysinversioneschcomputer/modules/transacciones/historial/historial_compras.php">Ver compras</a></div>
      </div>

      <div class="metric-card mc-green">
        <div class="mc-icon"><i class="fas fa-wrench"></i></div>
        <div class="mc-val sm">S/. <?= number_format($ingresos_servicios_mes,2) ?></div>
        <div class="mc-lbl">Ingresos servicios (mes)</div>
        <div class="mc-foot">
          <?php if(checkP('taller',$es_administrador,$permisos)): ?>
          <a href="/sysinversioneschcomputer/modules/servicios/taller.php">Ver taller</a>
          <?php else: ?><span>Sin acceso</span><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if(checkP('proveedores',$es_administrador,$permisos)): ?>
      <div class="metric-card mc-cyan">
        <div class="mc-icon"><i class="fas fa-truck"></i></div>
        <div class="mc-val"><?= qv($conexion,"SELECT COUNT(*) FROM proveedores WHERE estado=1") ?></div>
        <div class="mc-lbl">Proveedores activos</div>
        <div class="mc-foot">
          <a href="/sysinversioneschcomputer/modules/personas/proveedores.php">Ver proveedores</a>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /metric-grid -->

    <!-- ═══ GRÁFICOS ═══════════════════════════════════════════════════════════ -->
    <div class="sec-lbl"><span class="sec-dot" style="background:#8b5cf6;"></span> Análisis Visual</div>
    <div class="row mb-4">
      <?php if($es_administrador): ?>
      <div class="col-lg-8 mb-3">
        <div class="dc">
          <div class="dc-head">
            <h6><span class="dci" style="background:rgba(59,130,246,.12);"><i class="fas fa-chart-bar" style="color:#60a5fa;font-size:.75rem;"></i></span>Ventas vs Compras — Últimos 6 meses</h6>
            <a href="/sysinversioneschcomputer/modules/transacciones/historial/historial_ventas.php" class="vl">Ver historial <i class="fas fa-arrow-right"></i></a>
          </div>
          <div style="padding:16px 18px;"><div style="position:relative;height:240px;"><canvas id="chartVentasCompras"></canvas></div></div>
        </div>
      </div>
      <?php endif; ?>
      <div class="col-lg-<?= $es_administrador?'4':'12' ?> mb-3">
        <div class="dc">
          <div class="dc-head">
            <h6><span class="dci" style="background:rgba(6,182,212,.12);"><i class="fas fa-chart-pie" style="color:#22d3ee;font-size:.75rem;"></i></span>Productos por Categoría</h6>
            <?php if(checkP('productos',$es_administrador,$permisos)): ?>
            <a href="/sysinversioneschcomputer/modules/catalogos/productos.php" class="vl">Ver <i class="fas fa-arrow-right"></i></a>
            <?php endif; ?>
          </div>
          <div style="padding:16px 18px;"><div style="position:relative;height:240px;"><canvas id="chartCategorias"></canvas></div></div>
        </div>
      </div>
    </div>

    <!-- ═══ TABLAS OPERATIVAS ═══════════════════════════════════════════════ -->
    <div class="sec-lbl"><span class="sec-dot" style="background:#10b981;"></span> Actividad Reciente</div>
    <div class="row mb-4">

      <!-- Últimas órdenes de servicio -->
      <div class="col-lg-7 mb-3">
        <div class="dc">
          <div class="dc-head">
            <h6><span class="dci" style="background:rgba(13,148,136,.12);"><i class="fas fa-tools" style="color:#2dd4bf;font-size:.75rem;"></i></span>Últimas Órdenes de Servicio</h6>
            <?php if(checkP('taller',$es_administrador,$permisos)): ?>
            <a href="/sysinversioneschcomputer/modules/servicios/taller.php" class="vl">Ver todas <i class="fas fa-arrow-right"></i></a>
            <?php endif; ?>
          </div>
          <div style="overflow-x:auto;">
            <table class="dt">
              <thead><tr>
                <th style="padding-left:18px;">#</th>
                <th>Cliente</th>
                <th>Equipo</th>
                <th class="text-center">Prioridad</th>
                <th class="text-center">Estado</th>
              </tr></thead>
              <tbody>
              <?php if(!empty($ultimas_ordenes)): foreach($ultimas_ordenes as $os): ?>
              <tr>
                <td style="padding-left:18px;">
                  <span style="font-size:.7rem;font-weight:700;color:var(--text3);">#<?= str_pad($os['id_orden'],6,'0',STR_PAD_LEFT) ?></span>
                  <div style="font-size:.65rem;color:var(--text3);"><?= $os['fecha_rec'] ?></div>
                </td>
                <td style="font-weight:600;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($os['cliente_nombre']) ?>">
                  <?= htmlspecialchars(trim($os['cliente_nombre'])?:'Sin cliente') ?>
                </td>
                <td style="color:var(--text2);font-size:.78rem;">
                  <?= htmlspecialchars(trim(($os['marca']??'').' '.($os['modelo']??''))) ?>
                  <?php if($os['tipo_equipo']): ?><div style="font-size:.65rem;color:var(--text3);"><?= htmlspecialchars($os['tipo_equipo']) ?></div><?php endif; ?>
                </td>
                <td class="text-center"><span class="bx bx-<?= $os['prioridad']??'baja' ?>"><?= ucfirst($os['prioridad']??'baja') ?></span></td>
                <td class="text-center"><span class="bx bx-<?= $os['estado'] ?>"><?= ucfirst(str_replace('_',' ',$os['estado'])) ?></span></td>
              </tr>
              <?php endforeach; else: ?>
              <tr><td colspan="5" class="text-center py-4" style="color:var(--text3);font-size:.8rem;"><i class="fas fa-inbox mr-2"></i>Sin órdenes registradas.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Productos con stock bajo -->
      <div class="col-lg-5 mb-3">
        <div class="dc">
          <div class="dc-head">
            <h6><span class="dci" style="background:rgba(239,68,68,.12);"><i class="fas fa-exclamation-triangle" style="color:#f87171;font-size:.75rem;"></i></span>Productos con Stock Bajo</h6>
            <?php if(checkP('inventario',$es_administrador,$permisos)): ?>
            <a href="/sysinversioneschcomputer/modules/inventario/inventario.php" class="vl">Ver inventario <i class="fas fa-arrow-right"></i></a>
            <?php endif; ?>
          </div>
          <div style="overflow-x:auto;">
            <table class="dt">
              <thead><tr>
                <th style="padding-left:18px;">Producto</th>
                <th class="text-center">Stock actual</th>
              </tr></thead>
              <tbody>
              <?php if(!empty($productos_bajo_stock)): foreach($productos_bajo_stock as $pb): ?>
              <tr>
                <td style="padding-left:18px;font-weight:500;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($pb['nombre_producto']) ?>">
                  <?= htmlspecialchars($pb['nombre_producto']) ?>
                  <div style="font-size:.65rem;color:var(--text3);">Mínimo: <?= $pb['stock_minimo'] ?></div>
                </td>
                <td class="text-center">
                  <span style="font-size:1.1rem;font-weight:800;color:<?= $pb['stock']==0?'#f87171':'#fbbf24' ?>;"><?= $pb['stock'] ?></span>
                </td>
              </tr>
              <?php endforeach; else: ?>
              <tr><td colspan="2" class="text-center py-4" style="color:#34d399;font-size:.8rem;"><i class="fas fa-check-circle mr-1"></i>Stock en niveles normales.</td></tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ ÚLTIMAS VENTAS (admin) ═══════════════════════════════════════════ -->
    <?php if($es_administrador&&!empty($ultimas_ventas)): ?>
    <div class="row mb-4">
      <div class="col-12">
        <div class="dc">
          <div class="dc-head">
            <h6><span class="dci" style="background:rgba(59,130,246,.12);"><i class="fas fa-receipt" style="color:#60a5fa;font-size:.75rem;"></i></span>Últimas 5 Ventas</h6>
            <a href="/sysinversioneschcomputer/modules/transacciones/historial/historial_ventas.php" class="vl">Ver historial completo <i class="fas fa-arrow-right"></i></a>
          </div>
          <div style="overflow-x:auto;">
            <table class="dt">
              <thead><tr>
                <th style="padding-left:18px;">#</th>
                <th>Cliente</th>
                <th>Comprobante</th>
                <th>Método</th>
                <th class="text-right">Total</th>
                <th>Fecha</th>
                <th class="text-center">Estado</th>
              </tr></thead>
              <tbody>
              <?php foreach($ultimas_ventas as $v):
                $cls=$v['estado']==='pagado'?'bx-pagado':($v['estado']==='anulado'?'bx-anulado':'bx-pendiente');
                $lbl=$v['estado']==='pagado'?'Pagado':($v['estado']==='anulado'?'Anulado':'Pendiente');
                $metodo_icons=['efectivo'=>'fa-money-bill-wave','yape'=>'fa-mobile-alt','plin'=>'fa-mobile-alt','transferencia'=>'fa-university','tarjeta'=>'fa-credit-card'];
                $micon=$metodo_icons[$v['metodo_pago']??'efectivo']??'fa-money-bill-wave';
              ?>
              <tr>
                <td style="padding-left:18px;"><span style="font-size:.7rem;font-weight:700;color:var(--text3);">#<?= $v['id_venta'] ?></span></td>
                <td style="font-weight:600;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($v['cliente_nombre']) ?>">
                  <?= htmlspecialchars(trim($v['cliente_nombre'])?:'Cliente General') ?>
                </td>
                <td><span style="font-size:.72rem;background:var(--bg3);color:var(--text2);padding:2px 8px;border-radius:6px;font-weight:600;border:1px solid var(--border);"><?= ucfirst($v['tipo_comprobante']) ?></span></td>
                <td style="color:var(--text2);font-size:.78rem;"><i class="fas <?= $micon ?> mr-1"></i><?= ucfirst($v['metodo_pago']??'') ?></td>
                <td class="text-right" style="font-weight:800;color:#34d399;font-size:.88rem;">S/. <?= number_format($v['total'],2) ?></td>
                <td style="color:var(--text2);font-size:.78rem;"><?= $v['fecha_formateada'] ?></td>
                <td class="text-center"><span class="bx <?= $cls ?>"><?= $lbl ?></span></td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /padding -->
</div><!-- /content-wrapper -->

<footer class="main-footer sys-footer" style="font-size:.78rem;">
  <div class="float-right d-none d-sm-inline" style="display:flex;align-items:center;gap:6px;">
    <img src="/sysinversioneschcomputer/Logo/logo.jpg" alt="SysInversiones CH Computer"
         style="height:22px;width:22px;object-fit:cover;border-radius:4px;vertical-align:middle;">
    <strong>SysInversiones CH Computer</strong> v1.0
  </div>
  <strong>Copyright &copy; <?= date('Y') ?>.</strong> Todos los derechos reservados.
</footer>
</div><!-- /wrapper -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

  /* ── TEMA OSCURO / CLARO ─────────────────────────────────────────────────── */
  var html  = document.documentElement;
  var btn   = document.getElementById('themeToggle');
  var icon  = document.getElementById('themeIcon');
  var saved = localStorage.getItem('sys_theme') || 'dark';

  function applyTheme(t) {
    html.setAttribute('data-theme', t);
    localStorage.setItem('sys_theme', t);
    if (t === 'dark') {
      icon.className = 'fas fa-moon';
      btn.title = 'Cambiar a modo claro';
      Chart.defaults.color = '#64748b';
      Chart.defaults.borderColor = 'rgba(255,255,255,.06)';
    } else {
      icon.className = 'fas fa-sun';
      btn.title = 'Cambiar a modo oscuro';
      Chart.defaults.color = '#94a3b8';
      Chart.defaults.borderColor = 'rgba(0,0,0,.06)';
    }
  }
  applyTheme(saved);
  btn.addEventListener('click', function () {
    applyTheme(html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
    // Redibujar gráficos al cambiar tema
    if (window._chartVC) window._chartVC.destroy();
    if (window._chartCat) window._chartCat.destroy();
    buildCharts();
  });

  /* ── RELOJ ───────────────────────────────────────────────────────────────── */
  var rel = document.getElementById('reloj-header');
  if (rel) setInterval(function () {
    var n = new Date();
    rel.textContent = String(n.getHours()).padStart(2,'0') + ':' +
                      String(n.getMinutes()).padStart(2,'0') + ':' +
                      String(n.getSeconds()).padStart(2,'0');
  }, 1000);

  /* ── GRÁFICOS ────────────────────────────────────────────────────────────── */
  var COLS = ['#3b82f6','#06b6d4','#8b5cf6','#0d9488','#f59e0b','#ef4444','#10b981','#f97316','#6366f1','#ec4899'];

  function buildCharts() {
    var isDark = html.getAttribute('data-theme') === 'dark';
    var gridColor  = isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.06)';
    var tickColor  = isDark ? '#64748b' : '#94a3b8';
    var legendColor= isDark ? '#94a3b8' : '#475569';
    var borderCol  = isDark ? 'rgba(17,24,39,.8)' : 'rgba(255,255,255,.9)';

    <?php if($es_administrador): ?>
    var ctxB = document.getElementById('chartVentasCompras');
    if (ctxB) {
      window._chartVC = new Chart(ctxB.getContext('2d'), {
        type: 'bar',
        data: {
          labels: <?= json_encode($meses_labels) ?>,
          datasets: [
            { label: 'Ventas (S/.)',  data: <?= json_encode($datos_ventas) ?>,  backgroundColor: 'rgba(59,130,246,.85)',  borderRadius: 7, borderSkipped: false },
            { label: 'Compras (S/.)', data: <?= json_encode($datos_compras) ?>, backgroundColor: 'rgba(99,102,241,.55)', borderRadius: 7, borderSkipped: false }
          ]
        },
        options: {
          maintainAspectRatio: false, responsive: true,
          scales: {
            x: { grid: { display: false }, ticks: { color: tickColor, font: { size: 11 } } },
            y: { beginAtZero: true, grid: { color: gridColor },
                 ticks: { color: tickColor, font: { size: 11 },
                          callback: function(v){ return 'S/. ' + v.toLocaleString('es-PE'); } } }
          },
          plugins: {
            legend: { position: 'top', labels: { color: legendColor, font: { size: 11 }, usePointStyle: true, pointStyleWidth: 10 } },
            tooltip: { callbacks: { label: function(c){ return c.dataset.label + ': S/. ' + c.parsed.y.toLocaleString('es-PE',{minimumFractionDigits:2}); } } }
          }
        }
      });
    }
    <?php endif; ?>

    var ctxP = document.getElementById('chartCategorias');
    if (ctxP) {
      window._chartCat = new Chart(ctxP.getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: <?= json_encode($nombres_categorias) ?>,
          datasets: [{ data: <?= json_encode($cantidades_cat) ?>, backgroundColor: COLS, borderWidth: 3, borderColor: borderCol, hoverOffset: 10 }]
        },
        options: {
          maintainAspectRatio: false, responsive: true, cutout: '70%',
          plugins: {
            legend: { position: 'bottom', labels: { color: legendColor, font: { size: 11 }, usePointStyle: true, pointStyleWidth: 10, padding: 14 } },
            tooltip: { callbacks: { label: function(c){ return c.label + ': ' + c.parsed + ' producto(s)'; } } }
          }
        }
      });
    }
  }
  buildCharts();

  /* ── MODAL ACCESO DENEGADO ───────────────────────────────────────────────── */
  <?php if(isset($_GET['error']) && $_GET['error']==='sin_permiso'): ?>
  (function(){
    var ks = document.createElement('style');
    ks.textContent =
      '@keyframes adOvIn{from{opacity:0}to{opacity:1}}@keyframes adOvOut{from{opacity:1}to{opacity:0}}' +
      '@keyframes adCardIn{from{opacity:0;transform:scale(.82) translateY(40px)}to{opacity:1;transform:scale(1) translateY(0)}}' +
      '@keyframes adCardOut{from{opacity:1;transform:scale(1)}to{opacity:0;transform:scale(.88) translateY(-20px)}}' +
      '@keyframes adRingPulse{0%{transform:scale(1);opacity:.6}100%{transform:scale(1.6);opacity:0}}' +
      '@keyframes adScan{0%{top:-10%}100%{top:110%}}' +
      '@keyframes adGrid{from{background-position:0 0}to{background-position:28px 28px}}';
    document.head.appendChild(ks);

    var ov = document.createElement('div');
    ov.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.85);backdrop-filter:blur(12px);animation:adOvIn .3s ease both;';

    var card = document.createElement('div');
    card.style.cssText = 'position:relative;width:420px;max-width:94vw;background:#0d1117;border:1px solid rgba(239,68,68,.2);border-radius:20px;overflow:hidden;box-shadow:0 0 0 1px rgba(239,68,68,.08),0 30px 80px rgba(0,0,0,.9);animation:adCardIn .5s cubic-bezier(.22,1,.36,1) .06s both;';

    var hdr = document.createElement('div');
    hdr.style.cssText = 'position:relative;background:linear-gradient(135deg,#1a0a0a,#2d0f0f);padding:36px 28px 28px;text-align:center;overflow:hidden;';

    var grid = document.createElement('div');
    grid.style.cssText = 'position:absolute;inset:0;background-image:linear-gradient(rgba(239,68,68,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(239,68,68,.06) 1px,transparent 1px);background-size:28px 28px;animation:adGrid 20s linear infinite;';
    hdr.appendChild(grid);

    var scan = document.createElement('div');
    scan.style.cssText = 'position:absolute;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(239,68,68,.35),transparent);animation:adScan 3s ease-in-out infinite;pointer-events:none;';
    hdr.appendChild(scan);

    var iw = document.createElement('div');
    iw.style.cssText = 'position:relative;width:80px;height:80px;margin:0 auto 16px;z-index:2;';
    iw.innerHTML =
      '<div style="position:absolute;inset:-8px;border-radius:50%;border:1px solid rgba(239,68,68,.3);animation:adRingPulse 2s ease-out infinite;"></div>' +
      '<div style="position:absolute;inset:-18px;border-radius:50%;border:1px solid rgba(239,68,68,.15);animation:adRingPulse 2s ease-out infinite;animation-delay:.4s;"></div>' +
      '<div style="width:80px;height:80px;border-radius:50%;background:rgba(239,68,68,.1);border:2px solid rgba(239,68,68,.4);display:flex;align-items:center;justify-content:center;box-shadow:0 0 30px rgba(239,68,68,.2);">' +
      '<i class="fas fa-ban" style="color:#f87171;font-size:2rem;"></i></div>';

    var t1 = document.createElement('div');
    t1.style.cssText = 'position:relative;z-index:2;font-size:.6rem;font-weight:700;letter-spacing:2.5px;color:rgba(248,113,113,.6);text-transform:uppercase;margin-bottom:6px;';
    t1.textContent = 'ACCESO RESTRINGIDO';

    var t2 = document.createElement('div');
    t2.style.cssText = 'position:relative;z-index:2;font-size:1.4rem;font-weight:800;color:#fff;';
    t2.textContent = 'Acceso Denegado';

    <?php $mod_bloqueado = htmlspecialchars($_GET['modulo'] ?? 'módulo'); ?>
    var mb = document.createElement('div');
    mb.style.cssText = 'position:relative;z-index:2;display:inline-flex;align-items:center;gap:6px;margin-top:10px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:20px;padding:4px 14px;font-size:.72rem;font-weight:700;color:#f87171;font-family:monospace;';
    mb.innerHTML = '<i class="fas fa-lock" style="font-size:.65rem;"></i> <?= $mod_bloqueado ?>';

    hdr.appendChild(iw); hdr.appendChild(t1); hdr.appendChild(t2); hdr.appendChild(mb);

    var body = document.createElement('div');
    body.style.cssText = 'padding:20px 26px;background:#0d1117;';
    body.innerHTML =
      '<div style="display:flex;align-items:flex-start;gap:12px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15);border-left:3px solid #ef4444;border-radius:10px;padding:14px 16px;">' +
      '<div style="width:36px;height:36px;border-radius:9px;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">' +
      '<i class="fas fa-shield-alt" style="color:#f87171;font-size:.9rem;"></i></div>' +
      '<div><div style="font-size:.7rem;font-weight:700;color:#f87171;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">Sin autorización</div>' +
      '<div style="font-size:.84rem;color:#64748b;line-height:1.5;">No tienes permiso para acceder a este módulo. Contacta al administrador del sistema para solicitar acceso.</div></div></div>';

    var ftr = document.createElement('div');
    ftr.style.cssText = 'padding:0 26px 24px;';
    var btn2 = document.createElement('button');
    btn2.style.cssText = 'width:100%;padding:13px;border:none;border-radius:12px;cursor:pointer;background:linear-gradient(135deg,#1d4ed8,#6366f1);color:#fff;font-size:.9rem;font-weight:800;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 20px rgba(99,102,241,.4);transition:transform .2s,box-shadow .2s;margin-top:16px;';
    btn2.innerHTML = '<i class="fas fa-arrow-left"></i> Volver al Panel de Control';
    btn2.onmouseover = function(){ this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 28px rgba(99,102,241,.6)'; };
    btn2.onmouseout  = function(){ this.style.transform=''; this.style.boxShadow='0 4px 20px rgba(99,102,241,.4)'; };
    ftr.appendChild(btn2);

    card.appendChild(hdr); card.appendChild(body); card.appendChild(ftr);
    ov.appendChild(card); document.body.appendChild(ov);

    function closeAd(){
      ov.style.animation='adOvOut .3s ease forwards';
      card.style.animation='adCardOut .3s ease forwards';
      setTimeout(function(){ if(ov.parentNode) ov.parentNode.removeChild(ov); }, 320);
      // Limpiar la URL para que no reaparezca al recargar
      if(window.history && window.history.replaceState){
        window.history.replaceState({}, document.title, '/sysinversioneschcomputer/public/dashboard.php');
      }
    }
    btn2.addEventListener('click', closeAd);
    ov.addEventListener('click', function(e){ if(e.target===ov) closeAd(); });
  })();
  <?php endif; ?>

}); // DOMContentLoaded

<?php
if(isset($_SESSION['swal_login'])):
  $sd  = $_SESSION['swal_login']; unset($_SESSION['swal_login']);
  $rN  = [1=>'Administrador', 2=>'Asesor Comercial', 3=>'Técnico'];
  $rI  = [1=>'fa-user-shield', 2=>'fa-user-tie', 3=>'fa-tools'];
  $rG  = [1=>'linear-gradient(135deg,#1d4ed8,#6366f1)', 2=>'linear-gradient(135deg,#0e7490,#06b6d4)', 3=>'linear-gradient(135deg,#065f46,#10b981)'];
  $rNom = $rN[$sd['rol']] ?? 'Usuario';
  $rIco = $rI[$sd['rol']] ?? 'fa-user';
  $rCol = $rG[$sd['rol']] ?? $rG[1];
  $pN   = addslashes(htmlspecialchars(explode(' ', trim($sd['nombre']))[0]));
  $fN   = addslashes(htmlspecialchars($sd['nombre']));
  $h    = (int)date('H');
  if($h>=5&&$h<12){$sal='Buenos días';$emo='☀️';}
  elseif($h>=12&&$h<19){$sal='Buenas tardes';$emo='🌤️';}
  else{$sal='Buenas noches';$emo='🌙';}
  $fec = date('d/m/Y'); $hor = date('H:i');
?>
(function(){
  var G='<?= $rCol ?>';
  var ks=document.createElement('style');
  ks.textContent='@keyframes wOvIn{from{opacity:0}to{opacity:1}}@keyframes wOvOut{from{opacity:1}to{opacity:0}}'+
    '@keyframes wCardIn{from{opacity:0;transform:scale(.82) translateY(40px)}to{opacity:1;transform:scale(1) translateY(0)}}'+
    '@keyframes wCardOut{from{opacity:1;transform:scale(1)}to{opacity:0;transform:scale(.88) translateY(-24px)}}'+
    '@keyframes wFadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}'+
    '@keyframes wFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}';
  document.head.appendChild(ks);
  var ov=document.createElement('div');
  ov.style.cssText='position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(8,16,40,.8);backdrop-filter:blur(10px);animation:wOvIn .3s ease both;';
  var card=document.createElement('div');
  card.style.cssText='width:400px;max-width:95vw;background:#0f1623;border:1px solid rgba(255,255,255,.1);border-radius:22px;overflow:hidden;box-shadow:0 40px 100px rgba(0,0,0,.7);animation:wCardIn .55s cubic-bezier(.22,1,.36,1) .08s both;';
  var hdr=document.createElement('div');
  hdr.style.cssText='background:'+G+';padding:30px 28px 24px;text-align:center;';
  hdr.innerHTML='<div style="width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.15);border:3px solid rgba(255,255,255,.4);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;animation:wFloat 3s ease-in-out infinite;">'+
    '<i class="fas <?= $rIco ?>" style="color:#fff;font-size:1.8rem;"></i></div>'+
    '<div style="color:rgba(255,255,255,.75);font-size:.72rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px;"><?= $emo ?> <?= $sal ?></div>'+
    '<div style="color:#fff;font-size:1.5rem;font-weight:800;"><?= $pN ?>!</div>'+
    '<div style="display:inline-flex;align-items:center;gap:6px;margin-top:8px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:20px;padding:4px 14px;font-size:.73rem;font-weight:700;color:#fff;">'+
    '<i class="fas <?= $rIco ?>"></i> <?= $rNom ?></div>';
  var body=document.createElement('div');
  body.style.cssText='padding:18px 22px 10px;';
  body.innerHTML='<div style="background:rgba(59,130,246,.1);border-radius:12px;padding:12px 14px;margin-bottom:10px;border-left:3px solid #3b82f6;animation:wFadeUp .4s .4s ease both;opacity:0;animation-fill-mode:both;">'+
    '<div style="font-size:.65rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.6px;margin-bottom:2px;">Sesión iniciada como</div>'+
    '<div style="font-size:.9rem;font-weight:800;color:#f1f5f9;"><?= $fN ?></div></div>'+
    '<div style="display:flex;gap:8px;animation:wFadeUp .4s .5s ease both;opacity:0;animation-fill-mode:both;">'+
    '<div style="flex:1;background:rgba(255,255,255,.04);border-radius:10px;padding:10px;border:1px solid rgba(255,255,255,.07);text-align:center;">'+
    '<div style="font-size:.6rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:2px;"><i class="fas fa-calendar-alt" style="color:#3b82f6;margin-right:2px;"></i>Fecha</div>'+
    '<div style="font-size:.86rem;font-weight:700;color:#f1f5f9;"><?= $fec ?></div></div>'+
    '<div style="flex:1;background:rgba(255,255,255,.04);border-radius:10px;padding:10px;border:1px solid rgba(255,255,255,.07);text-align:center;">'+
    '<div style="font-size:.6rem;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:2px;"><i class="fas fa-clock" style="color:#3b82f6;margin-right:2px;"></i>Hora</div>'+
    '<div id="wClock" style="font-size:.86rem;font-weight:700;color:#f1f5f9;font-family:monospace;"><?= $hor ?></div></div></div>';
  var prog=document.createElement('div');
  prog.style.cssText='padding:10px 22px;animation:wFadeUp .4s .6s ease both;opacity:0;animation-fill-mode:both;';
  prog.innerHTML='<div style="display:flex;justify-content:space-between;margin-bottom:4px;">'+
    '<span style="font-size:.67rem;color:#64748b;"><i class="fas fa-hourglass-half mr-1"></i>Se cierra automáticamente</span>'+
    '<span id="wCd" style="font-size:.68rem;font-weight:800;color:#3b82f6;background:rgba(59,130,246,.15);border-radius:20px;padding:1px 8px;">6s</span></div>'+
    '<div style="height:4px;background:rgba(255,255,255,.08);border-radius:99px;overflow:hidden;">'+
    '<div id="wBar" style="height:100%;width:100%;background:'+G+';border-radius:99px;transition:width .1s linear;"></div></div>';
  var ftr=document.createElement('div');
  ftr.style.cssText='padding:10px 22px 22px;animation:wFadeUp .4s .65s ease both;opacity:0;animation-fill-mode:both;';
  var btnW=document.createElement('button');
  btnW.style.cssText='width:100%;padding:12px;border:none;border-radius:12px;cursor:pointer;background:'+G+';color:#fff;font-size:.87rem;font-weight:800;font-family:inherit;box-shadow:0 6px 20px rgba(0,0,0,.4);';
  btnW.innerHTML='<i class="fas fa-rocket mr-2"></i>Ir al Panel de Control';
  ftr.appendChild(btnW);
  card.appendChild(hdr);card.appendChild(body);card.appendChild(prog);card.appendChild(ftr);
  ov.appendChild(card);document.body.appendChild(ov);
  var clk=document.getElementById('wClock');
  setInterval(function(){var n=new Date();if(clk)clk.textContent=String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0')+':'+String(n.getSeconds()).padStart(2,'0');},1000);
  var tot=6000,el=0,st=50,cd=document.getElementById('wCd'),bar=document.getElementById('wBar');
  var tmr=setInterval(function(){el+=st;var p=Math.max(0,100-(el/tot*100));if(bar)bar.style.width=p+'%';if(cd)cd.textContent=Math.ceil((tot-el)/1000)+'s';if(el>=tot){clearInterval(tmr);closeW();}},st);
  function closeW(){clearInterval(tmr);ov.style.animation='wOvOut .3s ease forwards';card.style.animation='wCardOut .3s ease forwards';setTimeout(function(){if(ov.parentNode)ov.parentNode.removeChild(ov);},320);}
  btnW.addEventListener('click',closeW);
  ov.addEventListener('click',function(e){if(e.target===ov)closeW();});
})();
<?php endif; ?>
</script>
</body>
</html>