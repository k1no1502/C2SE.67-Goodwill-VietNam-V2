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

// Check if user is staff
if (!isLoggedIn() || !isStaff()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$chatId = (int)($_POST['chat_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($chatId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Chat ID không hợp lệ']);
    exit();
}

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Tin nhắn không được để trống']);
    exit();
}

try {
    // Verify that the staff member is assigned to this chat
    $staff = Database::fetch(
        "SELECT staff_id FROM staff WHERE user_id = ? AND status = 'active'",
        [$userId]
    );
    
    if (!$staff) {
        echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
        exit();
    }
    
    $staffId = (int)$staff['staff_id'];
    
    $chat = Database::fetch(
        "SELECT chat_id, staff_id FROM chat_sessions WHERE chat_id = ? AND status = 'open'",
        [$chatId]
    );
    
    if (!$chat) {
        echo json_encode(['success' => false, 'message' => 'Chat không tồn tại hoặc đã đóng']);
        exit();
    }

    // Auto-assign chat to this staff if not yet assigned
    if (empty($chat['staff_id']) || (int)$chat['staff_id'] !== $staffId) {
        Database::execute(
            "UPDATE chat_sessions SET staff_id = ?, updated_at = NOW() WHERE chat_id = ?",
            [$staffId, $chatId]
        );
    }
    
    // Insert the message
    Database::execute(
        "INSERT INTO chat_messages (chat_id, sender_type, sender_id, message, created_at)
         VALUES (?, 'staff', ?, ?, NOW())",
        [$chatId, $staffId, $message]
    );
    
    // Update last_message_at
    Database::execute(
        "UPDATE chat_sessions SET last_message_at = NOW(), updated_at = NOW() WHERE chat_id = ?",
        [$chatId]
    );

    ensureChatTypingSchema();
    Database::execute(
        "INSERT INTO chat_typing_status (chat_id, staff_typing, staff_updated_at, updated_at)
         VALUES (?, 0, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            staff_typing = 0,
            staff_updated_at = NOW(),
            updated_at = NOW()",
        [$chatId]
    );
    
    // Get all messages to return
    $messages = Database::fetchAll(
        "SELECT sender_type, sender_id, message, created_at FROM chat_messages WHERE chat_id = ? ORDER BY created_at ASC",
        [$chatId]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Tin nhắn đã được gửi',
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    error_log('Send message error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra'
    ]);
}
?>
