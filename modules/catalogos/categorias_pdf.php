<?php
// ============================================================
// modules/catalogos/categorias_pdf.php | SysInversiones CH Computer
// Genera PDF A4 del reporte de Categorías con FPDF
// Columnas: id_categoria, nombre_categoria, descripcion,
//           total_productos, estado, fecha_registro
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

// ── Conversión UTF-8 → ISO-8859-1 ────────────────────────────────────────────
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
$estado = $_GET['estado'] ?? 'all';

$where = match($estado) {
    'activo'   => 'WHERE c.estado = 1',
    'inactivo' => 'WHERE c.estado = 0',
    default    => '',
};

$label_estado = match($estado) {
    'activo'   => 'Activas',
    'inactivo' => 'Inactivas',
    default    => 'Todas',
};

// ── Obtener datos ─────────────────────────────────────────────────────────────
$activas = $pdo->query(
    "SELECT c.id_categoria, c.nombre_categoria, c.descripcion,
            COUNT(p.id_producto) AS total_productos,
            c.estado, c.fecha_registro
     FROM categorias c
     LEFT JOIN productos p ON c.id_categoria = p.id_categoria AND p.estado = 1
     WHERE c.estado = 1
     GROUP BY c.id_categoria
     ORDER BY c.nombre_categoria ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$inactivas = $pdo->query(
    "SELECT c.id_categoria, c.nombre_categoria, c.descripcion,
            COUNT(p.id_producto) AS total_productos,
            c.estado, c.fecha_registro
     FROM categorias c
     LEFT JOIN productos p ON c.id_categoria = p.id_categoria AND p.estado = 1
     WHERE c.estado = 0
     GROUP BY c.id_categoria
     ORDER BY c.nombre_categoria ASC"
)->fetchAll(PDO::FETCH_ASSOC);

if ($estado === 'activo')   $inactivas = [];
if ($estado === 'inactivo') $activas   = [];

$total_activas   = count($activas);
$total_inactivas = count($inactivas);
$total_all       = $total_activas + $total_inactivas;
$total_prods     = array_sum(array_column($activas, 'total_productos'));

$empresa = getEmpresa($pdo);

// ── Clase PDF ─────────────────────────────────────────────────────────────────
class CategoriasPDF extends FPDF
{
    public array  $empresa         = [];
    public int    $total_all       = 0;
    public int    $total_activas   = 0;
    public int    $total_inactivas = 0;
    public int    $total_prods     = 0;
    public string $label_estado    = 'Todas';

    private array $cAzulOscuro = [ 26,  82, 118];
    private array $cAzulMedio  = [ 41, 128, 185];
    private array $cVerde      = [ 26, 122,  74];
    private array $cBlanco     = [255, 255, 255];
    private array $cGrisBorde  = [226, 232, 240];
    private array $cTexto      = [ 30,  41,  59];
    private array $cTextoSuave = [100, 116, 139];
    private array $cAzulPale   = [232, 244, 253];

    function Header(): void
    {
        $emp = $this->empresa;

        $this->SetFillColor(...$this->cAzulOscuro);
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
        $this->Cell(42, 6, 'CATEGORIAS', 0, 1, 'C');
        $this->SetFont('Arial', '', 7);
        $this->SetXY(162, 11);
        $this->Cell(42, 5, 'CATALOGO', 0, 0, 'C');

        // Banda título
        $this->SetFillColor(...$this->cAzulMedio);
        $this->Rect(0, 22, 210, 10, 'F');
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetXY(6, 23);
        $this->Cell(120, 8, pdf_txt('Reporte de Categorias — ' . date('d/m/Y H:i') . '   |   Estado: ' . $this->label_estado), 0, 0, 'L');
        $this->SetFont('Arial', '', 7.5);
        $this->SetXY(126, 23);
        $this->Cell(78, 8, pdf_txt('Total: ' . $this->total_all . '  |  Activas: ' . $this->total_activas . '  |  Inactivas: ' . $this->total_inactivas . '  |  Productos: ' . $this->total_prods), 0, 0, 'R');

        $this->SetY(35);
        $this->SetTextColor(...$this->cTexto);
        $this->Ln(2);
    }

    function Footer(): void
    {
        $this->SetY(-12);
        $this->SetDrawColor(...$this->cGrisBorde);
        $this->SetLineWidth(0.3);
        $this->Line(6, $this->GetY(), 204, $this->GetY());
        $this->Ln(1);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(...$this->cTextoSuave);
        $this->Cell(0, 4, pdf_txt('SysInversiones CH Computer  |  Reporte de Categorias  |  Pagina ' . $this->PageNo() . '  |  Generado el ' . date('d/m/Y H:i:s')), 0, 0, 'C');
    }

    function seccionBanda(string $titulo): void
    {
        $this->Ln(3);
        $this->SetFillColor(...$this->cAzulOscuro);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 8, pdf_txt('  ' . $titulo), 0, 1, 'L', true);
        $this->SetTextColor(...$this->cTexto);
        $this->Ln(1);
    }

    // Columnas exactas de categorias + total_productos
    // Total: 10+62+68+22+16+20 = 198 mm (A4 portrait útil ~198)
    private function getCols(): array
    {
        return [
            ['ID',              10, 'C'],
            ['Nombre Categoria',62, 'L'],
            ['Descripcion',     68, 'L'],
            ['Productos',       22, 'C'],
            ['Estado',          16, 'C'],
            ['Registro',        20, 'C'],
        ];
    }

    function tablaCabecera(): void
    {
        $this->SetFillColor(...$this->cAzulOscuro);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetLineWidth(0);
        foreach ($this->getCols() as [$label, $w, $align]) {
            $this->Cell($w, 7, pdf_txt($label), 0, 0, $align, true);
        }
        $this->Ln();
        $this->SetTextColor(...$this->cTexto);
    }

    function tablaFila(array $r, int $n): void
    {
        $cols   = $this->getCols();
        $esAlt  = ($n % 2 === 0);
        $fillBg = $esAlt ? $this->cAzulPale : $this->cBlanco;

        $this->SetFillColor(...$fillBg);
        $this->SetFont('Arial', '', 7.5);
        $this->SetLineWidth(0.1);
        $this->SetDrawColor(...$this->cGrisBorde);

        $estado_txt = ($r['estado'] == 1) ? 'Activa' : 'Inactiva';
        $fecha      = !empty($r['fecha_registro']) ? date('d/m/Y', strtotime($r['fecha_registro'])) : '—';

        $valores = [
            $r['id_categoria'],
            mb_substr($r['nombre_categoria'] ?? '', 0, 38),
            mb_substr($r['descripcion'] ?? '—', 0, 42),
            $r['total_productos'],
            $estado_txt,
            $fecha,
        ];

        $h = 6;
        foreach ($cols as $i => [$label, $w, $align]) {
            if ($label === 'Estado') {
                $this->SetTextColor(
                    $estado_txt === 'Activa' ? 22  : 153,
                    $estado_txt === 'Activa' ? 101 : 27,
                    $estado_txt === 'Activa' ? 52  : 27
                );
            } elseif ($label === 'Productos') {
                $this->SetTextColor(...$this->cAzulOscuro);
            }
            $this->Cell($w, $h, pdf_txt($valores[$i]), 'B', 0, $align, true);
            $this->SetTextColor(...$this->cTexto);
        }
        $this->Ln();
    }

    function resumenSeccion(int $total, int $prods, string $etiqueta): void
    {
        $this->SetFillColor(...$this->cAzulMedio);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 7.5);
        $this->Cell(0, 6, pdf_txt('  Subtotal ' . $etiqueta . ': ' . $total . ' categorias   |   Productos asociados: ' . $prods), 0, 1, 'L', true);
        $this->SetTextColor(...$this->cTexto);
        $this->Ln(2);
    }
}

// ── Instanciar y generar PDF — A4 Portrait ────────────────────────────────────
$pdf = new CategoriasPDF('P', 'mm', 'A4');
$pdf->empresa         = $empresa;
$pdf->total_all       = $total_all;
$pdf->total_activas   = $total_activas;
$pdf->total_inactivas = $total_inactivas;
$pdf->total_prods     = $total_prods;
$pdf->label_estado    = $label_estado;
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 14);
$pdf->SetTitle('Categorias - SysInversiones CH Computer');
$pdf->SetAuthor('SysInversiones CH Computer');
$pdf->AddPage();

// ── ACTIVAS ───────────────────────────────────────────────────────────────────
if (!empty($activas)) {
    $pdf->seccionBanda('CATEGORIAS ACTIVAS (' . count($activas) . ')');
    $pdf->tablaCabecera();
    $n = 1;
    foreach ($activas as $r) {
        if ($pdf->GetY() > 270) { $pdf->AddPage(); $pdf->tablaCabecera(); }
        $pdf->tablaFila($r, $n++);
    }
    $prods_activas = array_sum(array_column($activas, 'total_productos'));
    $pdf->resumenSeccion(count($activas), $prods_activas, 'activas');
}

// ── INACTIVAS ─────────────────────────────────────────────────────────────────
if (!empty($inactivas)) {
    if (!empty($activas) && $pdf->GetY() > 220) $pdf->AddPage();
    $pdf->seccionBanda('CATEGORIAS INACTIVAS (' . count($inactivas) . ')');
    $pdf->tablaCabecera();
    $n = 1;
    foreach ($inactivas as $r) {
        if ($pdf->GetY() > 270) { $pdf->AddPage(); $pdf->tablaCabecera(); }
        $pdf->tablaFila($r, $n++);
    }
    $prods_inactivas = array_sum(array_column($inactivas, 'total_productos'));
    $pdf->resumenSeccion(count($inactivas), $prods_inactivas, 'inactivas');
}

// ── Resumen final ─────────────────────────────────────────────────────────────
$pdf->SetFillColor(26, 82, 118);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(0, 7, pdf_txt('  TOTAL GENERAL: ' . $total_all . ' categorias   |   Activas: ' . $total_activas . '   |   Inactivas: ' . $total_inactivas . '   |   Productos activos: ' . $total_prods . '   |   Generado el ' . date('d/m/Y H:i:s')), 0, 1, 'L', true);

ob_end_clean();
$filename = 'categorias_' . date('Ymd_His') . '.pdf';
$pdf->Output('D', $filename);
