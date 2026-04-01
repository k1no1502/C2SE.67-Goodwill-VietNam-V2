<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Giới thiệu";
include 'includes/header.php';
?>

<style>
.about-page {
    background: radial-gradient(circle at 8% 10%, rgba(14, 116, 144, 0.12), transparent 30%), #f7fcfe;
}
.about-hero {
    background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
    color: #fff;
    padding: 4rem 3rem;
    position: relative;
    overflow: hidden;
}
.about-hero::after {
    content: '';
    position: absolute;
    width: 360px;
    height: 360px;
    right: -120px;
    top: -110px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,0.18), transparent 70%);
}
.hero-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.7rem 1.35rem;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.45);
    background: rgba(255,255,255,0.15);
    font-size: 0.95rem;
    font-weight: 700;
}
.about-hero-copy {
    max-width: 760px;
}
.about-hero-title {
    font-size: clamp(2.7rem, 5.3vw, 5rem);
    line-height: 1.08;
    font-weight: 900;
    margin-bottom: 1rem;
    letter-spacing: -0.02em;
}
.about-hero-subtitle {
    font-size: clamp(1.18rem, 2vw, 1.9rem);
    line-height: 1.5;
    opacity: 0.92;
    max-width: 780px;
    margin-bottom: 0;
}
.about-hero-icon-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100%;
}
.about-hero-icon {
    font-size: clamp(6.5rem, 10vw, 8.6rem);
    opacity: 0.96;
    color: #ffffff;
}
.glass-box {
    border: 1px solid #d4eaf1;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 10px 26px rgba(14, 116, 144, 0.08);
}
.mission-card {
    border: 1px solid #d6ebf1;
    border-radius: 16px;
    padding: 1.2rem;
    height: 100%;
    background: linear-gradient(135deg, #ffffff 0%, #f7fdff 100%);
}
.mission-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: linear-gradient(135deg, #0e7490, #155e75);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.45rem;
    margin-bottom: 0.8rem;
}
.about-section-title {
    font-size: clamp(1.4rem, 2.4vw, 2rem);
    font-weight: 800;
    color: #0f172a;
}
.about-sub {
    color: #64748b;
}
.story-step {
    position: relative;
    padding-left: 2.2rem;
}
.story-step::before {
    content: '';
    position: absolute;
    left: 0.65rem;
    top: 0.45rem;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #0e7490;
}
.story-step::after {
    content: '';
    position: absolute;
    left: 0.95rem;
    top: 1.2rem;
    bottom: -1.2rem;
    width: 2px;
    background: linear-gradient(to bottom, #0e7490, #d6ebf1);
}
.story-step:last-child::after { display: none; }

.value-card {
    border: 1px solid #d7ebf2;
    border-radius: 16px;
    background: #fff;
    padding: 1.2rem;
    text-align: center;
    height: 100%;
    transition: all 0.2s ease;
}
.value-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 24px rgba(14, 116, 144, 0.12);
}
.value-bubble {
    width: 72px;
    height: 72px;
    margin: 0 auto 0.85rem;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.8rem;
    background: linear-gradient(135deg, #0e7490, #155e75);
}

.stats-section {
    background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
    color: #fff;
    border-radius: 20px;
}
.stat-box {
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 14px;
    padding: 1rem;
    text-align: center;
    background: rgba(255,255,255,0.08);
}
.stat-box .num {
    font-size: clamp(1.5rem, 3vw, 2.3rem);
    font-weight: 800;
    line-height: 1;
}

.team-card {
    border: 1px solid #d8ebf1;
    border-radius: 16px;
    background: #fff;
    text-align: center;
    padding: 1.25rem;
    transition: all 0.2s ease;
}
.team-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 24px rgba(14, 116, 144, 0.12);
}
.team-avatar {
    width: 80px;
    height: 80px;
    border-radius: 999px;
    margin: 0 auto 0.8rem;
    background: #e3f2f7;
    color: #0e7490;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}

.cta-wrap {
    border: 1px solid #d8ebf1;
    border-radius: 20px;
    background: #fff;
    padding: 2.2rem 1.4rem;
    text-align: center;
}
.btn-about-main {
    background: linear-gradient(135deg, #0e7490, #155e75);
    color: #fff;
    border: none;
    font-weight: 700;
    border-radius: 12px;
}
.btn-about-main:hover { filter: brightness(0.94); color: #fff; }
.btn-about-outline {
    border: 1.5px solid #0e7490;
    color: #0e7490;
    font-weight: 700;
    border-radius: 12px;
}
.btn-about-outline:hover { background: #0e7490; color: #fff; }
@media (max-width: 991.98px) {
    .about-hero {
        padding: 2.2rem 1.5rem;
    }
    .hero-chip {
        padding: 0.55rem 1rem;
        font-size: 0.88rem;
    }
    .about-hero-title {
        font-size: clamp(2rem, 8vw, 3.2rem);
    }
    .about-hero-subtitle {
        font-size: 1.04rem;
    }
    .about-hero-icon-wrap {
        justify-content: flex-start;
    }
    .about-hero-icon {
        font-size: 5.2rem;
    }
}
</style>

<div class="about-page pb-5">
    <section class="about-hero mb-4">
        <div class="container py-4">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="about-hero-copy">
                        <span class="hero-chip mb-4"><i class="bi bi-heart-fill"></i>Giới thiệu Goodwill Vietnam</span>
                        <h1 class="about-hero-title">Kết nối yêu thương, lan tỏa giá trị cộng đồng</h1>
                        <p class="about-hero-subtitle">
                            Chúng tôi xây dựng nền tảng thiện nguyện minh bạch, giúp người cho và người nhận gặp nhau đúng lúc.
                        </p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="about-hero-icon-wrap">
                        <i class="bi bi-heart-fill about-hero-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container py-4">

        <section class="glass-box p-3 p-md-4 mb-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="mission-card">
                        <div class="mission-icon"><i class="bi bi-bullseye"></i></div>
                        <h3 class="h4 fw-bold mb-2">Sứ mệnh</h3>
                        <p class="about-sub mb-0">
                            Xây dựng cầu nối giữa những người có nhu cầu với những tấm lòng hảo tâm, tạo dựng một cộng đồng chia sẻ, tương trợ lẫn nhau.
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mission-card">
                        <div class="mission-icon"><i class="bi bi-eye"></i></div>
                        <h3 class="h4 fw-bold mb-2">Tầm nhìn</h3>
                        <p class="about-sub mb-0">
                            Trở thành nền tảng thiện nguyện hàng đầu Việt Nam, nơi mọi người có thể dễ dàng chia sẻ và nhận được sự giúp đỡ.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-4">
            <div class="row g-4 align-items-start">
                <div class="col-lg-5">
                    <h2 class="about-section-title mb-2">Câu chuyện của chúng tôi</h2>
                    <p class="about-sub mb-0">Hành trình bắt đầu từ một câu hỏi đơn giản và niềm tin rằng mỗi hành động tử tế đều tạo nên thay đổi.</p>
                </div>
                <div class="col-lg-7">
                    <div class="glass-box p-3 p-md-4">
                        <div class="story-step pb-3 mb-2">
                            <p class="mb-0">Goodwill Vietnam ra đời từ ý tưởng: làm sao để kết nối những người muốn giúp đỡ với những người cần được giúp đỡ một cách hiệu quả nhất.</p>
                        </div>
                        <div class="story-step pb-3 mb-2">
                            <p class="mb-0">Nhiều vật dụng còn tốt bị bỏ quên, trong khi nhiều hoàn cảnh lại rất cần. Từ đó nền tảng được hình thành với mục tiêu kết nối minh bạch và dễ dàng.</p>
                        </div>
                        <div class="story-step">
                            <p class="mb-0">Mọi quyên góp đều được kiểm duyệt trước khi đến tay người nhận. Với chúng tôi, thiện nguyện là chia sẻ yêu thương và trách nhiệm xã hội.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-4">
            <div class="text-center mb-4">
                <h2 class="about-section-title mb-2">Giá trị cốt lõi</h2>
                <p class="about-sub mb-0">Kim chỉ nam trong mọi hoạt động của Goodwill Vietnam</p>
            </div>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="value-card">
                        <div class="value-bubble"><i class="bi bi-shield-check"></i></div>
                        <h6 class="fw-bold mb-1">Minh bạch</h6>
                        <small class="text-muted">Mọi hoạt động đều được công khai và có thể kiểm chứng</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="value-card">
                        <div class="value-bubble"><i class="bi bi-people"></i></div>
                        <h6 class="fw-bold mb-1">Cộng đồng</h6>
                        <small class="text-muted">Xây dựng một cộng đồng chia sẻ và tương trợ lẫn nhau</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="value-card">
                        <div class="value-bubble"><i class="bi bi-heart"></i></div>
                        <h6 class="fw-bold mb-1">Trách nhiệm</h6>
                        <small class="text-muted">Cam kết với sự phát triển bền vững của xã hội</small>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="value-card">
                        <div class="value-bubble"><i class="bi bi-lightning"></i></div>
                        <h6 class="fw-bold mb-1">Hiệu quả</h6>
                        <small class="text-muted">Tối ưu hóa quy trình để đạt kết quả tốt nhất</small>
                    </div>
                </div>
            </div>
        </section>

        <section class="stats-section p-3 p-md-4 mb-4">
            <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
                <h2 class="h3 fw-bold mb-0">Con số ấn tượng</h2>
                <small style="opacity:0.9;">Được cập nhật theo hệ thống</small>
            </div>
            <?php $stats = getStatistics(); ?>
            <div class="row g-3">
                <div class="col-6 col-lg-3"><div class="stat-box"><div class="num"><?php echo number_format($stats['users']); ?>+</div><div>Người dùng</div></div></div>
                <div class="col-6 col-lg-3"><div class="stat-box"><div class="num"><?php echo number_format($stats['donations']); ?>+</div><div>Quyên góp</div></div></div>
                <div class="col-6 col-lg-3"><div class="stat-box"><div class="num"><?php echo number_format($stats['items']); ?>+</div><div>Vật phẩm</div></div></div>
                <div class="col-6 col-lg-3"><div class="stat-box"><div class="num"><?php echo number_format($stats['campaigns']); ?>+</div><div>Chiến dịch</div></div></div>
            </div>
        </section>

        <section class="mb-4">
            <div class="text-center mb-4">
                <h2 class="about-section-title mb-2">Đội ngũ của chúng tôi</h2>
                <p class="about-sub mb-0">Những con người đứng sau hành trình kết nối cộng đồng</p>
            </div>
            <div class="row g-3">
                <div class="col-6 col-md-4 col-lg-3"><div class="team-card"><h6 class="fw-bold mb-1">Lê Văn Vũ Phong</h6><small class="text-muted">Scrum Master</small></div></div>
                <div class="col-6 col-md-4 col-lg-3"><div class="team-card"><h6 class="fw-bold mb-1">Võ Đình Dương</h6><small class="text-muted">Product Owner</small></div></div>
                <div class="col-6 col-md-4 col-lg-3"><div class="team-card"><h6 class="fw-bold mb-1">Nguyễn Thành Đạt</h6><small class="text-muted">Development</small></div></div>
                <div class="col-6 col-md-4 col-lg-3"><div class="team-card"><h6 class="fw-bold mb-1">Hằng Gia Bảo</h6><small class="text-muted">Development</small></div></div>
            </div>
        </section>

        <section class="cta-wrap">
            <h2 class="about-section-title mb-2">Cùng tham gia với chúng tôi</h2>
            <p class="about-sub mb-4">Mỗi đóng góp của bạn đều có ý nghĩa cho cộng đồng.</p>
            <div class="d-flex flex-wrap gap-2 justify-content-center">
                <a href="donate.php" class="btn btn-about-main btn-lg px-4">
                    <i class="bi bi-heart-fill me-2"></i>Quyên góp ngay
                </a>
                <a href="campaigns.php" class="btn btn-about-outline btn-lg px-4">
                    <i class="bi bi-trophy me-2"></i>Xem chiến dịch
                </a>
            </div>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
