<?php
// ============================================================
// modules/Caja/reporte_cajas_pdf.php
// PDF profesional consolidado — por cada caja: cabecera + movimientos
// A4 Landscape con FPDF | SysInversiones CH Computer 2026
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
              "\u{00FC}","\u{00DC}","\u{2014}","\u{2013}","\u{00B7}"];
        $r = [chr(233),chr(243),chr(250),chr(237),chr(225),chr(241),
              chr(193),chr(201),chr(205),chr(211),chr(218),chr(209),
              chr(252),chr(220),'--','-','.'];
        $t = str_replace($b, $r, $t);
        $x = iconv('UTF-8','ISO-8859-1//TRANSLIT//IGNORE',$t);
        return $x !== false ? $x : $t;
    }
}

$id_usuario_sesion = (int)($_SESSION['id_usuario'] ?? 0);
$id_rol            = (int)($_SESSION['id_rol']     ?? 0);
$es_admin          = ($id_rol === (defined('ROL_ADMINISTRADOR') ? ROL_ADMINISTRADOR : 1));

$f_desde   = $_GET['desde']       ?? date('Y-m-01');
$f_hasta   = $_GET['hasta']       ?? date('Y-m-d');
$f_estado  = $_GET['estado']      ?? 'all';
$f_usuario = (int)($_GET['id_usuario'] ?? 0);

$wheres = ["DATE(c.fecha_apertura) BETWEEN ? AND ?"];
$params = [$f_desde, $f_hasta];
if ($f_estado !== 'all') { $wheres[] = 'c.estado = ?'; $params[] = $f_estado; }
if (!$es_admin)          { $wheres[] = 'c.id_usuario = ?'; $params[] = $id_usuario_sesion; }
elseif ($f_usuario > 0)  { $wheres[] = 'c.id_usuario = ?'; $params[] = $f_usuario; }
$where_sql = 'WHERE ' . implode(' AND ', $wheres);

// ── Cajas ─────────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT c.id_caja, c.nombre AS nombre_caja, u.nombre_completo AS cajero,
           DATE_FORMAT(c.fecha_apertura,'%d/%m/%Y %H:%i:%s') AS apertura,
           IF(c.fecha_cierre IS NOT NULL,
              DATE_FORMAT(c.fecha_cierre,'%d/%m/%Y %H:%i:%s'), NULL) AS cierre,
           COALESCE(TIMESTAMPDIFF(MINUTE,
               c.fecha_apertura, IFNULL(c.fecha_cierre,NOW())),0) AS duracion_min,
           c.monto_inicial,
           COALESCE(c.monto_final,0)    AS monto_final,
           COALESCE(c.monto_esperado,0) AS monto_esperado,
           COALESCE(c.diferencia,0)     AS diferencia,
           c.estado,
           COALESCE(c.observacion,'')   AS observacion
    FROM caja c
    JOIN usuarios u ON u.id_usuario = c.id_usuario
    $where_sql
    ORDER BY c.fecha_apertura ASC");
$stmt->execute($params);
$cajas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Movimientos por caja ──────────────────────────────────────────────────────
$stmt_movs = $pdo->prepare("
    SELECT m.tipo, m.tipo_referencia, m.descripcion, m.monto, m.metodo_pago,
           DATE_FORMAT(m.fecha,'%d/%m/%Y %H:%i:%s') AS fecha_fmt,
           COALESCE(m.observacion,'') AS observacion,
           u.nombre_completo AS cajero_mov
    FROM movimientos_caja m
    LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
    WHERE m.id_caja = ?
    ORDER BY m.fecha ASC");

$movimientos = [];
foreach ($cajas as $caja) {
    $stmt_movs->execute([$caja['id_caja']]);
    $movimientos[$caja['id_caja']] = $stmt_movs->fetchAll(PDO::FETCH_ASSOC);
}

// ── Totales globales ──────────────────────────────────────────────────────────
$g_ingresos = 0; $g_egresos = 0; $g_movs = 0;
foreach ($cajas as $caja) {
    $movs = $movimientos[$caja['id_caja']] ?? [];
    foreach ($movs as $m) {
        if ($m['tipo']==='ingreso') $g_ingresos += (float)$m['monto'];
        else                        $g_egresos  += (float)$m['monto'];
    }
    $g_movs += count($movs);
}
$g_neto        = $g_ingresos - $g_egresos;
$cerradas_cnt  = count(array_filter($cajas, fn($c) => $c['estado'] === 'cerrada'));
$con_dif_cnt   = count(array_filter($cajas, fn($c) => abs((float)$c['diferencia']) > 0.01));
$label_periodo = date('d/m/Y', strtotime($f_desde)) . ' al ' . date('d/m/Y', strtotime($f_hasta));

$dur_fmt = function(int $min): string {
    return $min >= 60 ? floor($min/60).'h '.($min%60).'m' : $min.'m';
};

$empresa = getEmpresa($pdo);

// ── Clase PDF ─────────────────────────────────────────────────────────────────
class ReporteCajasPDF extends FPDF
{
    public array  $empresa       = [];
    public string $label_periodo = '';
    public int    $total_cajas   = 0;
    public int    $cerradas_cnt  = 0;
    public int    $con_dif_cnt   = 0;
    public float  $g_ingresos    = 0;
    public float  $g_egresos     = 0;
    public float  $g_neto        = 0;
    public int    $g_movs        = 0;

    // Paleta
    private array $cNavy   = [ 12,  26,  58];
    private array $cBlue   = [ 14, 165, 233];
    private array $cMidBlue= [ 30,  64, 175];
    private array $cGreen  = [ 16, 185, 129];
    private array $cRed    = [239,  68,  68];
    private array $cBlanco = [255, 255, 255];
    private array $cGris   = [226, 232, 240];
    private array $cTexto  = [ 30,  41,  59];
    private array $cSuave  = [100, 116, 139];
    private array $cPale   = [224, 242, 254];
    private array $cSlate  = [ 51,  65,  85];

    public function Header(): void
    {
        $emp = $this->empresa;
        $this->SetFillColor(...$this->cNavy);
        $this->Rect(0, 0, 297, 22, 'F');

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
        $this->SetFont('Arial', 'B', 12);
        $this->SetXY(38, 4);
        $this->Cell(160, 7, pdf_txt($emp['razon_social'] ?? 'SysInversiones CH Computer'), 0, 1, 'L');
        $this->SetFont('Arial', '', 7.5);
        $this->SetXY(38, 11);
        $this->Cell(160, 5, pdf_txt('RUC: '.($emp['ruc']??'').'   |   '.($emp['direccion']??'')), 0, 0, 'L');

        $this->SetFillColor(...$this->cBlue);
        $this->Rect(249, 2, 42, 18, 'F');
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetXY(249, 5);
        $this->Cell(42, 5, 'REPORTE', 0, 1, 'C');
        $this->SetFont('Arial', '', 6.5);
        $this->SetXY(249, 10);
        $this->Cell(42, 5, 'CONSOLIDADO', 0, 1, 'C');
        $this->SetXY(249, 15);
        $this->Cell(42, 4, 'DE CAJAS', 0, 0, 'C');

        // Barra de período
        $this->SetFillColor(15, 52, 96);
        $this->Rect(0, 22, 297, 10, 'F');
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetXY(6, 23);
        $this->Cell(130, 8, pdf_txt('Reporte Consolidado de Cajas — Periodo: '.$this->label_periodo), 0, 0, 'L');
        $this->SetFont('Arial', '', 7.5);
        $this->SetXY(136, 23);
        $this->Cell(155, 8, pdf_txt(
            'Cajas: '.$this->total_cajas.'  |  Cerradas: '.$this->cerradas_cnt.
            '  |  Con diferencia: '.$this->con_dif_cnt.
            '  |  Ingresos: S/. '.number_format($this->g_ingresos,2).
            '  |  Egresos: S/. '.number_format($this->g_egresos,2).
            '  |  Neto: S/. '.number_format($this->g_neto,2)
        ), 0, 0, 'R');

        $this->SetY(35);
        $this->SetTextColor(...$this->cTexto);
        $this->Ln(2);
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetDrawColor(...$this->cGris);
        $this->SetLineWidth(0.3);
        $this->Line(6, $this->GetY(), 291, $this->GetY());
        $this->Ln(1);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(...$this->cSuave);
        $this->Cell(0, 4, pdf_txt(
            'SysInversiones CH Computer  |  Reporte Consolidado de Cajas  |  '.
            'Pagina '.$this->PageNo().'  |  Generado el '.date('d/m/Y H:i:s')
        ), 0, 0, 'C');
    }

    // ── Cabecera de una caja ──────────────────────────────────────────────────
    public function cabeceraCaja(array $c, float $c_ing, float $c_eg, string $dur): void
    {
        $c_neto = $c_ing - $c_eg;
        $dif    = (float)$c['diferencia'];
        $dif_txt = abs($dif)<0.01 ? 'Exacto' : (($dif>0?'Sobrante +':'Faltante -').'S/. '.number_format(abs($dif),2));

        // Barra título caja
        $this->SetFillColor(...$this->cMidBlue);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetX(6);
        $this->Cell(0, 7,
            pdf_txt('CAJA #'.$c['id_caja'].' — '.$c['nombre_caja'].
                    '   |   Cajero: '.$c['cajero'].
                    '   |   Estado: '.strtoupper($c['estado']).
                    '   |   Duración: '.$dur),
            0, 1, 'L', true);

        // Fila datos apertura/cierre
        $this->SetFillColor(...$this->cPale);
        $this->SetTextColor(...$this->cTexto);
        $this->SetFont('Arial', '', 7.5);
        $this->SetX(6);
        $this->Cell(0, 5.5,
            pdf_txt('Apertura: '.$c['apertura'].
                    '   |   Cierre: '.($c['cierre'] ?? 'En curso').
                    '   |   Fondo inicial: S/. '.number_format($c['monto_inicial'],2).
                    '   |   Monto esperado: S/. '.number_format($c['monto_esperado'],2).
                    '   |   Monto contado: S/. '.number_format($c['monto_final'],2).
                    '   |   Diferencia: '.$dif_txt),
            0, 1, 'L', true);

        // Fila totales de la caja
        $this->SetFillColor(220, 252, 231); // verde muy claro
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetX(6);
        $this->SetTextColor(5, 150, 105);
        $this->Cell(60, 5.5, pdf_txt('Ingresos: +S/. '.number_format($c_ing,2)), 0, 0, 'L', true);
        $this->SetTextColor(220, 38, 38);
        $this->Cell(60, 5.5, pdf_txt('Egresos: -S/. '.number_format($c_eg,2)), 0, 0, 'L', true);
        $this->SetTextColor(...($c_neto>=0?[5,150,105]:[220,38,38]));
        $this->Cell(60, 5.5, pdf_txt('Neto: '.($c_neto>=0?'+':'').number_format($c_neto,2)), 0, 0, 'L', true);
        $this->SetTextColor(...$this->cTexto);
        $this->Cell(0, 5.5, '', 0, 1, 'L', true);

        if ($c['observacion']) {
            $this->SetFillColor(255, 251, 235);
            $this->SetFont('Arial', 'I', 7);
            $this->SetTextColor(120, 53, 15);
            $this->SetX(6);
            $this->Cell(0, 5, pdf_txt('Obs: '.$c['observacion']), 0, 1, 'L', true);
            $this->SetTextColor(...$this->cTexto);
        }
    }

    // ── Cabecera columnas movimientos ─────────────────────────────────────────
    private function getCols(): array {
        // Total: 8+28+12+14+70+18+22+26+47 = 245 mm (margen 6+6=12 → 285-12=273 disponible)
        return [
            ['#',          8,  'C'],
            ['Fecha/Hora', 28, 'C'],
            ['Tipo',       12, 'C'],
            ['Origen',     14, 'C'],
            ['Descripción',70, 'L'],
            ['Método',     18, 'C'],
            ['Monto',      22, 'R'],
            ['Cajero',     26, 'L'],
            ['Observación',47, 'L'],
        ];
    }

    public function cabMovimientos(): void
    {
        $this->SetFillColor(...$this->cNavy);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 6.5);
        $this->SetX(6);
        foreach ($this->getCols() as [$lbl, $w, $al])
            $this->Cell($w, 6, pdf_txt($lbl), 0, 0, $al, true);
        $this->Ln();
        $this->SetTextColor(...$this->cTexto);
    }

    public function filaMovimiento(array $m, int $n): void
    {
        $cols   = $this->getCols();
        $esAlt  = ($n % 2 === 0);
        $es_ing = ($m['tipo'] === 'ingreso');
        $fillBg = $esAlt ? $this->cPale : $this->cBlanco;
        $signo  = $es_ing ? '+' : '-';

        $valores = [
            (string)$n,
            $m['fecha_fmt'],
            ucfirst($m['tipo']),
            ucfirst($m['tipo_referencia']),
            mb_substr($m['descripcion'] ?? '', 0, 55),
            ucfirst($m['metodo_pago']),
            $signo.'S/. '.number_format($m['monto'],2),
            mb_substr($m['cajero_mov'] ?? '', 0, 22),
            mb_substr($m['observacion'] ?? '', 0, 40),
        ];

        $this->SetFillColor(...$fillBg);
        $this->SetFont('Arial', '', 6.5);
        $this->SetLineWidth(0.1);
        $this->SetDrawColor(...$this->cGris);
        $this->SetX(6);

        foreach ($cols as $i => [$lbl, $w, $al]) {
            if ($lbl === 'Tipo' || $lbl === 'Monto') {
                $this->SetTextColor(...($es_ing ? $this->cGreen : $this->cRed));
                $this->SetFont('Arial', 'B', 6.5);
            }
            $this->Cell($w, 5.5, pdf_txt((string)$valores[$i]), 'B', 0, $al, true);
            $this->SetTextColor(...$this->cTexto);
            $this->SetFont('Arial', '', 6.5);
        }
        $this->Ln();
    }

    public function subtotalCaja(int $id_caja, string $nombre, int $cnt, float $c_ing, float $c_eg): void
    {
        $c_neto = $c_ing - $c_eg;
        $this->SetFillColor(...$this->cSlate);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 7);
        $this->SetX(6);
        $this->Cell(0, 6,
            pdf_txt('  Subtotal Caja #'.$id_caja.' — '.$nombre.
                    '   ('.$cnt.' movimientos)'.
                    '   |   Ingresos: +S/. '.number_format($c_ing,2).
                    '   |   Egresos: -S/. '.number_format($c_eg,2).
                    '   |   Neto: '.($c_neto>=0?'+':'').number_format($c_neto,2)),
            0, 1, 'L', true);
        $this->SetTextColor(...$this->cTexto);
        $this->Ln(2);
    }

    public function totalGeneral(): void
    {
        $this->Ln(3);
        $this->SetFillColor(...$this->cNavy);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetX(6);
        $this->Cell(0, 8,
            pdf_txt('  TOTALES GENERALES — Periodo: '.$this->label_periodo.
                    '   |   Cajas: '.$this->total_cajas.
                    '   |   Cerradas: '.$this->cerradas_cnt.
                    '   |   Con diferencia: '.$this->con_dif_cnt.
                    '   |   Total movimientos: '.$this->g_movs),
            0, 1, 'L', true);
        $this->SetX(6);
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(15, 52, 96);
        $this->SetTextColor(16, 185, 129);
        $this->Cell(80, 7, pdf_txt('  Ingresos: +S/. '.number_format($this->g_ingresos,2)), 0, 0, 'L', true);
        $this->SetTextColor(239, 68, 68);
        $this->Cell(80, 7, pdf_txt('  Egresos: -S/. '.number_format($this->g_egresos,2)), 0, 0, 'L', true);
        $this->SetTextColor(...($this->g_neto>=0?[16,185,129]:[239,68,68]));
        $this->Cell(0, 7, pdf_txt('  Neto: '.($this->g_neto>=0?'+':'').number_format($this->g_neto,2)), 0, 1, 'L', true);
        $this->SetTextColor(...$this->cTexto);
    }
}

// ── Generar PDF ───────────────────────────────────────────────────────────────
$pdf = new ReporteCajasPDF('L', 'mm', 'A4');
$pdf->empresa       = $empresa;
$pdf->label_periodo = $label_periodo;
$pdf->total_cajas   = count($cajas);
$pdf->cerradas_cnt  = $cerradas_cnt;
$pdf->con_dif_cnt   = $con_dif_cnt;
$pdf->g_ingresos    = $g_ingresos;
$pdf->g_egresos     = $g_egresos;
$pdf->g_neto        = $g_neto;
$pdf->g_movs        = $g_movs;
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 14);
$pdf->SetTitle('Reporte Consolidado de Cajas - SysInversiones CH Computer');
$pdf->SetAuthor('SysInversiones CH Computer');
$pdf->AddPage();

if (empty($cajas)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 20, pdf_txt('No se encontraron cajas en el período seleccionado.'), 0, 1, 'C');
} else {
    foreach ($cajas as $caja) {
        $movs   = $movimientos[$caja['id_caja']] ?? [];
        $c_ing  = array_sum(array_map(fn($m) => $m['tipo']==='ingreso'?(float)$m['monto']:0, $movs));
        $c_eg   = array_sum(array_map(fn($m) => $m['tipo']==='egreso' ?(float)$m['monto']:0, $movs));
        $dur    = $dur_fmt((int)$caja['duracion_min']);

        // Si la cabecera de caja no cabe, nueva página
        if ($pdf->GetY() > 170) $pdf->AddPage();

        $pdf->cabeceraCaja($caja, $c_ing, $c_eg, $dur);

        if (empty($movs)) {
            $pdf->SetFont('Arial', 'I', 7);
            $pdf->SetTextColor(150, 150, 150);
            $pdf->SetX(6);
            $pdf->Cell(0, 5, pdf_txt('  (Sin movimientos registrados en esta caja)'), 0, 1, 'L');
            $pdf->SetTextColor(30, 41, 59);
            $pdf->Ln(2);
        } else {
            $pdf->cabMovimientos();
            $n = 1;
            foreach ($movs as $m) {
                if ($pdf->GetY() > 188) {
                    $pdf->AddPage();
                    $pdf->cabMovimientos();
                }
                $pdf->filaMovimiento($m, $n++);
            }
            $pdf->subtotalCaja($caja['id_caja'], $caja['nombre_caja'], count($movs), $c_ing, $c_eg);
        }
    }

    // Totales finales — si no caben, nueva página
    if ($pdf->GetY() > 175) $pdf->AddPage();
    $pdf->totalGeneral();
}

ob_end_clean();
$pdf->Output('D', 'reporte_consolidado_cajas_'.date('Ymd_His').'.pdf');
