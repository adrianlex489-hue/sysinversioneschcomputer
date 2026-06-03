<?php
// ============================================================
// modules/Caja/detalle_caja_reporte_pdf.php
// PDF del desglose completo de una caja — FPDF A4 Portrait
// SysInversiones CH Computer 2026
// ============================================================
ob_start();
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';
require_once $ruta_base . 'libs/fpdf.php';
require_once __DIR__ . '/../configuracion_empresa/empresa_helper.php';
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);
if (!isset($pdo) || !($pdo instanceof PDO)) { ob_end_clean(); die('Sin BD'); }

if (!function_exists('pdf_txt')) {
    function pdf_txt(string $t): string {
        $b = ["\u{00E9}","\u{00F3}","\u{00FA}","\u{00ED}","\u{00E1}","\u{00F1}",
              "\u{00C1}","\u{00C9}","\u{00CD}","\u{00D3}","\u{00DA}","\u{00D1}",
              "\u{00FC}","\u{00DC}","\u{2014}","\u{2013}"];
        $r = [chr(233),chr(243),chr(250),chr(237),chr(225),chr(241),
              chr(193),chr(201),chr(205),chr(211),chr(218),chr(209),
              chr(252),chr(220),'--','-'];
        $t = str_replace($b, $r, $t);
        $x = iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE',$t);
        return $x !== false ? $x : $t;
    }
}

$id_usuario_sesion = (int)($_SESSION['id_usuario'] ?? 0);
$id_rol            = (int)($_SESSION['id_rol']     ?? 0);
$es_admin          = ($id_rol === (defined('ROL_ADMINISTRADOR') ? ROL_ADMINISTRADOR : 1));

$id_caja = (int)($_GET['id_caja'] ?? 0);
if (!$id_caja) { ob_end_clean(); header('Location: reporte_cajas.php'); exit; }

// ── Caja ──────────────────────────────────────────────────────────────────────
$caja = null;
try {
    $st = $pdo->prepare("SELECT c.*, u.nombre_completo AS cajero FROM caja c JOIN usuarios u ON u.id_usuario=c.id_usuario WHERE c.id_caja=?");
    $st->execute([$id_caja]); $caja = $st->fetch();
} catch (PDOException $e) {}
if (!$caja) { ob_end_clean(); die('Caja no encontrada'); }
if (!$es_admin && (int)$caja['id_usuario'] !== $id_usuario_sesion) { ob_end_clean(); die('Sin acceso'); }

// ── Movimientos ───────────────────────────────────────────────────────────────
$rows = [];
try {
    $stM = $pdo->prepare("
        SELECT m.tipo, m.tipo_referencia, m.descripcion, m.monto, m.metodo_pago,
               DATE_FORMAT(m.fecha,'%d/%m/%Y %H:%i:%s') AS fecha_fmt,
               u.nombre_completo AS cajero
        FROM movimientos_caja m
        LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
        WHERE m.id_caja = ?
        ORDER BY m.fecha ASC
    ");
    $stM->execute([$id_caja]);
    $rows = $stM->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$total_ing = array_sum(array_map(fn($r) => $r['tipo']==='ingreso'?(float)$r['monto']:0, $rows));
$total_eg  = array_sum(array_map(fn($r) => $r['tipo']==='egreso' ?(float)$r['monto']:0, $rows));
$neto      = $total_ing - $total_eg;
$diferencia = (float)($caja['diferencia'] ?? 0);

$dur_min = 0;
if ($caja['fecha_apertura']) {
    $fin = $caja['fecha_cierre'] ?? date('Y-m-d H:i:s');
    $dur_min = (int)round((strtotime($fin) - strtotime($caja['fecha_apertura'])) / 60);
}
$dur_txt = $dur_min >= 60 ? floor($dur_min/60).'h '.($dur_min%60).'m' : $dur_min.'m';

$empresa = getEmpresa($pdo);

class DetalleCajaPDF extends FPDF
{
    public array  $empresa    = [];
    public array  $caja_data  = [];
    public string $dur_txt    = '';
    public float  $total_ing  = 0;
    public float  $total_eg   = 0;
    public float  $neto       = 0;
    public float  $diferencia = 0;
    public int    $total_movs = 0;

    private array $cNavy   = [ 12,  26,  58];
    private array $cBlue   = [ 14, 165, 233];
    private array $cGreen  = [ 16, 185, 129];
    private array $cRed    = [239,  68,  68];
    private array $cBlanco = [255, 255, 255];
    private array $cGris   = [226, 232, 240];
    private array $cTexto  = [ 30,  41,  59];
    private array $cSuave  = [100, 116, 139];
    private array $cPale   = [224, 242, 254];

    public function Header(): void
    {
        $emp = $this->empresa;
        $this->SetFillColor(...$this->cNavy);
        $this->Rect(0, 0, 210, 22, 'F');

        $logo_path = $_SERVER['DOCUMENT_ROOT'] . ($emp['logo'] ?? '');
        if (!empty($emp['logo']) && file_exists($logo_path)) {
            $info = @getimagesize($logo_path);
            if ($info) {
                $ratio = $info[0] / $info[1];
                $h = 14; $w = min($h * $ratio, 28);
                $this->Image($logo_path, 6, 4, $w, $h);
            }
        }

        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 11);
        $this->SetXY(38, 4);
        $this->Cell(120, 7, pdf_txt($emp['razon_social'] ?? 'SysInversiones CH Computer'), 0, 1, 'L');
        $this->SetFont('Arial', '', 7);
        $this->SetXY(38, 11);
        $this->Cell(120, 5, pdf_txt('RUC: '.($emp['ruc']??'').'   |   '.($emp['direccion']??'')), 0, 0, 'L');

        $this->SetFillColor(...$this->cBlue);
        $this->Rect(163, 2, 42, 18, 'F');
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetXY(163, 5);
        $this->Cell(42, 5, 'DETALLE', 0, 1, 'C');
        $this->SetFont('Arial', '', 6.5);
        $this->SetXY(163, 10);
        $this->Cell(42, 5, 'DE CAJA', 0, 0, 'C');

        // Barra info caja
        $this->SetFillColor(15, 52, 96);
        $this->Rect(0, 22, 210, 10, 'F');
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 8);
        $this->SetXY(6, 23);
        $nombre = $this->caja_data['nombre'] ?? 'Caja #'.$this->caja_data['id_caja'];
        $this->Cell(100, 8, pdf_txt($nombre.' — Cajero: '.$this->caja_data['cajero']), 0, 0, 'L');
        $this->SetFont('Arial', '', 7);
        $this->SetXY(106, 23);
        $apertura = date('d/m/Y H:i:s', strtotime($this->caja_data['fecha_apertura']));
        $cierre   = $this->caja_data['fecha_cierre'] ? date('d/m/Y H:i:s', strtotime($this->caja_data['fecha_cierre'])) : 'En curso';
        $this->Cell(98, 8, pdf_txt('Apertura: '.$apertura.'  |  Cierre: '.$cierre.'  |  Dur: '.$this->dur_txt), 0, 0, 'R');

        $this->SetY(35);
        $this->SetTextColor(...$this->cTexto);
        $this->Ln(2);
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetDrawColor(...$this->cGris);
        $this->SetLineWidth(0.3);
        $this->Line(6, $this->GetY(), 204, $this->GetY());
        $this->Ln(1);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(...$this->cSuave);
        $this->Cell(0, 4, pdf_txt('SysInversiones CH Computer  |  Detalle de Caja  |  Pagina '.$this->PageNo().'  |  Generado el '.date('d/m/Y H:i:s')), 0, 0, 'C');
    }

    public function bloqueResumen(): void
    {
        $this->SetFillColor(248, 250, 252);
        $this->SetDrawColor(...$this->cGris);
        $this->SetLineWidth(0.2);
        $this->Rect(6, $this->GetY(), 198, 18, 'DF');

        $y = $this->GetY() + 3;
        $cols = [
            ['Fondo inicial', 'S/. '.number_format($this->caja_data['monto_inicial'],2), '#0369a1'],
            ['Total ingresos', '+S/. '.number_format($this->total_ing,2), '#059669'],
            ['Total egresos', '-S/. '.number_format($this->total_eg,2), '#dc2626'],
            ['Neto', ($this->neto>=0?'+':'').number_format($this->neto,2), $this->neto>=0?'#059669':'#dc2626'],
            ['Movimientos', (string)$this->total_movs, '#0369a1'],
            ['Estado', ucfirst($this->caja_data['estado']), $this->caja_data['estado']==='abierta'?'#059669':'#475569'],
        ];

        $w = 33;
        $x = 6;
        foreach ($cols as [$lbl, $val, $color]) {
            $this->SetFont('Arial', '', 6.5);
            $this->SetTextColor(...$this->cSuave);
            $this->SetXY($x, $y);
            $this->Cell($w, 4, pdf_txt($lbl), 0, 1, 'C');
            $this->SetFont('Arial', 'B', 8);
            // parse color hex
            $r = hexdec(substr(ltrim($color,'#'),0,2));
            $g = hexdec(substr(ltrim($color,'#'),2,2));
            $b = hexdec(substr(ltrim($color,'#'),4,2));
            $this->SetTextColor($r,$g,$b);
            $this->SetXY($x, $y+4);
            $this->Cell($w, 5, pdf_txt($val), 0, 0, 'C');
            $x += $w;
        }
        $this->SetTextColor(...$this->cTexto);
        $this->SetY($this->GetY() + 22);
    }

    private function getCols(): array {
        // Total: 8+28+14+14+50+18+22+26 = 180 mm (margen 6+6=12, total 192)
        return [
            ['#',          8,  'C'],
            ['Fecha/Hora', 28, 'C'],
            ['Tipo',       14, 'C'],
            ['Origen',     14, 'C'],
            ['Descripción',50, 'L'],
            ['Método',     18, 'C'],
            ['Monto',      22, 'R'],
            ['Cajero',     26, 'L'],
        ];
    }

    public function tablaCabecera(): void
    {
        $this->SetFillColor(...$this->cNavy);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 7);
        foreach ($this->getCols() as [$lbl, $w, $al])
            $this->Cell($w, 6, pdf_txt($lbl), 0, 0, $al, true);
        $this->Ln();
        $this->SetTextColor(...$this->cTexto);
    }

    public function tablaFila(array $r, int $n): void
    {
        $cols   = $this->getCols();
        $esAlt  = ($n % 2 === 0);
        $fillBg = $esAlt ? $this->cPale : $this->cBlanco;
        $es_ing = ($r['tipo'] === 'ingreso');

        $valores = [
            (string)$n,
            $r['fecha_fmt'],
            ucfirst($r['tipo']),
            ucfirst($r['tipo_referencia']),
            mb_substr($r['descripcion'] ?? '', 0, 40),
            ucfirst($r['metodo_pago']),
            ($es_ing?'+':'-').'S/. '.number_format($r['monto'],2),
            mb_substr($r['cajero'] ?? '', 0, 20),
        ];

        $this->SetFillColor(...$fillBg);
        $this->SetFont('Arial', '', 6.5);
        $this->SetLineWidth(0.1);
        $this->SetDrawColor(...$this->cGris);

        foreach ($cols as $i => [$lbl, $w, $al]) {
            if ($lbl === 'Monto') {
                $this->SetTextColor(...($es_ing ? $this->cGreen : $this->cRed));
            } elseif ($lbl === 'Tipo') {
                $this->SetTextColor(...($es_ing ? $this->cGreen : $this->cRed));
            }
            $this->Cell($w, 5.5, pdf_txt((string)$valores[$i]), 'B', 0, $al, true);
            $this->SetTextColor(...$this->cTexto);
        }
        $this->Ln();
    }

    public function filaTotales(): void
    {
        $this->Ln(2);
        $this->SetFillColor(...$this->cNavy);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 7.5);
        $dif = $this->diferencia;
        $dif_txt = abs($dif)<0.01 ? 'Cuadre exacto' : (($dif>0?'Sobrante +':'Faltante -').'S/. '.number_format(abs($dif),2));
        $txt = '  TOTAL: '.$this->total_movs.' movimientos   |   Ingresos: S/. '.number_format($this->total_ing,2).'   |   Egresos: S/. '.number_format($this->total_eg,2).'   |   Neto: S/. '.number_format($this->neto,2).'   |   '.$dif_txt;
        $this->Cell(0, 7, pdf_txt($txt), 0, 1, 'L', true);
        $this->SetTextColor(...$this->cTexto);
    }
}

$pdf = new DetalleCajaPDF('P', 'mm', 'A4');
$pdf->empresa    = $empresa;
$pdf->caja_data  = $caja;
$pdf->dur_txt    = $dur_txt;
$pdf->total_ing  = $total_ing;
$pdf->total_eg   = $total_eg;
$pdf->neto       = $neto;
$pdf->diferencia = $diferencia;
$pdf->total_movs = count($rows);
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 14);
$pdf->SetTitle('Detalle Caja #'.$id_caja.' - SysInversiones CH Computer');
$pdf->SetAuthor('SysInversiones CH Computer');
$pdf->AddPage();

// Bloque resumen
$pdf->bloqueResumen();
$pdf->Ln(2);

if (empty($rows)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 20, pdf_txt('No hay movimientos registrados en esta caja.'), 0, 1, 'C');
} else {
    $pdf->tablaCabecera();
    $n = 1;
    foreach ($rows as $r) {
        if ($pdf->GetY() > 270) { $pdf->AddPage(); $pdf->tablaCabecera(); }
        $pdf->tablaFila($r, $n++);
    }
    $pdf->filaTotales();
}

ob_end_clean();
$pdf->Output('D', 'detalle_caja_'.$id_caja.'_'.date('Ymd_His').'.pdf');
