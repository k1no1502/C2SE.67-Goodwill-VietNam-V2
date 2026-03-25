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
    background: radial-gradient(circle at 7% 9%, rgba(14, 116, 144, 0.12), transparent 28%), #f6fbfd;
}
.campaigns-hero {
    background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
    color: #fff;
    border-radius: 22px;
    padding: 2.2rem;
    position: relative;
    overflow: hidden;
}
.campaigns-hero::after {
    content: '';
    position: absolute;
    right: -60px;
    top: -40px;
    width: 220px;
    height: 220px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.2), transparent 65%);
}
.hero-title { font-size: clamp(1.6rem, 3vw, 2.4rem); font-weight: 800; margin: 0; }
.hero-sub { opacity: 0.9; margin-top: 0.5rem; }

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

<div class="campaigns-page pt-5 mt-4 pb-5">
<div class="container py-4">

    <div class="campaigns-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h1 class="hero-title"><i class="bi bi-megaphone-fill me-2"></i>Chiến dịch thiện nguyện</h1>
                <p class="hero-sub mb-0">Tham gia các chiến dịch ý nghĩa và tạo ra tác động tích cực cho cộng đồng</p>
            </div>
            <?php if (isLoggedIn()): ?>
                <a href="create-campaign.php" class="btn btn-light fw-bold px-4 py-2 rounded-pill">
                    <i class="bi bi-plus-circle me-2"></i>Tạo chiến dịch mới
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-light fw-bold px-4 py-2 rounded-pill">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập để tham gia
                </a>
            <?php endif; ?>
        </div>
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

                                <?php if (isLoggedIn()): ?>
                                    <div class="btn-group" role="group">
                                        <a href="donate-to-campaign.php?campaign_id=<?php echo $campaign['campaign_id']; ?>" class="btn btn-main-gradient btn-sm">
                                            <i class="bi bi-heart me-1"></i>Quyên góp
                                        </a>
                                        <?php if (!empty($campaign['registered_by_me'])): ?>
                                            <button type="button" class="btn btn-main-gradient btn-sm" disabled>
                                                <i class="bi bi-person-check me-1"></i>Đã tham gia
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-campaign-outline btn-sm register-volunteer" data-campaign-id="<?php echo $campaign['campaign_id']; ?>">
                                                <i class="bi bi-person-plus me-1"></i>Tình nguyện
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-main-gradient btn-sm">
                                        <i class="bi bi-lock me-1"></i>Đăng nhập để tham gia
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</div>

<script>
// Register as volunteer
document.addEventListener('DOMContentLoaded', function() {
    const volunteerButtons = document.querySelectorAll('.register-volunteer');
    
            volunteerButtons.forEach(button => {
        button.addEventListener('click', function() {
            const campaignId = this.dataset.campaignId;
            const btn = this;
            if (!campaignId) return alert('ID chiến dịch không hợp lệ.');

            if (!confirm('Bạn có chắc chắn muốn đăng ký làm tình nguyện viên cho chiến dịch này?')) return;

            // Disable button while processing
            btn.disabled = true;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Đang xử lý...';

            const body = new URLSearchParams();
            body.append('campaign_id', campaignId);

            fetch('api/register-volunteer-detail.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: body.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data && data.success) {
                    alert(data.message || 'Đã đăng ký làm tình nguyện viên thành công!');
                    location.reload();
                } else {
                    alert('Lỗi: ' + (data && data.message ? data.message : 'Không thể đăng ký'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi đăng ký');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
