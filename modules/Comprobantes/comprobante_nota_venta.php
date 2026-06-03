<?php
// ============================================================
// comprobante_nota_venta.php | SysInversiones CH Computer 2026
// Nota de Venta — PDF A4 con diseño propio y número correlativo
// Uso: ?id_venta=5
// ============================================================
ob_start();

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'libs/fpdf.php';
require_once __DIR__ . '/../configuracion_empresa/empresa_helper.php';

if (!defined('ROL_ADMINISTRADOR'))    define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR_COMERCIAL')) define('ROL_ASESOR_COMERCIAL', 2);
if (!defined('ROL_TECNICO'))          define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);

$id_venta = (int)($_GET['id_venta'] ?? 0);
if (!$id_venta) { ob_end_clean(); die('ID de venta no especificado.'); }

if (!function_exists('pdf_txt')) {
    function pdf_txt(string $t): string {
        $b = ["\u{2014}","\u{2013}","\u{00B0}","\u{00BA}","\u{00AA}",
              "\u{00E9}","\u{00F3}","\u{00FA}","\u{00ED}","\u{00E1}",
              "\u{00F1}","\u{00C1}","\u{00C9}","\u{00CD}","\u{00D3}",
              "\u{00DA}","\u{00D1}","\u{00FC}","\u{00DC}"];
        $r = ['--','-',chr(176),chr(186),chr(170),
              chr(233),chr(243),chr(250),chr(237),chr(225),
              chr(241),chr(193),chr(201),chr(205),chr(211),
              chr(218),chr(209),chr(252),chr(220)];
        $t = str_replace($b, $r, $t);
        $x = iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE',$t);
        return $x !== false ? $x : $t;
    }
}

$empresa = getEmpresa($pdo);

// ── Datos de la venta ─────────────────────────────────────────────────────────
$stV = $pdo->prepare("
    SELECT v.*,
           CASE v.tipo_cliente
               WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
               ELSE TRIM(CONCAT_WS(' ', cn.nombres, cn.apellido_paterno, cn.apellido_materno))
           END AS nombre_cliente,
           CASE v.tipo_cliente WHEN 'empresa' THEN ce.ruc       ELSE cn.documento_identidad END AS doc_numero,
           CASE v.tipo_cliente WHEN 'empresa' THEN 'RUC'        ELSE cn.tipo_documento       END AS doc_tipo,
           CASE v.tipo_cliente WHEN 'empresa' THEN ce.telefono  ELSE cn.telefono              END AS tel_cliente,
           CASE v.tipo_cliente WHEN 'empresa' THEN ce.direccion ELSE cn.direccion             END AS dir_cliente,
           u.nombre_completo AS cajero
    FROM ventas v
    LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = v.id_cliente AND v.tipo_cliente = 'natural'
    LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = v.id_cliente AND v.tipo_cliente = 'empresa'
    JOIN usuarios u ON v.id_usuario = u.id_usuario
    WHERE v.id_venta = ?
");
$stV->execute([$id_venta]);
$venta = $stV->fetch();
if (!$venta) { ob_end_clean(); die('Venta no encontrada.'); }

// Redirigir si no es nota
if ($venta['tipo_comprobante'] !== 'nota') {
    ob_end_clean();
    header('Location: comprobante_venta.php?id_venta=' . $id_venta);
    exit;
}

// ── Detalle de productos ──────────────────────────────────────────────────────
$stD = $pdo->prepare("
    SELECT dv.cantidad, dv.precio_unitario, dv.descuento, dv.subtotal,
           p.nombre_producto, p.codigo, p.marca
    FROM detalle_venta dv
    JOIN productos p ON dv.id_producto = p.id_producto
    WHERE dv.id_venta = ?
    ORDER BY dv.id_detalle
");
$stD->execute([$id_venta]);
$detalle = $stD->fetchAll();

// ── Clase PDF ─────────────────────────────────────────────────────────────────
class NotaVentaPDF extends FPDF
{
    public array  $empresa = [];
    public string $numero  = '';
    public string $fecha   = '';

    // Paleta azul marino profesional
    private array $cNavy  = [12,  26,  58];
    private array $cBlue  = [14, 165, 233];
    private array $cBlanco= [255,255,255];
    private array $cGris  = [226,232,240];
    private array $cTexto = [30,  41,  59];
    private array $cSuave = [100,116,139];

    public function Header(): void
    {
        $emp = $this->empresa;

        // Franja superior navy
        $this->SetFillColor(...$this->cNavy);
        $this->Rect(0, 0, 210, 28, 'F');

        // Logo
        $logo_path = $_SERVER['DOCUMENT_ROOT'] . ($emp['logo'] ?? '');
        if (!empty($emp['logo']) && file_exists($logo_path)) {
            $info = @getimagesize($logo_path);
            if ($info) {
                $ratio = $info[0] / $info[1];
                $h = 18; $w = min($h * $ratio, 32);
                $this->Image($logo_path, 10, 5, $w, $h);
            }
        }

        // Datos empresa (centro)
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 12);
        $this->SetXY(46, 5);
        $this->Cell(110, 7, pdf_txt($emp['razon_social'] ?? 'SysInversiones CH Computer'), 0, 1, 'C');
        $this->SetFont('Arial', '', 7.5);
        $this->SetX(46);
        $this->Cell(110, 4.5, pdf_txt('RUC: '.($emp['ruc'] ?? '').'   |   '.($emp['direccion'] ?? '')), 0, 1, 'C');
        if (!empty($emp['telefono']) || !empty($emp['email'])) {
            $this->SetX(46);
            $contacto = trim(
                (!empty($emp['telefono']) ? 'Tel: '.$emp['telefono'] : '') .
                (!empty($emp['email'])    ? '   |   '.$emp['email']  : '')
            );
            $this->Cell(110, 4.5, pdf_txt($contacto), 0, 1, 'C');
        }

        // Caja NOTA DE VENTA (derecha)
        $this->SetFillColor(...$this->cBlue);
        $this->Rect(158, 3, 42, 22, 'F');
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetXY(158, 6);
        $this->Cell(42, 6, 'NOTA DE VENTA', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 8);
        $this->SetX(158);
        $this->Cell(42, 5, pdf_txt($this->numero), 0, 1, 'C');
        $this->SetFont('Arial', '', 7);
        $this->SetX(158);
        $this->Cell(42, 4, pdf_txt($this->fecha), 0, 1, 'C');

        $this->SetY(32);
        $this->SetTextColor(...$this->cTexto);
    }

    public function Footer(): void
    {
        $this->SetY(-14);
        $this->SetDrawColor(...$this->cGris);
        $this->SetLineWidth(0.3);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(...$this->cSuave);
        $pie = $this->empresa['pie_comprobante'] ?? 'Gracias por su preferencia';
        $this->Cell(0, 4, pdf_txt($pie.'   |   SysInversiones CH Computer   |   Pag. '.$this->PageNo()), 0, 0, 'C');
    }

    public function seccion(string $titulo): void
    {
        $this->SetFillColor(...$this->cNavy);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 7.5);
        $this->Cell(0, 5.5, pdf_txt('  '.$titulo), 0, 1, 'L', true);
        $this->SetTextColor(...$this->cTexto);
        $this->Ln(1);
    }

    public function campo(string $lbl, string $val, float $wLbl = 30, float $wVal = 0): void
    {
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($wLbl, 5, pdf_txt($lbl.':'), 0, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $this->Cell($wVal > 0 ? $wVal : 0, 5, pdf_txt($val), 0, 1, 'L');
    }
}

// ── Instanciar ────────────────────────────────────────────────────────────────
$pdf = new NotaVentaPDF('P', 'mm', 'A4');
$pdf->empresa = $empresa;
$pdf->numero  = $venta['numero_comprobante'] ?? ('N001-'.str_pad($id_venta,8,'0',STR_PAD_LEFT));
$pdf->fecha   = date('d/m/Y H:i', strtotime($venta['fecha']));
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

// ── BLOQUE DATOS CLIENTE + VENTA ──────────────────────────────────────────────
$pdf->seccion('DATOS DEL CLIENTE');

// Dos columnas
$col1_x = 10;
$col2_x = 110;
$y_ini  = $pdf->GetY();

// Columna izquierda
$pdf->SetXY($col1_x, $y_ini);
$pdf->campo('Cliente',    trim($venta['nombre_cliente']), 28, 62);
$pdf->SetX($col1_x);
$pdf->campo(($venta['doc_tipo'] ?? 'Doc.'), $venta['doc_numero'] ?? '—', 28, 62);
if (!empty($venta['dir_cliente'])) {
    $pdf->SetX($col1_x);
    $pdf->campo('Direccion', $venta['dir_cliente'], 28, 62);
}
if (!empty($venta['tel_cliente'])) {
    $pdf->SetX($col1_x);
    $pdf->campo('Telefono', $venta['tel_cliente'], 28, 62);
}

// Columna derecha
$pdf->SetXY($col2_x, $y_ini);
$pdf->campo('Fecha',      date('d/m/Y H:i', strtotime($venta['fecha'])), 24, 66);
$pdf->SetX($col2_x);
$pdf->campo('Cajero',     $venta['cajero'], 24, 66);
$pdf->SetX($col2_x);
$metodo_txt = strtolower($venta['tipo_pago']) === 'credito' ? 'Credito' : ucfirst($venta['metodo_pago']);
$pdf->campo('Pago',       $metodo_txt, 24, 66);
if (!empty($venta['observacion'])) {
    $pdf->SetX($col2_x);
    $pdf->campo('Obs.', $venta['observacion'], 24, 66);
}

$pdf->Ln(6);

// ── TABLA DE PRODUCTOS ────────────────────────────────────────────────────────
$pdf->seccion('DETALLE DE PRODUCTOS');

$hay_desc = array_sum(array_column($detalle, 'descuento')) > 0;

// Anchos
$wN  = 8;
$wP  = $hay_desc ? 90 : 108;
$wC  = 14;
$wPU = 26;
$wD  = $hay_desc ? 20 : 0;
$wS  = $hay_desc ? 22 : 34;

// Cabecera
$pdf->SetFillColor(...$pdf->empresa ? [12,26,58] : [12,26,58]);
$pdf->SetFillColor(12, 26, 58);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell($wN,  6.5, '#',         1, 0, 'C', true);
$pdf->Cell($wP,  6.5, 'PRODUCTO',  1, 0, 'L', true);
$pdf->Cell($wC,  6.5, 'CANT.',     1, 0, 'C', true);
$pdf->Cell($wPU, 6.5, 'P. UNIT.',  1, 0, 'R', true);
if ($hay_desc) $pdf->Cell($wD, 6.5, 'DESC.', 1, 0, 'R', true);
$pdf->Cell($wS,  6.5, 'SUBTOTAL',  1, 1, 'R', true);
$pdf->SetTextColor(30, 41, 59);

// Filas
$pdf->SetFont('Arial', '', 8);
$n = 1;
foreach ($detalle as $d) {
    $alt = ($n % 2 === 0);
    $pdf->SetFillColor($alt ? 240 : 255, $alt ? 245 : 255, $alt ? 255 : 255);

    $nombre = $d['nombre_producto'];
    if (!empty($d['marca'])) $nombre .= ' ('.$d['marca'].')';

    $pdf->Cell($wN,  6, $n, 1, 0, 'C', $alt);
    $pdf->Cell($wP,  6, pdf_txt($nombre), 1, 0, 'L', $alt);
    $pdf->Cell($wC,  6, $d['cantidad'], 1, 0, 'C', $alt);
    $pdf->Cell($wPU, 6, 'S/. '.number_format($d['precio_unitario'],2), 1, 0, 'R', $alt);
    if ($hay_desc) {
        $dt = (float)$d['descuento'] > 0 ? 'S/. '.number_format($d['descuento'],2) : '-';
        $pdf->Cell($wD, 6, $dt, 1, 0, 'R', $alt);
    }
    $pdf->Cell($wS, 6, 'S/. '.number_format($d['subtotal'],2), 1, 1, 'R', $alt);
    $n++;
}

$pdf->Ln(4);

// ── TOTALES ───────────────────────────────────────────────────────────────────
$xT = 130; $wLb = 40; $wVl = 30;

$pdf->SetFont('Arial', '', 9);
$pdf->SetX($xT);
$pdf->Cell($wLb, 6, 'Subtotal:', 0, 0, 'R');
$pdf->Cell($wVl, 6, 'S/. '.number_format($venta['subtotal'],2), 0, 1, 'R');

if ((float)$venta['descuento'] > 0) {
    $pdf->SetX($xT);
    $pdf->SetTextColor(180, 0, 0);
    $pdf->Cell($wLb, 6, 'Descuento:', 0, 0, 'R');
    $pdf->Cell($wVl, 6, '- S/. '.number_format($venta['descuento'],2), 0, 1, 'R');
    $pdf->SetTextColor(30, 41, 59);
}

if ($venta['aplica_igv'] && (float)$venta['igv'] > 0) {
    $pdf->SetX($xT);
    $pdf->Cell($wLb, 6, 'IGV (18%):', 0, 0, 'R');
    $pdf->Cell($wVl, 6, 'S/. '.number_format($venta['igv'],2), 0, 1, 'R');
}

// Línea total
$pdf->SetDrawColor(12, 26, 58);
$pdf->SetLineWidth(0.6);
$pdf->Line($xT, $pdf->GetY()+1, 200, $pdf->GetY()+1);
$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(12, 26, 58);
$pdf->SetX($xT);
$pdf->Cell($wLb, 8, 'TOTAL:', 0, 0, 'R');
$pdf->Cell($wVl, 8, 'S/. '.number_format($venta['total'],2), 0, 1, 'R');
$pdf->SetTextColor(30, 41, 59);

if ((float)$venta['saldo_pendiente'] > 0) {
    $pdf->Ln(1);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(200, 100, 0);
    $pdf->SetX($xT);
    $pdf->Cell($wLb, 6, 'Saldo pendiente:', 0, 0, 'R');
    $pdf->Cell($wVl, 6, 'S/. '.number_format($venta['saldo_pendiente'],2), 0, 1, 'R');
    $pdf->SetTextColor(30, 41, 59);
}

// ── FIRMAS ────────────────────────────────────────────────────────────────────
$pdf->Ln(16);
$pdf->SetDrawColor(180, 180, 180);
$pdf->SetLineWidth(0.3);
$pdf->Line(20,  $pdf->GetY(), 90,  $pdf->GetY());
$pdf->Line(120, $pdf->GetY(), 190, $pdf->GetY());
$pdf->Ln(2);
$pdf->SetFont('Arial', '', 7.5);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(100, 4, pdf_txt('Firma y sello del receptor'), 0, 0, 'C');
$pdf->Cell(100, 4, pdf_txt('Firma y sello del emisor'),   0, 1, 'C');
$pdf->SetFont('Arial', 'I', 7);
$pdf->Cell(100, 4, pdf_txt('Nombre: ____________________________'), 0, 0, 'C');
$pdf->Cell(100, 4, pdf_txt('Nombre: ____________________________'), 0, 1, 'C');

// ── Salida ────────────────────────────────────────────────────────────────────
ob_end_clean();
$num_limpio = str_replace(['/', '\\', ' '], '_', $venta['numero_comprobante'] ?? 'N001');
$pdf->Output('I', 'nota_venta_'.$num_limpio.'.pdf');
