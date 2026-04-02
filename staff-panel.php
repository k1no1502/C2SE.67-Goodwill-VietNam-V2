<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireStaffOrAdmin();

if (isStaff() && !isAdmin() && getStaffPanelKey() === 'support') {
    header('Location: index.php');
    exit();
}

$pageTitle = 'Panel công việc';
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRoleId = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : null;
$displayRole = getUserDisplayRole($currentUserId, $currentRoleId);

function normalizeRoleKey($roleName) {
    $name = mb_strtolower(trim((string)$roleName), 'UTF-8');

    $map = [
        'quản lý kho hàng' => 'warehouse',
        'quan ly kho hang' => 'warehouse',
        'quản lý quyên góp' => 'warehouse',
        'quan ly quyen gop' => 'warehouse',
        'quản lý đơn hàng' => 'orders',
        'quan ly don hang' => 'orders',
        'quản lý chiến dịch' => 'campaigns',
        'quan ly chien dich' => 'campaigns',
        'tư vấn chăm sóc khách hàng' => 'support',
        'tu van cham soc khach hang' => 'support'
    ];

    return $map[$name] ?? 'general';
}

$panelKey = normalizeRoleKey($displayRole);
if (isAdmin()) {
    $panelKey = 'admin';
}

$redirectMap = [
    'warehouse' => 'admin/warehouse-panel.php',
    'orders' => 'admin/orders-panel.php',
    'campaigns' => 'admin/campaigns-panel.php',
    'cashier' => 'admin/cashier-panel.php',
    'support' => 'index.php',
    'admin' => 'admin/dashboard.php',
    'general' => 'index.php'
];

header('Location: ' . ($redirectMap[$panelKey] ?? 'admin/dashboard.php'));
exit();

$metrics = [
    'a' => 0,
    'b' => 0,
    'c' => 0
];

$panelConfigs = [
    'warehouse' => [
        'title' => 'Panel quản lý kho hàng',
        'subtitle' => 'Theo dõi tồn kho, xử lý hàng duyệt và điều phối nhập/xuất.',
        'icon' => 'bi-box-seam',
        'badge' => 'Kho vận',
        'links' => [
            ['label' => 'Quản lý kho', 'url' => 'admin/inventory.php', 'desc' => 'Xem tồn kho, trạng thái và điều chỉnh hàng.'],
            ['label' => 'Đơn quyên góp', 'url' => 'admin/donations.php', 'desc' => 'Duyệt và đưa hàng vào kho.'],
            ['label' => 'Vị trí kho', 'url' => 'admin/warehouse-location.php', 'desc' => 'Quản lý khu vực và vị trí lưu trữ.']
        ],
        'statLabels' => ['Tổng số lượng tồn', 'Đơn chờ duyệt', 'Mặt hàng sắp hết']
    ],
    'orders' => [
        'title' => 'Panel quản lý đơn hàng',
        'subtitle' => 'Theo dõi đơn mới, xử lý vận hành và phản hồi trạng thái giao hàng.',
        'icon' => 'bi-bag-check',
        'badge' => 'Vận hành đơn',
        'links' => [
            ['label' => 'Quản lý đơn hàng', 'url' => 'admin/orders.php', 'desc' => 'Xử lý vòng đời đơn hàng theo trạng thái.'],
            ['label' => 'Giỏ hàng', 'url' => 'admin/carts.php', 'desc' => 'Theo dõi giỏ hàng và nhu cầu sản phẩm.'],
            ['label' => 'Thông báo', 'url' => 'admin/notifications.php', 'desc' => 'Gửi thông báo cập nhật cho người dùng.']
        ],
        'statLabels' => ['Đơn mới hôm nay', 'Đơn đang xử lý', 'Đơn đang giao']
    ],
    'campaigns' => [
        'title' => 'Panel quản lý chiến dịch',
        'subtitle' => 'Vận hành chiến dịch, nhiệm vụ và phân công nguồn lực.',
        'icon' => 'bi-megaphone',
        'badge' => 'Chiến dịch',
        'links' => [
            ['label' => 'Danh sách chiến dịch', 'url' => 'admin/campaigns.php', 'desc' => 'Tạo và quản trị chiến dịch theo tiến độ.'],
            ['label' => 'Nhiệm vụ chiến dịch', 'url' => 'admin/campaign-tasks.php', 'desc' => 'Quản lý công việc theo từng chiến dịch.'],
            ['label' => 'Phân công nhân sự', 'url' => 'admin/assignments.php', 'desc' => 'Điều phối tình nguyện viên và nhân sự.']
        ],
        'statLabels' => ['Chiến dịch hoạt động', 'Chiến dịch chờ duyệt', 'Nhiệm vụ đang mở']
    ],
    'support' => [
        'title' => 'Panel tư vấn chăm sóc khách hàng',
        'subtitle' => 'Theo dõi phản hồi, hỗ trợ khách hàng và chăm sóc sau tương tác.',
        'icon' => 'bi-headset',
        'badge' => 'CSKH',
        'links' => [
            ['label' => 'Phản hồi người dùng', 'url' => 'admin/feedback.php', 'desc' => 'Tiếp nhận và xử lý phản hồi khách hàng.'],
            ['label' => 'Thông báo', 'url' => 'admin/notifications.php', 'desc' => 'Gửi thông báo và chăm sóc chủ động.'],
            ['label' => 'Trang phản hồi', 'url' => 'feedback.php', 'desc' => 'Xem luồng phản hồi từ phía người dùng.']
        ],
        'statLabels' => ['Phản hồi chờ xử lý', 'Thông báo chưa đọc', 'Phiên chat đang mở']
    ],
    'admin' => [
        'title' => 'Panel quản trị tổng hợp',
        'subtitle' => 'Quyền quản trị đầy đủ cho toàn bộ nghiệp vụ hệ thống.',
        'icon' => 'bi-speedometer2',
        'badge' => 'Admin',
        'links' => [
            ['label' => 'Dashboard quản trị', 'url' => 'admin/dashboard.php', 'desc' => 'Theo dõi toàn cục và chỉ số quan trọng.'],
            ['label' => 'Người dùng', 'url' => 'admin/users.php', 'desc' => 'Quản lý tài khoản và phân quyền.'],
            ['label' => 'Báo cáo', 'url' => 'admin/reports.php', 'desc' => 'Xem báo cáo vận hành theo thời gian.']
        ],
        'statLabels' => ['Người dùng hoạt động', 'Đơn chờ xử lý', 'Chiến dịch hoạt động']
    ],
    'general' => [
        'title' => 'Panel nhân viên',
        'subtitle' => 'Tài khoản của bạn đã là nhân viên. Chọn khu vực làm việc phù hợp bên dưới.',
        'icon' => 'bi-person-workspace',
        'badge' => 'Nhân viên',
        'links' => [
            ['label' => 'Dashboard nhân sự', 'url' => 'admin/dashboard.php', 'desc' => 'Tổng quan công việc và cảnh báo mới.'],
            ['label' => 'Đơn hàng', 'url' => 'admin/orders.php', 'desc' => 'Theo dõi luồng đơn hàng.'],
            ['label' => 'Chiến dịch', 'url' => 'admin/campaigns.php', 'desc' => 'Quản trị chiến dịch cộng đồng.']
        ],
        'statLabels' => ['Công việc mới', 'Nhiệm vụ đang làm', 'Thông báo hệ thống']
    ]
];

$config = $panelConfigs[$panelKey] ?? $panelConfigs['general'];

try {
    if ($panelKey === 'warehouse') {
        $metrics['a'] = (int)(Database::fetch("SELECT COALESCE(SUM(quantity), 0) AS total FROM inventory WHERE status = 'available'")['total'] ?? 0);
        $metrics['b'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM donations WHERE status = 'pending'")['total'] ?? 0);
        $metrics['c'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM inventory WHERE status = 'available' AND quantity <= 2")['total'] ?? 0);
    } elseif ($panelKey === 'orders') {
        $metrics['a'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM orders WHERE DATE(created_at) = CURDATE()")['total'] ?? 0);
        $metrics['b'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM orders WHERE status IN ('pending', 'confirmed', 'processing')")['total'] ?? 0);
        $metrics['c'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM orders WHERE status = 'shipping'")['total'] ?? 0);
    } elseif ($panelKey === 'campaigns') {
        $metrics['a'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM campaigns WHERE status = 'active'")['total'] ?? 0);
        $metrics['b'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM campaigns WHERE status = 'pending'")['total'] ?? 0);
        $metrics['c'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM campaign_tasks WHERE status = 'open'")['total'] ?? 0);
    } elseif ($panelKey === 'support') {
        $metrics['a'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM feedback WHERE status = 'pending'")['total'] ?? 0);
        $metrics['b'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0")['total'] ?? 0);
        $metrics['c'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM chat_sessions WHERE status = 'open'")['total'] ?? 0);
    } elseif ($panelKey === 'admin') {
        $metrics['a'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM users WHERE status = 'active'")['total'] ?? 0);
        $metrics['b'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM orders WHERE status IN ('pending', 'confirmed', 'processing')")['total'] ?? 0);
        $metrics['c'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM campaigns WHERE status = 'active'")['total'] ?? 0);
    } else {
        $metrics['a'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM notifications WHERE is_read = 0")['total'] ?? 0);
        $metrics['b'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM campaign_tasks WHERE status IN ('open', 'in_progress')")['total'] ?? 0);
        $metrics['c'] = (int)(Database::fetch("SELECT COUNT(*) AS total FROM orders WHERE status IN ('pending', 'processing')")['total'] ?? 0);
    }
} catch (Exception $e) {
    error_log('Staff panel metrics error: ' . $e->getMessage());
}

include 'includes/header.php';
?>
<style>
    .work-panel-wrap {
        font-family: 'Manrope', sans-serif;
        min-height: calc(100vh - 140px);
        padding: 2.2rem 0 3rem;
        background:
            radial-gradient(1200px 500px at 10% -10%, rgba(0, 163, 188, 0.2), transparent 60%),
            radial-gradient(900px 420px at 100% 10%, rgba(45, 156, 219, 0.15), transparent 62%),
            linear-gradient(165deg, #edf8fb 0%, #f6fbfd 45%, #eef5ff 100%);
    }

    .work-hero {
        border: 1px solid #cae9f0;
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(8px);
        box-shadow: 0 22px 48px rgba(7, 74, 91, 0.12);
        padding: 1.3rem 1.4rem;
        margin-bottom: 1.15rem;
    }

    .work-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 999px;
        border: 1px solid #8de0ee;
        padding: 0.35rem 0.72rem;
        color: #067d97;
        background: #e9faff;
        font-weight: 700;
        font-size: 0.82rem;
        margin-bottom: 0.65rem;
    }

    .work-title {
        margin: 0;
        color: #0f2e3d;
        font-weight: 900;
        font-size: clamp(1.45rem, 3.5vw, 2.2rem);
    }

    .work-subtitle {
        margin: 0.4rem 0 0;
        color: #4d6470;
        font-size: 1rem;
    }

    .work-role {
        margin-top: 0.75rem;
        color: #274856;
        font-weight: 700;
    }

    .metric-card {
        border-radius: 18px;
        border: 1px solid #cbe9f1;
        background: #ffffff;
        box-shadow: 0 10px 22px rgba(5, 74, 91, 0.08);
        padding: 1rem;
        height: 100%;
    }

    .metric-number {
        font-size: 2rem;
        line-height: 1;
        color: #0e6a84;
        font-weight: 800;
    }

    .metric-label {
        margin-top: 0.35rem;
        color: #5f7580;
        font-weight: 600;
        font-size: 0.94rem;
    }

    .action-card {
        border-radius: 20px;
        border: 1px solid #cbe9f1;
        background: #fff;
        box-shadow: 0 14px 30px rgba(6, 72, 90, 0.09);
        padding: 1.1rem;
        height: 100%;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .action-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 34px rgba(6, 72, 90, 0.13);
    }

    .action-title {
        margin: 0;
        font-size: 1.1rem;
        color: #0f2e3d;
        font-weight: 800;
    }

    .action-desc {
        margin: 0.45rem 0 0.95rem;
        color: #59707a;
        min-height: 2.8rem;
    }

    .action-btn {
        border-radius: 12px;
        border: 0;
        background: linear-gradient(135deg, #00a8cf, #0d7e99);
        color: #fff;
        text-decoration: none;
        padding: 0.5rem 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        font-weight: 700;
    }

    .action-btn:hover {
        color: #fff;
        filter: brightness(1.03);
    }
</style>

<section class="work-panel-wrap">
    <div class="container">
        <div class="work-hero">
            <span class="work-badge"><i class="bi <?php echo htmlspecialchars($config['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i><?php echo htmlspecialchars($config['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
            <h1 class="work-title"><?php echo htmlspecialchars($config['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="work-subtitle"><?php echo htmlspecialchars($config['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="work-role mb-0">Vai trò hiện tại của bạn: <strong><?php echo htmlspecialchars($displayRole, ENT_QUOTES, 'UTF-8'); ?></strong></p>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-number"><?php echo (int)$metrics['a']; ?></div>
                    <div class="metric-label"><?php echo htmlspecialchars($config['statLabels'][0], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-number"><?php echo (int)$metrics['b']; ?></div>
                    <div class="metric-label"><?php echo htmlspecialchars($config['statLabels'][1], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-number"><?php echo (int)$metrics['c']; ?></div>
                    <div class="metric-label"><?php echo htmlspecialchars($config['statLabels'][2], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach ($config['links'] as $link): ?>
                <div class="col-md-4">
                    <div class="action-card">
                        <h3 class="action-title"><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="action-desc"><?php echo htmlspecialchars($link['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <a class="action-btn" href="<?php echo htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8'); ?>">
                            Mở khu vực <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
