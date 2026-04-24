<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$campaign_id = (int)($_GET['id'] ?? 0);

if ($campaign_id <= 0) {
    header('Location: campaigns.php');
    exit();
}

// Get campaign details
$campaign = Database::fetch(
    "SELECT c.*, u.name as creator_name, u.email as creator_email,
            (SELECT COUNT(*) FROM campaign_volunteers WHERE campaign_id = c.campaign_id) as volunteer_count,
            (SELECT COUNT(*) FROM campaign_donations WHERE campaign_id = c.campaign_id) as donation_count,
            DATEDIFF(c.end_date, CURDATE()) as days_remaining
     FROM campaigns c
     LEFT JOIN users u ON c.created_by = u.user_id
     WHERE c.campaign_id = ?",
    [$campaign_id]
);

if (!$campaign) {
    header('Location: campaigns.php');
    exit();
}

// Get campaign items
$items = Database::fetchAll(
    "SELECT ci.*, c.name as category_name,
            COALESCE(ci.quantity_received, 0) as quantity_received,
            CASE 
                WHEN ci.quantity_needed > 0 THEN 
                    ROUND((COALESCE(ci.quantity_received, 0) / ci.quantity_needed) * 100, 2)
                ELSE 0
            END as progress_percentage,
            CASE 
                WHEN COALESCE(ci.quantity_received, 0) >= ci.quantity_needed THEN 'Đủ'
                WHEN COALESCE(ci.quantity_received, 0) > 0 THEN 'Đang thiếu'
                ELSE 'Chưa có'
            END as status_text
     FROM campaign_items ci
     LEFT JOIN categories c ON ci.category_id = c.category_id
     WHERE ci.campaign_id = ?
     ORDER BY ci.item_id",
    [$campaign_id]
);

// Get all donations linked to this campaign (including custom/free)
$campaignDonations = Database::fetchAll(
    "SELECT 
        cd.campaign_item_id,
        cd.quantity_contributed,
        cd.created_at,
        d.item_name,
        d.description,
        d.unit,
        d.condition_status,
        d.status AS donation_status,
        cat.name AS category_name
     FROM campaign_donations cd
     JOIN donations d ON cd.donation_id = d.donation_id
     LEFT JOIN categories cat ON d.category_id = cat.category_id
     WHERE cd.campaign_id = ? AND d.status = 'approved'
     ORDER BY cd.created_at DESC",
    [$campaign_id]
);

// Get volunteers
$volunteers = Database::fetchAll(
    "SELECT cv.*, u.name, u.email, u.avatar 
     FROM campaign_volunteers cv
     LEFT JOIN users u ON cv.user_id = u.user_id
     WHERE cv.campaign_id = ? AND cv.status = 'approved'
     ORDER BY cv.created_at DESC",
    [$campaign_id]
);

// Check if user is volunteer
$isVolunteer = false;
if (isLoggedIn()) {
    $volunteerCheck = Database::fetch(
        "SELECT * FROM campaign_volunteers WHERE campaign_id = ? AND user_id = ?",
        [$campaign_id, $_SESSION['user_id']]
    );
    $isVolunteer = $volunteerCheck !== false;
}

// Calculate campaign progress
$totalItemsNeeded = 0;
$totalItemsReceived = 0;
foreach ($items as $item) {
    $totalItemsNeeded += $item['quantity_needed'] ?? 0;
    $totalItemsReceived += $item['quantity_received'] ?? 0;
}
$completionPercentage = $totalItemsNeeded > 0 
    ? min(100, round(($totalItemsReceived / $totalItemsNeeded) * 100)) 
    : 0;

$targetAmount = (float)($campaign['target_amount'] ?? 0);
$currentAmount = (float)($campaign['current_amount'] ?? 0);
$moneyProgressPercentage = $targetAmount > 0
    ? min(100, round(($currentAmount / $targetAmount) * 100))
    : 0;

// Status text mapping
$statusMap = [
    'draft' => ['class' => 'secondary', 'text' => 'Nháp'],
    'pending' => ['class' => 'warning', 'text' => 'Chờ duyệt'],
    'active' => ['class' => 'success', 'text' => 'Đang hoạt động'],
    'paused' => ['class' => 'info', 'text' => 'Tạm dừng'],
    'completed' => ['class' => 'primary', 'text' => 'Hoàn thành'],
    'cancelled' => ['class' => 'danger', 'text' => 'Đã hủy']
];
$statusInfo = $statusMap[$campaign['status']] ?? ['class' => 'secondary', 'text' => 'N/A'];

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

$pageTitle = $campaign['name'] ?? 'Chi tiết chiến dịch';
include 'includes/header.php';
?>

<style>
    .campaign-detail-page {
        background: radial-gradient(circle at 10% 10%, rgba(14, 116, 144, 0.14), rgba(14, 116, 144, 0) 32%), #f8fcfe;
    }
    .campaign-detail-page .card {
        border: 1px solid #d8edf3;
        border-radius: 16px;
        box-shadow: 0 12px 28px rgba(13, 64, 86, 0.08);
    }
    .btn-back-modern {
        border-radius: 999px;
        border-color: #0e7490;
        color: #0e7490;
        font-weight: 600;
    }
    .btn-back-modern:hover {
        background: #0e7490;
        color: #fff;
    }
    .campaign-detail-hero-wrap {
        margin-top: -1px;
        background: linear-gradient(135deg, #0e6f8b 0%, #176f89 100%);
        position: relative;
        overflow: hidden;
    }
    .campaign-detail-hero-wrap::before {
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 18% 20%, rgba(255, 255, 255, 0.12), rgba(255, 255, 255, 0) 38%);
        pointer-events: none;
    }
    .campaign-detail-hero-inner {
        position: relative;
        z-index: 1;
        padding-top: 0.55rem;
        padding-bottom: 2rem;
    }
    .campaign-detail-hero-row {
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }
    .campaign-detail-hero {
        color: #fff;
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }
    .campaign-detail-hero-copy {
        min-width: 0;
    }
    .campaign-detail-hero-icon {
        width: 114px;
        height: 114px;
        border-radius: 24px;
        border: 1px solid rgba(199, 237, 247, 0.34);
        background: rgba(255, 255, 255, 0.14);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        backdrop-filter: blur(6px);
    }
    .campaign-detail-hero-icon i {
        font-size: 3.35rem;
        line-height: 1;
    }
    .campaign-detail-hero .btn-back-modern {
        border-color: rgba(255, 255, 255, 0.56);
        color: #fff;
        background: rgba(255, 255, 255, 0.12);
        margin-bottom: 1rem;
        font-weight: 700;
    }
    .campaign-detail-hero .btn-back-modern:hover {
        background: rgba(255, 255, 255, 0.22);
        color: #fff;
        border-color: rgba(255, 255, 255, 0.7);
    }
    .campaign-detail-hero h1 {
        margin: 0;
        font-size: clamp(2.1rem, 4.7vw, 4.2rem);
        font-weight: 900;
        letter-spacing: 0.01em;
        line-height: 1.05;
    }
    .campaign-detail-hero p {
        margin: 0.45rem 0 0;
        font-size: clamp(1rem, 2vw, 1.22rem);
        color: rgba(226, 246, 252, 0.96);
        font-weight: 500;
    }
    .campaign-detail-hero-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.7rem;
        margin-top: 1rem;
    }
    .campaign-detail-hero-badge {
        display: inline-flex;
        align-items: center;
        border: 1px solid rgba(255, 255, 255, 0.45);
        border-radius: 999px;
        padding: 0.46rem 0.95rem;
        background: rgba(255, 255, 255, 0.12);
        color: #ffffff;
        font-weight: 700;
        font-size: 0.9rem;
        white-space: nowrap;
    }
    .overview-card {
        background: linear-gradient(135deg, #ffffff 0%, #f3fbfe 100%);
    }
    .metric-card {
        background: #ffffff;
        border-color: #b9dbe6 !important;
    }
    .metric-card .fs-4 {
        color: #0e7490;
    }
    .campaign-section-header {
        background: linear-gradient(135deg, #0e7490 0%, #155e75 100%) !important;
        color: #fff;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    .sidebar-card .btn-primary {
        background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
        border: none;
    }
    .sidebar-card .btn-primary:hover {
        filter: brightness(0.95);
    }
    .sidebar-card .btn-success {
        background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
        border: none;
    }
    .sidebar-card .btn-success:hover {
        filter: brightness(0.95);
    }

    .join-confirm-overlay {
        position: fixed;
        inset: 0;
        display: flex;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        z-index: 2100;
        background: rgba(6, 35, 45, 0.52);
        backdrop-filter: blur(2px);
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }

    .join-confirm-overlay.show {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }

    .join-confirm-dialog {
        width: min(680px, 96vw);
        border-radius: 18px;
        background: #ffffff;
        border: 1px solid #b8dce7;
        box-shadow: 0 24px 54px rgba(12, 67, 82, 0.26);
        padding: 1.4rem 1.35rem 1.1rem;
        transform: translateY(16px) scale(0.97);
        opacity: 0;
        transition: transform 0.28s cubic-bezier(0.2, 0.8, 0.2, 1), opacity 0.22s ease;
    }

    .join-confirm-overlay.show .join-confirm-dialog {
        transform: translateY(0) scale(1);
        opacity: 1;
    }

    .join-confirm-icon {
        width: 64px;
        height: 64px;
        border-radius: 14px;
        border: 1px solid #b8dce7;
        background: linear-gradient(180deg, #f4fcff, #edf8fb);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 0.95rem;
        box-shadow: 0 10px 22px rgba(19, 113, 136, 0.14);
    }

    .join-confirm-icon img {
        width: 42px;
        height: 42px;
        object-fit: contain;
    }

    .join-confirm-title {
        color: #0e4c5f;
        font-weight: 800;
        font-size: 1.2rem;
        line-height: 1.45;
        margin: 0;
        text-align: center;
    }

    .join-confirm-title .campaign-name {
        color: #0a7894;
    }

    .join-confirm-actions {
        margin-top: 1.15rem;
        display: flex;
        justify-content: center;
        gap: 0.75rem;
    }

    .btn-join-choice {
        min-width: 120px;
        border-radius: 999px;
        border: 1px solid #9ac8d6;
        padding: 0.55rem 1rem;
        font-weight: 800;
        letter-spacing: 0.02em;
    }

    .btn-join-yes {
        background: linear-gradient(135deg, #0f7c99, #18a9c5);
        color: #fff;
        border: none;
    }

    .btn-join-no {
        background: #fff;
        color: #0f657d;
    }

    .btn-join-no:hover {
        background: #f4fbfd;
    }

    .volunteer-register-dialog {
        width: min(920px, calc(100vw - 1.75rem));
        max-width: min(920px, calc(100vw - 1.75rem));
        margin: 0.8rem auto;
        transition: transform 0.28s cubic-bezier(0.2, 0.8, 0.2, 1), opacity 0.24s ease;
    }

    #volunteerModal.modal.fade .volunteer-register-dialog {
        transform: translateY(18px) scale(0.97);
        opacity: 0;
    }

    #volunteerModal.modal.show .volunteer-register-dialog {
        transform: translateY(0) scale(1);
        opacity: 1;
    }

    .volunteer-register-dialog .modal-content {
        border-radius: 16px;
        border: 1px solid #d2e8f0;
        overflow: hidden;
        box-shadow: 0 22px 44px rgba(8, 57, 74, 0.24);
        max-height: calc(100vh - 1.6rem);
    }

    .volunteer-register-dialog .modal-header {
        padding: 0.9rem 1.2rem;
        background: linear-gradient(135deg, #f5fcff 0%, #edf8fb 100%);
        border-bottom: 1px solid #d5eaf1;
    }

    .volunteer-register-dialog .modal-body {
        padding: 1rem 1.2rem;
        max-height: calc(100vh - 210px);
        overflow-y: auto;
    }

    .volunteer-register-dialog .modal-footer {
        padding: 0.75rem 1.2rem 0.9rem;
        border-top: 1px solid #d5eaf1;
        background: #f3fbfe;
    }

    .volunteer-register-dialog .form-label {
        margin-bottom: 0.35rem;
        color: #124f62;
        font-weight: 700;
    }

    .volunteer-register-dialog .form-control,
    .volunteer-register-dialog .form-select {
        border-color: #b8e1ec;
    }

    .volunteer-register-dialog .form-control:focus,
    .volunteer-register-dialog .form-select:focus {
        border-color: #0e7490;
        box-shadow: 0 0 0 0.18rem rgba(14, 116, 144, 0.16);
    }

    .volunteer-register-dialog .btn-secondary {
        border-radius: 999px;
        padding-left: 1.6rem;
        padding-right: 1.6rem;
    }

    .volunteer-register-dialog .btn-warning {
        border-radius: 999px;
        padding-left: 1.6rem;
        padding-right: 1.6rem;
        font-weight: 700;
    }

    .volunteer-register-dialog hr {
        margin: 0.7rem 0 0.9rem;
    }

    @media (max-width: 991.98px) {
        .volunteer-register-dialog {
            width: calc(100vw - 1rem);
            max-width: calc(100vw - 1rem);
            margin: 0.45rem auto;
        }

        .volunteer-register-dialog .modal-body {
            max-height: calc(100vh - 168px);
            padding: 0.85rem 0.9rem;
        }

        .volunteer-register-dialog .modal-header,
        .volunteer-register-dialog .modal-footer {
            padding-left: 0.9rem;
            padding-right: 0.9rem;
        }
    }

    @media (max-width: 575.98px) {
        .campaign-detail-hero-wrap {
            margin-top: -1px;
        }

        .campaign-detail-hero-inner {
            padding-top: 0.4rem;
            padding-bottom: 1.35rem;
        }

        .campaign-detail-hero-row,
        .campaign-detail-hero {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.95rem;
        }

        .campaign-detail-hero .btn-back-modern {
            margin-bottom: 0.2rem;
        }

        .campaign-detail-hero-icon {
            width: 88px;
            height: 88px;
            border-radius: 18px;
        }

        .campaign-detail-hero-icon i {
            font-size: 2.6rem;
        }

        .campaign-detail-hero-badge {
            font-size: 0.82rem;
            padding: 0.4rem 0.78rem;
        }

        .volunteer-register-dialog {
            width: 100vw;
            max-width: 100vw;
            margin: 0;
        }

        .volunteer-register-dialog .modal-content {
            border-radius: 0;
            border-left: none;
            border-right: none;
            max-height: 100vh;
        }
    }
</style>

<div class="campaign-detail-hero-wrap">
    <div class="container campaign-detail-hero-inner">
        <div class="campaign-detail-hero">
            <div class="campaign-detail-hero-copy">
                <a href="campaigns.php" class="btn btn-outline-secondary btn-back-modern">
                    <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
                </a>
                <div class="campaign-detail-hero-row">
                    <span class="campaign-detail-hero-icon" aria-hidden="true">
                        <i class="bi bi-megaphone"></i>
                    </span>
                    <div class="campaign-detail-hero-copy">
                        <h1>Chi tiết chiến dịch</h1>
                        <p><?php echo htmlspecialchars($campaign['name'] ?? 'Thông tin chiến dịch thiện nguyện'); ?></p>
                        <div class="campaign-detail-hero-badges">
                            <span class="campaign-detail-hero-badge"><i class="bi bi-bullseye me-2"></i>Mục tiêu rõ ràng</span>
                            <span class="campaign-detail-hero-badge"><i class="bi bi-people me-2"></i>Chung tay cộng đồng</span>
                            <span class="campaign-detail-hero-badge"><i class="bi bi-graph-up-arrow me-2"></i>Theo dõi tiến độ</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Campaign Detail -->
<div class="container py-4 campaign-detail-page">

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Campaign Overview Dashboard -->
            <div class="card shadow-sm mb-4 overview-card">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            <h4 class="mb-1 fw-bold">Tổng quan chiến dịch</h4>
                            <div class="text-muted small">
                                <?php echo htmlspecialchars($campaign['start_date']); ?> - <?php echo htmlspecialchars($campaign['end_date']); ?>
                                <?php if (!empty($campaign['location'])): ?>
                                    · <?php echo htmlspecialchars($campaign['location']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <span class="badge bg-<?php echo $statusInfo['class']; ?> px-3 py-2">
                                <?php echo htmlspecialchars($statusInfo['text']); ?>
                            </span>
                        </div>
                    </div>

                    <p class="mb-4 text-muted">
                        <?php echo nl2br(htmlspecialchars($campaign['description'] ?? '')); ?>
                    </p>

                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-3 h-100 metric-card">
                                <div class="text-muted small">Tình nguyện viên</div>
                                <div class="fs-4 fw-bold"><?php echo (int)count($volunteers); ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-3 h-100 metric-card">
                                <div class="text-muted small">Lượt quyên góp</div>
                                <div class="fs-4 fw-bold"><?php echo (int)($campaign['donation_count'] ?? 0); ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-3 h-100 metric-card">
                                <div class="text-muted small">Tổng cần</div>
                                <div class="fs-4 fw-bold"><?php echo number_format($totalItemsNeeded); ?></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded p-3 h-100 metric-card">
                                <div class="text-muted small">Đã nhận</div>
                                <div class="fs-4 fw-bold"><?php echo number_format($totalItemsReceived); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="fw-semibold">Tiến độ hoàn thành</div>
                            <div class="text-muted small"><?php echo $completionPercentage; ?>%</div>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar" style="width: <?php echo $completionPercentage; ?>%; background: linear-gradient(90deg, #0e7490, #155e75);"></div>
                        </div>
                        <div class="d-flex justify-content-between text-muted small mt-2">
                            <span>Bắt đầu</span>
                            <span>Hiện tại</span>
                            <span>Kết thúc</span>
                        </div>
                    </div>

                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="fw-semibold">Tiền quyên góp</div>
                            <div class="text-muted small"><?php echo number_format($currentAmount, 0, ',', '.'); ?> / <?php echo number_format($targetAmount, 0, ',', '.'); ?> VND</div>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-warning" style="width: <?php echo $moneyProgressPercentage; ?>%;"></div>
                        </div>
                        <div class="d-flex justify-content-between text-muted small mt-2">
                            <span>Đã quyên góp: <?php echo number_format($currentAmount, 0, ',', '.'); ?> VND</span>
                            <span><?php echo $moneyProgressPercentage; ?>%</span>
                        </div>
                    </div>

                    <?php if (isLoggedIn()): ?>
                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <?php if (!$isVolunteer && ($campaign['status'] ?? '') === 'active'): ?>
                                <a href="campaign-volunteer-register.php?campaign_id=<?php echo $campaign_id; ?>" class="btn btn-warning">
                                    <i class="bi bi-person-plus me-1"></i>Tham gia chiến dịch
                                </a>
                            <?php elseif ($isVolunteer): ?>
                                <button class="btn btn-outline-success" disabled>
                                    <i class="bi bi-person-check me-1"></i>Bạn đã tham gia
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Campaign Header -->
            <div class="card shadow-sm mb-4">
                <?php if ($campaign['image']): ?>
                    <img src="uploads/campaigns/<?php echo htmlspecialchars($campaign['image']); ?>" 
                         class="card-img-top" 
                         style="height: 400px; object-fit: cover;"
                         alt="<?php echo htmlspecialchars($campaign['name']); ?>"
                         onerror="this.src='assets/images/no-image.jpg'">
                <?php else: ?>
                    <div class="card-img-top bg-gradient-primary d-flex align-items-center justify-content-center" 
                         style="height: 400px;">
                        <i class="bi bi-trophy-fill text-white" style="font-size: 8rem;"></i>
                    </div>
                <?php endif; ?>

                <!-- Video Display Section -->
                <?php if ($campaign['video_type'] === 'upload' && $campaign['video_file']): ?>
                    <div class="card-img-top">
                        <video width="100%" height="400" controls style="object-fit: cover;">
                            <source src="uploads/campaigns/videos/<?php echo htmlspecialchars($campaign['video_file']); ?>" type="video/mp4">
                            Trình duyệt của bạn không hỗ trợ video.
                        </video>
                    </div>
                <?php elseif ($campaign['video_type'] === 'youtube' && $campaign['video_youtube']): ?>
                    <?php $youtubeEmbedId = extractYoutubeVideoId($campaign['video_youtube']); ?>
                    <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; background: #000;">
                        <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                                src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtubeEmbedId); ?>" 
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen>
                        </iframe>
                    </div>
                <?php elseif ($campaign['video_type'] === 'facebook' && $campaign['video_facebook']): ?>
                    <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; background: #000;">
                        <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                                src="https://www.facebook.com/plugins/video.php?href=<?php echo urlencode($campaign['video_facebook']); ?>&show_text=false" 
                                frameborder="0" 
                                allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" 
                                allowfullscreen>
                        </iframe>
                    </div>
                <?php elseif ($campaign['video_type'] === 'tiktok' && $campaign['video_tiktok']): ?>
                    <?php 
                    $tiktokUrl = trim((string)$campaign['video_tiktok']);
                    $tiktokVideoId = extractTikTokVideoId($tiktokUrl);
                    $tiktokLiveUrl = isTikTokLiveUrl($tiktokUrl) ? $tiktokUrl : '';
                    ?>
                    <?php if ($tiktokVideoId): ?>
                        <div style="position: relative; padding-bottom: 100%; height: 0; overflow: hidden; max-width: 100%; background: #000;">
                            <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                                    src="https://www.tiktok.com/embed/v2/<?php echo $tiktokVideoId; ?>" 
                                    frameborder="0" 
                                    allow="autoplay; encrypted-media" 
                                    allowfullscreen>
                            </iframe>
                        </div>
                    <?php elseif ($tiktokLiveUrl): ?>
                        <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; background: #000;">
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
                                <video width="100%" height="400" controls style="object-fit: cover;">
                                    <source src="uploads/campaigns/videos/<?php echo htmlspecialchars($campaign['video_file']); ?>" type="video/mp4">
                                    Trình duyệt của bạn không hỗ trợ video.
                                </video>
                            </div>
                        <?php endif; ?>
                        <?php if ($campaign['video_youtube']): ?>
                            <?php $youtubeEmbedId = extractYoutubeVideoId($campaign['video_youtube']); ?>
                            <div class="tab-pane fade <?php echo !$campaign['video_file'] ? 'show active' : ''; ?>" id="video-youtube">
                                <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; background: #000;">
                                    <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                                            src="https://www.youtube.com/embed/<?php echo htmlspecialchars($youtubeEmbedId); ?>" 
                                            frameborder="0" 
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                            allowfullscreen>
                                    </iframe>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($campaign['video_facebook']): ?>
                            <div class="tab-pane fade <?php echo !$campaign['video_file'] && !$campaign['video_youtube'] ? 'show active' : ''; ?>" id="video-facebook">
                                <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; background: #000;">
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
                                    <div style="position: relative; padding-bottom: 100%; height: 0; overflow: hidden; max-width: 100%; background: #000;">
                                        <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                                                src="https://www.tiktok.com/embed/v2/<?php echo $tiktokVideoId; ?>" 
                                                frameborder="0" 
                                                allow="autoplay; encrypted-media" 
                                                allowfullscreen>
                                        </iframe>
                                    </div>
                                <?php elseif ($tiktokLiveUrl): ?>
                                    <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; background: #000;">
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
                
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h2 class="fw-bold"><?php echo htmlspecialchars($campaign['name']); ?></h2>
                        <span class="badge bg-<?php echo $statusInfo['class']; ?> fs-6">
                            <?php echo $statusInfo['text']; ?>
                        </span>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="text-muted mb-2">
                                <i class="bi bi-person-circle me-2"></i>
                                <strong>Người tạo:</strong> <?php echo htmlspecialchars($campaign['creator_name'] ?? 'N/A'); ?>
                            </p>
                            <p class="text-muted mb-2">
                                <i class="bi bi-calendar-event me-2"></i>
                                <strong>Thời gian:</strong> 
                                <?php echo formatDate($campaign['start_date'], 'd/m/Y'); ?> - 
                                <?php echo formatDate($campaign['end_date'], 'd/m/Y'); ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted mb-2">
                                <i class="bi bi-people-fill me-2"></i>
                                <strong>Tình nguyện viên:</strong> <?php echo number_format($campaign['volunteer_count'] ?? 0); ?> người
                            </p>
                            <p class="text-muted mb-2">
                                <i class="bi bi-clock me-2"></i>
                                <strong>Còn lại:</strong> 
                                <?php 
                                $daysRemaining = $campaign['days_remaining'] ?? 0;
                                echo max(0, $daysRemaining); 
                                ?> ngày
                            </p>
                        </div>
                    </div>
                    
                    <h5 class="fw-bold mb-3">Mô tả chiến dịch</h5>
                    <p class="text-justify"><?php echo nl2br(htmlspecialchars($campaign['description'] ?? 'Chưa có mô tả')); ?></p>
                </div>
            </div>

            <!-- Campaign Items -->
            <div class="card shadow-sm mb-4">
                <div class="card-header campaign-section-header">
                    <h5 class="mb-0">
                        <i class="bi bi-list-check me-2"></i>Vật phẩm cần thiết
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($items)): ?>
                        <p class="text-muted">Chưa có danh sách vật phẩm.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Vật phẩm</th>
                                        <th>Danh mục</th>
                                        <th>Cần thiết</th>
                                        <th>Đã nhận</th>
                                        <th>Tiến độ</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                                <?php if (!empty($item['description'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($item['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo number_format($item['quantity_needed']); ?> <?php echo htmlspecialchars($item['unit'] ?? 'cái'); ?></td>
                                            <td><strong class="text-success"><?php echo number_format($item['quantity_received']); ?></strong> <?php echo htmlspecialchars($item['unit'] ?? 'cái'); ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-success" 
                                                         role="progressbar" 
                                                         style="width: <?php echo min($item['progress_percentage'], 100); ?>%">
                                                        <?php echo round($item['progress_percentage']); ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = $item['status_text'] === 'Đủ' ? 'success' : 
                                                              ($item['status_text'] === 'Đang thiếu' ? 'warning' : 'secondary');
                                                ?>
                                                <span class="badge bg-<?php echo $statusClass; ?>">
                                                    <?php echo htmlspecialchars($item['status_text']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($campaignDonations)): ?>
                        <hr class="my-4">
                        <h6 class="fw-bold mb-3">
                            <i class="bi bi-box-seam me-2"></i>Vat pham da quyen gop (bao gom quyen gop tu do)
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Vat pham</th>
                                        <th>Danh muc</th>
                                        <th>So luong</th>
                                        <th>Tinh trang</th>
                                        <th>Loai</th>
                                        <th>Ngay</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($campaignDonations as $donation): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($donation['item_name']); ?></strong>
                                                <?php if (!empty($donation['description'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($donation['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($donation['category_name'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php echo number_format($donation['quantity_contributed'] ?? 0); ?>
                                                <?php echo htmlspecialchars($donation['unit'] ?? 'cai'); ?>
                                            </td>
                                            <td>
                                                <?php
                                                    $conditionText = ucfirst(str_replace('_', ' ', $donation['condition_status'] ?? ''));
                                                ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($conditionText ?: 'N/A'); ?></span>
                                            </td>
                                            <td>
                                                <?php $isCustom = empty($donation['campaign_item_id']); ?>
                                                <span class="badge bg-<?php echo $isCustom ? 'info' : 'success'; ?>">
                                                    <?php echo $isCustom ? 'Quyen gop tu do' : 'Theo danh sach'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($donation['created_at'] ?? '', 'd/m/Y'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Volunteers List -->
            <div class="card shadow-sm mb-4">
                <div class="card-header campaign-section-header">
                    <h5 class="mb-0">
                        <i class="bi bi-people-fill me-2"></i>Tình nguyện viên (<?php echo count($volunteers); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($volunteers)): ?>
                        <p class="text-muted">Chưa có tình nguyện viên nào.</p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($volunteers as $volunteer): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="d-flex align-items-center">
                                        <?php if ($volunteer['avatar']): ?>
                                            <img src="uploads/avatars/<?php echo htmlspecialchars($volunteer['avatar']); ?>" 
                                                 class="rounded-circle me-3" 
                                                 width="50" 
                                                 height="50" 
                                                 alt="Avatar">
                                        <?php else: ?>
                                            <i class="bi bi-person-circle fs-3 text-success me-3"></i>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($volunteer['name'] ?? 'N/A'); ?></strong>
                                            <?php if (!empty($volunteer['role'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($volunteer['role']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Campaign Progress -->
            <div class="card shadow-sm mb-4 sidebar-card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Tiến độ chiến dịch</h5>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Hoàn thành</span>
                            <strong><?php echo $completionPercentage; ?>%</strong>
                        </div>
                        <div class="progress" style="height: 30px;">
                            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                                 role="progressbar" 
                                 style="width: <?php echo $completionPercentage; ?>%">
                                <?php echo $completionPercentage; ?>%
                            </div>
                        </div>
                    </div>
                    
                    <div class="row text-center">
                        <div class="col-6 border-end">
                            <h3 class="text-primary mb-0"><?php echo number_format($totalItemsReceived); ?></h3>
                            <small class="text-muted">Đã nhận</small>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success mb-0"><?php echo number_format($totalItemsNeeded); ?></h3>
                            <small class="text-muted">Mục tiêu</small>
                        </div>
                    </div>

                    <hr class="my-3">

                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Tiền đã quyên góp</span>
                            <strong><?php echo $moneyProgressPercentage; ?>%</strong>
                        </div>
                        <div class="progress" style="height: 12px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $moneyProgressPercentage; ?>%;"></div>
                        </div>
                        <div class="d-flex justify-content-between text-muted small mt-2">
                            <span><?php echo number_format($currentAmount, 0, ',', '.'); ?> VND</span>
                            <span>Mục tiêu <?php echo number_format($targetAmount, 0, ',', '.'); ?> VND</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="card shadow-sm mb-4 sidebar-card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Tham gia chiến dịch</h5>
                    
                    <?php if (!isLoggedIn()): ?>
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle me-1"></i>
                            Đăng nhập để tham gia chiến dịch
                        </div>
                        <div class="d-grid gap-2">
                            <a href="login.php?redirect=campaign-detail.php?id=<?php echo $campaign_id; ?>" 
                               class="btn btn-success">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="d-grid gap-2">
                            <!-- Donate to Campaign -->
                            <a href="donate-to-campaign.php?campaign_id=<?php echo $campaign_id; ?>" 
                               class="btn btn-primary">
                                <i class="bi bi-gift me-2"></i>Quyên góp cho chiến dịch
                            </a>
                            
                            <!-- Volunteer -->
                            <?php if ($isVolunteer): ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="bi bi-check-circle me-2"></i>Đã đăng ký tình nguyện
                                </button>
                            <?php else: ?>
                                <a href="campaign-volunteer-register.php?campaign_id=<?php echo $campaign_id; ?>"
                                   class="btn btn-warning">
                                    <i class="bi bi-person-plus me-2"></i>Đăng ký tình nguyện viên
                                </a>
                            <?php endif; ?>
                            
                            <!-- Share -->
                            <button class="btn btn-outline-secondary" onclick="shareOnSocial()">
                                <i class="bi bi-share me-2"></i>Chia sẻ chiến dịch
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contact -->
            <div class="card shadow-sm sidebar-card">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3">Liên hệ</h6>
                    <p class="small text-muted mb-2">
                        <i class="bi bi-envelope me-2"></i>
                        <?php echo htmlspecialchars($campaign['creator_email'] ?? 'N/A'); ?>
                    </p>
                    <p class="small text-muted mb-0">
                        <i class="bi bi-telephone me-2"></i>
                        Hotline: +84 123 456 789
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="joinConfirmOverlay" class="join-confirm-overlay" aria-hidden="true">
    <div class="join-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="joinConfirmTitle">
        <div class="join-confirm-icon">
            <img src="https://cdn-icons-png.flaticon.com/512/4470/4470914.png" alt="Volunteer Icon">
        </div>
        <p id="joinConfirmTitle" class="join-confirm-title">
            Bạn có muốn tham gia làm tình nguyện viên của chiến dịch "<span id="joinConfirmCampaignName" class="campaign-name"></span>"?
        </p>
        <div class="join-confirm-actions">
            <button type="button" class="btn-join-choice btn-join-yes" id="joinConfirmYes">CÓ</button>
            <button type="button" class="btn-join-choice btn-join-no" id="joinConfirmNo">KHÔNG</button>
        </div>
    </div>
</div>

<!-- Volunteer Modal -->
<div class="modal fade" id="volunteerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-lg-down volunteer-register-dialog">
        <div class="modal-content">
            <form id="volunteerForm">
                <div class="modal-header">
                    <h5 class="modal-title">Đăng ký tình nguyện viên</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">

                    <div class="row g-3 mb-1">
                        <div class="col-12">
                            <label class="form-label">Tên</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label">Ngày sinh</label>
                            <input type="date" class="form-control" name="date_of_birth" id="volunteerDob" required>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label">Tuổi</label>
                            <input type="number" class="form-control" name="age" id="volunteerAge" min="1" max="120" required>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label">Giới tính</label>
                            <select class="form-select" name="gender" required>
                                <option value="">-- Chọn giới tính --</option>
                                <option value="male">Nam</option>
                                <option value="female">Nữ</option>
                            </select>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>

                        <div class="col-lg-6">
                            <label class="form-label">SĐT</label>
                            <input type="tel" class="form-control" name="phone" required>
                        </div>
                    </div>

                    <hr>
                    
                    <div class="mb-3">
                        <label class="form-label">Kỹ năng bạn có thể đóng góp</label>
                        <textarea class="form-control" name="skills" rows="2" 
                                  placeholder="VD: Có xe máy, biết dùng máy tính..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Thời gian bạn có thể tham gia</label>
                        <textarea class="form-control" name="availability" rows="2" 
                                  placeholder="VD: Thứ 7, Chủ nhật..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Vai trò</label>
                        <input type="text" class="form-control" name="role" 
                               placeholder="VD: Tổ chức, Vận chuyển, Phân phát...">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Lời nhắn</label>
                        <textarea class="form-control" name="message" rows="3" 
                                  placeholder="Tại sao bạn muốn tham gia chiến dịch này?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-send me-2"></i>Gửi đăng ký
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showJoinConfirmPopup(campaignName) {
    const overlay = document.getElementById('joinConfirmOverlay');
    const nameEl = document.getElementById('joinConfirmCampaignName');
    const btnYes = document.getElementById('joinConfirmYes');
    const btnNo = document.getElementById('joinConfirmNo');

    if (!overlay || !nameEl || !btnYes || !btnNo) {
        return Promise.resolve(window.confirm('Bạn có muốn tham gia làm tình nguyện viên của chiến dịch "' + campaignName + '"?'));
    }

    nameEl.textContent = campaignName || 'N/A';
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');

    return new Promise((resolve) => {
        let settled = false;

        const cleanup = () => {
            btnYes.removeEventListener('click', onYes);
            btnNo.removeEventListener('click', onNo);
            overlay.removeEventListener('click', onOverlayClick);
            document.removeEventListener('keydown', onEsc);
        };

        const finish = (value, durationMs) => {
            if (settled) return;
            settled = true;

            overlay.classList.remove('show');
            overlay.setAttribute('aria-hidden', 'true');

            window.setTimeout(() => {
                cleanup();
                resolve(value);
            }, durationMs);
        };

        const onYes = () => finish(true, 150);
        const onNo = () => finish(false, 260);

        const onOverlayClick = (event) => {
            if (event.target === overlay) {
                onNo();
            }
        };

        const onEsc = (event) => {
            if (event.key === 'Escape') {
                onNo();
            }
        };

        btnYes.addEventListener('click', onYes);
        btnNo.addEventListener('click', onNo);
        overlay.addEventListener('click', onOverlayClick);
        document.addEventListener('keydown', onEsc);
    });
}

function openVolunteerConfirm() {
    const modalEl = document.getElementById('volunteerModal');
    if (!modalEl || typeof bootstrap === 'undefined') return;
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

function calculateAgeFromDob(dobValue) {
    if (!dobValue) return '';
    const dob = new Date(dobValue);
    if (Number.isNaN(dob.getTime())) return '';

    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const monthDiff = today.getMonth() - dob.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age -= 1;
    }
    return age >= 0 ? String(age) : '';
}

const volunteerDobInput = document.getElementById('volunteerDob');
const volunteerAgeInput = document.getElementById('volunteerAge');
if (volunteerDobInput && volunteerAgeInput) {
    volunteerDobInput.addEventListener('change', function() {
        volunteerAgeInput.value = calculateAgeFromDob(this.value);
    });
}

// Volunteer form submit
document.getElementById('volunteerForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    if (!this.checkValidity()) {
        this.reportValidity();
        return;
    }

    const campaignName = <?php echo json_encode((string)($campaign['name'] ?? 'chiến dịch này')); ?>;
    const formEl = this;
    const formData = new FormData(this);

    const fullName = (formData.get('full_name') || '').toString().trim();
    const dob = (formData.get('date_of_birth') || '').toString().trim();
    const age = (formData.get('age') || '').toString().trim();
    const gender = (formData.get('gender') || '').toString().trim();
    const email = (formData.get('email') || '').toString().trim();
    const phone = (formData.get('phone') || '').toString().trim();
    const existingMessage = (formData.get('message') || '').toString().trim();

    const profileBlock = [
        'Thong tin dang ky:',
        '- Ten: ' + fullName,
        '- Ngay sinh: ' + dob,
        '- Tuoi: ' + age,
        '- Gioi tinh: ' + (gender === 'male' ? 'Nam' : 'Nu'),
        '- Email: ' + email,
        '- SDT: ' + phone
    ].join('\n');
    formData.set('message', existingMessage !== '' ? (profileBlock + '\n\n' + existingMessage) : profileBlock);

    const btn = this.querySelector('button[type=submit]');
    const originalText = btn.innerHTML;

    showJoinConfirmPopup(campaignName).then((confirmed) => {
        if (!confirmed) {
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang gửi...';

        fetch('api/register-volunteer-detail.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Đăng ký thành công!');
                const modalEl = document.getElementById('volunteerModal');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                setTimeout(() => location.reload(), 1200);
            } else {
                alert(data.message || 'Có lỗi xảy ra!');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Lỗi kết nối!');
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    });
});

// Share function
function shareOnSocial() {
    const url = window.location.href;
    const title = '<?php echo addslashes($campaign['name']); ?>';
    const text = 'Tham gia chiến dịch thiện nguyện: ' + title;
    
    if (navigator.share) {
        navigator.share({ title: title, text: text, url: url });
    } else {
        const shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
        window.open(shareUrl, '_blank', 'width=600,height=400');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
