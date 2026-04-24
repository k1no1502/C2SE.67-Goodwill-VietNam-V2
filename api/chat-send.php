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

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$guestToken = session_id();
$message = trim($_POST['message'] ?? '');

if ($message === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Noi dung khong duoc de trong.'
    ]);
    exit();
}

try {
    $chat = null;

    if ($userId) {
        $chat = Database::fetch(
            "SELECT chat_id, staff_id FROM chat_sessions WHERE user_id = ? AND status = 'open' ORDER BY created_at DESC LIMIT 1",
            [$userId]
        );
    } else {
        $chat = Database::fetch(
            "SELECT chat_id, staff_id FROM chat_sessions WHERE guest_token = ? AND status = 'open' ORDER BY created_at DESC LIMIT 1",
            [$guestToken]
        );
    }

    if (!$chat) {
        echo json_encode([
            'success' => false,
            'message' => 'Vui long mo khung chat truoc.'
        ]);
        exit();
    }

    Database::execute(
        "INSERT INTO chat_messages (chat_id, sender_type, sender_id, message, created_at)
         VALUES (?, 'user', ?, ?, NOW())",
        [$chat['chat_id'], $userId, $message]
    );

    ensureChatTypingSchema();
    Database::execute(
        "INSERT INTO chat_typing_status (chat_id, user_typing, user_updated_at, updated_at)
         VALUES (?, 0, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            user_typing = 0,
            user_updated_at = NOW(),
            updated_at = NOW()",
        [$chat['chat_id']]
    );

    Database::execute(
        "UPDATE chat_sessions SET last_message_at = NOW(), updated_at = NOW() WHERE chat_id = ?",
        [$chat['chat_id']]
    );

    // Get updated messages
    $messages = Database::fetchAll(
        "SELECT sender_type, sender_id, message, created_at FROM chat_messages WHERE chat_id = ? ORDER BY created_at ASC",
        [$chat['chat_id']]
    );

    echo json_encode([
        'success' => true,
        'chat_id' => (int)$chat['chat_id'],
        'messages' => $messages
    ]);
} catch (Exception $e) {
    error_log('Chat send error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Khong gui duoc tin nhan. Vui long thu lai.'
    ]);
}
?>
