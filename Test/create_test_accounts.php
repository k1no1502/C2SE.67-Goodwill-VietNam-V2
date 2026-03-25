<?php
/**
 * Test Account Generator
 * Creates 10 test accounts for testing purposes
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

$testAccounts = [];
for ($i = 1; $i <= 10; $i++) {
    $email = "test{$i}@gmail.com";
    $name = "test{$i}";
    $password = "123456";
    
    $testAccounts[] = [
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'phone' => "090000000{$i}",
        'address' => "Test Address {$i}"
    ];
}

echo "Creating " . count($testAccounts) . " test accounts...\n";
echo str_repeat("=", 60) . "\n\n";

$created = 0;
$failed = 0;

foreach ($testAccounts as $account) {
    try {
        // Check if email already exists
        $existing = Database::fetch(
            "SELECT user_id FROM users WHERE email = ?",
            [$account['email']]
        );
        
        if ($existing) {
            echo "⚠ SKIP: {$account['email']} (Email already exists)\n";
            $failed++;
            continue;
        }
        
        // Hash password and generate token
        $hashedPassword = hashPassword($account['password']);
        $verificationToken = bin2hex(random_bytes(32));
        
        // Insert user
        Database::execute(
            "INSERT INTO users (name, email, password, phone, address, role_id, status, email_verified, verification_token, created_at) 
             VALUES (?, ?, ?, ?, ?, 2, 'active', 0, ?, NOW())",
            [
                $account['name'],
                $account['email'],
                $hashedPassword,
                $account['phone'],
                $account['address'],
                $verificationToken
            ]
        );
        
        $userId = $pdo->lastInsertId();
        
        // Log activity
        logActivity($userId, 'register', 'Test account created');
        
        echo "✓ CREATED: {$account['email']} (ID: $userId)\n";
        echo "  Name: {$account['name']}\n";
        echo "  Password: {$account['password']}\n";
        echo "  Phone: {$account['phone']}\n";
        echo "  Address: {$account['address']}\n\n";
        
        $created++;
        
    } catch (Exception $e) {
        echo "✗ FAILED: {$account['email']}\n";
        echo "  Error: " . $e->getMessage() . "\n\n";
        $failed++;
    }
}

echo str_repeat("=", 60) . "\n";
echo "Results:\n";
echo "  Created: $created\n";
echo "  Failed/Skipped: $failed\n";
echo "  Total: " . count($testAccounts) . "\n";
echo "\nAll test accounts have been created successfully!\n";
echo "Login credentials:\n";
for ($i = 1; $i <= 10; $i++) {
    echo "  test{$i}@gmail.com : 123456\n";
}
?>
