<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is staff
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);

try {
    // Get staff info
    $staff = Database::fetch(
        "SELECT staff_id FROM staff WHERE user_id = ? AND status = 'active'",
        [$userId]
    );
    
    if (!$staff) {
        echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
        exit();
    }
    
    $staffId = (int)$staff['staff_id'];
    
    // Get count of active chats
    $chatCount = Database::fetch(
        "SELECT COUNT(*) as count FROM chat_sessions WHERE staff_id = ? AND status = 'open'",
        [$staffId]
    );
    
    // Get unread messages count
    $unreadCount = Database::fetch(
        "SELECT COUNT(*) as count 
         FROM chat_messages cm
         JOIN chat_sessions cs ON cm.chat_id = cs.chat_id
         WHERE cs.staff_id = ? AND cm.sender_type = 'user' AND cm.created_at > NOW() - INTERVAL 5 MINUTE",
        [$staffId]
    );
    
    echo json_encode([
        'success' => true,
        'active_chats' => (int)($chatCount['count'] ?? 0),
        'unread_messages' => (int)($unreadCount['count'] ?? 0),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log('Get chat status error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Có lỗi xảy ra'
    ]);
}
?>
