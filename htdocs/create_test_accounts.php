<?php
/**
 * Create 10 test accounts for testing
 * Run this script once to populate test accounts
 */
require_once 'config/database.php';
require_once 'includes/functions.php';

try {
    $password = '123456';
    $hashed_password = hashPassword($password);
    $domain = 'goodwillvietnam.com';
    $created_accounts = 0;
    $skipped_accounts = 0;
    
    echo "Đang tạo 10 tài khoản test...\n\n";
    
    for ($i = 1; $i <= 10; $i++) {
        $email = "test{$i}@{$domain}";
        $name = "Test User {$i}";
        
        // Check if email already exists
        $check_sql = "SELECT user_id FROM users WHERE email = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$email]);
        
        if ($check_stmt->fetch()) {
            echo "⊘ Tài khoản {$email} đã tồn tại\n";
            $skipped_accounts++;
        } else {
            // Insert new test account
            $sql = "INSERT INTO users (name, email, password, phone, address, role_id, status, email_verified, created_at) 
                    VALUES (?, ?, ?, ?, ?, 2, 'active', TRUE, NOW())";
            $stmt = $pdo->prepare($sql);
            
            $result = $stmt->execute([
                $name,
                $email,
                $hashed_password,
                '0123456789',
                'Test Address'
            ]);
            
            if ($result) {
                echo "✓ Tài khoản {$email} đã được tạo thành công\n";
                $created_accounts++;
            } else {
                echo "✗ Lỗi khi tạo tài khoản {$email}\n";
            }
        }
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "Kết quả:\n";
    echo "  Tài khoản mới: {$created_accounts}\n";
    echo "  Tài khoản đã tồn tại: {$skipped_accounts}\n";
    echo "  Mật khẩu: 123456\n";
    echo "\nBạn có thể đăng nhập với:\n";
    echo "  Email: test1@goodwillvietnam.com\n";
    echo "  Password: 123456\n";
    echo str_repeat("=", 50) . "\n";
    
} catch (PDOException $e) {
    echo "Lỗi cơ sở dữ liệu: " . $e->getMessage();
}
?>
