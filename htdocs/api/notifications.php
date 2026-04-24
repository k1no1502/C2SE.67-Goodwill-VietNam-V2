<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../includes/notifications_helper.php';

$user = api_require_user();
$userId = (int)$user['user_id'];
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');
$method = $_SERVER['REQUEST_METHOD'];

processScheduledAdminNotifications();

function buildNotificationFilters() {
    return [
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'status' => $_GET['status'] ?? null,
        'type' => $_GET['type'] ?? null
    ];
}

try {
    switch ($action) {
        case 'count':
            api_json(true, ['count' => getUnreadNotificationCount($userId)]);
            break;

        case 'mark-read':
            if ($method !== 'POST') {
                throw new Exception('Invalid method.');
            }
            $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $notifyId = (int)($payload['notify_id'] ?? 0);
            if ($notifyId <= 0) {
                throw new Exception('Invalid notification.');
            }
            markNotificationAsRead($notifyId, $userId);
            api_json(true, ['message' => 'OK']);
            break;

        case 'mark-all':
            if ($method !== 'POST') {
                throw new Exception('Invalid method.');
            }
            markAllNotificationsAsRead($userId);
            api_json(true, ['message' => 'OK']);
            break;

        case 'detail':
            $notifyId = (int)($_GET['id'] ?? 0);
            if ($notifyId <= 0) {
                throw new Exception('Invalid notification.');
            }
            $notification = Database::fetch(
                "SELECT notify_id, title, message, type, category, is_read, action_url, created_at 
                 FROM notifications 
                 WHERE notify_id = ? AND user_id = ? AND created_at <= NOW()",
                [$notifyId, $userId]
            );
            if (!$notification) {
                throw new Exception('Not found.');
            }
            if (!(int)$notification['is_read']) {
                markNotificationAsRead($notifyId, $userId);
                $notification['is_read'] = 1;
            }
            api_json(true, ['data' => $notification]);
            break;

        case 'list':
        default:
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = (int)($_GET['per_page'] ?? 10);
            $perPage = max(5, min(50, $perPage));
            $offset = ($page - 1) * $perPage;

            $filters = buildNotificationFilters();
            $total = countUserNotifications($userId, $filters);
            $notifications = fetchUserNotifications($userId, $filters, $perPage, $offset);

            api_json(true, [
                'data' => $notifications,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1
                ]
            ]);
            break;
    }
} catch (Exception $e) {
    api_json(false, ['message' => $e->getMessage()], 400);
}
?>
