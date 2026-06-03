<?php
// ============================================================
// orden_trabajo_pdf.php | SysInversiones CH Computer 2026
// Genera PDF de Orden de Trabajo en formato ticket 80mm
// Uso: ?id_orden=5          → abre en navegador (imprimir)
//      ?id_orden=5&download=1 → descarga el PDF
// ============================================================
ob_start();

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'libs/fpdf.php';
require_once __DIR__ . '/../configuracion_empresa/empresa_helper.php';

if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_TECNICO'))       define('ROL_TECNICO', 3);
if (!defined('ROL_ASESOR_COMERCIAL'))        define('ROL_ASESOR_COMERCIAL', 2);
verificar_acceso([ROL_ADMINISTRADOR, ROL_TECNICO, ROL_ASESOR_COMERCIAL]);

$id_orden = (int)($_GET['id_orden'] ?? 0);
$download = isset($_GET['download']) && $_GET['download'] == '1';
if (!$id_orden) { ob_end_clean(); die('ID de orden no especificado.'); }

// ── Conversión UTF-8 → ISO-8859-1 para FPDF ──────────────────────────────────
if (!function_exists('pdf_txt')) {
    function pdf_txt(string $t): string {
        // Reemplazos manuales de caracteres especiales comunes en español
        $map = [
            'Á'=>chr(193),'É'=>chr(201),'Í'=>chr(205),'Ó'=>chr(211),'Ú'=>chr(218),
            'á'=>chr(225),'é'=>chr(233),'í'=>chr(237),'ó'=>chr(243),'ú'=>chr(250),
            'Ñ'=>chr(209),'ñ'=>chr(241),
            'Ü'=>chr(220),'ü'=>chr(252),
            '¿'=>chr(191),'¡'=>chr(161),
            '°'=>chr(176),'º'=>chr(186),'ª'=>chr(170),
            '–'=>'-','—'=>'-',
        ];
        return strtr($t, $map);
    }
}

// ── Datos de la orden ─────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT o.*,
           CASE o.tipo_cliente
               WHEN 'empresa' THEN COALESCE(ce.razon_social,'Sin nombre')
               ELSE TRIM(CONCAT_WS(', ',
                   NULLIF(TRIM(CONCAT_WS(' ', cn.apellido_paterno, cn.apellido_materno)),''),
                   NULLIF(TRIM(cn.nombres),'')
               ))
           END AS cliente_nombre,
           o.tipo_cliente,
           CASE o.tipo_cliente WHEN 'empresa' THEN ce.telefono ELSE cn.telefono END AS telefono,
           e.tipo AS equipo_tipo, e.marca, e.modelo, e.numero_serie, e.contrasena_equipo
    FROM ordenes_servicio o
    LEFT JOIN clientes_natural cn ON cn.id_cliente_natural = o.id_cliente AND o.tipo_cliente = 'natural'
    LEFT JOIN clientes_empresa ce ON ce.id_cliente_empresa = o.id_cliente AND o.tipo_cliente = 'empresa'
    JOIN equipos  e ON e.id_equipo  = o.id_equipo
    WHERE o.id_orden = ?
");
$stmt->execute([$id_orden]);
$orden = $stmt->fetch();
if (!$orden) { ob_end_clean(); die('Orden no encontrada.'); }

$empresa = getEmpresa($pdo);

// ── Clase PDF Ticket 80mm ─────────────────────────────────────────────────────
class OrdenTrabajoPDF extends FPDF
{
    public float $W = 74; // ancho útil (80mm - 3mm margen c/lado)

    // Línea separadora punteada
    public function separador(): void {
        $y = $this->GetY() + 1;
        $this->SetDrawColor(160, 160, 160);
        $this->SetLineWidth(0.2);
        // Línea punteada simulada con guiones
        $this->SetFont('Arial', '', 6);
        $this->SetTextColor(160, 160, 160);
        $guiones = str_repeat('-', 52);
        $this->SetXY(3, $y);
        $this->Cell($this->W, 3, $guiones, 0, 1, 'C');
        $this->SetTextColor(20, 20, 20);
        $this->Ln(1);
    }

    public function centrar(string $txt, int $sz, string $st = '', float $h = 5): void {
        $this->SetFont('Arial', $st, $sz);
        $this->Cell($this->W, $h, pdf_txt($txt), 0, 1, 'C');
    }

    // Línea etiqueta: valor
    public function linea_dato(string $etiqueta, string $valor, int $sz = 8): void {
        $this->SetFont('Arial', '', $sz);
        $texto = pdf_txt($etiqueta . $valor);
        // Usar MultiCell para valores largos
        $this->MultiCell($this->W, 4.5, $texto, 0, 'L');
    }

    // Título de sección en negrita
    public function titulo_seccion(string $txt): void {
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($this->W, 5, pdf_txt($txt), 0, 1, 'L');
    }

    // Recuadro con texto centrado (para contraseña)
    public function recuadro(string $label, string $valor = ''): void {
        $this->Ln(1);
        $this->SetFont('Arial', '', 7);
        $this->SetDrawColor(100, 100, 100);
        $this->SetLineWidth(0.3);
        $y = $this->GetY();
        $this->Rect(3, $y, $this->W, 8);
        $this->SetXY(3, $y + 1);
        $this->SetFont('Arial', '', 7);
        $this->Cell($this->W, 3, pdf_txt($label), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($this->W, 3, pdf_txt($valor), 0, 1, 'C');
        $this->Ln(2);
    }
}

// ── Calcular alto dinámico ────────────────────────────────────────────────────
$problema_txt = $orden['problema_reportado'] ?? '';
$lineas_prob  = max(1, ceil(strlen(pdf_txt($problema_txt)) / 38));
$alto = 185 + ($lineas_prob * 4);
if (!empty($orden['contrasena_equipo'])) $alto += 14;

$pdf = new OrdenTrabajoPDF('P', 'mm', [80, $alto]);
$pdf->SetMargins(3, 4, 3);
$pdf->SetAutoPageBreak(false, 4);
$pdf->AddPage();
$pdf->SetTextColor(20, 20, 20);

// ── LOGO ──────────────────────────────────────────────────────────────────────
$logo_path = $_SERVER['DOCUMENT_ROOT'] . ($empresa['logo'] ?? '');
if (!empty($empresa['logo']) && file_exists($logo_path)) {
    $info = @getimagesize($logo_path);
    if ($info) {
        $max_w  = 32;
        $draw_w = $max_w;
        $draw_h = $max_w * ($info[1] / $info[0]);
        $x_logo = (80 - $draw_w) / 2;
        $pdf->Image($logo_path, $x_logo, $pdf->GetY(), $draw_w, $draw_h);
        $pdf->Ln($draw_h + 2);
    }
}

// ── Datos empresa ─────────────────────────────────────────────────────────────
$pdf->centrar($empresa['razon_social'] ?? 'INVERSIONES CH COMPUTER S.R.L.', 9, 'B', 5);
if (!empty($empresa['direccion']))
    $pdf->centrar($empresa['direccion'], 7, '', 4);
if (!empty($empresa['telefono']))
    $pdf->centrar($empresa['telefono'], 7, '', 4);
if (!empty($empresa['web']))
    $pdf->centrar($empresa['web'], 7, '', 4);
if (!empty($empresa['email']))
    $pdf->centrar($empresa['email'], 7, '', 4);

// Fecha/hora de impresión
$pdf->centrar(date('d/m/Y H:i'), 8, '', 4);
$pdf->Ln(1);

// ── Título orden ──────────────────────────────────────────────────────────────
$pdf->centrar('ORDEN DE TRABAJO', 10, 'B', 6);
$num_orden = 'N° ' . str_pad($id_orden, 6, '0', STR_PAD_LEFT);
$pdf->centrar($num_orden, 11, 'B', 6);
$pdf->Ln(1);

// ── INFORMACIÓN DEL CLIENTE ───────────────────────────────────────────────────
$pdf->separador();
$pdf->titulo_seccion('INFORMACIÓN DEL CLIENTE');
$pdf->linea_dato('Cliente: ', strtoupper($orden['cliente_nombre']));
$pdf->linea_dato('Teléfono: ', $orden['telefono'] ?? '');

// ── INFORMACIÓN DE LA ORDEN ───────────────────────────────────────────────────
$pdf->separador();
$pdf->titulo_seccion('INFORMACIÓN DE LA ORDEN');
$fecha_rec = $orden['fecha_recepcion']
    ? date('d/m/Y H:i', strtotime($orden['fecha_recepcion']))
    : date('d/m/Y H:i');
$pdf->linea_dato('Fecha/hora ingreso: ', $fecha_rec);
$prioridad_label = strtoupper($orden['prioridad'] ?? 'NORMAL');
$pdf->SetFont('Arial', '', 8);
$pdf->SetXY(3, $pdf->GetY());
$pdf->Cell(22, 4.5, pdf_txt('Prioridad: '), 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell($pdf->W - 22, 4.5, pdf_txt($prioridad_label), 0, 1, 'L');

$fecha_prom = $orden['fecha_entrega_estimada']
    ? date('d/m/Y', strtotime($orden['fecha_entrega_estimada']))
    : '---';
$pdf->linea_dato('Fecha Prometida: ', $fecha_prom);
$pdf->linea_dato('Desperfecto: ', $orden['problema_reportado'] ?? '');

// ── INFORMACIÓN DEL EQUIPO ────────────────────────────────────────────────────
$pdf->separador();
$pdf->titulo_seccion('INFORMACIÓN DEL EQUIPO');
$serie = !empty($orden['numero_serie']) ? strtoupper($orden['numero_serie']) : 'GENERICO';
$pdf->linea_dato('Serie/IMEI: ', $serie);
$marca_modelo = trim(
    strtoupper($orden['marca'] ?? '') . ', ' .
    strtoupper($orden['equipo_tipo'] ?? '') . ', ' .
    strtoupper($orden['modelo'] ?? '')
, ', ');
$pdf->linea_dato('Marca y modelo: ', $marca_modelo);

// ── CONTRASEÑA (recuadro) ─────────────────────────────────────────────────────
$pdf->separador();
$clave = trim($orden['contrasena_equipo'] ?? '');
$pdf->recuadro('Contraseña', $clave);

// ── PIE ───────────────────────────────────────────────────────────────────────
$pdf->separador();
$pie = $empresa['pie_comprobante'] ?? 'SERVICIO TÉCNICO ESPECIALIZADO';
$pdf->SetFont('Arial', 'I', 7);
$pdf->SetTextColor(100, 100, 100);
$pdf->centrar($pie, 7, 'I', 4);

// ── Salida ────────────────────────────────────────────────────────────────────
ob_end_clean();
$nombre_archivo = 'orden_trabajo_' . str_pad($id_orden, 6, '0', STR_PAD_LEFT) . '.pdf';
$modo = $download ? 'D' : 'I';
$pdf->Output($modo, $nombre_archivo);
