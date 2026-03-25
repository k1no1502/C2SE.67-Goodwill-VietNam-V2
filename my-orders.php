<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

function buildInternalTrackingCode(int $orderId): string
{
    return 'GW' . str_pad((string)$orderId, 10, '0', STR_PAD_LEFT);
}

function buildTrackingUrl(?string $carrier, ?string $trackingCode): ?string
{
    $carrier = strtolower(trim((string)$carrier));
    $trackingCode = trim((string)$trackingCode);
    if ($carrier === '' || $trackingCode === '') {
        return null;
    }

    switch ($carrier) {
        case 'viettelpost':
        case 'viettel post':
        case 'vtp':
            return "https://viettelpost.com.vn/tra-cuu-hanh-trinh-don/?id=" . rawurlencode($trackingCode);
        case 'ghtk':
            return "https://i.ghtk.vn/" . rawurlencode($trackingCode);
        case 'ghn':
            return "https://donhang.ghn.vn/?order_code=" . rawurlencode($trackingCode);
        case 'j&t':
        case 'jt':
        case 'jnt':
            return "https://www.jtexpress.vn/vi/tracking?billcode=" . rawurlencode($trackingCode);
        case 'vnpost':
            return "https://vnpost.vn/vi-vn/dinh-vi/buu-pham?key=" . rawurlencode($trackingCode);
        default:
            return null;
    }
}

function getCarrierLabel(?string $carrier): string
{
    $key = strtolower(trim((string)$carrier));
    return match ($key) {
        'viettelpost', 'viettel post', 'vtp' => 'ViettelPost',
        'ghn' => 'GHN',
        'ghtk' => 'GHTK',
        'j&t', 'jt', 'jnt' => 'J&T Express',
        'vnpost' => 'VNPost',
        'grab' => 'GrabExpress',
        default => $carrier ? (string)$carrier : 'Chua chon',
    };
}

function getCarrierStatusMeta(?string $status): array
{
    $status = strtolower(trim((string)$status));
    return match ($status) {
        'created' => ['class' => 'secondary', 'text' => 'Da tao van don', 'icon' => 'receipt'],
        'waiting_pickup' => ['class' => 'warning', 'text' => 'Cho lay hang', 'icon' => 'clock'],
        'picked_up' => ['class' => 'info', 'text' => 'Da lay hang', 'icon' => 'box-seam'],
        'in_transit' => ['class' => 'primary', 'text' => 'Dang trung chuyen', 'icon' => 'truck'],
        'out_for_delivery' => ['class' => 'primary', 'text' => 'Dang giao', 'icon' => 'truck'],
        'delivered' => ['class' => 'success', 'text' => 'Giao thanh cong', 'icon' => 'house-check'],
        'failed_delivery' => ['class' => 'danger', 'text' => 'Giao that bai', 'icon' => 'x-circle'],
        'returning' => ['class' => 'warning', 'text' => 'Dang hoan', 'icon' => 'arrow-return-left'],
        'returned' => ['class' => 'dark', 'text' => 'Da hoan', 'icon' => 'arrow-return-left'],
        default => ['class' => 'secondary', 'text' => 'Dang cap nhat', 'icon' => 'info-circle'],
    };
}

function getLogisticsStatusConfig(): array
{
    return [
        'steps' => [
            'created' => ['label' => 'Da tao van don', 'icon' => 'receipt'],
            'waiting_pickup' => ['label' => 'Cho lay hang', 'icon' => 'clock'],
            'picked_up' => ['label' => 'Da lay hang', 'icon' => 'box-seam'],
            'in_transit' => ['label' => 'Dang trung chuyen', 'icon' => 'truck'],
            'out_for_delivery' => ['label' => 'Dang giao', 'icon' => 'truck'],
            'delivered' => ['label' => 'Giao thanh cong', 'icon' => 'house-check'],
            'failed_delivery' => ['label' => 'Giao that bai', 'icon' => 'x-circle'],
            'returning' => ['label' => 'Dang hoan', 'icon' => 'arrow-return-left'],
            'returned' => ['label' => 'Da hoan', 'icon' => 'arrow-return-left'],
        ],
        'order' => [
            'created',
            'waiting_pickup',
            'picked_up',
            'in_transit',
            'out_for_delivery',
            'delivered',
        ],
        'rank' => [
            'created' => 0,
            'waiting_pickup' => 1,
            'picked_up' => 2,
            'in_transit' => 3,
            'out_for_delivery' => 4,
            'failed_delivery' => 5,
            'returning' => 6,
            'returned' => 7,
            'delivered' => 7,
        ],
    ];
}

function getLegacyStatusConfig(): array
{
    return [
        'steps' => [
            'pending' => ['label' => 'Cho xu ly', 'icon' => 'clock'],
            'confirmed' => ['label' => 'Da xac nhan', 'icon' => 'check-circle'],
            'shipping' => ['label' => 'Dang giao', 'icon' => 'truck'],
            'delivered' => ['label' => 'Da giao', 'icon' => 'house-check'],
            'cancelled' => ['label' => 'Da huy', 'icon' => 'x-circle'],
        ],
        'order' => ['pending', 'confirmed', 'shipping', 'delivered'],
        'rank' => [
            'pending' => 0,
            'confirmed' => 1,
            'shipping' => 2,
            'delivered' => 3,
            'cancelled' => 3,
        ],
    ];
}

$pageTitle = "Đơn hàng của tôi";

// Get orders for current user
$sql = "SELECT o.*, 
        COUNT(oi.order_item_id) as total_items,
        SUM(oi.quantity) as total_quantity
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.order_id
        ORDER BY o.created_at DESC";
$orders = Database::fetchAll($sql, [$_SESSION['user_id']]);

// Get order statistics
$stats = Database::fetch(
    "SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
        SUM(CASE WHEN status = 'shipping' THEN 1 ELSE 0 END) as shipping_orders,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
        COALESCE(SUM(total_amount), 0) as total_spent
        FROM orders 
        WHERE user_id = ?",
    [$_SESSION['user_id']]
);

$logisticsStatusConfig = getLogisticsStatusConfig();
$legacyStatusConfig = getLegacyStatusConfig();

include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container orders-shell py-4 py-lg-5">
    <!-- Page Header -->
    <div class="orders-hero mb-4 mb-lg-5">
        <div class="row g-3 align-items-center">
            <div class="col-lg-8">
                <span class="orders-tag"><i class="bi bi-stars me-2"></i>Goodwill Vietnam</span>
                <h1 class="orders-title mt-3 mb-2">
                    <i class="bi bi-box-seam me-2"></i>Đơn hàng của tôi
                </h1>
                <p class="orders-subtitle mb-0">Theo dõi tiến trình giao hàng, kiểm tra thông tin nhận và quản lý mọi đơn mua trong một nơi.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="orders-total-spent">
                    <div class="small text-muted mb-1">Tổng chi tiêu</div>
                    <div class="orders-total-value"><?php echo number_format((float)$stats['total_spent']); ?> VNĐ</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-lg-4 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon bg-cyan"><i class="bi bi-list-check"></i></div>
                <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
                <div class="stat-label">Tổng đơn hàng</div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon bg-amber"><i class="bi bi-clock-history"></i></div>
                <div class="stat-value"><?php echo $stats['pending_orders']; ?></div>
                <div class="stat-label">Chờ xử lý</div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon bg-blue"><i class="bi bi-patch-check"></i></div>
                <div class="stat-value"><?php echo $stats['confirmed_orders']; ?></div>
                <div class="stat-label">Đã xác nhận</div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon bg-indigo"><i class="bi bi-truck"></i></div>
                <div class="stat-value"><?php echo $stats['shipping_orders']; ?></div>
                <div class="stat-label">Đang giao</div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon bg-green"><i class="bi bi-house-check"></i></div>
                <div class="stat-value"><?php echo $stats['delivered_orders']; ?></div>
                <div class="stat-label">Đã giao</div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-4 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon bg-red"><i class="bi bi-x-octagon"></i></div>
                <div class="stat-value"><?php echo $stats['cancelled_orders']; ?></div>
                <div class="stat-label">Đã hủy</div>
            </div>
        </div>
    </div>

    <?php if (empty($orders)): ?>
        <!-- Empty Orders -->
        <div class="row justify-content-center">
            <div class="col-xl-8 col-lg-10">
                <div class="empty-orders text-center">
                    <div class="empty-icon"><i class="bi bi-bag-x"></i></div>
                    <h3 class="mt-3 mb-2">Chưa có đơn hàng nào</h3>
                    <p class="text-muted mb-4">Bạn chưa phát sinh đơn mua. Khám phá cửa hàng để tìm sản phẩm phù hợp ngay.</p>
                    <a href="shop.php" class="btn btn-modern-primary btn-lg">
                        <i class="bi bi-shop me-2"></i>Mua sắm ngay
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Orders List -->
        <div class="row">
            <div class="col-12">
                <?php foreach ($orders as $order): ?>
                    <?php
                    $carrierLabel = getCarrierLabel($order['shipping_carrier'] ?? '');
$trackingCode = trim((string)($order['shipping_tracking_code'] ?? ''));
if ($trackingCode === '') {
    $trackingCode = buildInternalTrackingCode((int)$order['order_id']);
}
$trackingUrl = buildTrackingUrl($order['shipping_carrier'] ?? '', $trackingCode);
$shippingFee = (float)($order['shipping_fee'] ?? 0);

$lastMile = trim((string)($order['shipping_last_mile_status'] ?? ''));
if ($lastMile !== '') {
    $meta = getCarrierStatusMeta($lastMile);
    $statusClass = $meta['class'];
    $statusText = $meta['text'];
    $statusIcon = $meta['icon'];
} else {
    $statusClass = 'secondary';
    $statusText = $order['status'];
    $statusIcon = 'info-circle';
    switch ($order['status']) {
        case 'pending':
            $statusClass = 'warning';
            $statusText = 'Cho xu ly';
            $statusIcon = 'clock';
            break;
        case 'confirmed':
            $statusClass = 'info';
            $statusText = 'Da xac nhan';
            $statusIcon = 'check-circle';
            break;
        case 'shipping':
            $statusClass = 'primary';
            $statusText = 'Dang giao';
            $statusIcon = 'truck';
            break;
        case 'delivered':
            $statusClass = 'success';
            $statusText = 'Da giao';
            $statusIcon = 'house-check';
            break;
        case 'cancelled':
            $statusClass = 'danger';
            $statusText = 'Da huy';
            $statusIcon = 'x-circle';
            break;
    }
}

$statusConfig = ($order['status'] ?? '') !== 'cancelled'
    && (
        $lastMile !== ''
        || trim((string)($order['shipping_carrier'] ?? '')) !== ''
        || trim((string)($order['shipping_tracking_code'] ?? '')) !== ''
    )
        ? $logisticsStatusConfig
        : $legacyStatusConfig;

$steps = $statusConfig['steps'];
$statusOrder = $statusConfig['order'];
$statusRank = $statusConfig['rank'];

$currentStatus = $statusConfig === $logisticsStatusConfig ? $lastMile : (string)($order['status'] ?? 'pending');
if ($currentStatus === '' && $statusConfig === $logisticsStatusConfig) {
    switch ($order['status']) {
        case 'pending':
            $currentStatus = 'created';
            break;
        case 'confirmed':
            $currentStatus = 'waiting_pickup';
            break;
        case 'shipping':
            $currentStatus = 'in_transit';
            break;
        case 'delivered':
            $currentStatus = 'delivered';
            break;
        default:
            $currentStatus = 'created';
            break;
    }
}

$currentIndex = array_search($currentStatus, $statusOrder, true);
$currentRank = $statusRank[$currentStatus] ?? ($currentIndex !== false ? (int)$currentIndex : 0);
if ($currentIndex === false) {
    $progressPercent = 0;
} else {
    $progressPercent = (int)min(100, max(0, (($currentIndex + 1) / count($statusOrder)) * 100));
}

$currentStatusLabel = $steps[$currentStatus]['label'] ?? $statusText;
?>
                    <div class="order-card mb-3 mb-lg-4">
                        <div class="order-card-head">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h6 class="mb-1 fw-bold text-dark">
                                        <i class="bi bi-receipt me-2"></i>
                                        Đơn hàng #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?>
                                    </h6>
                                    <small class="text-muted d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-calendar me-1"></i>
                                        <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                    </small>
                                </div>
                                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                                    <span class="badge order-status-badge bg-<?php echo $statusClass; ?>">
                                        <i class="bi bi-<?php echo $statusIcon; ?> me-1"></i>
                                        <?php echo $statusText; ?>
                                    </span>
                                    <div class="small text-muted mt-2">
                                        <i class="bi bi-truck me-1"></i><?php echo htmlspecialchars($carrierLabel); ?>
                                        <?php if ($trackingUrl): ?>
                                            - <a href="<?php echo htmlspecialchars($trackingUrl); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($trackingCode); ?></a>
                                        <?php else: ?>
                                            - <?php echo htmlspecialchars($trackingCode); ?>
                                        <?php endif; ?>
                                        <?php if ($shippingFee > 0): ?>
                                            <div>Phí VC: <strong><?php echo number_format($shippingFee); ?></strong> VND</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="order-card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <strong>Người nhận:</strong> <?php echo htmlspecialchars($order['shipping_name']); ?>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong>Số điện thoại:</strong> <?php echo htmlspecialchars($order['shipping_phone']); ?>
                                        </div>
                                        <div class="col-md-12 mb-2">
                                            <strong>Địa chỉ:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?>
                                        </div>
                                        <?php if ($order['shipping_note']): ?>
                                            <div class="col-md-12 mb-2">
                                                <strong>Ghi chú:</strong> <?php echo htmlspecialchars($order['shipping_note']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4 mt-3 mt-md-0">
                                    <div class="text-md-end order-price-box">
                                        <p class="mb-1">
                                            <strong>Sản phẩm:</strong> <?php echo $order['total_items']; ?> loại (<?php echo $order['total_quantity']; ?> cái)
                                        </p>
                                        <p class="mb-1">
                                            <strong>Phương thức:</strong> 
                                            <?php echo $order['payment_method'] === 'cod' ? 'COD' : 'Chuyển khoản'; ?>
                                        </p>
                                        <p class="mb-0">
                                            <strong>Tổng tiền:</strong> 
                                            <span class="order-total-amount">
                                                <?php echo $order['total_amount'] > 0 ? number_format($order['total_amount']) . ' VNĐ' : 'Miễn phí'; ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="order-progress mt-3">
                                <div class="d-flex justify-content-between align-items-center small text-muted mb-1">
                                    <span><?php echo htmlspecialchars($currentStatusLabel); ?></span>
                                    <strong><?php echo $progressPercent; ?>%</strong>
                                </div>
                                <div class="progress order-progress-bar" aria-hidden="true">
                                    <div class="progress-bar"
                                         role="progressbar"
                                         style="width: <?php echo $progressPercent; ?>%;"
                                         aria-valuenow="<?php echo $progressPercent; ?>"
                                         aria-valuemin="0"
                                         aria-valuemax="100"></div>
                                </div>
                            </div>

                            <!-- Order Actions -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="d-flex gap-2 flex-wrap order-actions">
                                        <a href="order-detail.php?id=<?php echo $order['order_id']; ?>" 
                                           class="btn btn-outline-primary btn-sm action-btn">
                                            <i class="bi bi-eye me-1"></i>Xem chi tiết
                                        </a>
                                        
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <button class="btn btn-outline-danger btn-sm action-btn" 
                                                    onclick="cancelOrder(<?php echo $order['order_id']; ?>)">
                                                <i class="bi bi-x-circle me-1"></i>Hủy đơn hàng
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['status'] === 'delivered'): ?>
                                            <button class="btn btn-outline-success btn-sm action-btn" 
                                                    onclick="rateOrder(<?php echo $order['order_id']; ?>)">
                                                <i class="bi bi-star me-1"></i>Đánh giá
                                            </button>
                                        <?php endif; ?>
                                        
                                        <a href="order-tracking.php?id=<?php echo $order['order_id']; ?>" 
                                           class="btn btn-outline-info btn-sm action-btn">
                                            <i class="bi bi-truck me-1"></i>Theo dõi
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.orders-shell {
    margin-top: 5rem;
}
.orders-hero {
    padding: 1.3rem 1.4rem;
    border: 1px solid #d8eef3;
    border-radius: 20px;
    background:
        radial-gradient(circle at 85% 15%, rgba(14, 116, 144, 0.15), transparent 40%),
        linear-gradient(145deg, #f7fcfd 0%, #edf8fb 100%);
    box-shadow: 0 12px 34px rgba(9, 84, 103, 0.09);
}
.orders-tag {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.75rem;
    border-radius: 999px;
    background: #dff2f7;
    color: #0c6d86;
    font-weight: 700;
    font-size: 0.85rem;
}
.orders-title {
    color: #0f172a;
    font-weight: 800;
    letter-spacing: 0.2px;
}
.orders-subtitle {
    color: #5f6c80;
}
.orders-total-spent {
    display: inline-block;
    background: #fff;
    border: 1px solid #d8eef3;
    border-radius: 14px;
    padding: 0.8rem 1rem;
    min-width: 220px;
    box-shadow: 0 8px 20px rgba(9, 84, 103, 0.08);
}
.orders-total-value {
    color: #0c6d86;
    font-weight: 800;
    font-size: 1.1rem;
}
.stat-card {
    height: 100%;
    border: 1px solid #d9edf2;
    border-radius: 16px;
    background: #fff;
    padding: 0.95rem;
    box-shadow: 0 10px 24px rgba(8, 74, 92, 0.07);
}
.stat-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    margin-bottom: 0.45rem;
}
.bg-cyan { background: #0891b2; }
.bg-amber { background: #d97706; }
.bg-blue { background: #2563eb; }
.bg-indigo { background: #4f46e5; }
.bg-green { background: #059669; }
.bg-red { background: #dc2626; }
.stat-value {
    font-size: 1.35rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
}
.stat-label {
    color: #64748b;
    font-size: 0.88rem;
}
.empty-orders {
    border: 1px solid #d9edf2;
    border-radius: 20px;
    background: #fff;
    padding: 2.5rem 1.25rem;
    box-shadow: 0 12px 28px rgba(8, 74, 92, 0.08);
}
.empty-icon {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #0c6d86;
    background: #e8f5f9;
}
.btn-modern-primary {
    background: linear-gradient(135deg, #0e7490, #0891b2);
    border: none;
    color: #fff;
    font-weight: 700;
    border-radius: 12px;
    padding: 0.7rem 1.2rem;
}
.btn-modern-primary:hover {
    color: #fff;
    filter: brightness(0.95);
}
.order-card {
    border: 1px solid #d9edf2;
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 12px 28px rgba(8, 74, 92, 0.08);
    overflow: hidden;
}
.order-card-head {
    padding: 1rem 1.15rem;
    background: linear-gradient(140deg, #fbfeff 0%, #edf8fb 100%);
    border-bottom: 1px solid #d9edf2;
}
.order-card-body {
    padding: 1.05rem 1.15rem 1.15rem;
}
.order-status-badge {
    font-size: 0.85rem;
    font-weight: 700;
    border-radius: 10px;
    padding: 0.45rem 0.65rem;
}
.order-price-box {
    background: #f8fcfd;
    border: 1px solid #e0f0f4;
    border-radius: 12px;
    padding: 0.75rem;
}
.order-total-amount {
    color: #0f766e;
    font-weight: 800;
    font-size: 1.1rem;
}
.order-progress .progress {
    height: 10px;
    border-radius: 999px;
    overflow: hidden;
    background: #d9edf2;
}
.order-progress .progress-bar {
    transition: width 0.4s ease;
    background: linear-gradient(135deg, #0e7490, #0891b2);
}
.order-actions .action-btn {
    border-radius: 10px;
    font-weight: 600;
}
@media (max-width: 991.98px) {
    .orders-shell {
        margin-top: 4.8rem;
    }
    .orders-hero {
        padding: 1rem;
    }
    .order-card-head,
    .order-card-body {
        padding: 0.9rem;
    }
}
@media (max-width: 575.98px) {
    .orders-title {
        font-size: 1.5rem;
    }
    .orders-total-spent {
        width: 100%;
    }
}
</style>
<script>
function cancelOrder(orderId) {
    if (confirm('Bạn có chắc chắn muốn hủy đơn hàng này?')) {
        fetch('api/cancel-order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: orderId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Lỗi: ' + (data.message || 'Không thể hủy đơn hàng'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi hủy đơn hàng');
        });
    }
}

function rateOrder(orderId) {
    // TODO: Implement rating system
    alert('Tính năng đánh giá sẽ được phát triển trong phiên bản tiếp theo');
}
</script>

<?php include 'includes/footer.php'; ?>
