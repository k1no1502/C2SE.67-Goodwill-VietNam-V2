<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();

$pageTitle = 'Panel Đơn Hàng';
$panelType = 'orders';

// Restrict staff by approved job role (admin is always allowed)
if (isStaff() && !isAdmin()) {
    $displayRole = getUserDisplayRole((int)($_SESSION['user_id'] ?? 0), (int)($_SESSION['role_id'] ?? 0));
    $normalized = mb_strtolower(trim((string)$displayRole), 'UTF-8');
    $allowed = ['quản lý đơn hàng', 'quan ly don hang'];
    if (!in_array($normalized, $allowed, true)) {
        header('Location: ../staff-panel.php');
        exit();
    }
}

// ─── Statistics ────────────────────────────────────────────────────────────
$ordersToday     = 0;
$ordersPending   = 0;
$ordersShipping  = 0;
$ordersDelivered = 0;

try {
    $r = Database::fetch("SELECT COUNT(*) AS c FROM orders WHERE DATE(created_at) = CURDATE()");
    $ordersToday = (int)($r['c'] ?? 0);
} catch (Exception $e) {}

try {
    $r = Database::fetch("SELECT COUNT(*) AS c FROM orders WHERE status = 'pending'");
    $ordersPending = (int)($r['c'] ?? 0);
} catch (Exception $e) {}

try {
    $r = Database::fetch("SELECT COUNT(*) AS c FROM orders WHERE status = 'shipping'");
    $ordersShipping = (int)($r['c'] ?? 0);
} catch (Exception $e) {}

try {
    $r = Database::fetch("SELECT COUNT(*) AS c FROM orders WHERE status = 'delivered'");
    $ordersDelivered = (int)($r['c'] ?? 0);
} catch (Exception $e) {}

// ─── Line chart: orders per day for last 7 days ────────────────────────────
$orderTrend = [];
try {
    $trendRaw = Database::fetchAll("
        SELECT DATE(created_at) AS day, COUNT(*) AS total
        FROM orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
        ORDER BY day ASC
    ");
    // Fill all 7 days (even if 0 orders)
    $map = [];
    foreach ($trendRaw as $row) {
        $map[$row['day']] = (int)$row['total'];
    }
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $orderTrend[] = ['label' => date('d/m', strtotime($d)), 'total' => $map[$d] ?? 0];
    }
} catch (Exception $e) {}

// ─── Doughnut chart: order status distribution ─────────────────────────────
$statusChart = [];
try {
    $statusChart = Database::fetchAll("
        SELECT status AS label, COUNT(*) AS total
        FROM orders
        GROUP BY status
    ");
} catch (Exception $e) {}

// ─── Recent orders ──────────────────────────────────────────────────────────
$recentOrders = [];
try {
    $recentOrders = Database::fetchAll("
        SELECT o.order_id, o.status, o.total_amount, o.created_at,
               u.name AS customer_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.user_id
        ORDER BY o.created_at DESC, o.order_id DESC
        LIMIT 10
    ");
} catch (Exception $e) {}

$statusLabels = [
    'pending'   => ['text' => 'Chờ xử lý',   'class' => 'warning text-dark'],
    'confirmed' => ['text' => 'Đã xác nhận', 'class' => 'info text-dark'],
    'shipping'  => ['text' => 'Đang giao',    'class' => 'primary'],
    'delivered' => ['text' => 'Đã giao',      'class' => 'success'],
    'cancelled' => ['text' => 'Đã huỷ',       'class' => 'danger'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Goodwill Vietnam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body { background: #f3f9fc; }
        .admin-content { padding-top: 1rem; padding-bottom: 1.5rem; }

        .dashboard-topbar {
            background: linear-gradient(140deg, #f7fcfe 0%, #ecf7fb 100%);
            border: 1px solid #d7edf3;
            border-radius: 22px;
            padding: 1rem 1.45rem;
            margin-top: .35rem;
            margin-bottom: 1.2rem;
            box-shadow: 0 10px 24px rgba(8,74,92,.07);
        }
        .dashboard-topbar h1 {
            margin: 0; color: #0f172a; font-weight: 800;
            font-size: clamp(1.6rem, 2.5vw, 2.4rem); line-height: 1.1;
        }
        .dashboard-note { color: #64748b; font-size: .95rem; margin-top: .4rem; }

        .stat-card {
            border: 1px solid #d7edf3; border-radius: 16px;
            box-shadow: 0 10px 24px rgba(8,74,92,.08); background: #fff;
        }
        .stat-card .card-body { padding: 1rem 1.1rem; }
        .stat-label { font-size: .78rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; color: #0b728c; margin-bottom: .28rem; }
        .stat-value { font-size: 1.65rem; font-weight: 800; color: #0f172a; line-height: 1; }
        .stat-icon  { width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; }

        .stat-od1 .stat-icon { background: #dbeafe; color: #2563eb; }
        .stat-od2 .stat-icon { background: #fef3c7; color: #d97706; }
        .stat-od3 .stat-icon { background: #e0f2fe; color: #0284c7; }
        .stat-od4 .stat-icon { background: #dcfce7; color: #059669; }

        .dashboard-card {
            border: 1px solid #d7edf3; border-radius: 16px;
            box-shadow: 0 10px 24px rgba(8,74,92,.08); overflow: hidden;
        }
        .dashboard-card-header {
            background: linear-gradient(140deg, #fbfeff 0%, #edf8fb 100%);
            border-bottom: 1px solid #d7edf3; padding: .82rem 1rem;
        }
        .dashboard-card-title { font-weight: 700; color: #0b728c; margin: 0; }
        .chart-area { min-height: 280px; }
        .chart-pie  { min-height: 280px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/staff-sidebar.php'; ?>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">

            <!-- Top bar -->
            <div class="dashboard-topbar d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center gap-3">
                <div>
                    <h1><i class="bi bi-cart-check me-2"></i>Đơn Hàng</h1>
                    <div class="dashboard-note">Theo dõi và xử lý đơn hàng — Goodwill Vietnam</div>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="orders.php" class="btn btn-sm"
                       style="background:#e0f4fa;color:#0b728c;border:1px solid #9fd8e6;border-radius:12px;font-weight:700;padding:.55rem 1.2rem;">
                        <i class="bi bi-cart-check me-1"></i>Quản lý đơn hàng
                    </a>
                </div>
            </div>

            <!-- Stats cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-od1 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Đơn hôm nay</div>
                                    <div class="stat-value"><?php echo number_format($ordersToday); ?></div>
                                </div>
                                <i class="bi bi-calendar-check stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-od2 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Chờ xử lý</div>
                                    <div class="stat-value"><?php echo number_format($ordersPending); ?></div>
                                </div>
                                <i class="bi bi-hourglass-split stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-od3 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Đang giao</div>
                                    <div class="stat-value"><?php echo number_format($ordersShipping); ?></div>
                                </div>
                                <i class="bi bi-truck stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-od4 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Đã giao thành công</div>
                                    <div class="stat-value"><?php echo number_format($ordersDelivered); ?></div>
                                </div>
                                <i class="bi bi-check-circle-fill stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts row -->
            <div class="row mb-4">
                <div class="col-xl-8 col-lg-7">
                    <div class="card dashboard-card mb-4">
                        <div class="card-header dashboard-card-header">
                            <h6 class="dashboard-card-title">Đơn hàng 7 ngày gần nhất</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-area">
                                <canvas id="orderTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-5">
                    <div class="card dashboard-card mb-4">
                        <div class="card-header dashboard-card-header">
                            <h6 class="dashboard-card-title">Phân bổ trạng thái đơn</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-pie pt-4 pb-2">
                                <canvas id="statusDoughnutChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent orders table -->
            <div class="row">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header dashboard-card-header d-flex justify-content-between align-items-center">
                            <h6 class="dashboard-card-title">Đơn hàng gần đây</h6>
                            <a href="orders.php" class="btn btn-sm btn-outline-secondary" style="border-radius:10px;font-size:.8rem;">
                                Xem tất cả
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" style="font-size:.86rem;">
                                    <thead style="background:#f0f9fc;">
                                        <tr>
                                            <th class="px-3 py-2">#</th>
                                            <th class="py-2">Khách hàng</th>
                                            <th class="py-2">Tổng tiền</th>
                                            <th class="py-2">Trạng thái</th>
                                            <th class="py-2">Ngày đặt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentOrders)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">Chưa có đơn hàng nào</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td class="px-3 py-2 text-muted">#<?php echo (int)$order['order_id']; ?></td>
                                            <td class="py-2 fw-semibold"><?php echo htmlspecialchars($order['customer_name'] ?? 'Ẩn danh'); ?></td>
                                            <td class="py-2">
                                                <?php echo $order['total_amount'] !== null
                                                    ? number_format((float)$order['total_amount'], 0, ',', '.') . ' đ'
                                                    : '—'; ?>
                                            </td>
                                            <td class="py-2">
                                                <?php
                                                    $s  = $order['status'] ?? 'pending';
                                                    $sl = $statusLabels[$s] ?? ['text' => ucfirst($s), 'class' => 'secondary'];
                                                ?>
                                                <span class="badge bg-<?php echo $sl['class']; ?>"><?php echo $sl['text']; ?></span>
                                            </td>
                                            <td class="py-2 text-muted" style="font-size:.8rem;">
                                                <?php echo $order['created_at'] ? date('H:i d/m/Y', strtotime($order['created_at'])) : '—'; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
<script>
// Line chart — orders per day
const trendData   = <?php echo json_encode($orderTrend, JSON_UNESCAPED_UNICODE); ?>;
const trendLabels = trendData.map(d => d.label);
const trendVals   = trendData.map(d => parseInt(d.total));

new Chart(document.getElementById('orderTrendChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Số đơn hàng',
            data: trendVals,
            borderColor: '#0891b2',
            backgroundColor: 'rgba(8,145,178,.15)',
            fill: true,
            tension: .35,
            pointRadius: 4,
            pointBackgroundColor: '#0891b2',
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { color: '#334155' } } },
        scales: {
            y: { grid: { color: 'rgba(148,163,184,.2)' }, ticks: { color: '#64748b', precision: 0 } },
            x: { grid: { display: false },               ticks: { color: '#64748b' } }
        }
    }
});

// Doughnut — order status
const stData   = <?php echo json_encode($statusChart, JSON_UNESCAPED_UNICODE); ?>;
const stLabels = { pending:'Chờ xử lý', confirmed:'Đã xác nhận', shipping:'Đang giao', delivered:'Đã giao', cancelled:'Đã huỷ' };
const stColors = { pending:'#f59e0b', confirmed:'#38bdf8', shipping:'#0891b2', delivered:'#22c55e', cancelled:'#ef4444' };

new Chart(document.getElementById('statusDoughnutChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: stData.map(d => stLabels[d.label] || d.label),
        datasets: [{
            data: stData.map(d => parseInt(d.total)),
            backgroundColor: stData.map(d => stColors[d.label] || '#94a3b8')
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { color: '#334155' } } }
    }
});
</script>
</body>
</html>
