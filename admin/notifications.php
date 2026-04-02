<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/notifications_helper.php';

requireStaffOrAdmin();
enforceStaffPanelAccess(['orders']);
$panelType = 'orders';
processScheduledAdminNotifications();

$pageTitle = 'Trung tâm thông báo';
$errors = [];
$success = null;

$activeUsers = Database::fetchAll("SELECT user_id, name, email FROM users WHERE status = 'active' ORDER BY name ASC LIMIT 200");
$sendMode = $_POST['send_mode'] ?? 'now';
$sendTime = $_POST['send_time'] ?? '';
$selectedUsers = $_POST['target_users'] ?? [];
$selectedUsersMap = array_flip(array_map('strval', (array)$selectedUsers));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type = $_POST['type'] ?? 'system';
    $severity = $_POST['severity'] ?? 'info';
    $targetType = $_POST['target_type'] ?? 'all';
    if ($title === '') {
        $errors[] = 'Vui lòng nhập tiêu đề thông báo.';
    }
    if ($content === '') {
        $errors[] = 'Vui lòng nhập nội dung thông báo.';
    }
    if ($targetType === 'selected' && empty($selectedUsers)) {
        $errors[] = 'Vui lòng chọn ít nhất một người nhận.';
    }

    $scheduleDate = null;
    if ($sendMode === 'schedule') {
        if (empty($sendTime)) {
            $errors[] = 'Vui lòng chọn thời điểm gửi theo lịch.';
        } else {
            $timestamp = strtotime($sendTime);
            if ($timestamp && $timestamp > time()) {
                $scheduleDate = date('Y-m-d H:i:s', $timestamp);
            } else {
                $errors[] = 'Thời gian đặt lịch phải lớn hơn thời điểm hiện tại.';
            }
        }
    } else {
        $sendMode = 'now';
        $sendTime = '';
    }

    if (empty($errors)) {
        $payload = [
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'severity' => $severity,
            'target_type' => $targetType,
            'target_user_ids' => $targetType === 'selected' ? json_encode(array_map('intval', (array)$selectedUsers)) : null,
            'status' => $sendMode === 'schedule' ? 'scheduled' : 'sent',
            'scheduled_at' => $sendMode === 'schedule' ? $scheduleDate : null,
            'sent_at' => $sendMode === 'schedule' ? null : date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['user_id']
        ];

        $sendNow = $sendMode !== 'schedule';

        Database::execute(
            "INSERT INTO admin_notifications (title, content, type, severity, target_type, target_user_ids, status, scheduled_at, sent_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $payload['title'],
                $payload['content'],
                $payload['type'],
                $payload['severity'],
                $payload['target_type'],
                $payload['target_user_ids'],
                $payload['status'],
                $payload['scheduled_at'],
                $payload['sent_at'],
                $payload['created_by']
            ]
        );

        if ($sendNow) {
            $userIds = resolveNotificationTargetUsers($targetType, $selectedUsers);
            dispatchNotificationBatch($userIds, [
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'severity' => $severity,
                'sent_by' => $_SESSION['user_id']
            ]);
        }

        $success = $sendNow ? 'Đã gửi thông báo thành công.' : 'Đã đặt lịch thông báo thành công.';
        if ($success) {
            $sendMode = 'now';
            $sendTime = '';
            $selectedUsers = [];
            $selectedUsersMap = [];
        }
    }
}

$history = Database::fetchAll("
    SELECT an.*, u.name AS creator_name
    FROM admin_notifications an
    LEFT JOIN users u ON u.user_id = an.created_by
    ORDER BY an.created_at DESC
    LIMIT 25
");

$statsRow = Database::fetch("SELECT COUNT(*) AS total, SUM(status = 'sent') AS sent_count, SUM(status = 'scheduled') AS scheduled_count FROM admin_notifications");
$totalNotifications = (int)($statsRow['total'] ?? 0);
$sentNotifications = (int)($statsRow['sent_count'] ?? 0);
$scheduledNotifications = (int)($statsRow['scheduled_count'] ?? 0);
$activeUsersCount = count($activeUsers);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Goodwill Vietnam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --brand-700: #0e7490;
            --brand-500: #06b6d4;
            --brand-50: #ecfeff;
            --ink-900: #23324a;
            --ink-500: #62718a;
            --line: #d4e8f0;
        }

        body {
            background: #f3f9fc;
        }

        .notifications-topbar {
            background: linear-gradient(140deg, #f7fcfe 0%, #ecf7fb 100%);
            border: 1px solid #d7edf3;
            border-radius: 22px;
            padding: 1rem 1.45rem;
            color: #0f172a;
            margin-top: 0.35rem;
            margin-bottom: 1.2rem;
            box-shadow: 0 10px 24px rgba(8, 74, 92, 0.07);
        }

        .notifications-topbar h1 {
            font-size: clamp(2rem, 3vw, 3.4rem);
            font-weight: 800;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.7rem;
            line-height: 1.05;
            letter-spacing: .2px;
            color: #0f172a;
        }

        .notifications-topbar p {
            margin-top: 0.45rem;
            margin-bottom: 0;
            font-size: clamp(0.95rem, 1.2vw, 1.08rem);
            color: #64748b;
            opacity: 1;
        }

        .notifications-topbar h1 i {
            font-size: 0.9em;
        }

        @media (max-width: 767.98px) {
            .notifications-topbar {
                padding: 1rem;
            }

            .notifications-topbar h1 {
                font-size: 2rem;
            }
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 1rem;
        }

        .overview-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(14, 116, 144, .08);
            padding: 14px 16px;
        }

        .overview-card .label {
            display: block;
            color: var(--ink-500);
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 6px;
            font-weight: 700;
        }

        .overview-card .value {
            color: #0f172a;
            font-size: 1.35rem;
            font-weight: 800;
        }

        .notify-card {
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(14, 116, 144, .07);
            background: #fff;
        }

        .notify-card-header {
            background: #f9feff;
            border-bottom: 1px solid var(--line);
            padding: 14px 20px;
        }

        .notify-card-header h5 {
            margin: 0;
            font-weight: 700;
            color: var(--ink-900);
            font-size: 1rem;
        }

        .notify-card-header small {
            color: var(--ink-500);
            font-size: .8rem;
        }

        .notify-card-body {
            padding: 20px;
        }

        .notify-card-body .form-label {
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--ink-500);
            margin-bottom: 6px;
        }

        .notify-card-body .form-control,
        .notify-card-body .form-select,
        .notify-card-body textarea {
            border-color: var(--line);
            border-radius: 10px;
            font-size: .9rem;
        }

        .notify-card-body .form-control:focus,
        .notify-card-body .form-select:focus,
        .notify-card-body textarea:focus {
            border-color: var(--brand-500);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, .15);
        }

        .btn-notify-submit {
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            color: #fff;
            font-weight: 600;
            padding: 9px 18px;
        }

        .btn-notify-submit:hover {
            opacity: .92;
            color: #fff;
        }

        .muted-note {
            color: var(--ink-500);
            font-size: .8rem;
        }

        @media (max-width: 991.98px) {
            .overview-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .overview-grid {
                grid-template-columns: 1fr;
            }
        }

        .history-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
        }

        .history-table thead th {
            background: linear-gradient(135deg, #083344 0%, #0e7490 100%);
            color: rgba(255, 255, 255, .76);
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            border: none;
            padding: 14px 14px;
            white-space: nowrap;
        }

        .history-table tbody td {
            border-bottom: 1px solid #edf5f8;
            vertical-align: middle;
            color: var(--ink-900);
            padding: 13px 14px;
            font-size: .88rem;
        }

        .history-table tbody tr:hover {
            background: #f0fbfe;
        }

        .history-table tbody tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            border-radius: 999px;
            padding: 4px 11px;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .03em;
        }

        .status-badge.bg-success {
            background: rgba(22, 163, 74, .13) !important;
            color: #166534;
        }

        .status-badge.bg-warning {
            background: rgba(234, 179, 8, .16) !important;
            color: #854d0e;
        }

        .status-badge.bg-secondary {
            background: #eef2f7 !important;
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php
                if (isStaff() && !isAdmin()) {
                    include 'includes/staff-sidebar.php';
                } else {
                    include 'includes/sidebar.php';
                }
            ?>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="notifications-topbar">
                    <h1><i class="bi bi-broadcast-pin me-2"></i>Trung tâm thông báo</h1>
                    <p>Tạo thông báo chuyên nghiệp cho toàn hệ thống, gửi ngay hoặc lên lịch theo thời điểm bạn mong muốn.</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <div class="overview-grid">
                    <div class="overview-card">
                        <span class="label">Tổng thông báo</span>
                        <div class="value"><?php echo number_format($totalNotifications); ?></div>
                    </div>
                    <div class="overview-card">
                        <span class="label">Đã gửi</span>
                        <div class="value"><?php echo number_format($sentNotifications); ?></div>
                    </div>
                    <div class="overview-card">
                        <span class="label">Đặt lịch</span>
                        <div class="value"><?php echo number_format($scheduledNotifications); ?></div>
                    </div>
                    <div class="overview-card">
                        <span class="label">Người dùng nhận</span>
                        <div class="value"><?php echo number_format($activeUsersCount); ?></div>
                    </div>
                </div>

                <div class="notify-card mb-4">
                    <div class="notify-card-header">
                        <h5 class="mb-0">Soạn và gửi thông báo</h5>
                        <small>Thông điệp sẽ xuất hiện tại trung tâm thông báo của người dùng.</small>
                    </div>
                    <div class="notify-card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Tiêu đề</label>
                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Loại thông báo</label>
                                <select name="type" class="form-select">
                                    <option value="system" <?php echo ($_POST['type'] ?? 'system') === 'system' ? 'selected' : ''; ?>>Hệ thống</option>
                                    <option value="campaign" <?php echo ($_POST['type'] ?? '') === 'campaign' ? 'selected' : ''; ?>>Chiến dịch</option>
                                    <option value="donation" <?php echo ($_POST['type'] ?? '') === 'donation' ? 'selected' : ''; ?>>Quyên góp</option>
                                    <option value="order" <?php echo ($_POST['type'] ?? '') === 'order' ? 'selected' : ''; ?>>Đơn hàng</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Mức độ</label>
                                <select name="severity" class="form-select">
                                    <option value="info" <?php echo ($_POST['severity'] ?? 'info') === 'info' ? 'selected' : ''; ?>>Thông tin</option>
                                    <option value="success" <?php echo ($_POST['severity'] ?? '') === 'success' ? 'selected' : ''; ?>>Thành công</option>
                                    <option value="warning" <?php echo ($_POST['severity'] ?? '') === 'warning' ? 'selected' : ''; ?>>Cảnh báo</option>
                                    <option value="error" <?php echo ($_POST['severity'] ?? '') === 'error' ? 'selected' : ''; ?>>Khẩn cấp</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Nội dung</label>
                                <textarea name="content" class="form-control" rows="4" required><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Đối tượng nhận</label>
                                <select name="target_type" id="targetType" class="form-select">
                                    <option value="all" <?php echo ($_POST['target_type'] ?? 'all') === 'all' ? 'selected' : ''; ?>>Tất cả người dùng</option>
                                    <option value="selected" <?php echo ($_POST['target_type'] ?? '') === 'selected' ? 'selected' : ''; ?>>Chọn thủ công</option>
                                </select>
                            </div>
                            <div class="col-md-8" id="userSelectWrapper" style="display:none;">
                                <label class="form-label">Người nhận cụ thể</label>
                                <select name="target_users[]" class="form-select" multiple>
                                    <?php foreach ($activeUsers as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>" <?php echo isset($selectedUsersMap[(string)$user['user_id']]) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="muted-note">Giữ Ctrl (hoặc Command trên macOS) để chọn nhiều người.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label d-block">Hình thức gửi</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="send_mode" id="sendModeNow" value="now" <?php echo $sendMode === 'schedule' ? '' : 'checked'; ?>>
                                    <label class="form-check-label" for="sendModeNow">Gửi ngay</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="send_mode" id="sendModeSchedule" value="schedule" <?php echo $sendMode === 'schedule' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sendModeSchedule">Đặt lịch</label>
                                </div>
                            </div>
                            <div class="col-md-4" id="scheduleWrapper" style="<?php echo $sendMode === 'schedule' ? '' : 'display:none;'; ?>">
                                <label class="form-label">Thời gian gửi</label>
                                <input type="datetime-local" name="send_time" id="scheduleInput" class="form-control" value="<?php echo htmlspecialchars($sendTime); ?>" <?php echo $sendMode === 'schedule' ? '' : 'disabled'; ?>>
                                <small class="muted-note">Hệ thống sẽ tự động gửi khi đến đúng thời gian này.</small>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn-notify-submit">
                                    <i class="bi bi-send me-1"></i>Gửi thông báo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="notify-card">
                    <div class="notify-card-header">
                        <h5 class="mb-0">Lịch sử thông báo gần đây</h5>
                        <small>Hiển thị 25 thông báo mới nhất từ quản trị viên.</small>
                    </div>
                    <div class="table-responsive">
                        <table class="history-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tiêu đề</th>
                                    <th>Loại</th>
                                    <th>Đối tượng</th>
                                    <th>Trạng thái</th>
                                    <th>Lịch gửi</th>
                                    <th>Đã gửi lúc</th>
                                    <th>Người tạo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($history)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">Chưa có thông báo nào.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($history as $row): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($row['title']); ?></strong>
                                                <div class="text-muted small">
                                                    <?php
                                                    $snippet = function_exists('mb_substr') ? mb_substr($row['content'], 0, 80) : substr($row['content'], 0, 80);
                                                    $contentLength = function_exists('mb_strlen') ? mb_strlen($row['content']) : strlen($row['content']);
                                                    if ($contentLength > 80) {
                                                        $snippet .= '...';
                                                    }
                                                    echo htmlspecialchars($snippet);
                                                    ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $typeMap = [
                                                    'system' => 'Hệ thống',
                                                    'campaign' => 'Chiến dịch',
                                                    'donation' => 'Quyên góp',
                                                    'order' => 'Đơn hàng'
                                                ];
                                                echo htmlspecialchars($typeMap[$row['type']] ?? ucfirst((string)$row['type']));
                                                ?>
                                            </td>
                                            <td><?php echo $row['target_type'] === 'all' ? 'Tất cả' : 'Chọn thủ công'; ?></td>
                                            <td>
                                                <span class="badge status-badge bg-<?php echo $row['status'] === 'sent' ? 'success' : ($row['status'] === 'scheduled' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo $row['status'] === 'sent' ? 'Đã gửi' : ($row['status'] === 'scheduled' ? 'Đã lên lịch' : 'Bản nháp'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $row['scheduled_at'] ? formatDate($row['scheduled_at']) : '-'; ?></td>
                                            <td><?php echo $row['sent_at'] ? formatDate($row['sent_at']) : '-'; ?></td>
                                            <td><?php echo htmlspecialchars($row['creator_name'] ?? 'Hệ thống'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/data-refresh.js" data-base="../" data-interval="5000"></script>
    <script>
        const targetSelect = document.getElementById('targetType');
        const userWrapper = document.getElementById('userSelectWrapper');
        const sendModeRadios = document.querySelectorAll('input[name="send_mode"]');
        const scheduleWrapper = document.getElementById('scheduleWrapper');
        const scheduleInput = document.getElementById('scheduleInput');

        const toggleUserSelect = () => {
            userWrapper.style.display = targetSelect.value === 'selected' ? '' : 'none';
        };

        const toggleSchedule = () => {
            const selectedMode = document.querySelector('input[name="send_mode"]:checked');
            const isSchedule = selectedMode && selectedMode.value === 'schedule';
            if (scheduleWrapper) {
                scheduleWrapper.style.display = isSchedule ? '' : 'none';
            }
            if (scheduleInput) {
                scheduleInput.disabled = !isSchedule;
            }
        };

        targetSelect.addEventListener('change', toggleUserSelect);
        sendModeRadios.forEach(radio => radio.addEventListener('change', toggleSchedule));

        toggleUserSelect();
        toggleSchedule();
    </script>
</body>
</html>
