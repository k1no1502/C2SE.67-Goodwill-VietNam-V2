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

$pageTitle = "Theo dõi đơn hàng";

$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {
    header('Location: ' . ($isAdmin ? 'admin/orders.php' : 'my-orders.php'));
    exit();
}

// Lấy thông tin đơn hàng của chính user
$orderParams = [$order_id];
$orderSql = "SELECT o.*, u.name as user_name, u.email as user_email
     FROM orders o
     JOIN users u ON o.user_id = u.user_id
     WHERE o.order_id = ?";

if (!$isAdmin) {
    $orderSql .= " AND o.user_id = ?";
    $orderParams[] = (int)$_SESSION['user_id'];
}

$order = Database::fetch($orderSql, $orderParams);

if (!$order) {
    header('Location: ' . ($isAdmin ? 'admin/orders.php' : 'my-orders.php'));
    exit();
}

// Lấy lịch sử trạng thái (nếu có)
$statusHistory = [];
try {
    $statusHistory = Database::fetchAll(
        "SELECT * FROM order_status_history 
         WHERE order_id = ? 
         ORDER BY created_at ASC",
        [$order_id]
    );
} catch (Exception $e) {
    // Nếu bảng chưa tồn tại thì bỏ qua phần history
    $statusHistory = [];
}

// Logistics info
$carrierLabel = getCarrierLabel($order['shipping_carrier'] ?? '');
$trackingCode = trim((string)($order['shipping_tracking_code'] ?? ''));
if ($trackingCode === '') {
    $trackingCode = buildInternalTrackingCode($order_id);
}
$trackingUrl = buildTrackingUrl($order['shipping_carrier'] ?? '', $trackingCode);
$shippingFee = (float)($order['shipping_fee'] ?? 0);

// Map status -> steps (prefer logistics status when available)
$useLogistics = ($order['status'] ?? '') !== 'cancelled'
    && (
        trim((string)($order['shipping_last_mile_status'] ?? '')) !== ''
        || trim((string)($order['shipping_carrier'] ?? '')) !== ''
        || trim((string)($order['shipping_tracking_code'] ?? '')) !== ''
    );

	if ($useLogistics) {
	    $steps = [
	        'created' => ['label' => 'Đã tạo vận đơn', 'icon' => 'receipt'],
	        'waiting_pickup' => ['label' => 'Chờ lấy hàng', 'icon' => 'clock'],
	        'picked_up' => ['label' => 'Đã lấy hàng', 'icon' => 'box-seam'],
	        'in_transit' => ['label' => 'Đang trung chuyển', 'icon' => 'truck'],
	        'out_for_delivery' => ['label' => 'Đang giao', 'icon' => 'truck'],
	        'delivered' => ['label' => 'Giao thành công', 'icon' => 'house-check'],
	        'failed_delivery' => ['label' => 'Giao thất bại', 'icon' => 'x-circle'],
	        'returning' => ['label' => 'Đang hoàn', 'icon' => 'arrow-return-left'],
	        'returned' => ['label' => 'Đã hoàn', 'icon' => 'arrow-return-left'],
	    ];

	    $statusOrder = ['created', 'waiting_pickup', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered'];
	    $statusRank = [
	        'created' => 0,
	        'waiting_pickup' => 1,
	        'picked_up' => 2,
	        'in_transit' => 3,
	        'out_for_delivery' => 4,
	        'failed_delivery' => 5,
	        'returning' => 6,
	        'returned' => 7,
	        'delivered' => 7,
	    ];
	    $currentStatus = trim((string)($order['shipping_last_mile_status'] ?? ''));

    if ($currentStatus === '') {
	        switch ($order['status'] ?? '') {
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
        }
    }
	} else {
	    $steps = [
	        'pending'   => ['label' => 'Chờ xử lý', 'icon' => 'clock'],
	        'confirmed' => ['label' => 'Đã xác nhận', 'icon' => 'check-circle'],
	        'shipping'  => ['label' => 'Đang giao', 'icon' => 'truck'],
	        'delivered' => ['label' => 'Đã giao', 'icon' => 'house-check'],
	        'cancelled' => ['label' => 'Đã hủy', 'icon' => 'x-circle'],
	    ];
	    $statusOrder = ['pending', 'confirmed', 'shipping', 'delivered'];
	    $statusRank = [
	        'pending' => 0,
	        'confirmed' => 1,
	        'shipping' => 2,
	        'delivered' => 3,
	        'cancelled' => 3,
	    ];
	    $currentStatus = (string)($order['status'] ?? 'pending');
	}

// Progress
	$currentIndex = array_search($currentStatus, $statusOrder, true);
	$currentRank = $statusRank[$currentStatus] ?? ($currentIndex !== false ? (int)$currentIndex : 0);
	if ($currentIndex === false) {
	    $progressPercent = 0;
	} else {
	    $progressPercent = (($currentIndex + 1) / count($statusOrder)) * 100;
    $progressPercent = min(100, max(0, (int)$progressPercent));
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Main Content -->
<div class="container py-5 mt-5 tracking-page">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="tracking-hero">
                <div class="tracking-hero__glow"></div>
                <div class="tracking-hero__content d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="tracking-pill mb-2">
                            <i class="bi bi-activity"></i>
                            Theo dõi thời gian thực
                        </div>
                        <h1 class="tracking-title mb-2">
                            <i class="bi bi-truck me-2"></i>Theo dõi đơn hàng
                        </h1>
                        <p class="tracking-subtitle mb-0">
                            Đơn hàng #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?>
                            <span class="mx-2 text-secondary">•</span>
                            <?php echo htmlspecialchars($steps[$currentStatus]['label'] ?? $currentStatus); ?>
                        </p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="order-detail.php?id=<?php echo $order_id; ?>" class="btn btn-track-secondary">
                            <i class="bi bi-receipt me-2"></i>Xem chi tiết
                        </a>
                        <a href="my-orders.php" class="btn btn-track-primary">
                            <i class="bi bi-list-ul me-2"></i>Đơn hàng của tôi
                        </a>
                    </div>
                </div>
                <div class="tracking-meta-grid mt-3">
                    <div class="tracking-meta-card">
                        <div class="label">Đơn vị vận chuyển</div>
                        <div class="value"><?php echo htmlspecialchars($carrierLabel); ?></div>
                    </div>
                    <div class="tracking-meta-card">
                        <div class="label">Mã vận đơn</div>
                        <div class="value"><?php echo htmlspecialchars($trackingCode); ?></div>
                    </div>
                    <div class="tracking-meta-card">
                        <div class="label">Tiến độ</div>
                        <div class="value"><?php echo $progressPercent; ?>%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Tracking Timeline -->
        <div class="col-lg-8">
            <div class="card tracking-card mb-4">
                <div class="card-header tracking-card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-map me-2"></i>Bản đồ hành trình
                    </h5>
                    <small class="text-muted">Tự cập nhật mỗi 30 giây</small>
                </div>
                <div class="card-body">
                    <div id="trackingMap"></div>
                    <div id="trackingMapStatus" class="small text-muted mt-2"></div>
                    <div class="small text-muted mt-3 tracking-map-summary">
                        <div class="tracking-info-chip"><strong>Chặng hiện tại:</strong> <span id="trackingLegLabel">Đang tải dữ liệu vận chuyển...</span></div>
                        <div class="tracking-info-chip">Điểm xuất phát: <strong id="trackingOriginLabel">Đang cập nhật...</strong></div>
                        <div class="tracking-info-chip">Điểm đích: <strong id="trackingDestinationLabel">Đang cập nhật...</strong></div>
                    </div>
                </div>
            </div>

            <div class="card tracking-card mb-4">
                <div class="card-header tracking-card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-geo-alt me-2"></i>Trạng thái vận chuyển
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 text-muted tracking-shipping-meta">
                        <i class="bi bi-truck me-1"></i><?php echo htmlspecialchars($carrierLabel); ?>
                        <?php if ($trackingUrl): ?>
                            - <a href="<?php echo htmlspecialchars($trackingUrl); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($trackingCode); ?></a>
                        <?php else: ?>
                            - <span class="text-decoration-underline"><?php echo htmlspecialchars($trackingCode); ?></span>
                        <?php endif; ?>
                        <?php if ($shippingFee > 0): ?>
                            <div>Phí vận chuyển: <strong><?php echo number_format($shippingFee); ?></strong> VND</div>
                        <?php endif; ?>
                    </div>
                    <!-- Progress bar -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tiến độ đơn hàng</span>
                            <strong class="progress-value"><?php echo $progressPercent; ?>%</strong>
                        </div>
                        <div class="progress tracking-progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated"
                                 role="progressbar"
                                 style="width: <?php echo $progressPercent; ?>%">
                                <?php echo $progressPercent; ?>%
                            </div>
                        </div>
                    </div>

                    <!-- Steps -->
                    <div class="d-flex justify-content-between tracking-steps mb-4">
                        <?php foreach ($statusOrder as $statusKey): ?>
                            <?php
                            $stepInfo   = $steps[$statusKey];
                            $isActive   = ($statusOrder && array_search($statusKey, $statusOrder, true) <= $currentIndex);
                            $isCurrent  = ($statusKey === $currentStatus);
                            $stepClass  = $isActive ? 'step-active' : 'step-inactive';
                            if ($currentStatus === 'cancelled') {
                                $stepClass = $statusKey === 'pending' ? 'step-cancelled' : 'step-inactive';
                            }
                            ?>
                            <div class="tracking-step <?php echo $stepClass; ?>">
                                <div class="step-icon">
                                    <i class="bi bi-<?php echo $stepInfo['icon']; ?>"></i>
                                </div>
                                <div class="step-label">
                                    <?php echo $stepInfo['label']; ?>
                                </div>
                                <?php if ($isCurrent): ?>
                                    <div class="step-current">Hiện tại</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Status history -->
                    <h6 class="fw-bold mb-3 section-title-mini">
                        <i class="bi bi-clock-history me-2"></i>Lịch sử cập nhật
                    </h6>
                    <?php
                    $orderStatusTexts = [
                        'pending'   => 'Chờ xử lý',
                        'confirmed' => 'Đã xác nhận',
                        'shipping'  => 'Đang giao',
                        'delivered' => 'Đã giao',
                        'cancelled' => 'Đã hủy'
                    ];
                    
                    $filteredHistory = [];
                    $lastRenderedKey = null;
                    foreach ($statusHistory as $history) {
                        $rawKey = (string)($history['new_status'] ?? '');
                        $isLogisticsKey = str_starts_with($rawKey, 'logistics:');
                        $key = $isLogisticsKey ? substr($rawKey, strlen('logistics:')) : $rawKey;
                    
                        if ($useLogistics) {
                            if (!$isLogisticsKey || !isset($steps[$key])) {
                                continue;
                            }
                    
	                            $idx = $statusRank[$key] ?? null;
	                            if ($idx !== null && $idx > $currentRank) {
	                                continue;
	                            }
                    
                            if ($lastRenderedKey === $key) {
                                continue;
                            }
                    
                            $filteredHistory[] = [
                                'title' => $steps[$key]['label'],
                                'created_at' => (string)($history['created_at'] ?? ''),
                                'note' => (string)($history['note'] ?? ''),
                            ];
                            $lastRenderedKey = $key;
                        } else {
                            if ($isLogisticsKey) {
                                continue;
                            }
                            if ($key === '') {
                                continue;
                            }
                    
                            if ($lastRenderedKey === $key) {
                                continue;
                            }
                    
                            $filteredHistory[] = [
                                'title' => $orderStatusTexts[$key] ?? $key,
                                'created_at' => (string)($history['created_at'] ?? ''),
                                'note' => (string)($history['note'] ?? ''),
                            ];
                            $lastRenderedKey = $key;
                        }
                    }
                    ?>
                    
                    <?php if (!empty($filteredHistory)): ?>
                        <div class="timeline">
                            <?php foreach ($filteredHistory as $history): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($history['title']); ?></h6>
                                        <?php if (!empty($history['created_at'])): ?>
                                            <p class="text-muted small mb-1"><?php echo date('d/m/Y H:i:s', strtotime($history['created_at'])); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($history['note'])): ?>
                                            <p class="small mb-0"><?php echo htmlspecialchars($history['note']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">
                            Chưa có lịch sử trạng thái chi tiết cho đơn hàng này.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="col-lg-4">
            <div class="card tracking-card order-summary-card sticky-top" style="top: 100px;">
                <div class="card-header order-summary-header text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-receipt me-2"></i>Tóm tắt đơn hàng
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-success mb-3">Thông tin giao hàng</h6>
                        <p class="mb-1"><strong>Người nhận:</strong> <?php echo htmlspecialchars($order['shipping_name'] ?? $order['user_name']); ?></p>
                        <p class="mb-1"><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($order['shipping_phone'] ?? ''); ?></p>
                        <p class="mb-1"><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($order['shipping_address'] ?? ''); ?></p>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <h6 class="text-success mb-3">Thông tin thanh toán</h6>
                        <p class="mb-1"><strong>Trạng thái đơn:</strong>
                            <?php
                            $statusText = $steps[$currentStatus]['label'] ?? $currentStatus;
                            echo htmlspecialchars($statusText);
                            ?>
                        </p>
                        <p class="mb-1"><strong>Ngày đặt:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                        <p class="mb-1"><strong>Cập nhật cuối:</strong> <?php echo date('d/m/Y H:i', strtotime($order['updated_at'])); ?></p>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tổng tiền:</span>
                            <span class="fw-bold text-success fs-5">
                                <?php echo $order['total_amount'] > 0 ? number_format($order['total_amount']) . ' VNĐ' : 'Miễn phí'; ?>
                            </span>
                        </div>
                        <small class="text-muted">
                            * Phí vận chuyển: <?php echo $shippingFee > 0 ? number_format($shippingFee) . ' VND' : 'Miễn phí'; ?>
                        </small>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="order-detail.php?id=<?php echo $order_id; ?>" class="btn btn-track-secondary">
                            <i class="bi bi-receipt me-2"></i>Xem chi tiết đơn
                        </a>
                        <a href="my-orders.php" class="btn btn-track-primary">
                            <i class="bi bi-list-ul me-2"></i>Danh sách đơn hàng
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --track-bg-1: #f1f9f7;
    --track-bg-2: #ecf3ff;
    --track-card-bg: rgba(255, 255, 255, 0.88);
    --track-border: rgba(29, 110, 84, 0.14);
    --track-primary: #0f766e;
    --track-primary-2: #0ea5a5;
    --track-ink: #123036;
}

.tracking-page {
    font-family: 'Manrope', 'Segoe UI', sans-serif;
    position: relative;
}

.tracking-page::before {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    bottom: 0;
    background:
        radial-gradient(circle at 8% 12%, rgba(14, 165, 165, 0.18), transparent 30%),
        radial-gradient(circle at 90% 2%, rgba(37, 99, 235, 0.14), transparent 28%),
        linear-gradient(180deg, var(--track-bg-1) 0%, var(--track-bg-2) 100%);
    border-radius: 24px;
    z-index: -1;
}

.tracking-hero {
    position: relative;
    background: linear-gradient(130deg, rgba(15, 118, 110, 0.95), rgba(14, 165, 165, 0.9) 50%, rgba(21, 128, 61, 0.86));
    border-radius: 22px;
    padding: 1.4rem;
    color: #fff;
    overflow: hidden;
    box-shadow: 0 18px 40px rgba(15, 118, 110, 0.25);
}

.tracking-hero__glow {
    position: absolute;
    width: 320px;
    height: 320px;
    border-radius: 999px;
    top: -170px;
    right: -100px;
    background: radial-gradient(circle, rgba(255,255,255,0.38) 0%, rgba(255,255,255,0) 70%);
    pointer-events: none;
}

.tracking-hero__content,
.tracking-meta-grid {
    position: relative;
    z-index: 1;
}

.tracking-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.4rem 0.75rem;
    background: rgba(255, 255, 255, 0.18);
    border-radius: 999px;
    font-size: 0.8rem;
    letter-spacing: 0.2px;
}

.tracking-title {
    font-weight: 800;
    font-size: clamp(1.6rem, 2.5vw, 2.2rem);
    line-height: 1.2;
}

.tracking-subtitle {
    color: rgba(255, 255, 255, 0.88);
    font-weight: 500;
}

.tracking-meta-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
}

.tracking-meta-card {
    padding: 0.75rem 0.95rem;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.28);
    backdrop-filter: blur(4px);
}

.tracking-meta-card .label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.86);
    margin-bottom: 0.15rem;
}

.tracking-meta-card .value {
    font-size: 0.95rem;
    font-weight: 700;
    color: #fff;
}

.tracking-card {
    border: 1px solid var(--track-border);
    border-radius: 18px;
    box-shadow: 0 10px 24px rgba(0, 24, 61, 0.07);
    background: var(--track-card-bg);
    backdrop-filter: blur(6px);
    overflow: hidden;
}

.tracking-card-header {
    background: linear-gradient(180deg, rgba(255,255,255,0.95), rgba(250, 255, 253, 0.9));
    border-bottom: 1px solid rgba(13, 148, 136, 0.14);
}

#trackingMap {
    height: 400px;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid rgba(13, 148, 136, 0.2);
}

.tracking-map-summary {
    line-height: 1.65;
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.55rem;
}

.tracking-info-chip {
    padding: 0.55rem 0.7rem;
    border-radius: 10px;
    background: rgba(15, 118, 110, 0.06);
    border: 1px solid rgba(15, 118, 110, 0.11);
}

.tracking-shipping-meta a {
    color: #0f766e;
    font-weight: 700;
    text-decoration: none;
}

.tracking-shipping-meta a:hover {
    text-decoration: underline;
}

.tracking-progress {
    height: 22px;
    background: #dbece9;
}

.tracking-progress .progress-bar {
    background: linear-gradient(90deg, #0f766e 0%, #14b8a6 100%);
}

.progress-value {
    color: #0f766e;
}

.tracking-steps {
    gap: 10px;
    flex-wrap: wrap;
}
.tracking-step {
    flex: 1;
    text-align: center;
    min-width: 92px;
}
.tracking-step .step-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin: 0 auto 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #dfebe8;
    color: #6c757d;
    border: 1px solid #cbe1dd;
}
.tracking-step.step-active .step-icon {
    background: var(--track-primary);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 8px 20px rgba(15, 118, 110, 0.28);
}
.tracking-step.step-cancelled .step-icon {
    background: #dc3545;
    color: #fff;
}
.tracking-step .step-label {
    font-size: 0.85rem;
}
.tracking-step .step-current {
    font-size: 0.75rem;
    color: var(--track-primary);
}

.section-title-mini {
    color: var(--track-ink);
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
    background-color: #198754;
}
.timeline-content {
    background: linear-gradient(180deg, #ffffff, #f6fbfa);
    padding: 15px;
    border-radius: 12px;
    border: 1px solid #dceae6;
    border-left: 3px solid #9ad3c7;
}

.gw-vehicle-icon {
    background: transparent;
    border: 0;
}
.gw-vehicle {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 2px solid #fff;
    filter: drop-shadow(0 2px 8px rgba(0,0,0,0.35));
    transform-origin: 50% 50%;
}
.gw-vehicle i {
    font-size: 18px;
    line-height: 1;
    color: #fff;
}
.gw-vehicle--shipper {
    background: #0d6efd;
}
.gw-vehicle--truck {
    background: #fd7e14;
}

.order-summary-header {
    background: linear-gradient(110deg, #0f766e, #14b8a6);
    border-bottom: 0;
}

.btn-track-primary,
.btn-track-secondary {
    border-radius: 12px;
    font-weight: 700;
    padding: 0.55rem 0.95rem;
}

.btn-track-primary {
    color: #fff;
    background: linear-gradient(90deg, #0f766e, #14b8a6);
    border: 0;
}

.btn-track-primary:hover {
    color: #fff;
    filter: brightness(1.03);
}

.btn-track-secondary {
    color: #0f766e;
    background: #fff;
    border: 1px solid #9fd0c8;
}

.btn-track-secondary:hover {
    color: #0f766e;
    border-color: #7abdb1;
    background: #f7fffd;
}

@media (max-width: 991.98px) {
    .tracking-meta-grid {
        grid-template-columns: 1fr;
    }

    .tracking-hero {
        border-radius: 18px;
    }

    #trackingMap {
        height: 340px;
    }
}

@media (max-width: 575.98px) {
    .tracking-page {
        padding-top: 1rem;
    }

    .tracking-hero {
        padding: 1rem;
    }

    .tracking-title {
        font-size: 1.45rem;
    }

    .tracking-step {
        min-width: 74px;
    }

    .tracking-step .step-label {
        font-size: 0.78rem;
    }
}
</style>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
    const orderId = <?php echo (int)$order_id; ?>;
    const apiUrl = 'api/get-order-tracking-events.php?order_id=' + encodeURIComponent(orderId);
    let currentStatus = <?php echo json_encode((string)$currentStatus); ?>;
    const useLogistics = <?php echo json_encode((bool)$useLogistics); ?>;
    const isAdmin = <?php echo json_encode((bool)$isAdmin); ?>;

    const map = L.map('trackingMap', { zoomControl: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const layerGroup = L.layerGroup().addTo(map);
    const vehicleLayer = L.layerGroup().addTo(map);
    let routeLayer = null;
    const mapStatusEl = document.getElementById('trackingMapStatus');
    const trackingLegLabelEl = document.getElementById('trackingLegLabel');
    const trackingOriginLabelEl = document.getElementById('trackingOriginLabel');
    const trackingDestinationLabelEl = document.getElementById('trackingDestinationLabel');
    let lastFingerprint = '';
    let vehicleMarker = null;
    let routeFingerprint = '';
    let animHandle = null;
    let animStartMs = 0;
    let animDurationMs = 0;
    let routeSegments = [];
    let cumulativeDistances = [];
    let totalMeters = 0;
    const animStateKey = 'gw_tracking_anim_' + String(orderId);

    function safeText(value) {
        return (value == null ? '' : String(value));
    }

    function haversineMeters(a, b) {
        const R = 6371000;
        const toRad = (d) => (d * Math.PI) / 180;
        const lat1 = toRad(a[0]);
        const lat2 = toRad(b[0]);
        const dLat = toRad(b[0] - a[0]);
        const dLng = toRad(b[1] - a[1]);
        const s = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
        return 2 * R * Math.asin(Math.min(1, Math.sqrt(s)));
    }

    function bearingDeg(a, b) {
        const toRad = (d) => (d * Math.PI) / 180;
        const toDeg = (r) => (r * 180) / Math.PI;
        const lat1 = toRad(a[0]);
        const lat2 = toRad(b[0]);
        const dLng = toRad(b[1] - a[1]);
        const y = Math.sin(dLng) * Math.cos(lat2);
        const x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLng);
        return (toDeg(Math.atan2(y, x)) + 360) % 360;
    }

    async function fetchRoadRoute(points) {
        if (!Array.isArray(points) || points.length < 2) return null;

        // Cache by rounded coords to reduce requests
        const cacheKey = 'gw_route:' + points.map(p => `${p[0].toFixed(5)},${p[1].toFixed(5)}`).join('|');
        try {
            const cached = localStorage.getItem(cacheKey);
            if (cached) {
                const parsed = JSON.parse(cached);
                if (parsed && Array.isArray(parsed.coords) && parsed.coords.length >= 2) return parsed.coords;
            }
        } catch (e) {}

        const coordStr = points.map(p => `${p[1]},${p[0]}`).join(';'); // lng,lat
        const url = `https://router.project-osrm.org/route/v1/driving/${coordStr}?overview=full&geometries=geojson&steps=false`;

        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return null;
        const data = await res.json();
        const route = data && Array.isArray(data.routes) ? data.routes[0] : null;
        const coords = route && route.geometry && Array.isArray(route.geometry.coordinates) ? route.geometry.coordinates : null;
        if (!coords || coords.length < 2) return null;

        const latlngs = coords.map(c => [c[1], c[0]]);
        try { localStorage.setItem(cacheKey, JSON.stringify({ coords: latlngs, ts: Date.now() })); } catch (e) {}
        return latlngs;
    }

    async function geocodeAddress(address) {
        const trimmed = safeText(address).trim();
        if (!trimmed) return null;

        const cacheKey = 'gw_geocode:v2:' + trimmed.toLowerCase();
        try {
            const cached = localStorage.getItem(cacheKey);
            if (cached) {
                const parsed = JSON.parse(cached);
                if (parsed && typeof parsed.lat === 'number' && typeof parsed.lng === 'number') return parsed;
            }
        } catch (e) {}

        const candidates = [trimmed];
        if (!/viet\s*nam|vietnam/i.test(trimmed)) {
            candidates.push(trimmed + ', Việt Nam');
        }

        const parts = trimmed.split(',').map(p => p.trim()).filter(Boolean);
        if (parts.length >= 2) {
            candidates.push(parts.slice(-2).join(', ') + ', Việt Nam');
        }
        if (parts.length >= 1) {
            candidates.push(parts[parts.length - 1] + ', Việt Nam');
        }

        const unique = [];
        const seen = new Set();
        for (const c of candidates) {
            const key = c.toLowerCase();
            if (seen.has(key)) continue;
            seen.add(key);
            unique.push(c);
        }

        for (const query of unique) {
            const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=vn&q=' + encodeURIComponent(query);
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) continue;
            const data = await res.json();
            const first = Array.isArray(data) ? data[0] : null;
            if (!first || !first.lat || !first.lon) continue;

            const result = { lat: parseFloat(first.lat), lng: parseFloat(first.lon) };
            try { localStorage.setItem(cacheKey, JSON.stringify(result)); } catch (e) {}
            return result;
        }

        return null;
    }

    function fingerprint(events) {
        if (!Array.isArray(events) || events.length === 0) return '';
        const last = events[events.length - 1];
        return [events.length, safeText(last.occurred_at), safeText(last.title), safeText(last.location_address)].join('|');
    }

    async function hydrateCoords(event) {
        if (event.lat != null && event.lng != null) return event;
        const geo = await geocodeAddress(event.location_address || '');
        if (!geo) return event;
        return Object.assign({}, event, geo);
    }

    function isShippingMode() {
        const s = safeText(currentStatus).toLowerCase();
        if (!s) return false;
        if (useLogistics) {
            return ['created', 'waiting_pickup', 'picked_up', 'in_transit', 'out_for_delivery'].includes(s);
        }
        return s === 'shipping';
    }

    function legLabelForStatus(status) {
        const s = safeText(status).toLowerCase();
        if (s === 'in_transit') return 'Xe tải đang chạy giữa các kho trung chuyển';
        if (s === 'out_for_delivery') return 'Xe máy đang giao từ kho khu vực đến nhà khách';
        if (s === 'picked_up') return 'Đơn hàng vừa rời kho chính bằng xe tải';
        if (s === 'waiting_pickup' || s === 'created') return 'Đơn hàng đang ở kho chính và chờ xuất kho';
        if (s === 'delivered') return 'Đơn hàng đã giao thành công';
        if (s === 'cancelled') return 'Đơn hàng đã bị hủy';
        return 'Đang cập nhật trạng thái vận chuyển';
    }

    function updateTrackingSummary(payload) {
        const trackingStatus = safeText(payload && payload.tracking_status ? payload.tracking_status : currentStatus);
        const originAddress = safeText(payload && payload.map_origin_address);
        const destinationAddress = safeText(payload && payload.map_destination_address);

        if (trackingLegLabelEl) {
            trackingLegLabelEl.textContent = legLabelForStatus(trackingStatus);
        }
        if (trackingOriginLabelEl) {
            trackingOriginLabelEl.textContent = originAddress || 'Đang cập nhật...';
        }
        if (trackingDestinationLabelEl) {
            trackingDestinationLabelEl.textContent = destinationAddress || 'Đang cập nhật...';
        }
    }

    function buildVehicleIcon(vehicleType, rotationDeg) {
        const rot = Number.isFinite(rotationDeg) ? rotationDeg : 0;
        const type = safeText(vehicleType).toLowerCase();
        // Use icons that exist in Bootstrap Icons reliably
        const iconClass = type === 'truck' ? 'bi-truck' : 'bi-bicycle';
        const html = `
            <div class="gw-vehicle ${type === 'truck' ? 'gw-vehicle--truck' : 'gw-vehicle--shipper'}" style="transform: rotate(${rot}deg)">
                <i class="bi ${iconClass}"></i>
            </div>
        `;
        return L.divIcon({
            className: 'gw-vehicle-icon',
            html,
            iconSize: [34, 34],
            iconAnchor: [17, 17],
        });
    }

    function cancelAnimation() {
        if (animHandle) {
            cancelAnimationFrame(animHandle);
            animHandle = null;
        }
        animStartMs = 0;
        animDurationMs = 0;
        routeSegments = [];
        cumulativeDistances = [];
        totalMeters = 0;
    }

    function readAnimState() {
        try {
            const raw = localStorage.getItem(animStateKey);
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') return null;
            return parsed;
        } catch (e) {
            return null;
        }
    }

    function writeAnimState(state) {
        try {
            localStorage.setItem(animStateKey, JSON.stringify(state));
        } catch (e) {
            // Ignore localStorage write errors.
        }
    }

    function clearAnimState() {
        try {
            localStorage.removeItem(animStateKey);
        } catch (e) {
            // Ignore localStorage remove errors.
        }
    }

    function pointAtDistance(meters) {
        if (!routeSegments.length || totalMeters <= 0) return null;
        const target = Math.min(Math.max(0, meters), totalMeters);
        let segIdx = 0;
        while (segIdx < cumulativeDistances.length && cumulativeDistances[segIdx] < target) segIdx++;

        const prevCum = segIdx === 0 ? 0 : cumulativeDistances[segIdx - 1];
        const seg = routeSegments[Math.min(segIdx, routeSegments.length - 1)];
        const segLen = seg.len;
        if (segLen <= 0) return { latlng: seg.b, rotation: 0 };

        const t = Math.min(1, Math.max(0, (target - prevCum) / segLen));
        const lat = seg.a[0] + (seg.b[0] - seg.a[0]) * t;
        const lng = seg.a[1] + (seg.b[1] - seg.a[1]) * t;
        const rot = bearingDeg(seg.a, seg.b);
        return { latlng: [lat, lng], rotation: rot };
    }

    function vehicleTypeForStatus() {
        const s = safeText(currentStatus).toLowerCase();
        if (useLogistics) {
            if (['in_transit', 'picked_up'].includes(s)) return 'truck';
            if (s === 'out_for_delivery') return 'scooter';
            if (['created', 'waiting_pickup'].includes(s)) return 'truck';
            return 'scooter';
        }
        return s === 'shipping' ? 'scooter' : 'scooter';
    }

    function ensureVehicleMarker(latlng, rotationDeg) {
        if (!Array.isArray(latlng) || latlng.length !== 2) {
            return;
        }

        const icon = buildVehicleIcon(vehicleTypeForStatus(), rotationDeg);
        if (!vehicleMarker) {
            vehicleMarker = L.marker(latlng, {
                icon,
                keyboard: false,
                zIndexOffset: 1000,
            }).addTo(vehicleLayer);
            return;
        }

        vehicleMarker.setLatLng(latlng);
        vehicleMarker.setIcon(icon);
    }

    function getVehicleSpeed() {
        const s = safeText(currentStatus).toLowerCase();
        if (useLogistics) {
            // Trung chuyển: 70 km/h (transit hubs)
            if (s === 'in_transit') return 70;
            // Đang giao: 40 km/h (last-mile delivery is slower)
            if (s === 'out_for_delivery') return 40;
            // Default: 70 km/h
            return 70;
        }
        // Non-logistics mode: 40 km/h
        return 40;
    }

    function syntheticTransitStops(start, end, count) {
        const stops = [];
        if (!start || !end || count <= 0) return stops;

        // deterministic pseudo-random offset by orderId
        const seed = (orderId * 9301 + 49297) % 233280;
        const rand01 = (n) => ((seed + n * 9301) % 233280) / 233280;

        for (let i = 1; i <= count; i++) {
            const t = i / (count + 1);
            const lat = start[0] + (end[0] - start[0]) * t;
            const lng = start[1] + (end[1] - start[1]) * t;

            // perpendicular offset for "station feel" (small)
            const dx = end[1] - start[1];
            const dy = end[0] - start[0];
            const mag = Math.max(1e-9, Math.sqrt(dx * dx + dy * dy));
            const ox = (-dy / mag) * (0.01 * (rand01(i) - 0.5));
            const oy = (dx / mag) * (0.01 * (rand01(i + 7) - 0.5));

            stops.push({
                title: `Trạm chuyển tiếp ${i}`,
                note: 'Mô phỏng trạm chuyển tiếp (vận chuyển trung chuyển).',
                lat: lat + oy,
                lng: lng + ox,
            });
        }
        return stops;
    }

    function startVehicleAnimation(points) {
        if (!isShippingMode()) {
            vehicleLayer.clearLayers();
            vehicleMarker = null;
            cancelAnimation();
            clearAnimState();
            if (mapStatusEl) mapStatusEl.textContent = '';
            return;
        }

        if (!Array.isArray(points) || points.length < 2) {
            if (mapStatusEl) {
                mapStatusEl.textContent = 'Chưa đủ tọa độ để mô phỏng phương tiện trên chặng hiện tại.';
            }
            return;
        }
        const mode = safeText(currentStatus).toLowerCase();
        const vehicleLabel = (vehicleTypeForStatus() === 'truck') ? 'Xe tải' : 'Xe máy giao hàng';
        const speedKmh = getVehicleSpeed();
        if (mapStatusEl) mapStatusEl.textContent = `${vehicleLabel} đang di chuyển trên chặng hiện tại (${speedKmh} km/h mô phỏng).`;

        // Add synthetic transit stations for in_transit when only start/end
        let routePoints = points.slice();
        if (useLogistics && mode === 'in_transit' && points.length === 2 && haversineMeters(points[0], points[1]) > 80000) {
            const synth = syntheticTransitStops(points[0], points[1], 2);
            synth.forEach((s) => {
                const latlng = [s.lat, s.lng];
                routePoints.splice(routePoints.length - 1, 0, latlng);
                const html = `
                    <div style="min-width:220px">
                        <div><strong>${safeText(s.title)}</strong></div>
                        <div class="text-muted small">${safeText(s.note)}</div>
                    </div>
                `;
                L.circleMarker(latlng, { radius: 7, color: '#fd7e14', fillColor: '#fd7e14', fillOpacity: 0.85 })
                    .bindPopup(html)
                    .addTo(layerGroup);
            });
        }

        const fp = safeText(currentStatus) + '|' + routePoints.map(p => p.join(',')).join('|');
        if (fp === routeFingerprint && animHandle) return;
        routeFingerprint = fp;

        cancelAnimation();

        routeSegments = [];
        cumulativeDistances = [];
        totalMeters = 0;
        for (let i = 0; i < routePoints.length - 1; i++) {
            const a = routePoints[i];
            const b = routePoints[i + 1];
            const len = haversineMeters(a, b);
            if (len <= 0) continue;
            routeSegments.push({ a, b, len });
            totalMeters += len;
            cumulativeDistances.push(totalMeters);
        }
        if (totalMeters <= 0 || !routeSegments.length) return;

        const speedMps = (speedKmh * 1000) / 3600;
        animDurationMs = Math.max(1000, (totalMeters / speedMps) * 1000);

        const nowWall = Date.now();
        const nowPerf = performance.now();
        const saved = readAnimState();
        let startedAtWallMs = nowWall;

        if (
            saved
            && saved.fp === fp
            && Number.isFinite(saved.startedAtWallMs)
            && Number.isFinite(saved.durationMs)
            && saved.durationMs > 0
        ) {
            startedAtWallMs = saved.startedAtWallMs;
            animDurationMs = saved.durationMs;
        } else {
            writeAnimState({
                fp,
                startedAtWallMs,
                durationMs: animDurationMs,
            });
        }

        animStartMs = nowPerf - Math.max(0, (nowWall - startedAtWallMs));

        const start = pointAtDistance(0);
        if (start) ensureVehicleMarker(start.latlng, start.rotation);

        const tick = (now) => {
            const elapsed = now - animStartMs;
            const progress = Math.min(1, Math.max(0, elapsed / animDurationMs));
            const pos = pointAtDistance(progress * totalMeters);
            if (pos) ensureVehicleMarker(pos.latlng, pos.rotation);

            if (progress < 1) {
                animHandle = requestAnimationFrame(tick);
            } else {
                animHandle = null;
                clearAnimState();
                const end = pointAtDistance(totalMeters);
                if (end) ensureVehicleMarker(end.latlng, end.rotation);

                if (isAdmin) {
                    fetch('api/mark-order-delivered.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ order_id: orderId })
                    }).then(() => {
                        setTimeout(() => location.reload(), 800);
                    }).catch(() => {});
                } else if (useLogistics && safeText(currentStatus).toLowerCase() === 'out_for_delivery') {
                    fetch('api/auto-deliver-order.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify({ order_id: orderId })
                    }).then(async (r) => {
                        if (!r.ok) return;
                        const j = await r.json().catch(() => null);
                        if (j && j.success) {
                            setTimeout(() => location.reload(), 800);
                        }
                    }).catch(() => {});
                }
            }
        };

        animHandle = requestAnimationFrame(tick);
    }

    function render(events) {
        layerGroup.clearLayers();
        if (routeLayer) {
            try { map.removeLayer(routeLayer); } catch (e) {}
            routeLayer = null;
        }

        const points = [];
        events.forEach((ev) => {
            const hasCoord = typeof ev.lat === 'number' && !Number.isNaN(ev.lat) && typeof ev.lng === 'number' && !Number.isNaN(ev.lng);
            if (!hasCoord) return;

            const latlng = [ev.lat, ev.lng];
            points.push(latlng);

            const title = safeText(ev.title) || 'Cập nhật';
            const addr = safeText(ev.location_address);
            const time = safeText(ev.occurred_at);
            const note = safeText(ev.note);

            const html = `
                <div style="min-width:220px">
                    <div><strong>${title}</strong></div>
                    ${addr ? `<div class="text-muted">${addr}</div>` : ''}
                    ${time ? `<div class="text-muted small">${time}</div>` : ''}
                    ${note ? `<div style="margin-top:6px">${note}</div>` : ''}
                </div>
            `;

            L.marker(latlng, { title }).bindPopup(html).addTo(layerGroup);
        });

        if (points.length === 1) {
            map.setView(points[0], 13);
        } else {
            map.setView([16.047079, 108.206230], 11);
        }

        // Prefer road-following route (OSRM). Fallback to straight line.
        (async () => {
            if (points.length < 2) {
                startVehicleAnimation(points);
                return;
            }

            const mode = safeText(currentStatus).toLowerCase();
            let routePoints = points.slice();
            if (useLogistics && mode === 'in_transit' && points.length === 2 && haversineMeters(points[0], points[1]) > 80000) {
                const synth = syntheticTransitStops(points[0], points[1], 2);
                synth.forEach((s) => {
                    const latlng = [s.lat, s.lng];
                    routePoints.splice(routePoints.length - 1, 0, latlng);
                    const html = `
                        <div style="min-width:220px">
                            <div><strong>${safeText(s.title)}</strong></div>
                            <div class="text-muted small">${safeText(s.note)}</div>
                        </div>
                    `;
                    L.circleMarker(latlng, { radius: 7, color: '#fd7e14', fillColor: '#fd7e14', fillOpacity: 0.85 })
                        .bindPopup(html)
                        .addTo(layerGroup);
                });
            }

            const key = safeText(currentStatus) + '|' + routePoints.map(p => p.join(',')).join('|');
            if (key === routeFingerprint && routeLayer) {
                startVehicleAnimation(routePoints);
                return;
            }

            let road = null;
            try {
                road = await fetchRoadRoute(routePoints);
            } catch (e) {
                road = null;
            }
            const path = (road && road.length >= 2) ? road : routePoints;

            routeLayer = L.polyline(path, { color: '#0d6efd', weight: 4, opacity: 0.9 });
            routeLayer.addTo(map);
            try { map.fitBounds(routeLayer.getBounds(), { padding: [24, 24] }); } catch (e) {}

            startVehicleAnimation(path);
        })();
    }

    async function refresh() {
        try {
            const res = await fetch(apiUrl, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data || !data.success) return;
            currentStatus = safeText(data.tracking_status || currentStatus);
            updateTrackingSummary(data);

            const events = Array.isArray(data.events) ? data.events : [];
            const fp = fingerprint(events);
            if (fp && fp === lastFingerprint) return;
            lastFingerprint = fp;

            const hydrated = await Promise.all(events.map(hydrateCoords));
            render(hydrated);
        } catch (e) {
            if (mapStatusEl) {
                mapStatusEl.textContent = 'Không thể tải dữ liệu tracking lúc này.';
            }
        }
    }

    map.setView([16.047079, 108.206230], 11);
    refresh();
    setInterval(refresh, 30000);
})();
</script>

<?php include 'includes/footer.php'; ?>
