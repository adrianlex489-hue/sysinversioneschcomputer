<?php
// ============================================================
// comprobante_ticket.php | SysInversiones CH Computer 2026
// Ticket de venta formato impresora térmica 80mm
// Uso: ?id_venta=5  |  ?id_venta=5&download=1 (fuerza descarga)
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

$id_venta = (int)($_GET['id_venta'] ?? 0);
$download = isset($_GET['download']) && $_GET['download'] == '1';
if (!$id_venta) { ob_end_clean(); die('ID de venta no especificado.'); }

// ── Conversión robusta UTF-8 → ISO-8859-1 para FPDF ─────────────────────────
if (!function_exists('pdf_txt')) {
    function pdf_txt(string $t): string {
        $buscar     = ["\u{2014}", "\u{2013}", "\u{00B0}", "\u{00BA}", "\u{00AA}",
                       "\u{00E9}", "\u{00F3}", "\u{00FA}", "\u{00ED}", "\u{00E1}",
                       "\u{00F1}", "\u{00C1}", "\u{00C9}", "\u{00CD}", "\u{00D3}",
                       "\u{00DA}", "\u{00D1}", "\u{00FC}", "\u{00DC}"];
        $reemplazar = ['--', '-', chr(176), chr(186), chr(170),
                       chr(233), chr(243), chr(250), chr(237), chr(225),
                       chr(241), chr(193), chr(201), chr(205), chr(211),
                       chr(218), chr(209), chr(252), chr(220)];
        $t = str_replace($buscar, $reemplazar, $t);
        $r = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $t);
        return $r !== false ? $r : $t;
    }
}

$empresa = getEmpresa($pdo);

// ── Datos de la venta ─────────────────────────────────────────────────────────
$stV = $pdo->prepare(
    "SELECT v.*,
            CASE v.tipo_cliente
                WHEN 'empresa' THEN COALESCE(ce.razon_social, 'Sin nombre')
                ELSE CONCAT(COALESCE(cn.nombres,''), ' ', COALESCE(cn.apellido_paterno,''))
            END AS nombre_cliente,
            CASE v.tipo_cliente WHEN 'empresa' THEN ce.ruc ELSE cn.documento_identidad END AS documento_identidad,
            u.nombre_completo AS cajero
     FROM ventas v
     LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = v.id_cliente AND v.tipo_cliente = 'natural'
     LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = v.id_cliente AND v.tipo_cliente = 'empresa'
     JOIN usuarios u ON v.id_usuario = u.id_usuario
     WHERE v.id_venta = ?"
);
$stV->execute([$id_venta]);
$venta = $stV->fetch();
if (!$venta) { ob_end_clean(); die('Venta no encontrada.'); }

// ── Detalle de productos ──────────────────────────────────────────────────────
$stD = $pdo->prepare(
    "SELECT dv.cantidad, dv.precio_unitario, dv.descuento, dv.subtotal,
            p.nombre_producto, p.codigo
     FROM detalle_venta dv
     JOIN productos p ON dv.id_producto = p.id_producto
     WHERE dv.id_venta = ?
     ORDER BY dv.id_detalle"
);
$stD->execute([$id_venta]);
$detalle = $stD->fetchAll();

// ── Clase Ticket 80mm ─────────────────────────────────────────────────────────
class TicketVentaPDF extends FPDF
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
    $nombre_txt  = pdf_txt($d['nombre_producto']);
    $lineas      = max(1, ceil(strlen($nombre_txt) / 38));
    $alto_prods += ($lineas * 4) + 4;
}
$alto = max($alto_base + $alto_prods, 130);

$pdf = new TicketVentaPDF('P', 'mm', [80, $alto]);
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
if (!empty($empresa['telefono'])) $pdf->centrar('Tel: ' . $empresa['telefono'], 7, '', 4);

$pdf->Ln(1);
$pdf->linea(true);

// ── Tipo comprobante ──────────────────────────────────────────────────────────
$tipoLabel = [
    'boleta'  => 'BOLETA DE VENTA',
    'factura' => 'FACTURA',
    'ticket'  => 'TICKET DE VENTA',
    'nota'    => 'NOTA DE VENTA',
];
$pdf->centrar($tipoLabel[$venta['tipo_comprobante']] ?? 'COMPROBANTE DE VENTA', 11, 'B', 6);
$num = $venta['numero_comprobante'] ?? ('V-' . str_pad($id_venta, 6, '0', STR_PAD_LEFT));
$pdf->centrar($num, 9, 'B', 5);
$pdf->linea();

// ── Datos cliente ─────────────────────────────────────────────────────────────
$lineas_info = [
    'Cliente' => trim($venta['nombre_cliente']),
    'DNI'     => $venta['documento_identidad'] ?? '---',
    'Fecha'   => date('d/m/Y H:i', strtotime($venta['fecha'])),
    'Cajero'  => $venta['cajero'],
    'Pago'    => strtolower($venta['tipo_pago']) === 'credito'
                    ? 'CREDITO'
                    : strtoupper($venta['metodo_pago']),
];
foreach ($lineas_info as $lbl => $val) {
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(16, 4, pdf_txt($lbl . ':'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 7);
    $pdf->Cell($pdf->W - 16, 4, pdf_txt($val), 0, 1, 'L');
}
$pdf->linea();

// ── Cabecera tabla ────────────────────────────────────────────────────────────
$pdf->SetFillColor(26, 82, 118);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 7);
$pdf->Cell(36, 5, 'PRODUCTO', 0, 0, 'L', true);
$pdf->Cell(8,  5, 'CANT',     0, 0, 'C', true);
$pdf->Cell(15, 5, 'P.UNIT',   0, 0, 'R', true);
$pdf->Cell(15, 5, 'TOTAL',    0, 1, 'R', true);
$pdf->SetTextColor(20, 20, 20);
$pdf->Ln(1);

// ── Filas de productos ────────────────────────────────────────────────────────
foreach ($detalle as $idx => $d) {
    $nombre = pdf_txt($d['nombre_producto']);
    $bg     = ($idx % 2 === 0);
    $pdf->SetFillColor(248, 248, 248);

    $wNombre = 36;
    $wCant   = 8;
    $wPunit  = 15;
    $wTotal  = 15;

    // Calcular alto de fila según líneas del nombre
    $pdf->SetFont('Arial', 'B', 7);
    $ancho_char      = $pdf->GetStringWidth('A');
    $chars_por_linea = max(1, floor($wNombre / ($ancho_char > 0 ? $ancho_char : 2)));
    $lineas_nombre   = max(1, ceil(strlen($nombre) / $chars_por_linea));
    $alto_fila       = $lineas_nombre * 4;

    $y_ini = $pdf->GetY();

    // Nombre con MultiCell para nombres largos
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->MultiCell($wNombre, 4, $nombre, 0, 'L', $bg);
    $y_tras_nombre = $pdf->GetY();

    // Columnas numéricas alineadas al Y inicial
    $pdf->SetXY(3 + $wNombre, $y_ini);
    $pdf->SetFont('Arial', '', 7);
    $pdf->Cell($wCant,  $alto_fila, $d['cantidad'],                                    0, 0, 'C', $bg);
    $pdf->Cell($wPunit, $alto_fila, 'S/.' . number_format($d['precio_unitario'], 2),   0, 0, 'R', $bg);
    $pdf->Cell($wTotal, $alto_fila, 'S/.' . number_format($d['subtotal'], 2),          0, 1, 'R', $bg);

    $pdf->SetY(max($pdf->GetY(), $y_tras_nombre));

    // Código del producto
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
$pdf->par('Subtotal:', 'S/. ' . number_format($venta['subtotal'], 2));

if ((float)$venta['descuento'] > 0) {
    $pdf->SetTextColor(180, 0, 0);
    $pdf->par('Descuento:', '- S/. ' . number_format($venta['descuento'], 2));
    $pdf->SetTextColor(20, 20, 20);
}
if ($venta['aplica_igv']) {
    $pdf->par('IGV (18%):', 'S/. ' . number_format($venta['igv'], 2));
}

$pdf->Ln(1);
$pdf->linea();

// TOTAL destacado
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(26, 82, 118);
$pdf->par('TOTAL:', 'S/. ' . number_format($venta['total'], 2), 12, 38, 'B', 'B');
$pdf->SetTextColor(20, 20, 20);

// Saldo pendiente (crédito)
if ((float)$venta['saldo_pendiente'] > 0) {
    $pdf->Ln(1);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(200, 100, 0);
    $pdf->par('Saldo pendiente:', 'S/. ' . number_format($venta['saldo_pendiente'], 2), 8, 44, 'B', 'B');
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
$nombre_archivo = 'ticket_venta_' . str_pad($id_venta, 6, '0', STR_PAD_LEFT) . '.pdf';
$modo = $download ? 'D' : 'I';
$pdf->Output($modo, $nombre_archivo);
