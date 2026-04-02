<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();
enforceStaffPanelAccess(['campaigns']);
$panelType = 'campaigns';

// Handle campaign approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $campaign_id = (int)($_POST['campaign_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($campaign_id > 0) {
        try {
            if ($action === 'approve') {
                Database::beginTransaction();
                
                // Check if approved_by column exists, if not use status update only
                $columns = Database::fetchAll("SHOW COLUMNS FROM campaigns LIKE 'approved_by'");
                if (!empty($columns)) {
                    Database::execute(
                        "UPDATE campaigns SET status = 'active', approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE campaign_id = ?",
                        [$_SESSION['user_id'], $campaign_id]
                    );
                } else {
                    Database::execute(
                        "UPDATE campaigns SET status = 'active', updated_at = NOW() WHERE campaign_id = ?",
                        [$campaign_id]
                    );
                }
                
                Database::commit();
                setFlashMessage('success', 'Đã duyệt chiến dịch thành công.');
                logActivity($_SESSION['user_id'], 'approve_campaign', "Approved campaign #$campaign_id");
                
            } elseif ($action === 'reject') {
                $reject_reason = sanitize($_POST['reject_reason'] ?? 'Không đạt yêu cầu');
                Database::execute(
                    "UPDATE campaigns SET status = 'cancelled', updated_at = NOW() WHERE campaign_id = ?",
                    [$campaign_id]
                );
                setFlashMessage('success', 'Đã từ chối chiến dịch.');
                logActivity($_SESSION['user_id'], 'reject_campaign', "Rejected campaign #$campaign_id: $reject_reason");
            } elseif ($action === 'pause') {
                Database::execute(
                    "UPDATE campaigns SET status = 'paused', updated_at = NOW() WHERE campaign_id = ?",
                    [$campaign_id]
                );
                setFlashMessage('success', 'Đã tạm dừng chiến dịch.');
                logActivity($_SESSION['user_id'], 'pause_campaign', "Paused campaign #$campaign_id");
            } elseif ($action === 'resume') {
                Database::execute(
                    "UPDATE campaigns SET status = 'active', updated_at = NOW() WHERE campaign_id = ?",
                    [$campaign_id]
                );
                setFlashMessage('success', 'Đã tiếp tục chiến dịch.');
                logActivity($_SESSION['user_id'], 'resume_campaign', "Resumed campaign #$campaign_id");
            } elseif ($action === 'update') {
                $name = sanitize($_POST['name'] ?? '');
                $description = sanitize($_POST['description'] ?? '');
                $start_date = $_POST['start_date'] ?? '';
                $end_date = $_POST['end_date'] ?? '';
                $target_items = (int)($_POST['target_items'] ?? 0);
                
                if (empty($name) || empty($description) || empty($start_date) || empty($end_date)) {
                    throw new Exception('Vui lòng điền đầy đủ thông tin.');
                }
                
                Database::execute(
                    "UPDATE campaigns SET name = ?, description = ?, start_date = ?, end_date = ?, target_items = ?, updated_at = NOW() WHERE campaign_id = ?",
                    [$name, $description, $start_date, $end_date, $target_items, $campaign_id]
                );
                setFlashMessage('success', 'Đã cập nhật chiến dịch.');
                logActivity($_SESSION['user_id'], 'update_campaign', "Updated campaign #$campaign_id");
            } elseif ($action === 'delete') {
                // Check if campaign has donations
                $hasDonations = Database::fetch("SELECT COUNT(*) as count FROM campaign_donations WHERE campaign_id = ?", [$campaign_id])['count'];
                if ($hasDonations > 0) {
                    throw new Exception('Không thể xóa chiến dịch đã có quyên góp.');
                }
                
                Database::beginTransaction();
                // Delete campaign items first
                Database::execute("DELETE FROM campaign_items WHERE campaign_id = ?", [$campaign_id]);
                // Delete campaign
                Database::execute("DELETE FROM campaigns WHERE campaign_id = ?", [$campaign_id]);
                Database::commit();
                setFlashMessage('success', 'Đã xóa chiến dịch.');
                logActivity($_SESSION['user_id'], 'delete_campaign', "Deleted campaign #$campaign_id");
            }
        } catch (Exception $e) {
            Database::rollback();
            setFlashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
    
    header('Location: campaigns.php');
    exit();
}

// Get filters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = "1=1";
$params = [];

if ($status !== '') {
    $where .= " AND c.status = ?";
    $params[] = $status;
}

if ($search !== '') {
    $where .= " AND (c.name LIKE ? OR c.description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Get total count
$totalSql = "SELECT COUNT(*) as count FROM campaigns c WHERE $where";
$totalCampaigns = Database::fetch($totalSql, $params)['count'];
$totalPages = ceil($totalCampaigns / $per_page);

// Get campaigns with creator info
$sql = "SELECT c.*, u.name as creator_name, u.email as creator_email,
               (SELECT COUNT(*) FROM campaign_items WHERE campaign_id = c.campaign_id) as items_count,
               (SELECT COUNT(*) FROM campaign_donations WHERE campaign_id = c.campaign_id) as donations_count
        FROM campaigns c
        LEFT JOIN users u ON c.created_by = u.user_id
        WHERE $where
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$campaigns = Database::fetchAll($sql, $params);

// Get statistics
$stats = [
    'total' => Database::fetch("SELECT COUNT(*) as count FROM campaigns")['count'],
    'pending' => Database::fetch("SELECT COUNT(*) as count FROM campaigns WHERE status = 'draft' OR status = 'pending'")['count'],
    'active' => Database::fetch("SELECT COUNT(*) as count FROM campaigns WHERE status = 'active'")['count'],
    'completed' => Database::fetch("SELECT COUNT(*) as count FROM campaigns WHERE status = 'completed'")['count'],
];

function extractYoutubeVideoId($input) {
    $input = trim((string)$input);
    if ($input === '') {
        return '';
    }

    if (preg_match('/^[a-zA-Z0-9_-]{6,}$/', $input) && stripos($input, 'http') !== 0) {
        return $input;
    }

    $parts = @parse_url($input);
    if (!$parts || empty($parts['host'])) {
        return '';
    }

    $host = strtolower($parts['host']);
    $path = $parts['path'] ?? '';

    if (strpos($host, 'youtu.be') !== false) {
        $id = trim($path, '/');
        return preg_match('/^[a-zA-Z0-9_-]{6,}$/', $id) ? $id : '';
    }

    if (strpos($host, 'youtube.com') !== false || strpos($host, 'youtube-nocookie.com') !== false) {
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (!empty($query['v']) && preg_match('/^[a-zA-Z0-9_-]{6,}$/', $query['v'])) {
                return $query['v'];
            }
        }

        if (preg_match('#/(?:embed|shorts|live|reel|reels)/([a-zA-Z0-9_-]{6,})#i', $path, $matches)) {
            return $matches[1];
        }
    }

    return '';
}

function extractTikTokVideoId($url) {
    $url = trim((string)$url);
    if ($url === '') {
        return '';
    }

    if (preg_match('#/video/(\d+)#i', $url, $matches)) {
        return $matches[1];
    }

    if (preg_match('/[?&]item_id=(\d+)/i', $url, $matches)) {
        return $matches[1];
    }

    return '';
}

function isTikTokLiveUrl($url) {
    $url = trim((string)$url);
    if ($url === '') {
        return false;
    }

    return preg_match('#tiktok\.com/@[^/]+/live#i', $url) === 1 || strpos(strtolower($url), 'live.tiktok.com') !== false;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý chiến dịch - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --brand-700: #0e7490;
            --brand-600: #0f869f;
            --brand-500: #06b6d4;
            --brand-50: #ecfeff;
            --ink-900: #23324a;
            --ink-500: #62718a;
            --line: #d4e8f0;
        }

        body {
            background: #f3f9fc;
        }

        .campaign-topbar {
            background: linear-gradient(140deg, #f7fcfe 0%, #ecf7fb 100%);
            border: 1px solid #d7edf3;
            border-radius: 16px;
            padding: 1rem 1.1rem;
            color: #0f172a;
            margin: 0.35rem 0 1.2rem;
            box-shadow: 0 10px 24px rgba(8, 74, 92, 0.07);
        }

        .campaign-topbar h1 {
            font-size: clamp(2rem, 3vw, 3.4rem);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: 0.2px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.7rem;
            color: #0f172a;
        }

        .campaign-topbar h1 i {
            font-size: 0.9em;
        }

        .campaign-topbar p {
            color: #64748b;
            font-size: clamp(0.95rem, 1.2vw, 1.08rem);
            margin: 0.45rem 0 0;
            opacity: 1;
        }

        @media (max-width: 767.98px) {
            .campaign-topbar {
                padding: 1rem;
            }

            .campaign-topbar h1 {
                font-size: 2rem;
            }
        }

        .stat-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(14, 116, 144, .07);
            transition: transform .18s, box-shadow .18s;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(14, 116, 144, .13);
        }

        .stat-label {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--ink-500);
            margin-bottom: 5px;
        }

        .stat-value {
            font-size: 2rem;
            line-height: 1;
            font-weight: 800;
            color: var(--ink-900);
            margin: 0;
        }

        .stats-total .stat-value { color: var(--brand-700); }
        .stats-pending .stat-value { color: #b45309; }
        .stats-active .stat-value { color: #15803d; }
        .stats-completed .stat-value { color: #0c4a6e; }

        .filter-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 18px 22px;
            box-shadow: 0 2px 12px rgba(14, 116, 144, .06);
            margin-bottom: 1.5rem;
        }

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

        .btn-filter {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            height: 42px;
        }

        .btn-filter:hover {
            color: #fff;
            opacity: .92;
        }

        .campaign-table-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(14, 116, 144, .07);
        }

        .campaign-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .campaign-table thead th {
            background: linear-gradient(135deg, #083344 0%, #0e7490 100%);
            color: rgba(255, 255, 255, .76);
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            border: none;
            padding: 14px 14px;
            white-space: nowrap;
        }

        .campaign-table tbody td {
            border-bottom: 1px solid #edf5f8;
            vertical-align: middle;
            color: var(--ink-900);
            padding: 13px 14px;
            font-size: .88rem;
        }

        .campaign-table tbody tr:hover {
            background: #f0fbfe;
        }

        .campaign-table tbody tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            border-radius: 999px;
            padding: 4px 11px;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .03em;
        }

        .status-badge.bg-secondary { background: #eef2f7 !important; color: #475569; }
        .status-badge.bg-warning { background: rgba(234, 179, 8, .16) !important; color: #854d0e; }
        .status-badge.bg-success { background: rgba(22, 163, 74, .13) !important; color: #166534; }
        .status-badge.bg-info { background: rgba(8, 145, 178, .14) !important; color: #155e75; }
        .status-badge.bg-primary { background: rgba(14, 116, 144, .14) !important; color: var(--brand-700); }
        .status-badge.bg-danger { background: rgba(239, 68, 68, .12) !important; color: #991b1b; }

        .campaign-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .campaign-actions.pending-approval {
            gap: 8px;
        }

        .campaign-actions.pending-approval .btn {
            min-width: auto;
            padding: 0.45rem 0.9rem;
            font-size: .84rem;
            display: flex;
            align-items: center;
            gap: .4rem;
            white-space: nowrap;
            border-radius: 9px;
            border: none;
            font-weight: 600;
            transition: transform .15s, opacity .15s;
        }

        .campaign-actions.pending-approval .btn:hover {
            transform: translateY(-1px);
            opacity: .92;
        }

        .campaign-actions.pending-approval .btn-view {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            color: #fff;
        }

        .campaign-actions.pending-approval .btn-approve {
            background: #16a34a;
            color: #fff;
        }

        .campaign-actions.pending-approval .btn-cancel {
            background: #ef4444;
            color: #fff;
        }

        .campaign-action-btn {
            width: 34px;
            height: 34px;
            border: none;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: .95rem;
            transition: transform .15s, box-shadow .15s;
            cursor: pointer;
        }

        .campaign-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(6, 182, 212, .25);
        }

        .campaign-action-btn:hover {
            transform: translateY(-1px);
        }

        .campaign-action-btn.view { background: linear-gradient(135deg, var(--brand-700), var(--brand-500)); }
        .campaign-action-btn.edit { background: #64748b; }
        .campaign-action-btn.delete { background: #ef4444; }
        .campaign-action-btn.pause { background: #f59e0b; color: #2d2d2d; }
        .campaign-action-btn.resume { background: #16a34a; }
        .campaign-action-btn i { pointer-events: none; }

        .progress {
            border-radius: 999px;
            background: #e5f3f7;
            overflow: hidden;
            height: 12px !important;
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--brand-700), var(--brand-500));
            font-size: .65rem;
            font-weight: 700;
        }

        .modal-content {
            border: none;
            border-radius: 16px;
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, #083344, #0e7490);
            color: #fff;
            border-bottom: none;
            padding: 18px 24px;
        }

        .modal-header .btn-close {
            filter: invert(1) brightness(2);
        }

        .modal-title {
            font-weight: 700;
            font-size: 1rem;
        }

        .modal-footer {
            border-top: 1px solid var(--line);
        }

        .modal-action-group {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: flex-end;
        }

        .modal-action-btn {
            width: 44px;
            height: 38px;
            border: none;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1rem;
            transition: transform .15s, box-shadow .15s;
        }

        .modal-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(6, 182, 212, .25);
        }

        .modal-action-btn:hover {
            transform: translateY(-1px);
        }

        .modal-action-btn.cancel { background: #94a3b8; }
        .modal-action-btn.reject { background: #ef4444; }
        .modal-action-btn.save { background: linear-gradient(135deg, var(--brand-700), var(--brand-500)); }

        .pagination .page-link {
            border: 1px solid var(--line);
            color: var(--brand-700);
            border-radius: 8px !important;
            margin: 0 2px;
            font-weight: 500;
            padding: 6px 14px;
        }

        .pagination .page-link:hover {
            background: var(--brand-50);
        }

        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            border-color: transparent;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php
                if (isStaff() && !isAdmin()) {
                    include 'includes/staff-sidebar.php';
                } else {
                    include 'includes/sidebar.php';
                }
            ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="campaign-topbar">
                    <div>
                        <h1><i class="bi bi-trophy me-2"></i>Quản lý chiến dịch</h1>
                        <p>Theo dõi, duyệt và điều phối toàn bộ chiến dịch cộng đồng</p>
                    </div>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Statistics -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card stats-total">
                            <div class="stat-label">Tổng chiến dịch</div>
                            <h3 class="stat-value"><?php echo number_format($stats['total']); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card stats-pending">
                            <div class="stat-label">Chờ duyệt</div>
                            <h3 class="stat-value"><?php echo number_format($stats['pending']); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card stats-active">
                            <div class="stat-label">Đang hoạt động</div>
                            <h3 class="stat-value"><?php echo number_format($stats['active']); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card stats-completed">
                            <div class="stat-label">Hoàn thành</div>
                            <h3 class="stat-value"><?php echo number_format($stats['completed']); ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-card">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tìm kiếm</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Tên chiến dịch...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" name="status">
                                    <option value="">Tất cả</option>
                                    <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Nháp / Chờ duyệt</option>
                                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Đang hoạt động</option>
                                    <option value="paused" <?php echo $status === 'paused' ? 'selected' : ''; ?>>Tạm dừng</option>
                                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Hoàn thành</option>
                                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Đã hủy</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-filter w-100">
                                    <i class="bi bi-search me-1"></i>Lọc
                                </button>
                            </div>
                        </form>
                </div>

                <!-- Campaigns table -->
                <div class="campaign-table-card">
                        <div class="table-responsive">
                            <table class="campaign-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Chiến dịch</th>
                                        <th>Người tạo</th>
                                        <th>Thời gian</th>
                                        <th>Mục tiêu</th>
                                        <th>Tiến độ</th>
                                        <th>Trạng thái</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($campaigns)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Không có chiến dịch nào.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($campaigns as $campaign): ?>
                                            <tr>
                                                <td><?php echo $campaign['campaign_id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($campaign['name']); ?></strong>
                                                    <br><small class="text-muted">
                                                        <?php echo htmlspecialchars(substr($campaign['description'] ?? '', 0, 80)); ?>...
                                                    </small>
                                                    <br><small class="text-info">
                                                        <i class="bi bi-box-seam"></i> <?php echo $campaign['items_count']; ?> vật phẩm | 
                                                        <i class="bi bi-heart"></i> <?php echo $campaign['donations_count']; ?> quyên góp
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($campaign['creator_name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($campaign['creator_email']); ?></small>
                                                </td>
                                                <td>
                                                    <small>
                                                        Bắt đầu: <?php echo formatDate($campaign['start_date']); ?><br>
                                                        Kết thúc: <?php echo formatDate($campaign['end_date']); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php echo number_format($campaign['target_items'] ?? 0); ?> vật phẩm
                                                </td>
                                                <td>
                                                    <?php
                                                    $progress = $campaign['target_items'] > 0 
                                                        ? min(100, round(($campaign['current_items'] / $campaign['target_items']) * 100))
                                                        : 0;
                                                    ?>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar" 
                                                             role="progressbar" 
                                                             style="width: <?php echo $progress; ?>%"
                                                             aria-valuenow="<?php echo $progress; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                            <?php echo $progress; ?>%
                                                        </div>
                                                    </div>
                                                    <small><?php echo $campaign['current_items']; ?> / <?php echo $campaign['target_items']; ?></small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusMap = [
                                                        'draft' => ['class' => 'secondary', 'text' => 'Nháp'],
                                                        'pending' => ['class' => 'warning', 'text' => 'Chờ duyệt'],
                                                        'active' => ['class' => 'success', 'text' => 'Hoạt động'],
                                                        'paused' => ['class' => 'info', 'text' => 'Tạm dừng'],
                                                        'completed' => ['class' => 'primary', 'text' => 'Hoàn thành'],
                                                        'cancelled' => ['class' => 'danger', 'text' => 'Đã hủy']
                                                    ];
                                                    $st = $statusMap[$campaign['status']] ?? ['class' => 'secondary', 'text' => 'N/A'];
                                                    ?>
                                                    <span class="badge status-badge bg-<?php echo $st['class']; ?>">
                                                        <?php echo $st['text']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php 
                                                        $campaignStatus = strtolower($campaign['status'] ?? '');
                                                        // Xem như \"chờ duyệt\" nếu KHÔNG thuộc các trạng thái đã hoạt động / kết thúc
                                                        $waitingForApproval = !in_array($campaignStatus, [
                                                            'active',
                                                            'approved',
                                                            'paused',
                                                            'completed',
                                                            'cancelled'
                                                        ]);
                                                        if ($waitingForApproval):
                                                    ?>
                                                    <div class="campaign-actions pending-approval">
                                                        <!-- View button -->
                                                        <button type="button"
                                                                class="btn btn-view"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#viewModal<?php echo $campaign['campaign_id']; ?>"
                                                                title="Xem chi tiết">
                                                            <i class="bi bi-eye"></i>
                                                            <span>Xem</span>
                                                        </button>

                                                        <!-- Approve button -->
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Duyệt và bắt đầu chiến dịch này?');">
                                                            <input type="hidden" name="campaign_id" value="<?php echo $campaign['campaign_id']; ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <button type="submit"
                                                                    class="btn btn-approve"
                                                                    title="Đồng ý chiến dịch">
                                                                <i class="bi bi-check-lg"></i>
                                                                <span>Đồng ý</span>
                                                            </button>
                                                        </form>

                                                        <!-- Cancel/Reject button -->
                                                        <button type="button"
                                                                class="btn btn-cancel"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#rejectModal<?php echo $campaign['campaign_id']; ?>"
                                                                title="Từ chối chiến dịch">
                                                            <i class="bi bi-x-lg"></i>
                                                            <span>Huỷ</span>
                                                        </button>
                                                    </div>

                                                    <?php else: ?>
                                                    <!-- After approval: Show icon buttons for Edit, Delete, and Pause/Resume -->
                                                    <div class="campaign-actions">
                                                        <!-- View button -->
                                                        <button type="button"
                                                                class="campaign-action-btn view"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#viewModal<?php echo $campaign['campaign_id']; ?>"
                                                                title="Xem chi tiết">
                                                            <i class="bi bi-eye"></i>
                                                        </button>

                                                        <!-- Edit button -->
                                                        <button type="button"
                                                                class="campaign-action-btn edit"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#editModal<?php echo $campaign['campaign_id']; ?>"
                                                                title="Chỉnh sửa chiến dịch">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>

                                                        <!-- Delete button -->
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Xóa chiến dịch này? Hành động này không thể hoàn tác!');">
                                                            <input type="hidden" name="campaign_id" value="<?php echo $campaign['campaign_id']; ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit"
                                                                    class="campaign-action-btn delete"
                                                                    title="Xóa chiến dịch">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>

                                                        <!-- Pause/Resume button -->
                                                        <?php if ($campaignStatus === 'active'): ?>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tạm dừng chiến dịch này?');">
                                                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['campaign_id']; ?>">
                                                                <input type="hidden" name="action" value="pause">
                                                                <button type="submit"
                                                                        class="campaign-action-btn pause"
                                                                        title="Tạm dừng chiến dịch">
                                                                    <i class="bi bi-pause-fill"></i>
                                                                </button>
                                                            </form>
                                                        <?php elseif ($campaignStatus === 'paused'): ?>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Tiếp tục chiến dịch này?');">
                                                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['campaign_id']; ?>">
                                                                <input type="hidden" name="action" value="resume">
                                                                <button type="submit"
                                                                        class="campaign-action-btn resume"
                                                                        title="Tiếp tục chiến dịch">
                                                                    <i class="bi bi-play-fill"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php endif; ?>

                                                </td>
                                            </tr>

                                            <!-- View Modal -->
                                            <div class="modal" id="viewModal<?php echo $campaign['campaign_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Chi tiết chiến dịch #<?php echo $campaign['campaign_id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <p><strong>Tên chiến dịch:</strong> <?php echo htmlspecialchars($campaign['name']); ?></p>
                                                                    <p><strong>Người tạo:</strong> <?php echo htmlspecialchars($campaign['creator_name']); ?></p>
                                                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($campaign['creator_email']); ?></p>
                                                                    <p><strong>Ngày bắt đầu:</strong> <?php echo formatDate($campaign['start_date']); ?></p>
                                                                    <p><strong>Ngày kết thúc:</strong> <?php echo formatDate($campaign['end_date']); ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <p><strong>Mục tiêu:</strong> <?php echo number_format($campaign['target_items']); ?> vật phẩm</p>
                                                                    <p><strong>Đã nhận:</strong> <?php echo number_format($campaign['current_items']); ?> vật phẩm</p>
                                                                    <p><strong>Trạng thái:</strong> 
                                                                        <span class="badge status-badge bg-<?php echo $st['class']; ?>">
                                                                            <?php echo $st['text']; ?>
                                                                        </span>
                                                                    </p>
                                                                    <p><strong>Số vật phẩm cần:</strong> <?php echo $campaign['items_count']; ?></p>
                                                                    <p><strong>Số quyên góp:</strong> <?php echo $campaign['donations_count']; ?></p>
                                                                </div>
                                                            </div>
                                                            <p><strong>Mô tả:</strong></p>
                                                            <p><?php echo nl2br(htmlspecialchars($campaign['description'] ?? 'Không có mô tả')); ?></p>
                                                            
                                                            <?php if ($campaign['image']): ?>
                                                                <p><strong>Hình ảnh:</strong></p>
                                                                <img src="../uploads/campaigns/<?php echo $campaign['image']; ?>" 
                                                                     class="img-fluid rounded" 
                                                                     alt="Campaign Image">
                                                            <?php endif; ?>

                                                            <!-- Video Display -->
                                                            <?php if ($campaign['video_type'] === 'upload' && $campaign['video_file']): ?>
                                                                <p><strong>Video chiến dịch:</strong></p>
                                                                <video width="100%" controls class="rounded" style="max-height: 400px;">
                                                                    <source src="../uploads/campaigns/videos/<?php echo $campaign['video_file']; ?>" type="video/mp4">
                                                                    Trình duyệt của bạn không hỗ trợ video.
                                                                </video>
                                                            <?php elseif ($campaign['video_type'] === 'youtube' && $campaign['video_youtube']): ?>
                                                                <p><strong>Video YouTube:</strong></p>
                                                                <?php $youtubeEmbedId = extractYoutubeVideoId($campaign['video_youtube']); ?>
                                                                <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;">
                                                                    <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                                                                            src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtubeEmbedId); ?>" 
                                                                            frameborder="0" 
                                                                            allowfullscreen>
                                                                    </iframe>
                                                                </div>
                                                            <?php elseif ($campaign['video_type'] === 'facebook' && $campaign['video_facebook']): ?>
                                                                <p><strong>Facebook Livestream:</strong></p>
                                                                <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;">
                                                                    <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                                                                            src="https://www.facebook.com/plugins/video.php?href=<?php echo urlencode($campaign['video_facebook']); ?>&show_text=false" 
                                                                            frameborder="0" 
                                                                            allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" 
                                                                            allowfullscreen>
                                                                    </iframe>
                                                                </div>
                                                            <?php elseif ($campaign['video_type'] === 'tiktok' && $campaign['video_tiktok']): ?>
                                                                <p><strong>Video TikTok:</strong></p>
                                                                <?php 
                                                                $tiktokUrl = trim((string)$campaign['video_tiktok']);
                                                                $tiktokVideoId = extractTikTokVideoId($tiktokUrl);
                                                                $tiktokLiveUrl = isTikTokLiveUrl($tiktokUrl) ? $tiktokUrl : '';
                                                                ?>
                                                                <?php if ($tiktokVideoId): ?>
                                                                    <div style="position: relative; padding-bottom: 100%; height: 0; overflow: hidden; max-width: 100%;">
                                                                        <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                                                                                src="https://www.tiktok.com/embed/v2/<?php echo $tiktokVideoId; ?>" 
                                                                                frameborder="0" 
                                                                                allow="autoplay; encrypted-media" 
                                                                                allowfullscreen>
                                                                        </iframe>
                                                                    </div>
                                                                <?php elseif ($tiktokLiveUrl): ?>
                                                                    <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;">
                                                                        <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                                                                                src="<?php echo htmlspecialchars($tiktokLiveUrl); ?>" 
                                                                                frameborder="0" 
                                                                                allow="autoplay; encrypted-media" 
                                                                                allowfullscreen>
                                                                        </iframe>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="alert alert-warning" role="alert">
                                                                        <i class="bi bi-exclamation-triangle me-2"></i>Không thể nhúng link TikTok này. <a href="<?php echo htmlspecialchars($tiktokUrl); ?>" target="_blank" rel="noopener">Mở TikTok</a>.
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php elseif ($campaign['video_type'] === 'multi'): ?>
                                                                <!-- Multi-video section -->
                                                                <p><strong>Video chiến dịch:</strong></p>
                                                                <ul class="nav nav-tabs mb-3" role="tablist">
                                                                    <?php if ($campaign['video_file']): ?>
                                                                        <li class="nav-item" role="presentation">
                                                                            <button class="nav-link active" id="video-upload-tab" data-bs-toggle="tab" data-bs-target="#video-upload" type="button" role="tab">
                                                                                <i class="bi bi-upload me-1"></i>Video tải lên
                                                                            </button>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                    <?php if ($campaign['video_youtube']): ?>
                                                                        <li class="nav-item" role="presentation">
                                                                            <button class="nav-link <?php echo !$campaign['video_file'] ? 'active' : ''; ?>" id="video-youtube-tab" data-bs-toggle="tab" data-bs-target="#video-youtube" type="button" role="tab">
                                                                                <i class="bi bi-youtube me-1"></i>YouTube
                                                                            </button>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                    <?php if ($campaign['video_facebook']): ?>
                                                                        <li class="nav-item" role="presentation">
                                                                            <button class="nav-link <?php echo !$campaign['video_file'] && !$campaign['video_youtube'] ? 'active' : ''; ?>" id="video-facebook-tab" data-bs-toggle="tab" data-bs-target="#video-facebook" type="button" role="tab">
                                                                                <i class="bi bi-facebook me-1"></i>Facebook Livestream
                                                                            </button>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                    <?php if ($campaign['video_tiktok']): ?>
                                                                        <li class="nav-item" role="presentation">
                                                                            <button class="nav-link <?php echo !$campaign['video_file'] && !$campaign['video_youtube'] && !$campaign['video_facebook'] ? 'active' : ''; ?>" id="video-tiktok-tab" data-bs-toggle="tab" data-bs-target="#video-tiktok" type="button" role="tab">
                                                                                <i class="bi bi-play-circle me-1"></i>TikTok
                                                                            </button>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                </ul>
                                                                <div class="tab-content">
                                                                    <?php if ($campaign['video_file']): ?>
                                                                        <div class="tab-pane fade show active" id="video-upload">
                                                                            <video width="100%" controls class="rounded" style="max-height: 400px;">
                                                                                <source src="../uploads/campaigns/videos/<?php echo $campaign['video_file']; ?>" type="video/mp4">
                                                                                Trình duyệt của bạn không hỗ trợ video.
                                                                            </video>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <?php if ($campaign['video_youtube']): ?>
                                                                        <?php $youtubeEmbedId = extractYoutubeVideoId($campaign['video_youtube']); ?>
                                                                        <div class="tab-pane fade <?php echo !$campaign['video_file'] ? 'show active' : ''; ?>" id="video-youtube">
                                                                            <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;">
                                                                                <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                                                                                        src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtubeEmbedId); ?>" 
                                                                                        frameborder="0" 
                                                                                        allowfullscreen>
                                                                                </iframe>
                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <?php if ($campaign['video_facebook']): ?>
                                                                        <div class="tab-pane fade <?php echo !$campaign['video_file'] && !$campaign['video_youtube'] ? 'show active' : ''; ?>" id="video-facebook">
                                                                            <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;">
                                                                                <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                                                                                        src="https://www.facebook.com/plugins/video.php?href=<?php echo urlencode($campaign['video_facebook']); ?>&show_text=false" 
                                                                                        frameborder="0" 
                                                                                        allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" 
                                                                                        allowfullscreen>
                                                                                </iframe>
                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <?php if ($campaign['video_tiktok']): ?>
                                                                        <div class="tab-pane fade <?php echo !$campaign['video_file'] && !$campaign['video_youtube'] && !$campaign['video_facebook'] ? 'show active' : ''; ?>" id="video-tiktok">
                                                                            <?php 
                                                                            $tiktokUrl = trim((string)$campaign['video_tiktok']);
                                                                            $tiktokVideoId = extractTikTokVideoId($tiktokUrl);
                                                                            $tiktokLiveUrl = isTikTokLiveUrl($tiktokUrl) ? $tiktokUrl : '';
                                                                            ?>
                                                                            <?php if ($tiktokVideoId): ?>
                                                                                <div style="position: relative; padding-bottom: 100%; height: 0; overflow: hidden; max-width: 100%;">
                                                                                    <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                                                                                            src="https://www.tiktok.com/embed/v2/<?php echo $tiktokVideoId; ?>" 
                                                                                            frameborder="0" 
                                                                                            allow="autoplay; encrypted-media" 
                                                                                            allowfullscreen>
                                                                                    </iframe>
                                                                                </div>
                                                                            <?php elseif ($tiktokLiveUrl): ?>
                                                                                <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%;">
                                                                                    <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                                                                                            src="<?php echo htmlspecialchars($tiktokLiveUrl); ?>" 
                                                                                            frameborder="0" 
                                                                                            allow="autoplay; encrypted-media" 
                                                                                            allowfullscreen>
                                                                                    </iframe>
                                                                                </div>
                                                                            <?php else: ?>
                                                                                <div class="alert alert-warning" role="alert">
                                                                                    <i class="bi bi-exclamation-triangle me-2"></i>Không thể nhúng link TikTok này. <a href="<?php echo htmlspecialchars($tiktokUrl); ?>" target="_blank" rel="noopener">Mở TikTok</a>.
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <?php
                                                            // Get campaign items
                                                            $campaignItems = Database::fetchAll(
                                                                "SELECT ci.*, c.name as category_name 
                                                                 FROM campaign_items ci 
                                                                 LEFT JOIN categories c ON ci.category_id = c.category_id 
                                                                 WHERE ci.campaign_id = ?",
                                                                [$campaign['campaign_id']]
                                                            );
                                                            if (!empty($campaignItems)):
                                                            ?>
                                                                <p><strong>Vật phẩm cần thiết:</strong></p>
                                                                <ul>
                                                                    <?php foreach ($campaignItems as $item): ?>
                                                                        <li>
                                                                            <?php echo htmlspecialchars($item['item_name']); ?> 
                                                                            - <?php echo $item['quantity_needed']; ?> 
                                                                            <?php echo htmlspecialchars($item['unit'] ?? 'cái'); ?>
                                                                            (<?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?>)
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (in_array($campaign['status'], ['draft', 'pending'])): ?>
                                                            <div class="modal-footer">
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn chấp nhận và kích hoạt chiến dịch này?');">
                                                                    <input type="hidden" name="campaign_id" value="<?php echo $campaign['campaign_id']; ?>">
                                                                    <input type="hidden" name="action" value="approve">
                                                                    <button type="submit" class="btn btn-success">
                                                                        <i class="bi bi-check-circle me-1"></i>Chấp nhận chiến dịch
                                                                    </button>
                                                                </form>
                                                                <button type="button"
                                                                        class="btn btn-outline-danger"
                                                                        data-bs-target="#rejectModal<?php echo $campaign['campaign_id']; ?>"
                                                                        data-bs-toggle="modal">
                                                                    <i class="bi bi-x-circle me-1"></i>Từ chối
                                                                </button>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Edit Modal -->
                                            <div class="modal" id="editModal<?php echo $campaign['campaign_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Chỉnh sửa chiến dịch #<?php echo $campaign['campaign_id']; ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['campaign_id']; ?>">
                                                                <input type="hidden" name="action" value="update">
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Tên chiến dịch *</label>
                                                                    <input type="text" 
                                                                           class="form-control" 
                                                                           name="name" 
                                                                           value="<?php echo htmlspecialchars($campaign['name']); ?>" 
                                                                           required>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Mô tả *</label>
                                                                    <textarea class="form-control" 
                                                                              name="description" 
                                                                              rows="4" 
                                                                              required><?php echo htmlspecialchars($campaign['description'] ?? ''); ?></textarea>
                                                                </div>
                                                                
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label">Ngày bắt đầu *</label>
                                                                        <input type="date" 
                                                                               class="form-control" 
                                                                               name="start_date" 
                                                                               value="<?php echo $campaign['start_date']; ?>" 
                                                                               required>
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label">Ngày kết thúc *</label>
                                                                        <input type="date" 
                                                                               class="form-control" 
                                                                               name="end_date" 
                                                                               value="<?php echo $campaign['end_date']; ?>" 
                                                                               required>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Mục tiêu số lượng vật phẩm *</label>
                                                                    <input type="number" 
                                                                           class="form-control" 
                                                                           name="target_items" 
                                                                           value="<?php echo $campaign['target_items']; ?>" 
                                                                           min="1" 
                                                                           required>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <div class="modal-action-group">
                                                                    <button type="button" class="modal-action-btn cancel" data-bs-dismiss="modal" title="Huỷ">
                                                                        <i class="bi bi-x-lg"></i>
                                                                    </button>
                                                                    <button type="submit" class="modal-action-btn save" title="Cập nhật">
                                                                        <i class="bi bi-pencil-square"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Reject Modal -->
                                            <div class="modal" id="rejectModal<?php echo $campaign['campaign_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Từ chối chiến dịch</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="campaign_id" value="<?php echo $campaign['campaign_id']; ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Lý do từ chối:</label>
                                                                    <textarea class="form-control" name="reject_reason" rows="3" required></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <div class="modal-action-group">
                                                                    <button type="button" class="modal-action-btn cancel" data-bs-dismiss="modal" title="Há»§y">
                                                                        <i class="bi bi-x-lg"></i>
                                                                    </button>
                                                                    <button type="submit" class="modal-action-btn reject" title="Tá»« chá»i">
                                                                        <i class="bi bi-x-octagon"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav class="py-3">
                                <ul class="pagination justify-content-center mb-0">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
