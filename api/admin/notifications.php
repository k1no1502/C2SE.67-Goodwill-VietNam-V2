<?php
require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../../includes/notifications_helper.php';

processScheduledAdminNotifications();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $title = trim((string)($payload['title'] ?? ''));
    $content = trim((string)($payload['content'] ?? ''));
    $type = $payload['type'] ?? 'system';
    $severity = $payload['severity'] ?? 'info';
    $targetType = $payload['target_type'] ?? 'all';
    $selectedUsers = $payload['target_users'] ?? [];
    $sendMode = $payload['send_mode'] ?? 'now';
    $sendTime = $payload['send_time'] ?? '';

    $errors = [];
    if ($title === '') {
        $errors[] = 'Title required.';
    }
    if ($content === '') {
        $errors[] = 'Content required.';
    }
    if ($targetType === 'selected' && empty($selectedUsers)) {
        $errors[] = 'Select users.';
    }

    $scheduleDate = null;
    if ($sendMode === 'schedule') {
        if ($sendTime === '') {
            $errors[] = 'Schedule time required.';
        } else {
            $timestamp = strtotime($sendTime);
            if ($timestamp && $timestamp > time()) {
                $scheduleDate = date('Y-m-d H:i:s', $timestamp);
            } else {
                $errors[] = 'Schedule time must be in the future.';
            }
        }
    } else {
        $sendMode = 'now';
        $sendTime = '';
    }

    if (!empty($errors)) {
        api_json(false, ['message' => implode(' ', $errors)], 400);
    }

    try {
        $payloadRow = [
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'severity' => $severity,
            'target_type' => $targetType,
            'target_user_ids' => $targetType === 'selected' ? json_encode(array_map('intval', (array)$selectedUsers)) : null,
            'status' => $sendMode === 'schedule' ? 'scheduled' : 'sent',
            'scheduled_at' => $sendMode === 'schedule' ? $scheduleDate : null,
            'sent_at' => $sendMode === 'schedule' ? null : date('Y-m-d H:i:s'),
            'created_by' => $currentUserId
        ];

        $sendNow = $sendMode !== 'schedule';

        Database::execute(
            "INSERT INTO admin_notifications (title, content, type, severity, target_type, target_user_ids, status, scheduled_at, sent_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $payloadRow['title'],
                $payloadRow['content'],
                $payloadRow['type'],
                $payloadRow['severity'],
                $payloadRow['target_type'],
                $payloadRow['target_user_ids'],
                $payloadRow['status'],
                $payloadRow['scheduled_at'],
                $payloadRow['sent_at'],
                $payloadRow['created_by']
            ]
        );

        if ($sendNow) {
            $userIds = resolveNotificationTargetUsers($targetType, $selectedUsers);
            dispatchNotificationBatch($userIds, [
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'severity' => $severity,
                'sent_by' => $currentUserId
            ]);
        }

        api_json(true, ['message' => $sendNow ? 'Sent' : 'Scheduled']);
    } catch (Exception $e) {
        error_log('Admin notifications error: ' . $e->getMessage());
        api_json(false, ['message' => 'Failed to send notification.'], 500);
    }
}

try {
    $activeUsers = Database::fetchAll("SELECT user_id, name, email FROM users WHERE status = 'active' ORDER BY name ASC LIMIT 200");
    $history = Database::fetchAll(
        "SELECT an.*, u.name AS creator_name
         FROM admin_notifications an
         LEFT JOIN users u ON u.user_id = an.created_by
         ORDER BY an.created_at DESC
         LIMIT 25"
    );

    api_json(true, [
        'active_users' => $activeUsers,
        'history' => $history
    ]);
} catch (Exception $e) {
    error_log('Admin notifications list error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to load notifications.'], 500);
}
?>
