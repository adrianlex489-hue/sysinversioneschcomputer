<?php
// ============================================================
// modules/Caja/reporte_cajas.php | SysInversiones CH Computer 2026
// Reporte consolidado de todas las cajas aperturadas
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';
if (!isset($pdo)) die('Sin BD');
if (!defined('ROL_ADMINISTRADOR'))    define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR_COMERCIAL')) define('ROL_ASESOR_COMERCIAL', 2);
if (!defined('ROL_TECNICO'))          define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);
verificarPermiso($pdo, 'historial_caja');

$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
$id_rol     = (int)($_SESSION['id_rol']     ?? 0);
$es_admin   = ($id_rol === ROL_ADMINISTRADOR);

// ── Filtros GET ───────────────────────────────────────────────────────────────
$f_desde   = $_GET['desde']      ?? date('Y-m-01');
$f_hasta   = $_GET['hasta']      ?? date('Y-m-d');
$f_estado  = $_GET['estado']     ?? 'all';
$f_usuario = (int)($_GET['id_usuario'] ?? 0);

// ── Construir WHERE ───────────────────────────────────────────────────────────
$wheres = ["DATE(c.fecha_apertura) BETWEEN ? AND ?"];
$params = [$f_desde, $f_hasta];

if ($f_estado !== 'all') { $wheres[] = 'c.estado = ?'; $params[] = $f_estado; }
if (!$es_admin)          { $wheres[] = 'c.id_usuario = ?'; $params[] = $id_usuario; }
elseif ($f_usuario > 0)  { $wheres[] = 'c.id_usuario = ?'; $params[] = $f_usuario; }

$where_sql = 'WHERE ' . implode(' AND ', $wheres);

// ── Consulta principal ────────────────────────────────────────────────────────
$cajas = [];
try {
    $sql = "SELECT c.id_caja, c.nombre, c.fecha_apertura, c.fecha_cierre,
                   c.monto_inicial, c.monto_final, c.monto_esperado,
                   COALESCE(c.diferencia,0) AS diferencia,
                   c.estado, c.observacion,
                   u.nombre_completo,
                   COALESCE(TIMESTAMPDIFF(MINUTE, c.fecha_apertura, IFNULL(c.fecha_cierre, NOW())),0) AS duracion_min,
                   (SELECT COALESCE(SUM(m.monto),0) FROM movimientos_caja m WHERE m.id_caja=c.id_caja AND m.tipo='ingreso') AS total_ingresos,
                   (SELECT COALESCE(SUM(m.monto),0) FROM movimientos_caja m WHERE m.id_caja=c.id_caja AND m.tipo='egreso')  AS total_egresos,
                   (SELECT COUNT(*) FROM movimientos_caja m WHERE m.id_caja=c.id_caja) AS total_movimientos
            FROM caja c
            JOIN usuarios u ON u.id_usuario = c.id_usuario
            $where_sql
            ORDER BY c.fecha_apertura DESC";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $cajas = $st->fetchAll();
} catch (PDOException $e) {
    $cajas = [];
}

// ── Estadísticas consolidadas ─────────────────────────────────────────────────
$cerradas = array_filter($cajas, fn($c) => $c['estado'] === 'cerrada');
$stats = [
    'total'          => count($cajas),
    'cerradas'       => count($cerradas),
    'abiertas'       => count(array_filter($cajas, fn($c) => $c['estado'] === 'abierta')),
    'total_ingresos' => array_sum(array_column($cajas, 'total_ingresos')),
    'total_egresos'  => array_sum(array_column($cajas, 'total_egresos')),
    'con_diferencia' => count(array_filter($cerradas, fn($c) => abs((float)$c['diferencia']) > 0.01)),
];
$stats['neto'] = $stats['total_ingresos'] - $stats['total_egresos'];

// ── Lista de cajeros: solo usuarios con permiso 'caja' activo ─────────────────
$usuarios_lista = [];
if ($es_admin) {
    try {
        // Usuarios que tienen permiso de caja explícito O sin permisos configurados (acceso total)
        $usuarios_lista = $pdo->query("
            SELECT DISTINCT u.id_usuario, u.nombre_completo, u.estado AS activo
            FROM usuarios u
            WHERE u.estado = 1
              AND (
                  -- Tiene permiso de caja explícitamente habilitado
                  EXISTS (
                      SELECT 1 FROM permisos_usuario p
                      WHERE p.id_usuario = u.id_usuario
                        AND p.modulo = 'caja'
                        AND p.permitido = 1
                  )
                  OR
                  -- No tiene ningún permiso configurado (acceso total por defecto)
                  NOT EXISTS (
                      SELECT 1 FROM permisos_usuario p
                      WHERE p.id_usuario = u.id_usuario
                  )
              )
            ORDER BY u.nombre_completo ASC
        ")->fetchAll();
    } catch (PDOException $e) {
        // Fallback: todos los usuarios activos
        try {
            $usuarios_lista = $pdo->query("
                SELECT id_usuario, nombre_completo
                FROM usuarios WHERE estado = 1
                ORDER BY nombre_completo
            ")->fetchAll();
        } catch (PDOException $e2) {}
    }
}

$extra_css = '<link rel="stylesheet" href="/sysinversioneschcomputer/modules/Caja/css/caja.css?v=' . time() . '">
              <link rel="stylesheet" href="/sysinversioneschcomputer/modules/Caja/css/reporte_cajas.css?v=' . time() . '">';
$extra_js  = '<script src="/sysinversioneschcomputer/modules/Caja/js/reporte_cajas.js?v=' . time() . '"></script>';
include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<div class="content-wrapper">
<div class="content-header"><div class="container-fluid">
<div class="cx-page-header d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h4><i class="fas fa-chart-line mr-2"></i>Reporte de Cajas</h4>
        <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones &rsaquo; Caja &rsaquo; Reporte Consolidado</small>
    </div>
    <div class="d-flex align-items-center flex-wrap" style="gap:8px;">
        <a href="historial_caja.php" class="cx-btn cx-btn-ghost"><i class="fas fa-history mr-1"></i>Historial</a>
        <a href="caja.php" class="cx-btn cx-btn-ghost"><i class="fas fa-cash-register mr-1"></i>Ir a Caja</a>
        <button type="button" id="btnExportarReporte" class="cx-btn cx-btn-ghost">
            <i class="fas fa-download mr-1"></i>Exportar
        </button>
    </div>
</div>
</div></div>
<div class="content"><div class="container-fluid">

<!-- ── FILTROS ── -->
<form method="GET" id="formFiltros" class="rc-filtros-card mb-4">

    <!-- Título + indicador de filtros activos -->
    <div class="rc-filtros-header">
        <div class="rc-filtros-titulo">
            <i class="fas fa-sliders-h mr-2"></i>Filtros del Reporte
        </div>
        <div class="rc-filtros-periodo-badge">
            <i class="fas fa-calendar-alt mr-1"></i>
            <?= date('d/m/Y', strtotime($f_desde)) ?> &nbsp;→&nbsp; <?= date('d/m/Y', strtotime($f_hasta)) ?>
            <?php if ($f_estado !== 'all'): ?>
            <span class="rc-filtros-sep">·</span>
            <i class="fas fa-circle mr-1" style="font-size:.45rem;vertical-align:middle;"></i><?= ucfirst($f_estado) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Campos -->
    <div class="rc-filtros-campos">

        <!-- Rango de fechas como bloque unificado -->
        <div class="rc-filtros-grupo">
            <div class="rc-filtros-grupo-label"><i class="fas fa-calendar-alt mr-1"></i>Período</div>
            <div class="rc-filtros-rango">
                <div class="rc-filtros-input-wrap">
                    <span class="rc-filtros-input-icon"><i class="fas fa-calendar-day"></i></span>
                    <input type="date" name="desde" class="rc-filtros-input" value="<?= htmlspecialchars($f_desde) ?>">
                </div>
                <span class="rc-filtros-rango-sep"><i class="fas fa-arrow-right"></i></span>
                <div class="rc-filtros-input-wrap">
                    <span class="rc-filtros-input-icon"><i class="fas fa-calendar-day"></i></span>
                    <input type="date" name="hasta" class="rc-filtros-input" value="<?= htmlspecialchars($f_hasta) ?>">
                </div>
            </div>
        </div>

        <!-- Estado -->
        <div class="rc-filtros-grupo">
            <div class="rc-filtros-grupo-label"><i class="fas fa-toggle-on mr-1"></i>Estado</div>
            <div class="rc-filtros-input-wrap">
                <span class="rc-filtros-input-icon"><i class="fas fa-filter"></i></span>
                <select name="estado" class="rc-filtros-input rc-filtros-select">
                    <option value="all"     <?= $f_estado==='all'     ?'selected':'' ?>>Todos</option>
                    <option value="cerrada" <?= $f_estado==='cerrada' ?'selected':'' ?>>Cerradas</option>
                    <option value="abierta" <?= $f_estado==='abierta' ?'selected':'' ?>>Abiertas</option>
                </select>
            </div>
        </div>

        <?php if ($es_admin): ?>
        <!-- Cajero — modal selector -->
        <div class="rc-filtros-grupo">
            <div class="rc-filtros-grupo-label"><i class="fas fa-user-tie mr-1"></i>Cajero</div>

            <?php
            $cajero_sel_nombre = 'Todos los cajeros';
            $cajero_sel_ini    = '';
            $cajero_sel_color  = '';
            $colores = ['#0369a1','#0f766e','#7c3aed','#b45309','#be185d','#0891b2','#15803d'];
            if ($f_usuario > 0) {
                foreach ($usuarios_lista as $u) {
                    if ((int)$u['id_usuario'] === $f_usuario) {
                        $cajero_sel_nombre = $u['nombre_completo'];
                        $partes = explode(' ', trim($u['nombre_completo']));
                        foreach (array_slice($partes,0,2) as $p) $cajero_sel_ini .= strtoupper(mb_substr($p,0,1));
                        $cajero_sel_color = $colores[abs(crc32($u['nombre_completo'])) % count($colores)];
                        break;
                    }
                }
            }
            ?>

            <input type="hidden" name="id_usuario" id="cajeroValor" value="<?= $f_usuario ?>">

            <!-- Botón que abre el modal -->
            <button type="button" class="rc-cajero-trigger" data-toggle="modal" data-target="#modalSelCajero">
                <?php if ($cajero_sel_ini): ?>
                <div class="rc-cajero-avatar" style="background:<?= $cajero_sel_color ?>;"><?= htmlspecialchars($cajero_sel_ini) ?></div>
                <?php else: ?>
                <div class="rc-cajero-avatar rc-cajero-avatar-all"><i class="fas fa-users"></i></div>
                <?php endif; ?>
                <span id="cajeroNombreDisplay"><?= htmlspecialchars($cajero_sel_nombre) ?></span>
                <i class="fas fa-chevron-down" style="font-size:.65rem;color:#94a3b8;margin-left:auto;"></i>
            </button>

            <div style="font-size:.68rem;color:#94a3b8;margin-top:3px;">
                <i class="fas fa-shield-alt mr-1" style="color:#10b981;"></i><?= count($usuarios_lista) ?> cajero<?= count($usuarios_lista)!==1?'s':'' ?> con permiso de caja
            </div>
        </div>
        <?php endif; ?>

        <!-- Acciones -->
        <div class="rc-filtros-grupo rc-filtros-acciones">
            <div class="rc-filtros-grupo-label">&nbsp;</div>
            <div class="d-flex" style="gap:6px;">
                <button type="submit" class="rc-btn-aplicar">
                    <i class="fas fa-search mr-1"></i>Aplicar
                </button>
                <a href="reporte_cajas.php" class="rc-btn-limpiar" title="Limpiar filtros">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </div>

    </div>

    <!-- Chips de período rápido -->
    <div class="rc-chips-wrap">
        <span class="rc-chips-label"><i class="fas fa-bolt mr-1"></i>Acceso rápido:</span>
        <?php
        $chips = [
            ['label'=>'Hoy',         'desde'=>date('Y-m-d'),                                          'hasta'=>date('Y-m-d')],
            ['label'=>'Esta semana', 'desde'=>date('Y-m-d',strtotime('monday this week')),             'hasta'=>date('Y-m-d')],
            ['label'=>'Este mes',    'desde'=>date('Y-m-01'),                                          'hasta'=>date('Y-m-d')],
            ['label'=>'Mes anterior','desde'=>date('Y-m-01',strtotime('first day of last month')),     'hasta'=>date('Y-m-t',strtotime('first day of last month'))],
            ['label'=>'Este año',    'desde'=>date('Y-01-01'),                                         'hasta'=>date('Y-m-d')],
        ];
        foreach ($chips as $chip):
            $activo = ($f_desde === $chip['desde'] && $f_hasta === $chip['hasta']) ? 'activo' : '';
        ?>
        <a href="?desde=<?= $chip['desde'] ?>&hasta=<?= $chip['hasta'] ?>" class="rc-chip <?= $activo ?>">
            <?= $chip['label'] ?>
        </a>
        <?php endforeach; ?>
    </div>

<!-- ── KPIs CONSOLIDADOS ── -->
<div class="row mb-4 mt-2">
    <div class="col-6 col-md-3 mb-3">
        <div class="rc-kpi" style="--kc:#0ea5e9;background:linear-gradient(135deg,#0c1a3a,#0f3460);">
            <i class="fas fa-cash-register"></i>
            <div><div class="rc-kpi-val"><?= $stats['total'] ?></div><div class="rc-kpi-lbl">Cajas en período</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="rc-kpi" style="--kc:#10b981;background:linear-gradient(135deg,#047857,#10b981);">
            <i class="fas fa-arrow-up"></i>
            <div><div class="rc-kpi-val">S/. <?= number_format($stats['total_ingresos'],2) ?></div><div class="rc-kpi-lbl">Total ingresos</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="rc-kpi" style="--kc:#ef4444;background:linear-gradient(135deg,#b91c1c,#ef4444);">
            <i class="fas fa-arrow-down"></i>
            <div><div class="rc-kpi-val">S/. <?= number_format($stats['total_egresos'],2) ?></div><div class="rc-kpi-lbl">Total egresos</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="rc-kpi" style="--kc:<?= $stats['neto']>=0?'#10b981':'#ef4444' ?>;background:<?= $stats['neto']>=0?'linear-gradient(135deg,#047857,#10b981)':'linear-gradient(135deg,#b91c1c,#ef4444)' ?>;">
            <i class="fas fa-coins"></i>
            <div><div class="rc-kpi-val"><?= $stats['neto']>=0?'+':'' ?>S/. <?= number_format($stats['neto'],2) ?></div><div class="rc-kpi-lbl">Neto del período</div></div>
        </div>
    </div>
</div>

<!-- ── RESUMEN DEL PERÍODO ── -->
<div class="row mb-4 justify-content-end">
    <div class="col-md-4 mb-3">
        <div class="rc-resumen-card">
            <div class="rc-desglose-hdr"><i class="fas fa-info-circle mr-2"></i>Resumen del período</div>
            <div style="padding:14px 16px;">
                <div class="rc-resumen-row"><span>Cajas cerradas</span><strong style="color:#059669;"><?= $stats['cerradas'] ?></strong></div>
                <div class="rc-resumen-row"><span>Cajas abiertas</span><strong style="color:#0ea5e9;"><?= $stats['abiertas'] ?></strong></div>
                <div class="rc-resumen-row"><span>Con diferencia</span><strong style="color:<?= $stats['con_diferencia']>0?'#dc2626':'#059669' ?>;"><?= $stats['con_diferencia'] ?></strong></div>
                <div class="rc-resumen-row"><span>Período</span><strong style="font-size:.78rem;"><?= date('d/m/Y',strtotime($f_desde)) ?> — <?= date('d/m/Y',strtotime($f_hasta)) ?></strong></div>
            </div>
        </div>
    </div>
</div>

<!-- ── TABLA DETALLADA ── -->
<div class="rc-tabla-wrap mb-4">
    <div class="rc-tabla-hdr">
        <span><i class="fas fa-list-ul mr-2"></i>Detalle por caja — <?= count($cajas) ?> registros</span>
        <div class="d-flex align-items-center" style="gap:8px;">
            <input type="text" id="rcBuscar" placeholder="Buscar..." class="rc-buscar-input">
        </div>
    </div>
    <div class="table-responsive">
        <table id="tablaReporteCajas" class="table table-cx table-bordered table-hover table-sm mb-0">
            <thead><tr>
                <th class="text-center">#</th>
                <th>Cajero</th>
                <th>Nombre caja</th>
                <th>Apertura</th>
                <th>Cierre</th>
                <th class="text-center">Duración</th>
                <th class="text-right">Fondo</th>
                <th class="text-right">Ingresos</th>
                <th class="text-right">Egresos</th>
                <th class="text-right">Neto</th>
                <th class="text-center">Diferencia</th>
                <th class="text-center">Estado</th>
                <th class="text-center" style="width:60px;">Ver</th>
            </tr></thead>
            <tbody>
            <?php foreach ($cajas as $c):
                $dif     = (float)$c['diferencia'];
                $neto_c  = (float)$c['total_ingresos'] - (float)$c['total_egresos'];
                $dur_min = (int)$c['duracion_min'];
                $dur_txt = $dur_min >= 60
                    ? floor($dur_min/60).'h '.($dur_min%60).'m'
                    : $dur_min.'m';
                $dif_class = abs($dif)<0.01 ? 'cx-dif-ok' : ($dif>0 ? 'cx-dif-pos' : 'cx-dif-neg');
                $dif_txt   = abs($dif)<0.01
                    ? '<i class="fas fa-check-circle mr-1"></i>Exacto'
                    : ($dif>0 ? '+S/. '.number_format($dif,2) : '−S/. '.number_format(abs($dif),2));
            ?>
            <tr class="rc-fila" data-search="<?= strtolower(htmlspecialchars($c['nombre_completo'].' '.$c['nombre'])) ?>">
                <td class="text-center"><span class="cx-hist-num"><?= $c['id_caja'] ?></span></td>
                <td>
                    <div style="font-weight:700;font-size:.83rem;color:#1e293b;"><?= htmlspecialchars($c['nombre_completo']) ?></div>
                    <div style="font-size:.7rem;color:#94a3b8;"><?= $c['total_movimientos'] ?> movimientos</div>
                </td>
                <td><span class="cx-hist-nombre"><?= htmlspecialchars($c['nombre'] ?? 'Caja #'.$c['id_caja']) ?></span></td>
                <td style="font-size:.82rem;">
                    <div style="font-weight:600;"><?= date('d/m/Y',strtotime($c['fecha_apertura'])) ?></div>
                    <div style="color:#94a3b8;font-size:.7rem;"><?= date('H:i',strtotime($c['fecha_apertura'])) ?></div>
                </td>
                <td style="font-size:.82rem;">
                    <?php if ($c['fecha_cierre']): ?>
                    <div style="font-weight:600;"><?= date('d/m/Y',strtotime($c['fecha_cierre'])) ?></div>
                    <div style="color:#94a3b8;font-size:.7rem;"><?= date('H:i',strtotime($c['fecha_cierre'])) ?></div>
                    <?php else: ?><span style="color:#94a3b8;">—</span><?php endif; ?>
                </td>
                <td class="text-center"><span class="rc-dur-badge"><?= $dur_txt ?></span></td>
                <td class="text-right" style="font-family:monospace;font-weight:700;color:#0369a1;font-size:.83rem;white-space:nowrap;">S/. <?= number_format($c['monto_inicial'],2) ?></td>
                <td class="text-right" style="font-family:monospace;font-weight:700;color:#059669;font-size:.83rem;white-space:nowrap;">+S/. <?= number_format($c['total_ingresos'],2) ?></td>
                <td class="text-right" style="font-family:monospace;font-weight:700;color:#dc2626;font-size:.83rem;white-space:nowrap;">−S/. <?= number_format($c['total_egresos'],2) ?></td>
                <td class="text-right" style="font-family:monospace;font-weight:800;color:<?= $neto_c>=0?'#059669':'#dc2626' ?>;font-size:.85rem;white-space:nowrap;">
                    <?= $neto_c>=0?'+':'' ?>S/. <?= number_format($neto_c,2) ?>
                </td>
                <td class="text-center"><span class="<?= $dif_class ?>"><?= $dif_txt ?></span></td>
                <td class="text-center">
                    <?php if ($c['estado']==='abierta'): ?>
                    <span class="cx-badge-abierta"><i class="fas fa-circle" style="font-size:.45rem;"></i>Abierta</span>
                    <?php else: ?>
                    <span class="cx-badge-cerrada"><i class="fas fa-lock" style="font-size:.7rem;"></i>Cerrada</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <a href="detalle_caja_reporte.php?id_caja=<?= $c['id_caja'] ?>" title="Ver desglose completo"
                       style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;background:linear-gradient(135deg,#0369a1,#0ea5e9);color:#fff;border-radius:7px;text-decoration:none;font-size:.78rem;">
                        <i class="fas fa-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($cajas)): ?>
            <tr><td colspan="13" class="text-center py-5 text-muted">
                <i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>
                No hay cajas en el período seleccionado
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div></div></div>

<!-- ══ MODAL SELECTOR DE CAJERO ══ -->
<div class="modal fade" id="modalSelCajero" tabindex="-1" aria-hidden="true">
<div class="modal-dialog" style="max-width:420px;">
<div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;box-shadow:0 24px 60px rgba(0,0,0,.22);">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#0c1a3a,#0f3460);padding:18px 20px;display:flex;align-items:center;justify-content:space-between;">
        <div>
            <h5 style="color:#fff;font-weight:700;margin:0;font-size:1rem;"><i class="fas fa-user-tie mr-2"></i>Seleccionar Cajero</h5>
            <small style="color:rgba(255,255,255,.55);font-size:.73rem;">Solo usuarios con permiso de caja</small>
        </div>
        <button type="button" class="close" style="color:#fff;opacity:.7;text-shadow:none;font-size:1.4rem;" data-dismiss="modal"><span>&times;</span></button>
    </div>

    <!-- Buscador -->
    <div style="padding:14px 16px 8px;border-bottom:1px solid #f1f5f9;background:#f8fafc;">
        <div style="position:relative;">
            <i class="fas fa-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.78rem;"></i>
            <input type="text" id="cajeroModalBuscar" placeholder="Buscar cajero..."
                style="width:100%;height:36px;border:1.5px solid #e2e8f0;border-radius:9px;padding:0 12px 0 32px;font-size:.83rem;outline:none;background:#fff;transition:border-color .15s;"
                onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
        </div>
        <div style="font-size:.7rem;color:#94a3b8;margin-top:6px;">
            <i class="fas fa-shield-alt mr-1" style="color:#10b981;"></i>
            <?= count($usuarios_lista) ?> cajero<?= count($usuarios_lista)!==1?'s':'' ?> con permiso activo
        </div>
    </div>

    <!-- Lista -->
    <div style="max-height:380px;overflow-y:auto;" id="cajeroModalLista">

        <!-- Opción Todos -->
        <div class="rc-modal-cajero-item <?= $f_usuario==0?'rc-modal-cajero-activo':'' ?>"
             data-id="0" data-nombre="Todos los cajeros" data-ini="" data-color="">
            <div class="rc-modal-cajero-avatar" style="background:linear-gradient(135deg,#475569,#94a3b8);">
                <i class="fas fa-users"></i>
            </div>
            <div class="rc-modal-cajero-info">
                <div class="rc-modal-cajero-nombre">Todos los cajeros</div>
                <div class="rc-modal-cajero-sub">Ver cajas de todos los cajeros</div>
            </div>
            <?php if ($f_usuario==0): ?>
            <div class="rc-modal-cajero-check"><i class="fas fa-check-circle"></i></div>
            <?php endif; ?>
        </div>

        <?php if (!empty($usuarios_lista)): ?>
        <div style="padding:6px 16px 4px;font-size:.68rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;background:#f8fafc;border-bottom:1px solid #f1f5f9;">
            <i class="fas fa-cash-register mr-1" style="color:#0ea5e9;"></i>Cajeros con permiso
        </div>
        <?php endif; ?>

        <?php
        $colores = ['#0369a1','#0f766e','#7c3aed','#b45309','#be185d','#0891b2','#15803d'];
        foreach ($usuarios_lista as $u):
            $ini = '';
            $partes = explode(' ', trim($u['nombre_completo']));
            foreach (array_slice($partes,0,2) as $p) $ini .= strtoupper(mb_substr($p,0,1));
            $color  = $colores[abs(crc32($u['nombre_completo'])) % count($colores)];
            $activo = ((int)$u['id_usuario'] === $f_usuario);
        ?>
        <div class="rc-modal-cajero-item <?= $activo?'rc-modal-cajero-activo':'' ?>"
             data-id="<?= $u['id_usuario'] ?>"
             data-nombre="<?= htmlspecialchars($u['nombre_completo']) ?>"
             data-ini="<?= htmlspecialchars($ini) ?>"
             data-color="<?= $color ?>">
            <div class="rc-modal-cajero-avatar" style="background:<?= $color ?>;">
                <?= htmlspecialchars($ini) ?>
            </div>
            <div class="rc-modal-cajero-info">
                <div class="rc-modal-cajero-nombre"><?= htmlspecialchars($u['nombre_completo']) ?></div>
                <div class="rc-modal-cajero-sub">
                    <span style="background:#dcfce7;color:#15803d;border-radius:20px;padding:1px 8px;font-size:.65rem;font-weight:700;">
                        <i class="fas fa-cash-register mr-1"></i>Permiso caja activo
                    </span>
                </div>
            </div>
            <?php if ($activo): ?>
            <div class="rc-modal-cajero-check"><i class="fas fa-check-circle"></i></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if (empty($usuarios_lista)): ?>
        <div style="padding:30px;text-align:center;color:#94a3b8;">
            <i class="fas fa-user-slash fa-2x mb-2 d-block" style="opacity:.4;"></i>
            <div style="font-size:.83rem;">No hay cajeros con permiso de caja</div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Footer -->
    <div style="padding:12px 16px;background:#f8fafc;border-top:1px solid #f1f5f9;display:flex;justify-content:flex-end;">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i>Cancelar
        </button>
    </div>

</div></div></div>

<!-- ══ MODAL EXPORTAR ══ -->
<div class="modal fade" id="modalExportarRC" tabindex="-1" aria-hidden="true">
<div class="modal-dialog" style="max-width:420px;">
<div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#0c1a3a,#0ea5e9);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
    <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-download mr-2"></i>Exportar Reporte de Cajas</h6>
    <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-4">
    <p style="font-size:.83rem;color:#64748b;margin-bottom:14px;">
        <i class="fas fa-info-circle mr-1 text-primary"></i>
        Se exportarán los <strong><?= count($cajas) ?> registros</strong> del período actual con los filtros aplicados.
    </p>
    <div style="background:#f8f9fa;border-radius:10px;padding:14px;border:1px solid #e9ecef;">
        <p style="font-weight:600;font-size:.82rem;color:#495057;margin-bottom:10px;"><i class="fas fa-file-export mr-1"></i>Selecciona el formato:</p>
        <div class="d-flex" style="gap:8px;">
            <button type="button" id="rc_btn_csv"
                style="flex:1;background:linear-gradient(135deg,#047857,#10b981);color:#fff;border:none;border-radius:8px;padding:10px 8px;font-weight:700;font-size:.82rem;cursor:pointer;">
                <i class="fas fa-file-csv d-block mb-1" style="font-size:1.3rem;"></i>CSV
            </button>
            <button type="button" id="rc_btn_excel"
                style="flex:1;background:linear-gradient(135deg,#0c1a3a,#0ea5e9);color:#fff;border:none;border-radius:8px;padding:10px 8px;font-weight:700;font-size:.82rem;cursor:pointer;">
                <i class="fas fa-file-excel d-block mb-1" style="font-size:1.3rem;"></i>Excel
            </button>
            <button type="button" id="rc_btn_pdf"
                style="flex:1;background:linear-gradient(135deg,#922b21,#e74c3c);color:#fff;border:none;border-radius:8px;padding:10px 8px;font-weight:700;font-size:.82rem;cursor:pointer;">
                <i class="fas fa-file-pdf d-block mb-1" style="font-size:1.3rem;"></i>PDF
            </button>
        </div>
    </div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:10px 20px;">
    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cerrar</button>
</div>
</div></div></div>

<?php include $ruta_base . 'includes/footer.php'; ?>
