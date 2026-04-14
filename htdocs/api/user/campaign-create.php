<?php
require_once __DIR__ . '/../_auth.php';

$user = api_require_user();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$name = trim((string)($payload['name'] ?? ''));
$description = trim((string)($payload['description'] ?? ''));
$image = trim((string)($payload['image'] ?? ''));
$start_date = trim((string)($payload['start_date'] ?? ''));
$end_date = trim((string)($payload['end_date'] ?? ''));
$target_items = (int)($payload['target_items'] ?? 0);
$target_amount = (float)($payload['target_amount'] ?? 0);
$items = $payload['items'] ?? [];

if ($name === '' || $start_date === '' || $end_date === '') {
    api_json(false, ['message' => 'Missing required fields.'], 400);
}

try {
    Database::beginTransaction();

    Database::execute(
        "INSERT INTO campaigns (name, description, image, start_date, end_date, target_amount, target_items, status, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())",
        [
            $name,
            $description !== '' ? $description : null,
            $image !== '' ? $image : null,
            $start_date,
            $end_date,
            $target_amount > 0 ? $target_amount : null,
            $target_items > 0 ? $target_items : null,
            (int)$user['user_id']
        ]
    );

    $campaign_id = (int)Database::lastInsertId();

    if (is_array($items)) {
        foreach ($items as $item) {
            $item_name = trim((string)($item['name'] ?? ''));
            $qty = (int)($item['quantity'] ?? 0);
            if ($item_name === '' || $qty <= 0) {
                continue;
            }

            Database::execute(
                "INSERT INTO campaign_items (campaign_id, item_name, category_id, quantity_needed, unit, description)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $campaign_id,
                    $item_name,
                    (int)($item['category_id'] ?? 0),
                    $qty,
                    trim((string)($item['unit'] ?? 'item')),
                    trim((string)($item['description'] ?? ''))
                ]
            );
        }
    }

    Database::commit();

    logActivity((int)$user['user_id'], 'create_campaign', "Created campaign #$campaign_id");
    api_json(true, ['campaign_id' => $campaign_id]);
} catch (Exception $e) {
    if (Database::getConnection()->inTransaction()) {
        Database::rollback();
    }
    error_log('campaign-create error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to create campaign.'], 500);
}
?>
