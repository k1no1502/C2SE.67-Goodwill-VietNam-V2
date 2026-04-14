<?php
/**
 * Staff Sidebar — role-specific navigation panel
 * Set $panelType = 'warehouse' | 'orders' | 'campaigns' | 'cashier' before including.
 */
$panelType   = $panelType   ?? 'warehouse';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// ─── Current user ──────────────────────────────────────────────────────────
$sidebarUser = null;
if (!empty($_SESSION['user_id'])) {
    try {
        $sidebarUser = Database::fetch(
            "SELECT user_id, name, avatar FROM users WHERE user_id = ?",
            [$_SESSION['user_id']]
        );
    } catch (Exception $e) { /* ignore */ }
}

// ─── Pending badge count per role ──────────────────────────────────────────
$sidePending = 0;
try {
    if ($panelType === 'warehouse') {
        $r = Database::fetch("SELECT COUNT(*) AS c FROM donations WHERE status = 'pending'");
        $sidePending = (int)($r['c'] ?? 0);
    } elseif ($panelType === 'orders') {
        $r = Database::fetch("SELECT COUNT(*) AS c FROM orders WHERE status = 'pending'");
        $sidePending = (int)($r['c'] ?? 0);
    } elseif ($panelType === 'campaigns') {
        $r = Database::fetch("SELECT COUNT(*) AS c FROM campaigns WHERE status IN ('pending','draft')");
        $sidePending = (int)($r['c'] ?? 0);
    } elseif ($panelType === 'cashier') {
        $r = Database::fetch("SELECT COUNT(*) AS c FROM inventory WHERE status = 'available' AND is_for_sale = 1");
        $sidePending = (int)($r['c'] ?? 0);
    }
} catch (Exception $e) { /* ignore */ }

// ─── Menu config per role ──────────────────────────────────────────────────
$sideMenus = [
    'warehouse' => [
        'title' => 'QUẢN LÝ KHO HÀNG',
        'items' => [
            ['id' => 'warehouse-panel', 'href' => 'warehouse-panel.php',  'icon' => 'bi-speedometer2',       'label' => 'Tổng quan'],
            ['id' => 'inventory',       'href' => 'inventory.php',         'icon' => 'bi-box-seam',           'label' => 'Kho hàng'],
            ['id' => 'donations',       'href' => 'donations.php',         'icon' => 'bi-heart-fill',         'label' => 'Quyên góp', 'badge' => $sidePending],
        ],
    ],
    'orders' => [
        'title' => 'QUẢN LÝ ĐƠN HÀNG',
        'items' => [
            ['id' => 'orders-panel',  'href' => 'orders-panel.php',    'icon' => 'bi-speedometer2',       'label' => 'Tổng quan'],
            ['id' => 'orders',        'href' => 'orders.php',           'icon' => 'bi-cart-check',         'label' => 'Đơn hàng', 'badge' => $sidePending],
        ],
    ],
    'campaigns' => [
        'title' => 'QUẢN LÝ CHIẾN DỊCH',
        'items' => [
            ['id' => 'campaigns-panel',  'href' => 'campaigns-panel.php',  'icon' => 'bi-speedometer2',      'label' => 'Tổng quan'],
            ['id' => 'campaigns',        'href' => 'campaigns.php',         'icon' => 'bi-megaphone-fill',    'label' => 'Chiến dịch', 'badge' => $sidePending],
            ['id' => 'campaign-tasks',   'href' => 'campaign-tasks.php',    'icon' => 'bi-list-task',         'label' => 'Nhiệm vụ'],
            ['id' => 'assignments',      'href' => 'assignments.php',       'icon' => 'bi-person-check-fill', 'label' => 'Phân công'],
        ],
    ],
    'cashier' => [
        'title' => 'THU NGÂN',
        'items' => [
            ['id' => 'cashier-panel',       'href' => 'cashier-panel.php',       'icon' => 'bi-speedometer2', 'label' => 'Kho hàng'],
            ['id' => 'cashier-direct-sale', 'href' => 'cashier-direct-sale.php', 'icon' => 'bi-upc-scan',     'label' => 'Thu ngân'],
        ],
    ],
];

$sCfg = $sideMenus[$panelType] ?? $sideMenus['warehouse'];
?>
<style>
/* ── Staff Sidebar ── same visual style as admin-sidebar ─────────────────── */
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
.sidebar-brand-section { padding-top: .5rem; padding-bottom: .45rem; }
.sidebar-user-profile   { padding: .42rem .65rem !important; margin-bottom: .45rem; }
.sidebar-menu-header    { margin-bottom: .2rem; }
.sidebar-menu-header span { font-size: .72rem; letter-spacing: .08em; }
.sidebar-menu {
    margin: 0; padding: 0; list-style: none;
    display: flex; flex-direction: column; gap: .2rem;
}
.sidebar-item { margin: 0; }
.sidebar-menu-item {
    min-height: 36px !important;
    padding: .42rem .75rem !important;
    border-radius: 10px !important;
    font-size: .85rem !important;
    line-height: 1.25 !important;
}
.sidebar-menu-item i { font-size: 1rem !important; }
.sidebar-menu-item .badge {
    font-size: .68rem; font-weight: 700;
    min-width: 1.2rem; height: 1.2rem;
    display: inline-flex; align-items: center; justify-content: center;
    padding: 0 .35rem; border-radius: 999px;
    background: #ef4444; color: #fff;
    margin-left: auto;
    box-shadow: 0 0 0 2px rgba(255,255,255,.95);
}
.sidebar-footer { margin-top: auto; padding-top: .55rem; padding-bottom: .35rem; }
.btn-logout {
    min-height: 38px !important;
    border-radius: 12px;
    padding: .45rem .75rem !important;
    font-size: .86rem !important;
}
@media (max-height: 920px) {
    .sidebar-user-profile {
        display: flex !important; align-items: center !important;
        gap: .45rem !important; padding: .26rem .56rem !important;
        margin-bottom: .18rem !important;
    }
    .sidebar-user-profile .user-avatar,
    .sidebar-user-profile .avatar-placeholder,
    .sidebar-user-profile .user-avatar img {
        width: 30px !important; height: 30px !important;
        min-width: 30px !important; min-height: 30px !important;
    }
    .sidebar-user-profile .user-name  { font-size: .76rem !important; margin-bottom: 0 !important; line-height: 1.2 !important; }
    .sidebar-user-profile .user-status { display: none !important; }
    .sidebar-brand-section  { padding-top: .32rem !important; padding-bottom: .26rem !important; }
    .sidebar-menu-header    { margin-bottom: .1rem !important; }
    .sidebar-menu-header span { font-size: .66rem !important; letter-spacing: .06em !important; }
    .sidebar-menu           { gap: .12rem !important; }
    .sidebar-menu-item      { min-height: 32px !important; padding: .34rem .66rem !important; font-size: .8rem !important; }
    .sidebar-menu-item i    { font-size: .92rem !important; }
    .sidebar-footer         { padding-top: .34rem !important; padding-bottom: .22rem !important; }
    .btn-logout             { min-height: 34px !important; padding: .32rem .66rem !important; font-size: .78rem !important; }
}
@media (max-width: 991.98px) {
    .admin-sidebar,
    .admin-sidebar .position-sticky { height: auto; max-height: none; }
}
</style>

<nav class="col-md-3 col-lg-2 d-md-block admin-sidebar">
    <div class="position-sticky pt-0">

        <!-- Brand -->
        <div class="sidebar-brand-section">
            <a href="../index.php" class="text-decoration-none">
                <i class="bi bi-shield-heart-fill"></i>
            </a>
        </div>

        <!-- User profile -->
        <div class="sidebar-user-profile">
            <div class="user-avatar">
                <?php if ($sidebarUser && !empty($sidebarUser['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($sidebarUser['avatar']); ?>"
                         alt="<?php echo htmlspecialchars($sidebarUser['name']); ?>">
                <?php else: ?>
                    <div class="avatar-placeholder"><i class="bi bi-person-fill"></i></div>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <p class="user-name">
                    <?php echo htmlspecialchars($sidebarUser['name'] ?? 'Nhân viên'); ?>
                </p>
                <div class="user-status">
                    <span class="status-dot"></span>
                    <span class="status-text">Đang hoạt động</span>
                </div>
            </div>
        </div>

        <!-- Menu label -->
        <div class="sidebar-menu-header">
            <span><?php echo htmlspecialchars($sCfg['title']); ?></span>
        </div>

        <!-- Nav items -->
        <ul class="sidebar-menu">
            <?php foreach ($sCfg['items'] as $item): ?>
            <li class="sidebar-item">
                     <a class="sidebar-menu-item <?php echo $currentPage === $item['id'] ? 'active' : ''; ?>"
                         href="<?php echo '/admin/' . htmlspecialchars($item['href']); ?>">
                    <i class="bi <?php echo $item['icon']; ?>"></i>
                    <span><?php echo htmlspecialchars($item['label']); ?></span>
                    <?php if (!empty($item['badge']) && $item['badge'] > 0): ?>
                        <span class="badge"><?php echo (int)$item['badge']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>

            <?php if ($panelType !== 'cashier'): ?>
                <li class="sidebar-item">
                    <a class="sidebar-menu-item" href="/index.php">
                        <i class="bi bi-house-heart-fill"></i>
                        <span>Về Trang chủ</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <!-- Logout -->
        <div class="sidebar-footer">
            <a href="/logout.php" class="btn-logout">
                <i class="bi bi-box-arrow-right"></i>
                <span>Đăng xuất</span>
            </a>
        </div>

    </div>
</nav>
