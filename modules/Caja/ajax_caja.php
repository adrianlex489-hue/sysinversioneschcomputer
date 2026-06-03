<?php
// ============================================================
// modules/Caja/ajax_caja.php | SysInversiones CH Computer 2026
// Endpoints AJAX para el módulo de Caja
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo json_encode(['ok' => false, 'msg' => 'Sin conexión BD']); exit;
}

$accion     = $_GET['accion'] ?? $_POST['accion'] ?? '';
$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
$id_rol     = (int)($_SESSION['id_rol']     ?? 0);
$es_admin   = ($id_rol === 1);

$METODOS = ['efectivo','yape','transferencia'];

// ── Helper: calcular resumen por método para una caja ────────────────────────
function calcularResumen(PDO $pdo, int $id_caja, float $monto_inicial, array $METODOS): array {
    $st = $pdo->prepare("
        SELECT metodo_pago, tipo, COALESCE(SUM(monto),0) AS total
        FROM movimientos_caja
        WHERE id_caja = ?
        GROUP BY metodo_pago, tipo
    ");
    $st->execute([$id_caja]);
    $rows = $st->fetchAll();

    $por_metodo = [];
    foreach ($METODOS as $m) {
        $por_metodo[$m] = ['ingreso' => 0.0, 'egreso' => 0.0, 'neto' => 0.0, 'esperado' => 0.0];
    }
    // Efectivo arranca con el fondo inicial
    $por_metodo['efectivo']['esperado'] = $monto_inicial;

    foreach ($rows as $r) {
        $m = $r['metodo_pago'];
        if (!isset($por_metodo[$m])) continue;
        if ($r['tipo'] === 'ingreso') {
            $por_metodo[$m]['ingreso']  += (float)$r['total'];
            $por_metodo[$m]['esperado'] += (float)$r['total'];
        } else {
            $por_metodo[$m]['egreso']   += (float)$r['total'];
            $por_metodo[$m]['esperado'] -= (float)$r['total'];
        }
    }
    foreach ($por_metodo as $m => &$v) {
        $v['neto']     = round($v['ingreso'] - $v['egreso'], 2);
        $v['esperado'] = round($v['esperado'], 2);
    }

    $total_ingresos = array_sum(array_column($por_metodo, 'ingreso'));
    $total_egresos  = array_sum(array_column($por_metodo, 'egreso'));
    $saldo_total    = round($monto_inicial + $total_ingresos - $total_egresos, 2);

    return [
        'por_metodo'     => $por_metodo,
        'total_ingresos' => round($total_ingresos, 2),
        'total_egresos'  => round($total_egresos, 2),
        'saldo_total'    => $saldo_total,
        'monto_inicial'  => $monto_inicial,
    ];
}

// ════════════════════════════════════════════════════════════
// GET: resumen en tiempo real
// ════════════════════════════════════════════════════════════
if ($accion === 'resumen') {
    $id_caja = (int)($_GET['id_caja'] ?? 0);
    if (!$id_caja) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }
    try {
        $stC = $pdo->prepare("SELECT monto_inicial FROM caja WHERE id_caja=?");
        $stC->execute([$id_caja]);
        $monto_inicial = (float)($stC->fetchColumn() ?? 0);
        $resumen = calcularResumen($pdo, $id_caja, $monto_inicial, $METODOS);
        echo json_encode(['ok' => true] + $resumen);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
// GET: movimientos de una caja
// ════════════════════════════════════════════════════════════
if ($accion === 'movimientos') {
    $id_caja = (int)($_GET['id_caja'] ?? 0);
    $limit   = min((int)($_GET['limit'] ?? 100), 500);
    $filtro_tipo   = $_GET['tipo']   ?? '';
    $filtro_metodo = $_GET['metodo'] ?? '';

    if (!$id_caja) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }
    try {
        $where = ['m.id_caja = ?'];
        $params = [$id_caja];
        if ($filtro_tipo   && in_array($filtro_tipo,   ['ingreso','egreso']))
            { $where[] = 'm.tipo = ?';        $params[] = $filtro_tipo; }
        if ($filtro_metodo && in_array($filtro_metodo, ['efectivo','yape','transferencia']))
            { $where[] = 'm.metodo_pago = ?'; $params[] = $filtro_metodo; }

        $sql = "SELECT m.*, u.nombre_completo
                FROM movimientos_caja m
                LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
                WHERE " . implode(' AND ', $where) . "
                ORDER BY m.fecha DESC LIMIT ?";
        $params[] = $limit;
        $st = $pdo->prepare($sql);
        $st->execute($params);
        echo json_encode(['ok' => true, 'movimientos' => $st->fetchAll()], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
// GET: esperado por método (para formulario de cierre)
// ════════════════════════════════════════════════════════════
if ($accion === 'esperado_cierre') {
    $id_caja = (int)($_GET['id_caja'] ?? 0);
    if (!$id_caja) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }
    try {
        $stC = $pdo->prepare("SELECT monto_inicial FROM caja WHERE id_caja=?");
        $stC->execute([$id_caja]);
        $monto_inicial = (float)($stC->fetchColumn() ?? 0);
        $resumen = calcularResumen($pdo, $id_caja, $monto_inicial, $METODOS);
        // Solo devolver el esperado por método
        $esperado = [];
        foreach ($resumen['por_metodo'] as $m => $v) {
            $esperado[$m] = $v['esperado'];
        }
        echo json_encode([
            'ok'            => true,
            'esperado'      => $esperado,
            'total_esperado'=> $resumen['saldo_total'],
            'monto_inicial' => $monto_inicial,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
// GET: detalle de una caja cerrada (para historial)
// ════════════════════════════════════════════════════════════
if ($accion === 'detalle_caja') {
    $id_caja = (int)($_GET['id_caja'] ?? 0);
    if (!$id_caja) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }
    try {
        $stC = $pdo->prepare("
            SELECT c.*, u.nombre_completo
            FROM caja c
            JOIN usuarios u ON u.id_usuario = c.id_usuario
            WHERE c.id_caja = ?
        ");
        $stC->execute([$id_caja]);
        $caja = $stC->fetch();
        if (!$caja) { echo json_encode(['ok'=>false,'msg'=>'Caja no encontrada']); exit; }

        // Detalle de cierre por método
        $stDet = $pdo->prepare("SELECT * FROM cierre_caja_detalle WHERE id_caja=? ORDER BY metodo_pago");
        $stDet->execute([$id_caja]);
        $detalle_cierre = $stDet->fetchAll();

        // Movimientos
        $stMov = $pdo->prepare("
            SELECT m.*, u.nombre_completo AS cajero
            FROM movimientos_caja m
            LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
            WHERE m.id_caja = ?
            ORDER BY m.fecha DESC
        ");
        $stMov->execute([$id_caja]);
        $movimientos = $stMov->fetchAll();

        // Resumen por tipo_referencia
        $stRef = $pdo->prepare("
            SELECT tipo_referencia, tipo, COALESCE(SUM(monto),0) AS total, COUNT(*) AS cantidad
            FROM movimientos_caja WHERE id_caja=?
            GROUP BY tipo_referencia, tipo
        ");
        $stRef->execute([$id_caja]);
        $por_referencia = $stRef->fetchAll();

        echo json_encode([
            'ok'             => true,
            'caja'           => $caja,
            'detalle_cierre' => $detalle_cierre,
            'movimientos'    => $movimientos,
            'por_referencia' => $por_referencia,
        ]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
// GET: stats del historial
// ════════════════════════════════════════════════════════════
if ($accion === 'stats_historial') {
    try {
        $hoy = date('Y-m-d');
        $mes = date('Y-m');
        $st = $pdo->prepare("
            SELECT
                COUNT(*) AS total_cajas,
                SUM(estado='abierta') AS cajas_abiertas,
                SUM(DATE(fecha_apertura)=?) AS cajas_hoy,
                SUM(DATE_FORMAT(fecha_apertura,'%Y-%m')=?) AS cajas_mes,
                COALESCE(SUM(CASE WHEN estado='cerrada' THEN monto_final END),0) AS total_recaudado
            FROM caja
            WHERE id_usuario = ?
        ");
        $st->execute([$hoy, $mes, $id_usuario]);
        echo json_encode(['ok' => true, 'stats' => $st->fetch()]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Acción no reconocida: ' . htmlspecialchars($accion)]);
