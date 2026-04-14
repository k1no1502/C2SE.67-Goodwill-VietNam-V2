<?php
header('Content-Type: application/json; charset=UTF-8');
http_response_code(200);

require_once __DIR__ . '/../config/database.php';

$paymentConfigPath = __DIR__ . '/../config/payment.php';
$paymentConfig = file_exists($paymentConfigPath) ? require $paymentConfigPath : [];
$momoCfg = $paymentConfig['momo'] ?? [];
$secretKey = trim((string)($momoCfg['secret_key'] ?? ''));

function campaignNotifyExtractTransId(?string $orderId, ?string $extraData): int
{
    $orderId = (string)$orderId;
    if (preg_match('/^CDONATE(\d+)_/i', $orderId, $matches)) {
        return (int)$matches[1];
    }

    $extraData = trim((string)$extraData);
    if ($extraData !== '') {
        $decoded = base64_decode($extraData, true);
        if ($decoded !== false) {
            $payload = json_decode($decoded, true);
            if (is_array($payload) && !empty($payload['trans_id'])) {
                return (int)$payload['trans_id'];
            }
        }
    }

    return 0;
}

function campaignNotifyExtractCampaignIdFromNote(string $note): int
{
    if (preg_match('/^campaign_id=(\d+)$/mi', $note, $matches)) {
        return (int)$matches[1];
    }
    return 0;
}

function campaignNotifyHasAppliedFlag(string $note): bool
{
    return strpos($note, '[CAMPAIGN_AMOUNT_APPLIED]') !== false;
}

function campaignNotifyAppendAppliedFlag(string $note): string
{
    $note = rtrim($note);
    return $note === '' ? '[CAMPAIGN_AMOUNT_APPLIED]' : $note . "\n[CAMPAIGN_AMOUNT_APPLIED]";
}

function campaignNotifyApplyCompletedTransaction(int $transId, string $paymentReference): bool
{
    Database::beginTransaction();

    try {
        $tx = Database::fetch(
            'SELECT trans_id, amount, status, notes FROM transactions WHERE trans_id = ? FOR UPDATE',
            [$transId]
        );

        if (!$tx || strtolower((string)($tx['status'] ?? '')) !== 'completed') {
            Database::rollback();
            return false;
        }

        $note = (string)($tx['notes'] ?? '');
        $campaignId = campaignNotifyExtractCampaignIdFromNote($note);
        if ($campaignId <= 0) {
            Database::rollback();
            return false;
        }

        if (!campaignNotifyHasAppliedFlag($note)) {
            Database::execute(
                'UPDATE campaigns SET current_amount = COALESCE(current_amount, 0) + ? WHERE campaign_id = ?',
                [(float)$tx['amount'], $campaignId]
            );
            $note = campaignNotifyAppendAppliedFlag($note);
        }

        Database::execute(
            'UPDATE transactions SET notes = ?, payment_reference = ?, updated_at = NOW() WHERE trans_id = ?',
            [$note, $paymentReference, $transId]
        );

        Database::commit();
        return true;
    } catch (Throwable $e) {
        Database::rollback();
        throw $e;
    }
}

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

$transId = campaignNotifyExtractTransId((string)($data['orderId'] ?? ''), (string)($data['extraData'] ?? ''));
if ($transId <= 0) {
    echo json_encode([
        'resultCode' => 98,
        'message' => 'Cannot map order to transaction',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$tx = Database::fetch('SELECT trans_id FROM transactions WHERE trans_id = ?', [$transId]);
if (!$tx) {
    echo json_encode([
        'resultCode' => 98,
        'message' => 'Transaction not found',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)$data['resultCode'] === '0') {
    $paymentReference = 'MOMO-' . trim((string)$data['transId']);
    Database::execute(
        'UPDATE transactions SET status = ?, payment_reference = ?, updated_at = NOW() WHERE trans_id = ?',
        ['completed', $paymentReference, $transId]
    );
    campaignNotifyApplyCompletedTransaction($transId, $paymentReference);
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