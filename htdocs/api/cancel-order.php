<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/notifications_helper.php';

$user = api_require_user();
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$order_id = (int)($input['order_id'] ?? 0);
if ($order_id <= 0) {
    api_json(false, ['message' => 'Invalid order_id.'], 400);
}

try {
    $order = Database::fetch(
        "SELECT * FROM orders WHERE order_id = ? AND user_id = ?",
        [$order_id, (int)$user['user_id']]
    );

    if (!$order) {
        api_json(false, ['message' => 'Order not found.'], 404);
    }

    if ($order['status'] !== 'pending') {
        api_json(false, ['message' => 'Order cannot be cancelled now.'], 400);
    }

    Database::beginTransaction();

    $orderItems = Database::fetchAll(
        "SELECT item_id, quantity FROM order_items WHERE order_id = ?",
        [$order_id]
    );

    foreach ($orderItems as $item) {
        Database::execute(
            "UPDATE inventory SET quantity = quantity + ? WHERE item_id = ?",
            [$item['quantity'], $item['item_id']]
        );
    }

    Database::execute(
        "UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE order_id = ?",
        [$order_id]
    );

    $hasOrderHistoryTable = !empty(Database::fetchAll("SHOW TABLES LIKE 'order_status_history'"));
    if ($hasOrderHistoryTable) {
        Database::execute(
            "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
             VALUES (?, ?, 'cancelled', 'Customer cancelled', NOW())",
            [$order_id, $order['status']]
        );
    }

    createUserNotification(
        (int)$user['user_id'],
        'Order cancelled',
        'Order #' . str_pad((string)$order_id, 6, '0', STR_PAD_LEFT) . ' was cancelled.',
        [
            'type' => 'warning',
            'category' => 'order'
        ]
    );

    Database::commit();

    logActivity((int)$user['user_id'], 'cancel_order', "Cancelled order #$order_id");

    api_json(true, ['message' => 'Order cancelled.']);
} catch (Exception $e) {
    if (Database::getConnection()->inTransaction()) {
        Database::rollback();
    }
    error_log('cancel-order error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to cancel order.'], 500);
}
?>
