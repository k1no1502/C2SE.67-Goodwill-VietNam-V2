<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\DataValidation;

// Tạo workbook mới
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Sản Phẩm');

// Thêm header
$sheet->setCellValue('A1', 'STT');
$sheet->setCellValue('B1', 'Tên Sản Phẩm');
$sheet->setCellValue('C1', 'Mô Tả');
$sheet->setCellValue('D1', 'Danh Mục');
$sheet->setCellValue('E1', 'Giá');

// Định dạng header
$sheet->getStyle('A1:E1')->getFont()->setBold(true);
$sheet->getStyle('A1:E1')->getFill()->setFillType('solid')->getStartColor()->setARGB('ff00b050');
$sheet->getStyle('A1:E1')->getFont()->getColor()->setARGB('ffffffff');

// Độ rộng cột
$sheet->getColumnDimension('A')->setWidth(8);
$sheet->getColumnDimension('B')->setWidth(25);
$sheet->getColumnDimension('C')->setWidth(35);
$sheet->getColumnDimension('D')->setWidth(15);
$sheet->getColumnDimension('E')->setWidth(12);

// Danh sách danh mục
$danhMuc = array('Điện tử', 'Đồ chơi', 'Gia dụng', 'Khác', 'Quần áo', 'Sách', 'Thực phẩm', 'Y tế');

// Tạo sheet riêng cho danh mục
$categoriesSheet = $spreadsheet->addSheet(new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'DanhMuc'));
foreach ($danhMuc as $idx => $cat) {
    $categoriesSheet->setCellValue('A' . ($idx + 1), $cat);
}
$categoriesSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);

// Tạo Data Validation cho cột D (Danh Mục)
$dataValidation = new DataValidation();
$dataValidation->setType('list');
$dataValidation->setFormula1('DanhMuc!$A$1:$A$8');
$dataValidation->setShowDropDown(true);
$dataValidation->setAllowBlank(false);
$dataValidation->setErrorTitle('Lỗi');
$dataValidation->setError('Vui lòng chọn danh mục từ danh sách');
$dataValidation->setShowErrorMessage(true);

// Áp dụng Data Validation cho 100 dòng
for ($row = 2; $row <= 100; $row++) {
    $sheet->addDataValidation('D' . $row);
    $dvClone = clone $dataValidation;
    $sheet->getCell('D' . $row)->setDataValidation($dvClone);
}

// Thêm vài dòng example
$sheet->setCellValue('A2', '1');
$sheet->setCellValue('A3', '2');

// Lưu file
$writer = new Xlsx($spreadsheet);
$writer->save('SanPham_Template.xlsx');

echo "✓ File tạo thành công: SanPham_Template.xlsx\n";
echo "  - Sheet 'Sản Phẩm': sẵn sàng nhập dữ liệu\n";
echo "  - Cột D (Danh Mục): có dropdown list\n";
echo "  - Sheet 'DanhMuc': ẩn, chứa danh sách danh mục\n";
?>
