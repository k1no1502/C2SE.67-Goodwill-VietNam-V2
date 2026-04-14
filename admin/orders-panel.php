<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();

$pageTitle = 'Tổng quan đơn hàng';
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

// ─── Doughnut chart: direct vs online order groups ────────────────────────
$orderTypeChart = [];
try {
    $orderTypeChart = Database::fetchAll(" 
        SELECT order_group AS label, COUNT(*) AS total
        FROM (
            SELECT CASE
                WHEN o.shipping_method = 'pickup' THEN 'direct'
                WHEN o.status = 'delivered' THEN 'online_delivered'
                ELSE 'online_undelivered'
            END AS order_group
            FROM orders o
        ) grouped_orders
        GROUP BY order_group
    ");
} catch (Exception $e) {}

// ─── Recent orders ──────────────────────────────────────────────────────────
$recentOrders = [];
try {
    $recentOrders = Database::fetchAll("
        SELECT o.order_id, o.status, o.total_amount, o.created_at,
               o.shipping_method, o.shipping_note,
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

$orderTypeLabels = [
    'direct' => ['text' => 'Trực tiếp', 'class' => 'success-subtle text-success-emphasis'],
    'online' => ['text' => 'Online', 'class' => 'primary-subtle text-primary-emphasis'],
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

        .orders-topbar {
            background: transparent;
            border: 0;
            border-radius: 16px;
            padding: 0.15rem 0 0.25rem;
            color: #0f172a;
            margin-top: 0.2rem;
            margin-bottom: 1rem;
            box-shadow: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .orders-head {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }
        .orders-head-icon {
            width: 74px;
            height: 74px;
            border-radius: 18px;
            background: linear-gradient(145deg, #0b728c, #095f75);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(8, 74, 92, 0.23);
            flex-shrink: 0;
        }
        .orders-head-icon i {
            font-size: 2rem;
            line-height: 1;
        }
        .orders-topbar-title {
            font-size: clamp(1.7rem, 2.8vw, 2.9rem);
            font-weight: 900;
            line-height: 1.08;
            letter-spacing: 0.1px;
            margin: 0;
            color: #0f172a;
        }
        .orders-topbar-sub  {
            color: #58718a;
            font-size: clamp(1rem, 1.35vw, 1.15rem);
            margin: 0.35rem 0 0;
            line-height: 1.25;
        }
        @media (max-width: 767.98px) {
            .orders-topbar { padding: 0.05rem 0 0.25rem; }
            .orders-head {
                gap: 0.72rem;
                align-items: flex-start;
            }
            .orders-head-icon {
                width: 56px;
                height: 56px;
                border-radius: 14px;
            }
            .orders-head-icon i { font-size: 1.45rem; }
            .orders-topbar-sub { font-size: 1rem; }
        }

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
            <div class="orders-topbar">
                <div class="orders-head">
                    <div class="orders-head-icon">
                        <i class="bi bi-cart-check"></i>
                    </div>
                    <div>
                        <h1 class="orders-topbar-title">Tổng quan đơn hàng</h1>
                        <p class="orders-topbar-sub">Theo dõi và xử lý toàn bộ đơn hàng của hệ thống</p>
                    </div>
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
                            <h6 class="dashboard-card-title">Phân loại đơn hàng</h6>
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
                                            <th class="py-2">Loại đơn</th>
                                            <th class="py-2">Tổng tiền</th>
                                            <th class="py-2">Trạng thái</th>
                                            <th class="py-2">Ngày đặt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentOrders)): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-4">Chưa có đơn hàng nào</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($recentOrders as $order): ?>
                                        <?php
                                            $shippingMethod = strtolower(trim((string)($order['shipping_method'] ?? '')));
                                            $shippingNote = strtolower(trim((string)($order['shipping_note'] ?? '')));
                                            $orderTypeKey = ($shippingMethod === 'pickup' || str_contains($shippingNote, 'trực tiếp') || str_contains($shippingNote, 'mua tại quầy'))
                                                ? 'direct'
                                                : 'online';
                                            $typeMeta = $orderTypeLabels[$orderTypeKey] ?? $orderTypeLabels['online'];
                                        ?>
                                        <tr>
                                            <td class="px-3 py-2 text-muted">#<?php echo (int)$order['order_id']; ?></td>
                                            <td class="py-2 fw-semibold"><?php echo htmlspecialchars($order['customer_name'] ?? 'Ẩn danh'); ?></td>
                                            <td class="py-2">
                                                <span class="badge rounded-pill bg-<?php echo $typeMeta['class']; ?>">
                                                    Loại đơn: <?php echo $typeMeta['text']; ?>
                                                </span>
                                            </td>
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

// Doughnut — direct / online order groups
const stData   = <?php echo json_encode($orderTypeChart, JSON_UNESCAPED_UNICODE); ?>;
const stLabels = {
    direct: 'Đơn trực tiếp',
    online_undelivered: 'Online chưa giao',
    online_delivered: 'Online đã giao'
};
const stColors = {
    direct: '#0ea5a4',
    online_undelivered: '#f59e0b',
    online_delivered: '#22c55e'
};

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
