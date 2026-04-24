<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn() || (!isAdmin() && getStaffPanelKey() !== 'support')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$staffId = 0;

if (!isAdmin()) {
    $staff = Database::fetch(
        "SELECT staff_id FROM staff WHERE user_id = ? AND status = 'active'",
        [$userId]
    );

    if (!$staff) {
        echo json_encode(['success' => false, 'message' => 'Staff không hợp lệ']);
        exit();
    }

    $staffId = (int)$staff['staff_id'];
}

try {
    $whereClause = "cs.status = 'open'";
    $params = [];

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
         WHERE {$whereClause}
         ORDER BY COALESCE(cs.last_message_at, cs.created_at) DESC, cs.created_at DESC",
        $params
    );

    $stats = [
        'open_chats' => 0,
        'customers' => 0,
        'waiting_reply' => 0,
        'messages_today' => 0
    ];

    if (isAdmin()) {
        $statsRow = Database::fetch(
            "SELECT
                (SELECT COUNT(*) FROM chat_sessions WHERE status = 'open') AS open_chats,
                (SELECT COUNT(DISTINCT COALESCE(CAST(user_id AS CHAR), guest_token)) FROM chat_sessions WHERE status = 'open') AS customers,
                (SELECT COUNT(*)
                 FROM chat_sessions cs
                 JOIN (
                    SELECT cm.chat_id, cm.sender_type
                    FROM chat_messages cm
                    JOIN (
                        SELECT chat_id, MAX(message_id) AS max_message_id
                        FROM chat_messages
                        GROUP BY chat_id
                    ) x ON x.max_message_id = cm.message_id
                 ) lm ON lm.chat_id = cs.chat_id
                 WHERE cs.status = 'open' AND lm.sender_type = 'user') AS waiting_reply,
                (SELECT COUNT(*) FROM chat_messages WHERE DATE(created_at) = CURDATE()) AS messages_today"
        );
        $stats = [
            'open_chats' => (int)($statsRow['open_chats'] ?? 0),
            'customers' => (int)($statsRow['customers'] ?? 0),
            'waiting_reply' => (int)($statsRow['waiting_reply'] ?? 0),
            'messages_today' => (int)($statsRow['messages_today'] ?? 0)
        ];
    } else {
        $statsRow = Database::fetch(
            "SELECT
                (SELECT COUNT(*) FROM chat_sessions WHERE status = 'open') AS open_chats,
                (SELECT COUNT(DISTINCT COALESCE(CAST(user_id AS CHAR), guest_token)) FROM chat_sessions WHERE status = 'open') AS customers,
                (SELECT COUNT(*)
                 FROM chat_sessions cs
                 JOIN (
                    SELECT cm.chat_id, cm.sender_type
                    FROM chat_messages cm
                    JOIN (
                        SELECT chat_id, MAX(message_id) AS max_message_id
                        FROM chat_messages
                        GROUP BY chat_id
                    ) x ON x.max_message_id = cm.message_id
                 ) lm ON lm.chat_id = cs.chat_id
                 WHERE cs.status = 'open' AND lm.sender_type = 'user') AS waiting_reply,
                (SELECT COUNT(*) FROM chat_messages WHERE DATE(created_at) = CURDATE()) AS messages_today"
        );
        $stats = [
            'open_chats' => (int)($statsRow['open_chats'] ?? 0),
            'customers' => (int)($statsRow['customers'] ?? 0),
            'waiting_reply' => (int)($statsRow['waiting_reply'] ?? 0),
            'messages_today' => (int)($statsRow['messages_today'] ?? 0)
        ];
    }

    $dailyParams = [];
    $dailyWhere = '';
    $dailyStats = Database::fetchAll(
        "SELECT
            DATE(cm.created_at) AS chat_date,
            COUNT(DISTINCT cm.chat_id) AS total_chats,
            COUNT(*) AS total_messages,
            SUM(cm.sender_type = 'user') AS user_messages,
            SUM(cm.sender_type = 'staff') AS staff_messages
         FROM chat_messages cm
         JOIN chat_sessions cs ON cs.chat_id = cm.chat_id
         WHERE cm.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
           {$dailyWhere}
         GROUP BY DATE(cm.created_at)
         ORDER BY chat_date DESC",
        $dailyParams
    );

    echo json_encode([
        'success' => true,
        'chats' => $chats,
        'stats' => $stats,
        'daily_stats' => $dailyStats
    ]);
} catch (Exception $e) {
    error_log('chat-advisor-list error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Có lỗi hệ thống']);
}
