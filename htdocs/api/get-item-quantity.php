<?php
require_once __DIR__ . '/_auth.php';

$user = api_require_user();
$userId = (int)$user['user_id'];

try {
    $item_id = (int)($_GET['item_id'] ?? 0);
    if ($item_id <= 0) {
        throw new Exception('Invalid item.');
    }

    $quantity = (int)Database::fetch(
        "SELECT COALESCE(SUM(quantity), 0) as total FROM cart WHERE user_id = ? AND item_id = ?",
        [$userId, $item_id]
    )['total'];

    api_json(true, ['quantity' => $quantity]);
} catch (Exception $e) {
    error_log("Get item quantity error: " . $e->getMessage());
    api_json(false, ['message' => $e->getMessage()], 400);
}
?>
