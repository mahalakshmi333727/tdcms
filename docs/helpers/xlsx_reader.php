<?php
/**
 * Minimal native .xlsx reader.
 *
 * An .xlsx file is a ZIP archive of XML parts. This reads just enough of
 * it — shared strings + the first worksheet — to pull out a simple table
 * of header => value rows, using only PHP's built-in ZipArchive and
 * SimpleXML extensions. No Composer package (e.g. PhpSpreadsheet) is used,
 * in keeping with this project's Core-PHP-only requirement.
 *
 * Limitations (acceptable for a task-import template):
 *   - Reads the FIRST worksheet only.
 *   - Cell formatting/formulas are ignored; only the literal/shared-string
 *     value of each cell is read.
 *   - The first row of the sheet is treated as the header row.
 */

declare(strict_types=1);

/**
 * Convert a cell reference like "C7" into a zero-based column index (2).
 */
function xlsx_col_to_index(string $cellRef): int
{
    $letters = preg_replace('/[^A-Za-z]/', '', $cellRef);
    $index = 0;
    $len = strlen($letters);
    for ($i = 0; $i < $len; $i++) {
        $index = $index * 26 + (ord(strtoupper($letters[$i])) - 64);
    }
    return $index - 1; // zero-based
}

/**
 * Resolve the physical XML part for the FIRST worksheet (first tab, in
 * document order) using workbook.xml + workbook.xml.rels, since the
 * filename is not reliably "sheet1.xml" in every .xlsx file. Falls back to
 * 'xl/worksheets/sheet1.xml' if anything about this resolution fails.
 */
function xlsx_resolve_first_sheet_path(ZipArchive $zip): string
{
    $fallback = 'xl/worksheets/sheet1.xml';

    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($workbookXml === false || $relsXml === false) {
        return $fallback;
    }

    $workbook = @simplexml_load_string($workbookXml);
    $rels = @simplexml_load_string($relsXml);
    if ($workbook === false || $rels === false || !isset($workbook->sheets->sheet[0])) {
        return $fallback;
    }

    // First <sheet> element in document order = first tab shown in Excel.
    $firstSheet = $workbook->sheets->sheet[0];
    $rIdAttrs = $firstSheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $rId = isset($rIdAttrs['id']) ? (string) $rIdAttrs['id'] : '';
    if ($rId === '') {
        return $fallback;
    }

    foreach ($rels->Relationship as $rel) {
        if ((string) $rel['Id'] !== $rId) {
            continue;
        }

        $target = (string) $rel['Target'];
        if ($target === '') {
            return $fallback;
        }

        // Targets come in two flavors depending on the writer:
        //   "/xl/worksheets/sheet2.xml"  — absolute from the package root
        //   "worksheets/sheet2.xml"      — relative to the xl/ folder
        if ($target[0] === '/') {
            return ltrim($target, '/');
        }
        if (str_starts_with($target, '../')) {
            // Relative path that escapes xl/ — resolve against the package root.
            return ltrim(preg_replace('#^(\.\./)+#', '', $target), '/');
        }
        return 'xl/' . $target;
    }

    return $fallback;
}

/**
 * Read the first worksheet of an .xlsx file into an array of associative
 * rows keyed by the (lowercased, trimmed) header text from row 1.
 * Blank rows are skipped.
 *
 * @throws RuntimeException on any read/parse failure, with a message safe
 *         to show to the admin uploading the file.
 */
function read_xlsx_first_sheet(string $filePath): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('The server is missing the PHP zip extension, which is required to read .xlsx files.');
    }
    if (!extension_loaded('simplexml')) {
        throw new RuntimeException('The server is missing the PHP simplexml extension, which is required to read .xlsx files.');
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new RuntimeException('The uploaded file could not be opened. Make sure it is a valid .xlsx file.');
    }

    // Shared strings table (optional — small sheets sometimes inline everything)
    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $sst = @simplexml_load_string($sharedXml);
        if ($sst !== false) {
            foreach ($sst->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string) $si->t;
                } else {
                    // Rich text runs: concatenate every <r><t>...</t></r>
                    $text = '';
                    if (isset($si->r)) {
                        foreach ($si->r as $run) {
                            $text .= (string) $run->t;
                        }
                    }
                    $sharedStrings[] = $text;
                }
            }
        }
    }

    // Resolve the FIRST worksheet's actual XML part. Its filename is not
    // reliably "sheet1.xml" — that mapping is defined by workbook.xml (tab
    // order) + workbook.xml.rels (id -> file), and can point anywhere after
    // a sheet has been reordered/deleted in Excel, or in files exported by
    // Google Sheets/Numbers/LibreOffice. Falls back to sheet1.xml only if
    // that resolution isn't possible.
    $sheetPath = xlsx_resolve_first_sheet_path($zip);
    $sheetXml = $zip->getFromName($sheetPath);
    if ($sheetXml === false && $sheetPath !== 'xl/worksheets/sheet1.xml') {
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    }
    $zip->close();

    if ($sheetXml === false) {
        throw new RuntimeException('Could not find worksheet data in the .xlsx file (expected a first sheet).');
    }

    $sheet = @simplexml_load_string($sheetXml);
    if ($sheet === false || !isset($sheet->sheetData)) {
        throw new RuntimeException('The .xlsx worksheet could not be parsed.');
    }

    $rawRows = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        $maxIndex = -1;
        foreach ($row->c as $c) {
            $ref = (string) $c['r'];
            $colIndex = $ref !== '' ? xlsx_col_to_index($ref) : (count($cells));
            $type = (string) $c['t'];

            if ($type === 's') {
                $sIndex = (int) $c->v;
                $value = $sharedStrings[$sIndex] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = isset($c->is->t) ? (string) $c->is->t : '';
            } elseif (isset($c->v)) {
                $value = (string) $c->v;
            } else {
                $value = '';
            }

            $cells[$colIndex] = $value;
            $maxIndex = max($maxIndex, $colIndex);
        }

        $rowValues = [];
        for ($i = 0; $i <= $maxIndex; $i++) {
            $rowValues[] = $cells[$i] ?? '';
        }
        $rawRows[] = $rowValues;
    }

    if (empty($rawRows)) {
        return [];
    }

    $headers = array_map(
        static fn($h) => strtolower(trim((string) $h)),
        array_shift($rawRows)
    );

    $result = [];
    foreach ($rawRows as $rowValues) {
        $assoc = [];
        foreach ($headers as $i => $header) {
            if ($header === '') {
                continue;
            }
            $assoc[$header] = $rowValues[$i] ?? '';
        }
        // Skip rows that are entirely blank
        $hasContent = false;
        foreach ($assoc as $v) {
            if (trim((string) $v) !== '') {
                $hasContent = true;
                break;
            }
        }
        if ($hasContent) {
            $result[] = $assoc;
        }
    }

    return $result;
}
