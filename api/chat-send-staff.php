<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is staff
if (!isLoggedIn() || (!hasRole('staff') && !hasRole('nhân viên'))) {
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
        "SELECT chat_id, staff_id FROM chat_sessions WHERE chat_id = ? AND staff_id = ?",
        [$chatId, $staffId]
    );
    
    if (!$chat) {
        echo json_encode(['success' => false, 'message' => 'Chat không tồn tại']);
        exit();
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
