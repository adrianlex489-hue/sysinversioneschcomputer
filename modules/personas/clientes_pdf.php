<?php
// ============================================================
// modules/personas/clientes_pdf.php | SysInversiones CH Computer
// Genera PDF A4 del reporte de Clientes con FPDF
// ============================================================
ob_start();

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';
require_once $ruta_base . 'libs/fpdf.php';
require_once __DIR__ . '/../configuracion_empresa/empresa_helper.php';

if (!defined('ROL_ADMINISTRADOR'))    define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR_COMERCIAL')) define('ROL_ASESOR_COMERCIAL', 2);
if (!defined('ROL_TECNICO'))          define('ROL_TECNICO', 3);
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
$tipo = $_GET['tipo'] ?? 'all'; // natural | empresa | all

// ── Obtener datos — columnas exactas de cada tabla ───────────────────────────
$nat_activos = $nat_inactivos = $emp_activos = $emp_inactivos = [];

if ($tipo !== 'empresa') {
    $nat_activos   = $pdo->query(
        "SELECT id_cliente_natural, nombres, apellido_paterno, apellido_materno,
                tipo_documento, documento_identidad, telefono, email, direccion,
                estado_cliente, fecha_registro
         FROM clientes_natural WHERE estado_cliente=1 ORDER BY apellido_paterno ASC, nombres ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
    $nat_inactivos = $pdo->query(
        "SELECT id_cliente_natural, nombres, apellido_paterno, apellido_materno,
                tipo_documento, documento_identidad, telefono, email, direccion,
                estado_cliente, fecha_registro
         FROM clientes_natural WHERE estado_cliente=0 ORDER BY apellido_paterno ASC, nombres ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}
if ($tipo !== 'natural') {
    $emp_activos   = $pdo->query(
        "SELECT id_cliente_empresa, razon_social, ruc,
                telefono, email, direccion, estado_cliente, fecha_registro
         FROM clientes_empresa WHERE estado_cliente=1 ORDER BY razon_social ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
    $emp_inactivos = $pdo->query(
        "SELECT id_cliente_empresa, razon_social, ruc,
                telefono, email, direccion, estado_cliente, fecha_registro
         FROM clientes_empresa WHERE estado_cliente=0 ORDER BY razon_social ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

$empresa = getEmpresa($pdo);

$total_nat = count($nat_activos) + count($nat_inactivos);
$total_emp = count($emp_activos) + count($emp_inactivos);
$total_all = $total_nat + $total_emp;

// ── Clase PDF ─────────────────────────────────────────────────────────────────
class ClientesPDF extends FPDF
{
    public array  $empresa    = [];
    public string $tipo       = 'all';
    public int    $total_all  = 0;
    public int    $total_nat  = 0;
    public int    $total_emp  = 0;

    // Paleta corporativa
    private array $cVerde      = [ 26, 122,  74]; // #1a7a4a
    private array $cAzulOscuro = [ 26,  82, 118]; // #1a5276
    private array $cAzulMedio  = [ 37,  99, 235]; // #2563eb
    private array $cNaranja    = [192,  86,  33]; // #c05621
    private array $cNaranjaOsc = [123,  52,  30]; // #7b341e
    private array $cRojo       = [220,  38,  38]; // #dc2626
    private array $cBlanco     = [255, 255, 255];
    private array $cGrisClaro  = [248, 250, 252];
    private array $cGrisBorde  = [226, 232, 240];
    private array $cTexto      = [ 30,  41,  59];
    private array $cTextoSuave = [100, 116, 139];
    private array $cAzulPale   = [239, 246, 255];
    private array $cNarPale    = [255, 237, 213];

    function Header(): void
    {
        $emp = $this->empresa;

        // ── Banda superior verde oscuro ──
        $this->SetFillColor(...$this->cVerde);
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

        // Info empresa
        $this->SetFont('Arial', '', 8);
        $this->SetXY(38, 11);
        $info_emp = 'RUC: ' . ($emp['ruc'] ?? '') . '   |   ' . ($emp['direccion'] ?? '') . ($emp['distrito'] ? ' - ' . $emp['distrito'] : '');
        $this->Cell(160, 5, pdf_txt($info_emp), 0, 0, 'L');

        // Etiqueta "CLIENTES" en la derecha
        $this->SetFillColor(...$this->cAzulMedio);
        $this->Rect(240, 2, 52, 18, 'F');
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY(240, 5);
        $this->Cell(52, 6, 'CLIENTES', 0, 1, 'C');
        $this->SetFont('Arial', '', 7);
        $this->SetXY(240, 11);
        $this->Cell(52, 5, 'GESTION DE CLIENTES', 0, 0, 'C');

        // ── Banda de título del reporte ──
        $this->SetFillColor(...$this->cAzulMedio);
        $this->Rect(0, 22, 297, 10, 'F');
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY(6, 23);
        $this->Cell(180, 8, pdf_txt('Reporte de Clientes — ' . date('d/m/Y H:i')), 0, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $this->SetXY(186, 23);
        $this->Cell(105, 8, pdf_txt('Total: ' . $this->total_all . ' clientes  |  Naturales: ' . $this->total_nat . '  |  Empresas: ' . $this->total_emp), 0, 0, 'R');

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
        $this->Cell(0, 4, pdf_txt('SysInversiones CH Computer  |  Reporte de Clientes  |  Pagina ' . $this->PageNo() . '  |  Generado el ' . date('d/m/Y H:i:s')), 0, 0, 'C');
    }

    // ── Banda de sección (Naturales / Empresas) ───────────────────────────────
    function seccionBanda(string $titulo, string $tipo_sec): void
    {
        $this->Ln(3);
        $color = ($tipo_sec === 'natural') ? $this->cAzulOscuro : $this->cNaranjaOsc;
        $this->SetFillColor(...$color);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 8, pdf_txt('  ' . $titulo), 0, 1, 'L', true);
        $this->SetTextColor(...$this->cTexto);
        $this->Ln(1);
    }

    // ── Cabecera de tabla ─────────────────────────────────────────────────────
    function tablaCabecera(string $tipo_sec): void
    {
        $color = ($tipo_sec === 'natural') ? $this->cAzulOscuro : $this->cNaranjaOsc;
        $this->SetFillColor(...$color);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 7);
        $this->SetLineWidth(0);

        foreach ($this->getColsByTipo($tipo_sec) as [$label, $w, $align]) {
            $this->Cell($w, 7, pdf_txt($label), 0, 0, $align, true);
        }
        $this->Ln();
        $this->SetTextColor(...$this->cTexto);
    }

    // Columnas para Personas Naturales
    // Total: 10+30+26+26+12+22+20+44+54+16+17 = 277 mm (A4 landscape ~277 útil)
    private function getColsNatural(): array
    {
        return [
            ['ID',            10, 'C'],
            ['Apellido Paterno', 30, 'L'],
            ['Apellido Materno', 26, 'L'],
            ['Nombres',       26, 'L'],
            ['Tipo Doc.',     12, 'C'],
            ['N° Documento',  22, 'C'],
            ['Teléfono',      20, 'C'],
            ['Email',         44, 'L'],
            ['Dirección',     54, 'L'],
            ['Estado',        16, 'C'],
            ['Registro',      25, 'C'],
        ];
    }

    // Columnas para Empresas / Jurídicas
    // Total: 10+70+26+20+50+64+16+21 = 277 mm
    private function getColsEmpresa(): array
    {
        return [
            ['ID',            10, 'C'],
            ['Razón Social',  70, 'L'],
            ['RUC',           26, 'C'],
            ['Teléfono',      20, 'C'],
            ['Email',         50, 'L'],
            ['Dirección',     64, 'L'],
            ['Estado',        16, 'C'],
            ['Registro',      21, 'C'],
        ];
    }

    private function getColsByTipo(string $tipo_sec): array
    {
        return ($tipo_sec === 'natural') ? $this->getColsNatural() : $this->getColsEmpresa();
    }

    // ── Fila de datos ─────────────────────────────────────────────────────────
    function tablaFila(array $r, int $n, string $tipo_sec): void
    {
        $cols   = $this->getColsByTipo($tipo_sec);
        $esAlt  = ($n % 2 === 0);
        $fillBg = $esAlt
            ? ($tipo_sec === 'natural' ? $this->cAzulPale : $this->cNarPale)
            : $this->cBlanco;

        $this->SetFillColor(...$fillBg);
        $this->SetFont('Arial', '', 7);
        $this->SetLineWidth(0.1);
        $this->SetDrawColor(...$this->cGrisBorde);

        $estado = ($r['estado_cliente'] == 1) ? 'Activo' : 'Inactivo';
        $fecha  = !empty($r['fecha_registro']) ? date('d/m/Y', strtotime($r['fecha_registro'])) : '—';

        if ($tipo_sec === 'natural') {
            $valores = [
                $r['id_cliente_natural'],
                mb_substr($r['apellido_paterno'] ?? '', 0, 18),
                mb_substr($r['apellido_materno'] ?? '', 0, 16),
                mb_substr($r['nombres'] ?? '', 0, 16),
                $r['tipo_documento'] ?? 'DNI',
                $r['documento_identidad'] ?? '—',
                $r['telefono'] ?? '—',
                mb_substr($r['email'] ?? '—', 0, 26),
                mb_substr($r['direccion'] ?? '—', 0, 32),
                $estado,
                $fecha,
            ];
        } else {
            $valores = [
                $r['id_cliente_empresa'],
                mb_substr($r['razon_social'] ?? '', 0, 42),
                $r['ruc'] ?? '—',
                $r['telefono'] ?? '—',
                mb_substr($r['email'] ?? '—', 0, 30),
                mb_substr($r['direccion'] ?? '—', 0, 38),
                $estado,
                $fecha,
            ];
        }

        $h = 5.5;
        foreach ($cols as $i => [$label, $w, $align]) {
            if ($label === 'Estado') {
                if ($estado === 'Activo') {
                    $this->SetTextColor(22, 101, 52);
                } else {
                    $this->SetTextColor(153, 27, 27);
                }
            }
            $this->Cell($w, $h, pdf_txt($valores[$i]), 'B', 0, $align, true);
            $this->SetTextColor(...$this->cTexto);
        }
        $this->Ln();
    }

    // ── Resumen de sección ────────────────────────────────────────────────────
    function resumenSeccion(int $activos, int $inactivos, string $tipo_sec): void
    {
        $color = ($tipo_sec === 'natural') ? $this->cAzulMedio : $this->cNaranja;
        $this->SetFillColor(...$color);
        $this->SetTextColor(...$this->cBlanco);
        $this->SetFont('Arial', 'B', 7.5);
        $total = $activos + $inactivos;
        $this->Cell(0, 6, pdf_txt('  Subtotal: ' . $total . ' registros   |   Activos: ' . $activos . '   |   Inactivos: ' . $inactivos), 0, 1, 'L', true);
        $this->SetTextColor(...$this->cTexto);
        $this->Ln(2);
    }
}

// ── Instanciar y generar PDF ──────────────────────────────────────────────────
$pdf = new ClientesPDF('L', 'mm', 'A4');
$pdf->empresa   = $empresa;
$pdf->tipo      = $tipo;
$pdf->total_all = $total_all;
$pdf->total_nat = $total_nat;
$pdf->total_emp = $total_emp;
$pdf->SetMargins(6, 6, 6);
$pdf->SetAutoPageBreak(true, 14);
$pdf->SetTitle('Clientes - SysInversiones CH Computer');
$pdf->SetAuthor('SysInversiones CH Computer');
$pdf->AddPage();

// ── PERSONAS NATURALES ────────────────────────────────────────────────────────
if ($tipo !== 'empresa') {

    // Activos
    if (!empty($nat_activos)) {
        $pdf->seccionBanda('PERSONAS NATURALES — ACTIVOS (' . count($nat_activos) . ')', 'natural');
        $pdf->tablaCabecera('natural');
        $n = 1;
        foreach ($nat_activos as $r) {
            if ($pdf->GetY() > 188) { $pdf->AddPage(); $pdf->tablaCabecera('natural'); }
            $pdf->tablaFila($r, $n++, 'natural');
        }
        $pdf->Ln(1);
    }

    // Inactivos
    if (!empty($nat_inactivos)) {
        if ($pdf->GetY() > 160) $pdf->AddPage();
        $pdf->seccionBanda('PERSONAS NATURALES — INACTIVOS (' . count($nat_inactivos) . ')', 'natural');
        $pdf->tablaCabecera('natural');
        $n = 1;
        foreach ($nat_inactivos as $r) {
            if ($pdf->GetY() > 188) { $pdf->AddPage(); $pdf->tablaCabecera('natural'); }
            $pdf->tablaFila($r, $n++, 'natural');
        }
        $pdf->Ln(1);
    }

    $pdf->resumenSeccion(count($nat_activos), count($nat_inactivos), 'natural');
}

// ── EMPRESAS / JURÍDICAS ──────────────────────────────────────────────────────
if ($tipo !== 'natural') {

    if ($tipo === 'all' && $pdf->GetY() > 140) $pdf->AddPage();

    // Activos
    if (!empty($emp_activos)) {
        $pdf->seccionBanda('EMPRESAS / JURIDICAS — ACTIVOS (' . count($emp_activos) . ')', 'empresa');
        $pdf->tablaCabecera('empresa');
        $n = 1;
        foreach ($emp_activos as $r) {
            if ($pdf->GetY() > 188) { $pdf->AddPage(); $pdf->tablaCabecera('empresa'); }
            $pdf->tablaFila($r, $n++, 'empresa');
        }
        $pdf->Ln(1);
    }

    // Inactivos
    if (!empty($emp_inactivos)) {
        if ($pdf->GetY() > 160) $pdf->AddPage();
        $pdf->seccionBanda('EMPRESAS / JURIDICAS — INACTIVOS (' . count($emp_inactivos) . ')', 'empresa');
        $pdf->tablaCabecera('empresa');
        $n = 1;
        foreach ($emp_inactivos as $r) {
            if ($pdf->GetY() > 188) { $pdf->AddPage(); $pdf->tablaCabecera('empresa'); }
            $pdf->tablaFila($r, $n++, 'empresa');
        }
        $pdf->Ln(1);
    }

    $pdf->resumenSeccion(count($emp_activos), count($emp_inactivos), 'empresa');
}

// ── Resumen final total ───────────────────────────────────────────────────────
$pdf->SetFillColor(26, 122, 74);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(0, 7, pdf_txt('  TOTAL GENERAL: ' . $total_all . ' clientes   |   Naturales: ' . $total_nat . '   |   Empresas: ' . $total_emp . '   |   Generado el ' . date('d/m/Y H:i:s')), 0, 1, 'L', true);

ob_end_clean();
$filename = 'clientes_' . date('Ymd_His') . '.pdf';
$pdf->Output('D', $filename);
