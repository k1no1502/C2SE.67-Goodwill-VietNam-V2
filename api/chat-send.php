<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/functions.php';

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

    $autoReplies = [
        'Cam on ban da lien he. Nhan vien se phan hoi som nhat co the.',
        'Ban vui long cho biet them chi tiet de minh ho tro nhanh hon nhe.',
        'Toi da ghi nhan. Xin doi trong giay lat nhe.'
    ];
    $reply = $autoReplies[array_rand($autoReplies)];

    Database::execute(
        "INSERT INTO chat_messages (chat_id, sender_type, sender_id, message, created_at)
         VALUES (?, 'staff', ?, ?, NOW())",
        [$chat['chat_id'], $chat['staff_id'], $reply]
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
