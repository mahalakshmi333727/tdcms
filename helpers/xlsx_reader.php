<?php
declare(strict_types=1);

function xlsx_col_to_index(string $cellRef): int
{
    $letters = preg_replace('/[^A-Za-z]/', '', $cellRef) ?? '';
    $index = 0;
    for ($i = 0; $i < strlen($letters); $i++) $index = $index * 26 + ord(strtoupper($letters[$i])) - 64;
    return $index - 1;
}

function read_xlsx_first_sheet(string $filePath): array
{
    if (!class_exists('ZipArchive') || !extension_loaded('simplexml')) {
        throw new RuntimeException('The PHP zip and simplexml extensions are required for .xlsx imports.');
    }
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) throw new RuntimeException('The .xlsx file could not be opened.');
    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false && ($xml = @simplexml_load_string($sharedXml)) !== false) {
        foreach ($xml->si as $item) {
            $text = isset($item->t) ? (string) $item->t : '';
            foreach ($item->r as $run) $text .= (string) $run->t;
            $shared[] = $text;
        }
    }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheetXml === false || ($sheet = @simplexml_load_string($sheetXml)) === false) {
        throw new RuntimeException('The .xlsx worksheet could not be parsed.');
    }
    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $cell) {
            $index = xlsx_col_to_index((string) $cell['r']);
            $type = (string) $cell['t'];
            if ($type === 's') {
                $cells[$index] = $shared[(int) $cell->v] ?? '';
            } elseif ($type === 'inlineStr' || $type === 'str') {
                $cells[$index] = (string) ($type === 'inlineStr' ? ($cell->is->t ?? '') : ($cell->v ?? ''));
            } else {
                $cells[$index] = (string) ($cell->v ?? '');
            }
        }
        $rows[] = $cells;
    }
    if (empty($rows)) return [];
    $headers = [];
    foreach ($rows[0] as $header) $headers[] = normalize_task_key((string) $header);
    $result = [];
    foreach (array_slice($rows, 1) as $values) {
        $assoc = [];
        foreach ($headers as $index => $header) if ($header !== '') $assoc[$header] = $values[$index] ?? '';
        if (count(array_filter($assoc, static fn($value) => trim((string) $value) !== '')) > 0) $result[] = $assoc;
    }
    return $result;
}

function read_xls_first_sheet(string $filePath): array
{
    if (!class_exists('COM')) throw new RuntimeException('Legacy .xls imports require PHP COM with Microsoft Excel installed. Save the file as .xlsx or .csv.');
    $excel = new COM('Excel.Application');
    $excel->Visible = false;
    $workbook = $excel->Workbooks->Open($filePath, 0, true);
    try {
        $sheet = $workbook->Worksheets(1);
        $range = $sheet->UsedRange;
        $rows = [];
        $headers = [];
        for ($column = 1; $column <= (int) $range->Columns->Count; $column++) $headers[] = normalize_task_key((string) $sheet->Cells(1, $column)->Value);
        for ($row = 2; $row <= (int) $range->Rows->Count; $row++) {
            $assoc = [];
            for ($column = 1; $column <= count($headers); $column++) if ($headers[$column - 1] !== '') $assoc[$headers[$column - 1]] = (string) ($sheet->Cells($row, $column)->Value ?? '');
            if (count(array_filter($assoc, static fn($value) => trim((string) $value) !== '')) > 0) $rows[] = $assoc;
        }
        return $rows;
    } finally {
        $workbook->Close(false);
        $excel->Quit();
    }
}
