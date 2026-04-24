<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$isAdmin = isAdmin();

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
        default => $carrier ? (string)$carrier : 'Chưa chọn',
    };
}

function getCarrierStatusMeta(?string $status): array
{
    $status = strtolower(trim((string)$status));
    return match ($status) {
        'payment_completed' => ['class' => 'success', 'text' => 'Đã thanh toán', 'icon' => 'wallet2'],
        'payment_pending' => ['class' => 'warning', 'text' => 'Chưa hoàn tất thanh toán', 'icon' => 'wallet2'],
        'created' => ['class' => 'secondary', 'text' => 'Đã tạo vận đơn', 'icon' => 'receipt'],
        'waiting_pickup' => ['class' => 'warning', 'text' => 'Chờ lấy hàng', 'icon' => 'clock'],
        'picked_up' => ['class' => 'info', 'text' => 'Đã lấy hàng', 'icon' => 'box-seam'],
        'in_transit' => ['class' => 'primary', 'text' => 'Đang trung chuyển', 'icon' => 'truck'],
        'out_for_delivery' => ['class' => 'primary', 'text' => 'Đang giao', 'icon' => 'truck'],
        'delivered' => ['class' => 'success', 'text' => 'Giao thành công', 'icon' => 'house-check'],
        'failed_delivery' => ['class' => 'danger', 'text' => 'Giao thất bại', 'icon' => 'x-circle'],
        'returning' => ['class' => 'warning', 'text' => 'Đang hoàn', 'icon' => 'arrow-return-left'],
        'returned' => ['class' => 'dark', 'text' => 'Đã hoàn', 'icon' => 'arrow-return-left'],
        default => ['class' => 'light text-dark', 'text' => $status !== '' ? $status : '—', 'icon' => 'info-circle'],
    };
}

function splitVietnameseAddress(?string $address): array
{
    $address = trim((string)$address);
    if ($address === '') {
        return ['detail' => '', 'ward' => '', 'district' => '', 'city' => ''];
    }

    $parts = array_values(array_filter(array_map('trim', explode(',', $address)), fn($p) => $p !== ''));
    $count = count($parts);
    if ($count < 4) {
        return ['detail' => $address, 'ward' => '', 'district' => '', 'city' => ''];
    }

    $city = $parts[$count - 1];
    $district = $parts[$count - 2];
    $ward = $parts[$count - 3];
    $detail = trim(implode(', ', array_slice($parts, 0, $count - 3)));

    return ['detail' => $detail, 'ward' => $ward, 'district' => $district, 'city' => $city];
}

function getOrderPaymentMethodLabel(?string $method): string
{
    $method = strtolower(trim((string)$method));
    return match ($method) {
        'cod', 'cash' => 'Thanh toán khi nhận hàng (COD)',
        'momo' => 'Ví MoMo',
        'zalopay' => 'Ví ZaloPay',
        'bank_transfer' => 'Chuyển khoản ngân hàng',
        default => $method !== '' ? strtoupper($method) : 'Chưa cập nhật',
    };
}

$pageTitle = "Chi tiết đơn hàng";

$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {
    header('Location: ' . ($isAdmin ? 'admin/orders.php' : 'my-orders.php'));
    exit();
}

// Get order details
$orderParams = [$order_id];
$orderSql = "SELECT o.*, u.name as user_name, u.email as user_email 
     FROM orders o 
     JOIN users u ON o.user_id = u.user_id 
     WHERE o.order_id = ?";

if (!$isAdmin) {
    $orderSql .= " AND o.user_id = ?";
    $orderParams[] = $_SESSION['user_id'];
}

$order = Database::fetch($orderSql, $orderParams);

if (!$order) {
    header('Location: ' . ($isAdmin ? 'admin/orders.php' : 'my-orders.php'));
    exit();
}

// Get order items
$orderItems = Database::fetchAll(
    "SELECT oi.*, i.images, i.condition_status, i.unit, i.price_type
     FROM order_items oi
     LEFT JOIN inventory i ON oi.item_id = i.item_id
     WHERE oi.order_id = ?
     ORDER BY oi.created_at",
    [$order_id]
);

// Get order status history (nếu bảng tồn tại)
$statusHistory = [];
try {
    $statusHistory = Database::fetchAll(
        "SELECT * FROM order_status_history 
         WHERE order_id = ? 
         ORDER BY created_at DESC",
        [$order_id]
    );
} catch (Exception $e) {
    // Bảng có thể chưa tồn tại nếu chưa import orders_system.sql -> bỏ qua
    $statusHistory = [];
}

include 'includes/header.php';

$shippingAddressParts = splitVietnameseAddress($order['shipping_address'] ?? '');
$orderPaymentMethod = strtolower(trim((string)($order['payment_method'] ?? '')));
$orderPaymentStatus = strtolower(trim((string)($order['payment_status'] ?? '')));
$orderPaymentReference = trim((string)($order['payment_reference'] ?? ''));
$orderStatus = strtolower(trim((string)($order['status'] ?? '')));
$lastMileStatusKey = strtolower(trim((string)($order['shipping_last_mile_status'] ?? '')));
$hasPaymentStatusField = array_key_exists('payment_status', $order);
$isPaidOrder = $hasPaymentStatusField
    ? $orderPaymentStatus === 'paid'
    : (stripos($orderPaymentReference, 'MOMO-') === 0 || stripos($orderPaymentReference, 'ZALO-') === 0);
$isOnlineUnpaidOrder = (float)($order['total_amount'] ?? 0) > 0
    && in_array($orderPaymentMethod, ['bank_transfer', 'momo', 'zalopay'], true)
    && !$isPaidOrder;
$isMarkedPaymentPending = $lastMileStatusKey === 'payment_pending';
$canRepayOrder = ($isOnlineUnpaidOrder || $isMarkedPaymentPending)
    && !$isPaidOrder
    && $orderStatus !== 'cancelled';
$payError = trim((string)($_GET['pay_error'] ?? ''));
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Main Content -->
<div class="container py-5 mt-5 order-detail-page">
    <?php if ($payError !== ''): ?>
        <div class="alert alert-danger mb-4" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($payError); ?>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="order-hero">
                <div class="order-hero-glow"></div>
                <div class="order-hero-top d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <div class="order-hero-pill mb-2">
                            <i class="bi bi-stars"></i>
                            Chi tiết đơn hàng thời gian thực
                        </div>
                        <h1 class="order-hero-title mb-2">
                            <i class="bi bi-receipt me-2"></i>Chi tiết đơn hàng
                        </h1>
                        <p class="order-hero-subtitle mb-0">
                            Đơn hàng #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?>
                        </p>
                    </div>
                    <div>
                        <a href="<?php echo $isAdmin ? 'admin/orders.php' : 'my-orders.php'; ?>" class="btn btn-order-outline">
                            <i class="bi bi-arrow-left me-2"></i>Quay lại
                        </a>
                    </div>
                </div>
                <div class="order-hero-meta mt-3">
                    <div class="order-hero-meta-card">
                        <div class="label">Mã đơn</div>
                        <div class="value">#<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></div>
                    </div>
                    <div class="order-hero-meta-card">
                        <div class="label">Ngày đặt</div>
                        <div class="value"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></div>
                    </div>
                    <div class="order-hero-meta-card">
                        <div class="label">Tổng tiền</div>
                        <div class="value"><?php echo $order['total_amount'] > 0 ? number_format($order['total_amount']) . ' VNĐ' : 'Miễn phí'; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Order Information -->
        <div class="col-lg-8">
            <!-- Order Status -->
            <div class="card order-card mb-4">
                <div class="card-header order-card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>Trạng thái đơn hàng
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $carrierLabel = getCarrierLabel($order['shipping_carrier'] ?? '');
                    $trackingCode = trim((string)($order['shipping_tracking_code'] ?? ''));
                    if ($trackingCode === '') {
                        $trackingCode = buildInternalTrackingCode($order_id);
                    }
                    $trackingUrl = buildTrackingUrl($order['shipping_carrier'] ?? '', $trackingCode);
                    $shippingFee = (float)($order['shipping_fee'] ?? 0);

                    $statusClass = 'secondary';
                    $statusText = 'Đang cập nhật';
                    $statusIcon = 'info-circle';

                    $lastMileStatus = trim((string)($order['shipping_last_mile_status'] ?? ''));
                    if ($lastMileStatus !== '') {
                        $meta = getCarrierStatusMeta($lastMileStatus);
                        $statusClass = $meta['class'];
                        $statusText = $meta['text'];
                        $statusIcon = $meta['icon'];
                    } else {
                        switch ($order['status']) {
                            case 'pending':
                                $statusClass = 'warning';
                                $statusText = 'Chờ xử lý';
                                $statusIcon = 'clock';
                                break;
                            case 'confirmed':
                                $statusClass = 'info';
                                $statusText = 'Đã xác nhận';
                                $statusIcon = 'check-circle';
                                break;
                            case 'shipping':
                                $statusClass = 'primary';
                                $statusText = 'Đang giao';
                                $statusIcon = 'truck';
                                break;
                            case 'delivered':
                                $statusClass = 'success';
                                $statusText = 'Đã giao';
                                $statusIcon = 'house-check';
                                break;
                            case 'cancelled':
                                $statusClass = 'danger';
                                $statusText = 'Đã hủy';
                                $statusIcon = 'x-circle';
                                break;
                        }
                    }

                    if ($isOnlineUnpaidOrder) {
                        $statusClass = 'warning';
                        $statusText = 'Chưa hoàn tất thanh toán';
                        $statusIcon = 'wallet2';
                    }
                    ?>
                    <div class="text-center order-status-wrap">
                        <span class="badge bg-<?php echo htmlspecialchars($statusClass); ?> fs-5 px-4 py-2 order-status-badge">
                            <i class="bi bi-<?php echo htmlspecialchars($statusIcon); ?> me-2"></i>
                            <?php echo htmlspecialchars($statusText); ?>
                        </span>
                        <div class="mt-3 text-muted order-shipping-meta">
                            <div class="order-shipping-line">
                                <i class="bi bi-truck me-1"></i><?php echo htmlspecialchars($carrierLabel); ?>
                                <?php if ($trackingUrl): ?>
                                    - <a href="<?php echo htmlspecialchars($trackingUrl); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($trackingCode); ?></a>
                                <?php else: ?>
                                    - <span class="text-decoration-underline"><?php echo htmlspecialchars($trackingCode); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($shippingFee > 0): ?>
                                <div class="order-shipping-line">Phi VC: <strong><?php echo number_format($shippingFee); ?></strong> VND</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card order-card mb-4">
                <div class="card-header order-card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-box me-2"></i>Sản phẩm đã đặt (<?php echo count($orderItems); ?>)
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($orderItems as $item): ?>
                        <?php
                        $images = json_decode($item['images'] ?? '[]', true);
                        $firstImage = !empty($images) ? resolveDonationImageUrl((string)$images[0]) : 'uploads/donations/placeholder-default.svg';
                        ?>
                        <div class="order-item-row p-3">
                            <div class="row align-items-center">
                                <div class="col-md-2 col-3">
                                    <img src="<?php echo htmlspecialchars($firstImage); ?>" 
                                         class="img-fluid rounded order-item-image" 
                                         alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                         onerror="this.src='uploads/donations/placeholder-default.svg'">
                                </div>
                                <div class="col-md-4 col-9">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                    <p class="text-muted small mb-1">
                                        Tình trạng: <?php echo ucfirst($item['condition_status'] ?? 'Mới'); ?> | 
                                        Đơn vị: <?php echo $item['unit'] ?? 'Cái'; ?>
                                    </p>
                                    <div class="d-flex gap-1">
                                        <?php if ($item['price_type'] === 'free'): ?>
                                            <span class="badge bg-success">Miễn phí</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Giá rẻ</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6 text-center">
                                    <p class="text-muted small mb-1">Số lượng</p>
                                    <p class="mb-0 fw-bold"><?php echo $item['quantity']; ?></p>
                                </div>
                                <div class="col-md-2 col-6 text-center">
                                    <p class="text-muted small mb-1">Đơn giá</p>
                                    <p class="mb-0 fw-bold">
                                        <?php echo $item['unit_price'] > 0 ? number_format($item['unit_price']) . ' VNĐ' : 'Miễn phí'; ?>
                                    </p>
                                </div>
                                <div class="col-md-2 col-12 text-center">
                                    <p class="text-muted small mb-1">Thành tiền</p>
                                    <p class="mb-0 fw-bold text-success">
                                        <?php echo $item['total_price'] > 0 ? number_format($item['total_price']) . ' VNĐ' : 'Miễn phí'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Status History -->
            <?php if (!empty($statusHistory)): ?>
                <div class="card order-card mb-4">
                    <div class="card-header order-card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>Lịch sử trạng thái
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach ($statusHistory as $index => $history): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-<?php echo $index === 0 ? 'success' : 'secondary'; ?>"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1">
                                            <?php
                                            $statusTexts = [
                                                'pending' => 'Chờ xử lý',
                                                'confirmed' => 'Đã xác nhận',
                                                'shipping' => 'Đang giao',
                                                'delivered' => 'Đã giao',
                                                'cancelled' => 'Đã hủy'
                                            ];
                                            echo $statusTexts[$history['new_status']] ?? $history['new_status'];
                                            ?>
                                        </h6>
                                        <p class="text-muted small mb-1">
                                            <?php echo date('d/m/Y H:i:s', strtotime($history['created_at'])); ?>
                                        </p>
                                        <?php if ($history['note']): ?>
                                            <p class="small mb-0"><?php echo htmlspecialchars($history['note']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Order Summary -->
        <div class="col-lg-4">
            <div class="card sticky-top order-summary-card" style="top: 100px;">
                <div class="card-header order-summary-header text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-receipt me-2"></i>Tóm tắt đơn hàng
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Order Info -->
                    <div class="mb-3">
                        <h6 class="text-success mb-3">Thông tin giao hàng</h6>
                        <p class="mb-1"><strong>Người nhận:</strong> <?php echo htmlspecialchars($order['shipping_name']); ?></p>
                        <p class="mb-1"><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($order['shipping_phone']); ?></p>
                        <p class="mb-1"><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($shippingAddressParts['detail'] ?: ($order['shipping_address'] ?? '')); ?></p>
                        <?php if ($shippingAddressParts['ward'] || $shippingAddressParts['district'] || $shippingAddressParts['city']): ?>
                            <?php if ($shippingAddressParts['ward']): ?>
                                <p class="mb-1"><strong>Phường/Xã:</strong> <?php echo htmlspecialchars($shippingAddressParts['ward']); ?></p>
                            <?php endif; ?>
                            <?php if ($shippingAddressParts['district']): ?>
                                <p class="mb-1"><strong>Quận/Huyện:</strong> <?php echo htmlspecialchars($shippingAddressParts['district']); ?></p>
                            <?php endif; ?>
                            <?php if ($shippingAddressParts['city']): ?>
                                <p class="mb-1"><strong>Thành phố:</strong> <?php echo htmlspecialchars($shippingAddressParts['city']); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($order['shipping_note']): ?>
                            <p class="mb-1"><strong>Ghi chú:</strong> <?php echo htmlspecialchars($order['shipping_note']); ?></p>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <!-- Payment Info -->
                    <div class="mb-3">
                        <h6 class="text-success mb-3">Thông tin thanh toán</h6>
                        <p class="mb-1"><strong>Phương thức:</strong> 
                            <?php echo htmlspecialchars(getOrderPaymentMethodLabel($order['payment_method'] ?? '')); ?>
                        </p>
                        <?php if (array_key_exists('payment_status', $order)): ?>
                            <p class="mb-1"><strong>Trạng thái thanh toán:</strong>
                                <?php echo $isPaidOrder ? '<span class="text-success fw-semibold">Đã thanh toán</span>' : '<span class="text-warning fw-semibold">Chưa hoàn tất thanh toán</span>'; ?>
                            </p>
                        <?php endif; ?>
                        <p class="mb-1"><strong>Ngày đặt:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                        <p class="mb-1"><strong>Cập nhật cuối:</strong> <?php echo date('d/m/Y H:i', strtotime($order['updated_at'])); ?></p>
                    </div>

                    <hr>

                    <!-- Order Totals -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tổng sản phẩm:</span>
                            <strong><?php echo count($orderItems); ?> loại</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tổng số lượng:</span>
                            <strong><?php echo array_sum(array_column($orderItems, 'quantity')); ?> cái</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Phí vận chuyển:</span>
                            <span class="text-success">Miễn phí</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold">Tổng cộng:</span>
                            <span class="fw-bold text-success fs-5">
                                <?php echo $order['total_amount'] > 0 ? number_format($order['total_amount']) . ' VNĐ' : 'Miễn phí'; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-grid gap-2">
                        <?php if ($canRepayOrder): ?>
                            <a href="order-pay.php?id=<?php echo (int)$order['order_id']; ?>&method=momo"
                               class="btn btn-order-primary">
                                <i class="bi bi-wallet2 me-2"></i>Thanh toán đơn hàng
                            </a>
                        <?php endif; ?>

                        <?php if ($order['status'] === 'pending'): ?>
                            <button class="btn btn-order-danger" onclick="cancelOrder(<?php echo $order['order_id']; ?>)">
                                <i class="bi bi-x-circle me-2"></i>Hủy đơn hàng
                            </button>
                        <?php endif; ?>
                        
                        <a href="order-tracking.php?id=<?php echo $order['order_id']; ?>" class="btn btn-order-primary">
                            <i class="bi bi-truck me-2"></i>Theo dõi giao hàng
                        </a>
                        
                        <a href="<?php echo $isAdmin ? 'admin/orders.php' : 'my-orders.php'; ?>" class="btn btn-order-outline">
                            <i class="bi bi-list-ul me-2"></i>Xem tất cả đơn hàng
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --od-primary: #0f766e;
    --od-primary-2: #0ea5a5;
    --od-ink: #102a34;
    --od-soft-line: #d8ece7;
}

.order-detail-page {
    font-family: 'Manrope', 'Segoe UI', sans-serif;
    position: relative;
}

.order-detail-page::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 24px;
    z-index: -1;
    background:
        radial-gradient(circle at 6% 10%, rgba(20, 184, 166, 0.17), transparent 28%),
        radial-gradient(circle at 92% 4%, rgba(14, 116, 144, 0.11), transparent 25%),
        linear-gradient(180deg, #f2f9f8 0%, #edf5ff 100%);
}

.order-hero {
    position: relative;
    border-radius: 20px;
    background: linear-gradient(128deg, rgba(15, 118, 110, 0.95), rgba(20, 184, 166, 0.9));
    color: #fff;
    padding: 1.2rem;
    box-shadow: 0 18px 36px rgba(10, 95, 89, 0.24);
    overflow: hidden;
}

.order-hero-glow {
    position: absolute;
    width: 280px;
    height: 280px;
    top: -150px;
    right: -90px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.4), transparent 70%);
}

.order-hero-top,
.order-hero-meta {
    position: relative;
    z-index: 1;
}

.order-hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.38rem 0.75rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
    font-size: 0.8rem;
    font-weight: 600;
}

.order-hero-title {
    font-size: clamp(1.55rem, 2.7vw, 2.15rem);
    font-weight: 800;
    line-height: 1.2;
}

.order-hero-subtitle {
    color: rgba(255, 255, 255, 0.9);
    font-weight: 500;
}

.order-hero-meta {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.7rem;
}

.order-hero-meta-card {
    padding: 0.7rem 0.85rem;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.25);
}

.order-hero-meta-card .label {
    font-size: 0.74rem;
    color: rgba(255, 255, 255, 0.84);
    margin-bottom: 0.15rem;
}

.order-hero-meta-card .value {
    font-size: 0.94rem;
    font-weight: 700;
}

.order-card {
    border: 1px solid var(--od-soft-line);
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.9);
    box-shadow: 0 10px 22px rgba(5, 32, 55, 0.08);
    overflow: hidden;
}

.order-card-header {
    background: linear-gradient(180deg, #f9fffd 0%, #f2fbf7 100%);
    border-bottom: 1px solid var(--od-soft-line);
}

.order-status-badge {
    border-radius: 999px;
}

.order-shipping-meta {
    line-height: 1.65;
}

.order-shipping-line a {
    color: #0f766e;
    font-weight: 700;
    text-decoration: none;
}

.order-shipping-line a:hover {
    text-decoration: underline;
}

.order-item-row {
    border-bottom: 1px solid #e8f2ef;
}

.order-item-row:last-child {
    border-bottom: 0;
}

.order-item-image {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border: 1px solid #dceae7;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -35px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 3px #dee2e6;
}

.timeline-content {
    background: linear-gradient(180deg, #ffffff, #f7fbfa);
    padding: 15px;
    border-radius: 10px;
    border: 1px solid #dbe9e5;
    border-left: 3px solid #a4d6c9;
}

.order-summary-card {
    border: 1px solid var(--od-soft-line);
    border-radius: 16px;
    box-shadow: 0 10px 24px rgba(5, 32, 55, 0.08);
    overflow: hidden;
}

.order-summary-header {
    background: linear-gradient(120deg, #0f766e, #14b8a6);
    border-bottom: 0;
}

.btn-order-primary,
.btn-order-outline,
.btn-order-danger {
    border-radius: 11px;
    font-weight: 700;
    padding: 0.58rem 0.95rem;
}

.btn-order-primary {
    color: #fff;
    border: 0;
    background: linear-gradient(120deg, #0f766e, #14b8a6);
}

.btn-order-primary:hover {
    color: #fff;
    filter: brightness(1.03);
}

.btn-order-outline {
    color: #0f766e;
    border: 1px solid #97cbc0;
    background: #fff;
}

.btn-order-outline:hover {
    color: #0f766e;
    background: #f7fffd;
    border-color: #72b5a7;
}

.btn-order-danger {
    color: #b91c1c;
    border: 1px solid #f0b6b6;
    background: #fff;
}

.btn-order-danger:hover {
    color: #991b1b;
    background: #fff6f6;
}

@media (max-width: 991.98px) {
    .order-hero-meta {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 575.98px) {
    .order-hero {
        padding: 1rem;
    }

    .order-hero-title {
        font-size: 1.42rem;
    }

    .order-item-image {
        width: 70px;
        height: 70px;
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
</script>

<?php include 'includes/footer.php'; ?>
