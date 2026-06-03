<?php
// ============================================================
// modules/catalogos/ajax_categorias_export.php | SysInversiones CH Computer
// Exportación de Categorías — CSV y Excel (.xlsx)
// Columnas: id_categoria, nombre_categoria, descripcion,
//           total_productos, estado, fecha_registro
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

$formato = $_GET['exportar'] ?? 'csv';
$estado  = $_GET['estado']   ?? 'all';

$where = match($estado) {
    'activo'   => 'WHERE c.estado = 1',
    'inactivo' => 'WHERE c.estado = 0',
    default    => '',
};

$label_estado = match($estado) {
    'activo'   => 'activas',
    'inactivo' => 'inactivas',
    default    => 'todas',
};

// ── Consulta con total de productos activos por categoría ─────────────────────
$rows = $pdo->query(
    "SELECT c.id_categoria, c.nombre_categoria, c.descripcion,
            COUNT(p.id_producto) AS total_productos,
            CASE c.estado WHEN 1 THEN 'Activa' ELSE 'Inactiva' END AS estado_txt,
            DATE_FORMAT(c.fecha_registro,'%d/%m/%Y') AS fecha_registro
     FROM categorias c
     LEFT JOIN productos p ON c.id_categoria = p.id_categoria AND p.estado = 1
     $where
     GROUP BY c.id_categoria
     ORDER BY c.nombre_categoria ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// ══════════════════════════════════════════════════════════════════════════════
// EXPORTAR CSV
// ══════════════════════════════════════════════════════════════════════════════
if ($formato === 'csv') {
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="categorias_' . $label_estado . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ['ID','Nombre Categoría','Descripción','Productos Activos','Estado','Fecha Registro']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id_categoria'],
            $r['nombre_categoria']  ?? '',
            $r['descripcion']       ?? '',
            $r['total_productos'],
            $r['estado_txt'],
            $r['fecha_registro']    ?? '',
        ]);
    }

    fclose($out);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// EXPORTAR EXCEL (.xlsx nativo con ZipArchive + OOXML)
// ══════════════════════════════════════════════════════════════════════════════
if ($formato === 'excel') {
    try {
        $xesc = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        // Estilos: 0=normal,1=título,2=subtítulo,3=cabecera,4=dato,5=dato_alt,
        //          6=centro,7=centro_alt,8=pie,9=num,10=num_alt,11=activo,12=inactivo
        $S_NORMAL   = 0; $S_TITULO   = 1; $S_SUBTIT   = 2;
        $S_CAB      = 3; $S_DATO     = 4; $S_DATO_A   = 5;
        $S_CENTRO   = 6; $S_CENTRO_A = 7; $S_PIE      = 8;
        $S_NUM      = 9; $S_NUM_A    = 10;
        $S_ACTIVO   = 11; $S_INACTIVO = 12;

        // 6 columnas: A=ID, B=Nombre, C=Descripción, D=Productos, E=Estado, F=Fecha
        $NCOLS = 6;
        $rowsXml = ''; $rowNum = 1; $merges = [];

        $addRow = function(array $cells) use (&$rowsXml, &$rowNum, $xesc) {
            $rowsXml .= '<row r="' . $rowNum . '">';
            $col = 0;
            foreach ($cells as [$val, $si, $type]) {
                $ref = chr(65 + $col) . $rowNum;
                if ($type === 'n') {
                    $rowsXml .= '<c r="' . $ref . '" s="' . $si . '" t="n"><v>' . (int)$val . '</v></c>';
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

        // Título
        $merges[] = 'A1:F1';
        $addRow([['REPORTE DE CATEGORÍAS — SysInversiones CH Computer', $S_TITULO, 's'], ...array_fill(0, 5, ['', $S_TITULO, 's'])]);

        // Subtítulo
        $merges[] = 'A2:F2';
        $addRow([['Generado el ' . date('d/m/Y H:i:s') . '   |   Total: ' . count($rows) . ' categorías   |   Estado: ' . ucfirst($label_estado), $S_SUBTIT, 's'], ...array_fill(0, 5, ['', $S_SUBTIT, 's'])]);

        $emptyRow();

        // Cabeceras
        $cabs = ['ID', 'Nombre Categoría', 'Descripción', 'Productos Activos', 'Estado', 'Fecha Registro'];
        $addRow(array_map(fn($h) => [$h, $S_CAB, 's'], $cabs));

        // Datos
        $i = 0;
        foreach ($rows as $r) {
            $i++;
            $alt = ($i % 2 === 0);
            $d   = $alt ? $S_DATO_A   : $S_DATO;
            $c   = $alt ? $S_CENTRO_A : $S_CENTRO;
            $n   = $alt ? $S_NUM_A    : $S_NUM;
            $est = ($r['estado_txt'] === 'Activa') ? $S_ACTIVO : $S_INACTIVO;

            $addRow([
                [$r['id_categoria'],                $c,   'n'],
                [$r['nombre_categoria']  ?? '',     $d,   's'],
                [$r['descripcion']       ?? '',     $d,   's'],
                [$r['total_productos'],             $n,   'n'],
                [$r['estado_txt'],                  $est, 's'],
                [$r['fecha_registro']    ?? '',     $c,   's'],
            ]);
        }

        $emptyRow();

        // Pie
        $merges[] = 'A' . $rowNum . ':F' . $rowNum;
        $addRow([['SysInversiones CH Computer — Reporte generado el ' . date('d/m/Y H:i:s'), $S_PIE, 's'], ...array_fill(0, 5, ['', $S_PIE, 's'])]);

        // Anchos: ID, Nombre, Descripción, Productos, Estado, Fecha
        $colWidths = [6, 36, 44, 16, 12, 14];
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

        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="7">
  <font><sz val="11"/><name val="Calibri"/></font>
  <font><sz val="13"/><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="9"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="9"/><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="8"/><name val="Calibri"/></font>
  <font><sz val="7"/><i/><color rgb="FF94A3B8"/><name val="Calibri"/></font>
  <font><sz val="8"/><b/><color rgb="FF166534"/><name val="Calibri"/></font>
</fonts>
<fills count="10">
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF1A5276"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF2980B9"/></patternFill></fill>
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFE8F4FD"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF1A7A4A"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFFF3CD"/></patternFill></fill>
</fills>
<borders count="3">
  <border><left/><right/><top/><bottom/><diagonal/></border>
  <border><left style="thin"><color rgb="FFFFFFFF"/></left><right style="thin"><color rgb="FFFFFFFF"/></right><top style="thin"><color rgb="FFFFFFFF"/></top><bottom style="thin"><color rgb="FFFFFFFF"/></bottom><diagonal/></border>
  <border><left/><right/><top/><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border>
</borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="13">
  <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
  <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="4" fillId="4" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="4" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="right"/></xf>
  <xf numFmtId="0" fontId="4" fillId="4" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>
  <xf numFmtId="0" fontId="6" fillId="7" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="8" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
</cellXfs>
</styleSheet>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Categorias" sheetId="1" r:id="rId1"/></sheets>
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

        $tmpFile = tempnam(sys_get_temp_dir(), 'cat_') . '.xlsx';
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

        $filename = 'categorias_' . $label_estado . '_' . date('Ymd_His') . '.xlsx';
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
