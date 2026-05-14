<?php
// ============================================================
// modules/transacciones/ventas.php | Botica 2026
// Ventas al contado y credito con cuotas (max. 4)
// Numeracion automatica de comprobantes por tipo
// Stock se descuenta SIEMPRE al registrar (FEFO)
// ============================================================
$ruta_base = "../../";
require_once $ruta_base . "conf/database.php";
require_once $ruta_base . "conf/verificar_acceso.php";
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die("Error: Conexion BD no disponible.");
if (!defined("ROL_ADMINISTRADOR")) define("ROL_ADMINISTRADOR", 1);
if (!defined('ROL_CAJERO'))        define('ROL_CAJERO', 2);
if (!defined("ROL_TRABAJADOR"))    define('ROL_TRABAJADOR', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_CAJERO, ROL_TRABAJADOR]);
verificarPermiso($pdo, 'ventas');

$id_usuario = $_SESSION["id_usuario"] ?? 0;

$swal = null;
if (isset($_SESSION["swal_vta"])) { $swal = $_SESSION["swal_vta"]; unset($_SESSION["swal_vta"]); }

// ── Verificar caja abierta ────────────────────────────────────────────────────
$caja_activa_vta = null;
try {
    $stCajaVta = $pdo->prepare("SELECT id_caja, turno, monto_inicial, fecha_apertura FROM caja WHERE id_usuario=? AND estado='abierta' ORDER BY fecha_apertura DESC LIMIT 1");
    $stCajaVta->execute([$id_usuario]);
    $caja_activa_vta = $stCajaVta->fetch();
} catch (PDOException $e) {}
$hay_caja_vta = !empty($caja_activa_vta);

function redirigirVta(string $icon, string $title, string $text): void {
    $_SESSION["swal_vta"] = compact("icon", "title", "text");
    header("Location: ventas.php"); exit;
}

// Actualizar cuotas vencidas
try {
    $pdo->exec("UPDATE cuotas_venta SET estado='vencido' WHERE estado='pendiente' AND fecha_vencimiento < CURDATE()");
} catch (PDOException $e) {}

// AJAX: siguiente numero de comprobante
if (isset($_GET["accion"]) && $_GET["accion"] === "siguiente_numero") {
    $tipo = $_GET["tipo"] ?? "ticket";
    $prefijos = ["ticket"=>"T001","nota"=>"N001","boleta"=>"B001","factura"=>"F001"];
    $prefijo  = $prefijos[$tipo] ?? "T001";
    try {
        $stmt = $pdo->prepare("SELECT numero_comprobante FROM ventas WHERE tipo_comprobante=? AND numero_comprobante IS NOT NULL ORDER BY id_venta DESC LIMIT 1");
        $stmt->execute([$tipo]);
        $ultimo = $stmt->fetchColumn();
        if ($ultimo) { $partes = explode("-", $ultimo); $num = (int)end($partes) + 1; } else { $num = 1; }
        echo json_encode(["numero" => $prefijo . "-" . str_pad($num, 8, "0", STR_PAD_LEFT)]);
    } catch (PDOException $e) { echo json_encode(["numero" => $prefijo . "-00000001"]); }
    exit;
}

// AJAX: detalle de venta
if (isset($_GET["accion"]) && $_GET["accion"] === "detalle_ajax") {
    $id = (int)($_GET["id_venta"] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT v.*, CONCAT(c.nombres, " . "' '" . ", c.apellido_paterno) AS nombre_cliente, u.nombre_completo FROM ventas v JOIN clientes c ON v.id_cliente=c.id_cliente JOIN usuarios u ON v.id_usuario=u.id_usuario WHERE v.id_venta=?");
        $stmt->execute([$id]); $cab = $stmt->fetch();
        if (!$cab) { echo "<div class='alert alert-warning'>Venta no encontrada.</div>"; exit; }

        $det = $pdo->prepare("SELECT dv.*, p.nombre_producto, p.codigo, l.codigo_lote, l.fecha_vencimiento FROM detalle_venta dv JOIN productos p ON dv.id_producto=p.id_producto JOIN lotes l ON dv.id_lote=l.id_lote WHERE dv.id_venta=?");
        $det->execute([$id]); $items = $det->fetchAll();

        $cuotas = []; $pagos = [];
        try {
            $stC = $pdo->prepare("SELECT * FROM cuotas_venta WHERE id_venta=? ORDER BY numero_cuota"); $stC->execute([$id]); $cuotas = $stC->fetchAll();
            $stP = $pdo->prepare("SELECT pv.*, u.nombre_completo FROM pagos_venta pv JOIN usuarios u ON pv.id_usuario=u.id_usuario WHERE pv.id_venta=? ORDER BY pv.fecha"); $stP->execute([$id]); $pagos = $stP->fetchAll();
        } catch (PDOException $e) {}

        $bMap = ["pagado"=>"badge-vta-pagado","pendiente"=>"badge-vta-pendiente","anulado"=>"badge-vta-anulado"];
        echo "<div class='row mb-2'>";
        echo "<div class='col-md-5'><small class='text-muted d-block' style='font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;'><i class='fas fa-user mr-1'></i>Cliente</small><strong>" . htmlspecialchars($cab["nombre_cliente"]) . "</strong></div>";
        echo "<div class='col-md-3'><small class='text-muted d-block' style='font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;'><i class='fas fa-file-invoice mr-1'></i>Comprobante</small><strong>" . htmlspecialchars(ucfirst($cab["tipo_comprobante"])) . " " . htmlspecialchars($cab["numero_comprobante"]??"---") . "</strong></div>";
        echo "<div class='col-md-2'><small class='text-muted d-block' style='font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;'><i class='fas fa-credit-card mr-1'></i>T. Pago</small><span class='badge-" . htmlspecialchars($cab["tipo_pago"]) . "-vta'>" . strtoupper($cab["tipo_pago"]) . "</span></div>";
        echo "<div class='col-md-2'><small class='text-muted d-block' style='font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;'><i class='fas fa-circle mr-1'></i>Estado</small><span class='" . ($bMap[$cab["estado"]]??"") . "'>" . strtoupper($cab["estado"]) . "</span></div>";
        echo "</div>";
        echo "<div class='row mb-3 pt-2' style='border-top:1px solid #f0f0f0;'>";
        echo "<div class='col-md-4'><small class='text-muted d-block' style='font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;'><i class='fas fa-calendar-alt mr-1'></i>Fecha</small>" . date("d/m/Y H:i", strtotime($cab["fecha"])) . "</div>";
        echo "<div class='col-md-4'><small class='text-muted d-block' style='font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;'><i class='fas fa-user-tie mr-1'></i>Registrado por</small>" . htmlspecialchars($cab["nombre_completo"]) . "</div>";
        echo "<div class='col-md-4'><small class='text-muted d-block' style='font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;'><i class='fas fa-money-bill-wave mr-1'></i>Metodo pago</small>" . ucfirst($cab["metodo_pago"]) . "</div>";
        echo "</div>";
        echo "<h6 class='font-weight-bold text-muted mb-2' style='font-size:.82rem;'><i class='fas fa-shopping-cart mr-1'></i>PRODUCTOS VENDIDOS</h6>";
        echo "<div class='table-responsive mb-3'><table class='table table-sm table-bordered' style='font-size:.83rem;'>";
        echo "<thead style='background:#1a7a4a;color:#fff;'><tr><th><i class='fas fa-pills mr-1'></i>Producto</th><th><i class='fas fa-tag mr-1'></i>Lote</th><th><i class='fas fa-calendar-times mr-1'></i>Vence</th><th class='text-center'><i class='fas fa-sort-numeric-up mr-1'></i>Cant.</th><th class='text-right'><i class='fas fa-dollar-sign mr-1'></i>P.Venta</th><th class='text-right'><i class='fas fa-calculator mr-1'></i>Subtotal</th></tr></thead><tbody>";
        foreach ($items as $it) {
            $vence = $it["fecha_vencimiento"] ? date("d/m/Y", strtotime($it["fecha_vencimiento"])) : "---";
            $hoy = new DateTime(); $fv = new DateTime($it["fecha_vencimiento"]);
            $dias = (int)$hoy->diff($fv)->days * ($fv >= $hoy ? 1 : -1);
            $av = $dias < 0 ? "<span class='alerta-vencido ml-1'>VENCIDO</span>" : ($dias <= 90 ? "<span class='alerta-vence-pronto ml-1'>" . $dias . "d</span>" : "");
            echo "<tr><td><strong>" . htmlspecialchars($it["nombre_producto"]) . "</strong><br><small class='text-muted'>" . htmlspecialchars($it["codigo"]) . "</small></td>";
            echo "<td><code style='background:#e0f7fa;color:#117a8b;padding:2px 5px;border-radius:3px;'>" . htmlspecialchars($it["codigo_lote"]) . "</code></td>";
            echo "<td>" . $vence . $av . "</td><td class='text-center'>" . $it["cantidad"] . "</td>";
            echo "<td class='text-right'>S/. " . number_format($it["precio_unitario"], 2) . "</td>";
            echo "<td class='text-right font-weight-bold'>S/. " . number_format($it["subtotal"], 2) . "</td></tr>";
        }
        echo "</tbody></table></div>";
        echo "<div class='d-flex justify-content-end mb-3'>";
        echo "<div style='background:#f8f9fa;border-radius:10px;padding:14px 20px;border:1px solid #e9ecef;min-width:260px;'>";
        echo "<div style='display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:.87rem;color:#555;border-bottom:1px solid #e9ecef;'><span><i class='fas fa-receipt mr-2 text-muted'></i>Subtotal</span><strong style='font-family:monospace;'>S/. " . number_format($cab["subtotal"], 2) . "</strong></div>";
        if ($cab["descuento"] > 0) { echo "<div style='display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:.87rem;color:#555;border-bottom:1px solid #e9ecef;'><span><i class='fas fa-tag mr-2 text-muted'></i>Descuento</span><strong style='font-family:monospace;color:#e74c3c;'>- S/. " . number_format($cab["descuento"], 2) . "</strong></div>"; }
        if ($cab["aplica_igv"])    { echo "<div style='display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:.87rem;color:#555;border-bottom:1px solid #e9ecef;'><span><i class='fas fa-percentage mr-2 text-muted'></i>IGV 18%</span><strong style='font-family:monospace;'>S/. " . number_format($cab["igv"], 2) . "</strong></div>"; }
        echo "<div style='display:flex;justify-content:space-between;align-items:center;padding:8px 0 3px;font-size:1rem;font-weight:700;'><span style='color:#1a7a4a;'><i class='fas fa-check-circle mr-2'></i>TOTAL</span><span style='color:#1a7a4a;font-size:1.15rem;font-family:monospace;'>S/. " . number_format($cab["total"], 2) . "</span></div>";
        echo "</div></div>";

        if ($cab["tipo_pago"] === "credito" && !empty($cuotas)) {
            $eC = ["pendiente"=>"badge-vta-pendiente","pagado"=>"badge-vta-pagado","vencido"=>"badge-vta-anulado"];
            echo "<h6 class='font-weight-bold text-muted mb-2' style='font-size:.82rem;'><i class='fas fa-calendar-alt mr-1'></i>CRONOGRAMA DE CUOTAS</h6>";
            echo "<div class='table-responsive mb-3'><table class='table table-sm table-bordered' style='font-size:.83rem;'>";
            echo "<thead style='background:#e67e22;color:#fff;'><tr><th class='text-center'>Cuota</th><th class='text-right'>Monto</th><th class='text-center'>Vencimiento</th><th class='text-center'>Estado</th></tr></thead><tbody>";
            foreach ($cuotas as $cq) {
                echo "<tr><td class='text-center font-weight-bold'>" . $cq["numero_cuota"] . " / " . count($cuotas) . "</td>";
                echo "<td class='text-right font-weight-bold'>S/. " . number_format($cq["monto_cuota"], 2) . "</td>";
                echo "<td class='text-center'>" . date("d/m/Y", strtotime($cq["fecha_vencimiento"])) . "</td>";
                echo "<td class='text-center'><span class='" . ($eC[$cq["estado"]]??"") . "'>" . strtoupper($cq["estado"]) . "</span></td></tr>";
            }
            echo "</tbody></table></div>";
            if ($cab["saldo_pendiente"] > 0) echo "<div class='alert' style='background:#fff3cd;border-left:4px solid #e67e22;border-radius:6px;padding:10px 14px;font-size:.88rem;'><i class='fas fa-exclamation-circle mr-2' style='color:#e67e22;'></i><strong>Saldo pendiente: S/. " . number_format($cab["saldo_pendiente"], 2) . "</strong></div>";
        }
        if (!empty($pagos)) {
            echo "<h6 class='font-weight-bold text-muted mb-2' style='font-size:.82rem;'><i class='fas fa-money-bill-wave mr-1'></i>PAGOS REALIZADOS</h6>";
            echo "<div class='table-responsive'><table class='table table-sm table-bordered' style='font-size:.83rem;'>";
            echo "<thead style='background:#1a7a4a;color:#fff;'><tr><th>Fecha</th><th>Metodo</th><th class='text-right'>Monto</th><th>Registrado por</th></tr></thead><tbody>";
            foreach ($pagos as $pg) {
                echo "<tr><td>" . date("d/m/Y H:i", strtotime($pg["fecha"])) . "</td><td>" . strtoupper($pg["metodo_pago"]) . "</td>";
                echo "<td class='text-right font-weight-bold text-success'>S/. " . number_format($pg["monto"], 2) . "</td>";
                echo "<td>" . htmlspecialchars($pg["nombre_completo"]) . "</td></tr>";
            }
            echo "</tbody></table></div>";
        }
    } catch (PDOException $e) { echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>"; }
    exit;
}

// CRUD POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = $_POST["accion"] ?? "";

    // AJAX: actualizar precio de venta del producto
    if ($accion === "actualizar_precio_venta") {
        header("Content-Type: application/json");
        $id_producto = (int)($_POST["id_producto"]  ?? 0);
        $precio_venta = (float)($_POST["precio_venta"] ?? 0);
        if (!$id_producto || $precio_venta <= 0) {
            echo json_encode(["ok" => false, "msg" => "Datos invalidos."]); exit;
        }
        try {
            $pdo->prepare("UPDATE productos SET precio_venta=? WHERE id_producto=?")->execute([$precio_venta, $id_producto]);
            echo json_encode(["ok" => true]);
        } catch (PDOException $e) {
            echo json_encode(["ok" => false, "msg" => $e->getMessage()]);
        }
        exit;
    }

    // REGISTRAR VENTA
    if ($accion === "registrar") {
        // Bloquear si no hay caja abierta
        if (!$hay_caja_vta) redirigirVta("warning", "Caja no aperturada", "Debes aperturar una caja antes de registrar ventas.");
        $id_cliente         = (int)($_POST["id_cliente"]          ?? 0);
        $tipo_comprobante   = $_POST["tipo_comprobante"]           ?? "boleta";
        $numero_comprobante = trim($_POST["numero_comprobante"]    ?? "") ?: null;
        $fecha              = $_POST["fecha"]                      ?? date("Y-m-d H:i:s");
        $subtotal           = (float)($_POST["subtotal"]           ?? 0);
        $descuento_global   = (float)($_POST["descuento_global"]   ?? 0);
        $aplica_igv         = isset($_POST["aplica_igv"]) ? 1 : 0;
        $igv                = (float)($_POST["igv"]                ?? 0);
        $total              = (float)($_POST["total"]              ?? 0);
        $tipo_pago          = $_POST["tipo_pago"]                  ?? "contado";
        $metodo_pago        = $_POST["metodo_pago"]                ?? "efectivo";
        $observacion        = trim($_POST["observacion"]           ?? "") ?: null;
        $items              = $_POST["items"]                      ?? [];
        $num_cuotas         = (int)($_POST["num_cuotas"]           ?? 1);
        $fecha_primera_cuota = $_POST["fecha_primera_cuota"]       ?? null;
        $frecuencia_dias    = (int)($_POST["frecuencia_dias"]      ?? 30);

        if (!$id_cliente)  redirigirVta("warning", "Cliente requerido", "Selecciona un cliente.");
        if (empty($items)) redirigirVta("warning", "Sin productos", "Agrega al menos un producto.");
        if ($total <= 0)   redirigirVta("warning", "Total invalido", "El total debe ser mayor a 0.");
        if ($tipo_pago === "credito") {
            if ($num_cuotas < 1 || $num_cuotas > 4) redirigirVta("warning", "Cuotas invalidas", "El numero de cuotas debe ser entre 1 y 4.");
            if (empty($fecha_primera_cuota))         redirigirVta("warning", "Fecha requerida", "Indica la fecha de la primera cuota.");
        }

        try {
            $pdo->beginTransaction();
            $saldo_pendiente = ($tipo_pago === "credito") ? $total : 0.00;
            $estado_venta    = ($tipo_pago === "contado") ? "pagado" : "pendiente";
            $metodo_guardado = ($tipo_pago === "contado") ? $metodo_pago : "transferencia";

            // Obtener id_caja abierta del usuario — ya validado arriba
            $id_caja = (int)$caja_activa_vta['id_caja'];

            $pdo->prepare("INSERT INTO ventas (id_cliente,id_usuario,id_caja,fecha,tipo_comprobante,numero_comprobante,subtotal,descuento,aplica_igv,igv,total,saldo_pendiente,tipo_pago,fecha_vencimiento_pago,metodo_pago,estado,observacion) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$id_cliente,$id_usuario,$id_caja,$fecha,$tipo_comprobante,$numero_comprobante,$subtotal,$descuento_global,$aplica_igv,$igv,$total,$saldo_pendiente,$tipo_pago,($tipo_pago==="credito"?$fecha_primera_cuota:null),$metodo_guardado,$estado_venta,$observacion]);
            $id_venta = (int)$pdo->lastInsertId();

            foreach ($items as $item) {
                $id_producto    = (int)($item["id_producto"]    ?? 0);
                $cantidad       = (int)($item["cantidad"]       ?? 0);
                $precio_unitario = (float)($item["precio_unitario"] ?? 0);
                $descuento_item = (float)($item["descuento"]    ?? 0);
                $subtotal_item  = (float)($item["subtotal"]     ?? 0);
                if (!$id_producto || $cantidad <= 0) continue;

                // Verificar stock disponible
                $stStock = $pdo->prepare("SELECT stock FROM productos WHERE id_producto=?");
                $stStock->execute([$id_producto]);
                $stock_actual = (int)$stStock->fetchColumn();
                if ($stock_actual < $cantidad) {
                    $pdo->rollBack();
                    redirigirVta("warning", "Stock insuficiente", "No hay suficiente stock para uno de los productos.");
                }

                // Descontar stock FEFO (primero en vencer, primero en salir)
                $stLotes = $pdo->prepare("SELECT id_lote, stock_actual FROM lotes WHERE id_producto=? AND stock_actual > 0 ORDER BY fecha_vencimiento ASC");
                $stLotes->execute([$id_producto]);
                $lotes_disp = $stLotes->fetchAll();
                $restante = $cantidad;
                $id_lote_principal = null;

                foreach ($lotes_disp as $lt) {
                    if ($restante <= 0) break;
                    $desc = min($restante, (int)$lt["stock_actual"]);
                    $pdo->prepare("UPDATE lotes SET stock_actual = stock_actual - ? WHERE id_lote=?")->execute([$desc, $lt["id_lote"]]);
                    if ($id_lote_principal === null) $id_lote_principal = $lt["id_lote"];
                    $restante -= $desc;
                }

                if ($id_lote_principal === null) {
                    $pdo->rollBack();
                    redirigirVta("error", "Error de lote", "No se encontro lote disponible para un producto.");
                }

                $pdo->prepare("INSERT INTO detalle_venta (id_venta,id_producto,id_lote,cantidad,precio_unitario,descuento,subtotal) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$id_venta,$id_producto,$id_lote_principal,$cantidad,$precio_unitario,$descuento_item,$subtotal_item]);
                $pdo->prepare("UPDATE productos SET stock = GREATEST(0, stock - ?) WHERE id_producto=?")->execute([$cantidad,$id_producto]);
            }

            if ($tipo_pago === "credito") {
                $monto_cuota = round($total / $num_cuotas, 2);
                $diferencia  = round($total - ($monto_cuota * $num_cuotas), 2);
                $fecha_cuota = new DateTime($fecha_primera_cuota);
                for ($i = 1; $i <= $num_cuotas; $i++) {
                    $monto_esta = ($i === $num_cuotas) ? round($monto_cuota + $diferencia, 2) : $monto_cuota;
                    $pdo->prepare("INSERT INTO cuotas_venta (id_venta,numero_cuota,monto_cuota,fecha_vencimiento,estado) VALUES (?,?,?,?,'pendiente')")->execute([$id_venta,$i,$monto_esta,$fecha_cuota->format("Y-m-d")]);
                    $fecha_cuota->modify("+{$frecuencia_dias} days");
                }
            }

            $pdo->commit();
            // Registrar movimiento en caja solo si es venta al contado
            if ($tipo_pago === "contado" && $id_caja > 0) {
                try {
                    $desc_mov = "Venta #{$id_venta} - " . ucfirst($tipo_comprobante) . " " . ($numero_comprobante ?? "");
                    $pdo->prepare("INSERT INTO movimientos_caja (id_caja,tipo_referencia,id_referencia,id_usuario,tipo,descripcion,monto,metodo_pago) VALUES (?,'venta',?,?,'ingreso',?,?,?)")
                        ->execute([$id_caja,$id_venta,$id_usuario,trim($desc_mov),$total,$metodo_pago]);
                } catch (PDOException $e) { /* no interrumpir el flujo */ }
            }
            $msg = $tipo_pago === "contado" ? "Venta #$id_venta registrada al contado. Stock actualizado." : "Venta #$id_venta registrada a credito en $num_cuotas cuota(s). Stock actualizado.";
            redirigirVta("success", "Venta registrada!", $msg);
        } catch (PDOException $e) {
            $pdo->rollBack();
            redirigirVta("error", "Error al registrar", "Error: " . $e->getMessage());
        }

    // REGISTRAR PAGO (abono a credito)
    } elseif ($accion === "registrar_pago") {
        $id_venta    = (int)($_POST["id_venta"]      ?? 0);
        $monto       = (float)($_POST["monto_pago"]  ?? 0);
        $metodo_pago = $_POST["metodo_pago_abono"]   ?? "efectivo";
        $observacion = trim($_POST["obs_pago"]        ?? "") ?: null;
        if (!$id_venta) redirigirVta("warning", "Error", "Venta no identificada.");
        if ($monto <= 0) redirigirVta("warning", "Monto invalido", "El monto debe ser mayor a 0.");
        try {
            $pdo->beginTransaction();
            $stV = $pdo->prepare("SELECT total,saldo_pendiente,estado FROM ventas WHERE id_venta=?"); $stV->execute([$id_venta]); $ven = $stV->fetch();
            if (!$ven || $ven["estado"] === "anulado") throw new Exception("La venta no existe o esta anulada.");
            if ($ven["saldo_pendiente"] <= 0) throw new Exception("Esta venta ya esta completamente pagada.");
            if ($monto > $ven["saldo_pendiente"] + 0.01) throw new Exception("El monto (S/. " . number_format($monto,2) . ") supera el saldo pendiente (S/. " . number_format($ven["saldo_pendiente"],2) . ").");
            $pdo->prepare("INSERT INTO pagos_venta (id_venta,id_usuario,metodo_pago,monto,observacion) VALUES (?,?,?,?,?)")->execute([$id_venta,$id_usuario,$metodo_pago,$monto,$observacion]);
            $nuevo_saldo  = round($ven["saldo_pendiente"] - $monto, 2);
            $nuevo_estado = ($nuevo_saldo <= 0) ? "pagado" : "pendiente";
            $pdo->prepare("UPDATE ventas SET saldo_pendiente=?,estado=? WHERE id_venta=?")->execute([$nuevo_saldo,$nuevo_estado,$id_venta]);
            if ($nuevo_saldo <= 0) {
                try { $pdo->prepare("UPDATE cuotas_venta SET estado='pagado' WHERE id_venta=? AND estado!='pagado'")->execute([$id_venta]); } catch(PDOException $e){}
            } else {
                $totalPagado = round($ven["total"] - $nuevo_saldo, 2);
                try {
                    $stCq = $pdo->prepare("SELECT id_cuota,monto_cuota FROM cuotas_venta WHERE id_venta=? ORDER BY numero_cuota"); $stCq->execute([$id_venta]);
                    $acum = 0;
                    foreach ($stCq->fetchAll() as $cq) {
                        $acum += $cq["monto_cuota"];
                        if ($acum <= $totalPagado) $pdo->prepare("UPDATE cuotas_venta SET estado='pagado' WHERE id_cuota=?")->execute([$cq["id_cuota"]]);
                    }
                } catch(PDOException $e){}
            }
            $pdo->commit();
            // Registrar movimiento en caja por abono de crédito
            if ($hay_caja_vta) {
                try {
                    $desc_mov = "Abono Venta #{$id_venta} (" . ucfirst($metodo_pago) . " - crédito)";
                    $pdo->prepare("INSERT INTO movimientos_caja (id_caja,tipo_referencia,id_referencia,id_usuario,tipo,descripcion,monto,metodo_pago) VALUES (?,'venta',?,?,'ingreso',?,?,?)")
                        ->execute([$caja_activa_vta['id_caja'],$id_venta,$id_usuario,$desc_mov,$monto,$metodo_pago]);
                } catch (PDOException $e) {}
            }
            $msg = $nuevo_saldo <= 0 ? "Venta #$id_venta completamente pagada." : "Pago de S/. " . number_format($monto,2) . " registrado. Saldo: S/. " . number_format($nuevo_saldo,2) . ".";
            redirigirVta("success", "Pago registrado!", $msg);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            redirigirVta("error", "Error al registrar pago", $e->getMessage());
        }

    // ANULAR VENTA
    } elseif ($accion === "anular") {
        $id_venta = (int)($_POST["id_venta"] ?? 0);
        if (!$id_venta) redirigirVta("warning", "ID invalido", "No se pudo identificar la venta.");
        try {
            $pdo->beginTransaction();
            $stV = $pdo->prepare("SELECT estado FROM ventas WHERE id_venta=?"); $stV->execute([$id_venta]); $ven = $stV->fetch();
            if (!$ven || $ven["estado"] === "anulado") { $pdo->rollBack(); redirigirVta("warning", "No se puede anular", "La venta ya esta anulada o no existe."); }
            $det = $pdo->prepare("SELECT id_producto,id_lote,cantidad FROM detalle_venta WHERE id_venta=?"); $det->execute([$id_venta]);
            foreach ($det->fetchAll() as $d) {
                $pdo->prepare("UPDATE lotes    SET stock_actual = stock_actual + ? WHERE id_lote=?")->execute([$d["cantidad"],$d["id_lote"]]);
                $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id_producto=?")->execute([$d["cantidad"],$d["id_producto"]]);
            }
            $pdo->prepare("UPDATE ventas SET estado='anulado',saldo_pendiente=0 WHERE id_venta=?")->execute([$id_venta]);
            try { $pdo->prepare("UPDATE cuotas_venta SET estado='pagado' WHERE id_venta=?")->execute([$id_venta]); } catch(PDOException $e){}
            $pdo->commit();
            redirigirVta("info", "Venta anulada", "La venta #$id_venta fue anulada y el stock fue revertido.");
        } catch (PDOException $e) { $pdo->rollBack(); redirigirVta("error", "Error al anular", "Error: " . $e->getMessage()); }
    }
}

// DATOS — solo lo necesario para el formulario
$clientes = $productos_lista = [];
try {
    $clientes = $pdo->query("SELECT id_cliente, CONCAT(nombres,' ',apellido_paterno, CASE WHEN apellido_materno IS NOT NULL AND apellido_materno != '' THEN CONCAT(' ',apellido_materno) ELSE '' END) AS nombre_completo, dni, telefono FROM clientes WHERE estado_cliente=1 ORDER BY nombres")->fetchAll();
    $productos_lista = $pdo->query("SELECT p.id_producto,p.codigo,p.nombre_producto,p.laboratorio,p.precio_venta,p.stock,c.nombre_categoria FROM productos p LEFT JOIN categorias c ON p.id_categoria=c.id_categoria WHERE p.estado=1 ORDER BY p.nombre_producto")->fetchAll();
} catch (PDOException $e) { $swal = ["icon"=>"error","title"=>"Error","text"=>"Error al cargar datos: " . $e->getMessage()]; }

include $ruta_base . "includes/header.php";
include $ruta_base . "includes/sidebar.php";
?>
<link rel="stylesheet" href="css/ventas.css">
<div class="content-wrapper">
<div class="content-header"><div class="container-fluid">
<div class="page-header-vta d-flex justify-content-between align-items-center flex-wrap">
<div><h4><i class="fas fa-cash-register mr-2"></i>Registro de Ventas</h4>
<small><i class="fas fa-map-marker-alt mr-1"></i>Botica 2026 &rsaquo; Transacciones &rsaquo; Ventas</small></div>
<a href="/botica-2026/modules/transacciones/historial/historial_ventas.php" class="btn btn-light btn-sm font-weight-bold">
    <i class="fas fa-history mr-1"></i>Ver Historial
</a>
</div></div></div>
<div class="content"><div class="container-fluid">

<?php if ($swal): ?><script>document.addEventListener("DOMContentLoaded",function(){Swal.fire({icon:"<?= $swal["icon"] ?>",title:"<?= addslashes($swal["title"]) ?>",text:"<?= addslashes($swal["text"]) ?>",confirmButtonColor:"#1a7a4a",timer:<?= in_array($swal["icon"],["success","info"])?3500:0 ?>,timerProgressBar:<?= in_array($swal["icon"],["success","info"])?"true":"false" ?>,showConfirmButton:<?= in_array($swal["icon"],["success","info"])?"false":"true" ?>,});});</script><?php endif; ?>

<!-- FORMULARIO NUEVA VENTA -->
<?php if (!$hay_caja_vta): ?>
<div id="alertaCajaVta" style="background:linear-gradient(135deg,#fff3cd,#ffeeba);border:2px solid #ffc107;border-radius:12px;padding:20px 24px;margin-bottom:20px;display:flex;align-items:center;gap:16px;">
    <div style="width:56px;height:56px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid #ffc107;">
        <i class="fas fa-lock" style="font-size:1.5rem;color:#e67e22;"></i>
    </div>
    <div style="flex:1;">
        <h5 style="color:#856404;font-weight:700;margin:0 0 4px;">Caja no aperturada</h5>
        <p style="color:#856404;font-size:.9rem;margin:0;">No puedes registrar ventas sin una caja abierta. Apertura tu caja primero.</p>
    </div>
    <a href="/botica-2026/modules/caja/caja.php" class="btn btn-sm font-weight-bold" style="background:#e67e22;color:#fff;border-radius:8px;white-space:nowrap;flex-shrink:0;">
        <i class="fas fa-lock-open mr-1"></i>Ir a Caja
    </a>
</div>
<?php endif; ?>
<div class="form-venta-card mb-4" <?= !$hay_caja_vta ? 'id="formVtaBloqueado" class="form-venta-card mb-4 form-bloqueado"' : 'class="form-venta-card mb-4"' ?> style="<?= !$hay_caja_vta ? 'opacity:.55;' : '' ?>">
<div class="form-venta-header d-flex justify-content-between align-items-center">
<h5><i class="fas fa-plus-circle mr-2"></i>Nueva Venta</h5>
<button type="button" id="btnLimpiarFormVta" class="btn btn-sm btn-outline-light"><i class="fas fa-eraser mr-1"></i>Limpiar</button>
</div>
<div class="form-venta-body">
<form id="formNuevaVenta" method="POST">
<input type="hidden" name="accion" value="registrar">
<input type="hidden" name="id_cliente" id="id_cliente_hidden">
<input type="hidden" name="subtotal" id="hidden_subtotal_vta" value="0">
<input type="hidden" name="igv" id="hidden_igv_vta" value="0">
<input type="hidden" name="total" id="hidden_total_vta" value="0">
<input type="hidden" name="metodo_pago" id="hidden_metodo_pago_vta" value="efectivo">
<input type="hidden" name="tipo_pago" id="hidden_tipo_pago_vta" value="contado">
<input type="hidden" name="num_cuotas" id="hidden_num_cuotas_vta" value="1">
<div id="inputsOcultosVta"></div>

<div class="row mb-3">
<div class="col-md-5">
<label class="form-label-vta"><i class="fas fa-user mr-1 text-muted"></i>Cliente <span class="text-danger">*</span></label>
<div class="d-flex gap-2">
<div id="campoCliente" class="campo-selector-vta flex-grow-1" style="cursor:pointer;">
<span class="placeholder-text">Haz clic para seleccionar cliente...</span>
<span class="selected-text" style="display:none;"></span>
<i class="fas fa-search text-muted" style="font-size:.8rem;"></i>
</div>
<button type="button" id="btnSeleccionarCliente" class="btn btn-sm btn-outline-success" title="Buscar"><i class="fas fa-search"></i></button>
<button type="button" id="btnLimpiarCliente" class="btn btn-sm btn-outline-danger" title="Quitar"><i class="fas fa-times"></i></button>
</div>
</div>
<div class="col-md-2">
<label class="form-label-vta"><i class="fas fa-file-invoice mr-1 text-muted"></i>Tipo Comprobante</label>
<select class="form-control form-control-sm" name="tipo_comprobante" id="tipo_comprobante_vta">
<option value="ticket" selected>Ticket</option>
<option value="nota">Nota de Venta</option>
</select>
</div>
<div class="col-md-3">
<label class="form-label-vta"><i class="fas fa-hashtag mr-1 text-muted"></i>N&deg; Comprobante <small class="text-muted">(autogenerado)</small></label>
<div class="input-group input-group-sm">
<div class="input-group-prepend"><span class="input-group-text" id="prefijo_comprobante_vta" style="font-family:monospace;font-weight:700;color:#1a7a4a;background:#d4edda;border-color:#c8e6c9;">T001</span></div>
<input type="text" class="form-control" name="numero_comprobante" id="numero_comprobante_vta" readonly style="background:#f8f9fa;font-family:monospace;font-weight:700;color:#1a7a4a;">
</div>
</div>
<div class="col-md-2">
<label class="form-label-vta"><i class="fas fa-calendar mr-1 text-muted"></i>Fecha</label>
<input type="datetime-local" class="form-control form-control-sm" name="fecha" value="<?= date("Y-m-d\TH:i") ?>">
</div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
<label class="form-label-vta mb-0"><i class="fas fa-shopping-cart mr-1 text-muted"></i>Productos <span id="contadorItemsVta" class="text-muted" style="font-weight:400;font-size:.8rem;">0 productos</span></label>
<button type="button" id="btnAgregarProducto" class="btn btn-sm" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);color:#fff;font-weight:600;"><i class="fas fa-plus mr-1"></i>Agregar Producto</button>
</div>
<div class="tabla-detalle-vta-wrapper mb-3">
<table id="tablaDetalleVta" class="table tabla-detalle-vta table-sm mb-0">
<thead><tr>
<th style="width:40px;">#</th><th>Producto</th>
<th style="width:80px;" class="text-center">Cant.</th>
<th style="width:100px;" class="text-right">P. Venta</th>
<th style="width:90px;" class="text-right">Descuento</th>
<th style="width:100px;" class="text-right">Subtotal</th>
<th style="width:40px;"></th>
</tr></thead>
<tbody><tr class="fila-vacia-vta"><td colspan="7"><i class="fas fa-shopping-cart"></i>Sin productos agregados.<br><small>Haz clic en "Agregar Producto" para comenzar.</small></td></tr></tbody>
</table>
</div>

<div class="row">
<div class="col-md-7">
<div class="seccion-pago-vta">
<h6><i class="fas fa-credit-card mr-2"></i>Informacion de Pago</h6>
<div class="row">
<div class="col-md-6 mb-2">
<label class="form-label-vta">Tipo de Pago</label>
<div class="tipo-pago-group d-flex gap-2">
<button type="button" class="btn-tipo-pago-vta activo" data-tipo="contado" style="flex:1;"><i class="fas fa-money-bill-wave mr-1"></i>Contado</button>
<button type="button" class="btn-tipo-pago-vta" data-tipo="credito" style="flex:1;"><i class="fas fa-calendar-alt mr-1"></i>Credito</button>
</div>
</div>
<div class="col-md-6 mb-2" id="bloqueMetodoPagoVta">
<label class="form-label-vta">Metodo de Pago</label>
<div class="metodo-pago-vta-group">
<button type="button" class="btn-metodo-vta activo" data-metodo="efectivo"><i class="fas fa-money-bill-wave mr-1"></i>Efectivo</button>
<button type="button" class="btn-metodo-vta" data-metodo="yape"><i class="fas fa-mobile-alt mr-1"></i>Yape</button>
<button type="button" class="btn-metodo-vta" data-metodo="plin"><i class="fas fa-mobile-alt mr-1"></i>Plin</button>
<button type="button" class="btn-metodo-vta" data-metodo="transferencia"><i class="fas fa-university mr-1"></i>Transferencia</button>
<button type="button" class="btn-metodo-vta" data-metodo="tarjeta"><i class="fas fa-credit-card mr-1"></i>Tarjeta</button>
</div>
</div>
</div>
<div id="panelCreditoVta" style="display:none;">
<hr class="my-2">
<div style="font-size:.85rem;font-weight:700;color:#e67e22;margin-bottom:10px;"><i class="fas fa-calendar-alt mr-1"></i>Configuracion de Cuotas <small class="text-muted ml-1">(maximo 4 cuotas)</small></div>
<div class="row">
<div class="col-md-4 mb-2">
<label class="form-label-vta">N&deg; de Cuotas</label>
<div class="d-flex gap-2">
<button type="button" class="btn-cuota-vta activo" data-cuotas="1">1</button>
<button type="button" class="btn-cuota-vta" data-cuotas="2">2</button>
<button type="button" class="btn-cuota-vta" data-cuotas="3">3</button>
<button type="button" class="btn-cuota-vta" data-cuotas="4">4</button>
</div>
</div>
<div class="col-md-4 mb-2">
<label class="form-label-vta">Fecha 1ra Cuota <span class="text-danger">*</span></label>
<input type="date" class="form-control form-control-sm" name="fecha_primera_cuota" id="fecha_primera_cuota_vta" min="<?= date("Y-m-d") ?>">
</div>
<div class="col-md-4 mb-2">
<label class="form-label-vta">Frecuencia</label>
<select class="form-control form-control-sm" name="frecuencia_dias" id="frecuencia_dias_vta">
<option value="30">Mensual (30 dias)</option>
<option value="15">Quincenal (15 dias)</option>
<option value="7">Semanal (7 dias)</option>
<option value="60">Bimestral (60 dias)</option>
</select>
</div>
</div>
<div id="previewCuotasVta" style="display:none;margin-top:8px;"></div>
<div id="resumenCuotaVta" style="display:none;margin-top:6px;font-size:.82rem;color:#e67e22;font-weight:600;"><i class="fas fa-info-circle mr-1"></i><span id="resumenCuotaTextoVta"></span></div>
</div>
<div class="row mt-2">
<div class="col-md-8 mb-2">
<label class="form-label-vta"><i class="fas fa-comment-alt mr-1 text-muted"></i>Observacion</label>
<input type="text" class="form-control form-control-sm" name="observacion" placeholder="Notas adicionales (opcional)..." maxlength="200">
</div>
<div class="col-md-4 mb-2 d-flex align-items-end">
<div class="form-check">
<input class="form-check-input" type="checkbox" name="aplica_igv" id="aplica_igv_vta">
<label class="form-check-label font-weight-600" for="aplica_igv_vta" style="font-size:.85rem;font-weight:600;">Aplicar IGV 18%</label>
</div>
</div>
</div>
</div>
</div>
<div class="col-md-5">
<div class="resumen-totales-vta">
<div class="resumen-row-vta"><span><i class="fas fa-receipt mr-1 text-muted"></i>Subtotal</span><span class="monto" id="resumen_subtotal_vta">S/. 0.00</span></div>
<div class="resumen-row-vta"><span><i class="fas fa-tag mr-1 text-muted"></i>Descuento global <input type="number" step="0.01" min="0" id="descuento_global_vta" name="descuento_global" value="0" style="width:70px;font-size:.8rem;padding:2px 6px;border:1px solid #dee2e6;border-radius:4px;margin-left:6px;"></span><span class="monto" id="resumen_descuento_vta">- S/. 0.00</span></div>
<div class="resumen-row-vta"><span><i class="fas fa-percentage mr-1 text-muted"></i>IGV 18%</span><span class="monto" id="resumen_igv_vta">No aplica</span></div>
<div class="resumen-row-vta total-final"><span><i class="fas fa-check-circle mr-1"></i>TOTAL</span><span class="monto" id="resumen_total_vta">S/. 0.00</span></div>
</div>
<button type="submit" id="btnSubmitVenta" class="btn btn-block mt-3 font-weight-bold" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);color:#fff;border-radius:8px;padding:12px;font-size:.95rem;"><i class="fas fa-save mr-2"></i>Registrar Venta</button>
</div>
</div>
</form>
</div>
</div>

</div></div><!-- /container-fluid /content -->
</div><!-- /content-wrapper -->

<!-- MODAL SELECTOR CLIENTE -->
<div class="modal fade" id="modalSelectorCliente" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg"><div class="modal-content" style="border-radius:12px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a7a4a,#27ae60);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;">
<h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-user mr-2"></i>Seleccionar Cliente</h6>
<button type="button" class="close" style="color:#fff;opacity:.8;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-3">
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="input-group input-group-sm" style="flex:1;margin-right:10px;">
        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
        <input type="text" id="buscarCliente" class="form-control" placeholder="Buscar por nombre o DNI...">
        <div class="input-group-append"><span class="input-group-text text-muted" id="contClientes"><?= count($clientes) ?></span></div>
    </div>
    <button type="button" id="btnNuevoClienteDni" class="btn btn-sm btn-success font-weight-bold" style="white-space:nowrap;">
        <i class="fas fa-user-plus mr-1"></i>Nuevo por DNI
    </button>
</div>
<div id="listaClientes" style="max-height:380px;overflow-y:auto;">
<?php foreach ($clientes as $cl): ?>
<div class="item-selector-vta" data-id="<?= $cl["id_cliente"] ?>" data-nombre="<?= htmlspecialchars($cl["nombre_completo"],ENT_QUOTES) ?>" data-dni="<?= htmlspecialchars($cl["dni"],ENT_QUOTES) ?>">
<div class="item-nombre"><?= htmlspecialchars($cl["nombre_completo"]) ?></div>
<div class="item-sub"><i class="fas fa-id-card mr-1"></i>DNI: <?= htmlspecialchars($cl["dni"]) ?><?php if ($cl["telefono"]): ?> &nbsp;|&nbsp; <i class="fas fa-phone mr-1"></i><?= htmlspecialchars($cl["telefono"]) ?><?php endif; ?></div>
</div>
<?php endforeach; ?>
<div id="sinResultadosCli" style="display:none;text-align:center;padding:20px;color:#aaa;font-style:italic;">Sin resultados</div>
</div>
</div>
</div></div></div>

<!-- MODAL SELECTOR PRODUCTO -->
<div class="modal fade" id="modalSelectorProductoVta" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg"><div class="modal-content" style="border-radius:12px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a7a4a,#27ae60);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;">
<h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-pills mr-2"></i>Seleccionar Producto</h6>
<button type="button" class="close" style="color:#fff;opacity:.8;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-3">
<div class="input-group input-group-sm mb-3">
<div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
<input type="text" id="buscarProductoVta" class="form-control" placeholder="Buscar por nombre, codigo o laboratorio...">
</div>
<div class="d-flex justify-content-between mb-2"><small class="text-muted"><span id="contProductosVta"><?= count($productos_lista) ?></span> productos disponibles</small></div>
<div style="max-height:420px;overflow-y:auto;"><div id="listaProductosVta">
<?php foreach ($productos_lista as $pr): ?>
<div class="item-selector-vta" data-id="<?= $pr["id_producto"] ?>" data-nombre="<?= htmlspecialchars($pr["nombre_producto"],ENT_QUOTES) ?>" data-codigo="<?= htmlspecialchars($pr["codigo"],ENT_QUOTES) ?>" data-lab="<?= htmlspecialchars($pr["laboratorio"]??"",ENT_QUOTES) ?>" data-precio="<?= $pr["precio_venta"] ?>" data-stock="<?= $pr["stock"] ?>">
<div class="d-flex align-items-center justify-content-between">
<div class="d-flex align-items-center gap-3">
<div style="width:38px;height:38px;background:#e8f5e9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-pills" style="color:#1a7a4a;font-size:.85rem;"></i></div>
<div>
<div class="item-nombre"><?= htmlspecialchars($pr["nombre_producto"]) ?></div>
<div class="item-sub">
<code style="background:#e0f7fa;color:#117a8b;padding:1px 5px;border-radius:3px;font-size:.72rem;"><?= htmlspecialchars($pr["codigo"]) ?></code>
<?php if ($pr["laboratorio"]): ?> &nbsp;<?= htmlspecialchars($pr["laboratorio"]) ?><?php endif; ?>
<?php if (!empty($pr["nombre_categoria"])): ?> &nbsp;|&nbsp; <?= htmlspecialchars($pr["nombre_categoria"]) ?><?php endif; ?>
</div>
</div>
</div>
<div class="text-right" style="flex-shrink:0;">
<div style="font-size:.75rem;color:#999;">P. Venta</div>
<div style="font-weight:700;color:#1a7a4a;font-size:.9rem;">S/. <?= number_format($pr["precio_venta"],2) ?></div>
<?php if ($pr["stock"] <= 0): ?>
<span class="alerta-sin-stock">Sin stock</span>
<?php elseif ($pr["stock"] <= 5): ?>
<span class="alerta-stock-bajo"><?= $pr["stock"] ?> uds</span>
<?php else: ?>
<span style="font-size:.75rem;color:#27ae60;font-weight:600;"><?= $pr["stock"] ?> uds</span>
<?php endif; ?>
</div>
</div>
</div>
<?php endforeach; ?>
<div id="sinResultadosProdVta" style="display:none;text-align:center;padding:30px;color:#999;"><i class="fas fa-search" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px;"></i>No se encontraron productos</div>
</div></div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:10px 16px;">
<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
</div>
</div></div></div>

<!-- MODAL DETALLE ITEM -->
<div class="modal fade" id="modalDetalleItemVta" tabindex="-1" aria-hidden="true">
<div class="modal-dialog"><div class="modal-content" style="border-radius:12px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a7a4a,#27ae60);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;">
<div><h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-pills mr-2"></i>Detalle del Producto</h6>
<small id="modal_item_nombre_vta" style="color:rgba(255,255,255,.85);font-size:.82rem;"></small></div>
<button type="button" class="close" style="color:#fff;opacity:.8;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-4">
<input type="hidden" id="modal_item_id_producto_vta">
<input type="hidden" id="modal_item_stock_max_vta">
<div style="background:#f0fff4;border-radius:8px;padding:10px 14px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
<span style="font-size:.82rem;color:#555;"><i class="fas fa-cubes mr-1 text-muted"></i>Codigo: <strong id="modal_item_codigo_vta"></strong></span>
<span style="font-size:.82rem;color:#1a7a4a;font-weight:700;" id="modal_item_stock_disp_vta"></span>
</div>
<div class="row">
<div class="col-md-4 mb-3">
<label class="form-label-vta"><i class="fas fa-sort-numeric-up mr-1 text-muted"></i>Cantidad <span class="text-danger">*</span></label>
<input type="number" class="form-control form-control-sm" id="modal_item_cantidad_vta" min="1" value="1">
</div>
<div class="col-md-4 mb-3">
<label class="form-label-vta"><i class="fas fa-dollar-sign mr-1 text-muted"></i>Precio Venta</label>
<div class="input-group input-group-sm">
<input type="number" step="0.01" class="form-control form-control-sm" id="modal_item_precio_vta" min="0.01" readonly style="background:#f0fff4;color:#1a7a4a;font-weight:700;cursor:default;">
<div class="input-group-append">
<button type="button" class="btn btn-sm btn-outline-warning" id="btnEditarPrecioVta" title="Editar precio de venta del producto" style="border-radius:0 4px 4px 0;">
<i class="fas fa-pencil-alt"></i>
</button>
</div>
</div>
<small class="text-muted">Precio registrado — clic en <i class="fas fa-pencil-alt"></i> para actualizar</small>
</div>
<div class="col-md-4 mb-3">
<label class="form-label-vta"><i class="fas fa-tag mr-1 text-muted"></i>Descuento</label>
<input type="number" step="0.01" class="form-control form-control-sm" id="modal_item_descuento_vta" min="0" value="0.00">
</div>
</div>
<div style="background:#f8f9fa;border-radius:8px;padding:12px 16px;text-align:right;">
<span style="font-size:.82rem;color:#666;">Subtotal: </span>
<span id="modal_item_subtotal_vta" style="font-size:1.1rem;font-weight:700;color:#1a7a4a;">S/. 0.00</span>
</div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
<button type="button" id="btnConfirmarItemVta" class="btn btn-sm font-weight-bold" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);color:#fff;"><i class="fas fa-plus mr-1"></i>Agregar</button>
</div>
</div></div></div>

<!-- MODAL VER DETALLE VENTA -->
<div class="modal fade" id="modalVerVenta" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg"><div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a7a4a,#27ae60);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
<h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-file-invoice-dollar mr-2"></i>Detalle de Venta</h6>
<button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-4"><!-- AJAX --></div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cerrar</button>
</div>
</div></div></div>

<!-- MODAL REGISTRAR PAGO -->
<div class="modal fade" id="modalPagarVenta" tabindex="-1" aria-hidden="true">
<div class="modal-dialog"><div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a7a4a,#27ae60);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
<div><h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-dollar-sign mr-2"></i>Registrar Pago</h6>
<small id="pago_cliente_label" style="color:rgba(255,255,255,.85);font-size:.82rem;"></small></div>
<button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<form method="POST" id="formRegistrarPagoVta">
<input type="hidden" name="accion" value="registrar_pago">
<input type="hidden" name="id_venta" id="pago_id_venta">
<div class="modal-body p-4">
<div style="background:#f0fff4;border-radius:8px;padding:12px 16px;margin-bottom:16px;">
<div style="font-size:.8rem;color:#999;text-transform:uppercase;font-weight:600;">Saldo pendiente</div>
<div id="pago_saldo_display_vta" style="font-size:1.4rem;font-weight:700;color:#e67e22;"></div>
</div>
<div class="form-group">
<label style="font-weight:600;font-size:.83rem;"><i class="fas fa-coins mr-1 text-muted"></i>Monto a pagar <span class="text-danger">*</span></label>
<div class="input-group input-group-sm">
<div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
<input type="number" step="0.01" min="0.01" class="form-control" name="monto_pago" id="monto_pago_vta" placeholder="0.00">
</div>
<small class="text-muted">Puede ser pago parcial o total del saldo</small>
</div>
<div class="form-group">
<label style="font-weight:600;font-size:.83rem;"><i class="fas fa-money-bill-wave mr-1 text-muted"></i>Metodo de Pago</label>
<div class="d-flex gap-2 flex-wrap">
<button type="button" class="btn-metodo-pago-vta" data-metodo="efectivo"><i class="fas fa-money-bill-wave mr-1"></i>Efectivo</button>
<button type="button" class="btn-metodo-pago-vta" data-metodo="yape"><i class="fas fa-mobile-alt mr-1"></i>Yape</button>
<button type="button" class="btn-metodo-pago-vta" data-metodo="plin"><i class="fas fa-mobile-alt mr-1"></i>Plin</button>
<button type="button" class="btn-metodo-pago-vta" data-metodo="transferencia"><i class="fas fa-university mr-1"></i>Transferencia</button>
<button type="button" class="btn-metodo-pago-vta" data-metodo="tarjeta"><i class="fas fa-credit-card mr-1"></i>Tarjeta</button>
</div>
<input type="hidden" name="metodo_pago_abono" id="metodo_pago_abono_vta" value="efectivo">
</div>
<div class="form-group mb-0">
<label style="font-weight:600;font-size:.83rem;"><i class="fas fa-comment-alt mr-1 text-muted"></i>Observacion</label>
<textarea class="form-control form-control-sm" name="obs_pago" rows="2" placeholder="Notas del pago (opcional)..." maxlength="150"></textarea>
</div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
<button type="submit" class="btn btn-sm font-weight-bold" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);color:#fff;"><i class="fas fa-check mr-1"></i>Registrar Pago</button>
</div>
</form>
</div></div></div>

<!-- ══════════════════════════════════════════════════════════
     MODAL NUEVO CLIENTE POR DNI
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalNuevoClienteDni" tabindex="-1" aria-hidden="true" style="z-index:1080;">
<div class="modal-dialog modal-md">
<div class="modal-content" style="border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25);">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#1a7a4a,#27ae60);padding:20px 24px;position:relative;">
        <button type="button" class="close" data-dismiss="modal" style="position:absolute;top:14px;right:18px;color:#fff;opacity:.8;font-size:1.4rem;">&times;</button>
        <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-user-plus" style="font-size:1.3rem;color:#fff;"></i>
            </div>
            <div>
                <h5 style="color:#fff;font-weight:700;margin:0;font-size:1.05rem;">Buscar Cliente por DNI</h5>
                <small style="color:rgba(255,255,255,.8);">Consulta automática a RENIEC</small>
            </div>
        </div>
    </div>

    <!-- Body -->
    <div class="modal-body" style="padding:24px;">

        <!-- Input DNI -->
        <div class="form-group mb-4">
            <label style="font-weight:700;font-size:.82rem;color:#495057;text-transform:uppercase;letter-spacing:.5px;">
                <i class="fas fa-id-card mr-1 text-success"></i> Número de DNI
            </label>
            <div class="input-group">
                <input type="text" id="inputDniModal" class="form-control form-control-lg"
                    maxlength="8" placeholder="Ej: 12345678"
                    style="font-family:monospace;font-size:1.2rem;letter-spacing:3px;text-align:center;border-radius:10px 0 0 10px;border:2px solid #dee2e6;">
                <div class="input-group-append">
                    <button type="button" id="btnBuscarDniModal" class="btn btn-success btn-lg font-weight-bold"
                        style="border-radius:0 10px 10px 0;padding:0 20px;">
                        <i class="fas fa-search mr-1"></i>Buscar
                    </button>
                </div>
            </div>
            <small class="text-muted">Ingresa los 8 dígitos del DNI del cliente</small>
        </div>

        <!-- Estado de búsqueda -->
        <div id="dniModalEstado" style="display:none;"></div>

        <!-- Resultado -->
        <div id="dniModalResultado" style="display:none;">
            <div style="background:linear-gradient(135deg,#f0fff4,#e8f5e9);border:1.5px solid #c8e6c9;border-radius:12px;padding:18px 20px;margin-bottom:16px;">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div style="width:52px;height:52px;background:#27ae60;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-user" style="color:#fff;font-size:1.2rem;"></i>
                    </div>
                    <div>
                        <div id="dniNombreCompleto" style="font-size:1.05rem;font-weight:700;color:#2d3436;"></div>
                        <div id="dniBadge" style="margin-top:3px;"></div>
                    </div>
                </div>
                <div class="row" style="font-size:.85rem;color:#555;">
                    <div class="col-6 mb-2">
                        <i class="fas fa-id-card mr-1 text-success"></i>
                        <strong>DNI:</strong> <span id="dniNumero" style="font-family:monospace;"></span>
                    </div>
                    <div class="col-12" id="dniDireccionRow" style="display:none;">
                        <i class="fas fa-map-marker-alt mr-1 text-success"></i>
                        <strong>Dirección:</strong> <span id="dniDireccion"></span>
                    </div>
                </div>
            </div>

            <!-- Opciones -->
            <div id="dniOpciones"></div>
        </div>

    </div>

    <div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
            <i class="fas fa-times mr-1"></i>Cancelar
        </button>
    </div>
</div>
</div>
</div>

<?php include $ruta_base . "includes/footer.php"; ?>
<script src="js/ventas.js"></script>


