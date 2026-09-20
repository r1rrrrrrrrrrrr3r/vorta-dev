<?php

function xlsx_column_letter(int $index): string
{
    $letters = '';
    $n = $index + 1;
    while ($n > 0) {
        $rem = ($n - 1) % 26;
        $letters = chr(65 + $rem) . $letters;
        $n = intdiv($n - 1, 26);
    }
    return $letters;
}

function xlsx_escape($value): string
{
    $text = (string) $value;

    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text);
    return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function xlsx_is_number($value): bool
{
    if (is_int($value) || is_float($value)) {
        return true;
    }
    if (!is_string($value) || $value === '') {
        return false;
    }
    if (!preg_match('/^-?\d+(\.\d+)?$/', $value)) {
        return false;
    }

    if (strlen($value) > 1 && $value[0] === '0') {
        return false;
    }
    if (strlen(ltrim($value, '-')) > 15) {
        return false;
    }
    return true;
}

function xlsx_build_sheet(array $headers, array $rows): string
{
    $colCount = max(count($headers), 1);

    $widths = [];
    foreach ($headers as $i => $h) {
        $widths[$i] = min(60, max(10, mb_strlen((string) $h) + 4));
    }
    $sampled = 0;
    foreach ($rows as $row) {
        $values = array_values($row);
        foreach ($values as $i => $v) {
            $len = mb_strlen((string) $v) + 2;
            if (!isset($widths[$i]) || $len > $widths[$i]) {
                $widths[$i] = min(60, max(10, $len));
            }
        }
        if (++$sampled >= 200) {
            break;
        }
    }

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

    $xml .= '<cols>';
    for ($i = 0; $i < $colCount; $i++) {
        $w = $widths[$i] ?? 14;
        $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
    }
    $xml .= '</cols>';

    $xml .= '<sheetViews><sheetView workbookViewId="0">';
    $xml .= '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>';
    $xml .= '</sheetView></sheetViews>';

    $xml .= '<sheetData>';

    $xml .= '<row r="1">';
    foreach ($headers as $i => $header) {
        $ref = xlsx_column_letter($i) . '1';
        $xml .= '<c r="' . $ref . '" s="1" t="inlineStr"><is><t>' . xlsx_escape($header) . '</t></is></c>';
    }
    $xml .= '</row>';

    $rowNum = 1;
    foreach ($rows as $row) {
        $rowNum++;
        $xml .= '<row r="' . $rowNum . '">';
        foreach (array_values($row) as $i => $value) {
            $ref = xlsx_column_letter($i) . $rowNum;
            if ($value === null || $value === '') {
                continue;
            }
            if (xlsx_is_number($value)) {
                $xml .= '<c r="' . $ref . '"><v>' . xlsx_escape($value) . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . xlsx_escape($value) . '</t></is></c>';
            }
        }
        $xml .= '</row>';
    }

    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function xlsx_build(array $headers, array $rows, string $sheetName = 'Sheet1'): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('The PHP zip extension is required to build XLSX files.');
    }

    $sheetName = preg_replace('/[:\\\\\\/?*\[\]]/', '-', $sheetName);
    $sheetName = trim(mb_substr($sheetName, 0, 31));
    if ($sheetName === '') {
        $sheetName = 'Sheet1';
    }

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . xlsx_escape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="3">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFE5E7EB"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        . '</cellXfs>'
        . '</styleSheet>';

    $sheet = xlsx_build_sheet($headers, $rows);

    $tmp = tempnam(sys_get_temp_dir(), 'vorta_xlsx_');
    if ($tmp === false) {
        throw new RuntimeException('Could not create a temporary file for the XLSX export.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        throw new RuntimeException('Could not open the XLSX archive for writing.');
    }

    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rootRels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    $bytes = file_get_contents($tmp);
    @unlink($tmp);

    if ($bytes === false) {
        throw new RuntimeException('Could not read the generated XLSX file.');
    }
    return $bytes;
}

function xlsx_download(string $filename, array $headers, array $rows, string $sheetName = 'Sheet1'): void
{
    $bytes = xlsx_build($headers, $rows, $sheetName);

    $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);
    if (!preg_match('/\.xlsx$/i', $filename)) {
        $filename = preg_replace('/\.(xls|html?|csv)$/i', '', $filename) . '.xlsx';
    }

    if (ob_get_length()) {
        ob_end_clean();
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
    header('Pragma: public');

    echo $bytes;
    exit;
}
