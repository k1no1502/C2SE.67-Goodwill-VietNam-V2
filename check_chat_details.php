<?php
require_once 'config/database.php';

echo "=== Chat Session Details ===\n\n";

$chats = Database::fetchAll(
    "SELECT cs.chat_id, cs.user_id, cs.staff_id, cs.status, cs.created_at,
            u.name as customer_name, u.email as customer_email,
            s.user_id as advisor_user_id, au.name as advisor_name, au.email as advisor_email
     FROM chat_sessions cs
     LEFT JOIN users u ON cs.user_id = u.user_id
     LEFT JOIN staff s ON cs.staff_id = s.staff_id
     LEFT JOIN users au ON s.user_id = au.user_id
     ORDER BY cs.created_at DESC"
);

foreach ($chats as $chat) {
    echo "Chat ID: {$chat['chat_id']}\n";
    echo "  Customer: {$chat['customer_name']} ({$chat['customer_email']})\n";
    echo "  Advisor Staff ID: {$chat['staff_id']}\n";
    echo "  Advisor: {$chat['advisor_name']} ({$chat['advisor_email']})\n";
    echo "  Status: {$chat['status']}\n";
    echo "  Created: {$chat['created_at']}\n";
    
    // Get message count
    $msgCount = Database::fetch(
        "SELECT COUNT(*) as count FROM chat_messages WHERE chat_id = ?",
        [$chat['chat_id']]
    );
    echo "  Messages: {$msgCount['count']}\n\n";
}

echo "\n=== Login Credentials ===\n";
echo "Advisor Email: advisor1@gwvn.test\n";
echo "Advisor Password: 123456\n";
echo "Advisor Dashboard: /chat-advisor.php\n";
?>
