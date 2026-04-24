<?php
require_once __DIR__ . '/_bootstrap.php';

function api_get_bearer_token() {
    $authHeader = api_get_header('Authorization');
    if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
        return trim($matches[1]);
    }
    $token = api_get_header('X-Auth-Token');
    if ($token) {
        return trim($token);
    }
    return null;
}

function api_get_user_from_token($token) {
    if (!$token) {
        return null;
    }
    return Database::fetch(
        "SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.remember_token = ? AND u.status = 'active'",
        [$token]
    );
}

function api_require_user() {
    if (isLoggedIn()) {
        $user = Database::fetch(
            "SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ? AND u.status = 'active'",
            [$_SESSION['user_id']]
        );
        if ($user) {
            return $user;
        }
    }

    $token = api_get_bearer_token();
    $user = api_get_user_from_token($token);
    if (!$user) {
        api_json(false, ['message' => 'Unauthorized'], 401);
    }
    return $user;
}

function api_require_admin() {
    $user = api_require_user();
    $roleName = strtolower(trim((string)($user['role_name'] ?? '')));
    $roleId = (int)($user['role_id'] ?? 0);
    if ($roleId !== 1 && !in_array($roleName, ['admin', 'administrator', 'quan tri vien', 'quan tri'], true)) {
        api_json(false, ['message' => 'Forbidden'], 403);
    }
    return $user;
}

function api_require_staff_or_admin() {
    $user = api_require_user();
    $roleName = strtolower(trim((string)($user['role_name'] ?? '')));
    $roleId = (int)($user['role_id'] ?? 0);
    $isAdmin = $roleId === 1 || in_array($roleName, ['admin', 'administrator', 'quan tri vien', 'quan tri'], true);
    $isStaff = $roleId === 4 || in_array($roleName, ['staff', 'nhan vien', 'tu van vien'], true);
    if (!($isAdmin || $isStaff)) {
        api_json(false, ['message' => 'Forbidden'], 403);
    }
    return $user;
}
?>
