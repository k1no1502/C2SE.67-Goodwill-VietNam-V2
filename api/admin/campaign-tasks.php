<?php
require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../../includes/volunteer_tracking_helper.php';

$campaignId = (int)($_GET['campaign_id'] ?? ($_POST['campaign_id'] ?? 0));
if ($campaignId <= 0) {
    api_json(false, ['message' => 'Campaign required.'], 400);
}

$campaign = Database::fetch("SELECT * FROM campaigns WHERE campaign_id = ?", [$campaignId]);
if (!$campaign) {
    api_json(false, ['message' => 'Campaign not found.'], 404);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $payload['action'] ?? '';

    try {
        if ($action === 'create_task') {
            $name = trim((string)($payload['name'] ?? ''));
            $description = trim((string)($payload['description'] ?? ''));
            $taskType = $payload['task_type'] ?? 'support';
            $requiredVolunteers = max(1, (int)($payload['required_volunteers'] ?? 1));
            $estimatedHours = (float)($payload['estimated_hours'] ?? 0);
            $startAt = $payload['start_at'] ?? '';
            $endAt = $payload['end_at'] ?? '';

            if ($name === '') {
                throw new Exception('Task name required.');
            }

            $estimatedMinutes = (int)round(max(0, $estimatedHours) * 60);
            $startValue = $startAt ? date('Y-m-d H:i:s', strtotime($startAt)) : null;
            $endValue = $endAt ? date('Y-m-d H:i:s', strtotime($endAt)) : null;

            Database::execute(
                "INSERT INTO campaign_tasks (campaign_id, name, description, task_type, required_volunteers, estimated_minutes, start_at, end_at, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open', ?)",
                [
                    $campaignId,
                    $name,
                    $description,
                    $taskType,
                    $requiredVolunteers,
                    $estimatedMinutes,
                    $startValue,
                    $endValue,
                    $currentUserId
                ]
            );
            api_json(true, ['message' => 'Created']);
        }

        if ($action === 'update_task_status') {
            $taskId = (int)($payload['task_id'] ?? 0);
            $status = $payload['status'] ?? 'open';
            $allowed = ['open', 'assigned', 'in_progress', 'completed', 'cancelled'];
            if ($taskId > 0 && in_array($status, $allowed, true)) {
                Database::execute(
                    "UPDATE campaign_tasks SET status = ? WHERE task_id = ? AND campaign_id = ?",
                    [$status, $taskId, $campaignId]
                );
                api_json(true, ['message' => 'Updated']);
            }
            throw new Exception('Invalid task or status.');
        }

        if ($action === 'assign_volunteers' || $action === 'bulk_assign_panel') {
            $taskId = (int)($payload['task_id'] ?? 0);
            $role = $payload['role'] ?? 'member';
            $volunteers = $payload['volunteers'] ?? [];
            if ($taskId <= 0) {
                throw new Exception('Task required.');
            }
            if (empty($volunteers)) {
                throw new Exception('Volunteers required.');
            }

            $volunteers = array_values(array_filter(array_map('intval', (array)$volunteers)));
            Database::beginTransaction();
            foreach ($volunteers as $userId) {
                $exists = Database::fetch(
                    "SELECT 1 FROM campaign_task_assignments WHERE task_id = ? AND user_id = ?",
                    [$taskId, $userId]
                );
                if ($exists) {
                    continue;
                }
                Database::execute(
                    "INSERT INTO campaign_task_assignments (task_id, user_id, role, status, assigned_at)
                     VALUES (?, ?, ?, 'assigned', NOW())",
                    [$taskId, $userId, $role]
                );
            }

            Database::execute(
                "UPDATE campaign_tasks SET status = 'assigned' WHERE task_id = ? AND campaign_id = ? AND status = 'open'",
                [$taskId, $campaignId]
            );
            Database::commit();
            api_json(true, ['message' => 'Assigned']);
        }

        api_json(false, ['message' => 'Unsupported action.'], 400);
    } catch (Exception $e) {
        if (Database::getConnection()->inTransaction()) {
            Database::rollback();
        }
        error_log('Admin campaign tasks error: ' . $e->getMessage());
        api_json(false, ['message' => $e->getMessage()], 500);
    }
}

try {
    $tasks = Database::fetchAll(
        "SELECT * FROM campaign_tasks WHERE campaign_id = ? ORDER BY created_at DESC",
        [$campaignId]
    );

    $campaignVolunteers = Database::fetchAll(
        "SELECT cv.user_id, u.name, u.email
         FROM campaign_volunteers cv
         JOIN users u ON u.user_id = cv.user_id
         WHERE cv.campaign_id = ? AND cv.status = 'approved'
         ORDER BY u.name ASC",
        [$campaignId]
    );

    $taskAssignments = Database::fetchAll(
        "SELECT a.*, u.name, u.email, t.name AS task_name
         FROM campaign_task_assignments a
         JOIN users u ON u.user_id = a.user_id
         JOIN campaign_tasks t ON t.task_id = a.task_id
         WHERE t.campaign_id = ?
         ORDER BY a.created_at DESC",
        [$campaignId]
    );

    api_json(true, [
        'campaign' => $campaign,
        'tasks' => $tasks,
        'volunteers' => $campaignVolunteers,
        'assignments' => $taskAssignments
    ]);
} catch (Exception $e) {
    error_log('Admin campaign tasks list error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to load tasks.'], 500);
}
?>
