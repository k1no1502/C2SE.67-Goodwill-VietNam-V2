<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(false, ['message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$name = sanitize($input['name'] ?? '');
$email = sanitize($input['email'] ?? '');
$phone = sanitize($input['phone'] ?? '');
$password = $input['password'] ?? '';
$confirm = $input['confirm_password'] ?? '';
$address = sanitize($input['address'] ?? '');
$agree = !empty($input['agree_terms']);

if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
    api_json(false, ['message' => 'Vui long nhap day du thong tin bat buoc.'], 400);
}
if (!validateEmail($email)) {
    api_json(false, ['message' => 'Email khong hop le.'], 400);
}
if (strlen($password) < 6) {
    api_json(false, ['message' => 'Mat khau toi thieu 6 ky tu.'], 400);
}
if ($password !== $confirm) {
    api_json(false, ['message' => 'Mat khau xac nhan khong khop.'], 400);
}
if (!$agree) {
    api_json(false, ['message' => 'Vui long dong y dieu khoan su dung.'], 400);
}

try {
    $existing = Database::fetch("SELECT user_id FROM users WHERE email = ?", [$email]);
    if ($existing) {
        api_json(false, ['message' => 'Email da ton tai.'], 409);
    }

    $verificationToken = generateToken();
    $hashed = hashPassword($password);
    Database::execute(
        "INSERT INTO users (name, email, password, phone, address, role_id, status, email_verified, verification_token, created_at)
         VALUES (?, ?, ?, ?, ?, 2, 'active', FALSE, ?, NOW())",
        [$name, $email, $hashed, $phone, $address, $verificationToken]
    );

    $userId = (int)Database::lastInsertId();
    logActivity($userId, 'register', 'API register');

    api_json(true, [
        'user_id' => $userId,
        'message' => 'Dang ky thanh cong.'
    ]);
} catch (Exception $e) {
    error_log("API register error: " . $e->getMessage());
    api_json(false, ['message' => 'Co loi xay ra. Vui long thu lai sau.'], 500);
}
?>
