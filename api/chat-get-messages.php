<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/functions.php';

function ensureChatTypingSchema()
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    Database::execute(
        "CREATE TABLE IF NOT EXISTS chat_typing_status (
            chat_id INT PRIMARY KEY,
            user_typing TINYINT(1) NOT NULL DEFAULT 0,
            staff_typing TINYINT(1) NOT NULL DEFAULT 0,
            user_updated_at DATETIME NULL,
            staff_updated_at DATETIME NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_chat_typing_chat FOREIGN KEY (chat_id) REFERENCES chat_sessions(chat_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

$userId = isset($_SESSION['user_id']) ? (int)($_SESSION['user_id'] ?? 0) : 0;
$chatId = (int)($_POST['chat_id'] ?? 0);
$guestToken = session_id();

if ($chatId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Chat ID không hợp lệ']);
    exit();
}

try {
    ensureChatTypingSchema();

    // For staff/advisor
    if (isLoggedIn() && isStaff()) {
        $staff = Database::fetch(
            "SELECT staff_id FROM staff WHERE user_id = ? AND status = 'active'",
            [$userId]
        );
        
        if (!$staff) {
            echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
            exit();
        }
        
        $chat = Database::fetch(
            "SELECT cs.chat_id, cs.user_id, cs.staff_id, u.name, u.email
             FROM chat_sessions cs
             LEFT JOIN users u ON cs.user_id = u.user_id
             WHERE cs.chat_id = ? AND cs.status = 'open'",
            [$chatId]
        );
        
        if (!$chat) {
            echo json_encode(['success' => false, 'message' => 'Chat không tồn tại']);
            exit();
        }
    } else {
        // For customer/user
        try {
            Database::fetch("SELECT user_cleared_at FROM chat_sessions LIMIT 1");
        } catch (Exception $e) {
            Database::execute("ALTER TABLE chat_sessions ADD COLUMN user_cleared_at DATETIME NULL AFTER status");
        }

        $chat = Database::fetch(
            "SELECT cs.chat_id, cs.user_id, cs.staff_id, cs.user_cleared_at
             FROM chat_sessions cs
             WHERE cs.chat_id = ? AND (cs.user_id = ? OR cs.guest_token = ?)",
            [$chatId, $userId, $guestToken]
        );
        
        if (!$chat) {
            echo json_encode(['success' => false, 'message' => 'Chat không tồn tại']);
            exit();
        }
    }
    
    // Get all messages for this chat
    $clearedAt = null;
    $isStaff = (isLoggedIn() && isStaff());
    if (!$isStaff && isset($chat['user_cleared_at']) && $chat['user_cleared_at']) {
        $clearedAt = $chat['user_cleared_at'];
    }

    $sql = "SELECT sender_type, sender_id, message, created_at
            FROM chat_messages
            WHERE chat_id = ?";
    $params = [$chatId];

    if ($clearedAt) {
        $sql .= " AND created_at >= ?";
        $params[] = $clearedAt;
    }
    $sql .= " ORDER BY created_at ASC";

    $messages = Database::fetchAll($sql, $params);
    
    $typingRow = Database::fetch(
        "SELECT user_typing, staff_typing, user_updated_at, staff_updated_at
         FROM chat_typing_status
         WHERE chat_id = ?",
        [$chatId]
    );

    $isUserTyping = false;
    $isStaffTyping = false;
    $typingTimeout = 8;

    if ($typingRow) {
        if (!empty($typingRow['user_typing']) && !empty($typingRow['user_updated_at'])) {
            $isUserTyping = (time() - strtotime((string)$typingRow['user_updated_at'])) <= $typingTimeout;
        }
        if (!empty($typingRow['staff_typing']) && !empty($typingRow['staff_updated_at'])) {
            $isStaffTyping = (time() - strtotime((string)$typingRow['staff_updated_at'])) <= $typingTimeout;
        }
    }

    $response = [
        'success' => true,
        'chat_id' => (int)$chatId,
        'messages' => $messages,
        'typing' => [
            'user' => $isUserTyping,
            'staff' => $isStaffTyping
        ]
    ];
    
    // Add customer info for staff
    if (isset($chat['name'])) {
        $response['customer_name'] = $chat['name'] ?? 'Khách hàng';
        $response['customer_email'] = $chat['email'] ?? '';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log('Get messages error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra'
    ]);
}
?>
