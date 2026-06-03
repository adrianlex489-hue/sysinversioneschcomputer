<?php
// ============================================================
// modules/auditoria/auditoria_pdf.php | SysInversiones CH Computer
// Genera PDF A4 del reporte de Auditoría con FPDF
// ============================================================
ob_start();

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'libs/fpdf.php';
require_once __DIR__ . '/../configuracion_empresa/empresa_helper.php';

if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
verificar_acceso([ROL_ADMINISTRADOR]);

if (!isset($pdo) || !($pdo instanceof PDO)) {
    ob_end_clean(); die('Error: Sin conexión a la base de datos.');
}

// ── Conversión UTF-8 → ISO-8859-1 (igual que en otros PDFs del proyecto) ─────
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

// ── Leer filtros desde GET ────────────────────────────────────────────────────
$modulo      = trim($_GET['modulo']      ?? '');
$accion_f    = trim($_GET['accion']      ?? '');
$usuario     = trim($_GET['usuario']     ?? '');
$fecha_desde = trim($_GET['fecha_desde'] ?? '');
$fecha_hasta = trim($_GET['fecha_hasta'] ?? '');
$buscar      = trim($_GET['buscar']      ?? '');

// ── Construir consulta ────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];
if ($modulo)      { $where[] = 'a.modulo = ?';                                          $params[] = $modulo; }
if ($accion_f)    { $where[] = 'a.accion = ?';                                          $params[] = $accion_f; }
if ($usuario)     { $where[] = '(u.nombre_completo LIKE ? OR u.username LIKE ?)';       $params[] = "%$usuario%"; $params[] = "%$usuario%"; }
if ($fecha_desde) { $where[] = 'DATE(a.fecha) >= ?';                                    $params[] = $fecha_desde; }
if ($fecha_hasta) { $where[] = 'DATE(a.fecha) <= ?';                                    $params[] = $fecha_hasta; }
if ($buscar)      { $where[] = '(a.descripcion LIKE ? OR a.campo LIKE ? OR a.valor_antes LIKE ? OR a.valor_nuevo LIKE ?)';
                    $params[] = "%$buscar%"; $params[] = "%$buscar%"; $params[] = "%$buscar%"; $params[] = "%$buscar%"; }

$whereStr = implode(' AND ', $where);

try {
    $st = $pdo->prepare("
        SELECT a.*, u.nombre_completo AS nombre_usuario
        FROM auditoria a
        LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
        WHERE $whereStr
        ORDER BY a.fecha DESC
        LIMIT 2000
    ");
    $st->execute($params);
    $registros = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    ob_end_clean(); die('Error BD: ' . $e->getMessage());
}

$empresa = getEmpresa($pdo);

// ── Etiquetas de filtros activos para mostrar en el PDF ───────────────────────
$filtros_activos = [];
if ($modulo)      $filtros_activos[] = 'Modulo: ' . ucfirst($modulo);
if ($accion_f)    $filtros_activos[] = 'Accion: ' . ucfirst(str_replace('_', ' ', $accion_f));
if ($usuario)     $filtros_activos[] = 'Usuario: ' . $usuario;
if ($fecha_desde) $filtros_activos[] = 'Desde: ' . date('d/m/Y', strtotime($fecha_desde));
if ($fecha_hasta) $filtros_activos[] = 'Hasta: ' . date('d/m/Y', strtotime($fecha_hasta));
if ($buscar)      $filtros_activos[] = 'Busqueda: ' . $buscar;

// ── Clase PDF ─────────────────────────────────────────────────────────────────
class AuditoriaPDF extends FPDF
{
    public array  $empresa       = [];
    public string $filtros_txt   = '';
    public int    $total_regs    = 0;

    // Colores corporativos
    private array $cAzulOscuro = [15,  58, 138]; // #0f3a8a
    private array $cAzulMedio  = [37,  99, 235]; // #2563eb
    private array $cBlanco     = [255,255, 255];
    private array $cGrisClaro  = [248,250, 252]; // #f8fafc
    private array $cGrisBorde  = [226,232, 240]; // #e2e8f0
    private array $cTexto      = [ 30, 41,  59]; // #1e293b
    private array $cTextoSuave = [100,116, 139]; // #64748b
    private array $cAzulPale   = [239,246, 255]; // #eff6ff

    function Header(): void
    {
        $emp = $this->empresa;

        // ── Banda superior azul oscuro ──
        $this->SetFillColor(...$this->cAzulOscuro);
        $this->Rect(0, 0, 297, 22, 'F');

        // Logo
        $logo_path = $_SERVER['DOCUMENT_ROOT'] . ($emp['logo'] ?? '');
        if (!empty($emp['logo']) && file_exists($logo_path)) {
            $info = @getimagesize($logo_path);
            if ($info) {
                $ratio  = $info[0] / $info[1];
                $draw_h = 14;
                $draw_w = min($draw_h * $ratio, 28);
                $this->Image($logo_path, 6, 4, $draw_w, $draw_h);
            }
        }

        // Nombre empresa
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 13);
        $this->SetXY(38, 4);
        $this->Cell(160, 7, pdf_txt($emp['razon_social'] ?? 'SysInversiones CH Computer'), 0, 1, 'L');

        // Subtítulo empresa
        $this->SetFont('Arial', '', 8);
        $this->SetXY(38, 11);
        $info_emp = 'RUC: ' . ($emp['ruc'] ?? '') . '   |   ' . ($emp['direccion'] ?? '') . ($emp['distrito'] ? ' - ' . $emp['distrito'] : '');
        $this->Cell(160, 5, pdf_txt($info_emp), 0, 0, 'L');

        // Etiqueta "AUDITORÍA" en la derecha
        $this->SetFillColor(...$this->cAzulMedio);
        $this->Rect(240, 2, 52, 18, 'F');
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY(240, 5);
        $this->Cell(52, 6, 'AUDITORIA', 0, 1, 'C');
        $this->SetFont('Arial', '', 7);
        $this->SetXY(240, 11);
        $this->Cell(52, 5, 'DEL SISTEMA', 0, 0, 'C');

        // ── Banda de título del reporte ──
        $this->SetFillColor(...$this->cAzulMedio);
        $this->Rect(0, 22, 297, 10, 'F');
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY(6, 23);
        $this->Cell(180, 8, pdf_txt('Reporte de Registro de Eventos — ' . date('d/m/Y H:i')), 0, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $this->SetXY(186, 23);
        $this->Cell(105, 8, pdf_txt('Total: ' . number_format($this->total_regs) . ' registros'), 0, 0, 'R');

        // ── Banda de filtros activos ──
        if ($this->filtros_txt) {
            $this->SetFillColor(...$this->cGrisClaro);
            $this->Rect(0, 32, 297, 8, 'F');
            $this->SetDrawColor(...$this->cGrisBorde);
            $this->SetLineWidth(0.3);
            $this->Line(0, 40, 297, 40);
            $this->SetTextColor(...$this->cTextoSuave);
            $this->SetFont('Arial', 'I', 7);
            $this->SetXY(6, 33);
            $this->Cell(285, 6, pdf_txt('Filtros: ' . $this->filtros_txt), 0, 0, 'L');
            $this->SetY(42);
        } else {
            $this->SetY(33);
        }

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
        $this->Cell(0, 4, pdf_txt('SysInversiones CH Computer  |  Reporte de Auditoria  |  Pagina ' . $this->PageNo() . '  |  Generado el ' . date('d/m/Y H:i:s')), 0, 0, 'C');
    }

    // Cabecera de la tabla de datos
    function tablaCabecera(): void
    {
        $this->SetFillColor(...$this->cAzulOscuro);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 7);
        $this->SetLineWidth(0);

        $cols = $this->getCols();
        foreach ($cols as [$label, $w, $align]) {
            $this->Cell($w, 7, pdf_txt($label), 0, 0, $align, true);
        }
        $this->Ln();
        $this->SetTextColor(...$this->cTexto);
    }

    // Definición de columnas: [label, ancho, alineación]
    private function getCols(): array
    {
        return [
            ['#',          10, 'C'],
            ['Fecha/Hora', 28, 'C'],
            ['Usuario',    38, 'L'],
            ['Modulo',     24, 'C'],
            ['Accion',     22, 'C'],
            ['Campo',      24, 'L'],
            ['Antes',      35, 'L'],
            ['Despues',    35, 'L'],
            ['Descripcion',61, 'L'],
            ['IP',         20, 'C'],
        ];
    }

    // Fila de datos
    function tablaFila(array $r, int $n): void
    {
        $cols   = $this->getCols();
        $esAlt  = ($n % 2 === 0);
        $fillBg = $esAlt ? $this->cAzulPale : $this->cBlanco;

        $this->SetFillColor(...$fillBg);
        $this->SetFont('Arial', '', 7);
        $this->SetLineWidth(0.1);
        $this->SetDrawColor(...$this->cGrisBorde);

        // Calcular altura necesaria (descripción puede ser larga)
        $desc    = mb_substr($r['descripcion'] ?? '', 0, 80);
        $antes   = mb_substr($r['valor_antes'] ?? '', 0, 25);
        $despues = mb_substr($r['valor_nuevo'] ?? '', 0, 25);

        $valores = [
            $r['id'],
            date('d/m/y H:i', strtotime($r['fecha'])),
            mb_substr($r['nombre_usuario'] ?? '—', 0, 22),
            ucfirst($r['modulo']),
            ucfirst(str_replace('_', ' ', $r['accion'])),
            mb_substr($r['campo'] ?? '—', 0, 16),
            $antes  ?: '—',
            $despues ?: '—',
            $desc   ?: '—',
            $r['ip'] ?? '—',
        ];

        $h = 5.5;
        foreach ($cols as $i => [$label, $w, $align]) {
            $this->Cell($w, $h, pdf_txt($valores[$i]), 'B', 0, $align, true);
        }
        $this->Ln();
    }
}

// ── Instanciar y generar PDF ──────────────────────────────────────────────────
$pdf = new AuditoriaPDF('L', 'mm', 'A4'); // Landscape A4
$pdf->empresa     = $empresa;
$pdf->total_regs  = count($registros);
$pdf->filtros_txt = implode('   |   ', $filtros_activos);
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 14);
$pdf->SetTitle('Auditoria del Sistema');
$pdf->SetAuthor('SysInversiones CH Computer');
$pdf->AddPage();

// Cabecera de tabla
$pdf->tablaCabecera();

// Filas
$n = 1;
foreach ($registros as $r) {
    // Nueva página si queda poco espacio
    if ($pdf->GetY() > 188) {
        $pdf->AddPage();
        $pdf->tablaCabecera();
    }
    $pdf->tablaFila($r, $n);
    $n++;
}

// ── Resumen final ─────────────────────────────────────────────────────────────
$pdf->Ln(4);
$pdf->SetFillColor(15, 58, 138);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(0, 7, pdf_txt('  Total de registros exportados: ' . number_format(count($registros)) . '   |   Generado el ' . date('d/m/Y H:i:s')), 0, 1, 'L', true);

ob_end_clean();
$filename = 'auditoria_' . date('Ymd_His') . '.pdf';
$pdf->Output('D', $filename);
