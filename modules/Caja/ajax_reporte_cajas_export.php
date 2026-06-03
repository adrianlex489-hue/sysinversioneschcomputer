<?php
// ============================================================
// modules/Caja/ajax_reporte_cajas_export.php
// Reporte consolidado profesional — CSV y Excel (.xlsx)
// Por cada caja: cabecera + todos sus movimientos detallados
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

$formato   = $_GET['exportar']    ?? 'csv';
$f_desde   = $_GET['desde']       ?? date('Y-m-01');
$f_hasta   = $_GET['hasta']       ?? date('Y-m-d');
$f_estado  = $_GET['estado']      ?? 'all';
$f_usuario = (int)($_GET['id_usuario'] ?? 0);

// ── WHERE cajas ───────────────────────────────────────────────────────────────
$wheres = ["DATE(c.fecha_apertura) BETWEEN ? AND ?"];
$params = [$f_desde, $f_hasta];
if ($f_estado !== 'all') { $wheres[] = 'c.estado = ?'; $params[] = $f_estado; }
if (!$es_admin)          { $wheres[] = 'c.id_usuario = ?'; $params[] = $id_usuario_sesion; }
elseif ($f_usuario > 0)  { $wheres[] = 'c.id_usuario = ?'; $params[] = $f_usuario; }
$where_sql = 'WHERE ' . implode(' AND ', $wheres);

// ── Cajas del período ─────────────────────────────────────────────────────────
$sql_cajas = "
    SELECT c.id_caja, c.nombre AS nombre_caja, u.nombre_completo AS cajero,
           DATE_FORMAT(c.fecha_apertura,'%d/%m/%Y %H:%i:%s') AS apertura,
           IF(c.fecha_cierre IS NOT NULL,
              DATE_FORMAT(c.fecha_cierre,'%d/%m/%Y %H:%i:%s'), NULL)  AS cierre,
           COALESCE(TIMESTAMPDIFF(MINUTE,
               c.fecha_apertura, IFNULL(c.fecha_cierre,NOW())),0)     AS duracion_min,
           c.monto_inicial,
           COALESCE(c.monto_final,0)     AS monto_final,
           COALESCE(c.monto_esperado,0)  AS monto_esperado,
           COALESCE(c.diferencia,0)      AS diferencia,
           c.estado,
           COALESCE(c.observacion,'')    AS observacion
    FROM caja c
    JOIN usuarios u ON u.id_usuario = c.id_usuario
    $where_sql
    ORDER BY c.fecha_apertura ASC";

$stmt = $pdo->prepare($sql_cajas);
$stmt->execute($params);
$cajas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Movimientos de cada caja ──────────────────────────────────────────────────
$sql_movs = "
    SELECT m.id_movimiento, m.tipo, m.tipo_referencia, m.descripcion,
           m.monto, m.metodo_pago,
           DATE_FORMAT(m.fecha,'%d/%m/%Y %H:%i:%s') AS fecha_fmt,
           COALESCE(m.observacion,'') AS observacion,
           u.nombre_completo AS cajero_mov
    FROM movimientos_caja m
    LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
    WHERE m.id_caja = ?
    ORDER BY m.fecha ASC";
$stmt_movs = $pdo->prepare($sql_movs);

// Cargar movimientos en memoria agrupados por id_caja
$movimientos = [];
foreach ($cajas as $caja) {
    $stmt_movs->execute([$caja['id_caja']]);
    $movimientos[$caja['id_caja']] = $stmt_movs->fetchAll(PDO::FETCH_ASSOC);
}

// ── Totales globales ──────────────────────────────────────────────────────────
$g_ingresos = 0; $g_egresos = 0; $g_movs = 0;
foreach ($cajas as $caja) {
    $movs = $movimientos[$caja['id_caja']] ?? [];
    foreach ($movs as $m) {
        if ($m['tipo'] === 'ingreso') $g_ingresos += (float)$m['monto'];
        else                          $g_egresos  += (float)$m['monto'];
    }
    $g_movs += count($movs);
}
$g_neto = $g_ingresos - $g_egresos;
$cerradas_cnt = count(array_filter($cajas, fn($c) => $c['estado'] === 'cerrada'));
$con_dif_cnt  = count(array_filter($cajas, fn($c) => abs((float)$c['diferencia']) > 0.01));
$label_periodo = date('d/m/Y', strtotime($f_desde)) . ' al ' . date('d/m/Y', strtotime($f_hasta));

$dur_fmt = function(int $min): string {
    return $min >= 60 ? floor($min/60).'h '.($min%60).'m' : $min.'m';
};

// ══════════════════════════════════════════════════════════════════════════════
// CSV
// ══════════════════════════════════════════════════════════════════════════════
if ($formato === 'csv') {
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_consolidado_cajas_'.date('Ymd_His').'.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

    // Encabezado global
    fputcsv($out, ['REPORTE CONSOLIDADO DE CAJAS — SysInversiones CH Computer']);
    fputcsv($out, ['Período:', $label_periodo, 'Total cajas:', count($cajas), 'Cerradas:', $cerradas_cnt, 'Con diferencia:', $con_dif_cnt]);
    fputcsv($out, ['Total ingresos:', 'S/. '.number_format($g_ingresos,2,'.',''), 'Total egresos:', 'S/. '.number_format($g_egresos,2,'.',''), 'Neto:', 'S/. '.number_format($g_neto,2,'.','')]);
    fputcsv($out, ['Generado el:', date('d/m/Y H:i:s')]);
    fputcsv($out, []);

    foreach ($cajas as $caja) {
        $movs    = $movimientos[$caja['id_caja']] ?? [];
        $c_ing   = array_sum(array_map(fn($m) => $m['tipo']==='ingreso'?(float)$m['monto']:0, $movs));
        $c_eg    = array_sum(array_map(fn($m) => $m['tipo']==='egreso' ?(float)$m['monto']:0, $movs));
        $c_neto  = $c_ing - $c_eg;
        $dif     = (float)$caja['diferencia'];
        $dif_txt = abs($dif)<0.01 ? 'Exacto' : (($dif>0?'+':'-').'S/. '.number_format(abs($dif),2,'.',''));

        // Cabecera de caja
        fputcsv($out, ['━━━ CAJA #'.$caja['id_caja'].' — '.$caja['nombre_caja'].' ━━━']);
        fputcsv($out, ['Cajero:', $caja['cajero'], 'Estado:', ucfirst($caja['estado']), 'Duración:', $dur_fmt((int)$caja['duracion_min'])]);
        fputcsv($out, ['Apertura:', $caja['apertura'], 'Cierre:', $caja['cierre'] ?? '(en curso)']);
        fputcsv($out, ['Fondo inicial:', 'S/. '.number_format($caja['monto_inicial'],2,'.',''),
                       'Ingresos:', 'S/. '.number_format($c_ing,2,'.',''),
                       'Egresos:', 'S/. '.number_format($c_eg,2,'.',''),
                       'Neto:', 'S/. '.number_format($c_neto,2,'.',''),
                       'Diferencia:', $dif_txt]);
        if ($caja['observacion']) fputcsv($out, ['Observación:', $caja['observacion']]);
        fputcsv($out, []);

        if (empty($movs)) {
            fputcsv($out, ['  (Sin movimientos registrados)']);
        } else {
            // Cabecera movimientos
            fputcsv($out, ['  #','  Fecha/Hora','  Tipo','  Origen','  Descripción','  Método','  Monto','  Cajero','  Observación']);
            foreach ($movs as $i => $m) {
                $signo = $m['tipo']==='ingreso' ? '+' : '-';
                fputcsv($out, [
                    '  '.($i+1),
                    '  '.$m['fecha_fmt'],
                    '  '.ucfirst($m['tipo']),
                    '  '.ucfirst($m['tipo_referencia']),
                    '  '.$m['descripcion'],
                    '  '.ucfirst($m['metodo_pago']),
                    '  '.$signo.'S/. '.number_format($m['monto'],2,'.',''),
                    '  '.$m['cajero_mov'],
                    '  '.$m['observacion'],
                ]);
            }
            fputcsv($out, ['  Subtotal caja:', '', '', '', '',
                           'Ing: +S/. '.number_format($c_ing,2,'.',''),
                           'Eg: -S/. '.number_format($c_eg,2,'.',''),
                           'Neto: S/. '.number_format($c_neto,2,'.','')]);
        }
        fputcsv($out, []);
    }

    // Totales finales
    fputcsv($out, ['━━━ TOTALES GENERALES ━━━']);
    fputcsv($out, ['Cajas:', count($cajas), 'Cerradas:', $cerradas_cnt, 'Con diferencia:', $con_dif_cnt, 'Total movimientos:', $g_movs]);
    fputcsv($out, ['Total ingresos:', 'S/. '.number_format($g_ingresos,2,'.',''),
                   'Total egresos:', 'S/. '.number_format($g_egresos,2,'.',''),
                   'Neto:', 'S/. '.number_format($g_neto,2,'.','')]);

    fclose($out);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// EXCEL (.xlsx nativo ZipArchive + OOXML)
// Estructura: por cada caja → fila cabecera + filas de movimientos + subtotal
// ══════════════════════════════════════════════════════════════════════════════
if ($formato === 'excel') {
    try {
        $xesc = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        // Índices de estilo
        $S_NORMAL  = 0;  // normal
        $S_TITULO  = 1;  // título principal (navy bold blanco)
        $S_SUBTIT  = 2;  // subtítulo (azul oscuro blanco)
        $S_CAB_MOV = 3;  // cabecera columnas movimientos (navy bold)
        $S_CAJA_HD = 4;  // fila cabecera de caja (azul medio bold blanco)
        $S_CAJA_DT = 5;  // fila datos de caja (azul claro)
        $S_ING     = 6;  // ingreso (verde)
        $S_EG      = 7;  // egreso (rojo)
        $S_MOV_N   = 8;  // fila movimiento normal
        $S_MOV_A   = 9;  // fila movimiento alternada
        $S_SUB     = 10; // subtotal caja (gris oscuro bold)
        $S_TOTAL   = 11; // totales finales (navy bold blanco)
        $S_PIE     = 12; // pie de página
        $S_CENTRO  = 13; // centrado normal
        $S_CENTRO_A= 14; // centrado alternado

        $NCOLS = 9; // #, Fecha/Hora, Tipo, Origen, Descripción, Método, Monto, Cajero, Observación
        $rowsXml = ''; $rowNum = 1; $merges = [];

        $colLetter = function(int $idx): string {
            $letter = ''; $idx++;
            while ($idx > 0) { $idx--; $letter = chr(65 + ($idx % 26)) . $letter; $idx = (int)($idx / 26); }
            return $letter;
        };
        $lastCol = $colLetter($NCOLS - 1);

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

        $mergeRow = function(string $val, int $si) use (&$addRow, &$merges, &$rowNum, $NCOLS, $lastCol) {
            // El merge se registra aquí — NO agregar $merges[] externamente antes de llamar a esta función
            $merges[] = 'A'.$rowNum.':'.$lastCol.$rowNum;
            $cells = [[$val, $si, 's']];
            for ($i = 1; $i < $NCOLS; $i++) $cells[] = ['', $si, 's'];
            $addRow($cells);
        };

        // ── TÍTULO GLOBAL ─────────────────────────────────────────────────────
        $mergeRow('REPORTE CONSOLIDADO DE CAJAS — SysInversiones CH Computer', $S_TITULO);

        $sub = 'Período: '.$label_periodo.'   |   Cajas: '.count($cajas).'   |   Cerradas: '.$cerradas_cnt.'   |   Con diferencia: '.$con_dif_cnt.'   |   Ingresos: S/. '.number_format($g_ingresos,2).'   |   Egresos: S/. '.number_format($g_egresos,2).'   |   Neto: S/. '.number_format($g_neto,2);
        $mergeRow($sub, $S_SUBTIT);
        $mergeRow('Generado el: '.date('d/m/Y H:i:s').'   |   Total movimientos: '.$g_movs, $S_SUBTIT);

        $emptyRow();

        // ── POR CADA CAJA ─────────────────────────────────────────────────────
        foreach ($cajas as $caja) {
            $movs   = $movimientos[$caja['id_caja']] ?? [];
            $c_ing  = array_sum(array_map(fn($m) => $m['tipo']==='ingreso'?(float)$m['monto']:0, $movs));
            $c_eg   = array_sum(array_map(fn($m) => $m['tipo']==='egreso' ?(float)$m['monto']:0, $movs));
            $c_neto = $c_ing - $c_eg;
            $dif    = (float)$caja['diferencia'];
            $dif_txt = abs($dif)<0.01 ? 'Exacto' : (($dif>0?'Sobrante +':'Faltante -').'S/. '.number_format(abs($dif),2));
            $dur    = $dur_fmt((int)$caja['duracion_min']);

            $mergeRow('CAJA #'.$caja['id_caja'].' — '.$caja['nombre_caja'].'   |   Cajero: '.$caja['cajero'].'   |   Estado: '.strtoupper($caja['estado']).'   |   Duración: '.$dur, $S_CAJA_HD);

            $mergeRow('Apertura: '.$caja['apertura'].'   |   Cierre: '.($caja['cierre'] ?? 'En curso').'   |   Fondo inicial: S/. '.number_format($caja['monto_inicial'],2).'   |   Ingresos: S/. '.number_format($c_ing,2).'   |   Egresos: S/. '.number_format($c_eg,2).'   |   Neto: S/. '.number_format($c_neto,2).'   |   Diferencia: '.$dif_txt, $S_CAJA_DT);

            if ($caja['observacion']) {
                $mergeRow('Observación: '.$caja['observacion'], $S_CAJA_DT);
            }

            if (empty($movs)) {
                $mergeRow('(Sin movimientos registrados en esta caja)', $S_MOV_N);
            } else {
                // Cabecera columnas movimientos — sin merge, una celda por columna
                $cabs = ['#','Fecha / Hora','Tipo','Origen','Descripción','Método de pago','Monto','Cajero','Observación'];
                $addRow(array_map(fn($h) => [$h, $S_CAB_MOV, 's'], $cabs));

                // Filas de movimientos
                $i = 0;
                foreach ($movs as $m) {
                    $i++;
                    $alt    = ($i % 2 === 0);
                    $es_ing = ($m['tipo'] === 'ingreso');
                    $d      = $alt ? $S_MOV_A : $S_MOV_N;
                    $c      = $alt ? $S_CENTRO_A : $S_CENTRO;
                    $signo  = $es_ing ? '+' : '-';
                    $addRow([
                        [$i,                             $c,                    'n'],
                        [$m['fecha_fmt'],                $c,                    's'],
                        [ucfirst($m['tipo']),            $es_ing?$S_ING:$S_EG, 's'],
                        [ucfirst($m['tipo_referencia']), $d,                    's'],
                        [$m['descripcion'],              $d,                    's'],
                        [ucfirst($m['metodo_pago']),     $c,                    's'],
                        [$signo.'S/. '.number_format($m['monto'],2,'.',''), $es_ing?$S_ING:$S_EG, 's'],
                        [$m['cajero_mov'],               $d,                    's'],
                        [$m['observacion'],              $d,                    's'],
                    ]);
                }

                // Subtotal — merge completo A:I sin parciales
                $mergeRow('Subtotal caja #'.$caja['id_caja'].' — '.$caja['nombre_caja'].' ('.count($movs).' movimientos)   |   Ing: +S/. '.number_format($c_ing,2).'   |   Eg: -S/. '.number_format($c_eg,2).'   |   Neto: S/. '.number_format($c_neto,2), $S_SUB);
            }

            $emptyRow();
        }

        // ── TOTALES FINALES ───────────────────────────────────────────────────
        $mergeRow('TOTALES GENERALES DEL PERÍODO: '.$label_periodo, $S_TOTAL);
        $mergeRow('Cajas: '.count($cajas).'   |   Cerradas: '.$cerradas_cnt.'   |   Con diferencia: '.$con_dif_cnt.'   |   Total movimientos: '.$g_movs.'   |   Ingresos: S/. '.number_format($g_ingresos,2).'   |   Egresos: S/. '.number_format($g_egresos,2).'   |   Neto: S/. '.number_format($g_neto,2), $S_TOTAL);

        $emptyRow();

        // Pie
        $mergeRow('SysInversiones CH Computer — Reporte generado el '.date('d/m/Y H:i:s'), $S_PIE);

        // ── Anchos de columna ─────────────────────────────────────────────────
        $colWidths = [5, 20, 11, 13, 42, 18, 22, 24, 30];
        $colsXml = '';
        foreach ($colWidths as $idx => $w)
            $colsXml .= '<col min="'.($idx+1).'" max="'.($idx+1).'" width="'.$w.'" customWidth="1"/>';

        $mergesXml = '<mergeCells count="'.count($merges).'">';
        foreach ($merges as $mr) $mergesXml .= '<mergeCell ref="'.$mr.'"/>';
        $mergesXml .= '</mergeCells>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheetViews><sheetView workbookViewId="0" tabSelected="1"><selection activeCell="A1"/></sheetView></sheetViews>
<sheetFormatPr defaultRowHeight="15"/>
<cols>'.$colsXml.'</cols>
<sheetData>'.$rowsXml.'</sheetData>
'.$mergesXml.'
</worksheet>';

        // ── Estilos ───────────────────────────────────────────────────────────
        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="10">
  <font><sz val="11"/><name val="Calibri"/></font>
  <font><sz val="14"/><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="9"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="8"/><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="9"/><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="8"/><name val="Calibri"/></font>
  <font><sz val="8"/><b/><color rgb="FF065F46"/><name val="Calibri"/></font>
  <font><sz val="8"/><b/><color rgb="FF7F1D1D"/><name val="Calibri"/></font>
  <font><sz val="8"/><b/><color rgb="FF1E293B"/><name val="Calibri"/></font>
  <font><sz val="7"/><i/><color rgb="FF94A3B8"/><name val="Calibri"/></font>
</fonts>
<fills count="13">
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF0C1A3A"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF0F3460"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF1E40AF"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFE0F2FE"/></patternFill></fill>
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFE8F5E9"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFFF3E0"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFD1FAE5"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF334155"/></patternFill></fill>
</fills>
<borders count="3">
  <border><left/><right/><top/><bottom/><diagonal/></border>
  <border><left style="thin"><color rgb="FFFFFFFF"/></left><right style="thin"><color rgb="FFFFFFFF"/></right><top style="thin"><color rgb="FFFFFFFF"/></top><bottom style="thin"><color rgb="FFFFFFFF"/></bottom><diagonal/></border>
  <border><left/><right/><top/><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border>
</borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="15">
  <xf numFmtId="0" fontId="0"  fillId="0"  borderId="0" xfId="0"/>
  <xf numFmtId="0" fontId="1"  fillId="2"  borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="2"  fillId="3"  borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="3"  fillId="2"  borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="4"  fillId="4"  borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="left"   vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="2"  fillId="5"  borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="left"   vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="6"  fillId="9"  borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="7"  fillId="10" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="5"  fillId="6"  borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="5"  fillId="7"  borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="8"  fillId="12" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="3"  fillId="2"  borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="9"  fillId="0"  borderId="0" xfId="0" applyFont="1"><alignment horizontal="right"/></xf>
  <xf numFmtId="0" fontId="5"  fillId="6"  borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="5"  fillId="7"  borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
</cellXfs>
</styleSheet>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Reporte Consolidado" sheetId="1" r:id="rId1"/></sheets>
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
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"    Target="styles.xml"/>
</Relationships>';

        $tmpFile = tempnam(sys_get_temp_dir(), 'rcx_') . '.xlsx';
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

        $filename = 'reporte_consolidado_cajas_'.date('Ymd_His').'.xlsx';
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
