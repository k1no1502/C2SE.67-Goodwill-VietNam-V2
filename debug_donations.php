<?php
require 'config/Database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Debug: Kiểm Tra Donations Duplicate</h2>";

try {
    // Check donations by count
    $result = Database::fetch('SELECT COUNT(*) as total FROM donations');
    echo "<p><strong>Tổng donations trong database:</strong> " . $result['total'] . "</p>";
    
    // Check if there are multiple donations with same user in same day
    echo "\n<h3>Donations cùng ngày cùng user:</h3>";
    $duplicates = Database::fetchAll("
        SELECT 
            DATE(created_at) as date,
            user_id,
            COUNT(*) as count
        FROM donations
        GROUP BY DATE(created_at), user_id
        HAVING count > 1
        ORDER BY created_at DESC
    ");
    
    if (empty($duplicates)) {
        echo "<p>✅ Không tìm thấy duplicate donations</p>";
    } else {
        echo "<table border='1' cellpadding='10' style='width:100%; margin-top:10px'>";
        echo "<tr><th>Ngày</th><th>User ID</th><th>Số lần quyên góp</th></tr>";
        foreach ($duplicates as $dup) {
            echo "<tr>";
            echo "<td>" . $dup['date'] . "</td>";
            echo "<td>" . $dup['user_id'] . "</td>";
            echo "<td style='color:red; font-weight:bold'>" . $dup['count'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Show all donations with user info
    echo "\n<h3>Tất cả Donations:</h3>";
    $allDonations = Database::fetchAll("
        SELECT 
            d.donation_id,
            d.user_id,
            u.name,
            d.item_name,
            d.quantity,
            d.status,
            d.created_at
        FROM donations d
        LEFT JOIN users u ON d.user_id = u.user_id
        ORDER BY d.created_at DESC
        LIMIT 50
    ");
    
    if (!empty($allDonations)) {
        echo "<table border='1' cellpadding='8' style='width:100%; font-size:12px'>";
        echo "<tr style='background:#ccc'><th>#</th><th>User</th><th>Vật phẩm</th><th>Qty</th><th>Status</th><th>Ngày</th></tr>";
        foreach ($allDonations as $d) {
            echo "<tr>";
            echo "<td>" . $d['donation_id'] . "</td>";
            echo "<td>" . $d['name'] . " (ID:" . $d['user_id'] . ")</td>";
            echo "<td>" . $d['item_name'] . "</td>";
            echo "<td>{$d['quantity']}</td>";
            echo "<td>" . strtoupper($d['status']) . "</td>";
            echo "<td>" . $d['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Lỗi: " . $e->getMessage() . "</p>";
}
?>
