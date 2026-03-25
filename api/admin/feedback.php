<?php
require_once __DIR__ . '/_base.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $fb_id = (int)($payload['fb_id'] ?? 0);
    $action = $payload['action'] ?? '';

    if ($fb_id <= 0 || $action === '') {
        api_json(false, ['message' => 'Invalid request.'], 400);
    }

    try {
        if ($action === 'reply') {
            $admin_reply = sanitize($payload['admin_reply'] ?? '');
            if ($admin_reply === '') {
                throw new Exception('Reply required.');
            }
            Database::execute(
                "UPDATE feedback SET admin_reply = ?, status = 'replied', replied_by = ?, replied_at = NOW(), updated_at = NOW()
                 WHERE fb_id = ?",
                [$admin_reply, $currentUserId, $fb_id]
            );
            logActivity($currentUserId, 'reply_feedback', "Replied to feedback #$fb_id");
            api_json(true, ['message' => 'Replied']);
        }

        if ($action === 'update_status') {
            $status = $payload['status'] ?? '';
            Database::execute(
                "UPDATE feedback SET status = ?, updated_at = NOW() WHERE fb_id = ?",
                [$status, $fb_id]
            );
            logActivity($currentUserId, 'update_feedback_status', "Updated feedback #$fb_id status to $status");
            api_json(true, ['message' => 'Updated']);
        }

        api_json(false, ['message' => 'Unsupported action.'], 400);
    } catch (Exception $e) {
        error_log('Admin feedback error: ' . $e->getMessage());
        api_json(false, ['message' => $e->getMessage()], 500);
    }
}

try {
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 20);
    $perPage = max(5, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = '1=1';
    $params = [];

    if ($status !== '') {
        $where .= ' AND f.status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $where .= ' AND (f.name LIKE ? OR f.email LIKE ? OR f.subject LIKE ? OR f.content LIKE ?)';
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $totalSql = "SELECT COUNT(*) as count FROM feedback f WHERE $where";
    $totalFeedback = (int)(Database::fetch($totalSql, $params)['count'] ?? 0);
    $totalPages = $perPage > 0 ? (int)ceil($totalFeedback / $perPage) : 1;

    $sql = "SELECT f.*, u.name as user_name, u.email as user_email,
                   admin.name as admin_name
            FROM feedback f
            LEFT JOIN users u ON f.user_id = u.user_id
            LEFT JOIN users admin ON f.replied_by = admin.user_id
            WHERE $where
            ORDER BY f.created_at DESC
            LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $feedbackList = Database::fetchAll($sql, $params);

    $stats = [
        'total' => (int)(Database::fetch("SELECT COUNT(*) as count FROM feedback")['count'] ?? 0),
        'pending' => (int)(Database::fetch("SELECT COUNT(*) as count FROM feedback WHERE status = 'pending'")['count'] ?? 0),
        'read' => (int)(Database::fetch("SELECT COUNT(*) as count FROM feedback WHERE status = 'read'")['count'] ?? 0),
        'replied' => (int)(Database::fetch("SELECT COUNT(*) as count FROM feedback WHERE status = 'replied'")['count'] ?? 0),
    ];

    api_json(true, [
        'feedback' => $feedbackList,
        'stats' => $stats,
        'pagination' => [
            'total' => $totalFeedback,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages
        ]
    ]);
} catch (Exception $e) {
    error_log('Admin feedback list error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to load feedback.'], 500);
}
?>
