<?php
// ============================================================
// modules/servicios/ajax_cotizaciones.php
// AJAX para CRUD de cotizaciones de repuestos (orden_cotizaciones)
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo json_encode(['success' => false, 'error' => 'Sin conexión BD']); exit;
}

$accion     = $_POST['accion'] ?? $_GET['accion'] ?? '';
$id_orden   = (int)($_POST['id_orden'] ?? $_GET['id_orden'] ?? 0);
$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);

if (!$id_orden) {
    echo json_encode(['success' => false, 'error' => 'ID de orden inválido']); exit;
}

// ── Asegurar que el ENUM tenga los nuevos estados ─────────────────────────────
try {
    $pdo->exec("ALTER TABLE orden_cotizaciones MODIFY COLUMN estado 
        ENUM('cotizado','aprobado','rechazado','comprado','pendiente_compra','completado') 
        NOT NULL DEFAULT 'cotizado'");
} catch (PDOException $e) { /* Ya existe o no es necesario */ }

// ── Helper: leer cotizaciones de una orden ────────────────────────────────────
function getCotizaciones(PDO $pdo, int $id_orden): array {
    $stmt = $pdo->prepare("
        SELECT oc.*,
               p.nombre_producto, p.codigo AS codigo_producto, p.stock AS stock_actual,
               u.nombre_completo AS usuario_nombre
        FROM orden_cotizaciones oc
        LEFT JOIN productos p ON p.id_producto = oc.id_producto
        LEFT JOIN usuarios  u ON u.id_usuario  = oc.id_usuario
        WHERE oc.id_orden = ?
        ORDER BY oc.fecha_cotizacion ASC
    ");
    $stmt->execute([$id_orden]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ════════════════════════════════════════════════════════════
// LISTAR — también migra cotizaciones aprobadas con producto a completado
// ════════════════════════════════════════════════════════════
if ($accion === 'listar') {
    // Migrar cotizaciones antiguas: aprobadas con id_producto → completado
    // (el stock ya fue descontado al crear, no hay nada pendiente de comprar)
    try {
        $pdo->prepare("
            UPDATE orden_cotizaciones 
            SET estado = 'completado' 
            WHERE id_orden = ? AND estado = 'aprobado' AND id_producto IS NOT NULL
        ")->execute([$id_orden]);
    } catch (PDOException $e) { /* ignorar */ }

    $items = getCotizaciones($pdo, $id_orden);
    $total = array_sum(array_map(fn($i) => $i['estado'] !== 'rechazado' ? (float)$i['subtotal'] : 0, $items));
    echo json_encode(['success' => true, 'items' => $items, 'total' => $total]);
    exit;
}

// ════════════════════════════════════════════════════════════
// CREAR — descuenta stock si hay producto del inventario
// ════════════════════════════════════════════════════════════
if ($accion === 'crear') {
    $id_producto     = (int)($_POST['id_producto'] ?? 0) ?: null;
    $descripcion     = trim($_POST['descripcion']  ?? '');
    $cantidad        = max(1, (int)($_POST['cantidad'] ?? 1));
    $precio_unitario = (float)($_POST['precio_unitario'] ?? 0);
    $nota            = trim($_POST['nota'] ?? '') ?: null;

    if (empty($descripcion) && !$id_producto) {
        echo json_encode(['success' => false, 'error' => 'Ingresa una descripción o selecciona un producto.']); exit;
    }
    if ($precio_unitario <= 0) {
        echo json_encode(['success' => false, 'error' => 'El precio debe ser mayor a 0.']); exit;
    }

    $estado_inicial = 'cotizado';
    $stock_actual   = null;

    // Si hay producto del inventario: verificar stock y descontar
    if ($id_producto) {
        $stProd = $pdo->prepare("SELECT nombre_producto, stock FROM productos WHERE id_producto = ? FOR UPDATE");
        $stProd->execute([$id_producto]);
        $prod = $stProd->fetch(PDO::FETCH_ASSOC);

        if (!$prod) {
            echo json_encode(['success' => false, 'error' => 'Producto no encontrado.']); exit;
        }

        if (empty($descripcion)) {
            $descripcion = $prod['nombre_producto'];
        }

        $stock_actual = (int)$prod['stock'];

        if ($stock_actual >= $cantidad) {
            // Hay stock suficiente → descontar
            $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id_producto = ?")
                ->execute([$cantidad, $id_producto]);
            $estado_inicial = 'cotizado';
        } else {
            // Sin stock suficiente → marcar como pendiente de compra
            // Si hay algo de stock, descontar lo que hay
            if ($stock_actual > 0) {
                $pdo->prepare("UPDATE productos SET stock = 0 WHERE id_producto = ?")
                    ->execute([$id_producto]);
            }
            $estado_inicial = 'pendiente_compra';
        }
    }

    $subtotal = $precio_unitario * $cantidad;

    try {
        $pdo->prepare("
            INSERT INTO orden_cotizaciones
                (id_orden, id_producto, descripcion, cantidad, precio_unitario, subtotal, estado, id_usuario, fecha_cotizacion, nota)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ")->execute([$id_orden, $id_producto, $descripcion, $cantidad, $precio_unitario, $subtotal, $estado_inicial, $id_usuario, $nota]);

        $id_nuevo = (int)$pdo->lastInsertId();
        $items    = getCotizaciones($pdo, $id_orden);
        $total    = array_sum(array_map(fn($i) => $i['estado'] !== 'rechazado' ? (float)$i['subtotal'] : 0, $items));

        $msg = $estado_inicial === 'pendiente_compra'
            ? 'Stock insuficiente. Cotización registrada como pendiente de compra.'
            : 'Cotización registrada. Stock descontado del inventario.';

        echo json_encode([
            'success'        => true,
            'id'             => $id_nuevo,
            'items'          => $items,
            'total'          => $total,
            'estado_inicial' => $estado_inicial,
            'message'        => $msg
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
// APROBAR INTELIGENTE — si tiene producto del inventario → completado
//                       si es descripción libre → aprobado (pendiente compra)
// ════════════════════════════════════════════════════════════
if ($accion === 'aprobar_inteligente') {
    $id_cot = (int)($_POST['id_cotizacion'] ?? 0);
    if (!$id_cot) {
        echo json_encode(['success' => false, 'error' => 'ID inválido.']); exit;
    }

    try {
        $stCot = $pdo->prepare("SELECT id_producto, cantidad FROM orden_cotizaciones WHERE id = ? AND id_orden = ?");
        $stCot->execute([$id_cot, $id_orden]);
        $cot = $stCot->fetch(PDO::FETCH_ASSOC);

        if (!$cot) {
            echo json_encode(['success' => false, 'error' => 'Cotización no encontrada.']); exit;
        }

        // Si tiene producto del inventario, el stock ya fue descontado al crear → completado
        // Si es descripción libre (sin producto), queda aprobado → pendiente de compra al proveedor
        $estado_final = $cot['id_producto'] ? 'completado' : 'aprobado';

        $pdo->prepare("UPDATE orden_cotizaciones SET estado = ? WHERE id = ? AND id_orden = ?")
            ->execute([$estado_final, $id_cot, $id_orden]);

        $items = getCotizaciones($pdo, $id_orden);
        $total = array_sum(array_map(fn($i) => $i['estado'] !== 'rechazado' ? (float)$i['subtotal'] : 0, $items));

        echo json_encode([
            'success'      => true,
            'estado_final' => $estado_final,
            'items'        => $items,
            'total'        => $total
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
// CAMBIAR ESTADO (aprobar / rechazar / marcar comprado)
// ════════════════════════════════════════════════════════════
if ($accion === 'cambiar_estado') {
    $id_cot  = (int)($_POST['id_cotizacion'] ?? 0);
    $estado  = $_POST['estado'] ?? '';
    $estados = ['cotizado', 'aprobado', 'rechazado', 'comprado', 'pendiente_compra', 'completado'];

    if (!$id_cot || !in_array($estado, $estados)) {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos.']); exit;
    }

    try {
        // Leer estado actual y producto antes de cambiar
        $stActual = $pdo->prepare("SELECT estado, id_producto, cantidad FROM orden_cotizaciones WHERE id = ? AND id_orden = ?");
        $stActual->execute([$id_cot, $id_orden]);
        $actual = $stActual->fetch(PDO::FETCH_ASSOC);

        if (!$actual) {
            echo json_encode(['success' => false, 'error' => 'Cotización no encontrada.']); exit;
        }

        // Si se está rechazando una cotización con producto del inventario,
        // devolver el stock (solo si venía de cotizado o completado, donde el stock fue descontado)
        $estados_con_stock_descontado = ['cotizado', 'completado'];
        if ($estado === 'rechazado'
            && $actual['id_producto']
            && in_array($actual['estado'], $estados_con_stock_descontado)
        ) {
            $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id_producto = ?")
                ->execute([$actual['cantidad'], $actual['id_producto']]);
        }

        // Si se está reactivando (cotizado) desde rechazado con producto, volver a descontar
        if ($estado === 'cotizado'
            && $actual['estado'] === 'rechazado'
            && $actual['id_producto']
        ) {
            // Verificar si hay stock suficiente para volver a descontar
            $stStock = $pdo->prepare("SELECT stock FROM productos WHERE id_producto = ?");
            $stStock->execute([$actual['id_producto']]);
            $stock_disp = (int)$stStock->fetchColumn();

            if ($stock_disp >= $actual['cantidad']) {
                $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id_producto = ?")
                    ->execute([$actual['cantidad'], $actual['id_producto']]);
            } else {
                // Sin stock suficiente → reactivar como pendiente_compra
                $estado = 'pendiente_compra';
            }
        }

        $pdo->prepare("UPDATE orden_cotizaciones SET estado = ? WHERE id = ? AND id_orden = ?")
            ->execute([$estado, $id_cot, $id_orden]);

        $items = getCotizaciones($pdo, $id_orden);
        $total = array_sum(array_map(fn($i) => $i['estado'] !== 'rechazado' ? (float)$i['subtotal'] : 0, $items));

        echo json_encode(['success' => true, 'items' => $items, 'total' => $total, 'message' => 'Estado actualizado.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ════════════════════════════════════════════════════════════
// ELIMINAR — devuelve el stock si tenía producto y estaba cotizado
// ════════════════════════════════════════════════════════════
if ($accion === 'eliminar') {
    $id_cot = (int)($_POST['id_cotizacion'] ?? 0);
    if (!$id_cot) {
        echo json_encode(['success' => false, 'error' => 'ID inválido.']); exit;
    }

    try {
        $check = $pdo->prepare("SELECT estado, id_producto, cantidad FROM orden_cotizaciones WHERE id = ? AND id_orden = ?");
        $check->execute([$id_cot, $id_orden]);
        $row = $check->fetch();

        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Cotización no encontrada.']); exit;
        }
        if (in_array($row['estado'], ['aprobado', 'comprado', 'completado'])) {
            echo json_encode(['success' => false, 'error' => 'No se puede eliminar una cotización aprobada o ya completada.']); exit;
        }

        // Si tenía producto y el stock fue descontado (estado cotizado), devolver el stock
        if ($row['id_producto'] && $row['estado'] === 'cotizado') {
            $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id_producto = ?")
                ->execute([$row['cantidad'], $row['id_producto']]);
        }

        $pdo->prepare("DELETE FROM orden_cotizaciones WHERE id = ? AND id_orden = ?")
            ->execute([$id_cot, $id_orden]);

        $items = getCotizaciones($pdo, $id_orden);
        $total = array_sum(array_map(fn($i) => $i['estado'] !== 'rechazado' ? (float)$i['subtotal'] : 0, $items));

        echo json_encode(['success' => true, 'items' => $items, 'total' => $total, 'message' => 'Cotización eliminada.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no reconocida.']);
