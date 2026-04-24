<?php
require_once __DIR__ . '/../_auth.php';

$user = null;
$token = api_get_bearer_token();
if ($token) {
    $user = api_get_user_from_token($token);
}

$campaigns = Database::fetchAll(
    "SELECT c.*, u.name as creator_name,
            (SELECT COUNT(*) FROM campaign_volunteers WHERE campaign_id = c.campaign_id) as volunteer_count,
            (SELECT COUNT(*) FROM campaign_donations WHERE campaign_id = c.campaign_id) as donation_count
     FROM campaigns c
     LEFT JOIN users u ON c.created_by = u.user_id
     WHERE c.status = 'active' AND c.end_date >= CURDATE()
     ORDER BY c.created_at DESC"
);

if ($user && !empty($campaigns)) {
    $userId = (int)$user['user_id'];
    foreach ($campaigns as &$c) {
        $exists = Database::fetch(
            "SELECT 1 FROM campaign_volunteers WHERE campaign_id = ? AND user_id = ? LIMIT 1",
            [$c['campaign_id'], $userId]
        );
        $c['registered_by_me'] = $exists ? true : false;
    }
    unset($c);
}

api_json(true, ['campaigns' => $campaigns]);
?>
