<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();

// Get date range (default to last 6 months so chart is meaningful)
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$start_date = $_GET['start_date'] ?? date('Y-m-01', strtotime('-5 months', strtotime($end_date)));

// Get statistics
$stats = getStatistics();

// Get donation statistics by month

$rawDonationStats = Database::fetchAll("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
           COUNT(*) as count,
           SUM(quantity) as total_quantity,
           SUM(CASE WHEN status = 'approved' THEN quantity ELSE 0 END) as approved_quantity
    FROM donations
    WHERE created_at BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
", [$start_date, $end_date . ' 23:59:59']);

// Build a continuous month-by-month dataset so chart/table don't drop months.
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

// Get category distribution
$categoryStats = Database::fetchAll("
    SELECT c.name, COUNT(*) as count, SUM(d.quantity) as total_quantity
    FROM donations d
    LEFT JOIN categories c ON d.category_id = c.category_id
    WHERE d.created_at BETWEEN ? AND ? AND d.status = 'approved'
    GROUP BY c.category_id, c.name
    ORDER BY count DESC
    LIMIT 10
", [$start_date, $end_date . ' 23:59:59']);

// Get top donors
$topDonors = Database::fetchAll("
    SELECT u.name, u.email, COUNT(*) as donation_count, SUM(d.quantity) as total_items
    FROM donations d
    LEFT JOIN users u ON d.user_id = u.user_id
    WHERE d.created_at BETWEEN ? AND ? AND d.status = 'approved'
    GROUP BY u.user_id, u.name, u.email
    ORDER BY donation_count DESC
    LIMIT 10
", [$start_date, $end_date . ' 23:59:59']);

// Get campaign statistics
$campaignStats = Database::fetchAll("
    SELECT c.name, c.status, c.target_items, c.current_items,
           (SELECT COUNT(*) FROM campaign_donations WHERE campaign_id = c.campaign_id) as donations_count
    FROM campaigns c
    WHERE c.created_at BETWEEN ? AND ?
    ORDER BY c.created_at DESC
", [$start_date, $end_date . ' 23:59:59']);

// Get inventory statistics
$inventoryStats = [
    'total' => Database::fetch("SELECT COUNT(*) as count FROM inventory")['count'],
    'available' => Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE status = 'available'")['count'],
    'sold' => Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE status = 'sold'")['count'],
    'free' => Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE price_type = 'free' AND status = 'available'")['count'],
    'cheap' => Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE price_type = 'cheap' AND status = 'available'")['count'],
];

// Recent activities and donations (within selected date range)
$recentActivities = Database::fetchAll(
    "SELECT al.action, al.created_at, u.name as user_name
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.user_id
     WHERE al.created_at BETWEEN ? AND ?
     ORDER BY al.created_at DESC
     LIMIT 10",
    [$start_date, $end_date . ' 23:59:59']
);

$recentDonations = Database::fetchAll(
    "SELECT d.item_name, d.status, d.created_at, u.name as donor_name, c.name as category_name
     FROM donations d
     LEFT JOIN users u ON d.user_id = u.user_id
     LEFT JOIN categories c ON d.category_id = c.category_id
     WHERE d.created_at BETWEEN ? AND ?
     ORDER BY d.created_at DESC
     LIMIT 8",
    [$start_date, $end_date . ' 23:59:59']
);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo thống kê - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --brand-700: #0e7490;
            --brand-500: #06b6d4;
            --brand-50: #ecfeff;
            --ink-900: #23324a;
            --ink-500: #62718a;
            --line: #d4e8f0;
        }

        body { background: #f3f9fc; }

        .reports-topbar {
            background: linear-gradient(135deg, var(--brand-700) 0%, var(--brand-500) 100%);
            border-radius: 16px;
            padding: 22px 28px;
            color: #fff;
            margin: 1rem 0 1.5rem;
        }
        .reports-topbar h1 { font-size: 1.45rem; font-weight: 700; margin: 0; }
        .reports-topbar p { margin: 4px 0 0; opacity: .82; font-size: .88rem; }

        .filter-card,
        .report-panel {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(14, 116, 144, .07);
            overflow: hidden;
        }

        .filter-card { padding: 18px 22px; }

        .filter-card .form-label {
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--ink-500);
            margin-bottom: 6px;
        }

        .filter-card .form-control,
        .filter-card .form-select {
            border-color: var(--line);
            border-radius: 10px;
            font-size: .9rem;
        }
        .filter-card .form-control:focus,
        .filter-card .form-select:focus {
            border-color: var(--brand-500);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, .15);
        }

        .btn-report-view {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
        }
        .btn-report-view:hover { color: #fff; opacity: .92; }

        .btn-report-export {
            border: 1.5px solid var(--line);
            color: var(--brand-700);
            background: #fff;
            border-radius: 10px;
            font-weight: 600;
        }
        .btn-report-export:hover { background: var(--brand-50); color: var(--brand-700); }

        .stat-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(14, 116, 144, .07);
            transition: transform .18s, box-shadow .18s;
            height: 100%;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(14, 116, 144, .13); }
        .stat-card h6 {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--ink-500);
            margin-bottom: 5px;
        }
        .stat-card h3 { font-size: 2rem; line-height: 1; font-weight: 800; margin: 0; color: var(--ink-900); }

        .report-panel .card-header {
            background: #f9feff;
            border-bottom: 1px solid var(--line);
            padding: 14px 18px;
        }
        .report-panel .card-header h6,
        .report-panel .card-header small { margin: 0; }
        .report-panel .card-body { padding: 18px; }

        .report-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .report-table thead th {
            background: linear-gradient(135deg, #083344 0%, #0e7490 100%);
            color: rgba(255, 255, 255, .76);
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            border: none;
            padding: 13px 14px;
            white-space: nowrap;
        }
        .report-table tbody td {
            border-bottom: 1px solid #edf5f8;
            vertical-align: middle;
            color: var(--ink-900);
            padding: 12px 14px;
            font-size: .88rem;
        }
        .report-table tbody tr:hover { background: #f0fbfe; }
        .report-table tbody tr:last-child td { border-bottom: none; }

        .inv-tile {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 14px;
            text-align: center;
            background: #fcfeff;
        }

        .feed-list .list-group-item {
            border-left: none;
            border-right: none;
            padding: 0.8rem 0;
        }

        .feed-list .list-group-item:first-child {
            border-top: none;
            padding-top: 0;
        }

        .feed-list .list-group-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .feed-title {
            font-weight: 700;
            color: #0f172a;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="reports-topbar">
                    <h1><i class="bi bi-graph-up me-2"></i>Báo cáo thống kê</h1>
                    <p>Tổng hợp hiệu suất quyên góp, chiến dịch và vận hành kho theo thời gian.</p>
                </div>

                <!-- Date Range Filter -->
                <div class="filter-card mb-4">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Từ ngày</label>
                                <input type="date" 
                                       class="form-control" 
                                       name="start_date" 
                                       value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Đến ngày</label>
                                <input type="date" 
                                       class="form-control" 
                                       name="end_date" 
                                       value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-report-view flex-fill">
                                        <i class="bi bi-search me-1"></i>Xem báo cáo
                                    </button>
                                                <a class="btn btn-report-export flex-fill" target="_blank"
                                                    href="reports_export.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>">
                                        <i class="bi bi-file-earmark-excel me-1"></i>Xuất Excel
                                    </a>
                                </div>
                            </div>
                        </form>
                </div>

                <!-- Overview Statistics -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                                <h6>Tổng người dùng</h6>
                                <h3><?php echo number_format($stats['users']); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                                <h6>Tổng quyên góp</h6>
                                <h3><?php echo number_format($stats['donations']); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                                <h6>Vật phẩm trong kho</h6>
                                <h3><?php echo number_format($stats['items']); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                                <h6>Tổng chiến dịch</h6>
                                <h3><?php echo number_format($stats['campaigns']); ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="report-panel">
                            <div class="card-header">
                                <h6 class="mb-0">Thống kê quyên góp theo tháng</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="donationChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="report-panel">
                            <div class="card-header">
                                <h6 class="mb-0">Phân bố danh mục</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="categoryChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-lg-6 mb-4 mb-lg-0">
                        <div class="report-panel h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Hoạt động gần đây</h6>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush feed-list">
                                    <?php if (empty($recentActivities)): ?>
                                        <div class="text-center text-muted py-3">Không có hoạt động trong khoảng thời gian này.</div>
                                    <?php else: ?>
                                        <?php foreach ($recentActivities as $activity): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                                <div class="me-3">
                                                    <div class="feed-title"><?php echo htmlspecialchars($activity['action']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($activity['user_name'] ?? 'Hệ thống'); ?></small>
                                                </div>
                                                <small class="text-muted"><?php echo formatDate($activity['created_at'], 'H:i d/m'); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="report-panel h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Quyên góp gần đây</h6>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush feed-list">
                                    <?php if (empty($recentDonations)): ?>
                                        <div class="text-center text-muted py-3">Không có quyên góp trong khoảng thời gian này.</div>
                                    <?php else: ?>
                                        <?php foreach ($recentDonations as $donation): ?>
                                            <?php
                                                $donationStatusClass = 'secondary';
                                                if (($donation['status'] ?? '') === 'approved') {
                                                    $donationStatusClass = 'success';
                                                } elseif (($donation['status'] ?? '') === 'pending') {
                                                    $donationStatusClass = 'warning text-dark';
                                                } elseif (($donation['status'] ?? '') === 'rejected') {
                                                    $donationStatusClass = 'danger';
                                                }
                                            ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                                <div class="me-3">
                                                    <div class="feed-title"><?php echo htmlspecialchars($donation['item_name']); ?></div>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($donation['donor_name'] ?? 'Khách'); ?> - 
                                                        <?php echo htmlspecialchars($donation['category_name'] ?? 'Không xác định'); ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-<?php echo $donationStatusClass; ?>">
                                                    <?php echo ucfirst($donation['status'] ?? 'unknown'); ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Statistics -->
                <div class="report-panel mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Bảng thống kê quyên góp theo tháng</h6>
                        <small class="text-muted">So sánh số lượt quyên góp và tổng vật phẩm</small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="report-table align-middle">
                                <thead>
                                    <tr>
                                        <th>Tháng</th>
                                        <th>Số lượt quyên góp</th>
                                        <th>Tổng vật phẩm</th>
                                        <th>Vật phẩm đã duyệt</th>
                                        <th>Tăng trưởng so với tháng trước</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($donationGrowth)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">Không có dữ liệu trong khoảng thời gian đã chọn.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($donationGrowth as $row): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($row['month']); ?></strong></td>
                                                <td><?php echo number_format($row['count']); ?></td>
                                                <td><?php echo number_format($row['total_quantity']); ?></td>
                                                <td><?php echo number_format($row['approved_quantity'] ?? 0); ?></td>
                                                <td>
                                                    <?php if ($row['growth'] === null): ?>
                                                        <span class="text-muted">–</span>
                                                    <?php else: ?>
                                                        <?php if ($row['growth'] >= 0): ?>
                                                            <span class="text-success">
                                                                <i class="bi bi-arrow-up"></i>
                                                                <?php echo $row['growth']; ?>%
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-danger">
                                                                <i class="bi bi-arrow-down"></i>
                                                                <?php echo $row['growth']; ?>%
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="report-panel">
                            <div class="card-header">
                                <h6 class="mb-0">Top người quyên góp</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="report-table">
                                        <thead>
                                            <tr>
                                                <th>Người dùng</th>
                                                <th>Số lần quyên</th>
                                                <th>Tổng vật phẩm</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($topDonors)): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted">Không có dữ liệu</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($topDonors as $donor): ?>
                                                    <tr>
                                                        <td>
                                                            <?php echo htmlspecialchars($donor['name'] ?? 'Khách'); ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($donor['email'] ?? ''); ?></small>
                                                        </td>
                                                        <td><?php echo number_format($donor['donation_count']); ?></td>
                                                        <td><?php echo number_format($donor['total_items']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="report-panel">
                            <div class="card-header">
                                <h6 class="mb-0">Thống kê kho hàng</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6 mb-3">
                                        <div class="inv-tile">
                                            <h5 class="text-primary"><?php echo number_format($inventoryStats['total']); ?></h5>
                                            <small>Tổng vật phẩm</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="inv-tile">
                                            <h5 class="text-success"><?php echo number_format($inventoryStats['available']); ?></h5>
                                            <small>Có sẵn</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="inv-tile">
                                            <h5 class="text-info"><?php echo number_format($inventoryStats['sold']); ?></h5>
                                            <small>Đã bán</small>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-3">
                                        <div class="inv-tile">
                                            <h5 class="text-warning"><?php echo number_format($inventoryStats['free']); ?></h5>
                                            <small>Miễn phí</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Campaign Statistics -->
                <div class="report-panel mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Thống kê chiến dịch</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Tên chiến dịch</th>
                                        <th>Trạng thái</th>
                                        <th>Mục tiêu</th>
                                        <th>Đã nhận</th>
                                        <th>Tiến độ</th>
                                        <th>Số quyên góp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($campaignStats)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Không có chiến dịch nào</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($campaignStats as $campaign): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($campaign['name']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $campaign['status'] === 'active' ? 'success' : 
                                                            ($campaign['status'] === 'completed' ? 'primary' : 'secondary'); 
                                                    ?>">
                                                        <?php echo ucfirst($campaign['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($campaign['target_items']); ?></td>
                                                <td><?php echo number_format($campaign['current_items']); ?></td>
                                                <td>
                                                    <?php
                                                    $progress = $campaign['target_items'] > 0 
                                                        ? min(100, round(($campaign['current_items'] / $campaign['target_items']) * 100))
                                                        : 0;
                                                    ?>
                                                    <div class="progress" style="height: 12px; border-radius:999px; background:#e5f3f7;">
                                                        <div class="progress-bar" style="width: <?php echo $progress; ?>%">
                                                            <?php echo $progress; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo number_format($campaign['donations_count']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const donationLabels = <?php echo json_encode(array_column($donationStats, 'month')); ?>;
        const donationCounts = <?php echo json_encode(array_map('intval', array_column($donationStats, 'count'))); ?>;

        const categoryLabels = <?php echo json_encode(array_map(function($s) {
            return $s['name'] ?? 'Khong xac dinh';
        }, $categoryStats)); ?>;
        const categoryCounts = <?php echo json_encode(array_map('intval', array_column($categoryStats, 'count'))); ?>;

        // Donation Chart
        const donationCanvas = document.getElementById('donationChart');
        if (donationLabels.length === 0) {
            donationCanvas.replaceWith(Object.assign(document.createElement('p'), {
                className: 'text-center text-muted mb-0',
                innerText: 'Khong co du lieu'
            }));
        } else {
            new Chart(donationCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: donationLabels,
                    datasets: [{
                        label: 'So quyên góp',
                        data: donationCounts,
                        borderColor: '#0e7490',
                        backgroundColor: 'rgba(14, 116, 144, 0.16)',
                        borderWidth: 3,
                        tension: 0.25,
                        pointRadius: 5,
                        pointBackgroundColor: '#06b6d4',
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, position: 'top' },
                        tooltip: { intersect: false, mode: 'index' }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // Category Chart
        const categoryCanvas = document.getElementById('categoryChart');
        if (categoryLabels.length === 0) {
            categoryCanvas.replaceWith(Object.assign(document.createElement('p'), {
                className: 'text-center text-muted mb-0',
                innerText: 'Khong co du lieu'
            }));
        } else {
            new Chart(categoryCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        data: categoryCounts,
                        backgroundColor: [
                            '#0e7490', '#06b6d4', '#14b8a6', '#22d3ee', '#0891b2',
                            '#0f766e', '#38bdf8', '#155e75', '#67e8f9', '#0ea5e9'
                        ],
                        borderWidth: 2,
                        borderColor: '#f8f9fb'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '55%',
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 14, boxHeight: 14 } },
                        tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.formattedValue}` } }
                    }
                }
            });
        }
    </script>
</body>
</html>

