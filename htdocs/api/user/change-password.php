<?php
require_once __DIR__ . '/../_auth.php';

$user = api_require_user();
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$current_password = (string)($payload['current_password'] ?? '');
$new_password = (string)($payload['new_password'] ?? '');

if ($current_password === '' || $new_password === '') {
    api_json(false, ['message' => 'Missing password fields.'], 400);
}

if (!verifyPassword($current_password, $user['password'])) {
    api_json(false, ['message' => 'Current password is incorrect.'], 400);
}

try {
    $hash = hashPassword($new_password);
    Database::execute(
        "UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?",
        [$hash, (int)$user['user_id']]
    );
    logActivity((int)$user['user_id'], 'change_password', 'Changed password');
    api_json(true, ['message' => 'Password updated.']);
} catch (Exception $e) {
    error_log('change-password error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to update password.'], 500);
}
?>
