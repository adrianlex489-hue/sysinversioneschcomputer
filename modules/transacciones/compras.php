<?php
// ============================================================
// modules/transacciones/compras.php | SysInversiones 2026
// Compras al contado y credito con cuotas (max. 4)
// Comprobantes: ticket de compra y nota de compra
// Sin manejo de lotes
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';
require_once $ruta_base . 'conf/auditoria.php';

$id_usuario = $_SESSION['id_usuario'] ?? 0;

// Patron PRG
$swal = null;
if (isset($_SESSION['swal_comp'])) { $swal = $_SESSION['swal_comp']; unset($_SESSION['swal_comp']); }

// ── Caja (solo para registrar movimientos, no bloquea el formulario) ──────────
// Se busca cualquier caja abierta (no solo la del usuario actual)
// para que compras registradas por cualquier usuario afecten la caja correctamente.
$caja_activa_comp = null;
try {
    $caja_activa_comp = $pdo->query("SELECT id_caja, nombre, monto_inicial, fecha_apertura FROM caja WHERE estado='abierta' ORDER BY fecha_apertura DESC LIMIT 1")->fetch();
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

// Asegurar que el ENUM de orden_cotizaciones tenga los estados nuevos
try {
    $pdo->exec("ALTER TABLE orden_cotizaciones MODIFY COLUMN estado 
        ENUM('cotizado','aprobado','rechazado','comprado','pendiente_compra','completado') 
        NOT NULL DEFAULT 'cotizado'");
} catch (PDOException $e) { /* ya existe o tabla no existe */ }

// AJAX: siguiente numero de comprobante
if (isset($_GET['accion']) && $_GET['accion'] === 'siguiente_numero') {
    $tipo = $_GET['tipo'] ?? 'ticket';
    $prefijos = ['ticket' => 'T001', 'nota' => 'N001'];
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

        $det = $pdo->prepare("SELECT dc.*, pr.nombre_producto, pr.codigo FROM detalle_compra dc JOIN productos pr ON dc.id_producto=pr.id_producto WHERE dc.id_compra=?");
        $det->execute([$id]);
        $items = $det->fetchAll();

        $cuotas = []; $pagos = [];
        try {
            $stC = $pdo->prepare("SELECT * FROM cuotas_compra WHERE id_compra=? ORDER BY numero_cuota"); $stC->execute([$id]); $cuotas = $stC->fetchAll();
            $stP = $pdo->prepare("SELECT pc.*, u.nombre_completo FROM pagos_compra pc JOIN usuarios u ON pc.id_usuario=u.id_usuario WHERE pc.id_compra=? ORDER BY pc.fecha"); $stP->execute([$id]); $pagos = $stP->fetchAll();
        } catch (PDOException $e) {}

        $tipoLabel = ['ticket' => 'Ticket de Compra', 'nota' => 'Nota de Compra'];
        $bMap = ['pagado'=>'badge-comp-pagado','pendiente'=>'badge-comp-pendiente','anulado'=>'badge-comp-anulado'];
        echo '<div class="row mb-2">';
        echo '<div class="col-md-5"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-building mr-1"></i>Proveedor</small><strong>'.htmlspecialchars($cab['razon_social']).'</strong></div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-file-invoice mr-1"></i>Comprobante</small><strong>'.htmlspecialchars($tipoLabel[$cab['tipo_comprobante']] ?? ucfirst($cab['tipo_comprobante'])).' '.htmlspecialchars($cab['numero_comprobante']??'---').'</strong></div>';
        echo '<div class="col-md-2"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-credit-card mr-1"></i>T. Pago</small><span class="badge-'.htmlspecialchars($cab['tipo_pago']).'">'.strtoupper($cab['tipo_pago']).'</span></div>';
        echo '<div class="col-md-2"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-circle mr-1"></i>Estado</small><span class="'.($bMap[$cab['estado']]??'').'">'.strtoupper($cab['estado']).'</span></div>';
        echo '</div>';
        echo '<div class="row mb-3 pt-2" style="border-top:1px solid #f0f0f0;">';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-calendar-alt mr-1"></i>Fecha</small>'.date('d/m/Y H:i',strtotime($cab['fecha'])).'</div>';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-user mr-1"></i>Registrado por</small>'.htmlspecialchars($cab['nombre_completo']).'</div>';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-money-bill-wave mr-1"></i>Método de pago</small>'.ucfirst($cab['metodo_pago']).'</div>';
        echo '</div>';
        echo '<h6 class="font-weight-bold text-muted mb-2" style="font-size:.82rem;"><i class="fas fa-boxes mr-1"></i>PRODUCTOS</h6>';
        echo '<div class="table-responsive mb-3"><table class="table table-sm table-bordered" style="font-size:.83rem;">';
        echo '<thead style="background:#1a5276;color:#fff;"><tr><th><i class="fas fa-box mr-1"></i>Producto</th><th class="text-center"><i class="fas fa-sort-numeric-up mr-1"></i>Cant.</th><th class="text-right"><i class="fas fa-dollar-sign mr-1"></i>P.Compra</th><th class="text-right"><i class="fas fa-tag mr-1"></i>Descuento</th><th class="text-right"><i class="fas fa-calculator mr-1"></i>Subtotal</th></tr></thead><tbody>';
        foreach ($items as $it) {
            echo '<tr><td><strong>'.htmlspecialchars($it['nombre_producto']).'</strong><br><small class="text-muted">'.htmlspecialchars($it['codigo']).'</small></td>';
            echo '<td class="text-center">'.$it['cantidad'].'</td>';
            echo '<td class="text-right">S/. '.number_format($it['precio_compra'],2).'</td>';
            echo '<td class="text-right">'.($it['descuento']>0 ? 'S/. '.number_format($it['descuento'],2) : '---').'</td>';
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
            if (isset($cab['saldo_pendiente']) && $cab['saldo_pendiente']>0) echo '<div class="alert" style="background:#fff3cd;border-left:4px solid #e67e22;border-radius:6px;padding:10px 14px;font-size:.88rem;"><i class="fas fa-exclamation-circle mr-2" style="color:#e67e22;"></i><strong>Saldo pendiente: S/. '.number_format($cab['saldo_pendiente'],2).'</strong></div>';
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
        $id_producto   = (int)($_POST['id_producto']   ?? 0);
        $precio_compra = (float)($_POST['precio_compra'] ?? 0);
        if (!$id_producto || $precio_compra <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Datos invalidos.']); exit;
        }
        try {
            $stPrecioC = $pdo->prepare("SELECT precio_compra, nombre_producto FROM productos WHERE id_producto=?");
            $stPrecioC->execute([$id_producto]);
            $prod_pc = $stPrecioC->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare("UPDATE productos SET precio_compra=? WHERE id_producto=?")->execute([$precio_compra, $id_producto]);
            registrarAuditoria($pdo, 'compras', 'editar', 'productos', $id_producto,
                "Precio de compra modificado en módulo Compras — " . ($prod_pc['nombre_producto'] ?? ''),
                'precio_compra', $prod_pc['precio_compra'] ?? null, $precio_compra);
            echo json_encode(['ok' => true]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    // REGISTRAR COMPRA
    if ($accion === 'registrar') {
        // ── Validación: caja debe estar abierta ───────────────────────────────
        if (!$hay_caja_comp) {
            redirigirComp('warning', 'Caja no aperturada', 'Debes abrir una caja antes de registrar compras. Ve al módulo de Caja y apertura una sesión.');
        }

        $id_proveedor        = (int)($_POST['id_proveedor']       ?? 0);
        $tipo_comprobante    = $_POST['tipo_comprobante']          ?? 'ticket';
        $numero_comprobante  = trim($_POST['numero_comprobante']   ?? '') ?: null;
        $fecha               = $_POST['fecha']                     ?? date('Y-m-d H:i:s');
        $subtotal            = (float)($_POST['subtotal']          ?? 0);
        $descuento_global    = (float)($_POST['descuento_global']  ?? 0);
        $aplica_igv          = isset($_POST['aplica_igv']) ? 1 : 0;
        $igv                 = (float)($_POST['igv']               ?? 0);
        $total               = (float)($_POST['total']             ?? 0);
        $tipo_pago           = $_POST['tipo_pago']                 ?? 'contado';
        $metodo_pago         = $_POST['metodo_pago']               ?? 'efectivo';
        $observacion         = trim($_POST['observacion']          ?? '') ?: null;
        $items               = $_POST['items']                     ?? [];
        $num_cuotas          = (int)($_POST['num_cuotas']          ?? 1);
        $fecha_primera_cuota = $_POST['fecha_primera_cuota']       ?? null;
        $frecuencia_dias     = (int)($_POST['frecuencia_dias']     ?? 30);

        // Validar tipo de comprobante permitido
        if (!in_array($tipo_comprobante, ['ticket', 'nota'])) $tipo_comprobante = 'ticket';

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
            $id_caja_comp    = $hay_caja_comp ? (int)$caja_activa_comp['id_caja'] : null;

            $pdo->prepare("INSERT INTO compras (id_proveedor,id_usuario,id_caja,fecha,tipo_comprobante,numero_comprobante,subtotal,descuento,aplica_igv,igv,total,saldo_pendiente,tipo_pago,metodo_pago,estado,observacion) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$id_proveedor,$id_usuario,$id_caja_comp,$fecha,$tipo_comprobante,$numero_comprobante,$subtotal,$descuento_global,$aplica_igv,$igv,$total,$saldo_pendiente,$tipo_pago,$metodo_guardado,$estado_compra,$observacion]);
            $id_compra = (int)$pdo->lastInsertId();

            foreach ($items as $item) {
                $id_producto    = (int)($item['id_producto']     ?? 0);
                $cantidad       = (int)($item['cantidad']        ?? 0);
                $precio_compra  = (float)($item['precio_compra'] ?? 0);
                $descuento_item = (float)($item['descuento']     ?? 0);
                $subtotal_item  = (float)($item['subtotal']      ?? 0);
                $id_cotizacion  = (int)($item['id_cotizacion']   ?? 0) ?: null;
                if (!$id_producto || $cantidad <= 0) continue;

                $pdo->prepare("INSERT INTO detalle_compra (id_compra,id_producto,cantidad,precio_compra,descuento,subtotal,id_cotizacion) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$id_compra,$id_producto,$cantidad,$precio_compra,$descuento_item,$subtotal_item,$id_cotizacion]);
                $pdo->prepare("UPDATE productos SET stock=stock+?,precio_compra=? WHERE id_producto=?")
                    ->execute([$cantidad,$precio_compra,$id_producto]);

                // Obtener stock actualizado tras la compra
                $stStock = $pdo->prepare("SELECT stock FROM productos WHERE id_producto = ?");
                $stStock->execute([$id_producto]);
                $stock_nuevo = (int)$stStock->fetchColumn();

                // Si viene vinculado a una cotización específica, actualizarla
                if ($id_cotizacion) {
                    $stCot = $pdo->prepare("SELECT cantidad FROM orden_cotizaciones WHERE id = ?");
                    $stCot->execute([$id_cotizacion]);
                    $cant_cot = (int)$stCot->fetchColumn();
                    $nuevo_estado = ($stock_nuevo >= $cant_cot) ? 'completado' : 'comprado';
                    $pdo->prepare("UPDATE orden_cotizaciones SET estado = ? WHERE id = ?")
                        ->execute([$nuevo_estado, $id_cotizacion]);
                }

                // Resolver automáticamente cotizaciones pendiente_compra del mismo producto
                // aunque no estén vinculadas explícitamente (compra directa sin botón "Agregar a compra")
                $excluir_id = $id_cotizacion ?: 0;
                $stPend = $pdo->prepare("
                    SELECT id, cantidad 
                    FROM orden_cotizaciones 
                    WHERE id_producto = ? 
                      AND estado = 'pendiente_compra'
                      AND id != ?
                    ORDER BY fecha_cotizacion ASC
                ");
                $stPend->execute([$id_producto, $excluir_id]);
                $pendientes = $stPend->fetchAll(PDO::FETCH_ASSOC);

                // Recargar stock_restante porque puede haber cambiado
                $stStock2 = $pdo->prepare("SELECT stock FROM productos WHERE id_producto = ?");
                $stStock2->execute([$id_producto]);
                $stock_restante = (int)$stStock2->fetchColumn();

                foreach ($pendientes as $pend) {
                    if ($stock_restante >= (int)$pend['cantidad']) {
                        // Marcar como completado Y descontar el stock
                        // (estas cotizaciones nunca descontaron stock al crearse porque no había)
                        $pdo->prepare("UPDATE orden_cotizaciones SET estado = 'completado' WHERE id = ?")
                            ->execute([$pend['id']]);
                        $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id_producto = ?")
                            ->execute([$pend['cantidad'], $id_producto]);
                        $stock_restante -= (int)$pend['cantidad'];
                    }
                }
            }
            if ($tipo_pago === 'credito') {
                $monto_cuota = round($total / $num_cuotas, 2);
                $diferencia  = round($total - ($monto_cuota * $num_cuotas), 2);
                $fecha_cuota = new DateTime($fecha_primera_cuota);
                for ($i = 1; $i <= $num_cuotas; $i++) {
                    $monto_esta = ($i === $num_cuotas) ? round($monto_cuota + $diferencia, 2) : $monto_cuota;
                    $pdo->prepare("INSERT INTO cuotas_compra (id_compra,numero_cuota,monto_cuota,fecha_vencimiento,estado) VALUES (?,?,?,?,'pendiente')")
                        ->execute([$id_compra,$i,$monto_esta,$fecha_cuota->format('Y-m-d')]);
                    $fecha_cuota->modify("+{$frecuencia_dias} days");
                }
            }

            // Registrar movimiento en caja solo si es compra al contado — dentro de la transacción
            if ($tipo_pago === 'contado' && !empty($caja_activa_comp)) {
                $tipoLabel = ['ticket'=>'Ticket de Compra','nota'=>'Nota de Compra'];
                $desc_mov  = "Compra #{$id_compra} - " . ($tipoLabel[$tipo_comprobante] ?? ucfirst($tipo_comprobante)) . " " . ($numero_comprobante ?? "");
                $pdo->prepare("INSERT INTO movimientos_caja (id_caja,tipo_referencia,id_referencia,id_usuario,tipo,descripcion,monto,metodo_pago) VALUES (?,'compra',?,?,'egreso',?,?,?)")
                    ->execute([$caja_activa_comp['id_caja'],$id_compra,$id_usuario,trim($desc_mov),$total,$metodo_pago]);
            }

            $pdo->commit();

            // Auditoría de compra registrada
            registrarAuditoria($pdo, 'compras', 'crear', 'compras', $id_compra,
                "Compra #{$id_compra} — {$tipo_comprobante} {$numero_comprobante} — Total: S/. " . number_format($total, 2) . " — Pago: {$tipo_pago}");

            $msg = $tipo_pago==='contado'
                ? "Compra #$id_compra registrada al contado. Stock actualizado."
                : "Compra #$id_compra registrada a credito en $num_cuotas cuota(s). Stock actualizado.";
            redirigirComp('success','Compra registrada!',$msg);
        } catch (PDOException $e) {
            $pdo->rollBack();
            redirigirComp('error','Error al registrar','Error: '.$e->getMessage());
        }

    // REGISTRAR PAGO (abono a credito)
    } elseif ($accion === 'registrar_pago') {
        // ── Validación: caja debe estar abierta ───────────────────────────────
        if (!$hay_caja_comp) {
            redirigirComp('warning', 'Caja no aperturada', 'Debes abrir una caja antes de registrar pagos. Ve al módulo de Caja y apertura una sesión.');
        }

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
            $nuevo_saldo  = round($comp['saldo_pendiente'] - $monto, 2);
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
            // Movimiento en caja por abono a crédito — dentro de la transacción
            if ($hay_caja_comp) {
                $desc_mov = "Abono Compra #{$id_compra} (" . ucfirst($metodo_pago) . " - credito)";
                $pdo->prepare("INSERT INTO movimientos_caja (id_caja,tipo_referencia,id_referencia,id_usuario,tipo,descripcion,monto,metodo_pago) VALUES (?,'compra',?,?,'egreso',?,?,?)")
                    ->execute([$caja_activa_comp['id_caja'],$id_compra,$id_usuario,$desc_mov,$monto,$metodo_pago]);
            }

            $pdo->commit();
            // Auditoría del abono a proveedor
            registrarAuditoria($pdo, 'compras', 'editar', 'compras', $id_compra,
                "Abono crédito Compra #{$id_compra} — S/. " . number_format($monto, 2) . " (" . ucfirst($metodo_pago) . ") — Saldo restante: S/. " . number_format($nuevo_saldo, 2),
                'saldo_pendiente', $comp['saldo_pendiente'], $nuevo_saldo);
            $msg = $nuevo_saldo<=0
                ? "Pago registrado. Compra #$id_compra completamente pagada."
                : "Pago de S/. ".number_format($monto,2)." registrado. Saldo restante: S/. ".number_format($nuevo_saldo,2).".";
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
            // Revertir stock
            $det = $pdo->prepare("SELECT id_producto,cantidad FROM detalle_compra WHERE id_compra=?"); $det->execute([$id_compra]);
            foreach ($det->fetchAll() as $d) {
                $pdo->prepare("UPDATE productos SET stock=GREATEST(0,stock-?) WHERE id_producto=?")->execute([$d['cantidad'],$d['id_producto']]);
            }
            $pdo->prepare("UPDATE compras SET estado='anulado', saldo_pendiente=0 WHERE id_compra=?")->execute([$id_compra]);
            try { $pdo->prepare("UPDATE cuotas_compra SET estado='pagado' WHERE id_compra=?")->execute([$id_compra]); } catch(PDOException $e){}
            $pdo->commit();
            registrarAuditoria($pdo, 'compras', 'anular', 'compras', $id_compra,
                "Compra #{$id_compra} anulada — stock revertido", 'estado', 'pagado/pendiente', 'anulado');
            redirigirComp('info','Compra anulada',"La compra #$id_compra fue anulada y el stock fue revertido.");
        } catch (PDOException $e) { $pdo->rollBack(); redirigirComp('error','Error al anular','Error: '.$e->getMessage()); }
    }
}

// DATOS
$proveedores = $productos_lista = [];
$stats = ['total_mes'=>0,'monto_mes'=>0,'pendientes'=>0,'anuladas'=>0];
$repuestos_pendientes = [];
try {
    $proveedores     = $pdo->query("SELECT id_proveedor,razon_social,ruc,telefono FROM proveedores WHERE estado=1 ORDER BY razon_social")->fetchAll();
    $productos_lista = $pdo->query("SELECT p.id_producto,p.codigo,p.nombre_producto,p.precio_compra,c.nombre_categoria FROM productos p LEFT JOIN categorias c ON p.id_categoria=c.id_categoria WHERE p.estado=1 ORDER BY p.nombre_producto")->fetchAll();
    $mes = date('Y-m');
    $st = $pdo->prepare("SELECT COUNT(*) AS total_mes,COALESCE(SUM(total),0) AS monto_mes,SUM(estado='pendiente') AS pendientes,SUM(estado='anulado') AS anuladas FROM compras WHERE DATE_FORMAT(fecha,'%Y-%m')=?");
    $st->execute([$mes]); $stats = $st->fetch();

    // Repuestos aprobados pendientes de compra
    try {
        $stRep = $pdo->query("
            SELECT oc.id, oc.descripcion, oc.cantidad, oc.precio_unitario, oc.subtotal, oc.nota,
                   oc.id_producto, p.nombre_producto, p.codigo AS codigo_producto,
                   os.id_orden, CONCAT('ORD-', LPAD(os.id_orden,6,'0')) AS num_orden,
                   CASE os.tipo_cliente
                       WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
                       ELSE TRIM(CONCAT_WS(', ',
                           NULLIF(TRIM(CONCAT_WS(' ', cn.apellido_paterno, cn.apellido_materno)),''),
                           NULLIF(TRIM(cn.nombres),'')
                       ))
                   END AS cliente_nombre,
                   e.tipo AS equipo_tipo, e.marca, e.modelo
            FROM orden_cotizaciones oc
            JOIN ordenes_servicio os ON os.id_orden = oc.id_orden
            LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = os.id_cliente AND os.tipo_cliente = 'natural'
            LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = os.id_cliente AND os.tipo_cliente = 'empresa'
            JOIN equipos  e ON e.id_equipo  = os.id_equipo
            LEFT JOIN productos p ON p.id_producto = oc.id_producto
            WHERE oc.estado = 'aprobado'
            ORDER BY oc.fecha_cotizacion ASC
        ");
        $repuestos_pendientes = $stRep->fetchAll();
    } catch (PDOException $e) { $repuestos_pendientes = []; }

} catch (PDOException $e) { $swal = ['icon'=>'error','title'=>'Error','text'=>'Error al cargar datos: '.$e->getMessage()]; }

$tipoLabel = ['ticket'=>'Ticket de Compra','nota'=>'Nota de Compra'];

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<link rel="stylesheet" href="css/compras.css">
<div class="content-wrapper">
<div class="content-header"><div class="container-fluid">
<div class="page-header-comp d-flex justify-content-between align-items-center flex-wrap">
<div>
    <h4><i class="fas fa-truck mr-2"></i>Registro de Compras</h4>
    <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones &rsaquo; Transacciones &rsaquo; Compras</small>
</div>
<a href="historial/historial_compras.php" class="btn btn-light font-weight-bold btn-sm">
    <i class="fas fa-history mr-1"></i>Ver Historial
</a>
</div></div></div>

<div class="content"><div class="container-fluid">

<?php if ($swal): ?>
<script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({icon:'<?= $swal['icon'] ?>',title:'<?= addslashes($swal['title']) ?>',text:'<?= addslashes($swal['text']) ?>',confirmButtonColor:'#1a5276',timer:<?= in_array($swal['icon'],['success','info'])?3500:0 ?>,timerProgressBar:<?= in_array($swal['icon'],['success','info'])?'true':'false' ?>,showConfirmButton:<?= in_array($swal['icon'],['success','info'])?'false':'true' ?>,});});</script>
<?php endif; ?>

<!-- Stats -->
<div class="row mb-3">
<div class="col-6 col-md-3 mb-2">
    <div class="stat-mini-comp" style="background:linear-gradient(135deg,#1a5276,#2980b9);">
        <i class="fas fa-shopping-cart"></i>
        <div><div class="stat-value"><?= (int)$stats['total_mes'] ?></div><div class="stat-label">Compras este mes</div></div>
    </div>
</div>
<div class="col-6 col-md-3 mb-2">
    <div class="stat-mini-comp" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);">
        <i class="fas fa-money-bill-wave"></i>
        <div><div class="stat-value">S/. <?= number_format($stats['monto_mes'],0) ?></div><div class="stat-label">Monto del mes</div></div>
    </div>
</div>
<div class="col-6 col-md-3 mb-2">
    <div class="stat-mini-comp" style="background:linear-gradient(135deg,#e67e22,#f39c12);">
        <i class="fas fa-clock"></i>
        <div><div class="stat-value"><?= (int)$stats['pendientes'] ?></div><div class="stat-label">Pendientes de pago</div></div>
    </div>
</div>
<div class="col-6 col-md-3 mb-2">
    <div class="stat-mini-comp" style="background:linear-gradient(135deg,#e74c3c,#c0392b);">
        <i class="fas fa-ban"></i>
        <div><div class="stat-value"><?= (int)$stats['anuladas'] ?></div><div class="stat-label">Anuladas</div></div>
    </div>
</div>
</div>

<?php if (!empty($repuestos_pendientes)): ?>
<!-- ── ALERTA: REPUESTOS PENDIENTES DE COMPRA ── -->
<div class="mb-4" style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1.5px solid #fde68a;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(217,119,6,.12);">
    <div style="background:linear-gradient(90deg,#92400e,#d97706);padding:10px 18px;display:flex;align-items:center;justify-content:space-between;">
        <div style="color:#fff;font-weight:700;font-size:.9rem;">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?= count($repuestos_pendientes) ?> repuesto<?= count($repuestos_pendientes)>1?'s':'' ?> pendiente<?= count($repuestos_pendientes)>1?'s':'' ?> de compra
        </div>
        <button type="button" data-toggle="collapse" data-target="#collapseRepuestos"
            style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);color:#fff;border-radius:6px;padding:3px 12px;font-size:.78rem;font-weight:600;cursor:pointer;">
            <i class="fas fa-eye mr-1"></i>Ver detalle
        </button>
    </div>
    <div class="collapse" id="collapseRepuestos">
        <div style="padding:14px 18px;">
            <p style="font-size:.82rem;color:#92400e;margin-bottom:12px;">
                <i class="fas fa-info-circle mr-1"></i>
                Estos repuestos fueron aprobados por los clientes. Al registrar la compra, usa el botón
                <strong>"Agregar a compra"</strong> para pre-cargar el producto en el formulario.
            </p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered" style="font-size:.82rem;background:#fff;border-radius:8px;overflow:hidden;">
                    <thead style="background:#92400e;color:#fff;">
                        <tr>
                            <th>Orden</th>
                            <th>Cliente / Equipo</th>
                            <th>Repuesto</th>
                            <th class="text-center">Cant.</th>
                            <th class="text-right">P.Unit.</th>
                            <th class="text-right">Subtotal</th>
                            <th class="text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($repuestos_pendientes as $rp): ?>
                    <tr>
                        <td>
                            <a href="/sysinversioneschcomputer/modules/servicios/orden_trabajo.php?id=<?= $rp['id_orden'] ?>"
                               target="_blank" style="font-family:monospace;font-weight:700;color:#1e40af;font-size:.8rem;">
                                <?= htmlspecialchars($rp['num_orden']) ?>
                            </a>
                        </td>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($rp['cliente_nombre']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($rp['equipo_tipo'].' '.$rp['marca'].($rp['modelo']?' '.$rp['modelo']:'')) ?></small>
                        </td>
                        <td>
                            <div style="font-weight:600;"><?= htmlspecialchars($rp['nombre_producto'] ?: $rp['descripcion']) ?></div>
                            <?php if ($rp['nota']): ?>
                            <small class="text-muted"><i class="fas fa-sticky-note mr-1"></i><?= htmlspecialchars($rp['nota']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center font-weight-bold"><?= $rp['cantidad'] ?></td>
                        <td class="text-right">S/. <?= number_format($rp['precio_unitario'],2) ?></td>
                        <td class="text-right font-weight-bold" style="color:#92400e;">S/. <?= number_format($rp['subtotal'],2) ?></td>
                        <td class="text-center">
                            <button type="button" class="btn-vincular-cotizacion"
                                data-id-cot="<?= $rp['id'] ?>"
                                data-id-producto="<?= $rp['id_producto'] ?: '' ?>"
                                data-nombre="<?= htmlspecialchars($rp['nombre_producto'] ?: $rp['descripcion'], ENT_QUOTES) ?>"
                                data-precio="<?= $rp['precio_unitario'] ?>"
                                data-cantidad="<?= $rp['cantidad'] ?>"
                                data-orden="<?= htmlspecialchars($rp['num_orden'], ENT_QUOTES) ?>"
                                style="background:linear-gradient(135deg,#92400e,#d97706);color:#fff;border:none;border-radius:6px;font-size:.72rem;font-weight:600;padding:5px 10px;cursor:pointer;white-space:nowrap;">
                                <i class="fas fa-plus mr-1"></i>Agregar a compra
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!$hay_caja_comp): ?>
<!-- Sin caja abierta: solo informativo, no bloquea -->
<?php endif; ?>

<!-- FORMULARIO NUEVA COMPRA -->
<div id="seccionNuevaCompra" class="form-compra-card mb-4">
<div class="form-compra-header d-flex justify-content-between align-items-center">
    <h5><i class="fas fa-plus-circle mr-2"></i>Nueva Compra</h5>
    <button type="button" id="btnLimpiarForm" class="btn btn-sm btn-outline-light"><i class="fas fa-eraser mr-1"></i>Limpiar</button>
</div>
<div class="form-compra-body">

<?php if (!$hay_caja_comp): ?>
<div class="alert alert-warning d-flex align-items-center mb-3" style="border-left:4px solid #e67e22;border-radius:8px;background:#fff8f0;">
    <i class="fas fa-exclamation-triangle fa-2x mr-3" style="color:#e67e22;"></i>
    <div>
        <strong>Caja no aperturada</strong><br>
        <span style="font-size:.9rem;">No puedes registrar compras sin una caja abierta. <a href="../Caja/caja.php" class="font-weight-bold" style="color:#e67e22;">Ir al módulo de Caja →</a></span>
    </div>
</div>
<div id="wrapperSinCajaComp" style="position:relative;opacity:.5;user-select:none;">
    <div id="overlaySinCajaComp" style="position:absolute;inset:0;z-index:10;background:transparent;"></div>
<?php endif; ?>

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
        <div class="d-flex" style="gap:8px;">
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
            <option value="ticket" selected>Ticket de Compra</option>
            <option value="nota">Nota de Compra</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label-comp"><i class="fas fa-hashtag mr-1 text-muted"></i>N&deg; Comprobante <small class="text-muted">(autogenerado)</small></label>
        <div class="input-group input-group-sm">
            <div class="input-group-prepend"><span class="input-group-text" id="prefijo_comprobante" style="font-family:monospace;font-weight:700;color:#1a5276;background:#e3f2fd;border-color:#bbdefb;">TC001</span></div>
            <input type="text" class="form-control" name="numero_comprobante" id="numero_comprobante" readonly style="background:#f8f9fa;font-family:monospace;font-weight:700;color:#1a5276;">
        </div>
        <small class="text-muted">Se genera automaticamente al elegir el tipo</small>
    </div>
    <div class="col-md-2">
        <label class="form-label-comp"><i class="fas fa-calendar mr-1 text-muted"></i>Fecha</label>
        <input type="datetime-local" class="form-control form-control-sm" name="fecha" value="<?= date('Y-m-d\TH:i') ?>">
    </div>
</div>

<!-- TABLA PRODUCTOS -->
<div class="d-flex justify-content-between align-items-center mb-2">
    <label class="form-label-comp mb-0"><i class="fas fa-boxes mr-1 text-muted"></i>Productos <span id="contadorItems" class="text-muted" style="font-weight:400;font-size:.8rem;">0 productos</span></label>
    <button type="button" id="btnAgregarProducto" class="btn btn-sm" style="background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;font-weight:600;"><i class="fas fa-plus mr-1"></i>Agregar Producto</button>
</div>
<div class="tabla-detalle-wrapper mb-3">
<table id="tablaDetalle" class="table tabla-detalle table-sm mb-0">
<thead><tr>
    <th style="width:40px;">#</th>
    <th>Producto</th>
    <th style="width:80px;" class="text-center">Cant.</th>
    <th style="width:100px;" class="text-right">P. Compra</th>
    <th style="width:90px;" class="text-right">Descuento</th>
    <th style="width:100px;" class="text-right">Subtotal</th>
    <th style="width:40px;"></th>
</tr></thead>
<tbody>
    <tr class="fila-vacia"><td colspan="7"><i class="fas fa-box-open"></i>Sin productos agregados.<br><small>Haz clic en "Agregar Producto" para comenzar.</small></td></tr>
</tbody>
</table>
</div>

<div class="row">
<div class="col-md-7">
<div class="seccion-pago">
<h6><i class="fas fa-credit-card mr-2"></i>Informacion de Pago</h6>
<div class="row">
    <div class="col-md-6 mb-2">
        <label class="form-label-comp">Tipo de Pago</label>
        <div class="tipo-pago-group d-flex" style="gap:8px;">
            <button type="button" class="btn-tipo-pago activo" data-tipo="contado" style="flex:1;">
                <i class="fas fa-money-bill-wave mr-1"></i>Contado
            </button>
            <button type="button" class="btn-tipo-pago" data-tipo="credito" style="flex:1;">
                <i class="fas fa-calendar-alt mr-1"></i>Credito
            </button>
        </div>
    </div>
    <div class="col-md-6 mb-2" id="bloqueMetodoPago">
        <label class="form-label-comp">Metodo de Pago</label>
        <div class="metodo-pago-group">
            <button type="button" class="btn-metodo activo" data-metodo="efectivo"><i class="fas fa-money-bill-wave mr-1"></i>Efectivo</button>
            <button type="button" class="btn-metodo" data-metodo="yape"><i class="fas fa-mobile-alt mr-1"></i>Yape</button>
            <button type="button" class="btn-metodo" data-metodo="transferencia"><i class="fas fa-university mr-1"></i>Transferencia</button>
        </div>
    </div>
</div>

<div id="panelCredito" style="display:none;">
<hr class="my-2">
<div style="font-size:.85rem;font-weight:700;color:#e67e22;margin-bottom:10px;"><i class="fas fa-calendar-alt mr-1"></i>Configuracion de Cuotas <small class="text-muted ml-1">(maximo 4 cuotas)</small></div>
<div class="row">
    <div class="col-md-4 mb-2">
        <label class="form-label-comp">N&deg; de Cuotas</label>
        <div class="d-flex" style="gap:8px;">
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
<div class="mt-3 mb-4 d-flex justify-content-end">
    <button type="button" class="btn btn-secondary btn-sm mr-2" onclick="$('#btnLimpiarForm').trigger('click')"><i class="fas fa-eraser mr-1"></i>Limpiar</button>
    <button type="submit" id="btnSubmitCompra" class="btn btn-sm font-weight-bold" style="background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;padding:8px 24px;"><i class="fas fa-save mr-1"></i>Registrar Compra</button>
</div>
</div>
</div>
</form>
<?php if (!$hay_caja_comp): ?>
</div><!-- /wrapperSinCajaComp -->
<?php endif; ?>
</div>
</div>



</div></div><!-- /content /container-fluid -->

<!-- ══ MODAL VER DETALLE ══ -->
<div class="modal fade" id="modalVerCompra" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg"><div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a5276,#2980b9);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
    <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-file-invoice mr-2"></i>Detalle de Compra</h6>
    <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-4" id="cuerpoDetalleCompra">
    <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;">
    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cerrar</button>
</div>
</div></div></div>

<!-- ══ MODAL REGISTRAR PAGO ══ -->
<div class="modal fade" id="modalPagarCompra" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-sm"><div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a7a4a,#27ae60);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
    <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-money-bill-wave mr-2"></i>Registrar Pago</h6>
    <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-4">
<form method="POST" id="formPagarCompra">
<input type="hidden" name="accion" value="registrar_pago">
<input type="hidden" name="id_compra" id="pago_id_compra">
<div class="mb-3">
    <small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;">Proveedor</small>
    <strong id="pago_proveedor_label"></strong>
</div>
<div class="mb-3">
    <small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;">Saldo pendiente</small>
    <span id="pago_saldo_display" style="font-size:1.3rem;font-weight:700;color:#e67e22;"></span>
</div>
<div class="mb-3">
    <label class="form-label-comp">Monto a pagar <span class="text-danger">*</span></label>
    <div class="input-group input-group-sm">
        <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
        <input type="number" step="0.01" min="0.01" class="form-control" name="monto_pago" id="monto_pago" required>
    </div>
</div>
<div class="mb-3">
    <label class="form-label-comp">Metodo de pago</label>
    <div class="d-flex flex-wrap" style="gap:8px;">
        <button type="button" class="btn-metodo-pago" data-metodo="efectivo"><i class="fas fa-money-bill-wave mr-1"></i>Efectivo</button>
        <button type="button" class="btn-metodo-pago" data-metodo="yape"><i class="fas fa-mobile-alt mr-1"></i>Yape</button>
        <button type="button" class="btn-metodo-pago" data-metodo="transferencia"><i class="fas fa-university mr-1"></i>Transferencia</button>
    </div>
    <input type="hidden" name="metodo_pago_abono" id="metodo_pago_abono" value="efectivo">
</div>
<div class="mb-2">
    <label class="form-label-comp">Observacion</label>
    <input type="text" class="form-control form-control-sm" name="obs_pago" placeholder="Opcional..." maxlength="100">
</div>
</form>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;">
    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
    <button type="submit" form="formPagarCompra" class="btn btn-sm font-weight-bold" style="background:#1a7a4a;color:#fff;"><i class="fas fa-check mr-1"></i>Registrar Pago</button>
</div>
</div></div></div>

<!-- ══ MODAL SELECTOR PROVEEDOR ══ -->
<div class="modal fade" id="modalSelectorProveedor" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-md"><div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a5276,#2980b9);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
    <div>
        <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-building mr-2"></i>Seleccionar Proveedor</h6>
        <small style="color:rgba(255,255,255,.75);">Haz clic en un proveedor para seleccionarlo</small>
    </div>
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
    <div class="d-flex align-items-center" style="gap:12px;">
        <div style="width:38px;height:38px;background:#e3f2fd;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-industry" style="color:#1a5276;font-size:.85rem;"></i></div>
        <div>
            <div class="item-nombre"><?= htmlspecialchars($prov['razon_social']) ?></div>
            <div class="item-sub"><i class="fas fa-id-card mr-1"></i>RUC: <?= htmlspecialchars($prov['ruc']) ?><?php if($prov['telefono']): ?> &nbsp;|&nbsp; <i class="fas fa-phone mr-1"></i><?= htmlspecialchars($prov['telefono']) ?><?php endif; ?></div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<div id="sinResultadosProv" style="display:none;text-align:center;padding:30px;color:#999;"><i class="fas fa-search" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px;"></i>No se encontraron proveedores</div>
</div></div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:10px 16px;">
    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
</div>
</div></div></div>

<!-- ══ MODAL NUEVO PROVEEDOR POR RUC ══ -->
<div class="modal fade" id="modalNuevoProveedorRuc" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-md"><div class="modal-content" style="border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25);">
<div style="background:linear-gradient(135deg,#1a5276,#2980b9);padding:20px 24px;position:relative;">
    <button type="button" class="close" data-dismiss="modal" style="position:absolute;top:14px;right:18px;color:#fff;opacity:.8;font-size:1.4rem;">&times;</button>
    <div class="d-flex align-items-center" style="gap:12px;">
        <div style="width:48px;height:48px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-building" style="font-size:1.3rem;color:#fff;"></i>
        </div>
        <div>
            <h5 style="color:#fff;font-weight:700;margin:0;font-size:1.05rem;">Buscar Proveedor por RUC</h5>
            <small style="color:rgba(255,255,255,.8);">Consulta automatica a SUNAT</small>
        </div>
    </div>
</div>
<div class="modal-body" style="padding:24px;">
    <div class="form-group mb-4">
        <label style="font-weight:700;font-size:.82rem;color:#495057;text-transform:uppercase;letter-spacing:.5px;"><i class="fas fa-id-card mr-1" style="color:#1a5276;"></i> Numero de RUC</label>
        <div class="input-group">
            <input type="text" id="inputRucModal" class="form-control form-control-lg" maxlength="11" placeholder="Ej: 20123456789" style="font-family:monospace;font-size:1.1rem;letter-spacing:2px;text-align:center;border-radius:10px 0 0 10px;border:2px solid #dee2e6;">
            <div class="input-group-append">
                <button type="button" id="btnBuscarRucModal" class="btn btn-lg font-weight-bold" style="background:#1a5276;color:#fff;border-radius:0 10px 10px 0;padding:0 20px;">
                    <i class="fas fa-search mr-1"></i>Buscar
                </button>
            </div>
        </div>
        <small class="text-muted">Ingresa los 11 digitos del RUC del proveedor</small>
    </div>
    <div id="rucModalEstado" style="display:none;"></div>
    <div id="rucModalResultado" style="display:none;">
        <div style="background:linear-gradient(135deg,#e3f2fd,#e8eaf6);border:1.5px solid #bbdefb;border-radius:12px;padding:18px 20px;margin-bottom:16px;">
            <div class="d-flex align-items-center" style="gap:12px; mb-3">
                <div style="width:52px;height:52px;background:#1a5276;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-building" style="color:#fff;font-size:1.2rem;"></i>
                </div>
                <div>
                    <div id="rucRazonSocial" style="font-size:1.05rem;font-weight:700;color:#2d3436;"></div>
                    <div id="rucBadge" style="margin-top:3px;"></div>
                </div>
            </div>
            <div class="row" style="font-size:.85rem;color:#555;">
                <div class="col-6 mb-2"><i class="fas fa-id-card mr-1" style="color:#1a5276;"></i><strong>RUC:</strong> <span id="rucNumero" style="font-family:monospace;"></span></div>
                <div class="col-12" id="rucDireccionRow" style="display:none;"><i class="fas fa-map-marker-alt mr-1" style="color:#1a5276;"></i><strong>Direccion:</strong> <span id="rucDireccion"></span></div>
            </div>
        </div>
        <div id="rucOpciones"></div>
    </div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
</div>
</div></div></div>

<!-- ══ MODAL SELECTOR PRODUCTO ══ -->
<div class="modal fade" id="modalSelectorProducto" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg"><div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#1a7a4a,#27ae60);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
    <div>
        <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-box mr-2"></i>Seleccionar Producto</h6>
        <small style="color:rgba(255,255,255,.75);">Busca y haz clic en el producto para agregarlo</small>
    </div>
    <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-3">
<div class="input-group mb-3" style="box-shadow:0 2px 8px rgba(0,0,0,.08);border-radius:8px;overflow:hidden;">
    <div class="input-group-prepend"><span class="input-group-text" style="background:#f8f9fa;border:none;border-right:1px solid #dee2e6;"><i class="fas fa-search text-muted"></i></span></div>
    <input type="text" id="buscarProducto" class="form-control" placeholder="Buscar por nombre, codigo o categoria..." style="border:none;font-size:.9rem;">
</div>
<div class="d-flex justify-content-between mb-2"><small class="text-muted"><span id="contProductos"><?= count($productos_lista) ?></span> productos disponibles</small></div>
<div style="max-height:420px;overflow-y:auto;"><div id="listaProductos">
<?php foreach ($productos_lista as $prod): ?>
<div class="item-selector"
    data-id="<?= $prod['id_producto'] ?>"
    data-nombre="<?= htmlspecialchars($prod['nombre_producto'],ENT_QUOTES) ?>"
    data-codigo="<?= htmlspecialchars($prod['codigo'],ENT_QUOTES) ?>"
    data-cat="<?= htmlspecialchars($prod['nombre_categoria']??'',ENT_QUOTES) ?>"
    data-precio="<?= $prod['precio_compra'] ?>">
    <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center" style="gap:12px;">
            <div style="width:38px;height:38px;background:#e8f5e9;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-box" style="color:#1a7a4a;font-size:.85rem;"></i></div>
            <div>
                <div class="item-nombre"><?= htmlspecialchars($prod['nombre_producto']) ?></div>
                <div class="item-sub">
                    <code style="background:#e0f7fa;color:#117a8b;padding:1px 5px;border-radius:3px;font-size:.72rem;"><?= htmlspecialchars($prod['codigo']) ?></code>
                    <?php if($prod['nombre_categoria']): ?> &nbsp;|&nbsp; <?= htmlspecialchars($prod['nombre_categoria']) ?><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="text-right" style="flex-shrink:0;">
            <div style="font-size:.75rem;color:#999;">P. Compra</div>
            <div style="font-weight:700;color:#1a5276;font-size:.9rem;">S/. <?= number_format($prod['precio_compra'],2) ?></div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<div id="sinResultadosProd" style="display:none;text-align:center;padding:30px;color:#999;"><i class="fas fa-search" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px;"></i>No se encontraron productos</div>
</div></div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:10px 16px;">
    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
</div>
</div></div></div>

<!-- ══ MODAL DETALLE ITEM ══ -->
<div class="modal fade" id="modalDetalleItem" tabindex="-1" aria-hidden="true">
<div class="modal-dialog"><div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#117a8b,#1a5276);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
    <div>
        <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-box mr-2"></i>Detalle del Producto</h6>
        <small id="modal_item_nombre" style="color:rgba(255,255,255,.85);font-size:.85rem;"></small>
    </div>
    <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-4">
<input type="hidden" id="modal_item_id_producto">
<small id="modal_item_codigo" class="text-muted d-block mb-3"></small>
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label-comp">Cantidad <span class="text-danger">*</span></label>
        <div class="input-group input-group-sm">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-sort-numeric-up"></i></span></div>
            <input type="number" class="form-control" id="modal_item_cantidad" min="1" value="1">
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label-comp">Precio Compra</label>
        <div class="input-group input-group-sm">
            <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
            <input type="text" class="form-control" id="modal_item_precio" style="background:#f0fff4;color:#1a7a4a;font-weight:700;cursor:default;" readonly>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label-comp">Descuento
            <button type="button" id="btnDesbloquearDescComp" class="btn btn-xs ml-1"
                    style="display:none;background:#fff3cd;border:1px solid #ffc107;color:#856404;border-radius:6px;padding:1px 7px;font-size:.72rem;font-weight:700;cursor:pointer;"
                    title="Requiere autorización del administrador">
                <i class="fas fa-lock mr-1"></i>Desbloquear
            </button>
        </label>
        <div class="input-group input-group-sm">
            <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
            <input type="number" step="0.01" class="form-control" id="modal_item_descuento" min="0" value="0.00">
        </div>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center mt-2 p-3" style="background:#f8f9fa;border-radius:8px;">
    <span style="font-weight:600;color:#555;">Subtotal:</span>
    <span id="modal_item_subtotal" style="font-size:1.2rem;font-weight:700;color:#1a7a4a;">S/. 0.00</span>
</div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
    <button type="button" id="btnConfirmarItem" class="btn btn-sm font-weight-bold" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);color:#fff;border:none;"><i class="fas fa-plus mr-1"></i>Agregar a la compra</button>
</div>
</div></div></div>

<!-- ══ MODAL AUTORIZACIÓN ADMINISTRADOR — COMPRAS ══ -->
<div class="modal fade" id="modalAdminAuthComp" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
<div class="modal-dialog modal-dialog-centered" style="max-width:380px;">
<div class="modal-content" style="border:none;border-radius:18px;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.55);font-family:'Segoe UI',sans-serif;">

    <div style="background:linear-gradient(160deg,#1a0000 0%,#3d0000 55%,#1a0000 100%);padding:32px 24px 26px;text-align:center;position:relative;overflow:hidden;">
        <div style="position:absolute;inset:0;background-image:linear-gradient(rgba(220,50,50,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(220,50,50,.07) 1px,transparent 1px);background-size:26px 26px;pointer-events:none;"></div>
        <div style="width:80px;height:80px;border-radius:50%;border:2px solid rgba(220,50,50,.45);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;animation:authPulse 2s ease-in-out infinite;position:relative;">
            <div style="width:56px;height:56px;border-radius:50%;background:rgba(220,50,50,.12);border:1.5px solid rgba(220,50,50,.35);display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-lock" style="font-size:1.5rem;color:#e05555;"></i>
            </div>
        </div>
        <div style="font-size:.65rem;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:5px;">Acción restringida</div>
        <div style="font-size:1.35rem;font-weight:800;color:#fff;letter-spacing:-.3px;">Autorización requerida</div>
        <div style="display:inline-flex;align-items:center;gap:6px;margin-top:10px;background:rgba(220,50,50,.18);border:1px solid rgba(220,50,50,.35);border-radius:20px;padding:4px 14px;">
            <i class="fas fa-lock" style="font-size:.7rem;color:#e05555;"></i>
            <span style="font-size:.75rem;color:#e05555;font-weight:600;">descuento</span>
        </div>
    </div>

    <div style="background:#111827;padding:20px 20px 16px;">
        <div style="display:flex;align-items:flex-start;gap:12px;background:#1c0a0a;border:1px solid rgba(220,50,50,.3);border-left:3px solid #e05555;border-radius:10px;padding:14px;margin-bottom:16px;">
            <div style="width:36px;height:36px;border-radius:8px;background:rgba(220,50,50,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-shield-alt" style="color:#e05555;font-size:.9rem;"></i>
            </div>
            <div>
                <div style="font-size:.7rem;font-weight:700;color:#e05555;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">Sin autorización</div>
                <div style="font-size:.83rem;color:#ccc;line-height:1.45;">Solo el <strong style="color:#fff;">Administrador</strong> puede aplicar descuentos. Ingresa su contraseña para continuar.</div>
            </div>
        </div>

        <div style="position:relative;margin-bottom:8px;">
            <span style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#555;pointer-events:none;">
                <i class="fas fa-key" style="font-size:.85rem;"></i>
            </span>
            <input type="password" id="adminAuthPassComp" autocomplete="new-password"
                   placeholder="Contraseña del administrador"
                   style="width:100%;padding:12px 44px 12px 38px;background:#1e293b;border:1.5px solid #2d3748;border-radius:10px;font-size:.88rem;color:#e2e8f0;outline:none;box-sizing:border-box;transition:border-color .2s;"
                   onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#2d3748'">
            <button type="button" id="adminAuthEyeComp" tabindex="-1"
                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#555;cursor:pointer;padding:0;font-size:.85rem;">
                <i class="fas fa-eye"></i>
            </button>
        </div>

        <div id="adminAuthErrComp" style="display:none;padding:9px 13px;background:#1c0a0a;border-left:3px solid #e74c3c;border-radius:6px;font-size:.8rem;color:#fc8181;margin-bottom:8px;">
            <i class="fas fa-exclamation-circle mr-1"></i><span id="adminAuthErrTxtComp"></span>
        </div>
    </div>

    <div style="background:#111827;padding:0 20px 20px;display:flex;gap:10px;">
        <button type="button" id="adminAuthCancelComp"
                style="flex:1;background:#1e293b;color:#94a3b8;border:1.5px solid #2d3748;border-radius:10px;padding:11px;font-weight:700;font-size:.88rem;cursor:pointer;transition:all .2s;"
                onmouseover="this.style.background='#2d3748'" onmouseout="this.style.background='#1e293b'">
            <i class="fas fa-times mr-1"></i>Cancelar
        </button>
        <button type="button" id="adminAuthConfirmComp"
                style="flex:2;background:linear-gradient(135deg,#1d4ed8,#3b82f6);color:#fff;border:none;border-radius:10px;padding:11px;font-weight:700;font-size:.88rem;cursor:pointer;transition:opacity .2s;"
                onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
            <i class="fas fa-unlock mr-1"></i>Verificar acceso
        </button>
    </div>

</div>
</div>
</div>

<?php
$extra_js  = '<script>var hayCajaComp = ' . ($hay_caja_comp ? 'true' : 'false') . '; var esAdminComp = ' . ((int)($_SESSION['id_rol'] ?? 0) === 1 ? 'true' : 'false') . ';</script>';
$extra_js .= '<script src="js/compras.js?v=' . time() . '"></script>';
include $ruta_base . 'includes/footer.php';
?>

