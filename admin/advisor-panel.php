<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();

$pageTitle = 'Panel tư vấn viên';
$panelType = 'support';

if (isStaff() && !isAdmin() && getStaffPanelKey() !== 'support') {
    header('Location: ../staff-panel.php');
    exit();
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$staff = Database::fetch(
    "SELECT staff_id FROM staff WHERE user_id = ? AND status = 'active'",
    [$currentUserId]
);

$staffId = (int)($staff['staff_id'] ?? 0);

$stats = [
    'open_chats' => 0,
    'customers' => 0,
    'waiting_reply' => 0,
    'messages_today' => 0
];

$chats = [];
$dailyChatStats = [];

if ($staffId > 0) {
    try {
        $openRow = Database::fetch(
            "SELECT COUNT(*) AS total FROM chat_sessions WHERE status = 'open'"
        );
        $stats['open_chats'] = (int)($openRow['total'] ?? 0);
    } catch (Exception $e) {
    }

    try {
        $customerRow = Database::fetch(
            "SELECT COUNT(DISTINCT COALESCE(CAST(user_id AS CHAR), guest_token)) AS total
             FROM chat_sessions
             WHERE status = 'open'"
        );
        $stats['customers'] = (int)($customerRow['total'] ?? 0);
    } catch (Exception $e) {
    }

    try {
        $waitingRow = Database::fetch(
            "SELECT COUNT(*) AS total
             FROM chat_sessions cs
             JOIN (
                SELECT cm.chat_id, cm.sender_type
                FROM chat_messages cm
                JOIN (
                    SELECT chat_id, MAX(message_id) AS max_message_id
                    FROM chat_messages
                    GROUP BY chat_id
                ) last_message ON last_message.max_message_id = cm.message_id
             ) lm ON lm.chat_id = cs.chat_id
             WHERE cs.status = 'open'
               AND lm.sender_type = 'user'"
        );
        $stats['waiting_reply'] = (int)($waitingRow['total'] ?? 0);
    } catch (Exception $e) {
    }

    try {
        $todayRow = Database::fetch(
            "SELECT COUNT(*) AS total
             FROM chat_messages cm
             JOIN chat_sessions cs ON cs.chat_id = cm.chat_id
             WHERE DATE(cm.created_at) = CURDATE()"
        );
        $stats['messages_today'] = (int)($todayRow['total'] ?? 0);
    } catch (Exception $e) {
    }

    try {
        $chats = Database::fetchAll(
            "SELECT
                cs.chat_id,
                cs.user_id,
                cs.guest_token,
                cs.status,
                cs.last_message_at,
                cs.created_at,
                COALESCE(u.name, 'Khách hàng') AS customer_name,
                COALESCE(u.email, cs.guest_token, 'Ẩn danh') AS customer_email,
                (
                    SELECT COUNT(*)
                    FROM chat_messages cm_total
                    WHERE cm_total.chat_id = cs.chat_id
                ) AS message_count,
                (
                    SELECT cm_last.message
                    FROM chat_messages cm_last
                    WHERE cm_last.chat_id = cs.chat_id
                    ORDER BY cm_last.message_id DESC
                    LIMIT 1
                ) AS last_message,
                (
                    SELECT cm_last.sender_type
                    FROM chat_messages cm_last
                    WHERE cm_last.chat_id = cs.chat_id
                    ORDER BY cm_last.message_id DESC
                    LIMIT 1
                ) AS last_sender,
                (
                    SELECT COUNT(*)
                    FROM chat_messages cm_user
                    WHERE cm_user.chat_id = cs.chat_id
                      AND cm_user.sender_type = 'user'
                      AND cm_user.message_id > COALESCE((
                            SELECT MAX(cm_staff.message_id)
                            FROM chat_messages cm_staff
                            WHERE cm_staff.chat_id = cs.chat_id
                              AND cm_staff.sender_type = 'staff'
                      ), 0)
                ) AS unread_count
             FROM chat_sessions cs
             LEFT JOIN users u ON cs.user_id = u.user_id
             WHERE cs.status = 'open'
             ORDER BY COALESCE(cs.last_message_at, cs.created_at) DESC, cs.created_at DESC"
        );
    } catch (Exception $e) {
        $chats = [];
    }

    try {
        $dailyChatStats = Database::fetchAll(
            "SELECT
                DATE(cm.created_at) AS chat_date,
                COUNT(DISTINCT cm.chat_id) AS total_chats,
                COUNT(*) AS total_messages,
                SUM(cm.sender_type = 'user') AS user_messages,
                SUM(cm.sender_type = 'staff') AS staff_messages
             FROM chat_messages cm
             JOIN chat_sessions cs ON cs.chat_id = cm.chat_id
             WHERE cs.staff_id = ?
               AND cm.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
             GROUP BY DATE(cm.created_at)
             ORDER BY chat_date DESC",
            [$staffId]
        );
    } catch (Exception $e) {
        $dailyChatStats = [];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Goodwill Vietnam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body { background: #f3f9fc; }
        .admin-content { padding-top: 1rem; padding-bottom: 1.5rem; }

        .advisor-topbar {
            background: transparent;
            border: 0;
            border-radius: 16px;
            padding: 0.15rem 0 0.25rem;
            color: #0f172a;
            margin-top: 0.2rem;
            margin-bottom: 1rem;
            box-shadow: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .advisor-head {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }

        .advisor-head-icon {
            width: 74px;
            height: 74px;
            border-radius: 18px;
            background: linear-gradient(145deg, #0b728c, #095f75);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(8, 74, 92, 0.23);
            flex-shrink: 0;
        }

        .advisor-head-icon i {
            font-size: 2rem;
            line-height: 1;
        }

        .advisor-head-title {
            margin: 0;
            color: #0f172a;
            font-weight: 900;
            letter-spacing: 0.1px;
            font-size: clamp(1.7rem, 2.8vw, 2.9rem);
            line-height: 1.08;
        }

        .advisor-note {
            color: #58718a;
            font-size: clamp(1rem, 1.35vw, 1.14rem);
            margin-top: 0.35rem;
            line-height: 1.25;
        }

        .btn-advisor-refresh {
            background: linear-gradient(135deg, #0e7490, #06B6D4);
            color: #fff;
            border: 0;
            border-radius: 14px;
            font-weight: 700;
            min-height: 54px;
            padding: 0.75rem 1rem;
        }

        .btn-advisor-refresh:hover {
            color: #fff;
            filter: brightness(0.97);
        }

        .stat-card {
            border: 1px solid #d7edf3;
            border-radius: 16px;
            box-shadow: 0 10px 24px rgba(8, 74, 92, 0.08);
            background: #fff;
            height: 100%;
        }

        .stat-card .card-body {
            padding: 1rem 1.1rem;
        }

        .stat-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
            color: #0b728c;
            margin-bottom: 0.28rem;
        }

        .stat-value {
            font-size: 1.65rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-open .stat-icon { background: #e0f4fa; color: #0891b2; }
        .stat-customer .stat-icon { background: #dcfce7; color: #059669; }
        .stat-waiting .stat-icon { background: #fef3c7; color: #d97706; }
        .stat-today .stat-icon { background: #dbeafe; color: #2563eb; }

        .dashboard-card {
            border: 1px solid #d7edf3;
            border-radius: 16px;
            box-shadow: 0 10px 24px rgba(8, 74, 92, 0.08);
            overflow: hidden;
            background: #fff;
        }

        .dashboard-card-header {
            background: linear-gradient(140deg, #fbfeff 0%, #edf8fb 100%);
            border-bottom: 1px solid #d7edf3;
            padding: 0.82rem 1rem;
        }

        .dashboard-card-title {
            font-weight: 700;
            color: #0b728c;
            margin: 0;
        }

        .chat-list-wrap {
            max-height: 64vh;
            overflow-y: auto;
            padding: 0.85rem;
            background: #f8fcfe;
        }

        .chat-user-card {
            width: 100%;
            border: 1px solid #d4e8ef;
            border-radius: 12px;
            padding: 0.72rem 0.75rem;
            margin-bottom: 0.62rem;
            background: #fff;
            text-align: left;
            transition: border-color .2s ease, transform .15s ease, box-shadow .2s ease;
        }

        .chat-user-card:hover {
            border-color: #8ecedf;
            box-shadow: 0 8px 16px rgba(8, 74, 92, 0.08);
            transform: translateY(-1px);
        }

        .chat-user-card.active {
            border-color: #2ea8c4;
            box-shadow: 0 0 0 2px rgba(46, 168, 196, 0.15);
            background: #f2fbff;
        }

        .chat-name {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f2a3a;
        }

        .chat-meta {
            font-size: 0.8rem;
            color: #5f7588;
            margin-top: 0.18rem;
        }

        .chat-last {
            font-size: 0.84rem;
            color: #334e62;
            margin-top: 0.42rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.6rem;
            height: 1.6rem;
            border-radius: 999px;
            background: #0ea5b7;
            color: #fff;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0 .45rem;
        }

        .chat-notify-dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #ef4444;
            display: inline-block;
            box-shadow: 0 0 0 2px #fff;
        }

        .daily-stats-wrap {
            max-height: 32vh;
            overflow-y: auto;
            padding: 0.8rem;
            background: #f8fcfe;
        }

        .daily-stat-item {
            border: 1px solid #d9ebf1;
            background: #fff;
            border-radius: 12px;
            padding: 0.65rem 0.72rem;
            margin-bottom: 0.58rem;
        }

        .daily-stat-item:last-child {
            margin-bottom: 0;
        }

        .daily-date {
            color: #0f2a3a;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .daily-meta {
            font-size: 0.8rem;
            color: #5d7488;
            margin-top: 0.22rem;
        }

        .chat-window {
            display: flex;
            flex-direction: column;
            min-height: 64vh;
        }

        .chat-window-head {
            border-bottom: 1px solid #d7edf3;
            padding: 0.85rem 1rem;
            background: linear-gradient(140deg, #fbfeff 0%, #edf8fb 100%);
        }

        .chat-window-title {
            font-size: 1rem;
            font-weight: 700;
            color: #0f2a3a;
            margin: 0;
        }

        .chat-window-subtitle {
            margin: 0.2rem 0 0;
            color: #5e7588;
            font-size: 0.84rem;
        }

        .messages-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: #f7fbfd;
        }

        .msg-row {
            margin-bottom: 0.78rem;
            display: flex;
            flex-direction: column;
        }

        .msg-row.staff {
            align-items: flex-end;
        }

        .msg-row.user {
            align-items: flex-start;
        }

        .msg-bubble {
            max-width: 78%;
            border-radius: 14px;
            padding: 0.55rem 0.72rem;
            font-size: 0.9rem;
            line-height: 1.38;
            border: 1px solid transparent;
            white-space: pre-wrap;
        }

        .msg-row.staff .msg-bubble {
            background: #0ea5b7;
            border-color: #0ea5b7;
            color: #fff;
        }

        .msg-row.user .msg-bubble {
            background: #fff;
            border-color: #d5e9f0;
            color: #1f3e52;
        }

        .msg-time {
            margin-top: 0.2rem;
            font-size: 0.75rem;
            color: #73879a;
        }

        .msg-typing {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            border: 1px solid #d5e9f0;
            border-radius: 14px;
            padding: 0.45rem 0.62rem;
            width: fit-content;
        }

        .msg-typing-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #111;
            opacity: 0.42;
            animation: advisorTypingPulse 1s infinite ease-in-out;
        }

        .msg-typing-dot:nth-child(2) {
            animation-delay: 0.14s;
        }

        .msg-typing-dot:nth-child(3) {
            animation-delay: 0.28s;
        }

        @keyframes advisorTypingPulse {
            0%, 80%, 100% {
                transform: translateY(0);
                opacity: 0.35;
            }

            40% {
                transform: translateY(-3px);
                opacity: 1;
            }
        }

        .chat-input-wrap {
            border-top: 1px solid #d7edf3;
            padding: 0.85rem 1rem;
            background: #fff;
        }

        .chat-empty {
            height: 100%;
            min-height: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            text-align: center;
            color: #637a8e;
            gap: 0.45rem;
        }

        .chat-empty i {
            font-size: 2.4rem;
            color: #94b7c4;
        }

        @media (max-width: 991.98px) {
            .advisor-head-icon {
                width: 56px;
                height: 56px;
                border-radius: 14px;
            }

            .advisor-head-icon i { font-size: 1.45rem; }
            .chat-list-wrap,
            .chat-window { min-height: 52vh; max-height: none; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/staff-sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="advisor-topbar">
                    <div class="advisor-head">
                        <div class="advisor-head-icon">
                            <i class="bi bi-chat-heart-fill"></i>
                        </div>
                        <div>
                            <h1 class="advisor-head-title">Panel Tư vấn viên</h1>
                            <div class="advisor-note">Mỗi user một ô chat. Chọn user để hỗ trợ trực tiếp ngay tại đây.</div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-advisor-refresh" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Làm mới danh sách
                    </button>
                </div>

                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card stat-open">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Cuộc chat đang mở</div>
                                    <div class="stat-value" id="statOpenChats"><?php echo number_format($stats['open_chats']); ?></div>
                                </div>
                                <i class="bi bi-chat-left-dots-fill stat-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card stat-customer">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Người dùng đang chat</div>
                                    <div class="stat-value" id="statCustomers"><?php echo number_format($stats['customers']); ?></div>
                                </div>
                                <i class="bi bi-people-fill stat-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card stat-waiting">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">User chờ phản hồi</div>
                                    <div class="stat-value" id="statWaiting"><?php echo number_format($stats['waiting_reply']); ?></div>
                                </div>
                                <i class="bi bi-hourglass-split stat-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card stat-today">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="stat-label">Tin nhắn hôm nay</div>
                                    <div class="stat-value" id="statToday"><?php echo number_format($stats['messages_today']); ?></div>
                                </div>
                                <i class="bi bi-chat-text-fill stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-4 mb-4">
                        <div class="card dashboard-card">
                            <div class="dashboard-card-header">
                                <h6 class="dashboard-card-title">Danh sách ô chat theo user</h6>
                            </div>
                            <div class="chat-list-wrap" id="chatListWrap">
                                <?php if (empty($chats)): ?>
                                    <div class="chat-empty">
                                        <i class="bi bi-chat-left-text"></i>
                                        <div>Chưa có user nào đang chat.</div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($chats as $chat): ?>
                                        <?php
                                            $timeValue = $chat['last_message_at'] ?? $chat['created_at'];
                                            $timeText = !empty($timeValue) ? date('H:i d/m', strtotime((string)$timeValue)) : '--';
                                            $lastMessage = trim((string)($chat['last_message'] ?? 'Chưa có tin nhắn'));
                                            if ($lastMessage === '') {
                                                $lastMessage = 'Chưa có tin nhắn';
                                            }
                                            $isWaiting = ((int)($chat['unread_count'] ?? 0)) > 0;
                                        ?>
                                        <button
                                            type="button"
                                            class="chat-user-card"
                                            data-chat-id="<?php echo (int)$chat['chat_id']; ?>"
                                            data-user-name="<?php echo htmlspecialchars((string)$chat['customer_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-user-email="<?php echo htmlspecialchars((string)$chat['customer_email'], ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            <div class="d-flex align-items-start justify-content-between gap-2">
                                                <div class="flex-grow-1">
                                                    <p class="chat-name mb-0"><?php echo htmlspecialchars((string)$chat['customer_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <div class="chat-meta"><?php echo htmlspecialchars((string)$chat['customer_email'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?php if ($isWaiting): ?>
                                                        <span class="chat-notify-dot" title="Có tin nhắn mới"></span>
                                                    <?php endif; ?>
                                                    <span class="chat-pill"><?php echo (int)$chat['message_count']; ?></span>
                                                </div>
                                            </div>
                                            <div class="chat-last">
                                                <?php if ($isWaiting): ?>
                                                    <span class="text-danger fw-semibold">Tin nhắn mới:</span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars(mb_substr($lastMessage, 0, 70, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                            <div class="chat-meta"><?php echo htmlspecialchars($timeText, ENT_QUOTES, 'UTF-8'); ?></div>
                                        </button>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card dashboard-card mt-3">
                            <div class="dashboard-card-header">
                                <h6 class="dashboard-card-title">Thống kê chat theo ngày</h6>
                            </div>
                            <div class="daily-stats-wrap" id="dailyStatsWrap">
                                <?php if (empty($dailyChatStats)): ?>
                                    <div class="text-muted small">Chưa có dữ liệu chat trong 7 ngày gần đây.</div>
                                <?php else: ?>
                                    <?php foreach ($dailyChatStats as $daily): ?>
                                        <div class="daily-stat-item">
                                            <div class="daily-date"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string)$daily['chat_date'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="daily-meta">
                                                <?php echo (int)$daily['total_chats']; ?> cuộc chat | <?php echo (int)$daily['total_messages']; ?> tin nhắn
                                            </div>
                                            <div class="daily-meta">
                                                User: <?php echo (int)$daily['user_messages']; ?> | Tư vấn: <?php echo (int)$daily['staff_messages']; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8 mb-4">
                        <div class="card dashboard-card chat-window" id="chatWindow">
                            <div class="chat-window-head">
                                <h6 class="chat-window-title" id="chatTitle">Chọn user để bắt đầu chat</h6>
                                <p class="chat-window-subtitle" id="chatSubtitle">Bạn sẽ thấy toàn bộ tin nhắn ở đây</p>
                            </div>
                            <div class="messages-body" id="messagesContainer">
                                <div class="chat-empty" id="chatEmpty">
                                    <i class="bi bi-chat-right-heart"></i>
                                    <div>Chọn một ô chat bên trái để mở hội thoại với user.</div>
                                </div>
                            </div>
                            <div class="chat-input-wrap" id="chatInputWrap" style="display:none;">
                                <form id="chatForm" class="d-flex gap-2">
                                    <textarea
                                        class="form-control"
                                        id="messageInput"
                                        placeholder="Nhập phản hồi cho user..."
                                        autocomplete="off"
                                        rows="1"
                                        style="resize: none; min-height: 38px; max-height: 120px;"
                                        required
                                    ></textarea>
                                    <button type="submit" class="btn btn-primary px-3" tabindex="-1">
                                        <i class="bi bi-send-fill"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentChatId = null;
        let messageRefreshTimer = null;
        let listRefreshTimer = null;
        let typingSendTimer = null;
        let typingIdleTimer = null;
        let currentTypingNode = null;
        let lastMessageSignature = '';

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            };
            return String(text || '').replace(/[&<>"']/g, m => map[m]);
        }

        function formatDateTime(value) {
            if (!value) return '--';
            const dt = new Date(value);
            if (Number.isNaN(dt.getTime())) return '--';
            const hour = String(dt.getHours()).padStart(2, '0');
            const minute = String(dt.getMinutes()).padStart(2, '0');
            const day = String(dt.getDate()).padStart(2, '0');
            const month = String(dt.getMonth() + 1).padStart(2, '0');
            return `${hour}:${minute} ${day}/${month}`;
        }

        function buildChatCard(chat) {
            const lastMessage = String(chat.last_message || 'Chưa có tin nhắn').trim() || 'Chưa có tin nhắn';
            const unreadCount = Number(chat.unread_count || 0);
            const userName = escapeHtml(chat.customer_name || 'Khách hàng');
            const userEmail = escapeHtml(chat.customer_email || 'Ẩn danh');
            const shortMessage = escapeHtml(lastMessage.slice(0, 70));
            const timeText = escapeHtml(formatDateTime(chat.last_message_at || chat.created_at));

            return `
                <button
                    type="button"
                    class="chat-user-card"
                    data-chat-id="${Number(chat.chat_id || 0)}"
                    data-user-name="${userName}"
                    data-user-email="${userEmail}"
                >
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="flex-grow-1">
                            <p class="chat-name mb-0">${userName}</p>
                            <div class="chat-meta">${userEmail}</div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            ${unreadCount > 0 ? '<span class="chat-notify-dot" title="Có tin nhắn mới"></span>' : ''}
                            <span class="chat-pill">${Number(chat.message_count || 0)}</span>
                        </div>
                    </div>
                    <div class="chat-last">
                        ${unreadCount > 0 ? '<span class="text-danger fw-semibold">Tin nhắn mới:</span> ' : ''}
                        ${shortMessage}
                    </div>
                    <div class="chat-meta">${timeText}</div>
                </button>
            `;
        }

        function attachChatCardEvents() {
            const cards = document.querySelectorAll('.chat-user-card');
            cards.forEach(card => {
                card.addEventListener('click', () => {
                    openChat(card);
                });
            });
        }

        function updateStats(stats) {
            if (!stats) return;
            const openEl = document.getElementById('statOpenChats');
            const customerEl = document.getElementById('statCustomers');
            const waitingEl = document.getElementById('statWaiting');
            const todayEl = document.getElementById('statToday');
            if (openEl) openEl.textContent = Number(stats.open_chats || 0).toLocaleString('vi-VN');
            if (customerEl) customerEl.textContent = Number(stats.customers || 0).toLocaleString('vi-VN');
            if (waitingEl) waitingEl.textContent = Number(stats.waiting_reply || 0).toLocaleString('vi-VN');
            if (todayEl) todayEl.textContent = Number(stats.messages_today || 0).toLocaleString('vi-VN');
        }

        function renderDailyStats(dailyStats) {
            const wrap = document.getElementById('dailyStatsWrap');
            if (!wrap) return;

            if (!Array.isArray(dailyStats) || dailyStats.length === 0) {
                wrap.innerHTML = '<div class="text-muted small">Chưa có dữ liệu chat trong 7 ngày gần đây.</div>';
                return;
            }

            wrap.innerHTML = dailyStats.map(daily => {
                const raw = String(daily.chat_date || '');
                const parts = raw.split('-');
                const day = parts.length === 3
                    ? `${parts[2]}/${parts[1]}/${parts[0]}`
                    : '--/--/----';
                return `
                    <div class="daily-stat-item">
                        <div class="daily-date">${escapeHtml(day)}</div>
                        <div class="daily-meta">${Number(daily.total_chats || 0)} cuộc chat | ${Number(daily.total_messages || 0)} tin nhắn</div>
                        <div class="daily-meta">User: ${Number(daily.user_messages || 0)} | Tư vấn: ${Number(daily.staff_messages || 0)}</div>
                    </div>
                `;
            }).join('');
        }

        async function refreshChatList() {
            const response = await fetch('/api/chat-advisor-list.php', { cache: 'no-store' });
            const data = await response.json();
            if (!data.success) {
                return;
            }

            const wrap = document.getElementById('chatListWrap');
            if (!wrap) return;

            const chats = Array.isArray(data.chats) ? data.chats : [];
            if (chats.length === 0) {
                wrap.innerHTML = '<div class="chat-empty"><i class="bi bi-chat-left-text"></i><div>Chưa có user nào đang chat.</div></div>';
            } else {
                wrap.innerHTML = chats.map(buildChatCard).join('');
                attachChatCardEvents();
                if (currentChatId) {
                    setActiveChatCard(currentChatId);
                }
            }

            updateStats(data.stats || null);
            renderDailyStats(data.daily_stats || []);
        }

        function showUserTyping(isTyping) {
            const container = document.getElementById('messagesContainer');
            if (!container) {
                return;
            }

            if (isTyping) {
                if (currentTypingNode) {
                    return;
                }

                const row = document.createElement('div');
                row.className = 'msg-row user';
                row.setAttribute('id', 'userTypingRow');

                const typing = document.createElement('div');
                typing.className = 'msg-typing';
                typing.innerHTML = '<span class="msg-typing-dot"></span><span class="msg-typing-dot"></span><span class="msg-typing-dot"></span>';
                row.appendChild(typing);
                container.appendChild(row);
                container.scrollTop = container.scrollHeight;
                currentTypingNode = row;
            } else if (currentTypingNode && currentTypingNode.parentNode) {
                currentTypingNode.parentNode.removeChild(currentTypingNode);
                currentTypingNode = null;
            }
        }

        async function notifyTyping(isTyping) {
            if (!currentChatId) {
                return;
            }

            const formData = new FormData();
            formData.append('chat_id', String(currentChatId));
            formData.append('is_typing', isTyping ? '1' : '0');
            await fetch('/api/chat-typing.php', {
                method: 'POST',
                body: formData
            });
        }

        function setActiveChatCard(chatId) {
            document.querySelectorAll('.chat-user-card').forEach(card => {
                if (parseInt(card.getAttribute('data-chat-id'), 10) === chatId) {
                    card.classList.add('active');
                } else {
                    card.classList.remove('active');
                }
            });
        }

        function renderMessages(data) {
            const container = document.getElementById('messagesContainer');
            const empty = document.getElementById('chatEmpty');
            if (empty) {
                empty.remove();
            }

            container.innerHTML = '';
            const messages = Array.isArray(data.messages) ? data.messages : [];

            if (messages.length === 0) {
                container.innerHTML = '<div class="chat-empty"><i class="bi bi-chat-left-text"></i><div>Chưa có tin nhắn trong hội thoại này.</div></div>';
                return;
            }

            messages.forEach(msg => {
                const row = document.createElement('div');
                const isStaff = msg.sender_type === 'staff';
                row.className = 'msg-row ' + (isStaff ? 'staff' : 'user');

                const bubble = document.createElement('div');
                bubble.className = 'msg-bubble';
                bubble.textContent = msg.message || '';

                const time = document.createElement('div');
                time.className = 'msg-time';
                const date = new Date(msg.created_at);
                time.textContent = date.toLocaleTimeString('vi-VN', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                row.appendChild(bubble);
                row.appendChild(time);
                container.appendChild(row);
            });

            container.scrollTop = container.scrollHeight;

            if (data && data.typing && data.typing.user) {
                showUserTyping(true);
            } else {
                showUserTyping(false);
            }

            if (messages.length > 0) {
                const last = messages[messages.length - 1];
                lastMessageSignature = `${messages.length}|${last.sender_type}|${last.created_at}`;
            } else {
                lastMessageSignature = '0';
            }
        }

        async function loadChatMessages() {
            if (!currentChatId) {
                return;
            }

            const formBody = new URLSearchParams();
            formBody.append('chat_id', String(currentChatId));

            const response = await fetch('/api/chat-get-messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formBody.toString()
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Không thể tải tin nhắn.');
            }

            const messages = Array.isArray(data.messages) ? data.messages : [];
            const currentSignature = messages.length > 0
                ? `${messages.length}|${messages[messages.length - 1].sender_type}|${messages[messages.length - 1].created_at}`
                : '0';

            if (currentSignature !== lastMessageSignature || (data.typing && data.typing.user)) {
                renderMessages(data);
            } else if (data.typing && !data.typing.user) {
                showUserTyping(false);
            }

            await refreshChatList();
        }

        async function openChat(card) {
            currentChatId = parseInt(card.getAttribute('data-chat-id'), 10);
            const userName = card.getAttribute('data-user-name') || 'Khách hàng';
            const userEmail = card.getAttribute('data-user-email') || '';

            document.getElementById('chatTitle').textContent = userName;
            document.getElementById('chatSubtitle').textContent = userEmail;
            document.getElementById('chatInputWrap').style.display = 'block';
            setActiveChatCard(currentChatId);

            try {
                await loadChatMessages();
            } catch (error) {
                const container = document.getElementById('messagesContainer');
                container.innerHTML = '<div class="alert alert-danger mb-0">' + (error.message || 'Lỗi tải tin nhắn') + '</div>';
            }

            if (messageRefreshTimer) {
                clearInterval(messageRefreshTimer);
            }

            messageRefreshTimer = setInterval(() => {
                loadChatMessages().catch(() => {});
            }, 3000);

            if (typingSendTimer) {
                clearTimeout(typingSendTimer);
                typingSendTimer = null;
            }

            if (typingIdleTimer) {
                clearTimeout(typingIdleTimer);
                typingIdleTimer = null;
            }
        }

        async function sendMessage(event) {
            event.preventDefault();
            if (!currentChatId) {
                return;
            }

            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            if (!message) {
                return;
            }

            await notifyTyping(false);

            const formData = new FormData();
            formData.append('chat_id', String(currentChatId));
            formData.append('message', message);

            const response = await fetch('/api/chat-send-staff.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (!data.success) {
                alert(data.message || 'Không gửi được tin nhắn.');
                return;
            }

            input.value = '';
            await loadChatMessages();
        }

        document.addEventListener('DOMContentLoaded', () => {
            attachChatCardEvents();

            const chatForm = document.getElementById('chatForm');
            if (chatForm) {
                chatForm.addEventListener('submit', (event) => {
                    sendMessage(event).catch(error => {
                        alert(error.message || 'Có lỗi xảy ra khi gửi tin nhắn.');
                    });
                });
            }

            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                // Tự động tăng chiều cao textarea
                messageInput.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
                    if (!currentChatId) return;
                    if (typingSendTimer) clearTimeout(typingSendTimer);
                    typingSendTimer = setTimeout(() => { notifyTyping(true).catch(() => {}); }, 120);
                    if (typingIdleTimer) clearTimeout(typingIdleTimer);
                    typingIdleTimer = setTimeout(() => { notifyTyping(false).catch(() => {}); }, 1400);
                });

                // Shift+Enter xuống dòng, Enter gửi
                messageInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        if (e.shiftKey) {
                            // Xuống dòng
                            e.stopPropagation();
                            return;
                        } else {
                            // Gửi tin nhắn
                            e.preventDefault();
                            const form = document.getElementById('chatForm');
                            if (form) form.requestSubmit();
                        }
                    }
                });

                messageInput.addEventListener('blur', () => {
                    notifyTyping(false).catch(() => {});
                });
            }

            listRefreshTimer = setInterval(() => {
                refreshChatList().catch(() => {});
            }, 3000);

            const cards = document.querySelectorAll('.chat-user-card');
            if (cards.length > 0) {
                openChat(cards[0]);
            }

            refreshChatList().catch(() => {});
        });

        window.addEventListener('beforeunload', () => {
            notifyTyping(false).catch(() => {});
            if (messageRefreshTimer) {
                clearInterval(messageRefreshTimer);
            }
            if (listRefreshTimer) {
                clearInterval(listRefreshTimer);
            }
        });
    </script>
</body>
</html>
