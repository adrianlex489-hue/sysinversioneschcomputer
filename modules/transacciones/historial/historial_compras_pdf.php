<?php
// ============================================================
// modules/transacciones/historial/historial_compras_pdf.php
// Genera PDF A4 Landscape del Historial de Compras con FPDF
// SysInversiones CH Computer 2026
// ============================================================
ob_start();

$ruta_base = '../../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';
require_once $ruta_base . 'libs/fpdf.php';
require_once __DIR__ . '/../../configuracion_empresa/empresa_helper.php';

verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);

if (!isset($pdo) || !($pdo instanceof PDO)) {
    ob_end_clean(); die('Error: Sin conexión a la base de datos.');
}

// ── UTF-8 → ISO-8859-1 ───────────────────────────────────────────────────────
if (!function_exists('pdf_txt')) {
    function pdf_txt(string $t): string {
        $buscar     = ["\u{2014}","\u{2013}","\u{2012}","\u{00B0}","\u{00BA}",
                       "\u{00AA}","\u{00E9}","\u{00F3}","\u{00FA}","\u{00ED}",
                       "\u{00E1}","\u{00F1}","\u{00C1}","\u{00C9}","\u{00CD}",
                       "\u{00D3}","\u{00DA}","\u{00D1}","\u{00FC}","\u{00DC}"];
        $reemplazar = ['--','-','-',chr(176),chr(186),
                       chr(170),chr(233),chr(243),chr(250),chr(237),
                       chr(225),chr(241),chr(193),chr(201),chr(205),
                       chr(211),chr(218),chr(209),chr(252),chr(220)];
        $t = str_replace($buscar, $reemplazar, $t);
        $r = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $t);
        return $r !== false ? $r : $t;
    }
}

// ── Parámetros ────────────────────────────────────────────────────────────────
$filtro_est  = $_GET['estado']      ?? 'all';
$filtro_pago = $_GET['tipo_pago']   ?? 'all';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

$wheres = []; $params = [];
if ($filtro_est  !== 'all') { $wheres[] = 'c.estado = ?';        $params[] = $filtro_est; }
if ($filtro_pago !== 'all') { $wheres[] = 'c.tipo_pago = ?';     $params[] = $filtro_pago; }
if ($fecha_desde !== '')    { $wheres[] = 'DATE(c.fecha) >= ?';  $params[] = $fecha_desde; }
if ($fecha_hasta !== '')    { $wheres[] = 'DATE(c.fecha) <= ?';  $params[] = $fecha_hasta; }
$where_sql = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$label_estado = match($filtro_est) {
    'pagado'   => 'Pagadas',
    'pendiente'=> 'Pendientes',
    'anulado'  => 'Anuladas',
    default    => 'Todas',
};

// ── Consulta ──────────────────────────────────────────────────────────────────
$sql = "SELECT
    c.id_compra,
    DATE_FORMAT(c.fecha,'%d/%m/%Y %H:%i') AS fecha,
    p.razon_social AS proveedor,
    c.tipo_comprobante,
    COALESCE(c.numero_comprobante,'---') AS numero_comprobante,
    c.tipo_pago,
    c.metodo_pago,
    c.total,
    COALESCE(c.saldo_pendiente,0) AS saldo_pendiente,
    c.estado,
    u.nombre_completo AS registrado_por
FROM compras c
JOIN proveedores p ON c.id_proveedor = p.id_proveedor
JOIN usuarios   u ON c.id_usuario   = u.id_usuario
$where_sql
ORDER BY c.fecha DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tipoLabel = ['ticket' => 'Ticket', 'nota' => 'Nota'];

$total_monto   = array_sum(array_column(array_filter($rows, fn($r) => $r['estado'] !== 'anulado'), 'total'));
$total_pagadas = count(array_filter($rows, fn($r) => $r['estado'] === 'pagado'));
$total_pend    = count(array_filter($rows, fn($r) => $r['estado'] === 'pendiente'));
$total_anul    = count(array_filter($rows, fn($r) => $r['estado'] === 'anulado'));

$empresa = getEmpresa($pdo);

// ── Clase PDF ─────────────────────────────────────────────────────────────────
class HistorialComprasPDF extends FPDF
{
    public array  $empresa      = [];
    public int    $total_reg    = 0;
    public float  $total_monto  = 0;
    public int    $total_pag    = 0;
    public int    $total_pend   = 0;
    public int    $total_anul   = 0;
    public string $label_estado = 'Todas';

    private array $cAzul       = [ 26,  82, 118];
    private array $cAzulMedio  = [ 41, 128, 185];
    private array $cVerde      = [ 26, 122,  74];
    private array $cBlanco     = [255, 255, 255];
    private array $cGrisBorde  = [226, 232, 240];
    private array $cTexto      = [ 30,  41,  59];
    private array $cTextoSuave = [100, 116, 139];
    private array $cAzulPale   = [227, 242, 253];

    public function Header(): void
    {
        $emp = $this->empresa;

        $this->SetFillColor(...$this->cAzul);
        $this->Rect(0, 0, 297, 22, 'F');

        $logo_path = $_SERVER['DOCUMENT_ROOT'] . ($emp['logo'] ?? '');
        if (!empty($emp['logo']) && file_exists($logo_path)) {
            $info = @getimagesize($logo_path);
            if ($info) {
                $ratio  = $info[0] / $info[1];
                $draw_h = 14; $draw_w = min($draw_h * $ratio, 28);
                $this->Image($logo_path, 6, 4, $draw_w, $draw_h);
            }
        }

        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 12);
        $this->SetXY(38, 4);
        $this->Cell(160, 7, pdf_txt($emp['razon_social'] ?? 'SysInversiones CH Computer'), 0, 1, 'L');
        $this->SetFont('Arial', '', 7.5);
        $this->SetXY(38, 11);
        $info_emp = 'RUC: ' . ($emp['ruc'] ?? '') . '   |   ' . ($emp['direccion'] ?? '') . ($emp['distrito'] ? ' - ' . $emp['distrito'] : '');
        $this->Cell(160, 5, pdf_txt($info_emp), 0, 0, 'L');

        // Etiqueta derecha
        $this->SetFillColor(...$this->cVerde);
        $this->Rect(249, 2, 42, 18, 'F');
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY(249, 5);
        $this->Cell(42, 6, 'COMPRAS', 0, 1, 'C');
        $this->SetFont('Arial', '', 7);
        $this->SetXY(249, 11);
        $this->Cell(42, 5, 'HISTORIAL', 0, 0, 'C');

        // Banda subtítulo
        $this->SetFillColor(...$this->cAzulMedio);
        $this->Rect(0, 22, 297, 10, 'F');
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetXY(6, 23);
        $this->Cell(160, 8, pdf_txt('Historial de Compras — ' . date('d/m/Y H:i') . '   |   Estado: ' . $this->label_estado), 0, 0, 'L');
        $this->SetFont('Arial', '', 7.5);
        $this->SetXY(166, 23);
        $this->Cell(125, 8, pdf_txt('Total: ' . $this->total_reg . '  |  Pagadas: ' . $this->total_pag . '  |  Pendientes: ' . $this->total_pend . '  |  Anuladas: ' . $this->total_anul . '  |  Monto: S/. ' . number_format($this->total_monto, 2)), 0, 0, 'R');

        $this->SetY(35);
        $this->SetTextColor(...$this->cTexto);
        $this->Ln(2);
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetDrawColor(...$this->cGrisBorde);
        $this->SetLineWidth(0.3);
        $this->Line(6, $this->GetY(), 291, $this->GetY());
        $this->Ln(1);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(...$this->cTextoSuave);
        $this->Cell(0, 4, pdf_txt('SysInversiones CH Computer  |  Historial de Compras  |  Pagina ' . $this->PageNo() . '  |  Generado el ' . date('d/m/Y H:i:s')), 0, 0, 'C');
    }

    // Anchos: #, Fecha, Proveedor, Comprobante, N°, T.Pago, Método, Total, Saldo, Estado, Registrado por
    // Total: 10+28+52+18+26+16+16+22+22+18+34 = 262 mm
    private function getCols(): array
    {
        return [
            ['#',            10, 'C'],
            ['Fecha',        28, 'C'],
            ['Proveedor',    52, 'L'],
            ['Comprobante',  18, 'C'],
            ['N° Comp.',     26, 'C'],
            ['T. Pago',      16, 'C'],
            ['Metodo',       16, 'C'],
            ['Total',        22, 'R'],
            ['Saldo',        22, 'R'],
            ['Estado',       18, 'C'],
            ['Registrado por', 34, 'L'],
        ];
    }

    public function tablaCabecera(): void
    {
        $this->SetFillColor(...$this->cAzul);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetLineWidth(0);
        foreach ($this->getCols() as [$label, $w, $align]) {
            $this->Cell($w, 7, pdf_txt($label), 0, 0, $align, true);
        }
        $this->Ln();
        $this->SetTextColor(...$this->cTexto);
    }

    public function tablaFila(array $r, int $n, array $tipoLabel): void
    {
        $cols  = $this->getCols();
        $esAlt = ($n % 2 === 0);
        $fillBg = $esAlt ? $this->cAzulPale : $this->cBlanco;

        $this->SetFillColor(...$fillBg);
        $this->SetFont('Arial', '', 7.5);
        $this->SetLineWidth(0.1);
        $this->SetDrawColor(...$this->cGrisBorde);

        $estado_txt = ucfirst($r['estado']);
        $saldo      = (float)$r['saldo_pendiente'];

        $valores = [
            '#' . $r['id_compra'],
            $r['fecha'],
            mb_substr($r['proveedor'] ?? '', 0, 32),
            $tipoLabel[$r['tipo_comprobante']] ?? ucfirst($r['tipo_comprobante']),
            $r['numero_comprobante'],
            ucfirst($r['tipo_pago']),
            ucfirst($r['metodo_pago']),
            'S/. ' . number_format($r['total'], 2),
            $saldo > 0 ? 'S/. ' . number_format($saldo, 2) : '---',
            $estado_txt,
            mb_substr($r['registrado_por'] ?? '', 0, 22),
        ];

        $h = 6;
        foreach ($cols as $i => [$label, $w, $align]) {
            if ($label === 'Estado') {
                [$fr, $fg, $fb] = match($r['estado']) {
                    'pagado'   => [22, 101, 52],
                    'pendiente'=> [133, 100, 4],
                    'anulado'  => [114, 28, 36],
                    default    => $this->cTexto,
                };
                $this->SetTextColor($fr, $fg, $fb);
            } elseif ($label === 'T. Pago') {
                $this->SetTextColor(...($r['tipo_pago'] === 'credito' ? [180, 80, 0] : $this->cVerde));
            } elseif ($label === 'Total') {
                $this->SetTextColor(...$this->cAzul);
            } elseif ($label === 'Saldo' && $saldo > 0) {
                $this->SetTextColor(180, 80, 0);
            }
            $this->Cell($w, $h, pdf_txt($valores[$i]), 'B', 0, $align, true);
            $this->SetTextColor(...$this->cTexto);
        }
        $this->Ln();
    }

    public function filaResumen(): void
    {
        $this->Ln(2);
        $this->SetFillColor(...$this->cAzul);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 8);
        $resumen = '  TOTAL: ' . $this->total_reg . ' compras   |   Pagadas: ' . $this->total_pag . '   |   Pendientes: ' . $this->total_pend . '   |   Anuladas: ' . $this->total_anul . '   |   Monto total: S/. ' . number_format($this->total_monto, 2) . '   |   Generado el ' . date('d/m/Y H:i:s');
        $this->Cell(0, 7, pdf_txt($resumen), 0, 1, 'L', true);
        $this->SetTextColor(...$this->cTexto);
    }
}

// ── Instanciar y generar PDF ──────────────────────────────────────────────────
$pdf = new HistorialComprasPDF('L', 'mm', 'A4');
$pdf->empresa      = $empresa;
$pdf->total_reg    = count($rows);
$pdf->total_monto  = $total_monto;
$pdf->total_pag    = $total_pagadas;
$pdf->total_pend   = $total_pend;
$pdf->total_anul   = $total_anul;
$pdf->label_estado = $label_estado;
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 14);
$pdf->SetTitle('Historial de Compras - SysInversiones CH Computer');
$pdf->SetAuthor('SysInversiones CH Computer');
$pdf->AddPage();

if (empty($rows)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 20, pdf_txt('No se encontraron compras con los filtros seleccionados.'), 0, 1, 'C');
} else {
    $pdf->tablaCabecera();
    $n = 1;
    foreach ($rows as $r) {
        if ($pdf->GetY() > 185) { $pdf->AddPage(); $pdf->tablaCabecera(); }
        $pdf->tablaFila($r, $n++, $tipoLabel);
    }
    $pdf->filaResumen();
}

ob_end_clean();
$filename = 'historial_compras_' . date('Ymd_His') . '.pdf';
$pdf->Output('D', $filename);
