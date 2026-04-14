<?php
header('Content-Type: application/json; charset=UTF-8');
http_response_code(200);

require_once __DIR__ . '/../config/database.php';

$paymentConfigPath = __DIR__ . '/../config/payment.php';
$paymentConfig = file_exists($paymentConfigPath) ? require $paymentConfigPath : [];
$momoCfg = $paymentConfig['momo'] ?? [];
$secretKey = trim((string)($momoCfg['secret_key'] ?? ''));

$rawBody = file_get_contents('php://input') ?: '';
$data = json_decode($rawBody, true);
if (!is_array($data)) {
    $data = $_POST;
}
if (!is_array($data)) {
    $data = [];
}

$required = ['accessKey', 'amount', 'extraData', 'message', 'orderId', 'orderInfo', 'orderType', 'partnerCode', 'payType', 'requestId', 'responseTime', 'resultCode', 'transId', 'signature'];
foreach ($required as $key) {
    if (!array_key_exists($key, $data)) {
        echo json_encode([
            'resultCode' => 99,
            'message' => 'Missing field: ' . $key,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($secretKey === '') {
    echo json_encode([
        'resultCode' => 99,
        'message' => 'Missing momo secret_key config',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawHash = 'accessKey=' . $data['accessKey']
    . '&amount=' . $data['amount']
    . '&extraData=' . $data['extraData']
    . '&message=' . $data['message']
    . '&orderId=' . $data['orderId']
    . '&orderInfo=' . $data['orderInfo']
    . '&orderType=' . $data['orderType']
    . '&partnerCode=' . $data['partnerCode']
    . '&payType=' . $data['payType']
    . '&requestId=' . $data['requestId']
    . '&responseTime=' . $data['responseTime']
    . '&resultCode=' . $data['resultCode']
    . '&transId=' . $data['transId'];

$partnerSignature = hash_hmac('sha256', $rawHash, $secretKey);
if (!hash_equals($partnerSignature, (string)$data['signature'])) {
    echo json_encode([
        'resultCode' => 97,
        'message' => 'Invalid signature',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderId = (string)($data['orderId'] ?? '');
$checkoutOrderId = 0;
if (preg_match('/^ORDER(\d+)_/i', $orderId, $m)) {
    $checkoutOrderId = (int)$m[1];
}

if ($checkoutOrderId <= 0) {
    $extraData = trim((string)($data['extraData'] ?? ''));
    if ($extraData !== '') {
        $decoded = base64_decode($extraData, true);
        if ($decoded !== false) {
            $arr = json_decode($decoded, true);
            if (is_array($arr) && !empty($arr['order_id'])) {
                $checkoutOrderId = (int)$arr['order_id'];
            }
        }
    }
}

if ($checkoutOrderId <= 0) {
    echo json_encode([
        'resultCode' => 98,
        'message' => 'Cannot map order to checkout order',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$order = Database::fetch('SELECT order_id, status, shipping_last_mile_status FROM orders WHERE order_id = ?', [$checkoutOrderId]);
if (!$order) {
    echo json_encode([
        'resultCode' => 98,
        'message' => 'Order not found',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$paymentStatusColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'payment_status'");
$paymentReferenceColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'payment_reference'");
$shippingLastMileColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'shipping_last_mile_status'");
$shippingLastMileUpdatedAtColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'shipping_last_mile_updated_at'");
$updatedAtColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'updated_at'");
$statusColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'status'");

$setClauses = [];
$params = [];

if ((string)($data['resultCode'] ?? '') === '0') {
    if (!empty($paymentStatusColumn['Type']) && strpos($paymentStatusColumn['Type'], 'enum(') === 0) {
        preg_match_all("/'([^']+)'/", $paymentStatusColumn['Type'], $paymentStatusMatches);
        $allowedPaymentStatuses = $paymentStatusMatches[1] ?? [];
        if (in_array('paid', $allowedPaymentStatuses, true)) {
            $setClauses[] = 'payment_status = ?';
            $params[] = 'paid';
        }
    }

    $setClauses[] = 'payment_method = ?';
    $params[] = 'momo';

    if (!empty($paymentReferenceColumn)) {
        $setClauses[] = 'payment_reference = ?';
        $params[] = 'MOMO-' . trim((string)$data['transId']);
    }

    if (!empty($statusColumn['Type']) && strpos($statusColumn['Type'], 'enum(') === 0) {
        preg_match_all("/'([^']+)'/", $statusColumn['Type'], $orderStatusMatches);
        $allowedStatuses = $orderStatusMatches[1] ?? [];
        $currentStatus = strtolower((string)($order['status'] ?? ''));
        if ($currentStatus === 'pending') {
            if (in_array('confirmed', $allowedStatuses, true)) {
                $setClauses[] = 'status = ?';
                $params[] = 'confirmed';
            } elseif (in_array('processing', $allowedStatuses, true)) {
                $setClauses[] = 'status = ?';
                $params[] = 'processing';
            }
        }
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
}

if (!empty($updatedAtColumn)) {
    $setClauses[] = 'updated_at = NOW()';
}

if (!empty($setClauses)) {
    $params[] = $checkoutOrderId;
    Database::execute('UPDATE orders SET ' . implode(', ', $setClauses) . ' WHERE order_id = ?', $params);
}

echo json_encode([
    'resultCode' => 0,
    'message' => 'IPN processed',
], JSON_UNESCAPED_UNICODE);
