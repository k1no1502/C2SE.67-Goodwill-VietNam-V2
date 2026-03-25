<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireStaffOrAdmin();

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$stats = getStatistics();
$donationTrend = getDonationTrendData();
$categoryDistribution = getCategoryDistributionData();

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('Goodwill Vietnam')
    ->setTitle('Xuất báo cáo Dashboard')
    ->setSubject('Dữ liệu Dashboard')
    ->setDescription('Tổng quan dữ liệu Dashboard tại thời điểm ' . date('d/m/Y H:i'));

// Overview sheet
$overview = $spreadsheet->getActiveSheet();
$overview->setTitle('Tổng quan');
$overview->fromArray([
    ['Chỉ số', 'Giá trị'],
    ['Tổng người dùng', $stats['users']],
    ['Tổng quyên góp', $stats['donations']],
    ['Vật phẩm tồn kho', $stats['items']],
    ['Chiến dịch', $stats['campaigns']],
], null, 'A1');
$overview->getStyle('A1:B1')->getFont()->setBold(true)->setSize(12);
$overview->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
$overview->getStyle('A1:B1')->getFont()->getColor()->setRGB('FFFFFF');
$overview->getStyle('A2:B5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$overview->getColumnDimension('A')->setWidth(28);
$overview->getColumnDimension('B')->setWidth(20);
$overview->getStyle('A1:B5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$overview->getStyle('B2:B5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$overview->getStyle('B2:B5')->getNumberFormat()->setFormatCode('#,##0');
$overview->setCellValue('D1', 'Thời gian xuất')
    ->setCellValue('E1', date('d/m/Y H:i'));
$overview->getStyle('D1:E1')->getFont()->setBold(true);

// KPI column chart (replicates dashboard cards visually)
$kpiCategoriesRange = "'Tổng quan'!\$A\$2:\$A\$5";
$kpiValuesRange = "'Tổng quan'!\$B\$2:\$B\$5";
$kpiSeries = new DataSeries(
    DataSeries::TYPE_BARCHART,
    DataSeries::GROUPING_CLUSTERED,
    range(0, 0),
    [new DataSeriesValues('String', "'Tổng quan'!\$B\$1", null, 1)],
    [new DataSeriesValues('String', $kpiCategoriesRange, null, 4)],
    [new DataSeriesValues('Number', $kpiValuesRange, null, 4)]
);
$kpiSeries->setPlotDirection(DataSeries::DIRECTION_COL);
$kpiPlot = new PlotArea(null, [$kpiSeries]);
$kpiChart = new Chart(
    'kpi_summary_chart',
    new Title('Tổng quan người dùng - quyên góp - vật phẩm - chiến dịch'),
    new Legend(Legend::POSITION_RIGHT, null, false),
    $kpiPlot
);
$kpiChart->setTopLeftPosition('D3');
$kpiChart->setBottomRightPosition('L18');
$overview->addChart($kpiChart);

// Donation trend sheet
$donationSheet = $spreadsheet->createSheet();
$donationSheet->setTitle('Quyên góp theo tháng');
$donationSheet->fromArray([
    ['Tháng', 'Số quyên góp']
], null, 'A1');
foreach ($donationTrend as $index => $item) {
    $row = $index + 2;
    $donationSheet->setCellValue('A' . $row, $item['label']);
    $donationSheet->setCellValue('B' . $row, $item['total']);
}
$donationSheet->getStyle('A1:B1')->getFont()->setBold(true)->setSize(12);
$donationSheet->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('70AD47');
$donationSheet->getStyle('A1:B1')->getFont()->getColor()->setRGB('FFFFFF');
$donationSheet->getStyle('A2:B' . (count($donationTrend) + 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$donationSheet->getColumnDimension('A')->setWidth(20);
$donationSheet->getColumnDimension('B')->setWidth(18);
$donationSheet->getStyle('B2:B' . (count($donationTrend) + 1))->getNumberFormat()->setFormatCode('#,##0');
$donationRangeEnd = count($donationTrend) + 1;
$donationSeriesLabels = [
    new DataSeriesValues('String', "'Quyên góp theo tháng'!\$B\$1", null, 1)
];
$donationAxis = [
    new DataSeriesValues('String', "'Quyên góp theo tháng'!\$A\$2:\$A\$" . max(2, $donationRangeEnd), null, max(1, count($donationTrend)))
];
$donationValues = [
    new DataSeriesValues('Number', "'Quyên góp theo tháng'!\$B\$2:\$B\$" . max(2, $donationRangeEnd), null, max(1, count($donationTrend)))
];
$donationSeries = new DataSeries(
    DataSeries::TYPE_LINECHART,
    DataSeries::GROUPING_STANDARD,
    range(0, count($donationValues) - 1),
    $donationSeriesLabels,
    $donationAxis,
    $donationValues
);
$donationPlotArea = new PlotArea(null, [$donationSeries]);
$donationLegend = new Legend(Legend::POSITION_TOPRIGHT, null, false);
$donationChart = new Chart(
    'donation_trend_chart',
    new Title('Thống kê quyên góp theo tháng'),
    $donationLegend,
    $donationPlotArea
);
$donationChart->setTopLeftPosition('D2');
$donationChart->setBottomRightPosition('L18');
$donationSheet->addChart($donationChart);

// Category sheet
$categorySheet = $spreadsheet->createSheet();
$categorySheet->setTitle('Danh mục');
$categorySheet->fromArray([
    ['Danh mục', 'Tổng số']
], null, 'A1');
foreach ($categoryDistribution as $index => $item) {
    $row = $index + 2;
    $categorySheet->setCellValue('A' . $row, $item['label']);
    $categorySheet->setCellValue('B' . $row, $item['total']);
}
$categorySheet->getStyle('A1:B1')->getFont()->setBold(true)->setSize(12);
$categorySheet->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC7CE');
$categorySheet->getStyle('A1:B1')->getFont()->getColor()->setRGB('000000');
$categorySheet->getStyle('A2:B' . (count($categoryDistribution) + 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$categorySheet->getColumnDimension('A')->setWidth(28);
$categorySheet->getColumnDimension('B')->setWidth(15);
$categorySheet->getStyle('B2:B' . (count($categoryDistribution) + 1))->getNumberFormat()->setFormatCode('#,##0');
$categoryRangeEnd = count($categoryDistribution) + 1;
$categoryLabelsRange = "'Danh mục'!\$A\$2:\$A\$" . max(2, $categoryRangeEnd);
$categoryValuesRange = "'Danh mục'!\$B\$2:\$B\$" . max(2, $categoryRangeEnd);
$categorySeries = new DataSeries(
    DataSeries::TYPE_DONUTCHART,
    null,
    range(0, 0),
    [new DataSeriesValues('String', "'Danh mục'!\$A\$1", null, 1)],
    [new DataSeriesValues('String', $categoryLabelsRange, null, max(1, count($categoryDistribution)))],
    [new DataSeriesValues('Number', $categoryValuesRange, null, max(1, count($categoryDistribution)))]
);
$categorySeries->setPlotDirection(DataSeries::DIRECTION_COL);
$categoryPlot = new PlotArea(null, [$categorySeries]);
$categoryLegend = new Legend(Legend::POSITION_RIGHT, null, false);
$categoryChart = new Chart(
    'category_distribution',
    new Title('Phân bổ danh mục'),
    $categoryLegend,
    $categoryPlot
);
$categoryChart->setTopLeftPosition('D2');
$categoryChart->setBottomRightPosition('J18');
$categorySheet->addChart($categoryChart);

// Visual dashboard sheet aggregating requested charts
$visualSheet = $spreadsheet->createSheet();
$visualSheet->setTitle('Sơ đồ');
$visualSheet->setCellValue('A1', 'Dashboard - Báo cáo toàn bộ dữ liệu');
$visualSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$visualSheet->setCellValue('A3', 'Sơ đồ tổng quan (các chỉ số chính)');
$visualSheet->setCellValue('A20', 'Thống kê quyên góp theo tháng');
$visualSheet->setCellValue('A36', 'Phân bổ danh mục');

// Reuse KPI chart on the visual sheet
$dashboardKpiSeries = new DataSeries(
    DataSeries::TYPE_BARCHART,
    DataSeries::GROUPING_CLUSTERED,
    range(0, 0),
    [new DataSeriesValues('String', "'Tổng quan'!\$B\$1", null, 1)],
    [new DataSeriesValues('String', $kpiCategoriesRange, null, 4)],
    [new DataSeriesValues('Number', $kpiValuesRange, null, 4)]
);
$dashboardKpiSeries->setPlotDirection(DataSeries::DIRECTION_COL);
$dashboardKpiPlot = new PlotArea(null, [$dashboardKpiSeries]);
$dashboardKpiChart = new Chart(
    'dashboard_kpi_chart',
    new Title('Tổng người dùng / Quyên góp / Vật phẩm / Chiến dịch'),
    new Legend(Legend::POSITION_RIGHT, null, false),
    $dashboardKpiPlot
);
$dashboardKpiChart->setTopLeftPosition('C4');
$dashboardKpiChart->setBottomRightPosition('L18');
$visualSheet->addChart($dashboardKpiChart);

// Donation trend chart mirrored on dashboard sheet
$dashboardTrendSeries = new DataSeries(
    DataSeries::TYPE_LINECHART,
    DataSeries::GROUPING_STANDARD,
    range(0, 0),
    [new DataSeriesValues('String', "'Quyên góp theo tháng'!\$B\$1", null, 1)],
    [new DataSeriesValues('String', "'Quyên góp theo tháng'!\$A\$2:\$A\$" . max(2, $donationRangeEnd), null, max(1, count($donationTrend)))],
    [new DataSeriesValues('Number', "'Quyên góp theo tháng'!\$B\$2:\$B\$" . max(2, $donationRangeEnd), null, max(1, count($donationTrend)))]
);
$dashboardTrendPlot = new PlotArea(null, [$dashboardTrendSeries]);
$dashboardTrendChart = new Chart(
    'dashboard_trend_chart',
    new Title('Thống kê quyên góp theo tháng'),
    new Legend(Legend::POSITION_BOTTOM, null, false),
    $dashboardTrendPlot
);
$dashboardTrendChart->setTopLeftPosition('C21');
$dashboardTrendChart->setBottomRightPosition('L35');
$visualSheet->addChart($dashboardTrendChart);

// Category distribution donut mirrored on dashboard sheet
$visualCategorySeries = new DataSeries(
    DataSeries::TYPE_DONUTCHART,
    null,
    range(0, 0),
    [new DataSeriesValues('String', "'Danh mục'!\$A\$1", null, 1)],
    [new DataSeriesValues('String', $categoryLabelsRange, null, max(1, count($categoryDistribution)))],
    [new DataSeriesValues('Number', $categoryValuesRange, null, max(1, count($categoryDistribution)))]
);
$visualCategorySeries->setPlotDirection(DataSeries::DIRECTION_COL);
$visualCategoryPlot = new PlotArea(null, [$visualCategorySeries]);
$visualCategoryChart = new Chart(
    'dashboard_category_chart',
    new Title('Phân bổ danh mục'),
    new Legend(Legend::POSITION_RIGHT, null, false),
    $visualCategoryPlot
);
$visualCategoryChart->setTopLeftPosition('C38');
$visualCategoryChart->setBottomRightPosition('L60');
$visualSheet->addChart($visualCategoryChart);

$filename = 'Dashboard-' . date('d-m-Y H-i') . '.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

while (ob_get_level() > 0) {
    ob_end_clean();
}
$writer->save('php://output');
exit;
