<?php
header('Content-Type: text/html; charset=utf-8');

// Include the functions from donate.php
function excelColumnToIndex(string $letters): int
{
    $letters = strtoupper($letters);
    $len = strlen($letters);
    $index = 0;
    for ($i = 0; $i < $len; $i++) {
        $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $index - 1;
}

function readXlsxRows(string $filePath): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Máy chủ chưa bật ZipArchive.');
    }

    $rows = [];
    $zip = new ZipArchive();
    $openResult = $zip->open($filePath);
    
    if ($openResult !== true) {
        throw new RuntimeException('Cannot open XLSX: ' . $openResult);
    }

    try {
        // Shared strings
        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $shared = @simplexml_load_string($sharedXml);
            if ($shared && isset($shared->si)) {
                foreach ($shared->si as $si) {
                    $text = '';
                    foreach ($si->t as $t) {
                        $text .= (string)$t;
                    }
                    if ($text === '' && isset($si->t[0])) {
                        $text = (string)$si->t[0];
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        // First worksheet
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            throw new RuntimeException('sheet1.xml not found');
        }
        
        $sheet = @simplexml_load_string($sheetXml);
        if (!$sheet) {
            throw new RuntimeException('Cannot parse sheet1.xml');
        }
        
        if (!isset($sheet->sheetData) || !isset($sheet->sheetData->row)) {
            throw new RuntimeException('No sheetData or rows found');
        }

        foreach ($sheet->sheetData->row as $row) {
            $rowData = [];
            
            if (!isset($row->c)) {
                continue;
            }
            
            foreach ($row->c as $cell) {
                $ref = (string)$cell['r'];
                if (!preg_match('/([A-Z]+)/', $ref, $m)) {
                    continue;
                }
                
                $colIndex = excelColumnToIndex($m[1]);
                $type = (string)($cell['t'] ?? '');
                $value = '';
                
                if ($type === 's') {
                    $idx = (int)((string)($cell->v ?? ''));
                    $value = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr' || $type === 'is') {
                    $value = (string)($cell->is->t ?? ($cell->t ?? ''));
                } else {
                    $value = (string)($cell->v ?? '');
                }
                
                $rowData[$colIndex] = trim($value);
            }
            
            if (!empty($rowData)) {
                ksort($rowData);
                $rows[] = array_values($rowData);
            }
        }
    } finally {
        $zip->close();
    }
    
    return $rows;
}

echo "<h1>Debug: Check XLSX File Content</h1>";

$testFile = __DIR__ . '/assets/excel/donation_template.xlsx';
echo "<p>Testing file: <code>" . htmlspecialchars($testFile) . "</code></p>";

if (!file_exists($testFile)) {
    echo "<div style='color: red;'><strong>ERROR:</strong> File not found!</div>";
    exit;
}

echo "<p>File size: " . filesize($testFile) . " bytes</p>";
echo "<p>File exists: YES</p>";

try {
    echo "<h2>Attempting to parse XLSX...</h2>";
    $rows = readXlsxRows($testFile);
    
    echo "<p style='color: green;'><strong>SUCCESS!</strong> Parsed " . count($rows) . " rows</p>";
    
    echo "<table border='1' cellpadding='5' style='background: #f9f9f9;'>";
    echo "<tr><th>Row #</th>";
    for ($i = 0; $i < 8; $i++) {
        echo "<th>Col " . ($i + 1) . "</th>";
    }
    echo "</tr>";
    
    foreach ($rows as $idx => $row) {
        echo "<tr><td>" . ($idx + 1) . "</td>";
        for ($i = 0; $i < 8; $i++) {
            $val = isset($row[$i]) ? htmlspecialchars(substr($row[$i], 0, 30)) : '(empty)';
            echo "<td>" . $val . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<div style='color: red; border: 1px solid red; padding: 10px;'>";
    echo "<strong>ERROR:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
