<?php
session_start();
header('Content-Type: application/json');

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
    // For staff/advisor
    if (isLoggedIn() && (hasRole('staff') || hasRole('nhân viên'))) {
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
             WHERE cs.chat_id = ? AND cs.staff_id = ?",
            [$chatId, $staff['staff_id']]
        );
        
        if (!$chat) {
            echo json_encode(['success' => false, 'message' => 'Chat không tồn tại']);
            exit();
        }
    } else {
        // For customer/user
        $chat = Database::fetch(
            "SELECT cs.chat_id, cs.user_id, cs.staff_id
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
    $messages = Database::fetchAll(
        "SELECT sender_type, sender_id, message, created_at
         FROM chat_messages
         WHERE chat_id = ?
         ORDER BY created_at ASC",
        [$chatId]
    );
    
    $response = [
        'success' => true,
        'chat_id' => (int)$chatId,
        'messages' => $messages
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
