<?php
// ============================================================
// modules/transacciones/historial/ajax_historial_compras_export.php
// Exportación de Historial de Compras — CSV y Excel (.xlsx)
// SysInversiones CH Computer 2026
// ============================================================
ob_start();

$ruta_base = '../../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';

verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);

if (!isset($pdo) || !($pdo instanceof PDO)) {
    ob_end_clean(); die('Error: Sin conexión a la base de datos.');
}

$formato     = $_GET['exportar']    ?? 'csv';
$filtro_est  = $_GET['estado']      ?? 'all';
$filtro_pago = $_GET['tipo_pago']   ?? 'all';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

// ── WHERE dinámico ────────────────────────────────────────────────────────────
$wheres = []; $params = [];
if ($filtro_est  !== 'all') { $wheres[] = 'c.estado = ?';        $params[] = $filtro_est; }
if ($filtro_pago !== 'all') { $wheres[] = 'c.tipo_pago = ?';     $params[] = $filtro_pago; }
if ($fecha_desde !== '')    { $wheres[] = 'DATE(c.fecha) >= ?';  $params[] = $fecha_desde; }
if ($fecha_hasta !== '')    { $wheres[] = 'DATE(c.fecha) <= ?';  $params[] = $fecha_hasta; }
$where_sql = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

$label_estado = match($filtro_est) {
    'pagado'   => 'pagadas',
    'pendiente'=> 'pendientes',
    'anulado'  => 'anuladas',
    default    => 'todas',
};

// ── Consulta ──────────────────────────────────────────────────────────────────
$sql = "SELECT
    c.id_compra,
    DATE_FORMAT(c.fecha,'%d/%m/%Y %H:%i') AS fecha,
    p.razon_social AS proveedor,
    c.tipo_comprobante,
    COALESCE(c.numero_comprobante,'---') AS numero_comprobante,
    c.tipo_pago,
    c.metodo_pago,
    c.subtotal,
    c.descuento,
    c.igv,
    c.total,
    COALESCE(c.saldo_pendiente,0) AS saldo_pendiente,
    c.estado,
    u.nombre_completo AS registrado_por
FROM compras c
JOIN proveedores p ON c.id_proveedor = p.id_proveedor
JOIN usuarios   u ON c.id_usuario   = u.id_usuario
$where_sql
ORDER BY c.fecha DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tipoLabel = ['ticket' => 'Ticket de Compra', 'nota' => 'Nota de Compra'];

$total_monto   = array_sum(array_column(array_filter($rows, fn($r) => $r['estado'] !== 'anulado'), 'total'));
$total_pagadas = count(array_filter($rows, fn($r) => $r['estado'] === 'pagado'));
$total_pend    = count(array_filter($rows, fn($r) => $r['estado'] === 'pendiente'));
$total_anul    = count(array_filter($rows, fn($r) => $r['estado'] === 'anulado'));

// ══════════════════════════════════════════════════════════════════════════════
// CSV
// ══════════════════════════════════════════════════════════════════════════════
if ($formato === 'csv') {
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="historial_compras_' . $label_estado . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ['#Compra','Fecha','Proveedor','Comprobante','N° Comprobante','T. Pago','Método Pago','Subtotal','Descuento','IGV','Total','Saldo Pendiente','Estado','Registrado por']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id_compra'],
            $r['fecha'],
            $r['proveedor'],
            $tipoLabel[$r['tipo_comprobante']] ?? ucfirst($r['tipo_comprobante']),
            $r['numero_comprobante'],
            ucfirst($r['tipo_pago']),
            ucfirst($r['metodo_pago']),
            number_format($r['subtotal'],  2, '.', ''),
            number_format($r['descuento'], 2, '.', ''),
            number_format($r['igv'],       2, '.', ''),
            number_format($r['total'],     2, '.', ''),
            number_format($r['saldo_pendiente'], 2, '.', ''),
            ucfirst($r['estado']),
            $r['registrado_por'],
        ]);
    }

    fputcsv($out, []);
    fputcsv($out, ['','','','','','','TOTALES','','','', number_format($total_monto,2,'.',''),'','','']);
    fputcsv($out, ['','','','','','','Pagadas: '.$total_pagadas,'Pendientes: '.$total_pend,'Anuladas: '.$total_anul,'','','','','']);
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
        $S_PAGADO    = 11; $S_PENDIENTE = 12; $S_ANULADO   = 13;
        $S_CREDITO   = 14; $S_CONTADO   = 15;

        $NCOLS = 14;
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

        $lastCol = $colLetter($NCOLS - 1); // 'N'

        // Título
        $merges[] = 'A1:' . $lastCol . '1';
        $addRow([['REPORTE DE HISTORIAL DE COMPRAS — SysInversiones CH Computer', $S_TITULO, 's'], ...array_fill(0, $NCOLS - 1, ['', $S_TITULO, 's'])]);

        // Subtítulo
        $merges[] = 'A2:' . $lastCol . '2';
        $sub = 'Generado el ' . date('d/m/Y H:i:s') . '   |   Total: ' . count($rows) . ' compras   |   Estado: ' . ucfirst($label_estado);
        $addRow([[$sub, $S_SUBTIT, 's'], ...array_fill(0, $NCOLS - 1, ['', $S_SUBTIT, 's'])]);

        $emptyRow();

        // Cabeceras
        $cabs = ['#Compra','Fecha','Proveedor','Comprobante','N° Comprobante','T. Pago','Método Pago','Subtotal','Descuento','IGV','Total','Saldo Pendiente','Estado','Registrado por'];
        $addRow(array_map(fn($h) => [$h, $S_CAB, 's'], $cabs));

        // Datos
        $i = 0;
        foreach ($rows as $r) {
            $i++;
            $alt = ($i % 2 === 0);
            $d   = $alt ? $S_DATO_A   : $S_DATO;
            $c   = $alt ? $S_CENTRO_A : $S_CENTRO;
            $n   = $alt ? $S_NUM_A    : $S_NUM;

            $est_si = match($r['estado']) {
                'pagado'   => $S_PAGADO,
                'pendiente'=> $S_PENDIENTE,
                'anulado'  => $S_ANULADO,
                default    => $c,
            };
            $pago_si = ($r['tipo_pago'] === 'credito') ? $S_CREDITO : $S_CONTADO;

            $addRow([
                [$r['id_compra'],                                                    $c,      'n'],
                [$r['fecha'],                                                        $c,      's'],
                [$r['proveedor'],                                                    $d,      's'],
                [$tipoLabel[$r['tipo_comprobante']] ?? ucfirst($r['tipo_comprobante']), $d,   's'],
                [$r['numero_comprobante'],                                           $c,      's'],
                [ucfirst($r['tipo_pago']),                                           $pago_si,'s'],
                [ucfirst($r['metodo_pago']),                                         $c,      's'],
                [number_format($r['subtotal'],  2, '.', ''),                         $n,      's'],
                [number_format($r['descuento'], 2, '.', ''),                         $n,      's'],
                [number_format($r['igv'],       2, '.', ''),                         $n,      's'],
                [number_format($r['total'],     2, '.', ''),                         $n,      's'],
                [number_format($r['saldo_pendiente'], 2, '.', ''),                   $n,      's'],
                [ucfirst($r['estado']),                                              $est_si, 's'],
                [$r['registrado_por'],                                               $d,      's'],
            ]);
        }

        $emptyRow();

        // Fila resumen
        $merges[] = 'A' . $rowNum . ':G' . $rowNum;
        $addRow([
            ['RESUMEN: Pagadas: ' . $total_pagadas . '   |   Pendientes: ' . $total_pend . '   |   Anuladas: ' . $total_anul, $S_CAB, 's'],
            ['', $S_CAB, 's'], ['', $S_CAB, 's'], ['', $S_CAB, 's'],
            ['', $S_CAB, 's'], ['', $S_CAB, 's'], ['', $S_CAB, 's'],
            ['', $S_NUM, 's'], ['', $S_NUM, 's'], ['', $S_NUM, 's'],
            ['S/. ' . number_format($total_monto, 2), $S_PAGADO, 's'],
            ['', $S_NUM, 's'], ['', $S_CAB, 's'], ['', $S_CAB, 's'],
        ]);

        $emptyRow();

        // Pie
        $merges[] = 'A' . $rowNum . ':' . $lastCol . $rowNum;
        $addRow([['SysInversiones CH Computer — Reporte generado el ' . date('d/m/Y H:i:s'), $S_PIE, 's'], ...array_fill(0, $NCOLS - 1, ['', $S_PIE, 's'])]);

        // Anchos de columna
        $colWidths = [7, 16, 36, 16, 16, 10, 12, 12, 12, 12, 12, 14, 12, 28];
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

        // Estilos — paleta azul para compras
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
  <font><sz val="8"/><b/><color rgb="FF7D4E00"/><name val="Calibri"/></font>
  <font><sz val="8"/><b/><color rgb="FF1A5276"/><name val="Calibri"/></font>
</fonts>
<fills count="14">
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF1A5276"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF2980B9"/></patternFill></fill>
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFE3F2FD"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFD4EDDA"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFFF3CD"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFF8D7DA"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFFF3E0"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFBBDEFB"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFEF9C3"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFE8F4FD"/></patternFill></fill>
</fills>
<borders count="3">
  <border><left/><right/><top/><bottom/><diagonal/></border>
  <border><left style="thin"><color rgb="FFFFFFFF"/></left><right style="thin"><color rgb="FFFFFFFF"/></right><top style="thin"><color rgb="FFFFFFFF"/></top><bottom style="thin"><color rgb="FFFFFFFF"/></bottom><diagonal/></border>
  <border><left/><right/><top/><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border>
</borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="16">
  <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
  <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="4" fillId="4" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="13" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="4" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="13" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="right"/></xf>
  <xf numFmtId="0" fontId="4" fillId="4" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="13" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>
  <xf numFmtId="0" fontId="6" fillId="6" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="7" fillId="7" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="8" fillId="8" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="9" fillId="9" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="10" fillId="10" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
</cellXfs>
</styleSheet>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Historial Compras" sheetId="1" r:id="rId1"/></sheets>
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

        $tmpFile = tempnam(sys_get_temp_dir(), 'hcmp_') . '.xlsx';
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

        $filename = 'historial_compras_' . $label_estado . '_' . date('Ymd_His') . '.xlsx';
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
