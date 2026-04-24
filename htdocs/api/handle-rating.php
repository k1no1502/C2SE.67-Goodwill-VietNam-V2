<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'submit_rating') {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $rating_stars = (int)($_POST['rating_stars'] ?? 0);
    $review_text = sanitize($_POST['review_text'] ?? '');
    
    // Validate
    if ($item_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Item ID invalid']);
        exit();
    }
    
    if ($rating_stars < 1 || $rating_stars > 5) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn đánh giá từ 1 đến 5 sao']);
        exit();
    }
    
    // Check if item exists
    $item = Database::fetch("SELECT item_id FROM inventory WHERE item_id = ?", [$item_id]);
    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Vật phẩm không tồn tại']);
        exit();
    }
    
    try {
        // Check if user already rated
        $existingRating = Database::fetch(
            "SELECT rating_id FROM ratings WHERE item_id = ? AND user_id = ?",
            [$item_id, $_SESSION['user_id']]
        );
        
        if ($existingRating) {
            // Update
            Database::execute(
                "UPDATE ratings SET rating_stars = ?, review_text = ?, updated_at = NOW() 
                 WHERE item_id = ? AND user_id = ?",
                [$rating_stars, $review_text ?: null, $item_id, $_SESSION['user_id']]
            );
            $message = 'Cập nhật đánh giá thành công!';
        } else {
            // Insert
            Database::execute(
                "INSERT INTO ratings (item_id, user_id, rating_stars, review_text, created_at) 
                 VALUES (?, ?, ?, ?, NOW())",
                [$item_id, $_SESSION['user_id'], $rating_stars, $review_text ?: null]
            );
            $message = 'Đánh giá thành công!';
        }
        
        // Update average rating
        $stats = Database::fetch(
            "SELECT AVG(rating_stars) as avg_rating, COUNT(*) as count 
             FROM ratings WHERE item_id = ?",
            [$item_id]
        );
        
        Database::execute(
            "UPDATE inventory SET average_rating = ?, rating_count = ? WHERE item_id = ?",
            [round($stats['avg_rating'] ?? 0, 2), $stats['count'] ?? 0, $item_id]
        );
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'rating' => [
                'stars' => $rating_stars,
                'review' => $review_text,
                'average' => round($stats['avg_rating'] ?? 0, 1),
                'count' => $stats['count'] ?? 0
            ]
        ]);
    } catch (Exception $e) {
        error_log("Rating API error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra khi lưu đánh giá']);
    }
} elseif ($action === 'get_ratings') {
    $item_id = (int)($_GET['item_id'] ?? 0);
    
    if ($item_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Item ID invalid']);
        exit();
    }
    
    try {
        $ratings = Database::fetchAll(
            "SELECT r.*, u.name as user_name, u.avatar 
             FROM ratings r 
             LEFT JOIN users u ON r.user_id = u.user_id 
             WHERE r.item_id = ? 
             ORDER BY r.created_at DESC",
            [$item_id]
        );
        
        echo json_encode([
            'success' => true,
            'ratings' => $ratings,
            'total' => count($ratings)
        ]);
    } catch (Exception $e) {
        error_log("Get ratings error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action not supported']);
}
?>
