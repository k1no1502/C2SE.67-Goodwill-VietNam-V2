<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Kiểm tra xem người dùng đã đăng nhập chưa
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : 'guest';

// Lấy thống kê
try {
    $stats = getStatistics();
    // Debug: Check if stats is loaded correctly
    error_log("Stats loaded: " . json_encode($stats));
} catch (Exception $e) {
    error_log("Error getting statistics: " . $e->getMessage());
    error_log("Exception trace: " . $e->getTraceAsString());
    $stats = [
        'users' => 0,
        'donations' => 0,
        'items' => 0,
        'campaigns' => 0
    ];
}

$pageTitle = "Trang chủ";
include 'includes/header.php';
?>

<style>
/* ===== HOME PAGE ===== */
.hp-hero {
    position: relative;
    min-height: 92vh;
    display: flex;
    align-items: center;
    background: linear-gradient(135deg, rgba(14,116,144,0.72) 0%, rgba(21,94,117,0.80) 100%),
                url('https://special.vietnamplus.vn/wp-content/uploads/2021/03/vnplaodong-1525760471-98.jpg')
                center/cover no-repeat;
    color: #fff;
    overflow: hidden;
}
.hp-hero::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 80px;
    background: linear-gradient(to bottom, transparent, #f6fbfd);
    pointer-events: none;
}
.hp-hero .badge-pill {
    background: rgba(255,255,255,0.18);
    border: 1px solid rgba(255,255,255,0.35);
    color: #fff;
    font-size: .82rem;
    font-weight: 600;
    padding: .4rem .9rem;
    border-radius: 999px;
    backdrop-filter: blur(4px);
}
.hp-hero h1 {
    font-size: clamp(2.4rem, 6vw, 4rem);
    font-weight: 900;
    line-height: 1.12;
    letter-spacing: -.5px;
}
.hp-hero .lead { font-size: 1.15rem; opacity: .9; max-width: 520px; }
.btn-hero-primary {
    background: #fff;
    color: #0e7490;
    font-weight: 700;
    border-radius: 999px;
    padding: .75rem 2rem;
    border: none;
    box-shadow: 0 4px 18px rgba(0,0,0,0.15);
    transition: all .2s;
}
.btn-hero-primary:hover { background: #f0f9fb; color: #0e7490; transform: translateY(-2px); }
.btn-hero-outline {
    background: transparent;
    color: #fff;
    font-weight: 700;
    border-radius: 999px;
    padding: .75rem 2rem;
    border: 2px solid rgba(255,255,255,0.7);
    transition: all .2s;
}
.btn-hero-outline:hover { background: rgba(255,255,255,0.15); color: #fff; transform: translateY(-2px); }

/* Stats strip */
.hp-stats {
    background: #fff;
    box-shadow: 0 8px 32px rgba(14,116,144,0.10);
    border-radius: 20px;
    margin-top: -48px;
    position: relative;
    z-index: 10;
}
.hp-stats .stat-item { padding: 1.6rem 1rem; }
.hp-stats .stat-num {
    font-size: 2rem;
    font-weight: 800;
    color: #0e7490;
    line-height: 1;
}
.hp-stats .stat-label { font-size: .82rem; color: #64748b; margin-top: .3rem; }
.hp-stats .stat-divider {
    width: 1px; background: #e0f0f5;
    align-self: stretch; margin: 1rem 0;
}

/* Features */
.hp-features { background: #f6fbfd; }
.feature-card {
    border: 1px solid #cde8f0;
    border-radius: 18px;
    padding: 2rem 1.6rem;
    background: #fff;
    transition: box-shadow .25s, transform .25s;
}
.feature-card:hover { box-shadow: 0 12px 32px rgba(14,116,144,0.13); transform: translateY(-4px); }
.feature-icon {
    width: 62px; height: 62px;
    border-radius: 16px;
    background: linear-gradient(135deg, #e0f5fa, #b9dde8);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.7rem;
    color: #0e7490;
    margin-bottom: 1.2rem;
}

/* How it works */
.step-circle {
    width: 48px; height: 48px; border-radius: 50%;
    background: linear-gradient(135deg, #0e7490, #155e75);
    color: #fff; font-weight: 800; font-size: 1.1rem;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(14,116,144,0.25);
}
.step-line {
    width: 2px; background: linear-gradient(to bottom, #0e7490, #b9dde8);
    flex-grow: 1; min-height: 32px;
    margin: 4px auto;
}

/* Donations */
.donation-item-card {
    border: 1px solid #cde8f0;
    border-radius: 14px;
    background: #fff;
    transition: box-shadow .2s;
}
.donation-item-card:hover { box-shadow: 0 8px 24px rgba(14,116,144,0.12); }

/* CTA banner */
.hp-cta {
    background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
    border-radius: 24px;
    color: #fff;
    padding: 3.5rem 2rem;
}
.hp-cta h2 { font-size: 2rem; font-weight: 800; }

/* Section title */
.hp-section-title { font-size: 1.75rem; font-weight: 800; color: #0d1f27; }
.hp-section-sub  { color: #64748b; font-size: 1rem; }
.title-pip {
    display: inline-block;
    width: 40px; height: 4px;
    background: linear-gradient(90deg, #0e7490, #155e75);
    border-radius: 2px;
    margin-bottom: 1rem;
}
</style>

<!-- ===== HERO ===== -->
<section class="hp-hero">
    <div class="container py-5">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <span class="badge-pill"><i class="bi bi-heart-fill me-1"></i>Thiện nguyện</span>
                    <span class="badge-pill"><i class="bi bi-people-fill me-1"></i>Cộng đồng</span>
                    <span class="badge-pill"><i class="bi bi-shield-check me-1"></i>Minh bạch</span>
                </div>
                <h1 class="mb-4">Chung tay<br>vì cộng đồng</h1>
                <p class="lead mb-5">Hệ thống thiện nguyện kết nối những tấm lòng nhân ái, tạo nên những điều kỳ diệu cho cộng đồng.</p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="donate.php" class="btn btn-hero-primary">
                        <i class="bi bi-heart-fill me-2"></i>Quyên góp ngay
                    </a>
                    <a href="campaigns.php" class="btn btn-hero-outline">
                        <i class="bi bi-megaphone me-2"></i>Xem chiến dịch
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== STATS STRIP ===== -->
<div class="container" style="position:relative; z-index:10;">
    <div class="hp-stats d-flex flex-wrap justify-content-around text-center">
        <div class="stat-item">
            <div class="stat-num"><?php echo number_format($stats['users'] ?? 0); ?>+</div>
            <div class="stat-label"><i class="bi bi-people-fill me-1 text-muted"></i>Thành viên</div>
        </div>
        <div class="stat-divider d-none d-md-block"></div>
        <div class="stat-item">
            <div class="stat-num"><?php echo number_format($stats['donations'] ?? 0); ?>+</div>
            <div class="stat-label"><i class="bi bi-box-seam me-1 text-muted"></i>Lượt quyên góp</div>
        </div>
        <div class="stat-divider d-none d-md-block"></div>
        <div class="stat-item">
            <div class="stat-num"><?php echo number_format($stats['campaigns'] ?? 0); ?>+</div>
            <div class="stat-label"><i class="bi bi-megaphone me-1 text-muted"></i>Chiến dịch</div>
        </div>
        <div class="stat-divider d-none d-md-block"></div>
        <div class="stat-item">
            <div class="stat-num"><?php echo number_format($stats['items'] ?? 0); ?>+</div>
            <div class="stat-label"><i class="bi bi-gift me-1 text-muted"></i>Vật phẩm</div>
        </div>
    </div>
</div>

<!-- ===== FEATURES ===== -->
<section class="hp-features py-6" style="padding-top: 5rem; padding-bottom: 5rem;">
    <div class="container">
        <div class="text-center mb-5">
            <div class="title-pip"></div>
            <h2 class="hp-section-title">Tại sao chọn Goodwill?</h2>
            <p class="hp-section-sub mt-2">Nền tảng được thiết kế để tối ưu hóa quá trình thiện nguyện</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="feature-icon"><i class="bi bi-shield-check-fill"></i></div>
                    <h5 class="fw-800 mb-2">Bảo mật & Minh bạch</h5>
                    <p class="text-muted mb-0">Hệ thống bảo mật đa tầng, mọi giao dịch quyên góp đều được ghi nhận và công khai minh bạch.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="feature-icon"><i class="bi bi-graph-up-arrow"></i></div>
                    <h5 class="fw-800 mb-2">Theo dõi thời thực</h5>
                    <p class="text-muted mb-0">Báo cáo và thống kê trực quan giúp bạn theo dõi tác động thực tế của từng đóng góp.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="feature-icon"><i class="bi bi-people-fill"></i></div>
                    <h5 class="fw-800 mb-2">Kết nối cộng đồng</h5>
                    <p class="text-muted mb-0">Kết nối người tặng, tình nguyện viên và các chiến dịch thiện nguyện trên cùng một nền tảng.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="feature-icon"><i class="bi bi-megaphone-fill"></i></div>
                    <h5 class="fw-800 mb-2">Tạo chiến dịch</h5>
                    <p class="text-muted mb-0">Bất kỳ ai cũng có thể khởi tạo chiến dịch kêu gọi quyên góp và nhận được sự hỗ trợ từ cộng đồng.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="feature-icon"><i class="bi bi-box-seam-fill"></i></div>
                    <h5 class="fw-800 mb-2">Quản lý vật phẩm</h5>
                    <p class="text-muted mb-0">Theo dõi từng vật phẩm từ lúc quyên góp đến khi được trao tặng đến tay người nhận.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <div class="feature-icon"><i class="bi bi-phone-fill"></i></div>
                    <h5 class="fw-800 mb-2">Dễ dùng mọi nơi</h5>
                    <p class="text-muted mb-0">Giao diện tối ưu cho mọi thiết bị, truy cập và quyên góp dễ dàng từ điện thoại hay máy tính.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== HOW IT WORKS ===== -->
<section class="py-5" style="background:#fff;">
    <div class="container">
        <div class="text-center mb-5">
            <div class="title-pip"></div>
            <h2 class="hp-section-title">Cách thức hoạt động</h2>
            <p class="hp-section-sub mt-2">Chỉ 3 bước đơn giản để đóng góp cho cộng đồng</p>
        </div>
        <div class="row justify-content-center g-0">
            <div class="col-lg-8">
                <div class="d-flex gap-4 mb-4 align-items-start">
                    <div class="d-flex flex-column align-items-center">
                        <div class="step-circle">1</div>
                        <div class="step-line"></div>
                    </div>
                    <div class="pb-4">
                        <h5 class="fw-700 mb-1">Đăng ký tài khoản</h5>
                        <p class="text-muted mb-0">Tạo tài khoản miễn phí trong vài giây và cá nhân hóa hồ sơ của bạn.</p>
                    </div>
                </div>
                <div class="d-flex gap-4 mb-4 align-items-start">
                    <div class="d-flex flex-column align-items-center">
                        <div class="step-circle">2</div>
                        <div class="step-line"></div>
                    </div>
                    <div class="pb-4">
                        <h5 class="fw-700 mb-1">Chọn chiến dịch</h5>
                        <p class="text-muted mb-0">Duyệt qua các chiến dịch đang hoạt động và chọn chiến dịch bạn muốn hỗ trợ.</p>
                    </div>
                </div>
                <div class="d-flex gap-4 align-items-start">
                    <div class="d-flex flex-column align-items-center">
                        <div class="step-circle">3</div>
                    </div>
                    <div>
                        <h5 class="fw-700 mb-1">Quyên góp & Theo dõi</h5>
                        <p class="text-muted mb-0">Đóng góp vật phẩm hoặc hiện vật và theo dõi hành trình tác động của bạn theo thời gian thực.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== RECENT DONATIONS ===== -->
<section class="py-5" style="background:#f6fbfd;">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-5 flex-wrap gap-3">
            <div>
                <div class="title-pip"></div>
                <h2 class="hp-section-title mb-1">Quyên góp gần đây</h2>
                <p class="hp-section-sub mb-0">Những đóng góp ý nghĩa từ cộng đồng</p>
            </div>
            <a href="my-donations.php" class="btn btn-outline-secondary rounded-pill px-4">
                Xem tất cả <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="row g-3" id="recentDonations">
            <!-- Loaded via AJAX -->
        </div>
    </div>
</section>

<!-- ===== CTA BANNER ===== -->
<section class="py-5" style="background:#f6fbfd;">
    <div class="container">
        <div class="hp-cta text-center">
            <div class="mb-3">
                <span style="font-size:2.5rem;">🤝</span>
            </div>
            <h2 class="mb-3">Bắt đầu hành trình thiện nguyện<br class="d-none d-md-block">của bạn hôm nay</h2>
            <p class="mb-4 opacity-85" style="font-size:1.05rem; max-width:500px; margin:0 auto 1.5rem;">
                Mỗi đóng góp dù nhỏ đều tạo ra sự thay đổi lớn. Hãy cùng chúng tôi lan toả điều tốt đẹp.
            </p>
            <div class="d-flex justify-content-center flex-wrap gap-3">
                <?php if (!$isLoggedIn): ?>
                <a href="register.php" class="btn btn-hero-primary">
                    <i class="bi bi-person-plus-fill me-2"></i>Tham gia ngay
                </a>
                <?php endif; ?>
                <a href="campaigns.php" class="btn btn-hero-outline">
                    <i class="bi bi-megaphone me-2"></i>Xem chiến dịch
                </a>
                <a href="donate.php" class="btn btn-hero-outline">
                    <i class="bi bi-heart me-2"></i>Quyên góp ngay
                </a>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
