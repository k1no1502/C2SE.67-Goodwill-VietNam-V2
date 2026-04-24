<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Authentication & Authorization
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get all active campaigns for this user
$campaigns = Database::fetchAll(
    "SELECT * FROM campaigns 
     WHERE created_by = ? AND status IN ('pending', 'active', 'paused') 
     ORDER BY created_at DESC", 
    [$user_id]
);

if (empty($campaigns)) {
    header('Location: index.php');
    exit();
}

// Select current campaign
$current_campaign_id = isset($_GET['id']) ? (int)$_GET['id'] : $campaigns[0]['campaign_id'];

// Validate selected campaign belongs to user
$current_campaign = null;
foreach ($campaigns as $c) {
    if ($c['campaign_id'] == $current_campaign_id) {
        $current_campaign = $c;
        break;
    }
}

if (!$current_campaign) {
    $current_campaign = $campaigns[0];
    $current_campaign_id = $current_campaign['campaign_id'];
}

// Handle Volunteer Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['volunteer_id'])) {
    $v_id = (int)$_POST['volunteer_id'];
    $action = $_POST['action'];
    
    // Validate volunteer belongs to this campaign
    $vCheck = Database::fetch("SELECT * FROM campaign_volunteers WHERE id = ? AND campaign_id = ?", [$v_id, $current_campaign_id]);
    if ($vCheck) {
        if ($action === 'approve') {
            Database::execute("UPDATE campaign_volunteers SET status = 'approved' WHERE id = ?", [$v_id]);
            $_SESSION['flash_message'] = "Đã duyệt tình nguyện viên thành công!";
        } elseif ($action === 'reject') {
            Database::execute("UPDATE campaign_volunteers SET status = 'rejected' WHERE id = ?", [$v_id]);
            $_SESSION['flash_message'] = "Đã từ chối tình nguyện viên.";
        }
    }
    header("Location: campaign-panel.php?id=" . $current_campaign_id . "&tab=volunteers");
    exit();
}

// Fetch Campaign Data
$items = Database::fetchAll(
    "SELECT ci.*, c.name as category_name,
            COALESCE(ci.quantity_received, 0) as quantity_received,
            COALESCE(ci.quantity_transferred, 0) as quantity_transferred
     FROM campaign_items ci
     LEFT JOIN categories c ON ci.category_id = c.category_id
     WHERE ci.campaign_id = ?
     ORDER BY ci.item_id",
    [$current_campaign_id]
);

$volunteers = Database::fetchAll(
    "SELECT cv.*, u.name, u.email, u.phone as user_phone, u.avatar 
     FROM campaign_volunteers cv
     LEFT JOIN users u ON cv.user_id = u.user_id
     WHERE cv.campaign_id = ?
     ORDER BY cv.created_at DESC",
    [$current_campaign_id]
);

$donations = Database::fetchAll(
    "SELECT cd.*, d.item_name, d.quantity, d.unit, d.status as donation_status, u.name as donor_name
     FROM campaign_donations cd
     JOIN donations d ON cd.donation_id = d.donation_id
     LEFT JOIN users u ON d.user_id = u.user_id
     WHERE cd.campaign_id = ?
     ORDER BY cd.created_at DESC",
    [$current_campaign_id]
);

// Statistics
$totalItemsNeeded = 0;
$totalItemsReceived = 0;
$totalItemsTransferred = 0;

foreach ($items as $item) {
    $totalItemsNeeded += $item['quantity_needed'];
    $totalItemsReceived += $item['quantity_received'];
    $totalItemsTransferred += $item['quantity_transferred'];
}

$activeVolunteers = count(array_filter($volunteers, fn($v) => $v['status'] === 'approved'));
$pendingVolunteers = count(array_filter($volunteers, fn($v) => $v['status'] === 'pending'));

$targetAmount = (float)($current_campaign['target_amount'] ?? 0);
$currentAmount = (float)($current_campaign['current_amount'] ?? 0);

$activeTab = $_GET['tab'] ?? 'overview';

$pageTitle = "Panel Chiến dịch - " . $current_campaign['name'];
include 'includes/header.php';
?>

<style>
    .campaign-panel {
        background-color: #f8fafc;
        min-height: calc(100vh - 70px);
        padding: 2rem 0;
    }
    .panel-sidebar {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        padding: 1.5rem;
        position: sticky;
        top: 90px;
    }
    .nav-pills-custom .nav-link {
        color: #475569;
        font-weight: 600;
        border-radius: 12px;
        padding: 0.8rem 1.2rem;
        margin-bottom: 0.5rem;
        transition: all 0.2s ease;
    }
    .nav-pills-custom .nav-link:hover {
        background: #f1f5f9;
        color: #0e7490;
    }
    .nav-pills-custom .nav-link.active {
        background: linear-gradient(135deg, #0e7490, #155e75);
        color: #fff;
        box-shadow: 0 4px 10px rgba(14,116,144,0.3);
    }
    .nav-pills-custom .nav-link i {
        width: 24px;
        text-align: center;
        margin-right: 8px;
    }
    .stat-card {
        background: #fff;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        border: 1px solid #e2e8f0;
        transition: transform 0.2s ease;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .stat-icon.primary { background: #e0f2fe; color: #0284c7; }
    .stat-icon.success { background: #dcfce7; color: #16a34a; }
    .stat-icon.warning { background: #fef9c3; color: #ca8a04; }
    .stat-icon.info { background: #ccfbf1; color: #0d9488; }
    
    .table-custom {
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    }
    .table-custom th {
        background: #f8fafc;
        color: #475569;
        font-weight: 700;
        border-bottom: 2px solid #e2e8f0;
        padding: 1rem;
    }
    .table-custom td {
        padding: 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
    .campaign-selector {
        background: #fff;
        border-radius: 12px;
        padding: 0.5rem;
        border: 1px solid #cbd5e1;
        font-weight: 600;
        color: #0f172a;
    }
    .badge-soft-success { background: #dcfce7; color: #16a34a; }
    .badge-soft-warning { background: #fef3c7; color: #d97706; }
    .badge-soft-danger { background: #fee2e2; color: #dc2626; }
    .badge-soft-info { background: #e0f2fe; color: #0284c7; }
</style>

<div class="campaign-panel">
    <div class="container">
        
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?php echo $_SESSION['flash_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-kanban me-2 text-info"></i>Panel Chiến Dịch</h2>
                <p class="text-muted mb-0">Quản lý các chiến dịch thiện nguyện của bạn một cách chuyên nghiệp.</p>
            </div>
            
            <?php if (count($campaigns) > 1): ?>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-muted">Chọn chiến dịch:</span>
                    <form method="GET" action="campaign-panel.php" id="campaignSelectForm">
                        <?php if (isset($_GET['tab'])): ?>
                            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($_GET['tab']); ?>">
                        <?php endif; ?>
                        <select name="id" class="form-select campaign-selector" onchange="document.getElementById('campaignSelectForm').submit()">
                            <?php foreach ($campaigns as $c): ?>
                                <option value="<?php echo $c['campaign_id']; ?>" <?php echo $c['campaign_id'] == $current_campaign_id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['name']); ?> (<?php echo ucfirst($c['status']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="panel-sidebar">
                    <h5 class="fw-bold mb-3 px-2 text-truncate" title="<?php echo htmlspecialchars($current_campaign['name']); ?>">
                        <?php echo htmlspecialchars($current_campaign['name']); ?>
                    </h5>
                    <div class="nav flex-column nav-pills nav-pills-custom" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                        <a class="nav-link <?php echo $activeTab === 'overview' ? 'active' : ''; ?>" 
                           href="?id=<?php echo $current_campaign_id; ?>&tab=overview">
                            <i class="bi bi-grid-1x2"></i> Tổng quan
                        </a>
                        <a class="nav-link <?php echo $activeTab === 'items' ? 'active' : ''; ?>" 
                           href="?id=<?php echo $current_campaign_id; ?>&tab=items">
                            <i class="bi bi-box-seam"></i> Quản lý Vật phẩm
                        </a>
                        <a class="nav-link <?php echo $activeTab === 'volunteers' ? 'active' : ''; ?>" 
                           href="?id=<?php echo $current_campaign_id; ?>&tab=volunteers">
                            <i class="bi bi-people"></i> Tình nguyện viên
                            <?php if ($pendingVolunteers > 0): ?>
                                <span class="badge bg-danger ms-2 rounded-pill"><?php echo $pendingVolunteers; ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-link <?php echo $activeTab === 'donations' ? 'active' : ''; ?>" 
                           href="?id=<?php echo $current_campaign_id; ?>&tab=donations">
                            <i class="bi bi-gift"></i> Giao dịch quyên góp
                        </a>
                    </div>
                    
                    <hr class="my-4">
                    <div class="d-grid gap-2">
                        <a href="campaign-detail.php?id=<?php echo $current_campaign_id; ?>" class="btn btn-outline-info" target="_blank">
                            <i class="bi bi-eye me-2"></i>Xem trang chiến dịch
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                
                <?php if ($activeTab === 'overview'): ?>
                    <!-- OVERVIEW TAB -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-6 col-xl-3">
                            <div class="stat-card d-flex align-items-center gap-3">
                                <div class="stat-icon primary"><i class="bi bi-box2-heart"></i></div>
                                <div>
                                    <div class="text-muted small fw-bold text-uppercase">Vật phẩm đã nhận</div>
                                    <div class="fs-4 fw-bolder text-dark"><?php echo number_format($totalItemsReceived); ?> <span class="fs-6 text-muted fw-normal">/ <?php echo number_format($totalItemsNeeded); ?></span></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="stat-card d-flex align-items-center gap-3">
                                <div class="stat-icon success"><i class="bi bi-shop"></i></div>
                                <div>
                                    <div class="text-muted small fw-bold text-uppercase">Đã chuyển Shop</div>
                                    <div class="fs-4 fw-bolder text-dark"><?php echo number_format($totalItemsTransferred); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="stat-card d-flex align-items-center gap-3">
                                <div class="stat-icon warning"><i class="bi bi-people"></i></div>
                                <div>
                                    <div class="text-muted small fw-bold text-uppercase">Tình nguyện viên</div>
                                    <div class="fs-4 fw-bolder text-dark"><?php echo $activeVolunteers; ?> <span class="fs-6 text-muted fw-normal">duyệt</span></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <div class="stat-card d-flex align-items-center gap-3">
                                <div class="stat-icon info"><i class="bi bi-cash-coin"></i></div>
                                <div>
                                    <div class="text-muted small fw-bold text-uppercase">Tiền quyên góp</div>
                                    <div class="fs-5 fw-bolder text-dark"><?php echo number_format($currentAmount, 0, ',', '.'); ?>đ</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-7">
                            <div class="card border-0 shadow-sm rounded-4 h-100">
                                <div class="card-header bg-white border-0 pt-4 pb-0">
                                    <h5 class="fw-bold"><i class="bi bi-activity me-2"></i>Hoạt động gần đây</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($donations) && empty($volunteers)): ?>
                                        <div class="text-center text-muted py-4">Chưa có hoạt động nào.</div>
                                    <?php else: ?>
                                        <div class="timeline">
                                            <?php 
                                            // Combine recent donations and volunteers
                                            $recent = [];
                                            foreach (array_slice($donations, 0, 3) as $d) {
                                                $recent[] = ['type' => 'donation', 'time' => strtotime($d['created_at']), 'data' => $d];
                                            }
                                            foreach (array_slice($volunteers, 0, 3) as $v) {
                                                $recent[] = ['type' => 'volunteer', 'time' => strtotime($v['created_at']), 'data' => $v];
                                            }
                                            usort($recent, fn($a, $b) => $b['time'] - $a['time']);
                                            
                                            foreach (array_slice($recent, 0, 5) as $item): 
                                            ?>
                                                <div class="d-flex gap-3 mb-3 pb-3 border-bottom">
                                                    <?php if ($item['type'] === 'donation'): ?>
                                                        <div class="bg-light-info text-info rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px; background:#e0f2fe;">
                                                            <i class="bi bi-gift-fill"></i>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($item['data']['donor_name'] ?? 'Khách ẩn danh'); ?></div>
                                                            <div class="text-muted small">Đã quyên góp <?php echo $item['data']['quantity']; ?> <?php echo htmlspecialchars($item['data']['item_name']); ?></div>
                                                            <div class="text-muted" style="font-size: 0.75rem;"><?php echo date('d/m/Y H:i', $item['time']); ?></div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="bg-light-warning text-warning rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px; background:#fef3c7;">
                                                            <i class="bi bi-person-plus-fill"></i>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($item['data']['name'] ?? 'Người dùng'); ?></div>
                                                            <div class="text-muted small">Đã đăng ký làm tình nguyện viên</div>
                                                            <div class="text-muted" style="font-size: 0.75rem;"><?php echo date('d/m/Y H:i', $item['time']); ?></div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-5">
                            <div class="card border-0 shadow-sm rounded-4 h-100" style="background: linear-gradient(135deg, #0e7490, #155e75); color: #fff;">
                                <div class="card-body p-4 text-center d-flex flex-column justify-content-center">
                                    <i class="bi bi-rocket-takeoff display-1 mb-3 opacity-75"></i>
                                    <h4 class="fw-bold mb-3">Chuyển đồ dư sang Shop</h4>
                                    <p class="mb-4 opacity-75">Chia sẻ những vật phẩm đã nhận vượt mức yêu cầu cho cộng đồng thông qua Shop Miễn phí.</p>
                                    <a href="?id=<?php echo $current_campaign_id; ?>&tab=items" class="btn btn-light fw-bold text-info-emphasis rounded-pill px-4 py-2">
                                        Quản lý vật phẩm ngay
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($activeTab === 'items'): ?>
                    <!-- ITEMS TAB -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="fw-bold">Quản lý Vật phẩm</h4>
                        <button class="btn btn-success rounded-pill fw-bold" onclick="openTransferModal()">
                            <i class="bi bi-box-arrow-right me-2"></i>Chuyển vào Shop
                        </button>
                    </div>
                    
                    <div class="table-custom">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Tên vật phẩm</th>
                                    <th class="text-center">Cần thiết</th>
                                    <th class="text-center">Đã nhận</th>
                                    <th class="text-center text-primary">Đã chuyển Shop</th>
                                    <th class="text-center text-success">Còn dư</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                    <tr><td colspan="6" class="text-center py-4">Chưa có danh sách vật phẩm.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($items as $item): 
                                        $received = (int)$item['quantity_received'];
                                        $needed = (int)$item['quantity_needed'];
                                        $transferred = (int)$item['quantity_transferred'];
                                        $leftover = max(0, $received - $needed - $transferred);
                                        
                                        $progress = $needed > 0 ? min(100, round(($received / $needed) * 100)) : 100;
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($item['category_name'] ?? ''); ?></div>
                                            </td>
                                            <td class="text-center fw-bold"><?php echo $needed; ?></td>
                                            <td class="text-center fw-bold text-info"><?php echo $received; ?></td>
                                            <td class="text-center fw-bold text-primary"><?php echo $transferred; ?></td>
                                            <td class="text-center fw-bold <?php echo $leftover > 0 ? 'text-success' : 'text-muted'; ?>">
                                                <?php echo $leftover > 0 ? "+".$leftover : "0"; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 6px;">
                                                        <div class="progress-bar <?php echo $progress >= 100 ? 'bg-success' : 'bg-info'; ?>" style="width: <?php echo $progress; ?>%"></div>
                                                    </div>
                                                    <span class="small fw-bold <?php echo $progress >= 100 ? 'text-success' : 'text-muted'; ?>"><?php echo $progress; ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($activeTab === 'volunteers'): ?>
                    <!-- VOLUNTEERS TAB -->
                    <h4 class="fw-bold mb-4">Quản lý Tình nguyện viên</h4>
                    
                    <div class="table-custom">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Thông tin</th>
                                    <th>Liên hệ</th>
                                    <th>Đăng ký lúc</th>
                                    <th>Trạng thái</th>
                                    <th class="text-end">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($volunteers)): ?>
                                    <tr><td colspan="5" class="text-center py-4">Chưa có người đăng ký.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($volunteers as $v): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <?php if (!empty($v['avatar'])): ?>
                                                        <img src="uploads/avatars/<?php echo htmlspecialchars($v['avatar']); ?>" class="rounded-circle" width="40" height="40" style="object-fit:cover;">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-secondary d-flex justify-content-center align-items-center text-white" style="width: 40px; height: 40px;">
                                                            <i class="bi bi-person"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($v['name'] ?? $v['full_name']); ?></div>
                                                        <div class="small text-muted">Role: <?php echo htmlspecialchars($v['role'] ?? 'Chung'); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small"><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($v['email']); ?></div>
                                                <div class="small"><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($v['phone'] ?? $v['user_phone']); ?></div>
                                            </td>
                                            <td><div class="small text-muted"><?php echo date('d/m/Y H:i', strtotime($v['created_at'])); ?></div></td>
                                            <td>
                                                <?php 
                                                    if ($v['status'] === 'approved') echo '<span class="badge badge-soft-success">Đã duyệt</span>';
                                                    elseif ($v['status'] === 'rejected') echo '<span class="badge badge-soft-danger">Đã từ chối</span>';
                                                    else echo '<span class="badge badge-soft-warning">Chờ duyệt</span>';
                                                ?>
                                            </td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-light border btn-view-msg" data-msg="<?php echo htmlspecialchars($v['message']); ?>" title="Xem lời nhắn">
                                                    <i class="bi bi-chat-text"></i>
                                                </button>
                                                <?php if ($v['status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="volunteer_id" value="<?php echo $v['id']; ?>">
                                                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" title="Duyệt"><i class="bi bi-check-lg"></i></button>
                                                        <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger" title="Từ chối" onclick="return confirm('Bạn chắc chắn muốn từ chối?')"><i class="bi bi-x-lg"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($activeTab === 'donations'): ?>
                    <!-- DONATIONS TAB -->
                    <h4 class="fw-bold mb-4">Giao dịch quyên góp</h4>
                    
                    <div class="table-custom">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Người tặng</th>
                                    <th>Vật phẩm</th>
                                    <th class="text-center">Số lượng</th>
                                    <th>Thời gian</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($donations)): ?>
                                    <tr><td colspan="5" class="text-center py-4">Chưa có giao dịch quyên góp nào.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($donations as $d): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($d['donor_name'] ?? 'Khách ẩn danh'); ?></div>
                                            </td>
                                            <td>
                                                <div class="text-dark"><?php echo htmlspecialchars($d['item_name']); ?></div>
                                            </td>
                                            <td class="text-center fw-bold">
                                                <?php echo $d['quantity']; ?> <span class="fw-normal small text-muted"><?php echo htmlspecialchars($d['unit']); ?></span>
                                            </td>
                                            <td>
                                                <div class="small text-muted"><?php echo date('d/m/Y H:i', strtotime($d['created_at'])); ?></div>
                                            </td>
                                            <td>
                                                <?php 
                                                    if ($d['donation_status'] === 'approved') echo '<span class="badge badge-soft-success">Đã duyệt</span>';
                                                    elseif ($d['donation_status'] === 'pending') echo '<span class="badge badge-soft-warning">Chờ xử lý</span>';
                                                    else echo '<span class="badge badge-soft-secondary">'.$d['donation_status'].'</span>';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<!-- Volunteer Message Modal -->
<div class="modal fade" id="msgModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Lời nhắn Tình nguyện viên</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="p-3 bg-light rounded-3" id="msgContent" style="white-space: pre-wrap; font-size: 0.95rem;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Transfer Modal is similar to campaign-detail, we can reuse logic here -->
<div class="modal fade" id="transferModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%); border-bottom: 1px solid #99f6e4;">
                <h5 class="modal-title text-teal-800 fw-bold"><i class="bi bi-box-arrow-right me-2"></i>Chuyển vật phẩm vào Shop</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-4">Các vật phẩm này sẽ được đưa lên Shop dưới dạng "Miễn phí" cho cộng đồng.</p>
                
                <form id="transferForm">
                    <input type="hidden" name="campaign_id" value="<?php echo $current_campaign_id; ?>">
                    
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Vật phẩm</th>
                                    <th class="text-center">Cần</th>
                                    <th class="text-center">Đã nhận</th>
                                    <th class="text-center text-success">Còn dư</th>
                                    <th class="text-center" style="width: 150px;">SL chuyển</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $hasLeftover = false;
                                foreach ($items as $item): 
                                    $received = (int)($item['quantity_received']);
                                    $needed = (int)($item['quantity_needed']);
                                    $transferred = (int)($item['quantity_transferred']);
                                    $leftover = max(0, $received - $needed - $transferred);
                                    
                                    if ($leftover > 0):
                                        $hasLeftover = true;
                                ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                        </td>
                                        <td class="text-center"><?php echo $needed; ?></td>
                                        <td class="text-center"><?php echo $received; ?></td>
                                        <td class="text-center fw-bold text-success">
                                            <?php echo $leftover; ?> <?php echo htmlspecialchars($item['unit'] ?? ''); ?>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control text-center transfer-qty" 
                                                       name="transfer_items[<?php echo $item['item_id']; ?>]" 
                                                       value="0" min="0" max="<?php echo $leftover; ?>">
                                            </div>
                                        </td>
                                    </tr>
                                <?php 
                                    endif;
                                endforeach; 
                                
                                if (!$hasLeftover):
                                ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            Hiện tại không có vật phẩm nào còn dư để chuyển.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light border-top-0 rounded-bottom-3">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-success fw-bold" id="btnSubmitTransfer" <?php echo !$hasLeftover ? 'disabled' : ''; ?>>
                    <i class="bi bi-check2-circle me-1"></i>Xác nhận chuyển
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Volunteer Msg
    document.querySelectorAll('.btn-view-msg').forEach(btn => {
        btn.addEventListener('click', function() {
            const msg = this.dataset.msg;
            document.getElementById('msgContent').textContent = msg || 'Không có lời nhắn.';
            const modal = new bootstrap.Modal(document.getElementById('msgModal'));
            modal.show();
        });
    });

    // Transfer Modal
    function openTransferModal() {
        const modalEl = document.getElementById('transferModal');
        if (!modalEl || typeof bootstrap === 'undefined') return;
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    document.getElementById('btnSubmitTransfer')?.addEventListener('click', function() {
        const form = document.getElementById('transferForm');
        const formData = new FormData(form);
        let hasItems = false;
        
        form.querySelectorAll('.transfer-qty').forEach(input => {
            if (parseInt(input.value) > 0) hasItems = true;
        });

        if (!hasItems) {
            alert('Vui lòng chọn số lượng ít nhất 1 vật phẩm để chuyển.');
            return;
        }

        if (!confirm('Bạn chắc chắn muốn chuyển các vật phẩm này vào Shop dưới dạng Miễn phí chứ? Hành động này không thể hoàn tác.')) {
            return;
        }

        const btn = this;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...';

        fetch('api/transfer-campaign-items.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Chuyển vật phẩm thành công! Các vật phẩm đã có mặt trên Shop.');
                location.reload();
            } else {
                alert(data.message || 'Có lỗi xảy ra!');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(err => {
            console.error(err);
            alert('Lỗi kết nối!');
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
