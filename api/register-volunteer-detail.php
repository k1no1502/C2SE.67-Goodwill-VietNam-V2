<?php
require_once __DIR__ . '/_auth.php';

$user = api_require_user();
$userId = (int)$user['user_id'];

try {
    $campaign_id = (int)($_POST['campaign_id'] ?? 0);
    $skills = sanitize($_POST['skills'] ?? '');
    $availability = sanitize($_POST['availability'] ?? '');
    $role = sanitize($_POST['role'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if ($campaign_id <= 0) {
        throw new Exception('Invalid campaign.');
    }

    $campaign = Database::fetch(
        "SELECT * FROM campaigns WHERE campaign_id = ? AND status = 'active'",
        [$campaign_id]
    );

    if (!$campaign) {
        throw new Exception('Campaign not available.');
    }

    $existing = Database::fetch(
        "SELECT * FROM campaign_volunteers WHERE campaign_id = ? AND user_id = ?",
        [$campaign_id, $userId]
    );

    if ($existing) {
        throw new Exception('Already registered.');
    }

    $columns = Database::fetchAll("SHOW COLUMNS FROM campaign_volunteers LIKE 'role'");
    if (!empty($columns)) {
        Database::execute(
            "INSERT INTO campaign_volunteers (campaign_id, user_id, skills, availability, role, message, status, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW())",
            [$campaign_id, $userId, $skills, $availability, $role, $message]
        );
    } else {
        Database::execute(
            "INSERT INTO campaign_volunteers (campaign_id, user_id, skills, availability, message, status, created_at) 
             VALUES (?, ?, ?, ?, ?, 'approved', NOW())",
            [$campaign_id, $userId, $skills, $availability, $message]
        );
    }

    logActivity($userId, 'register_volunteer', "Registered as volunteer for campaign #$campaign_id with details");

    api_json(true, ['message' => 'Registered']);
} catch (Exception $e) {
    error_log("Register volunteer error: " . $e->getMessage());
    api_json(false, ['message' => $e->getMessage()], 400);
}
?>
