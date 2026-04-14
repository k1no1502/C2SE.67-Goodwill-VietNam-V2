<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$campaign_id = (int)($_GET['campaign_id'] ?? 0);

if ($campaign_id <= 0) {
    header('Location: campaigns.php');
    exit();
}

// Get campaign
$campaign = Database::fetch(
    "SELECT * FROM campaigns WHERE campaign_id = ? AND status = 'active'",
    [$campaign_id]
);

if (!$campaign) {
    setFlashMessage('error', 'Chi?n d?ch khï¿½ng t?n t?i ho?c dï¿½ k?t thï¿½c.');
    header('Location: campaigns.php');
    exit();
}

// Get campaign items
$items = Database::fetchAll(
    "SELECT * FROM v_campaign_items_progress WHERE campaign_id = ? ORDER BY progress_percentage ASC",
    [$campaign_id]
);

// Get categories
$categories = Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order, name");

$success = '';
$error = '';
$completeMessage = "\u{0110}\u{00E3} \u{0111}\u{1EE7} quy\u{00EA}n g\u{00F3}p";
$paymentConfig = [];
$paymentConfigPath = __DIR__ . '/config/payment.php';
if (file_exists($paymentConfigPath)) {
    $paymentConfig = require $paymentConfigPath;
}

$selectedDonateMode = 'campaign_item';
if (trim((string)($_POST['action'] ?? '')) === 'money_donation' || !empty($_GET['payment_success']) || !empty($_GET['payment_error'])) {
    $selectedDonateMode = 'money';
} elseif (trim((string)($_POST['donate_type'] ?? '')) === 'custom') {
    $selectedDonateMode = 'custom';
}

function campaignDonateGetBaseUrl(): string
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function campaignDonatePostJson(string $url, array $payload): array
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
        throw new RuntimeException('Cổng thanh toán trả về lỗi HTTP ' . $httpCode . ($gatewayMessage !== '' ? ': ' . $gatewayMessage : ''));
    }

    return $decoded;
}

function campaignDonateBuildMomoCreateSignature(array $momo, string $amount, string $extraData, string $ipnUrl, string $orderId, string $orderInfo, string $redirectUrl, string $requestId, string $requestType): string
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

function campaignDonateExtractTransIdFromMomoData(?string $orderId, ?string $extraData): int
{
    $orderId = (string)$orderId;
    if (preg_match('/^CDONATE(\d+)_/i', $orderId, $m)) {
        return (int)$m[1];
    }

    $extraData = trim((string)$extraData);
    if ($extraData !== '') {
        $decoded = base64_decode($extraData, true);
        if ($decoded !== false) {
            $arr = json_decode($decoded, true);
            if (is_array($arr) && !empty($arr['trans_id'])) {
                return (int)$arr['trans_id'];
            }
        }
    }

    return 0;
}

function campaignDonateVerifyMomoResponseSignature(array $payload, string $secretKey): bool
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

function campaignDonateBuildMoneyNote(int $campaignId, string $campaignName, string $message = ''): string
{
    $lines = [
        '[CAMPAIGN_MONEY_DONATION]',
        'campaign_id=' . $campaignId,
        'campaign_name=' . preg_replace('/\s+/', ' ', trim($campaignName)),
    ];

    $message = trim($message);
    if ($message !== '') {
        $lines[] = 'message=' . preg_replace('/\s+/', ' ', $message);
    }

    return implode("\n", $lines);
}

function campaignDonateExtractCampaignIdFromNote(string $note): int
{
    if (preg_match('/^campaign_id=(\d+)$/mi', $note, $matches)) {
        return (int)$matches[1];
    }
    return 0;
}

function campaignDonateNoteHasAppliedFlag(string $note): bool
{
    return strpos($note, '[CAMPAIGN_AMOUNT_APPLIED]') !== false;
}

function campaignDonateAppendAppliedFlag(string $note): string
{
    $note = rtrim($note);
    return $note === '' ? '[CAMPAIGN_AMOUNT_APPLIED]' : $note . "\n[CAMPAIGN_AMOUNT_APPLIED]";
}

function campaignDonateApplyMoneyTransaction(int $transId, string $paymentReference = ''): bool
{
    Database::beginTransaction();

    try {
        $tx = Database::fetch(
            'SELECT trans_id, amount, status, notes, payment_reference FROM transactions WHERE trans_id = ? FOR UPDATE',
            [$transId]
        );

        if (!$tx) {
            Database::rollback();
            return false;
        }

        if (strtolower((string)($tx['status'] ?? '')) !== 'completed') {
            Database::rollback();
            return false;
        }

        $note = (string)($tx['notes'] ?? '');
        $campaignId = campaignDonateExtractCampaignIdFromNote($note);
        if ($campaignId <= 0) {
            Database::rollback();
            return false;
        }

        if (!campaignDonateNoteHasAppliedFlag($note)) {
            Database::execute(
                'UPDATE campaigns SET current_amount = COALESCE(current_amount, 0) + ? WHERE campaign_id = ?',
                [(float)$tx['amount'], $campaignId]
            );
            $note = campaignDonateAppendAppliedFlag($note);
        }

        $setClauses = ['notes = ?', 'updated_at = NOW()'];
        $params = [$note];
        if ($paymentReference !== '') {
            $setClauses[] = 'payment_reference = ?';
            $params[] = $paymentReference;
        }
        $params[] = $transId;

        Database::execute('UPDATE transactions SET ' . implode(', ', $setClauses) . ' WHERE trans_id = ?', $params);
        Database::commit();
        return true;
    } catch (Throwable $e) {
        Database::rollback();
        throw $e;
    }
}

function campaignDonateCreateMoneyTransaction(int $userId, int $campaignId, float $amount, string $method, string $campaignName, string $message = ''): int
{
    $note = campaignDonateBuildMoneyNote($campaignId, $campaignName, $message);
    Database::execute(
        "INSERT INTO transactions (user_id, type, amount, status, payment_method, notes, created_at)
         VALUES (?, 'donation', ?, 'pending', ?, ?, NOW())",
        [$userId, $amount, $method, $note]
    );

    return (int)Database::lastInsertId();
}

function campaignDonateRedirectSuccessPage(int $campaignId, string $campaignName, string $method, int $transId): void
{
    $methodLabel = strtoupper(trim($method));
    $message = 'Thanh toán ' . ($methodLabel !== '' ? $methodLabel . ' ' : '') . 'thành công! Cảm ơn bạn đã quyên góp cho chiến dịch ' . trim($campaignName) . '.';
    $query = [
        'message' => $message,
        'method' => strtolower(trim($method)),
        'trans_id' => $transId,
        'campaign_id' => $campaignId,
        'campaign_name' => $campaignName,
        'return_to' => 'campaign-detail.php?id=' . $campaignId,
        'return_label' => 'Chi tiết chiến dịch',
    ];

    header('Location: payment-success.php?' . http_build_query($query));
    exit();
}

if (!empty($_GET['payment_success'])) {
    $method = strtolower(trim((string)($_GET['method'] ?? '')));
    $transId = (int)($_GET['trans_id'] ?? 0);
    if ($transId > 0) {
        $tx = Database::fetch('SELECT trans_id, user_id, status FROM transactions WHERE trans_id = ?', [$transId]);
        if ($tx && (int)$tx['user_id'] === (int)$_SESSION['user_id'] && strtolower((string)$tx['status']) === 'completed') {
            campaignDonateApplyMoneyTransaction($transId);
            campaignDonateRedirectSuccessPage($campaign_id, (string)($campaign['name'] ?? ''), $method, $transId);
        }
    }
    $success = 'Thanh toán thành công. Cảm ơn bạn đã quyên góp cho chiến dịch.';
}

if (!empty($_GET['payment_error'])) {
    $error = trim((string)($_GET['message'] ?? 'Thanh toán không thành công. Vui lòng thử lại.'));
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
    } elseif (!campaignDonateVerifyMomoResponseSignature($returnParams, $secretKey)) {
        $failedMessage = 'Xác thực chữ ký MoMo thất bại.';
    }

    $transId = campaignDonateExtractTransIdFromMomoData((string)($_GET['orderId'] ?? ''), (string)($_GET['extraData'] ?? ''));
    if ($failedMessage === '' && $transId <= 0) {
        $failedMessage = 'Không xác định được giao dịch thanh toán.';
    }

    if ($failedMessage === '') {
        $tx = Database::fetch('SELECT trans_id, user_id FROM transactions WHERE trans_id = ?', [$transId]);
        if (!$tx || (int)$tx['user_id'] !== (int)$_SESSION['user_id']) {
            $failedMessage = 'Không tìm thấy giao dịch hợp lệ để xác nhận thanh toán.';
        }
    }

    $baseReturnUrl = 'donate-to-campaign.php?campaign_id=' . $campaign_id;
    if ($failedMessage !== '') {
        header('Location: ' . $baseReturnUrl . '&payment_error=1&method=momo&message=' . urlencode($failedMessage));
        exit();
    }

    if ($resultCode === '0') {
        $paymentReference = 'MOMO-' . trim((string)($_GET['transId'] ?? ($_GET['orderId'] ?? '')));
        Database::execute(
            'UPDATE transactions SET status = ?, payment_reference = ?, updated_at = NOW() WHERE trans_id = ?',
            ['completed', $paymentReference, $transId]
        );
        campaignDonateApplyMoneyTransaction($transId, $paymentReference);
        campaignDonateRedirectSuccessPage($campaign_id, (string)($campaign['name'] ?? ''), 'momo', $transId);
    }

    Database::execute(
        'UPDATE transactions SET status = ?, updated_at = NOW() WHERE trans_id = ? AND status = ?',
        ['cancelled', $transId, 'pending']
    );
    $failedMessage = 'Thanh toán MoMo không thành công: ' . trim((string)($_GET['message'] ?? 'Vui lòng thử lại.'));
    header('Location: ' . $baseReturnUrl . '&payment_error=1&method=momo&message=' . urlencode($failedMessage));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string)($_POST['action'] ?? '')) === 'money_donation') {
    $amount = (float)($_POST['donate_amount'] ?? 0);
    $message = sanitize($_POST['donate_message'] ?? '');
    $method = strtolower(trim((string)($_POST['payment_method'] ?? '')));
    $allowedMethods = ['momo', 'zalopay'];
    $transId = 0;

    if ($amount < 1000) {
        $error = 'Vui lòng nhập số tiền hợp lệ (tối thiểu 1.000 VND).';
    } elseif (!in_array($method, $allowedMethods, true)) {
        $error = 'Vui lòng chọn phương thức thanh toán.';
    } else {
        try {
            $transId = campaignDonateCreateMoneyTransaction($_SESSION['user_id'], $campaign_id, $amount, $method, (string)($campaign['name'] ?? ''), $message);

            if ($method === 'momo') {
                $momoCfg = $paymentConfig['momo'] ?? [];
                $requiredFields = ['partner_code', 'access_key', 'secret_key', 'endpoint'];
                foreach ($requiredFields as $field) {
                    if (trim((string)($momoCfg[$field] ?? '')) === '') {
                        throw new RuntimeException('Thiếu cấu hình MoMo: ' . $field . '. Vui lòng cập nhật file config/payment.php.');
                    }
                }

                $orderId = 'CDONATE' . $transId . '_' . time();
                $requestId = $orderId;
                $requestType = trim((string)($momoCfg['request_type'] ?? 'captureWallet'));
                $redirectUrl = campaignDonateGetBaseUrl() . '/donate-to-campaign.php?campaign_id=' . $campaign_id;
                $ipnUrl = campaignDonateGetBaseUrl() . '/api/momo_campaign_notify.php';
                $amountStr = (string)((int)round($amount));
                $extraData = base64_encode(json_encode([
                    'trans_id' => $transId,
                    'user_id' => (int)$_SESSION['user_id'],
                    'campaign_id' => $campaign_id,
                ], JSON_UNESCAPED_UNICODE));
                $orderInfo = 'Quyen gop chien dich #' . $campaign_id . ' - GD #' . $transId;

                $signature = campaignDonateBuildMomoCreateSignature(
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

                $momoRes = campaignDonatePostJson($momoCfg['endpoint'], $payload);
                $payUrl = trim((string)($momoRes['payUrl'] ?? ''));
                $resultCode = (string)($momoRes['resultCode'] ?? '');
                if ($payUrl === '' || $resultCode !== '0') {
                    $msg = trim((string)($momoRes['message'] ?? 'Không thể tạo yêu cầu thanh toán MoMo.'));
                    if ($msg !== '' && (mb_stripos($msg, 'số tiền tối thiểu') !== false || mb_stripos($msg, 'số tiền tối đa') !== false)) {
                        $msg .= ' Số tiền hiện đang gửi sang MoMo là ' . number_format((int)$amountStr, 0, ',', '.') . ' VND.';
                    }
                    throw new RuntimeException($msg);
                }

                header('Location: ' . $payUrl);
                exit();
            }

            $returnTo = 'donate-to-campaign.php?campaign_id=' . $campaign_id;
            header('Location: sandbox_payment.php?method=' . urlencode($method) . '&trans_id=' . $transId . '&return_to=' . urlencode($returnTo));
            exit();
        } catch (Throwable $e) {
            if ($transId > 0) {
                Database::execute(
                    'UPDATE transactions SET status = ?, updated_at = NOW() WHERE trans_id = ? AND status = ?',
                    ['cancelled', $transId, 'pending']
                );
            }
            $error = 'Không thể tạo thanh toán: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string)($_POST['action'] ?? '')) !== 'money_donation') {
    $donate_type = $_POST['donate_type'] ?? 'custom'; // campaign_item | custom
    $item_name = sanitize($_POST['item_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    $unit = sanitize($_POST['unit'] ?? 'cï¿½i');
    $condition_status = sanitize($_POST['condition_status'] ?? 'good');
    $campaign_item_id = (int)($_POST['campaign_item_id'] ?? 0);
    $estimated_value = isset($_POST['estimated_value']) ? (float)$_POST['estimated_value'] : 0;
    $image_links = sanitize($_POST['image_links'] ?? '');
    
    // New fields
    $pickup_address = sanitize($_POST['pickup_address'] ?? '');
    $pickup_date = $_POST['pickup_date'] ?? '';
    $pickup_time = $_POST['pickup_time'] ?? '';
    $delivery_date = $_POST['delivery_date'] ?? '';
    $address_status = sanitize($_POST['address_status'] ?? '');
    $contact_phone = sanitize($_POST['contact_phone'] ?? '');
    $condition_detail = sanitize($_POST['condition_detail'] ?? '');
    
    // Location fields
    $pickup_city = sanitize($_POST['pickup_city'] ?? '');
    $pickup_district = sanitize($_POST['pickup_district'] ?? '');
    $pickup_ward = sanitize($_POST['pickup_ward'] ?? '');
    $product_condition = sanitize($_POST['product_condition'] ?? 'good');
    // NÃ¡ÂºÂ¿u chÃ¡Â»Ân vÃ¡ÂºÂ­t phÃ¡ÂºÂ©m chiÃ¡ÂºÂ¿n dÃ¡Â»?ch, lÃ¡ÂºÂ¥y dÃ¡Â»Â¯ liÃ¡Â»?u gÃ¡Â»?c tÃ¡Â»Â« DB Ã„?Ã¡Â»? trÃƒÂ¡nh nhÃ¡ÂºÂ­p sai
    if ($donate_type === 'campaign_item') {
        if ($campaign_item_id <= 0) {
            $error = 'Vui lï¿½ng ch?n v?t ph?m c?n quyï¿½n gï¿½p trong chi?n d?ch';
        } else {
            $campaignItem = Database::fetch(
                "SELECT item_name, category_id, unit, description 
                 FROM campaign_items 
                 WHERE item_id = ? AND campaign_id = ?",
                [$campaign_item_id, $campaign_id]
            );
            if (!$campaignItem) {
                $error = 'V?t ph?m chi?n d?ch khï¿½ng t?n t?i.';
            } else {
                $item_name = $campaignItem['item_name'];
                $category_id = (int)($campaignItem['category_id'] ?? 0);
                $unit = $campaignItem['unit'] ?: 'cï¿½i';
                $description = $campaignItem['description'] ?? '';
            }
        }
    }

    // Chu?n hï¿½a d? li?u tru?c l?i FK/NOT NULL
    $category_id = $category_id > 0 ? $category_id : null;
    $unit = $unit ?: 'cai';
    
    // Validate required fields
    if (!$error && (empty($item_name) || $quantity <= 0)) {
        $error = 'Vui lï¿½ng nh?p d?y d? thï¿½ng tin.';
    }
    
    // Validate pickup information
    if (!$error) {
        if (empty($pickup_city)) {
            $error = 'Vui lï¿½ng ch?n thï¿½nh ph?.';
        } elseif (empty($pickup_district)) {
            $error = 'Vui lï¿½ng ch?n qu?n/huy?n.';
        } elseif (empty($pickup_ward)) {
            $error = 'Vui lï¿½ng ch?n phu?ng/xï¿½.';
        } elseif (empty($pickup_address)) {
            $error = 'Vui lï¿½ng nh?p d?a ch? chi?t vï¿½ t?ng g?n.';
        } elseif (empty($pickup_date)) {
            $error = 'Vui lï¿½ng ch?n ngï¿½y l?y hï¿½ng.';
        } elseif (empty($delivery_date)) {
            $error = 'Vui lï¿½ng ch?n ngï¿½y giao hï¿½ng.';
        } elseif (empty($address_status)) {
            $error = 'Vui lï¿½ng ch?n lo?i d?a ch?.';
        } elseif (empty($contact_phone)) {
            $error = 'Vui lï¿½ng nh?p s? di?n tho?i liï¿½n h?.';
        } elseif (!preg_match('/^\d{10,}$/', preg_replace('/[^\d]/', '', $contact_phone))) {
            $error = 'S? di?n tho?i ph?i cï¿½ ï¿½t nh?t 10 ch? s?.';
        } elseif (empty($product_condition)) {
            $error = 'Vui lï¿½ng ch?n tï¿½nh tr?ng s?n ph?m.';
        }
    } 
    
    if (!$error) {
        try {
            Database::beginTransaction();
            
            // Handle image upload
            $images = [];
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                $uploadDir = 'uploads/donations/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                    if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['images']['name'][$i],
                            'type' => $_FILES['images']['type'][$i],
                            'tmp_name' => $_FILES['images']['tmp_name'][$i],
                            'error' => $_FILES['images']['error'][$i],
                            'size' => $_FILES['images']['size'][$i]
                        ];
                        
                        $uploadResult = uploadFile($file, $uploadDir);
                        if ($uploadResult['success']) {
                            $images[] = $uploadResult['filename'];
                        }
                    }
                }
            }
            
            // Insert donation
            $sql = "INSERT INTO donations (user_id, item_name, description, category_id, quantity, unit, 
                    condition_status, images, pickup_address, pickup_city, pickup_district, pickup_ward, 
                    pickup_date, pickup_time, delivery_date, address_status, contact_phone, product_condition, 
                    condition_detail, estimated_value, image_links, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW())";
            
            Database::execute($sql, [
                $_SESSION['user_id'],
                $item_name,
                $description,
                $category_id,
                $quantity,
                $unit,
                $condition_status,
                json_encode($images),
                $pickup_address,
                $pickup_city,
                $pickup_district,
                $pickup_ward,
                $pickup_date,
                $pickup_time,
                $delivery_date,
                $address_status,
                $contact_phone,
                $product_condition,
                $condition_detail,
                $estimated_value,
                $image_links
            ]);
            
            $donation_id = Database::lastInsertId();
            
            // Link donation to campaign
            $sql = "INSERT INTO campaign_donations (campaign_id, donation_id, campaign_item_id, quantity_contributed, created_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            Database::execute($sql, [
                $campaign_id,
                $donation_id,
                $campaign_item_id > 0 ? $campaign_item_id : null,
                $quantity
            ]);

            // Update requested item progress if linked
            if ($campaign_item_id > 0) {
                Database::execute(
                    "UPDATE campaign_items 
                     SET quantity_received = GREATEST(quantity_received + ?, 0)
                     WHERE item_id = ? AND campaign_id = ?",
                    [$quantity, $campaign_item_id, $campaign_id]
                );
            }

            // Sync campaign current_items with sum of received quantities
            Database::execute(
                "UPDATE campaigns c
                 SET current_items = (
                     SELECT COALESCE(SUM(quantity_received), 0)
                     FROM campaign_items
                     WHERE campaign_id = c.campaign_id
                 )
                 WHERE c.campaign_id = ?",
                [$campaign_id]
            );
            
            // Add to inventory
            if ($estimated_value <= 0) {
                $priceType = 'free';
                $salePrice = 0;
            } elseif ($estimated_value < 100000) {
                $priceType = 'cheap';
                $salePrice = $estimated_value;
            } else {
                $priceType = 'normal';
                $salePrice = $estimated_value;
            }

            Database::execute(
                "INSERT INTO inventory (donation_id, name, description, category_id, quantity, unit, 
                 condition_status, images, status, price_type, sale_price, is_for_sale, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available', ?, ?, TRUE, NOW())",
                [
                    $donation_id,
                    $item_name,
                    $description,
                    $category_id,
                    $quantity,
                    $unit,
                    $condition_status,
                    json_encode($images),
                    $priceType,
                    $salePrice
                ]
            );
            
            Database::commit();
            
            logActivity($_SESSION['user_id'], 'donate_to_campaign', "Donated to campaign #$campaign_id");
            
            $success = 'Quyï¿½n gï¿½p thï¿½nh cï¿½ng! C?m on b?n dï¿½ dï¿½ gï¿½p cho chi?n d?ch.';
            
            // Redirect after 2 seconds
            header("refresh:2;url=campaign-detail.php?id=$campaign_id");
            
        } catch (Exception $e) {
            Database::rollback();
            error_log("Donate to campaign error: " . $e->getMessage());
            $error = 'CÃƒÂ³ lÃ¡Â»?i xÃ¡ÂºÂ£y ra. Vui lÃƒÂ²ng thÃ¡Â»Â­ lÃ¡ÂºÂ¡i.';
        }
    }
}

$pageTitle = "Quyền góp cho chiến dịch"; 
include 'includes/header.php';
?>

<div class="container mt-5 mb-5">
    <div class="row">
        <div class="col-lg-8 offset-lg-2">
            <!-- Campaign Header Info -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <?php if ($campaign['image']): ?>
                                <img src="<?= htmlspecialchars($campaign['image']) ?>" alt="<?= htmlspecialchars($campaign['name']) ?>" class="img-fluid rounded" style="max-height: 100px; object-fit: cover; width: 100%;">
                            <?php else: ?>
                                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 100px;">
                                    <i class="bi bi-image text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <h4 class="card-title mb-2"><?= htmlspecialchars($campaign['name']) ?></h4>
                            <div class="progress mb-2" style="height: 20px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $campaign['progress_percentage'] ?>%;" aria-valuenow="<?= $campaign['progress_percentage'] ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?= round($campaign['progress_percentage']) ?>%
                                </div>
                            </div>
                            <small class="text-muted">
                                <?= $campaign['current_items'] ?> / <?= $campaign['target_items'] ?> vật phẩm
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Donation Type Selection -->
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-list-check"></i> Chọn hình thức góp</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <input type="radio" class="btn-check" name="donate_type_selector" id="donate_campaign" value="campaign_item" <?= $selectedDonateMode === 'campaign_item' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary flex-fill" for="donate_campaign">
                            <i class="bi bi-box-seam"></i> Góp theo vật phẩm chiến dịch
                        </label>

                        <input type="radio" class="btn-check" name="donate_type_selector" id="donate_custom" value="custom" <?= $selectedDonateMode === 'custom' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary flex-fill" for="donate_custom">
                            <i class="bi bi-plus-circle"></i> Góp vật phẩm khác
                        </label>

                        <input type="radio" class="btn-check" name="donate_type_selector" id="donate_money" value="money" <?= $selectedDonateMode === 'money' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary flex-fill" for="donate_money">
                            <i class="bi bi-cash-coin"></i> Quyên góp tiền
                        </label>
                    </div>
                </div>
            </div>

            <!-- Item Donation Form -->
            <form method="POST" enctype="multipart/form-data" id="itemDonationForm">
                <input type="hidden" name="donate_type" id="itemDonateTypeInput" value="<?= $selectedDonateMode === 'custom' ? 'custom' : 'campaign_item' ?>">

                <!-- Campaign Item Section -->
                <div id="campaignItemSection" class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-collection"></i> Vật phẩm cần góp</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($items)): ?>
                            <div class="mb-3">
                                <label for="campaign_item_id" class="form-label">Chọn vật phẩm <span class="text-danger">*</span></label>
                                <select class="form-select form-select-lg" id="campaign_item_id" name="campaign_item_id">
                                    <option value="">-- Chọn vật phẩm --</option>
                                    <?php foreach ($items as $item): ?>
                                        <option value="<?= $item['item_id'] ?>" 
                                                data-name="<?= htmlspecialchars($item['item_name']) ?>"
                                                data-category="<?= $item['category_id'] ?>"
                                                data-unit="<?= htmlspecialchars($item['unit']) ?>"
                                                data-description="<?= htmlspecialchars($item['description'] ?? '') ?>"
                                                data-image="<?= htmlspecialchars($item['image'] ?? '') ?>"
                                                data-condition="<?= htmlspecialchars($item['condition_status'] ?? 'good') ?>"
                                                data-needed="<?= $item['quantity_needed'] ?>"
                                                data-received="<?= $item['quantity_received'] ?>">
                                            <?= htmlspecialchars($item['item_name']) ?> 
                                            <span class="text-muted">(<?= $item['quantity_received'] ?>/<?= $item['quantity_needed'] ?> <?= htmlspecialchars($item['unit']) ?>)</span>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Product Information Card -->
                            <div id="productInfoCard" class="card bg-light mb-3" style="display: none;">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <img id="productImage" src="" alt="Hình ảnh sản phẩm" class="img-fluid rounded" style="max-height: 150px; object-fit: cover; width: 100%;">
                                        </div>
                                        <div class="col-md-8">
                                            <h6 id="productName" class="fw-bold mb-2"></h6>
                                            <div class="row mb-2">
                                                <div class="col-6">
                                                    <small class="text-muted">Tình trạng:</small>
                                                    <p id="productCondition" class="mb-0"><span class="badge bg-info"></span></p>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Còn cần:</small>
                                                    <p id="productNeeded" class="mb-0 fw-bold"></p>
                                                </div>
                                            </div>
                                            <small id="productDescription" class="text-muted d-block"></small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="quantity_campaign" class="form-label">Số lượng góp <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="quantity_campaign" name="quantity" min="1" value="1" required>
                                    <span class="input-group-text" id="unitDisplay">cái</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Chiến dịch này hiện không có vật phẩm cần góp cụ thể. Vui lòng chọn góp vật phẩm khác.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Custom Donation Section -->
                <div id="customDonationSection" class="card mb-4 border-0 shadow-sm" style="display: none;">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-gift"></i> Thông tin vật phẩm góp</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="item_name" class="form-label">Tên vật phẩm <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="item_name" name="item_name" placeholder="VD: Sách, Quần áo..." required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="quantity_custom" class="form-label">Số lượng <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="quantity_custom" name="quantity" min="1" value="1" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Danh mục</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">-- Chọn danh mục --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="unit" class="form-label">Đơn vị tính</label>
                                <input type="text" class="form-control" id="unit" name="unit" value="cái" placeholder="cái, bộ, chiếc...">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Mô tả chi tiết</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Mô tả về vật phẩm..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Location Information Card -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Thông tin địa chỉ lấy hàng</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="pickup_city" class="form-label">Thành phố/Tỉnh <span class="text-danger">*</span></label>
                                <select class="form-select" id="pickup_city" name="pickup_city" required>
                                    <option value="">-- Chọn --</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="pickup_district" class="form-label">Quận/Huyện <span class="text-danger">*</span></label>
                                <select class="form-select" id="pickup_district" name="pickup_district" required>
                                    <option value="">-- Chọn --</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="pickup_ward" class="form-label">Phường/Xã <span class="text-danger">*</span></label>
                                <select class="form-select" id="pickup_ward" name="pickup_ward" required>
                                    <option value="">-- Chọn --</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="pickup_address" class="form-label">Địa chỉ chi tiết <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="pickup_address" name="pickup_address" placeholder="VD: 123 Đường Abc, Tòa nhà XYZ..." required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="address_status" class="form-label">Loại địa chỉ <span class="text-danger">*</span></label>
                                <select class="form-select" id="address_status" name="address_status" required>
                                    <option value="">-- Chọn --</option>
                                    <option value="home">Nhà riêng</option>
                                    <option value="office">Cơ quan</option>
                                    <option value="shop">Cửa hàng</option>
                                    <option value="other">Khác</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact_phone" class="form-label">Số điện thoại liên hệ <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="contact_phone" name="contact_phone" placeholder="0123456789" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pickup & Delivery Dates -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-calendar-event"></i> Lịch lấy hàng</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="pickup_date" class="form-label">Ngày lấy hàng <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="pickup_date" name="pickup_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="pickup_time" class="form-label">Giờ lấy hàng</label>
                                <input type="time" class="form-control" id="pickup_time" name="pickup_time">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="delivery_date" class="form-label">Ngày giao hàng dự kiến <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="delivery_date" name="delivery_date" required>
                        </div>
                    </div>
                </div>

                <!-- Product Condition Card -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="bi bi-brightness-high"></i> Tình trạng sản phẩm</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="product_condition" class="form-label">Tình trạng <span class="text-danger">*</span></label>
                            <select class="form-select" id="product_condition" name="product_condition" required>
                                <option value="">-- Chọn --</option>
                                <option value="new">Mới 100%</option>
                                <option value="like_new">Như mới</option>
                                <option value="good">Tốt</option>
                                <option value="fair">Bình thường</option>
                                <option value="old">Cũ</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="condition_detail" class="form-label">Mô tả chi tiết tình trạng</label>
                            <textarea class="form-control" id="condition_detail" name="condition_detail" rows="2" placeholder="VD: Có vết xước nhẹ, còn hoạt động bình thường..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Images & Value Card -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="bi bi-image"></i> Hình ảnh & Giá trị</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="images" class="form-label">Tải lên hình ảnh</label>
                            <input type="file" class="form-control" id="images" name="images[]" multiple accept="image/*">
                            <small class="text-muted">Có thể chọn nhiều ảnh (JPG, PNG, GIF)</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="estimated_value" class="form-label">Giá trị ước tính (VND)</label>
                                <input type="number" class="form-control" id="estimated_value" name="estimated_value" min="0" step="1000" placeholder="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="image_links" class="form-label">Hoặc dán link ảnh</label>
                                <input type="text" class="form-control" id="image_links" name="image_links" placeholder="https://...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-between mb-5">
                    <a href="campaign-detail.php?id=<?= $campaign_id ?>" class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-arrow-left"></i> Quay lại
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle"></i> Xác nhận quyên góp
                    </button>
                </div>

            </form>

            <div id="moneyDonationSection" class="card mb-4 border-0 shadow-sm" style="display: none;">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-cash-stack"></i> Quyên góp tiền cho chiến dịch</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="moneyDonationForm">
                        <input type="hidden" name="action" value="money_donation">
                        <input type="hidden" name="payment_method" id="payment_method_input" value="">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="donate_amount" class="form-label">Số tiền quyên góp (VND) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="donate_amount" name="donate_amount" min="1000" step="1000" placeholder="Ví dụ: 500000" value="<?= htmlspecialchars((string)($_POST['donate_amount'] ?? '')) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="donate_message" class="form-label">Lời nhắn</label>
                                <input type="text" class="form-control" id="donate_message" name="donate_message" placeholder="Ví dụ: Chúc chiến dịch thành công" value="<?= htmlspecialchars((string)($_POST['donate_message'] ?? '')) ?>">
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="button" id="showPaymentOptionsBtn" class="btn btn-primary btn-lg">
                                <i class="bi bi-wallet2"></i> Chọn cổng thanh toán
                            </button>
                        </div>

                        <div id="moneyPaymentOptions" class="mt-3 d-none">
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-info-circle"></i> Chọn ZaloPay hoặc MoMo để hoàn tất quyên góp tiền cho chiến dịch này.
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-info" id="pay-zalopay">ZaloPay</button>
                                <button type="button" class="btn btn-outline-danger" id="pay-momo">MoMo</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$additionalScripts = <<<'SCRIPT'
<script>
// Vietnam location data
const vietnamData = {
    'Hà Nội': {
        'Ba Đình': ['Phúc Tân', 'Đội Cấn', 'Nguyễn Du', 'Cát Linh', 'Quảng An'],
        'Hoàn Kiếm': ['Hàng Bài', 'Hàng Gai', 'Hàng Buồm', 'Tràng Tiền', 'Lý Thái Tổ'],
        'Hai Bà Trưng': ['Phạm Ngọc Thạch', 'Lê Đại Hành', 'Quỳnh Mai', 'Ngô Quyền', 'Định Công']
    },
    'TP. Hồ Chí Minh': {
        'Quận 1': ['Phường Bến Nghé', 'Phường Bến Thành', 'Phường Đa Kao', 'Phường Tân Định'],
        'Quận 2': ['Phường An Khánh', 'Phường An Lợi Đông', 'Phường Bình An', 'Phường Cát Lái'],
        'Quận 3': ['Phường 1', 'Phường 2', 'Phường 3', 'Phường 4', 'Phường 5']
    }
};

document.addEventListener('DOMContentLoaded', function() {
    const donateTypeRadios = document.querySelectorAll('input[name="donate_type_selector"]');
    const itemDonationForm = document.getElementById('itemDonationForm');
    const itemDonateTypeInput = document.getElementById('itemDonateTypeInput');
    const moneyDonationSection = document.getElementById('moneyDonationSection');
    const campaignItemSection = document.getElementById('campaignItemSection');
    const customDonationSection = document.getElementById('customDonationSection');
    const campaignItemSelect = document.getElementById('campaign_item_id');
    const productInfoCard = document.getElementById('productInfoCard');
    const pickupCitySelect = document.getElementById('pickup_city');
    const pickupDistrictSelect = document.getElementById('pickup_district');
    const pickupWardSelect = document.getElementById('pickup_ward');
    const quantityCampaign = document.getElementById('quantity_campaign');
    const quantityCustom = document.getElementById('quantity_custom');
    const unitDisplay = document.getElementById('unitDisplay');
    const showPaymentOptionsBtn = document.getElementById('showPaymentOptionsBtn');
    const moneyPaymentOptions = document.getElementById('moneyPaymentOptions');
    const paymentMethodInput = document.getElementById('payment_method_input');
    const payZaloBtn = document.getElementById('pay-zalopay');
    const payMomoBtn = document.getElementById('pay-momo');
    const moneyDonationForm = document.getElementById('moneyDonationForm');

    const syncDonationMode = function() {
        const selectedRadio = document.querySelector('input[name="donate_type_selector"]:checked');
        const selectedValue = selectedRadio ? selectedRadio.value : 'campaign_item';
        const showMoney = selectedValue === 'money';
        const showCustom = selectedValue === 'custom';

        if (itemDonationForm) {
            itemDonationForm.style.display = showMoney ? 'none' : 'block';
        }
        if (moneyDonationSection) {
            moneyDonationSection.style.display = showMoney ? 'block' : 'none';
        }
        if (campaignItemSection) {
            campaignItemSection.style.display = showCustom ? 'none' : 'block';
        }
        if (customDonationSection) {
            customDonationSection.style.display = showCustom ? 'block' : 'none';
        }
        if (quantityCampaign) {
            quantityCampaign.name = showCustom ? 'quantity_hidden' : 'quantity';
        }
        if (quantityCustom) {
            quantityCustom.name = showCustom ? 'quantity' : 'quantity_hidden';
        }
        if (itemDonateTypeInput) {
            itemDonateTypeInput.value = showCustom ? 'custom' : 'campaign_item';
        }
        if (!showMoney && moneyPaymentOptions) {
            moneyPaymentOptions.classList.add('d-none');
        }
        if (productInfoCard && (showCustom || showMoney)) {
            productInfoCard.style.display = 'none';
        }
    };

    donateTypeRadios.forEach(radio => {
        radio.addEventListener('change', syncDonationMode);
    });
    syncDonationMode();

    // Show product info when campaign item selected
    if (campaignItemSelect) {
        campaignItemSelect.addEventListener('change', function() {
            if (this.value) {
                const option = this.options[this.selectedIndex];
                document.getElementById('productName').textContent = option.dataset.name;
                document.getElementById('productImage').src = option.dataset.image || 'assets/placeholder.png';
                document.getElementById('productCondition').innerHTML = '<span class="badge bg-info">' + option.dataset.condition + '</span>';
                document.getElementById('productNeeded').textContent = (option.dataset.needed - option.dataset.received) + ' ' + option.dataset.unit;
                document.getElementById('productDescription').textContent = option.dataset.description || '';
                unitDisplay.textContent = option.dataset.unit;
                productInfoCard.style.display = 'block';
            } else {
                productInfoCard.style.display = 'none';
            }
        });
    }

    // Populate city dropdown
    Object.keys(vietnamData).forEach(city => {
        const option = document.createElement('option');
        option.value = city;
        option.textContent = city;
        pickupCitySelect.appendChild(option);
    });

    // Cascading dropdown - City to District
    pickupCitySelect.addEventListener('change', function() {
        pickupDistrictSelect.innerHTML = '<option value=\"\">-- Chọn --</option>';
        pickupWardSelect.innerHTML = '<option value=\"\">-- Chọn --</option>';
        
        if (this.value) {
            Object.keys(vietnamData[this.value]).forEach(district => {
                const option = document.createElement('option');
                option.value = district;
                option.textContent = district;
                pickupDistrictSelect.appendChild(option);
            });
        }
    });

    // Cascading dropdown - District to Ward
    pickupDistrictSelect.addEventListener('change', function() {
        pickupWardSelect.innerHTML = '<option value=\"\">-- Chọn --</option>';
        
        const city = pickupCitySelect.value;
        if (city && this.value) {
            vietnamData[city][this.value].forEach(ward => {
                const option = document.createElement('option');
                option.value = ward;
                option.textContent = ward;
                pickupWardSelect.appendChild(option);
            });
        }
    });

    if (showPaymentOptionsBtn && moneyPaymentOptions) {
        showPaymentOptionsBtn.addEventListener('click', function() {
            moneyPaymentOptions.classList.remove('d-none');
        });
    }

    const submitMoneyPayment = function(method) {
        const donateAmountInput = document.getElementById('donate_amount');
        if (!moneyDonationForm || !paymentMethodInput || !donateAmountInput) {
            return;
        }

        if (!donateAmountInput.value || Number(donateAmountInput.value) < 1000) {
            alert('Vui lòng nhập số tiền hợp lệ từ 1.000 VND trở lên.');
            return;
        }

        paymentMethodInput.value = method;
        moneyDonationForm.submit();
    };

    if (payZaloBtn) {
        payZaloBtn.addEventListener('click', function() {
            submitMoneyPayment('zalopay');
        });
    }

    if (payMomoBtn) {
        payMomoBtn.addEventListener('click', function() {
            submitMoneyPayment('momo');
        });
    }

    if (itemDonationForm) {
        itemDonationForm.addEventListener('submit', function(e) {
            const donateType = itemDonateTypeInput ? itemDonateTypeInput.value : 'campaign_item';
            const itemName = document.getElementById('item_name').value.trim();
            const quantityInput = document.querySelector('input[name="quantity"]');
            const quantity = quantityInput ? quantityInput.value : '0';

            if (donateType === 'campaign_item' && !document.getElementById('campaign_item_id').value) {
                e.preventDefault();
                alert('Vui lòng chọn vật phẩm cần góp');
                return false;
            }

            if (donateType === 'custom' && !itemName) {
                e.preventDefault();
                alert('Vui lòng nhập tên vật phẩm');
                return false;
            }

            if (Number(quantity) <= 0) {
                e.preventDefault();
                alert('Số lượng phải lớn hơn 0');
                return false;
            }

            if (!document.getElementById('pickup_address').value.trim()) {
                e.preventDefault();
                alert('Vui lòng nhập địa chỉ chi tiết');
                return false;
            }

            if (!document.getElementById('contact_phone').value.trim()) {
                e.preventDefault();
                alert('Vui lòng nhập số điện thoại liên hệ');
                return false;
            }
        });
    }
});
</script>
SCRIPT;
include 'includes/footer.php';
?>