<?php
require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../../includes/volunteer_tracking_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $payload['action'] ?? '';
    $logId = (int)($payload['log_id'] ?? 0);

    if ($logId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        api_json(false, ['message' => 'Invalid request.'], 400);
    }

    try {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        Database::execute(
            "UPDATE volunteer_hours_logs
             SET status = ?, approved_by = ?, approved_at = NOW()
             WHERE log_id = ?",
            [$status, $currentUserId, $logId]
        );
        api_json(true, ['message' => 'Updated']);
    } catch (Exception $e) {
        error_log('Admin volunteer hours error: ' . $e->getMessage());
        api_json(false, ['message' => $e->getMessage()], 500);
    }
}

try {
    $pendingLogs = Database::fetchAll(
        "SELECT l.*, u.name AS user_name, u.email AS user_email, c.name AS campaign_name, t.name AS task_name
         FROM volunteer_hours_logs l
         JOIN users u ON u.user_id = l.user_id
         JOIN campaigns c ON c.campaign_id = l.campaign_id
         LEFT JOIN campaign_tasks t ON t.task_id = l.task_id
         WHERE l.status = 'pending'
         ORDER BY l.created_at ASC
         LIMIT 50"
    );

    api_json(true, ['pending_logs' => $pendingLogs]);
} catch (Exception $e) {
    error_log('Admin volunteer hours list error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to load logs.'], 500);
}
?>
