<?php
/**
 * Setup Script: Create Test Advisor Account
 * 
 * This script creates a test advisor (Tư vấn viên) account for testing the chat system.
 * Usage: Run this script once to set up the test advisor.
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "=== Setting Up Test Advisor Account ===\n\n";

try {
    // 1. Check if role "staff" exists
    $staffRole = Database::fetch(
        "SELECT role_id FROM roles WHERE role_id = 4 LIMIT 1"
    );
    
    if (!$staffRole) {
        // Try alternative lookup
        $staffRole = Database::fetch(
            "SELECT role_id FROM roles WHERE role_name = 'staff' LIMIT 1"
        );
    }
    
    if (!$staffRole) {
        echo "✗ Staff role (role_id = 4) not found. Please ensure roles are properly created.\n";
        exit(1);
    }
    
    $staffRoleId = (int)$staffRole['role_id'];
    echo "✓ Found staff role ID: $staffRoleId\n";
    
    // 2. Create advisor user if not exists
    $advisorEmail = 'advisor1@gwvn.test';
    $advisorName = 'Tư Vấn Viên 1';
    $advisorPassword = '123456';
    
    $existingUser = Database::fetch(
        "SELECT user_id FROM users WHERE email = ?",
        [$advisorEmail]
    );
    
    if ($existingUser) {
        echo "✓ Advisor user already exists: $advisorEmail\n";
        $advisorUserId = (int)$existingUser['user_id'];
    } else {
        $hashedPassword = hashPassword($advisorPassword);
        $verificationToken = bin2hex(random_bytes(32));
        
        Database::execute(
            "INSERT INTO users (name, email, password, phone, address, role_id, status, email_verified, verification_token, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'active', 1, ?, NOW())",
            [
                $advisorName,
                $advisorEmail,
                $hashedPassword,
                '0901234567',
                'Goodwill Vietnam',
                $staffRoleId,
                $verificationToken
            ]
        );
        
        $advisorUserId = (int)$pdo->lastInsertId();
        echo "✓ Created advisor user: $advisorEmail (ID: $advisorUserId)\n";
    }
    
    // 3. Check if staff record exists
    $staffRecord = Database::fetch(
        "SELECT staff_id FROM staff WHERE user_id = ?",
        [$advisorUserId]
    );
    
    if ($staffRecord) {
        echo "✓ Staff record already exists for user ID: $advisorUserId\n";
        $staffId = (int)$staffRecord['staff_id'];
    } else {
        Database::execute(
            "INSERT INTO staff (user_id, employee_id, position, department, status, hire_date, created_at)
             VALUES (?, ?, 'Tư vấn viên', 'Tư vấn', 'active', NOW(), NOW())",
            [
                $advisorUserId,
                'GW-ADV-001'
            ]
        );
        
        $staffId = (int)$pdo->lastInsertId();
        echo "✓ Created staff record for advisor (Staff ID: $staffId)\n";
    }
    
    echo "\n=== Setup Complete ===\n\n";
    echo "Advisor Account Details:\n";
    echo "  Email: $advisorEmail\n";
    echo "  Password: $advisorPassword\n";
    echo "  Name: $advisorName\n";
    echo "  User ID: $advisorUserId\n";
    echo "  Staff ID: $staffId\n";
    echo "  Position: Tư vấn viên\n";
    echo "\n";
    echo "Login URL: login.php\n";
    echo "Advisor Chat Dashboard: chat-advisor.php\n";
    
} catch (Exception $e) {
    error_log('Setup error: ' . $e->getMessage());
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
