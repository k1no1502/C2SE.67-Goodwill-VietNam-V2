<?php
require_once __DIR__ . '/../_auth.php';

$user = api_require_user();

api_json(true, [
    'user' => [
        'user_id' => (int)$user['user_id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role_id' => (int)$user['role_id'],
        'role' => $user['role_name'],
        'avatar' => $user['avatar'],
        'phone' => $user['phone'],
        'address' => $user['address'],
        'status' => $user['status']
    ]
]);
?>
