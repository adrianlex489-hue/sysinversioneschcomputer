<?php
// ============================================================
// comprobante_venta.php | SysInversiones CH Computer
// Genera PDF A4: Boleta / Factura / Nota de Venta
// Ticket 80mm → comprobante_ticket.php
// Uso: ?id_venta=5
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
if (!$id_venta) { ob_end_clean(); die('ID de venta no especificado.'); }

// ── Conversión segura UTF-8 → ISO-8859-1 ─────────────────────────────────────
// Reemplaza caracteres problemáticos ANTES de iconv
if (!function_exists('pdf_txt')) {
    function pdf_txt(string $t): string {
        // Reemplazar caracteres especiales comunes por equivalentes ASCII seguros
        $buscar  = ["\u{2014}", "\u{2013}", "\u{2012}", "\u{00B0}", "\u{00BA}",
                    "\u{00AA}", "\u{00E9}", "\u{00F3}", "\u{00FA}", "\u{00ED}",
                    "\u{00E1}", "\u{00F1}", "\u{00C1}", "\u{00C9}", "\u{00CD}",
                    "\u{00D3}", "\u{00DA}", "\u{00D1}", "\u{00FC}", "\u{00DC}"];
        $reemplazar = ['--', '-', '-', chr(176), chr(186),
                       chr(170), chr(233), chr(243), chr(250), chr(237),
                       chr(225), chr(241), chr(193), chr(201), chr(205),
                       chr(211), chr(218), chr(209), chr(252), chr(220)];
        $t = str_replace($buscar, $reemplazar, $t);
        // Convertir el resto
        $r = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $t);
        return $r !== false ? $r : $t;
    }
}

// Función para mostrar el método de pago de forma limpia
function fmt_metodo(string $tipo_pago, string $metodo): string {
    // Si es crédito, mostrar solo CREDITO
    if (strtolower($tipo_pago) === 'credito') {
        return 'CREDITO';
    }
    // Si es contado, mostrar solo el método (EFECTIVO, YAPE, PLIN, etc.)
    return strtoupper($metodo);
}

$empresa = getEmpresa($pdo);

// Redirigir ticket al archivo correcto
$stTipo = $pdo->prepare("SELECT tipo_comprobante FROM ventas WHERE id_venta=?");
$stTipo->execute([$id_venta]);
$tipoRow = $stTipo->fetch();
if ($tipoRow && $tipoRow['tipo_comprobante'] === 'ticket') {
    ob_end_clean();
    header('Location: comprobante_ticket.php?id_venta=' . $id_venta);
    exit;
}

$stV = $pdo->prepare(
    "SELECT v.*,
            CASE v.tipo_cliente
                WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
                ELSE CONCAT(COALESCE(cn.nombres,''),' ',COALESCE(cn.apellido_paterno,''),
                     CASE WHEN cn.apellido_materno IS NOT NULL AND cn.apellido_materno != ''
                          THEN CONCAT(' ',cn.apellido_materno) ELSE '' END)
            END AS nombre_cliente,
            CASE v.tipo_cliente WHEN 'empresa' THEN ce.ruc ELSE cn.documento_identidad END AS dni,
            CASE v.tipo_cliente WHEN 'empresa' THEN ce.telefono ELSE cn.telefono END AS tel_cliente,
            CASE v.tipo_cliente WHEN 'empresa' THEN ce.direccion ELSE cn.direccion END AS dir_cliente,
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

$stD = $pdo->prepare(
    "SELECT dv.*, p.nombre_producto, p.codigo, p.presentacion,
            l.codigo_lote, l.fecha_vencimiento
     FROM detalle_venta dv
     JOIN productos p ON dv.id_producto = p.id_producto
     JOIN lotes l     ON dv.id_lote     = l.id_lote
     WHERE dv.id_venta = ?"
);
$stD->execute([$id_venta]);
$detalle = $stD->fetchAll();

// ── Clase PDF A4 ──────────────────────────────────────────────────────────────
class VentaPDF extends FPDF
{
    public array  $empresa = [];
    public string $tipo    = '';
    public string $numero  = '';

    function Header()
    {
        $emp = $this->empresa;

        // ── Logo (columna izquierda, tamaño controlado) ──
        $logo_path = $_SERVER['DOCUMENT_ROOT'] . ($emp['logo'] ?? '');
        if (!empty($emp['logo']) && file_exists($logo_path)) {
            $info = @getimagesize($logo_path);
            if ($info) {
                $max_h  = 22; // altura máxima del logo en mm
                $ratio  = $info[0] / $info[1];
                $draw_h = $max_h;
                $draw_w = $max_h * $ratio;
                $draw_w = min($draw_w, 36); // ancho máximo 36mm
                $this->Image($logo_path, 10, 8, $draw_w, $draw_h);
            }
        }

        // ── Datos empresa (columna central) ──
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

        // ── Caja tipo comprobante (columna derecha) ──
        $this->SetDrawColor(26, 122, 74);
        $this->SetLineWidth(0.6);
        $this->Rect(162, 8, 38, 24);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(26, 122, 74);
        $this->SetXY(162, 11);
        $this->Cell(38, 7, pdf_txt(strtoupper($this->tipo)), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(40, 40, 40);
        $this->SetX(162);
        $this->Cell(38, 5, pdf_txt('N' . chr(176) . ' ' . $this->numero), 0, 1, 'C');

        // ── Línea separadora verde ──
        $this->SetDrawColor(26, 122, 74);
        $this->SetLineWidth(1.0);
        $this->Line(10, 35, 200, 35);
        $this->SetLineWidth(0.2);
        $this->Ln(8);
    }

    function Footer()
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

    function seccion(string $titulo): void
    {
        $this->SetFillColor(26, 122, 74);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(0, 6, pdf_txt('  ' . $titulo), 0, 1, 'L', true);
        $this->SetTextColor(40, 40, 40);
        $this->Ln(2);
    }

    function dato(string $label, string $valor, float $wLbl = 32): void
    {
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($wLbl, 5, pdf_txt($label . ':'), 0, 0);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, pdf_txt($valor), 0, 1);
    }
}

// ── Instanciar PDF ────────────────────────────────────────────────────────────
$tipoLabel = [
    'nota'    => 'NOTA DE VENTA',
    'ticket'  => 'TICKET',
    'boleta'  => 'BOLETA',
    'factura' => 'FACTURA',
];

$pdf = new VentaPDF('P', 'mm', 'A4');
$pdf->empresa = $empresa;
$pdf->tipo    = $tipoLabel[$venta['tipo_comprobante']] ?? strtoupper($venta['tipo_comprobante']);
$pdf->numero  = $venta['numero_comprobante'] ?? ('V-' . str_pad($id_venta, 6, '0', STR_PAD_LEFT));
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// ── DATOS DEL CLIENTE ─────────────────────────────────────────────────────────
$pdf->seccion('DATOS DEL CLIENTE');
$pdf->dato('Cliente',    trim($venta['nombre_cliente']));
$pdf->dato('DNI/Doc.',   $venta['dni'] ?? '—');
if (!empty($venta['dir_cliente'])) {
    $pdf->dato('Direccion',  $venta['dir_cliente']);
}
$pdf->dato('Fecha',      date('d/m/Y H:i', strtotime($venta['fecha'])));
$pdf->dato('Cajero',     $venta['cajero']);
$pdf->dato('Tipo Pago',  fmt_metodo($venta['tipo_pago'], $venta['metodo_pago']));
$pdf->Ln(4);

// ── DETALLE DE PRODUCTOS ──────────────────────────────────────────────────────
$pdf->seccion('DETALLE DE PRODUCTOS');
$pdf->Ln(1);

// Detectar si algún ítem tiene descuento para mostrar esa columna
$hay_descuento = array_sum(array_column($detalle, 'descuento')) > 0;

// Anchos de columna según si hay descuento o no
if ($hay_descuento) {
    $wNum  = 8;  $wProd = 88; $wCant = 14; $wPU = 24; $wDesc = 20; $wSub = 26;
} else {
    $wNum  = 8;  $wProd = 108; $wCant = 14; $wPU = 26; $wDesc = 0; $wSub = 34;
}

// Cabecera tabla
$pdf->SetFillColor(26, 122, 74);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell($wNum,  7, '#',         1, 0, 'C', true);
$pdf->Cell($wProd, 7, 'PRODUCTO',  1, 0, 'L', true);
$pdf->Cell($wCant, 7, 'CANT.',     1, 0, 'C', true);
$pdf->Cell($wPU,   7, 'P. UNIT.',  1, 0, 'R', true);
if ($hay_descuento) {
    $pdf->Cell($wDesc, 7, 'DESC.',  1, 0, 'R', true);
}
$pdf->Cell($wSub,  7, 'SUBTOTAL',  1, 1, 'R', true);
$pdf->SetTextColor(40, 40, 40);

// Filas
$pdf->SetFont('Arial', '', 8);
$n = 1;
foreach ($detalle as $d) {
    $fill = ($n % 2 === 0);
    $pdf->SetFillColor($fill ? 240 : 255, $fill ? 248 : 255, $fill ? 240 : 255);

    $nombre = $d['nombre_producto'];
    if (!empty($d['presentacion'])) $nombre .= ' - ' . $d['presentacion'];

    $pdf->Cell($wNum,  6, $n,                                              1, 0, 'C', $fill);
    $pdf->Cell($wProd, 6, pdf_txt($nombre),                                1, 0, 'L', $fill);
    $pdf->Cell($wCant, 6, $d['cantidad'],                                  1, 0, 'C', $fill);
    $pdf->Cell($wPU,   6, 'S/. ' . number_format($d['precio_unitario'],2), 1, 0, 'R', $fill);
    if ($hay_descuento) {
        $desc_txt = (float)$d['descuento'] > 0
            ? 'S/. ' . number_format($d['descuento'], 2) : '-';
        $pdf->Cell($wDesc, 6, $desc_txt, 1, 0, 'R', $fill);
    }
    $pdf->Cell($wSub, 6, 'S/. ' . number_format($d['subtotal'],2), 1, 1, 'R', $fill);
    $n++;
}
$pdf->Ln(4);

// ── TOTALES ───────────────────────────────────────────────────────────────────
$xT  = 132;
$wLb = 38;
$wVl = 30;

$pdf->SetFont('Arial', '', 9);
$pdf->SetX($xT);
$pdf->Cell($wLb, 6, 'Subtotal:', 0, 0, 'R');
$pdf->Cell($wVl, 6, 'S/. ' . number_format($venta['subtotal'], 2), 0, 1, 'R');

if ((float)$venta['descuento'] > 0) {
    $pdf->SetX($xT);
    $pdf->SetTextColor(200, 0, 0);
    $pdf->Cell($wLb, 6, 'Descuento:', 0, 0, 'R');
    $pdf->Cell($wVl, 6, '- S/. ' . number_format($venta['descuento'], 2), 0, 1, 'R');
    $pdf->SetTextColor(40, 40, 40);
}

if ($venta['aplica_igv']) {
    $pdf->SetX($xT);
    $pdf->Cell($wLb, 6, 'IGV (18%):', 0, 0, 'R');
    $pdf->Cell($wVl, 6, 'S/. ' . number_format($venta['igv'], 2), 0, 1, 'R');
}

// Línea total
$pdf->SetDrawColor(26, 122, 74);
$pdf->SetLineWidth(0.6);
$pdf->Line($xT, $pdf->GetY() + 1, 200, $pdf->GetY() + 1);
$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(26, 122, 74);
$pdf->SetX($xT);
$pdf->Cell($wLb, 8, 'TOTAL:', 0, 0, 'R');
$pdf->Cell($wVl, 8, 'S/. ' . number_format($venta['total'], 2), 0, 1, 'R');
$pdf->SetTextColor(40, 40, 40);

if ((float)$venta['saldo_pendiente'] > 0) {
    $pdf->Ln(1);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(200, 100, 0);
    $pdf->SetX($xT);
    $pdf->Cell($wLb, 6, 'Saldo pendiente:', 0, 0, 'R');
    $pdf->Cell($wVl, 6, 'S/. ' . number_format($venta['saldo_pendiente'], 2), 0, 1, 'R');
    $pdf->SetTextColor(40, 40, 40);
}

// ── OBSERVACIÓN ───────────────────────────────────────────────────────────────
if (!empty($venta['observacion'])) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(0, 5, 'Observacion:', 0, 1);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->MultiCell(0, 5, pdf_txt($venta['observacion']));
}

// ── FIRMA — solo para nota de venta (receptor confirma recepción) ─────────────
if ($venta['tipo_comprobante'] === 'nota') {
    $pdf->Ln(14);
    $pdf->SetDrawColor(150, 150, 150);
    $pdf->SetLineWidth(0.3);
    $pdf->Line(20, $pdf->GetY(), 90, $pdf->GetY());
    $pdf->Line(110, $pdf->GetY(), 180, $pdf->GetY());
    $pdf->Ln(2);
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(130, 130, 130);
    $pdf->Cell(100, 4, 'Firma del receptor', 0, 0, 'C');
    $pdf->Cell(100, 4, pdf_txt('Firma del emisor'), 0, 1, 'C');
}

ob_end_clean();
$nombre = strtolower($venta['tipo_comprobante']) . '_'
        . str_replace(['/', '\\', '-', ' '], '_', $venta['numero_comprobante'] ?? $id_venta)
        . '.pdf';
$pdf->Output('I', $nombre);
