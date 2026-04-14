<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/notifications_helper.php';

requireLogin();
processScheduledAdminNotifications();

$pageTitle = 'Thông báo';
$userId = $_SESSION['user_id'];

function sanitizeDate($date)
{
    if (empty($date)) {
        return null;
    }
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
}

$filters = [
    'date_from' => sanitizeDate($_GET['date_from'] ?? ''),
    'date_to' => sanitizeDate($_GET['date_to'] ?? ''),
    'status' => $_GET['status'] ?? 'all',
    'type' => $_GET['type'] ?? ''
];

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$queryFilters = $filters;
if ($filters['status'] === 'all') {
    $queryFilters['status'] = null;
}
if (empty($filters['type']) || $filters['type'] === 'all') {
    $queryFilters['type'] = null;
}

$total = countUserNotifications($userId, $queryFilters);
$notifications = fetchUserNotifications($userId, $queryFilters, $perPage, $offset);
$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
$unreadCount = getUnreadNotificationCount($userId);

include 'includes/header.php';
?>
<style>
    :root {
        --noti-ink: #163246;
        --noti-muted: #607c90;
        --noti-line: #d1e7ee;
        --noti-brand: #1b8097;
        --noti-brand-dark: #176b81;
        --noti-shadow: 0 18px 42px rgba(16, 92, 112, .10);
    }

    .notifications-page {
        padding-top: 0;
        padding-bottom: 3.2rem;
        background:
            radial-gradient(circle at top left, rgba(27, 128, 151, .10), transparent 24%),
            linear-gradient(180deg, #f6fafb 0%, #edf6f8 100%);
    }

    .notifications-hero {
        margin-left: calc(50% - 50vw);
        margin-right: calc(50% - 50vw);
        padding: 3rem 0 2.5rem;
        background:
            radial-gradient(circle at top right, rgba(255,255,255,.13), transparent 24%),
            linear-gradient(140deg, #1b8097 0%, #187086 52%, #225e73 100%);
        color: #fff;
    }
    .notifications-hero-inner {
        max-width: 1320px;
        margin: 0 auto;
        padding: 0 1rem;
        display: flex;
        align-items: center;
        gap: 1.35rem;
    }
    .notifications-hero-icon {
        width: 102px;
        height: 102px;
        border-radius: 26px;
        border: 1px solid rgba(255,255,255,.24);
        background: linear-gradient(180deg, rgba(255,255,255,.17), rgba(255,255,255,.08));
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 2.9rem;
        box-shadow: 0 18px 34px rgba(7, 49, 59, .2);
        flex-shrink: 0;
    }
    .notifications-hero h1 {
        margin: 0;
        font-size: clamp(2.1rem, 3.9vw, 3.8rem);
        font-weight: 900;
        line-height: .98;
        letter-spacing: -.04em;
    }
    .notifications-hero p {
        margin: .78rem 0 0;
        font-size: clamp(1rem, 1.35vw, 1.15rem);
        color: rgba(255,255,255,.93);
    }

    .notifications-wrap {
        margin-top: 1.8rem;
    }
    .notifications-topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    .notifications-unread {
        color: var(--noti-muted);
        font-size: .96rem;
        margin: 0;
    }

    .noti-btn {
        border-radius: 12px;
        font-weight: 700;
        border: 1px solid #c7dfe7;
        background: #fff;
        color: #24475f;
        padding: .55rem .9rem;
    }
    .noti-btn:hover {
        border-color: #aecfda;
        background: #f4fbfd;
        color: #1b8097;
    }
    .noti-btn.primary {
        border: 0;
        color: #fff;
        background: linear-gradient(145deg, var(--noti-brand), var(--noti-brand-dark));
        box-shadow: 0 12px 24px rgba(21, 100, 121, .22);
    }
    .noti-btn.primary:hover {
        color: #fff;
        filter: brightness(.97);
    }

    .noti-card {
        border: 1px solid var(--noti-line);
        border-radius: 24px;
        background: rgba(255,255,255,.98);
        box-shadow: var(--noti-shadow);
        overflow: hidden;
        position: relative;
    }
    .noti-card::before {
        content: '';
        position: absolute;
        inset: 0 0 auto 0;
        height: 4px;
        background: linear-gradient(90deg, var(--noti-brand), #4db4ca);
    }
    .noti-card-body {
        padding: 1.12rem 1.2rem 1.2rem;
    }

    .filter-label {
        color: #1f455d;
        font-weight: 700;
        margin-bottom: .4rem;
        font-size: .9rem;
    }
    .form-control,
    .form-select {
        min-height: 46px;
        border-radius: 12px;
        border: 1.5px solid #d2e8ee;
        background: #fbfeff;
        color: #17364b;
        box-shadow: none;
    }
    .form-control:focus,
    .form-select:focus {
        border-color: var(--noti-brand);
        box-shadow: 0 0 0 4px rgba(27, 128, 151, .12);
        background: #fff;
    }

    .noti-list {
        padding: .25rem;
    }
    .notification-item {
        border: 1px solid #deedf2 !important;
        border-radius: 14px !important;
        margin: .6rem;
        background: linear-gradient(180deg, #fff 0%, #f8fcfd 100%);
        transition: transform .15s ease, border-color .15s ease, box-shadow .15s ease;
    }
    .notification-item:hover {
        transform: translateY(-1px);
        border-color: #bddce6 !important;
        box-shadow: 0 10px 18px rgba(21,100,121,.08);
    }
    .notification-item.unread {
        border-color: #a9d7e6 !important;
        background: linear-gradient(180deg, #f7fdff 0%, #eff9fc 100%);
    }
    .notification-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: linear-gradient(145deg, #e8f6fa, #f5fcfe);
        border: 1px solid #d2eaf1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #1b8097;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .badge.noti-badge {
        font-size: .73rem;
        font-weight: 700;
        padding: .35rem .55rem;
        border-radius: 999px;
    }
    .badge.noti-warning { background: rgba(245,158,11,.16); color: #8c5a00; }
    .badge.noti-success { background: rgba(34,197,94,.14); color: #146c3b; }
    .badge.noti-info { background: rgba(59,130,246,.14); color: #1e4fb5; }
    .badge.noti-default { background: rgba(107,114,128,.14); color: #3f4955; }

    .noti-empty {
        text-align: center;
        color: var(--noti-muted);
        padding: 3rem 1rem;
    }
    .noti-empty i {
        font-size: 2rem;
        color: #88b9c7;
        display: block;
        margin-bottom: .55rem;
    }

    .pagination .page-link {
        border: 1px solid #cde2ea;
        color: #2c556b;
        border-radius: 10px;
        margin: 0 .18rem;
        min-width: 38px;
        text-align: center;
    }
    .pagination .page-item.active .page-link {
        background: linear-gradient(145deg, var(--noti-brand), var(--noti-brand-dark));
        border-color: transparent;
        color: #fff;
    }

    #notificationModal .modal-content {
        border: 0;
        border-radius: 18px;
        overflow: hidden;
    }
    #notificationModal .modal-header {
        background: linear-gradient(145deg, var(--noti-brand), var(--noti-brand-dark));
        color: #fff;
        border-bottom: 0;
    }
    #notificationModal .btn-close {
        filter: invert(1) brightness(2);
    }

    @media (max-width: 767.98px) {
        .notifications-hero {
            padding: 2.1rem 0 1.9rem;
        }
        .notifications-hero-inner {
            align-items: flex-start;
            gap: .95rem;
        }
        .notifications-hero-icon {
            width: 74px;
            height: 74px;
            border-radius: 18px;
            font-size: 2.1rem;
        }
        .noti-card-body {
            padding: .95rem .95rem 1rem;
        }
    }
</style>

<main class="notifications-page">
    <section class="notifications-hero">
        <div class="notifications-hero-inner">
            <div class="notifications-hero-icon"><i class="bi bi-bell-fill"></i></div>
            <div>
                <h1>Thông báo của bạn</h1>
                <p>Theo dõi cập nhật hệ thống, đơn hàng, quyên góp và chiến dịch mới một cách rõ ràng.</p>
            </div>
        </div>
    </section>

    <section class="container notifications-wrap">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="notifications-topbar">
                    <p class="notifications-unread">Bạn có <strong><?php echo $unreadCount; ?></strong> thông báo chưa đọc.</p>
                    <div class="d-flex gap-2">
                        <button class="noti-btn" id="resetFiltersBtn">
                            <i class="bi bi-arrow-clockwise me-1"></i>Đặt lại
                        </button>
                        <button class="noti-btn primary" id="markAllBtn">
                            <i class="bi bi-check2-all me-1"></i>Đánh dấu đã đọc tất cả
                        </button>
                    </div>
                </div>

                <article class="noti-card mb-4">
                    <div class="noti-card-body">
                        <form class="row gy-3" id="filterForm" method="GET">
                            <div class="col-md-3">
                                <label class="filter-label">Từ ngày</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="filter-label">Đến ngày</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="filter-label">Trạng thái</label>
                                <select class="form-select" name="status">
                                    <?php
                                    $statusOptions = [
                                        'all' => 'Tất cả',
                                        'unread' => 'Chưa đọc',
                                        'read' => 'Đã đọc'
                                    ];
                                    foreach ($statusOptions as $value => $label):
                                    ?>
                                        <option value="<?php echo $value; ?>" <?php echo $filters['status'] === $value ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="filter-label">Loại</label>
                                <select class="form-select" name="type">
                                    <?php
                                    $typeOptions = [
                                        '' => 'Tất cả',
                                        'system' => 'Hệ thống',
                                        'campaign' => 'Chiến dịch',
                                        'donation' => 'Quyên góp',
                                        'order' => 'Đơn hàng',
                                        'general' => 'Chung'
                                    ];
                                    foreach ($typeOptions as $value => $label):
                                    ?>
                                        <option value="<?php echo $value; ?>" <?php echo ($filters['type'] ?? '') === $value ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="noti-btn primary">
                                    <i class="bi bi-funnel me-1"></i>Áp dụng bộ lọc
                                </button>
                            </div>
                        </form>
                    </div>
                </article>

                <article class="noti-card">
                    <div class="noti-list" id="notificationList">
                        <?php if (empty($notifications)): ?>
                            <div class="noti-empty">
                                <i class="bi bi-inbox"></i>
                                Không có thông báo phù hợp.
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <?php
                                $isUnread = !$notification['is_read'];
                                $badgeClass = match ($notification['category']) {
                                    'campaign' => 'noti-warning',
                                    'donation' => 'noti-success',
                                    'order' => 'noti-info',
                                    default => 'noti-default'
                                };
                                ?>
                                <button type="button" class="list-group-item list-group-item-action d-flex gap-3 align-items-start notification-item <?php echo $isUnread ? 'unread' : ''; ?>"
                                    data-id="<?php echo $notification['notify_id']; ?>"
                                    data-title="<?php echo htmlspecialchars($notification['title']); ?>"
                                    data-message="<?php echo htmlspecialchars($notification['message']); ?>"
                                    data-time="<?php echo htmlspecialchars(formatDate($notification['created_at'])); ?>">
                                    <div class="notification-icon">
                                        <i class="bi bi-bell"></i>
                                    </div>
                                    <div class="flex-grow-1 text-start">
                                        <div class="d-flex justify-content-between mb-1 align-items-start gap-2">
                                            <h6 class="mb-0 <?php echo $isUnread ? 'fw-bold' : ''; ?>">
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                            </h6>
                                            <span class="badge noti-badge <?php echo $badgeClass; ?>">
                                                <?php echo ucfirst($notification['category']); ?>
                                            </span>
                                        </div>
                                        <p class="text-muted mb-1 small">
                                            <?php
                                            $preview = function_exists('mb_strimwidth')
                                                ? mb_strimwidth($notification['message'], 0, 120, '...')
                                                : substr($notification['message'], 0, 120) . (strlen($notification['message']) > 120 ? '...' : '');
                                            echo htmlspecialchars($preview);
                                            ?>
                                        </p>
                                        <small class="text-muted">
                                            <i class="bi bi-clock me-1"></i><?php echo htmlspecialchars(formatDate($notification['created_at'])); ?>
                                            <?php if ($isUnread): ?>
                                                <span class="ms-2 text-success">• Chưa đọc</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </article>

                <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php
                                $query = $_GET;
                                $query['page'] = $i;
                                ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo htmlspecialchars('?' . http_build_query($query)); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<!-- Detail Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết thông báo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h5 id="modalTitle"></h5>
                <p class="text-muted small mb-2" id="modalTime"></p>
                <p id="modalMessage" class="mb-0"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('notificationModal'));
    const notificationItems = document.querySelectorAll('.notification-item');
    const markAllBtn = document.getElementById('markAllBtn');
    const resetBtn = document.getElementById('resetFiltersBtn');
    const form = document.getElementById('filterForm');

    resetBtn?.addEventListener('click', function() {
        form.reset();
        window.location.href = 'notifications.php';
    });

    notificationItems.forEach(item => {
        item.addEventListener('click', function() {
            const id = this.dataset.id;
            fetch(`api/notifications.php?action=detail&id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.message || 'Không thể tải thông báo');
                    }
                    document.getElementById('modalTitle').textContent = data.data.title;
                    document.getElementById('modalTime').textContent = data.data.created_at;
                    document.getElementById('modalMessage').textContent = data.data.message;
                    this.classList.remove('unread');
                    const unreadFlag = this.querySelector('.text-success');
                    if (unreadFlag) unreadFlag.remove();
                    modal.show();
                })
                .catch(err => alert(err.message));
        });
    });

    markAllBtn?.addEventListener('click', function() {
        if (!confirm('Đánh dấu tất cả thông báo là đã đọc?')) {
            return;
        }
        fetch('api/notifications.php?action=mark-all', {
            method: 'POST'
        })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Không thể cập nhật thông báo');
                }
                window.location.reload();
            })
            .catch(err => alert(err.message));
    });
});
</script>

<?php include 'includes/footer.php'; ?>
