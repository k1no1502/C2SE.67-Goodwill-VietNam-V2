<?php
require_once __DIR__ . '/../_auth.php';

$user = api_require_user();

try {
    Database::execute("UPDATE users SET remember_token = NULL WHERE user_id = ?", [$user['user_id']]);
    api_json(true, ['message' => 'Da dang xuat.']);
} catch (Exception $e) {
    error_log("API logout error: " . $e->getMessage());
    api_json(false, ['message' => 'Co loi xay ra.'], 500);
}
?>
