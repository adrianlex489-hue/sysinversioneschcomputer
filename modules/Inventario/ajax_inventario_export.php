<?php
// ============================================================
// modules/Inventario/ajax_inventario_export.php
// Exportación de Inventario — CSV y Excel (.xlsx)
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

$formato      = $_GET['exportar']   ?? 'csv';
$filtro_stock = $_GET['stock']      ?? 'all';
$filtro_cat   = $_GET['categoria']  ?? 'all';

// ── WHERE dinámico ────────────────────────────────────────────────────────────
$wheres = ['p.estado = 1'];
$params = [];

if ($filtro_cat !== 'all') {
    $wheres[] = 'p.id_categoria = ?';
    $params[]  = (int)$filtro_cat;
}

$where_sql = 'WHERE ' . implode(' AND ', $wheres);

$label_stock = match($filtro_stock) {
    'ok'      => 'normal',
    'bajo'    => 'stock_bajo',
    'agotado' => 'agotados',
    'exceso'  => 'exceso',
    default   => 'todos',
};

// ── Consulta ──────────────────────────────────────────────────────────────────
$sql = "SELECT
    p.id_producto,
    p.codigo,
    p.nombre_producto,
    p.marca,
    p.modelo,
    c.nombre_categoria,
    pr.razon_social AS proveedor,
    p.stock,
    p.stock_minimo,
    p.stock_maximo,
    p.precio_compra,
    p.precio_venta,
    ROUND(p.stock * p.precio_compra, 2) AS valor_inventario,
    CASE
        WHEN p.stock <= 0                              THEN 'Agotado'
        WHEN p.stock <= p.stock_minimo                 THEN 'Stock bajo'
        WHEN p.stock_maximo > 0 AND p.stock > p.stock_maximo THEN 'Exceso'
        ELSE 'Normal'
    END AS estado_stock,
    DATE_FORMAT(p.fecha_registro,'%d/%m/%Y') AS fecha_registro
FROM productos p
LEFT JOIN categorias  c  ON p.id_categoria = c.id_categoria
LEFT JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
$where_sql
ORDER BY
    CASE
        WHEN p.stock <= 0              THEN 3
        WHEN p.stock <= p.stock_minimo THEN 2
        ELSE 1
    END ASC,
    p.stock DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtrar por estado de stock en PHP (más flexible)
$rows = match($filtro_stock) {
    'ok'      => array_filter($all_rows, fn($r) => $r['estado_stock'] === 'Normal'),
    'bajo'    => array_filter($all_rows, fn($r) => $r['estado_stock'] === 'Stock bajo'),
    'agotado' => array_filter($all_rows, fn($r) => $r['estado_stock'] === 'Agotado'),
    'exceso'  => array_filter($all_rows, fn($r) => $r['estado_stock'] === 'Exceso'),
    default   => $all_rows,
};
$rows = array_values($rows);

// Totales
$valor_total   = array_sum(array_column($rows, 'valor_inventario'));
$total_ok      = count(array_filter($rows, fn($r) => $r['estado_stock'] === 'Normal'));
$total_bajo    = count(array_filter($rows, fn($r) => $r['estado_stock'] === 'Stock bajo'));
$total_agotado = count(array_filter($rows, fn($r) => $r['estado_stock'] === 'Agotado'));
$total_exceso  = count(array_filter($rows, fn($r) => $r['estado_stock'] === 'Exceso'));

// ══════════════════════════════════════════════════════════════════════════════
// CSV
// ══════════════════════════════════════════════════════════════════════════════
if ($formato === 'csv') {
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventario_' . $label_stock . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ['#','Código','Producto','Marca','Modelo','Categoría','Proveedor','Stock','Stock Mín.','Stock Máx.','P. Compra','P. Venta','Valor Inventario','Estado Stock','Fecha Registro']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id_producto'],
            $r['codigo'],
            $r['nombre_producto'],
            $r['marca']           ?? '',
            $r['modelo']          ?? '',
            $r['nombre_categoria']?? '',
            $r['proveedor']       ?? '',
            $r['stock'],
            $r['stock_minimo'],
            $r['stock_maximo'],
            number_format($r['precio_compra'], 2, '.', ''),
            number_format($r['precio_venta'],  2, '.', ''),
            number_format($r['valor_inventario'], 2, '.', ''),
            $r['estado_stock'],
            $r['fecha_registro']  ?? '',
        ]);
    }

    fputcsv($out, []);
    fputcsv($out, ['','','','','','','TOTALES','','','','','','S/. ' . number_format($valor_total, 2, '.', ''),'','']);
    fputcsv($out, ['','','','','','','Normal: '.$total_ok,'Stock bajo: '.$total_bajo,'Agotados: '.$total_agotado,'Exceso: '.$total_exceso,'','','','','']);
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
        $S_OK        = 11; $S_BAJO      = 12;
        $S_AGOTADO   = 13; $S_EXCESO    = 14;

        $NCOLS = 15;
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

        $lastCol = $colLetter($NCOLS - 1); // 'O'

        // Título
        $merges[] = 'A1:' . $lastCol . '1';
        $addRow([['REPORTE DE INVENTARIO — SysInversiones CH Computer', $S_TITULO, 's'], ...array_fill(0, $NCOLS - 1, ['', $S_TITULO, 's'])]);

        // Subtítulo
        $merges[] = 'A2:' . $lastCol . '2';
        $sub = 'Generado el ' . date('d/m/Y H:i:s') . '   |   Total: ' . count($rows) . ' productos   |   Valor inventario: S/. ' . number_format($valor_total, 2);
        $addRow([[$sub, $S_SUBTIT, 's'], ...array_fill(0, $NCOLS - 1, ['', $S_SUBTIT, 's'])]);

        $emptyRow();

        // Cabeceras
        $cabs = ['#','Código','Producto','Marca','Modelo','Categoría','Proveedor','Stock','Stock Mín.','Stock Máx.','P. Compra','P. Venta','Valor Inv.','Estado Stock','Fecha Reg.'];
        $addRow(array_map(fn($h) => [$h, $S_CAB, 's'], $cabs));

        // Datos
        $i = 0;
        foreach ($rows as $r) {
            $i++;
            $alt = ($i % 2 === 0);
            $d   = $alt ? $S_DATO_A   : $S_DATO;
            $c   = $alt ? $S_CENTRO_A : $S_CENTRO;
            $n   = $alt ? $S_NUM_A    : $S_NUM;

            $est_si = match($r['estado_stock']) {
                'Normal'     => $S_OK,
                'Stock bajo' => $S_BAJO,
                'Agotado'    => $S_AGOTADO,
                'Exceso'     => $S_EXCESO,
                default      => $c,
            };

            $addRow([
                [$r['id_producto'],                          $c,      'n'],
                [$r['codigo'],                               $c,      's'],
                [$r['nombre_producto'],                      $d,      's'],
                [$r['marca']            ?? '',               $d,      's'],
                [$r['modelo']           ?? '',               $d,      's'],
                [$r['nombre_categoria'] ?? '',               $d,      's'],
                [$r['proveedor']        ?? '',               $d,      's'],
                [$r['stock'],                                $n,      'n'],
                [$r['stock_minimo'],                         $n,      'n'],
                [$r['stock_maximo'],                         $n,      'n'],
                [number_format($r['precio_compra'], 2, '.', ''), $n,  's'],
                [number_format($r['precio_venta'],  2, '.', ''), $n,  's'],
                [number_format($r['valor_inventario'], 2, '.', ''), $n, 's'],
                [$r['estado_stock'],                         $est_si, 's'],
                [$r['fecha_registro']   ?? '',               $c,      's'],
            ]);
        }

        $emptyRow();

        // Fila resumen
        $merges[] = 'A' . $rowNum . ':G' . $rowNum;
        $addRow([
            ['RESUMEN: Normal: ' . $total_ok . '   |   Stock bajo: ' . $total_bajo . '   |   Agotados: ' . $total_agotado . '   |   Exceso: ' . $total_exceso, $S_CAB, 's'],
            ['', $S_CAB, 's'], ['', $S_CAB, 's'], ['', $S_CAB, 's'],
            ['', $S_CAB, 's'], ['', $S_CAB, 's'], ['', $S_CAB, 's'],
            ['', $S_NUM, 's'], ['', $S_NUM, 's'], ['', $S_NUM, 's'],
            ['', $S_NUM, 's'], ['', $S_NUM, 's'],
            ['S/. ' . number_format($valor_total, 2), $S_OK, 's'],
            ['', $S_CAB, 's'], ['', $S_CAB, 's'],
        ]);

        $emptyRow();

        // Pie
        $merges[] = 'A' . $rowNum . ':' . $lastCol . $rowNum;
        $addRow([['SysInversiones CH Computer — Reporte generado el ' . date('d/m/Y H:i:s'), $S_PIE, 's'], ...array_fill(0, $NCOLS - 1, ['', $S_PIE, 's'])]);

        // Anchos: #, Código, Producto, Marca, Modelo, Categoría, Proveedor, Stock, Mín, Máx, P.Compra, P.Venta, Valor, Estado, Fecha
        $colWidths = [5, 12, 36, 14, 14, 18, 28, 8, 8, 8, 11, 11, 13, 12, 12];
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

        // Estilos — paleta azul/teal para inventario
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
  <font><sz val="8"/><b/><color rgb="FF856404"/><name val="Calibri"/></font>
  <font><sz val="8"/><b/><color rgb="FF721C24"/><name val="Calibri"/></font>
  <font><sz val="8"/><b/><color rgb="FF6C3483"/><name val="Calibri"/></font>
  <font><sz val="8"/><b/><color rgb="FF1A5276"/><name val="Calibri"/></font>
</fonts>
<fills count="14">
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF1A5276"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF117A8B"/></patternFill></fill>
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFE8F4FD"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFD4EDDA"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFFF3CD"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFF8D7DA"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFE8D5F5"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFBBDEFB"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFEF9C3"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFF3E5F5"/></patternFill></fill>
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
  <xf numFmtId="0" fontId="4" fillId="4" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>
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
<sheets><sheet name="Inventario" sheetId="1" r:id="rId1"/></sheets>
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

        $tmpFile = tempnam(sys_get_temp_dir(), 'inv_') . '.xlsx';
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

        $filename = 'inventario_' . $label_stock . '_' . date('Ymd_His') . '.xlsx';
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
