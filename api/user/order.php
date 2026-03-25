<?php
require_once __DIR__ . '/../_auth.php';

$user = api_require_user();
$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    api_json(false, ['message' => 'Missing order_id.'], 400);
}

$order = Database::fetch(
    "SELECT * FROM orders WHERE order_id = ? AND user_id = ?",
    [$order_id, (int)$user['user_id']]
);

if (!$order) {
    api_json(false, ['message' => 'Order not found.'], 404);
}

$items = Database::fetchAll(
    "SELECT oi.*, i.images
     FROM order_items oi
     LEFT JOIN inventory i ON oi.item_id = i.item_id
     WHERE oi.order_id = ?",
    [$order_id]
);

$history = [];
$hasOrderHistoryTable = !empty(Database::fetchAll("SHOW TABLES LIKE 'order_status_history'"));
if ($hasOrderHistoryTable) {
    $history = Database::fetchAll(
        "SELECT history_id, old_status, new_status, note, created_at
         FROM order_status_history
         WHERE order_id = ?
         ORDER BY created_at ASC, history_id ASC",
        [$order_id]
    );
}

api_json(true, [
    'order' => $order,
    'items' => $items,
    'history' => $history,
]);
?>
