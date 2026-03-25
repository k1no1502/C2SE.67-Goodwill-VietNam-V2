<?php
require_once __DIR__ . '/_auth.php';

$user = api_require_staff_or_admin();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$orderId = (int)($payload['order_id'] ?? 0);
if ($orderId <= 0) {
    api_json(false, ['message' => 'Missing order_id.'], 400);
}

try {
    $order = Database::fetch("SELECT order_id FROM orders WHERE order_id = ?", [$orderId]);
    if (!$order) {
        api_json(false, ['message' => 'Order not found.'], 404);
    }

    $statusCode = trim((string)($payload['status_code'] ?? ''));
    $title = trim((string)($payload['title'] ?? ''));
    $note = trim((string)($payload['note'] ?? ''));
    $locationAddress = trim((string)($payload['location_address'] ?? ''));

    $lat = $payload['lat'] ?? null;
    $lng = $payload['lng'] ?? null;
    $lat = ($lat === '' || $lat === null) ? null : (float)$lat;
    $lng = ($lng === '' || $lng === null) ? null : (float)$lng;

    $occurredAt = trim((string)($payload['occurred_at'] ?? ''));
    if ($occurredAt === '') {
        $occurredAt = date('Y-m-d H:i:s');
    }

    if ($title === '') {
        $title = 'Delivery update';
    }

    Database::execute(
        "INSERT INTO order_tracking_events
            (order_id, status_code, title, note, location_address, lat, lng, occurred_at, created_by)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $orderId,
            $statusCode,
            $title,
            $note,
            $locationAddress,
            $lat,
            $lng,
            $occurredAt,
            (int)$user['user_id'],
        ]
    );

    api_json(true, ['event_id' => (int)Database::lastInsertId()]);
} catch (Exception $e) {
    error_log('add-order-tracking-event error: ' . $e->getMessage());
    api_json(false, ['message' => 'Unable to add tracking event.'], 500);
}
?>
