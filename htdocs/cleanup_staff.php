<?php
/**
 * Cleanup: Remove test accounts from staff
 * Keep only the designated advisor: advisor1@gwvn.test
 */

require_once 'config/database.php';

echo "=== Cleaning Up Test Staff Accounts ===\n\n";

// Get the proper advisor
$proper = Database::fetch(
    "SELECT s.staff_id, s.user_id FROM staff s 
     JOIN users u ON s.user_id = u.user_id 
     WHERE u.email = 'advisor1@gwvn.test'"
);

if (!$proper) {
    echo "✗ Could not find proper advisor\n";
    exit(1);
}

$properStaffId = (int)$proper['staff_id'];
echo "✓ Proper advisor found: Staff ID {$properStaffId}\n";

// Get all OTHER staff members
$others = Database::fetchAll(
    "SELECT s.staff_id, u.email FROM staff s 
     JOIN users u ON s.user_id = u.user_id 
     WHERE s.staff_id != ?"
    , [$properStaffId]
);

if (!empty($others)) {
    echo "\n✓ Found " . count($others) . " other staff member(s) to remove:\n";
    foreach ($others as $other) {
        echo "  - Staff ID {$other['staff_id']}: {$other['email']}\n";
    }
    
    echo "\nRemoving...\n";
    foreach ($others as $other) {
        Database::execute("DELETE FROM staff WHERE staff_id = ?", [$other['staff_id']]);
        echo "  ✓ Deleted Staff ID {$other['staff_id']}\n";
    }
} else {
    echo "✓ No other staff members found - already clean\n";
}

echo "\n=== Cleanup Complete ===\n";
echo "\nProper Advisor Details:\n";
echo "  Email: advisor1@gwvn.test\n";
echo "  Password: 123456\n";
echo "  Staff ID: {$properStaffId}\n";
?>
