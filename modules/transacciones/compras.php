<?php
// ============================================================
// modules/transacciones/compras.php | Botica 2026
// Compras al contado y credito con cuotas (max. 4)
// Numeracion automatica de comprobantes por tipo
// Lotes se crean SIEMPRE al registrar (independiente del pago)
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!isset($pdo) || !($pdo instanceof PDO)) die('Error: Conexion BD no disponible.');
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_CAJERO'))        define('ROL_CAJERO', 2);
if (!defined('ROL_TRABAJADOR'))    define('ROL_TRABAJADOR', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_CAJERO, ROL_TRABAJADOR]);
verificarPermiso($pdo, 'compras');

$id_usuario = $_SESSION['id_usuario'] ?? 0;

// Patron PRG
$swal = null;
if (isset($_SESSION['swal_comp'])) { $swal = $_SESSION['swal_comp']; unset($_SESSION['swal_comp']); }

// ── Verificar caja abierta ────────────────────────────────────────────────────
$caja_activa_comp = null;
try {
    $stCajaComp = $pdo->prepare("SELECT id_caja, turno, monto_inicial, fecha_apertura FROM caja WHERE id_usuario=? AND estado='abierta' ORDER BY fecha_apertura DESC LIMIT 1");
    $stCajaComp->execute([$id_usuario]);
    $caja_activa_comp = $stCajaComp->fetch();
} catch (PDOException $e) {}
$hay_caja_comp = !empty($caja_activa_comp);

function redirigirComp(string $icon, string $title, string $text): void {
    $_SESSION['swal_comp'] = compact('icon', 'title', 'text');
    header('Location: compras.php'); exit;
}

// Actualizar cuotas vencidas automaticamente
try {
    $pdo->exec("UPDATE cuotas_compra SET estado='vencido' WHERE estado='pendiente' AND fecha_vencimiento < CURDATE()");
} catch (PDOException $e) { /* silencioso si la tabla no existe aun */ }

// AJAX: siguiente numero de comprobante
if (isset($_GET['accion']) && $_GET['accion'] === 'siguiente_numero') {
    $tipo = $_GET['tipo'] ?? 'ticket';
    $prefijos = ['ticket'=>'T001','nota'=>'N001','factura'=>'F001','boleta'=>'B001'];
    $prefijo  = $prefijos[$tipo] ?? 'T001';
    try {
        $stmt = $pdo->prepare("SELECT numero_comprobante FROM compras WHERE tipo_comprobante=? AND numero_comprobante IS NOT NULL ORDER BY id_compra DESC LIMIT 1");
        $stmt->execute([$tipo]);
        $ultimo = $stmt->fetchColumn();
        if ($ultimo) {
            $partes = explode('-', $ultimo);
            $num = (int)end($partes) + 1;
        } else {
            $num = 1;
        }
        echo json_encode(['numero' => $prefijo . '-' . str_pad($num, 8, '0', STR_PAD_LEFT)]);
    } catch (PDOException $e) {
        echo json_encode(['numero' => $prefijo . '-00000001']);
    }
    exit;
}

// AJAX: detalle de compra
if (isset($_GET['accion']) && $_GET['accion'] === 'detalle_ajax') {
    $id = (int)($_GET['id_compra'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT c.*, p.razon_social, u.nombre_completo FROM compras c JOIN proveedores p ON c.id_proveedor=p.id_proveedor JOIN usuarios u ON c.id_usuario=u.id_usuario WHERE c.id_compra=?");
        $stmt->execute([$id]);
        $cab = $stmt->fetch();
        if (!$cab) { echo '<div class="alert alert-warning">Compra no encontrada.</div>'; exit; }

        $det = $pdo->prepare("SELECT dc.*, pr.nombre_producto, pr.codigo, l.codigo_lote, l.fecha_vencimiento FROM detalle_compra dc JOIN productos pr ON dc.id_producto=pr.id_producto JOIN lotes l ON dc.id_lote=l.id_lote WHERE dc.id_compra=?");
        $det->execute([$id]);
        $items = $det->fetchAll();

        $cuotas = []; $pagos = [];
        try {
            $stC = $pdo->prepare("SELECT * FROM cuotas_compra WHERE id_compra=? ORDER BY numero_cuota"); $stC->execute([$id]); $cuotas = $stC->fetchAll();
            $stP = $pdo->prepare("SELECT pc.*, u.nombre_completo FROM pagos_compra pc JOIN usuarios u ON pc.id_usuario=u.id_usuario WHERE pc.id_compra=? ORDER BY pc.fecha"); $stP->execute([$id]); $pagos = $stP->fetchAll();
        } catch (PDOException $e) {}

        $bMap = ['pagado'=>'badge-comp-pagado','pendiente'=>'badge-comp-pendiente','anulado'=>'badge-comp-anulado'];
        echo '<div class="row mb-2">';
        echo '<div class="col-md-5"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-building mr-1"></i>Proveedor</small><strong>'.htmlspecialchars($cab['razon_social']).'</strong></div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-file-invoice mr-1"></i>Comprobante</small><strong>'.htmlspecialchars(ucfirst($cab['tipo_comprobante'])).' '.htmlspecialchars($cab['numero_comprobante']??'---').'</strong></div>';
        echo '<div class="col-md-2"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-credit-card mr-1"></i>T. Pago</small><span class="badge-'.htmlspecialchars($cab['tipo_pago']).'">'.strtoupper($cab['tipo_pago']).'</span></div>';
        echo '<div class="col-md-2"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-circle mr-1"></i>Estado</small><span class="'.($bMap[$cab['estado']]??'').'">'.strtoupper($cab['estado']).'</span></div>';
        echo '</div>';
        echo '<div class="row mb-3 pt-2" style="border-top:1px solid #f0f0f0;">';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-calendar-alt mr-1"></i>Fecha</small>'.date('d/m/Y H:i',strtotime($cab['fecha'])).'</div>';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-user mr-1"></i>Registrado por</small>'.htmlspecialchars($cab['nombre_completo']).'</div>';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-money-bill-wave mr-1"></i>Método de pago</small>'.ucfirst($cab['metodo_pago']).'</div>';
        echo '</div>';
        echo '<h6 class="font-weight-bold text-muted mb-2" style="font-size:.82rem;"><i class="fas fa-boxes mr-1"></i>PRODUCTOS / LOTES</h6>';
        echo '<div class="table-responsive mb-3"><table class="table table-sm table-bordered" style="font-size:.83rem;">';
        echo '<thead style="background:#1a5276;color:#fff;"><tr><th><i class="fas fa-pills mr-1"></i>Producto</th><th><i class="fas fa-tag mr-1"></i>Lote</th><th><i class="fas fa-calendar-times mr-1"></i>Vence</th><th class="text-center"><i class="fas fa-sort-numeric-up mr-1"></i>Cant.</th><th class="text-right"><i class="fas fa-dollar-sign mr-1"></i>P.Compra</th><th class="text-right"><i class="fas fa-calculator mr-1"></i>Subtotal</th></tr></thead><tbody>';
        foreach ($items as $it) {
            $vence = $it['fecha_vencimiento'] ? date('d/m/Y', strtotime($it['fecha_vencimiento'])) : '---';
            $hoy = new DateTime(); $fv = new DateTime($it['fecha_vencimiento']);
            $dias = (int)$hoy->diff($fv)->days * ($fv >= $hoy ? 1 : -1);
            $av = $dias < 0 ? '<span class="alerta-vencido ml-1">VENCIDO</span>' : ($dias <= 90 ? '<span class="alerta-vence-pronto ml-1">'.$dias.'d</span>' : '');
            echo '<tr><td><strong>'.htmlspecialchars($it['nombre_producto']).'</strong><br><small class="text-muted">'.htmlspecialchars($it['codigo']).'</small></td>';
            echo '<td><code style="background:#e0f7fa;color:#117a8b;padding:2px 5px;border-radius:3px;">'.htmlspecialchars($it['codigo_lote']).'</code></td>';
            echo '<td>'.$vence.$av.'</td><td class="text-center">'.$it['cantidad'].'</td>';
            echo '<td class="text-right">S/. '.number_format($it['precio_compra'],2).'</td>';
            echo '<td class="text-right font-weight-bold">S/. '.number_format($it['subtotal'],2).'</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="d-flex justify-content-end mb-3">';
        echo '<div style="background:#f8f9fa;border-radius:10px;padding:14px 20px;border:1px solid #e9ecef;min-width:260px;">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:.87rem;color:#555;border-bottom:1px solid #e9ecef;">';
        echo '<span><i class="fas fa-receipt mr-2 text-muted"></i>Subtotal</span>';
        echo '<strong style="font-family:monospace;">S/. '.number_format($cab['subtotal'],2).'</strong></div>';
        if ($cab['descuento']>0) {
            echo '<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:.87rem;color:#555;border-bottom:1px solid #e9ecef;">';
            echo '<span><i class="fas fa-tag mr-2 text-muted"></i>Descuento</span>';
            echo '<strong style="font-family:monospace;color:#e74c3c;">- S/. '.number_format($cab['descuento'],2).'</strong></div>';
        }
        if ($cab['aplica_igv']) {
            echo '<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:.87rem;color:#555;border-bottom:1px solid #e9ecef;">';
            echo '<span><i class="fas fa-percentage mr-2 text-muted"></i>IGV 18%</span>';
            echo '<strong style="font-family:monospace;">S/. '.number_format($cab['igv'],2).'</strong></div>';
        }
        echo '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0 3px;font-size:1rem;font-weight:700;">';
        echo '<span style="color:#1a5276;"><i class="fas fa-check-circle mr-2"></i>TOTAL</span>';
        echo '<span style="color:#1a7a4a;font-size:1.15rem;font-family:monospace;">S/. '.number_format($cab['total'],2).'</span></div>';
        echo '</div></div>';

        if ($cab['tipo_pago']==='credito' && !empty($cuotas)) {
            $eC=['pendiente'=>'badge-comp-pendiente','pagado'=>'badge-comp-pagado','vencido'=>'badge-comp-anulado'];
            echo '<h6 class="font-weight-bold text-muted mb-2" style="font-size:.82rem;"><i class="fas fa-calendar-alt mr-1"></i>CRONOGRAMA DE CUOTAS</h6>';
            echo '<div class="table-responsive mb-3"><table class="table table-sm table-bordered" style="font-size:.83rem;">';
            echo '<thead style="background:#e67e22;color:#fff;"><tr><th class="text-center">Cuota</th><th class="text-right">Monto</th><th class="text-center">Vencimiento</th><th class="text-center">Estado</th></tr></thead><tbody>';
            foreach ($cuotas as $cq) {
                echo '<tr><td class="text-center font-weight-bold">'.$cq['numero_cuota'].' / '.count($cuotas).'</td>';
                echo '<td class="text-right font-weight-bold">S/. '.number_format($cq['monto_cuota'],2).'</td>';
                echo '<td class="text-center">'.date('d/m/Y',strtotime($cq['fecha_vencimiento'])).'</td>';
                echo '<td class="text-center"><span class="'.($eC[$cq['estado']]??'').'">'.strtoupper($cq['estado']).'</span></td></tr>';
            }
            echo '</tbody></table></div>';
            if ($cab['saldo_pendiente']>0) echo '<div class="alert" style="background:#fff3cd;border-left:4px solid #e67e22;border-radius:6px;padding:10px 14px;font-size:.88rem;"><i class="fas fa-exclamation-circle mr-2" style="color:#e67e22;"></i><strong>Saldo pendiente: S/. '.number_format($cab['saldo_pendiente'],2).'</strong></div>';
        }
        if (!empty($pagos)) {
            echo '<h6 class="font-weight-bold text-muted mb-2" style="font-size:.82rem;"><i class="fas fa-money-bill-wave mr-1"></i>PAGOS REALIZADOS</h6>';
            echo '<div class="table-responsive"><table class="table table-sm table-bordered" style="font-size:.83rem;">';
            echo '<thead style="background:#1a7a4a;color:#fff;"><tr><th>Fecha</th><th>Metodo</th><th class="text-right">Monto</th><th>Registrado por</th></tr></thead><tbody>';
            foreach ($pagos as $pg) {
                echo '<tr><td>'.date('d/m/Y H:i',strtotime($pg['fecha'])).'</td><td>'.strtoupper($pg['metodo_pago']).'</td>';
                echo '<td class="text-right font-weight-bold text-success">S/. '.number_format($pg['monto'],2).'</td>';
                echo '<td>'.htmlspecialchars($pg['nombre_completo']).'</td></tr>';
            }
            echo '</tbody></table></div>';
        }
    } catch (PDOException $e) { echo '<div class="alert alert-danger">Error: '.htmlspecialchars($e->getMessage()).'</div>'; }
    exit;
}

// CRUD POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // AJAX: actualizar precio de compra del producto
    if ($accion === 'actualizar_precio_compra') {
        header('Content-Type: application/json');
        $id_producto  = (int)($_POST['id_producto']  ?? 0);
        $precio_compra = (float)($_POST['precio_compra'] ?? 0);
        if (!$id_producto || $precio_compra <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Datos invalidos.']); exit;
        }
        try {
            $pdo->prepare("UPDATE productos SET precio_compra=? WHERE id_producto=?")->execute([$precio_compra, $id_producto]);
            echo json_encode(['ok' => true]); 
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    // REGISTRAR COMPRA
    if ($accion === 'registrar') {
        // Bloquear si no hay caja abierta
        if (!$hay_caja_comp) redirigirComp('warning','Caja no aperturada','Debes aperturar una caja antes de registrar compras.');
        $id_proveedor       = (int)($_POST['id_proveedor']       ?? 0);
        $tipo_comprobante   = $_POST['tipo_comprobante']          ?? 'factura';
        $numero_comprobante = trim($_POST['numero_comprobante']   ?? '') ?: null;
        $fecha              = $_POST['fecha']                     ?? date('Y-m-d H:i:s');
        $subtotal           = (float)($_POST['subtotal']          ?? 0);
        $descuento_global   = (float)($_POST['descuento_global']  ?? 0);
        $aplica_igv         = isset($_POST['aplica_igv']) ? 1 : 0;
        $igv                = (float)($_POST['igv']               ?? 0);
        $total              = (float)($_POST['total']             ?? 0);
        $tipo_pago          = $_POST['tipo_pago']                 ?? 'contado';
        $metodo_pago        = $_POST['metodo_pago']               ?? 'efectivo';
        $observacion        = trim($_POST['observacion']          ?? '') ?: null;
        $items              = $_POST['items']                     ?? [];
        $num_cuotas         = (int)($_POST['num_cuotas']          ?? 1);
        $fecha_primera_cuota = $_POST['fecha_primera_cuota']      ?? null;
        $frecuencia_dias    = (int)($_POST['frecuencia_dias']     ?? 30);

        if (!$id_proveedor)  redirigirComp('warning','Proveedor requerido','Selecciona un proveedor.');
        if (empty($items))   redirigirComp('warning','Sin productos','Agrega al menos un producto.');
        if ($total <= 0)     redirigirComp('warning','Total invalido','El total debe ser mayor a 0.');
        if ($tipo_pago === 'credito') {
            if ($num_cuotas < 1 || $num_cuotas > 4) redirigirComp('warning','Cuotas invalidas','El numero de cuotas debe ser entre 1 y 4.');
            if (empty($fecha_primera_cuota))         redirigirComp('warning','Fecha requerida','Indica la fecha de la primera cuota.');
        }

        try {
            $pdo->beginTransaction();
            $saldo_pendiente = ($tipo_pago === 'credito') ? $total : 0.00;
            $estado_compra   = ($tipo_pago === 'contado') ? 'pagado' : 'pendiente';
            $metodo_guardado = ($tipo_pago === 'contado') ? $metodo_pago : 'transferencia';

            $pdo->prepare("INSERT INTO compras (id_proveedor,id_usuario,id_caja,fecha,tipo_comprobante,numero_comprobante,subtotal,descuento,aplica_igv,igv,total,saldo_pendiente,tipo_pago,fecha_vencimiento_pago,metodo_pago,estado,observacion) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$id_proveedor,$id_usuario,(int)$caja_activa_comp['id_caja'],$fecha,$tipo_comprobante,$numero_comprobante,$subtotal,$descuento_global,$aplica_igv,$igv,$total,$saldo_pendiente,$tipo_pago,($tipo_pago==='credito'?$fecha_primera_cuota:null),$metodo_guardado,$estado_compra,$observacion]);
            $id_compra = (int)$pdo->lastInsertId();

            foreach ($items as $item) {
                $id_producto       = (int)($item['id_producto']     ?? 0);
                $cantidad          = (int)($item['cantidad']        ?? 0);
                $precio_compra     = (float)($item['precio_compra'] ?? 0);
                $descuento_item    = (float)($item['descuento']     ?? 0);
                $subtotal_item     = (float)($item['subtotal']      ?? 0);
                $codigo_lote       = strtoupper(trim($item['codigo_lote'] ?? ''));
                $fecha_vencimiento = $item['fecha_vencimiento']     ?? null;
                if (!$id_producto || $cantidad <= 0 || !$codigo_lote) continue;

                $stL = $pdo->prepare("SELECT id_lote FROM lotes WHERE id_producto=? AND codigo_lote=?");
                $stL->execute([$id_producto,$codigo_lote]);
                $loteEx = $stL->fetch();
                if ($loteEx) {
                    $id_lote = $loteEx['id_lote'];
                    $pdo->prepare("UPDATE lotes SET stock_actual=stock_actual+?,stock_inicial=stock_inicial+? WHERE id_lote=?")->execute([$cantidad,$cantidad,$id_lote]);
                } else {
                    $pdo->prepare("INSERT INTO lotes (id_producto,codigo_lote,fecha_vencimiento,stock_inicial,stock_actual) VALUES (?,?,?,?,?)")->execute([$id_producto,$codigo_lote,$fecha_vencimiento,$cantidad,$cantidad]);
                    $id_lote = (int)$pdo->lastInsertId();
                }
                $pdo->prepare("INSERT INTO detalle_compra (id_compra,id_producto,id_lote,cantidad,precio_compra,descuento,subtotal) VALUES (?,?,?,?,?,?,?)")->execute([$id_compra,$id_producto,$id_lote,$cantidad,$precio_compra,$descuento_item,$subtotal_item]);
                $pdo->prepare("UPDATE productos SET stock=stock+?,precio_compra=? WHERE id_producto=?")->execute([$cantidad,$precio_compra,$id_producto]);
            }

            if ($tipo_pago === 'credito') {
                $monto_cuota = round($total / $num_cuotas, 2);
                $diferencia  = round($total - ($monto_cuota * $num_cuotas), 2);
                $fecha_cuota = new DateTime($fecha_primera_cuota);
                for ($i = 1; $i <= $num_cuotas; $i++) {
                    $monto_esta = ($i === $num_cuotas) ? round($monto_cuota + $diferencia, 2) : $monto_cuota;
                    $pdo->prepare("INSERT INTO cuotas_compra (id_compra,numero_cuota,monto_cuota,fecha_vencimiento,estado) VALUES (?,?,?,?,'pendiente')")->execute([$id_compra,$i,$monto_esta,$fecha_cuota->format('Y-m-d')]);
                    $fecha_cuota->modify("+{$frecuencia_dias} days");
                }
            }

            $pdo->commit();
            // Registrar movimiento en caja solo si es compra al contado
            if ($tipo_pago === 'contado' && !empty($caja_activa_comp)) {
                try {
                    $desc_mov = "Compra #{$id_compra} - " . ucfirst($tipo_comprobante) . " " . ($numero_comprobante ?? "");
                    $pdo->prepare("INSERT INTO movimientos_caja (id_caja,tipo_referencia,id_referencia,id_usuario,tipo,descripcion,monto,metodo_pago) VALUES (?,'compra',?,?,'egreso',?,?,?)")
                        ->execute([$caja_activa_comp['id_caja'],$id_compra,$id_usuario,trim($desc_mov),$total,$metodo_pago]);
                } catch (PDOException $e) {}
            }
            $msg = $tipo_pago==='contado' ? "Compra #$id_compra registrada al contado. Lotes y stock actualizados." : "Compra #$id_compra registrada a credito en $num_cuotas cuota(s). Lotes y stock actualizados.";
            redirigirComp('success','Compra registrada!',$msg);
        } catch (PDOException $e) {
            $pdo->rollBack();
            redirigirComp('error','Error al registrar','Error: '.$e->getMessage());
        }

    // REGISTRAR PAGO (abono a credito)
    } elseif ($accion === 'registrar_pago') {
        $id_compra   = (int)($_POST['id_compra']    ?? 0);
        $monto       = (float)($_POST['monto_pago'] ?? 0);
        $metodo_pago = $_POST['metodo_pago_abono']  ?? 'efectivo';
        $observacion = trim($_POST['obs_pago']       ?? '') ?: null;
        if (!$id_compra) redirigirComp('warning','Error','Compra no identificada.');
        if ($monto <= 0) redirigirComp('warning','Monto invalido','El monto debe ser mayor a 0.');
        try {
            $pdo->beginTransaction();
            $stC = $pdo->prepare("SELECT total,saldo_pendiente,estado FROM compras WHERE id_compra=?"); $stC->execute([$id_compra]); $comp = $stC->fetch();
            if (!$comp || $comp['estado']==='anulado') throw new Exception('La compra no existe o esta anulada.');
            if ($comp['saldo_pendiente'] <= 0) throw new Exception('Esta compra ya esta completamente pagada.');
            if ($monto > $comp['saldo_pendiente']) throw new Exception('El monto (S/. '.number_format($monto,2).') supera el saldo pendiente (S/. '.number_format($comp['saldo_pendiente'],2).').');
            $pdo->prepare("INSERT INTO pagos_compra (id_compra,id_usuario,metodo_pago,monto,observacion) VALUES (?,?,?,?,?)")->execute([$id_compra,$id_usuario,$metodo_pago,$monto,$observacion]);
            $nuevo_saldo = round($comp['saldo_pendiente'] - $monto, 2);
            $nuevo_estado = ($nuevo_saldo <= 0) ? 'pagado' : 'pendiente';
            $pdo->prepare("UPDATE compras SET saldo_pendiente=?,estado=? WHERE id_compra=?")->execute([$nuevo_saldo,$nuevo_estado,$id_compra]);
            if ($nuevo_saldo <= 0) {
                $pdo->prepare("UPDATE cuotas_compra SET estado='pagado' WHERE id_compra=? AND estado!='pagado'")->execute([$id_compra]);
            } else {
                $totalPagado = round($comp['total'] - $nuevo_saldo, 2);
                $stCq = $pdo->prepare("SELECT id_cuota,monto_cuota FROM cuotas_compra WHERE id_compra=? ORDER BY numero_cuota"); $stCq->execute([$id_compra]);
                $acum = 0;
                foreach ($stCq->fetchAll() as $cq) {
                    $acum += $cq['monto_cuota'];
                    if ($acum <= $totalPagado) $pdo->prepare("UPDATE cuotas_compra SET estado='pagado' WHERE id_cuota=?")->execute([$cq['id_cuota']]);
                }
            }
            $pdo->commit();
            // Registrar movimiento en caja por abono de compra a crédito
            if ($hay_caja_comp) {
                try {
                    $desc_mov = "Abono Compra #{$id_compra} (" . ucfirst($metodo_pago) . " - crédito)";
                    $pdo->prepare("INSERT INTO movimientos_caja (id_caja,tipo_referencia,id_referencia,id_usuario,tipo,descripcion,monto,metodo_pago) VALUES (?,'compra',?,?,'egreso',?,?,?)")
                        ->execute([$caja_activa_comp['id_caja'],$id_compra,$id_usuario,$desc_mov,$monto,$metodo_pago]);
                } catch (PDOException $e) {}
            }
            $msg = $nuevo_saldo<=0 ? "Pago registrado. Compra #$id_compra completamente pagada." : "Pago de S/. ".number_format($monto,2)." registrado. Saldo restante: S/. ".number_format($nuevo_saldo,2).".";
            redirigirComp('success','Pago registrado!',$msg);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            redirigirComp('error','Error al registrar pago',$e->getMessage());
        }

    // ANULAR COMPRA
    } elseif ($accion === 'anular') {
        $id_compra = (int)($_POST['id_compra'] ?? 0);
        if (!$id_compra) redirigirComp('warning','ID invalido','No se pudo identificar la compra.');
        try {
            $pdo->beginTransaction();
            $stC = $pdo->prepare("SELECT estado FROM compras WHERE id_compra=?"); $stC->execute([$id_compra]); $comp = $stC->fetch();
            if (!$comp || $comp['estado']==='anulado') { $pdo->rollBack(); redirigirComp('warning','No se puede anular','La compra ya esta anulada o no existe.'); }
            $det = $pdo->prepare("SELECT id_producto,id_lote,cantidad FROM detalle_compra WHERE id_compra=?"); $det->execute([$id_compra]);
            foreach ($det->fetchAll() as $d) {
                $pdo->prepare("UPDATE lotes    SET stock_actual=GREATEST(0,stock_actual-?) WHERE id_lote=?")->execute([$d['cantidad'],$d['id_lote']]);
                $pdo->prepare("UPDATE productos SET stock=GREATEST(0,stock-?) WHERE id_producto=?")->execute([$d['cantidad'],$d['id_producto']]);
            }
            $pdo->prepare("UPDATE compras SET estado='anulado',saldo_pendiente=0 WHERE id_compra=?")->execute([$id_compra]);
            try { $pdo->prepare("UPDATE cuotas_compra SET estado='pagado' WHERE id_compra=?")->execute([$id_compra]); } catch(PDOException $e){}
            $pdo->commit();
            redirigirComp('info','Compra anulada',"La compra #$id_compra fue anulada y el stock fue revertido.");
        } catch (PDOException $e) { $pdo->rollBack(); redirigirComp('error','Error al anular','Error: '.$e->getMessage()); }
    }
}

// DATOS
$proveedores = $productos_lista = $compras = [];
$stats = ['total_mes'=>0,'monto_mes'=>0,'pendientes'=>0,'anuladas'=>0];
try {
    $proveedores    = $pdo->query("SELECT id_proveedor,razon_social,ruc,telefono FROM proveedores WHERE estado=1 ORDER BY razon_social")->fetchAll();
    $productos_lista = $pdo->query("SELECT p.id_producto,p.codigo,p.nombre_producto,p.laboratorio,p.precio_compra,c.nombre_categoria FROM productos p LEFT JOIN categorias c ON p.id_categoria=c.id_categoria WHERE p.estado=1 ORDER BY p.nombre_producto")->fetchAll();
    $compras = $pdo->query("SELECT c.id_compra,c.fecha,c.tipo_comprobante,c.numero_comprobante,c.total,c.tipo_pago,c.metodo_pago,c.estado,c.saldo_pendiente,p.razon_social,u.nombre_completo,
        (SELECT COUNT(*) FROM cuotas_compra cc WHERE cc.id_compra=c.id_compra AND cc.estado='pendiente') AS cuotas_pendientes,
        (SELECT COUNT(*) FROM cuotas_compra cc WHERE cc.id_compra=c.id_compra AND cc.estado='vencido')   AS cuotas_vencidas,
        (SELECT COUNT(*) FROM cuotas_compra cc WHERE cc.id_compra=c.id_compra) AS total_cuotas
        FROM compras c JOIN proveedores p ON c.id_proveedor=p.id_proveedor JOIN usuarios u ON c.id_usuario=u.id_usuario ORDER BY c.fecha DESC LIMIT 200")->fetchAll();
    $mes = date('Y-m');
    $st = $pdo->prepare("SELECT COUNT(*) AS total_mes,COALESCE(SUM(total),0) AS monto_mes,SUM(estado='pendiente') AS pendientes,SUM(estado='anulado') AS anuladas FROM compras WHERE DATE_FORMAT(fecha,'%Y-%m')=?"); $st->execute([$mes]); $stats = $st->fetch();
} catch (PDOException $e) { $swal = ['icon'=>'error','title'=>'Error','text'=>'Error al cargar datos: '.$e->getMessage()]; }

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<link rel="stylesheet" href="css/compras.css">
<div class="content-wrapper">
<div class="content-header"><div class="container-fluid">
<div class="page-header-comp d-flex justify-content-between align-items-center flex-wrap">
<div><h4><i class="fas fa-truck-fast mr-2"></i>Registro de Compras</h4>
<small><i class="fas fa-map-marker-alt mr-1"></i>Botica 2026 &rsaquo; Transacciones &rsaquo; Compras</small></div>
<a href="/botica-2026/modules/transacciones/historial/historial_compras.php" class="btn btn-light font-weight-bold btn-sm">
    <i class="fas fa-history mr-1"></i>Ver Historial
</a>
</div></div></div>
<div class="content"><div class="container-fluid">
<?php if ($swal): ?><script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({icon:'<?= $swal['icon'] ?>',title:'<?= addslashes($swal['title']) ?>',text:'<?= addslashes($swal['text']) ?>',confirmButtonColor:'#1a5276',timer:<?= in_array($swal['icon'],['success','info'])?3500:0 ?>,timerProgressBar:<?= in_array($swal['icon'],['success','info'])?'true':'false' ?>,showConfirmButton:<?= in_array($swal['icon'],['success','info'])?'false':'true' ?>,});});</script><?php endif; ?>

<?php if (!$hay_caja_comp): ?>
<div id="alertaCajaComp" style="background:linear-gradient(135deg,#fff3cd,#ffeeba);border:2px solid #ffc107;border-radius:12px;padding:20px 24px;margin-bottom:20px;display:flex;align-items:center;gap:16px;">
    <div style="width:56px;height:56px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid #ffc107;">
        <i class="fas fa-lock" style="font-size:1.5rem;color:#e67e22;"></i>
    </div>
    <div style="flex:1;">
        <h5 style="color:#856404;font-weight:700;margin:0 0 4px;">Caja no aperturada</h5>
        <p style="color:#856404;font-size:.9rem;margin:0;">No puedes registrar compras sin una caja abierta. Apertura tu caja primero.</p>
    </div>
    <a href="/botica-2026/modules/caja/caja.php" class="btn btn-sm font-weight-bold" style="background:#e67e22;color:#fff;border-radius:8px;white-space:nowrap;flex-shrink:0;">
        <i class="fas fa-lock-open mr-1"></i>Ir a Caja
    </a>
</div>
<?php endif; ?>
<div id="seccionNuevaCompra" <?= !$hay_caja_comp ? 'class="form-compra-card mb-4 form-bloqueado" style="opacity:.55;"' : 'class="form-compra-card mb-4"' ?>>
<div class="form-compra-header d-flex justify-content-between align-items-center">
<h5><i class="fas fa-plus-circle mr-2"></i>Nueva Compra</h5>
<button type="button" id="btnLimpiarForm" class="btn btn-sm btn-outline-light"><i class="fas fa-eraser mr-1"></i>Limpiar</button>
</div>
<div class="form-compra-body">
<form id="formNuevaCompra" method="POST">
<input type="hidden" name="accion" value="registrar">
<input type="hidden" name="id_proveedor" id="id_proveedor_hidden">
<input type="hidden" name="subtotal" id="hidden_subtotal" value="0">
<input type="hidden" name="igv" id="hidden_igv" value="0">
<input type="hidden" name="total" id="hidden_total" value="0">
<input type="hidden" name="metodo_pago" id="hidden_metodo_pago" value="efectivo">
<input type="hidden" name="tipo_pago" id="hidden_tipo_pago" value="contado">
<input type="hidden" name="num_cuotas" id="hidden_num_cuotas" value="1">
<div id="inputsOcultos"></div>

<div class="row mb-3">
<div class="col-md-5">
<label class="form-label-comp"><i class="fas fa-building mr-1 text-muted"></i>Proveedor <span class="text-danger">*</span></label>
<div class="d-flex gap-2">
<div id="campoProveedor" class="campo-selector flex-grow-1" style="cursor:pointer;">
<span class="placeholder-text">Haz clic para seleccionar proveedor...</span>
<span class="selected-text" style="display:none;"></span>
<i class="fas fa-search text-muted" style="font-size:.8rem;"></i>
</div>
<button type="button" id="btnSeleccionarProveedor" class="btn btn-sm btn-outline-primary" title="Buscar"><i class="fas fa-search"></i></button>
<button type="button" id="btnLimpiarProveedor" class="btn btn-sm btn-outline-danger" title="Quitar"><i class="fas fa-times"></i></button>
</div>
</div>
<div class="col-md-2">
<label class="form-label-comp"><i class="fas fa-file-invoice mr-1 text-muted"></i>Tipo Comprobante</label>
<select class="form-control form-control-sm" name="tipo_comprobante" id="tipo_comprobante">
<option value="ticket" selected>Ticket</option>
<option value="nota">Nota de Venta</option>
</select>
</div>
<div class="col-md-3">
<label class="form-label-comp"><i class="fas fa-hashtag mr-1 text-muted"></i>N&deg; Comprobante <small class="text-muted">(autogenerado)</small></label>
<div class="input-group input-group-sm">
<div class="input-group-prepend"><span class="input-group-text" id="prefijo_comprobante" style="font-family:monospace;font-weight:700;color:#1a5276;background:#e3f2fd;border-color:#bbdefb;">T001</span></div>
<input type="text" class="form-control" name="numero_comprobante" id="numero_comprobante" readonly style="background:#f8f9fa;font-family:monospace;font-weight:700;color:#1a5276;">
</div>
<small class="text-muted">Se genera automaticamente al elegir el tipo</small>
</div>
<div class="col-md-2">
<label class="form-label-comp"><i class="fas fa-calendar mr-1 text-muted"></i>Fecha</label>
<input type="datetime-local" class="form-control form-control-sm" name="fecha" value="<?= date('Y-m-d\TH:i') ?>">
</div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
<label class="form-label-comp mb-0"><i class="fas fa-boxes mr-1 text-muted"></i>Productos <span id="contadorItems" class="text-muted" style="font-weight:400;font-size:.8rem;">0 productos</span></label>
<button type="button" id="btnAgregarProducto" class="btn btn-sm" style="background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;font-weight:600;"><i class="fas fa-plus mr-1"></i>Agregar Producto</button>
</div>
<div class="tabla-detalle-wrapper mb-3">
<table id="tablaDetalle" class="table tabla-detalle table-sm mb-0">
<thead><tr>
<th style="width:40px;">#</th><th>Producto</th>
<th style="width:160px;">Lote / Vencimiento</th>
<th style="width:80px;" class="text-center">Cant.</th>
<th style="width:100px;" class="text-right">P. Compra</th>
<th style="width:90px;" class="text-right">Descuento</th>
<th style="width:100px;" class="text-right">Subtotal</th>
<th style="width:40px;"></th>
</tr></thead>
<tbody><tr class="fila-vacia"><td colspan="8"><i class="fas fa-box-open"></i>Sin productos agregados.<br><small>Haz clic en "Agregar Producto" para comenzar.</small></td></tr></tbody>
</table>
</div>

<div class="row">
<div class="col-md-7">
<div class="seccion-pago">
<h6><i class="fas fa-credit-card mr-2"></i>Informacion de Pago</h6>
<div class="row">
<div class="col-md-6 mb-2">
<label class="form-label-comp">Tipo de Pago</label>
<div class="tipo-pago-group d-flex gap-2">
<button type="button" class="btn-tipo-pago activo" data-tipo="contado" style="flex:1;border:2px solid #dee2e6;border-radius:8px;padding:8px;font-size:.85rem;font-weight:700;cursor:pointer;background:#fff;color:#555;transition:all .2s;">
<i class="fas fa-money-bill-wave mr-1"></i>Contado
</button>
<button type="button" class="btn-tipo-pago" data-tipo="credito" style="flex:1;border:2px solid #dee2e6;border-radius:8px;padding:8px;font-size:.85rem;font-weight:700;cursor:pointer;background:#fff;color:#555;transition:all .2s;">
<i class="fas fa-calendar-alt mr-1"></i>Credito
</button>
</div>
</div>
<div class="col-md-6 mb-2" id="bloqueMetodoPago">
<label class="form-label-comp">Metodo de Pago</label>
<div class="metodo-pago-group">
<button type="button" class="btn-metodo activo" data-metodo="efectivo"><i class="fas fa-money-bill-wave mr-1"></i>Efectivo</button>
<button type="button" class="btn-metodo" data-metodo="yape"><i class="fas fa-mobile-alt mr-1"></i>Yape</button>
<button type="button" class="btn-metodo" data-metodo="plin"><i class="fas fa-mobile-alt mr-1"></i>Plin</button>
<button type="button" class="btn-metodo" data-metodo="transferencia"><i class="fas fa-university mr-1"></i>Transferencia</button>
<button type="button" class="btn-metodo" data-metodo="tarjeta"><i class="fas fa-credit-card mr-1"></i>Tarjeta</button>
</div>
</div>
</div>

<div id="panelCredito" style="display:none;">
<hr class="my-2">
<div style="font-size:.85rem;font-weight:700;color:#e67e22;margin-bottom:10px;"><i class="fas fa-calendar-alt mr-1"></i>Configuracion de Cuotas <small class="text-muted ml-1">(maximo 4 cuotas)</small></div>
<div class="row">
<div class="col-md-4 mb-2">
<label class="form-label-comp">N&deg; de Cuotas</label>
<div class="d-flex gap-2">
<button type="button" class="btn-cuota activo" data-cuotas="1">1</button>
<button type="button" class="btn-cuota" data-cuotas="2">2</button>
<button type="button" class="btn-cuota" data-cuotas="3">3</button>
<button type="button" class="btn-cuota" data-cuotas="4">4</button>
</div>
</div>
<div class="col-md-4 mb-2">
<label class="form-label-comp">Fecha 1ra Cuota <span class="text-danger">*</span></label>
<input type="date" class="form-control form-control-sm" name="fecha_primera_cuota" id="fecha_primera_cuota" min="<?= date('Y-m-d') ?>">
</div>
<div class="col-md-4 mb-2">
<label class="form-label-comp">Frecuencia</label>
<select class="form-control form-control-sm" name="frecuencia_dias" id="frecuencia_dias">
<option value="30">Mensual (30 dias)</option>
<option value="15">Quincenal (15 dias)</option>
<option value="7">Semanal (7 dias)</option>
<option value="60">Bimestral (60 dias)</option>
</select>
</div>
</div>
<div id="previewCuotas" style="display:none;margin-top:8px;"></div>
</div>

<div class="row mt-2">
<div class="col-12">
<label class="form-label-comp">Observacion</label>
<textarea class="form-control form-control-sm" name="observacion" rows="2" placeholder="Notas adicionales (opcional)..." maxlength="200"></textarea>
</div>
</div>
</div>
</div>
<div class="col-md-5">
<div class="resumen-totales">
<div class="resumen-row"><span>Subtotal</span><span class="monto" id="resumen_subtotal">S/. 0.00</span></div>
<div class="resumen-row">
<span>Descuento global <input type="number" step="0.01" min="0" id="descuento_global" name="descuento_global" value="0" style="width:70px;font-size:.8rem;padding:2px 6px;border:1px solid #dee2e6;border-radius:4px;margin-left:6px;"></span>
<span class="monto" id="resumen_descuento">- S/. 0.00</span>
</div>
<div class="resumen-row" id="filaIgv">
<span><label class="mb-0" style="cursor:pointer;"><input type="checkbox" id="aplica_igv" name="aplica_igv" checked style="margin-right:4px;">IGV (18%)</label></span>
<span class="monto" id="resumen_igv">S/. 0.00</span>
</div>
<div class="resumen-row total-final"><span><i class="fas fa-equals mr-1"></i>TOTAL</span><span class="monto" id="resumen_total">S/. 0.00</span></div>
<div id="resumenCuota" style="display:none;margin-top:8px;padding:8px 10px;background:#fff3e0;border-radius:6px;border-left:3px solid #e67e22;">
<div style="font-size:.78rem;color:#e67e22;font-weight:600;"><i class="fas fa-calendar-alt mr-1"></i>CREDITO</div>
<div id="resumenCuotaTexto" style="font-size:.82rem;color:#555;margin-top:2px;"></div>
</div>
</div>
<div class="mt-3 d-flex gap-2 justify-content-end">
<button type="button" class="btn btn-secondary btn-sm mr-2" onclick="$('#btnLimpiarForm').trigger('click')"><i class="fas fa-eraser mr-1"></i>Limpiar</button>
<button type="submit" id="btnSubmitCompra" class="btn btn-sm font-weight-bold" style="background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;padding:8px 24px;"><i class="fas fa-save mr-1"></i>Registrar Compra</button>
</div>
</div>
</div>
</form>
</div>
</div>

</div></div>

<!-- MODAL SELECTOR PROVEEDOR -->
<div class="modal fade" id="modalSelectorProveedor" tabindex="-1" aria-hidden="true" style="z-index:1060;">
<div class="modal-dialog modal-md"><div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a5276,#2980b9);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
<div><h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-building mr-2"></i>Seleccionar Proveedor</h6>
<small style="color:rgba(255,255,255,.75);">Haz clic en un proveedor para seleccionarlo</small></div>
<button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-3">
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="input-group" style="flex:1;margin-right:10px;box-shadow:0 2px 8px rgba(0,0,0,.08);border-radius:8px;overflow:hidden;">
        <div class="input-group-prepend"><span class="input-group-text" style="background:#f8f9fa;border:none;border-right:1px solid #dee2e6;"><i class="fas fa-search text-muted"></i></span></div>
        <input type="text" id="buscarProveedor" class="form-control" placeholder="Buscar por nombre o RUC..." style="border:none;font-size:.9rem;">
    </div>
    <button type="button" id="btnNuevoProveedorRuc" class="btn btn-sm font-weight-bold" style="background:#1a5276;color:#fff;white-space:nowrap;">
        <i class="fas fa-building mr-1"></i>Nuevo por RUC
    </button>
</div>
<div class="d-flex justify-content-between mb-2"><small class="text-muted"><span id="contProveedores"><?= count($proveedores) ?></span> proveedores</small></div>
<div style="max-height:380px;overflow-y:auto;"><div id="listaProveedores">
<?php foreach ($proveedores as $prov): ?>
<div class="item-selector" data-id="<?= $prov['id_proveedor'] ?>" data-nombre="<?= htmlspecialchars($prov['razon_social'],ENT_QUOTES) ?>" data-ruc="<?= htmlspecialchars($prov['ruc'],ENT_QUOTES) ?>">
<div class="d-flex align-items-center gap-3">
<div style="width:38px;height:38px;background:#e3f2fd;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-industry" style="color:#1a5276;font-size:.85rem;"></i></div>
<div><div class="item-nombre"><?= htmlspecialchars($prov['razon_social']) ?></div>
<div class="item-sub"><i class="fas fa-id-card mr-1"></i>RUC: <?= htmlspecialchars($prov['ruc']) ?><?php if($prov['telefono']): ?> &nbsp;|&nbsp; <i class="fas fa-phone mr-1"></i><?= htmlspecialchars($prov['telefono']) ?><?php endif; ?></div>
</div></div></div>
<?php endforeach; ?>
<div id="sinResultadosProv" style="display:none;text-align:center;padding:30px;color:#999;"><i class="fas fa-search" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px;"></i>No se encontraron proveedores</div>
</div></div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:10px 16px;">
<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
</div>
</div></div></div>

<!-- MODAL SELECTOR PRODUCTO -->
<div class="modal fade" id="modalSelectorProducto" tabindex="-1" aria-hidden="true" style="z-index:1060;">
<div class="modal-dialog modal-lg"><div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a7a4a,#27ae60);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
<div><h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-capsules mr-2"></i>Seleccionar Producto</h6>
<small style="color:rgba(255,255,255,.75);">Busca y haz clic en el producto para agregarlo</small></div>
<button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-3">
<div class="input-group mb-3" style="box-shadow:0 2px 8px rgba(0,0,0,.08);border-radius:8px;overflow:hidden;">
<div class="input-group-prepend"><span class="input-group-text" style="background:#f8f9fa;border:none;border-right:1px solid #dee2e6;"><i class="fas fa-search text-muted"></i></span></div>
<input type="text" id="buscarProducto" class="form-control" placeholder="Buscar por nombre, codigo o laboratorio..." style="border:none;font-size:.9rem;">
</div>
<div class="d-flex justify-content-between mb-2"><small class="text-muted"><span id="contProductos"><?= count($productos_lista) ?></span> productos disponibles</small></div>
<div style="max-height:420px;overflow-y:auto;"><div id="listaProductos">
<?php foreach ($productos_lista as $prod): ?>
<div class="item-selector" data-id="<?= $prod['id_producto'] ?>" data-nombre="<?= htmlspecialchars($prod['nombre_producto'],ENT_QUOTES) ?>" data-codigo="<?= htmlspecialchars($prod['codigo'],ENT_QUOTES) ?>" data-lab="<?= htmlspecialchars($prod['laboratorio']??'',ENT_QUOTES) ?>" data-precio="<?= $prod['precio_compra'] ?>">
<div class="d-flex align-items-center justify-content-between">
<div class="d-flex align-items-center gap-3">
<div style="width:38px;height:38px;background:#e8f5e9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-pills" style="color:#1a7a4a;font-size:.85rem;"></i></div>
<div><div class="item-nombre"><?= htmlspecialchars($prod['nombre_producto']) ?></div>
<div class="item-sub"><code style="background:#e0f7fa;color:#117a8b;padding:1px 5px;border-radius:3px;font-size:.72rem;"><?= htmlspecialchars($prod['codigo']) ?></code>
<?php if($prod['laboratorio']): ?> &nbsp;<?= htmlspecialchars($prod['laboratorio']) ?><?php endif; ?>
<?php if($prod['nombre_categoria']): ?> &nbsp;|&nbsp; <?= htmlspecialchars($prod['nombre_categoria']) ?><?php endif; ?>
</div></div></div>
<div class="text-right" style="flex-shrink:0;"><div style="font-size:.75rem;color:#999;">P. Compra</div><div style="font-weight:700;color:#1a5276;font-size:.9rem;">S/. <?= number_format($prod['precio_compra'],2) ?></div></div>
</div></div>
<?php endforeach; ?>
<div id="sinResultadosProd" style="display:none;text-align:center;padding:30px;color:#999;"><i class="fas fa-search" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px;"></i>No se encontraron productos</div>
</div></div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:10px 16px;">
<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
</div>
</div></div></div>

<!-- MODAL DETALLE ITEM -->
<div class="modal fade" id="modalDetalleItem" tabindex="-1" aria-hidden="true" style="z-index:1070;">
<div class="modal-dialog"><div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#117a8b,#1a5276);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
<div><h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-box mr-2"></i>Detalle del Producto</h6>
<small id="modal_item_nombre" style="color:rgba(255,255,255,.85);font-size:.85rem;"></small></div>
<button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-4">
<input type="hidden" id="modal_item_id_producto">
<small id="modal_item_codigo" class="text-muted d-block mb-3"></small>
<div class="row">
<div class="col-md-4 mb-3"><label class="form-label-comp">Cantidad <span class="text-danger">*</span></label>
<div class="input-group input-group-sm"><div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-sort-numeric-up"></i></span></div>
<input type="number" class="form-control" id="modal_item_cantidad" min="1" value="1"></div></div>
<div class="col-md-4 mb-3"><label class="form-label-comp">Precio Compra</label>
<div class="input-group input-group-sm">
<div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
<input type="number" step="0.01" class="form-control" id="modal_item_precio" min="0.01" readonly style="background:#f0fff4;color:#1a7a4a;font-weight:700;cursor:default;">
<div class="input-group-append">
<button type="button" class="btn btn-sm btn-outline-warning" id="btnEditarPrecioComp" title="Editar precio de compra del producto" style="border-radius:0 4px 4px 0;">
<i class="fas fa-pencil-alt"></i>
</button>
</div>
</div>
<small class="text-muted" id="hint_precio_comp">Precio registrado — clic en <i class="fas fa-pencil-alt"></i> para actualizar</small>
</div>
<div class="col-md-4 mb-3"><label class="form-label-comp">Descuento</label>
<div class="input-group input-group-sm"><div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
<input type="number" step="0.01" class="form-control" id="modal_item_descuento" min="0" value="0.00"></div></div>
</div>
<hr class="my-2">
<div class="row">
<div class="col-md-6 mb-3"><label class="form-label-comp"><i class="fas fa-barcode mr-1 text-muted"></i>Codigo de Lote <span class="text-danger">*</span></label>
<div class="input-group input-group-sm"><div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-tag"></i></span></div>
<input type="text" class="form-control" id="modal_item_lote" placeholder="Ej: LOT-2026-001" maxlength="50" style="text-transform:uppercase;"></div>
<small class="text-muted">Codigo del fabricante en el empaque</small></div>
<div class="col-md-6 mb-3"><label class="form-label-comp"><i class="fas fa-calendar-times mr-1 text-muted"></i>Fecha de Vencimiento <span class="text-danger">*</span></label>
<input type="date" class="form-control form-control-sm" id="modal_item_vencimiento" min="<?= date('Y-m-d') ?>">
<small class="text-muted">Fecha impresa en el producto</small></div>
</div>
<div class="d-flex justify-content-between align-items-center mt-2 p-3" style="background:#f8f9fa;border-radius:8px;">
<span style="font-weight:600;color:#555;">Subtotal:</span>
<span id="modal_item_subtotal" style="font-size:1.2rem;font-weight:700;color:#1a7a4a;">S/. 0.00</span>
</div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
<button type="button" id="btnConfirmarItem" class="btn btn-sm font-weight-bold" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);color:#fff;"><i class="fas fa-plus mr-1"></i>Agregar a la compra</button>
</div>
</div></div></div>

<!-- ══════════════════════════════════════════════════════════
     MODAL NUEVO PROVEEDOR POR RUC
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalNuevoProveedorRuc" tabindex="-1" aria-hidden="true" style="z-index:1080;">
<div class="modal-dialog modal-md">
<div class="modal-content" style="border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25);">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#1a5276,#2980b9);padding:20px 24px;position:relative;">
        <button type="button" class="close" data-dismiss="modal" style="position:absolute;top:14px;right:18px;color:#fff;opacity:.8;font-size:1.4rem;">&times;</button>
        <div class="d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-building" style="font-size:1.3rem;color:#fff;"></i>
            </div>
            <div>
                <h5 style="color:#fff;font-weight:700;margin:0;font-size:1.05rem;">Buscar Proveedor por RUC</h5>
                <small style="color:rgba(255,255,255,.8);">Consulta automática a SUNAT</small>
            </div>
        </div>
    </div>

    <!-- Body -->
    <div class="modal-body" style="padding:24px;">

        <!-- Input RUC -->
        <div class="form-group mb-4">
            <label style="font-weight:700;font-size:.82rem;color:#495057;text-transform:uppercase;letter-spacing:.5px;">
                <i class="fas fa-id-card mr-1" style="color:#1a5276;"></i> Número de RUC
            </label>
            <div class="input-group">
                <input type="text" id="inputRucModal" class="form-control form-control-lg"
                    maxlength="11" placeholder="Ej: 20123456789"
                    style="font-family:monospace;font-size:1.1rem;letter-spacing:2px;text-align:center;border-radius:10px 0 0 10px;border:2px solid #dee2e6;">
                <div class="input-group-append">
                    <button type="button" id="btnBuscarRucModal" class="btn btn-lg font-weight-bold"
                        style="background:#1a5276;color:#fff;border-radius:0 10px 10px 0;padding:0 20px;">
                        <i class="fas fa-search mr-1"></i>Buscar
                    </button>
                </div>
            </div>
            <small class="text-muted">Ingresa los 11 dígitos del RUC del proveedor</small>
        </div>

        <!-- Estado -->
        <div id="rucModalEstado" style="display:none;"></div>

        <!-- Resultado -->
        <div id="rucModalResultado" style="display:none;">
            <div style="background:linear-gradient(135deg,#e3f2fd,#e8eaf6);border:1.5px solid #bbdefb;border-radius:12px;padding:18px 20px;margin-bottom:16px;">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div style="width:52px;height:52px;background:#1a5276;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-building" style="color:#fff;font-size:1.2rem;"></i>
                    </div>
                    <div>
                        <div id="rucRazonSocial" style="font-size:1.05rem;font-weight:700;color:#2d3436;"></div>
                        <div id="rucBadge" style="margin-top:3px;"></div>
                    </div>
                </div>
                <div class="row" style="font-size:.85rem;color:#555;">
                    <div class="col-6 mb-2">
                        <i class="fas fa-id-card mr-1" style="color:#1a5276;"></i>
                        <strong>RUC:</strong> <span id="rucNumero" style="font-family:monospace;"></span>
                    </div>
                    <div class="col-12" id="rucDireccionRow" style="display:none;">
                        <i class="fas fa-map-marker-alt mr-1" style="color:#1a5276;"></i>
                        <strong>Dirección:</strong> <span id="rucDireccion"></span>
                    </div>
                </div>
            </div>

            <!-- Opciones -->
            <div id="rucOpciones"></div>
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

<?php include $ruta_base . 'includes/footer.php'; ?>
<script src="js/compras.js"></script>


