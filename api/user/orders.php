<?php
require_once __DIR__ . '/../_auth.php';

$user = api_require_user();
$status = trim((string)($_GET['status'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = max(5, min(50, (int)($_GET['per_page'] ?? 10)));
$offset = ($page - 1) * $per_page;

$where = 'o.user_id = ?';
$params = [(int)$user['user_id']];
if ($status !== '') {
    $where .= ' AND o.status = ?';
    $params[] = $status;
}

$totalRow = Database::fetch("SELECT COUNT(*) as count FROM orders o WHERE $where", $params);
$totalItems = (int)($totalRow['count'] ?? 0);
$totalPages = max(1, (int)ceil($totalItems / $per_page));

$sql = "SELECT o.*, COUNT(oi.order_item_id) AS line_items, COALESCE(SUM(oi.quantity), 0) AS total_quantity
        FROM orders o
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE $where
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?";
$paramsList = array_merge($params, [$per_page, $offset]);
$orders = Database::fetchAll($sql, $paramsList);

$statusRows = Database::fetchAll(
    "SELECT status, COUNT(*) as count FROM orders WHERE user_id = ? GROUP BY status",
    [(int)$user['user_id']]
);
$statusCounts = [];
foreach ($statusRows as $row) {
    $statusCounts[$row['status']] = (int)($row['count'] ?? 0);
}

api_json(true, [
    'orders' => $orders,
    'status_counts' => $statusCounts,
    'pagination' => [
        'total' => $totalItems,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $totalPages,
    ]
]);
?>
