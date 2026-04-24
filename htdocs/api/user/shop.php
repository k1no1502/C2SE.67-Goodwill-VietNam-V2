<?php
require_once __DIR__ . '/../_auth.php';

$category_id = (int)($_GET['category'] ?? 0);
$price_type = trim((string)($_GET['price_type'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'newest'));
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = max(5, min(50, (int)($_GET['per_page'] ?? 12)));
$offset = ($page - 1) * $per_page;

$inventoryCols = Database::fetchAll(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory'
           AND COLUMN_NAME IN ('is_for_sale','price_type','sale_price','unit')"
);
$hasCol = array_column($inventoryCols, 'COLUMN_NAME', 'COLUMN_NAME');
$hasIsForSale = isset($hasCol['is_for_sale']);
$hasPriceType = isset($hasCol['price_type']);
$hasSalePrice = isset($hasCol['sale_price']);
$hasUnit = isset($hasCol['unit']);

$where = ["i.status = 'available'"];
$params = [];

if ($hasIsForSale) {
    $where[] = 'i.is_for_sale = 1';
}
if ($category_id > 0) {
    $where[] = 'i.category_id = ?';
    $params[] = $category_id;
}
if ($price_type !== '' && $hasPriceType) {
    $where[] = 'i.price_type = ?';
    $params[] = $price_type;
}
if ($search !== '') {
    $where[] = '(i.name LIKE ? OR i.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereClause = implode(' AND ', $where);

$countSql = "SELECT COUNT(*) as count FROM inventory i WHERE $whereClause";
$totalItems = (int)(Database::fetch($countSql, $params)['count'] ?? 0);
$totalPages = max(1, (int)ceil($totalItems / $per_page));

$orderBy = 'i.created_at DESC';
if ($sort === 'oldest') {
    $orderBy = 'i.created_at ASC';
} elseif ($sort === 'name') {
    $orderBy = 'i.name ASC';
} elseif ($sort === 'price_asc' && $hasSalePrice) {
    $orderBy = 'i.sale_price ASC';
} elseif ($sort === 'price_desc' && $hasSalePrice) {
    $orderBy = 'i.sale_price DESC';
}

$priceTypeSelect = $hasPriceType ? 'i.price_type' : "'free' AS price_type";
$salePriceSelect = $hasSalePrice ? 'i.sale_price' : '0 AS sale_price';
$unitSelect = $hasUnit ? "IFNULL(i.unit, 'item')" : "'item' AS unit";

$sql = "SELECT 
            i.*,
            $priceTypeSelect,
            $salePriceSelect,
            $unitSelect,
            c.name AS category_name,
            c.icon AS category_icon,
            GREATEST(i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id), 0), 0) AS available_quantity
        FROM inventory i
        LEFT JOIN categories c ON i.category_id = c.category_id
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
$paramsWithLimit = array_merge($params, [$per_page, $offset]);
$items = Database::fetchAll($sql, $paramsWithLimit);

$categories = Database::fetchAll("SELECT category_id, name, icon FROM categories WHERE status = 'active' ORDER BY sort_order, name");

api_json(true, [
    'items' => $items,
    'categories' => $categories,
    'filters' => [
        'category' => $category_id,
        'price_type' => $price_type,
        'search' => $search,
        'sort' => $sort,
    ],
    'pagination' => [
        'total' => $totalItems,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $totalPages,
    ]
]);
?>
