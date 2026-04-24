<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Get current user
$currentUser = NULL;
if (!empty($_SESSION['user_id'])) {
    try {
        $currentUser = Database::fetch("SELECT user_id, name, email, avatar, role FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
    } catch (Exception $e) {
        $currentUser = NULL;
    }
}

// Get pending counts with error handling
$pendingDonations = 0;
$pendingFeedback = 0;
$pendingCampaigns = 0;
$pendingRecruitment = 0;
$pendingOrders = 0;
$pendingNotifications = 0;

try {
    $result = Database::fetch("SELECT COUNT(*) as count FROM donations WHERE status = 'pending'");
    $pendingDonations = $result ? ($result['count'] ?? 0) : 0;
} catch (Exception $e) {
    $pendingDonations = 0;
}

try {
    $result = Database::fetch("SELECT COUNT(*) as count FROM feedback WHERE status = 'pending'");
    $pendingFeedback = $result ? ($result['count'] ?? 0) : 0;
} catch (Exception $e) {
    $pendingFeedback = 0;
}

try {
    $result = Database::fetch("SELECT COUNT(*) as count FROM campaigns WHERE status = 'pending' OR status = 'draft'");
    $pendingCampaigns = $result ? ($result['count'] ?? 0) : 0;
} catch (Exception $e) {
    $pendingCampaigns = 0;
}

try {
    $result = Database::fetch("SELECT COUNT(*) as count FROM recruitment_applications WHERE status = 'pending'");
    $pendingRecruitment = $result ? ($result['count'] ?? 0) : 0;
} catch (Exception $e) {
    $pendingRecruitment = 0;
}

try {
    $result = Database::fetch("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
    $pendingOrders = $result ? ($result['count'] ?? 0) : 0;
} catch (Exception $e) {
    $pendingOrders = 0;
}

try {
    $result = Database::fetch("SELECT COUNT(*) as count FROM admin_notifications WHERE status = 'scheduled'");
    $pendingNotifications = $result ? ($result['count'] ?? 0) : 0;
} catch (Exception $e) {
    $pendingNotifications = 0;
}
?>
<style>
/* Keep the full admin menu visible without scrolling inside sidebar */
.admin-sidebar,
.admin-sidebar .position-sticky {
    height: 100vh !important;
    max-height: 100vh !important;
    overflow-y: visible !important;
    overflow-x: hidden !important;
}

.admin-sidebar .position-sticky {
    display: flex;
    flex-direction: column;
    padding-top: 0 !important;
}

.sidebar-brand-section {
    padding-top: 0.5rem;
    padding-bottom: 0.45rem;
}

.sidebar-user-profile {
    padding: 0.42rem 0.65rem !important;
    margin-bottom: 0.45rem;
}

.sidebar-menu-header {
    margin-bottom: 0.2rem;
}

.sidebar-menu-header span {
    font-size: 0.72rem;
    letter-spacing: 0.08em;
}

.sidebar-menu {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.sidebar-item {
    margin: 0;
}

.sidebar-menu-item {
    min-height: 36px !important;
    padding: 0.42rem 0.75rem !important;
    border-radius: 10px !important;
    font-size: 0.85rem !important;
    line-height: 1.25 !important;
}

.sidebar-menu-item i {
    font-size: 1rem !important;
}

.sidebar-menu-item .badge {
    font-size: 0.68rem;
    font-weight: 700;
    min-width: 1.2rem;
    height: 1.2rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 0.35rem;
    border-radius: 999px;
    background: #ef4444;
    color: #fff;
    margin-left: auto;
    box-shadow: 0 0 0 2px rgba(255, 255, 255, .95);
}

.sidebar-footer {
    margin-top: auto;
    padding-top: 0.55rem;
    padding-bottom: 0.35rem;
}

.btn-logout {
    min-height: 38px !important;
    border-radius: 12px;
    padding: 0.45rem 0.75rem !important;
    font-size: 0.86rem !important;
}

@media (max-height: 920px) {
    .sidebar-user-profile {
        display: flex !important;
        align-items: center !important;
        gap: 0.45rem !important;
        padding: 0.26rem 0.56rem !important;
        margin-bottom: 0.18rem !important;
    }

    .sidebar-user-profile .user-avatar,
    .sidebar-user-profile .avatar-placeholder,
    .sidebar-user-profile .user-avatar img {
        width: 30px !important;
        height: 30px !important;
        min-width: 30px !important;
        min-height: 30px !important;
    }

    .sidebar-user-profile .user-name {
        font-size: 0.76rem !important;
        margin-bottom: 0 !important;
        line-height: 1.2 !important;
    }

    .sidebar-user-profile .user-status {
        display: none !important;
    }

    .sidebar-brand-section {
        padding-top: 0.32rem !important;
        padding-bottom: 0.26rem !important;
    }

    .sidebar-menu-header {
        margin-bottom: 0.1rem !important;
    }

    .sidebar-menu-header span {
        font-size: 0.66rem !important;
        letter-spacing: 0.06em !important;
    }

    .sidebar-menu {
        gap: 0.12rem !important;
    }

    .sidebar-menu-item {
        min-height: 32px !important;
        padding: 0.34rem 0.66rem !important;
        font-size: 0.8rem !important;
    }

    .sidebar-menu-item i {
        font-size: 0.92rem !important;
    }

    .sidebar-footer {
        padding-top: 0.34rem !important;
        padding-bottom: 0.22rem !important;
    }

    .btn-logout {
        min-height: 34px !important;
        padding: 0.32rem 0.66rem !important;
        font-size: 0.78rem !important;
    }
}

@media (max-width: 991.98px) {
    .admin-sidebar,
    .admin-sidebar .position-sticky {
        height: auto;
        max-height: none;
    }
}
</style>
<nav class="col-md-3 col-lg-2 d-md-block admin-sidebar">
    <div class="position-sticky pt-0">
        <!-- Brand Header -->
        <div class="sidebar-brand-section">
            <a href="../index.php" class="text-decoration-none">
                <i class="bi bi-shield-heart-fill"></i>
            </a>
        </div>

        <!-- User Profile Section -->
        <div class="sidebar-user-profile">
            <div class="user-avatar">
                <?php if ($currentUser && !empty($currentUser['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($currentUser['avatar']); ?>" alt="<?php echo htmlspecialchars($currentUser['name']); ?>">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <i class="bi bi-person-fill"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <p class="user-name"><?php echo $currentUser ? htmlspecialchars($currentUser['name']) : 'Admin'; ?></p>
                <div class="user-status">
                    <span class="status-dot"></span>
                    <span class="status-text">Đang hoạt động</span>
                </div>
            </div>
        </div>

        <!-- Menu Section -->
        <div class="sidebar-menu-header">
            <span>MENU QUẢN TRỊ</span>
        </div>
        
        <!-- Main Menu -->
        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a class="sidebar-menu-item <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="/admin/dashboard.php">
                    <i class="bi bi-speedometer2"></i>
                    <span>Tổng quan</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a class="sidebar-menu-item <?php echo $currentPage === 'donations' ? 'active' : ''; ?>" href="/admin/donations.php">
                    <i class="bi bi-heart-fill"></i>
                    <span>Quản lý quyên góp</span>
                    <?php if ($pendingDonations > 0): ?>
                        <span class="badge"><?php echo $pendingDonations; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="sidebar-item">
                <a class="sidebar-menu-item <?php echo $currentPage === 'inventory' ? 'active' : ''; ?>" href="/admin/inventory.php">
                    <i class="bi bi-box-seam"></i>
                    <span>Quản lý Kho Hàng</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a class="sidebar-menu-item <?php echo $currentPage === 'orders' ? 'active' : ''; ?>" href="/admin/orders.php">
                    <i class="bi bi-cart-check"></i>
                    <span>Quản lý đơn hàng</span>
                    <?php if ($pendingOrders > 0): ?>
                        <span class="badge"><?php echo $pendingOrders; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="sidebar-item">
                <a class="sidebar-menu-item <?php echo $currentPage === 'campaigns' ? 'active' : ''; ?>" href="/admin/campaigns.php">
                    <i class="bi bi-box2"></i>
                    <span>Quản lý chiến dịch</span>
                    <?php if ($pendingCampaigns > 0): ?>
                        <span class="badge"><?php echo $pendingCampaigns; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="sidebar-item">
                <a class="sidebar-menu-item <?php echo $currentPage === 'users' ? 'active' : ''; ?>" href="/admin/users.php">
                    <i class="bi bi-people-fill"></i>
                    <span>Quản lý người dùng</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a class="sidebar-menu-item <?php echo $currentPage === 'staff-management' ? 'active' : ''; ?>" href="/admin/staff-management.php">
                    <i class="bi bi-person-workspace"></i>
                    <span>Quản lý nhân viên</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a class="sidebar-menu-item <?php echo $currentPage === 'feedback' ? 'active' : ''; ?>" href="/admin/feedback.php">
                    <i class="bi bi-building"></i>
                    <span>Quản lý phản hồi</span>
                    <?php if ($pendingFeedback > 0): ?>
                        <span class="badge"><?php echo $pendingFeedback; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="sidebar-item">
                <a class="sidebar-menu-item <?php echo $currentPage === 'recruitment-applications' ? 'active' : ''; ?>" href="/admin/recruitment-applications.php">
                    <i class="bi bi-lightning-charge-fill"></i>
                    <span>Quản lý Tuyển dụng</span>
                    <?php if ($pendingRecruitment > 0): ?>
                        <span class="badge"><?php echo $pendingRecruitment; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="sidebar-item">
                <a class="sidebar-menu-item <?php echo $currentPage === 'notifications' ? 'active' : ''; ?>" href="/admin/notifications.php">
                    <i class="bi bi-gear-fill"></i>
                    <span>Quản lý thông báo</span>
                    <?php if ($pendingNotifications > 0): ?>
                        <span class="badge"><?php echo $pendingNotifications; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="sidebar-item">
                <a class="sidebar-menu-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>" href="/admin/settings.php">
                    <i class="bi bi-sliders"></i>
                    <span>Cài đặt hệ thống</span>
                </a>
            </li>

            <li class="sidebar-item">
                <a class="sidebar-menu-item <?php echo in_array($currentPage, ['reports', 'reports_export', 'export_reports', 'dashboard_export'], true) ? 'active' : ''; ?>" href="/admin/reports.php">
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Báo cáo</span>
                </a>
            </li>

            <li class="sidebar-item sidebar-item-home">
                <a class="sidebar-menu-item" href="/index.php">
                    <i class="bi bi-house-heart-fill"></i>
                    <span>Về Trang chủ</span>
                </a>
            </li>
        </ul>

        <!-- Logout Button -->
        <div class="sidebar-footer">
            <a href="/logout.php" class="btn-logout">
                <i class="bi bi-box-arrow-right"></i>
                <span>Đăng xuất</span>
            </a>
        </div>
    </div>
</nav>





