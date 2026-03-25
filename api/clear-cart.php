<?php
require_once __DIR__ . '/_auth.php';

$user = api_require_user();
$userId = (int)$user['user_id'];

try {
    Database::execute("DELETE FROM cart WHERE user_id = ?", [$userId]);
    api_json(true, ['message' => 'Cleared']);
} catch (Exception $e) {
    error_log("Clear cart error: " . $e->getMessage());
    api_json(false, ['message' => $e->getMessage()], 500);
}
?>
