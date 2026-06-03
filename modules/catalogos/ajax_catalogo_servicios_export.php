<?php
// ============================================================
// modules/catalogos/ajax_catalogo_servicios_export.php
// Exportación del Catálogo de Servicios — CSV y Excel (.xlsx)
// SysInversiones CH Computer 2026
// ============================================================
ob_start();

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);

if (!isset($pdo) || !($pdo instanceof PDO)) {
    ob_end_clean(); die('Error: Sin conexión a la base de datos.');
}

$formato      = $_GET['exportar']    ?? 'csv';
$filtro_est   = $_GET['estado']      ?? 'all';
$filtro_tipo  = $_GET['tipo']        ?? 'all';
$filtro_equip = $_GET['tipo_equipo'] ?? 'all';

// ── WHERE dinámico ────────────────────────────────────────────────────────────
$wheres = [];
$params = [];

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
    'activo'   => 'activos',
    'inactivo' => 'inactivos',
    default    => 'todos',
};

// ── Consulta ──────────────────────────────────────────────────────────────────
$sql = "SELECT
    s.id_servicio,
    s.nombre,
    s.tipo,
    s.descripcion,
    s.precio_base,
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
        if (empty($r['tipos_equipo'])) return true; // aplica a todos
        return in_array($filtro_equip, array_map('trim', explode(',', $r['tipos_equipo'])));
    }));
}
$rows = $all_rows;

// Totales
$total_activos   = count(array_filter($rows, fn($r) => $r['estado_txt'] === 'Activo'));
$total_inactivos = count(array_filter($rows, fn($r) => $r['estado_txt'] === 'Inactivo'));
$total_catalogo  = count(array_filter($rows, fn($r) => $r['tipo'] === 'catalogo'));
$total_person    = count(array_filter($rows, fn($r) => $r['tipo'] === 'personalizado'));
$precio_promedio = $total_activos > 0
    ? array_sum(array_column(array_filter($rows, fn($r) => $r['estado_txt'] === 'Activo'), 'precio_base')) / $total_activos
    : 0;

// ══════════════════════════════════════════════════════════════════════════════
// CSV
// ══════════════════════════════════════════════════════════════════════════════
if ($formato === 'csv') {
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="catalogo_servicios_' . $label_estado . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ['#','Nombre del Servicio','Tipo','Descripción','Precio Base','Equipos Compatibles','Veces Usado','Estado']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id_servicio'],
            $r['nombre'],
            ucfirst($r['tipo']),
            $r['descripcion'] ?? '',
            number_format($r['precio_base'], 2, '.', ''),
            $r['tipos_equipo'] ? $r['tipos_equipo'] : 'Todos los equipos',
            $r['veces_usado'],
            $r['estado_txt'],
        ]);
    }

    fputcsv($out, []);
    fputcsv($out, ['','RESUMEN','','','','','','']);
    fputcsv($out, ['','Activos: '.$total_activos,'Inactivos: '.$total_inactivos,'Catálogo: '.$total_catalogo,'Personalizado: '.$total_person,'Precio promedio: S/. '.number_format($precio_promedio,2),'','']);
    fclose($out);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// EXCEL (.xlsx nativo ZipArchive + OOXML)
// ══════════════════════════════════════════════════════════════════════════════
if ($formato === 'excel') {
    try {
        $xesc = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        // Índices de estilo
        $S_NORMAL    = 0;  $S_TITULO    = 1;  $S_SUBTIT    = 2;
        $S_CAB       = 3;  $S_DATO      = 4;  $S_DATO_A    = 5;
        $S_CENTRO    = 6;  $S_CENTRO_A  = 7;  $S_PIE       = 8;
        $S_NUM       = 9;  $S_NUM_A     = 10;
        $S_ACTIVO    = 11; $S_INACTIVO  = 12;
        $S_CATALOGO  = 13; $S_PERSON    = 14;

        $NCOLS = 8;
        $rowsXml = ''; $rowNum = 1; $merges = [];

        $colLetter = function(int $idx): string {
            $letter = ''; $idx++;
            while ($idx > 0) { $idx--; $letter = chr(65 + ($idx % 26)) . $letter; $idx = (int)($idx / 26); }
            return $letter;
        };

        $addRow = function(array $cells) use (&$rowsXml, &$rowNum, $xesc, $colLetter) {
            $rowsXml .= '<row r="' . $rowNum . '">';
            $col = 0;
            foreach ($cells as [$val, $si, $type]) {
                $ref = $colLetter($col) . $rowNum;
                if ($type === 'n') {
                    $rowsXml .= '<c r="' . $ref . '" s="' . $si . '" t="n"><v>' . (is_numeric($val) ? $val : 0) . '</v></c>';
                } else {
                    $rowsXml .= '<c r="' . $ref . '" s="' . $si . '" t="inlineStr"><is><t>' . $xesc($val) . '</t></is></c>';
                }
                $col++;
            }
            $rowsXml .= '</row>';
            $rowNum++;
        };

        $emptyRow = function() use (&$addRow, $S_NORMAL, $NCOLS) {
            $addRow(array_fill(0, $NCOLS, ['', $S_NORMAL, 's']));
        };

        $lastCol = $colLetter($NCOLS - 1); // 'H'

        // Título
        $merges[] = 'A1:' . $lastCol . '1';
        $addRow([['CATÁLOGO DE SERVICIOS TÉCNICOS — SysInversiones CH Computer', $S_TITULO, 's'], ...array_fill(0, $NCOLS - 1, ['', $S_TITULO, 's'])]);

        // Subtítulo
        $merges[] = 'A2:' . $lastCol . '2';
        $sub = 'Generado el ' . date('d/m/Y H:i:s') . '   |   Total: ' . count($rows) . ' servicios   |   Precio promedio: S/. ' . number_format($precio_promedio, 2);
        $addRow([[$sub, $S_SUBTIT, 's'], ...array_fill(0, $NCOLS - 1, ['', $S_SUBTIT, 's'])]);

        $emptyRow();

        // Cabeceras
        $cabs = ['#','Nombre del Servicio','Tipo','Descripción','Precio Base','Equipos Compatibles','Veces Usado','Estado'];
        $addRow(array_map(fn($h) => [$h, $S_CAB, 's'], $cabs));

        // Datos
        $i = 0;
        foreach ($rows as $r) {
            $i++;
            $alt = ($i % 2 === 0);
            $d   = $alt ? $S_DATO_A   : $S_DATO;
            $c   = $alt ? $S_CENTRO_A : $S_CENTRO;
            $n   = $alt ? $S_NUM_A    : $S_NUM;

            $est_si  = ($r['estado_txt'] === 'Activo') ? $S_ACTIVO : $S_INACTIVO;
            $tipo_si = ($r['tipo'] === 'catalogo')     ? $S_CATALOGO : $S_PERSON;

            $addRow([
                [$r['id_servicio'],                                                  $c,      'n'],
                [$r['nombre'],                                                       $d,      's'],
                [ucfirst($r['tipo']),                                                $tipo_si,'s'],
                [$r['descripcion'] ?? '',                                            $d,      's'],
                ['S/. ' . number_format($r['precio_base'], 2),                       $n,      's'],
                [$r['tipos_equipo'] ? $r['tipos_equipo'] : 'Todos los equipos',     $d,      's'],
                [$r['veces_usado'],                                                  $n,      'n'],
                [$r['estado_txt'],                                                   $est_si, 's'],
            ]);
        }

        $emptyRow();

        // Fila resumen
        $merges[] = 'A' . $rowNum . ':D' . $rowNum;
        $addRow([
            ['RESUMEN: Activos: ' . $total_activos . '   |   Inactivos: ' . $total_inactivos . '   |   Catálogo: ' . $total_catalogo . '   |   Personalizado: ' . $total_person, $S_CAB, 's'],
            ['', $S_CAB, 's'], ['', $S_CAB, 's'], ['', $S_CAB, 's'],
            ['S/. ' . number_format($precio_promedio, 2) . ' (promedio)', $S_ACTIVO, 's'],
            ['', $S_CAB, 's'], ['', $S_CAB, 's'], ['', $S_CAB, 's'],
        ]);

        $emptyRow();

        // Pie
        $merges[] = 'A' . $rowNum . ':' . $lastCol . $rowNum;
        $addRow([['SysInversiones CH Computer — Reporte generado el ' . date('d/m/Y H:i:s'), $S_PIE, 's'], ...array_fill(0, $NCOLS - 1, ['', $S_PIE, 's'])]);

        // Anchos: #, Nombre, Tipo, Descripción, Precio, Equipos, Usado, Estado
        $colWidths = [5, 40, 14, 44, 14, 36, 12, 12];
        $colsXml = '';
        foreach ($colWidths as $idx => $w) {
            $colsXml .= '<col min="' . ($idx+1) . '" max="' . ($idx+1) . '" width="' . $w . '" customWidth="1"/>';
        }

        $mergesXml = '<mergeCells count="' . count($merges) . '">';
        foreach ($merges as $m) $mergesXml .= '<mergeCell ref="' . $m . '"/>';
        $mergesXml .= '</mergeCells>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheetViews><sheetView workbookViewId="0"><selection activeCell="A1"/></sheetView></sheetViews>
<sheetFormatPr defaultRowHeight="15"/>
<cols>' . $colsXml . '</cols>
<sheetData>' . $rowsXml . '</sheetData>
' . $mergesXml . '
</worksheet>';

        // Estilos — paleta azul índigo para servicios
        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="11">
  <font><sz val="11"/><name val="Calibri"/></font>
  <font><sz val="13"/><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="9"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="9"/><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="8"/><name val="Calibri"/></font>
  <font><sz val="7"/><i/><color rgb="FF94A3B8"/><name val="Calibri"/></font>
  <font><sz val="8"/><b/><color rgb="FF155724"/><name val="Calibri"/></font>
  <font><sz val="8"/><b/><color rgb="FF721C24"/><name val="Calibri"/></font>
  <font><sz val="8"/><b/><color rgb="FF1E40AF"/><name val="Calibri"/></font>
  <font><sz val="8"/><b/><color rgb="FF92400E"/><name val="Calibri"/></font>
  <font><sz val="8"/><b/><color rgb="FF1A3A6B"/><name val="Calibri"/></font>
</fonts>
<fills count="14">
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF1A3A6B"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/></patternFill></fill>
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFEFF6FF"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFD4EDDA"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFF8D7DA"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFBBDEFB"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFEF9C3"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFE0E7FF"/></patternFill></fill>
</fills>
<borders count="3">
  <border><left/><right/><top/><bottom/><diagonal/></border>
  <border><left style="thin"><color rgb="FFFFFFFF"/></left><right style="thin"><color rgb="FFFFFFFF"/></right><top style="thin"><color rgb="FFFFFFFF"/></top><bottom style="thin"><color rgb="FFFFFFFF"/></bottom><diagonal/></border>
  <border><left/><right/><top/><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border>
</borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="15">
  <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
  <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="4" fillId="4" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="4" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="4" fillId="4" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="right"/></xf>
  <xf numFmtId="0" fontId="4" fillId="4" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>
  <xf numFmtId="0" fontId="6" fillId="6" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="7" fillId="7" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="8" fillId="8" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="9" fillId="9" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
</cellXfs>
</styleSheet>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Catalogo Servicios" sheetId="1" r:id="rId1"/></sheets>
</workbook>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"            ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"   ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml"              ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

        $relsRoot = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

        $relsWorkbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"   Target="styles.xml"/>
</Relationships>';

        $tmpFile = tempnam(sys_get_temp_dir(), 'csrv_') . '.xlsx';
        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
            throw new Exception('No se pudo crear el archivo ZIP temporal.');

        $zip->addFromString('[Content_Types].xml',        $contentTypes);
        $zip->addFromString('_rels/.rels',                $relsRoot);
        $zip->addFromString('xl/workbook.xml',            $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $relsWorkbook);
        $zip->addFromString('xl/styles.xml',              $stylesXml);
        $zip->addFromString('xl/worksheets/sheet1.xml',   $sheetXml);
        $zip->close();

        $filename = 'catalogo_servicios_' . $label_estado . '_' . date('Ymd_His') . '.xlsx';
        ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpFile));
        header('Cache-Control: max-age=0');
        readfile($tmpFile);
        unlink($tmpFile);

    } catch (Exception $e) {
        ob_end_clean();
        header('Content-Type: text/plain');
        echo 'Error Excel: ' . $e->getMessage();
    }
    exit;
}

ob_end_clean();
echo 'Formato no soportado.';
