<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();

$pageTitle = 'Panel Chiến Dịch';
$panelType = 'campaigns';

// Restrict staff by approved job role (admin is always allowed)
if (isStaff() && !isAdmin()) {
    $displayRole = getUserDisplayRole((int)($_SESSION['user_id'] ?? 0), (int)($_SESSION['role_id'] ?? 0));
    $normalized = mb_strtolower(trim((string)$displayRole), 'UTF-8');
    $allowed = ['quản lý chiến dịch', 'quan ly chien dich'];
    if (!in_array($normalized, $allowed, true)) {
        header('Location: ../staff-panel.php');
        exit();
    }
}

// ─── Statistics ────────────────────────────────────────────────────────────
$activeCampaigns  = 0;
$pendingCampaigns = 0;
$openTasks        = 0;
$approvedVols     = 0;

try {
    $r = Database::fetch("SELECT COUNT(*) AS c FROM campaigns WHERE status = 'active'");
    $activeCampaigns = (int)($r['c'] ?? 0);
} catch (Exception $e) {}

try {
    $r = Database::fetch("SELECT COUNT(*) AS c FROM campaigns WHERE status IN ('pending','draft')");
    $pendingCampaigns = (int)($r['c'] ?? 0);
} catch (Exception $e) {}

try {
    $r = Database::fetch("SELECT COUNT(*) AS c FROM campaign_tasks WHERE status IN ('open','in_progress')");
    $openTasks = (int)($r['c'] ?? 0);
} catch (Exception $e) {}

try {
    $r = Database::fetch("SELECT COUNT(*) AS c FROM recruitment_applications WHERE status = 'approved'");
    $approvedVols = (int)($r['c'] ?? 0);
} catch (Exception $e) {}

// ─── Chart 1: top 6 campaigns by progress ─────────────────────────────────
$progressChart = [];
try {
    $progressRows = Database::fetchAll("
        SELECT name, goal_amount, current_amount
        FROM campaigns
        ORDER BY updated_at DESC
        LIMIT 6
    ");

    foreach ($progressRows as $row) {
        $goal = (float)($row['goal_amount'] ?? 0);
        $cur  = (float)($row['current_amount'] ?? 0);
        $pct  = $goal > 0 ? min(100, round(($cur / $goal) * 100, 1)) : 0;
        $progressChart[] = [
            'label' => $row['name'] ?? 'Chiến dịch',
            'total' => $pct,
        ];
    }
} catch (Exception $e) {}

// ─── Chart 2: campaign status distribution ────────────────────────────────
$statusChart = [];
try {
    $statusChart = Database::fetchAll("
        SELECT status AS label, COUNT(*) AS total
        FROM campaigns
        GROUP BY status
    ");
} catch (Exception $e) {}

// ─── Recent tasks list ─────────────────────────────────────────────────────
$recentTasks = [];
try {
    $recentTasks = Database::fetchAll("
        SELECT ct.task_id, ct.title, ct.status, ct.deadline, ct.created_at,
               c.name AS campaign_name
        FROM campaign_tasks ct
        LEFT JOIN campaigns c ON ct.campaign_id = c.campaign_id
        ORDER BY ct.created_at DESC, ct.task_id DESC
        LIMIT 10
    ");
} catch (Exception $e) {}

$taskStatusLabels = [
    'open'        => ['text' => 'Mở',         'class' => 'warning text-dark'],
    'in_progress' => ['text' => 'Đang làm',   'class' => 'info text-dark'],
    'completed'   => ['text' => 'Hoàn thành', 'class' => 'success'],
    'closed'      => ['text' => 'Đóng',       'class' => 'secondary'],
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

        .stat-cp1 .stat-icon { background: #fef3c7; color: #d97706; }
        .stat-cp2 .stat-icon { background: #fee2e2; color: #dc2626; }
        .stat-cp3 .stat-icon { background: #dbeafe; color: #2563eb; }
        .stat-cp4 .stat-icon { background: #dcfce7; color: #059669; }

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

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
            <div class="dashboard-topbar d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center gap-3">
                <div>
                    <h1><i class="bi bi-megaphone-fill me-2"></i>Chiến Dịch</h1>
                    <div class="dashboard-note">Theo dõi tiến độ chiến dịch và nhiệm vụ triển khai</div>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="campaigns.php" class="btn btn-sm"
                       style="background:#e0f4fa;color:#0b728c;border:1px solid #9fd8e6;border-radius:12px;font-weight:700;padding:.55rem 1.2rem;">
                        <i class="bi bi-megaphone me-1"></i>Quản lý chiến dịch
                    </a>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-cp1 h-100"><div class="card-body"><div class="d-flex align-items-center justify-content-between"><div><div class="stat-label">Đang hoạt động</div><div class="stat-value"><?php echo number_format($activeCampaigns); ?></div></div><i class="bi bi-broadcast-pin stat-icon"></i></div></div></div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-cp2 h-100"><div class="card-body"><div class="d-flex align-items-center justify-content-between"><div><div class="stat-label">Chờ duyệt / Nháp</div><div class="stat-value"><?php echo number_format($pendingCampaigns); ?></div></div><i class="bi bi-hourglass-split stat-icon"></i></div></div></div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-cp3 h-100"><div class="card-body"><div class="d-flex align-items-center justify-content-between"><div><div class="stat-label">Nhiệm vụ đang mở</div><div class="stat-value"><?php echo number_format($openTasks); ?></div></div><i class="bi bi-list-task stat-icon"></i></div></div></div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stat-card stat-cp4 h-100"><div class="card-body"><div class="d-flex align-items-center justify-content-between"><div><div class="stat-label">Ứng viên đã duyệt</div><div class="stat-value"><?php echo number_format($approvedVols); ?></div></div><i class="bi bi-person-check-fill stat-icon"></i></div></div></div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-8 col-lg-7">
                    <div class="card dashboard-card mb-4">
                        <div class="card-header dashboard-card-header"><h6 class="dashboard-card-title">Tiến độ chiến dịch (%)</h6></div>
                        <div class="card-body"><div class="chart-area"><canvas id="progressChart"></canvas></div></div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-5">
                    <div class="card dashboard-card mb-4">
                        <div class="card-header dashboard-card-header"><h6 class="dashboard-card-title">Phân bổ trạng thái chiến dịch</h6></div>
                        <div class="card-body"><div class="chart-pie pt-4 pb-2"><canvas id="statusChart"></canvas></div></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="card dashboard-card">
                        <div class="card-header dashboard-card-header d-flex justify-content-between align-items-center">
                            <h6 class="dashboard-card-title">Nhiệm vụ gần đây</h6>
                            <a href="campaign-tasks.php" class="btn btn-sm btn-outline-secondary" style="border-radius:10px;font-size:.8rem;">Xem tất cả</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" style="font-size:.86rem;">
                                    <thead style="background:#f0f9fc;">
                                        <tr>
                                            <th class="px-3 py-2">#</th>
                                            <th class="py-2">Nhiệm vụ</th>
                                            <th class="py-2">Chiến dịch</th>
                                            <th class="py-2">Hạn chót</th>
                                            <th class="py-2">Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentTasks)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">Chưa có nhiệm vụ nào</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($recentTasks as $task): ?>
                                        <tr>
                                            <td class="px-3 py-2 text-muted">#<?php echo (int)$task['task_id']; ?></td>
                                            <td class="py-2 fw-semibold"><?php echo htmlspecialchars($task['title'] ?? ''); ?></td>
                                            <td class="py-2 text-muted"><?php echo htmlspecialchars($task['campaign_name'] ?? 'Không xác định'); ?></td>
                                            <td class="py-2 text-muted"><?php echo !empty($task['deadline']) ? date('d/m/Y', strtotime($task['deadline'])) : '—'; ?></td>
                                            <td class="py-2">
                                                <?php
                                                    $s = $task['status'] ?? 'open';
                                                    $sl = $taskStatusLabels[$s] ?? ['text' => ucfirst($s), 'class' => 'secondary'];
                                                ?>
                                                <span class="badge bg-<?php echo $sl['class']; ?>"><?php echo $sl['text']; ?></span>
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
const progressData = <?php echo json_encode($progressChart, JSON_UNESCAPED_UNICODE); ?>;
new Chart(document.getElementById('progressChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: progressData.map(d => d.label),
        datasets: [{
            label: 'Tiến độ (%)',
            data: progressData.map(d => Number(d.total)),
            backgroundColor: ['#0891b2','#06b6d4','#14b8a6','#38bdf8','#0ea5e9','#22d3ee'],
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                min: 0,
                max: 100,
                grid: { color: 'rgba(148,163,184,.2)' },
                ticks: { color: '#64748b', callback: (v) => v + '%' }
            },
            x: { grid: { display: false }, ticks: { color: '#64748b' } }
        }
    }
});

const statusData = <?php echo json_encode($statusChart, JSON_UNESCAPED_UNICODE); ?>;
const statusText = {
    active: 'Đang hoạt động',
    pending: 'Chờ duyệt',
    draft: 'Nháp',
    completed: 'Hoàn thành',
    cancelled: 'Đã huỷ'
};
const statusColor = {
    active: '#22c55e',
    pending: '#f59e0b',
    draft: '#94a3b8',
    completed: '#0891b2',
    cancelled: '#ef4444'
};

new Chart(document.getElementById('statusChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: statusData.map(d => statusText[d.label] || d.label),
        datasets: [{
            data: statusData.map(d => Number(d.total)),
            backgroundColor: statusData.map(d => statusColor[d.label] || '#64748b')
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
