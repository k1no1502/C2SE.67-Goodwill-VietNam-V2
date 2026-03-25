<?php
/**
 * Chat System Diagnostic Tool
 * Use this to debug chat system issues
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "=== Chat System Diagnostic ===\n\n";

// 1. Check advisor setup
echo "1. Checking Staff/Advisor Setup:\n";
$advisors = Database::fetchAll(
    "SELECT s.staff_id, u.user_id, u.name, u.email, u.role_id, s.status
     FROM staff s
     JOIN users u ON s.user_id = u.user_id
     WHERE u.role_id = 4
     ORDER BY u.user_id"
);

if (empty($advisors)) {
    echo "   ✗ No advisors found (role_id = 4)\n";
} else {
    echo "   ✓ Found " . count($advisors) . " advisor(s):\n";
    foreach ($advisors as $advisor) {
        echo "     - {$advisor['name']} ({$advisor['email']}) - Status: {$advisor['status']}\n";
    }
}

// 2. Check active chat sessions
echo "\n2. Checking Chat Sessions:\n";
$chats = Database::fetchAll(
    "SELECT cs.chat_id, cs.user_id, cs.guest_token, cs.staff_id, cs.status, 
            cs.created_at, u.name, u.email, s.staff_id as advisor_id
     FROM chat_sessions cs
     LEFT JOIN users u ON cs.user_id = u.user_id
     LEFT JOIN staff s ON cs.staff_id = s.staff_id
     ORDER BY cs.created_at DESC
     LIMIT 10"
);

if (empty($chats)) {
    echo "   ✗ No chat sessions found\n";
} else {
    echo "   ✓ Found " . count($chats) . " chat session(s):\n";
    foreach ($chats as $chat) {
        $customerName = $chat['name'] ?? 'Guest';
        echo "     - Chat ID: {$chat['chat_id']} | Customer: {$customerName} | Status: {$chat['status']} | Created: " . substr($chat['created_at'], 0, 10) . "\n";
    }
}

// 3. Check recent messages
echo "\n3. Checking Recent Messages:\n";
$messages = Database::fetchAll(
    "SELECT cm.message_id, cm.chat_id, cm.sender_type, cm.message, cm.created_at
     FROM chat_messages cm
     ORDER BY cm.created_at DESC
     LIMIT 15"
);

if (empty($messages)) {
    echo "   ✗ No messages found\n";
} else {
    echo "   ✓ Found " . count($messages) . " message(s):\n";
    foreach ($messages as $msg) {
        $preview = substr($msg['message'], 0, 50);
        echo "     - Chat {$msg['chat_id']}: [{$msg['sender_type']}] {$preview} ({$msg['created_at']})\n";
    }
}

// 4. Check test customers
echo "\n4. Checking Test Customer Accounts:\n";
$testUsers = Database::fetchAll(
    "SELECT user_id, name, email, role_id, status
     FROM users
     WHERE email LIKE 'test%@gmail.com'
     ORDER BY user_id"
);

if (empty($testUsers)) {
    echo "   ✗ No test accounts found\n";
} else {
    echo "   ✓ Found " . count($testUsers) . " test account(s):\n";
    foreach ($testUsers as $user) {
        echo "     - {$user['name']} ({$user['email']}) - Status: {$user['status']}\n";
    }
}

// 5. Quick test: Try initial chat with test user
echo "\n5. Testing Chat Initialization:\n";
try {
    $testChat = Database::fetch(
        "SELECT cs.chat_id, cs.staff_id FROM chat_sessions 
         WHERE status = 'open'
         ORDER BY created_at DESC LIMIT 1"
    );
    
    if ($testChat) {
        echo "   ✓ Found active chat: ID {$testChat['chat_id']} assigned to advisor {$testChat['staff_id']}\n";
        
        // Check messages in that chat
        $chatMsgs = Database::fetchAll(
            "SELECT sender_type, message FROM chat_messages WHERE chat_id = ? ORDER BY created_at DESC LIMIT 3",
            [$testChat['chat_id']]
        );
        
        if (!empty($chatMsgs)) {
            echo "   ✓ Latest messages:\n";
            foreach ($chatMsgs as $m) {
                echo "     - [{$m['sender_type']}] " . substr($m['message'], 0, 40) . "\n";
            }
        }
    } else {
        echo "   ⚠ No active chat sessions - Create one by opening chat widget as customer\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Diagnostic Complete ===\n";
echo "\nTo test the chat system:\n";
echo "1. Open a customer page and click the chat button\n";
echo "2. Advisor should see a new chat appear at /chat-advisor.php\n";
echo "3. Advisor and customer can then exchange messages\n";
?>
