<?php
require_once __DIR__ . '/../_auth.php';

$user = api_require_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $name = trim((string)($payload['name'] ?? ''));
    $phone = trim((string)($payload['phone'] ?? ''));
    $address = trim((string)($payload['address'] ?? ''));
    $avatar = trim((string)($payload['avatar'] ?? ''));

    if ($name === '') {
        api_json(false, ['message' => 'Name is required.'], 400);
    }

    try {
        Database::execute(
            "UPDATE users SET name = ?, phone = ?, address = ?, avatar = ?, updated_at = NOW() WHERE user_id = ?",
            [
                $name,
                $phone !== '' ? $phone : null,
                $address !== '' ? $address : null,
                $avatar !== '' ? $avatar : null,
                (int)$user['user_id']
            ]
        );
        logActivity((int)$user['user_id'], 'update_profile', 'Updated profile');
        $user = Database::fetch("SELECT * FROM users WHERE user_id = ?", [(int)$user['user_id']]);
        api_json(true, ['user' => $user]);
    } catch (Exception $e) {
        error_log('profile update error: ' . $e->getMessage());
        api_json(false, ['message' => 'Failed to update profile.'], 500);
    }
}

api_json(true, ['user' => $user]);
?>
