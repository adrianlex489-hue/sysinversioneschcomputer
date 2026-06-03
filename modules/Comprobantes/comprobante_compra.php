<?php
// ============================================================
// comprobante_compra.php | SysInversiones CH Computer
// Genera PDF A4: Nota de Compra / Ticket de Compra (A4)
// Ticket 80mm → comprobante_ticket_compra.php
// Uso: ?id_compra=3
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
if (!$id_compra) { ob_end_clean(); die('ID de compra no especificado.'); }

// ── Conversión segura UTF-8 → ISO-8859-1 ─────────────────────────────────────
if (!function_exists('pdf_txt')) {
    function pdf_txt(string $t): string {
        $buscar     = ["\u{2014}", "\u{2013}", "\u{2012}", "\u{00B0}", "\u{00BA}",
                       "\u{00AA}", "\u{00E9}", "\u{00F3}", "\u{00FA}", "\u{00ED}",
                       "\u{00E1}", "\u{00F1}", "\u{00C1}", "\u{00C9}", "\u{00CD}",
                       "\u{00D3}", "\u{00DA}", "\u{00D1}", "\u{00FC}", "\u{00DC}"];
        $reemplazar = ['--', '-', '-', chr(176), chr(186),
                       chr(170), chr(233), chr(243), chr(250), chr(237),
                       chr(225), chr(241), chr(193), chr(201), chr(205),
                       chr(211), chr(218), chr(209), chr(252), chr(220)];
        $t = str_replace($buscar, $reemplazar, $t);
        $r = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $t);
        return $r !== false ? $r : $t;
    }
}

// Tipo de pago limpio
function fmt_pago_comp(string $tipo, string $metodo): string {
    return strtolower($tipo) === 'credito' ? 'CREDITO' : strtoupper($metodo);
}

$empresa = getEmpresa($pdo);

// Redirigir ticket al archivo 80mm
$stTipo = $pdo->prepare("SELECT tipo_comprobante FROM compras WHERE id_compra=?");
$stTipo->execute([$id_compra]);
$tipoRow = $stTipo->fetch();
if ($tipoRow && $tipoRow['tipo_comprobante'] === 'ticket') {
    ob_end_clean();
    header('Location: comprobante_ticket_compra.php?id_compra=' . $id_compra);
    exit;
}

$stC = $pdo->prepare(
    "SELECT c.*, p.razon_social AS proveedor, p.ruc AS ruc_prov,
            p.telefono AS tel_prov, p.direccion AS dir_prov,
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
    "SELECT dc.*, p.nombre_producto, p.codigo, p.presentacion,
            l.codigo_lote, l.fecha_vencimiento
     FROM detalle_compra dc
     JOIN productos p ON dc.id_producto = p.id_producto
     JOIN lotes l     ON dc.id_lote     = l.id_lote
     WHERE dc.id_compra = ?"
);
$stD->execute([$id_compra]);
$detalle = $stD->fetchAll();

// ── Clase PDF ─────────────────────────────────────────────────────────────────
class CompraPDF extends FPDF
{
    public array  $empresa = [];
    public string $tipo    = '';
    public string $numero  = '';

    public function Header(): void
    {
        $emp = $this->empresa;

        // Logo con tamaño controlado
        $logo_path = $_SERVER['DOCUMENT_ROOT'] . ($emp['logo'] ?? '');
        if (!empty($emp['logo']) && file_exists($logo_path)) {
            $info = @getimagesize($logo_path);
            if ($info) {
                $max_h  = 22;
                $draw_h = $max_h;
                $draw_w = min($max_h * ($info[0] / $info[1]), 36);
                $this->Image($logo_path, 10, 8, $draw_w, $draw_h);
            }
        }

        // Datos empresa — columna central
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(26, 122, 74);
        $this->SetXY(50, 8);
        $this->Cell(108, 7, pdf_txt($emp['razon_social'] ?? ''), 0, 1, 'C');

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(70, 70, 70);
        $this->SetX(50);
        $this->Cell(108, 5, pdf_txt('RUC: ' . ($emp['ruc'] ?? '')), 0, 1, 'C');
        $this->SetX(50);
        $dir = ($emp['direccion'] ?? '');
        if (!empty($emp['distrito'])) $dir .= ' - ' . $emp['distrito'];
        $this->Cell(108, 5, pdf_txt($dir), 0, 1, 'C');
        if (!empty($emp['telefono'])) {
            $this->SetX(50);
            $this->Cell(108, 5, pdf_txt('Tel: ' . $emp['telefono']), 0, 1, 'C');
        }
        if (!empty($emp['email'])) {
            $this->SetX(50);
            $this->Cell(108, 5, pdf_txt($emp['email']), 0, 1, 'C');
        }

        // Caja tipo comprobante — columna derecha
        $this->SetDrawColor(26, 122, 74);
        $this->SetLineWidth(0.6);
        $this->Rect(162, 8, 38, 24);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(26, 122, 74);
        $this->SetXY(162, 11);
        $this->Cell(38, 7, pdf_txt($this->tipo), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(40, 40, 40);
        $this->SetX(162);
        $this->Cell(38, 5, pdf_txt('N' . chr(176) . ' ' . $this->numero), 0, 1, 'C');

        // Línea separadora verde
        $this->SetDrawColor(26, 122, 74);
        $this->SetLineWidth(1.0);
        $this->Line(10, 35, 200, 35);
        $this->SetLineWidth(0.2);
        $this->Ln(8);
    }

    public function Footer(): void
    {
        $this->SetY(-16);
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.3);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(150, 150, 150);
        $pie = $this->empresa['pie_comprobante'] ?? 'Gracias por su preferencia';
        $this->Cell(0, 4, pdf_txt($pie), 0, 1, 'C');
        $this->Cell(0, 4, pdf_txt('Generado el ' . date('d/m/Y H:i')), 0, 0, 'C');
    }

    public function seccion(string $titulo): void
    {
        $this->SetFillColor(26, 122, 74);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(0, 6, pdf_txt('  ' . $titulo), 0, 1, 'L', true);
        $this->SetTextColor(40, 40, 40);
        $this->Ln(2);
    }

    public function dato(string $label, string $valor, float $wLbl = 32): void
    {
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($wLbl, 5, pdf_txt($label . ':'), 0, 0);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, pdf_txt($valor), 0, 1);
    }
}

// ── Generar PDF ───────────────────────────────────────────────────────────────
ob_end_clean();

$tipoLabel = [
    'nota'    => 'NOTA DE COMPRA',
    'ticket'  => 'TICKET DE COMPRA',
    'factura' => 'FACTURA',
    'boleta'  => 'BOLETA',
];

$pdf = new CompraPDF('P', 'mm', 'A4');
$pdf->empresa = $empresa;
$pdf->tipo    = $tipoLabel[$compra['tipo_comprobante']] ?? strtoupper($compra['tipo_comprobante']);
$pdf->numero  = $compra['numero_comprobante'] ?? ('C-' . str_pad($id_compra, 6, '0', STR_PAD_LEFT));
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// ── DATOS DEL PROVEEDOR ───────────────────────────────────────────────────────
$pdf->seccion('DATOS DEL PROVEEDOR');
$pdf->dato('Proveedor',     $compra['proveedor']);
$pdf->dato('RUC',           $compra['ruc_prov'] ?? '—');
if (!empty($compra['dir_prov'])) $pdf->dato('Direccion',    $compra['dir_prov']);
if (!empty($compra['tel_prov'])) $pdf->dato('Telefono',     $compra['tel_prov']);
$pdf->dato('Fecha',         date('d/m/Y H:i', strtotime($compra['fecha'])));
$pdf->dato('Tipo Pago',     fmt_pago_comp($compra['tipo_pago'], $compra['metodo_pago']));
$pdf->dato('Registrado por',$compra['registrado_por']);
$pdf->Ln(4);

// ── DETALLE DE PRODUCTOS ──────────────────────────────────────────────────────
$pdf->seccion('DETALLE DE PRODUCTOS');
$pdf->Ln(1);

// Detectar si hay descuentos para mostrar esa columna
$hay_descuento = array_sum(array_column($detalle, 'descuento')) > 0;

// Anchos: con lote y vencimiento (info interna de compra, sí aplica aquí)
if ($hay_descuento) {
    $wN=8; $wP=52; $wL=20; $wV=16; $wC=12; $wPC=22; $wD=18; $wS=22;
} else {
    $wN=8; $wP=60; $wL=22; $wV=18; $wC=14; $wPC=24; $wD=0;  $wS=24;
}

$pdf->SetFillColor(26, 122, 74);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell($wN, 7, '#',         1, 0, 'C', true);
$pdf->Cell($wP, 7, 'PRODUCTO',  1, 0, 'L', true);
$pdf->Cell($wL, 7, 'LOTE',      1, 0, 'C', true);
$pdf->Cell($wV, 7, 'VENCE',     1, 0, 'C', true);
$pdf->Cell($wC, 7, 'CANT.',     1, 0, 'C', true);
$pdf->Cell($wPC,7, 'P.COMPRA',  1, 0, 'R', true);
if ($hay_descuento) $pdf->Cell($wD, 7, 'DESC.', 1, 0, 'R', true);
$pdf->Cell($wS, 7, 'SUBTOTAL',  1, 1, 'R', true);
$pdf->SetTextColor(40, 40, 40);

$pdf->SetFont('Arial', '', 8);
$n = 1;
foreach ($detalle as $d) {
    $fill  = ($n % 2 === 0);
    $pdf->SetFillColor($fill ? 240 : 255, $fill ? 248 : 255, $fill ? 240 : 255);
    $nombre = $d['nombre_producto'];
    if (!empty($d['presentacion'])) $nombre .= ' - ' . $d['presentacion'];
    $vence = !empty($d['fecha_vencimiento'])
        ? date('d/m/Y', strtotime($d['fecha_vencimiento'])) : '—';

    $pdf->Cell($wN,  6, $n,                                              1, 0, 'C', $fill);
    $pdf->Cell($wP,  6, pdf_txt($nombre),                                1, 0, 'L', $fill);
    $pdf->Cell($wL,  6, $d['codigo_lote'],                               1, 0, 'C', $fill);
    $pdf->Cell($wV,  6, $vence,                                          1, 0, 'C', $fill);
    $pdf->Cell($wC,  6, $d['cantidad'],                                  1, 0, 'C', $fill);
    $pdf->Cell($wPC, 6, 'S/. ' . number_format($d['precio_compra'], 2),  1, 0, 'R', $fill);
    if ($hay_descuento) {
        $desc_txt = (float)$d['descuento'] > 0
            ? 'S/. ' . number_format($d['descuento'], 2) : '-';
        $pdf->Cell($wD, 6, $desc_txt, 1, 0, 'R', $fill);
    }
    $pdf->Cell($wS, 6, 'S/. ' . number_format($d['subtotal'], 2), 1, 1, 'R', $fill);
    $n++;
}
$pdf->Ln(4);

// ── TOTALES ───────────────────────────────────────────────────────────────────
$xT = 132; $wLb = 38; $wVl = 30;

$pdf->SetFont('Arial', '', 9);
$pdf->SetX($xT);
$pdf->Cell($wLb, 6, 'Subtotal:', 0, 0, 'R');
$pdf->Cell($wVl, 6, 'S/. ' . number_format($compra['subtotal'], 2), 0, 1, 'R');

if ((float)$compra['descuento'] > 0) {
    $pdf->SetX($xT);
    $pdf->SetTextColor(200, 0, 0);
    $pdf->Cell($wLb, 6, 'Descuento:', 0, 0, 'R');
    $pdf->Cell($wVl, 6, '- S/. ' . number_format($compra['descuento'], 2), 0, 1, 'R');
    $pdf->SetTextColor(40, 40, 40);
}
if ($compra['aplica_igv']) {
    $pdf->SetX($xT);
    $pdf->Cell($wLb, 6, 'IGV (18%):', 0, 0, 'R');
    $pdf->Cell($wVl, 6, 'S/. ' . number_format($compra['igv'], 2), 0, 1, 'R');
}

$pdf->SetDrawColor(26, 122, 74);
$pdf->SetLineWidth(0.6);
$pdf->Line($xT, $pdf->GetY() + 1, 200, $pdf->GetY() + 1);
$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(26, 122, 74);
$pdf->SetX($xT);
$pdf->Cell($wLb, 8, 'TOTAL:', 0, 0, 'R');
$pdf->Cell($wVl, 8, 'S/. ' . number_format($compra['total'], 2), 0, 1, 'R');
$pdf->SetTextColor(40, 40, 40);

if ((float)$compra['saldo_pendiente'] > 0) {
    $pdf->Ln(1);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(200, 100, 0);
    $pdf->SetX($xT);
    $pdf->Cell($wLb, 6, 'Saldo pendiente:', 0, 0, 'R');
    $pdf->Cell($wVl, 6, 'S/. ' . number_format($compra['saldo_pendiente'], 2), 0, 1, 'R');
    $pdf->SetTextColor(40, 40, 40);
}

// ── OBSERVACIÓN ───────────────────────────────────────────────────────────────
if (!empty($compra['observacion'])) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(0, 5, 'Observacion:', 0, 1);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->MultiCell(0, 5, pdf_txt($compra['observacion']));
}

// ── FIRMA para nota de compra ─────────────────────────────────────────────────
if ($compra['tipo_comprobante'] === 'nota') {
    $pdf->Ln(14);
    $pdf->SetDrawColor(150, 150, 150);
    $pdf->SetLineWidth(0.3);
    $pdf->Line(20, $pdf->GetY(), 90, $pdf->GetY());
    $pdf->Line(110, $pdf->GetY(), 180, $pdf->GetY());
    $pdf->Ln(2);
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(130, 130, 130);
    $pdf->Cell(100, 4, 'Firma del proveedor', 0, 0, 'C');
    $pdf->Cell(100, 4, pdf_txt('Firma del receptor'), 0, 1, 'C');
}

$nombre_archivo = strtolower($compra['tipo_comprobante']) . '_compra_'
    . str_pad($id_compra, 6, '0', STR_PAD_LEFT) . '.pdf';
$pdf->Output('I', $nombre_archivo);
