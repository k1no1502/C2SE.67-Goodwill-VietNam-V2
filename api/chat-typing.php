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

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$chatId = (int)($_POST['chat_id'] ?? 0);
$isTyping = (int)($_POST['is_typing'] ?? 0) === 1 ? 1 : 0;
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($chatId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Chat không hợp lệ']);
    exit();
}

try {
    ensureChatTypingSchema();

    if (isStaff()) {
        $staff = Database::fetch(
            "SELECT staff_id FROM staff WHERE user_id = ? AND status = 'active'",
            [$userId]
        );

        if (!$staff) {
            echo json_encode(['success' => false, 'message' => 'Staff không hợp lệ']);
            exit();
        }

        $chat = Database::fetch(
            "SELECT chat_id FROM chat_sessions WHERE chat_id = ? AND status = 'open'",
            [$chatId]
        );

        if (!$chat) {
            echo json_encode(['success' => false, 'message' => 'Không có quyền']);
            exit();
        }

        Database::execute(
            "INSERT INTO chat_typing_status (chat_id, staff_typing, staff_updated_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                staff_typing = VALUES(staff_typing),
                staff_updated_at = NOW(),
                updated_at = NOW()",
            [$chatId, $isTyping]
        );
    } else {
        $chat = Database::fetch(
            "SELECT chat_id
             FROM chat_sessions
             WHERE chat_id = ? AND (user_id = ? OR guest_token = ?)",
            [$chatId, $userId, session_id()]
        );

        if (!$chat) {
            echo json_encode(['success' => false, 'message' => 'Không có quyền']);
            exit();
        }

        Database::execute(
            "INSERT INTO chat_typing_status (chat_id, user_typing, user_updated_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                user_typing = VALUES(user_typing),
                user_updated_at = NOW(),
                updated_at = NOW()",
            [$chatId, $isTyping]
        );
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('chat-typing error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống']);
}
