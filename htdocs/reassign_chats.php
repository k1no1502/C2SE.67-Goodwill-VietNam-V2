<?php
require_once 'config/database.php';

echo "=== Reassigning Chats to Proper Advisor ===\n\n";

// Get the proper advisor
$proper = Database::fetch(
    "SELECT staff_id FROM staff WHERE staff_id = 3"
);

if (!$proper) {
    echo "✗ Proper advisor not found\n";
    exit(1);
}

$properStaffId = 3;

// Get all open chats with old advisor assignments
$oldChats = Database::fetchAll(
    "SELECT chat_id, staff_id FROM chat_sessions WHERE status = 'open' AND staff_id != ?",
    [$properStaffId]
);

if (empty($oldChats)) {
    echo "✓ All chats already assigned to proper advisor\n";
} else {
    echo "Found " . count($oldChats) . " chats to reassign:\n\n";
    
    foreach ($oldChats as $chat) {
        Database::execute(
            "UPDATE chat_sessions SET staff_id = ? WHERE chat_id = ?",
            [$properStaffId, $chat['chat_id']]
        );
        echo "✓ Chat {$chat['chat_id']}: {$chat['staff_id']} → {$properStaffId}\n";
    }
}

echo "\n=== Reassignment Complete ===\n";
echo "All chats now assigned to advisor1@gwvn.test (Staff ID: 3)\n";
?>
