<?php
require_once __DIR__ . '/../_auth.php';

$user = api_require_user();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$shipping_name = trim((string)($payload['shipping_name'] ?? ''));
$shipping_phone = trim((string)($payload['shipping_phone'] ?? ''));
$shipping_address = trim((string)($payload['shipping_address'] ?? ''));
$shipping_note = trim((string)($payload['shipping_note'] ?? ''));
$payment_method = trim((string)($payload['payment_method'] ?? 'cod'));

$cartItems = Database::fetchAll(
    "SELECT 
            c.cart_id,
            c.item_id,
            c.quantity AS cart_quantity,
            c.created_at AS cart_created_at,
            i.name AS item_name,
            i.quantity AS inventory_quantity,
            i.price_type,
            i.sale_price,
            i.status AS inventory_status
     FROM cart c
     JOIN inventory i ON c.item_id = i.item_id
     WHERE c.user_id = ?",
    [(int)$user['user_id']]
);

if (empty($cartItems)) {
    api_json(false, ['message' => 'Cart is empty.'], 400);
}

$totalAmount = 0.0;
$totalItems = 0;
foreach ($cartItems as $item) {
    $qty = (int)$item['cart_quantity'];
    $unitPrice = ($item['price_type'] === 'free') ? 0 : (float)$item['sale_price'];
    $totalAmount += $unitPrice * $qty;
    $totalItems += $qty;
}

try {
    Database::beginTransaction();

    $hasShippingName = !empty(Database::fetchAll("SHOW COLUMNS FROM orders LIKE 'shipping_name'"));
    $statusColumn = Database::fetch("SHOW COLUMNS FROM orders LIKE 'status'");
    $allowedStatuses = [];
    if (!empty($statusColumn['Type']) && strpos($statusColumn['Type'], 'enum(') === 0) {
        preg_match_all("/'([^']+)'/", $statusColumn['Type'], $matches);
        $allowedStatuses = $matches[1] ?? [];
    }
    $orderStatus = in_array('pending', $allowedStatuses, true) ? 'pending' : ($allowedStatuses[0] ?? 'pending');

    if ($hasShippingName) {
        Database::execute(
            "INSERT INTO orders (user_id, shipping_name, shipping_phone, shipping_address, shipping_note, payment_method, total_amount, total_items, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                (int)$user['user_id'],
                $shipping_name,
                $shipping_phone,
                $shipping_address,
                $shipping_note !== '' ? $shipping_note : null,
                $payment_method,
                $totalAmount,
                $totalItems,
                $orderStatus
            ]
        );
    } else {
        $order_number = 'ORD-' . date('Ymd-His') . '-' . (int)$user['user_id'];
        $legacyPaymentMethod = $payment_method === 'cod' ? 'cash' : $payment_method;
        if (!in_array($legacyPaymentMethod, ['cash', 'bank_transfer', 'credit_card', 'free'], true)) {
            $legacyPaymentMethod = 'cash';
        }
        Database::execute(
            "INSERT INTO orders (order_number, user_id, total_amount, total_items, status, payment_method, shipping_address, shipping_phone, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $order_number,
                (int)$user['user_id'],
                $totalAmount,
                $totalItems,
                $orderStatus,
                $legacyPaymentMethod,
                $shipping_address,
                $shipping_phone,
                $shipping_note !== '' ? $shipping_note : null
            ]
        );
    }

    $order_id = (int)Database::lastInsertId();

    $hasUnitPrice = !empty(Database::fetchAll("SHOW COLUMNS FROM order_items LIKE 'unit_price'"));

    foreach ($cartItems as $item) {
        $qty = (int)$item['cart_quantity'];
        $unitPrice = ($item['price_type'] === 'free') ? 0 : (float)$item['sale_price'];
        $itemTotal = $unitPrice * $qty;

        if ($hasUnitPrice) {
            Database::execute(
                "INSERT INTO order_items (order_id, item_id, item_name, quantity, unit_price, total_price, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [
                    $order_id,
                    $item['item_id'],
                    $item['item_name'],
                    $qty,
                    $unitPrice,
                    $itemTotal
                ]
            );
        } else {
            Database::execute(
                "INSERT INTO order_items (order_id, item_id, item_name, quantity, price, price_type, subtotal, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $order_id,
                    $item['item_id'],
                    $item['item_name'],
                    $qty,
                    $unitPrice,
                    $item['price_type'],
                    $itemTotal
                ]
            );
        }

        $updateInventoryStmt = Database::execute(
            "UPDATE inventory
             SET quantity = quantity - ?
             WHERE item_id = ? AND status = 'available' AND quantity >= ?",
            [$qty, $item['item_id'], $qty]
        );
        if ($updateInventoryStmt->rowCount() !== 1) {
            throw new Exception('Insufficient inventory for item #' . $item['item_id']);
        }
    }

    Database::execute("DELETE FROM cart WHERE user_id = ?", [(int)$user['user_id']]);

    $hasOrderHistoryTable = !empty(Database::fetchAll("SHOW TABLES LIKE 'order_status_history'"));
    if ($hasOrderHistoryTable) {
        Database::execute(
            "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
             VALUES (?, 'pending', 'pending', 'Order created', NOW())",
            [$order_id]
        );
    }

    Database::commit();

    logActivity((int)$user['user_id'], 'create_order', "Created order #$order_id");
    api_json(true, ['order_id' => $order_id]);
} catch (Exception $e) {
    if (Database::getConnection()->inTransaction()) {
        Database::rollback();
    }
    error_log('checkout error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to create order.'], 500);
}
?>
