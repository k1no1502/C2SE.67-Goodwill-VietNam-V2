<?php
require_once __DIR__ . '/../_auth.php';

$user = api_require_user();
$status = trim((string)($_GET['status'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = max(5, min(50, (int)($_GET['per_page'] ?? 10)));
$offset = ($page - 1) * $per_page;

$where = 'd.user_id = ?';
$params = [(int)$user['user_id']];
if ($status !== '') {
    $where .= ' AND d.status = ?';
    $params[] = $status;
}

$totalRow = Database::fetch("SELECT COUNT(*) as count FROM donations d WHERE $where", $params);
$totalItems = (int)($totalRow['count'] ?? 0);
$totalPages = max(1, (int)ceil($totalItems / $per_page));

$sql = "SELECT d.*, c.name as category_name, c.icon as category_icon
        FROM donations d
        LEFT JOIN categories c ON d.category_id = c.category_id
        WHERE $where
        ORDER BY d.created_at DESC
        LIMIT ? OFFSET ?";
$paramsList = array_merge($params, [$per_page, $offset]);
$donations = Database::fetchAll($sql, $paramsList);

$statusRows = Database::fetchAll(
    "SELECT status, COUNT(*) as count FROM donations WHERE user_id = ? GROUP BY status",
    [(int)$user['user_id']]
);
$statusCounts = [];
foreach ($statusRows as $row) {
    $statusCounts[$row['status']] = (int)($row['count'] ?? 0);
}

api_json(true, [
    'donations' => $donations,
    'status_counts' => $statusCounts,
    'pagination' => [
        'total' => $totalItems,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $totalPages,
    ]
]);
?>
