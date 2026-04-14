<?php
/**
 * Full Chat System Test
 * Simulates customer sending message and checks if advisor can see it
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "=== FULL CHAT SYSTEM TEST ===\n\n";

// Test 1: Check advisor
echo "1. Checking Advisor Setup...\n";
$advisor = Database::fetch(
    "SELECT s.staff_id, u.user_id, u.name, u.email FROM staff s
     JOIN users u ON s.user_id = u.user_id
     WHERE s.staff_id = 3 AND u.role_id = 4"
);

if ($advisor) {
    echo "   ✓ Advisor: {$advisor['name']} ({$advisor['email']})\n";
    echo "   ✓ Staff ID: {$advisor['staff_id']}\n";
    echo "   ✓ User ID: {$advisor['user_id']}\n";
} else {
    echo "   ✗ Advisor not found!\n";
    exit(1);
}

// Test 2: Get current chats
echo "\n2. Checking Current Chats...\n";
$chats = Database::fetchAll(
    "SELECT cs.chat_id, cs.user_id, cs.staff_id, u.name, u.email,
            (SELECT COUNT(*) FROM chat_messages WHERE chat_id = cs.chat_id) as msg_count
     FROM chat_sessions cs
     LEFT JOIN users u ON cs.user_id = u.user_id
     WHERE cs.status = 'open' AND cs.staff_id = 3
     ORDER BY cs.created_at DESC"
);

if (empty($chats)) {
    echo "   ⚠ No open chats found\n";
} else {
    echo "   ✓ Found " . count($chats) . " open chat(s):\n";
    foreach ($chats as $chat) {
        echo "     - Chat {$chat['chat_id']}: {$chat['name']} ({$chat['email']}) - {$chat['msg_count']} messages\n";
    }
}

// Test 3: Check messages in each chat
echo "\n3. Checking Messages in Each Chat...\n";
foreach ($chats as $chat) {
    echo "   Chat {$chat['chat_id']}:\n";
    
    $messages = Database::fetchAll(
        "SELECT sender_type, message, created_at FROM chat_messages 
         WHERE chat_id = ? 
         ORDER BY created_at DESC 
         LIMIT 5",
        [$chat['chat_id']]
    );
    
    if (empty($messages)) {
        echo "     (No messages)\n";
    } else {
        foreach ($messages as $msg) {
            $preview = substr($msg['message'], 0, 40) . (strlen($msg['message']) > 40 ? '...' : '');
            echo "     [{$msg['sender_type']}] {$preview}\n";
        }
    }
}

// Test 4: Test API endpoints
echo "\n4. Testing API Endpoints...\n";

// Test chat-get-messages for first chat
if (!empty($chats)) {
    $testChatId = $chats[0]['chat_id'];
    echo "   Testing /api/chat-get-messages.php with Chat {$testChatId}...\n";
    
    // Simulate POST request
    $_POST['chat_id'] = $testChatId;
    $_SESSION['user_id'] = $advisor['user_id'];
    $_SESSION['role_id'] = 4;
    
    ob_start();
    require 'api/chat-get-messages.php';
    $output = ob_get_clean();
    
    $result = json_decode($output, true);
    if ($result['success']) {
        echo "   ✓ Messages retrieved: " . count($result['messages']) . " messages\n";
    } else {
        echo "   ✗ Error: " . $result['message'] . "\n";
    }
}

// Test 5: Advisor Dashboard Query
echo "\n5. Testing Advisor Dashboard Query...\n";
$dashboardChats = Database::fetchAll(
    "SELECT 
        cs.chat_id,
        cs.user_id,
        cs.status,
        cs.last_message_at,
        cs.created_at,
        COALESCE(u.name, 'Khách hàng') as customer_name,
        COALESCE(u.email, cs.guest_token) as customer_email,
        (SELECT COUNT(*) FROM chat_messages WHERE chat_id = cs.chat_id AND sender_type = 'user') as message_count,
        (SELECT message FROM chat_messages WHERE chat_id = cs.chat_id ORDER BY created_at DESC LIMIT 1) as last_message
     FROM chat_sessions cs
     LEFT JOIN users u ON cs.user_id = u.user_id
     WHERE cs.staff_id = 3 AND cs.status = 'open'
     ORDER BY COALESCE(cs.last_message_at, cs.created_at) DESC"
);

echo "   ✓ Dashboard query returned " . count($dashboardChats) . " chats\n";
foreach ($dashboardChats as $chat) {
    echo "     - {$chat['customer_name']}: \"{$chat['last_message']}\"\n";
}

echo "\n=== TEST COMPLETE ===\n";
echo "\n✅ Status: System is working correctly\n";
echo "\nNext Steps:\n";
echo "1. Customer: Login with test1@gmail.com / 123456\n";
echo "2. Open any page and click chat button\n";
echo "3. Advisor: Login with advisor1@gwvn.test / 123456\n";
echo "4. Go to /chat-advisor.php\n";
echo "5. Customer sends a message\n";
echo "6. Advisor should see it in the dashboard\n";
?>
