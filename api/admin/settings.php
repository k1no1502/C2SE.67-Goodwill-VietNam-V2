<?php
require_once __DIR__ . '/../_auth.php';

$currentUser = api_require_admin();
$currentUserId = (int)($currentUser['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $payload['action'] ?? '';

    try {
        if ($action === 'update_settings') {
            $tableExists = Database::fetch(
                "SELECT COUNT(*) as count
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = 'system_settings'"
            );

            if (!empty($tableExists['count'])) {
                foreach ($payload as $key => $value) {
                    if ($key !== 'action' && strpos($key, 'setting_') === 0) {
                        $setting_key = str_replace('setting_', '', $key);
                        $setting_value = sanitize((string)$value);
                        Database::execute(
                            "INSERT INTO system_settings (setting_key, setting_value, updated_at)
                             VALUES (?, ?, NOW())
                             ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()",
                            [$setting_key, $setting_value, $setting_value]
                        );
                    }
                }
            }

            logActivity($currentUserId, 'update_settings', 'Updated system settings');
            api_json(true, ['message' => 'Updated']);
        }

        if ($action === 'update_profile') {
            $name = sanitize($payload['admin_name'] ?? '');
            $email = sanitize($payload['admin_email'] ?? '');
            $current_password = $payload['current_password'] ?? '';
            $new_password = $payload['new_password'] ?? '';
            $confirm_password = $payload['confirm_password'] ?? '';

            if ($name === '' || $email === '') {
                throw new Exception('Name and email required.');
            }

            Database::execute(
                "UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE user_id = ?",
                [$name, $email, $currentUserId]
            );

            if ($new_password !== '') {
                if ($current_password === '') {
                    throw new Exception('Current password required.');
                }
                if ($new_password !== $confirm_password) {
                    throw new Exception('Password confirmation mismatch.');
                }

                $user = Database::fetch("SELECT password FROM users WHERE user_id = ?", [$currentUserId]);
                if (!$user || !verifyPassword($current_password, $user['password'])) {
                    throw new Exception('Current password invalid.');
                }

                Database::execute(
                    "UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?",
                    [hashPassword($new_password), $currentUserId]
                );
            }

            logActivity($currentUserId, 'update_profile', 'Updated admin profile');
            api_json(true, ['message' => 'Updated']);
        }

        api_json(false, ['message' => 'Unsupported action.'], 400);
    } catch (Exception $e) {
        error_log('Admin settings error: ' . $e->getMessage());
        api_json(false, ['message' => $e->getMessage()], 500);
    }
}

try {
    $admin = Database::fetch("SELECT * FROM users WHERE user_id = ?", [$currentUserId]);

    $settings = [];
    $tableExists = Database::fetch(
        "SELECT COUNT(*) as count
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'system_settings'"
    );

    if (!empty($tableExists['count'])) {
        $settingsRows = Database::fetchAll("SELECT setting_key, setting_value FROM system_settings");
        foreach ($settingsRows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    api_json(true, [
        'admin' => $admin,
        'settings' => $settings
    ]);
} catch (Exception $e) {
    error_log('Admin settings list error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to load settings.'], 500);
}
?>
