<?php
require_once __DIR__ . '/../_auth.php';

$category_id = (int)($_GET['category'] ?? 0);
$search = trim((string)($_GET['search'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = max(5, min(50, (int)($_GET['per_page'] ?? 12)));
$offset = ($page - 1) * $per_page;

$where = ["d.status = 'approved'"];
$params = [];
if ($category_id > 0) {
    $where[] = 'd.category_id = ?';
    $params[] = $category_id;
}
if ($search !== '') {
    $where[] = '(d.item_name LIKE ? OR d.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereClause = implode(' AND ', $where);

$totalRow = Database::fetch("SELECT COUNT(*) as count FROM donations d WHERE $whereClause", $params);
$totalItems = (int)($totalRow['count'] ?? 0);
$totalPages = max(1, (int)ceil($totalItems / $per_page));

$sql = "SELECT d.*, c.name as category_name, c.icon as category_icon, u.name as donor_name
        FROM donations d 
        LEFT JOIN categories c ON d.category_id = c.category_id 
        LEFT JOIN users u ON d.user_id = u.user_id
        WHERE $whereClause 
        ORDER BY d.created_at DESC 
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$items = Database::fetchAll($sql, $params);

$categories = Database::fetchAll("SELECT category_id, name, icon FROM categories WHERE status = 'active' ORDER BY sort_order, name");

api_json(true, [
    'items' => $items,
    'categories' => $categories,
    'filters' => [
        'category' => $category_id,
        'search' => $search,
    ],
    'pagination' => [
        'total' => $totalItems,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $totalPages,
    ]
]);
?>
