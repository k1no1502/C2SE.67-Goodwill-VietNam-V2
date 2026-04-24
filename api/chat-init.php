<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/functions.php';

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$guestToken = session_id();

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
        // Find available support advisor staff only
        $staff = Database::fetch(
            "SELECT s.staff_id, u.name, u.user_id
             FROM staff s
             JOIN users u ON s.user_id = u.user_id
             WHERE s.status = 'active'
               AND u.status = 'active'
               AND u.role_id = 4
               AND LOWER(s.position) LIKE '%tư vấn%'
             ORDER BY RAND()
             LIMIT 1"
        );

        if (!$staff) {
            echo json_encode([
                'success' => false,
                'message' => 'Hien chua co nhan vien tu van truc tuyen.'
            ]);
            exit();
        }

        $staffId = $staff['staff_id'];
        $staffName = $staff['name'] ?? 'Tu van vien';

        Database::execute(
            "INSERT INTO chat_sessions (user_id, guest_token, staff_id, status, created_at, updated_at)
             VALUES (?, ?, ?, 'open', NOW(), NOW())",
            [$userId, $guestToken, $staffId]
        );

        $chatId = (int) Database::lastInsertId();

        $greeting = 'Xin chao! Toi la ' . $staffName . '. Toi co the ho tro gi cho ban?';
        Database::execute(
            "INSERT INTO chat_messages (chat_id, sender_type, sender_id, message, created_at)
             VALUES (?, 'staff', ?, ?, NOW())",
            [$chatId, $staffId, $greeting]
        );

        $chat = [
            'chat_id' => $chatId,
            'staff_id' => $staffId
        ];
    }

    $staffName = 'Tu van vien';
    if (!empty($chat['staff_id'])) {
        $staffRow = Database::fetch(
            "SELECT u.name
             FROM staff s
             JOIN users u ON s.user_id = u.user_id
             WHERE s.staff_id = ?",
            [$chat['staff_id']]
        );
        if ($staffRow && !empty($staffRow['name'])) {
            $staffName = $staffRow['name'];
        }
    }

    $messages = Database::fetchAll(
        "SELECT sender_type, message, created_at
         FROM chat_messages
         WHERE chat_id = ?
         ORDER BY created_at ASC
         LIMIT 50",
        [$chat['chat_id']]
    );

    echo json_encode([
        'success' => true,
        'chat_id' => (int) $chat['chat_id'],
        'staff_name' => $staffName,
        'messages' => $messages
    ]);
} catch (Exception $e) {
    error_log('Chat init error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Khong the mo chat. Vui long thu lai.'
    ]);
}
?>
