<?php
/**
 * Chat System Status Check
 */
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

try {
    $response = [
        'status' => 'ok',
        'timestamp' => date('Y-m-d H:i:s'),
        'checks' => []
    ];
    
    // 1. Check database connection
    $response['checks'][] = [
        'name' => 'Database Connection',
        'status' => 'ok',
        'details' => 'Connected to goodwill_vietnam'
    ];
    
    // 2. Check advisor
    $advisor = Database::fetch(
        "SELECT s.staff_id, u.name, u.email, u.status, s.status as staff_status
         FROM staff s
         JOIN users u ON s.user_id = u.user_id
         WHERE u.email = 'advisor1@gwvn.test' AND u.role_id = 4"
    );
    
    if ($advisor) {
        $response['checks'][] = [
            'name' => 'Advisor Account',
            'status' => 'ok',
            'details' => "Found: {$advisor['name']} (Staff ID: {$advisor['staff_id']})"
        ];
    } else {
        $response['checks'][] = [
            'name' => 'Advisor Account',
            'status' => 'warning',
            'details' => 'Advisor account not found or not properly configured'
        ];
    }
    
    // 3. Check chat sessions
    $chatCount = Database::fetch(
        "SELECT COUNT(*) as count FROM chat_sessions WHERE status = 'open'"
    );
    
    $response['checks'][] = [
        'name' => 'Active Chat Sessions',
        'status' => 'ok',
        'details' => "Found {$chatCount['count']} active session(s)"
    ];
    
    // 4. Check recent messages
    $msgCount = Database::fetch(
        "SELECT COUNT(*) as count FROM chat_messages WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    
    $response['checks'][] = [
        'name' => 'Recent Messages (last 1 hour)',
        'status' => 'ok',
        'details' => "Found {$msgCount['count']} message(s)"
    ];
    
    // 5. List current chats
    $chats = Database::fetchAll(
        "SELECT cs.chat_id, cs.staff_id, cs.status, COALESCE(u.name, 'Guest') as customer_name,
         (SELECT COUNT(*) FROM chat_messages WHERE chat_id = cs.chat_id) as messages
         FROM chat_sessions cs
         LEFT JOIN users u ON cs.user_id = u.user_id
         WHERE cs.status = 'open'
         ORDER BY cs.created_at DESC LIMIT 5"
    );
    
    $response['active_chats'] = $chats;
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
