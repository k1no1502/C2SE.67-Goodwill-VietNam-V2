<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

// Simple test to see if XLSX file parsing works
$testFile = __DIR__ . '/assets/excel/donation_template.xlsx';

if (!file_exists($testFile)) {
    echo json_encode(['error' => 'File not found: ' . $testFile]);
    exit;
}

require_once __DIR__ . '/config/database.php';

// Try to read XLSX
try {
    if (!class_exists('ZipArchive')) {
        throw new Exception('ZipArchive not available');
    }
    
    $zip = new ZipArchive();
    $result = $zip->open($testFile);
    
    if ($result !== true) {
        throw new Exception('ZipArchive::open failed with code: ' . $result);
    }
    
    // Get sharedStrings
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    
    $output = [
        'file_exists' => true,
        'file_size' => filesize($testFile),
        'ziparchive_ok' => true,
        'has_sharedStrings' => $sharedXml !== false,
        'has_sheet1' => $sheetXml !== false,
    ];
    
    if ($sheetXml !== false) {
        $sheet = @simplexml_load_string($sheetXml);
        if ($sheet) {
            $rowCount = isset($sheet->sheetData->row) ? count($sheet->sheetData->row) : 0;
            $output['rows_count'] = $rowCount;
            
            // Get first row
            if ($rowCount > 0) {
                $firstRow = $sheet->sheetData->row[0];
                $cellCount = isset($firstRow->c) ? count($firstRow->c) : 0;
                $output['first_row_cells'] = $cellCount;
            }
        }
    }
    
    $zip->close();
    echo json_encode(['success' => true, 'data' => $output]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
