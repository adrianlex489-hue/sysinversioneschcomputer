<?php
// ============================================================
// modules/personas/ajax_clientes_import.php | SysInversiones CH Computer
// Importación masiva de clientes desde Excel (.xlsx / .xls / .csv)
// Soporta: personas naturales (clientes_natural)
//          personas jurídicas (clientes_empresa)
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

// ── Parámetros ────────────────────────────────────────────────────────────────
$tipo = $_POST['tipo'] ?? '';   // 'natural' | 'empresa'
if (!in_array($tipo, ['natural', 'empresa'])) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'error' => 'Tipo de cliente no válido.']);
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

// ══════════════════════════════════════════════════════════════════════════════
// LEER FILAS DEL ARCHIVO
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Lee un .xlsx nativo (OOXML) sin librerías externas.
 * Devuelve array de arrays con los valores de cada celda.
 */
function leerXlsx(string $file): array
{
    $rows = [];
    $zip  = new ZipArchive();
    if ($zip->open($file) !== true) return $rows;

    // Strings compartidos
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $ss = new SimpleXMLElement($ssXml);
        foreach ($ss->si as $si) {
            // Puede ser <t> directo o varios <r><t>
            $text = '';
            if (isset($si->t)) {
                $text = (string)$si->t;
            } else {
                foreach ($si->r as $r) {
                    $text .= (string)$r->t;
                }
            }
            $sharedStrings[] = $text;
        }
    }

    // Hoja 1
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false) return $rows;

    $sheet = new SimpleXMLElement($sheetXml);
    $ns    = $sheet->getNamespaces(true);
    $def   = $ns[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    foreach ($sheet->sheetData->row as $row) {
        $rowData = [];
        $maxCol  = 0;

        foreach ($row->c as $cell) {
            $ref  = (string)$cell['r'];
            // Extraer columna (letras) y convertir a índice 0-based
            preg_match('/^([A-Z]+)(\d+)$/', $ref, $m);
            $colLetters = $m[1] ?? 'A';
            $colIdx = 0;
            for ($i = 0; $i < strlen($colLetters); $i++) {
                $colIdx = $colIdx * 26 + (ord($colLetters[$i]) - ord('A') + 1);
            }
            $colIdx--; // 0-based

            $t = (string)$cell['t'];
            $v = isset($cell->v) ? (string)$cell->v : '';

            if ($t === 's') {
                // Shared string
                $val = $sharedStrings[(int)$v] ?? '';
            } elseif ($t === 'inlineStr') {
                $val = isset($cell->is->t) ? (string)$cell->is->t : '';
            } else {
                $val = $v;
            }

            // Rellenar huecos con vacío
            while (count($rowData) <= $colIdx) $rowData[] = '';
            $rowData[$colIdx] = trim($val);
            if ($colIdx > $maxCol) $maxCol = $colIdx;
        }
        $rows[] = $rowData;
    }
    return $rows;
}

/**
 * Lee un .csv con detección de separador (coma o punto y coma).
 */
function leerCsv(string $file): array
{
    $rows = [];
    $handle = fopen($file, 'r');
    if (!$handle) return $rows;

    // Detectar BOM UTF-8
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);

    // Detectar separador leyendo primera línea
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

// ── Leer según extensión ──────────────────────────────────────────────────────
try {
    if ($ext === 'csv') {
        $allRows = leerCsv($tmpFile);
    } else {
        // xlsx y xls (xls se intenta como xlsx; si falla se trata como CSV)
        $allRows = leerXlsx($tmpFile);
        if (empty($allRows) && $ext === 'xls') {
            $allRows = leerCsv($tmpFile);
        }
    }
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

// ══════════════════════════════════════════════════════════════════════════════
// DETECTAR FILA DE CABECERA Y MAPEAR COLUMNAS
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Normaliza un texto para comparación: minúsculas, sin tildes, sin espacios extra.
 */
function norm(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = str_replace(
        ['á','é','í','ó','ú','ü','ñ','à','è','ì','ò','ù'],
        ['a','e','i','o','u','u','n','a','e','i','o','u'],
        $s
    );
    return preg_replace('/\s+/', ' ', $s);
}

// Columnas esperadas por tipo
$mapNatural = [
    'nombres'             => ['nombres', 'nombre'],
    'apellido_paterno'    => ['apellido paterno', 'ap paterno', 'paterno', 'apellido_paterno'],
    'apellido_materno'    => ['apellido materno', 'ap materno', 'materno', 'apellido_materno'],
    'tipo_documento'      => ['tipo documento', 'tipo doc', 'tipo_documento', 'tipodoc'],
    'documento_identidad' => ['n documento', 'nro documento', 'numero documento', 'documento', 'dni', 'documento_identidad', 'n° documento', 'no documento'],
    'telefono'            => ['telefono', 'teléfono', 'tel', 'celular'],
    'email'               => ['email', 'correo', 'e-mail'],
    'direccion'           => ['direccion', 'dirección', 'dir'],
];

$mapEmpresa = [
    'razon_social' => ['razon social', 'razón social', 'razon_social', 'empresa', 'nombre empresa'],
    'ruc'          => ['ruc'],
    'telefono'     => ['telefono', 'teléfono', 'tel', 'celular'],
    'email'        => ['email', 'correo', 'e-mail'],
    'direccion'    => ['direccion', 'dirección', 'dir'],
];

$mapCols = ($tipo === 'natural') ? $mapNatural : $mapEmpresa;

// Buscar fila de cabecera (primeras 10 filas)
$headerRow  = -1;
$colIndices = [];   // campo => índice de columna

for ($i = 0; $i < min(10, count($allRows)); $i++) {
    $row = $allRows[$i];
    $found = [];
    foreach ($row as $ci => $cell) {
        $cn = norm($cell);
        foreach ($mapCols as $campo => $aliases) {
            if (in_array($cn, $aliases)) {
                $found[$campo] = $ci;
            }
        }
    }
    // Consideramos cabecera si encontramos al menos 2 columnas clave
    $minCols = ($tipo === 'natural') ? 2 : 2;
    if (count($found) >= $minCols) {
        $headerRow  = $i;
        $colIndices = $found;
        break;
    }
}

// Si no encontró cabecera, intentar mapeo posicional por defecto
if ($headerRow === -1) {
    if ($tipo === 'natural') {
        // Orden esperado: nombres, apellido_paterno, apellido_materno, tipo_documento, documento_identidad, telefono, email, direccion
        $defaults = ['nombres','apellido_paterno','apellido_materno','tipo_documento','documento_identidad','telefono','email','direccion'];
    } else {
        // Orden esperado: razon_social, ruc, telefono, email, direccion
        $defaults = ['razon_social','ruc','telefono','email','direccion'];
    }
    foreach ($defaults as $ci => $campo) {
        $colIndices[$campo] = $ci;
    }
    $headerRow = 0; // empezar desde la primera fila (sin cabecera)
}

// ══════════════════════════════════════════════════════════════════════════════
// PROCESAR FILAS DE DATOS
// ══════════════════════════════════════════════════════════════════════════════

$get = function(array $row, string $campo) use ($colIndices): string {
    if (!isset($colIndices[$campo])) return '';
    return trim($row[$colIndices[$campo]] ?? '');
};

$insertados  = 0;
$omitidos    = 0;
$errores     = [];
$previsuales = [];   // primeras 5 filas para mostrar en modal

$dataRows = array_slice($allRows, $headerRow + 1);

// Preparar statements
if ($tipo === 'natural') {
    $stCheck  = $pdo->prepare("SELECT COUNT(*) FROM clientes_natural WHERE documento_identidad = ?");
    $stInsert = $pdo->prepare(
        "INSERT INTO clientes_natural
         (nombres, apellido_paterno, apellido_materno, tipo_documento, documento_identidad,
          telefono, email, direccion, estado_cliente, fecha_registro)
         VALUES (?,?,?,?,?,?,?,?,1,NOW())"
    );
} else {
    $stCheck  = $pdo->prepare("SELECT COUNT(*) FROM clientes_empresa WHERE ruc = ?");
    $stInsert = $pdo->prepare(
        "INSERT INTO clientes_empresa
         (razon_social, ruc, telefono, email, direccion, estado_cliente, fecha_registro)
         VALUES (?,?,?,?,?,1,NOW())"
    );
}

foreach ($dataRows as $lineNum => $row) {
    // Ignorar filas completamente vacías
    $rowStr = implode('', $row);
    if (trim($rowStr) === '') continue;

    // Ignorar filas de sección/comentario (empiezan con ===)
    $firstCell = trim($row[0] ?? '');
    if (str_starts_with($firstCell, '===') || str_starts_with($firstCell, '#')) continue;

    $lineReal = $headerRow + 2 + $lineNum; // número de fila real en el archivo

    try {
        if ($tipo === 'natural') {
            $nombres   = strtoupper($get($row, 'nombres'));
            $ap        = strtoupper($get($row, 'apellido_paterno'));
            $am        = strtoupper($get($row, 'apellido_materno')) ?: null;
            $tipodoc   = strtoupper($get($row, 'tipo_documento')) ?: 'DNI';
            $doc       = $get($row, 'documento_identidad');
            $tel       = $get($row, 'telefono') ?: null;
            $email     = $get($row, 'email') ?: null;
            $dir       = $get($row, 'direccion') ?: null;

            // Validaciones mínimas
            if (empty($nombres) && empty($ap) && empty($doc)) continue; // fila vacía
            if (empty($nombres)) { $errores[] = "Fila $lineReal: Nombres vacío."; $omitidos++; continue; }
            if (empty($ap))      { $errores[] = "Fila $lineReal: Apellido paterno vacío."; $omitidos++; continue; }
            if (empty($doc))     { $errores[] = "Fila $lineReal: Documento de identidad vacío."; $omitidos++; continue; }

            // Verificar duplicado
            $stCheck->execute([$doc]);
            if ($stCheck->fetchColumn() > 0) {
                $errores[] = "Fila $lineReal: Documento \"$doc\" ya existe — omitido.";
                $omitidos++;
                continue;
            }

            $stInsert->execute([$nombres, $ap, $am, $tipodoc, $doc, $tel, $email, $dir]);
            $insertados++;

            if (count($previsuales) < 5) {
                $previsuales[] = [
                    'nombre'    => "$ap $am, $nombres",
                    'documento' => "$tipodoc: $doc",
                    'telefono'  => $tel ?? '—',
                    'email'     => $email ?? '—',
                ];
            }

        } else {
            $rs  = strtoupper($get($row, 'razon_social'));
            $ruc = $get($row, 'ruc');
            $tel = $get($row, 'telefono') ?: null;
            $email = $get($row, 'email') ?: null;
            $dir = $get($row, 'direccion') ?: null;

            if (empty($rs) && empty($ruc)) continue;
            if (empty($rs))  { $errores[] = "Fila $lineReal: Razón social vacía."; $omitidos++; continue; }
            if (empty($ruc)) { $errores[] = "Fila $lineReal: RUC vacío."; $omitidos++; continue; }
            if (!preg_match('/^\d{11}$/', $ruc)) {
                $errores[] = "Fila $lineReal: RUC \"$ruc\" inválido (debe tener 11 dígitos) — omitido.";
                $omitidos++;
                continue;
            }

            $stCheck->execute([$ruc]);
            if ($stCheck->fetchColumn() > 0) {
                $errores[] = "Fila $lineReal: RUC \"$ruc\" ya existe — omitido.";
                $omitidos++;
                continue;
            }

            $stInsert->execute([$rs, $ruc, $tel, $email, $dir]);
            $insertados++;

            if (count($previsuales) < 5) {
                $previsuales[] = [
                    'nombre'    => $rs,
                    'documento' => "RUC: $ruc",
                    'telefono'  => $tel ?? '—',
                    'email'     => $email ?? '—',
                ];
            }
        }

    } catch (PDOException $e) {
        // Traducir errores técnicos de BD a mensajes comprensibles
        if ($e->getCode() == 23000) {
            $msg = $e->getMessage();
            if ($tipo === 'natural') {
                if (stripos($msg, 'documento_identidad') !== false || stripos($msg, 'documento') !== false) {
                    $motivo = "El documento \"$doc\" ya está registrado en el sistema.";
                } elseif (stripos($msg, 'email') !== false) {
                    $emailVal = $email ?? '(vacío)';
                    $motivo = "El email \"$emailVal\" ya está en uso. Usa un email diferente o déjalo vacío.";
                } else {
                    $motivo = "Ya existe un cliente con alguno de estos datos (documento o email duplicado).";
                }
            } else {
                if (stripos($msg, 'ruc') !== false) {
                    $motivo = "El RUC \"$ruc\" ya está registrado en el sistema.";
                } elseif (stripos($msg, 'email') !== false) {
                    $emailVal = $email ?? '(vacío)';
                    $motivo = "El email \"$emailVal\" ya está en uso. Usa un email diferente o déjalo vacío.";
                } else {
                    $motivo = "Ya existe una empresa con alguno de estos datos (RUC o email duplicado).";
                }
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
    'ok'          => true,
    'insertados'  => $insertados,
    'omitidos'    => $omitidos,
    'errores'     => array_slice($errores, 0, 20), // máximo 20 errores en respuesta
    'total_errores' => count($errores),
    'previsuales' => $previsuales,
    'tipo'        => $tipo,
]);
