<?php
require_once __DIR__ . '/_auth.php';

$user = api_require_user();
$userId = (int)$user['user_id'];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $cart_id = (int)($input['cart_id'] ?? 0);
    $quantity = (int)($input['quantity'] ?? 1);

    if ($cart_id <= 0) {
        throw new Exception('Invalid cart item.');
    }
    if ($quantity <= 0) {
        throw new Exception('Invalid quantity.');
    }

    $cartItem = Database::fetch(
        "SELECT * FROM cart WHERE cart_id = ? AND user_id = ?",
        [$cart_id, $userId]
    );

    if (!$cartItem) {
        throw new Exception('Cart item not found.');
    }

    Database::execute(
        "UPDATE cart SET quantity = ?, updated_at = NOW() WHERE cart_id = ?",
        [$quantity, $cart_id]
    );

    api_json(true, ['message' => 'OK']);
} catch (Exception $e) {
    error_log("Update cart quantity error: " . $e->getMessage());
    api_json(false, ['message' => $e->getMessage()], 400);
}
?>
