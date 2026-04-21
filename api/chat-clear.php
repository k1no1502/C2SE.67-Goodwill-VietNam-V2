<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../includes/functions.php';

$userId = isset($_SESSION['user_id']) ? (int)($_SESSION['user_id'] ?? 0) : 0;
$chatId = (int)($_POST['chat_id'] ?? 0);
$guestToken = session_id();

if ($chatId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Chat ID không hợp lệ']);
    exit();
}

try {
    try {
        Database::fetch("SELECT user_cleared_at FROM chat_sessions LIMIT 1");
    } catch (Exception $e) {
        Database::execute("ALTER TABLE chat_sessions ADD COLUMN user_cleared_at DATETIME NULL AFTER status");
    }

    if (isLoggedIn() && isStaff()) {
        // Staff can't clear user history in this endpoint, this is for users.
        echo json_encode(['success' => false, 'message' => 'Lệnh này dành cho khách hàng']);
        exit();
    }

    $chat = Database::fetch(
        "SELECT cs.chat_id FROM chat_sessions cs WHERE cs.chat_id = ? AND (cs.user_id = ? OR cs.guest_token = ?)",
        [$chatId, $userId, $guestToken]
    );

    if (!$chat) {
        echo json_encode(['success' => false, 'message' => 'Chat không tồn tại']);
        exit();
    }

    // Set cleared_at to NOW
    Database::execute(
        "UPDATE chat_sessions SET user_cleared_at = NOW() WHERE chat_id = ?",
        [$chatId]
    );

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Clear chat error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra'
    ]);
}
