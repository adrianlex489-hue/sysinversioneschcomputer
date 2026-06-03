<?php
// ============================================================
// modules/personas/ajax_proveedores_plantilla.php
// Genera plantilla Excel (.xlsx) para importación de proveedores
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

$xesc = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

// ── Estilos ───────────────────────────────────────────────────────────────────
$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="5">
  <font><sz val="11"/><name val="Calibri"/></font>
  <font><sz val="13"/><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="9"/><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="9"/><name val="Calibri"/></font>
  <font><sz val="8"/><i/><color rgb="FF64748B"/><name val="Calibri"/></font>
</fonts>
<fills count="7">
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF1A5276"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF2980B9"/></patternFill></fill>
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFEFF6FF"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFFF3CD"/></patternFill></fill>
</fills>
<borders count="2">
  <border><left/><right/><top/><bottom/><diagonal/></border>
  <border><left style="thin"><color rgb="FFCBD5E1"/></left><right style="thin"><color rgb="FFCBD5E1"/></right><top style="thin"><color rgb="FFCBD5E1"/></top><bottom style="thin"><color rgb="FFCBD5E1"/></bottom><diagonal/></border>
</borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="7">
  <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
  <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyFont="1" applyBorder="1"><alignment vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="6" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
</cellXfs>
</styleSheet>';

// ── Construir filas ───────────────────────────────────────────────────────────
$rowsXml = '';
$rowNum  = 1;
$merges  = [];

$addRow = function(array $cells) use (&$rowsXml, &$rowNum, $xesc) {
    $rowsXml .= '<row r="' . $rowNum . '" ht="18" customHeight="1">';
    foreach ($cells as $ci => [$val, $sIdx]) {
        $colLetter = chr(65 + $ci);
        $cellRef   = $colLetter . $rowNum;
        $rowsXml  .= '<c r="' . $cellRef . '" s="' . $sIdx . '" t="inlineStr"><is><t>' . $xesc($val) . '</t></is></c>';
    }
    $rowsXml .= '</row>';
    $rowNum++;
};

// Fila 1: Título (A-F)
$merges[] = 'A1:F1';
$addRow([
    ['PLANTILLA IMPORTACIÓN — PROVEEDORES', 1],
    ['', 1], ['', 1], ['', 1], ['', 1], ['', 1],
]);

// Fila 2: Instrucciones
$merges[] = 'A2:F2';
$addRow([
    ['Instrucciones: Complete los datos desde la fila 5. Columnas con * son obligatorias. No modifique los encabezados.', 5],
    ['', 5], ['', 5], ['', 5], ['', 5], ['', 5],
]);

// Fila 3: Leyenda
$merges[] = 'A3:F3';
$addRow([
    ['(*) OBLIGATORIO — RUC debe tener exactamente 11 dígitos numéricos.', 5],
    ['', 5], ['', 5], ['', 5], ['', 5], ['', 5],
]);

// Fila 4: Cabeceras
$addRow([
    ['Razón Social *', 2],
    ['RUC *',          2],
    ['Teléfono',       2],
    ['Email',          2],
    ['Dirección',      2],
    ['Contacto',       2],
]);

// Filas de ejemplo
$ejemplos = [
    ['DISTRIBUIDORA NORTE S.A.C.',    '20100123456', '074234567', 'ventas@distnorte.com',   'AV. GRAU 123 CHICLAYO',       'JUAN PEREZ'],
    ['IMPORTACIONES DEL SUR E.I.R.L.','20200234567', '01234567',  'info@impsur.com',        'JR. UNION 456 LIMA',          'MARIA GARCIA'],
    ['COMERCIAL ANDINA S.R.L.',        '20300345678', '054345678', 'comercial@andina.pe',    'CAL. LOS PINOS 789 AREQUIPA', ''],
];
foreach ($ejemplos as $i => $ej) {
    $sIdx = ($i % 2 === 0) ? 3 : 4;
    $addRow(array_map(fn($v) => [$v, $sIdx], $ej));
}

// ── Anchos de columna ─────────────────────────────────────────────────────────
$colWidths = [40, 14, 14, 30, 40, 24];
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
<sheetViews><sheetView workbookViewId="0"><selection activeCell="A5"/></sheetView></sheetViews>
<sheetFormatPr defaultRowHeight="15"/>
<cols>' . $colsXml . '</cols>
<sheetData>' . $rowsXml . '</sheetData>
' . $mergesXml . '
</worksheet>';

$workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Proveedores" sheetId="1" r:id="rId1"/></sheets>
</workbook>';

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"          ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml"            ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
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

try {
    $tmpFile = tempnam(sys_get_temp_dir(), 'prov_') . '.xlsx';
    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
        throw new Exception('No se pudo crear el archivo temporal.');
    $zip->addFromString('[Content_Types].xml',        $contentTypes);
    $zip->addFromString('_rels/.rels',                $relsRoot);
    $zip->addFromString('xl/workbook.xml',            $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $relsWorkbook);
    $zip->addFromString('xl/styles.xml',              $stylesXml);
    $zip->addFromString('xl/worksheets/sheet1.xml',   $sheetXml);
    $zip->close();

    ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="plantilla_proveedores.xlsx"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: max-age=0');
    readfile($tmpFile);
    unlink($tmpFile);
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: text/plain');
    echo 'Error: ' . $e->getMessage();
}
exit;
