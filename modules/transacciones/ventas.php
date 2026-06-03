<?php
// ============================================================
// modules/transacciones/ventas.php | SysInversiones 2026
// Ventas al contado y crédito con cuotas (max. 4)
// Sin manejo de lotes
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';
require_once $ruta_base . 'conf/auditoria.php';

$id_usuario = $_SESSION['id_usuario'] ?? 0;

$swal = null;
if (isset($_SESSION['swal_vta'])) { $swal = $_SESSION['swal_vta']; unset($_SESSION['swal_vta']); }

// ── Caja activa ───────────────────────────────────────────────────────────────
// Se busca cualquier caja abierta (no solo la del usuario actual)
// para que ventas registradas por cualquier usuario afecten la caja correctamente.
$caja_activa_vta = null;
try {
    $caja_activa_vta = $pdo->query("SELECT id_caja, nombre, monto_inicial, fecha_apertura FROM caja WHERE estado='abierta' ORDER BY fecha_apertura DESC LIMIT 1")->fetch();
} catch (PDOException $e) {}
$hay_caja_vta = !empty($caja_activa_vta);

function redirigirVta(string $icon, string $title, string $text): void {
    $_SESSION['swal_vta'] = compact('icon', 'title', 'text');
    header('Location: ventas.php'); exit;
}

// Actualizar cuotas vencidas
try {
    $pdo->exec("UPDATE cuotas_venta SET estado='vencido' WHERE estado='pendiente' AND fecha_vencimiento < CURDATE()");
} catch (PDOException $e) {}

// ── AJAX: siguiente número de comprobante ─────────────────────────────────────
if (isset($_GET['accion']) && $_GET['accion'] === 'siguiente_numero') {
    $tipo    = $_GET['tipo'] ?? 'ticket';
    $prefijos = ['ticket'=>'T001','nota'=>'N001'];
    $prefijo  = $prefijos[$tipo] ?? 'T001';
    try {
        $stmt = $pdo->prepare("SELECT numero_comprobante FROM ventas WHERE tipo_comprobante=? AND numero_comprobante IS NOT NULL ORDER BY id_venta DESC LIMIT 1");
        $stmt->execute([$tipo]);
        $ultimo = $stmt->fetchColumn();
        if ($ultimo) { $partes = explode('-', $ultimo); $num = (int)end($partes) + 1; } else { $num = 1; }
        echo json_encode(['numero' => $prefijo . '-' . str_pad($num, 8, '0', STR_PAD_LEFT)]);
    } catch (PDOException $e) { echo json_encode(['numero' => $prefijo . '-00000001']); }
    exit;
}

// ── AJAX: detalle de venta ────────────────────────────────────────────────────
if (isset($_GET['accion']) && $_GET['accion'] === 'detalle_ajax') {
    $id = (int)($_GET['id_venta'] ?? 0);
    try {
        $stmt = $pdo->prepare(
            "SELECT v.*,
             CASE v.tipo_cliente
                 WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
                 ELSE CONCAT(COALESCE(cn.nombres,''),' ',COALESCE(cn.apellido_paterno,''),
                      CASE WHEN cn.apellido_materno IS NOT NULL AND cn.apellido_materno!='' THEN CONCAT(' ',cn.apellido_materno) ELSE '' END)
             END AS nombre_cliente,
             u.nombre_completo
             FROM ventas v
             LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = v.id_cliente AND v.tipo_cliente = 'natural'
             LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = v.id_cliente AND v.tipo_cliente = 'empresa'
             JOIN usuarios u ON v.id_usuario=u.id_usuario
             WHERE v.id_venta=?"
        );
        $stmt->execute([$id]); $cab = $stmt->fetch();
        if (!$cab) { echo '<div class="alert alert-warning">Venta no encontrada.</div>'; exit; }

        $det = $pdo->prepare("SELECT dv.*,p.nombre_producto,p.codigo FROM detalle_venta dv JOIN productos p ON dv.id_producto=p.id_producto WHERE dv.id_venta=?");
        $det->execute([$id]); $items = $det->fetchAll();

        $cuotas = []; $pagos = [];
        try {
            $stC = $pdo->prepare("SELECT * FROM cuotas_venta WHERE id_venta=? ORDER BY numero_cuota"); $stC->execute([$id]); $cuotas = $stC->fetchAll();
            $stP = $pdo->prepare("SELECT pv.*,u.nombre_completo FROM pagos_venta pv JOIN usuarios u ON pv.id_usuario=u.id_usuario WHERE pv.id_venta=? ORDER BY pv.fecha"); $stP->execute([$id]); $pagos = $stP->fetchAll();
        } catch (PDOException $e) {}

        $tipoLabel = ['ticket'=>'Ticket de Venta','nota'=>'Nota de Venta'];
        $bMap = ['pagado'=>'badge-vta-pagado','pendiente'=>'badge-vta-pendiente','anulado'=>'badge-vta-anulado'];

        // Data-attribute para que el JS detecte el tipo y muestre el botón correcto
        echo '<span data-tipo-comprobante="'.htmlspecialchars($cab['tipo_comprobante']).'" style="display:none;"></span>';

        echo '<div class="row mb-2">';
        echo '<div class="col-md-5"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-user mr-1"></i>Cliente</small><strong>'.htmlspecialchars($cab['nombre_cliente']).'</strong></div>';
        echo '<div class="col-md-3"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-file-invoice mr-1"></i>Comprobante</small><strong>'.htmlspecialchars($tipoLabel[$cab['tipo_comprobante']] ?? ucfirst($cab['tipo_comprobante'])).' '.htmlspecialchars($cab['numero_comprobante']??'---').'</strong></div>';
        echo '<div class="col-md-2"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-credit-card mr-1"></i>T. Pago</small><span class="badge-'.htmlspecialchars($cab['tipo_pago'] ?? 'contado').'-vta">'.strtoupper($cab['tipo_pago'] ?? 'CONTADO').'</span></div>';
        echo '<div class="col-md-2"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-circle mr-1"></i>Estado</small><span class="'.($bMap[$cab['estado']]??'').'">'.strtoupper($cab['estado']).'</span></div>';
        echo '</div>';
        echo '<div class="row mb-3 pt-2" style="border-top:1px solid #f0f0f0;">';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-calendar-alt mr-1"></i>Fecha</small>'.date('d/m/Y H:i',strtotime($cab['fecha'])).'</div>';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-user-tie mr-1"></i>Registrado por</small>'.htmlspecialchars($cab['nombre_completo']).'</div>';
        echo '<div class="col-md-4"><small class="text-muted d-block" style="font-size:.72rem;text-transform:uppercase;font-weight:600;letter-spacing:.4px;"><i class="fas fa-money-bill-wave mr-1"></i>Método de pago</small>'.ucfirst($cab['metodo_pago'] ?? '---').'</div>';
        echo '</div>';

        echo '<h6 class="font-weight-bold text-muted mb-2" style="font-size:.82rem;"><i class="fas fa-shopping-cart mr-1"></i>PRODUCTOS VENDIDOS</h6>';
        echo '<div class="table-responsive mb-3"><table class="table table-sm table-bordered" style="font-size:.83rem;">';
        echo '<thead style="background:#1a5276;color:#fff;"><tr><th><i class="fas fa-box mr-1"></i>Producto</th><th class="text-center">Cant.</th><th class="text-right">P.Venta</th><th class="text-right">Descuento</th><th class="text-right">Subtotal</th></tr></thead><tbody>';
        foreach ($items as $it) {
            echo '<tr><td><strong>'.htmlspecialchars($it['nombre_producto']).'</strong><br><small class="text-muted">'.htmlspecialchars($it['codigo']).'</small></td>';
            echo '<td class="text-center">'.$it['cantidad'].'</td>';
            echo '<td class="text-right">S/. '.number_format($it['precio_unitario'],2).'</td>';
            echo '<td class="text-right">'.($it['descuento']>0 ? 'S/. '.number_format($it['descuento'],2) : '---').'</td>';
            echo '<td class="text-right font-weight-bold">S/. '.number_format($it['subtotal'],2).'</td></tr>';
        }
        echo '</tbody></table></div>';

        echo '<div class="d-flex justify-content-end mb-3">';
        echo '<div style="background:#f8f9fa;border-radius:10px;padding:14px 20px;border:1px solid #e9ecef;min-width:260px;">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:.87rem;color:#555;border-bottom:1px solid #e9ecef;"><span><i class="fas fa-receipt mr-2 text-muted"></i>Subtotal</span><strong style="font-family:monospace;">S/. '.number_format($cab['subtotal'],2).'</strong></div>';
        if ($cab['descuento']>0) echo '<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:.87rem;color:#555;border-bottom:1px solid #e9ecef;"><span><i class="fas fa-tag mr-2 text-muted"></i>Descuento</span><strong style="font-family:monospace;color:#e74c3c;">- S/. '.number_format($cab['descuento'],2).'</strong></div>';
        if ($cab['aplica_igv'])  echo '<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:.87rem;color:#555;border-bottom:1px solid #e9ecef;"><span><i class="fas fa-percentage mr-2 text-muted"></i>IGV 18%</span><strong style="font-family:monospace;">S/. '.number_format($cab['igv'],2).'</strong></div>';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0 3px;font-size:1rem;font-weight:700;"><span style="color:#1a5276;"><i class="fas fa-check-circle mr-2"></i>TOTAL</span><span style="color:#1a5276;font-size:1.15rem;font-family:monospace;">S/. '.number_format($cab['total'],2).'</span></div>';
        echo '</div></div>';

        if ($cab['tipo_pago']==='credito' && !empty($cuotas)) {
            $eC = ['pendiente'=>'badge-vta-pendiente','pagado'=>'badge-vta-pagado','vencido'=>'badge-vta-anulado'];
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
            if (isset($cab['saldo_pendiente']) && $cab['saldo_pendiente']>0)
                echo '<div class="alert" style="background:#fff3cd;border-left:4px solid #e67e22;border-radius:6px;padding:10px 14px;font-size:.88rem;"><i class="fas fa-exclamation-circle mr-2" style="color:#e67e22;"></i><strong>Saldo pendiente: S/. '.number_format($cab['saldo_pendiente'],2).'</strong></div>';
        }
        if (!empty($pagos)) {
            echo '<h6 class="font-weight-bold text-muted mb-2" style="font-size:.82rem;"><i class="fas fa-money-bill-wave mr-1"></i>PAGOS REALIZADOS</h6>';
            echo '<div class="table-responsive"><table class="table table-sm table-bordered" style="font-size:.83rem;">';
            echo '<thead style="background:#1a5276;color:#fff;"><tr><th>Fecha</th><th>Método</th><th class="text-right">Monto</th><th>Registrado por</th></tr></thead><tbody>';
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

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // AJAX: actualizar precio de venta
    if ($accion === 'actualizar_precio_venta') {
        header('Content-Type: application/json');
        $id_producto  = (int)($_POST['id_producto']  ?? 0);
        $precio_venta = (float)($_POST['precio_venta'] ?? 0);
        if (!$id_producto || $precio_venta <= 0) { echo json_encode(['ok'=>false,'msg'=>'Datos invalidos.']); exit; }
        try {
            $stPrecio = $pdo->prepare("SELECT precio_venta, nombre_producto FROM productos WHERE id_producto=?");
            $stPrecio->execute([$id_producto]);
            $prod_precio = $stPrecio->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare("UPDATE productos SET precio_venta=? WHERE id_producto=?")->execute([$precio_venta,$id_producto]);
            registrarAuditoria($pdo, 'ventas', 'editar', 'productos', $id_producto,
                "Precio de venta modificado en módulo Ventas — " . ($prod_precio['nombre_producto'] ?? ''),
                'precio_venta', $prod_precio['precio_venta'] ?? null, $precio_venta);
            echo json_encode(['ok'=>true]);
        } catch (PDOException $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
        exit;
    }

    // REGISTRAR VENTA
    if ($accion === 'registrar') {
        // ── Validación: caja debe estar abierta ───────────────────────────────
        if (!$hay_caja_vta) {
            redirigirVta('warning', 'Caja no aperturada', 'Debes abrir una caja antes de registrar ventas. Ve al módulo de Caja y apertura una sesión.');
        }

        $id_cliente          = (int)($_POST['id_cliente']          ?? 0);
        $tipo_cliente        = ($_POST['tipo_cliente'] ?? 'natural') === 'empresa' ? 'empresa' : 'natural';
        $tipo_comprobante    = $_POST['tipo_comprobante']           ?? 'ticket';
        $numero_comprobante  = trim($_POST['numero_comprobante']    ?? '') ?: null;
        $fecha               = $_POST['fecha']                      ?? date('Y-m-d H:i:s');
        $subtotal            = (float)($_POST['subtotal']           ?? 0);
        $descuento_global    = (float)($_POST['descuento_global']   ?? 0);
        $aplica_igv          = isset($_POST['aplica_igv']) ? 1 : 0;
        $igv                 = (float)($_POST['igv']                ?? 0);
        $total               = (float)($_POST['total']              ?? 0);
        $tipo_pago           = $_POST['tipo_pago']                  ?? 'contado';
        $metodo_pago         = $_POST['metodo_pago']                ?? 'efectivo';
        $observacion         = trim($_POST['observacion']           ?? '') ?: null;
        $items               = $_POST['items']                      ?? [];
        $num_cuotas          = (int)($_POST['num_cuotas']           ?? 1);
        $fecha_primera_cuota = $_POST['fecha_primera_cuota']        ?? null;
        $frecuencia_dias     = (int)($_POST['frecuencia_dias']      ?? 30);

        if (!in_array($tipo_comprobante, ['ticket','nota'])) $tipo_comprobante = 'ticket';
        if (!$id_cliente)  redirigirVta('warning','Cliente requerido','Selecciona un cliente.');
        if (empty($items)) redirigirVta('warning','Sin productos','Agrega al menos un producto.');
        if ($total <= 0)   redirigirVta('warning','Total invalido','El total debe ser mayor a 0.');
        if ($tipo_pago === 'credito') {
            if ($num_cuotas < 1 || $num_cuotas > 4) redirigirVta('warning','Cuotas invalidas','El numero de cuotas debe ser entre 1 y 4.');
            if (empty($fecha_primera_cuota))         redirigirVta('warning','Fecha requerida','Indica la fecha de la primera cuota.');
        }

        try {
            $pdo->beginTransaction();
            $saldo_pendiente = ($tipo_pago === 'credito') ? $total : 0.00;
            $estado_venta    = ($tipo_pago === 'contado') ? 'pagado' : 'pendiente';
            $metodo_guardado = ($tipo_pago === 'contado') ? $metodo_pago : 'transferencia';
            $id_caja         = $hay_caja_vta ? (int)$caja_activa_vta['id_caja'] : null;

            $pdo->prepare("INSERT INTO ventas (id_cliente,tipo_cliente,id_usuario,id_caja,fecha,tipo_comprobante,numero_comprobante,subtotal,descuento,aplica_igv,igv,total,saldo_pendiente,tipo_pago,fecha_vencimiento_pago,metodo_pago,estado,observacion) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$id_cliente,$tipo_cliente,$id_usuario,$id_caja,$fecha,$tipo_comprobante,$numero_comprobante,$subtotal,$descuento_global,$aplica_igv,$igv,$total,$saldo_pendiente,$tipo_pago,($tipo_pago==='credito'?$fecha_primera_cuota:null),$metodo_guardado,$estado_venta,$observacion]);
            $id_venta = (int)$pdo->lastInsertId();

            foreach ($items as $item) {
                $id_producto     = (int)($item['id_producto']     ?? 0);
                $cantidad        = (int)($item['cantidad']        ?? 0);
                $precio_unitario = (float)($item['precio_unitario'] ?? 0);
                $descuento_item  = (float)($item['descuento']     ?? 0);
                $subtotal_item   = (float)($item['subtotal']      ?? 0);
                if (!$id_producto || $cantidad <= 0) continue;

                // Verificar stock
                $stStock = $pdo->prepare("SELECT stock FROM productos WHERE id_producto=?");
                $stStock->execute([$id_producto]);
                $stock_actual = (int)$stStock->fetchColumn();
                if ($stock_actual < $cantidad) {
                    $pdo->rollBack();
                    redirigirVta('warning','Stock insuficiente','No hay suficiente stock para uno de los productos.');
                }

                $pdo->prepare("INSERT INTO detalle_venta (id_venta,id_producto,cantidad,precio_unitario,descuento,subtotal) VALUES (?,?,?,?,?,?)")
                    ->execute([$id_venta,$id_producto,$cantidad,$precio_unitario,$descuento_item,$subtotal_item]);
                $pdo->prepare("UPDATE productos SET stock=GREATEST(0,stock-?) WHERE id_producto=?")
                    ->execute([$cantidad,$id_producto]);
            }

            if ($tipo_pago === 'credito') {
                $monto_cuota = round($total / $num_cuotas, 2);
                $diferencia  = round($total - ($monto_cuota * $num_cuotas), 2);
                $fecha_cuota = new DateTime($fecha_primera_cuota);
                for ($i = 1; $i <= $num_cuotas; $i++) {
                    $monto_esta = ($i === $num_cuotas) ? round($monto_cuota + $diferencia, 2) : $monto_cuota;
                    $pdo->prepare("INSERT INTO cuotas_venta (id_venta,numero_cuota,monto_cuota,fecha_vencimiento,estado) VALUES (?,?,?,?,'pendiente')")
                        ->execute([$id_venta,$i,$monto_esta,$fecha_cuota->format('Y-m-d')]);
                    $fecha_cuota->modify("+{$frecuencia_dias} days");
                }
            }

            // Movimiento en caja (solo contado) — dentro de la transacción
            if ($tipo_pago === 'contado' && $hay_caja_vta) {
                $tipoLabel = ['ticket'=>'Ticket de Venta','nota'=>'Nota de Venta'];
                $desc_mov  = "Venta #{$id_venta} - ".($tipoLabel[$tipo_comprobante] ?? ucfirst($tipo_comprobante))." ".($numero_comprobante ?? "");
                $pdo->prepare("INSERT INTO movimientos_caja (id_caja,tipo_referencia,id_referencia,id_usuario,tipo,descripcion,monto,metodo_pago) VALUES (?,'venta',?,?,'ingreso',?,?,?)")
                    ->execute([$caja_activa_vta['id_caja'],$id_venta,$id_usuario,trim($desc_mov),$total,$metodo_pago]);
            }

            $pdo->commit();

            // Auditoría de venta registrada
            $desc_audit = "Venta #{$id_venta} — {$tipo_comprobante} {$numero_comprobante} — Total: S/. " . number_format($total, 2) . " — Pago: {$tipo_pago}";
            if ($descuento_global > 0) $desc_audit .= " — Descuento: S/. " . number_format($descuento_global, 2);
            registrarAuditoria($pdo, 'ventas', 'crear', 'ventas', $id_venta, $desc_audit);

            $msg = $tipo_pago==='contado'
                ? "Venta #$id_venta registrada al contado. Stock actualizado."
                : "Venta #$id_venta registrada a crédito en $num_cuotas cuota(s). Stock actualizado.";
            redirigirVta('success','¡Venta registrada!',$msg);
        } catch (PDOException $e) {
            $pdo->rollBack();
            redirigirVta('error','Error al registrar','Error: '.$e->getMessage());
        }

    // REGISTRAR PAGO (abono a crédito)
    } elseif ($accion === 'registrar_pago') {
        // ── Validación: caja debe estar abierta ───────────────────────────────
        if (!$hay_caja_vta) {
            redirigirVta('warning', 'Caja no aperturada', 'Debes abrir una caja antes de registrar pagos. Ve al módulo de Caja y apertura una sesión.');
        }

        $id_venta    = (int)($_POST['id_venta']      ?? 0);
        $monto       = (float)($_POST['monto_pago']  ?? 0);
        $metodo_pago = $_POST['metodo_pago_abono']   ?? 'efectivo';
        $observacion = trim($_POST['obs_pago']        ?? '') ?: null;
        if (!$id_venta) redirigirVta('warning','Error','Venta no identificada.');
        if ($monto <= 0) redirigirVta('warning','Monto invalido','El monto debe ser mayor a 0.');
        try {
            $pdo->beginTransaction();
            $stV = $pdo->prepare("SELECT total,saldo_pendiente,estado FROM ventas WHERE id_venta=?"); $stV->execute([$id_venta]); $ven = $stV->fetch();
            if (!$ven || $ven['estado']==='anulado') throw new Exception('La venta no existe o está anulada.');
            if ($ven['saldo_pendiente'] <= 0) throw new Exception('Esta venta ya está completamente pagada.');
            if ($monto > $ven['saldo_pendiente']) throw new Exception('El monto (S/. '.number_format($monto,2).') supera el saldo pendiente (S/. '.number_format($ven['saldo_pendiente'],2).').');
            $pdo->prepare("INSERT INTO pagos_venta (id_venta,id_usuario,metodo_pago,monto,observacion) VALUES (?,?,?,?,?)")->execute([$id_venta,$id_usuario,$metodo_pago,$monto,$observacion]);
            $nuevo_saldo  = round($ven['saldo_pendiente'] - $monto, 2);
            $nuevo_estado = ($nuevo_saldo <= 0) ? 'pagado' : 'pendiente';
            $pdo->prepare("UPDATE ventas SET saldo_pendiente=?,estado=? WHERE id_venta=?")->execute([$nuevo_saldo,$nuevo_estado,$id_venta]);
            if ($nuevo_saldo <= 0) {
                $pdo->prepare("UPDATE cuotas_venta SET estado='pagado' WHERE id_venta=? AND estado!='pagado'")->execute([$id_venta]);
            } else {
                $totalPagado = round($ven['total'] - $nuevo_saldo, 2);
                $stCq = $pdo->prepare("SELECT id_cuota,monto_cuota FROM cuotas_venta WHERE id_venta=? ORDER BY numero_cuota"); $stCq->execute([$id_venta]);
                $acum = 0;
                foreach ($stCq->fetchAll() as $cq) {
                    $acum += $cq['monto_cuota'];
                    if ($acum <= $totalPagado) $pdo->prepare("UPDATE cuotas_venta SET estado='pagado' WHERE id_cuota=?")->execute([$cq['id_cuota']]);
                }
            }
            // Movimiento en caja por abono a crédito — dentro de la transacción
            if ($hay_caja_vta) {
                $desc_mov = "Abono Venta #{$id_venta} (".ucfirst($metodo_pago)." - crédito)";
                $pdo->prepare("INSERT INTO movimientos_caja (id_caja,tipo_referencia,id_referencia,id_usuario,tipo,descripcion,monto,metodo_pago) VALUES (?,'venta',?,?,'ingreso',?,?,?)")
                    ->execute([$caja_activa_vta['id_caja'],$id_venta,$id_usuario,$desc_mov,$monto,$metodo_pago]);
            }

            $pdo->commit();
            // Auditoría del abono
            registrarAuditoria($pdo, 'ventas', 'editar', 'ventas', $id_venta,
                "Abono crédito Venta #{$id_venta} — S/. " . number_format($monto, 2) . " (" . ucfirst($metodo_pago) . ") — Saldo restante: S/. " . number_format($nuevo_saldo, 2),
                'saldo_pendiente', $ven['saldo_pendiente'], $nuevo_saldo);
            $msg = $nuevo_saldo<=0
                ? "Pago registrado. Venta #$id_venta completamente pagada."
                : "Pago de S/. ".number_format($monto,2)." registrado. Saldo: S/. ".number_format($nuevo_saldo,2).".";
            redirigirVta('success','¡Pago registrado!',$msg);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            redirigirVta('error','Error al registrar pago',$e->getMessage());
        }

    // ANULAR VENTA
    } elseif ($accion === 'anular') {
        $id_venta = (int)($_POST['id_venta'] ?? 0);
        if (!$id_venta) redirigirVta('warning','ID invalido','No se pudo identificar la venta.');
        try {
            $pdo->beginTransaction();
            $stV = $pdo->prepare("SELECT estado FROM ventas WHERE id_venta=?"); $stV->execute([$id_venta]); $ven = $stV->fetch();
            if (!$ven || $ven['estado']==='anulado') { $pdo->rollBack(); redirigirVta('warning','No se puede anular','La venta ya está anulada o no existe.'); }
            $det = $pdo->prepare("SELECT id_producto,cantidad FROM detalle_venta WHERE id_venta=?"); $det->execute([$id_venta]);
            foreach ($det->fetchAll() as $d) {
                $pdo->prepare("UPDATE productos SET stock=stock+? WHERE id_producto=?")->execute([$d['cantidad'],$d['id_producto']]);
            }
            $pdo->prepare("UPDATE ventas SET estado='anulado',saldo_pendiente=0 WHERE id_venta=?")->execute([$id_venta]);
            try { $pdo->prepare("UPDATE cuotas_venta SET estado='pagado' WHERE id_venta=?")->execute([$id_venta]); } catch(PDOException $e){}
            $pdo->commit();
            registrarAuditoria($pdo, 'ventas', 'anular', 'ventas', $id_venta,
                "Venta #{$id_venta} anulada — stock revertido", 'estado', 'pagado/pendiente', 'anulado');
            redirigirVta('info','Venta anulada',"La venta #$id_venta fue anulada y el stock fue revertido.");
        } catch (PDOException $e) { $pdo->rollBack(); redirigirVta('error','Error al anular','Error: '.$e->getMessage()); }
    }
}

// ── Datos para el formulario ──────────────────────────────────────────────────
$clientes = $productos_lista = [];
$stats = ['total_mes'=>0,'monto_mes'=>0,'pendientes'=>0,'anuladas'=>0];
try {
    $clientes_nat_v = $pdo->query(
        "SELECT id_cliente_natural AS id_cliente, 'natural' AS tipo_cliente,
         CONCAT(nombres,' ',apellido_paterno,
         CASE WHEN apellido_materno IS NOT NULL AND apellido_materno!='' THEN CONCAT(' ',apellido_materno) ELSE '' END) AS nombre_completo,
         documento_identidad, telefono
         FROM clientes_natural WHERE estado_cliente=1 ORDER BY nombres"
    )->fetchAll();
    $clientes_emp_v = $pdo->query(
        "SELECT id_cliente_empresa AS id_cliente, 'empresa' AS tipo_cliente,
         razon_social AS nombre_completo, ruc AS documento_identidad, telefono
         FROM clientes_empresa WHERE estado_cliente=1 ORDER BY razon_social"
    )->fetchAll();
    $clientes = array_merge($clientes_nat_v, $clientes_emp_v);
    $productos_lista = $pdo->query(
        "SELECT p.id_producto,p.codigo,p.nombre_producto,p.precio_venta,p.stock,c.nombre_categoria
         FROM productos p LEFT JOIN categorias c ON p.id_categoria=c.id_categoria
         WHERE p.estado=1 ORDER BY p.nombre_producto"
    )->fetchAll();
    $mes = date('Y-m');
    $st  = $pdo->prepare("SELECT COUNT(*) AS total_mes,COALESCE(SUM(total),0) AS monto_mes,SUM(estado='pendiente') AS pendientes,SUM(estado='anulado') AS anuladas FROM ventas WHERE DATE_FORMAT(fecha,'%Y-%m')=?");
    $st->execute([$mes]); $stats = $st->fetch();
} catch (PDOException $e) { $swal = ['icon'=>'error','title'=>'Error','text'=>'Error al cargar datos: '.$e->getMessage()]; }

include $ruta_base . 'includes/header.php';
include $ruta_base . 'includes/sidebar.php';
?>
<link rel="stylesheet" href="css/ventas.css">
<div class="content-wrapper">
<div class="content-header"><div class="container-fluid">
<div class="page-header-vta d-flex justify-content-between align-items-center flex-wrap">
    <div>
        <h4><i class="fas fa-cash-register mr-2"></i>Registro de Ventas</h4>
        <small><i class="fas fa-map-marker-alt mr-1"></i>SysInversiones &rsaquo; Transacciones &rsaquo; Ventas</small>
    </div>
    <a href="historial/historial_ventas.php" class="btn btn-light font-weight-bold btn-sm">
        <i class="fas fa-history mr-1"></i>Ver Historial
    </a>
</div>
</div></div>

<div class="content"><div class="container-fluid">

<?php if ($swal): ?>
<script>document.addEventListener('DOMContentLoaded',function(){Swal.fire({icon:'<?= $swal['icon'] ?>',title:'<?= addslashes($swal['title']) ?>',text:'<?= addslashes($swal['text']) ?>',confirmButtonColor:'#1a5276',timer:<?= in_array($swal['icon'],['success','info'])?3500:0 ?>,timerProgressBar:<?= in_array($swal['icon'],['success','info'])?'true':'false' ?>,showConfirmButton:<?= in_array($swal['icon'],['success','info'])?'false':'true' ?>,});});</script>
<?php endif; ?>

<!-- Stats -->
<div class="row mb-3">
<div class="col-6 col-md-3 mb-2">
    <div class="stat-mini-vta" style="background:linear-gradient(135deg,#1a5276,#2980b9);">
        <i class="fas fa-shopping-cart"></i>
        <div><div class="stat-value"><?= (int)$stats['total_mes'] ?></div><div class="stat-label">Ventas este mes</div></div>
    </div>
</div>
<div class="col-6 col-md-3 mb-2">
    <div class="stat-mini-vta" style="background:linear-gradient(135deg,#1a7a4a,#27ae60);">
        <i class="fas fa-money-bill-wave"></i>
        <div><div class="stat-value">S/. <?= number_format($stats['monto_mes'],0) ?></div><div class="stat-label">Monto del mes</div></div>
    </div>
</div>
<div class="col-6 col-md-3 mb-2">
    <div class="stat-mini-vta" style="background:linear-gradient(135deg,#e67e22,#f39c12);">
        <i class="fas fa-clock"></i>
        <div><div class="stat-value"><?= (int)$stats['pendientes'] ?></div><div class="stat-label">Pendientes de pago</div></div>
    </div>
</div>
<div class="col-6 col-md-3 mb-2">
    <div class="stat-mini-vta" style="background:linear-gradient(135deg,#e74c3c,#c0392b);">
        <i class="fas fa-ban"></i>
        <div><div class="stat-value"><?= (int)$stats['anuladas'] ?></div><div class="stat-label">Anuladas</div></div>
    </div>
</div>
</div>

<!-- FORMULARIO NUEVA VENTA -->
<div class="form-venta-card mb-4">
<div class="form-venta-header d-flex justify-content-between align-items-center">
    <h5><i class="fas fa-plus-circle mr-2"></i>Nueva Venta</h5>
    <button type="button" id="btnLimpiarFormVta" class="btn btn-sm btn-outline-light"><i class="fas fa-eraser mr-1"></i>Limpiar</button>
</div>
<div class="form-venta-body">

<?php if (!$hay_caja_vta): ?>
<div class="alert alert-warning d-flex align-items-center mb-3" style="border-left:4px solid #e67e22;border-radius:8px;background:#fff8f0;">
    <i class="fas fa-exclamation-triangle fa-2x mr-3" style="color:#e67e22;"></i>
    <div>
        <strong>Caja no aperturada</strong><br>
        <span style="font-size:.9rem;">No puedes registrar ventas sin una caja abierta. <a href="../Caja/caja.php" class="font-weight-bold" style="color:#e67e22;">Ir al módulo de Caja →</a></span>
    </div>
</div>
<div id="wrapperSinCajaVta" style="position:relative;opacity:.5;user-select:none;">
    <div id="overlaySinCajaVta" style="position:absolute;inset:0;z-index:10;background:transparent;"></div>
<?php endif; ?>

<form id="formNuevaVenta" method="POST">
<input type="hidden" name="accion" value="registrar">
<input type="hidden" name="id_cliente" id="id_cliente_hidden">
<input type="hidden" name="tipo_cliente" id="tipo_cliente_hidden" value="natural">
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
        <div class="d-flex" style="gap:8px;">
            <div id="campoClienteVta" class="campo-selector-vta flex-grow-1" style="cursor:pointer;">
                <span class="placeholder-text">Haz clic para seleccionar cliente...</span>
                <span class="selected-text" style="display:none;"></span>
                <i class="fas fa-search text-muted" style="font-size:.8rem;"></i>
            </div>
            <button type="button" id="btnSeleccionarClienteVta" class="btn btn-sm btn-outline-primary" title="Buscar"><i class="fas fa-search"></i></button>
            <button type="button" id="btnLimpiarClienteVta" class="btn btn-sm btn-outline-danger" title="Quitar"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <div class="col-md-2">
        <label class="form-label-vta"><i class="fas fa-file-invoice mr-1 text-muted"></i>Tipo Comprobante</label>
        <select class="form-control form-control-sm" name="tipo_comprobante" id="tipo_comprobante_vta">
            <option value="ticket" selected>Ticket de Venta</option>
            <option value="nota">Nota de Venta</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label-vta"><i class="fas fa-hashtag mr-1 text-muted"></i>N° Comprobante</label>
        <div class="input-group input-group-sm">
            <div class="input-group-prepend">
                <span class="input-group-text" id="prefijo_comprobante_vta" style="font-family:monospace;font-weight:700;color:#1a5276;background:#e3f2fd;border-color:#bbdefb;min-width:60px;justify-content:center;">T001</span>
            </div>
            <input type="text" class="form-control" id="numero_comprobante_vta" name="numero_comprobante" readonly
                   style="background:#f8f9fa;font-family:monospace;font-weight:700;color:#1a5276;">
        </div>
    </div>
    <div class="col-md-2">
        <label class="form-label-vta"><i class="fas fa-calendar-alt mr-1 text-muted"></i>Fecha</label>
        <input type="datetime-local" class="form-control form-control-sm" name="fecha"
               value="<?= date('Y-m-d\TH:i') ?>">
    </div>
</div>

<!-- Tabla de productos -->
<div class="mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="form-label-vta mb-0"><i class="fas fa-boxes mr-1 text-muted"></i>Productos</label>
        <button type="button" id="btnAgregarProductoVta" class="btn btn-sm btn-primary">
            <i class="fas fa-plus mr-1"></i>Agregar Producto
        </button>
    </div>
    <div class="tabla-detalle-vta-wrapper">
        <table class="table table-sm mb-0 tabla-detalle-vta" id="tablaDetalleVta">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Producto</th>
                    <th style="width:90px;" class="text-center">Cantidad</th>
                    <th style="width:110px;" class="text-right">P. Venta</th>
                    <th style="width:100px;" class="text-right">Descuento</th>
                    <th style="width:110px;" class="text-right">Subtotal</th>
                    <th style="width:40px;"></th>
                </tr>
            </thead>
            <tbody>
                <tr class="fila-vacia-vta"><td colspan="7"><i class="fas fa-shopping-cart"></i>Sin productos agregados.<br><small>Haz clic en "Agregar Producto" para comenzar.</small></td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="row">
    <!-- Sección de pago -->
    <div class="col-md-7">
        <div class="seccion-pago-vta">
            <h6><i class="fas fa-credit-card mr-2"></i>Forma de Pago</h6>
            <div class="row mb-3">
                <div class="col-12">
                    <label class="form-label-vta">Tipo de Pago</label>
                    <div class="tipo-pago-group-vta">
                        <button type="button" class="btn-tipo-pago-vta activo" data-tipo="contado" style="flex:1;">
                            <i class="fas fa-money-bill-wave d-block mb-1" style="font-size:1.2rem;"></i>Contado
                        </button>
                        <button type="button" class="btn-tipo-pago-vta" data-tipo="credito" style="flex:1;">
                            <i class="fas fa-calendar-alt d-block mb-1" style="font-size:1.2rem;"></i>Crédito
                        </button>
                    </div>
                </div>
            </div>

            <!-- Método de pago (contado) -->
            <div id="bloqueMetodoPagoVta">
                <label class="form-label-vta">Método de Pago</label>
                <div class="metodo-pago-group-vta">
                    <button type="button" class="btn-metodo-vta activo" data-metodo="efectivo"><i class="fas fa-money-bill mr-1"></i>Efectivo</button>
                    <button type="button" class="btn-metodo-vta" data-metodo="yape"><i class="fas fa-mobile-alt mr-1"></i>Yape</button>
                    <button type="button" class="btn-metodo-vta" data-metodo="transferencia"><i class="fas fa-university mr-1"></i>Transferencia</button>
                </div>
            </div>

            <!-- Panel crédito -->
            <div id="panelCreditoVta" style="display:none;">
                <div class="row mb-2">
                    <div class="col-12">
                        <label class="form-label-vta">Número de Cuotas</label>
                        <div class="d-flex" style="gap:8px;">
                            <button type="button" class="btn-cuota-vta activo" data-cuotas="1">1</button>
                            <button type="button" class="btn-cuota-vta" data-cuotas="2">2</button>
                            <button type="button" class="btn-cuota-vta" data-cuotas="3">3</button>
                            <button type="button" class="btn-cuota-vta" data-cuotas="4">4</button>
                        </div>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-6">
                        <label class="form-label-vta">Fecha 1ra Cuota</label>
                        <input type="date" class="form-control form-control-sm" id="fecha_primera_cuota_vta" name="fecha_primera_cuota"
                               min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-vta">Frecuencia (días)</label>
                        <select class="form-control form-control-sm" id="frecuencia_dias_vta" name="frecuencia_dias">
                            <option value="7">Semanal (7 días)</option>
                            <option value="15">Quincenal (15 días)</option>
                            <option value="30" selected>Mensual (30 días)</option>
                        </select>
                    </div>
                </div>
                <div id="previewCuotasVta" style="display:none;"></div>
                <div id="resumenCuotaVta" style="display:none;margin-top:8px;">
                    <small style="color:#e67e22;font-weight:600;"><i class="fas fa-info-circle mr-1"></i><span id="resumenCuotaTextoVta"></span></small>
                </div>
            </div>

            <div class="mt-3">
                <label class="form-label-vta"><i class="fas fa-comment-alt mr-1 text-muted"></i>Observación</label>
                <input type="text" class="form-control form-control-sm" name="observacion" placeholder="Opcional..." maxlength="200">
            </div>
        </div>
    </div>

    <!-- Resumen de totales -->
    <div class="col-md-5">
        <div class="resumen-totales-vta">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span style="font-weight:700;color:#1a5276;font-size:.9rem;"><i class="fas fa-calculator mr-1"></i>RESUMEN</span>
                <div class="d-flex align-items-center" style="gap:8px;">
                    <label style="font-size:.82rem;font-weight:600;color:#495057;margin:0;">IGV 18%</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="switchIgvVta" name="aplica_igv" checked>
                        <label class="custom-control-label" for="switchIgvVta"></label>
                    </div>
                </div>
            </div>
            <div class="resumen-row-vta">
                <span><i class="fas fa-receipt mr-2 text-muted"></i>Subtotal</span>
                <span class="monto" id="resumen_subtotal_vta">S/. 0.00</span>
            </div>
            <div class="resumen-row-vta" id="filaDescGlobalVta" style="display:none;">
                <span><i class="fas fa-tag mr-2 text-muted"></i>Descuento</span>
                <span class="monto" style="color:#e74c3c;" id="resumen_descuento_vta">- S/. 0.00</span>
            </div>
            <div class="resumen-row-vta" id="filaIgvVta">
                <span><i class="fas fa-percentage mr-2 text-muted"></i>IGV (18%)</span>
                <span class="monto" id="resumen_igv_vta">S/. 0.00</span>
            </div>
            <div class="resumen-row-vta">
                <span><i class="fas fa-tag mr-2 text-muted"></i>Descuento global</span>
                <div class="input-group input-group-sm" style="width:120px;">
                    <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
                    <input type="number" step="0.01" min="0" class="form-control text-right" id="descuento_global_vta" name="descuento_global" value="0.00" style="font-size:.85rem;">
                </div>
            </div>
            <div class="resumen-row-vta total-final">
                <span><i class="fas fa-check-circle mr-2"></i>TOTAL</span>
                <span class="monto" id="resumen_total_vta">S/. 0.00</span>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-block font-weight-bold" id="btnRegistrarVenta"
                    style="background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;border:none;border-radius:10px;padding:14px;font-size:1rem;box-shadow:0 4px 15px rgba(26,82,118,.3);">
                <i class="fas fa-save mr-2"></i>Registrar Venta
            </button>
        </div>
    </div>
</div>
</form>
<?php if (!$hay_caja_vta): ?>
</div><!-- /wrapperSinCajaVta -->
<?php endif; ?>
</div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODALES
═══════════════════════════════════════════════════════════ -->

<!-- Modal Selector Cliente -->
<div class="modal fade" id="modalSelectorClienteVta" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header" style="background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;border:none;">
    <h5 class="modal-title"><i class="fas fa-user mr-2"></i>Seleccionar Cliente</h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body">
    <div class="d-flex" style="gap:8px;margin-bottom:12px;">
        <div class="input-group input-group-sm flex-grow-1">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
            <input type="text" class="form-control" id="buscarClienteVta" placeholder="Buscar por nombre o documento...">
        </div>
        <button type="button" id="btnNuevoClienteDniVta" class="btn btn-sm btn-outline-primary" style="white-space:nowrap;">
            <i class="fas fa-id-card mr-1"></i>Buscar por DNI
        </button>
    </div>
    <small class="text-muted d-block mb-2"><i class="fas fa-users mr-1"></i><span id="contClientesVta"><?= count($clientes) ?></span> clientes activos</small>
    <div id="listaClientesVta" style="max-height:380px;overflow-y:auto;">
        <?php foreach ($clientes as $cl): ?>
        <div class="item-selector-vta"
             data-id="<?= $cl['id_cliente'] ?>"
             data-tipo-cliente="<?= $cl['tipo_cliente'] ?>"
             data-nombre="<?= htmlspecialchars($cl['nombre_completo']) ?>"
             data-doc="<?= htmlspecialchars($cl['documento_identidad'] ?? '') ?>">
            <div class="d-flex align-items-center" style="gap:12px;">
                <div style="width:38px;height:38px;background:<?= $cl['tipo_cliente']==='empresa' ? '#fde8d8' : '#e3f2fd' ?>;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas <?= $cl['tipo_cliente']==='empresa' ? 'fa-building' : 'fa-user' ?>" style="color:<?= $cl['tipo_cliente']==='empresa' ? '#c05621' : '#1a5276' ?>;font-size:.85rem;"></i>
                </div>
                <div>
                    <div class="item-nombre"><?= htmlspecialchars($cl['nombre_completo']) ?></div>
                    <div class="item-sub">
                        <i class="fas fa-id-card mr-1"></i><?= htmlspecialchars($cl['documento_identidad'] ?? '---') ?>
                        <?php if (!empty($cl['telefono'])): ?> &nbsp;|&nbsp; <i class="fas fa-phone mr-1"></i><?= htmlspecialchars($cl['telefono']) ?><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($clientes)): ?>
        <div class="text-center text-muted py-4"><i class="fas fa-users fa-2x mb-2 d-block" style="opacity:.3;"></i>No hay clientes registrados.</div>
        <?php endif; ?>
    </div>
    <div id="sinResultadosCliVta" style="display:none;" class="text-center text-muted py-4">
        <i class="fas fa-search fa-2x mb-2 d-block" style="opacity:.3;"></i>Sin resultados para tu búsqueda.
    </div>
</div>
</div></div>
</div>

<!-- Modal Buscar DNI -->
<div class="modal fade" id="modalNuevoClienteDniVta" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<div class="modal-header" style="background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;border:none;">
    <h5 class="modal-title"><i class="fas fa-id-card mr-2"></i>Buscar Cliente por DNI</h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body">
    <div class="panel-buscar-api-vta mb-3">
        <label style="font-size:.82rem;font-weight:600;color:#1a5276;margin-bottom:6px;"><i class="fas fa-search mr-1"></i>Consultar RENIEC</label>
        <div class="input-group input-group-sm">
            <input type="text" class="form-control" id="inputDniModalVta" placeholder="Ingresa 8 dígitos..." maxlength="8" style="font-family:monospace;font-size:1rem;font-weight:700;letter-spacing:2px;">
            <div class="input-group-append">
                <button type="button" class="btn btn-sm font-weight-bold" id="btnBuscarDniModalVta" style="background:#1a5276;color:#fff;">
                    <i class="fas fa-search mr-1"></i>Buscar
                </button>
            </div>
        </div>
    </div>
    <div id="dniModalEstadoVta"></div>
    <div id="dniModalResultadoVta" style="display:none;">
        <div class="resultado-api-card-vta">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="resultado-api-nombre-vta" id="dniNombreCompletoVta"></div>
                <div id="dniBadgeVta"></div>
            </div>
            <div class="resultado-api-dato-vta"><i class="fas fa-id-card mr-1"></i>DNI: <strong id="dniNumeroVta"></strong></div>
            <div class="resultado-api-dato-vta" id="dniDireccionRowVta" style="display:none;"><i class="fas fa-map-marker-alt mr-1"></i><span id="dniDireccionVta"></span></div>
        </div>
        <div id="dniOpcionesVta" class="mt-3"></div>
    </div>
</div>
</div></div>
</div>

<!-- Modal Selector Producto -->
<div class="modal fade" id="modalSelectorProductoVta" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header" style="background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;border:none;">
    <h5 class="modal-title"><i class="fas fa-box mr-2"></i>Seleccionar Producto</h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body">
    <div class="input-group input-group-sm mb-2">
        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
        <input type="text" class="form-control" id="buscarProductoVta" placeholder="Buscar por nombre, código o categoría...">
    </div>
    <small class="text-muted d-block mb-2"><i class="fas fa-boxes mr-1"></i><span id="contProductosVta"><?= count($productos_lista) ?></span> productos activos</small>
    <div id="listaProductosVta" style="max-height:380px;overflow-y:auto;">
        <?php foreach ($productos_lista as $pr): ?>
        <div class="item-selector-vta"
             data-id="<?= $pr['id_producto'] ?>"
             data-nombre="<?= htmlspecialchars($pr['nombre_producto']) ?>"
             data-codigo="<?= htmlspecialchars($pr['codigo'] ?? '') ?>"
             data-precio="<?= $pr['precio_venta'] ?>"
             data-stock="<?= $pr['stock'] ?>"
             data-cat="<?= htmlspecialchars($pr['nombre_categoria'] ?? '') ?>">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center" style="gap:12px;">
                    <div style="width:38px;height:38px;background:#e3f2fd;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-box" style="color:#1a5276;font-size:.85rem;"></i>
                    </div>
                    <div>
                        <div class="item-nombre"><?= htmlspecialchars($pr['nombre_producto']) ?></div>
                        <div class="item-sub">
                            <i class="fas fa-barcode mr-1"></i><?= htmlspecialchars($pr['codigo'] ?? '---') ?>
                            <?php if (!empty($pr['nombre_categoria'])): ?> &nbsp;|&nbsp; <?= htmlspecialchars($pr['nombre_categoria']) ?><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="text-right" style="flex-shrink:0;">
                    <div style="font-weight:700;color:#1a7a4a;font-size:.92rem;">S/. <?= number_format($pr['precio_venta'],2) ?></div>
                    <div style="font-size:.75rem;color:<?= $pr['stock'] <= 0 ? '#e74c3c' : ($pr['stock'] <= 5 ? '#e67e22' : '#6c757d') ?>;">
                        Stock: <?= $pr['stock'] ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($productos_lista)): ?>
        <div class="text-center text-muted py-4"><i class="fas fa-boxes fa-2x mb-2 d-block" style="opacity:.3;"></i>No hay productos activos.</div>
        <?php endif; ?>
    </div>
    <div id="sinResultadosProdVta" style="display:none;" class="text-center text-muted py-4">
        <i class="fas fa-search fa-2x mb-2 d-block" style="opacity:.3;"></i>Sin resultados.
    </div>
</div>
</div></div>
</div>

<!-- Modal Detalle Item -->
<div class="modal fade" id="modalDetalleItemVta" tabindex="-1">
<div class="modal-dialog"><div class="modal-content" style="border-radius:14px;overflow:hidden;">
<div style="background:linear-gradient(135deg,#117a8b,#1a5276);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
    <div>
        <h6 style="color:#fff;font-weight:700;margin:0;"><i class="fas fa-box mr-2"></i>Detalle del Producto</h6>
        <small id="modal_item_nombre_vta" style="color:rgba(255,255,255,.85);font-size:.85rem;"></small>
    </div>
    <button type="button" class="close" style="color:#fff;opacity:.8;font-size:1.3rem;" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body p-4">
<input type="hidden" id="modal_item_id_producto_vta">
<small id="modal_item_codigo_vta" class="text-muted d-block mb-3"></small>
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label-vta">Cantidad <span class="text-danger">*</span></label>
        <div class="input-group input-group-sm">
            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-sort-numeric-up"></i></span></div>
            <input type="number" class="form-control" id="modal_item_cantidad_vta" min="1" value="1">
        </div>
        <small class="text-muted" id="modal_item_stock_disp_vta"></small>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label-vta">Precio Venta</label>
        <div class="input-group input-group-sm">
            <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
            <input type="text" class="form-control" id="modal_item_precio_vta" style="background:#f0fff4;color:#1a7a4a;font-weight:700;cursor:default;" readonly>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label-vta">Descuento
            <button type="button" id="btnDesbloquearDescVta" class="btn btn-xs ml-1"
                    style="display:none;background:#fff3cd;border:1px solid #ffc107;color:#856404;border-radius:6px;padding:1px 7px;font-size:.72rem;font-weight:700;cursor:pointer;"
                    title="Requiere autorización del administrador">
                <i class="fas fa-lock mr-1"></i>Desbloquear
            </button>
        </label>
        <div class="input-group input-group-sm">
            <div class="input-group-prepend"><span class="input-group-text">S/.</span></div>
            <input type="number" step="0.01" class="form-control" id="modal_item_descuento_vta" min="0" value="0.00">
        </div>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center mt-2 p-3" style="background:#f8f9fa;border-radius:8px;">
    <span style="font-weight:600;color:#555;">Subtotal:</span>
    <span id="modal_item_subtotal_vta" style="font-size:1.2rem;font-weight:700;color:#1a7a4a;">S/. 0.00</span>
</div>
</div>
<div class="modal-footer" style="border-top:1px solid #f0f0f0;padding:12px 20px;">
    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
    <button type="button" id="btnConfirmarItemVta" class="btn btn-sm font-weight-bold" style="background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;border:none;border-radius:8px;padding:8px 20px;"><i class="fas fa-check mr-1"></i>Agregar a la venta</button>
</div>
</div></div></div>

<!-- Modal Ver Venta -->
<div class="modal fade" id="modalVerVenta" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header" style="background:linear-gradient(135deg,#1a5276,#2980b9);color:#fff;border:none;">
    <h5 class="modal-title"><i class="fas fa-eye mr-2"></i>Detalle de Venta</h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>
<div class="modal-body" id="cuerpoModalVerVenta">
    <div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x" style="color:#1a5276;"></i></div>
</div>
<div class="modal-footer" style="border:none;" id="footerModalVerVenta"></div>
</div></div>
</div>

<!-- Modal Pagar Venta -->
<div class="modal fade" id="modalPagarVenta" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<div class="modal-header" style="background:linear-gradient(135deg,#e67e22,#f39c12);color:#fff;border:none;">
    <h5 class="modal-title"><i class="fas fa-hand-holding-usd mr-2"></i>Registrar Abono</h5>
    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
</div>
<form method="POST">
<input type="hidden" name="accion" value="registrar_pago">
<input type="hidden" name="id_venta" id="pago_id_venta">
<div class="modal-body">
    <div class="mb-3 p-3" style="background:#fff3e0;border-radius:8px;border:1px solid #ffe0b2;">
        <div style="font-size:.8rem;color:#e67e22;font-weight:600;margin-bottom:4px;"><i class="fas fa-file-invoice mr-1"></i>Venta</div>
        <div style="font-weight:700;font-size:.95rem;" id="pago_info_venta"></div>
        <div style="font-size:.88rem;color:#e67e22;margin-top:4px;"><i class="fas fa-exclamation-circle mr-1"></i>Saldo pendiente: <strong id="pago_saldo_venta"></strong></div>
    </div>
    <div class="mb-3">
        <label class="form-label-vta">Monto a Abonar (S/.)</label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text font-weight-bold">S/.</span></div>
            <input type="number" step="0.01" min="0.01" class="form-control font-weight-bold" name="monto_pago" id="pago_monto_venta" placeholder="0.00" style="font-size:1.1rem;">
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label-vta">Método de Pago</label>
        <div class="d-flex flex-wrap" style="gap:6px;">
            <button type="button" class="btn-metodo-pago-vta activo" data-metodo="efectivo"><i class="fas fa-money-bill mr-1"></i>Efectivo</button>
            <button type="button" class="btn-metodo-pago-vta" data-metodo="yape"><i class="fas fa-mobile-alt mr-1"></i>Yape</button>
            <button type="button" class="btn-metodo-pago-vta" data-metodo="transferencia"><i class="fas fa-university mr-1"></i>Transferencia</button>
        </div>
        <input type="hidden" name="metodo_pago_abono" id="pago_metodo_venta" value="efectivo">
    </div>
    <div class="mb-2">
        <label class="form-label-vta">Observación</label>
        <input type="text" class="form-control form-control-sm" name="obs_pago" placeholder="Opcional..." maxlength="200">
    </div>
</div>
<div class="modal-footer" style="border:none;">
    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
    <button type="submit" class="btn btn-sm font-weight-bold" style="background:linear-gradient(135deg,#e67e22,#f39c12);color:#fff;border:none;border-radius:8px;padding:8px 20px;">
        <i class="fas fa-check mr-1"></i>Registrar Abono
    </button>
</div>
</form>
</div></div>
</div>

</div></div>
</div><!-- /content-wrapper -->

<!-- ══ MODAL AUTORIZACIÓN ADMINISTRADOR — VENTAS ══ -->
<div class="modal fade" id="modalAdminAuthVta" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="false" data-keyboard="false" style="z-index:1060;">
<div class="modal-dialog modal-dialog-centered" style="max-width:380px;z-index:1061;">
<div class="modal-content" style="border:none;border-radius:18px;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.55);font-family:'Segoe UI',sans-serif;">

    <!-- Header rojo oscuro con grid y círculo -->
    <div style="background:linear-gradient(160deg,#1a0000 0%,#3d0000 55%,#1a0000 100%);padding:32px 24px 26px;text-align:center;position:relative;overflow:hidden;">
        <div style="position:absolute;inset:0;background-image:linear-gradient(rgba(220,50,50,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(220,50,50,.07) 1px,transparent 1px);background-size:26px 26px;pointer-events:none;"></div>
        <!-- Círculo animado -->
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

    <!-- Cuerpo oscuro -->
    <div style="background:#111827;padding:20px 20px 16px;">

        <!-- Info box rojo -->
        <div style="display:flex;align-items:flex-start;gap:12px;background:#1c0a0a;border:1px solid rgba(220,50,50,.3);border-left:3px solid #e05555;border-radius:10px;padding:14px;margin-bottom:16px;">
            <div style="width:36px;height:36px;border-radius:8px;background:rgba(220,50,50,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-shield-alt" style="color:#e05555;font-size:.9rem;"></i>
            </div>
            <div>
                <div style="font-size:.7rem;font-weight:700;color:#e05555;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">Sin autorización</div>
                <div style="font-size:.83rem;color:#ccc;line-height:1.45;">Solo el <strong style="color:#fff;">Administrador</strong> puede aplicar descuentos. Ingresa su contraseña para continuar.</div>
            </div>
        </div>

        <!-- Campo contraseña -->
        <div style="position:relative;margin-bottom:8px;">
            <span style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#555;pointer-events:none;">
                <i class="fas fa-key" style="font-size:.85rem;"></i>
            </span>
            <input type="password" id="adminAuthPassVta" autocomplete="new-password"
                   placeholder="Contraseña del administrador"
                   style="width:100%;padding:12px 44px 12px 38px;background:#1e293b;border:1.5px solid #2d3748;border-radius:10px;font-size:.88rem;color:#e2e8f0;outline:none;box-sizing:border-box;transition:border-color .2s;"
                   onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#2d3748'">
            <button type="button" id="adminAuthEyeVta" tabindex="-1"
                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#555;cursor:pointer;padding:0;font-size:.85rem;">
                <i class="fas fa-eye"></i>
            </button>
        </div>

        <!-- Error -->
        <div id="adminAuthErrVta" style="display:none;padding:9px 13px;background:#1c0a0a;border-left:3px solid #e74c3c;border-radius:6px;font-size:.8rem;color:#fc8181;margin-bottom:8px;">
            <i class="fas fa-exclamation-circle mr-1"></i><span id="adminAuthErrTxtVta"></span>
        </div>
    </div>

    <!-- Footer -->
    <div style="background:#111827;padding:0 20px 20px;display:flex;gap:10px;">
        <button type="button" id="adminAuthCancelVta"
                style="flex:1;background:#1e293b;color:#94a3b8;border:1.5px solid #2d3748;border-radius:10px;padding:11px;font-weight:700;font-size:.88rem;cursor:pointer;transition:all .2s;"
                onmouseover="this.style.background='#2d3748'" onmouseout="this.style.background='#1e293b'">
            <i class="fas fa-times mr-1"></i>Cancelar
        </button>
        <button type="button" id="adminAuthConfirmVta"
                style="flex:2;background:linear-gradient(135deg,#1d4ed8,#3b82f6);color:#fff;border:none;border-radius:10px;padding:11px;font-weight:700;font-size:.88rem;cursor:pointer;transition:opacity .2s;"
                onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
            <i class="fas fa-unlock mr-1"></i>Verificar acceso
        </button>
    </div>

</div>
</div>
</div>
<style>
@keyframes authPulse {
    0%,100% { box-shadow: 0 0 0 0 rgba(220,50,50,.3); }
    50%      { box-shadow: 0 0 0 10px rgba(220,50,50,.0); }
}
</style>

<?php
$extra_js  = '<script>var hayCajaVta = ' . ($hay_caja_vta ? 'true' : 'false') . '; var esAdminVta = ' . ((int)($_SESSION['id_rol'] ?? 0) === 1 ? 'true' : 'false') . ';</script>';
$extra_js .= '<script src="js/ventas.js?v=' . time() . '"></script>';
include $ruta_base . 'includes/footer.php';
?>
