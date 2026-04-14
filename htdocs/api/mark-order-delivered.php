<?php
require_once __DIR__ . '/_auth.php';

api_require_admin();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$orderId = (int)($payload['order_id'] ?? 0);
if ($orderId <= 0) {
    api_json(false, ['message' => 'Missing order_id.'], 400);
}

try {
    $order = Database::fetch("SELECT order_id, status FROM orders WHERE order_id = ?", [$orderId]);
    if (!$order) {
        api_json(false, ['message' => 'Order not found.'], 404);
    }

    $historyTableExists = !empty(Database::fetchAll("SHOW TABLES LIKE 'order_status_history'"));
    $oldStatus = (string)($order['status'] ?? '');

    Database::beginTransaction();

    Database::execute(
        "UPDATE orders
         SET status = 'delivered',
             shipping_last_mile_status = 'delivered',
             shipping_last_mile_updated_at = NOW(),
             delivered_at = NOW(),
             updated_at = NOW()
         WHERE order_id = ?",
        [$orderId]
    );

    if ($historyTableExists) {
        Database::execute(
            "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$orderId, $oldStatus !== '' ? $oldStatus : 'shipping', 'delivered', 'Auto complete delivery (map)']
        );

        Database::execute(
            "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$orderId, 'logistics:out_for_delivery', 'logistics:delivered', 'Auto complete delivery (map)']
        );
    }

    Database::commit();

    api_json(true, ['message' => 'Order delivered.']);
} catch (Exception $e) {
    if (Database::getConnection()->inTransaction()) {
        Database::rollback();
    }
    error_log('mark-order-delivered error: ' . $e->getMessage());
    api_json(false, ['message' => 'Unable to mark delivered.'], 500);
}
?>
