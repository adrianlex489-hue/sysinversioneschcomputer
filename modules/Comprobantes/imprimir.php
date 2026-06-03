<?php
// ============================================================
// imprimir.php | SysInversiones CH Computer
// Router de comprobantes — detecta tipo y redirige al PDF correcto
// Uso:
//   ?tipo=venta&id=5
//   ?tipo=compra&id=3
// ============================================================
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';

if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR_COMERCIAL'))        define('ROL_ASESOR_COMERCIAL', 2);
if (!defined('ROL_TECNICO'))    define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);

$tipo = $_GET['tipo'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

if (!$id) die('ID no especificado.');

if ($tipo === 'venta') {

    $st = $pdo->prepare("SELECT tipo_comprobante FROM ventas WHERE id_venta = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) die('Venta no encontrada.');

    if ($row['tipo_comprobante'] === 'ticket') {
        header("Location: comprobante_ticket.php?id_venta={$id}");
    } elseif ($row['tipo_comprobante'] === 'nota') {
        header("Location: comprobante_nota_venta.php?id_venta={$id}");
    } else {
        // boleta, factura → PDF A4
        header("Location: comprobante_venta.php?id_venta={$id}");
    }
    exit;

} elseif ($tipo === 'compra') {

    $st = $pdo->prepare("SELECT tipo_comprobante FROM compras WHERE id_compra = ?");
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) die('Compra no encontrada.');

    if ($row['tipo_comprobante'] === 'ticket') {
        // Compra con ticket del proveedor → ticket compacto 80mm
        header("Location: comprobante_ticket_compra.php?id_compra={$id}");
    } elseif ($row['tipo_comprobante'] === 'nota') {
        // Nota de compra → diseño A4 propio
        header("Location: comprobante_nota_compra.php?id_compra={$id}");
    } else {
        // factura, boleta → orden de compra A4
        header("Location: comprobante_compra.php?id_compra={$id}");
    }
    exit;

} else {
    die('Tipo de comprobante no reconocido.');
}
