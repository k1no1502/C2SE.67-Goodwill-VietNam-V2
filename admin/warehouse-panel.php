<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();

$pageTitle = 'Panel Kho Hàng';
$panelType = 'warehouse';

// Restrict staff by approved job role (admin is always allowed)
if (isStaff() && !isAdmin()) {
    $displayRole = getUserDisplayRole((int)($_SESSION['user_id'] ?? 0), (int)($_SESSION['role_id'] ?? 0));
    $normalized = mb_strtolower(trim((string)$displayRole), 'UTF-8');
    $allowed = ['quản lý kho hàng', 'quan ly kho hang'];
    if (!in_array($normalized, $allowed, true)) {
        header('Location: ../staff-panel.php');
        exit();
    }
}

// ─── Statistics ────────────────────────────────────────────────────────────
$totalItems      = 0;
$pendingDonations = 0;
$lowStockItems   = 0;
$categories      = 0;

try {
    $r = Database::fetch("SELECT COUNT(*) AS c FROM inventory WHERE status != 'disposed'");
    $totalItems = (int)($r['c'] ?? 0);
} catch (Exception $e) {}

try {
    $r = Database::fetch("SELECT COUNT(*) AS c FROM donations WHERE status = 'pending'");
    $pendingDonations = (int)($r['c'] ?? 0);
} catch (Exception $e) {}

try {
    $r = Database::fetch("SELECT COUNT(*) AS c FROM inventory WHERE quantity <= 2 AND quantity > 0 AND status = 'available'");
    $lowStockItems = (int)($r['c'] ?? 0);
} catch (Exception $e) {}

try {
    $r = Database::fetch("SELECT COUNT(DISTINCT category_id) AS c FROM inventory WHERE category_id IS NOT NULL");
    $categories = (int)($r['c'] ?? 0);
} catch (Exception $e) {}

// ─── Bar chart: inventory by category ─────────────────────────────────────
$categoryChart = [];
try {
    $categoryChart = Database::fetchAll("
        SELECT c.name AS label, COUNT(i.item_id) AS total
        FROM inventory i
        LEFT JOIN categories c ON i.category_id = c.category_id
        WHERE i.status != 'disposed'
        GROUP BY c.category_id, c.name
        ORDER BY total DESC
        LIMIT 7
    ");
} catch (Exception $e) {}

// ─── Doughnut chart: item status distribution ─────────────────────────────
$statusChart = [];
try {
    $statusChart = Database::fetchAll("
        SELECT status AS label, COUNT(*) AS total
        FROM inventory
        GROUP BY status
    ");
} catch (Exception $e) {}

// ─── Recent inventory items ────────────────────────────────────────────────
$recentItems = [];
try {
    $recentItems = Database::fetchAll("
        SELECT i.item_id, i.name, i.quantity, i.unit, i.status, i.updated_at,
               c.name AS category_name
        FROM inventory i
        LEFT JOIN categories c ON i.category_id = c.category_id
        ORDER BY i.updated_at DESC, i.item_id DESC
        LIMIT 10
    ");
} catch (Exception $e) {}

$statusLabels = [
    'available' => ['text' => 'Có sẵn',    'class' => 'success'],
    'reserved'  => ['text' => 'Đã đặt',    'class' => 'warning'],
    'sold'      => ['text' => 'Đã bán',     'class' => 'secondary'],
    'disposed'  => ['text' => 'Đã loại',    'class' => 'danger'],
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
            display: flex;
            align-items: center;
            gap: 12px;
            background: transparent;
            border: 0;
            border-radius: 16px;
            padding: 0.15rem 0 0.3rem;
            margin-top: 0.2rem;
            margin-bottom: 1.05rem;
            box-shadow: none;
        }
        .dashboard-topbar-icon {
            width: 74px;
            height: 74px;
            border-radius: 18px;
            background: linear-gradient(145deg, #0b728c, #095f75);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
            box-shadow: 0 10px 20px rgba(8, 74, 92, 0.23);
        }
        .dashboard-topbar h1 {
            margin: 0;
            color: #0f172a;
            font-weight: 900;
            font-size: clamp(1.7rem, 2.8vw, 2.9rem);
            line-height: 1.08;
            letter-spacing: 0.1px;
        }
        .dashboard-note {
            color: #58718a;
            font-size: clamp(1rem, 1.35vw, 1.15rem);
            line-height: 1.25;
            margin-top: .35rem;
            margin-bottom: 0;
            font-weight: 700;
        }

        @media (max-width: 767.98px) {
            .dashboard-topbar {
                padding: 0.05rem 0 0.25rem;
                gap: 0.72rem;
                align-items: flex-start;
            }

            .dashboard-topbar-icon {
                width: 56px;
                height: 56px;
                border-radius: 14px;
                font-size: 1.45rem;
            }

            .dashboard-note { font-size: 1rem; }
        }

        .stat-card {
            border: 1px solid #d7edf3; border-radius: 16px;
            box-shadow: 0 10px 24px rgba(8,74,92,.08); background: #fff;
        }
        .stat-card .card-body { padding: 1rem 1.1rem; }
        .stat-label { font-size: .78rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; color: #0b728c; margin-bottom: .28rem; }
        .stat-value { font-size: 1.65rem; font-weight: 800; color: #0f172a; line-height: 1; }
        .stat-icon  { width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; }

        .stat-wh1 .stat-icon { background: #dbeafe; color: #2563eb; }
        .stat-wh2 .stat-icon { background: #dcfce7; color: #059669; }
        .stat-wh3 .stat-icon { background: #fef3c7; color: #d97706; }
        .stat-wh4 .stat-icon { background: #f3e8ff; color: #9333ea; }

        .dashboard-card {
            border: 1px solid #d7edf3; border-radius: 16px;
            box-shadow: 0 10px 24px rgba(8,74,92,.08); overflow: hidden;
        }
        .dashboard-card-header {
            background: linear-gradient(140deg, #fbfeff 0%, #edf8fb 100%);
            border-bottom: 1px solid #d7edf3; padding: .82rem 1rem;
        }
        .dashboard-card-title { font-weight: 700; color: #0b728c; margin: 0; }
        .chart-area  { min-height: 280px; }
        .chart-pie   { min-height: 280px; }
        .activity-item {
            border: 1px solid #ebf6fa; border-radius: 12px;
            margin-bottom: .55rem; background: #fff;
        }
        .activity-item:last-child { margin-bottom: 0; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/staff-sidebar.php'; ?>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">

            <!-- Top bar -->
            <div class="dashboard-topbar">
                <div class="dashboard-topbar-icon">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div>
                    <h1>Quản lý kho hàng</h1>
                    <p class="dashboard-note">Theo dõi và cập nhật toàn bộ vật phẩm trong kho</p>
                </div>
            </div>

            <!-- Stats cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-wh1 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Hàng trong kho</div>
                                    <div class="stat-value"><?php echo number_format($totalItems); ?></div>
                                </div>
                                <i class="bi bi-boxes stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-wh2 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Quyên góp chờ xử lý</div>
                                    <div class="stat-value"><?php echo number_format($pendingDonations); ?></div>
                                </div>
                                <i class="bi bi-heart-fill stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-wh3 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Sắp hết hàng (≤ 2)</div>
                                    <div class="stat-value"><?php echo number_format($lowStockItems); ?></div>
                                </div>
                                <i class="bi bi-exclamation-triangle-fill stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-wh4 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Danh mục hàng</div>
                                    <div class="stat-value"><?php echo number_format($categories); ?></div>
                                </div>
                                <i class="bi bi-tag-fill stat-icon"></i>
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
                            <h6 class="dashboard-card-title">Số lượng hàng theo danh mục</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-area">
                                <canvas id="categoryBarChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-5">
                    <div class="card dashboard-card mb-4">
                        <div class="card-header dashboard-card-header">
                            <h6 class="dashboard-card-title">Trạng thái hàng trong kho</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-pie pt-4 pb-2">
                                <canvas id="statusDoughnutChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent inventory -->
            <div class="row">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header dashboard-card-header d-flex justify-content-between align-items-center">
                            <h6 class="dashboard-card-title">Hàng tồn kho gần đây</h6>
                            <a href="inventory.php" class="btn btn-sm btn-outline-secondary" style="border-radius:10px;font-size:.8rem;">
                                Xem tất cả
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" style="font-size:.86rem;">
                                    <thead style="background:#f0f9fc;">
                                        <tr>
                                            <th class="px-3 py-2">#</th>
                                            <th class="py-2">Tên hàng</th>
                                            <th class="py-2">Danh mục</th>
                                            <th class="py-2">Số lượng</th>
                                            <th class="py-2">Trạng thái</th>
                                            <th class="py-2">Cập nhật</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentItems)): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-4">Chưa có dữ liệu kho</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($recentItems as $item): ?>
                                        <tr>
                                            <td class="px-3 py-2 text-muted"><?php echo (int)$item['item_id']; ?></td>
                                            <td class="py-2 fw-semibold"><?php echo htmlspecialchars($item['name'] ?? ''); ?></td>
                                            <td class="py-2 text-muted"><?php echo htmlspecialchars($item['category_name'] ?? 'Chưa phân loại'); ?></td>
                                            <td class="py-2"><?php echo (int)($item['quantity'] ?? 0); ?> <?php echo htmlspecialchars($item['unit'] ?? ''); ?></td>
                                            <td class="py-2">
                                                <?php
                                                    $s = $item['status'] ?? 'available';
                                                    $sl = $statusLabels[$s] ?? ['text' => ucfirst($s), 'class' => 'secondary'];
                                                ?>
                                                <span class="badge bg-<?php echo $sl['class']; ?>"><?php echo $sl['text']; ?></span>
                                            </td>
                                            <td class="py-2 text-muted" style="font-size:.8rem;">
                                                <?php echo $item['updated_at'] ? date('H:i d/m/Y', strtotime($item['updated_at'])) : '—'; ?>
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
// Bar chart — inventory by category
const catData   = <?php echo json_encode($categoryChart, JSON_UNESCAPED_UNICODE); ?>;
const catLabels = catData.map(d => d.label || 'Khác');
const catVals   = catData.map(d => parseInt(d.total));

new Chart(document.getElementById('categoryBarChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: catLabels,
        datasets: [{
            label: 'Số lượng hàng',
            data: catVals,
            backgroundColor: [
                '#0891b2','#06b6d4','#14b8a6','#38bdf8',
                '#0ea5e9','#22d3ee','#67e8f9'
            ],
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { grid: { color: 'rgba(148,163,184,.2)' }, ticks: { color: '#64748b' } },
            x: { grid: { display: false },               ticks: { color: '#64748b' } }
        }
    }
});

// Doughnut — item status
const stData   = <?php echo json_encode($statusChart, JSON_UNESCAPED_UNICODE); ?>;
const stLabels = { available: 'Có sẵn', reserved: 'Đã đặt', sold: 'Đã bán', disposed: 'Đã loại' };
const stColors = { available: '#22c55e', reserved: '#f59e0b', sold: '#94a3b8', disposed: '#ef4444' };

new Chart(document.getElementById('statusDoughnutChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: stData.map(d => stLabels[d.label] || d.label),
        datasets: [{
            data: stData.map(d => parseInt(d.total)),
            backgroundColor: stData.map(d => stColors[d.label] || '#0891b2')
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { color: '#334155' } }
        }
    }
});
</script>
</body>
</html>
