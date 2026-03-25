<?php
require_once __DIR__ . '/_auth.php';

$user = api_require_user();
$userId = (int)$user['user_id'];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $campaign_id = (int)($input['campaign_id'] ?? 0);

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

    Database::execute(
        "INSERT INTO campaign_volunteers (campaign_id, user_id, status, created_at) 
         VALUES (?, ?, 'approved', NOW())",
        [$campaign_id, $userId]
    );

    logActivity($userId, 'register_volunteer', "Registered as volunteer for campaign #$campaign_id");

    api_json(true, ['message' => 'Registered']);
} catch (Exception $e) {
    error_log("Register volunteer error: " . $e->getMessage());
    api_json(false, ['message' => $e->getMessage()], 400);
}
?>
