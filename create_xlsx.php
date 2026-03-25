<?php
/**
 * Create a valid XLSX file manually using ZIP + XML with category dropdowns
 */

// Load database and get categories
require_once 'config/database.php';
require_once 'includes/functions.php';

@mkdir('assets/excel', 0755, true);

// Get active categories from database
$categories = [];
try {
    $results = Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order, name");
    foreach ($results as $cat) {
        // Ensure proper UTF-8 encoding
        $catName = ensureUtf8($cat['name']);
        $categories[] = $catName;
    }
} catch (Exception $e) {
    // Fallback if database not available
    $categories = ['Áo', 'Sách', 'Giày', 'Đồ chơi', 'Điện tử'];
}

if (empty($categories)) {
    $categories = ['Áo', 'Sách', 'Giày', 'Đồ chơi', 'Điện tử'];
}

// XLSX is just a ZIP file with specific structure
// Creating a minimal valid XLSX

$zip = new ZipArchive();
$filepath = 'assets/excel/donation_template.xlsx';

// Remove if exists
if (file_exists($filepath)) {
    unlink($filepath);
}

if ($zip->open($filepath, ZipArchive::CREATE) !== true) {
    die("Cannot create XLSX file\n");
}

// 1. Create [Content_Types].xml
$content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>';

$zip->addFromString('[Content_Types].xml', $content_types);

// 2. Create .rels (relationships)
$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>';

$zip->addFromString('_rels/.rels', $rels);

// 3. Create shared strings (the actual text data)
$strings = [
    'STT',
    'Tên vật phẩm',
    'Mô tả',
    'Danh mục',
    'Số lượng',
    'Đơn vị',
    'Tình trạng',
    'Giá trị ước tính (VND)',
    'URL hình ảnh',
    'Áo sơ mi nam',
    'Áo cotton màu xanh size M',
    'cái',
    'good',
    'https://example.com/img1.jpg',
    'Sách tiếng Anh',
    'Sách tiếng Anh lớp 10 bản mới',
    'cuốn',
    'like_new',
    'https://example.com/img2.jpg',
    'Giày thể thao',
    'Giày Nike chạy bộ size 40-41',
    'đôi',
    'https://example.com/img3.jpg',
];

// Add all categories to strings for references
foreach ($categories as $cat) {
    $strings[] = $cat;
}

$shared_strings_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';

foreach ($strings as $str) {
    $shared_strings_xml .= '<si><t>' . htmlspecialchars($str, ENT_XML1, 'UTF-8') . '</t></si>';
}

$shared_strings_xml .= '</sst>';

$zip->addFromString('xl/sharedStrings.xml', $shared_strings_xml);

// 4. Create styles
$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="2">
<font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>
<font><sz val="11"/><bold val="1"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>
</fonts>
<fills count="3">
<fill><patternFill patternType="none"/></fill>
<fill><patternFill patternType="gray125"/></fill>
<fill><patternFill patternType="solid"><fgColor rgb="10B981"/></patternFill></fill>
</fills>
<borders count="1">
<border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border>
</borders>
<cellStyleXfs count="1">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
</cellStyleXfs>
<cellXfs count="3">
<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0"/>
<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>
</cellXfs>
<cellStyles count="1">
<cellStyle name="Normal" xfId="0" builtinId="0"/>
</cellStyles>
<dxfs count="0"/>
<tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleMedium9"/>
</styleSheet>';

$zip->addFromString('xl/styles.xml', $styles);

// 5. Create worksheet
$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews>
<sheetFormatPr defaultRowHeight="20"/>
<cols>
<col min="1" max="1" width="6"/>
<col min="2" max="2" width="20"/>
<col min="3" max="3" width="30"/>
<col min="4" max="4" width="15"/>
<col min="5" max="5" width="10"/>
<col min="6" max="6" width="10"/>
<col min="7" max="7" width="12"/>
<col min="8" max="8" width="18"/>
<col min="9" max="9" width="30"/>
</cols>
<sheetData>';

// Headers (row 1) - with styling
$headers = ['STT', 'Tên vật phẩm', 'Mô tả', 'Danh mục', 'Số lượng', 'Đơn vị', 'Tình trạng', 'Giá trị ước tính (VND)', 'URL hình ảnh'];
$sheet .= '<row r="1" ht="25">';
for ($i = 0; $i < count($headers); $i++) {
    $col = chr(65 + $i);
    $sheet .= '<c r="' . $col . '1" s="1" t="s"><v>' . $i . '</v></c>';
}
$sheet .= '</row>';

// Sample data rows with formulas for auto-increment
$sample_data = [
    [9, 10, 3, 11, 12, 13, 14],        // Row 2: Áo sơ mi
    [15, 16, 5, 17, 18, 19, 20],       // Row 3: Sách
    [21, 22, 2, 23, 12, 24, 25],       // Row 4: Giày
];

foreach ($sample_data as $row_idx => $row_data) {
    $row_num = $row_idx + 2;
    $sheet .= '<row r="' . $row_num . '">';
    
    // Column A: STT with formula (auto-increment)
    $sheet .= '<c r="A' . $row_num . '" s="2"><f>ROW()-1</f></c>';
    
    // Columns B-I: Data
    $col_idx = 1;
    foreach ($row_data as $value) {
        $col = chr(65 + $col_idx);
        if (is_numeric($value) && $value > 100) {
            $sheet .= '<c r="' . $col . $row_num . '" s="2" t="n"><v>' . $value . '</v></c>';
        } else {
            $sheet .= '<c r="' . $col . $row_num . '" s="2" t="s"><v>' . $value . '</v></c>';
        }
        $col_idx++;
    }
    $sheet .= '</row>';
}

// Add empty rows with formulas up to row 100
for ($row_num = 5; $row_num <= 100; $row_num++) {
    $sheet .= '<row r="' . $row_num . '">';
    $sheet .= '<c r="A' . $row_num . '" s="2"><f>ROW()-1</f></c>';
    $sheet .= '</row>';
}

$sheet .= '</sheetData>';

// Data Validations - Simpler format that Excel understands better
$cat_formula = implode(',', array_map(function($cat) { 
    return htmlspecialchars($cat, ENT_XML1, 'UTF-8'); 
}, $categories));

$sheet .= '
<dataValidations count="3">
<dataValidation type="list" formula1="' . $cat_formula . '" allowBlank="1" showDropDown="1" sqref="D2:D100"/>
<dataValidation type="list" formula1="cái,bộ,kg,cuốn,thùng,đôi,chiếc" allowBlank="1" showDropDown="1" sqref="F2:F100"/>
<dataValidation type="list" formula1="new,like_new,good,fair,poor" allowBlank="1" showDropDown="1" sqref="G2:G100"/>
</dataValidations>';

$sheet .= '<pageMargins left="0.75" top="1" right="0.75" bottom="1" header="0.5" footer="0.5"/>
</worksheet>';

$zip->addFromString('xl/worksheets/sheet1.xml', $sheet);

// 6. Create workbook
$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<workbookPr codeName="ThisWorkbook"/>
<sheets>
<sheet name="Quyên góp vật phẩm" sheetId="1" r:id="rId1"/>
</sheets>
</workbook>';

$zip->addFromString('xl/workbook.xml', $workbook);

// 7. Add workbook relationships
$wb_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';

$zip->addFromString('xl/_rels/workbook.xml.rels', $wb_rels);

// 8. Create core properties
$core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/officeDocument/2006/custom-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<dc:creator>Goodwill Vietnam</dc:creator>
<cp:lastModifiedBy>Goodwill Vietnam</cp:lastModifiedBy>
<dcterms:created xsi:type="dcterms:W3CDTF">2026-03-10T00:00:00Z</dcterms:created>
<dcterms:modified xsi:type="dcterms:W3CDTF">2026-03-10T00:00:00Z</dcterms:modified>
<dc:title>Mẫu quyên góp vật phẩm - Goodwill Vietnam</dc:title>
<dc:description>Mẫu nhập liệu quyên góp vật phẩm với dropdown danh mục, đơn vị, tình trạng và STT tự động</dc:description>
</cp:coreProperties>';

$zip->addFromString('docProps/core.xml', $core);

$zip->close();

echo "✅ Excel template tạo thành công!\n";
echo "📊 Tính năng:\n";
echo "  ✓ Cột STT tự động tăng (ROW formula)\n";
echo "  ✓ Dropdown danh mục cho tất cả 99 dòng\n";
echo "  ✓ Dropdown đơn vị (cái, bộ, kg, cuốn, thùng)\n";
echo "  ✓ Dropdown tình trạng (new, like_new, good, fair, poor)\n";
echo "  ✓ Dữ liệu mẫu 3 vật phẩm\n";
echo "  ✓ UTF-8 tiếng Việt đúng\n";
