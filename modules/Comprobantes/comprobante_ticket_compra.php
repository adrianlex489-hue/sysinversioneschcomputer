<?php
// ============================================================
// comprobante_ticket_compra.php | Botica 2026
// Ticket de compra formato impresora térmica 80mm
// Uso: ?id_compra=3
// ============================================================
ob_start();

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'libs/fpdf.php';
require_once __DIR__ . '/empresa_helper.php';

if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_CAJERO'))        define('ROL_CAJERO', 2);
if (!defined('ROL_TRABAJADOR'))    define('ROL_TRABAJADOR', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_CAJERO, ROL_TRABAJADOR]);

$id_compra = (int)($_GET['id_compra'] ?? 0);
if (!$id_compra) { ob_end_clean(); die('ID de compra no especificado.'); }

if (!function_exists('pdf_txt')) {
    function pdf_txt(string $t): string {
        $r = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $t);
        return $r !== false ? $r : $t;
    }
}

$empresa = getEmpresa($pdo);

$stC = $pdo->prepare(
    "SELECT c.*, p.razon_social AS proveedor, p.ruc AS ruc_prov,
            u.nombre_completo AS registrado_por
     FROM compras c
     JOIN proveedores p ON c.id_proveedor = p.id_proveedor
     JOIN usuarios u    ON c.id_usuario   = u.id_usuario
     WHERE c.id_compra = ?"
);
$stC->execute([$id_compra]);
$compra = $stC->fetch();
if (!$compra) { ob_end_clean(); die('Compra no encontrada.'); }

$stD = $pdo->prepare(
    "SELECT dc.cantidad, dc.precio_compra, dc.descuento, dc.subtotal,
            p.nombre_producto, l.codigo_lote
     FROM detalle_compra dc
     JOIN productos p ON dc.id_producto = p.id_producto
     JOIN lotes l     ON dc.id_lote     = l.id_lote
     WHERE dc.id_compra = ?"
);
$stD->execute([$id_compra]);
$detalle = $stD->fetchAll();

// ── Clase Ticket ──────────────────────────────────────────────────────────────
class TicketCompraPDF extends FPDF
{
    public float $W = 74; // ancho útil (80mm - 3mm margen c/lado)

    public function linea(bool $doble = false): void {
        $y = $this->GetY() + 1;
        $this->SetDrawColor(160, 160, 160);
        $this->SetLineWidth(0.3);
        $this->Line(3, $y, 77, $y);
        if ($doble) {
            $this->SetLineWidth(0.5);
            $this->Line(3, $y + 1.2, 77, $y + 1.2);
            $this->Ln(4);
        } else {
            $this->Ln(3);
        }
    }

    public function centrar(string $txt, int $sz, string $st = '', float $h = 5): void {
        $this->SetFont('Arial', $st, $sz);
        $this->Cell($this->W, $h, pdf_txt($txt), 0, 1, 'C');
    }

    public function par(string $izq, string $der, int $sz = 8,
                        float $wIzq = 44, string $stIzq = '', string $stDer = ''): void {
        $this->SetFont('Arial', $stIzq, $sz);
        $this->Cell($wIzq, 5, pdf_txt($izq), 0, 0, 'L');
        $this->SetFont('Arial', $stDer, $sz);
        $this->Cell($this->W - $wIzq, 5, pdf_txt($der), 0, 1, 'R');
    }
}

// ── Alto dinámico ─────────────────────────────────────────────────────────────
// Cabecera empresa: ~50mm (logo + datos)
// Tipo comprobante: ~18mm
// Datos proveedor:  ~28mm (5 líneas)
// Tabla header:     ~8mm
// Productos:        ~12mm por item
// Totales:          ~35mm (subtotal + igv + total + saldo)
// Pie + barras:     ~25mm
$alto_base   = 50 + 18 + 28 + 8 + 35 + 15;
$alto_prods  = count($detalle) * 14;
$alto        = $alto_base + $alto_prods;
$alto        = max($alto, 130);

$pdf = new TicketCompraPDF('P', 'mm', [80, $alto]);
$pdf->SetMargins(3, 4, 3);
$pdf->SetAutoPageBreak(true, 8); // margen inferior 8mm para no cortar contenido
$pdf->AddPage();
$pdf->SetTextColor(20, 20, 20);

// ── LOGO (solo si existe, centrado, tamaño controlado) ────────────────────────
$logo_path = $_SERVER['DOCUMENT_ROOT'] . ($empresa['logo'] ?? '');
if (!empty($empresa['logo']) && file_exists($logo_path)) {
    // Obtener dimensiones reales para escalar proporcionalmente
    $info = getimagesize($logo_path);
    if ($info) {
        $img_w = $info[0]; $img_h = $info[1];
        // Limitar a 28mm de ancho máximo
        $max_w = 28;
        $ratio = $img_h / $img_w;
        $draw_w = $max_w;
        $draw_h = $max_w * $ratio;
        // Centrar horizontalmente en 80mm
        $x_logo = (80 - $draw_w) / 2;
        $pdf->Image($logo_path, $x_logo, $pdf->GetY(), $draw_w, $draw_h);
        $pdf->Ln($draw_h + 2);
    }
}

// ── Nombre empresa (solo razón social, sin duplicar si el logo ya tiene texto) ─
$pdf->centrar($empresa['razon_social'] ?? '', 9, 'B', 5);
$pdf->centrar('RUC: ' . ($empresa['ruc'] ?? ''), 8, 'B', 4);
$pdf->centrar($empresa['direccion'] ?? '', 7, '', 4);

$ubigeo = trim(($empresa['distrito'] ?? '') . ' - ' . ($empresa['departamento'] ?? ''), ' -');
if ($ubigeo !== '' && $ubigeo !== '-') {
    $pdf->centrar($ubigeo, 7, '', 4);
}
if (!empty($empresa['telefono'])) {
    $pdf->centrar('Tel: ' . $empresa['telefono'], 7, '', 4);
}

$pdf->Ln(1);
$pdf->linea(true);

// ── Tipo comprobante ──────────────────────────────────────────────────────────
$pdf->centrar('TICKET DE COMPRA', 11, 'B', 6);
$num = $compra['numero_comprobante'] ?? ('C-' . str_pad($id_compra, 6, '0', STR_PAD_LEFT));
$pdf->centrar($num, 9, 'B', 5);

$pdf->linea();

// ── Datos proveedor ───────────────────────────────────────────────────────────
$pdf->SetFont('Arial', '', 7);
$lineas_info = [
    'Proveedor' => $compra['proveedor'],
    'RUC'       => $compra['ruc_prov'] ?? '—',
    'Fecha'     => date('d/m/Y H:i', strtotime($compra['fecha'])),
    'Cajero'    => $compra['registrado_por'],
    'Pago'      => strtoupper($compra['tipo_pago']) === 'CREDITO'
                    ? 'CREDITO'
                    : strtoupper($compra['metodo_pago']),
];
foreach ($lineas_info as $lbl => $val) {
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(18, 4, pdf_txt($lbl . ':'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 7);
    $pdf->Cell($pdf->W - 18, 4, pdf_txt($val), 0, 1, 'L');
}

$pdf->linea();

// ── Cabecera tabla ────────────────────────────────────────────────────────────
$pdf->SetFillColor(25, 25, 25);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(36, 5, 'PRODUCTO',  0, 0, 'L', true);
$pdf->Cell(8,  5, 'CANT',      0, 0, 'C', true);
$pdf->Cell(15, 5, 'P.COMP',    0, 0, 'R', true);
$pdf->Cell(15, 5, 'TOTAL',     0, 1, 'R', true);
$pdf->SetTextColor(20, 20, 20);
$pdf->Ln(1);

// ── Filas de productos ────────────────────────────────────────────────────────
foreach ($detalle as $idx => $d) {
    $nombre = pdf_txt($d['nombre_producto']);
    $bg = ($idx % 2 === 0);
    $pdf->SetFillColor(248, 248, 248);

    // Nombre del producto
    $pdf->SetFont('Arial', 'B', 7);
    if (strlen($nombre) > 19) {
        $pdf->Cell(74, 4, $nombre, 0, 1, 'L', $bg);
        $pdf->SetFont('Arial', '', 7);
        $pdf->Cell(36, 5, '', 0, 0, 'L', $bg);
    } else {
        $pdf->Cell(36, 5, $nombre, 0, 0, 'L', $bg);
        $pdf->SetFont('Arial', '', 7);
    }
    $pdf->Cell(8,  5, $d['cantidad'], 0, 0, 'C', $bg);
    $pdf->Cell(15, 5, 'S/.' . number_format($d['precio_compra'], 2), 0, 0, 'R', $bg);
    $pdf->Cell(15, 5, 'S/.' . number_format($d['subtotal'], 2), 0, 1, 'R', $bg);

    // Lote en gris pequeño
    if (!empty($d['codigo_lote'])) {
        $pdf->SetFont('Arial', 'I', 6);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->Cell(74, 3, pdf_txt('  Lote: ' . $d['codigo_lote']), 0, 1, 'L');
        $pdf->SetTextColor(20, 20, 20);
    }
    // Descuento si aplica
    if ((float)$d['descuento'] > 0) {
        $pdf->SetFont('Arial', 'I', 6);
        $pdf->SetTextColor(180, 0, 0);
        $pdf->Cell(74, 3, pdf_txt('  Dto: -S/.' . number_format($d['descuento'], 2)), 0, 1, 'L');
        $pdf->SetTextColor(20, 20, 20);
    }
}

$pdf->Ln(1);
$pdf->linea(true);

// ── Totales ───────────────────────────────────────────────────────────────────
$pdf->SetFont('Arial', '', 8);
$pdf->par('Subtotal:', 'S/. ' . number_format($compra['subtotal'], 2));

if ((float)$compra['descuento'] > 0) {
    $pdf->SetTextColor(180, 0, 0);
    $pdf->par('Descuento:', '- S/. ' . number_format($compra['descuento'], 2));
    $pdf->SetTextColor(20, 20, 20);
}
if ($compra['aplica_igv']) {
    $pdf->par('IGV (18%):', 'S/. ' . number_format($compra['igv'], 2));
}

$pdf->Ln(1);
$pdf->linea();

// TOTAL destacado
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(26, 82, 118);
$pdf->par('TOTAL:', 'S/. ' . number_format($compra['total'], 2), 12, 38, 'B', 'B');
$pdf->SetTextColor(20, 20, 20);

if ((float)$compra['saldo_pendiente'] > 0) {
    $pdf->Ln(1);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(200, 100, 0);
    $pdf->par('Saldo pendiente:', 'S/. ' . number_format($compra['saldo_pendiente'], 2), 8, 44, 'B', 'B');
    $pdf->SetTextColor(20, 20, 20);
}

$pdf->Ln(3);
$pdf->linea();

// ── Pie ───────────────────────────────────────────────────────────────────────
$pie = $empresa['pie_comprobante'] ?? 'Gracias por su preferencia';
$pdf->SetFont('Arial', 'I', 7);
$pdf->SetTextColor(110, 110, 110);
$pdf->centrar($pie, 7, 'I', 4);
$pdf->centrar(date('d/m/Y H:i'), 7, '', 4);

ob_end_clean();
$nombre_archivo = 'ticket_compra_' . str_pad($id_compra, 6, '0', STR_PAD_LEFT) . '.pdf';
$pdf->Output('I', $nombre_archivo);
