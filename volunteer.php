<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = "Tham gia Tình nguyện";

// Get available campaigns for volunteering
$availableCampaigns = Database::fetchAll(
    "SELECT c.*, u.name as creator_name,
            (SELECT COUNT(*) FROM campaign_volunteers WHERE campaign_id = c.campaign_id) as volunteer_count,
            (SELECT COUNT(*) FROM campaign_volunteers WHERE campaign_id = c.campaign_id AND user_id = ?) as registered_by_me
     FROM campaigns c
     LEFT JOIN users u ON c.created_by = u.user_id
     WHERE c.status = 'active' AND c.end_date >= CURDATE()
     ORDER BY c.created_at DESC",
    [$_SESSION['user_id']]
);

// Get user's volunteer activities
$userVolunteerCount = Database::fetch(
    "SELECT COUNT(*) as count FROM campaign_volunteers WHERE user_id = ? AND status = 'approved'",
    [$_SESSION['user_id']]
);

$userVolunteerHours = Database::fetch(
    "SELECT SUM(minutes) as total FROM volunteer_hours_logs WHERE user_id = ? AND status = 'approved'",
    [$_SESSION['user_id']]
);

include 'includes/header.php';
?>

<style>
:root {
    --volunteer-primary: #1b7f9d;
    --volunteer-primary-deep: #145f77;
    --volunteer-primary-soft: #e9f6fb;
    --volunteer-primary-border: #c8e7f0;
    --volunteer-primary-border-strong: #8bc5d6;
    --volunteer-primary-glow: rgba(73, 167, 192, 0.06);
    --volunteer-text-strong: #11485a;
    --volunteer-shadow: rgba(23, 126, 154, 0.16);
}

.volunteer-page {
    background: linear-gradient(135deg, #f4fbfd 0%, #edf7fb 45%, #f8fcfd 100%);
    min-height: 100vh;
}

.volunteer-hero {
    background:
        linear-gradient(135deg, rgba(14, 74, 92, 0.54) 0%, rgba(25, 121, 146, 0.38) 100%),
        url('https://www.ymca.org/sites/default/files/2025-07/2025_06_10_youthgovcon_2068.jpg');
    background-size: cover;
    background-position: center 34%;
    background-repeat: no-repeat;
    color: #fff;
    padding: 4rem 2.5rem;
    position: relative;
    overflow: hidden;
}

.volunteer-hero > * {
    position: relative;
    z-index: 1;
}

.volunteer-hero .stats-grid::before {
    content: '';
    position: absolute;
    inset: -16px;
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    pointer-events: none;
}

.volunteer-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, rgba(7, 43, 54, 0.3) 0%, rgba(7, 43, 54, 0.08) 46%, rgba(255,255,255,0.02) 100%);
}

.volunteer-hero::after {
    content: '';
    position: absolute;
    width: 260px;
    height: 260px;
    right: 4%;
    top: 10%;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(123, 231, 255, 0.22), transparent 72%);
}

.volunteer-hero-content {
    position: relative;
    z-index: 2;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255,255,255,0.18);
    border: 1px solid rgba(255,255,255,0.34);
    border-radius: 999px;
    padding: 0.5rem 1.2rem;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    backdrop-filter: blur(12px);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-top: 3rem;
    position: relative;
}

.stat-card {
    background: linear-gradient(180deg, rgba(255,255,255,0.22), rgba(255,255,255,0.1));
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    backdrop-filter: blur(20px);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.22), 0 16px 30px rgba(9, 72, 93, 0.18);
    position: relative;
}

.stat-card::after {
    content: '';
    position: absolute;
    inset: 7px;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.1);
    pointer-events: none;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.95;
}

.campaigns-header {
    margin-bottom: 3rem;
    text-align: center;
}

.campaigns-title {
    font-size: clamp(1.5rem, 3vw, 2.4rem);
    font-weight: 800;
    color: var(--volunteer-text-strong);
    margin-bottom: 0.5rem;
}

.campaigns-subtitle {
    color: #6b7280;
    font-size: 1.05rem;
}

.campaign-card {
    border: 1.5px solid var(--volunteer-primary-border);
    border-radius: 20px;
    background: linear-gradient(180deg, #ffffff 0%, #fafdff 100%);
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 16px 34px rgba(23, 126, 154, 0.1), 0 2px 8px rgba(17, 83, 103, 0.06);
    height: 100%;
    display: flex;
    flex-direction: column;
    position: relative;
    isolation: isolate;
}

.campaign-card::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 20px;
    padding: 1px;
    background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(27,127,157,0.16));
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}

.campaign-card::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 20px;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.9), inset 0 -1px 0 rgba(139, 197, 214, 0.16);
    pointer-events: none;
}

.campaign-card:hover {
    transform: translateY(-8px) scale(1.006);
    border-color: var(--volunteer-primary-border-strong);
    box-shadow: 0 24px 42px rgba(23, 126, 154, 0.15), 0 0 0 3px rgba(233, 246, 251, 0.64), 0 0 8px var(--volunteer-primary-glow);
}

.campaign-card-header {
    background: linear-gradient(135deg, var(--volunteer-primary-soft) 0%, #f2fbfd 100%);
    padding: 1.5rem 1.5rem 1.1rem;
    border-bottom: 1px solid var(--volunteer-primary-border);
    position: relative;
}

.campaign-image-wrap {
    width: 100%;
    height: 170px;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid var(--volunteer-primary-border);
    background: linear-gradient(135deg, #eaf7fb 0%, #d7edf4 100%);
    margin-bottom: 1rem;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
}

.campaign-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.campaign-image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #78aebe;
    font-size: 2.2rem;
}

.campaign-card-header::after {
    content: '';
    position: absolute;
    left: 1.5rem;
    bottom: 0;
    width: 72px;
    height: 3px;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--volunteer-primary), #63b8cd);
}

.campaign-card-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--volunteer-text-strong);
    margin-bottom: 0.5rem;
}

.campaign-card-body {
    padding: 1.5rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    background: linear-gradient(180deg, rgba(255,255,255,1) 0%, rgba(248,252,253,1) 100%);
}

.campaign-description {
    color: #6b7280;
    font-size: 0.95rem;
    line-height: 1.6;
    margin-bottom: 1.2rem;
    flex-grow: 1;
}

.campaign-meta {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #e2f1f6;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.9rem;
    color: #6b7280;
}

.meta-item i {
    color: var(--volunteer-primary);
    font-size: 1rem;
}

.campaign-date {
    background: var(--volunteer-primary-soft);
    color: var(--volunteer-primary-deep);
    border: 1px solid var(--volunteer-primary-border);
    padding: 0.5rem 0.8rem;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.65);
}

.volunteer-count {
    background: linear-gradient(135deg, #d8eef5, #c3e8f1);
    color: var(--volunteer-primary-deep);
    border: 1px solid var(--volunteer-primary-border);
    padding: 0.5rem;
    border-radius: 12px;
    font-size: 0.9rem;
    font-weight: 600;
    text-align: center;
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.5), 0 8px 16px rgba(23, 126, 154, 0.08);
}

.campaign-footer {
    display: flex;
    gap: 0.8rem;
    align-items: center;
}

.btn-view-detail {
    flex: 1;
    background: linear-gradient(135deg, var(--volunteer-primary), #2595b0);
    color: #fff;
    border: 1px solid rgba(17, 96, 120, 0.25);
    border-radius: 12px;
    padding: 0.75rem 1.2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    box-shadow: 0 12px 22px rgba(27, 127, 157, 0.2), inset 0 1px 0 rgba(255,255,255,0.16);
}

.btn-join-volunteer {
    flex: 1;
    background: #fff;
    color: var(--volunteer-primary-deep);
    border: 1.5px solid var(--volunteer-primary-border-strong);
    border-radius: 12px;
    padding: 0.75rem 1rem;
    font-weight: 700;
    transition: all 0.25s ease;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    box-shadow: 0 8px 16px rgba(23, 126, 154, 0.08);
}

.btn-join-volunteer:hover {
    transform: translateY(-1px);
    border-color: var(--volunteer-primary);
    color: var(--volunteer-primary-deep);
    background: #f5fbfd;
}

.btn-join-volunteer.is-joined,
.btn-join-volunteer:disabled {
    cursor: default;
    background: #eaf6fb;
    color: #0f667f;
    border-color: #9bcede;
    box-shadow: none;
}

.action-row {
    display: flex;
    gap: 0.65rem;
}

.btn-view-detail:hover {
    background: linear-gradient(135deg, #0f667f, var(--volunteer-primary));
    transform: translateY(-1px) scale(1.01);
    color: #fff;
    text-decoration: none;
    box-shadow: 0 14px 24px rgba(27, 127, 157, 0.18), 0 0 6px rgba(95, 184, 205, 0.06);
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: rgba(255, 255, 255, 0.82);
    border: 1px solid var(--volunteer-primary-border);
    border-radius: 24px;
    box-shadow: 0 16px 34px rgba(23, 126, 154, 0.1), inset 0 1px 0 rgba(255,255,255,0.8);
}

.empty-icon {
    font-size: 4rem;
    color: #d1d5db;
    margin-bottom: 1rem;
}

.empty-title {
    font-size: 1.3rem;
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.empty-text {
    color: #9ca3af;
    margin-bottom: 0;
}

@media (max-width: 768px) {
    .volunteer-hero {
        padding: 2.5rem 1.5rem;
    }
    
    .stat-number {
        font-size: 1.8rem;
    }
    
    .campaigns-title {
        font-size: 1.5rem;
    }

    .action-row {
        flex-direction: column;
    }
}

.join-confirm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(6, 35, 45, 0.52);
    backdrop-filter: blur(2px);
    display: flex;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    align-items: center;
    justify-content: center;
    z-index: 2100;
    padding: 1rem;
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
</style>

<div class="volunteer-page pb-5">
    <!-- Hero Section -->
    <section class="volunteer-hero">
        <div class="container">
            <div class="volunteer-hero-content">
                <div class="hero-badge">
                    <i class="bi bi-star-fill"></i>
                    <span>Cơ hội tình nguyện</span>
                </div>
                <h1 class="display-4 fw-bold mb-3">Tham gia Tình nguyện</h1>
                <p class="lead mb-0" style="opacity: 0.95;">
                    Đóng góp sức mình cho cộng đồng thông qua các hoạt động tình nguyện có ý nghĩa
                </p>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $userVolunteerCount['count'] ?? 0; ?></div>
                        <div class="stat-label">Chiến dịch tham gia</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo ceil(($userVolunteerHours['total'] ?? 0) / 60); ?></div>
                        <div class="stat-label">Giờ tình nguyện</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($availableCampaigns); ?></div>
                        <div class="stat-label">Cơ hội sẵn có</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Campaigns Section -->
    <div class="container py-5">
        <div class="campaigns-header">
            <h2 class="campaigns-title">
                <i class="bi bi-megaphone me-2" style="color: #177e9a;"></i>Chiến dịch đang tuyển tình nguyện viên
            </h2>
            <p class="campaigns-subtitle">Chọn chiến dịch phù hợp với bạn và bắt đầu tham gia</p>
        </div>

        <?php if (empty($availableCampaigns)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                <h5 class="empty-title">Chưa có chiến dịch nào</h5>
                <p class="empty-text">Hãy quay lại sau để xem các cơ hội tình nguyện mới</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($availableCampaigns as $campaign): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="campaign-card">
                            <div class="campaign-card-header">
                                <div class="campaign-image-wrap">
                                    <?php if (!empty($campaign['image'])): ?>
                                        <img src="uploads/campaigns/<?php echo htmlspecialchars($campaign['image']); ?>"
                                             alt="<?php echo htmlspecialchars($campaign['name']); ?>"
                                             class="campaign-image"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="campaign-image-placeholder" style="display:none;"><i class="bi bi-image"></i></div>
                                    <?php else: ?>
                                        <div class="campaign-image-placeholder"><i class="bi bi-image"></i></div>
                                    <?php endif; ?>
                                </div>
                                <h5 class="campaign-card-title">
                                    <?php echo htmlspecialchars(substr($campaign['name'], 0, 40)); ?>
                                    <?php if (strlen($campaign['name']) > 40): ?>..<?php endif; ?>
                                </h5>
                            </div>
                            
                            <div class="campaign-card-body">
                                <p class="campaign-description">
                                    <?php echo htmlspecialchars(substr($campaign['description'] ?? '', 0, 100)); ?>
                                    <?php if (strlen($campaign['description'] ?? '') > 100): ?>..<?php endif; ?>
                                </p>

                                <div class="campaign-meta">
                                    <div class="meta-item">
                                        <i class="bi bi-person-fill"></i>
                                        <span><?php echo htmlspecialchars($campaign['creator_name'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="bi bi-calendar3"></i>
                                        <span class="campaign-date">
                                            <?php echo formatDate($campaign['start_date'], 'd/m/Y'); ?> - 
                                            <?php echo formatDate($campaign['end_date'], 'd/m/Y'); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="volunteer-count">
                                    <i class="bi bi-people-fill me-1"></i>
                                    <span data-volunteer-count><?php echo $campaign['volunteer_count'] ?? 0; ?></span> tình nguyện viên
                                </div>
                            </div>

                            <div style="padding: 0 1.5rem 1.5rem;">
                                <div class="action-row">
                                    <a href="campaign-detail.php?id=<?php echo $campaign['campaign_id']; ?>" 
                                       class="btn-view-detail">
                                        <i class="bi bi-arrow-right"></i>Xem chi tiết
                                    </a>
                                    <button type="button"
                                            class="btn-join-volunteer <?php echo !empty($campaign['registered_by_me']) ? 'is-joined' : ''; ?>"
                                            data-campaign-id="<?php echo (int)$campaign['campaign_id']; ?>"
                                            data-campaign-name="<?php echo htmlspecialchars($campaign['name']); ?>"
                                            <?php echo !empty($campaign['registered_by_me']) ? 'disabled' : ''; ?>
                                            onclick="joinVolunteerCampaign(this)">
                                        <?php if (!empty($campaign['registered_by_me'])): ?>
                                            <i class="bi bi-check2-circle"></i>Đã tham gia
                                        <?php else: ?>
                                            <i class="bi bi-person-plus"></i>Tham gia
                                        <?php endif; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
            if (settled) {
                return;
            }
            settled = true;

            overlay.classList.remove('show');
            overlay.setAttribute('aria-hidden', 'true');

            window.setTimeout(() => {
                cleanup();
                resolve(value);
            }, durationMs);
        };

        const onYes = () => {
            finish(true, 150);
        };

        const onNo = () => {
            // Keep a longer fade-out when user selects NO.
            finish(false, 260);
        };

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

async function joinVolunteerCampaign(button) {
    if (!button || button.disabled) {
        return;
    }

    const campaignId = button.getAttribute('data-campaign-id');
    const campaignName = button.getAttribute('data-campaign-name') || 'chiến dịch này';
    if (!campaignId) {
        return;
    }

    const isConfirmed = await showJoinConfirmPopup(campaignName);
    if (!isConfirmed) {
        return;
    }

    const originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="bi bi-hourglass-split"></i>Đang xử lý';

    try {
        const response = await fetch('api/register-volunteer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ campaign_id: Number(campaignId) })
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Không thể tham gia lúc này.');
        }

        button.classList.add('is-joined');
        button.innerHTML = '<i class="bi bi-check2-circle"></i>Đã tham gia';

        const card = button.closest('.campaign-card');
        if (card) {
            const countEl = card.querySelector('[data-volunteer-count]');
            if (countEl) {
                const current = parseInt(countEl.textContent || '0', 10);
                countEl.textContent = Number.isNaN(current) ? '1' : String(current + 1);
            }
        }
    } catch (error) {
        button.disabled = false;
        button.innerHTML = originalHtml;
        alert(error.message || 'Không thể tham gia chiến dịch.');
    }
}
</script>

<?php include 'includes/footer.php'; ?>