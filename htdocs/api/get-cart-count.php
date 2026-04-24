<?php
require_once __DIR__ . '/_auth.php';

$user = api_require_user();
$userId = (int)$user['user_id'];

try {
    $cartCount = Database::fetch(
        "SELECT COUNT(*) as count FROM cart WHERE user_id = ?",
        [$userId]
    )['count'];

    api_json(true, ['count' => (int)$cartCount]);
} catch (Exception $e) {
    error_log("Get cart count error: " . $e->getMessage());
    api_json(false, ['message' => $e->getMessage(), 'count' => 0], 500);
}
?>
