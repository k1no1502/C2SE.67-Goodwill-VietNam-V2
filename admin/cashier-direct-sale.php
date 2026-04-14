<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();
if (!isAdmin() && getStaffPanelKey() !== 'cashier') {
    header('Location: ../staff-panel.php');
    exit();
}

// 1. Handle MoMo return URL result for POS (before displaying anything)
if (isset($_GET['momo_return']) && $_GET['momo_return'] === '1' && isset($_GET['pos_order_id'])) {
    $posOrderId = (int)$_GET['pos_order_id'];
    $resultCode = (string)($_GET['resultCode'] ?? '');

    $order = Database::fetch('SELECT * FROM orders WHERE order_id = ?', [$posOrderId]);
    if ($order) {
        if ($resultCode === '0') {
            Database::execute('UPDATE orders SET payment_status = ?, status = ?, updated_at = NOW() WHERE order_id = ? AND payment_status = ?', ['paid', 'delivered', $posOrderId, 'pending']);
            // Generate receipt to show instantly
            $items = Database::fetchAll('SELECT item_name, quantity, subtotal FROM order_items WHERE order_id = ?', [$posOrderId]);
            $_SESSION['pos_receipt'] = [
                'order_id'       => $posOrderId,
                'created_at'     => date('d/m/Y H:i:s', strtotime($order['created_at'])),
                'customer_name'  => $order['shipping_name'] ?? '',
                'payment_method' => 'momo',
                'items'          => $items,
                'total_amount'   => $order['total_amount'],
            ];
        } else {
            $_SESSION['pos_error'] = 'Giao dịch MoMo bị hủy hoặc lỗi (Mã ' . $resultCode . ')';
            Database::execute('UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ? AND payment_status = ?', ['cancelled', $posOrderId, 'pending']);
        }
    } else {
        $_SESSION['pos_error'] = 'Không tìm thấy thông tin đơn hàng POS (Mã ' . $posOrderId . ')';
    }

    header('Location: cashier-direct-sale.php');
    exit;
}

$pageTitle  = 'Kho hàng mã vạch';
$panelType  = 'cashier';

if (isset($_SESSION['pos_receipt'])) {
    $receipt = $_SESSION['pos_receipt'];
    unset($_SESSION['pos_receipt']);
} else if (!isset($receipt)) {
    $receipt = null;
}

if (isset($_SESSION['pos_error'])) {
    $error = $_SESSION['pos_error'];
    unset($_SESSION['pos_error']);
} else {
    $error = isset($_GET['error']) ? trim($_GET['error']) : '';
}

if (isset($_GET['stock_snapshot']) && $_GET['stock_snapshot'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $stockRows = Database::fetchAll(
            "SELECT i.item_id,
                    i.status,
                    i.is_for_sale,
                    GREATEST(
                        i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id), 0),
                        0
                    ) AS available_quantity
             FROM inventory i
             WHERE i.is_for_sale = 1"
        );

        $snapshot = [];
        foreach ($stockRows as $row) {
            $snapshot[(int)$row['item_id']] = [
                'available_quantity' => (int)($row['available_quantity'] ?? 0),
                'status' => (string)($row['status'] ?? ''),
                'is_for_sale' => (int)($row['is_for_sale'] ?? 0),
            ];
        }

        echo json_encode(['success' => true, 'data' => $snapshot], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Cannot load stock snapshot']);
    }
    exit();
}

$products = Database::fetchAll(
    "SELECT i.item_id, i.name, i.quantity, i.sale_price, i.price_type,
            i.images, c.name AS category_name,
            GREATEST(
                i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id), 0),
                0
            ) AS available_quantity
     FROM inventory i
     LEFT JOIN categories c ON i.category_id = c.category_id
     WHERE i.status = 'available' AND i.is_for_sale = 1
     HAVING available_quantity > 0
     ORDER BY c.name, i.name"
);

foreach ($products as &$p) {
    $imgs = json_decode((string)($p['images'] ?? '[]'), true);
    $p['img_url'] = !empty($imgs)
        ? resolveDonationImageUrl((string)$imgs[0])
        : 'uploads/donations/placeholder-default.svg';
}
unset($p);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_json'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Yêu cầu không hợp lệ.';
    } else {
        $cart          = json_decode((string)($_POST['cart_json'] ?? '[]'), true);
        $paymentMethod = (string)($_POST['payment_method'] ?? 'cash');
        $customerName  = trim((string)($_POST['customer_name'] ?? ''));

        if (!is_array($cart) || empty($cart)) {
            $error = 'Vui lòng chọn ít nhất 1 sản phẩm.';
        } elseif (!in_array($paymentMethod, ['cash', 'bank_transfer', 'momo'], true)) {
            $error = 'Phương thức thanh toán không hợp lệ.';
        } else {
            try {
                Database::beginTransaction();

                $lines       = [];
                $totalAmount = 0;
                $totalItems  = 0;

                foreach ($cart as $line) {
                    $itemId = (int)($line['item_id'] ?? 0);
                    $qty    = (int)($line['quantity'] ?? 0);
                    if ($itemId <= 0 || $qty <= 0) {
                        continue;
                    }

                    $item = Database::fetch(
                        "SELECT item_id, name, quantity, sale_price, status, is_for_sale
                         FROM inventory WHERE item_id = ? FOR UPDATE",
                        [$itemId]
                    );

                    $reservedByCart = Database::fetch(
                        "SELECT COALESCE(SUM(quantity), 0) AS q FROM cart WHERE item_id = ?",
                        [$itemId]
                    );
                    $availableQuantity = $item
                        ? max(0, (int)($item['quantity'] ?? 0) - (int)($reservedByCart['q'] ?? 0))
                        : 0;

                    if (!$item
                        || $availableQuantity < $qty
                        || $item['status'] !== 'available'
                        || (int)$item['is_for_sale'] !== 1
                    ) {
                        throw new Exception('Sản phẩm "' . htmlspecialchars((string)($item['name'] ?? '#' . $itemId), ENT_QUOTES, 'UTF-8') . '" không đủ số lượng.');
                    }

                    $price    = (float)($item['sale_price'] ?? 0);
                    $subtotal = $price * $qty;
                    $totalAmount += $subtotal;
                    $totalItems  += $qty;

                    $lines[] = [
                        'item_id'   => (int)$item['item_id'],
                        'item_name' => (string)$item['name'],
                        'quantity'  => $qty,
                        'price'     => $price,
                        'subtotal'  => $subtotal,
                    ];
                }

                if (empty($lines)) {
                    throw new Exception('Không có dữ liệu giỏ hàng hợp lệ.');
                }

                $payment = $paymentMethod === 'cash' ? 'cod' : ($paymentMethod === 'momo' ? 'momo' : 'bank_transfer');
                $paymentStatus = $paymentMethod === 'momo' ? 'pending' : 'paid';

                Database::execute(
                    "INSERT INTO orders
                        (user_id, shipping_name, shipping_address, shipping_method,
                         shipping_note, payment_method, payment_status, total_amount,
                         total_items, status, created_at, updated_at)
                     VALUES (?, ?, ?, 'pickup', ?, ?, ?, ?, ?, 'delivered', NOW(), NOW())",
                    [
                        (int)($_SESSION['user_id'] ?? 0),
                        $customerName !== '' ? $customerName : 'Khách mua tại quầy',
                        'Mua tại quầy Goodwill Vietnam',
                        'Bán trực tiếp tại quầy thu ngân',
                        $payment,
                        $paymentStatus,
                        $totalAmount,
                        $totalItems,
                    ]
                );

                $orderId = (int)Database::lastInsertId();

                foreach ($lines as $line) {
                    Database::execute(
                        "INSERT INTO order_items (order_id, item_id, item_name, quantity, price, price_type, subtotal)
                         VALUES (?, ?, ?, ?, ?, 'normal', ?)",
                        [$orderId, $line['item_id'], $line['item_name'], $line['quantity'], $line['price'], $line['subtotal']]
                    );

                    Database::execute(
                        "UPDATE inventory
                         SET quantity    = quantity - ?,
                             status      = CASE WHEN quantity - ? <= 0 THEN 'sold' ELSE status END,
                             is_for_sale = CASE WHEN quantity - ? <= 0 THEN 0 ELSE is_for_sale END,
                             updated_at  = NOW()
                         WHERE item_id = ?",
                        [$line['quantity'], $line['quantity'], $line['quantity'], $line['item_id']]
                    );
                }

                logActivity((int)($_SESSION['user_id'] ?? 0), 'cashier_direct_sale', 'Bill #' . $orderId);
                Database::commit();

                if ($paymentMethod === 'momo') {
                    $paymentConfigPath = __DIR__ . '/../config/payment.php';
                    $paymentConfig = file_exists($paymentConfigPath) ? require $paymentConfigPath : [];
                    $momoCfg = $paymentConfig['momo'] ?? [];
                    $requiredFields = ['partner_code', 'access_key', 'secret_key', 'endpoint'];
                    foreach ($requiredFields as $field) {
                        if (trim((string)($momoCfg[$field] ?? '')) === '') {
                            throw new Exception('Thiếu cấu hình MoMo: ' . $field);
                        }
                    }

                    $momoOrderId = 'POS' . $orderId . '_' . time();
                    $requestId = $momoOrderId;
                    $requestType = trim((string)($momoCfg['request_type'] ?? 'captureWallet'));
                    
                    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                    $scheme = $isHttps ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $baseUrl = $scheme . '://' . $host;
                    
                    $redirectUrl = $baseUrl . '/admin/cashier-direct-sale.php?momo_return=1&pos_order_id=' . $orderId;
                    $ipnUrl = $baseUrl . '/api/momo_checkout_notify.php';
                    
                    $extraData = base64_encode(json_encode([
                        'order_id' => $orderId,
                        'user_id' => (int)($_SESSION['user_id'] ?? 0),
                        'is_pos' => 1
                    ], JSON_UNESCAPED_UNICODE));
                    
                    $orderInfo = 'Thanh toán đơn hàng #' . $orderId . ' tại quầy';
                    $amountStr = (string)((int)round($totalAmount));
                    
                    $rawHash = 'accessKey=' . $momoCfg['access_key']
                        . '&amount=' . $amountStr
                        . '&extraData=' . $extraData
                        . '&ipnUrl=' . $ipnUrl
                        . '&orderId=' . $momoOrderId
                        . '&orderInfo=' . $orderInfo
                        . '&partnerCode=' . $momoCfg['partner_code']
                        . '&redirectUrl=' . $redirectUrl
                        . '&requestId=' . $requestId
                        . '&requestType=' . $requestType;
                    
                    $signature = hash_hmac('sha256', $rawHash, $momoCfg['secret_key']);
                    
                    $payload = [
                        'partnerCode' => $momoCfg['partner_code'],
                        'accessKey' => $momoCfg['access_key'],
                        'requestId' => $requestId,
                        'amount' => $amountStr,
                        'orderId' => $momoOrderId,
                        'orderInfo' => $orderInfo,
                        'redirectUrl' => $redirectUrl,
                        'ipnUrl' => $ipnUrl,
                        'extraData' => $extraData,
                        'requestType' => $requestType,
                        'lang' => 'vi',
                        'partnerName' => trim((string)($momoCfg['partner_name'] ?? 'Goodwill Vietnam')),
                        'storeId' => trim((string)($momoCfg['store_id'] ?? 'GoodwillStore')),
                        'signature' => $signature,
                    ];
                    
                    $ch = curl_init($momoCfg['endpoint']);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
                        CURLOPT_TIMEOUT => 30,
                    ]);
                    $response = curl_exec($ch);
                    curl_close($ch);
                    
                    $decoded = $response ? json_decode($response, true) : null;
                    $payUrl = trim((string)($decoded['payUrl'] ?? ''));
                    
                    if (!$response || $payUrl === '' || (string)($decoded['resultCode'] ?? '') !== '0') {
                        throw new Exception('Lỗi cổng MoMo: ' . ($decoded['message'] ?? 'Không thể kết nối'));
                    }
                    
                    header('Location: ' . $payUrl);
                    exit;
                }

                $receipt = [
                    'order_id'       => $orderId,
                    'created_at'     => date('d/m/Y H:i:s'),
                    'customer_name'  => $customerName !== '' ? $customerName : 'Khách mua tại quầy',
                    'payment_method' => $paymentMethod,
                    'items'          => $lines,
                    'total_amount'   => $totalAmount,
                ];
            } catch (Exception $e) {
                Database::rollback();
                $error = 'Không thể thanh toán: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Goodwill Vietnam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        /* ── Root ── */
        :root {
            --teal:     #0b728c;
            --teal-dk:  #084f63;
            --teal-lt:  #e3f4f9;
            --border:   #d7edf3;
            --bg:       #f0f4f8;
            --text:     #0f172a;
            --muted:    #64748b;
            --subtle:   #94a3b8;
        }
        body { background: var(--bg); }
        .admin-content { padding: .8rem 1rem 2rem; }

        /* ── Page header ── */
        .pos-topbar {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: .9rem;
        }
        .pos-topbar-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--teal), var(--teal-dk));
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(11,114,140,.35);
        }
        .pos-topbar-icon i { color:#fff; font-size: 1.25rem; }
        .pos-topbar-text h2 {
            font-size: 1.25rem; font-weight: 800;
            color: var(--text); margin: 0;
        }
        .pos-topbar-text p { margin: 0; font-size: .78rem; color: var(--muted); }

        /* ── Left column wrapper ── */
        .pos-left { display: flex; flex-direction: column; gap: .75rem; }

        /* ── Search card ── */
        .search-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(8,74,92,.06);
            padding: .7rem .85rem;
            position: relative;
        }
        .search-card .srch-ic {
            position: absolute; left: 1.55rem; top: 50%;
            transform: translateY(-50%);
            color: var(--subtle); font-size: .9rem; pointer-events: none;
        }
        .search-card input {
            width: 100%;
            padding: .5rem .75rem .5rem 2.1rem;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: .88rem;
            background: #f8fbfc;
            transition: border-color .18s, box-shadow .18s;
            font-family: inherit;
        }
        .search-card input:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 3px rgba(11,114,140,.12);
            outline: none; background: #fff;
        }

        /* ── Product grid card ── */
        .products-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(8,74,92,.06);
            overflow: hidden;
        }
        .products-card-head {
            padding: .65rem 1rem;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
        }
        .products-card-head h6 {
            margin: 0; font-weight: 700; font-size: .9rem; color: var(--teal);
        }
        .products-card-body {
            padding: .75rem;
        }

        /* ── Product grid ── */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: .65rem;
            max-height: calc(100vh - 260px);
            overflow-y: auto;
            padding-right: 2px;
        }
        .product-grid::-webkit-scrollbar { width: 4px; }
        .product-grid::-webkit-scrollbar-thumb { background: #c8dde6; border-radius: 4px; }

        .product-card {
            border: 1.5px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            background: #fff;
            display: flex; flex-direction: column;
            transition: transform .18s cubic-bezier(.34,1.56,.64,1),
                        box-shadow .18s ease, border-color .18s;
            user-select: none;
        }
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 22px rgba(11,114,140,.16);
            border-color: var(--teal);
        }
        .product-card:active { transform: scale(.96); }

        .product-card .p-img {
            width: 100%; aspect-ratio: 1/1;
            object-fit: cover; background: #f1f5f9;
            transition: transform .22s;
        }
        .product-card:hover .p-img { transform: scale(1.04); }

        .product-card .p-body {
            padding: .5rem .6rem .55rem;
            flex: 1; display: flex; flex-direction: column;
        }
        .p-name {
            font-size: .76rem; font-weight: 700; color: var(--text);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            margin-bottom: .08rem;
        }
        .p-cat  { font-size: .66rem; color: var(--subtle); margin-bottom: .25rem; }
        .p-price { font-size: .86rem; font-weight: 800; color: var(--teal); margin-top: auto; }
        .p-stock { font-size: .65rem; color: var(--subtle); margin: .15rem 0 .4rem; }

        .btn-add {
            display: block; width: 100%;
            background: linear-gradient(135deg, var(--teal), var(--teal-dk));
            color: #fff; border: none; border-radius: 7px;
            padding: .28rem 0; font-size: .74rem; font-weight: 600;
            cursor: pointer; font-family: inherit;
            transition: opacity .15s, transform .12s;
        }
        .btn-add:hover  { opacity: .88; }
        .btn-add:active { transform: scale(.96); }

        .barcode-box {
            border: 1px dashed #c7dfe8;
            border-radius: 8px;
            background: #f8fcfe;
            padding: .3rem .35rem;
            margin: .28rem 0 .45rem;
            text-align: center;
        }
        .barcode-svg {
            width: 100%;
            max-width: 150px;
            height: 44px;
        }
        .barcode-code {
            display: block;
            font-size: .67rem;
            color: var(--muted);
            margin-top: .12rem;
            font-weight: 600;
            letter-spacing: .4px;
        }
        .btn-download-barcode {
            width: 100%;
            border: 1px solid #cde4ee;
            background: #fff;
            color: var(--teal);
            border-radius: 8px;
            padding: .3rem 0;
            font-size: .72rem;
            font-weight: 700;
            transition: all .15s;
        }
        .btn-download-barcode:hover {
            background: #e9f6fb;
            border-color: #9fc9d8;
        }

        .product-card.in-cart {
            border-color: var(--teal);
            background: linear-gradient(180deg, #f0fbfe 0%, #fff 100%);
        }
        .product-card.in-cart .btn-add {
            background: linear-gradient(135deg, #059669, #047857);
        }
        .product-card.hidden { display: none; }

        /* ── Right sidebar ── */
        .pos-sidebar {
            display: flex; flex-direction: column; gap: .6rem;
            position: sticky; top: .75rem;
        }

        /* Shared sidebar card */
        .sb-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(8,74,92,.06);
            overflow: hidden;
        }
        .sb-card-head {
            padding: .6rem .9rem;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: .5rem;
        }
        .sb-card-head h6 {
            margin: 0; font-weight: 700; font-size: .85rem; color: var(--teal);
        }
        .sb-badge {
            margin-left: auto;
            background: var(--teal); color: #fff;
            border-radius: 99px; padding: .05rem .45rem;
            font-size: .68rem; font-weight: 700;
        }

        /* Customer input */
        .sb-customer-body { padding: .6rem .9rem; }
        .sb-customer-body input {
            width: 100%; border: 1.5px solid var(--border);
            border-radius: 9px; padding: .4rem .7rem;
            font-size: .82rem; font-family: inherit;
            transition: border-color .18s, box-shadow .18s;
        }
        .sb-customer-body input:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 3px rgba(11,114,140,.1);
            outline: none;
        }

        .sb-scan-body {
            padding: .6rem .9rem .8rem;
        }
        .scan-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .45rem;
            margin-bottom: .55rem;
        }
        .scan-btn {
            border: 1px solid #cde4ee;
            background: #fff;
            border-radius: 8px;
            padding: .42rem .45rem;
            font-size: .76rem;
            font-weight: 700;
            color: var(--teal);
        }
        .scan-btn.primary {
            background: linear-gradient(135deg, var(--teal), var(--teal-dk));
            color: #fff;
            border-color: transparent;
        }
        .scan-btn:disabled {
            opacity: .55;
            cursor: not-allowed;
        }
        .scan-manual {
            display: flex;
            gap: .4rem;
            margin-bottom: .5rem;
        }
        .scan-manual input {
            flex: 1;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            padding: .35rem .6rem;
            font-size: .82rem;
        }
        .scan-manual button {
            border: 1px solid #cde4ee;
            border-radius: 8px;
            background: #fff;
            color: var(--teal);
            font-size: .76rem;
            font-weight: 700;
            padding: 0 .7rem;
        }
        /* ── Scanner reader + overlay ── */
        .scan-reader-wrap {
            position: relative;
            display: none;
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            background: #f4fbfe;
        }
        .scan-reader-wrap.scanning { display: block; }
        #scannerReader {
            display: block;
            min-height: 160px;
            width: 100%;
        }
        /* Smooth zoom on video when camera is active */
        #scannerReader video {
            transition: transform .35s ease;
        }
        #scannerReader.barcode-found video {
            transform: scale(1.05);
        }

        /* Overlay painted on top of video */
        .scan-overlay {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 20;
            border-radius: 10px;
            overflow: visible;
        }

        /* Dark side vignette to focus on centre */
        .scan-overlay::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 55% at 50% 50%,
                transparent 45%, rgba(0,0,0,.32) 100%);
            border-radius: 10px;
        }

        /* Animated laser line */
        .scan-laser {
            position: absolute;
            left: 12%;
            right: 12%;
            height: 2px;
            top: 35%;
            background: linear-gradient(90deg,
                transparent 0%,
                rgba(0,220,255,.85) 15%,
                #00dcff 50%,
                rgba(0,220,255,.85) 85%,
                transparent 100%);
            box-shadow: 0 0 7px 2px rgba(0,220,255,.5);
            border-radius: 2px;
            animation: laserSweep 2s ease-in-out infinite;
        }

        @keyframes laserSweep {
            0%   { top: 28%; opacity: .75; }
            50%  { top: 62%; opacity: 1;   }
            100% { top: 28%; opacity: .75; }
        }

        /* Corner bracket shared base */
        .scan-corner {
            position: absolute;
            width: 22px;
            height: 22px;
            transition: border-color .2s;
        }
        .scan-corner::before,
        .scan-corner::after {
            content: '';
            position: absolute;
            background: #00dcff;
            box-shadow: 0 0 6px rgba(0,220,255,.7);
            border-radius: 1.5px;
            transition: background .2s, box-shadow .2s;
        }
        .scan-corner::before { width: 22px; height: 3px; }
        .scan-corner::after  { width: 3px;  height: 22px; }

        /* Positions: top/bottom 25% inset, left/right 10% inset */
        .scan-corner.tl { top:24%; left:9%; }
        .scan-corner.tl::before { top:0;    left:0; }
        .scan-corner.tl::after  { top:0;    left:0; }

        .scan-corner.tr { top:24%; right:9%; }
        .scan-corner.tr::before { top:0;    right:0; left:auto; }
        .scan-corner.tr::after  { top:0;    right:0; left:auto; }

        .scan-corner.bl { bottom:24%; left:9%; }
        .scan-corner.bl::before { bottom:0; left:0; top:auto; }
        .scan-corner.bl::after  { bottom:0; left:0; top:auto; }

        .scan-corner.br { bottom:24%; right:9%; }
        .scan-corner.br::before { bottom:0; right:0; left:auto; top:auto; }
        .scan-corner.br::after  { bottom:0; right:0; left:auto; top:auto; }

        /* Corner pulse animation */
        .scan-reader-wrap.scanning .scan-corner::before,
        .scan-reader-wrap.scanning .scan-corner::after {
            animation: cornerPulse 2.2s ease-in-out infinite;
        }
        @keyframes cornerPulse {
            0%,100% { background: #00dcff; box-shadow: 0 0 5px rgba(0,220,255,.6); }
            50%      { background: #52f2ff; box-shadow: 0 0 10px rgba(82,242,255,.9); }
        }

        /* On barcode found: turn corners green */
        .scan-reader-wrap.barcode-found .scan-corner::before,
        .scan-reader-wrap.barcode-found .scan-corner::after {
            background: #22c55e !important;
            box-shadow: 0 0 10px rgba(34,197,94,.9) !important;
            animation: none;
        }
        .scan-reader-wrap.barcode-found .scan-laser {
            background: linear-gradient(90deg,
                transparent 0%, rgba(34,197,94,.9) 15%,
                #22c55e 50%, rgba(34,197,94,.9) 85%, transparent 100%) !important;
            box-shadow: 0 0 10px 3px rgba(34,197,94,.55) !important;
            animation: none;
        }

        /* Full-screen green flash */
        .scan-success-flash {
            position: absolute;
            inset: 0;
            border-radius: 10px;
            background: transparent;
            pointer-events: none;
        }
        .scan-success-flash.active {
            animation: greenFlash .45s ease-out forwards;
        }
        @keyframes greenFlash {
            0%   { background: rgba(34,197,94,.38); }
            100% { background: transparent; }
        }

        /* Badge pop-up on successful scan */
        .scan-badge-pop {
            position: absolute;
            bottom: 12%;
            left: 50%;
            transform: translateX(-50%) scale(.7);
            background: rgba(22,163,74,.92);
            color: #fff;
            font-size: .78rem;
            font-weight: 700;
            border-radius: 20px;
            padding: .28rem .9rem;
            pointer-events: none;
            opacity: 0;
            white-space: nowrap;
            box-shadow: 0 4px 16px rgba(22,163,74,.35);
            z-index: 30;
        }
        .scan-badge-pop.active {
            animation: badgePop .65s ease-out forwards;
        }
        @keyframes badgePop {
            0%   { opacity: 1; transform: translateX(-50%) scale(1);    bottom:12%; }
            65%  { opacity: 1; transform: translateX(-50%) scale(1.06); bottom:18%; }
            100% { opacity: 0; transform: translateX(-50%) scale(.9);   bottom:22%; }
        }

        .scan-hint {
            font-size: .74rem;
            color: var(--muted);
            margin-top: .45rem;
        }
        .scan-status {
            font-size: .75rem;
            color: #0b728c;
            background: #eef8fc;
            border-radius: 7px;
            padding: .3rem .5rem;
            margin-top: .45rem;
            min-height: 28px;
        }

        /* Cart items */
        .sb-cart-body {
            max-height: 240px;
            overflow-y: auto;
            padding: .4rem .85rem;
        }
        .sb-cart-body::-webkit-scrollbar { width: 4px; }
        .sb-cart-body::-webkit-scrollbar-thumb { background: #c8dde6; border-radius: 4px; }

        .cart-empty {
            text-align: center; padding: 1.4rem .5rem; color: var(--subtle);
        }
        .cart-empty i { font-size: 2.2rem; display: block; margin-bottom: .35rem; opacity: .55; }
        .cart-empty span { font-size: .76rem; }

        .cart-item {
            display: flex; align-items: center; gap: .4rem;
            padding: .4rem 0;
            border-bottom: 1px dashed #e2eef2;
            animation: fadeSlide .22s ease;
        }
        .cart-item:last-child { border-bottom: none; }
        @keyframes fadeSlide {
            from { opacity: 0; transform: translateX(12px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .ci-info { flex: 1; min-width: 0; }
        .ci-name { font-size: .76rem; font-weight: 700; color: var(--text);
                   white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ci-unit { font-size: .66rem; color: var(--subtle); }
        .ci-sub  { font-size: .78rem; font-weight: 800; color: var(--teal); white-space: nowrap; }

        .qty-ctrl { display: flex; align-items: center; gap: .2rem; }
        .qty-btn {
            width: 20px; height: 20px; border-radius: 5px;
            border: 1.5px solid #cfe4ec; background: #f5fbfd;
            color: var(--teal); font-weight: 700; font-size: .8rem;
            cursor: pointer; display: flex; align-items: center;
            justify-content: center; padding: 0; line-height: 1;
            transition: background .14s, border-color .14s;
        }
        .qty-btn:hover { background: #ddf0f6; border-color: var(--teal); }
        .qty-num { font-size: .8rem; font-weight: 700; min-width: 18px; text-align: center; }
        .ci-remove {
            background: none; border: none; color: #e74c3c;
            font-size: .74rem; cursor: pointer; padding: 0;
            transition: color .14s; flex-shrink: 0;
        }
        .ci-remove:hover { color: #c0392b; }

        /* Payment methods */
        .sb-pay-body { padding: .6rem .9rem; }
        .pay-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .4rem; }
        .pay-opt {
            border: 2px solid var(--border);
            border-radius: 10px; background: #f8fbfc;
            padding: .5rem .3rem; text-align: center;
            cursor: pointer; transition: all .18s cubic-bezier(.34,1.56,.64,1);
            font-family: inherit;
        }
        .pay-opt:hover { border-color: var(--teal); background: var(--teal-lt); transform: translateY(-1px); }
        .pay-opt.active {
            border-color: var(--teal); background: var(--teal-lt);
            box-shadow: 0 0 0 3px rgba(11,114,140,.15);
            transform: translateY(-1px);
        }
        .pay-opt i { font-size: 1.05rem; display: block; color: var(--teal); margin-bottom: .12rem; }
        .pay-opt span { font-size: .7rem; font-weight: 700; color: var(--text); }

        /* Totals */
        .sb-totals-body { padding: .5rem .9rem .6rem; }
        .total-row {
            display: flex; justify-content: space-between; align-items: center;
            font-size: .8rem; padding: .2rem 0;
        }
        .total-row .lbl { color: var(--muted); }
        .total-row .val { font-weight: 600; color: var(--text); }
        .total-row.grand { padding-top: .4rem; border-top: 1.5px solid var(--border); margin-top: .25rem; }
        .total-row.grand .lbl { font-weight: 700; color: var(--text); font-size: .88rem; }
        .total-row.grand .val { font-size: 1.1rem; font-weight: 800; color: var(--teal); }

        /* Checkout button */
        .sb-checkout-body { padding: .5rem .9rem .8rem; }
        .btn-checkout {
            width: 100%;
            background: linear-gradient(135deg, var(--teal), var(--teal-dk));
            color: #fff; border: none; border-radius: 11px;
            padding: .72rem; font-size: .92rem; font-weight: 700;
            cursor: pointer; font-family: inherit;
            display: flex; align-items: center; justify-content: center; gap: .4rem;
            transition: opacity .15s, transform .15s, box-shadow .15s;
            box-shadow: 0 4px 14px rgba(11,114,140,.35);
        }
        .btn-checkout:hover:not(:disabled) {
            opacity: .92; transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(11,114,140,.45);
        }
        .btn-checkout:active:not(:disabled) { transform: scale(.98); }
        .btn-checkout:disabled { background: #cbd5e1; box-shadow: none; cursor: not-allowed; }

        /* ── Receipt Overlay ── */
        .receipt-overlay {
            position: fixed; inset: 0;
            background: rgba(8,20,35,.6);
            z-index: 9999; display: flex;
            align-items: center; justify-content: center;
            padding: 1rem; backdrop-filter: blur(3px);
            animation: fadeIn .2s ease;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .receipt-card {
            background: #fff;
            border-radius: 22px;
            width: 100%; max-width: 440px;
            max-height: 94vh; overflow-y: auto;
            box-shadow: 0 32px 64px rgba(0,0,0,.32);
            animation: popUp .28s cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes popUp {
            from { transform: scale(.88) translateY(20px); opacity: 0; }
            to   { transform: scale(1)   translateY(0);    opacity: 1; }
        }
        .receipt-head {
            background: linear-gradient(135deg, var(--teal), var(--teal-dk));
            color: #fff; border-radius: 22px 22px 0 0;
            padding: 1.4rem 1.5rem 1.1rem; text-align: center;
        }
        .receipt-ok-icon {
            font-size: 2.8rem; display: block; margin-bottom: .3rem;
            animation: bounceIn .4s .1s both;
        }
        @keyframes bounceIn {
            from { transform: scale(0); opacity: 0; }
            60%  { transform: scale(1.2); }
            to   { transform: scale(1); opacity: 1; }
        }
        .receipt-head h4 { margin: 0 0 .2rem; font-weight: 800; font-size: 1.2rem; }
        .receipt-head p  { margin: 0; opacity: .8; font-size: .8rem; }

        .receipt-body { padding: 1.1rem 1.4rem .6rem; }
        .r-meta-row {
            display: flex; justify-content: space-between;
            font-size: .82rem; padding: .22rem 0; color: var(--muted);
        }
        .r-meta-row strong { color: var(--text); }
        .r-dash {
            border: none; border-top: 1.5px dashed #cce5ee; margin: .65rem 0;
        }
        .r-item {
            display: flex; justify-content: space-between;
            align-items: baseline; gap: .5rem;
            padding: .28rem 0; font-size: .82rem;
        }
        .r-item-name { flex: 1; color: var(--text); }
        .r-item-qty  { color: var(--subtle); white-space: nowrap; font-size: .76rem; }
        .r-item-amt  { font-weight: 700; color: var(--teal); white-space: nowrap; }
        .r-total {
            display: flex; justify-content: space-between;
            font-size: 1.05rem; font-weight: 800; color: var(--teal); padding-top: .4rem;
        }
        .receipt-actions {
            display: flex; gap: .6rem; padding: .8rem 1.4rem 1.4rem;
        }
        .btn-bill-print {
            flex: 1;
            background: linear-gradient(135deg, var(--teal), var(--teal-dk));
            color: #fff; border: none; border-radius: 11px;
            padding: .68rem; font-weight: 700; font-size: .88rem;
            cursor: pointer; font-family: inherit;
            display: flex; align-items: center; justify-content: center; gap: .4rem;
            transition: opacity .15s;
        }
        .btn-bill-print:hover { opacity: .9; }
        .btn-new-sale {
            flex: 1;
            background: #f1f5f9; color: var(--text);
            border: 1.5px solid var(--border); border-radius: 11px;
            padding: .68rem; font-weight: 700; font-size: .88rem;
            text-decoration: none; text-align: center;
            display: flex; align-items: center; justify-content: center; gap: .4rem;
            transition: background .15s;
        }
        .btn-new-sale:hover { background: #e2eaf0; color: var(--text); }

        /* ── Zoom 100% tuning ── */
        .admin-content {
            padding: 1rem 1.25rem 2.2rem;
        }
        .pos-topbar {
            gap: .9rem;
            margin-bottom: 1.05rem;
        }
        .pos-topbar-icon {
            width: 50px;
            height: 50px;
        }
        .pos-topbar-icon i {
            font-size: 1.35rem;
        }
        .pos-topbar-text h2 {
            font-size: 1.65rem;
            line-height: 1.2;
        }
        .pos-topbar-text p {
            font-size: .95rem;
        }

        .search-card {
            padding: .9rem 1rem;
            border-radius: 16px;
        }
        .search-card input {
            min-height: 44px;
            font-size: .95rem;
        }

        .products-card {
            border-radius: 16px;
        }
        .products-card-head {
            padding: .9rem 1.1rem;
        }
        .products-card-head h6 {
            font-size: 1.05rem;
        }
        .products-card-body {
            padding: .95rem;
        }

        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(185px, 1fr));
            gap: .9rem;
            max-height: calc(100vh - 240px);
        }
        .product-card {
            border-radius: 14px;
        }
        .product-card .p-body {
            padding: .7rem .75rem .72rem;
        }
        .p-name {
            font-size: .92rem;
            margin-bottom: .2rem;
        }
        .p-cat {
            font-size: .78rem;
            margin-bottom: .35rem;
        }
        .p-price {
            font-size: 1rem;
        }
        .p-stock {
            font-size: .76rem;
            margin: .25rem 0 .5rem;
        }
        .btn-add {
            font-size: .84rem;
            padding: .44rem 0;
            border-radius: 9px;
        }

        .pos-sidebar {
            gap: .8rem;
            top: 1rem;
        }
        .sb-card {
            border-radius: 16px;
        }
        .sb-card-head {
            padding: .78rem 1rem;
        }
        .sb-card-head h6 {
            font-size: .96rem;
        }
        .sb-badge {
            font-size: .76rem;
            padding: .12rem .55rem;
        }
        .sb-customer-body,
        .sb-pay-body,
        .sb-totals-body,
        .sb-checkout-body {
            padding-left: 1rem;
            padding-right: 1rem;
        }
        .sb-customer-body input {
            min-height: 42px;
            font-size: .92rem;
        }

        .sb-cart-body {
            max-height: 285px;
            padding: .55rem 1rem;
        }
        .cart-empty {
            padding: 1.8rem .6rem;
        }
        .cart-empty span {
            font-size: .88rem;
        }
        .ci-name {
            font-size: .86rem;
        }
        .ci-unit,
        .r-item-qty {
            font-size: .76rem;
        }
        .ci-sub {
            font-size: .9rem;
        }
        .qty-btn {
            width: 24px;
            height: 24px;
            font-size: .9rem;
        }
        .qty-num {
            font-size: .9rem;
            min-width: 21px;
        }

        .pay-grid {
            gap: .52rem;
        }
        .pay-opt {
            padding: .65rem .35rem;
        }
        .pay-opt i {
            font-size: 1.2rem;
        }
        .pay-opt span {
            font-size: .8rem;
        }

        .total-row {
            font-size: .9rem;
            padding: .28rem 0;
        }
        .total-row.grand .lbl {
            font-size: 1rem;
        }
        .total-row.grand .val {
            font-size: 1.3rem;
        }

        .btn-checkout {
            min-height: 46px;
            font-size: .98rem;
            border-radius: 12px;
        }

        @media (max-width: 1400px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            }
        }

        @media (max-width: 1199.98px) {
            .product-grid {
                max-height: none;
            }
            .pos-sidebar {
                position: static;
            }
            .sb-cart-body {
                max-height: 260px;
            }
        }

        /* ── Print ── */
        @media print {
            @page {
                size: 80mm 297mm; /* Standard POS printer width */
                margin: 0;
            }
            body { 
                background: none !important;
                color: #000 !important;
                font-family: "Courier New", Courier, monospace, sans-serif !important;
            }
            body > *:not(.receipt-overlay) { display: none !important; }
            .receipt-overlay { 
                position: absolute !important; inset: 0 !important;
                background: none !important; padding: 2mm 5mm !important; 
                backdrop-filter: none !important;
                justify-content: flex-start !important;
                align-items: flex-start !important;
            }
            .receipt-card { 
                max-width: 72mm; width: 100% !important; margin: 0 auto;
                max-height: none !important; box-shadow: none !important; 
                border-radius: 0 !important; animation: none !important; 
                background: transparent !important;
                border: none !important;
            }
            .receipt-head { 
                background: none !important; color: #000 !important; 
                border-radius: 0 !important; border-bottom: 1.5px dashed #000 !important; 
                padding: 0 0 10px 0 !important; margin-bottom: 10px !important;
            }
            .receipt-head h4 { font-size: 1.1rem !important; font-weight: bold !important; margin-bottom: 4px !important; color: #000 !important; }
            .receipt-head p  { font-size: 0.75rem !important; opacity: 1 !important; color: #000 !important; }
            .receipt-ok-icon { display: none !important; }
            
            .receipt-body { padding: 0 !important; }
            
            .r-meta-row { font-size: 0.75rem !important; color: #000 !important; padding: 2px 0 !important; flex-wrap: wrap; justify-content: space-between; }
            .r-meta-row span:first-child { width: 40%; text-align: left; }
            .r-meta-row strong { width: 60%; text-align: right; color: #000 !important; font-weight: normal !important; }
            
            .r-dash { border-top: 1.5px dashed #000 !important; margin: 8px 0 !important; }
            
            .r-item { font-size: 0.75rem !important; padding: 3px 0 !important; flex-wrap: wrap !important; }
            .r-item-name { width: 100% !important; margin-bottom: 2px !important; font-weight: bold !important; color: #000 !important; }
            .r-item-qty  { width: 30% !important; text-align: left !important; color: #000 !important; }
            .r-item-amt  { width: 70% !important; text-align: right !important; font-weight: bold !important; color: #000 !important; }
            
            .r-total { display: flex; justify-content: space-between; font-size: 1.1rem !important; font-weight: bold !important; padding-top: 5px !important; color: #000 !important;}
            
            /* Hidden icons to format clean text */
            .r-meta-row strong {
               font-family: inherit !important; 
            }
            .r-meta-row { font-size: 0.85rem !important; }
            .r-item { font-size: 0.85rem !important; padding-bottom: 8px !important; }
            .receipt-actions { display: none !important; }
            .r-total { margin-bottom: 20px !important; font-size: 1.15rem !important; }
            .receipt-body { padding-bottom: 15px !important; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/staff-sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">

            <!-- Top bar -->
            <div class="pos-topbar">
                <div class="pos-topbar-icon"><i class="bi bi-cash-coin"></i></div>
                <div class="pos-topbar-text">
                    <h2>Kho hàng mã vạch</h2>
                    <p>Sản phẩm + mã vạch Goodwill Vietnam • Quét mã để thêm nhanh vào giỏ</p>
                </div>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-3">

                <!-- ═══ LEFT ═══ -->
                <div class="col-xl-8 col-lg-7">
                    <div class="pos-left">

                        <!-- Search -->
                        <div class="search-card">
                            <i class="bi bi-search srch-ic"></i>
                            <input type="text" id="searchInput"
                                   placeholder="Tìm kiếm sản phẩm theo tên, mã, danh mục...">
                        </div>

                        <!-- Product grid -->
                        <div class="products-card">
                            <div class="products-card-head">
                                <h6><i class="bi bi-grid-3x3-gap me-1"></i>Danh sách sản phẩm</h6>
                                <span class="text-muted" style="font-size:.75rem;" id="productCount">
                                    <?php echo count($products); ?> sản phẩm
                                </span>
                            </div>
                            <div class="products-card-body">
                                <div class="product-grid" id="productGrid">
                                    <?php foreach ($products as $p): ?>
                                        <div class="product-card"
                                             data-id="<?php echo (int)$p['item_id']; ?>"
                                             data-name="<?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                             data-price="<?php echo (float)($p['sale_price'] ?? 0); ?>"
                                            data-qty="<?php echo (int)($p['available_quantity'] ?? 0); ?>"
                                            data-cat="<?php echo htmlspecialchars($p['category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-barcode="<?php echo 'GWV' . str_pad((string)((int)$p['item_id']), 6, '0', STR_PAD_LEFT); ?>">
                                            <img src="../<?php echo htmlspecialchars($p['img_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                                 alt="<?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                 class="p-img"
                                                 onerror="this.src='../uploads/donations/placeholder-default.svg'">
                                            <div class="p-body">
                                                <div class="p-name" title="<?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                                <div class="p-cat"><?php echo htmlspecialchars($p['category_name'] ?? 'Không phân loại', ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="barcode-box">
                                                    <svg class="barcode-svg" id="barcode-<?php echo (int)$p['item_id']; ?>"></svg>
                                                    <span class="barcode-code"><?php echo 'GWV' . str_pad((string)((int)$p['item_id']), 6, '0', STR_PAD_LEFT); ?></span>
                                                </div>
                                                <button class="btn-download-barcode" type="button" data-action="download-barcode">
                                                    <i class="bi bi-download me-1"></i>Tải mã PNG
                                                </button>
                                                <div class="p-price"><?php echo number_format((float)($p['sale_price'] ?? 0), 0, ',', '.'); ?>đ</div>
                                                <div class="p-stock" data-stock-text="<?php echo (int)$p['item_id']; ?>">SL: <?php echo (int)($p['available_quantity'] ?? 0); ?></div>
                                                <button class="btn-add" type="button">+ Thêm</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ═══ RIGHT SIDEBAR ═══ -->
                <div class="col-xl-4 col-lg-5">
                    <div class="pos-sidebar">

                        <!-- Customer info -->
                        <div class="sb-card">
                            <div class="sb-card-head">
                                <i class="bi bi-person-circle" style="color:var(--teal);font-size:.95rem;"></i>
                                <h6>Thông tin khách hàng</h6>
                            </div>
                            <div class="sb-customer-body">
                                <input type="text" id="customerName"
                                       placeholder="Tên khách hàng (tuỳ chọn)">
                            </div>
                        </div>

                        <div class="sb-card">
                            <div class="sb-card-head">
                                <i class="bi bi-upc-scan" style="color:var(--teal);font-size:.95rem;"></i>
                                <h6>Quét mã vạch sản phẩm</h6>
                            </div>
                            <div class="sb-scan-body">
                                <div class="scan-actions">
                                    <button class="scan-btn primary" type="button" id="startScanBtn">
                                        <i class="bi bi-camera-video me-1"></i>Bật camera
                                    </button>
                                    <button class="scan-btn" type="button" id="stopScanBtn" disabled>
                                        <i class="bi bi-stop-circle me-1"></i>Dừng quét
                                    </button>
                                </div>
                                <div class="scan-manual">
                                    <input type="text" id="manualBarcodeInput" placeholder="Nhập mã (VD: GWV000123)">
                                    <button type="button" id="manualAddBtn">Thêm</button>
                                </div>
                                <div class="scan-reader-wrap" id="scanReaderWrap">
                                    <div id="scannerReader"></div>
                                    <div class="scan-overlay" id="scanOverlay">
                                        <div class="scan-corner tl"></div>
                                        <div class="scan-corner tr"></div>
                                        <div class="scan-corner bl"></div>
                                        <div class="scan-corner br"></div>
                                        <div class="scan-laser" id="scanLaser"></div>
                                        <div class="scan-success-flash" id="scanFlash"></div>
                                        <div class="scan-badge-pop" id="scanBadge">✓ Đã nhận diện</div>
                                    </div>
                                </div>
                                <div class="scan-status" id="scanStatus">Sẵn sàng quét mã vạch.</div>
                                <div class="scan-hint">Dùng camera laptop/điện thoại để quét, sản phẩm sẽ tự vào giỏ.</div>
                            </div>
                        </div>

                        <!-- Cart -->
                        <div class="sb-card">
                            <div class="sb-card-head">
                                <i class="bi bi-cart3" style="color:var(--teal);font-size:.95rem;"></i>
                                <h6>Giỏ hàng</h6>
                                <span class="sb-badge" id="cartCount">0 món</span>
                            </div>
                            <div class="sb-cart-body" id="cartItemsList">
                                <div class="cart-empty" id="cartEmptyState">
                                    <i class="bi bi-cart4"></i>
                                    <span>Chưa có sản phẩm nào</span>
                                </div>
                            </div>
                        </div>

                        <!-- Payment methods -->
                        <div class="sb-card">
                            <div class="sb-card-head">
                                <i class="bi bi-credit-card-2-front" style="color:var(--teal);font-size:.95rem;"></i>
                                <h6>Phương thức thanh toán</h6>
                            </div>
                            <div class="sb-pay-body">
                                <div class="pay-grid">
                                    <button class="pay-opt active" type="button" data-method="cash">
                                        <i class="bi bi-cash-coin"></i>
                                        <span>Tiền mặt</span>
                                    </button>
                                    <button class="pay-opt" type="button" data-method="bank_transfer">
                                        <i class="bi bi-bank"></i>
                                        <span>CK</span>
                                    </button>
                                    <button class="pay-opt" type="button" data-method="momo">
                                        <i class="bi bi-wallet2"></i>
                                        <span>MoMo</span>
                                    </button>
                                    <button class="pay-opt" type="button" data-method="bank_transfer" style="display:none;">
                                        <i class="bi bi-credit-card"></i>
                                        <span>Thẻ</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Totals -->
                        <div class="sb-card">
                            <div class="sb-totals-body">
                                <div class="total-row">
                                    <span class="lbl">Tạm tính</span>
                                    <span class="val" id="subtotalAmt">0đ</span>
                                </div>
                                <div class="total-row">
                                    <span class="lbl">Giảm giá</span>
                                    <span class="val">0đ</span>
                                </div>
                                <div class="total-row grand">
                                    <span class="lbl">Tổng cộng</span>
                                    <span class="val" id="totalAmount">0đ</span>
                                </div>
                            </div>
                            <div class="sb-checkout-body">
                                <button class="btn-checkout" id="checkoutBtn" type="button" disabled>
                                    <i class="bi bi-receipt-cutoff"></i>
                                    Thanh toán &amp; Xuất bill
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

<!-- Hidden POST form -->
<form method="post" id="checkoutForm" style="display:none;">
    <input type="hidden" name="csrf_token"     value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="cart_json"       id="cartJson"           value="[]">
    <input type="hidden" name="payment_method"  id="paymentInput"       value="cash">
    <input type="hidden" name="customer_name"   id="customerNameHidden" value="">
</form>

<?php if ($receipt): ?>
<!-- Receipt Overlay -->
<div class="receipt-overlay" id="receiptOverlay">
    <div class="receipt-card">
        <div class="receipt-head">
            <i class="bi bi-check-circle-fill receipt-ok-icon"></i>
            <h4>Thanh toán thành công!</h4>
            <p>Goodwill Vietnam — Hoá đơn bán hàng tại quầy</p>
        </div>
        <div class="receipt-body">
            <div class="r-meta-row">
                <span>Số hoá đơn</span>
                <strong>#<?php echo (int)$receipt['order_id']; ?></strong>
            </div>
            <div class="r-meta-row">
                <span>Thời gian</span>
                <strong><?php echo htmlspecialchars($receipt['created_at'], ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="r-meta-row">
                <span>Khách hàng</span>
                <strong><?php echo htmlspecialchars($receipt['customer_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="r-meta-row">
                <span>Phương thức</span>
                <strong><?php echo $receipt['payment_method'] === 'cash' ? '💵 Tiền mặt' : ($receipt['payment_method'] === 'momo' ? '📱 MoMo' : '🏦 Chuyển khoản / Thẻ'); ?></strong>
            </div>

            <hr class="r-dash">

            <?php foreach ($receipt['items'] as $it): ?>
                <div class="r-item">
                    <span class="r-item-name"><?php echo htmlspecialchars($it['item_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="r-item-qty">x<?php echo (int)$it['quantity']; ?></span>
                    <span class="r-item-amt"><?php echo number_format((float)$it['subtotal'], 0, ',', '.'); ?>đ</span>
                </div>
            <?php endforeach; ?>

            <hr class="r-dash">
            <div class="r-total">
                <span>Tổng cộng</span>
                <span><?php echo number_format((float)$receipt['total_amount'], 0, ',', '.'); ?>đ</span>
            </div>
        </div>
        <div class="receipt-actions">
            <button class="btn-bill-print" onclick="window.print()">
                <i class="bi bi-printer-fill"></i> In hoá đơn
            </button>
            <a href="cashier-direct-sale.php" class="btn-new-sale">
                <i class="bi bi-plus-circle"></i> Bán mới
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
(() => {
    'use strict';

    /* ── State ── */
    const cart = new Map();     // item_id → { id, name, price, maxQty, quantity }
    let selectedPayment = 'cash';

    /* ── Elements ── */
    const cartItemsList   = document.getElementById('cartItemsList');
    const cartEmptyState  = document.getElementById('cartEmptyState');
    const cartCountEl     = document.getElementById('cartCount');
    const subtotalAmtEl   = document.getElementById('subtotalAmt');
    const totalAmountEl   = document.getElementById('totalAmount');
    const checkoutBtn     = document.getElementById('checkoutBtn');
    const cartJsonInput   = document.getElementById('cartJson');
    const paymentInput    = document.getElementById('paymentInput');
    const customerInput   = document.getElementById('customerName');
    const customerHidden  = document.getElementById('customerNameHidden');
    const searchInput     = document.getElementById('searchInput');
    const productCountEl  = document.getElementById('productCount');
    const scanStatusEl    = document.getElementById('scanStatus');
    const startScanBtn    = document.getElementById('startScanBtn');
    const stopScanBtn     = document.getElementById('stopScanBtn');
    const scannerReader   = document.getElementById('scannerReader');
    const scanReaderWrap  = document.getElementById('scanReaderWrap');
    const scanFlash       = document.getElementById('scanFlash');
    const scanBadge       = document.getElementById('scanBadge');
    const manualBarcodeInput = document.getElementById('manualBarcodeInput');
    const manualAddBtn    = document.getElementById('manualAddBtn');

    const productByBarcode = new Map();
    const stockById = new Map();
    let html5QrCode = null;
    let scanning = false;
    let lastScanText = '';
    let lastScanAt = 0;
    const scanCooldownMs = 220;
    const lastCameraKey = 'gwv_cashier_last_camera_id';

    /* ── Helpers ── */
    const fmt = (n) => new Intl.NumberFormat('vi-VN').format(Math.round(n || 0)) + 'đ';
    const esc = (s) => String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');

    const normalizeBarcode = (value) => String(value || '').trim().toUpperCase();

    function setScanStatus(msg, isError = false) {
        if (!scanStatusEl) return;
        scanStatusEl.textContent = msg;
        scanStatusEl.style.color = isError ? '#b91c1c' : '#0b728c';
        scanStatusEl.style.background = isError ? '#fef2f2' : '#eef8fc';
    }

    function flashCard(card) {
        card.style.boxShadow = '0 0 0 3px rgba(16,185,129,.35), 0 8px 22px rgba(11,114,140,.16)';
        setTimeout(() => { card.style.boxShadow = ''; }, 650);
    }

    let _scanFoundTimer = null;
    function _triggerScanFound(code) {
        // Zoom-in + green corners on the wrapper
        scanReaderWrap.classList.add('barcode-found');
        scannerReader.classList.add('barcode-found');

        // Green flash
        if (scanFlash) {
            scanFlash.classList.remove('active');
            void scanFlash.offsetWidth; // reflow to restart animation
            scanFlash.classList.add('active');
        }

        // Badge pop with code name
        if (scanBadge) {
            const found = productByBarcode.get(code);
            scanBadge.textContent = '✓ ' + (found ? found.dataset.name : code);
            scanBadge.classList.remove('active');
            void scanBadge.offsetWidth;
            scanBadge.classList.add('active');
        }

        // Revert to scanning state after a short moment
        clearTimeout(_scanFoundTimer);
        _scanFoundTimer = setTimeout(() => {
            scanReaderWrap.classList.remove('barcode-found');
            scannerReader.classList.remove('barcode-found');
            if (scanFlash)  scanFlash.classList.remove('active');
            if (scanBadge)  scanBadge.classList.remove('active');
        }, 600);
    }

    function applyStockSnapshot(snapshot = {}) {
        Object.entries(snapshot).forEach(([idStr, item]) => {
            const id = parseInt(idStr, 10);
            const card = document.querySelector(`.product-card[data-id="${id}"]`);
            if (!card) return;

            const qty = Math.max(0, parseInt(item.available_quantity || 0, 10));
            stockById.set(id, qty);
            card.dataset.qty = String(qty);

            const stockText = card.querySelector(`[data-stock-text="${id}"]`);
            if (stockText) {
                stockText.textContent = 'SL: ' + qty;
            }

            const addBtn = card.querySelector('.btn-add');
            if (qty <= 0 || String(item.status || '') !== 'available' || parseInt(item.is_for_sale || 0, 10) !== 1) {
                card.classList.add('hidden');
                if (addBtn) {
                    addBtn.disabled = true;
                    addBtn.textContent = 'Hết hàng';
                }
            } else if (addBtn && !cart.has(id)) {
                addBtn.disabled = false;
                addBtn.textContent = '+ Thêm';
            }
        });

        // Keep cart quantities within latest available stock
        cart.forEach((item, id) => {
            const latest = stockById.has(id) ? stockById.get(id) : item.maxQty;
            item.maxQty = latest;
            if (latest <= 0) {
                cart.delete(id);
            } else if (item.quantity > latest) {
                item.quantity = latest;
                cart.set(id, item);
            }
        });
    }

    async function refreshStockSnapshot(showError = false) {
        try {
            const res = await fetch('cashier-direct-sale.php?stock_snapshot=1', {
                cache: 'no-store',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            if (!json || json.success !== true || typeof json.data !== 'object') {
                throw new Error('Invalid response');
            }
            applyStockSnapshot(json.data);
            renderCart();
            return true;
        } catch (e) {
            if (showError) {
                setScanStatus('Không thể đồng bộ tồn kho thời gian thực.', true);
            }
            return false;
        }
    }

    /* ── Render cart ── */
    function renderCart() {
        cartItemsList.querySelectorAll('.cart-item').forEach(el => el.remove());

        let total = 0, count = 0;
        if (cart.size === 0) {
            cartEmptyState.style.display = 'block';
        } else {
            cartEmptyState.style.display = 'none';
            cart.forEach(item => {
                const sub = item.quantity * item.price;
                total += sub;
                count += item.quantity;

                const div = document.createElement('div');
                div.className = 'cart-item';
                div.innerHTML = `
                    <div class="ci-info">
                        <div class="ci-name">${esc(item.name)}</div>
                        <div class="ci-unit">${fmt(item.price)} / cái</div>
                    </div>
                    <div class="qty-ctrl">
                        <button class="qty-btn" data-action="dec" data-id="${item.id}">−</button>
                        <span class="qty-num">${item.quantity}</span>
                        <button class="qty-btn" data-action="inc" data-id="${item.id}">+</button>
                    </div>
                    <span class="ci-sub">${fmt(sub)}</span>
                    <button class="ci-remove" data-action="remove" data-id="${item.id}" title="Xoá">
                        <i class="bi bi-x-lg"></i>
                    </button>`;
                cartItemsList.appendChild(div);
            });
        }

        subtotalAmtEl.textContent = fmt(total);
        totalAmountEl.textContent = fmt(total);
        cartCountEl.textContent   = count + ' món';
        checkoutBtn.disabled      = cart.size === 0;
        cartJsonInput.value       = JSON.stringify(
            Array.from(cart.values()).map(i => ({ item_id: i.id, quantity: i.quantity }))
        );

        /* Highlight cards */
        document.querySelectorAll('.product-card').forEach(card => {
            const id  = parseInt(card.dataset.id, 10);
            const btn = card.querySelector('.btn-add');
            const liveQty = stockById.has(id) ? stockById.get(id) : parseInt(card.dataset.qty || '0', 10);
            if (cart.has(id)) {
                card.classList.add('in-cart');
                btn.textContent = '✓ ' + cart.get(id).quantity + ' trong giỏ';
                btn.disabled = false;
            } else if (liveQty <= 0) {
                card.classList.remove('in-cart');
                btn.textContent = 'Hết hàng';
                btn.disabled = true;
            } else {
                card.classList.remove('in-cart');
                btn.textContent = '+ Thêm';
                btn.disabled = false;
            }
        });
    }

    /* ── Add to cart ── */
    function addItem(card) {
        const id     = parseInt(card.dataset.id, 10);
        const name   = card.dataset.name;
        const price  = parseFloat(card.dataset.price || '0');
        const maxQty = stockById.has(id) ? stockById.get(id) : parseInt(card.dataset.qty || '0', 10);
        if (!id || maxQty <= 0) return;

        if (cart.has(id)) {
            const item = cart.get(id);
            if (item.quantity < item.maxQty) { item.quantity++; cart.set(id, item); }
        } else {
            cart.set(id, { id, name, price, maxQty, quantity: 1 });
        }
        renderCart();
    }

    function addByBarcode(rawCode) {
        const code = normalizeBarcode(rawCode);
        if (!code) return false;
        const card = productByBarcode.get(code);
        if (!card) {
            setScanStatus('Không tìm thấy sản phẩm với mã: ' + code, true);
            return false;
        }
        addItem(card);
        flashCard(card);
        setScanStatus('Đã thêm vào giỏ: ' + card.dataset.name + ' (' + code + ')');
        return true;
    }

    function renderAllBarcodes() {
        if (typeof JsBarcode !== 'function') return;
        document.querySelectorAll('.product-card').forEach((card) => {
            const code = normalizeBarcode(card.dataset.barcode);
            const svg = card.querySelector('.barcode-svg');
            if (!svg || !code) return;
            try {
                JsBarcode(svg, code, {
                    format: 'CODE128',
                    lineColor: '#0f172a',
                    width: 1.45,
                    height: 40,
                    displayValue: false,
                    margin: 0
                });
                productByBarcode.set(code, card);
            } catch (e) {
                console.warn('Cannot render barcode', code, e);
            }
        });
    }

    function downloadBarcodePng(card) {
        const svgEl = card.querySelector('.barcode-svg');
        const code = normalizeBarcode(card.dataset.barcode);
        const productName = String(card.dataset.name || 'San pham').trim();
        if (!svgEl || !code) return;

        const serializer = new XMLSerializer();
        const svgData = serializer.serializeToString(svgEl);
        const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
        const url = URL.createObjectURL(svgBlob);
        const img = new Image();

        img.onload = function () {
            const canvas = document.createElement('canvas');
            const labelWidth = 720;
            const labelHeight = 330;
            canvas.width = labelWidth;
            canvas.height = labelHeight;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Header
            ctx.fillStyle = '#0f172a';
            ctx.font = '700 30px Arial';
            ctx.textAlign = 'center';
            const nameText = productName.length > 44 ? (productName.slice(0, 41) + '...') : productName;
            ctx.fillText(nameText, labelWidth / 2, 52);

            // Barcode
            const barcodeW = Math.min(620, labelWidth - 60);
            const barcodeH = 150;
            const x = Math.round((labelWidth - barcodeW) / 2);
            const y = 78;
            ctx.drawImage(img, x, y, barcodeW, barcodeH);

            // Code text
            ctx.fillStyle = '#1f2937';
            ctx.font = '700 28px Arial';
            ctx.fillText(code, labelWidth / 2, 265);

            // Footer
            ctx.fillStyle = '#64748b';
            ctx.font = '500 20px Arial';
            ctx.fillText('Goodwill Vietnam', labelWidth / 2, 299);
            URL.revokeObjectURL(url);

            const link = document.createElement('a');
            link.download = code + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        };

        img.src = url;
    }

    async function startScanner() {
        if (scanning) return;
        if (typeof Html5Qrcode === 'undefined') {
            setScanStatus('Không tải được thư viện camera scan.', true);
            return;
        }

        scanReaderWrap.classList.add('scanning');
        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode('scannerReader');
        }

        const onDecoded = (decodedText) => {
            const now = Date.now();
            const normalized = normalizeBarcode(decodedText);
            if (!normalized) return;
            if (normalized === lastScanText && now - lastScanAt < scanCooldownMs) return;
            lastScanText = normalized;
            lastScanAt = now;
            addByBarcode(normalized);
            // Haptic feedback on mobile when scan succeeds.
            if (navigator.vibrate) navigator.vibrate(18);
            // Visual: zoom corners + green flash + badge pop
            _triggerScanFound(normalized);
        };

        const scanConfig = {
            fps: 24,
            qrbox: {
                width: Math.min(420, Math.floor(window.innerWidth * 0.78)),
                height: 110
            },
            aspectRatio: 1.777,
            disableFlip: true,
            formatsToSupport: [
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E
            ]
        };

        const pickBestCameraId = async () => {
            try {
                const cams = await Html5Qrcode.getCameras();
                if (!Array.isArray(cams) || cams.length === 0) return null;

                const remembered = localStorage.getItem(lastCameraKey);
                if (remembered) {
                    const matched = cams.find(c => c.id === remembered);
                    if (matched) return matched.id;
                }

                const preferred = cams.find(c => /back|rear|environment|sau/i.test((c.label || '').toLowerCase()));
                return (preferred || cams[0]).id;
            } catch (e) {
                return null;
            }
        };

        try {
            const cameraId = await pickBestCameraId();
            await html5QrCode.start(
                cameraId ? { deviceId: { exact: cameraId } } : { facingMode: { ideal: 'environment' } },
                scanConfig,
                onDecoded,
                () => {}
            );
            if (cameraId) {
                localStorage.setItem(lastCameraKey, cameraId);
            }
        } catch (err) {
            try {
                await html5QrCode.start(
                    { facingMode: 'environment' },
                    scanConfig,
                    onDecoded,
                    () => {}
                );
            } catch (err2) {
                scannerReader.style.display = 'none';
                setScanStatus('Không thể bật camera quét mã. Kiểm tra quyền camera.', true);
                return;
            }
        }

        scanning = true;
        startScanBtn.disabled = true;
        stopScanBtn.disabled = false;
        setScanStatus('Đang quét mã vạch (chế độ siêu nhanh)...');
    }

    async function stopScanner() {
        if (!scanning || !html5QrCode) return;
        try {
            await html5QrCode.stop();
            await html5QrCode.clear();
        } catch (e) {}

        scanning = false;
        scanReaderWrap.classList.remove('scanning', 'barcode-found');
        startScanBtn.disabled = false;
        stopScanBtn.disabled = true;
        setScanStatus('Đã dừng camera quét.');
    }

    /* ── Product card clicks ── */
    document.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('click', () => addItem(card));
        card.querySelector('[data-action="download-barcode"]').addEventListener('click', e => {
            e.stopPropagation();
            downloadBarcodePng(card);
        });
        card.querySelector('.btn-add').addEventListener('click', e => {
            e.stopPropagation();
            addItem(card);
        });
    });

    /* ── Cart actions (delegation) ── */
    cartItemsList.addEventListener('click', e => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const id     = parseInt(btn.dataset.id, 10);
        const action = btn.dataset.action;

        if (action === 'inc') {
            const item = cart.get(id);
            if (item && item.quantity < item.maxQty) { item.quantity++; cart.set(id, item); }
        } else if (action === 'dec') {
            const item = cart.get(id);
            if (item) {
                if (item.quantity > 1) { item.quantity--; cart.set(id, item); }
                else { cart.delete(id); }
            }
        } else if (action === 'remove') {
            cart.delete(id);
        }
        renderCart();
    });

    /* ── Payment selection ── */
    document.querySelectorAll('.pay-opt').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.pay-opt').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            selectedPayment    = btn.dataset.method;
            paymentInput.value = selectedPayment;
        });
    });

    /* ── Checkout ── */
    checkoutBtn.addEventListener('click', () => {
        if (cart.size === 0) return;
        refreshStockSnapshot(true).then(() => {
            if (cart.size === 0) {
                setScanStatus('Giỏ hàng đã được cập nhật do thay đổi tồn kho.', true);
                return;
            }
            customerHidden.value = customerInput.value.trim();
            paymentInput.value   = selectedPayment;
            document.getElementById('checkoutForm').submit();
        });
    });

    /* ── Search / filter ── */
    searchInput.addEventListener('input', () => {
        const q = searchInput.value.trim().toLowerCase();
        let visible = 0;
        document.querySelectorAll('.product-card').forEach(card => {
            const id = parseInt(card.dataset.id, 10);
            const liveQty = stockById.has(id) ? stockById.get(id) : parseInt(card.dataset.qty || '0', 10);
            const hit = !q
                || card.dataset.name.toLowerCase().includes(q)
                || (card.dataset.cat || '').toLowerCase().includes(q)
                || (card.dataset.barcode || '').toLowerCase().includes(q);
            const shouldShow = hit && liveQty > 0;
            card.classList.toggle('hidden', !shouldShow);
            if (shouldShow) visible++;
        });
        if (productCountEl) productCountEl.textContent = visible + ' sản phẩm';
    });

    if (startScanBtn) {
        startScanBtn.addEventListener('click', startScanner);
    }
    if (stopScanBtn) {
        stopScanBtn.addEventListener('click', stopScanner);
    }
    if (manualAddBtn) {
        manualAddBtn.addEventListener('click', () => {
            const ok = addByBarcode(manualBarcodeInput.value);
            if (ok) manualBarcodeInput.value = '';
        });
    }
    if (manualBarcodeInput) {
        manualBarcodeInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                manualAddBtn.click();
            }
        });
    }

    window.addEventListener('beforeunload', () => {
        if (scanning) stopScanner();
    });

    /* ── Init ── */
    renderAllBarcodes();
    refreshStockSnapshot(false);
    setInterval(() => { refreshStockSnapshot(false); }, 7000);
    renderCart();
})();
</script>
</body>
</html>
