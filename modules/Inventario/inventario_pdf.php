<?php
// ============================================================
// modules/Inventario/inventario_pdf.php
// Genera PDF A4 Landscape del Inventario con FPDF
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
$filtro_stock = $_GET['stock']     ?? 'all';
$filtro_cat   = $_GET['categoria'] ?? 'all';

$wheres = ['p.estado = 1'];
$params = [];
if ($filtro_cat !== 'all') { $wheres[] = 'p.id_categoria = ?'; $params[] = (int)$filtro_cat; }
$where_sql = 'WHERE ' . implode(' AND ', $wheres);

$label_stock = match($filtro_stock) {
    'ok'      => 'Normal',
    'bajo'    => 'Stock bajo',
    'agotado' => 'Agotados',
    'exceso'  => 'Exceso',
    default   => 'Todos',
};

// ── Consulta ──────────────────────────────────────────────────────────────────
$sql = "SELECT
    p.id_producto, p.codigo, p.nombre_producto, p.marca, p.modelo,
    c.nombre_categoria,
    p.stock, p.stock_minimo, p.stock_maximo,
    p.precio_compra, p.precio_venta,
    ROUND(p.stock * p.precio_compra, 2) AS valor_inventario,
    CASE
        WHEN p.stock <= 0                                   THEN 'Agotado'
        WHEN p.stock <= p.stock_minimo                      THEN 'Stock bajo'
        WHEN p.stock_maximo > 0 AND p.stock > p.stock_maximo THEN 'Exceso'
        ELSE 'Normal'
    END AS estado_stock
FROM productos p
LEFT JOIN categorias c ON p.id_categoria = c.id_categoria
$where_sql
ORDER BY
    CASE WHEN p.stock <= 0 THEN 3 WHEN p.stock <= p.stock_minimo THEN 2 ELSE 1 END ASC,
    p.stock DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$rows = match($filtro_stock) {
    'ok'      => array_values(array_filter($all_rows, fn($r) => $r['estado_stock'] === 'Normal')),
    'bajo'    => array_values(array_filter($all_rows, fn($r) => $r['estado_stock'] === 'Stock bajo')),
    'agotado' => array_values(array_filter($all_rows, fn($r) => $r['estado_stock'] === 'Agotado')),
    'exceso'  => array_values(array_filter($all_rows, fn($r) => $r['estado_stock'] === 'Exceso')),
    default   => $all_rows,
};

$valor_total   = array_sum(array_column($rows, 'valor_inventario'));
$total_ok      = count(array_filter($rows, fn($r) => $r['estado_stock'] === 'Normal'));
$total_bajo    = count(array_filter($rows, fn($r) => $r['estado_stock'] === 'Stock bajo'));
$total_agotado = count(array_filter($rows, fn($r) => $r['estado_stock'] === 'Agotado'));
$total_exceso  = count(array_filter($rows, fn($r) => $r['estado_stock'] === 'Exceso'));

$empresa = getEmpresa($pdo);

// ── Clase PDF ─────────────────────────────────────────────────────────────────
class InventarioPDF extends FPDF
{
    public array  $empresa      = [];
    public int    $total_reg    = 0;
    public float  $valor_total  = 0;
    public int    $total_ok     = 0;
    public int    $total_bajo   = 0;
    public int    $total_agot   = 0;
    public int    $total_exc    = 0;
    public string $label_stock  = 'Todos';

    private array $cAzul       = [ 26,  82, 118];
    private array $cTeal       = [ 17, 122, 139];
    private array $cVerde      = [ 26, 122,  74];
    private array $cBlanco     = [255, 255, 255];
    private array $cGrisBorde  = [226, 232, 240];
    private array $cTexto      = [ 30,  41,  59];
    private array $cTextoSuave = [100, 116, 139];
    private array $cAzulPale   = [232, 244, 253];

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
        $this->SetFillColor(...$this->cTeal);
        $this->Rect(249, 2, 42, 18, 'F');
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY(249, 5);
        $this->Cell(42, 6, 'INVENTARIO', 0, 1, 'C');
        $this->SetFont('Arial', '', 7);
        $this->SetXY(249, 11);
        $this->Cell(42, 5, 'STOCK GENERAL', 0, 0, 'C');

        // Banda subtítulo
        $this->SetFillColor(...$this->cTeal);
        $this->Rect(0, 22, 297, 10, 'F');
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetXY(6, 23);
        $this->Cell(140, 8, pdf_txt('Stock General — ' . date('d/m/Y H:i') . '   |   Filtro: ' . $this->label_stock), 0, 0, 'L');
        $this->SetFont('Arial', '', 7.5);
        $this->SetXY(146, 23);
        $this->Cell(145, 8, pdf_txt('Total: ' . $this->total_reg . '  |  Normal: ' . $this->total_ok . '  |  Bajo: ' . $this->total_bajo . '  |  Agotado: ' . $this->total_agot . '  |  Valor: S/. ' . number_format($this->valor_total, 2)), 0, 0, 'R');

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
        $this->Cell(0, 4, pdf_txt('SysInversiones CH Computer  |  Inventario Stock General  |  Pagina ' . $this->PageNo() . '  |  Generado el ' . date('d/m/Y H:i:s')), 0, 0, 'C');
    }

    // Anchos: #, Código, Producto, Marca/Modelo, Categoría, Stock, Mín, Máx, P.Compra, P.Venta, Valor, Estado
    // Total: 8+18+52+26+24+14+10+10+18+18+20+18 = 236 mm (A4 landscape útil ~277)
    private function getCols(): array
    {
        return [
            ['#',          8,  'C'],
            ['Codigo',    18,  'C'],
            ['Producto',  52,  'L'],
            ['Marca/Mod', 26,  'L'],
            ['Categoria', 24,  'L'],
            ['Stock',     14,  'C'],
            ['Min.',      10,  'C'],
            ['Max.',      10,  'C'],
            ['P.Compra',  18,  'R'],
            ['P.Venta',   18,  'R'],
            ['Valor Inv.',20,  'R'],
            ['Estado',    18,  'C'],
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

        // Override color de fila por estado crítico
        if ($r['estado_stock'] === 'Agotado') {
            $fillBg = [255, 245, 245];
        } elseif ($r['estado_stock'] === 'Stock bajo') {
            $fillBg = [255, 248, 240];
        }

        $this->SetFillColor(...$fillBg);
        $this->SetFont('Arial', '', 7.5);
        $this->SetLineWidth(0.1);
        $this->SetDrawColor(...$this->cGrisBorde);

        $marca_mod = trim(($r['marca'] ?? '') . ($r['modelo'] ? ' / ' . $r['modelo'] : ''));

        $valores = [
            $r['id_producto'],
            $r['codigo'],
            mb_substr($r['nombre_producto'] ?? '', 0, 32),
            mb_substr($marca_mod, 0, 18),
            mb_substr($r['nombre_categoria'] ?? '—', 0, 16),
            $r['stock'],
            $r['stock_minimo'],
            $r['stock_maximo'],
            'S/. ' . number_format($r['precio_compra'], 2),
            'S/. ' . number_format($r['precio_venta'],  2),
            'S/. ' . number_format($r['valor_inventario'], 2),
            $r['estado_stock'],
        ];

        $h = 6;
        foreach ($cols as $i => [$label, $w, $align]) {
            if ($label === 'Estado') {
                [$fr, $fg, $fb] = match($r['estado_stock']) {
                    'Normal'     => [22, 101, 52],
                    'Stock bajo' => [133, 100, 4],
                    'Agotado'    => [114, 28, 36],
                    'Exceso'     => [108, 52, 131],
                    default      => $this->cTexto,
                };
                $this->SetTextColor($fr, $fg, $fb);
            } elseif ($label === 'Stock') {
                [$fr, $fg, $fb] = match($r['estado_stock']) {
                    'Normal'     => [22, 101, 52],
                    'Stock bajo' => [133, 100, 4],
                    'Agotado'    => [114, 28, 36],
                    'Exceso'     => [108, 52, 131],
                    default      => $this->cTexto,
                };
                $this->SetTextColor($fr, $fg, $fb);
            } elseif ($label === 'Valor Inv.') {
                $this->SetTextColor(...$this->cAzul);
            }
            $this->Cell($w, $h, pdf_txt((string)$valores[$i]), 'B', 0, $align, true);
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
        $resumen = '  TOTAL: ' . $this->total_reg . ' productos   |   Normal: ' . $this->total_ok . '   |   Stock bajo: ' . $this->total_bajo . '   |   Agotados: ' . $this->total_agot . '   |   Exceso: ' . $this->total_exc . '   |   Valor total: S/. ' . number_format($this->valor_total, 2) . '   |   ' . date('d/m/Y H:i:s');
        $this->Cell(0, 7, pdf_txt($resumen), 0, 1, 'L', true);
        $this->SetTextColor(...$this->cTexto);
    }
}

// ── Instanciar y generar PDF ──────────────────────────────────────────────────
$pdf = new InventarioPDF('L', 'mm', 'A4');
$pdf->empresa     = $empresa;
$pdf->total_reg   = count($rows);
$pdf->valor_total = $valor_total;
$pdf->total_ok    = $total_ok;
$pdf->total_bajo  = $total_bajo;
$pdf->total_agot  = $total_agotado;
$pdf->total_exc   = $total_exceso;
$pdf->label_stock = $label_stock;
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 14);
$pdf->SetTitle('Inventario Stock General - SysInversiones CH Computer');
$pdf->SetAuthor('SysInversiones CH Computer');
$pdf->AddPage();

if (empty($rows)) {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 20, pdf_txt('No se encontraron productos con los filtros seleccionados.'), 0, 1, 'C');
} else {
    $pdf->tablaCabecera();
    $n = 1;
    foreach ($rows as $r) {
        if ($pdf->GetY() > 185) { $pdf->AddPage(); $pdf->tablaCabecera(); }
        $pdf->tablaFila($r, $n++);
    }
    $pdf->filaResumen();
}

ob_end_clean();
$filename = 'inventario_' . date('Ymd_His') . '.pdf';
$pdf->Output('D', $filename);
