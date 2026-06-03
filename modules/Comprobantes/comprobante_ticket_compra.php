<?php
// ============================================================
// comprobante_ticket_compra.php | SysInversiones 2026
// Ticket de compra formato 80mm — sin lotes
// Uso: ?id_compra=3  |  ?id_compra=3&download=1 (fuerza descarga)
// ============================================================
ob_start();

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'libs/fpdf.php';
require_once __DIR__ . '/../configuracion_empresa/empresa_helper.php';

if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR_COMERCIAL'))        define('ROL_ASESOR_COMERCIAL', 2);
if (!defined('ROL_TECNICO'))    define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);

$id_compra = (int)($_GET['id_compra'] ?? 0);
$download  = isset($_GET['download']) && $_GET['download'] == '1';
if (!$id_compra) { ob_end_clean(); die('ID de compra no especificado.'); }

// ── Conversión UTF-8 → ISO-8859-1 para FPDF ──────────────────────────────────
if (!function_exists('pdf_txt')) {
    function pdf_txt(string $t): string {
        $buscar     = ["\u{2014}","\u{2013}","\u{00B0}","\u{00BA}","\u{00AA}",
                       "\u{00E9}","\u{00F3}","\u{00FA}","\u{00ED}","\u{00E1}",
                       "\u{00F1}","\u{00C1}","\u{00C9}","\u{00CD}","\u{00D3}",
                       "\u{00DA}","\u{00D1}","\u{00FC}","\u{00DC}"];
        $reemplazar = ['--','-',chr(176),chr(186),chr(170),
                       chr(233),chr(243),chr(250),chr(237),chr(225),
                       chr(241),chr(193),chr(201),chr(205),chr(211),
                       chr(218),chr(209),chr(252),chr(220)];
        $t = str_replace($buscar, $reemplazar, $t);
        $r = iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE', $t);
        return $r !== false ? $r : $t;
    }
}

$empresa = getEmpresa($pdo);

// ── Datos de la compra ────────────────────────────────────────────────────────
$stC = $pdo->prepare(
    "SELECT c.*,
            p.razon_social  AS proveedor,
            p.ruc           AS ruc_prov,
            p.telefono      AS tel_prov,
            u.nombre_completo AS registrado_por
     FROM compras c
     JOIN proveedores p ON c.id_proveedor = p.id_proveedor
     JOIN usuarios    u ON c.id_usuario   = u.id_usuario
     WHERE c.id_compra = ?"
);
$stC->execute([$id_compra]);
$compra = $stC->fetch();
if (!$compra) { ob_end_clean(); die('Compra no encontrada.'); }

// ── Detalle de productos (sin lotes) ─────────────────────────────────────────
$stD = $pdo->prepare(
    "SELECT dc.cantidad, dc.precio_compra, dc.descuento, dc.subtotal,
            pr.nombre_producto, pr.codigo
     FROM detalle_compra dc
     JOIN productos pr ON dc.id_producto = pr.id_producto
     WHERE dc.id_compra = ?
     ORDER BY dc.id_detalle"
);
$stD->execute([$id_compra]);
$detalle = $stD->fetchAll();

// ── Clase Ticket 80mm ─────────────────────────────────────────────────────────
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
$alto_base  = 50 + 18 + 30 + 8 + 35 + 15;
$alto_prods = 0;
foreach ($detalle as $d) {
    $nombre_txt = pdf_txt($d['nombre_producto']);
    // Estimación: ~10 caracteres por línea en 74mm a 7pt
    $lineas = max(1, ceil(strlen($nombre_txt) / 38));
    $alto_prods += ($lineas * 4) + 4; // nombre + código/descuento
}
$alto = max($alto_base + $alto_prods, 130);

$pdf = new TicketCompraPDF('P', 'mm', [80, $alto]);
$pdf->SetMargins(3, 4, 3);
$pdf->SetAutoPageBreak(true, 8);
$pdf->AddPage();
$pdf->SetTextColor(20, 20, 20);

// ── LOGO ──────────────────────────────────────────────────────────────────────
$logo_path = $_SERVER['DOCUMENT_ROOT'] . ($empresa['logo'] ?? '');
if (!empty($empresa['logo']) && file_exists($logo_path)) {
    $info = @getimagesize($logo_path);
    if ($info) {
        $max_w  = 28;
        $draw_w = $max_w;
        $draw_h = $max_w * ($info[1] / $info[0]);
        $x_logo = (80 - $draw_w) / 2;
        $pdf->Image($logo_path, $x_logo, $pdf->GetY(), $draw_w, $draw_h);
        $pdf->Ln($draw_h + 2);
    }
}

// ── Datos empresa ─────────────────────────────────────────────────────────────
$pdf->centrar($empresa['razon_social'] ?? 'SYSINVERSIONES CH COMPUTER', 9, 'B', 5);
$pdf->centrar('RUC: ' . ($empresa['ruc'] ?? ''), 8, 'B', 4);
$pdf->centrar($empresa['direccion'] ?? '', 7, '', 4);
$ubigeo = trim(($empresa['distrito'] ?? '') . ' - ' . ($empresa['departamento'] ?? ''), ' -');
if ($ubigeo && $ubigeo !== '-') $pdf->centrar($ubigeo, 7, '', 4);
if (!empty($empresa['telefono']))  $pdf->centrar('Tel: ' . $empresa['telefono'], 7, '', 4);

$pdf->Ln(1);
$pdf->linea(true);

// ── Tipo comprobante ──────────────────────────────────────────────────────────
$tipoLabel = ['ticket' => 'TICKET DE COMPRA', 'nota' => 'NOTA DE COMPRA'];
$pdf->centrar($tipoLabel[$compra['tipo_comprobante']] ?? 'COMPROBANTE DE COMPRA', 11, 'B', 6);
$num = $compra['numero_comprobante'] ?? ('C-' . str_pad($id_compra, 6, '0', STR_PAD_LEFT));
$pdf->centrar($num, 9, 'B', 5);
$pdf->linea();

// ── Datos proveedor ───────────────────────────────────────────────────────────
$lineas_info = [
    'Proveedor' => $compra['proveedor'],
    'RUC'       => $compra['ruc_prov'] ?? '---',
    'Fecha'     => date('d/m/Y H:i', strtotime($compra['fecha'])),
    'Cajero'    => $compra['registrado_por'],
    'Pago'      => strtolower($compra['tipo_pago']) === 'credito'
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
$pdf->SetFillColor(26, 82, 118);
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
    $bg     = ($idx % 2 === 0);
    $pdf->SetFillColor(248, 248, 248);

    // Anchos de columnas (deben sumar 74mm = ancho útil del ticket)
    $wNombre = 36; // columna PRODUCTO
    $wCant   = 8;
    $wPcomp  = 15;
    $wTotal  = 15;

    // Calcular cuántas líneas ocupa el nombre dentro de los 36mm
    $pdf->SetFont('Arial', 'B', 7);
    $ancho_char = $pdf->GetStringWidth('A'); // ancho aprox de 1 carácter
    $chars_por_linea = max(1, floor($wNombre / ($ancho_char > 0 ? $ancho_char : 2)));
    $lineas_nombre = max(1, ceil(strlen($nombre) / $chars_por_linea));
    $alto_fila = $lineas_nombre * 4;

    $y_ini = $pdf->GetY();

    // ── Nombre: MultiCell en 36mm — se parte en varias líneas si es largo ──
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->MultiCell($wNombre, 4, $nombre, 0, 'L', $bg);
    $y_tras_nombre = $pdf->GetY();

    // ── Columnas numéricas: volver a Y inicial, posición X = 3 + 36 ──
    $pdf->SetXY(3 + $wNombre, $y_ini);
    $pdf->SetFont('Arial', '', 7);
    $pdf->Cell($wCant,  $alto_fila, $d['cantidad'],                                0, 0, 'C', $bg);
    $pdf->Cell($wPcomp, $alto_fila, 'S/.' . number_format($d['precio_compra'], 2), 0, 0, 'R', $bg);
    $pdf->Cell($wTotal, $alto_fila, 'S/.' . number_format($d['subtotal'], 2),      0, 1, 'R', $bg);

    // Continuar desde debajo del bloque más alto (nombre o celdas numéricas)
    $y_fin = max($pdf->GetY(), $y_tras_nombre);
    $pdf->SetY($y_fin);

    // Código del producto en gris pequeño
    if (!empty($d['codigo'])) {
        $pdf->SetFont('Arial', 'I', 6);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->Cell(74, 3, pdf_txt('  Cod: ' . $d['codigo']), 0, 1, 'L');
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

// Saldo pendiente (crédito)
if (isset($compra['saldo_pendiente']) && (float)$compra['saldo_pendiente'] > 0) {
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
$pdf->centrar('SysInversiones CH Computer', 7, '', 4);
$pdf->centrar(date('d/m/Y H:i'), 7, '', 4);

// ── Salida ────────────────────────────────────────────────────────────────────
ob_end_clean();
$nombre_archivo = 'ticket_compra_' . str_pad($id_compra, 6, '0', STR_PAD_LEFT) . '.pdf';
$modo = $download ? 'D' : 'I';
$pdf->Output($modo, $nombre_archivo);
