<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$pageTitle = "Thanh toán";
$hasOrderHistoryTable = !empty(Database::fetchAll("SHOW TABLES LIKE 'order_status_history'"));
$googleConfigPath = __DIR__ . '/config/google.php';
$googleConfig = file_exists($googleConfigPath) ? require $googleConfigPath : [];
$googleMapsKey = trim((string)($googleConfig['maps_api_key'] ?? ''));
$paymentConfigPath = __DIR__ . '/config/payment.php';
$paymentConfig = file_exists($paymentConfigPath) ? require $paymentConfigPath : [];

$success = '';
$error = '';

// Get user info
$user = getUserById($_SESSION['user_id']);
$shipping_name = $user['name'] ?? '';
$shipping_phone = $user['phone'] ?? '';
$shipping_address = $user['address'] ?? '';
$shipping_note = '';
$payment_method = 'cod';

function checkoutGetBaseUrl(): string
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function checkoutPostJson(string $url, array $payload): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Không thể khởi tạo kết nối thanh toán.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Lỗi kết nối cổng thanh toán: ' . $curlError);
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Phản hồi từ cổng thanh toán không hợp lệ.');
    }

    if ($httpCode >= 400) {
        $gatewayMessage = trim((string)($decoded['message'] ?? ''));
        throw new RuntimeException('Cổng thanh toán trả về lỗi HTTP ' . $httpCode . ($gatewayMessage !== '' ? (': ' . $gatewayMessage) : ''));
    }

    return $decoded;
}

function checkoutBuildMomoCreateSignature(array $momo, string $amount, string $extraData, string $ipnUrl, string $orderId, string $orderInfo, string $redirectUrl, string $requestId, string $requestType): string
{
    $rawHash = 'accessKey=' . $momo['access_key']
        . '&amount=' . $amount
        . '&extraData=' . $extraData
        . '&ipnUrl=' . $ipnUrl
        . '&orderId=' . $orderId
        . '&orderInfo=' . $orderInfo
        . '&partnerCode=' . $momo['partner_code']
        . '&redirectUrl=' . $redirectUrl
        . '&requestId=' . $requestId
        . '&requestType=' . $requestType;

    return hash_hmac('sha256', $rawHash, $momo['secret_key']);
}

function checkoutExtractOrderIdFromMomoData(?string $orderId, ?string $extraData): int
{
    $orderId = (string)$orderId;
    if (preg_match('/^ORDER(\d+)_/i', $orderId, $m)) {
        return (int)$m[1];
    }

    $extraData = trim((string)$extraData);
    if ($extraData !== '') {
        $decoded = base64_decode($extraData, true);
        if ($decoded !== false) {
            $arr = json_decode($decoded, true);
            if (is_array($arr) && !empty($arr['order_id'])) {
                return (int)$arr['order_id'];
            }
        }
    }

    return 0;
}

function checkoutVerifyMomoResponseSignature(array $payload, string $secretKey): bool
{
    $required = ['amount', 'extraData', 'message', 'orderId', 'orderInfo', 'orderType', 'partnerCode', 'payType', 'requestId', 'responseTime', 'resultCode', 'transId', 'signature'];
    foreach ($required as $key) {
        if (!array_key_exists($key, $payload)) {
            return false;
        }
    }

    $rawHash = 'accessKey=' . $payload['accessKey']
        . '&amount=' . $payload['amount']
        . '&extraData=' . $payload['extraData']
        . '&message=' . $payload['message']
        . '&orderId=' . $payload['orderId']
        . '&orderInfo=' . $payload['orderInfo']
        . '&orderType=' . $payload['orderType']
        . '&partnerCode=' . $payload['partnerCode']
        . '&payType=' . $payload['payType']
        . '&requestId=' . $payload['requestId']
        . '&responseTime=' . $payload['responseTime']
        . '&resultCode=' . $payload['resultCode']
        . '&transId=' . $payload['transId'];

    $partnerSignature = hash_hmac('sha256', $rawHash, $secretKey);
    return hash_equals($partnerSignature, (string)$payload['signature']);
}

if (!empty($_GET['orderId']) && isset($_GET['resultCode']) && !empty($_GET['signature'])) {
    $momoCfg = $paymentConfig['momo'] ?? [];
    $secretKey = trim((string)($momoCfg['secret_key'] ?? ''));
    $resultCode = (string)($_GET['resultCode'] ?? '');
    $returnParams = $_GET;
    if (empty($returnParams['accessKey'])) {
        $returnParams['accessKey'] = trim((string)($momoCfg['access_key'] ?? ''));
    }

    $failedMessage = '';
    if ($secretKey === '') {
        $failedMessage = 'Thiếu cấu hình MoMo secret_key. Vui lòng kiểm tra file config/payment.php.';
    } elseif (!checkoutVerifyMomoResponseSignature($returnParams, $secretKey)) {
        $failedMessage = 'Xác thực chữ ký MoMo thất bại.';
    }

    $returnOrderId = checkoutExtractOrderIdFromMomoData((string)($_GET['orderId'] ?? ''), (string)($_GET['extraData'] ?? ''));
    if ($failedMessage === '' && $returnOrderId <= 0) {
        $failedMessage = 'Không xác định được đơn hàng thanh toán MoMo.';
    }

    if ($failedMessage === '') {
        $order = Database::fetch('SELECT order_id, user_id, status, shipping_last_mile_status FROM orders WHERE order_id = ?', [$returnOrderId]);
        if (!$order || (int)$order['user_id'] !== (int)$_SESSION['user_id']) {
            $failedMessage = 'Không tìm thấy đơn hàng hợp lệ để xác nhận thanh toán.';
        }
    }

    if ($failedMessage !== '') {
        header('Location: payment-failed.php?message=' . urlencode($failedMessage));
        exit();
    }

    if ($resultCode === '0') {
        $paymentStatusColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'payment_status'");
        $paymentReferenceColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'payment_reference'");
        $shippingLastMileColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'shipping_last_mile_status'");
        $shippingLastMileUpdatedAtColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'shipping_last_mile_updated_at'");
        $updatedAtColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'updated_at'");
        $statusColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'status'");

        $setClauses = [];
        $params = [];

        if (!empty($paymentStatusColumn['Type']) && strpos($paymentStatusColumn['Type'], 'enum(') === 0) {
            preg_match_all("/'([^']+)'/", $paymentStatusColumn['Type'], $statusMatches);
            $allowedPaymentStatuses = $statusMatches[1] ?? [];
            if (in_array('paid', $allowedPaymentStatuses, true)) {
                $setClauses[] = 'payment_status = ?';
                $params[] = 'paid';
            }
        }

        $setClauses[] = 'payment_method = ?';
        $params[] = 'momo';

        if (!empty($paymentReferenceColumn)) {
            $setClauses[] = 'payment_reference = ?';
            $params[] = 'MOMO-' . trim((string)($_GET['transId'] ?? ($_GET['orderId'] ?? '')));
        }

        $nextStatus = null;
        if (!empty($statusColumn['Type']) && strpos($statusColumn['Type'], 'enum(') === 0) {
            preg_match_all("/'([^']+)'/", $statusColumn['Type'], $orderStatusMatches);
            $allowedOrderStatuses = $orderStatusMatches[1] ?? [];
            $currentStatus = strtolower((string)($order['status'] ?? ''));
            if ($currentStatus === 'pending') {
                if (in_array('confirmed', $allowedOrderStatuses, true)) {
                    $nextStatus = 'confirmed';
                } elseif (in_array('processing', $allowedOrderStatuses, true)) {
                    $nextStatus = 'processing';
                }
            }
        }
        if ($nextStatus !== null) {
            $setClauses[] = 'status = ?';
            $params[] = $nextStatus;
        }

        if (!empty($shippingLastMileColumn)) {
            $currentLastMile = strtolower(trim((string)($order['shipping_last_mile_status'] ?? '')));
            if ($currentLastMile === '' || $currentLastMile === 'payment_pending') {
                $setClauses[] = 'shipping_last_mile_status = ?';
                $params[] = 'payment_completed';
                if (!empty($shippingLastMileUpdatedAtColumn)) {
                    $setClauses[] = 'shipping_last_mile_updated_at = NOW()';
                }
            }
        }

        if (!empty($updatedAtColumn)) {
            $setClauses[] = 'updated_at = NOW()';
        }

        if (!empty($setClauses)) {
            $params[] = $returnOrderId;
            Database::execute('UPDATE orders SET ' . implode(', ', $setClauses) . ' WHERE order_id = ?', $params);
        }

        if ($hasOrderHistoryTable && $nextStatus !== null) {
            Database::execute(
                "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
                 VALUES (?, ?, ?, 'Thanh toán MoMo thành công', NOW())",
                [$returnOrderId, (string)($order['status'] ?? 'pending'), $nextStatus]
            );
        }

        header('Location: order-success.php?order_id=' . $returnOrderId);
        exit();
    }

    $message = 'Thanh toán MoMo không thành công: ' . trim((string)($_GET['message'] ?? 'Vui lòng thử lại.'));
    header('Location: payment-failed.php?message=' . urlencode($message));
    exit();
}

// Get cart items with explicit columns (avoid quantity/name collisions)
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
            i.condition_status,
            i.price_type,
            i.sale_price,
            i.unit,
            i.images,
            i.status AS inventory_status,
            cat.name as category_name
        FROM cart c
        JOIN inventory i ON c.item_id = i.item_id
        LEFT JOIN categories cat ON i.category_id = cat.category_id
        WHERE c.user_id = ? AND i.status = 'available'
        ORDER BY c.created_at DESC";
$cartItems = Database::fetchAll($sql, [$_SESSION['user_id']]);

if (empty($cartItems)) {
    header('Location: cart.php');
    exit();
}

// Calculate totals
$totalItems = 0;
$totalAmount = 0;
$freeItemsCount = 0;
$paidItemsCount = 0;

foreach ($cartItems as $item) {
    $qty = (int)$item['cart_quantity'];
    $totalItems += $qty;

    $unitPrice = ($item['price_type'] === 'free') ? 0 : (float)$item['sale_price'];
    $itemTotal = $unitPrice * $qty;
    $totalAmount += $itemTotal;
    
    if ($item['price_type'] === 'free') {
        $freeItemsCount += $qty;
    } else {
        $paidItemsCount += $qty;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_name = sanitize($_POST['shipping_name'] ?? $shipping_name);
    $shipping_phone = sanitize($_POST['shipping_phone'] ?? $shipping_phone);
    $shipping_city = sanitize($_POST['shipping_city'] ?? '');
    $shipping_district = sanitize($_POST['shipping_district'] ?? '');
    $shipping_ward = sanitize($_POST['shipping_ward'] ?? '');
    $shipping_address = sanitize($_POST['shipping_address'] ?? $shipping_address);
    $shipping_note = sanitize($_POST['shipping_note'] ?? '');
    $payment_method = sanitize($_POST['payment_method'] ?? $payment_method);
    $allowedCheckoutMethods = ['cod', 'bank_transfer', 'momo', 'zalopay'];

    $shipping_place_id = '';
    $shipping_lat = null;
    $shipping_lng = null;
    
    // Validation
    if (empty($shipping_name)) {
        $error = 'Vui lòng nhập họ tên người nhận.';
    } elseif (empty($shipping_phone)) {
        $error = 'Vui lòng nhập số điện thoại.';
    } elseif (empty($shipping_city) || empty($shipping_district) || empty($shipping_ward)) {
        $error = 'Vui lòng chọn Thành phố, Quận/Huyện và Phường/Xã.';
    } elseif (empty($shipping_address)) {
        $error = 'Vui lòng nhập địa chỉ giao hàng.';
    } elseif (empty($payment_method)) {
        $error = 'Vui lòng chọn phương thức thanh toán.';
    } elseif (!in_array($payment_method, $allowedCheckoutMethods, true)) {
        $error = 'Phương thức thanh toán không hợp lệ.';
    } else {
        try {
            Database::beginTransaction();

            $shipping_address_full = trim(implode(', ', array_filter([
                $shipping_address,
                $shipping_ward,
                $shipping_district,
                $shipping_city,
            ])));

            // Kiểm tra schema bảng orders (hỗ trợ cả 2 kiểu: update_schema & orders_system)
            $hasShippingName = !empty(Database::fetchAll("SHOW COLUMNS FROM orders LIKE 'shipping_name'"));
            $hasShippingMethod = !empty(Database::fetchAll("SHOW COLUMNS FROM orders LIKE 'shipping_method'"));
            $statusColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'status'");
            $allowedStatuses = [];
            if (!empty($statusColumn['Type']) && strpos($statusColumn['Type'], "enum(") === 0) {
                preg_match_all("/'([^']+)'/", $statusColumn['Type'], $matches);
                $allowedStatuses = $matches[1] ?? [];
            }
            $orderStatus = in_array('pending', $allowedStatuses, true) ? 'pending' : ($allowedStatuses[0] ?? 'pending');
            $paymentMethodColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'payment_method'");
            $allowedPaymentMethods = [];
            if (!empty($paymentMethodColumn['Type']) && strpos($paymentMethodColumn['Type'], 'enum(') === 0) {
                preg_match_all("/'([^']+)'/", $paymentMethodColumn['Type'], $methodMatches);
                $allowedPaymentMethods = $methodMatches[1] ?? [];
            }

            $paymentMethodToStore = $payment_method;
            if (!empty($allowedPaymentMethods) && !in_array($paymentMethodToStore, $allowedPaymentMethods, true)) {
                if (in_array($paymentMethodToStore, ['momo', 'zalopay'], true) && in_array('bank_transfer', $allowedPaymentMethods, true)) {
                    $paymentMethodToStore = 'bank_transfer';
                } elseif (in_array('cod', $allowedPaymentMethods, true)) {
                    $paymentMethodToStore = 'cod';
                } elseif (in_array('cash', $allowedPaymentMethods, true)) {
                    $paymentMethodToStore = 'cash';
                } else {
                    $paymentMethodToStore = $allowedPaymentMethods[0] ?? $paymentMethodToStore;
                }
            }

            $legacyPaymentMethod = $paymentMethodToStore === 'cod' ? 'cash' : $paymentMethodToStore;
            if (in_array($legacyPaymentMethod, ['momo', 'zalopay'], true)) {
                $legacyPaymentMethod = 'bank_transfer';
            }
            $allowedLegacyMethods = ['cash', 'bank_transfer', 'credit_card', 'free'];
            if (!in_array($legacyPaymentMethod, $allowedLegacyMethods, true)) {
                $legacyPaymentMethod = 'cash';
            }

            if ($hasShippingName) {
                // Schema mới: có shipping_name, shipping_note (orders_system.sql)
                $hasShippingGeo = !empty(Database::fetchAll("SHOW COLUMNS FROM orders LIKE 'shipping_lat'"));
                $hasShippingPlaceId = !empty(Database::fetchAll("SHOW COLUMNS FROM orders LIKE 'shipping_place_id'"));
                if ($hasShippingGeo) {
                    $insertColumns = ['user_id', 'shipping_name', 'shipping_phone', 'shipping_address'];
                    $insertValues = [
                        $_SESSION['user_id'],
                        $shipping_name,
                        $shipping_phone,
                        $shipping_address_full,
                    ];
                    if ($hasShippingPlaceId) {
                        $insertColumns[] = 'shipping_place_id';
                        $insertValues[] = null;
                    }
                    $insertColumns[] = 'shipping_lat';
                    $insertColumns[] = 'shipping_lng';
                    $insertValues[] = $shipping_lat;
                    $insertValues[] = $shipping_lng;
                    if ($hasShippingMethod) {
                        $insertColumns[] = 'shipping_method';
                        $insertValues[] = 'delivery';
                    }
                    $insertColumns[] = 'shipping_note';
                    $insertColumns[] = 'payment_method';
                    $insertColumns[] = 'total_amount';
                    $insertColumns[] = 'status';
                    $insertColumns[] = 'created_at';
                    $insertValues[] = $shipping_note;
                    $insertValues[] = $paymentMethodToStore;
                    $insertValues[] = $totalAmount;
                    $insertValues[] = $orderStatus;

                    $placeholders = implode(', ', array_fill(0, count($insertValues), '?'));
                    Database::execute(
                        "INSERT INTO orders (" . implode(', ', $insertColumns) . ") VALUES (" . $placeholders . ", NOW())",
                        $insertValues
                    );
                } else {
                    Database::execute(
                        $hasShippingMethod
                            ? "INSERT INTO orders (user_id, shipping_name, shipping_phone, shipping_address,
                                                   shipping_method, shipping_note, payment_method, total_amount, status, created_at)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                            : "INSERT INTO orders (user_id, shipping_name, shipping_phone, shipping_address,
                                                   shipping_note, payment_method, total_amount, status, created_at)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                        $hasShippingMethod
                            ? [
                                $_SESSION['user_id'],
                                $shipping_name,
                                $shipping_phone,
                                $shipping_address_full,
                                'delivery',
                                $shipping_note,
                                $paymentMethodToStore,
                                $totalAmount,
                                $orderStatus
                            ]
                            : [
                                $_SESSION['user_id'],
                                $shipping_name,
                                $shipping_phone,
                                $shipping_address_full,
                                $shipping_note,
                                $paymentMethodToStore,
                                $totalAmount,
                                $orderStatus
                            ]
                    );
                }
            } else {
                // Schema cũ trong update_schema.sql: dùng order_number, total_items, notes...
                $order_number = 'ORD-' . date('Ymd-His') . '-' . $_SESSION['user_id'];
                Database::execute(
                    "INSERT INTO orders (
                        order_number, user_id, total_amount, total_items, status, 
                        payment_method, shipping_address, shipping_phone, notes, created_at
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $order_number,
                        $_SESSION['user_id'],
                        $totalAmount,
                        $totalItems,
                        $orderStatus,
                        $legacyPaymentMethod,
                        $shipping_address_full,
                        $shipping_phone,
                        $shipping_note
                    ]
                );
            }
            
            $order_id = Database::lastInsertId();
            
            // Kiểm tra schema bảng order_items
            $hasUnitPrice = !empty(Database::fetchAll("SHOW COLUMNS FROM order_items LIKE 'unit_price'"));

            // Create order items
            foreach ($cartItems as $item) {
                $qty       = (int)$item['cart_quantity'];
                $unitPrice = ($item['price_type'] === 'free') ? 0 : (float)$item['sale_price'];
                $itemTotal = $unitPrice * $qty;
                
                if ($hasUnitPrice) {
                    // Schema mới: unit_price + total_price
                    Database::execute(
                        "INSERT INTO order_items (order_id, item_id, item_name, quantity, unit_price, total_price, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, NOW())",
                        [
                            $order_id,
                            $item['item_id'],
                            $item['item_name'],
                            $qty,
                            $unitPrice,
                            $itemTotal
                        ]
                    );
                } else {
                    // Schema cũ: price, price_type, subtotal
                    Database::execute(
                        "INSERT INTO order_items (order_id, item_id, item_name, quantity, price, price_type, subtotal, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                        [
                            $order_id,
                            $item['item_id'],
                            $item['item_name'],
                            $qty,
                            $unitPrice,
                            $item['price_type'],
                            $itemTotal
                        ]
                    );
                }
                
                // Update inventory (guard against oversell)
                $updateInventoryStmt = Database::execute(
                    "UPDATE inventory
                     SET quantity = quantity - ?
                     WHERE item_id = ? AND status = 'available' AND quantity >= ?",
                    [$qty, $item['item_id'], $qty]
                );
                if ($updateInventoryStmt->rowCount() !== 1) {
                    throw new Exception('So luong ton kho khong du de hoan tat don hang cho item #' . $item['item_id'] . '.');
                }
            }
            
            // Clear cart
            Database::execute("DELETE FROM cart WHERE user_id = ?", [$_SESSION['user_id']]);
            
            // Log activity
            logActivity($_SESSION['user_id'], 'create_order', "Created order #$order_id");

            // Save order history entry (pending) nếu có bảng
            if ($hasOrderHistoryTable) {
                Database::execute(
                    "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
                     VALUES (?, 'pending', 'pending', 'Tạo đơn hàng mới', NOW())",
                    [$order_id]
                );
            }

            if ($payment_method === 'momo' && $totalAmount > 0) {
                $momoCfg = $paymentConfig['momo'] ?? [];
                $requiredFields = ['partner_code', 'access_key', 'secret_key', 'endpoint'];
                foreach ($requiredFields as $field) {
                    if (trim((string)($momoCfg[$field] ?? '')) === '') {
                        throw new RuntimeException('Thiếu cấu hình MoMo: ' . $field . '. Vui lòng cập nhật file config/payment.php.');
                    }
                }

                $orderId = 'ORDER' . $order_id . '_' . time();
                $requestId = $orderId;
                $requestType = trim((string)($momoCfg['request_type'] ?? 'captureWallet'));
                $redirectUrl = checkoutGetBaseUrl() . '/checkout.php';
                $ipnUrl = checkoutGetBaseUrl() . '/api/momo_checkout_notify.php';
                $extraData = base64_encode(json_encode([
                    'order_id' => $order_id,
                    'user_id' => (int)$_SESSION['user_id'],
                ], JSON_UNESCAPED_UNICODE));
                $orderInfo = 'Thanh toan don hang #' . $order_id;
                $amountStr = (string)((int)round($totalAmount));

                $signature = checkoutBuildMomoCreateSignature(
                    $momoCfg,
                    $amountStr,
                    $extraData,
                    $ipnUrl,
                    $orderId,
                    $orderInfo,
                    $redirectUrl,
                    $requestId,
                    $requestType
                );

                $payload = [
                    'partnerCode' => $momoCfg['partner_code'],
                    'accessKey' => $momoCfg['access_key'],
                    'requestId' => $requestId,
                    'amount' => $amountStr,
                    'orderId' => $orderId,
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

                $momoRes = checkoutPostJson($momoCfg['endpoint'], $payload);
                $payUrl = trim((string)($momoRes['payUrl'] ?? ''));
                $resultCode = (string)($momoRes['resultCode'] ?? '');
                if ($payUrl === '' || $resultCode !== '0') {
                    $msg = trim((string)($momoRes['message'] ?? 'Không thể tạo yêu cầu thanh toán MoMo.'));
                    if (stripos($msg, 'số tiền tối thiểu') !== false || stripos($msg, 'số tiền tối đa') !== false) {
                        $msg .= ' Tổng tiền hiện đang gửi sang MoMo là ' . number_format((int)$amountStr) . ' VND.';
                    }
                    throw new RuntimeException($msg);
                }

                Database::commit();
                header('Location: ' . $payUrl);
                exit();
            }
            
            Database::commit();
            
            // Redirect to success page
            header("Location: order-success.php?order_id=$order_id");
            exit();
            
        } catch (Exception $e) {
            try {
                Database::rollback();
            } catch (Throwable $rollbackError) {
                // Ignore rollback errors when transaction was not opened.
            }
            error_log("Checkout error: " . $e->getMessage());
            $message = trim((string)$e->getMessage());
            if ($message === '') {
                $error = 'Có lỗi xảy ra khi tạo đơn hàng. Vui lòng thử lại.';
            } else {
                $error = 'Không thể tạo đơn hàng: ' . $message;
            }
        }
    }
}

include 'includes/header.php';
?>

<style>
    :root {
        --checkout-ink: #15364a;
        --checkout-muted: #648094;
        --checkout-line: #cfe4ea;
        --checkout-surface: #ffffff;
        --checkout-bg: #eef7f8;
        --checkout-brand-700: #187f98;
        --checkout-brand-800: #12687d;
        --checkout-brand-100: #dff3f7;
        --checkout-brand-50: #f4fbfc;
        --checkout-shadow: 0 18px 44px rgba(18, 104, 125, .10);
    }

    body {
        background:
            radial-gradient(circle at top left, rgba(24, 127, 152, .14), transparent 24%),
            radial-gradient(circle at top right, rgba(12, 86, 102, .10), transparent 20%),
            linear-gradient(180deg, #f7fbfc 0%, #ecf6f8 100%);
    }

    .checkout-shell {
        padding-top: 0;
        padding-bottom: 3rem;
    }

    .checkout-hero {
        background:
            radial-gradient(circle at top left, rgba(255,255,255,.10), transparent 24%),
            linear-gradient(135deg, #1b8097 0%, #176f86 52%, #215e73 100%);
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 0;
        box-shadow: 0 24px 50px rgba(16, 93, 112, .18);
        padding: 3.4rem 3rem 3.2rem;
        position: relative;
        overflow: hidden;
        color: #fff;
        margin-left: calc(50% - 50vw);
        margin-right: calc(50% - 50vw);
    }
    .checkout-hero::before {
        content: '';
        position: absolute;
        inset: auto auto -70px -30px;
        width: 260px;
        height: 260px;
        background: radial-gradient(circle, rgba(255,255,255,.14) 0%, rgba(255,255,255,0) 72%);
        pointer-events: none;
    }
    .checkout-hero::after {
        content: '';
        position: absolute;
        top: -90px;
        right: -40px;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,.10) 0%, rgba(255,255,255,0) 70%);
        pointer-events: none;
    }
    .checkout-hero-inner {
        position: relative;
        z-index: 1;
        max-width: 1480px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        gap: 2rem;
    }
    .checkout-hero-icon {
        width: 110px;
        height: 110px;
        border-radius: 28px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(180deg, rgba(255,255,255,.16) 0%, rgba(255,255,255,.08) 100%);
        border: 1px solid rgba(255,255,255,.18);
        backdrop-filter: blur(4px);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.16), 0 20px 34px rgba(11, 57, 69, .18);
        flex-shrink: 0;
    }
    .checkout-hero-icon i {
        font-size: 3.4rem;
        line-height: 1;
        color: #fff;
    }
    .checkout-hero-content {
        min-width: 0;
    }
    .checkout-hero h1 {
        margin: 0;
        font-size: clamp(2.5rem, 5vw, 4.8rem);
        line-height: .98;
        font-weight: 900;
        letter-spacing: -.045em;
        color: #fff;
        max-width: 760px;
    }
    .checkout-hero p {
        margin: 1rem 0 0;
        font-size: clamp(1.05rem, 1.5vw, 1.28rem);
        color: rgba(255,255,255,.9);
        max-width: 980px;
        line-height: 1.45;
    }
    .checkout-hero-chips {
        display: flex;
        flex-wrap: wrap;
        gap: .9rem;
        margin-top: 1.55rem;
    }
    .checkout-hero-chip {
        display: inline-flex;
        align-items: center;
        gap: .65rem;
        min-height: 56px;
        padding: 0 1.4rem;
        border-radius: 999px;
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.28);
        color: #fff;
        font-weight: 800;
        font-size: clamp(.95rem, 1.1vw, 1.05rem);
        letter-spacing: -.01em;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.10);
        backdrop-filter: blur(3px);
    }
    .checkout-hero-chip i {
        font-size: 1.1rem;
    }

    .checkout-card,
    .summary-card {
        background: rgba(255,255,255,.98);
        border: 1px solid var(--checkout-line);
        border-radius: 24px;
        box-shadow: var(--checkout-shadow);
        overflow: hidden;
        position: relative;
    }
    .checkout-card::before,
    .summary-card::before {
        content: '';
        position: absolute;
        inset: 0 0 auto 0;
        height: 4px;
        background: linear-gradient(90deg, var(--checkout-brand-700), #4db4ca);
    }
    .checkout-card-header,
    .summary-card-header {
        display: flex;
        align-items: center;
        gap: .8rem;
        padding: 1rem 1.2rem;
        border-bottom: 1px solid var(--checkout-line);
        background: linear-gradient(180deg, #fbfeff 0%, #eff8fa 100%);
    }
    .checkout-card-header i,
    .summary-card-header i {
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: linear-gradient(145deg, var(--checkout-brand-700), var(--checkout-brand-800));
        color: #fff;
        font-size: 1rem;
        box-shadow: 0 10px 20px rgba(24,127,152,.18);
    }
    .checkout-card-header h5,
    .summary-card-header h5 {
        margin: 0;
        font-weight: 800;
        color: var(--checkout-ink);
    }
    .checkout-card-body,
    .summary-card-body {
        padding: 1.25rem;
    }

    .checkout-section-title {
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--checkout-brand-700);
        font-weight: 800;
        margin: 0 0 .9rem;
    }

    .form-label {
        color: var(--checkout-ink);
        font-weight: 700;
        font-size: .92rem;
        margin-bottom: .45rem;
    }
    .form-control,
    .form-select {
        min-height: 52px;
        border-radius: 16px;
        border: 1.5px solid var(--checkout-line);
        background: #f4fbfc;
        color: var(--checkout-ink);
        padding: .75rem 1.1rem;
        box-shadow: none;
        transition: all .2s ease;
    }
    textarea.form-control {
        min-height: 120px;
    }
    .form-control:focus,
    .form-select:focus {
        border-color: var(--checkout-brand-700);
        box-shadow: 0 0 0 4px rgba(24,127,152,.15);
        background: #fff;
    }
    .address-select {
        background-color: #eff8fa;
        font-weight: 500;
    }
    .form-text {
        color: var(--checkout-muted);
        font-size: .82rem;
        margin-top: .45rem;
    }

    .checkout-alert {
        border: 1px solid #f3c2c7;
        border-radius: 16px;
        background: #fff5f6;
        color: #a7384a;
        padding: .9rem 1rem;
        margin-bottom: 1rem;
    }

    .payment-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .9rem;
    }
    .payment-option {
        position: relative;
        display: flex;
        align-items: center;
        gap: .8rem;
        min-height: 86px;
        padding: 1rem 1rem 1rem 3rem;
        border-radius: 18px;
        border: 1.5px solid var(--checkout-line);
        background: linear-gradient(180deg, #ffffff 0%, #f7fcfd 100%);
        cursor: pointer;
        transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
    }
    .payment-option:hover {
        transform: translateY(-2px);
        border-color: rgba(24,127,152,.32);
        box-shadow: 0 12px 24px rgba(24,127,152,.10);
    }
    .payment-option .form-check-input {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        margin: 0;
        width: 1.15rem;
        height: 1.15rem;
        accent-color: var(--checkout-brand-700);
    }
    .payment-option-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(145deg, var(--checkout-brand-700), var(--checkout-brand-800));
        color: #fff;
        font-size: 1.2rem;
        box-shadow: 0 10px 20px rgba(24,127,152,.18);
        flex-shrink: 0;
    }
    .payment-option-title {
        color: var(--checkout-ink);
        font-weight: 800;
        margin: 0 0 .18rem;
    }
    .payment-option-sub {
        color: var(--checkout-muted);
        font-size: .84rem;
        margin: 0;
    }
    .payment-option:has(.form-check-input:checked) {
        border-color: var(--checkout-brand-700);
        background: linear-gradient(180deg, #f7feff 0%, #edf9fb 100%);
        box-shadow: 0 14px 28px rgba(24,127,152,.14);
    }
    .payment-suboptions {
        grid-column: 1 / -1;
        display: none;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .7rem;
        margin-top: -.2rem;
        padding: .8rem;
        border: 1px dashed var(--checkout-line);
        border-radius: 16px;
        background: linear-gradient(180deg, #f9fdfe 0%, #f2fafb 100%);
    }
    .payment-suboptions.is-open {
        display: grid;
    }
    .payment-suboption {
        position: relative;
        display: flex;
        align-items: center;
        gap: .65rem;
        min-height: 60px;
        padding: .7rem .8rem .7rem 2.4rem;
        border-radius: 14px;
        border: 1.5px solid #d7e9ee;
        background: #fff;
        cursor: pointer;
        transition: border-color .16s ease, box-shadow .16s ease, transform .16s ease;
    }
    .payment-suboption:hover {
        border-color: rgba(24,127,152,.38);
        transform: translateY(-1px);
    }
    .payment-suboption .form-check-input {
        position: absolute;
        left: .75rem;
        top: 50%;
        transform: translateY(-50%);
        margin: 0;
        width: 1rem;
        height: 1rem;
        accent-color: var(--checkout-brand-700);
    }
    .payment-suboption-icon {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        flex-shrink: 0;
        font-size: .95rem;
        font-weight: 800;
    }
    .payment-suboption-icon.zalo {
        background: linear-gradient(145deg, #0a7cff, #1364c8);
    }
    .payment-suboption-icon.momo {
        background: linear-gradient(145deg, #b10472, #8b055a);
    }
    .payment-suboption-title {
        color: var(--checkout-ink);
        font-weight: 800;
        margin: 0;
        line-height: 1.2;
        font-size: .9rem;
    }
    .payment-suboption-sub {
        color: var(--checkout-muted);
        margin: .1rem 0 0;
        font-size: .78rem;
    }
    .payment-suboption:has(.form-check-input:checked) {
        border-color: var(--checkout-brand-700);
        box-shadow: 0 10px 18px rgba(24,127,152,.14);
        background: linear-gradient(180deg, #f8fdff 0%, #eef8fb 100%);
    }

    .checkout-submit {
        min-height: 54px;
        border: 0;
        border-radius: 16px;
        background: linear-gradient(145deg, var(--checkout-brand-700), var(--checkout-brand-800));
        color: #fff;
        font-weight: 800;
        letter-spacing: .01em;
        box-shadow: 0 16px 26px rgba(24,127,152,.24);
        transition: transform .16s ease, box-shadow .16s ease;
    }
    .checkout-submit:hover,
    .checkout-submit:focus {
        transform: translateY(-2px);
        box-shadow: 0 18px 32px rgba(24,127,152,.28);
        color: #fff;
    }

    .summary-card {
        position: sticky;
        top: 100px;
    }
    .summary-list {
        display: flex;
        flex-direction: column;
        gap: .75rem;
        margin-bottom: 1rem;
    }
    .summary-item {
        display: grid;
        grid-template-columns: 54px minmax(0, 1fr) auto;
        gap: .8rem;
        align-items: center;
        padding: .75rem;
        border-radius: 18px;
        border: 1px solid #e2eff2;
        background: linear-gradient(180deg, #fff 0%, #f9fcfd 100%);
    }
    .summary-thumb {
        width: 54px;
        height: 54px;
        object-fit: cover;
        border-radius: 14px;
        border: 1px solid rgba(24,127,152,.10);
        background: #fff;
    }
    .summary-name {
        font-size: .92rem;
        font-weight: 700;
        color: var(--checkout-ink);
        margin: 0 0 .15rem;
    }
    .summary-meta {
        color: var(--checkout-muted);
        font-size: .82rem;
    }
    .summary-price {
        text-align: right;
        font-size: .86rem;
        font-weight: 800;
        color: var(--checkout-ink);
        white-space: nowrap;
    }
    .summary-stats {
        border-top: 1px dashed var(--checkout-line);
        border-bottom: 1px dashed var(--checkout-line);
        padding: .95rem 0;
        margin: 1rem 0;
    }
    .summary-row {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        color: var(--checkout-muted);
        margin-bottom: .55rem;
        font-size: .92rem;
    }
    .summary-row:last-child {
        margin-bottom: 0;
    }
    .summary-row strong,
    .summary-row span:last-child {
        color: var(--checkout-ink);
    }
    .summary-row.is-total {
        font-size: 1.05rem;
        font-weight: 800;
        color: var(--checkout-ink);
        margin-top: .8rem;
        padding-top: .8rem;
        border-top: 1px solid var(--checkout-line);
    }
    .summary-row.is-total span:last-child {
        color: var(--checkout-brand-700);
        font-size: 1.25rem;
        font-weight: 900;
    }
    .trust-box {
        padding: 1rem 1rem 1rem 1.05rem;
        border-radius: 20px;
        background: linear-gradient(180deg, #f4fbfc 0%, #ebf7f9 100%);
        border: 1px solid var(--checkout-line);
    }
    .trust-box h6 {
        color: var(--checkout-brand-700);
        margin: 0 0 .7rem;
        font-weight: 800;
    }
    .trust-list {
        display: grid;
        gap: .45rem;
        color: var(--checkout-muted);
        font-size: .84rem;
    }
    .trust-list span {
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    .trust-list i {
        color: var(--checkout-brand-700);
    }

    @media (max-width: 991.98px) {
        .checkout-shell {
            padding-top: 0;
        }
        .checkout-hero {
            padding: 2.4rem 1.4rem 2.2rem;
        }
        .checkout-hero-inner {
            gap: 1.2rem;
        }
        .checkout-hero-icon {
            width: 84px;
            height: 84px;
            border-radius: 22px;
        }
        .checkout-hero-icon i {
            font-size: 2.6rem;
        }
        .summary-card {
            position: static;
            top: auto;
            margin-top: 1rem;
        }
    }

    @media (max-width: 767.98px) {
        .checkout-hero {
            padding: 2rem 1rem 1.9rem;
        }
        .checkout-hero-inner {
            align-items: flex-start;
            gap: 1rem;
        }
        .checkout-hero-icon {
            width: 76px;
            height: 76px;
            border-radius: 20px;
        }
        .checkout-hero-icon i {
            font-size: 2.2rem;
        }
        .checkout-hero p {
            font-size: .95rem;
        }
        .checkout-hero-chips {
            gap: .7rem;
            margin-top: 1.15rem;
        }
        .checkout-hero-chip {
            min-height: 48px;
            padding: 0 1rem;
            font-size: .92rem;
        }
        .checkout-card-body,
        .summary-card-body {
            padding: 1rem;
        }
        .payment-grid {
            grid-template-columns: 1fr;
        }
        .payment-suboptions {
            grid-template-columns: 1fr;
        }
        .summary-item {
            grid-template-columns: 46px minmax(0, 1fr);
        }
        .summary-price {
            grid-column: 2;
            text-align: left;
            padding-top: .15rem;
        }
    }
</style>

<!-- Main Content -->
<div class="container checkout-shell">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="checkout-hero">
                <div class="checkout-hero-inner">
                    <div class="checkout-hero-icon">
                        <i class="bi bi-heart-fill"></i>
                    </div>
                    <div class="checkout-hero-content">
                        <h1>Thanh toán cho Goodwill Vietnam</h1>
                        <p>Hoàn tất đơn hàng nhanh chóng, minh bạch và đồng bộ với trải nghiệm hiện đại của Goodwill Vietnam.</p>
                        <div class="checkout-hero-chips">
                            <span class="checkout-hero-chip"><i class="bi bi-shield-check"></i>Minh bạch</span>
                            <span class="checkout-hero-chip"><i class="bi bi-lightning-charge"></i>Xử lý nhanh</span>
                            <span class="checkout-hero-chip"><i class="bi bi-geo-alt"></i>Hỗ trợ toàn quốc</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Checkout Form -->
        <div class="col-lg-8">
            <div class="checkout-card">
                <div class="checkout-card-header">
                    <i class="bi bi-person-lines-fill"></i>
                    <h5>Thông tin giao hàng</h5>
                </div>
                <div class="checkout-card-body">
                    <?php if ($error): ?>
                        <div class="checkout-alert" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <!-- Shipping Information -->
                        <div class="checkout-section-title">Người nhận và địa chỉ</div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="shipping_name" class="form-label">Họ tên người nhận *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="shipping_name" 
                                       name="shipping_name" 
                                       value="<?php echo htmlspecialchars($shipping_name ?: $user['name']); ?>"
                                       required>
                                <div class="invalid-feedback">
                                    Vui lòng nhập họ tên người nhận.
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="shipping_phone" class="form-label">Số điện thoại *</label>
                                <input type="tel" 
                                       class="form-control" 
                                       id="shipping_phone" 
                                       name="shipping_phone" 
                                       value="<?php echo htmlspecialchars($shipping_phone ?: $user['phone']); ?>"
                                       required>
                                <div class="invalid-feedback">
                                    Vui lòng nhập số điện thoại.
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Thành phố *</label>
                                    <select class="form-select address-select" id="shipping_city" name="shipping_city" required data-selected="<?php echo htmlspecialchars($_POST['shipping_city'] ?? ''); ?>">
                                        <option value="">-- Chọn Thành phố --</option>
                                    </select>
                                    <div class="invalid-feedback">Vui lòng chọn Thành phố</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Quận/Huyện *</label>
                                    <select class="form-select address-select" id="shipping_district" name="shipping_district" required data-selected="<?php echo htmlspecialchars($_POST['shipping_district'] ?? ''); ?>" disabled>
                                        <option value="">-- Chọn Quận/Huyện --</option>
                                    </select>
                                    <div class="invalid-feedback">Vui lòng chọn Quận/Huyện</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Phường/Xã *</label>
                                    <select class="form-select address-select" id="shipping_ward" name="shipping_ward" required data-selected="<?php echo htmlspecialchars($_POST['shipping_ward'] ?? ''); ?>" disabled>
                                        <option value="">-- Chọn Phường/Xã --</option>
                                    </select>
                                    <div class="invalid-feedback">Vui lòng chọn Phường/Xã</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="shipping_address" class="form-label">Địa chỉ giao hàng *</label>
                                <textarea class="form-control" 
                                          id="shipping_address" 
                                          name="shipping_address" 
                                          rows="3" 
                                          placeholder="Nhập địa chỉ chi tiết (số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố)"
                                          required><?php echo htmlspecialchars($shipping_address ?: $user['address']); ?></textarea>
                                <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i> Nhập đầy đủ địa chỉ để hệ thống giao hàng chính xác.</div>
                                <div class="invalid-feedback">
                                    Vui lòng nhập địa chỉ giao hàng.
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="shipping_note" class="form-label">Ghi chú giao hàng</label>
                            <textarea class="form-control" 
                                      id="shipping_note" 
                                      name="shipping_note" 
                                      rows="2" 
                                      placeholder="Ghi chú thêm cho đơn hàng (tùy chọn)"><?php echo htmlspecialchars($shipping_note); ?></textarea>
                        </div>

                        <!-- Payment Method -->
                        <div class="mb-4">
                            <div class="checkout-section-title">Phương thức thanh toán</div>
                            <div class="payment-grid">
                                <label class="payment-option" for="cod">
                                    <input class="form-check-input" 
                                           type="radio" 
                                           name="payment_method" 
                                           id="cod" 
                                           value="cod"
                                           <?php echo ($payment_method === 'cod' || empty($payment_method)) ? 'checked' : ''; ?>>
                                    <span class="payment-option-icon"><i class="bi bi-cash-coin"></i></span>
                                    <span>
                                        <span class="payment-option-title d-block">Thanh toán khi nhận hàng</span>
                                        <span class="payment-option-sub d-block">Thanh toán trực tiếp cho người giao hàng khi nhận đơn.</span>
                                    </span>
                                </label>
                                <label class="payment-option" for="bank_transfer">
                                    <input class="form-check-input" 
                                           type="radio" 
                                           name="payment_method" 
                                           id="bank_transfer" 
                                           value="bank_transfer"
                                           <?php echo in_array($payment_method, ['bank_transfer', 'momo', 'zalopay'], true) ? 'checked' : ''; ?>>
                                    <span class="payment-option-icon"><i class="bi bi-bank"></i></span>
                                    <span>
                                        <span class="payment-option-title d-block">Chuyển khoản ngân hàng</span>
                                        <span class="payment-option-sub d-block">Hoàn tất thanh toán online để xử lý đơn nhanh hơn.</span>
                                    </span>
                                </label>
                                <div class="payment-suboptions" id="bankSuboptions" aria-hidden="true">
                                    <label class="payment-suboption" for="zalopay">
                                        <input class="form-check-input"
                                               type="radio"
                                               name="payment_method"
                                               id="zalopay"
                                               value="zalopay"
                                               <?php echo $payment_method === 'zalopay' ? 'checked' : ''; ?>>
                                        <span class="payment-suboption-icon zalo">Z</span>
                                        <span>
                                            <span class="payment-suboption-title d-block">ZaloPay</span>
                                            <span class="payment-suboption-sub d-block">Ví điện tử ZaloPay.</span>
                                        </span>
                                    </label>
                                    <label class="payment-suboption" for="momo">
                                        <input class="form-check-input"
                                               type="radio"
                                               name="payment_method"
                                               id="momo"
                                               value="momo"
                                               <?php echo $payment_method === 'momo' ? 'checked' : ''; ?>>
                                        <span class="payment-suboption-icon momo">M</span>
                                        <span>
                                            <span class="payment-suboption-title d-block">MoMo</span>
                                            <span class="payment-suboption-sub d-block">Ví điện tử MoMo.</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid">
                            <button id="submitOrderBtn" type="submit" class="btn checkout-submit btn-lg">
                                <i class="bi bi-check-circle me-2"></i>Hoan tat don hang
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="col-lg-4">
            <div class="summary-card">
                <div class="summary-card-header">
                    <i class="bi bi-receipt"></i>
                    <h5>Tóm tắt đơn hàng</h5>
                </div>
                <div class="summary-card-body">
                    <!-- Order Items -->
                    <div class="summary-list">
                        <?php foreach ($cartItems as $item): ?>
                            <?php
                            $images = json_decode($item['images'] ?? '[]', true);
                            $firstImage = !empty($images) ? resolveDonationImageUrl((string)$images[0]) : 'uploads/donations/placeholder-default.svg';
                            $itemTotal = $item['price_type'] === 'free' ? 0 : $item['sale_price'] * $item['cart_quantity'];
                            ?>
                            <div class="summary-item">
                                <img src="<?php echo htmlspecialchars($firstImage); ?>" 
                                     class="summary-thumb"
                                     alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                     onerror="this.src='uploads/donations/placeholder-default.svg'">
                                <div>
                                    <div class="summary-name"><?php echo htmlspecialchars(substr($item['item_name'], 0, 38)); ?></div>
                                    <div class="summary-meta">x<?php echo $item['cart_quantity']; ?> • <?php echo htmlspecialchars($item['category_name'] ?? 'Sản phẩm'); ?></div>
                                </div>
                                <div class="summary-price">
                                    <?php echo $item['price_type'] === 'free' ? 'Miễn phí' : number_format($itemTotal) . ' VNĐ'; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Order Totals -->
                    <div class="summary-stats">
                        <div class="summary-row">
                            <span>Tổng sản phẩm:</span>
                            <strong><?php echo $totalItems; ?> sản phẩm</strong>
                        </div>
                        <div class="summary-row">
                            <span>Sản phẩm miễn phí:</span>
                            <span><?php echo $freeItemsCount; ?> sản phẩm</span>
                        </div>
                        <div class="summary-row">
                            <span>Sản phẩm trả phí:</span>
                            <span><?php echo $paidItemsCount; ?> sản phẩm</span>
                        </div>
                        <div class="summary-row">
                            <span>Phí vận chuyển:</span>
                            <span>Miễn phí</span>
                        </div>
                        <div class="summary-row is-total">
                            <span class="fw-bold">Tổng cộng:</span>
                            <span>
                                <?php echo $totalAmount > 0 ? number_format($totalAmount) . ' VNĐ' : 'Miễn phí'; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Security Info -->
                    <div class="trust-box">
                        <h6><i class="bi bi-shield-check me-1"></i>Cam kết</h6>
                        <div class="trust-list">
                            <span><i class="bi bi-check2-circle"></i>Giao hàng tận nơi miễn phí</span>
                            <span><i class="bi bi-check2-circle"></i>Kiểm tra hàng trước khi thanh toán</span>
                            <span><i class="bi bi-check2-circle"></i>Hỗ trợ đổi trả trong 7 ngày</span>
                            <span><i class="bi bi-check2-circle"></i>Bảo mật thông tin khách hàng</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Toggle submit button label based on payment method
(function() {
    const submitBtn = document.getElementById('submitOrderBtn');
    const radios = document.querySelectorAll('input[name=\"payment_method\"]');
    const bankSuboptions = document.getElementById('bankSuboptions');
    const updateLabel = () => {
        if (!submitBtn) return;
        const selectedMethod = document.querySelector('input[name=\"payment_method\"]:checked')?.value || 'cod';
        const onlineMethods = ['bank_transfer', 'momo', 'zalopay'];
        const bankSelected = onlineMethods.includes(selectedMethod);
        submitBtn.innerHTML = bankSelected
            ? '<i class="bi bi-check-circle me-2"></i>Hoan tat thanh toan'
            : '<i class="bi bi-check-circle me-2"></i>Hoan tat don hang';

        if (bankSuboptions) {
            bankSuboptions.classList.toggle('is-open', bankSelected);
            bankSuboptions.setAttribute('aria-hidden', bankSelected ? 'false' : 'true');
        }
    };
    radios.forEach(r => r.addEventListener('change', updateLabel));
    updateLabel();
})();

// Vietnamese address selects (City/District/Ward) via local JSON API
(function () {
    const cityEl = document.getElementById('shipping_city');
    const districtEl = document.getElementById('shipping_district');
    const wardEl = document.getElementById('shipping_ward');
    if (!cityEl || !districtEl || !wardEl) return;

    const API_BASE = 'api/vn-address.php';

    const clearSelect = (el, placeholder) => {
        el.innerHTML = '';
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = placeholder;
        el.appendChild(opt);
        el.value = '';
    };

    const setSelectedByValue = (el, value) => {
        if (!value) return false;
        const options = Array.from(el.options);
        const found = options.find(o => (o.value || '').trim() === value.trim());
        if (found) {
            el.value = found.value;
            return true;
        }
        return false;
    };

    const populate = (el, items, placeholder) => {
        clearSelect(el, placeholder);
        for (const item of items) {
            const opt = document.createElement('option');
            opt.value = item.name;
            opt.textContent = item.name;
            opt.dataset.code = String(item.code);
            el.appendChild(opt);
        }
    };

    const fetchJson = async (url) => {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    };

    const loadCities = async () => {
        const provinces = await fetchJson(`${API_BASE}?type=provinces`);
        populate(cityEl, provinces, '-- Chọn Thành phố --');
        cityEl.disabled = false;
    };

    const loadDistricts = async (provinceCode) => {
        const districts = await fetchJson(`${API_BASE}?type=districts&province_code=${encodeURIComponent(provinceCode)}`);
        populate(districtEl, districts, '-- Chọn Quận/Huyện --');
        districtEl.disabled = false;
    };

    const loadWards = async (districtCode) => {
        const wards = await fetchJson(`${API_BASE}?type=wards&district_code=${encodeURIComponent(districtCode)}`);
        populate(wardEl, wards, '-- Chọn Phường/Xã --');
        wardEl.disabled = false;
    };

    const getSelectedCode = (el) => {
        const opt = el.options[el.selectedIndex];
        return opt ? (opt.dataset.code || '') : '';
    };

    const init = async () => {
        clearSelect(districtEl, '-- Chọn Quận/Huyện --');
        clearSelect(wardEl, '-- Chọn Phường/Xã --');
        districtEl.disabled = true;
        wardEl.disabled = true;

        try {
            await loadCities();
        } catch (e) {
            console.error('Failed to load provinces:', e);
            cityEl.disabled = false;
            return;
        }

        const selectedCity = cityEl.dataset.selected || '';
        const selectedDistrict = districtEl.dataset.selected || '';
        const selectedWard = wardEl.dataset.selected || '';

        if (setSelectedByValue(cityEl, selectedCity)) {
            const pCode = getSelectedCode(cityEl);
            if (pCode) {
                try {
                    await loadDistricts(pCode);
                    if (setSelectedByValue(districtEl, selectedDistrict)) {
                        const dCode = getSelectedCode(districtEl);
                        if (dCode) {
                            await loadWards(dCode);
                            setSelectedByValue(wardEl, selectedWard);
                        }
                    }
                } catch (e) {
                    console.error('Failed to restore address selects:', e);
                }
            }
        }
    };

    cityEl.addEventListener('change', async () => {
        clearSelect(districtEl, '-- Chọn Quận/Huyện --');
        clearSelect(wardEl, '-- Chọn Phường/Xã --');
        districtEl.disabled = true;
        wardEl.disabled = true;

        const provinceCode = getSelectedCode(cityEl);
        if (!provinceCode) return;

        try {
            await loadDistricts(provinceCode);
        } catch (e) {
            console.error('Failed to load districts:', e);
        }
    });

    districtEl.addEventListener('change', async () => {
        clearSelect(wardEl, '-- Chọn Phường/Xã --');
        wardEl.disabled = true;

        const districtCode = getSelectedCode(districtEl);
        if (!districtCode) return;

        try {
            await loadWards(districtCode);
        } catch (e) {
            console.error('Failed to load wards:', e);
        }
    });

    init();
})();

</script>

<?php include 'includes/footer.php'; ?>
