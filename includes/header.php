<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/notifications_helper.php';

$pageTitle = $pageTitle ?? "Goodwill Vietnam";

processScheduledAdminNotifications();
$notificationCount = isLoggedIn() ? getUnreadNotificationCount($_SESSION['user_id']) : 0;
$currentPage = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Goodwill Vietnam</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="assets/images/favicons/GWVN.jpg">
    
    <style>
        .gw-navbar {
            backdrop-filter: blur(8px);
            background: rgba(255, 255, 255, 0.96) !important;
            border-bottom: 1px solid #d8ebf1;
            z-index: 3000;
        }
        .gw-navbar,
        .gw-navbar .container,
        .gw-navbar .navbar-collapse,
        .gw-navbar .navbar-nav {
            overflow: visible;
        }
        .gw-navbar .container {
            max-width: 1460px;
        }
        .navbar-brand {
            font-weight: 800;
            font-size: 1.08rem;
            letter-spacing: 0.2px;
            color: #0E7490 !important;
            white-space: nowrap;
        }
        .navbar-brand i {
            color: #0E7490;
        }
        .navbar-nav {
            align-items: center;
            gap: 0.05rem;
        }
        .main-nav .nav-link i {
            margin-right: 0.22rem !important;
        }
        .nav-link {
            font-weight: 700;
            color: #0f172a !important;
            border-radius: 10px;
            padding: 0.48rem 0.52rem !important;
            transition: all 0.2s ease;
            white-space: nowrap;
            font-size: 0.82rem;
        }
        .nav-link:hover {
            color: #0E7490 !important;
            background: #edf8fb;
        }
        .nav-link.active {
            color: #0E7490 !important;
            background: #e2f3f8;
        }
        .nav-icon-link {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 !important;
            border-radius: 999px;
            border: 1px solid #d8ebf1;
            background: #fff;
        }
        .nav-icon-link:hover {
            background: #edf8fb;
            border-color: #bfdde8;
        }
        .gw-auth-btn {
            border-radius: 10px;
            font-weight: 700;
            padding: 0.5rem 0.95rem;
        }
        .gw-auth-btn-primary {
            background: linear-gradient(135deg, #0E7490, #155e75);
            color: #fff;
            border: none;
        }
        .gw-auth-btn-primary:hover {
            filter: brightness(0.94);
            color: #fff;
        }
        .gw-auth-btn-outline {
            border: 1.5px solid #0E7490;
            color: #0E7490;
            background: #fff;
        }
        .gw-auth-btn-outline:hover {
            background: #0E7490;
            color: #fff;
        }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .volunteer-btn {
            background: linear-gradient(135deg, #0E7490, #155e75);
            border: none;
            color: white;
            font-weight: 700;
            transition: all 0.2s ease;
            border-radius: 12px;
            padding: 0.42rem 0.72rem;
            font-size: 0.78rem;
            white-space: nowrap;
        }
        .volunteer-btn:hover {
            filter: brightness(0.94);
            color: #fff;
        }
        .notification-indicator {
            position: absolute;
            top: -2px;
            right: -2px;
            min-width: 18px;
            height: 18px;
            padding: 0 4px;
            background-color: #dc3545;
            color: #fff;
            border-radius: 999px;
            font-size: 11px;
            line-height: 18px;
            text-align: center;
            display: none;
        }
        .notification-indicator.show {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .navbar .dropdown-toggle {
            font-size: 0.9rem;
            padding: 0.48rem 0.52rem !important;
        }
        .user-name {
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
            vertical-align: middle;
        }
        .navbar .badge {
            font-size: 0.72rem;
        }
        .dropdown-toggle::after {
            margin-left: 0.45rem;
        }
        .navbar .dropdown-menu {
            min-width: 255px;
            max-height: min(70vh, 460px);
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 2000;
        }
        .navbar .dropdown-item {
            white-space: normal;
        }
        .user-dropdown {
            position: relative;
        }
        .user-dropdown-toggle {
            display: inline-flex;
            align-items: center;
            gap: 0.32rem;
        }
        .navbar .dropdown-menu.user-dropdown-menu {
            min-width: 280px;
            max-width: 320px;
            max-height: min(72vh, 520px);
            margin-top: 0.8rem;
            padding: 0.55rem 0;
            border: 1px solid #cfe4ec;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 22px 48px rgba(15, 23, 42, 0.16);
            z-index: 4000;
        }
        .user-dropdown-menu .dropdown-divider {
            margin: 0.45rem 0;
        }
        .user-dropdown-header {
            padding: 0.6rem 1.15rem 0.7rem;
        }
        .user-dropdown-role {
            margin: 0;
            color: #475569;
            font-size: 0.98rem;
            line-height: 1.45;
        }
        .user-dropdown-role strong {
            color: #334155;
            font-weight: 800;
        }
        .user-dropdown-menu .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.8rem 1.15rem;
            font-size: 0.98rem;
            color: #1e293b;
            white-space: nowrap;
        }
        .user-dropdown-menu .dropdown-item i {
            width: 1.1rem;
            text-align: center;
            color: #334155;
            flex-shrink: 0;
        }
        .user-dropdown-menu .dropdown-item:hover,
        .user-dropdown-menu .dropdown-item:focus {
            background: #eff8fb;
            color: #0e7490;
        }
        .user-dropdown-menu .dropdown-item:hover i,
        .user-dropdown-menu .dropdown-item:focus i {
            color: #0e7490;
        }
        @media (max-width: 1599.98px) {
            .main-nav .nav-link i {
                display: none;
            }
            .volunteer-btn .txt-full,
            .main-nav .txt-full {
                display: none;
            }
            .volunteer-btn .txt-short,
            .main-nav .txt-short {
                display: inline;
            }
            .user-name {
                max-width: 85px;
            }
        }
        @media (min-width: 1600px) {
            .volunteer-btn .txt-short,
            .main-nav .txt-short {
                display: none;
            }
            .volunteer-btn .txt-full,
            .main-nav .txt-full {
                display: inline;
            }
        }
        @media (max-width: 1399.98px) {
            .navbar-brand {
                font-size: 1rem;
            }
            .nav-link {
                font-size: 0.78rem;
                padding: 0.45rem 0.42rem !important;
            }
            .volunteer-btn {
                font-size: 0.74rem;
                padding: 0.4rem 0.62rem;
            }
            .user-name {
                display: none;
            }
        }
        @media (max-width: 991.98px) {
            .navbar-nav {
                align-items: stretch;
                gap: 0.25rem;
            }
            .navbar .dropdown-menu {
                max-height: calc(100vh - 120px);
            }
            .navbar .dropdown-menu.user-dropdown-menu {
                min-width: 100%;
                max-width: none;
                margin-top: 0.45rem;
            }
            .main-nav .nav-link i {
                display: inline;
            }
            .main-nav .txt-full,
            .volunteer-btn .txt-full,
            .main-nav .txt-short,
            .volunteer-btn .txt-short {
                display: inline;
            }
            .volunteer-btn {
                width: 100%;
                font-size: 0.9rem;
            }
            .nav-icon-link {
                width: 100%;
                justify-content: flex-start;
                border-radius: 10px;
                height: auto;
                padding: 0.5rem 0.7rem !important;
            }
            .nav-icon-link .icon-label {
                display: inline;
                margin-left: 0.5rem;
                font-weight: 600;
            }
        }
        @media (min-width: 992px) {
            .nav-icon-link .icon-label {
                display: none;
            }
        }
    </style>
    <script>
        // Apply saved theme as early as possible to avoid flicker
        (function() {
            try {
                const theme = localStorage.getItem('gwvnTheme');
                if (theme === 'dark' || theme === 'light') {
                    document.documentElement.setAttribute('data-bs-theme', theme);
                    if (document.body) {
                        document.body.classList.toggle('dark-mode', theme === 'dark');
                    } else {
                        window.addEventListener('DOMContentLoaded', () => {
                            document.body.classList.toggle('dark-mode', theme === 'dark');
                        });
                    }
                }
            } catch (e) {
                // Ignore localStorage errors
            }
        })();
    </script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light gw-navbar sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-heart-fill me-2"></i>Goodwill Vietnam
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto main-nav">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>" href="index.php">
                            <i class="bi bi-house"></i><span class="txt-full">Trang chủ</span><span class="txt-short">Trang chủ</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'donate.php' ? 'active' : ''; ?>" href="donate.php">
                            <i class="bi bi-gift"></i><span class="txt-full">Quyên góp</span><span class="txt-short">Quyên góp</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'shop.php' ? 'active' : ''; ?>" href="shop.php">
                            <i class="bi bi-shop"></i><span class="txt-full">Shop Bán Hàng</span><span class="txt-short">Shop</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'campaigns.php' ? 'active' : ''; ?>" href="campaigns.php">
                            <i class="bi bi-megaphone"></i><span class="txt-full">Chiến dịch</span><span class="txt-short">Chiến dịch</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'recruitment.php' ? 'active' : ''; ?>" href="recruitment.php">
                            <i class="bi bi-briefcase"></i><span class="txt-full">Tuyển nhân viên</span><span class="txt-short">Tuyển dụng</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'about.php' ? 'active' : ''; ?>" href="about.php">
                            <i class="bi bi-info-circle"></i><span class="txt-full">Giới thiệu</span><span class="txt-short">Giới thiệu</span>
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <!-- Volunteer Button -->
                        <li class="nav-item me-2">
                            <a href="volunteer.php" class="btn volunteer-btn btn-sm">
                                <i class="bi bi-people-fill me-1"></i><span class="txt-full">Tham gia Tình nguyện</span><span class="txt-short">Tình nguyện</span>
                            </a>
                        </li>
                        
                        <!-- Notifications -->
                        <li class="nav-item me-2">
                            <a href="notifications.php" class="nav-link nav-icon-link position-relative <?php echo $currentPage === 'notifications.php' ? 'active' : ''; ?>">
                                <i class="bi bi-bell"></i>
                                <span class="icon-label">Thông báo</span>
                                <span class="notification-indicator <?php echo $notificationCount > 0 ? 'show' : ''; ?>" id="notification-count">
                                    <?php echo $notificationCount > 99 ? '99+' : $notificationCount; ?>
                                </span>
                            </a>
                        </li>

                        <!-- Cart -->
                        <li class="nav-item me-2">
                            <a href="cart.php" class="nav-link nav-icon-link position-relative <?php echo $currentPage === 'cart.php' ? 'active' : ''; ?>">
                                <i class="bi bi-cart3"></i>
                                <span class="icon-label">Giỏ hàng</span>
                                <span class="badge bg-warning text-dark" id="cart-count">0</span>
                            </a>
                        </li>
                        
                        <!-- User Menu -->
                        <li class="nav-item dropdown user-dropdown">
                            <a class="nav-link dropdown-toggle user-dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i><span class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu" aria-labelledby="navbarDropdown">
                                <li class="user-dropdown-header">
                                    <p class="user-dropdown-role">
                                        <strong>Vai trò:</strong>
                                        <?php 
                                            // Use role_id for more reliable role mapping
                                            $roleId = $_SESSION['role_id'] ?? null;
                                            $roleDisplay = $_SESSION['role'] ?? 'Khách';
                                            
                                            // Map based on role_id (1=admin, 2=user, 3=guest, 4=staff)
                                            if ($roleId === 1) {
                                                $roleDisplay = 'Quản Trị Viên';
                                            } elseif ($roleId === 2) {
                                                $roleDisplay = 'Khách Hàng';
                                            } elseif ($roleId === 3) {
                                                $roleDisplay = 'Khách';
                                            } elseif ($roleId === 4) {
                                                $roleDisplay = 'Nhân Viên';
                                            } else {
                                                // Fallback to original role name if mapping fails
                                                $roleDisplay = htmlspecialchars($roleDisplay, ENT_QUOTES, 'UTF-8');
                                            }
                                            
                                            if ($roleId !== null) {
                                                echo htmlspecialchars($roleDisplay, ENT_QUOTES, 'UTF-8');
                                            } else {
                                                echo htmlspecialchars($roleDisplay, ENT_QUOTES, 'UTF-8');
                                            }
                                        ?>
                                    </p>
                                </li>
                                <li><hr class="dropdown-divider my-2"></li>
                                <li><a class="dropdown-item" href="profile.php">
                                    <i class="bi bi-person me-2"></i>Hồ sơ
                                </a></li>
                                <li><a class="dropdown-item" href="my-donations.php">
                                    <i class="bi bi-gift me-2"></i>Quyên góp của tôi
                                </a></li>
                                <li><a class="dropdown-item" href="my-orders.php">
                                    <i class="bi bi-bag me-2"></i>Đơn hàng của tôi
                                </a></li>
                                                                <li><a class="dropdown-item" href="volunteer.php">
                                <i class="bi bi-people me-2"></i>Tình nguyện viên
                                </a></li>
                                <li><a class="dropdown-item" href="feedback.php">
                                    <i class="bi bi-chat-dots me-2"></i>Phản hồi
                                </a></li>
                                <?php if (isAdmin() || isStaff()): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php">
                                        <i class="bi bi-speedometer2 me-2"></i><?php echo isStaff() ? 'Staff Panel' : 'Admin Panel'; ?>
                                    </a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="change-password.php">
                                    <i class="bi bi-key me-2"></i>Đổi mật khẩu
                                </a></li>
                                <li><a class="dropdown-item" href="logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
                                </a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a href="login.php" class="btn gw-auth-btn gw-auth-btn-outline me-2">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="register.php" class="btn gw-auth-btn gw-auth-btn-primary">
                                <i class="bi bi-person-plus me-1"></i>Đăng ký
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <script>
        <?php if (isLoggedIn()): ?>
        document.addEventListener('DOMContentLoaded', function() {
            fetch('<?php echo isset($baseUrl) ? $baseUrl : ''; ?>api/get-cart-count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const cartCount = document.getElementById('cart-count');
                        if (cartCount) {
                            cartCount.textContent = data.count;
                            if (data.count > 0) {
                                cartCount.classList.add('pulse');
                            }
                        }
                    }
                })
                .catch(error => console.error('Error loading cart count:', error));

            fetch('api/notifications.php?action=count')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const badge = document.getElementById('notification-count');
                        if (badge) {
                            const value = data.count > 99 ? '99+' : data.count;
                            badge.textContent = value;
                            if (data.count > 0) {
                                badge.classList.add('show');
                            } else {
                                badge.classList.remove('show');
                            }
                        }
                    }
                })
                .catch(error => console.error('Error loading notifications count:', error));
        });
<?php endif; ?>
    </script>
    <script src="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>assets/js/data-refresh.js" data-base="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>" data-interval="5000"></script>

