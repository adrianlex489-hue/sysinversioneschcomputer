<?php
// ============================================================
// modules/Caja/ajax_detalle_caja_export.php
// Exportación del Detalle de una Caja — CSV y Excel (.xlsx)
// SysInversiones CH Computer 2026
// ============================================================
ob_start();
$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';
require_once $ruta_base . 'conf/permisos.php';
verificar_acceso([ROL_ADMINISTRADOR, ROL_ASESOR_COMERCIAL, ROL_TECNICO]);
if (!isset($pdo) || !($pdo instanceof PDO)) { ob_end_clean(); die('Sin BD'); }

$id_usuario_sesion = (int)($_SESSION['id_usuario'] ?? 0);
$id_rol            = (int)($_SESSION['id_rol']     ?? 0);
$es_admin          = ($id_rol === (defined('ROL_ADMINISTRADOR') ? ROL_ADMINISTRADOR : 1));

$id_caja = (int)($_GET['id_caja'] ?? 0);
$formato = $_GET['exportar'] ?? 'csv';
if (!$id_caja) { ob_end_clean(); die('Sin caja'); }

// ── Datos de la caja ──────────────────────────────────────────────────────────
$caja = null;
try {
    $st = $pdo->prepare("SELECT c.*, u.nombre_completo AS cajero FROM caja c JOIN usuarios u ON u.id_usuario=c.id_usuario WHERE c.id_caja=?");
    $st->execute([$id_caja]); $caja = $st->fetch();
} catch (PDOException $e) {}
if (!$caja) { ob_end_clean(); die('Caja no encontrada'); }
if (!$es_admin && (int)$caja['id_usuario'] !== $id_usuario_sesion) { ob_end_clean(); die('Sin acceso'); }

// ── Movimientos ───────────────────────────────────────────────────────────────
$rows = [];
try {
    $stM = $pdo->prepare("
        SELECT m.id_movimiento,
               DATE_FORMAT(m.fecha,'%d/%m/%Y %H:%i:%s') AS fecha_fmt,
               m.tipo, m.tipo_referencia, m.descripcion, m.monto,
               m.metodo_pago, m.observacion,
               u.nombre_completo AS cajero
        FROM movimientos_caja m
        LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
        WHERE m.id_caja = ?
        ORDER BY m.fecha ASC
    ");
    $stM->execute([$id_caja]);
    $rows = $stM->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$total_ing = array_sum(array_map(fn($r) => $r['tipo']==='ingreso'?(float)$r['monto']:0, $rows));
$total_eg  = array_sum(array_map(fn($r) => $r['tipo']==='egreso' ?(float)$r['monto']:0, $rows));
$neto      = $total_ing - $total_eg;

$nombre_caja   = $caja['nombre'] ?? 'Caja #'.$id_caja;
$label_periodo = date('d/m/Y H:i', strtotime($caja['fecha_apertura']));
if ($caja['fecha_cierre']) $label_periodo .= ' — ' . date('d/m/Y H:i', strtotime($caja['fecha_cierre']));

// ══════════════════════════════════════════════════════════════════════════════
// CSV
// ══════════════════════════════════════════════════════════════════════════════
if ($formato === 'csv') {
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="detalle_caja_'.$id_caja.'_'.date('Ymd_His').'.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($out, ['Caja: '.$nombre_caja, 'Cajero: '.$caja['cajero'], 'Período: '.$label_periodo, 'Estado: '.ucfirst($caja['estado'])]);
    fputcsv($out, ['Fondo inicial: S/. '.number_format($caja['monto_inicial'],2,'.',''), 'Ingresos: S/. '.number_format($total_ing,2,'.',''), 'Egresos: S/. '.number_format($total_eg,2,'.',''), 'Neto: S/. '.number_format($neto,2,'.','')]);
    fputcsv($out, []);
    fputcsv($out, ['#','Fecha/Hora','Tipo','Origen','Descripción','Método','Monto','Cajero','Observación']);

    foreach ($rows as $i => $r) {
        fputcsv($out, [
            $i + 1,
            $r['fecha_fmt'],
            ucfirst($r['tipo']),
            ucfirst($r['tipo_referencia']),
            $r['descripcion'],
            ucfirst($r['metodo_pago']),
            ($r['tipo']==='ingreso'?'+':'-') . number_format($r['monto'],2,'.',''),
            $r['cajero'],
            $r['observacion'] ?? '',
        ]);
    }

    fputcsv($out, []);
    fputcsv($out, ['TOTALES','','','','','',
        'Ing: +'.number_format($total_ing,2,'.','').' | Eg: -'.number_format($total_eg,2,'.','').' | Neto: '.number_format($neto,2,'.',''),
        '','']);
    fclose($out);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// EXCEL (.xlsx nativo ZipArchive + OOXML)
// ══════════════════════════════════════════════════════════════════════════════
if ($formato === 'excel') {
    try {
        $xesc = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $S_NORMAL=0; $S_TITULO=1; $S_SUBTIT=2; $S_CAB=3;
        $S_DATO=4;   $S_DATO_A=5; $S_CENTRO=6; $S_CENTRO_A=7;
        $S_PIE=8;    $S_NUM=9;    $S_NUM_A=10;
        $S_ING=11;   $S_EG=12;

        $NCOLS = 9;
        $rowsXml = ''; $rowNum = 1; $merges = [];

        $colLetter = function(int $idx): string {
            $letter = ''; $idx++;
            while ($idx > 0) { $idx--; $letter = chr(65 + ($idx % 26)) . $letter; $idx = (int)($idx / 26); }
            return $letter;
        };

        $addRow = function(array $cells) use (&$rowsXml, &$rowNum, $xesc, $colLetter) {
            $rowsXml .= '<row r="'.$rowNum.'">';
            $col = 0;
            foreach ($cells as [$val, $si, $type]) {
                $ref = $colLetter($col) . $rowNum;
                if ($type === 'n') {
                    $rowsXml .= '<c r="'.$ref.'" s="'.$si.'" t="n"><v>'.(is_numeric($val)?$val:0).'</v></c>';
                } else {
                    $rowsXml .= '<c r="'.$ref.'" s="'.$si.'" t="inlineStr"><is><t>'.$xesc($val).'</t></is></c>';
                }
                $col++;
            }
            $rowsXml .= '</row>';
            $rowNum++;
        };

        $emptyRow = function() use (&$addRow, $S_NORMAL, $NCOLS) {
            $addRow(array_fill(0, $NCOLS, ['', $S_NORMAL, 's']));
        };

        $lastCol = $colLetter($NCOLS - 1);

        // Título
        $merges[] = 'A1:'.$lastCol.'1';
        $addRow([['DETALLE DE CAJA — '.$nombre_caja.' — SysInversiones CH Computer', $S_TITULO, 's'], ...array_fill(0, $NCOLS-1, ['', $S_TITULO, 's'])]);

        // Subtítulo
        $merges[] = 'A2:'.$lastCol.'2';
        $sub = 'Cajero: '.$caja['cajero'].'   |   Período: '.$label_periodo.'   |   Estado: '.ucfirst($caja['estado']).'   |   Movimientos: '.count($rows);
        $addRow([[$sub, $S_SUBTIT, 's'], ...array_fill(0, $NCOLS-1, ['', $S_SUBTIT, 's'])]);

        // Resumen
        $merges[] = 'A3:'.$lastCol.'3';
        $res = 'Fondo inicial: S/. '.number_format($caja['monto_inicial'],2).'   |   Ingresos: S/. '.number_format($total_ing,2).'   |   Egresos: S/. '.number_format($total_eg,2).'   |   Neto: S/. '.number_format($neto,2);
        $addRow([[$res, $S_SUBTIT, 's'], ...array_fill(0, $NCOLS-1, ['', $S_SUBTIT, 's'])]);

        $emptyRow();

        // Cabeceras
        $cabs = ['#','Fecha/Hora','Tipo','Origen','Descripción','Método','Monto','Cajero','Observación'];
        $addRow(array_map(fn($h) => [$h, $S_CAB, 's'], $cabs));

        // Datos
        $i = 0;
        foreach ($rows as $r) {
            $i++;
            $alt = ($i % 2 === 0);
            $d = $alt ? $S_DATO_A   : $S_DATO;
            $c = $alt ? $S_CENTRO_A : $S_CENTRO;
            $n = $alt ? $S_NUM_A    : $S_NUM;
            $es_ing = ($r['tipo'] === 'ingreso');
            $monto_fmt = ($es_ing?'+':'-') . number_format($r['monto'],2,'.','');

            $addRow([
                [$i,                                    $c,                    'n'],
                [$r['fecha_fmt'],                       $c,                    's'],
                [ucfirst($r['tipo']),                   $es_ing?$S_ING:$S_EG, 's'],
                [ucfirst($r['tipo_referencia']),        $d,                    's'],
                [$r['descripcion'],                     $d,                    's'],
                [ucfirst($r['metodo_pago']),            $c,                    's'],
                [$monto_fmt,                            $es_ing?$S_ING:$S_EG, 's'],
                [$r['cajero'],                          $d,                    's'],
                [$r['observacion'] ?? '',               $d,                    's'],
            ]);
        }

        $emptyRow();

        // Totales
        $merges[] = 'A'.$rowNum.':F'.$rowNum;
        $addRow([
            ['TOTALES: '.count($rows).' movimientos', $S_CAB, 's'],
            ['', $S_CAB, 's'], ['', $S_CAB, 's'], ['', $S_CAB, 's'], ['', $S_CAB, 's'], ['', $S_CAB, 's'],
            ['Ing: +'.number_format($total_ing,2).' | Eg: -'.number_format($total_eg,2).' | Neto: '.number_format($neto,2), $S_CAB, 's'],
            ['', $S_CAB, 's'], ['', $S_CAB, 's'],
        ]);

        $emptyRow();

        // Pie
        $merges[] = 'A'.$rowNum.':'.$lastCol.$rowNum;
        $addRow([['SysInversiones CH Computer — Generado el '.date('d/m/Y H:i:s'), $S_PIE, 's'], ...array_fill(0, $NCOLS-1, ['', $S_PIE, 's'])]);

        $colWidths = [5,18,10,12,36,14,16,24,28];
        $colsXml = '';
        foreach ($colWidths as $idx => $w)
            $colsXml .= '<col min="'.($idx+1).'" max="'.($idx+1).'" width="'.$w.'" customWidth="1"/>';

        $mergesXml = '<mergeCells count="'.count($merges).'">';
        foreach ($merges as $m) $mergesXml .= '<mergeCell ref="'.$m.'"/>';
        $mergesXml .= '</mergeCells>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheetViews><sheetView workbookViewId="0"><selection activeCell="A1"/></sheetView></sheetViews>
<sheetFormatPr defaultRowHeight="15"/>
<cols>'.$colsXml.'</cols>
<sheetData>'.$rowsXml.'</sheetData>
'.$mergesXml.'
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
  <font><sz val="8"/><b/><color rgb="FF065F46"/><name val="Calibri"/></font>
</fonts>
<fills count="10">
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF0C1A3A"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF0F3460"/></patternFill></fill>
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFE0F2FE"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFD1FAE5"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/></patternFill></fill>
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
  <xf numFmtId="0" fontId="4" fillId="4" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="4" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="4" fillId="4" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="right"/></xf>
  <xf numFmtId="0" fontId="4" fillId="4" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right" vertical="center"/></xf>
  <xf numFmtId="0" fontId="6" fillId="6" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="7" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
</cellXfs>
</styleSheet>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Detalle Caja" sheetId="1" r:id="rId1"/></sheets>
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

        $tmpFile = tempnam(sys_get_temp_dir(), 'dcj_') . '.xlsx';
        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
            throw new Exception('No se pudo crear el archivo ZIP.');
        $zip->addFromString('[Content_Types].xml',        $contentTypes);
        $zip->addFromString('_rels/.rels',                $relsRoot);
        $zip->addFromString('xl/workbook.xml',            $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $relsWorkbook);
        $zip->addFromString('xl/styles.xml',              $stylesXml);
        $zip->addFromString('xl/worksheets/sheet1.xml',   $sheetXml);
        $zip->close();

        $filename = 'detalle_caja_'.$id_caja.'_'.date('Ymd_His').'.xlsx';
        ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: '.filesize($tmpFile));
        header('Cache-Control: max-age=0');
        readfile($tmpFile);
        unlink($tmpFile);

    } catch (Exception $e) {
        ob_end_clean();
        header('Content-Type: text/plain');
        echo 'Error Excel: '.$e->getMessage();
    }
    exit;
}

ob_end_clean();
echo 'Formato no soportado.';
