<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/moderation.php';

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
    }

    // === KIỂM DUYỆT LỜI NHẮN TIỀN ===
    if (!$error && $message !== '') {
        $toxicWord = checkToxicTextLocal($message);
        if ($toxicWord !== null) {
            $error = 'Quyên góp bị từ chối! Từ bị cấm: ' . htmlspecialchars($toxicWord);
        } else {
            $geminiCheck = checkToxicTextGemini($message);
            if ($geminiCheck['violate']) {
                $error = 'Quyên góp bị từ chối! ' . htmlspecialchars($geminiCheck['reason']);
            }
        }
    }

    if (!$error) {
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
        } elseif (empty($address_status)) {
            $error = 'Vui lï¿½ng ch?n lo?i d?a ch?.';
        } elseif (empty($contact_phone)) {
            $error = 'Vui lï¿½ng nh?p s? di?n tho?i liï¿½n h?.';
        } elseif (!preg_match('/^\d{10,}$/', preg_replace('/[^\d]/', '', $contact_phone))) {
            $error = 'S? di?n tho?i ph?i cï¿½ ï¿½t nh?t 10 ch? s?.';
        } elseif (empty($product_condition)) {
            $error = 'Vui lï¿½ng ch?n tï¿½nh tr?ng s?n ph?m.';
        } elseif (!empty($pickup_date) && !empty($campaign['start_date']) && strtotime($pickup_date) >= strtotime($campaign['start_date'])) {
            $error = 'Ngày nhận hàng phải trước ngày bắt đầu chiến dịch (' . date('d/m/Y', strtotime($campaign['start_date'])) . ').';
        }
    } 

    // === KIỂM DUYỆT NỘI DUNG VẬT PHẨM ===
    if (!$error) {
        $allText = $item_name . ' ' . $description . ' ' . $pickup_address . ' ' . $condition_detail;
        $toxicWord = checkToxicTextLocal($allText);
        if ($toxicWord !== null) {
            $error = 'Quyên góp bị từ chối! Từ bị cấm: ' . htmlspecialchars($toxicWord);
        } else {
            $geminiCheck = checkToxicTextGemini($allText);
            if ($geminiCheck['violate']) {
                $error = 'Quyên góp bị từ chối! ' . htmlspecialchars($geminiCheck['reason']);
            }
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
                            // === KIỂM DUYỆT ẢNH NSFW ===
                            $fullPath = $uploadDir . $uploadResult['filename'];
                            if (file_exists($fullPath)) {
                                $imgCheck = checkNsfwImageGemini($fullPath);
                                if ($imgCheck['violate']) {
                                    @unlink($fullPath);
                                    throw new Exception('Quyên góp bị từ chối! ' . $imgCheck['reason']);
                                }
                            }
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
            if (strpos($e->getMessage(), 'Quyên góp bị từ chối') !== false || strpos($e->getMessage(), 'Quyên góp thất bại') !== false) {
                $error = $e->getMessage();
            } else {
                $error = 'Có lỗi xảy ra. Vui lòng thử lại.';
            }
        }
    }
}

$pageTitle = "Quyên góp cho chiến dịch"; 
include 'includes/header.php';
?>

<style>
.donate-campaign-hero {
    background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
    color: #fff;
    padding: 64px 0 48px;
    position: relative;
    overflow: hidden;
    margin-top: -1px;
}
.donate-campaign-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 80% 50%, rgba(255,255,255,0.07) 0%, transparent 60%);
}
.donate-campaign-hero-row {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 1.6rem;
}
.dc-hero-main {
    display: flex;
    align-items: center;
    gap: 1.6rem;
}
.dc-hero-icon-box {
    width: 134px;
    height: 134px;
    border-radius: 34px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    background: rgba(255, 255, 255, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    backdrop-filter: blur(6px);
}
.dc-hero-icon-box i {
    font-size: 3.8rem;
    color: rgba(255, 255, 255, 0.95);
}
.dc-hero-title {
    font-size: clamp(2.4rem, 5.2vw, 5rem);
    line-height: 1.05;
    font-weight: 900;
    margin: 0;
    letter-spacing: -0.02em;
}
.dc-hero-sub {
    opacity: 0.88;
    margin-top: 0.7rem;
    margin-bottom: 0;
    font-size: clamp(1.05rem, 1.7vw, 2.05rem);
    max-width: 940px;
}
.dc-hero-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    margin-top: 1rem;
}
.dc-hero-badges .badge {
    font-size: 0.92rem;
    font-weight: 500;
    padding: 0.45rem 1rem;
    border-radius: 999px;
    background: rgba(255,255,255,0.13);
    border: 1px solid rgba(255,255,255,0.22);
    backdrop-filter: blur(4px);
}
@media (max-width: 991.98px) {
    .donate-campaign-hero { padding: 38px 0 34px; }
    .dc-hero-main { gap: 1rem; }
    .dc-hero-icon-box { width: 90px; height: 90px; border-radius: 20px; }
    .dc-hero-icon-box i { font-size: 2.45rem; }
    .dc-hero-title { font-size: clamp(1.8rem, 8vw, 2.8rem); }
    .dc-hero-sub { font-size: 1rem; }
}
</style>

<div class="donate-campaign-hero">
    <div class="container">
        <div class="donate-campaign-hero-row">
            <div class="dc-hero-main">
                <div class="dc-hero-icon-box">
                    <i class="bi bi-heart-fill"></i>
                </div>
                <div>
                    <h1 class="dc-hero-title">Quyên góp cho chiến dịch</h1>
                    <p class="dc-hero-sub">Chung tay đóng góp cho chiến dịch "<?= htmlspecialchars($campaign['name']) ?>" và tạo ra sự thay đổi tích cực</p>
                    <div class="dc-hero-badges">
                        <span class="badge"><i class="bi bi-shield-check me-1"></i> Minh bạch</span>
                        <span class="badge"><i class="bi bi-lightning-charge me-1"></i> Xử lý nhanh</span>
                        <span class="badge"><i class="bi bi-geo-alt me-1"></i> Hỗ trợ toàn quốc</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container mt-4 mb-5">
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
                <?php
                $isModReject = (strpos($error, 'Quyên góp bị từ chối') !== false || strpos($error, 'Quyên góp thất bại') !== false);
                ?>
                <?php if ($isModReject): ?>
                    <?= renderModerationError('Quyên góp bị từ chối', str_replace(['Quyên góp thất bại! ', 'Quyên góp bị từ chối! '], '', $error)) ?>
                <?php else: ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
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
                                    <input type="number" class="form-control" id="quantity_campaign" name="quantity" min="1" value="1">
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
                                <input type="text" class="form-control" id="item_name" name="item_name" placeholder="VD: Sách, Quần áo...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="quantity_custom" class="form-label">Số lượng <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="quantity_custom" name="quantity" min="1" value="1">
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
                                <label class="form-label">Thành phố/Tỉnh <span class="text-danger">*</span></label>
                                <select class="form-select" id="pickup_city" name="pickup_city" required data-selected="<?php echo htmlspecialchars($_POST['pickup_city'] ?? ''); ?>">
                                    <option value="">-- Chọn Thành phố --</option>
                                </select>
                                <div class="invalid-feedback">Vui lòng chọn Thành phố</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Quận/Huyện <span class="text-danger">*</span></label>
                                <select class="form-select" id="pickup_district" name="pickup_district" required data-selected="<?php echo htmlspecialchars($_POST['pickup_district'] ?? ''); ?>" disabled>
                                    <option value="">-- Chọn Quận/Huyện --</option>
                                </select>
                                <div class="invalid-feedback">Vui lòng chọn Quận/Huyện</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Phường/Xã <span class="text-danger">*</span></label>
                                <select class="form-select" id="pickup_ward" name="pickup_ward" required data-selected="<?php echo htmlspecialchars($_POST['pickup_ward'] ?? ''); ?>" disabled>
                                    <option value="">-- Chọn Phường/Xã --</option>
                                </select>
                                <div class="invalid-feedback">Vui lòng chọn Phường/Xã</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Địa chỉ chi tiết <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="pickup_address" name="pickup_address" rows="2" placeholder="VD: 123 Đường Abc, Tòa nhà XYZ..." required><?php echo htmlspecialchars($_POST['pickup_address'] ?? ''); ?></textarea>
                            <div class="invalid-feedback">Vui lòng nhập địa chỉ chi tiết</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Loại địa chỉ <span class="text-danger">*</span></label>
                                <select class="form-select" id="address_status" name="address_status" required>
                                    <option value="">-- Chọn --</option>
                                    <option value="home">Nhà riêng</option>
                                    <option value="office">Cơ quan</option>
                                    <option value="shop">Cửa hàng</option>
                                    <option value="other">Khác</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Số điện thoại liên hệ <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="contact_phone" name="contact_phone" placeholder="0123456789" value="<?php echo htmlspecialchars($_POST['contact_phone'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ngày Tháng Năm</label>
                                <?php
                                $maxDateStr = '';
                                if (!empty($campaign['start_date'])) {
                                    $maxDateStr = date('Y-m-d', strtotime($campaign['start_date'] . ' -1 day'));
                                }
                                ?>
                                <input type="date" class="form-control" id="pickup_date" name="pickup_date" value="<?php echo htmlspecialchars($_POST['pickup_date'] ?? ''); ?>" min="<?php echo date('Y-m-d'); ?>" <?php echo $maxDateStr ? 'max="'.$maxDateStr.'"' : ''; ?> data-start-date="<?php echo htmlspecialchars($campaign['start_date'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Giờ nhận hàng</label>
                                <input type="time" class="form-control" id="pickup_time" name="pickup_time" value="<?php echo htmlspecialchars($_POST['pickup_time'] ?? ''); ?>">
                            </div>
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
document.addEventListener('DOMContentLoaded', function() {
    const donateTypeRadios = document.querySelectorAll('input[name="donate_type_selector"]');
    const itemDonationForm = document.getElementById('itemDonationForm');
    const itemDonateTypeInput = document.getElementById('itemDonateTypeInput');
    const moneyDonationSection = document.getElementById('moneyDonationSection');
    const campaignItemSection = document.getElementById('campaignItemSection');
    const customDonationSection = document.getElementById('customDonationSection');
    const campaignItemSelect = document.getElementById('campaign_item_id');
    const productInfoCard = document.getElementById('productInfoCard');
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

    // Vietnamese address selects (City/District/Ward) via local JSON API
    (function () {
        const cityEl = document.getElementById('pickup_city');
        const districtEl = document.getElementById('pickup_district');
        const wardEl = document.getElementById('pickup_ward');
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

            const campaignItemIdEl = document.getElementById('campaign_item_id');
            if (donateType === 'campaign_item' && (!campaignItemIdEl || !campaignItemIdEl.value)) {
                e.preventDefault();
                alert('Vui lòng chọn vật phẩm cần góp hoặc chuyển sang Góp vật phẩm khác');
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

            const pickupDateEl = document.getElementById('pickup_date');
            if (pickupDateEl && pickupDateEl.value) {
                const pickupDate = new Date(pickupDateEl.value);
                const startDateStr = pickupDateEl.getAttribute('data-start-date');
                if (startDateStr) {
                    const startDate = new Date(startDateStr);
                    pickupDate.setHours(0,0,0,0);
                    startDate.setHours(0,0,0,0);
                    if (pickupDate >= startDate) {
                        e.preventDefault();
                        alert('Ngày tháng năm phải trước ngày bắt đầu chiến dịch (' + startDate.toLocaleDateString('vi-VN') + ')');
                        return false;
                    }
                }
            }
        });
    }
});
</script>
SCRIPT;
include 'includes/footer.php';
?>