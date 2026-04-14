<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = "Đặt hàng thành công";

$order_id = (int)($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    header('Location: cart.php');
    exit();
}

// Get order details
$order = Database::fetch(
    "SELECT o.*, u.name as user_name, u.email as user_email 
     FROM orders o 
     JOIN users u ON o.user_id = u.user_id 
     WHERE o.order_id = ? AND o.user_id = ?",
    [$order_id, $_SESSION['user_id']]
);

if (!$order) {
    header('Location: cart.php');
    exit();
}

// Compat cho cA3 hai phiA�n bA?n schema
$shippingName = isset($order['shipping_name']) && $order['shipping_name'] !== '' ? $order['shipping_name'] : ($order['user_name'] ?? '');
$shippingNote = $order['shipping_note'] ?? ($order['notes'] ?? '');
$paymentMethodLabel = formatPaymentMethodLabel($order['payment_method'] ?? '');
$isBankTransfer = strtolower((string)($order['payment_method'] ?? '')) === 'bank_transfer';
$qrPlaceholder = 'data:image/svg+xml;base64,' . base64_encode('
<svg xmlns="http://www.w3.org/2000/svg" width="420" height="420" viewBox="0 0 420 420">
  <rect width="420" height="420" fill="#0f5132" rx="24" />
  <rect x="16" y="16" width="388" height="388" fill="#f8f9fa" rx="20" />
  <rect x="42" y="42" width="336" height="336" fill="#0f5132" rx="16" opacity="0.06"/>
  <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
        font-family="Arial, Helvetica, sans-serif" font-size="28" fill="#0f5132">
    QR chuyen khoan
  </text>
</svg>');

// Get order items
$orderItems = Database::fetchAll(
    "SELECT oi.*, i.images, i.condition_status, i.unit
     FROM order_items oi
     LEFT JOIN inventory i ON oi.item_id = i.item_id
     WHERE oi.order_id = ?
     ORDER BY oi.created_at",
    [$order_id]
);

include 'includes/header.php';
?>

<?php
$orderStatusKey = strtolower((string)($order['status'] ?? 'pending'));
$statusMetaMap = [
    'pending' => ['class' => 'warning', 'text' => 'Đang xử lý'],
    'confirmed' => ['class' => 'info', 'text' => 'Đã xác nhận'],
    'processing' => ['class' => 'info', 'text' => 'Đang xử lý'],
    'shipping' => ['class' => 'primary', 'text' => 'Đang giao'],
    'delivered' => ['class' => 'success', 'text' => 'Đã giao'],
    'completed' => ['class' => 'success', 'text' => 'Hoàn tất'],
    'cancelled' => ['class' => 'danger', 'text' => 'Đã hủy'],
];
$statusMeta = $statusMetaMap[$orderStatusKey] ?? ['class' => 'secondary', 'text' => ucfirst((string)$orderStatusKey)];
?>

<style>
    :root {
        --os-bg: #eaf6f8;
        --os-surface: #ffffff;
        --os-line: #cce6ed;
        --os-ink: #10313b;
        --os-muted: #5b7380;
        --os-brand-700: #0f6f86;
        --os-brand-600: #17839c;
        --os-brand-500: #1f9bb5;
        --os-brand-100: #e2f4f8;
        --os-shadow: 0 14px 40px rgba(15, 111, 134, 0.14);
    }

    body {
        background:
            radial-gradient(circle at 0% 0%, rgba(31, 155, 181, 0.16), transparent 26%),
            radial-gradient(circle at 100% 0%, rgba(15, 111, 134, 0.14), transparent 30%),
            linear-gradient(180deg, #f5fbfc 0%, var(--os-bg) 100%);
    }

    .os-page {
        padding-top: 2rem;
        padding-bottom: 3rem;
    }

    .os-hero {
        border-radius: 24px;
        background: linear-gradient(135deg, var(--os-brand-700) 0%, var(--os-brand-600) 56%, #246b7d 100%);
        color: #fff;
        box-shadow: var(--os-shadow);
        position: relative;
        overflow: hidden;
        padding: 2rem;
    }

    .os-hero::before {
        content: '';
        position: absolute;
        width: 340px;
        height: 340px;
        border-radius: 50%;
        right: -120px;
        top: -140px;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0));
    }

    .os-hero-grid {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 1.2rem;
        align-items: center;
    }

    .os-check {
        width: 78px;
        height: 78px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.17);
        border: 1px solid rgba(255, 255, 255, 0.26);
        font-size: 2.2rem;
        margin-bottom: 1rem;
    }

    .os-title {
        margin: 0;
        font-size: clamp(1.8rem, 2.6vw, 2.6rem);
        line-height: 1.1;
        font-weight: 900;
    }

    .os-sub {
        margin-top: 0.75rem;
        margin-bottom: 0;
        color: rgba(255, 255, 255, 0.9);
        max-width: 720px;
    }

    .os-pill-wrap {
        display: flex;
        gap: 0.55rem;
        flex-wrap: wrap;
        margin-top: 1rem;
    }

    .os-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: #fff;
        padding: 0.48rem 0.75rem;
        border-radius: 999px;
        font-weight: 600;
        font-size: 0.84rem;
    }

    .os-hero-actions {
        display: grid;
        gap: 0.6rem;
        min-width: 220px;
    }

    .os-btn-main,
    .os-btn-sub {
        border: 0;
        border-radius: 12px;
        padding: 0.72rem 1rem;
        text-align: center;
        font-weight: 700;
        text-decoration: none;
    }

    .os-btn-main {
        background: #fff;
        color: var(--os-brand-700);
    }

    .os-btn-main:hover {
        color: var(--os-brand-700);
        background: #f2fbfd;
    }

    .os-btn-sub {
        background: rgba(255, 255, 255, 0.14);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .os-btn-sub:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.22);
    }

    .os-card {
        border: 1px solid var(--os-line);
        border-radius: 18px;
        box-shadow: 0 8px 28px rgba(16, 77, 92, 0.08);
        background: var(--os-surface);
    }

    .os-card-head {
        padding: 1rem 1.2rem;
        border-bottom: 1px solid #e7f1f4;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.8rem;
        flex-wrap: wrap;
    }

    .os-card-title {
        margin: 0;
        font-size: 1rem;
        color: var(--os-ink);
        font-weight: 800;
    }

    .os-card-body {
        padding: 1.15rem 1.2rem;
    }

    .os-summary-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.7rem;
        margin-top: 1rem;
    }

    .os-mini {
        background: #f7fdfe;
        border: 1px solid #d9edf3;
        border-radius: 12px;
        padding: 0.75rem 0.85rem;
    }

    .os-mini-label {
        color: var(--os-muted);
        font-size: 0.74rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.2rem;
    }

    .os-mini-value {
        color: var(--os-ink);
        font-weight: 700;
        font-size: 0.95rem;
    }

    .os-items-list {
        display: grid;
        gap: 0.9rem;
    }

    .os-item {
        border: 1px solid #e3f0f4;
        border-radius: 14px;
        padding: 0.75rem;
        display: grid;
        grid-template-columns: 62px 1fr auto;
        gap: 0.8rem;
        align-items: center;
    }

    .os-item img {
        width: 62px;
        height: 62px;
        border-radius: 10px;
        object-fit: cover;
        border: 1px solid #d5eaf0;
    }

    .os-item-name {
        margin: 0;
        color: var(--os-ink);
        font-size: 0.95rem;
        font-weight: 700;
    }

    .os-item-sub {
        margin: 0.22rem 0 0;
        color: var(--os-muted);
        font-size: 0.82rem;
    }

    .os-item-price {
        text-align: right;
        min-width: 120px;
    }

    .os-item-price .unit {
        color: var(--os-muted);
        font-size: 0.75rem;
    }

    .os-item-price .line {
        color: #0f7d63;
        font-weight: 800;
        margin: 0;
        font-size: 0.93rem;
    }

    .os-badge-soft {
        border-radius: 999px;
        font-weight: 700;
        font-size: 0.75rem;
        padding: 0.35rem 0.7rem;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .os-badge-soft.warning { background: #fff7db; color: #9a6700; }
    .os-badge-soft.info { background: #e3f3ff; color: #0b5da8; }
    .os-badge-soft.primary { background: #e8ecff; color: #3740a5; }
    .os-badge-soft.success { background: #e3f8ed; color: #13663f; }
    .os-badge-soft.danger { background: #ffe7ea; color: #9a2330; }
    .os-badge-soft.secondary { background: #f0f4f7; color: #516072; }

    .os-steps {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
        margin-top: 0.5rem;
    }

    .os-step {
        border: 1px solid #d7ebf1;
        border-radius: 14px;
        padding: 0.8rem;
        background: #f8fdfe;
        text-align: center;
    }

    .os-step i {
        color: var(--os-brand-700);
        font-size: 1.35rem;
    }

    .os-step h6 {
        margin: 0.5rem 0 0.2rem;
        color: var(--os-ink);
        font-size: 0.9rem;
    }

    .os-step p {
        margin: 0;
        color: var(--os-muted);
        font-size: 0.77rem;
    }

    @media (max-width: 991.98px) {
        .os-hero-grid {
            grid-template-columns: 1fr;
        }

        .os-hero-actions {
            min-width: 0;
            grid-template-columns: 1fr 1fr;
        }

        .os-summary-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .os-page {
            padding-top: 1rem;
        }

        .os-hero {
            border-radius: 18px;
            padding: 1.25rem;
        }

        .os-item {
            grid-template-columns: 54px 1fr;
        }

        .os-item-price {
            grid-column: 1 / -1;
            text-align: left;
        }

        .os-steps {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="container os-page mt-5">
    <section class="os-hero mb-4">
        <div class="os-hero-grid">
            <div>
                <div class="os-check"><i class="bi bi-check2-circle"></i></div>
                <h1 class="os-title">Đơn hàng đã được ghi nhận</h1>
                <p class="os-sub">Cảm ơn bạn đã ủng hộ Goodwill Vietnam. Hệ thống đã tiếp nhận đơn và sẽ cập nhật trạng thái giao hàng sớm nhất.</p>

                <div class="os-pill-wrap">
                    <span class="os-pill"><i class="bi bi-hash"></i>Mã đơn: <?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></span>
                    <span class="os-pill"><i class="bi bi-wallet2"></i><?php echo htmlspecialchars($paymentMethodLabel); ?></span>
                    <span class="os-pill"><i class="bi bi-clock-history"></i><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></span>
                </div>
            </div>

            <div class="os-hero-actions">
                <a href="my-orders.php" class="os-btn-main"><i class="bi bi-list-ul me-1"></i>Đơn hàng của tôi</a>
                <a href="order-tracking.php?id=<?php echo (int)$order_id; ?>" class="os-btn-sub"><i class="bi bi-truck me-1"></i>Theo dõi đơn</a>
                <a href="shop.php" class="os-btn-sub" style="grid-column: 1 / -1;"><i class="bi bi-bag me-1"></i>Tiếp tục mua sắm</a>
            </div>
        </div>
    </section>

    <?php if ($isBankTransfer): ?>
        <section class="os-card mb-4">
            <div class="os-card-head">
                <h2 class="os-card-title"><i class="bi bi-bank me-2"></i>Thông tin chuyển khoản</h2>
                <span class="os-badge-soft warning"><i class="bi bi-hourglass-split"></i>Chờ xác nhận</span>
            </div>
            <div class="os-card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-lg-7">
                        <div class="os-summary-grid">
                            <div class="os-mini">
                                <div class="os-mini-label">Ngân hàng</div>
                                <div class="os-mini-value">ACB (demo)</div>
                            </div>
                            <div class="os-mini">
                                <div class="os-mini-label">Số tài khoản</div>
                                <div class="os-mini-value">123 456 789</div>
                            </div>
                            <div class="os-mini">
                                <div class="os-mini-label">Chủ tài khoản</div>
                                <div class="os-mini-value">Goodwill Vietnam</div>
                            </div>
                            <div class="os-mini">
                                <div class="os-mini-label">Số tiền</div>
                                <div class="os-mini-value"><?php echo $order['total_amount'] > 0 ? number_format($order['total_amount']) . ' VNĐ' : '0 VNĐ'; ?></div>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3 mb-0">
                            <strong>Nội dung chuyển khoản:</strong> DON-<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?><br>
                            Vui lòng chờ 1-5 phút để hệ thống tự động xác nhận thanh toán.
                        </div>
                    </div>
                    <div class="col-lg-5 text-center">
                        <img src="<?php echo $qrPlaceholder; ?>" alt="QR chuyển khoản" class="img-fluid" style="max-width:220px;border-radius:14px;border:1px solid #d5ebf1;padding:8px;background:#fff;">
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <section class="os-card mb-4">
                <div class="os-card-head">
                    <h2 class="os-card-title"><i class="bi bi-box-seam me-2"></i>Sản phẩm trong đơn (<?php echo count($orderItems); ?>)</h2>
                    <span class="os-badge-soft <?php echo htmlspecialchars($statusMeta['class']); ?>"><i class="bi bi-activity"></i><?php echo htmlspecialchars($statusMeta['text']); ?></span>
                </div>
                <div class="os-card-body">
                    <div class="os-items-list">
                        <?php foreach ($orderItems as $item): ?>
                            <?php
                            $images = json_decode($item['images'] ?? '[]', true);
                            $firstImage = !empty($images) ? resolveDonationImageUrl((string)$images[0]) : 'uploads/donations/placeholder-default.svg';
                            $qty = (int)($item['quantity'] ?? 0);
                            $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : (isset($item['price']) ? (float)$item['price'] : 0);
                            $lineTotal = isset($item['total_price']) ? (float)$item['total_price'] : (isset($item['subtotal']) ? (float)$item['subtotal'] : ($unitPrice * $qty));
                            ?>
                            <article class="os-item">
                                <img src="<?php echo htmlspecialchars($firstImage); ?>" alt="<?php echo htmlspecialchars((string)$item['item_name']); ?>" onerror="this.src='uploads/donations/placeholder-default.svg'">
                                <div>
                                    <h3 class="os-item-name"><?php echo htmlspecialchars((string)$item['item_name']); ?></h3>
                                    <p class="os-item-sub">
                                        Số lượng: <?php echo $qty; ?> | Đơn vị: <?php echo htmlspecialchars((string)($item['unit'] ?? 'Cái')); ?>
                                    </p>
                                </div>
                                <div class="os-item-price">
                                    <div class="unit"><?php echo $unitPrice > 0 ? number_format($unitPrice) . ' VNĐ' : 'Miễn phí'; ?></div>
                                    <p class="line"><?php echo $lineTotal > 0 ? number_format($lineTotal) . ' VNĐ' : 'Miễn phí'; ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="os-card mb-4">
                <div class="os-card-head">
                    <h2 class="os-card-title"><i class="bi bi-stars me-2"></i>Các bước tiếp theo</h2>
                </div>
                <div class="os-card-body">
                    <div class="os-steps">
                        <div class="os-step">
                            <i class="bi bi-telephone-forward"></i>
                            <h6>Xác nhận</h6>
                            <p>Hệ thống/nhân viên xác nhận đơn trong thời gian sớm nhất.</p>
                        </div>
                        <div class="os-step">
                            <i class="bi bi-box2-heart"></i>
                            <h6>Chuẩn bị hàng</h6>
                            <p>Kho đóng gói và bàn giao cho vận chuyển.</p>
                        </div>
                        <div class="os-step">
                            <i class="bi bi-house-check"></i>
                            <h6>Giao hàng</h6>
                            <p>Theo dõi trạng thái ở trang theo dõi đơn hàng.</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="os-card mb-4 sticky-top" style="top: 96px;">
                <div class="os-card-head">
                    <h2 class="os-card-title"><i class="bi bi-receipt-cutoff me-2"></i>Tóm tắt đơn hàng</h2>
                </div>
                <div class="os-card-body">
                    <div class="os-summary-grid">
                        <div class="os-mini">
                            <div class="os-mini-label">Người nhận</div>
                            <div class="os-mini-value"><?php echo htmlspecialchars((string)$shippingName); ?></div>
                        </div>
                        <div class="os-mini">
                            <div class="os-mini-label">Số điện thoại</div>
                            <div class="os-mini-value"><?php echo htmlspecialchars((string)($order['shipping_phone'] ?? '')); ?></div>
                        </div>
                        <div class="os-mini" style="grid-column: 1 / -1;">
                            <div class="os-mini-label">Địa chỉ giao hàng</div>
                            <div class="os-mini-value"><?php echo htmlspecialchars((string)($order['shipping_address'] ?? '')); ?></div>
                        </div>
                        <?php if ($shippingNote !== ''): ?>
                            <div class="os-mini" style="grid-column: 1 / -1;">
                                <div class="os-mini-label">Ghi chú</div>
                                <div class="os-mini-value"><?php echo htmlspecialchars((string)$shippingNote); ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="os-mini">
                            <div class="os-mini-label">Phương thức</div>
                            <div class="os-mini-value"><?php echo htmlspecialchars($paymentMethodLabel); ?></div>
                        </div>
                        <div class="os-mini">
                            <div class="os-mini-label">Trạng thái</div>
                            <div class="os-mini-value"><span class="os-badge-soft <?php echo htmlspecialchars($statusMeta['class']); ?>"><?php echo htmlspecialchars($statusMeta['text']); ?></span></div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted">Tổng tiền</span>
                        <strong style="font-size:1.15rem;color:#0f7d63;">
                            <?php echo $order['total_amount'] > 0 ? number_format((float)$order['total_amount']) . ' VNĐ' : 'Miễn phí'; ?>
                        </strong>
                    </div>

                    <a href="order-detail.php?id=<?php echo (int)$order_id; ?>" class="btn btn-outline-info w-100 mt-2">
                        <i class="bi bi-journal-text me-1"></i>Xem chi tiết đơn hàng
                    </a>
                </div>
            </section>

            <section class="os-card">
                <div class="os-card-head">
                    <h2 class="os-card-title"><i class="bi bi-headset me-2"></i>Hỗ trợ khách hàng</h2>
                </div>
                <div class="os-card-body">
                    <p class="text-muted mb-2">Nếu cần hỗ trợ về đơn hàng, vui lòng liên hệ:</p>
                    <p class="mb-1"><i class="bi bi-telephone me-2" style="color:var(--os-brand-700);"></i><strong>Hotline:</strong> 1900 1234</p>
                    <p class="mb-1"><i class="bi bi-envelope me-2" style="color:var(--os-brand-700);"></i><strong>Email:</strong> support@goodwillvietnam.org</p>
                    <p class="mb-0"><i class="bi bi-clock me-2" style="color:var(--os-brand-700);"></i><strong>Giờ làm việc:</strong> 8:00 - 22:00</p>
                </div>
            </section>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
