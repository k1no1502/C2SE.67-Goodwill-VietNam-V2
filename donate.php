<?php
header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

/**
 * Fallback placeholder image for donations without uploads/links.
 */
function buildPlaceholderSvg(string $label, string $bgColor = '#f0f4ff', string $textColor = '#1d4ed8'): string
{
    $label = trim($label);
    if ($label === '') {
        $label = 'Quyên góp';
    }
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="260">
    <rect width="100%" height="100%" rx="28" fill="{$bgColor}"/>
    <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
          font-size="42" font-family="Arial, Helvetica, sans-serif" fill="{$textColor}">
        {$label}
    </text>
</svg>
SVG;
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function getDonationPlaceholder(string $itemName, ?string $categoryName = null): string
{
    if ($categoryName && trim($categoryName) !== '') {
        return buildPlaceholderSvg($categoryName);
    }

    $source = mb_strtolower(trim($itemName));
    $map = [
        'áo' => ['Áo', '#dbeafe', '#243a77'],
        'ao' => ['Áo', '#dbeafe', '#641515'],
        'quần' => ['Quần', '#fff7ed', '#c2410c'],
        'quan' => ['Quần', '#fff7ed', '#c2410c'],
        'đồ chơi' => ['Đồ chơi', '#fef9c3', '#9ab409'],
        'do choi' => ['Đồ chơi', '#fef9c3', '#56b409'],
        'sách' => ['Sách', '#ede9fe', '#6d28d9'],
        'sach' => ['Sách', '#ede9fe', '#6d28d9'],
        'giày' => ['Giày', '#ecfccb', '#3f6212'],
        'giay' => ['Giày', '#ecfccb', '#3f6212'],
        'điện tử' => ['Điện tử', '#e0f2fe', '#0369a1'],
        'dien tu' => ['Điện tử', '#e0f2fe', '#0369a1'],
        'điện thoại' => ['Điện thoại', '#e0f2fe', '#0369a1'],
        'dien thoai' => ['Điện thoại', '#e0f2fe', '#0369a1'],
        'laptop' => ['Laptop', '#e0f2fe', '#0369a1'],
    ];

    foreach ($map as $keyword => $file) {
        if (mb_strpos($source, $keyword) !== false) {
            [$label, $bg, $text] = $file;
            return buildPlaceholderSvg($label, $bg, $text);
        }
    }

    $label = $categoryName ?: 'Quyên góp';
    return buildPlaceholderSvg($label);
}

$pageTitle = "Quyên góp";
$success = '';
$error = '';
$paymentSuccess = '';
$paymentError = '';
$bankTransferPendingId = null;

$paymentConfig = [];
$bankDetails = [];
$paymentConfigPath = __DIR__ . '/config/payment.php';
if (file_exists($paymentConfigPath)) {
    $paymentConfig = require $paymentConfigPath;
    $bankDetails = $paymentConfig['bank_transfer'] ?? [];
}

// Display return messages after sandbox payment simulation
if (!empty($_GET['payment_success'])) {
    $method = strtoupper(trim($_GET['method'] ?? ''));
    $paymentSuccess = 'Thanh toán ' . ($method ? $method . ' ' : '') . 'thành công. Cảm ơn bạn đã hỗ trợ!';
}
if (!empty($_GET['payment_error'])) {
    $method = strtoupper(trim($_GET['method'] ?? ''));
    $paymentError = 'Thanh toán ' . ($method ? $method . ' ' : '') . 'không thành công. Vui lòng thử lại.';
}

/**
 * Create a transaction record for a money donation.
 */
function createMoneyDonationTransaction(int $userId, float $amount, string $method, string $note = ''): int
{
    Database::execute(
        "INSERT INTO transactions (user_id, type, amount, status, payment_method, notes, created_at)
         VALUES (?, 'donation', ?, 'pending', ?, ?, NOW())",
        [$userId, $amount, $method, $note]
    );
    return (int)Database::lastInsertId();
}

/**
 * Convert Excel column letters (e.g., A, B, AA) to zero-based index.
 */
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

/**
 * Lightweight XLSX reader for the first sheet (returns array of rows).
 * Only uses built-in ZipArchive + SimpleXML.
 */
function readXlsxRows(string $filePath): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Máy chủ chưa bật ZipArchive. Vui lòng sử dụng file CSV hoặc XLS.');
    }

    $rows = [];
    $zip = new ZipArchive();
    $openResult = $zip->open($filePath);
    
    if ($openResult !== true) {
        error_log("[XLSX] ZipArchive::open failed with code: $openResult");
        throw new RuntimeException('Không thể mở file Excel (.xlsx). File có thể bị hỏng.');
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
        error_log("[XLSX] Loaded " . count($sharedStrings) . " shared strings");

        // First worksheet
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            error_log("[XLSX] sheet1.xml not found");
            throw new RuntimeException('Không tìm thấy dữ liệu sheet1 trong file .xlsx.');
        }
        
        $sheet = @simplexml_load_string($sheetXml);
        if (!$sheet) {
            error_log("[XLSX] Failed to parse sheet1.xml");
            throw new RuntimeException('Không thể đọc nội dung sheet1 trong file .xlsx.');
        }
        
        if (!isset($sheet->sheetData) || !isset($sheet->sheetData->row)) {
            error_log("[XLSX] No sheetData or rows found");
            throw new RuntimeException('Không tìm thấy dữ liệu trong sheet1.');
        }

        $rowCount = 0;
        $firstRowIsHeader = false;
        
        foreach ($sheet->sheetData->row as $rowIdx => $row) {
            $rowCount++;
            $rowData = [];
            
            if (!isset($row->c)) {
                continue; // Skip empty rows
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
                    // String reference
                    $idx = (int)((string)($cell->v ?? ''));
                    $value = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr' || $type === 'is') {
                    // Inline string
                    $value = (string)($cell->is->t ?? ($cell->t ?? ''));
                } else {
                    // Number or other
                    $value = (string)($cell->v ?? '');
                }
                
                $rowData[$colIndex] = trim($value);
            }
            
            if (!empty($rowData)) {
                ksort($rowData);
                $rowArray = array_values($rowData);
                
                // Check if first row looks like header
                if ($rowCount === 1) {
                    // Check common header indicators
                    $headerIndicators = ['tên', 'name', 'item', 'mô', 'description', 'danh mục', 'category', 'số lượng', 'quantity', 'đơn vị', 'unit'];
                    $isHeader = false;
                    
                    foreach ($rowArray as $cell) {
                        $cellLower = mb_strtolower(trim($cell));
                        foreach ($headerIndicators as $indicator) {
                            if (mb_strpos($cellLower, $indicator) !== false) {
                                $isHeader = true;
                                break 2;
                            }
                        }
                    }
                    
                    if ($isHeader) {
                        $firstRowIsHeader = true;
                        error_log("[XLSX] First row detected as header, skipping");
                        continue; // Skip header row
                    }
                }
                
                $rows[] = $rowArray;
            }
        }
        
        error_log("[XLSX] Successfully parsed $rowCount rows, returned " . count($rows) . " rows");
    } finally {
        $zip->close();
    }
    
    return $rows;
}

/**
 * Normalize legacy-encoded Vietnamese text to UTF-8.
 */
function normalizeVietnameseText(?string $text): string
{
    if ($text === null || $text === '') {
        return '';
    }
        $detected = safeDetectEncoding($text);
        if ($detected && strtoupper($detected) !== 'UTF-8') {
            $converted = @iconv($detected, 'UTF-8//IGNORE', $text);
            if ($converted !== false) {
                return $converted;
            }
        }
        return $text;
}

/**
 * Safely detect encoding handling environments where specific names may be unsupported.
 */
function safeDetectEncoding(string $text): ?string
{
    // Try mb_detect_encoding with the runtime order first, but filter unsupported names
    $order = mb_detect_order();
    $supportedList = mb_list_encodings();
    $supportedUpper = array_map('strtoupper', $supportedList);
    $orderFiltered = array_values(array_filter((array)$order, function ($e) use ($supportedUpper) {
        return in_array(strtoupper($e), $supportedUpper, true);
    }));
    if (!empty($orderFiltered)) {
        $enc = @mb_detect_encoding($text, $orderFiltered, true);
        if ($enc) {
            return $enc;
        }
    }

    // Candidate encodings to try if the default order fails
    $candidates = ['UTF-8', 'WINDOWS-1258', 'CP1252', 'ISO-8859-1', 'ASCII'];
    $supported = array_map('strtoupper', mb_list_encodings());
    foreach ($candidates as $c) {
        if (!in_array(strtoupper($c), $supported, true)) {
            continue;
        }
        $e = @mb_detect_encoding($text, $c, true);
        if ($e) return $e;
    }
    return null;
}

$success = '';
$error = '';

$categories = Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order, name");

// Ensure all category names are properly UTF-8 encoded
foreach ($categories as &$cat) {
    $cat['name'] = ensureUtf8($cat['name']);
}
unset($cat);

$categoryNameToId = [];
$categoryIdToName = [];
foreach ($categories as $cat) {
    $categoryNameToId[mb_strtolower(trim($cat['name']))] = $cat['category_id'];
    $categoryIdToName[$cat['category_id']] = $cat['name'];
}


// Download remote images for a single item (URLs separated by comma)
function downloadItemImagesFromUrls(string $urlList, string $uploadDir): array
{
    $result = [];
    if (trim($urlList) === '') {
        return $result;
    }
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $urls = array_filter(array_map('trim', explode(',', $urlList)));
    foreach ($urls as $url) {
        if (!preg_match('~^https?://~i', $url)) {
            continue;
        }
        $ext = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
        $ext = $ext ? '.' . strtolower($ext) : '.jpg';
        $filename = uniqid('donation_', true) . $ext;
        $content = @file_get_contents($url);
        if ($content === false) {
            continue;
        }
        if (file_put_contents($uploadDir . $filename, $content) !== false) {
            $result[] = $filename;
        }
    }
    return $result;
}

// 1) Prefill from uploaded Excel/CSV template (only load data, not insert)

// Test endpoint to check if ZipArchive and XLSX parsing works
if (isset($_GET['test']) && $_GET['test'] === 'xlsx_support') {
    header('Content-Type: application/json; charset=utf-8');
    $info = [
        'ziparchive_loaded' => extension_loaded('zip'),
        'simplexml_loaded' => extension_loaded('simplexml'),
        'class_exists_ziparchive' => class_exists('ZipArchive'),
        'temp_upload_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
    ];
    echo json_encode($info);
    exit;
}

// AJAX preview endpoint for uploaded Excel/CSV (returns parsed rows as JSON)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'excel_preview') {
    header('Content-Type: application/json; charset=utf-8');
    error_log("[EXCEL UPLOAD] AJAX excel_preview request received");
    
    if (!isset($_FILES['donation_excel'])) {
        error_log("[EXCEL UPLOAD] No file in \$_FILES");
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        exit;
    }
    
    if ($_FILES['donation_excel']['error'] !== UPLOAD_ERR_OK) {
        $errMsg = match($_FILES['donation_excel']['error']) {
            UPLOAD_ERR_INI_SIZE => 'File quá lớn (vượt quá giới hạn upload)',
            UPLOAD_ERR_FORM_SIZE => 'File quá lớn (vượt quá giới hạn form)',
            UPLOAD_ERR_PARTIAL => 'File upload không hoàn chỉnh',
            UPLOAD_ERR_NO_FILE => 'Không có file được chọn',
            UPLOAD_ERR_NO_TMP_DIR => 'Lỗi server (không có thư mục temp)',
            UPLOAD_ERR_CANT_WRITE => 'Không thể ghi file',
            UPLOAD_ERR_EXTENSION => 'Extension bị cấm',
            default => 'Lỗi upload không xác định'
        };
        error_log("[EXCEL UPLOAD] Upload error: " . $errMsg);
        echo json_encode(['success' => false, 'error' => $errMsg]);
        exit;
    }
    
    $filePath = $_FILES['donation_excel']['tmp_name'];
    $fileName = $_FILES['donation_excel']['name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    error_log("[EXCEL UPLOAD] File: $fileName, Size: " . filesize($filePath) . ", Ext: $ext");
    
    $rowsOut = [];
    try {
        if ($ext === 'xlsx') {
            error_log("[EXCEL UPLOAD] Processing XLSX file");
            $rows = readXlsxRows($filePath);
            if (!empty($rows)) {
                $rowsOut = $rows;
                error_log("[EXCEL UPLOAD] XLSX parsed, rows count: " . count($rows));
            } else {
                error_log("[EXCEL UPLOAD] XLSX file is empty");
            }
        } elseif ($ext === 'xls') {
            error_log("[EXCEL UPLOAD] Processing XLS file");
            // XLS files can be read as CSV in many cases
            if (($handle = fopen($filePath, 'r')) !== false) {
                $rowCount = 0;
                // Try to skip BOM if present
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle); // Not UTF-8 BOM, rewind
                }
                
                while (($row = fgetcsv($handle)) !== false) {
                    $rowCount++;
                    $row = array_map(function ($cell) {
                        if ($cell === null || $cell === '') return '';
                        $detected = safeDetectEncoding($cell);
                        if ($detected && strtoupper($detected) !== 'UTF-8') {
                            $converted = @mb_convert_encoding($cell, 'UTF-8', $detected);
                            if ($converted !== false) return $converted;
                        }
                        return preg_replace('/^\xEF\xBB\xBF/', '', $cell);
                    }, $row);
                    if (!empty(array_filter($row))) { // Only add non-empty rows
                        $rowsOut[] = $row;
                    }
                }
                fclose($handle);
                error_log("[EXCEL UPLOAD] XLS parsed, rows count: " . $rowCount);
                if (empty($rowsOut)) {
                    error_log("[EXCEL UPLOAD] No data found in XLS file");
                }
            }
        } elseif ($ext === 'csv') {
            error_log("[EXCEL UPLOAD] Processing CSV file");
            if (($handle = fopen($filePath, 'r')) !== false) {
                $rowCount = 0;
                // Try to skip BOM if present
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle); // Not UTF-8 BOM, rewind
                }
                
                while (($row = fgetcsv($handle)) !== false) {
                    $rowCount++;
                    $row = array_map(function ($cell) {
                        if ($cell === null || $cell === '') return '';
                        $detected = safeDetectEncoding($cell);
                        if ($detected && strtoupper($detected) !== 'UTF-8') {
                            $converted = @mb_convert_encoding($cell, 'UTF-8', $detected);
                            if ($converted !== false) return $converted;
                        }
                        return preg_replace('/^\xEF\xBB\xBF/', '', $cell);
                    }, $row);
                    if (!empty(array_filter($row))) { // Only add non-empty rows
                        $rowsOut[] = $row;
                    }
                }
                fclose($handle);
                error_log("[EXCEL UPLOAD] CSV parsed, rows count: " . $rowCount);
                if (empty($rowsOut)) {
                    error_log("[EXCEL UPLOAD] No data found in CSV file");
                }
            }
        } else {
            error_log("[EXCEL UPLOAD] Unsupported extension: $ext");
            echo json_encode(['success' => false, 'error' => 'Định dạng file không hỗ trợ. Vui lòng sử dụng CSV, XLS hoặc XLSX']);
            exit;
        }
        
        if (empty($rowsOut)) {
            error_log("[EXCEL UPLOAD] No data or empty file");
            echo json_encode(['success' => false, 'error' => 'File không có dữ liệu. Vui lòng kiểm tra file đã được điền đầy đủ']);
            exit;
        }
        
        error_log("[EXCEL UPLOAD] Success, returning " . count($rowsOut) . " rows");
        echo json_encode(['success' => true, 'rows' => $rowsOut]);
    } catch (Throwable $e) {
        error_log("[EXCEL UPLOAD] Exception: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Lỗi đọc file: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['donation_excel']) && $_FILES['donation_excel']['error'] === UPLOAD_ERR_OK) {
    $filePath = $_FILES['donation_excel']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['donation_excel']['name'], PATHINFO_EXTENSION));
    $prefill = [
        'item_name' => [],
        'description' => [],
        'category_id' => [],
        'quantity' => [],
        'unit' => [],
        'condition_status' => [],
        'estimated_value' => [],
        'image_urls' => []
    ];

    try {
        if ($ext === 'xlsx') {
            $rows = readXlsxRows($filePath);
            if (!empty($rows)) {
                array_shift($rows); // drop header
                foreach ($rows as $row) {
                    if (count($row) < 7) {
                        continue;
                    }
                    $name = trim($row[0] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $desc = $row[1] ?? '';
                    $catName = $row[2] ?? '';
                    $qty = $row[3] ?? 1;
                    $unit = $row[4] ?? 'cai';
                    $cond = $row[5] ?? 'good';
                    $value = $row[6] ?? '';
                    $imgUrls = $row[7] ?? '';

                    $catKey = mb_strtolower(trim($catName));
                    $catId = $categoryNameToId[$catKey] ?? 0;
                    $prefill['item_name'][] = $name;
                    $prefill['description'][] = trim($desc);
                    $prefill['category_id'][] = $catId;
                    $prefill['quantity'][] = max(1, (int)$qty);
                    $prefill['unit'][] = $unit !== '' ? $unit : 'cai';
                    $prefill['condition_status'][] = $cond !== '' ? $cond : 'good';
                    $prefill['estimated_value'][] = is_numeric($value) ? (float)$value : 0;
                    $prefill['image_urls'][] = $imgUrls;
                }
            }
        } elseif ($ext === 'csv' || $ext === 'xls') {
            if (($handle = fopen($filePath, 'r')) !== false) {
                // Read header first
                $header = fgetcsv($handle);
                if ($header === false) {
                    throw new RuntimeException('Không thể đọc header từ file CSV.');
                }

                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 7) {
                        continue;
                    }

                    // Normalize encoding per cell
                    $row = array_map(function ($cell) {
                        if ($cell === null || $cell === '') {
                            return '';
                        }
                        $detected = safeDetectEncoding($cell);
                        if ($detected && strtoupper($detected) !== 'UTF-8') {
                            return mb_convert_encoding($cell, 'UTF-8', $detected);
                        }
                        // Remove BOM if present
                        return preg_replace('/^\xEF\xBB\xBF/', '', $cell);
                    }, $row);

                    $name = trim($row[0] ?? '');
                    if ($name === '') {
                        continue;
                    }

                    $desc = trim($row[1] ?? '');
                    $catName = trim($row[2] ?? '');
                    $qty = $row[3] ?? 1;
                    $unit = $row[4] ?? 'cái';
                    $cond = $row[5] ?? 'good';
                    $value = $row[6] ?? '';
                    $imgUrls = $row[7] ?? '';

                    $catKey = mb_strtolower($catName);
                    $catId = $categoryNameToId[$catKey] ?? 0;
                    $prefill['item_name'][] = $name;
                    $prefill['description'][] = $desc;
                    $prefill['category_id'][] = $catId;
                    $prefill['quantity'][] = max(1, (int)$qty);
                    $prefill['unit'][] = $unit !== '' ? $unit : 'cái';
                    $prefill['condition_status'][] = $cond !== '' ? $cond : 'good';
                    $prefill['estimated_value'][] = is_numeric($value) ? (float)$value : 0;
                    $prefill['image_urls'][] = $imgUrls;
                }
                fclose($handle);
            } else {
                throw new RuntimeException('Không thể mở file CSV.');
            }
        }
    } catch (Throwable $e) {
        $error = 'Lỗi khi xử lý file: ' . $e->getMessage();
    }

    if (!empty($prefill['item_name'])) {
        $_POST = array_merge($_POST, $prefill);
        $success = "Đã tải dữ liệu từ file. Vui lòng kiểm tra và bấm gửi Quyên góp.";
    } else {
        $error = "Không đọc được dữ liệu hợp lệ từ file. Vui lòng kiểm tra nội dung.";
    }
}

// 1.5) Xử lý quyên góp tiền (ZaloPay/Momo/Chuyển khoản)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (trim($_POST['action'] ?? '') === 'money_donation')) {
    $amount = (float)($_POST['donate_amount'] ?? 0);
    $message = sanitize($_POST['donate_message'] ?? '');
    $method = strtolower(trim($_POST['payment_method'] ?? ''));
    $allowedMethods = ['momo', 'zalopay', 'bank_transfer'];

    if ($amount < 1000) {
        $paymentError = 'Vui lòng nhập số tiền hợp lệ (tối thiểu 1.000 VND).';
    } elseif (!in_array($method, $allowedMethods, true)) {
        $paymentError = 'Vui lòng chọn phương thức thanh toán.';
    } else {
        $transId = createMoneyDonationTransaction($_SESSION['user_id'], $amount, $method, $message);
        if ($method === 'bank_transfer') {
            $bankTransferPendingId = $transId;
            $paymentSuccess = 'Yêu cầu chuyển khoản đã được tạo. Vui lòng thực hiện chuyển khoản theo thông tin bên dưới và bấm "Tôi đã chuyển khoản" khi hoàn thành.';
        } else {
            header('Location: sandbox_payment.php?method=' . urlencode($method) . '&trans_id=' . $transId);
            exit;
        }
    }
}

// Xử lý xác nhận đã chuyển khoản
if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim($_POST['action'] ?? '') === 'confirm_bank_transfer') {
    $transId = (int)($_POST['trans_id'] ?? 0);
    $tx = Database::fetch('SELECT * FROM transactions WHERE trans_id = ? AND user_id = ?', [$transId, $_SESSION['user_id']]);
    if (!$tx) {
        $paymentError = 'Giao dịch không hợp lệ.';
    } elseif ($tx['payment_method'] !== 'bank_transfer') {
        $paymentError = 'Giao dịch không thuộc phương thức chuyển khoản.';
    } elseif ($tx['status'] !== 'pending') {
        $paymentError = 'Giao dịch đã được cập nhật.';
    } else {
        Database::execute('UPDATE transactions SET status = ?, payment_reference = ?, updated_at = NOW() WHERE trans_id = ?', ['completed', 'BANK-' . uniqid(), $transId]);
        logActivity($_SESSION['user_id'], 'donation_payment', "Bank transfer completed transaction #$transId");
        $paymentSuccess = 'Cảm ơn! Chúng tôi đã ghi nhận bạn đã chuyển khoản. Bạn có thể theo dõi giao dịch trong trang của tôi.';
    }
}

// 2) X? lư submit thêm quyên góp (không ph?i upload excel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !(isset($_FILES['donation_excel']) && $_FILES['donation_excel']['error'] === UPLOAD_ERR_OK)) {
    $items = [];
    $itemNames = $_POST['item_name'] ?? [];
    $descriptions = $_POST['description'] ?? [];
    $categoryIds = $_POST['category_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $units = $_POST['unit'] ?? [];
    $conditions = $_POST['condition_status'] ?? [];
    $values = $_POST['estimated_value'] ?? [];
    $imageLinks = $_POST['image_urls'] ?? [];

    $pickup_city = sanitize($_POST['pickup_city'] ?? '');
    $pickup_district = sanitize($_POST['pickup_district'] ?? '');
    $pickup_ward = sanitize($_POST['pickup_ward'] ?? '');
    $pickup_address = sanitize($_POST['pickup_address'] ?? '');
    $pickup_date = $_POST['pickup_date'] ?? '';
    $pickup_time = $_POST['pickup_time'] ?? '';
    $contact_phone = sanitize($_POST['contact_phone'] ?? '');
    if (empty($pickup_city) || empty($pickup_district) || empty($pickup_ward)) {
        $error = "Vui lòng chọn Thành phố, Quận/Huyện và Phường/Xã.";
    } elseif (empty($pickup_address)) {
        $error = "Vui lòng nhập địa chỉ nhận hàng.";
    }

    $pickup_address_full = trim(implode(', ', array_filter([
        $pickup_address,
        $pickup_ward,
        $pickup_district,
        $pickup_city
    ])));

    $count = is_array($itemNames) ? count($itemNames) : 0;
    if (!$error && $count === 0) {
        $error = "Vui lòng thêm ít nhất 1 vật phẩm.";
    }

    for ($i = 0; !$error && $i < $count; $i++) {
        $name = sanitize($itemNames[$i] ?? '');
        $desc = sanitize($descriptions[$i] ?? '');
        $catId = (int)($categoryIds[$i] ?? 0);
        $qty = (int)($quantities[$i] ?? 1);
        $unit = sanitize($units[$i] ?? "cái");
        $cond = sanitize($conditions[$i] ?? 'good');
        $val = (float)($values[$i] ?? 0);
        if ($name === '') {
            $error = "Vui lòng nhập tên vật phẩm cho tất cả hàng.";
            break;
        }
        if ($catId <= 0) {
            $error = "Vui lòng chọn danh mục cho tất cả hàng.";
            break;
        }
        if ($qty <= 0) {
            $error = "Số lượng mỗi hàng phải lớn hơn 0.";
            break;
        }
        $items[] = [
            '__index' => $i,
            'name' => $name,
            'description' => $desc,
            'category_id' => $catId,
            'category_name' => $categoryIdToName[$catId] ?? '',
            'quantity' => $qty,
            'unit' => $unit,
            'condition_status' => $cond,
            'estimated_value' => $val,
            'image_urls' => trim($imageLinks[$i] ?? '')
        ];
    }

    if (!$error) {
        try {
            Database::beginTransaction();

            $sql = "INSERT INTO donations (user_id, item_name, description, category_id, quantity, unit, 
                    condition_status, estimated_value, images, pickup_address, pickup_date, pickup_time, 
                    contact_phone, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            foreach ($items as $item) {
                $images = [];
                $googleDriveIds = []; // Lưu ID Google Drive files
                
                // tải ảnh từ URL (Nhập từ Excel/CSV)
                if (!empty($item['image_urls'])) {
                    $images = array_merge($images, downloadItemImagesFromUrls($item['image_urls'], 'uploads/donations/'));
                }
                if (isset($_FILES['item_images']['name'][$item['__index']])) {
                    $uploadDir = 'uploads/donations/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $names = $_FILES['item_images']['name'][$item['__index']];
                    $types = $_FILES['item_images']['type'][$item['__index']];
                    $tmps = $_FILES['item_images']['tmp_name'][$item['__index']];
                    $errors = $_FILES['item_images']['error'][$item['__index']];
                    $sizes = $_FILES['item_images']['size'][$item['__index']];
                    $fileCount = is_array($names) ? count($names) : 0;
                    for ($f = 0; $f < $fileCount; $f++) {
                        if ($errors[$f] === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $names[$f],
                                'type' => $types[$f],
                                'tmp_name' => $tmps[$f],
                                'error' => $errors[$f],
                                'size' => $sizes[$f]
                            ];
                            $uploadResult = uploadFile($file, $uploadDir);
                            if ($uploadResult['success']) {
                                $images[] = $uploadResult['filename'];
                                
                                // Tự động upload lên Google Drive (nếu đã cấu hình)
                                $gdriveId = uploadFileToGoogleDrive(
                                    $uploadResult['path'],
                                    'donation_' . uniqid() . '.' . pathinfo($uploadResult['filename'], PATHINFO_EXTENSION)
                                );
                                if ($gdriveId) {
                                    $googleDriveIds[] = $gdriveId;
                                    error_log("[Donation] Image backed up to Google Drive: " . $gdriveId);
                                }
                            }
                        }
                    }
                }

                // Gắn ảnh placeholder tương ứng với danh mục nếu không có ảnh
                if (empty($images)) {
                    $images[] = getCategoryPlaceholderImage($item['category_id']);
                }

                Database::execute($sql, [
                    $_SESSION['user_id'],
                    $item['name'],
                    $item['description'],
                    $item['category_id'],
                    $item['quantity'],
                    $item['unit'],
                    $item['condition_status'],
                    $item['estimated_value'],
                    json_encode($images),
                    $pickup_address_full,
                    $pickup_date ?: null,
                    $pickup_time ?: null,
                    $contact_phone
                ]);

                $donation_id = Database::lastInsertId();
                
                // Lưu Google Drive IDs vào database nếu có (tuỳ chọn)
                if (!empty($googleDriveIds)) {
                    // Nếu bạn có bảng riêng để lưu backup links, có thể sử dụng ở đây
                    // Hoặc có thể thêm cột 'google_drive_backup' vào bảng donations
                    error_log("[Donation #$donation_id] Google Drive backups: " . json_encode($googleDriveIds));
                }
                
                logActivity($_SESSION['user_id'], 'donate', "Created donation #$donation_id: {$item['name']}");
            }

            Database::commit();
            $success = "Quyên góp dă được gửi. Bạn có thể theo dõi trong trang của tôi.";
            $_POST = [];
        } catch (Exception $e) {
            Database::rollback();
            error_log("Donation error: " . $e->getMessage());
            $error = "Có lỗi xảy ra khi gửi quyên góp. Vui llòng thử lỗi.";
        }
    }
}

// D? li?u hi?n th? l?i form
$formItems = [];
if (!empty($_POST['item_name'])) {
    $count = count($_POST['item_name']);
    for ($i = 0; $i < $count; $i++) {
        $formItems[] = [
            'name' => $_POST['item_name'][$i] ?? '',
            'description' => $_POST['description'][$i] ?? '',
            'category_id' => (int)($_POST['category_id'][$i] ?? 0),
            'quantity' => $_POST['quantity'][$i] ?? 1,
            'unit' => $_POST['unit'][$i] ?? 'cái',
            'condition_status' => $_POST['condition_status'][$i] ?? 'good',
            'estimated_value' => $_POST['estimated_value'][$i] ?? '',
            'image_urls' => $_POST['image_urls'][$i] ?? ''
        ];
    }
}
if (empty($formItems)) {
    $formItems[] = [
        'name' => '',
        'description' => '',
        'category_id' => 0,
        'quantity' => 1,
        'unit' => 'cái',
        'condition_status' => 'good',
        'estimated_value' => '',
        'image_urls' => ''
    ];
}

include 'includes/header.php';
?>

<!-- Main Content -->
<section class="donate-page">
    <div class="donate-hero">
        <div class="container-fluid px-4 px-lg-5">
            <div class="donate-hero-row">
                <div class="donate-hero-icon-box">
                    <i class="bi bi-heart-fill"></i>
                </div>
                <div class="donate-hero-copy">
                    <div class="hero-heading mb-2">
                        <h1 class="mb-0 donate-hero-title">Quyên góp cho Goodwill Vietnam</h1>
                    </div>
                    <p class="mb-0 donate-hero-sub">Bạn có thể quyên góp vật phẩm hoặc tiền mặt, theo dõi minh bạch toàn bộ quá trình xử lý.</p>
                    <div class="donate-hero-badges mt-3">
                        <span class="hero-badge"><i class="bi bi-shield-check me-2"></i>Minh bạch</span>
                        <span class="hero-badge"><i class="bi bi-lightning-charge me-2"></i>Xử lý nhanh</span>
                        <span class="hero-badge"><i class="bi bi-geo-alt me-2"></i>Hỗ trợ toàn quốc</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 px-lg-5 pt-4 pb-5">
        <div class="row g-4">
            <div class="col-xl-3">
                <aside class="donate-side-panel">
                    <h5 class="fw-bold mb-3">Quy trình quyên góp</h5>
                    <div class="donate-step"><span>1</span><div>Điền thông tin vật phẩm hoặc số tiền muốn ủng hộ</div></div>
                    <div class="donate-step"><span>2</span><div>Xác nhận thông tin nhận hàng hoặc phương thức thanh toán</div></div>
                    <div class="donate-step"><span>3</span><div>Theo dõi trạng thái xử lý trong tài khoản của bạn</div></div>
                    <hr>
                    <h6 class="fw-bold">Lưu ý nhanh</h6>
                    <ul class="small mb-0 ps-3 text-muted">
                        <li>Ảnh rõ ràng giúp duyệt nhanh hơn.</li>
                        <li>Nên nhập mô tả tình trạng chi tiết.</li>
                        <li>Bạn có thể nhập danh sách bằng Excel/CSV.</li>
                    </ul>
                </aside>
            </div>

            <div class="col-xl-9">
                <div class="card shadow-sm border-0 donate-main-card">
                    <div class="card-header text-white donate-main-header">
                        <h2 class="card-title mb-0">
                            <i class="bi bi-box2-heart me-2"></i>Biểu mẫu quyên góp
                        </h2>
                        <p class="mb-0 mt-2">Điền thông tin bên dưới, hệ thống sẽ tự động ghi nhận và xử lý.</p>
                    </div>

                    <div class="card-body p-4 p-lg-4">
                        <!-- Import/Template Section -->
                        <div class="import-template-section mb-4">
                            <p class="text-muted text-center mb-3" style="font-size: 0.95rem;">Để nhập nhiều vật phẩm nhanh chóng, hãy sử dụng mẫu sau:</p>
                            <div class="row g-3">
                                <!-- Download Excel Template -->
                                <div class="col-md-4">
                                    <a href="assets/excel/donation_template.xlsx" class="btn btn-lg w-100 modern-btn-download" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; font-weight: 600;" download>
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="bi bi-download fs-4 mb-2"></i>
                                            <span class="fw-semibold">Tải mẫu Excel</span>
                                            <small class="mt-1" style="opacity: 0.85;">File .xlsx</small>
                                        </div>
                                    </a>
                                </div>

                                <!-- Download CSV Template -->
                                <div class="col-md-4">
                                    <a href="assets/excel/donation_template.csv" class="btn btn-lg w-100 modern-btn-csv" style="background: linear-gradient(135deg, #06B6D4 0%, #22d3ee 100%); color: white; border: none; font-weight: 600;">
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="bi bi-file-earmark-spreadsheet fs-4 mb-2"></i>
                                            <span class="fw-semibold">Tải mẫu CSV</span>
                                            <small class="mt-1" style="opacity: 0.85;">UTF-8</small>
                                        </div>
                                    </a>
                                </div>

                                <!-- Upload Excel File -->
                                <div class="col-md-4">
                                    <form id="excel-upload-form" action="" method="post" enctype="multipart/form-data" class="d-inline w-100">
                                        <label class="btn btn-lg w-100 modern-btn-upload mb-0" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; border: none; font-weight: 600; cursor: pointer;">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="bi bi-upload fs-4 mb-2"></i>
                                                <span class="fw-semibold">Nhập từ Excel</span>
                                                <small class="mt-1" style="opacity: 0.85;">CSV/XLS/XLSX</small>
                                            </div>
                                            <input id="donation_excel_input" type="file" name="donation_excel" accept=".csv,.xls,.xlsx" style="display:none">
                                        </label>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($paymentSuccess): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($paymentSuccess); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($paymentError): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($paymentError); ?>
                            </div>
                        <?php endif; ?>

                        <?php
                            $donationTypeSelected = 'items';
                            if (!empty($bankTransferPendingId) || (($_POST['donation_type'] ?? '') === 'money')) {
                                $donationTypeSelected = 'money';
                            }
                        ?>
                        <div class="mb-4">
                            <label class="form-label d-block fw-bold text-dark">Bạn muốn quyên góp gì ?</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="donation_type" id="donation_type_items" value="items" <?php echo $donationTypeSelected === 'items' ? 'checked' : ''; ?> >
                                <label class="form-check-label" for="donation_type_items">Quyên góp vật phẩm</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="donation_type" id="donation_type_money" value="money" <?php echo $donationTypeSelected === 'money' ? 'checked' : ''; ?> >
                                <label class="form-check-label" for="donation_type_money">Quyên góp tiền</label>
                            </div>
                        </div>

                        <div id="donation-items-section">
<form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <div id="items-container">
                                <?php foreach ($formItems as $idx => $fi): ?>
                                <div class="item-block border rounded-3 p-3 mb-3 bg-light position-relative" data-index="<?php echo $idx; ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="mb-0">Vật phẩm <span class="item-number"><?php echo $idx + 1; ?></span></h5>
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-item" style="<?php echo count($formItems) > 1 ? "" : "display:none;"; ?>">Xóa</button>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-8 mb-3">
                                            <label class="form-label">Tên vật phẩm *</label>
                                            <input type="text" class="form-control" name="item_name[]" value="<?php echo htmlspecialchars($fi['name']); ?>" placeholder="Ví dụ: áo sơ mi nam, Sách giáo khoa lớp 5...
" required>
                                            <div class="invalid-feedback">Vui lòng nhập tên vật phẩm.</div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Danh mục *</label>
                                            <select class="form-select category-select" name="category_id[]" required>
                                                <option value="">Chọn danh mục</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['category_id']; ?>" data-description="<?php echo htmlspecialchars(getCategoryDescription($category['category_id'])); ?>" <?php echo ($fi['category_id'] == $category['category_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="category-description form-text" style="color: #666; margin-top: 8px; font-size: 0.9em;"></div>
                                            <div class="invalid-feedback">Vui lòng chọn danh mục.</div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Mô tả chi tiết</label>
                                        <textarea class="form-control" name="description[]" rows="3" placeholder="Mô tả tình trạng, kích thước, màu sắc..."><?php echo htmlspecialchars($fi['description']); ?></textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Số lượng *</label>
                                            <input type="number" class="form-control" name="quantity[]" value="<?php echo htmlspecialchars($fi['quantity']); ?>" min="1" required>
                                            <div class="invalid-feedback">Số lượng phải lớn hơn 0.</div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Đơn vị</label>
                                            <select class="form-select" name="unit[]">
                                                <?php foreach (["cái","bộ","kg","cuốn","thùng"] as $u): ?>
                                                    <option value="<?php echo $u; ?>" <?php echo ($fi['unit'] === $u) ? 'selected' : ''; ?>><?php echo ucfirst($u); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Tình trạng</label>
                                            <select class="form-select" name="condition_status[]">
                                                <?php
                                                    $condOptions = [
                                                        'new' => 'Mới',
                                                        'like_new' => 'Như mới',
                                                        'good' => 'Tốt',
                                                        'fair' => 'Khá',
                                                        'poor' => 'Cũ'
                                                    ];
                                                    foreach ($condOptions as $k => $text):
                                                ?>
                                                    <option value="<?php echo $k; ?>" <?php echo ($fi['condition_status'] === $k) ? 'selected' : ''; ?>><?php echo $text; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label">Giá trị ước tính (vnd)</label>
                                            <input type="number" class="form-control" name="estimated_value[]" value="<?php echo htmlspecialchars($fi['estimated_value']); ?>" min="0" step="1000">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Hình ảnh</label>
                                        <input type="file" class="form-control images-input" name="item_images[<?php echo $idx; ?>][]" data-base-name="item_images" id="images-<?php echo $idx; ?>" multiple accept="image/*">
                                        <div class="form-text">Liên kết ảnh cho vật phẩm này.</div>
                                        <div class="image-preview mt-2" id="preview-<?php echo $idx; ?>"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Liên kết ảnh (Chỉ nhập đường link)</label>
                                        <textarea class="form-control" name="image_urls[]" rows="2" placeholder="http://example.com/anh1.jpg, http://example.com/anh2.png"><?php echo htmlspecialchars($fi['image_urls']); ?></textarea>
                                        <div class="form-text">Nếu nhập ảnh từ Excel.</div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-3">
                                <button type="button" id="add-item" class="btn btn-outline-primary">
                                    <i class="bi bi-plus-circle me-1"></i>Thêm Vật phẩm
                                </button>
                            </div>

                            <div class="mb-3">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Thành phố *</label>
                                        <select class="form-select" id="pickup_city" name="pickup_city" required data-selected="<?php echo htmlspecialchars($_POST['pickup_city'] ?? ''); ?>">
                                            <option value="">-- Chọn Thành phố --</option>
                                        </select>
                                        <div class="invalid-feedback">Vui lòng chọn Thành phố</div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Quận/Huyện *</label>
                                        <select class="form-select" id="pickup_district" name="pickup_district" required data-selected="<?php echo htmlspecialchars($_POST['pickup_district'] ?? ''); ?>" disabled>
                                            <option value="">-- Chọn Quận/Huyện --</option>
                                        </select>
                                        <div class="invalid-feedback">Vui lòng chọn Quận/Huyện</div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Phường/Xã *</label>
                                        <select class="form-select" id="pickup_ward" name="pickup_ward" required data-selected="<?php echo htmlspecialchars($_POST['pickup_ward'] ?? ''); ?>" disabled>
                                            <option value="">-- Chọn Phường/Xã --</option>
                                        </select>
                                        <div class="invalid-feedback">Vui lòng chọn Phường/Xã</div>
                                    </div>
                                </div>
                                <label class="form-label">Địa chỉ nhận hàng *</label>
                                <textarea class="form-control" name="pickup_address" rows="2" placeholder="Vui lòng nhập địa chỉ nhận hàng" required><?php echo htmlspecialchars($_POST['pickup_address'] ?? ''); ?></textarea>
                                <div class="invalid-feedback">Vui lòng nhập địa chỉ nhận hàng</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Ngày Tháng Năm</label>
                                    <input type="date" class="form-control" name="pickup_date" value="<?php echo htmlspecialchars($_POST['pickup_date'] ?? ''); ?>" min="<?php echo date('Y-m-d'); ?>">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Giờ nhận hàng</label>
                                    <input type="time" class="form-control" name="pickup_time" value="<?php echo htmlspecialchars($_POST['pickup_time'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Số điện thoại</label>
                                <input type="tel" class="form-control" name="contact_phone" value="<?php echo htmlspecialchars($_POST['contact_phone'] ?? ''); ?>" placeholder="Vui lòng nhập số điện thoại">
                            </div>


                            <div class="d-grid">
                                <button type="submit" class="btn btn-lg fw-bold" style="background: linear-gradient(135deg, #0E7490 0%, #155e75 100%); color: white; border: none;">
                                    <i class="bi bi-heart-fill me-2"></i>Gửi quyên góp
                                </button>
                            </div>
                        </form>
                        </div>

                        <div id="donation-money-section" style="display:none;">
                            <form method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="action" value="money_donation">
                                <input type="hidden" name="payment_method" id="payment_method_input" value="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Số tiền cần quyên góp (VND)</label>
                                        <input type="number" class="form-control" name="donate_amount" min="1000" step="1000" placeholder="Ví dụ: 500000" required>
                                        <div class="invalid-feedback">Vui lòng nhập số tiền.</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Lời nhắn</label>
                                        <input type="text" class="form-control" name="donate_message" placeholder="Ví dụ: Chúc chiến dịch thành công">
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <button type="button" id="donate-money-btn" class="btn btn-lg fw-bold" style="background: linear-gradient(135deg, #06B6D4 0%, #22d3ee 100%); color: white; border: none;">
                                        <i class="bi bi-cash-coin me-2"></i>Quyên góp
                                    </button>
                                </div>
                                <div id="payment-options" class="mt-3 d-none">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-outline-success" id="pay-zalopay" style="border-color: #06B6D4; color: #06B6D4;">ZaloPay</button>
                                        <button type="button" class="btn btn-outline-danger" id="pay-momo" style="border-color: #06B6D4; color: #06B6D4;">Momo</button>
                                        <button type="button" class="btn btn-outline-secondary" id="pay-bank" style="border-color: #06B6D4; color: #06B6D4;">Chuyển khoản</button>
                                    </div>
                                </div>
                            </form>

                            <?php if (!empty($bankTransferPendingId) && !empty($bankDetails)): ?>
                                <div class="card mt-3" style="border: 1px solid #cffafe; border-radius: 12px;">
                                    <div class="card-header text-white" style="background: linear-gradient(135deg, #06B6D4 0%, #22d3ee 100%);">
                                        <h5 class="mb-0">Thông tin chuyển khoản</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>Cảm ơn bạn đã chọn chuyển khoản. Vui lòng chuyển đúng số tiền và ghi rõ nội dung:</p>
                                        <ul class="list-unstyled">
                                            <?php if (!empty($bankDetails['account_name'])): ?><li><strong>Chủ tài khoản:</strong> <?php echo htmlspecialchars($bankDetails['account_name']); ?></li><?php endif; ?>
                                            <?php if (!empty($bankDetails['account_number'])): ?><li><strong>Số tài khoản:</strong> <?php echo htmlspecialchars($bankDetails['account_number']); ?></li><?php endif; ?>
                                            <?php if (!empty($bankDetails['bank_name'])): ?><li><strong>Ngân hàng:</strong> <?php echo htmlspecialchars($bankDetails['bank_name']); ?></li><?php endif; ?>
                                            <?php if (!empty($bankDetails['branch'])): ?><li><strong>Chi nhánh:</strong> <?php echo htmlspecialchars($bankDetails['branch']); ?></li><?php endif; ?>
                                            <?php if (!empty($bankDetails['note'])): ?><li><strong>Ghi chú:</strong> <?php echo htmlspecialchars($bankDetails['note']); ?></li><?php endif; ?>
                                        </ul>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="confirm_bank_transfer">
                                            <input type="hidden" name="trans_id" value="<?php echo (int)$bankTransferPendingId; ?>">
                                            <button type="submit" class="btn btn-lg fw-bold" style="background: linear-gradient(135deg, #06B6D4 0%, #22d3ee 100%); color: white; border: none;">Tôi đã chuyển khoản</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
    .donate-page {
        background: #f4fafd;
        min-height: calc(100vh - 110px);
    }

    .donate-hero {
        background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
        padding: 64px 0 52px;
        position: relative;
        overflow: hidden;
    }

    .donate-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 80% 50%, rgba(255,255,255,0.07) 0%, transparent 60%);
    }

    .donate-hero-row {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 1.7rem;
    }

    .donate-hero-icon-box {
        width: 90px;
        height: 90px;
        border-radius: 24px;
        border: 1px solid rgba(255, 255, 255, 0.25);
        background: rgba(255, 255, 255, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        backdrop-filter: blur(6px);
    }

    .donate-hero-icon-box i {
        font-size: 2.7rem;
        color: rgba(255, 255, 255, 0.95);
    }

    .hero-heading {
        max-width: 920px;
    }

    .donate-hero-copy {
        max-width: 980px;
        min-width: 0;
    }

    .donate-hero-title {
        font-size: clamp(1.9rem, 4vw, 2.95rem);
        font-weight: 900;
        color: #ffffff;
        line-height: 1.1;
    }

    .donate-hero-sub {
        color: rgba(255, 255, 255, 0.88);
        max-width: 900px;
        font-size: 1.05rem;
        line-height: 1.45;
    }

    .donate-hero-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.7rem;
    }

    .hero-badge {
        display: inline-flex;
        align-items: center;
        border: 1px solid rgba(255, 255, 255, 0.45);
        border-radius: 999px;
        padding: 0.5rem 1rem;
        background: rgba(255, 255, 255, 0.12);
        color: #ffffff;
        font-weight: 700;
        font-size: 0.9rem;
        white-space: nowrap;
    }

    .donate-side-panel {
        position: static;
        border: 1px solid #bae6fd;
        background: #ffffff;
        border-radius: 16px;
        padding: 1.2rem;
        box-shadow: 0 10px 24px rgba(8, 47, 73, 0.08);
    }

    .donate-step {
        display: flex;
        gap: 0.7rem;
        margin-bottom: 0.75rem;
        color: #164e63;
        font-size: 0.92rem;
    }

    .donate-step span {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.78rem;
        font-weight: 700;
        background: linear-gradient(135deg, #06b6d4, #0ea5e9);
        color: #fff;
        flex: 0 0 26px;
    }

    .donate-main-card {
        border: 1px solid #bae6fd;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 18px 40px rgba(8, 47, 73, 0.12);
    }

    .donate-main-header {
        background: linear-gradient(135deg, #0E7490 0%, #155e75 100%);
        padding: 1.1rem 1.4rem;
    }

    .item-block {
        border: 1px solid #cffafe !important;
        border-radius: 12px !important;
        background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%) !important;
        padding: 1.25rem !important;
        margin-bottom: 1rem !important;
        transition: all 0.25s ease;
    }

    .item-block:hover {
        box-shadow: 0 8px 20px rgba(6, 182, 212, 0.12);
        border-color: #a5f3fc !important;
    }

    .item-block h5 {
        color: #0E7490;
        font-weight: 700;
    }

    .donate-page .form-control:focus,
    .donate-page .form-select:focus {
        border-color: #06B6D4 !important;
        box-shadow: 0 0 0 0.2rem rgba(6, 182, 212, 0.25) !important;
    }

    .donate-page .form-control,
    .donate-page .form-select {
        border-color: #a5f3fc;
    }

    .donate-page .form-label {
        color: #0f172a;
        font-weight: 600;
    }

    .btn-outline-danger {
        border-color: #fee2e2;
        color: #dc2626;
    }

    .btn-outline-danger:hover {
        background-color: #fee2e2;
        border-color: #fecaca;
    }

    #add-item {
        border-color: #0E7490;
        color: #0E7490;
        font-weight: 600;
    }

    #add-item:hover {
        background-color: #e6f7fb;
        border-color: #0E7490;
        color: #0B5F76;
    }

    /* Modern Button Styling */
    .modern-btn-download,
    .modern-btn-csv,
    .modern-btn-upload {
        border: none;
        color: white;
        font-weight: 600;
        padding: 1.25rem !important;
        border-radius: 12px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    /* Download Button - Green Gradient */
    .modern-btn-download {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .modern-btn-download:hover {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        color: white;
    }

    .modern-btn-download:active {
        transform: translateY(0);
    }

    /* CSV Button - Teal Gradient */
    .modern-btn-csv {
        background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
    }

    .modern-btn-csv:hover {
        background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(6, 182, 212, 0.4);
        color: white;
    }

    .modern-btn-csv:active {
        transform: translateY(0);
    }

    /* Upload Button - Emerald Gradient */
    .modern-btn-upload {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .modern-btn-upload:hover {
        background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
        color: white;
    }

    .modern-btn-upload:active {
        transform: translateY(0);
    }

    /* Icon Animation */
    .modern-btn-download:hover i,
    .modern-btn-csv:hover i,
    .modern-btn-upload:hover i {
        animation: iconBounce 0.6s ease-in-out;
    }

    @keyframes iconBounce {
        0% { transform: translateY(0); }
        50% { transform: translateY(-4px); }
        100% { transform: translateY(0); }
    }

    @media (max-width: 991.98px) {
        .donate-hero {
            padding: 40px 0 34px;
        }

        .donate-hero-row {
            gap: 1rem;
        }

        .donate-hero-badges {
            gap: 0.65rem;
        }

        .hero-badge {
            padding: 0.52rem 0.95rem;
            font-size: 0.88rem;
        }

        .donate-hero-icon-box {
            width: 92px;
            height: 92px;
            border-radius: 22px;
        }

        .donate-hero-icon-box i {
            font-size: 2.45rem;
        }

        .donate-hero-title {
            font-size: clamp(1.85rem, 8vw, 2.95rem);
        }

        .donate-hero-sub {
            font-size: 1rem;
        }
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .modern-btn-download,
        .modern-btn-csv,
        .modern-btn-upload {
            padding: 1rem !important;
            font-size: 0.95rem;
        }

        .modern-btn-download i,
        .modern-btn-csv i,
        .modern-btn-upload i {
            font-size: 1.5rem !important;
        }
    }

    /* Import Template Section */
    .import-template-section {
        padding: 1.5rem;
        background: linear-gradient(to bottom, rgba(16, 185, 129, 0.05), rgba(20, 184, 166, 0.05));
        border-radius: 15px;
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    @media (max-width: 1199.98px) {
        .donate-side-panel {
            position: static !important;
        }
    }
</style>


<?php include 'includes/footer.php'; ?>
    
    <script>
        // Hàm cập nhật mô tả danh mục khi thay đổi category
        function initCategoryDescriptions() {
            const categorySelects = document.querySelectorAll('.category-select');
            
            categorySelects.forEach(select => {
                const updateDescription = () => {
                    const selectedOption = select.options[select.selectedIndex];
                    const description = selectedOption.getAttribute('data-description') || '';
                    
                    // Tìm div .category-description trong cùng item-block
                    const itemBlock = select.closest('.item-block');
                    if (itemBlock) {
                        const descDiv = itemBlock.querySelector('.category-description');
                        if (descDiv) {
                            descDiv.textContent = description;
                            descDiv.style.display = description ? 'block' : 'none';
                        }
                    }
                };
                
                // Cập nhật ngay khi page load
                updateDescription();
                
                // Cập nhật khi user thay đổi category
                select.addEventListener('change', updateDescription);
            });
        }
        
        // Gọi hàm khi page load
        document.addEventListener('DOMContentLoaded', function() {
            initCategoryDescriptions();
        });
        
        // Gọi lại khi thêm item mới (để bind các select mới)
        const originalAddListener = (function() {
            const addBtn = document.getElementById('add-item');
            if (addBtn) {
                const originalListener = addBtn.onclick;
                return function() {
                    if (originalListener) originalListener.call(addBtn, event);
                    setTimeout(() => initCategoryDescriptions(), 100);
                };
            }
        })();
        
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                Array.prototype.forEach.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Donation type toggle
        (function() {
            const itemsRadio = document.getElementById('donation_type_items');
            const moneyRadio = document.getElementById('donation_type_money');
            const itemsSection = document.getElementById('donation-items-section');
            const moneySection = document.getElementById('donation-money-section');
            const moneyBtn = document.getElementById('donate-money-btn');
            const paymentOptions = document.getElementById('payment-options');
            if (!itemsRadio || !moneyRadio || !itemsSection || !moneySection) return;

            const sync = () => {
                const showMoney = moneyRadio.checked;
                itemsSection.style.display = showMoney ? 'none' : 'block';
                moneySection.style.display = showMoney ? 'block' : 'none';
                if (!showMoney && paymentOptions) {
                    paymentOptions.classList.add('d-none');
                }
            };

            itemsRadio.addEventListener('change', sync);
            moneyRadio.addEventListener('change', sync);
            sync();

            <?php if (!empty($bankTransferPendingId)): ?>
                if (paymentOptions) {
                    paymentOptions.classList.remove('d-none');
                }
            <?php endif; ?>

            const paymentMethodInput = document.getElementById('payment_method_input');
            const payZaloBtn = document.getElementById('pay-zalopay');
            const payMomoBtn = document.getElementById('pay-momo');
            const payBankBtn = document.getElementById('pay-bank');
            const moneyForm = moneyBtn ? moneyBtn.closest('form') : null;

            if (moneyBtn && paymentOptions) {
                moneyBtn.addEventListener('click', () => {
                    paymentOptions.classList.remove('d-none');
                });
            }

            const submitPayment = (method) => {
                if (!moneyForm) return;
                if (paymentMethodInput) {
                    paymentMethodInput.value = method;
                }
                moneyForm.submit();
            };

            if (payZaloBtn) {
                payZaloBtn.addEventListener('click', () => submitPayment('zalopay'));
            }
            if (payMomoBtn) {
                payMomoBtn.addEventListener('click', () => submitPayment('momo'));
            }
            if (payBankBtn) {
                payBankBtn.addEventListener('click', () => submitPayment('bank_transfer'));
            }
        })();

        // Dynamic items
        const container = document.getElementById('items-container');
        const addBtn = document.getElementById('add-item');

        function attachRemoveListener(btn, block) {
            if (!btn) return;
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const targetBlock = block || btn.closest('.item-block');
                if (targetBlock) {
                    targetBlock.remove();
                    updateRemoveButtons();
                }
            });
        }

        addBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const index = container.children.length;
            const block = container.firstElementChild.cloneNode(true);
            block.dataset.index = index;
            block.querySelector('.item-number').textContent = index + 1;
            block.querySelectorAll('input[type="text"], textarea, input[type="number"]').forEach(el => { el.value = ''; });
            block.querySelectorAll('select').forEach(sel => sel.selectedIndex = 0);
            const fileInput = block.querySelector('.images-input');
            const preview = block.querySelector('.image-preview');
            fileInput.name = `item_images[${index}][]`;
            fileInput.id = `images-${index}`;
            preview.id = `preview-${index}`;
            fileInput.value = '';
            preview.innerHTML = '';
            const removeBtn = block.querySelector('.remove-item');
            if (removeBtn) {
                removeBtn.style.display = 'inline-block';
                attachRemoveListener(removeBtn, block);
            }
            container.appendChild(block);
            bindPreview(fileInput, preview);
        });

        function updateRemoveButtons() {
            const blocks = container.querySelectorAll('.item-block');
            blocks.forEach((block, idx) => {
                block.dataset.index = idx;
                block.querySelector('.item-number').textContent = idx + 1;
                const btn = block.querySelector('.remove-item');
                if (btn) btn.style.display = blocks.length > 1 ? 'inline-block' : 'none';
                const fileInput = block.querySelector('.images-input');
                const preview = block.querySelector('.image-preview');
                if (fileInput) {
                    fileInput.name = `item_images[${idx}][]`;
                    fileInput.id = `images-${idx}`;
                }
                if (preview) preview.id = `preview-${idx}`;
            });
        }

        function bindPreview(input, preview) {
            input.addEventListener('change', function(e) {
                const files = e.target.files;
                preview.innerHTML = '';
                if (files.length > 0) {
                    preview.innerHTML = '<h6>Hình ảnh đã chọn:</h6>';
                    Array.from(files).forEach(file => {
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(evt) {
                                const img = document.createElement('img');
                                img.src = evt.target.result;
                                img.className = 'img-thumbnail me-2 mb-2';
                                img.style.maxWidth = '120px';
                                img.style.maxHeight = '120px';
                                preview.appendChild(img);
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                }
            });
        }

        // Initialize - bind preview for initial blocks and remove buttons
        document.querySelectorAll('.images-input').forEach(input => {
            const idx = input.closest('.item-block').dataset.index;
            const preview = document.getElementById(`preview-${idx}`);
            bindPreview(input, preview);
        });

        document.querySelectorAll('.remove-item').forEach(btn => {
            const block = btn.closest('.item-block');
            attachRemoveListener(btn, block);
        });

        updateRemoveButtons();

        // Helper function to build a form block with proper category options
        function buildItemBlock(idx, data = {}) {
            const template = container.querySelector('.item-block');
            let block;
            
            if (template) {
                // Clone existing template if available
                block = template.cloneNode(true);
            } else {
                // Create new block from scratch
                block = document.createElement('div');
                block.className = 'item-block border rounded-3 p-3 mb-3 bg-light position-relative';
                block.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Vật phẩm <span class="item-number">${idx+1}</span></h5>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-item">Xóa</button>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Tên vật phẩm *</label>
                            <input type="text" class="form-control" name="item_name[]" placeholder="Tên vật phẩm" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Danh mục *</label>
                            <select class="form-select" name="category_id[]" required><option value="">Chọn danh mục</option></select>
                        </div>
                    </div>
                    <div class="mb-3"><label class="form-label">Mô tả chi tiết</label><textarea class="form-control" name="description[]" rows="3" placeholder="Mô tả tình trạng, kích thước, màu sắc..."></textarea></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Số lượng *</label><input type="number" class="form-control" name="quantity[]" value="1" min="1" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Đơn vị *</label><select class="form-select" name="unit[]" required><option value="cai">cái</option><option value="chiec">chiếc</option><option value="bo">bộ</option><option value="tui">túi</option><option value="hom">hộm</option></select></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Tình trạng *</label><select class="form-select" name="condition_status[]" required><option value="excellent">Tuyệt vời</option><option value="good" selected>Tốt</option><option value="fair">Trung bình</option><option value="poor">Yếu</option></select></div>
                    <div class="mb-3"><label class="form-label">Giá trị dự kiến (VND)</label><input type="number" class="form-control" name="estimated_value[]" placeholder="0" value="0"></div>
                    <div class="mb-3"><label class="form-label">URL Hình ảnh (phân cách bằng dấu phẩy)</label><textarea class="form-control" name="image_urls[]" rows="2" placeholder="http://example.com/image.jpg"></textarea></div>
                `;
            }
            
            block.dataset.index = idx;
            
            // Populate with data if provided
            if (data && typeof data === 'object' && data.length > 0) {
                console.log('[EXCEL] Raw data row: ', data);
                
                // Excel structure: Tên Vật Phẩm | Danh Mục | Mô Tả Chi Tiết | Số Lượng | Đơn Vị | Tình Trạng | Giá trị | URL Ảnh
                // Index:           0              1          2                3          4        5            6        7
                
                // Reset only necessary inputs
                block.querySelectorAll('input[name="item_name[]"], textarea[name="description[]"], textarea[name="image_urls[]"]').forEach(el => { el.value = ''; });
                block.querySelectorAll('select').forEach(sel => {
                    sel.selectedIndex = 0;
                });
                
                // Fill in the data - CORRECT MAPPING
                const nameEl = block.querySelector('input[name="item_name[]"]');
                if (nameEl && data[0]) {
                    nameEl.value = String(data[0]).trim();
                    console.log('[EXCEL] Set name:', nameEl.value);
                }
                
                // CATEGORY is at index 1 (not 2!)
                const catSelect = block.querySelector('select[name="category_id[]"]');
                if (catSelect && data[1]) {
                    const excelCatName = String(data[1]).trim();
                    console.log('[EXCEL] Trying to match category: "' + excelCatName + '"');
                    
                    let found = false;
                    Array.from(catSelect.options).forEach(opt => {
                        const optText = opt.text.trim().toLowerCase();
                        const optVal = opt.value.toString().trim();
                        const excelMatch = excelCatName.toLowerCase();
                        
                        if (optText === excelMatch || optVal === excelMatch) {
                            console.log('[EXCEL] Category matched: "' + opt.text + '" (value=' + opt.value + ')');
                            opt.selected = true;
                            found = true;
                        }
                    });
                    
                    if (!found) {
                        console.warn('[EXCEL] Category not found for: "' + excelCatName + '". Available: ' + 
                            Array.from(catSelect.options).map(o => o.text).join(', '));
                    }
                }
                
                // DESCRIPTION is at index 2 (not 1!)
                const descEl = block.querySelector('textarea[name="description[]"]');
                if (descEl && data[2]) {
                    descEl.value = String(data[2]).trim();
                    console.log('[EXCEL] Set description:', descEl.value.substring(0, 30));
                }
                
                const qtyEl = block.querySelector('input[name="quantity[]"]');
                if (qtyEl && data[3]) {
                    const qty = String(data[3]).trim();
                    console.log('[EXCEL] Setting quantity: ' + qty);
                    qtyEl.value = qty || 1;
                } else if (qtyEl) {
                    qtyEl.value = qtyEl.value || 1;
                }
                
                const unitSel = block.querySelector('select[name="unit[]"]');
                if (unitSel && data[4]) {
                    const excelUnit = String(data[4]).trim().toLowerCase();
                    console.log('[EXCEL] Trying to match unit: "' + excelUnit + '"');
                    
                    let found = false;
                    Array.from(unitSel.options).forEach(opt => {
                        if (opt.value === excelUnit || opt.text.toLowerCase() === excelUnit) {
                            console.log('[EXCEL] Unit matched: ' + opt.value);
                            opt.selected = true;
                            found = true;
                        }
                    });
                    if (!found) {
                        console.log('[EXCEL] Unit not matched, keeping default');
                    }
                }
                
                const condSel = block.querySelector('select[name="condition_status[]"]');
                if (condSel && data[5]) {
                    const excelCond = String(data[5]).trim().toLowerCase();
                    console.log('[EXCEL] Trying to match condition: "' + excelCond + '"');
                    
                    let found = false;
                    Array.from(condSel.options).forEach(opt => {
                        if (opt.value === excelCond) {
                            console.log('[EXCEL] Condition matched: ' + opt.value);
                            opt.selected = true;
                            found = true;
                        }
                    });
                    if (!found) {
                        console.log('[EXCEL] Condition not matched, keeping default');
                    }
                }
                
                const valEl = block.querySelector('input[name="estimated_value[]"]');
                if (valEl && data[6]) {
                    const val = String(data[6]).trim();
                    console.log('[EXCEL] Setting value: ' + val);
                    valEl.value = val || 0;
                } else if (valEl) {
                    valEl.value = valEl.value || 0;
                }
                
                const imgEl = block.querySelector('textarea[name="image_urls[]"]');
                if (imgEl && data[7]) {
                    const img = String(data[7]).trim();
                    console.log('[EXCEL] Setting images: ' + img);
                    imgEl.value = img;
                }
            }
            
            // Setup event listeners
            const removeBtn = block.querySelector('.remove-item');
            if (removeBtn) {
                removeBtn.style.display = 'inline-block';
                attachRemoveListener(removeBtn, block);
            }
            
            return block;
        }

        // Excel/CSV upload preview handler
        (function() {
            const excelInput = document.getElementById('donation_excel_input');
            if (!excelInput) {
                console.error('[EXCEL UPLOAD] File input element not found');
                return;
            }
            console.log('[EXCEL UPLOAD] Event listener attached to file input');
            
            excelInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) {
                    console.log('[EXCEL UPLOAD] No file selected');
                    return;
                }
                
                console.log('[EXCEL UPLOAD] File selected: ' + file.name + ' (' + file.size + ' bytes)');
                
                // Quick validation
                const ext = file.name.split('.').pop().toLowerCase();
                if (!['xlsx', 'xls', 'csv'].includes(ext)) {
                    alert('Định dạng file không hỗ trợ. Vui lòng chọn file .xlsx, .xls hoặc .csv');
                    return;
                }
                
                const fd = new FormData();
                fd.append('donation_excel', file);
                
                console.log('[EXCEL UPLOAD] Sending AJAX request to: ?ajax=excel_preview');
                fetch(window.location.pathname + '?ajax=excel_preview', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                })
                .then(response => {
                    console.log('[EXCEL UPLOAD] Response status:', response.status, response.statusText);
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                    }
                    return response.text();
                })
                .then(text => {
                    if (!text || text.trim().length === 0) {
                        throw new Error('Server returned empty response');
                    }
                    console.log('[EXCEL UPLOAD] Response text (first 200 chars):', text.substring(0, 200));
                    
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        throw new Error('JSON parse error: ' + e.message + '. Response: ' + text.substring(0, 100));
                    }
                    
                    console.log('[EXCEL UPLOAD] Parsed successfully, success =', data.success);
                    
                    if (!data.success) {
                        alert('Server Error:\\n' + (data.error || 'Unknown error'));
                        return;
                    }
                    
                    const rows = data.rows || [];
                    console.log('[EXCEL UPLOAD] Total rows:', rows.length);
                    
                    if (rows.length === 0) {
                        alert('File không có dữ liệu. Vui lòng kiểm tra file.');
                        return;
                    }
                    
                    // First row might be header, ask to confirm
                    const dataRows = rows.length > 1 ? rows.slice(1) : rows;
                    if (dataRows.length === 0) {
                        alert('File chỉ có tiêu đề mà không có dữ liệu vật phẩm.');
                        return;
                    }
                    
                    if (!confirm('Xem trước thành công (' + dataRows.length + ' vật phẩm).\\nBạn muốn điền dữ liệu vào form?')) {
                        console.log('[EXCEL UPLOAD] User cancelled');
                        return;
                    }

                    console.log('[EXCEL UPLOAD] Clearing form and populating with ' + dataRows.length + ' items');
                    
                    // Clear container and rebuild
                    container.innerHTML = '';
                    dataRows.forEach((rowData, idx) => {
                        console.log('[EXCEL UPLOAD] Processing row ' + (idx + 1), rowData);
                        const block = buildItemBlock(idx, rowData);
                        container.appendChild(block);
                    });
                    
                    updateRemoveButtons();
                    
                    // Scroll to form
                    const firstBlock = container.querySelector('.item-block');
                    if (firstBlock) firstBlock.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    
                    console.log('[EXCEL UPLOAD] Successfully populated ' + dataRows.length + ' items');
                    alert('Đã điền ' + dataRows.length + ' vật phẩm vào form. Vui lòng kiểm tra và chỉnh sửa nếu cần.');
                })
                .catch(err => {
                    console.error('[EXCEL UPLOAD] Error:', err);
                    alert('Lỗi xử lý file:\\n' + err.message);
                });
            });
        })();

        // Vietnamese address selects (City/District/Ward) via local JSON API
        (function () {
            const cityEl = document.getElementById('pickup_city');
            const districtEl = document.getElementById('pickup_district');
            const wardEl = document.getElementById('pickup_ward');
            if (!cityEl || !districtEl || !wardEl) return;

            const API_BASE = 'api/vn-address.php';

            const clearSelect = (el, placeholder) => {
                el.innerHTML = '';
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = placeholder;
                el.appendChild(opt);
                el.value = '';
            };

            const setSelectedByValue = (el, value) => {
                if (!value) return false;
                const options = Array.from(el.options);
                const found = options.find(o => (o.value || '').trim() === value.trim());
                if (found) {
                    el.value = found.value;
                    return true;
                }
                return false;
            };

            const populate = (el, items, placeholder) => {
                clearSelect(el, placeholder);
                for (const item of items) {
                    const opt = document.createElement('option');
                    opt.value = item.name;
                    opt.textContent = item.name;
                    opt.dataset.code = String(item.code);
                    el.appendChild(opt);
                }
            };

            const fetchJson = async (url) => {
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            };

            const loadCities = async () => {
                const provinces = await fetchJson(`${API_BASE}?type=provinces`);
                populate(cityEl, provinces, '-- Chọn Thành phố --');
                cityEl.disabled = false;
            };

            const loadDistricts = async (provinceCode) => {
                const districts = await fetchJson(`${API_BASE}?type=districts&province_code=${encodeURIComponent(provinceCode)}`);
                populate(districtEl, districts, '-- Chọn Quận/Huyện --');
                districtEl.disabled = false;
            };

            const loadWards = async (districtCode) => {
                const wards = await fetchJson(`${API_BASE}?type=wards&district_code=${encodeURIComponent(districtCode)}`);
                populate(wardEl, wards, '-- Chọn Phường/Xã --');
                wardEl.disabled = false;
            };

            const getSelectedCode = (el) => {
                const opt = el.options[el.selectedIndex];
                return opt ? (opt.dataset.code || '') : '';
            };

            const init = async () => {
                clearSelect(districtEl, '-- Chọn Quận/Huyện --');
                clearSelect(wardEl, '-- Chọn Phường/Xã --');
                districtEl.disabled = true;
                wardEl.disabled = true;

                try {
                    await loadCities();
                } catch (e) {
                    console.error('Failed to load provinces:', e);
                    cityEl.disabled = false;
                    return;
                }

                const selectedCity = cityEl.dataset.selected || '';
                const selectedDistrict = districtEl.dataset.selected || '';
                const selectedWard = wardEl.dataset.selected || '';

                if (setSelectedByValue(cityEl, selectedCity)) {
                    const pCode = getSelectedCode(cityEl);
                    if (pCode) {
                        try {
                            await loadDistricts(pCode);
                            if (setSelectedByValue(districtEl, selectedDistrict)) {
                                const dCode = getSelectedCode(districtEl);
                                if (dCode) {
                                    await loadWards(dCode);
                                    setSelectedByValue(wardEl, selectedWard);
                                }
                            }
                        } catch (e) {
                            console.error('Failed to restore address selects:', e);
                        }
                    }
                }
            };

            cityEl.addEventListener('change', async () => {
                clearSelect(districtEl, '-- Chọn Quận/Huyện --');
                clearSelect(wardEl, '-- Chọn Phường/Xã --');
                districtEl.disabled = true;
                wardEl.disabled = true;

                const provinceCode = getSelectedCode(cityEl);
                if (!provinceCode) return;

                try {
                    await loadDistricts(provinceCode);
                } catch (e) {
                    console.error('Failed to load districts:', e);
                }
            });

            districtEl.addEventListener('change', async () => {
                clearSelect(wardEl, '-- Chọn Phường/Xã --');
                wardEl.disabled = true;

                const districtCode = getSelectedCode(districtEl);
                if (!districtCode) return;

                try {
                    await loadWards(districtCode);
                } catch (e) {
                    console.error('Failed to load wards:', e);
                }
            });

            init();
        })();
    </script>
</body>
</html>




