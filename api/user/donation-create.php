<?php
require_once __DIR__ . '/../_auth.php';

$user = api_require_user();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$items = $payload['items'] ?? [];
$pickup_address = trim((string)($payload['pickup_address'] ?? ''));
$pickup_date = trim((string)($payload['pickup_date'] ?? ''));
$pickup_time = trim((string)($payload['pickup_time'] ?? ''));
$contact_phone = trim((string)($payload['contact_phone'] ?? ''));

if (!is_array($items) || empty($items)) {
    api_json(false, ['message' => 'No donation items provided.'], 400);
}

try {
    Database::beginTransaction();

    $created = [];
    foreach ($items as $item) {
        $item_name = trim((string)($item['item_name'] ?? $item['name'] ?? ''));
        if ($item_name === '') {
            continue;
        }
        $description = trim((string)($item['description'] ?? ''));
        $category_id = (int)($item['category_id'] ?? 0);
        $quantity = max(1, (int)($item['quantity'] ?? 1));
        $unit = trim((string)($item['unit'] ?? 'item'));
        $condition_status = trim((string)($item['condition_status'] ?? 'good'));
        $estimated_value = (float)($item['estimated_value'] ?? 0);
        $images = $item['images'] ?? [];
        if (!is_array($images)) {
            $images = [];
        }

        Database::execute(
            "INSERT INTO donations (user_id, item_name, description, category_id, quantity, unit,
                    condition_status, estimated_value, images, pickup_address, pickup_date, pickup_time,
                    contact_phone, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                (int)$user['user_id'],
                $item_name,
                $description !== '' ? $description : null,
                $category_id > 0 ? $category_id : null,
                $quantity,
                $unit,
                $condition_status !== '' ? $condition_status : 'good',
                $estimated_value > 0 ? $estimated_value : null,
                json_encode($images),
                $pickup_address !== '' ? $pickup_address : null,
                $pickup_date !== '' ? $pickup_date : null,
                $pickup_time !== '' ? $pickup_time : null,
                $contact_phone !== '' ? $contact_phone : null
            ]
        );

        $donation_id = (int)Database::lastInsertId();
        $created[] = $donation_id;
        logActivity((int)$user['user_id'], 'donate', "Created donation #$donation_id");
    }

    if (empty($created)) {
        throw new Exception('No valid items to create.');
    }

    Database::commit();
    api_json(true, ['donation_ids' => $created]);
} catch (Exception $e) {
    if (Database::getConnection()->inTransaction()) {
        Database::rollback();
    }
    error_log('donation-create error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to create donation.'], 500);
}
?>
