<?php
require 'config/Database.php';
require 'includes/functions.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    // Check total ratings
    $totalRatings = Database::fetch('SELECT COUNT(*) as total FROM ratings');
    echo "=== KIỂM TRA DỮ LIỆU ĐÁNH GIÁ ===\n\n";
    echo "Tổng số đánh giá: " . $totalRatings['total'] . "\n\n";
    
    // Check by item
    echo "=== ĐÁNH GIÁ THEO SẢN PHẨM ===\n";
    $ratingsByItem = Database::fetchAll(
        'SELECT item_id, COUNT(*) as count FROM ratings GROUP BY item_id ORDER BY item_id'
    );
    
    if (empty($ratingsByItem)) {
        echo "❌ Chưa có đánh giá nào trong database!\n";
    } else {
        foreach ($ratingsByItem as $r) {
            echo "- Item ID {$r['item_id']}: {$r['count']} đánh giá\n";
        }
    }
    
    // Check sample ratings
    echo "\n=== CHI TIẾT ĐÁNH GIÁ ===\n";
    $allRatings = Database::fetchAll(
        'SELECT r.*, u.name as user_name FROM ratings r LEFT JOIN users u ON r.user_id = u.user_id ORDER BY r.item_id, r.created_at DESC'
    );
    
    if (!empty($allRatings)) {
        foreach ($allRatings as $rating) {
            echo "- Item {$rating['item_id']}, User: {$rating['user_name']} ({$rating['user_id']}), Stars: {$rating['rating_stars']}/5\n";
            echo "  Text: " . substr($rating['review_text'], 0, 50) . "...\n";
        }
    }
    
    // Check inventory average ratings
    echo "\n=== TRUNG BÌNH ĐÁNH GIÁ SẢN PHẨM ===\n";
    $inventoryRatings = Database::fetchAll(
        'SELECT item_id, name, average_rating, rating_count FROM inventory WHERE average_rating > 0 OR rating_count > 0'
    );
    
    if (empty($inventoryRatings)) {
        echo "⚠️  Chưa cập nhật average_rating trong inventory\n";
    } else {
        foreach ($inventoryRatings as $item) {
            echo "- Item {$item['item_id']}: {$item['name']} -> Avg: {$item['average_rating']}/5 ({$item['rating_count']} đánh giá)\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Lỗi: " . $e->getMessage();
}
?>
