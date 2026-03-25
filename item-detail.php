<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$item_id = (int)($_GET['id'] ?? 0);

if ($item_id <= 0) {
    header('Location: shop.php');
    exit();
}

// Get item details
$sql = "SELECT i.*,
        c.name as category_name, c.icon as category_icon,
        d.user_id as donor_id, u.name as donor_name, d.created_at as donation_date,
        GREATEST(i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id), 0), 0) as available_quantity
        FROM inventory i
        LEFT JOIN categories c ON i.category_id = c.category_id
        LEFT JOIN donations d ON i.donation_id = d.donation_id
        LEFT JOIN users u ON d.user_id = u.user_id
        WHERE i.item_id = ?";
$item = Database::fetch($sql, [$item_id]);

if (!$item) {
    header('Location: shop.php');
    exit();
}

// Get related items (same category)
$relatedItems = Database::fetchAll(
    "SELECT i.*, c.name as category_name 
     FROM inventory i 
     LEFT JOIN categories c ON i.category_id = c.category_id
     WHERE i.category_id = ? AND i.item_id != ? AND i.status = 'available' AND i.is_for_sale = TRUE
     ORDER BY RAND()
     LIMIT 4",
    [$item['category_id'], $item_id]
);

// Check if in cart
$inCart = false;
if (isLoggedIn()) {
    $inCart = Database::fetch(
        "SELECT * FROM cart WHERE user_id = ? AND item_id = ?",
        [$_SESSION['user_id'], $item_id]
    ) !== false;
}

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_rating') {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
    
    // Get item_id from either GET or POST for better compatibility
    $ratingItemId = (int)($_POST['item_id'] ?? $_GET['id'] ?? 0);
    if ($ratingItemId <= 0) {
        $ratingItemId = $item_id; // Use the already-extracted item_id
    }
    
    $rating_stars = (int)($_POST['rating_stars'] ?? 0);
    $review_text = sanitize($_POST['review_text'] ?? '');
    
    // Validation
    if ($rating_stars < 1 || $rating_stars > 5) {
        setFlashMessage('error', 'Vui lòng chọn đánh giá từ 1 đến 5 sao.');
        header("Location: item-detail.php?id=$ratingItemId");
        exit();
    }
    
    if ($ratingItemId <= 0) {
        setFlashMessage('error', 'Sản phẩm không tồn tại.');
        header("Location: shop.php");
        exit();
    }
    
    try {
        // Check if user already rated this item
        $existingRating = Database::fetch(
            "SELECT rating_id FROM ratings WHERE item_id = ? AND user_id = ?",
            [$ratingItemId, $_SESSION['user_id']]
        );
        
        if ($existingRating) {
            // Update existing rating
            Database::execute(
                "UPDATE ratings SET rating_stars = ?, review_text = ?, updated_at = NOW() 
                 WHERE item_id = ? AND user_id = ?",
                [$rating_stars, $review_text ?: null, $ratingItemId, $_SESSION['user_id']]
            );
            $successMsg = 'Cập nhật đánh giá thành công!';
        } else {
            // Insert new rating
            Database::execute(
                "INSERT INTO ratings (item_id, user_id, rating_stars, review_text, created_at) 
                 VALUES (?, ?, ?, ?, NOW())",
                [$ratingItemId, $_SESSION['user_id'], $rating_stars, $review_text ?: null]
            );
            $successMsg = 'Đánh giá thành công!';
        }
        
        // Update inventory average rating
        $stats = Database::fetch(
            "SELECT AVG(rating_stars) as avg_rating, COUNT(*) as count 
             FROM ratings WHERE item_id = ?",
            [$ratingItemId]
        );
        
        Database::execute(
            "UPDATE inventory SET average_rating = ?, rating_count = ? WHERE item_id = ?",
            [$stats['avg_rating'] ?? 0, $stats['count'] ?? 0, $ratingItemId]
        );
        
        // Log activity
        logActivity($_SESSION['user_id'], 'product_rating', "Rated item #$ratingItemId with $rating_stars stars");
        
        setFlashMessage('success', $successMsg);
        
        // Check if this is an AJAX/Fetch request
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        if ($isAjax) {
            // Return JSON for AJAX/Fetch
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => $successMsg,
                'rating_stars' => $rating_stars
            ]);
            exit();
        } else {
            // Redirect for regular form submission
            header("Location: item-detail.php?id=$ratingItemId");
            exit();
        }
        
    } catch (Exception $e) {
        $errorMsg = 'Có lỗi xảy ra khi lưu đánh giá.';
        setFlashMessage('error', $errorMsg);
        error_log("Rating error: " . $e->getMessage());
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => $errorMsg
            ]);
            exit();
        }
        
        header("Location: item-detail.php?id=$ratingItemId");
        exit();
    }
}

// Get ratings for this item
$ratings = Database::fetchAll(
    "SELECT r.*, u.name as user_name, u.avatar 
     FROM ratings r 
     LEFT JOIN users u ON r.user_id = u.user_id 
     WHERE r.item_id = ? 
     ORDER BY r.created_at DESC",
    [$item_id]
);

// Get user's rating if logged in
$userRating = null;
if (isLoggedIn()) {
    $userRating = Database::fetch(
        "SELECT * FROM ratings WHERE item_id = ? AND user_id = ?",
        [$item_id, $_SESSION['user_id']]
    );
}

$images = json_decode($item['images'] ?? '[]', true);
$availableQty = max(0, (int)($item['available_quantity'] ?? 0));
$pageTitle = $item['name'];
include 'includes/header.php';
?>

<style>
.item-detail-page {
    background: radial-gradient(circle at 8% 10%, rgba(14, 116, 144, 0.12), transparent 30%), #f6fbfd;
}
.item-shell {
    border: 1px solid #cfe8ef;
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 12px 30px rgba(14, 116, 144, 0.1);
}
.item-media-wrap {
    background: #eff9fc;
    border-radius: 18px;
    border: 1px solid #d3ebf2;
    overflow: hidden;
}
.thumb-strip img {
    border: 2px solid transparent;
    border-radius: 10px;
    height: 88px;
    object-fit: cover;
    cursor: pointer;
    transition: all 0.2s ease;
}
.thumb-strip img:hover { transform: translateY(-2px); }
.thumb-strip img.active { border-color: #0e7490; }

.item-title { font-size: clamp(1.6rem, 2.6vw, 2.2rem); font-weight: 800; color: #0f172a; }
.price-main {
    background: linear-gradient(135deg, #0e7490, #155e75);
    color: #fff;
    border-radius: 999px;
    padding: 0.55rem 1rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-weight: 700;
}
.info-mini {
    border: 1px solid #d5eaf0;
    border-radius: 14px;
    padding: 0.9rem;
    background: #fafdfe;
    height: 100%;
}
.info-mini .label { color: #64748b; font-size: 0.8rem; }
.info-mini .value { font-weight: 700; color: #0f172a; }

.btn-item-primary {
    background: linear-gradient(135deg, #0e7490, #155e75);
    border: none;
    color: #fff;
    border-radius: 12px;
    font-weight: 700;
    padding: 0.8rem 1rem;
}
.btn-item-primary:hover { filter: brightness(0.93); color: #fff; }

.section-block {
    border: 1px solid #d6ebf1;
    border-radius: 16px;
    background: #fff;
}
.section-head {
    border-bottom: 1px solid #e3f1f5;
    padding: 1rem 1.2rem;
    font-weight: 700;
    color: #0e7490;
}
.rating-summary-box {
    background: linear-gradient(135deg, #f3fbfe, #ffffff);
    border: 1px solid #d6ebf1;
    border-radius: 14px;
    padding: 1.1rem;
}

.rating-form {
    background: linear-gradient(135deg, #fafdfe 0%, #f0f9fc 100%);
    border: 1px solid #d6ebf1;
    border-radius: 14px;
    padding: 1.2rem;
    margin-bottom: 1.5rem;
}

.star-rating {
    display: flex;
    gap: 12px;
    margin: 15px 0;
    flex-wrap: wrap;
}

.star-item {
    transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    cursor: pointer;
    outline: none;
}

.star-item:hover {
    transform: scale(1.15) rotate(-5deg);
    filter: drop-shadow(0 4px 8px rgba(255, 193, 7, 0.4));
}

.star-item:focus {
    outline: 2px solid #0e7490;
    border-radius: 50%;
}

.review-item {
    border: 1px solid #e3f1f5;
    border-radius: 14px;
    padding: 1rem;
    background: #fcfeff;
    transition: all 0.3s ease;
    margin-bottom: 0.5rem;
}

.review-item:hover {
    box-shadow: 0 4px 12px rgba(14, 116, 144, 0.08);
    border-color: #bfe8f0;
}

.related-card {
    border: 1px solid #d3e9f1;
    border-radius: 14px;
    background: #fff;
    transition: all 0.22s ease;
}
.related-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 24px rgba(14, 116, 144, 0.12);
}
</style>

<div class="item-detail-page pt-5 mt-4 pb-5">
    <div class="container py-4">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="shop.php">Shop</a></li>
                <li class="breadcrumb-item"><a href="shop.php?category=<?php echo $item['category_id']; ?>"><?php echo htmlspecialchars($item['category_name'] ?? 'Khác'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars(substr($item['name'], 0, 32)); ?></li>
            </ol>
        </nav>

        <div class="item-shell p-3 p-lg-4 mb-4">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="item-media-wrap p-2 p-md-3">
                        <?php if (!empty($images)): ?>
                            <div class="position-relative">
                                <img id="mainImage"
                                     src="uploads/donations/<?php echo $images[0]; ?>"
                                     class="img-fluid w-100 rounded-3"
                                     style="height: 460px; object-fit: cover;"
                                     onerror="this.src='uploads/donations/placeholder-default.svg'"
                                     alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <?php if ($item['status'] !== 'available'): ?>
                                    <span class="badge bg-danger position-absolute top-0 start-0 m-3 px-3 py-2">
                                        <?php echo $item['status'] === 'sold' ? 'Đã bán' : 'Không có sẵn'; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (count($images) > 1): ?>
                                <div class="thumb-strip row g-2 mt-2">
                                    <?php foreach ($images as $index => $img): ?>
                                        <div class="col-3">
                                            <img src="uploads/donations/<?php echo $img; ?>"
                                                 class="img-fluid thumbnail-img <?php echo $index === 0 ? 'active' : ''; ?>"
                                                 onclick="changeMainImage('uploads/donations/<?php echo $img; ?>', this)"
                                                 onerror="this.src='uploads/donations/placeholder-default.svg'"
                                                 alt="Ảnh phụ">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <img src="uploads/donations/placeholder-default.svg" class="img-fluid w-100 rounded-3" style="height: 460px; object-fit: cover;" alt="placeholder">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-6">
                    <h1 class="item-title mb-3"><?php echo htmlspecialchars($item['name']); ?></h1>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <?php if ($item['price_type'] === 'free'): ?>
                            <span class="price-main"><i class="bi bi-gift"></i>Miễn phí</span>
                            <small class="text-muted">Giá trị tham khảo: <del><?php echo formatCurrency($item['estimated_value']); ?></del></small>
                        <?php else: ?>
                            <span class="price-main"><i class="bi bi-cash-stack"></i><?php echo formatCurrency($item['sale_price']); ?></span>
                            <?php if ($item['estimated_value'] > $item['sale_price']): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                    Tiết kiệm <?php echo round((($item['estimated_value'] - $item['sale_price']) / max(1, $item['estimated_value'])) * 100); ?>%
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php
                    $conditionMap = [
                        'new' => 'Mới 100%',
                        'like_new' => 'Như mới',
                        'good' => 'Tốt',
                        'fair' => 'Khá',
                        'poor' => 'Cũ'
                    ];
                    ?>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="info-mini">
                                <div class="label">Danh mục</div>
                                <div class="value"><i class="bi bi-tag me-1 text-muted"></i><?php echo htmlspecialchars($item['category_name'] ?? 'Khác'); ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-mini">
                                <div class="label">Tình trạng</div>
                                <div class="value"><i class="bi bi-stars me-1 text-muted"></i><?php echo $conditionMap[$item['condition_status']] ?? 'N/A'; ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-mini">
                                <div class="label">Số lượng còn</div>
                                <div class="value"><i class="bi bi-box-seam me-1 text-muted"></i><?php echo $availableQty . ' ' . htmlspecialchars($item['unit']); ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="info-mini">
                                <div class="label">Vị trí</div>
                                <div class="value"><i class="bi bi-geo-alt me-1 text-muted"></i><?php echo htmlspecialchars($item['location'] ?? 'Kho chính'); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?php if ($item['status'] === 'available'): ?>
                            <?php if (isLoggedIn()): ?>
                                <?php if ($inCart): ?>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-secondary btn-lg" disabled><i class="bi bi-check-circle me-2"></i>Đã có trong giỏ hàng</button>
                                        <a href="cart.php" class="btn btn-outline-secondary btn-lg"><i class="bi bi-cart3 me-2"></i>Xem giỏ hàng</a>
                                    </div>
                                <?php elseif ($availableQty <= 0): ?>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-secondary btn-lg" disabled><i class="bi bi-x-circle me-2"></i>Hết hàng</button>
                                        <a href="shop.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Tiếp tục mua sắm</a>
                                    </div>
                                <?php else: ?>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-item-primary btn-lg add-to-cart" data-item-id="<?php echo $item_id; ?>"><i class="bi bi-cart-plus me-2"></i>Thêm vào giỏ hàng</button>
                                        <a href="shop.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Tiếp tục mua sắm</a>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="d-grid">
                                    <a href="login.php?redirect=item-detail.php?id=<?php echo $item_id; ?>" class="btn btn-item-primary btn-lg"><i class="bi bi-lock me-2"></i>Đăng nhập để đặt hàng</a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-danger text-center"><i class="bi bi-x-circle-fill me-2"></i><strong>Vật phẩm này đã được bán</strong></div>
                            <div class="d-grid"><a href="shop.php" class="btn btn-outline-secondary btn-lg"><i class="bi bi-arrow-left me-2"></i>Xem vật phẩm khác</a></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($item['description']): ?>
                        <div class="section-block p-3 mb-3">
                            <h6 class="fw-bold mb-2" style="color:#0e7490;"><i class="bi bi-file-text me-2"></i>Mô tả chi tiết</h6>
                            <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="section-block p-3">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <small class="text-muted d-block">Ngày quyên góp</small>
                                <strong><?php echo formatDate($item['donation_date'], 'd/m/Y'); ?></strong>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">Người quyên góp</small>
                                <strong><?php echo htmlspecialchars($item['donor_name'] ?? 'Ẩn danh'); ?></strong>
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn share-btn share-facebook" onclick="shareOnFacebook()"><i class="bi bi-facebook me-2"></i>Facebook</button>
                            <button class="btn share-btn share-x" onclick="shareOnX()"><i class="bi bi-twitter-x me-2"></i>X</button>
                            <button class="btn share-btn share-copy" onclick="copyLink()"><i class="bi bi-link-45deg me-2"></i>Sao chép link</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-block mb-4">
            <div class="section-head">
                <i class="bi bi-star-fill me-2"></i>Đánh giá sản phẩm
                <span class="badge ms-2" style="background:#e2f3f8;color:#0e7490;"><?php echo count($ratings); ?> đánh giá</span>
            </div>
            <div class="p-3 p-md-4">
                <?php
                $ratingDistribution = [];
                for ($i = 5; $i >= 1; $i--) {
                    $count = count(array_filter($ratings, fn($r) => $r['rating_stars'] == $i));
                    $ratingDistribution[$i] = $count;
                }
                $totalRatings = count($ratings);
                $avgRating = round($item['average_rating'] ?? 0, 1);
                ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="rating-summary-box text-center h-100">
                            <div class="display-4 fw-bold text-warning mb-1"><?php echo $avgRating; ?></div>
                            <div class="stars-display mb-1">
                                <?php $avgRounded = round($item['average_rating'] ?? 0); for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi <?php echo $i <= $avgRounded ? 'bi-star-fill' : 'bi-star'; ?> text-warning"></i>
                                <?php endfor; ?>
                            </div>
                            <small class="text-muted">Trung bình / 5 sao</small>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="rating-summary-box h-100">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="me-2" style="min-width: 38px;"><?php echo $i; ?> <i class="bi bi-star-fill text-warning"></i></span>
                                    <div class="progress flex-grow-1" style="height: 8px;">
                                        <div class="progress-bar" style="background:linear-gradient(90deg,#0e7490,#155e75); width: <?php echo $totalRatings > 0 ? ($ratingDistribution[$i] / $totalRatings * 100) : 0; ?>%"></div>
                                    </div>
                                    <span class="ms-2 text-muted" style="min-width: 24px;"><?php echo $ratingDistribution[$i]; ?></span>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold mb-3"><?php echo isLoggedIn() ? 'Đánh giá của bạn' : 'Đăng nhập để đánh giá'; ?></h6>
                    <?php if (isLoggedIn()): ?>
                        <form method="POST" id="ratingForm" class="rating-form">
                            <input type="hidden" name="action" value="submit_rating">
                            <input type="hidden" name="rating_stars" id="ratingStars" value="<?php echo $userRating['rating_stars'] ?? 0; ?>">

                            <div class="mb-4">
                                <label class="form-label fw-bold">Chọn số sao <span class="text-danger">*</span></label>
                                <div class="star-rating" id="starRating" role="group" aria-label="Chọn đánh giá bằng sao">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi bi-star star-item" 
                                           data-value="<?php echo $i; ?>" 
                                           role="button" 
                                           tabindex="0"
                                           aria-label="<?php echo $i; ?> sao" 
                                           style="font-size:2.2rem; cursor:pointer; color: <?php echo ($userRating && $userRating['rating_stars'] >= $i) ? '#FFC107' : '#DDD'; ?>;"></i>
                                    <?php endfor; ?>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <span id="starCount"><?php echo $userRating ? $userRating['rating_stars'] . ' sao' : 'Chưa chọn'; ?></span>
                                </small>
                            </div>

                            <div class="mb-4">
                                <label for="reviewText" class="form-label fw-bold">Đánh giá chi tiết <span class="text-muted">(tùy chọn)</span></label>
                                <textarea class="form-control" 
                                          id="reviewText" 
                                          name="review_text" 
                                          rows="5" 
                                          maxlength="500" 
                                          placeholder="Hãy chia sẻ trải nghiệm của bạn về sản phẩm này. Những góp ý chi tiết sẽ giúp ích cho những người khác..."><?php echo htmlspecialchars($userRating['review_text'] ?? ''); ?></textarea>
                                <div class="d-flex justify-content-between mt-2">
                                    <small class="text-muted"><span id="charCount"><?php echo strlen($userRating['review_text'] ?? ''); ?></span>/500 ký tự</small>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex" role="group">
                                <button type="submit" class="btn btn-item-primary flex-grow-1" id="submitRating">
                                    <i class="bi bi-check-circle me-2"></i><?php echo $userRating ? 'Cập nhật đánh giá' : 'Gửi đánh giá'; ?>
                                </button>
                                <button type="reset" class="btn btn-outline-secondary flex-md-grow-0">
                                    <i class="bi bi-arrow-counterclockwise me-2"></i>Xóa
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info mb-0 d-flex align-items-center" role="alert">
                            <i class="bi bi-info-circle me-2"></i>
                            <span><a href="login.php?redirect=item-detail.php?id=<?php echo $item_id; ?>" class="alert-link">Đăng nhập</a> để trở thành người đầu tiên đánh giá sản phẩm này.</span>
                        </div>
                    <?php endif; ?>
                </div>

                <h6 class="fw-bold mb-3"><i class="bi bi-chat-left-text me-2"></i>Các đánh giá khác</h6>
                <?php if (empty($ratings)): ?>
                    <div class="alert alert-secondary mb-0"><em>Chưa có đánh giá nào. Hãy là người đầu tiên đánh giá!</em></div>
                <?php else: ?>
                    <div class="d-grid gap-2">
                        <?php foreach ($ratings as $review): ?>
                            <div class="review-item">
                                <div class="d-flex align-items-start gap-3">
                                    <div>
                                        <?php if ($review['avatar']): ?>
                                            <img src="<?php echo htmlspecialchars($review['avatar']); ?>" alt="<?php echo htmlspecialchars($review['user_name']); ?>" class="rounded-circle" style="width: 42px; height: 42px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width:42px;height:42px;"><i class="bi bi-person"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between flex-wrap">
                                            <div>
                                                <strong><?php echo htmlspecialchars($review['user_name'] ?? 'Ẩn danh'); ?></strong>
                                                <div class="stars-display">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi <?php echo $i <= $review['rating_stars'] ? 'bi-star-fill' : 'bi-star'; ?> text-warning" style="font-size:0.9rem;"></i>
                                                    <?php endfor; ?>
                                                    <span class="text-muted ms-2" style="font-size:0.85rem;"><?php echo $review['rating_stars']; ?>/5</span>
                                                </div>
                                            </div>
                                            <small class="text-muted"><?php echo formatDate($review['created_at'], 'd/m/Y H:i'); ?></small>
                                        </div>
                                        <?php if ($review['review_text']): ?>
                                            <p class="mb-0 mt-2 text-muted"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($relatedItems)): ?>
            <div class="mb-2 d-flex justify-content-between align-items-center">
                <h4 class="fw-bold mb-0"><i class="bi bi-box-seam me-2"></i>Vật phẩm tương tự</h4>
                <a href="shop.php?category=<?php echo $item['category_id']; ?>" class="btn btn-sm btn-outline-secondary">Xem thêm</a>
            </div>
            <div class="row g-3">
                <?php foreach ($relatedItems as $relatedItem):
                    $relatedImages = json_decode($relatedItem['images'] ?? '[]', true);
                    $relatedImageUrl = !empty($relatedImages) ? 'uploads/donations/' . $relatedImages[0] : 'uploads/donations/placeholder-default.svg';
                    $relatedPriceDisplay = $relatedItem['price_type'] === 'free' ? 'Miễn phí' : formatCurrency($relatedItem['sale_price']);
                ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="related-card h-100 overflow-hidden">
                            <img src="<?php echo $relatedImageUrl; ?>" class="w-100" style="height:170px; object-fit:cover;" onerror="this.src='uploads/donations/placeholder-default.svg'" alt="related">
                            <div class="p-2 p-md-3">
                                <h6 class="mb-1"><?php echo htmlspecialchars(substr($relatedItem['name'], 0, 44)); ?></h6>
                                <small class="text-muted d-block mb-2"><i class="bi bi-tag me-1"></i><?php echo htmlspecialchars($relatedItem['category_name']); ?></small>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge" style="background:#e2f3f8;color:#0e7490;"><?php echo $relatedPriceDisplay; ?></span>
                                    <a href="item-detail.php?id=<?php echo $relatedItem['item_id']; ?>" class="btn btn-sm btn-outline-secondary">Xem</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$additionalScripts = "
<script>
// Star Rating Interaction
document.addEventListener('DOMContentLoaded', function() {
    const starRating = document.getElementById('starRating');
    const ratingStarsInput = document.getElementById('ratingStars');
    const starCount = document.getElementById('starCount');
    const stars = document.querySelectorAll('#starRating .star-item');
    const ratingForm = document.getElementById('ratingForm');
    const submitRating = document.getElementById('submitRating');
    const reviewText = document.getElementById('reviewText');
    const charCount = document.getElementById('charCount');
    const itemId = " . $item_id . ";
    
    // Star selection
    if (starRating) {
        stars.forEach((star, index) => {
            star.addEventListener('click', function() {
                const value = this.dataset.value;
                ratingStarsInput.value = value;
                
                // Update star colors
                stars.forEach((s, i) => {
                    if (i < value) {
                        s.style.color = '#FFC107';
                        s.classList.add('bi-star-fill');
                        s.classList.remove('bi-star');
                    } else {
                        s.style.color = '#DDD';
                        s.classList.remove('bi-star-fill');
                        s.classList.add('bi-star');
                    }
                });
                
                // Update text
                starCount.textContent = value;
                
                // Enable submit button
                if (submitRating) {
                    submitRating.disabled = false;
                }
            });
            
            // Hover effect
            star.addEventListener('mouseenter', function() {
                const value = this.dataset.value;
                stars.forEach((s, i) => {
                    if (i < value) {
                        s.style.color = '#FFC107';
                    } else {
                        s.style.color = '#DDD';
                    }
                });
            });
        });
        
        // Mouse leave - reset to current value
        starRating.addEventListener('mouseleave', function() {
            const currentValue = parseInt(ratingStarsInput.value) || 0;
            stars.forEach((s, i) => {
                if (i < currentValue) {
                    s.style.color = '#FFC107';
                } else {
                    s.style.color = '#DDD';
                }
            });
        });
    }
    
    // Character counter
    if (reviewText && charCount) {
        reviewText.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });
        
        // Initialize character count
        charCount.textContent = reviewText.value.length;
    }
    
    // Keyboard support for star rating
    if (starRating) {
        stars.forEach((star, index) => {
            star.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });
    }
    
    // Form submission
    if (ratingForm) {
        ratingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const rating = parseInt(ratingStarsInput.value);
            if (rating === 0) {
                GoodwillVietnam.showAlert('⚠️ Vui lòng chọn số sao trước khi gửi đánh giá!', 'warning');
                return false;
            }
            
            // Disable submit button
            submitRating.disabled = true;
            const originalText = submitRating.innerHTML;
            submitRating.innerHTML = '<span class=\"spinner-border spinner-border-sm me-2\"></span>Đang gửi...';
            
            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'submit_rating');
            formData.append('rating_stars', rating);
            formData.append('review_text', reviewText.value);
            
            // Submit via fetch - include item_id in URL to ensure proper routing
            fetch('item-detail.php?id=' + itemId, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                // Try to parse as JSON first, fall back to text
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text().then(text => ({isHtml: true, html: text}));
                }
            })
            .then(data => {
                if (data.isHtml) {
                    // If HTML response, just reload
                    GoodwillVietnam.showAlert('✓ Đánh giá của bạn đã được gửi thành công!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 800);
                } else if (data.success) {
                    // JSON response
                    GoodwillVietnam.showAlert('✓ ' + (data.message || 'Đánh giá của bạn đã được gửi thành công!'), 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 800);
                } else {
                    throw new Error(data.message || 'Có lỗi không xác định');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                GoodwillVietnam.showAlert('❌ Có lỗi khi gửi đánh giá: ' + error.message, 'error');
                submitRating.disabled = false;
                submitRating.innerHTML = originalText;
            });
        });
    }
});
</script>

<style>
/* Form and Validation */
.rating-form textarea.form-control:focus {
    border-color: #0e7490;
    box-shadow: 0 0 0 0.2rem rgba(14, 116, 144, 0.15);
}

/* Animation for new reviews */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.review-item {
    animation: fadeInUp 0.4s ease-out;
}

/* Responsive design */
@media (max-width: 576px) {
    .star-rating {
        gap: 8px;
    }
    
    .star-item {
        font-size: 1.8rem !important;
    }
}
</style>

<script>
// Change main image when clicking thumbnail
function changeMainImage(src, element) {
    document.getElementById('mainImage').src = src;
    
    // Remove active class from all thumbnails
    document.querySelectorAll('.thumbnail-img').forEach(img => {
        img.style.borderColor = 'transparent';
        img.classList.remove('active');
    });
    
    // Add active class to clicked thumbnail
    element.style.borderColor = '#198754';
    element.classList.add('active');
}

// Add to cart
const addToCartBtn = document.querySelector('.add-to-cart');
if (addToCartBtn) {
    addToCartBtn.addEventListener('click', function() {
        const itemId = this.dataset.itemId;
        const btn = this;
        
        btn.disabled = true;
        btn.innerHTML = '<span class=\"spinner-border spinner-border-sm me-2\"></span>Đang thêm...';
        
        fetch('api/add-to-cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: itemId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const cartCountEl = document.getElementById('cart-count');
                if (cartCountEl) {
                    cartCountEl.textContent = data.cart_count;
                }
                GoodwillVietnam.showAlert('Đã thêm vào giỏ hàng!', 'success');
                
                // Update button
                btn.outerHTML = \`
                    <a href=\"cart.php\" class=\"btn btn-success btn-lg w-100\">
                        <i class=\"bi bi-cart-check me-2\"></i>Xem giỏ hàng
                    </a>
                \`;
            } else {
                GoodwillVietnam.showAlert(data.message || 'Có lỗi xảy ra!', 'error');
                btn.innerHTML = '<i class=\"bi bi-cart-plus me-2\"></i>Thêm vào giỏ hàng';
                btn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            GoodwillVietnam.showAlert('Lỗi kết nối!', 'error');
            btn.innerHTML = '<i class=\"bi bi-cart-plus me-2\"></i>Thêm vào giỏ hàng';
            btn.disabled = false;
        });
    });
}


// Share functions
function shareOnFacebook() {
    const url = window.location.href;
    const facebookUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
    window.open(facebookUrl, '_blank', 'width=600,height=400');
}

function shareOnX() {
    const url = window.location.href;
    const xUrl = 'https://x.com/intent/tweet?url=' + encodeURIComponent(url);
    window.open(xUrl, '_blank', 'width=600,height=400');
}

function copyLink() {
    const url = window.location.href;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(() => {
            GoodwillVietnam.showAlert('✓ Đã sao chép liên kết vào clipboard!', 'success');
        }).catch(err => {
            console.error('Failed to copy:', err);
            fallbackCopyLink(url);
        });
    } else {
        fallbackCopyLink(url);
    }
}

function fallbackCopyLink(url) {
    const textarea = document.createElement('textarea');
    textarea.value = url;
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        GoodwillVietnam.showAlert('✓ Đã sao chép liên kết vào clipboard!', 'success');
    } catch (err) {
        GoodwillVietnam.showAlert('✗ Không thể sao chép liên kết!', 'error');
    }
    document.body.removeChild(textarea);
}
</script>

<style>
.share-btn {
    border-radius: 25px;
    padding: 0.6rem 1.2rem;
    font-weight: 600;
    font-size: 0.95rem;
    border: 2px solid;
    transition: all 0.3s ease;
}

.share-facebook {
    border-color: #1877F2;
    color: #1877F2;
}

.share-facebook:hover {
    background-color: #1877F2;
    color: white;
    transform: translateY(-2px);
}

.share-x {
    border-color: #000;
    color: #000;
}

.share-x:hover {
    background-color: #000;
    color: white;
    transform: translateY(-2px);
}

.share-copy {
    border-color: #0E7490;
    color: #0E7490;
}

.share-copy:hover {
    background-color: #0E7490;
    color: white;
    transform: translateY(-2px);
}

.thumbnail-img.active {
    border-color: #0E7490 !important;
}

.thumbnail-img:hover {
    opacity: 0.8;
    transform: scale(1.05);
    transition: all 0.3s ease;
}

#mainImage {
    transition: all 0.3s ease;
}
</style>
";

include 'includes/footer.php';
?>
