<?php
require 'config/Database.php';

header('Content-Type: text/html; charset=utf-8');

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int)$_POST['item_id'] ?? 0;
    $user_id = (int)$_POST['user_id'] ?? 0;
    $rating_stars = (int)$_POST['rating_stars'] ?? 0;
    $review_text = $_POST['review_text'] ?? '';
    
    if (!$item_id || !$user_id || !$rating_stars) {
        $message = '❌ Lỗi: Thiếu dữ liệu bắt buộc';
    } else if ($rating_stars < 1 || $rating_stars > 5) {
        $message = '❌ Lỗi: Đánh giá phải từ 1-5 sao';
    } else {
        try {
            // Check if rating exists
            $existing = Database::fetch(
                'SELECT rating_id FROM ratings WHERE item_id = ? AND user_id = ?',
                [$item_id, $user_id]
            );
            
            if ($existing) {
                // Update
                Database::execute(
                    'UPDATE ratings SET rating_stars = ?, review_text = ?, updated_at = NOW() WHERE item_id = ? AND user_id = ?',
                    [$rating_stars, $review_text, $item_id, $user_id]
                );
                $message = '✅ Cập nhật đánh giá thành công!';
            } else {
                // Insert
                Database::execute(
                    'INSERT INTO ratings (item_id, user_id, rating_stars, review_text, created_at) VALUES (?, ?, ?, ?, NOW())',
                    [$item_id, $user_id, $rating_stars, $review_text]
                );
                $message = '✅ Thêm đánh giá thành công!';
            }
            
            // Update inventory average
            $avgResult = Database::fetch(
                'SELECT AVG(rating_stars) as avg, COUNT(*) as count FROM ratings WHERE item_id = ?',
                [$item_id]
            );
            
            Database::execute(
                'UPDATE inventory SET average_rating = ?, rating_count = ? WHERE item_id = ?',
                [$avgResult['avg'], $avgResult['count'], $item_id]
            );
            
            $success = true;
        } catch (Exception $e) {
            $message = '❌ Lỗi database: ' . $e->getMessage();
        }
    }
}

// Get all items for dropdown
$items = Database::fetchAll('SELECT item_id, name FROM inventory ORDER BY item_id LIMIT 50');
$users = Database::fetchAll('SELECT user_id, name FROM users WHERE role_id IN (1,2) ORDER BY user_id LIMIT 20');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Ghi Đánh Giá</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">🧪 Test: Ghi Đánh Giá Vào Database</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="item_id" class="form-label fw-bold">Sản phẩm *</label>
                                <select class="form-control" id="item_id" name="item_id" required>
                                    <option value="">-- Chọn sản phẩm --</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?php echo $item['item_id']; ?>">
                                            [ID <?php echo $item['item_id']; ?>] <?php echo htmlspecialchars($item['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Item ID 35 = Áo (dùng để test)</small>
                            </div>

                            <div class="mb-3">
                                <label for="user_id" class="form-label fw-bold">Người dùng *</label>
                                <select class="form-control" id="user_id" name="user_id" required>
                                    <option value="">-- Chọn người dùng --</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>">
                                            [ID <?php echo $user['user_id']; ?>] <?php echo htmlspecialchars($user['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">ID 1 = Administrator</small>
                            </div>

                            <div class="mb-3">
                                <label for="rating_stars" class="form-label fw-bold">Số sao *</label>
                                <select class="form-control" id="rating_stars" name="rating_stars" required>
                                    <option value="">-- Chọn --</option>
                                    <option value="1">⭐ 1 sao</option>
                                    <option value="2">⭐⭐ 2 sao</option>
                                    <option value="3">⭐⭐⭐ 3 sao</option>
                                    <option value="4">⭐⭐⭐⭐ 4 sao</option>
                                    <option value="5" selected>⭐⭐⭐⭐⭐ 5 sao</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="review_text" class="form-label fw-bold">Nội dung đánh giá</label>
                                <textarea class="form-control" id="review_text" name="review_text" rows="3" placeholder="Nhận xét về sản phẩm..."></textarea>
                                <small class="text-muted">Có thể để trống</small>
                            </div>

                            <button type="submit" class="btn btn-success btn-lg w-100">
                                <i class="bi bi-check-circle"></i> Ghi vào Database
                            </button>
                        </form>

                        <hr class="my-4">

                        <div class="alert alert-info">
                            <strong>📝 Hướng dẫn:</strong>
                            <ol class="mb-0 mt-2">
                                <li>Chọn Sản phẩm (ID 35 để test)</li>
                                <li>Chọn Người dùng</li>
                                <li>Chọn số sao</li>
                                <li>Nhập nội dung (tuỳ chọn)</li>
                                <li>Nhấn "Ghi vào Database"</li>
                                <li>Vào <a href="http://localhost/item-detail.php?id=35">trang sản phẩm</a> để kiểm tra</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="mt-3 text-center">
                    <a href="item-detail.php?id=35" class="btn btn-outline-primary">
                        👁️ Xem sản phẩm ID 35
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
