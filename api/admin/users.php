<?php
require_once __DIR__ . '/../_auth.php';

$currentUser = api_require_admin();
$currentUserId = (int)($currentUser['user_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $user_id = (int)($payload['user_id'] ?? 0);
    $action = $payload['action'] ?? '';

    if ($user_id <= 0 || $action === '') {
        api_json(false, ['message' => 'Invalid request.'], 400);
    }

    try {
        if ($action === 'update_status') {
            $status = $payload['status'] ?? '';
            $allowedStatuses = ['active', 'inactive', 'banned'];
            if (!in_array($status, $allowedStatuses, true)) {
                throw new Exception('Invalid status.');
            }
            Database::execute(
                "UPDATE users SET status = ?, updated_at = NOW() WHERE user_id = ?",
                [$status, $user_id]
            );
            logActivity($currentUserId, 'update_user_status', "Updated user #$user_id status to $status");
            api_json(true, ['message' => 'Updated']);
        }

        if ($action === 'update_role') {
            $role_id = (int)($payload['role_id'] ?? 0);
            if ($role_id <= 0) {
                throw new Exception('Invalid role.');
            }
            Database::execute(
                "UPDATE users SET role_id = ?, updated_at = NOW() WHERE user_id = ?",
                [$role_id, $user_id]
            );
            logActivity($currentUserId, 'update_user_role', "Updated user #$user_id role to $role_id");
            api_json(true, ['message' => 'Updated']);
        }

        api_json(false, ['message' => 'Unsupported action.'], 400);
    } catch (Exception $e) {
        error_log('Admin users error: ' . $e->getMessage());
        api_json(false, ['message' => $e->getMessage()], 500);
    }
}

try {
    $status = $_GET['status'] ?? '';
    $role_id = (int)($_GET['role'] ?? 0);
    $search = $_GET['search'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 20);
    $perPage = max(5, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = '1=1';
    $params = [];

    if ($status !== '') {
        $where .= ' AND u.status = ?';
        $params[] = $status;
    }

    if ($role_id > 0) {
        $where .= ' AND u.role_id = ?';
        $params[] = $role_id;
    }

    if ($search !== '') {
        $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $roles = Database::fetchAll("SELECT * FROM roles ORDER BY role_id");

    $totalSql = "SELECT COUNT(*) as count FROM users u WHERE $where";
    $totalUsers = (int)(Database::fetch($totalSql, $params)['count'] ?? 0);
    $totalPages = $perPage > 0 ? (int)ceil($totalUsers / $perPage) : 1;

    $sql = "SELECT u.*, r.role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.role_id
            WHERE $where
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $users = Database::fetchAll($sql, $params);

    $stats = [
        'total' => (int)(Database::fetch("SELECT COUNT(*) as count FROM users")['count'] ?? 0),
        'active' => (int)(Database::fetch("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'] ?? 0),
        'inactive' => (int)(Database::fetch("SELECT COUNT(*) as count FROM users WHERE status = 'inactive'")['count'] ?? 0),
        'banned' => (int)(Database::fetch("SELECT COUNT(*) as count FROM users WHERE status = 'banned'")['count'] ?? 0),
    ];

    api_json(true, [
        'roles' => $roles,
        'users' => $users,
        'stats' => $stats,
        'pagination' => [
            'total' => $totalUsers,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages
        ]
    ]);
} catch (Exception $e) {
    error_log('Admin users list error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to load users.'], 500);
}
?>
