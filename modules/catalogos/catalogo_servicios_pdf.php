<?php
// ============================================================
// modules/catalogos/catalogo_servicios_pdf.php
// Genera PDF A4 del Catálogo de Servicios con FPDF
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
$filtro_est   = $_GET['estado']      ?? 'all';
$filtro_tipo  = $_GET['tipo']        ?? 'all';
$filtro_equip = $_GET['tipo_equipo'] ?? 'all';

$wheres = []; $params = [];
if ($filtro_est !== 'all') {
    $wheres[] = 's.estado = ?';
    $params[]  = ($filtro_est === 'activo') ? 1 : 0;
}
if ($filtro_tipo !== 'all') {
    $wheres[] = 's.tipo = ?';
    $params[]  = $filtro_tipo;
}
$where_sql = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$label_estado = match($filtro_est) {
    'activo'   => 'Activos',
    'inactivo' => 'Inactivos',
    default    => 'Todos',
};

// ── Consulta ──────────────────────────────────────────────────────────────────
$sql = "SELECT
    s.id_servicio, s.nombre, s.tipo, s.descripcion, s.precio_base,
    CASE s.estado WHEN 1 THEN 'Activo' ELSE 'Inactivo' END AS estado_txt,
    GROUP_CONCAT(st.tipo_equipo ORDER BY st.tipo_equipo SEPARATOR ', ') AS tipos_equipo,
    (SELECT COUNT(*) FROM detalle_orden d WHERE d.id_servicio = s.id_servicio) AS veces_usado
FROM servicios s
LEFT JOIN servicio_tipos st ON st.id_servicio = s.id_servicio
$where_sql
GROUP BY s.id_servicio
ORDER BY s.estado DESC, s.nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtrar por tipo de equipo en PHP
if ($filtro_equip !== 'all') {
    $all_rows = array_values(array_filter($all_rows, function($r) use ($filtro_equip) {
        if (empty($r['tipos_equipo'])) return true;
        return in_array($filtro_equip, array_map('trim', explode(',', $r['tipos_equipo'])));
    }));
}
$rows = $all_rows;

$activos   = array_values(array_filter($rows, fn($r) => $r['estado_txt'] === 'Activo'));
$inactivos = array_values(array_filter($rows, fn($r) => $r['estado_txt'] === 'Inactivo'));

$total_activos   = count($activos);
$total_inactivos = count($inactivos);
$precio_promedio = $total_activos > 0
    ? array_sum(array_column($activos, 'precio_base')) / $total_activos
    : 0;

$empresa = getEmpresa($pdo);

// ── Clase PDF ─────────────────────────────────────────────────────────────────
class CatalogoServiciosPDF extends FPDF
{
    public array  $empresa         = [];
    public int    $total_activos   = 0;
    public int    $total_inactivos = 0;
    public float  $precio_promedio = 0;
    public string $label_estado    = 'Todos';

    private array $cAzul       = [ 26,  58, 107];
    private array $cAzulMedio  = [ 37,  99, 235];
    private array $cVerde      = [  6,  95,  70];
    private array $cBlanco     = [255, 255, 255];
    private array $cGrisBorde  = [226, 232, 240];
    private array $cTexto      = [ 30,  41,  59];
    private array $cTextoSuave = [100, 116, 139];
    private array $cAzulPale   = [239, 246, 255];

    public function Header(): void
    {
        $emp = $this->empresa;

        $this->SetFillColor(...$this->cAzul);
        $this->Rect(0, 0, 210, 22, 'F');

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
        $this->Cell(120, 7, pdf_txt($emp['razon_social'] ?? 'SysInversiones CH Computer'), 0, 1, 'L');
        $this->SetFont('Arial', '', 7.5);
        $this->SetXY(38, 11);
        $info_emp = 'RUC: ' . ($emp['ruc'] ?? '') . '   |   ' . ($emp['direccion'] ?? '') . ($emp['distrito'] ? ' - ' . $emp['distrito'] : '');
        $this->Cell(120, 5, pdf_txt($info_emp), 0, 0, 'L');

        // Etiqueta derecha
        $this->SetFillColor(...$this->cAzulMedio);
        $this->Rect(162, 2, 42, 18, 'F');
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY(162, 5);
        $this->Cell(42, 6, 'SERVICIOS', 0, 1, 'C');
        $this->SetFont('Arial', '', 7);
        $this->SetXY(162, 11);
        $this->Cell(42, 5, 'CATALOGO', 0, 0, 'C');

        // Banda subtítulo
        $this->SetFillColor(...$this->cAzulMedio);
        $this->Rect(0, 22, 210, 10, 'F');
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetXY(6, 23);
        $this->Cell(120, 8, pdf_txt('Catalogo de Servicios — ' . date('d/m/Y H:i') . '   |   Estado: ' . $this->label_estado), 0, 0, 'L');
        $this->SetFont('Arial', '', 7.5);
        $this->SetXY(126, 23);
        $this->Cell(78, 8, pdf_txt('Activos: ' . $this->total_activos . '  |  Inactivos: ' . $this->total_inactivos . '  |  Precio prom.: S/. ' . number_format($this->precio_promedio, 2)), 0, 0, 'R');

        $this->SetY(35);
        $this->SetTextColor(...$this->cTexto);
        $this->Ln(2);
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetDrawColor(...$this->cGrisBorde);
        $this->SetLineWidth(0.3);
        $this->Line(6, $this->GetY(), 204, $this->GetY());
        $this->Ln(1);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(...$this->cTextoSuave);
        $this->Cell(0, 4, pdf_txt('SysInversiones CH Computer  |  Catalogo de Servicios  |  Pagina ' . $this->PageNo() . '  |  Generado el ' . date('d/m/Y H:i:s')), 0, 0, 'C');
    }

    public function seccionBanda(string $titulo): void
    {
        $this->Ln(3);
        $this->SetFillColor(...$this->cAzul);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 8, pdf_txt('  ' . $titulo), 0, 1, 'L', true);
        $this->SetTextColor(...$this->cTexto);
        $this->Ln(1);
    }

    // Anchos: #, Nombre, Tipo, Descripción, Equipos, Precio, Usado
    // Total: 8+56+22+56+30+20+14 = 206 mm (A4 portrait útil ~198)
    private function getCols(): array
    {
        return [
            ['#',          8,  'C'],
            ['Nombre',    56,  'L'],
            ['Tipo',      22,  'C'],
            ['Descripcion',56, 'L'],
            ['Equipos',   30,  'L'],
            ['Precio',    20,  'R'],
            ['Usado',     14,  'C'],
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

    public function tablaFila(array $r, int $n): void
    {
        $cols  = $this->getCols();
        $esAlt = ($n % 2 === 0);
        $fillBg = $esAlt ? $this->cAzulPale : $this->cBlanco;

        $this->SetFillColor(...$fillBg);
        $this->SetFont('Arial', '', 7.5);
        $this->SetLineWidth(0.1);
        $this->SetDrawColor(...$this->cGrisBorde);

        $equipos = !empty($r['tipos_equipo']) ? mb_substr($r['tipos_equipo'], 0, 22) : 'Todos';

        $valores = [
            $r['id_servicio'],
            mb_substr($r['nombre'] ?? '', 0, 36),
            ucfirst($r['tipo']),
            mb_substr($r['descripcion'] ?? '—', 0, 38),
            $equipos,
            'S/. ' . number_format($r['precio_base'], 2),
            $r['veces_usado'],
        ];

        $h = 6;
        foreach ($cols as $i => [$label, $w, $align]) {
            if ($label === 'Tipo') {
                $this->SetTextColor(...($r['tipo'] === 'catalogo' ? [30, 64, 175] : [146, 64, 14]));
            } elseif ($label === 'Precio') {
                $this->SetTextColor(...$this->cVerde);
            }
            $this->Cell($w, $h, pdf_txt((string)$valores[$i]), 'B', 0, $align, true);
            $this->SetTextColor(...$this->cTexto);
        }
        $this->Ln();
    }

    public function resumenSeccion(int $total, float $promedio, string $etiqueta): void
    {
        $this->SetFillColor(...$this->cAzulMedio);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 7.5);
        $this->Cell(0, 6, pdf_txt('  Subtotal ' . $etiqueta . ': ' . $total . ' servicios   |   Precio promedio: S/. ' . number_format($promedio, 2)), 0, 1, 'L', true);
        $this->SetTextColor(...$this->cTexto);
        $this->Ln(2);
    }
}

// ── Instanciar y generar PDF ──────────────────────────────────────────────────
$pdf = new CatalogoServiciosPDF('P', 'mm', 'A4');
$pdf->empresa         = $empresa;
$pdf->total_activos   = $total_activos;
$pdf->total_inactivos = $total_inactivos;
$pdf->precio_promedio = $precio_promedio;
$pdf->label_estado    = $label_estado;
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 14);
$pdf->SetTitle('Catalogo de Servicios - SysInversiones CH Computer');
$pdf->SetAuthor('SysInversiones CH Computer');
$pdf->AddPage();

if (empty($rows)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 20, pdf_txt('No se encontraron servicios con los filtros seleccionados.'), 0, 1, 'C');
} else {
    // Activos
    if (!empty($activos)) {
        $pdf->seccionBanda('SERVICIOS ACTIVOS (' . count($activos) . ')');
        $pdf->tablaCabecera();
        $n = 1;
        foreach ($activos as $r) {
            if ($pdf->GetY() > 270) { $pdf->AddPage(); $pdf->tablaCabecera(); }
            $pdf->tablaFila($r, $n++);
        }
        $prom_act = count($activos) > 0 ? array_sum(array_column($activos, 'precio_base')) / count($activos) : 0;
        $pdf->resumenSeccion(count($activos), $prom_act, 'activos');
    }

    // Inactivos
    if (!empty($inactivos)) {
        if (!empty($activos) && $pdf->GetY() > 220) $pdf->AddPage();
        $pdf->seccionBanda('SERVICIOS INACTIVOS (' . count($inactivos) . ')');
        $pdf->tablaCabecera();
        $n = 1;
        foreach ($inactivos as $r) {
            if ($pdf->GetY() > 270) { $pdf->AddPage(); $pdf->tablaCabecera(); }
            $pdf->tablaFila($r, $n++);
        }
        $prom_in = count($inactivos) > 0 ? array_sum(array_column($inactivos, 'precio_base')) / count($inactivos) : 0;
        $pdf->resumenSeccion(count($inactivos), $prom_in, 'inactivos');
    }

    // Resumen final
    $pdf->SetFillColor(26, 58, 107);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(0, 7, pdf_txt('  TOTAL GENERAL: ' . count($rows) . ' servicios   |   Activos: ' . $total_activos . '   |   Inactivos: ' . $total_inactivos . '   |   Precio promedio activos: S/. ' . number_format($precio_promedio, 2) . '   |   Generado el ' . date('d/m/Y H:i:s')), 0, 1, 'L', true);
}

ob_end_clean();
$filename = 'catalogo_servicios_' . date('Ymd_His') . '.pdf';
$pdf->Output('D', $filename);
