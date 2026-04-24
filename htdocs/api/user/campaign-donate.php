<?php
require_once __DIR__ . '/../_auth.php';

$user = api_require_user();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$campaign_id = (int)($payload['campaign_id'] ?? 0);
$campaign_item_id = (int)($payload['campaign_item_id'] ?? 0);
$item_name = trim((string)($payload['item_name'] ?? ''));
$description = trim((string)($payload['description'] ?? ''));
$category_id = (int)($payload['category_id'] ?? 0);
$quantity = max(1, (int)($payload['quantity'] ?? 1));
$unit = trim((string)($payload['unit'] ?? 'item'));
$condition_status = trim((string)($payload['condition_status'] ?? 'good'));
$estimated_value = (float)($payload['estimated_value'] ?? 0);
$images = $payload['images'] ?? [];

if ($campaign_id <= 0 || $item_name === '') {
    api_json(false, ['message' => 'Missing required fields.'], 400);
}

if (!is_array($images)) {
    $images = [];
}

try {
    Database::beginTransaction();

    Database::execute(
        "INSERT INTO donations (user_id, item_name, description, category_id, quantity, unit, condition_status, estimated_value, images, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW())",
        [
            (int)$user['user_id'],
            $item_name,
            $description !== '' ? $description : null,
            $category_id > 0 ? $category_id : null,
            $quantity,
            $unit,
            $condition_status !== '' ? $condition_status : 'good',
            $estimated_value > 0 ? $estimated_value : null,
            json_encode($images)
        ]
    );

    $donation_id = (int)Database::lastInsertId();

    Database::execute(
        "INSERT INTO campaign_donations (campaign_id, donation_id, campaign_item_id, quantity_contributed, created_at)
         VALUES (?, ?, ?, ?, NOW())",
        [
            $campaign_id,
            $donation_id,
            $campaign_item_id > 0 ? $campaign_item_id : null,
            $quantity
        ]
    );

    if ($campaign_item_id > 0) {
        Database::execute(
            "UPDATE campaign_items
             SET quantity_received = GREATEST(quantity_received + ?, 0)
             WHERE item_id = ? AND campaign_id = ?",
            [$quantity, $campaign_item_id, $campaign_id]
        );
    }

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

    $priceType = 'free';
    $salePrice = 0;
    if ($estimated_value > 0 && $estimated_value < 100000) {
        $priceType = 'cheap';
        $salePrice = $estimated_value;
    } elseif ($estimated_value >= 100000) {
        $priceType = 'normal';
        $salePrice = $estimated_value;
    }

    Database::execute(
        "INSERT INTO inventory (donation_id, name, description, category_id, quantity, unit, condition_status, images, status, price_type, sale_price, is_for_sale, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available', ?, ?, TRUE, NOW())",
        [
            $donation_id,
            $item_name,
            $description !== '' ? $description : null,
            $category_id > 0 ? $category_id : null,
            $quantity,
            $unit,
            $condition_status !== '' ? $condition_status : 'good',
            json_encode($images),
            $priceType,
            $salePrice
        ]
    );

    Database::commit();

    logActivity((int)$user['user_id'], 'donate_to_campaign', "Donated to campaign #$campaign_id");
    api_json(true, ['donation_id' => $donation_id]);
} catch (Exception $e) {
    if (Database::getConnection()->inTransaction()) {
        Database::rollback();
    }
    error_log('campaign-donate error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to donate to campaign.'], 500);
}
?>
