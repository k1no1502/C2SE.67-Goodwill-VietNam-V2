<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(false, ['message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = sanitize($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    api_json(false, ['message' => 'Email va mat khau bat buoc.'], 400);
}

if (!validateEmail($email)) {
    api_json(false, ['message' => 'Email khong hop le.'], 400);
}

try {
    $user = Database::fetch(
        "SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.email = ? AND u.status = 'active'",
        [$email]
    );

    if (!$user || !verifyPassword($password, $user['password'])) {
        api_json(false, ['message' => 'Email hoac mat khau khong dung.'], 401);
    }

    $token = generateToken();
    Database::execute("UPDATE users SET remember_token = ?, last_login = NOW() WHERE user_id = ?", [$token, $user['user_id']]);
    logActivity($user['user_id'], 'login', 'API login');

    api_json(true, [
        'token' => $token,
        'user' => [
            'user_id' => (int)$user['user_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role_id' => (int)$user['role_id'],
            'role' => $user['role_name'],
            'avatar' => $user['avatar'],
            'phone' => $user['phone'],
            'address' => $user['address']
        ]
    ]);
} catch (Exception $e) {
    error_log("API login error: " . $e->getMessage());
    api_json(false, ['message' => 'Co loi xay ra. Vui long thu lai sau.'], 500);
}
?>
