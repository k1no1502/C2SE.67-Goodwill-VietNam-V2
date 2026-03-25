<?php
require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../../includes/volunteer_tracking_helper.php';

try {
    $campaigns = Database::fetchAll(
        "SELECT c.*,
               (SELECT COUNT(*) FROM campaign_volunteers cv WHERE cv.campaign_id = c.campaign_id AND cv.status = 'approved') AS volunteers_joined,
               (SELECT COUNT(*) FROM campaign_tasks t WHERE t.campaign_id = c.campaign_id) AS tasks_total,
               (SELECT COUNT(*) FROM campaign_tasks t WHERE t.campaign_id = c.campaign_id AND t.status = 'completed') AS tasks_completed
         FROM campaigns c
         ORDER BY c.created_at DESC
         LIMIT 100"
    );

    api_json(true, ['campaigns' => $campaigns]);
} catch (Exception $e) {
    error_log('Admin assignments error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to load assignments.'], 500);
}
?>
