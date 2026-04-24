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
$transId = 0;
if (preg_match('/^DONATE(\d+)_/i', $orderId, $m)) {
    $transId = (int)$m[1];
}

if ($transId <= 0) {
    $extraData = trim((string)($data['extraData'] ?? ''));
    if ($extraData !== '') {
        $decoded = base64_decode($extraData, true);
        if ($decoded !== false) {
            $arr = json_decode($decoded, true);
            if (is_array($arr) && !empty($arr['trans_id'])) {
                $transId = (int)$arr['trans_id'];
            }
        }
    }
}

if ($transId <= 0) {
    echo json_encode([
        'resultCode' => 98,
        'message' => 'Cannot map order to transaction',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$tx = Database::fetch('SELECT trans_id, status FROM transactions WHERE trans_id = ?', [$transId]);
if (!$tx) {
    echo json_encode([
        'resultCode' => 98,
        'message' => 'Transaction not found',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)$data['resultCode'] === '0') {
    Database::execute(
        'UPDATE transactions SET status = ?, payment_reference = ?, updated_at = NOW() WHERE trans_id = ?',
        ['completed', 'MOMO-' . trim((string)$data['transId']), $transId]
    );
} else {
    Database::execute(
        'UPDATE transactions SET status = ?, updated_at = NOW() WHERE trans_id = ? AND status = ?',
        ['cancelled', $transId, 'pending']
    );
}

echo json_encode([
    'resultCode' => 0,
    'message' => 'IPN processed',
], JSON_UNESCAPED_UNICODE);
