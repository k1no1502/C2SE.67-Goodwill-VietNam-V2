<?php
require_once 'config/database.php';

$users = Database::fetchAll(
    "SELECT user_id, name, email FROM users WHERE email LIKE 'test%@gmail.com' ORDER BY user_id"
);

echo "Verification: Test Accounts Created\n";
echo str_repeat("=", 50) . "\n";
foreach ($users as $user) {
    echo "ID: {$user['user_id']} | Name: {$user['name']} | Email: {$user['email']}\n";
}
echo str_repeat("=", 50) . "\n";
echo "Total: " . count($users) . " accounts\n";
?>
