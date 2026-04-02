<?php
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$endDateTime = $end_date . ' 23:59:59';

$stats = getStatistics();

$rawDonationStats = Database::fetchAll(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
            COUNT(*) AS count,
            SUM(quantity) AS total_quantity,
            SUM(CASE WHEN status = 'approved' THEN quantity ELSE 0 END) AS approved_quantity
     FROM donations
     WHERE created_at BETWEEN ? AND ?
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY month ASC",
    [$start_date, $endDateTime]
);

$statsByMonth = [];
foreach ($rawDonationStats as $item) {
    $statsByMonth[$item['month']] = [
        'count' => (int)$item['count'],
        'total_quantity' => (int)$item['total_quantity'],
        'approved_quantity' => (int)$item['approved_quantity']
    ];
}

$donationStats = [];
$cursorMonth = new DateTime(date('Y-m-01', strtotime($start_date)));
$endMonth = new DateTime(date('Y-m-01', strtotime($end_date)));
while ($cursorMonth <= $endMonth) {
    $monthKey = $cursorMonth->format('Y-m');
    $monthData = $statsByMonth[$monthKey] ?? [
        'count' => 0,
        'total_quantity' => 0,
        'approved_quantity' => 0
    ];

    $donationStats[] = [
        'month' => $monthKey,
        'count' => $monthData['count'],
        'total_quantity' => $monthData['total_quantity'],
        'approved_quantity' => $monthData['approved_quantity']
    ];

    $cursorMonth->modify('+1 month');
}

$donationGrowth = [];
$previousCount = null;
foreach ($donationStats as $stat) {
    $growth = null;
    if ($previousCount !== null) {
        $growth = $previousCount > 0
            ? round((($stat['count'] - $previousCount) / $previousCount) * 100, 2)
            : null;
    }
    $donationGrowth[] = array_merge($stat, ['growth' => $growth]);
    $previousCount = (int)$stat['count'];
}

$categoryStats = Database::fetchAll(
    "SELECT c.category_id, c.name, COUNT(*) AS count, SUM(d.quantity) AS total_quantity
     FROM donations d
     LEFT JOIN categories c ON d.category_id = c.category_id
     WHERE d.created_at BETWEEN ? AND ? AND d.status = 'approved'
     GROUP BY c.category_id, c.name
     ORDER BY count DESC
     LIMIT 10",
    [$start_date, $endDateTime]
);

$categoryNameLookupRows = Database::fetchAll(
    "SELECT category_id, name FROM categories"
);
$categoryNameLookup = [];
foreach ($categoryNameLookupRows as $rowItem) {
    $id = (int)($rowItem['category_id'] ?? 0);
    $name = trim((string)($rowItem['name'] ?? ''));
    if ($id > 0 && $name !== '') {
        $categoryNameLookup[$id] = $name;
    }
}

function resolveCategoryLabel(array $cat, array $nameLookup): string
{
    $rawName = trim((string)($cat['name'] ?? ''));
    $categoryId = (int)($cat['category_id'] ?? 0);

    if ($rawName !== '' && !ctype_digit($rawName)) {
        return $rawName;
    }

    if ($rawName !== '' && ctype_digit($rawName)) {
        $nameByRaw = $nameLookup[(int)$rawName] ?? '';
        if ($nameByRaw !== '') {
            return $nameByRaw;
        }
    }

    if ($categoryId > 0 && !empty($nameLookup[$categoryId])) {
        return (string)$nameLookup[$categoryId];
    }

    if ($categoryId > 0) {
        return 'Danh muc #' . $categoryId;
    }

    return 'Khong xac dinh';
}

$topDonors = Database::fetchAll(
    "SELECT u.name, u.email, COUNT(*) AS donation_count, SUM(d.quantity) AS total_items
     FROM donations d
     LEFT JOIN users u ON d.user_id = u.user_id
     WHERE d.created_at BETWEEN ? AND ? AND d.status = 'approved'
     GROUP BY u.user_id, u.name, u.email
     ORDER BY donation_count DESC
     LIMIT 10",
    [$start_date, $endDateTime]
);

$campaignStats = Database::fetchAll(
    "SELECT c.name, c.status, c.target_items, c.current_items,
            (SELECT COUNT(*) FROM campaign_donations WHERE campaign_id = c.campaign_id) AS donations_count
     FROM campaigns c
     WHERE c.created_at BETWEEN ? AND ?
     ORDER BY c.created_at DESC",
    [$start_date, $endDateTime]
);

$inventoryStats = [
    'total' => Database::fetch("SELECT COUNT(*) AS count FROM inventory")['count'] ?? 0,
    'available' => Database::fetch("SELECT COUNT(*) AS count FROM inventory WHERE status = 'available'")['count'] ?? 0,
    'sold' => Database::fetch("SELECT COUNT(*) AS count FROM inventory WHERE status = 'sold'")['count'] ?? 0,
    'free' => Database::fetch("SELECT COUNT(*) AS count FROM inventory WHERE price_type = 'free' AND status = 'available'")['count'] ?? 0,
    'cheap' => Database::fetch("SELECT COUNT(*) AS count FROM inventory WHERE price_type = 'cheap' AND status = 'available'")['count'] ?? 0,
];

$recentActivities = Database::fetchAll(
    "SELECT al.action, al.created_at, u.name AS user_name
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.user_id
     WHERE al.created_at BETWEEN ? AND ?
     ORDER BY al.created_at DESC
     LIMIT 10",
    [$start_date, $endDateTime]
);

$recentDonations = Database::fetchAll(
    "SELECT d.item_name, d.status, d.created_at, u.name AS donor_name, c.name AS category_name
     FROM donations d
     LEFT JOIN users u ON d.user_id = u.user_id
     LEFT JOIN categories c ON d.category_id = c.category_id
     WHERE d.created_at BETWEEN ? AND ?
     ORDER BY d.created_at DESC
     LIMIT 8",
    [$start_date, $endDateTime]
);

function xmlEsc(string $value): string
{
    $normalized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
    if ($normalized === false) {
        $normalized = $value;
    }
    $normalized = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $normalized);
    if ($normalized === null) {
        $normalized = '';
    }
    return htmlspecialchars($normalized, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function cellRef(string $col, int $row): string
{
    return $col . $row;
}

function inlineCell(string $col, int $row, string $text, int $style = 3): string
{
    return '<c r="' . cellRef($col, $row) . '" s="' . $style . '" t="inlineStr"><is><t>'
        . xmlEsc($text) . '</t></is></c>';
}

function numberCell(string $col, int $row, $num, int $style = 4): string
{
    return '<c r="' . cellRef($col, $row) . '" s="' . $style . '"><v>' . (float)$num . '</v></c>';
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    echo 'ZipArchive is not enabled on this server.';
    exit;
}

$row = 1;
$sheetRows = '';
$mergeCells = [];

// Title
$sheetRows .= '<row r="' . $row . '" ht="30" customHeight="1">' . inlineCell('A', $row, 'BAO CAO THONG KE HE THONG', 2) . '</row>';
$mergeCells[] = 'A1:F1';
$row++;

// Date range
$sheetRows .= '<row r="' . $row . '">'
    . inlineCell('A', $row, 'Tu ngay', 3)
    . inlineCell('B', $row, $start_date, 3)
    . inlineCell('C', $row, 'Den ngay', 3)
    . inlineCell('D', $row, $end_date, 3)
    . '</row>';
$row += 2;

// Section Overview
$sheetRows .= '<row r="' . $row . '">' . inlineCell('A', $row, 'TONG QUAN', 1) . '</row>';
$mergeCells[] = 'A' . $row . ':F' . $row;
$row++;
$sheetRows .= '<row r="' . $row . '">' . inlineCell('A', $row, 'Chi so', 1) . inlineCell('B', $row, 'Gia tri', 1) . '</row>';
$row++;

$overviewRows = [
    ['Tong nguoi dung', (int)($stats['users'] ?? 0)],
    ['Tong quyên góp', (int)($stats['donations'] ?? 0)],
    ['Tong vat pham', (int)($stats['items'] ?? 0)],
    ['Tong chien dich', (int)($stats['campaigns'] ?? 0)],
    ['Kho - Tong vat pham', (int)$inventoryStats['total']],
    ['Kho - Co san', (int)$inventoryStats['available']],
    ['Kho - Da ban', (int)$inventoryStats['sold']],
    ['Kho - Mien phi', (int)$inventoryStats['free']],
    ['Kho - Gia re', (int)$inventoryStats['cheap']],
];
foreach ($overviewRows as $o) {
    $sheetRows .= '<row r="' . $row . '">' . inlineCell('A', $row, $o[0], 3) . numberCell('B', $row, $o[1], 4) . '</row>';
    $row++;
}

$row++;

// Donation monthly
$donationSectionRow = $row;
$sheetRows .= '<row r="' . $row . '">' . inlineCell('A', $row, 'QUYEN GOP THEO THANG', 1) . '</row>';
$mergeCells[] = 'A' . $row . ':F' . $row;
$row++;
$sheetRows .= '<row r="' . $row . '">'
    . inlineCell('A', $row, 'Thang', 1)
    . inlineCell('B', $row, 'So luot quyên góp', 1)
    . inlineCell('C', $row, 'Tong vat pham', 1)
    . inlineCell('D', $row, 'Vat pham da duyet', 1)
    . inlineCell('E', $row, 'Tang truong (%)', 1)
    . '</row>';
$row++;

$donationDataStartRow = $row;
$donationDataEndRow = $row;

if (empty($donationGrowth)) {
    $sheetRows .= '<row r="' . $row . '">' . inlineCell('A', $row, 'Khong co du lieu', 3) . '</row>';
    $donationDataEndRow = $row;
    $row++;
} else {
    foreach ($donationGrowth as $d) {
        $sheetRows .= '<row r="' . $row . '">'
            . inlineCell('A', $row, (string)$d['month'], 3)
            . numberCell('B', $row, (int)$d['count'], 4)
            . numberCell('C', $row, (int)$d['total_quantity'], 4)
            . numberCell('D', $row, (int)($d['approved_quantity'] ?? 0), 4)
            . ($d['growth'] === null ? inlineCell('E', $row, '-', 3) : numberCell('E', $row, (float)$d['growth'], 4))
            . '</row>';
        $donationDataEndRow = $row;
        $row++;
    }
}

$row++;

// Top donors
$sheetRows .= '<row r="' . $row . '">' . inlineCell('A', $row, 'TOP NGUOI QUYEN GOP', 1) . '</row>';
$mergeCells[] = 'A' . $row . ':F' . $row;
$row++;
$sheetRows .= '<row r="' . $row . '">'
    . inlineCell('A', $row, 'Nguoi dung', 1)
    . inlineCell('B', $row, 'Email', 1)
    . inlineCell('C', $row, 'So lan quyên', 1)
    . inlineCell('D', $row, 'Tong vat pham', 1)
    . '</row>';
$row++;

if (empty($topDonors)) {
    $sheetRows .= '<row r="' . $row . '">' . inlineCell('A', $row, 'Khong co du lieu', 3) . '</row>';
    $row++;
} else {
    foreach ($topDonors as $t) {
        $sheetRows .= '<row r="' . $row . '">'
            . inlineCell('A', $row, (string)($t['name'] ?? 'Khach'), 3)
            . inlineCell('B', $row, (string)($t['email'] ?? ''), 3)
            . numberCell('C', $row, (int)$t['donation_count'], 4)
            . numberCell('D', $row, (int)$t['total_items'], 4)
            . '</row>';
        $row++;
    }
}

$row++;

// Recent activities
$sheetRows .= '<row r="' . $row . '">' . inlineCell('A', $row, 'HOAT DONG GAN DAY', 1) . '</row>';
$mergeCells[] = 'A' . $row . ':F' . $row;
$row++;
$sheetRows .= '<row r="' . $row . '">'
    . inlineCell('A', $row, 'Hanh dong', 1)
    . inlineCell('B', $row, 'Nguoi dung', 1)
    . inlineCell('C', $row, 'Thoi gian', 1)
    . '</row>';
$row++;

if (empty($recentActivities)) {
    $sheetRows .= '<row r="' . $row . '">' . inlineCell('A', $row, 'Khong co du lieu', 3) . '</row>';
    $row++;
} else {
    foreach ($recentActivities as $a) {
        $timeText = !empty($a['created_at']) ? date('H:i d/m/Y', strtotime($a['created_at'])) : '';
        $sheetRows .= '<row r="' . $row . '">'
            . inlineCell('A', $row, (string)($a['action'] ?? ''), 3)
            . inlineCell('B', $row, (string)($a['user_name'] ?? 'He thong'), 3)
            . inlineCell('C', $row, $timeText, 3)
            . '</row>';
        $row++;
    }
}

$row++;

// Recent donations
$sheetRows .= '<row r="' . $row . '">' . inlineCell('A', $row, 'QUYEN GOP GAN DAY', 1) . '</row>';
$mergeCells[] = 'A' . $row . ':F' . $row;
$row++;
$sheetRows .= '<row r="' . $row . '">'
    . inlineCell('A', $row, 'Vat pham', 1)
    . inlineCell('B', $row, 'Nguoi quyen', 1)
    . inlineCell('C', $row, 'Danh muc', 1)
    . inlineCell('D', $row, 'Trang thai', 1)
    . inlineCell('E', $row, 'Thoi gian', 1)
    . '</row>';
$row++;

if (empty($recentDonations)) {
    $sheetRows .= '<row r="' . $row . '">' . inlineCell('A', $row, 'Khong co du lieu', 3) . '</row>';
    $row++;
} else {
    foreach ($recentDonations as $d) {
        $timeText = !empty($d['created_at']) ? date('H:i d/m/Y', strtotime($d['created_at'])) : '';
        $sheetRows .= '<row r="' . $row . '">'
            . inlineCell('A', $row, (string)($d['item_name'] ?? ''), 3)
            . inlineCell('B', $row, (string)($d['donor_name'] ?? 'Khach'), 3)
            . inlineCell('C', $row, (string)($d['category_name'] ?? 'Khong xac dinh'), 3)
            . inlineCell('D', $row, ucfirst((string)($d['status'] ?? '')), 3)
            . inlineCell('E', $row, $timeText, 3)
            . '</row>';
        $row++;
    }
}

$row++;

// Campaign
$sheetRows .= '<row r="' . $row . '">' . inlineCell('A', $row, 'THONG KE CHIEN DICH', 1) . '</row>';
$mergeCells[] = 'A' . $row . ':F' . $row;
$row++;
$sheetRows .= '<row r="' . $row . '">'
    . inlineCell('A', $row, 'Ten chien dich', 1)
    . inlineCell('B', $row, 'Trang thai', 1)
    . inlineCell('C', $row, 'Muc tieu', 1)
    . inlineCell('D', $row, 'Da nhan', 1)
    . inlineCell('E', $row, 'Tien do (%)', 1)
    . inlineCell('F', $row, 'So quyên góp', 1)
    . '</row>';
$row++;

if (empty($campaignStats)) {
    $sheetRows .= '<row r="' . $row . '">' . inlineCell('A', $row, 'Khong co du lieu', 3) . '</row>';
    $row++;
} else {
    foreach ($campaignStats as $c) {
        $progress = ($c['target_items'] ?? 0) > 0
            ? min(100, round(($c['current_items'] / $c['target_items']) * 100, 2))
            : 0;

        $sheetRows .= '<row r="' . $row . '">'
            . inlineCell('A', $row, (string)($c['name'] ?? ''), 3)
            . inlineCell('B', $row, ucfirst((string)($c['status'] ?? '')), 3)
            . numberCell('C', $row, (int)$c['target_items'], 4)
            . numberCell('D', $row, (int)$c['current_items'], 4)
            . numberCell('E', $row, $progress, 4)
            . numberCell('F', $row, (int)$c['donations_count'], 4)
            . '</row>';
        $row++;
    }
}

$row++;

// Category
$sheetRows .= '<row r="' . $row . '">' . inlineCell('A', $row, 'PHAN BO DANH MUC (TOP 10)', 1) . '</row>';
$mergeCells[] = 'A' . $row . ':F' . $row;
$row++;
$categoryHeaderRow = $row;
$sheetRows .= '<row r="' . $row . '">'
    . inlineCell('A', $row, 'Danh muc', 1)
    . inlineCell('B', $row, 'So luot', 1)
    . inlineCell('C', $row, 'Tong vat pham', 1)
    . '</row>';
$row++;

$categoryDataStartRow = $row;
$categoryDataEndRow = $row;

if (empty($categoryStats)) {
    $sheetRows .= '<row r="' . $row . '">' . inlineCell('A', $row, 'Khong co du lieu', 3) . '</row>';
    $categoryDataEndRow = $row;
    $row++;
} else {
    foreach ($categoryStats as $cat) {
        $categoryLabel = resolveCategoryLabel($cat, $categoryNameLookup);
        $sheetRows .= '<row r="' . $row . '">'
            . inlineCell('A', $row, $categoryLabel, 3)
            . numberCell('B', $row, (int)$cat['count'], 4)
            . numberCell('C', $row, (int)$cat['total_quantity'], 4)
            . '</row>';
        $categoryDataEndRow = $row;
        $row++;
    }
}

$includeDonationChart = !empty($donationGrowth);
$includeCategoryChart = !empty($categoryStats);
$includeAnyChart = $includeDonationChart || $includeCategoryChart;

$lastRow = max(1, $row - 1);
$mergeXml = '';
if (!empty($mergeCells)) {
    $mergeXml = '<mergeCells count="' . count($mergeCells) . '">';
    foreach ($mergeCells as $m) {
        $mergeXml .= '<mergeCell ref="' . $m . '"/>';
    }
    $mergeXml .= '</mergeCells>';
}

$drawingXmlInSheet = '';

$lineChartFromCol = 0;
$lineChartFromRow = 2;
$lineChartToCol = 5;
$lineChartToRow = 19;

$pieChartFromCol = 6;
$pieChartFromRow = 2;
$pieChartToCol = 11;
$pieChartToRow = 19;

$donationChartRelId = '';
$categoryChartRelId = '';
$relCounter = 1;
if ($includeDonationChart) {
    $donationChartRelId = 'rId' . $relCounter;
    $relCounter++;
}
if ($includeCategoryChart) {
    $categoryChartRelId = 'rId' . $relCounter;
}

$sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<dimension ref="A1:F' . $lastRow . '"/>'
    . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
    . '<sheetFormatPr defaultRowHeight="18"/>'
    . '<cols>'
    . '<col min="1" max="1" width="36" customWidth="1"/>'
    . '<col min="2" max="2" width="28" customWidth="1"/>'
    . '<col min="3" max="3" width="18" customWidth="1"/>'
    . '<col min="4" max="4" width="18" customWidth="1"/>'
    . '<col min="5" max="5" width="18" customWidth="1"/>'
    . '<col min="6" max="6" width="18" customWidth="1"/>'
    . '</cols>'
    . '<sheetData>' . $sheetRows . '</sheetData>'
    . $mergeXml
    . $drawingXmlInSheet
    . '</worksheet>';

$chartSheetDrawingXml = $includeAnyChart ? '<drawing r:id="rId1"/>' : '';
$chartSheetRowsXml = '<row r="1" ht="34" customHeight="1"><c r="A1" s="5" t="inlineStr"><is><t>BIỂU ĐỒ THỐNG KÊ</t></is></c></row>';
$chartSheetMergeXml = '<mergeCells count="1"><mergeCell ref="A1:L1"/></mergeCells>';
$chartSheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<dimension ref="A1:L40"/>'
    . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
    . '<sheetFormatPr defaultRowHeight="18"/>'
    . '<cols>'
    . '<col min="1" max="1" width="20" customWidth="1"/>'
    . '<col min="2" max="12" width="12" customWidth="1"/>'
    . '</cols>'
    . '<sheetData>' . $chartSheetRowsXml . '</sheetData>'
    . $chartSheetMergeXml
    . $chartSheetDrawingXml
    . '</worksheet>';

$sheetRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
    . '</Relationships>';

$chartSheetRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
    . '</Relationships>';

$drawingAnchorsXml = '';
if ($includeDonationChart) {
    $drawingAnchorsXml .= '<xdr:twoCellAnchor>'
        . '<xdr:from><xdr:col>' . $lineChartFromCol . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' . $lineChartFromRow . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>'
        . '<xdr:to><xdr:col>' . $lineChartToCol . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' . $lineChartToRow . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>'
        . '<xdr:graphicFrame macro="">'
        . '<xdr:nvGraphicFramePr><xdr:cNvPr id="2" name="Donation Line Chart"/><xdr:cNvGraphicFramePr/></xdr:nvGraphicFramePr>'
        . '<xdr:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/></xdr:xfrm>'
        . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart">'
        . '<c:chart xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:id="' . $donationChartRelId . '"/>'
        . '</a:graphicData></a:graphic>'
        . '</xdr:graphicFrame>'
        . '<xdr:clientData/>'
        . '</xdr:twoCellAnchor>';
}

if ($includeCategoryChart) {
    $drawingAnchorsXml .= '<xdr:twoCellAnchor>'
        . '<xdr:from><xdr:col>' . $pieChartFromCol . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' . $pieChartFromRow . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>'
        . '<xdr:to><xdr:col>' . $pieChartToCol . '</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>' . $pieChartToRow . '</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>'
        . '<xdr:graphicFrame macro="">'
        . '<xdr:nvGraphicFramePr><xdr:cNvPr id="3" name="Category Pie Chart"/><xdr:cNvGraphicFramePr/></xdr:nvGraphicFramePr>'
        . '<xdr:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/></xdr:xfrm>'
        . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/chart">'
        . '<c:chart xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:id="' . $categoryChartRelId . '"/>'
        . '</a:graphicData></a:graphic>'
        . '</xdr:graphicFrame>'
        . '<xdr:clientData/>'
        . '</xdr:twoCellAnchor>';
}

$drawingXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . $drawingAnchorsXml
    . '</xdr:wsDr>';

$drawingRelItemsXml = '';
if ($includeDonationChart) {
    $drawingRelItemsXml .= '<Relationship Id="' . $donationChartRelId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/chart1.xml"/>';
}
if ($includeCategoryChart) {
    $categoryChartTarget = $includeDonationChart ? 'chart2.xml' : 'chart1.xml';
    $drawingRelItemsXml .= '<Relationship Id="' . $categoryChartRelId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="../charts/' . $categoryChartTarget . '"/>';
}

$drawingRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . $drawingRelItemsXml
    . '</Relationships>';

$donationCategoriesRef = "'Bao cao'!\$A\$" . $donationDataStartRow . ':\$A\$' . $donationDataEndRow;
$donationValuesRef = "'Bao cao'!\$B\$" . $donationDataStartRow . ':\$B\$' . $donationDataEndRow;

$donationPointCount = count($donationGrowth);
$donationCatCache = '';
$donationValCache = '';
foreach ($donationGrowth as $i => $d) {
    $donationCatCache .= '<c:pt idx="' . $i . '"><c:v>' . xmlEsc((string)($d['month'] ?? '')) . '</c:v></c:pt>';
    $donationValCache .= '<c:pt idx="' . $i . '"><c:v>' . (int)($d['count'] ?? 0) . '</c:v></c:pt>';
}

$donationChartXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
    . '<c:chart>'
    . '<c:title><c:tx><c:rich><a:bodyPr/><a:lstStyle/><a:p><a:r><a:rPr lang="vi-VN" sz="1200" b="1"/><a:t>Thong ke quyen gop theo thang</a:t></a:r></a:p></c:rich></c:tx><c:overlay val="0"/></c:title>'
    . '<c:plotArea><c:layout/>'
    . '<c:lineChart><c:grouping val="standard"/><c:varyColors val="0"/>'
    . '<c:ser>'
    . '<c:idx val="0"/><c:order val="0"/>'
    . '<c:tx><c:v>So quyen gop</c:v></c:tx>'
    . '<c:marker><c:symbol val="circle"/></c:marker>'
    . '<c:cat><c:strRef><c:f>' . xmlEsc($donationCategoriesRef) . '</c:f><c:strCache><c:ptCount val="' . $donationPointCount . '"/>' . $donationCatCache . '</c:strCache></c:strRef></c:cat>'
    . '<c:val><c:numRef><c:f>' . xmlEsc($donationValuesRef) . '</c:f><c:numCache><c:formatCode>General</c:formatCode><c:ptCount val="' . $donationPointCount . '"/>' . $donationValCache . '</c:numCache></c:numRef></c:val>'
    . '</c:ser>'
    . '<c:axId val="200000"/><c:axId val="200001"/>'
    . '</c:lineChart>'
    . '<c:catAx><c:axId val="200000"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="b"/><c:tickLblPos val="nextTo"/><c:crossAx val="200001"/><c:crosses val="autoZero"/><c:auto val="1"/><c:lblAlgn val="ctr"/><c:lblOffset val="100"/></c:catAx>'
    . '<c:valAx><c:axId val="200001"/><c:scaling><c:orientation val="minMax"/></c:scaling><c:delete val="0"/><c:axPos val="l"/><c:numFmt formatCode="General" sourceLinked="1"/><c:majorGridlines/><c:tickLblPos val="nextTo"/><c:crossAx val="200000"/><c:crosses val="autoZero"/><c:crossBetween val="between"/></c:valAx>'
    . '</c:plotArea>'
    . '<c:legend><c:legendPos val="t"/><c:layout/></c:legend>'
    . '<c:plotVisOnly val="1"/>'
    . '</c:chart>'
    . '</c:chartSpace>';

$categoriesRef = "'Bao cao'!\$A\$" . $categoryDataStartRow . ':\$A\$' . $categoryDataEndRow;
$valuesRef = "'Bao cao'!\$B\$" . $categoryDataStartRow . ':\$B\$' . $categoryDataEndRow;

$chartPointCount = count($categoryStats);
$chartCatCache = '';
$chartValCache = '';
foreach ($categoryStats as $i => $cat) {
    $categoryLabel = resolveCategoryLabel($cat, $categoryNameLookup);
    $chartCatCache .= '<c:pt idx="' . $i . '"><c:v>' . xmlEsc($categoryLabel) . '</c:v></c:pt>';
    $chartValCache .= '<c:pt idx="' . $i . '"><c:v>' . (int)($cat['count'] ?? 0) . '</c:v></c:pt>';
}

$categoryChartXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
    . '<c:chart>'
    . '<c:title><c:tx><c:rich><a:bodyPr/><a:lstStyle/><a:p><a:r><a:rPr lang="vi-VN" sz="1200" b="1"/><a:t>Phan bo danh muc</a:t></a:r></a:p></c:rich></c:tx><c:overlay val="0"/></c:title>'
    . '<c:plotArea><c:layout/>'
    . '<c:pieChart><c:varyColors val="1"/>'
    . '<c:ser>'
    . '<c:idx val="0"/><c:order val="0"/>'
    . '<c:tx><c:v>Phan bo danh muc</c:v></c:tx>'
    . '<c:cat><c:strRef><c:f>' . xmlEsc($categoriesRef) . '</c:f><c:strCache><c:ptCount val="' . $chartPointCount . '"/>' . $chartCatCache . '</c:strCache></c:strRef></c:cat>'
    . '<c:val><c:numRef><c:f>' . xmlEsc($valuesRef) . '</c:f><c:numCache><c:formatCode>General</c:formatCode><c:ptCount val="' . $chartPointCount . '"/>' . $chartValCache . '</c:numCache></c:numRef></c:val>'
    . '</c:ser>'
    . '</c:pieChart>'
    . '</c:plotArea>'
    . '<c:legend><c:legendPos val="r"/><c:layout/></c:legend>'
    . '<c:plotVisOnly val="1"/>'
    . '</c:chart>'
    . '</c:chartSpace>';

$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<fonts count="3">'
    . '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
    . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
    . '<font><b/><sz val="20"/><color rgb="FF000000"/><name val="Calibri"/><family val="2"/></font>'
    . '</fonts>'
    . '<fills count="4">'
    . '<fill><patternFill patternType="none"/></fill>'
    . '<fill><patternFill patternType="gray125"/></fill>'
    . '<fill><patternFill patternType="solid"><fgColor rgb="FF0E7490"/><bgColor indexed="64"/></patternFill></fill>'
    . '<fill><patternFill patternType="solid"><fgColor rgb="FFF2FCFF"/><bgColor indexed="64"/></patternFill></fill>'
    . '</fills>'
    . '<borders count="2">'
    . '<border><left/><right/><top/><bottom/><diagonal/></border>'
    . '<border><left style="thin"><color rgb="FFD4E8F0"/></left><right style="thin"><color rgb="FFD4E8F0"/></right><top style="thin"><color rgb="FFD4E8F0"/></top><bottom style="thin"><color rgb="FFD4E8F0"/></bottom><diagonal/></border>'
    . '</borders>'
    . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
    . '<cellXfs count="6">'
    . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
    . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
    . '<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>'
    . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
    . '<xf numFmtId="4" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
    . '<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
    . '</cellXfs>'
    . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
    . '</styleSheet>';

$workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets><sheet name="Bao cao" sheetId="1" r:id="rId1"/><sheet name="Biểu đồ" sheetId="2" r:id="rId2"/></sheets>'
    . '</workbook>';

$workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
    . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    . '</Relationships>';

$rootRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
    . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
    . '</Relationships>';

$chartOverridesXml = '';
if ($includeAnyChart) {
    $chartOverridesXml = '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>';
    if ($includeDonationChart) {
        $chartOverridesXml .= '<Override PartName="/xl/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>';
    }
    if ($includeCategoryChart) {
        $categoryChartPart = $includeDonationChart ? '/xl/charts/chart2.xml' : '/xl/charts/chart1.xml';
        $chartOverridesXml .= '<Override PartName="' . $categoryChartPart . '" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>';
    }
}

$contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . $chartOverridesXml
    . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
    . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
    . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
    . '</Types>';

$coreXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
    . 'xmlns:dc="http://purl.org/dc/elements/1.1/" '
    . 'xmlns:dcterms="http://purl.org/dc/terms/" '
    . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" '
    . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
    . '<dc:title>Bao cao thong ke</dc:title>'
    . '<dc:creator>Goodwill Vietnam</dc:creator>'
    . '<cp:lastModifiedBy>Goodwill Vietnam</cp:lastModifiedBy>'
    . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
    . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:modified>'
    . '</cp:coreProperties>';

$appXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
    . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
    . '<Application>Microsoft Excel</Application>'
    . '</Properties>';

$tmpFile = tempnam(sys_get_temp_dir(), 'rpt_xlsx_');
if ($tmpFile === false) {
    http_response_code(500);
    echo 'Cannot create temp file.';
    exit;
}

$xlsxPath = $tmpFile . '.xlsx';
if (!@rename($tmpFile, $xlsxPath)) {
    $xlsxPath = $tmpFile;
}

$zip = new ZipArchive();
if ($zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo 'Cannot create xlsx archive.';
    @unlink($xlsxPath);
    exit;
}

$zip->addFromString('[Content_Types].xml', $contentTypesXml);
$zip->addFromString('_rels/.rels', $rootRelsXml);
$zip->addFromString('docProps/core.xml', $coreXml);
$zip->addFromString('docProps/app.xml', $appXml);
$zip->addFromString('xl/workbook.xml', $workbookXml);
$zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
$zip->addFromString('xl/styles.xml', $stylesXml);
$zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
 $zip->addFromString('xl/worksheets/sheet2.xml', $chartSheetXml);
if ($includeAnyChart) {
    $zip->addFromString('xl/worksheets/_rels/sheet2.xml.rels', $chartSheetRelsXml);
    $zip->addFromString('xl/drawings/drawing1.xml', $drawingXml);
    $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', $drawingRelsXml);
    if ($includeDonationChart) {
        $zip->addFromString('xl/charts/chart1.xml', $donationChartXml);
    }
    if ($includeCategoryChart) {
        $categoryChartFile = $includeDonationChart ? 'xl/charts/chart2.xml' : 'xl/charts/chart1.xml';
        $zip->addFromString($categoryChartFile, $categoryChartXml);
    }
}
$zip->close();

$fileName = sprintf(
    'bao-cao-%s-den-%s.xlsx',
    preg_replace('/[^0-9\-]/', '', $start_date),
    preg_replace('/[^0-9\-]/', '', $end_date)
);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');
header('Content-Length: ' . filesize($xlsxPath));

readfile($xlsxPath);
@unlink($xlsxPath);
exit;
