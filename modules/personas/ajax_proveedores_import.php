<?php
// ============================================================
// modules/personas/ajax_proveedores_import.php
// Importación masiva de proveedores desde Excel/CSV
// Tabla: proveedores (razon_social, ruc, telefono, email,
//                     direccion, contacto, estado, fecha_registro)
// ============================================================
ob_start();

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

if (!defined('ROL_ADMINISTRADOR'))    define('ROL_ADMINISTRADOR', 1);
if (!defined('ROL_ASESOR_COMERCIAL')) define('ROL_ASESOR_COMERCIAL', 2);
if (!defined('ROL_TECNICO'))          define('ROL_TECNICO', 3);
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);

header('Content-Type: application/json; charset=utf-8');

if (!isset($pdo) || !($pdo instanceof PDO)) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => 'Sin conexión a la base de datos.']);
    exit;
}

// ── Archivo subido ────────────────────────────────────────────────────────────
if (empty($_FILES['archivo']['tmp_name']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => 'No se recibió ningún archivo o hubo un error al subirlo.']);
    exit;
}

$tmpFile  = $_FILES['archivo']['tmp_name'];
$origName = $_FILES['archivo']['name'];
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => 'Formato no soportado. Use .xlsx, .xls o .csv']);
    exit;
}

// ── Leer XLSX nativo ──────────────────────────────────────────────────────────
function leerXlsx(string $file): array
{
    $rows = [];
    $zip  = new ZipArchive();
    if ($zip->open($file) !== true) return $rows;

    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ss = new SimpleXMLElement($ssXml);
        foreach ($ss->si as $si) {
            $text = '';
            if (isset($si->t)) { $text = (string)$si->t; }
            else { foreach ($si->r as $r) { $text .= (string)$r->t; } }
            $sharedStrings[] = $text;
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) return $rows;

    $sheet = new SimpleXMLElement($sheetXml);
    foreach ($sheet->sheetData->row as $row) {
        $rowData = [];
        foreach ($row->c as $cell) {
            preg_match('/^([A-Z]+)(\d+)$/', (string)$cell['r'], $m);
            $colLetters = $m[1] ?? 'A';
            $colIdx = 0;
            for ($i = 0; $i < strlen($colLetters); $i++) {
                $colIdx = $colIdx * 26 + (ord($colLetters[$i]) - ord('A') + 1);
            }
            $colIdx--;
            $t = (string)$cell['t'];
            $v = isset($cell->v) ? (string)$cell->v : '';
            if ($t === 's')         { $val = $sharedStrings[(int)$v] ?? ''; }
            elseif ($t === 'inlineStr') { $val = isset($cell->is->t) ? (string)$cell->is->t : ''; }
            else                    { $val = $v; }
            while (count($rowData) <= $colIdx) $rowData[] = '';
            $rowData[$colIdx] = trim($val);
        }
        $rows[] = $rowData;
    }
    return $rows;
}

// ── Leer CSV ──────────────────────────────────────────────────────────────────
function leerCsv(string $file): array
{
    $rows = [];
    $handle = fopen($file, 'r');
    if (!$handle) return $rows;
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    $firstLine = fgets($handle);
    rewind($handle);
    if ($bom === "\xEF\xBB\xBF") fread($handle, 3);
    $sep = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';
    while (($data = fgetcsv($handle, 2000, $sep)) !== false) {
        $rows[] = array_map('trim', $data);
    }
    fclose($handle);
    return $rows;
}

// ── Leer archivo ──────────────────────────────────────────────────────────────
try {
    $allRows = ($ext === 'csv') ? leerCsv($tmpFile) : leerXlsx($tmpFile);
    if (empty($allRows) && $ext === 'xls') $allRows = leerCsv($tmpFile);
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => 'Error al leer el archivo: ' . $e->getMessage()]);
    exit;
}

if (empty($allRows)) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => 'El archivo está vacío o no se pudo leer.']);
    exit;
}

// ── Normalizar texto para comparación ────────────────────────────────────────
function norm(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = str_replace(
        ['á','é','í','ó','ú','ü','ñ','à','è','ì','ò','ù'],
        ['a','e','i','o','u','u','n','a','e','i','o','u'], $s
    );
    return preg_replace('/\s+/', ' ', $s);
}

// ── Mapa de columnas ──────────────────────────────────────────────────────────
$mapCols = [
    'razon_social' => ['razon social', 'razon_social', 'razón social', 'empresa', 'nombre empresa', 'proveedor'],
    'ruc'          => ['ruc'],
    'telefono'     => ['telefono', 'teléfono', 'tel', 'celular'],
    'email'        => ['email', 'correo', 'e-mail'],
    'direccion'    => ['direccion', 'dirección', 'dir'],
    'contacto'     => ['contacto', 'persona contacto', 'nombre contacto', 'representante'],
];

// ── Detectar fila de cabecera ─────────────────────────────────────────────────
$headerRow  = -1;
$colIndices = [];

for ($i = 0; $i < min(10, count($allRows)); $i++) {
    $found = [];
    foreach ($allRows[$i] as $ci => $cell) {
        $cn = norm($cell);
        foreach ($mapCols as $campo => $aliases) {
            if (in_array($cn, $aliases)) $found[$campo] = $ci;
        }
    }
    if (count($found) >= 2) { $headerRow = $i; $colIndices = $found; break; }
}

// Mapeo posicional por defecto si no se encontró cabecera
if ($headerRow === -1) {
    $defaults = ['razon_social','ruc','telefono','email','direccion','contacto'];
    foreach ($defaults as $ci => $campo) $colIndices[$campo] = $ci;
    $headerRow = 0;
}

// ── Procesar filas ────────────────────────────────────────────────────────────
$get = function(array $row, string $campo) use ($colIndices): string {
    if (!isset($colIndices[$campo])) return '';
    return trim($row[$colIndices[$campo]] ?? '');
};

$insertados = 0;
$omitidos   = 0;
$errores    = [];

$stCheck  = $pdo->prepare("SELECT COUNT(*) FROM proveedores WHERE ruc = ?");
$stInsert = $pdo->prepare(
    "INSERT INTO proveedores
     (razon_social, ruc, telefono, email, direccion, contacto, estado, fecha_registro)
     VALUES (?,?,?,?,?,?,1,NOW())"
);

$dataRows = array_slice($allRows, $headerRow + 1);

foreach ($dataRows as $lineNum => $row) {
    $rowStr    = implode('', $row);
    $firstCell = trim($row[0] ?? '');
    if (trim($rowStr) === '') continue;
    if (str_starts_with($firstCell, '===') || str_starts_with($firstCell, '#')) continue;

    $lineReal = $headerRow + 2 + $lineNum;

    try {
        $rs       = strtoupper($get($row, 'razon_social'));
        $ruc      = $get($row, 'ruc');
        $tel      = $get($row, 'telefono')  ?: null;
        $email    = $get($row, 'email')     ?: null;
        $dir      = $get($row, 'direccion') ?: null;
        $contacto = $get($row, 'contacto')  ?: null;

        if (empty($rs) && empty($ruc)) continue;
        if (empty($rs))  { $errores[] = "Fila $lineReal: Razón social vacía.";  $omitidos++; continue; }
        if (empty($ruc)) { $errores[] = "Fila $lineReal: RUC vacío.";           $omitidos++; continue; }
        if (!preg_match('/^\d{11}$/', $ruc)) {
            $errores[] = "Fila $lineReal: RUC \"$ruc\" inválido (debe tener 11 dígitos).";
            $omitidos++; continue;
        }

        $stCheck->execute([$ruc]);
        if ($stCheck->fetchColumn() > 0) {
            $errores[] = "Fila $lineReal: RUC \"$ruc\" ya existe — omitido.";
            $omitidos++; continue;
        }

        $stInsert->execute([$rs, $ruc, $tel, $email, $dir, $contacto]);
        $insertados++;

    } catch (PDOException $e) {
        // Traducir errores técnicos de BD a mensajes comprensibles
        if ($e->getCode() == 23000) {
            $msg = $e->getMessage();
            if (stripos($msg, 'ruc') !== false) {
                $motivo = "El RUC \"$ruc\" ya está registrado en el sistema.";
            } elseif (stripos($msg, 'email') !== false) {
                $emailVal = $email ?? '(vacío)';
                $motivo = "El email \"$emailVal\" ya está en uso por otro proveedor. Usa un email diferente o déjalo vacío.";
            } else {
                $motivo = "Ya existe un proveedor con alguno de estos datos (RUC o email duplicado).";
            }
            $errores[] = "Fila $lineReal: Omitido — $motivo";
        } else {
            $errores[] = "Fila $lineReal: No se pudo guardar el registro. Contacta al administrador del sistema.";
        }
        $omitidos++;
    }
}

ob_end_clean();
echo json_encode([
    'ok'           => true,
    'insertados'   => $insertados,
    'omitidos'     => $omitidos,
    'errores'      => array_slice($errores, 0, 20),
    'total_errores'=> count($errores),
]);
