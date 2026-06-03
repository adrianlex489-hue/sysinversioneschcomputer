<?php
// ============================================================
// modules/personas/ajax_clientes_export.php | SysInversiones CH Computer
// Exportación de Clientes — CSV y Excel (.xlsx)
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

if (!isset($pdo) || !($pdo instanceof PDO)) {
    ob_end_clean(); die('Error: Sin conexión a la base de datos.');
}

$formato = $_GET['exportar'] ?? 'csv';   // csv | excel
$tipo    = $_GET['tipo']     ?? 'all';   // natural | empresa | all

// ── Obtener datos — columnas exactas de cada tabla ───────────────────────────
$nat_rows = $emp_rows = [];

if ($tipo !== 'empresa') {
    // clientes_natural: nombres, apellido_paterno, apellido_materno, tipo_documento, documento_identidad
    $nat_rows = $pdo->query(
        "SELECT id_cliente_natural, nombres, apellido_paterno, apellido_materno,
                tipo_documento, documento_identidad,
                telefono, email, direccion,
                CASE estado_cliente WHEN 1 THEN 'Activo' ELSE 'Inactivo' END AS estado,
                DATE_FORMAT(fecha_registro,'%d/%m/%Y') AS fecha_registro
         FROM clientes_natural ORDER BY apellido_paterno ASC, nombres ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

if ($tipo !== 'natural') {
    // clientes_empresa: razon_social, ruc (sin nombres ni tipo_documento)
    $emp_rows = $pdo->query(
        "SELECT id_cliente_empresa, razon_social, ruc,
                telefono, email, direccion,
                CASE estado_cliente WHEN 1 THEN 'Activo' ELSE 'Inactivo' END AS estado,
                DATE_FORMAT(fecha_registro,'%d/%m/%Y') AS fecha_registro
         FROM clientes_empresa ORDER BY razon_social ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

// ── Etiqueta del tipo para el nombre de archivo ───────────────────────────────
$label_tipo = match($tipo) {
    'natural' => 'personas_naturales',
    'empresa' => 'empresas_juridicas',
    default   => 'todos_clientes',
};

// ══════════════════════════════════════════════════════════════════════════════
// EXPORTAR CSV
// ══════════════════════════════════════════════════════════════════════════════
if ($formato === 'csv') {
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clientes_' . $label_tipo . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

    if ($tipo !== 'empresa') {
        fputcsv($out, ['=== PERSONAS NATURALES ===']);
        // Columnas exactas de clientes_natural
        fputcsv($out, ['ID','Apellido Paterno','Apellido Materno','Nombres','Tipo Documento','N° Documento','Teléfono','Email','Dirección','Estado','Fecha Registro']);
        foreach ($nat_rows as $r) {
            fputcsv($out, [
                $r['id_cliente_natural'],
                $r['apellido_paterno'],
                $r['apellido_materno'] ?? '',
                $r['nombres'],
                $r['tipo_documento'],
                $r['documento_identidad'],
                $r['telefono'] ?? '',
                $r['email'] ?? '',
                $r['direccion'] ?? '',
                $r['estado'],
                $r['fecha_registro'],
            ]);
        }
        if ($tipo === 'all') fputcsv($out, []);
    }

    if ($tipo !== 'natural') {
        fputcsv($out, ['=== EMPRESAS / JURÍDICAS ===']);
        // Columnas exactas de clientes_empresa
        fputcsv($out, ['ID','Razón Social','RUC','Teléfono','Email','Dirección','Estado','Fecha Registro']);
        foreach ($emp_rows as $r) {
            fputcsv($out, [
                $r['id_cliente_empresa'],
                $r['razon_social'],
                $r['ruc'],
                $r['telefono'] ?? '',
                $r['email'] ?? '',
                $r['direccion'] ?? '',
                $r['estado'],
                $r['fecha_registro'],
            ]);
        }
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

        // ── Índices de estilos ────────────────────────────────────────────────
        $S_NORMAL   = 0;
        $S_TITULO   = 1;
        $S_SUBTIT   = 2;
        $S_CAB_NAT  = 3;  // cabecera azul (naturales)
        $S_CAB_EMP  = 4;  // cabecera naranja (empresas)
        $S_DATO     = 5;
        $S_DATO_A   = 6;
        $S_CENTRO   = 7;
        $S_CENTRO_A = 8;
        $S_SECCION  = 9;  // fila de sección (verde oscuro)
        $S_PIE      = 10;
        $S_ACTIVO   = 11;
        $S_INACTIVO = 12;

        $rowsXml = '';
        $rowNum  = 1;
        $NCOLS   = 11; // máximo de columnas (naturales tiene 11: A-K)

        $addRow = function(array $cells) use (&$rowsXml, &$rowNum, $xesc) {
            $rowsXml .= '<row r="' . $rowNum . '">';
            $col = 0;
            foreach ($cells as [$val, $styleIdx, $type]) {
                $colLetter = chr(65 + $col);
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

        $emptyRow = function() use (&$addRow, $S_NORMAL) {
            $addRow(array_fill(0, 11, ['', $S_NORMAL, 's']));
        };

        $merges = [];

        // ── Fila título (A-K) ─────────────────────────────────────────────────
        $merges[] = 'A1:K1';
        $addRow([
            ['REPORTE DE CLIENTES — SysInversiones CH Computer', $S_TITULO, 's'],
            ...array_fill(0, 10, ['', $S_TITULO, 's'])
        ]);

        // ── Fila subtítulo (A-K) ──────────────────────────────────────────────
        $merges[] = 'A2:K2';
        $totalReg = count($nat_rows) + count($emp_rows);
        $addRow([
            ['Generado el ' . date('d/m/Y H:i:s') . '   |   Total: ' . $totalReg . ' clientes', $S_SUBTIT, 's'],
            ...array_fill(0, 10, ['', $S_SUBTIT, 's'])
        ]);

        $emptyRow();

        // ── PERSONAS NATURALES ────────────────────────────────────────────────
        if ($tipo !== 'empresa') {
            $merges[] = 'A' . $rowNum . ':K' . $rowNum;
            $addRow([
                ['PERSONAS NATURALES (' . count($nat_rows) . ' registros)', $S_SECCION, 's'],
                ...array_fill(0, 10, ['', $S_SECCION, 's'])
            ]);
            // Cabeceras exactas de clientes_natural (11 columnas A-K)
            $cabNat = ['ID','Apellido Paterno','Apellido Materno','Nombres','Tipo Doc.','N° Documento','Teléfono','Email','Dirección','Estado','Fecha Reg.'];
            $addRow(array_map(fn($h) => [$h, $S_CAB_NAT, 's'], $cabNat));

            $i = 0;
            foreach ($nat_rows as $r) {
                $i++;
                $alt = ($i % 2 === 0);
                $d   = $alt ? $S_DATO_A   : $S_DATO;
                $c   = $alt ? $S_CENTRO_A : $S_CENTRO;
                $est = ($r['estado'] === 'Activo') ? $S_ACTIVO : $S_INACTIVO;
                $addRow([
                    [$r['id_cliente_natural'],          $c,   'n'],
                    [$r['apellido_paterno'],             $d,   's'],
                    [$r['apellido_materno'] ?? '',       $d,   's'],
                    [$r['nombres'],                      $d,   's'],
                    [$r['tipo_documento'],               $c,   's'],
                    [$r['documento_identidad'],          $c,   's'],
                    [$r['telefono'] ?? '',               $d,   's'],
                    [$r['email'] ?? '',                  $d,   's'],
                    [$r['direccion'] ?? '',              $d,   's'],
                    [$r['estado'],                       $est, 's'],
                    [$r['fecha_registro'],               $c,   's'],
                ]);
            }
            $emptyRow();
        }

        // ── EMPRESAS / JURÍDICAS ──────────────────────────────────────────────
        if ($tipo !== 'natural') {
            $merges[] = 'A' . $rowNum . ':H' . $rowNum;
            $addRow([
                ['EMPRESAS / JURÍDICAS (' . count($emp_rows) . ' registros)', $S_SECCION, 's'],
                ...array_fill(0, 7, ['', $S_SECCION, 's'])
            ]);
            // Cabeceras exactas de clientes_empresa (8 columnas A-H)
            $cabEmp = ['ID','Razón Social','RUC','Teléfono','Email','Dirección','Estado','Fecha Reg.'];
            $addRow(array_map(fn($h) => [$h, $S_CAB_EMP, 's'], $cabEmp));

            $i = 0;
            foreach ($emp_rows as $r) {
                $i++;
                $alt = ($i % 2 === 0);
                $d   = $alt ? $S_DATO_A   : $S_DATO;
                $c   = $alt ? $S_CENTRO_A : $S_CENTRO;
                $est = ($r['estado'] === 'Activo') ? $S_ACTIVO : $S_INACTIVO;
                $addRow([
                    [$r['id_cliente_empresa'],  $c,   'n'],
                    [$r['razon_social'],         $d,   's'],
                    [$r['ruc'],                  $c,   's'],
                    [$r['telefono'] ?? '',       $d,   's'],
                    [$r['email'] ?? '',          $d,   's'],
                    [$r['direccion'] ?? '',      $d,   's'],
                    [$r['estado'],               $est, 's'],
                    [$r['fecha_registro'],       $c,   's'],
                ]);
            }
            $emptyRow();
        }

        // ── Fila pie (A-K) ────────────────────────────────────────────────────
        $merges[] = 'A' . $rowNum . ':K' . $rowNum;
        $addRow([
            ['SysInversiones CH Computer — Reporte generado el ' . date('d/m/Y H:i:s'), $S_PIE, 's'],
            ...array_fill(0, 10, ['', $S_PIE, 's'])
        ]);

        // ── Anchos de columna (11 cols para naturales, las 3 extra quedan vacías en empresas)
        // A=ID, B=Ap.Pat, C=Ap.Mat, D=Nombres, E=TipoDoc, F=NroDoc, G=Tel, H=Email, I=Dir, J=Estado, K=Fecha
        $colWidths = [6, 22, 22, 26, 10, 14, 14, 28, 34, 10, 12];
        $colsXml = '';
        foreach ($colWidths as $idx => $w) {
            $colsXml .= '<col min="' . ($idx+1) . '" max="' . ($idx+1) . '" width="' . $w . '" customWidth="1"/>';
        }

        // ── Merges XML ────────────────────────────────────────────────────────
        $mergesXml = '<mergeCells count="' . count($merges) . '">';
        foreach ($merges as $m) $mergesXml .= '<mergeCell ref="' . $m . '"/>';
        $mergesXml .= '</mergeCells>';

        // ── sheet1.xml ────────────────────────────────────────────────────────
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
           xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheetViews><sheetView workbookViewId="0"><selection activeCell="A1"/></sheetView></sheetViews>
<sheetFormatPr defaultRowHeight="15"/>
<cols>' . $colsXml . '</cols>
<sheetData>' . $rowsXml . '</sheetData>
' . $mergesXml . '
</worksheet>';

        // ── styles.xml ────────────────────────────────────────────────────────
        // Fuentes: 0=normal, 1=título, 2=subtítulo, 3=cabecera, 4=dato, 5=pie, 6=activo, 7=inactivo
        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="8">
  <font><sz val="11"/><name val="Calibri"/></font>
  <font><sz val="13"/><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="9"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="9"/><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  <font><sz val="8"/><name val="Calibri"/></font>
  <font><sz val="7"/><i/><color rgb="FF94A3B8"/><name val="Calibri"/></font>
  <font><sz val="8"/><b/><color rgb="FF166534"/><name val="Calibri"/></font>
  <font><sz val="8"/><b/><color rgb="FF991B1B"/><name val="Calibri"/></font>
</fonts>
<fills count="11">
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="gray125"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF1A5276"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF7B341E"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFC05621"/></patternFill></fill>
  <fill><patternFill patternType="none"/></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFEFF6FF"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FF1A7A4A"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/></patternFill></fill>
  <fill><patternFill patternType="solid"><fgColor rgb="FFFEE2E2"/></patternFill></fill>
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
  <xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  <xf numFmtId="0" fontId="4" fillId="6" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="7" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="6" borderId="2" xfId="0" applyFont="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="4" fillId="7" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="3" fillId="8" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="left" vertical="center"/></xf>
  <xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment horizontal="right"/></xf>
  <xf numFmtId="0" fontId="6" fillId="9"  borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
  <xf numFmtId="0" fontId="7" fillId="10" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center"/></xf>
</cellXfs>
</styleSheet>';

        // ── workbook.xml ──────────────────────────────────────────────────────
        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Clientes" sheetId="1" r:id="rId1"/></sheets>
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

        // ── Ensamblar ZIP ─────────────────────────────────────────────────────
        $tmpFile = tempnam(sys_get_temp_dir(), 'cli_') . '.xlsx';
        $zip = new ZipArchive();
        if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('No se pudo crear el archivo ZIP temporal.');
        }
        $zip->addFromString('[Content_Types].xml',          $contentTypes);
        $zip->addFromString('_rels/.rels',                  $relsRoot);
        $zip->addFromString('xl/workbook.xml',              $workbookXml);
        $zip->addFromString('xl/_rels/workbook.xml.rels',   $relsWorkbook);
        $zip->addFromString('xl/styles.xml',                $stylesXml);
        $zip->addFromString('xl/worksheets/sheet1.xml',     $sheetXml);
        $zip->close();

        $filename = 'clientes_' . $label_tipo . '_' . date('Ymd_His') . '.xlsx';
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
