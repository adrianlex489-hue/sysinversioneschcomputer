<?php
// ============================================================
// modules/catalogos/productos_pdf.php | SysInversiones CH Computer
// Genera PDF A4 Landscape del reporte de Productos con FPDF
// Columnas (sin foto/imagenes):
//   id_producto, codigo, nombre_producto, marca, modelo,
//   nombre_categoria, nombre_proveedor, stock, stock_minimo,
//   stock_maximo, precio_compra, precio_venta, estado, fecha_registro
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
    'activo'   => 'WHERE p.estado = 1',
    'inactivo' => 'WHERE p.estado = 0',
    default    => '',
};

$label_estado = match($estado) {
    'activo'   => 'Activos',
    'inactivo' => 'Inactivos',
    default    => 'Todos',
};

// ── Obtener datos — sin columna imagenes ──────────────────────────────────────
$activos = $pdo->query(
    "SELECT p.id_producto, p.codigo, p.nombre_producto, p.marca, p.modelo,
            c.nombre_categoria, pr.razon_social AS nombre_proveedor,
            p.stock, p.stock_minimo, p.stock_maximo,
            p.precio_compra, p.precio_venta, p.estado, p.fecha_registro
     FROM productos p
     LEFT JOIN categorias  c  ON p.id_categoria = c.id_categoria
     LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
     WHERE p.estado = 1 ORDER BY p.nombre_producto ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$inactivos = $pdo->query(
    "SELECT p.id_producto, p.codigo, p.nombre_producto, p.marca, p.modelo,
            c.nombre_categoria, pr.razon_social AS nombre_proveedor,
            p.stock, p.stock_minimo, p.stock_maximo,
            p.precio_compra, p.precio_venta, p.estado, p.fecha_registro
     FROM productos p
     LEFT JOIN categorias  c  ON p.id_categoria = c.id_categoria
     LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
     WHERE p.estado = 0 ORDER BY p.nombre_producto ASC"
)->fetchAll(PDO::FETCH_ASSOC);

if ($estado === 'activo')   $inactivos = [];
if ($estado === 'inactivo') $activos   = [];

$total_activos   = count($activos);
$total_inactivos = count($inactivos);
$total_all       = $total_activos + $total_inactivos;
$agotados        = count(array_filter($activos, fn($p) => (int)$p['stock'] <= 0));
$stock_bajo      = count(array_filter($activos, fn($p) => (int)$p['stock'] > 0 && (int)$p['stock'] <= (int)$p['stock_minimo']));

$empresa = getEmpresa($pdo);

// ── Clase PDF ─────────────────────────────────────────────────────────────────
class ProductosPDF extends FPDF
{
    public array  $empresa         = [];
    public int    $total_all       = 0;
    public int    $total_activos   = 0;
    public int    $total_inactivos = 0;
    public int    $agotados        = 0;
    public int    $stock_bajo      = 0;
    public string $label_estado    = 'Todos';

    // Paleta
    private array $cAzulOscuro = [ 26,  82, 118];
    private array $cAzulMedio  = [ 41, 128, 185];
    private array $cVerde      = [ 26, 122,  74];
    private array $cRojo       = [220,  38,  38];
    private array $cNaranja    = [230, 126,  34];
    private array $cBlanco     = [255, 255, 255];
    private array $cGrisBorde  = [226, 232, 240];
    private array $cTexto      = [ 30,  41,  59];
    private array $cTextoSuave = [100, 116, 139];
    private array $cAzulPale   = [232, 244, 253];

    function Header(): void
    {
        $emp = $this->empresa;

        $this->SetFillColor(...$this->cAzulOscuro);
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
        $this->SetFont('Arial', 'B', 13);
        $this->SetXY(38, 4);
        $this->Cell(160, 7, pdf_txt($emp['razon_social'] ?? 'SysInversiones CH Computer'), 0, 1, 'L');
        $this->SetFont('Arial', '', 8);
        $this->SetXY(38, 11);
        $info_emp = 'RUC: ' . ($emp['ruc'] ?? '') . '   |   ' . ($emp['direccion'] ?? '') . ($emp['distrito'] ? ' - ' . $emp['distrito'] : '');
        $this->Cell(160, 5, pdf_txt($info_emp), 0, 0, 'L');

        $this->SetFillColor(...$this->cAzulMedio);
        $this->Rect(240, 2, 52, 18, 'F');
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY(240, 5);
        $this->Cell(52, 6, 'PRODUCTOS', 0, 1, 'C');
        $this->SetFont('Arial', '', 7);
        $this->SetXY(240, 11);
        $this->Cell(52, 5, 'CATALOGO DE PRODUCTOS', 0, 0, 'C');

        $this->SetFillColor(...$this->cAzulMedio);
        $this->Rect(0, 22, 297, 10, 'F');
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY(6, 23);
        $this->Cell(180, 8, pdf_txt('Reporte de Productos — ' . date('d/m/Y H:i') . '   |   Estado: ' . $this->label_estado), 0, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $this->SetXY(186, 23);
        $this->Cell(105, 8, pdf_txt('Total: ' . $this->total_all . '  |  Activos: ' . $this->total_activos . '  |  Inactivos: ' . $this->total_inactivos . '  |  Agotados: ' . $this->agotados . '  |  Stock bajo: ' . $this->stock_bajo), 0, 0, 'R');

        $this->SetY(35);
        $this->SetTextColor(...$this->cTexto);
        $this->Ln(2);
    }

    function Footer(): void
    {
        $this->SetY(-12);
        $this->SetDrawColor(...$this->cGrisBorde);
        $this->SetLineWidth(0.3);
        $this->Line(6, $this->GetY(), 291, $this->GetY());
        $this->Ln(1);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(...$this->cTextoSuave);
        $this->Cell(0, 4, pdf_txt('SysInversiones CH Computer  |  Reporte de Productos  |  Pagina ' . $this->PageNo() . '  |  Generado el ' . date('d/m/Y H:i:s')), 0, 0, 'C');
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

    // Columnas (sin foto):
    // ID | Código | Nombre Producto | Marca | Modelo | Categoría | Proveedor | Stock | S.Min | S.Max | P.Compra | P.Venta | Estado | Registro
    // Total: 8+14+46+14+16+20+30+9+9+9+14+14+14+20 = 237 mm  (A4 landscape útil ~277)
    private function getCols(): array
    {
        return [
            ['ID',        8,  'C'],
            ['Codigo',   14,  'C'],
            ['Nombre Producto', 46, 'L'],
            ['Marca',    14,  'L'],
            ['Modelo',   16,  'L'],
            ['Categoria',20,  'L'],
            ['Proveedor',30,  'L'],
            ['Stock',     9,  'C'],
            ['S.Min',     9,  'C'],
            ['S.Max',     9,  'C'],
            ['P.Compra', 16,  'R'],
            ['P.Venta',  16,  'R'],
            ['Estado',   14,  'C'],
            ['Registro', 20,  'C'],
        ];
    }

    function tablaCabecera(): void
    {
        $this->SetFillColor(...$this->cAzulOscuro);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 6.5);
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

        $stock = (int)($r['stock'] ?? 0);
        $smin  = (int)($r['stock_minimo'] ?? 0);

        // Color de fondo según stock
        if ($r['estado'] == 1) {
            if ($stock <= 0)        $fillBg = [255, 235, 235]; // rojo suave
            elseif ($stock <= $smin) $fillBg = [255, 248, 220]; // amarillo suave
        }

        $this->SetFillColor(...$fillBg);
        $this->SetFont('Arial', '', 6.5);
        $this->SetLineWidth(0.1);
        $this->SetDrawColor(...$this->cGrisBorde);

        $estado_txt = ($r['estado'] == 1) ? 'Activo' : 'Inactivo';
        $fecha      = !empty($r['fecha_registro']) ? date('d/m/Y', strtotime($r['fecha_registro'])) : '—';

        $valores = [
            $r['id_producto'],
            mb_substr($r['codigo'] ?? '', 0, 10),
            mb_substr($r['nombre_producto'] ?? '', 0, 30),
            mb_substr($r['marca'] ?? '—', 0, 10),
            mb_substr($r['modelo'] ?? '—', 0, 12),
            mb_substr($r['nombre_categoria'] ?? '—', 0, 14),
            mb_substr($r['nombre_proveedor'] ?? '—', 0, 20),
            $stock,
            $r['stock_minimo'] ?? 0,
            $r['stock_maximo'] ?? 0,
            'S/.' . number_format((float)($r['precio_compra'] ?? 0), 2),
            'S/.' . number_format((float)($r['precio_venta']  ?? 0), 2),
            $estado_txt,
            $fecha,
        ];

        $h = 5.5;
        foreach ($cols as $i => [$label, $w, $align]) {
            // Colorear stock
            if ($label === 'Stock') {
                if ($stock <= 0)         $this->SetTextColor(...$this->cRojo);
                elseif ($stock <= $smin) $this->SetTextColor(...$this->cNaranja);
                else                     $this->SetTextColor(...$this->cVerde);
            } elseif ($label === 'Estado') {
                $this->SetTextColor(
                    $estado_txt === 'Activo' ? 22  : 153,
                    $estado_txt === 'Activo' ? 101 : 27,
                    $estado_txt === 'Activo' ? 52  : 27
                );
            } elseif (in_array($label, ['P.Compra','P.Venta'])) {
                $this->SetTextColor(26, 82, 118);
            }
            $this->Cell($w, $h, pdf_txt($valores[$i]), 'B', 0, $align, true);
            $this->SetTextColor(...$this->cTexto);
        }
        $this->Ln();
    }

    function resumenSeccion(int $total, string $etiqueta): void
    {
        $this->SetFillColor(...$this->cAzulMedio);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 7.5);
        $this->Cell(0, 6, pdf_txt('  Subtotal ' . $etiqueta . ': ' . $total . ' productos'), 0, 1, 'L', true);
        $this->SetTextColor(...$this->cTexto);
        $this->Ln(2);
    }
}

// ── Instanciar y generar PDF ──────────────────────────────────────────────────
$pdf = new ProductosPDF('L', 'mm', 'A4');
$pdf->empresa         = $empresa;
$pdf->total_all       = $total_all;
$pdf->total_activos   = $total_activos;
$pdf->total_inactivos = $total_inactivos;
$pdf->agotados        = $agotados;
$pdf->stock_bajo      = $stock_bajo;
$pdf->label_estado    = $label_estado;
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 14);
$pdf->SetTitle('Productos - SysInversiones CH Computer');
$pdf->SetAuthor('SysInversiones CH Computer');
$pdf->AddPage();

// ── ACTIVOS ───────────────────────────────────────────────────────────────────
if (!empty($activos)) {
    $pdf->seccionBanda('PRODUCTOS ACTIVOS (' . count($activos) . ')');
    $pdf->tablaCabecera();
    $n = 1;
    foreach ($activos as $r) {
        if ($pdf->GetY() > 188) { $pdf->AddPage(); $pdf->tablaCabecera(); }
        $pdf->tablaFila($r, $n++);
    }
    $pdf->resumenSeccion(count($activos), 'activos');
}

// ── INACTIVOS ─────────────────────────────────────────────────────────────────
if (!empty($inactivos)) {
    if (!empty($activos) && $pdf->GetY() > 150) $pdf->AddPage();
    $pdf->seccionBanda('PRODUCTOS INACTIVOS (' . count($inactivos) . ')');
    $pdf->tablaCabecera();
    $n = 1;
    foreach ($inactivos as $r) {
        if ($pdf->GetY() > 188) { $pdf->AddPage(); $pdf->tablaCabecera(); }
        $pdf->tablaFila($r, $n++);
    }
    $pdf->resumenSeccion(count($inactivos), 'inactivos');
}

// ── Resumen final ─────────────────────────────────────────────────────────────
$pdf->SetFillColor(26, 82, 118);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(0, 7, pdf_txt('  TOTAL GENERAL: ' . $total_all . ' productos   |   Activos: ' . $total_activos . '   |   Inactivos: ' . $total_inactivos . '   |   Agotados: ' . $agotados . '   |   Stock bajo: ' . $stock_bajo . '   |   Generado el ' . date('d/m/Y H:i:s')), 0, 1, 'L', true);

ob_end_clean();
$filename = 'productos_' . date('Ymd_His') . '.pdf';
$pdf->Output('D', $filename);
