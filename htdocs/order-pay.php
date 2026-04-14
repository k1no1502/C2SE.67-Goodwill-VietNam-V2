<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

function orderPayGetBaseUrl(): string
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function orderPayPostJson(string $url, array $payload): array
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

function orderPayBuildMomoCreateSignature(array $momo, string $amount, string $extraData, string $ipnUrl, string $orderId, string $orderInfo, string $redirectUrl, string $requestId, string $requestType): string
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

$orderId = (int)($_GET['id'] ?? 0);
$method = strtolower(trim((string)($_GET['method'] ?? 'momo')));

if ($orderId <= 0) {
    header('Location: my-orders.php');
    exit;
}

if ($method !== 'momo') {
    header('Location: order-detail.php?id=' . $orderId . '&pay_error=' . urlencode('Hiện tại chỉ hỗ trợ thanh toán lại bằng MoMo.'));
    exit;
}

$order = Database::fetch('SELECT * FROM orders WHERE order_id = ? AND user_id = ?', [$orderId, (int)$_SESSION['user_id']]);
if (!$order) {
    header('Location: my-orders.php');
    exit;
}

$orderStatus = strtolower(trim((string)($order['status'] ?? '')));
if (in_array($orderStatus, ['cancelled', 'completed'], true)) {
    header('Location: order-detail.php?id=' . $orderId . '&pay_error=' . urlencode('Đơn hàng hiện không thể thanh toán lại.'));
    exit;
}

if ((float)($order['total_amount'] ?? 0) <= 0) {
    header('Location: order-detail.php?id=' . $orderId . '&pay_error=' . urlencode('Đơn hàng không có số tiền cần thanh toán.'));
    exit;
}

if (array_key_exists('payment_status', $order) && strtolower((string)$order['payment_status']) === 'paid') {
    header('Location: order-detail.php?id=' . $orderId . '&pay_error=' . urlencode('Đơn hàng này đã được thanh toán.'));
    exit;
}

$paymentConfigPath = __DIR__ . '/config/payment.php';
$paymentConfig = file_exists($paymentConfigPath) ? require $paymentConfigPath : [];
$momoCfg = $paymentConfig['momo'] ?? [];
$requiredFields = ['partner_code', 'access_key', 'secret_key', 'endpoint'];

foreach ($requiredFields as $field) {
    if (trim((string)($momoCfg[$field] ?? '')) === '') {
        header('Location: order-detail.php?id=' . $orderId . '&pay_error=' . urlencode('Thiếu cấu hình MoMo: ' . $field . '.'));
        exit;
    }
}

try {
    $gatewayOrderId = 'ORDER' . $orderId . '_REPAY_' . time();
    $requestId = $gatewayOrderId;
    $requestType = trim((string)($momoCfg['request_type'] ?? 'captureWallet'));
    $redirectUrl = orderPayGetBaseUrl() . '/checkout.php';
    $ipnUrl = orderPayGetBaseUrl() . '/api/momo_checkout_notify.php';

    $extraData = base64_encode(json_encode([
        'order_id' => $orderId,
        'user_id' => (int)$_SESSION['user_id'],
    ], JSON_UNESCAPED_UNICODE));

    $amountStr = (string)((int)round((float)$order['total_amount']));
    $orderInfo = 'Thanh toan lai don hang #' . $orderId;

    $signature = orderPayBuildMomoCreateSignature(
        $momoCfg,
        $amountStr,
        $extraData,
        $ipnUrl,
        $gatewayOrderId,
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
        'orderId' => $gatewayOrderId,
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

    $momoRes = orderPayPostJson($momoCfg['endpoint'], $payload);
    $payUrl = trim((string)($momoRes['payUrl'] ?? ''));
    $resultCode = (string)($momoRes['resultCode'] ?? '');
    if ($payUrl === '' || $resultCode !== '0') {
        $msg = trim((string)($momoRes['message'] ?? 'Không thể tạo yêu cầu thanh toán MoMo.'));
        if (stripos($msg, 'số tiền tối thiểu') !== false || stripos($msg, 'số tiền tối đa') !== false) {
            $msg .= ' Tổng tiền hiện đang gửi sang MoMo là ' . number_format((int)$amountStr) . ' VND.';
        }
        throw new RuntimeException($msg);
    }

    header('Location: ' . $payUrl);
    exit;
} catch (Throwable $e) {
    header('Location: order-detail.php?id=' . $orderId . '&pay_error=' . urlencode('Không thể tạo thanh toán: ' . $e->getMessage()));
    exit;
}
