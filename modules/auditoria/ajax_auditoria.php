<?php
// ============================================================
// modules/auditoria/ajax_auditoria.php | SysInversiones CH Computer
// Endpoint AJAX para el módulo de Auditoría
// ============================================================
ob_start(); // captura cualquier output accidental (warnings, BOM, etc.)

$ruta_base = '../../';
require_once $ruta_base . 'conf/database.php';
require_once $ruta_base . 'conf/verificar_acceso.php';

// Solo el administrador puede acceder a la auditoría
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 1);
verificar_acceso([ROL_ADMINISTRADOR]);

if (!isset($pdo) || !($pdo instanceof PDO)) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Sin conexión BD']); exit;
}

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Detectar si es exportación (no JSON)
$es_exportacion = isset($_GET['exportar']);
if (!$es_exportacion) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
}

// ── STATS ─────────────────────────────────────────────────────────────────────
if ($accion === 'stats') {
    try {
        $hoy    = date('Y-m-d');
        $semana = date('Y-m-d', strtotime('-7 days'));

        $st = $pdo->query("
            SELECT
                COUNT(CASE WHEN DATE(fecha) = '$hoy'    THEN 1 END) AS hoy,
                COUNT(CASE WHEN DATE(fecha) >= '$semana' THEN 1 END) AS semana,
                COUNT(*) AS total,
                COUNT(DISTINCT id_usuario) AS usuarios_activos
            FROM auditoria
        ");
        $stats = $st->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true] + $stats);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── DETALLE DE UN REGISTRO ────────────────────────────────────────────────────
if ($accion === 'detalle') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['ok' => false, 'msg' => 'ID inválido']); exit; }
    try {
        $st = $pdo->prepare("
            SELECT a.*, u.nombre_completo AS nombre_usuario, u.username
            FROM auditoria a
            LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
            WHERE a.id = ?
        ");
        $st->execute([$id]);
        $reg = $st->fetch(PDO::FETCH_ASSOC);
        if (!$reg) { echo json_encode(['ok' => false, 'msg' => 'Registro no encontrado']); exit; }
        echo json_encode(['ok' => true, 'registro' => $reg]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    }
    exit;
}

// ── EXPORTAR CSV ──────────────────────────────────────────────────────────────
if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    try {
        [$sql, $params] = buildQuery(false);
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        ob_end_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="auditoria_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
        fputcsv($out, ['ID','Fecha','Usuario','Módulo','Acción','Tabla','ID Registro','Campo','Valor Antes','Valor Nuevo','Descripción','IP']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'], $r['fecha'], $r['nombre_usuario'] ?? '', $r['modulo'],
                $r['accion'], $r['tabla'] ?? '', $r['id_registro'] ?? '',
                $r['campo'] ?? '', $r['valor_antes'] ?? '', $r['valor_nuevo'] ?? '',
                $r['descripcion'] ?? '', $r['ip'] ?? ''
            ]);
        }
        fclose($out);
    } catch (PDOException $e) {
        ob_end_clean();
        echo 'Error: ' . $e->getMessage();
    }
    exit;
}

// ── EXPORTAR EXCEL (.xlsx nativo con ZipArchive + OOXML, sin warnings) ────────
if (isset($_GET['exportar']) && $_GET['exportar'] === 'excel') {
    try {
        [$sql, $params] = buildQuery(false);
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // ── Helpers ───────────────────────────────────────────────────────────
        $xesc = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        // ── Índices de estilos (definidos en styles.xml) ──────────────────────
        // 0 = normal, 1 = título, 2 = subtítulo, 3 = cabecera,
        // 4 = dato normal, 5 = dato alterno, 6 = mono normal, 7 = mono alterno, 8 = pie
        $S_NORMAL  = 0;
        $S_TITULO  = 1;
        $S_SUBTIT  = 2;
        $S_CAB     = 3;
        $S_DATO    = 4;
        $S_DATO_A  = 5;
        $S_MONO    = 6;
        $S_MONO_A  = 7;
        $S_PIE     = 8;
        $S_CENTRO  = 9;
        $S_CENTRO_A= 10;

        // ── Construir filas XML ───────────────────────────────────────────────
        $rowsXml = '';
        $rowNum  = 1;

        $addRow = function(array $cells) use (&$rowsXml, &$rowNum, $xesc) {
            $rowsXml .= '<row r="' . $rowNum . '">';
            $col = 0;
            foreach ($cells as [$val, $styleIdx, $type]) {
                $colLetter = chr(65 + $col); // A-L
                $cellRef   = $colLetter . $rowNum;
                if ($type === 'n') {
                    $rowsXml .= '<c r="' . $cellRef . '" s="' . $styleIdx . '" t="n"><v>' . (int)$val . '</v></c>';
                } else {
                    $rowsXml .= '<c r="' . $cellRef . '" s="' . $styleIdx . '" t="inlineStr"><is><t>' . $xesc($val) . '</t></is></c>';
                }
                $col++;
            }
            $rowsXml .= '</row>';
            $rowNum++;
        };

        $emptyRow = function(int $cols = 12) use (&$addRow, $S_NORMAL) {
            $cells = [];
            for ($i = 0; $i < $cols; $i++) $cells[] = ['', $S_NORMAL, 's'];
            $addRow($cells);
        };

        // Fila título (12 celdas, primera con texto)
        $addRow([
            ['REPORTE DE AUDITORIA DEL SISTEMA - SysInversiones CH Computer', $S_TITULO, 's'],
            ...array_fill(0, 11, ['', $S_TITULO, 's'])
        ]);

        // Fila subtítulo
        $addRow([
            ['Generado el ' . date('d/m/Y H:i:s') . '   |   Total: ' . count($rows) . ' registros', $S_SUBTIT, 's'],
            ...array_fill(0, 11, ['', $S_SUBTIT, 's'])
        ]);

        $emptyRow();

        // Cabeceras
        $headers = ['ID','Fecha','Usuario','Modulo','Accion','Tabla','ID Reg.','Campo','Valor Antes','Valor Nuevo','Descripcion','IP'];
        $addRow(array_map(fn($h) => [$h, $S_CAB, 's'], $headers));

        // Datos
        $i = 0;
        foreach ($rows as $r) {
            $i++;
            $alt = ($i % 2 === 0);
            $d   = $alt ? $S_DATO_A   : $S_DATO;
            $m   = $alt ? $S_MONO_A   : $S_MONO;
            $c   = $alt ? $S_CENTRO_A : $S_CENTRO;

            $addRow([
                [$r['id'] ?? 0,                                              $c, 'n'],
                [$r['fecha'] ?? '',                                          $d, 's'],
                [$r['nombre_usuario'] ?? '',                                 $d, 's'],
                [ucfirst($r['modulo'] ?? ''),                                $c, 's'],
                [ucfirst(str_replace('_',' ',$r['accion'] ?? '')),           $c, 's'],
                [$r['tabla'] ?? '',                                          $m, 's'],
                [$r['id_registro'] ?? '',                                    $c, 's'],
                [$r['campo'] ?? '',                                          $m, 's'],
                [$r['valor_antes'] ?? '',                                    $m, 's'],
                [$r['valor_nuevo'] ?? '',                                    $m, 's'],
                [$r['descripcion'] ?? '',                                    $d, 's'],
                [$r['ip'] ?? '',                                             $m, 's'],
            ]);
        }

        $emptyRow();
        $addRow([
            ['SysInversiones CH Computer - Reporte generado el ' . date('d/m/Y H:i:s'), $S_PIE, 's'],
            ...array_fill(0, 11, ['', $S_PIE, 's'])
        ]);

        // ── Anchos de columna ─────────────────────────────────────────────────
        $colWidths = [6, 16, 24, 12, 14, 12, 8, 14, 20, 20, 36, 14];
        $colsXml = '';
        foreach ($colWidths as $idx => $w) {
            $colsXml .= '<col min="' . ($idx+1) . '" max="' . ($idx+1) . '" width="' . $w . '" customWidth="1"/>';
        }

        // ── sheet1.xml ────────────────────────────────────────────────────────
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheetViews><sheetView workbookViewId="0"><selection activeCell="A1"/></sheetView></sheetViews>
<sheetFormatPr defaultRowHeight="15"/>
<cols>' . $colsXml . '</cols>
<sheetData>' . $rowsXml . '</sheetData>
<mergeCells count="4">
  <mergeCell ref="A1:L1"/>
  <mergeCell ref="A2:L2"/>
  <mergeCell ref="A' . ($rowNum-1) . ':L' . ($rowNum-1) . '"/>
</mergeCells>
</worksheet>';

        // ── styles.xml ────────────────────────────────────────────────────────
        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="6">
  <font><sz val="11"/><name val="Calibri"/></font>
  <font><sz val="13"/><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="9"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="9"/><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="8"/><name val="Calibri"/></font>
  <font><sz val="7"/><i/><color rgb="FF94A3B8"/><name val="Calibri"/></font>
</fonts>
<fills count="7">
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF1E3A8A"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/></patternFill></fill>
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFEFF6FF"/></patternFill></fill>
  <fill><patternFill patternType="none"/></fill>
</fills>
<borders count="3">
  <border><left/><right/><top/><bottom/><diagonal/></border>
  <border><left style="thin"><color rgb="FFFFFFFF"/></left><right style="thin"><color rgb="FFFFFFFF"/></right><top style="thin"><color rgb="FFFFFFFF"/></top><bottom style="thin"><color rgb="FFFFFFFF"/></bottom><diagonal/></border>
  <border><left/><right/><top/><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border>
</borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="11">
  <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
  <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="4" fillId="4" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="4" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>
  <xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="right"/></xf>
  <xf numFmtId="0" fontId="4" fillId="4" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="5" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
</cellXfs>
</styleSheet>';

        // ── workbook.xml ──────────────────────────────────────────────────────
        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Auditoria" sheetId="1" r:id="rId1"/></sheets>
</workbook>';

        // ── [Content_Types].xml ───────────────────────────────────────────────
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"            ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"   ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml"              ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

        // ── _rels/.rels ───────────────────────────────────────────────────────
        $relsRoot = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

        // ── xl/_rels/workbook.xml.rels ────────────────────────────────────────
        $relsWorkbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"   Target="styles.xml"/>
</Relationships>';

        // ── Ensamblar ZIP ─────────────────────────────────────────────────────
        $tmpFile = tempnam(sys_get_temp_dir(), 'audit_') . '.xlsx';
        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('No se pudo crear el archivo ZIP temporal.');
        }
        $zip->addFromString('[Content_Types].xml',            $contentTypes);
        $zip->addFromString('_rels/.rels',                    $relsRoot);
        $zip->addFromString('xl/workbook.xml',                $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels',     $relsWorkbook);
        $zip->addFromString('xl/styles.xml',                  $stylesXml);
        $zip->addFromString('xl/worksheets/sheet1.xml',       $sheetXml);
        $zip->close();

        // ── Enviar al navegador ───────────────────────────────────────────────
        $filename = 'auditoria_' . date('Ymd_His') . '.xlsx';
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

// ── LISTAR REGISTROS (paginado + filtros) ─────────────────────────────────────
function buildQuery(bool $conPaginacion): array {
    global $pdo;

    $modulo      = trim($_GET['modulo']      ?? '');
    $accion_f    = trim($_GET['accion']      ?? '');
    $usuario     = trim($_GET['usuario']     ?? '');
    $fecha_desde = trim($_GET['fecha_desde'] ?? '');
    $fecha_hasta = trim($_GET['fecha_hasta'] ?? '');
    $buscar      = trim($_GET['buscar']      ?? '');
    $pagina      = max(1, (int)($_GET['pagina']    ?? 1));
    $por_pagina  = min(100, max(10, (int)($_GET['por_pagina'] ?? 25)));

    $where  = ['1=1'];
    $params = [];

    if ($modulo)      { $where[] = 'a.modulo = ?';                    $params[] = $modulo; }
    if ($accion_f)    { $where[] = 'a.accion = ?';                    $params[] = $accion_f; }
    if ($usuario)     { $where[] = '(u.nombre_completo LIKE ? OR u.username LIKE ?)'; $params[] = "%$usuario%"; $params[] = "%$usuario%"; }
    if ($fecha_desde) { $where[] = 'DATE(a.fecha) >= ?';              $params[] = $fecha_desde; }
    if ($fecha_hasta) { $where[] = 'DATE(a.fecha) <= ?';              $params[] = $fecha_hasta; }
    if ($buscar)      { $where[] = '(a.descripcion LIKE ? OR a.campo LIKE ? OR a.valor_antes LIKE ? OR a.valor_nuevo LIKE ?)';
                        $params[] = "%$buscar%"; $params[] = "%$buscar%"; $params[] = "%$buscar%"; $params[] = "%$buscar%"; }

    $whereStr = implode(' AND ', $where);

    $sql = "
        SELECT a.*, u.nombre_completo AS nombre_usuario, u.username
        FROM auditoria a
        LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
        WHERE $whereStr
        ORDER BY a.fecha DESC
    ";

    if ($conPaginacion) {
        $offset = ($pagina - 1) * $por_pagina;
        $sql .= " LIMIT $por_pagina OFFSET $offset";
    }

    return [$sql, $params, $whereStr, $params, $pagina, $por_pagina];
}

try {
    [$sql, $params, $whereStr, $countParams, $pagina, $por_pagina] = buildQuery(true);

    // Contar total
    $stCount = $pdo->prepare("
        SELECT COUNT(*) FROM auditoria a
        LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
        WHERE $whereStr
    ");
    $stCount->execute($countParams);
    $total = (int)$stCount->fetchColumn();

    // Obtener registros
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $registros = $st->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok'         => true,
        'registros'  => $registros,
        'total'      => $total,
        'pagina'     => $pagina,
        'por_pagina' => $por_pagina,
    ]);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
