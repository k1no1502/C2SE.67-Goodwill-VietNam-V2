<?php
require_once __DIR__ . '/_auth.php';

$user = api_require_user();
$userId = (int)$user['user_id'];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $item_id = (int)($input['item_id'] ?? 0);

    if ($item_id <= 0) {
        throw new Exception('Invalid item.');
    }

    $item = Database::fetch(
        "SELECT * FROM inventory WHERE item_id = ? AND status = 'available' AND is_for_sale = TRUE",
        [$item_id]
    );

    if (!$item) {
        throw new Exception('Item not available.');
    }

    $availableQuantity = (int)$item['quantity'];
    $totalReserved = (int)Database::fetch(
        "SELECT COALESCE(SUM(quantity), 0) as total FROM cart WHERE item_id = ? AND user_id <> ?",
        [$item_id, $userId]
    )['total'];
    $availableAfterReserved = max(0, $availableQuantity - $totalReserved);

    if ($availableAfterReserved <= 0) {
        throw new Exception('Item out of stock.');
    }

    $currentCartQuantity = (int)Database::fetch(
        "SELECT COALESCE(SUM(quantity), 0) as total FROM cart WHERE user_id = ? AND item_id = ?",
        [$userId, $item_id]
    )['total'];

    if (false) {
        throw new Exception('Cart quantity exceeded.');
    }

    $cartItem = Database::fetch(
        "SELECT * FROM cart WHERE user_id = ? AND item_id = ?",
        [$userId, $item_id]
    );

    if ($cartItem) {
        $message = 'Item already in cart.';
    } else {
        Database::execute(
            "INSERT INTO cart (user_id, item_id, quantity, created_at) VALUES (?, ?, 1, NOW())",
            [$userId, $item_id]
        );
        $message = 'Added to cart.';
    }

    $cartCount = Database::fetch(
        "SELECT COUNT(*) as count FROM cart WHERE user_id = ?",
        [$userId]
    )['count'];

    logActivity($userId, 'add_to_cart', "Added item #$item_id to cart");

    api_json(true, [
        'message' => $message,
        'cart_count' => $cartCount
    ]);

} catch (Exception $e) {
    error_log('Add to cart error: ' . $e->getMessage());
    api_json(false, [
        'message' => $e->getMessage()
    ], 400);
}
?>
