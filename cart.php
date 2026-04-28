<?php
// Bắt đầu session để lưu trữ thông tin người dùng
session_start();

// Kết nối cơ sở dữ liệu và các hàm tiện ích
require_once 'config/database.php';
require_once 'includes/functions.php';

// Kiểm tra người dùng đã đăng nhập hay chưa
requireLogin();

// Tiêu đề trang hiển thị trên trình duyệt
$pageTitle = "Giỏ hàng";

// Lấy danh sách sản phẩm trong giỏ hàng từ cơ sở dữ liệu
// Truy vấn SQL để lấy thông tin chi tiết của từng sản phẩm
$sql = "SELECT 
            c.cart_id,
            c.user_id,
            c.item_id,
            c.quantity AS cart_quantity,
            c.created_at AS cart_created_at,
            i.name AS item_name,
            i.description,
            i.category_id,
            i.quantity AS inventory_quantity,
            GREATEST(i.quantity - COALESCE((SELECT SUM(quantity) FROM cart c2 WHERE c2.item_id = i.item_id AND c2.user_id <> ?), 0), 0) AS available_quantity,
            i.condition_status,
            i.price_type,
            i.sale_price,
            i.unit,
            i.images,
            i.status AS inventory_status,
            cat.name AS category_name
        FROM cart c
        JOIN inventory i ON c.item_id = i.item_id
        LEFT JOIN categories cat ON i.category_id = cat.category_id
        WHERE c.user_id = ? AND i.status = 'available'
        ORDER BY c.created_at DESC";
$cartItems = Database::fetchAll($sql, [$_SESSION['user_id'], $_SESSION['user_id']]);

// Tính toán tổng số lượng và giá trị của các sản phẩm trong giỏ hàng
$totalAmount       = 0; // Tổng tiền
$totalLines        = count($cartItems);   // Số dòng sản phẩm (loại sản phẩm)
$totalFreeLines    = 0;                   // Số dòng sản phẩm miễn phí
$totalPaidLines    = 0;                   // Số dòng sản phẩm trả phí
$totalQuantityAll  = 0;                   // Tổng số lượng tất cả sản phẩm

foreach ($cartItems as $item) {
    $qty = max(0, (int)$item['cart_quantity']);
    $totalQuantityAll += $qty;

    // Đơn giá thực tế: miễn phí = 0, còn lại dùng sale_price
    $unitPrice = ($item['price_type'] === 'free') ? 0 : (float)$item['sale_price'];
    $itemTotal = $unitPrice * $qty;
    $totalAmount += $itemTotal;
    
    if ($item['price_type'] === 'free') {
        $totalFreeLines++;
    } else {
        $totalPaidLines++;
    }
}

// Bao gồm header của trang
include 'includes/header.php';
?>

<style>
    /* Phần CSS để định dạng giao diện giỏ hàng */
    .cart-shell {
        margin-top: 5rem;
        margin-bottom: 2rem;
    }
    .cart-hero {
        border: 1px solid #d9edf2;
        border-radius: 20px;
        padding: 1.25rem 1.4rem;
        background:
            radial-gradient(circle at 88% 10%, rgba(6, 182, 212, 0.16), transparent 40%),
            linear-gradient(135deg, #f8fdff 0%, #edf9fc 100%);
        box-shadow: 0 14px 34px rgba(8, 74, 92, 0.08);
    }
    .cart-title {
        color: #0f172a;
        font-weight: 800;
        margin-bottom: 0.4rem;
    }
    .cart-subtitle {
        color: #64748b;
        margin-bottom: 0;
    }
    .cart-main-card,
    .cart-summary-card {
        border: 1px solid #d9edf2;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 12px 30px rgba(8, 74, 92, 0.08);
        background: #fff;
    }
    .cart-main-head {
        background: linear-gradient(130deg, #ffffff 0%, #eff9fc 100%);
        border-bottom: 1px solid #d9edf2;
        padding: 1rem 1.15rem;
    }
    .cart-link-btn {
        border-color: #06B6D4;
        color: #0891b2;
        border-radius: 10px;
        font-weight: 600;
    }
    .cart-link-btn:hover {
        background: #06B6D4;
        color: #fff;
    }
    .cart-item {
        border-bottom: 1px solid #ecf7fb;
        padding: 1.15rem;
        transition: background-color 0.25s ease;
    }
    .cart-item:last-child {
        border-bottom: none;
    }
    .cart-item:hover {
        background-color: #f7fcfe;
    }
    .product-image {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #ccecf4;
        box-shadow: 0 8px 20px rgba(10, 105, 128, 0.08);
    }
    .product-info h6 {
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.45rem;
    }
    .badge {
        border-radius: 999px;
        padding: 0.4rem 0.7rem;
        font-size: 0.74rem;
        font-weight: 700;
    }
    .badge-free {
        background-color: #10b981;
        color: #fff;
    }
    .badge-paid {
        background-color: #f59e0b;
        color: #fff;
    }
    .badge-condition {
        background-color: #06B6D4;
        color: #fff;
    }
    .qty-input-group {
        max-width: 122px;
    }
    .qty-input-group .btn {
        border: 1px solid #a5e7f3;
        color: #0e7490;
        padding: 0.35rem 0.52rem;
        font-size: 0.9rem;
        background: #fff;
    }
    .qty-input-group .btn:hover:not(:disabled) {
        background-color: #effafd;
    }
    .qty-input-group input {
        border: 1px solid #a5e7f3;
        text-align: center;
        font-weight: 700;
        color: #0e7490;
    }
    .price-display {
        font-size: 1rem;
        font-weight: 800;
        color: #0e7490;
    }
    .btn-delete-item {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background-color: #fef2f2;
        border: 1px solid #fecaca;
        color: #dc2626;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    .btn-delete-item:hover {
        background-color: #ef4444;
        color: #fff;
        transform: translateY(-1px);
    }
    .cart-summary-head {
        background: linear-gradient(135deg, #0891b2 0%, #06B6D4 100%);
        color: #fff;
        padding: 1rem 1.15rem;
    }
    .cart-total {
        color: #0e7490;
        font-size: 1.2rem;
        font-weight: 800;
    }
    .btn-checkout {
        background: linear-gradient(135deg, #0891b2 0%, #06B6D4 100%);
        color: #fff;
        border: none;
        border-radius: 12px;
        font-weight: 700;
    }
    .btn-checkout:hover {
        color: #fff;
        filter: brightness(0.96);
    }
    .security-box {
        background: #f3fbfe;
        border: 1px solid #cbeaf2;
        border-radius: 12px;
    }
    .empty-cart {
        border: 1px solid #d9edf2;
        border-radius: 20px;
        background: #fff;
        box-shadow: 0 12px 30px rgba(8, 74, 92, 0.08);
        padding: 2.8rem 1.5rem;
    }
    .btn-modern-shop {
        background: linear-gradient(135deg, #0891b2 0%, #06B6D4 100%);
        color: #fff;
        border: none;
        border-radius: 12px;
        font-weight: 700;
        padding: 0.7rem 1.2rem;
    }
    .btn-modern-shop:hover {
        color: #fff;
        filter: brightness(0.96);
    }
    @media (max-width: 991.98px) {
        .cart-shell {
            margin-top: 4.7rem;
        }
        .cart-hero {
            padding: 1rem;
        }
        .cart-item {
            padding: 1rem;
        }
    }
</style>

<!-- Nội dung chính của trang giỏ hàng -->
<div class="container cart-shell">
    <!-- Tiêu đề trang -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="cart-hero">
                <h1 class="display-6 cart-title">
                    <i class="bi bi-cart3 me-2" style="color: #06B6D4;"></i>Giỏ hàng của bạn
                </h1>
                <p class="cart-subtitle">Quản lý sản phẩm đã chọn, điều chỉnh số lượng và tiếp tục thanh toán nhanh chóng.</p>
            </div>
        </div>
    </div>

    <?php if (empty($cartItems)): ?>
        <!-- Hiển thị khi giỏ hàng trống -->
        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-7">
                <div class="text-center empty-cart">
                    <i class="bi bi-cart-x display-1" style="color: #cffafe;"></i>
                    <h3 class="mt-4 text-dark fw-bold">Giỏ hàng trống</h3>
                    <p class="text-muted fs-5 mb-4">Hãy thêm sản phẩm vào giỏ hàng để tiếp tục mua sắm</p>
                    <a href="shop.php" class="btn btn-lg btn-modern-shop">
                        <i class="bi bi-shop me-2"></i>Tiếp tục mua sắm
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Hiển thị danh sách sản phẩm trong giỏ hàng -->
        <div class="row">
            <!-- Danh sách sản phẩm -->
            <div class="col-lg-8">
                <div class="cart-main-card">
                    <div class="cart-main-head d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="bi bi-bag me-2" style="color: #06B6D4;"></i>Sản phẩm trong giỏ (<?php echo count($cartItems); ?>)
                        </h5>
                        <a href="shop.php" class="btn btn-outline-primary cart-link-btn">
                            <i class="bi bi-arrow-left me-1"></i>Tiếp tục mua sắm
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($cartItems as $item): ?>
                            <?php
                            $images = json_decode($item['images'] ?? '[]', true);
                            $firstImage = !empty($images) ? resolveDonationImageUrl((string)$images[0]) : 'uploads/donations/placeholder-default.svg';
                            
                            $priceDisplay = '';
                            $priceClass = '';
                            
                            $unitPrice = ($item['price_type'] === 'free') ? 0 : (float)$item['sale_price'];
                            if ($item['price_type'] === 'free') {
                                $priceDisplay = 'Miễn phí';
                                $priceClass = 'text-success';
                            } elseif ($unitPrice > 0) {
                                $priceDisplay = number_format($unitPrice) . ' VNĐ';
                                $priceClass = 'text-warning';
                            } else {
                                $priceDisplay = '0 VNĐ';
                                $priceClass = 'text-info';
                            }
                            
                            $itemTotal = $unitPrice * (int)$item['cart_quantity'];
                            ?>
                            <div class="cart-item" data-cart-id="<?php echo $item['cart_id']; ?>">
                                <div class="row align-items-center g-3">
                                    <!-- Hình ảnh sản phẩm -->
                                    <div class="col-md-2 col-4">
                                        <div class="product-image">
                                            <img src="<?php echo htmlspecialchars($firstImage); ?>" 
                                                 class="img-fluid" 
                                                 style="width: 100%; height: 100px; object-fit: cover;"
                                                 alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                 onerror="this.src='uploads/donations/placeholder-default.svg'">
                                        </div>
                                    </div>
                                    
                                    <!-- Thông tin sản phẩm -->
                                    <div class="col-md-3 col-8">
                                        <div class="product-info">
                                            <h6 class="mb-2"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                            <p class="text-muted small mb-2">
                                                <i class="bi bi-tag" style="color: #06B6D4;"></i>
                                                <?php echo htmlspecialchars($item['category_name'] ?? 'Khác'); ?>
                                            </p>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <?php if ($item['price_type'] === 'free'): ?>
                                                    <span class="badge badge-free">Miễn phí</span>
                                                <?php else: ?>
                                                    <span class="badge badge-paid">Giá rẻ</span>
                                                <?php endif; ?>
                                                <span class="badge badge-condition"><?php echo ucfirst($item['condition_status']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Giá -->
                                    <div class="col-md-2 col-6 text-center">
                                        <p class="text-muted small mb-1">Đơn giá</p>
                                        <p class="price-display mb-0"><?php echo $priceDisplay; ?></p>
                                    </div>
                                    
                                    <!-- Số lượng -->
                                    <div class="col-md-2 col-6">
                                        <p class="text-muted small mb-1 text-center">Số lượng</p>
                                        <div class="input-group input-group-sm qty-input-group mx-auto">
                                            <button class="btn update-quantity" 
                                                    data-action="decrease"
                                                data-cart-id="<?php echo $item['cart_id']; ?>"
