<?php
require_once __DIR__ . '/../_auth.php';

$campaign_id = (int)($_GET['campaign_id'] ?? 0);
if ($campaign_id <= 0) {
    api_json(false, ['message' => 'Missing campaign_id.'], 400);
}

$user = null;
$token = api_get_bearer_token();
if ($token) {
    $user = api_get_user_from_token($token);
}

$campaign = Database::fetch(
    "SELECT c.*, u.name as creator_name
     FROM campaigns c
     LEFT JOIN users u ON c.created_by = u.user_id
     WHERE c.campaign_id = ?",
    [$campaign_id]
);

if (!$campaign) {
    api_json(false, ['message' => 'Campaign not found.'], 404);
}

$items = Database::fetchAll(
    "SELECT ci.*, c.name as category_name,
            CASE WHEN ci.quantity_needed > 0 THEN ROUND((ci.quantity_received / ci.quantity_needed) * 100, 2) ELSE 0 END AS progress_percentage
     FROM campaign_items ci
     LEFT JOIN categories c ON ci.category_id = c.category_id
     WHERE ci.campaign_id = ?
     ORDER BY ci.item_id ASC",
    [$campaign_id]
);

$donations = Database::fetchAll(
    "SELECT cd.*, d.item_name, d.quantity, d.unit, d.created_at, u.name as donor_name
     FROM campaign_donations cd
     JOIN donations d ON cd.donation_id = d.donation_id
     LEFT JOIN users u ON d.user_id = u.user_id
     WHERE cd.campaign_id = ?
     ORDER BY cd.created_at DESC
     LIMIT 20",
    [$campaign_id]
);

$volunteers = Database::fetchAll(
    "SELECT cv.*, u.name as user_name, u.email as user_email
     FROM campaign_volunteers cv
     LEFT JOIN users u ON cv.user_id = u.user_id
     WHERE cv.campaign_id = ?
     ORDER BY cv.created_at DESC
     LIMIT 20",
    [$campaign_id]
);

$registeredByMe = false;
$myVolunteerStatus = null;
if ($user) {
    $mine = Database::fetch(
        "SELECT status FROM campaign_volunteers WHERE campaign_id = ? AND user_id = ?",
        [$campaign_id, (int)$user['user_id']]
    );
    if ($mine) {
        $registeredByMe = true;
        $myVolunteerStatus = $mine['status'];
    }
}

api_json(true, [
    'campaign' => $campaign,
    'items' => $items,
    'donations' => $donations,
    'volunteers' => $volunteers,
    'registered_by_me' => $registeredByMe,
    'my_volunteer_status' => $myVolunteerStatus,
]);
?>
