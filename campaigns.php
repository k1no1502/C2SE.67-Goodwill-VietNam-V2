<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Get active campaigns
$sql = "SELECT c.*, u.name as creator_name,
        (SELECT COUNT(*) FROM campaign_volunteers WHERE campaign_id = c.campaign_id) as volunteer_count,
        (SELECT COUNT(*) FROM campaign_donations WHERE campaign_id = c.campaign_id) as donation_count
        FROM campaigns c
        LEFT JOIN users u ON c.created_by = u.user_id
        WHERE c.status = 'active' AND c.end_date >= CURDATE()
        ORDER BY c.created_at DESC";
$campaigns = Database::fetchAll($sql);

// If logged in, mark campaigns the user already registered for
if (isLoggedIn() && !empty($campaigns)) {
    $userId = $_SESSION['user_id'];
    foreach ($campaigns as &$c) {
        $exists = Database::fetch(
            "SELECT 1 FROM campaign_volunteers WHERE campaign_id = ? AND user_id = ? LIMIT 1",
            [$c['campaign_id'], $userId]
        );
        $c['registered_by_me'] = $exists ? true : false;
    }
    unset($c);
}

$pageTitle = "Chiến dịch";
include 'includes/header.php';
?>

<style>
.campaigns-page {
    background: #d0d8de;
    min-height: 100vh;
}
.campaigns-hero {
    background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
    color: #fff;
    padding: 64px 0 48px;
    position: relative;
    overflow: hidden;
    margin-top: -1px;
}
.campaigns-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 80% 50%, rgba(255,255,255,0.07) 0%, transparent 60%);
}
.campaigns-hero-row {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 1.6rem;
}
.hero-main {
    display: flex;
    align-items: center;
    gap: 1.6rem;
}
.hero-icon-box {
    width: 134px;
    height: 134px;
    border-radius: 34px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    background: rgba(255, 255, 255, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    backdrop-filter: blur(6px);
}
.hero-icon-box i {
    font-size: 3.8rem;
    color: rgba(255, 255, 255, 0.95);
}
.hero-title {
    font-size: clamp(2.4rem, 5.2vw, 5rem);
    line-height: 1.05;
    font-weight: 900;
    margin: 0;
    letter-spacing: -0.02em;
}
.hero-sub {
    opacity: 0.88;
    margin-top: 0.7rem;
    margin-bottom: 0;
    font-size: clamp(1.05rem, 1.7vw, 2.05rem);
    max-width: 940px;
}
.campaigns-toolbar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-bottom: 1.15rem;
}
.campaign-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    border-radius: 999px;
    padding: 0.76rem 1.45rem;
    font-weight: 700;
    white-space: nowrap;
    word-break: keep-all;
    min-width: 250px;
    width: auto;
}
@media (max-width: 991.98px) {
    .campaigns-hero {
        padding: 38px 0 34px;
    }
    .hero-main {
        gap: 1rem;
    }
    .hero-icon-box {
        width: 90px;
        height: 90px;
        border-radius: 20px;
    }
    .hero-icon-box i {
        font-size: 2.45rem;
    }
    .hero-title {
        font-size: clamp(1.8rem, 8vw, 2.8rem);
    }
    .hero-sub {
        font-size: 1rem;
    }
    .campaigns-toolbar {
        justify-content: stretch;
    }
    .campaign-action-btn {
        width: 100%;
        min-width: 0;
        text-align: center;
    }
}

.mini-stat {
    border: 1px solid #d0e8ef;
    background: #ffffff;
    border-radius: 14px;
    padding: 1rem;
    text-align: center;
    box-shadow: 0 4px 14px rgba(14, 116, 144, 0.07);
}
.mini-stat .num {
    color: #0e7490;
    font-size: 1.5rem;
    font-weight: 800;
    line-height: 1;
}
.mini-stat .txt { color: #64748b; font-size: 0.82rem; margin-top: 0.35rem; }

.btn-main-gradient {
    background: linear-gradient(135deg, #0E7490 0%, #155e75 100%);
    color: #fff;
    border: none;
    font-weight: 700;
    border-radius: 12px;
}
.btn-main-gradient:hover { filter: brightness(0.93); color: #fff; }

.campaign-card {
    border: 1px solid #c9e2ea;
    border-radius: 16px;
    overflow: hidden;
    background: #fff;
    transition: all 0.25s ease;
    box-shadow: 0 4px 14px rgba(14, 116, 144, 0.08);
}
.campaign-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 14px 30px rgba(14, 116, 144, 0.14);
    border-color: #93c8d6;
}
.campaign-cover {
    height: 170px;
    background: linear-gradient(135deg, #e8f5f9 0%, #d6edf4 100%);
    position: relative;
}
.campaign-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.campaign-badge {
    border-radius: 999px;
    font-size: 0.73rem;
    font-weight: 700;
    padding: 0.3rem 0.65rem;
}
.badge-active { background: #10b981; color: #fff; }
.badge-video { background: #0e7490; color: #fff; }

.campaign-title {
    color: #0f172a;
    font-weight: 800;
    line-height: 1.3;
    min-height: 44px;
}
.campaign-meta {
    font-size: 0.84rem;
    color: #64748b;
}
.stats-row {
    background: #f8fcfe;
    border: 1px solid #e2f0f5;
    border-radius: 12px;
    padding: 0.65rem;
}
.stat-number {
    color: #0e7490;
    font-weight: 800;
    font-size: 1.05rem;
}

.btn-campaign-outline {
    border: 1.5px solid #0E7490;
    color: #0E7490;
    font-weight: 700;
    border-radius: 10px;
}
.btn-campaign-outline:hover { background: #0E7490; color: #fff; }

.empty-wrap {
    border: 1px dashed #bfdce5;
    border-radius: 18px;
    background: #fff;
    padding: 3rem 1.25rem;
}
</style>

<div class="campaigns-hero">
    <div class="container">
        <div class="campaigns-hero-row">
            <div class="hero-main">
                <div class="hero-icon-box">
                    <i class="bi bi-megaphone"></i>
                </div>
                <div>
                    <h1 class="hero-title">Chiến dịch thiện nguyện</h1>
                    <p class="hero-sub">Tham gia các chiến dịch ý nghĩa và tạo ra tác động tích cực cho cộng đồng</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="campaigns-page pb-5">
<div class="container py-4">

    <div class="campaigns-toolbar">
        <?php if (isLoggedIn()): ?>
            <a href="create-campaign.php" class="btn btn-light campaign-action-btn">
                <i class="bi bi-plus-circle me-2"></i>Tạo chiến dịch mới
            </a>
        <?php else: ?>
            <a href="login.php" class="btn btn-outline-secondary campaign-action-btn">
                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập để tham gia
            </a>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="mini-stat">
                <div class="num"><?php echo count($campaigns); ?></div>
                <div class="txt">Chiến dịch đang mở</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="mini-stat">
                <div class="num"><?php echo array_sum(array_map(fn($x) => (int)$x['volunteer_count'], $campaigns)); ?></div>
                <div class="txt">Tình nguyện viên</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="mini-stat">
                <div class="num"><?php echo array_sum(array_map(fn($x) => (int)$x['donation_count'], $campaigns)); ?></div>
                <div class="txt">Lượt quyên góp</div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="mini-stat">
                <div class="num"><?php echo isLoggedIn() ? count(array_filter($campaigns, fn($x) => !empty($x['registered_by_me']))) : 0; ?></div>
                <div class="txt">Bạn đã tham gia</div>
            </div>
        </div>
    </div>

    <?php if (empty($campaigns)): ?>
        <div class="empty-wrap text-center">
            <i class="bi bi-trophy display-1" style="color:#b7dce5;"></i>
            <h3 class="mt-3 mb-2 fw-bold">Chưa có chiến dịch nào</h3>
            <p class="text-muted mb-4">Hãy bắt đầu chiến dịch đầu tiên để lan tỏa điều tốt đẹp</p>
            <?php if (isLoggedIn()): ?>
                <a href="create-campaign.php" class="btn btn-main-gradient px-4 py-2">Tạo chiến dịch</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-main-gradient px-4 py-2">Đăng nhập để tạo chiến dịch</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($campaigns as $campaign): ?>
                <?php
                $cover = !empty($campaign['image']) ? 'uploads/campaigns/' . $campaign['image'] : '';
                $hasVideo = $campaign['video_type'] !== 'none' && (($campaign['video_type'] === 'upload' && $campaign['video_file']) || ($campaign['video_type'] === 'youtube' && $campaign['video_youtube']));
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="campaign-card h-100 d-flex flex-column">
                        <div class="campaign-cover">
                            <?php if ($cover): ?>
                                <img src="<?php echo htmlspecialchars($cover); ?>" alt="cover" onerror="this.style.display='none';">
                            <?php endif; ?>
                            <div class="position-absolute top-0 start-0 m-2 d-flex gap-2 flex-wrap">
                                <span class="campaign-badge badge-active">Đang diễn ra</span>
                                <?php if ($hasVideo): ?>
                                    <span class="campaign-badge badge-video"><i class="bi bi-play-circle me-1"></i>Video</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="p-3 p-md-4 d-flex flex-column flex-grow-1">
                            <h5 class="campaign-title mb-2"><?php echo htmlspecialchars($campaign['name']); ?></h5>
                            <p class="text-muted mb-3" style="font-size:0.92rem; min-height:60px;">
                                <?php echo htmlspecialchars(substr($campaign['description'], 0, 120)); ?><?php if (strlen($campaign['description']) > 120): ?>...<?php endif; ?>
                            </p>

                            <div class="campaign-meta mb-3">
                                <i class="bi bi-person me-1" style="color:#0E7490;"></i>
                                Tạo bởi: <strong><?php echo htmlspecialchars($campaign['creator_name']); ?></strong>
                            </div>

                            <div class="stats-row mb-3">
                                <div class="row text-center g-0">
                                    <div class="col-6 border-end" style="border-color:#e3f1f5 !important;">
                                        <div class="stat-number"><?php echo (int)$campaign['volunteer_count']; ?></div>
                                        <small class="text-muted">Tình nguyện viên</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="stat-number"><?php echo (int)$campaign['donation_count']; ?></div>
                                        <small class="text-muted">Quyên góp</small>
                                    </div>
                                </div>
                            </div>

                            <div class="campaign-meta mb-3">
                                <i class="bi bi-calendar me-1" style="color:#0E7490;"></i>
                                Kết thúc: <strong><?php echo date('d/m/Y', strtotime($campaign['end_date'])); ?></strong>
                            </div>

                            <div class="mt-auto d-grid gap-2">
                                <a href="campaign-detail.php?id=<?php echo $campaign['campaign_id']; ?>" class="btn btn-campaign-outline">
                                    <i class="bi bi-eye me-1"></i>Xem chi tiết
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</div>

<?php include 'includes/footer.php'; ?>
