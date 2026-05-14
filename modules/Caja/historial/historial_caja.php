<?php
// ============================================================
// modules/caja/historial/historial_caja.php | Botica 2026
// Historial completo de cajas por turnos
// ============================================================
$ruta_base = '../../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexión BD no disponible.');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_CAJERO'))        define('ROL_CAJERO', 2);
if (!defined('ROL_TRABAJADOR'))    define('ROL_TRABAJADOR', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_CAJERO, ROL_TRABAJADOR]);
verificarPermiso($pdo, 'historial_caja');

$id_usuario = $_SESSION['id_usuario'] ?? 0;
$id_rol     = $_SESSION['id_rol']     ?? 0;
$es_admin   = ($id_rol == ROL_ADMINISTRADOR);

// ── AJAX: detalle de caja ─────────────────────────────────────────────────────
if (isset($_GET['accion']) && $_GET['accion'] === 'detalle_ajax') {
    $id = (int)($_GET['id_caja'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT c.*, u.nombre_completo FROM caja c JOIN usuarios u ON c.id_usuario=u.id_usuario WHERE c.id_caja=?");
        $stmt->execute([$id]);
        $cab = $stmt->fetch();
        if (!$cab) { echo '<div class="alert alert-warning">Caja no encontrada.</div>'; exit; }

        // Solo el propio usuario o admin puede ver el detalle
        if (!$es_admin && $cab['id_usuario'] != $id_usuario) {
            echo '<div class="alert alert-danger">Sin permiso para ver esta caja.</div>'; exit;
        }

        $movs = $pdo->prepare("SELECT m.*, u.nombre_completo AS cajero FROM movimientos_caja m JOIN usuarios u ON m.id_usuario=u.id_usuario WHERE m.id_caja=? ORDER BY m.fecha ASC");
        $movs->execute([$id]);
        $movimientos = $movs->fetchAll();

        $turnoIco   = ['mañana' => '☀️', 'tarde' => '🌤️', 'noche' => '🌙'];
        $turnoClass = ['mañana' => 'badge-turno-manana', 'tarde' => 'badge-turno-tarde', 'noche' => 'badge-turno-noche'];

        // Cabecera info
        echo '<div class="row mb-3">';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;">Cajero</small><strong>' . htmlspecialchars($cab['nombre_completo']) . '</strong></div>';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;">Turno</small>';
        echo '<span class="' . ($turnoClass[$cab['turno']] ?? '') . '">' . ($turnoIco[$cab['turno']] ?? '') . ' ' . ucfirst($cab['turno']) . '</span></div>';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;">Estado</small>';
        echo $cab['estado'] === 'abierta'
            ? '<span class="badge-hcaja-abierta">🟢 Abierta</span>'
            : '<span class="badge-hcaja-cerrada">🔴 Cerrada</span>';
        echo '</div></div>';

        // Fechas y montos
        echo '<div class="row mb-3 pt-2" style="border-top:1px solid #f0f0f0;">';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;">Apertura</small>' . date('d/m/Y H:i', strtotime($cab['fecha_apertura'])) . '</div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;">Cierre</small>' . ($cab['fecha_cierre'] ? date('d/m/Y H:i', strtotime($cab['fecha_cierre'])) : '—') . '</div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;">Fondo Inicial</small><strong style="color:#1a7a4a;">S/. ' . number_format($cab['monto_inicial'], 2) . '</strong></div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.7rem;text-transform:uppercase;font-weight:600;">Monto Final</small><strong style="color:#1a7a4a;">' . ($cab['monto_final'] !== null ? 'S/. ' . number_format($cab['monto_final'], 2) : '—') . '</strong></div>';
        echo '</div>';

        // Resumen financiero si está cerrada
        if ($cab['monto_esperado'] !== null) {
            $dif      = (float)$cab['diferencia'];
            $difClass = $dif > 0 ? 'diferencia-positiva' : ($dif < 0 ? 'diferencia-negativa' : 'diferencia-cero');
            $difTxt   = $dif > 0
                ? '▲ Sobrante S/. ' . number_format($dif, 2)
                : ($dif < 0 ? '▼ Faltante S/. ' . number_format(abs($dif), 2) : '✓ Cuadre exacto');

            echo '<div class="resumen-modal-caja">';
            // Calcular totales de movimientos
            $tot_ing = array_sum(array_column(array_filter($movimientos, fn($m) => $m['tipo'] === 'ingreso'), 'monto'));
            $tot_egr = array_sum(array_column(array_filter($movimientos, fn($m) => $m['tipo'] === 'egreso'),  'monto'));
            echo '<div class="resumen-modal-row"><span><i class="fas fa-wallet mr-2 text-muted"></i>Fondo inicial</span><strong style="font-family:monospace;">S/. ' . number_format($cab['monto_inicial'], 2) . '</strong></div>';
            echo '<div class="resumen-modal-row"><span><i class="fas fa-plus-circle mr-2 text-success"></i>Total ingresos</span><strong style="font-family:monospace;color:#155724;">+ S/. ' . number_format($tot_ing, 2) . '</strong></div>';
            echo '<div class="resumen-modal-row"><span><i class="fas fa-minus-circle mr-2 text-danger"></i>Total egresos</span><strong style="font-family:monospace;color:#721c24;">- S/. ' . number_format($tot_egr, 2) . '</strong></div>';
            echo '<div class="resumen-modal-row total-row"><span><i class="fas fa-coins mr-2"></i>Monto esperado</span><strong style="font-family:monospace;">S/. ' . number_format($cab['monto_esperado'], 2) . '</strong></div>';
            echo '<div class="resumen-modal-row"><span><i class="fas fa-hand-holding-usd mr-2 text-muted"></i>Monto contado</span><strong style="font-family:monospace;">S/. ' . number_format($cab['monto_final'], 2) . '</strong></div>';
            echo '<div class="resumen-modal-row"><span><i class="fas fa-balance-scale mr-2 text-muted"></i>Diferencia</span><span class="' . $difClass . '">' . $difTxt . '</span></div>';
            if ($cab['observacion']) {
                echo '<div class="resumen-modal-row"><span><i class="fas fa-comment mr-2 text-muted"></i>Observación</span><em style="font-size:.85rem;">' . htmlspecialchars($cab['observacion']) . '</em></div>';
            }
            echo '</div>';
        }

        // Movimientos
        echo '<h6 class="font-weight-bold text-muted mb-2" style="font-size:.82rem;"><i class="fas fa-exchange-alt mr-1"></i>MOVIMIENTOS (' . count($movimientos) . ')</h6>';
        if (empty($movimientos)) {
            echo '<div class="text-center text-muted py-3"><i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i><small>Sin movimientos registrados</small></div>';
        } else {
            $refMap = ['venta' => 'badge-ref-venta', 'compra' => 'badge-ref-compra', 'manual' => 'badge-ref-manual'];
            $refLbl = ['venta' => 'Venta', 'compra' => 'Compra', 'manual' => 'Manual'];
            echo '<div class="table-responsive"><table class="table table-sm table-bordered" style="font-size:.82rem;">';
            echo '<thead style="background:#1a7a4a;color:#fff;"><tr>';
            echo '<th>Fecha/Hora</th><th>Tipo</th><th>Descripción</th><th>Ref.</th><th>Método</th><th class="text-right">Monto</th>';
            echo '</tr></thead><tbody>';
            foreach ($movimientos as $m) {
                $refBadge = '<span class="' . ($refMap[$m['tipo_referencia']] ?? '') . '">'
                    . ($refLbl[$m['tipo_referencia']] ?? '')
                    . ($m['id_referencia'] ? ' #' . $m['id_referencia'] : '')
                    . '</span>';
                $color = $m['tipo'] === 'ingreso' ? '#155724' : '#721c24';
                $signo = $m['tipo'] === 'ingreso' ? '+' : '-';
                echo '<tr>';
                echo '<td style="white-space:nowrap;">' . date('d/m/Y H:i', strtotime($m['fecha'])) . '</td>';
                echo '<td><span class="badge-' . ($m['tipo'] === 'ingreso' ? 'ingreso' : 'egreso') . '">' . strtoupper($m['tipo']) . '</span></td>';
                echo '<td>' . htmlspecialchars($m['descripcion']) . '</td>';
                echo '<td>' . $refBadge . '</td>';
                echo '<td style="font-size:.8rem;font-weight:600;">' . strtoupper($m['metodo_pago']) . '</td>';
                echo '<td class="text-right font-weight-bold" style="font-family:monospace;color:' . $color . ';">' . $signo . ' S/. ' . number_format($m['monto'], 2) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
    } catch (PDOException $e) {
        echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    exit;
}

// ── DATOS ─────────────────────────────────────────────────────────────────────
$cajas  = [];
$stats  = ['total' => 0, 'abiertas' => 0, 'cerradas' => 0, 'total_ingresos' => 0, 'total_egresos' => 0];

try {
    // Admin ve todas las cajas; otros solo las suyas
    if ($es_admin) {
        $cajas = $pdo->query(
            "SELECT c.*, u.nombre_completo,
             (SELECT COUNT(*)           FROM movimientos_caja m WHERE m.id_caja=c.id_caja)                              AS total_movimientos,
             (SELECT COALESCE(SUM(m.monto),0) FROM movimientos_caja m WHERE m.id_caja=c.id_caja AND m.tipo='ingreso')  AS total_ingresos,
             (SELECT COALESCE(SUM(m.monto),0) FROM movimientos_caja m WHERE m.id_caja=c.id_caja AND m.tipo='egreso')   AS total_egresos
             FROM caja c
             JOIN usuarios u ON c.id_usuario=u.id_usuario
             ORDER BY c.fecha_apertura DESC"
        )->fetchAll();
    } else {
        $st = $pdo->prepare(
            "SELECT c.*, u.nombre_completo,
             (SELECT COUNT(*)           FROM movimientos_caja m WHERE m.id_caja=c.id_caja)                              AS total_movimientos,
             (SELECT COALESCE(SUM(m.monto),0) FROM movimientos_caja m WHERE m.id_caja=c.id_caja AND m.tipo='ingreso')  AS total_ingresos,
             (SELECT COALESCE(SUM(m.monto),0) FROM movimientos_caja m WHERE m.id_caja=c.id_caja AND m.tipo='egreso')   AS total_egresos
             FROM caja c
             JOIN usuarios u ON c.id_usuario=u.id_usuario
             WHERE c.id_usuario=?
             ORDER BY c.fecha_apertura DESC"
        );
        $st->execute([$id_usuario]);
        $cajas = $st->fetchAll();
    }

    $stats['total']          = count($cajas);
    $stats['abiertas']       = count(array_filter($cajas, fn($c) => $c['estado'] === 'abierta'));
    $stats['cerradas']       = count(array_filter($cajas, fn($c) => $c['estado'] === 'cerrada'));
    $stats['total_ingresos'] = array_sum(array_column($cajas, 'total_ingresos'));
    $stats['total_egresos']  = array_sum(array_column($cajas, 'total_egresos'));

} catch (PDOException $e) {
    $swal = ['icon' => 'error', 'title' => 'Error', 'text' => 'Error al cargar datos: ' . $e->getMessage()];
}

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<link rel="stylesheet" href="css/historial_caja.css">

<div class="content-wrapper">

    <!-- ── CABECERA ── -->
    <div class="content-header"><div class="container-fluid">
        <div class="page-header-hist-caja d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h4><i class="fas fa-history mr-2"></i>Historial de Cajas</h4>
                <small><i class="fas fa-map-marker-alt mr-1"></i>Botica 2026 &rsaquo; Caja &rsaquo; Historial</small>
            </div>
            <a href="/botica-2026/modules/caja/caja.php" class="btn btn-sm font-weight-bold"
               style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.4);">
                <i class="fas fa-cash-register mr-1"></i>Ir a Caja
            </a>
        </div>
    </div></div>

    <div class="content"><div class="container-fluid">

        <!-- ── STATS ── -->
        <div class="row mb-4">
            <div class="col-md-3 col-6 mb-2">
                <div class="stat-mini-hist-caja" style="background:linear-gradient(135deg,#0d3b26,#1a7a4a);">
                    <i class="fas fa-cash-register"></i>
                    <div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total cajas</div></div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="stat-mini-hist-caja" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);">
                    <i class="fas fa-lock-open"></i>
                    <div><div class="stat-value"><?= $stats['abiertas'] ?></div><div class="stat-label">Abiertas</div></div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="stat-mini-hist-caja" style="background:linear-gradient(135deg,#117a8b,#1a9aad);">
                    <i class="fas fa-arrow-circle-up"></i>
                    <div><div class="stat-value">S/. <?= number_format($stats['total_ingresos'], 0) ?></div><div class="stat-label">Total ingresos</div></div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="stat-mini-hist-caja" style="background:linear-gradient(135deg,#922b21,#e74c3c);">
                    <i class="fas fa-arrow-circle-down"></i>
                    <div><div class="stat-value">S/. <?= number_format($stats['total_egresos'], 0) ?></div><div class="stat-label">Total egresos</div></div>
                </div>
            </div>
        </div>

        <?php if (isset($swal)): ?>
        <script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({icon:'<?= $swal['icon'] ?>',title:'<?= addslashes($swal['title']) ?>',text:'<?= addslashes($swal['text']) ?>',confirmButtonColor:'#1a7a4a',showConfirmButton:true});});</script>
        <?php endif; ?>

        <!-- ── FILTROS ── -->
        <div class="filtros-card d-flex align-items-center gap-3 flex-wrap">
            <span style="font-weight:700;font-size:.85rem;color:#495057;"><i class="fas fa-filter mr-1"></i>Estado:</span>
            <button class="btn btn-sm btn-filtro-estado" data-estado="todos"    data-color="#1a7a4a"
                style="border-radius:20px;border:2px solid #1a7a4a;background:#1a7a4a;color:#fff;font-weight:600;padding:4px 14px;">
                Todos
            </button>
            <button class="btn btn-sm btn-filtro-estado" data-estado="abierta" data-color="#27ae60"
                style="border-radius:20px;border:2px solid #27ae60;color:#27ae60;font-weight:600;padding:4px 14px;">
                🟢 Abiertas
            </button>
            <button class="btn btn-sm btn-filtro-estado" data-estado="cerrada" data-color="#e74c3c"
                style="border-radius:20px;border:2px solid #e74c3c;color:#e74c3c;font-weight:600;padding:4px 14px;">
                🔴 Cerradas
            </button>
            <span class="mx-2" style="color:#dee2e6;">|</span>
            <span style="font-weight:700;font-size:.85rem;color:#495057;"><i class="fas fa-sun mr-1"></i>Turno:</span>
            <button class="btn btn-sm btn-filtro-turno" data-turno="todos"   data-color="#636e72"
                style="border-radius:20px;border:2px solid #636e72;background:#636e72;color:#fff;font-weight:600;padding:4px 14px;">
                Todos
            </button>
            <button class="btn btn-sm btn-filtro-turno" data-turno="mañana" data-color="#856404"
                style="border-radius:20px;border:2px solid #856404;color:#856404;font-weight:600;padding:4px 14px;">
                ☀️ Mañana
            </button>
            <button class="btn btn-sm btn-filtro-turno" data-turno="tarde"  data-color="#e65100"
                style="border-radius:20px;border:2px solid #e65100;color:#e65100;font-weight:600;padding:4px 14px;">
                🌤️ Tarde
            </button>
            <button class="btn btn-sm btn-filtro-turno" data-turno="noche"  data-color="#283593"
                style="border-radius:20px;border:2px solid #283593;color:#283593;font-weight:600;padding:4px 14px;">
                🌙 Noche
            </button>
        </div>

        <!-- ── TABLA ── -->
        <div class="card">
            <div class="card-header-hist-caja d-flex align-items-center justify-content-between">
                <h6 class="mb-0"><i class="fas fa-list mr-2"></i>Registro de Cajas</h6>
                <span class="badge badge-light"><?= count($cajas) ?> registros</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaHistorialCajas" class="table table-hist-caja table-bordered table-hover table-sm">
                        <thead>
                            <tr>
                                <th style="width:55px;">#</th>
                                <?php if ($es_admin): ?>
                                <th>Cajero</th>
                                <?php endif; ?>
                                <th style="width:110px;" class="text-center">Turno</th>
                                <th style="width:130px;">Apertura</th>
                                <th style="width:130px;">Cierre</th>
                                <th style="width:90px;" class="text-right">F. Inicial</th>
                                <th style="width:90px;" class="text-right">Ingresos</th>
                                <th style="width:90px;" class="text-right">Egresos</th>
                                <th style="width:90px;" class="text-right">Diferencia</th>
                                <th style="width:90px;" class="text-center">Estado</th>
                                <th style="width:80px;" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $turnoClass = ['mañana' => 'badge-turno-manana', 'tarde' => 'badge-turno-tarde', 'noche' => 'badge-turno-noche'];
                        $turnoIco   = ['mañana' => '☀️', 'tarde' => '🌤️', 'noche' => '🌙'];
                        $n = 1;
                        foreach ($cajas as $c):
                            $dif      = (float)($c['diferencia'] ?? 0);
                            $difClass = $dif > 0 ? 'diferencia-positiva' : ($dif < 0 ? 'diferencia-negativa' : 'diferencia-cero');
                            $difTxt   = $c['estado'] === 'abierta'
                                ? '—'
                                : ($dif > 0
                                    ? '+S/. ' . number_format($dif, 2)
                                    : ($dif < 0
                                        ? '-S/. ' . number_format(abs($dif), 2)
                                        : 'S/. 0.00'));
                        ?>
                            <tr>
                                <td class="text-center"><div class="num-fila-hcaja"><?= $n++ ?></div></td>
                                <?php if ($es_admin): ?>
                                <td style="font-size:.85rem;font-weight:600;"><?= htmlspecialchars($c['nombre_completo']) ?></td>
                                <?php endif; ?>
                                <td class="text-center">
                                    <span class="<?= $turnoClass[$c['turno']] ?? '' ?>">
                                        <?= ($turnoIco[$c['turno']] ?? '') . ' ' . ucfirst($c['turno']) ?>
                                    </span>
                                </td>
                                <td style="font-size:.82rem;white-space:nowrap;"><?= date('d/m/Y H:i', strtotime($c['fecha_apertura'])) ?></td>
                                <td style="font-size:.82rem;white-space:nowrap;"><?= $c['fecha_cierre'] ? date('d/m/Y H:i', strtotime($c['fecha_cierre'])) : '—' ?></td>
                                <td class="text-right" style="font-family:monospace;font-weight:600;">
                                    S/. <?= number_format($c['monto_inicial'], 2) ?>
                                </td>
                                <td class="text-right" style="font-family:monospace;color:#155724;font-weight:600;">
                                    +S/. <?= number_format($c['total_ingresos'], 2) ?>
                                </td>
                                <td class="text-right" style="font-family:monospace;color:#721c24;font-weight:600;">
                                    -S/. <?= number_format($c['total_egresos'], 2) ?>
                                </td>
                                <td class="text-right">
                                    <span class="<?= $difClass ?>"><?= $difTxt ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($c['estado'] === 'abierta'): ?>
                                    <span class="badge-hcaja-abierta">🟢 Abierta</span>
                                    <?php else: ?>
                                    <span class="badge-hcaja-cerrada">🔴 Cerrada</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-info btn-ver-caja"
                                        title="Ver detalle"
                                        data-id="<?= $c['id_caja'] ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div></div>
</div>

<!-- ══════════════════════════════════════════════════════════
     MODAL VER DETALLE CAJA
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalDetalleCaja" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div style="background:linear-gradient(135deg,#0d3b26,#1a7a4a);padding:18px 24px;display:flex;align-items:center;justify-content:space-between;">
                <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-search mr-2"></i>Detalle de Caja</h6>
                <button type="button" class="close" data-dismiss="modal"
                    style="color:#fff;opacity:.8;font-size:1.4rem;">&times;</button>
            </div>
            <div class="modal-body" style="padding:20px 24px;">
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<?php include $ruta_base . 'includes/footer.php'; ?>
<script src="js/historial_caja.js"></script>

