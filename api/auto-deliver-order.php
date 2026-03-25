<?php
require_once __DIR__ . '/_auth.php';

$user = api_require_user();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$orderId = (int)($payload['order_id'] ?? 0);
if ($orderId <= 0) {
    api_json(false, ['message' => 'Missing order_id.'], 400);
}

$logisticsConfigPath = __DIR__ . '/../config/logistics.php';
$logisticsConfig = file_exists($logisticsConfigPath) ? require $logisticsConfigPath : [];
$sim = $logisticsConfig['simulation'] ?? [];
$enabled = (bool)($sim['auto_deliver_enabled'] ?? false);
$minSeconds = (int)($sim['auto_deliver_min_seconds'] ?? 0);

if (!$enabled) {
    api_json(false, ['message' => 'Auto deliver disabled.'], 403);
}

try {
    $order = Database::fetch(
        "SELECT order_id, user_id, status, shipping_last_mile_status,
                UNIX_TIMESTAMP(shipping_last_mile_updated_at) AS last_mile_ts,
                UNIX_TIMESTAMP(shipped_at) AS shipped_ts
         FROM orders
         WHERE order_id = ? AND user_id = ?",
        [$orderId, (int)$user['user_id']]
    );

    if (!$order) {
        api_json(false, ['message' => 'Order not found.'], 404);
    }

    $status = strtolower(trim((string)($order['status'] ?? '')));
    $lastMile = strtolower(trim((string)($order['shipping_last_mile_status'] ?? '')));

    if ($status === 'cancelled') {
        api_json(false, ['message' => 'Order cancelled.'], 400);
    }

    if (!in_array($lastMile, ['out_for_delivery', 'in_transit', 'picked_up'], true) && $status !== 'shipping') {
        api_json(false, ['message' => 'Order is not in delivery.'], 400);
    }

    $now = time();
    $baseTs = (int)($order['last_mile_ts'] ?? 0);
    if ($baseTs <= 0) {
        $baseTs = (int)($order['shipped_ts'] ?? 0);
    }

    if ($minSeconds > 0 && $baseTs > 0 && ($now - $baseTs) < $minSeconds) {
        api_json(false, [
            'message' => 'Too early to auto-complete delivery.',
            'retry_after_seconds' => max(1, $minSeconds - ($now - $baseTs)),
        ], 429);
    }

    $historyTableExists = !empty(Database::fetchAll("SHOW TABLES LIKE 'order_status_history'"));
    $oldStatus = (string)($order['status'] ?? 'shipping');

    Database::beginTransaction();

    Database::execute(
        "UPDATE orders
         SET status = 'delivered',
             shipping_last_mile_status = 'delivered',
             shipping_last_mile_updated_at = NOW(),
             delivered_at = NOW(),
             updated_at = NOW()
         WHERE order_id = ? AND user_id = ?",
        [$orderId, (int)$user['user_id']]
    );

    if ($historyTableExists) {
        Database::execute(
            "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$orderId, $oldStatus !== '' ? $oldStatus : 'shipping', 'delivered', 'Auto complete delivery (buyer map)']
        );

        Database::execute(
            "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$orderId, 'logistics:' . ($lastMile !== '' ? $lastMile : 'out_for_delivery'), 'logistics:delivered', 'Auto complete delivery (buyer map)']
        );
    }

    Database::commit();

    api_json(true, ['message' => 'Order delivered.']);
} catch (Exception $e) {
    if (Database::getConnection()->inTransaction()) {
        Database::rollback();
    }
    error_log('auto-deliver-order error: ' . $e->getMessage());
    api_json(false, ['message' => 'Unable to auto-complete delivery.'], 500);
}
?>
