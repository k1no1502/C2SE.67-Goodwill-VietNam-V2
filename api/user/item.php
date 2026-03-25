<?php
require_once __DIR__ . '/../_auth.php';

$item_id = (int)($_GET['item_id'] ?? 0);
if ($item_id <= 0) {
    api_json(false, ['message' => 'Missing item_id.'], 400);
}

$inventoryCols = Database::fetchAll(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory'
           AND COLUMN_NAME IN ('price_type','sale_price','unit','is_for_sale')"
);
$hasCol = array_column($inventoryCols, 'COLUMN_NAME', 'COLUMN_NAME');
$hasPriceType = isset($hasCol['price_type']);
$hasSalePrice = isset($hasCol['sale_price']);
$hasUnit = isset($hasCol['unit']);
$hasIsForSale = isset($hasCol['is_for_sale']);

$priceTypeSelect = $hasPriceType ? 'i.price_type' : "'free' AS price_type";
$salePriceSelect = $hasSalePrice ? 'i.sale_price' : '0 AS sale_price';
$unitSelect = $hasUnit ? "IFNULL(i.unit, 'item')" : "'item' AS unit";

$where = "i.item_id = ?";
$params = [$item_id];
if ($hasIsForSale) {
    $where .= ' AND i.is_for_sale = 1';
}

$item = Database::fetch(
    "SELECT i.*, $priceTypeSelect, $salePriceSelect, $unitSelect, c.name AS category_name, c.icon AS category_icon
     FROM inventory i
     LEFT JOIN categories c ON i.category_id = c.category_id
     WHERE $where",
    $params
);

if (!$item) {
    api_json(false, ['message' => 'Item not found.'], 404);
}

$availableRow = Database::fetch(
    "SELECT GREATEST(i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id), 0), 0) AS available_quantity
     FROM inventory i WHERE i.item_id = ?",
    [$item_id]
);
$item['available_quantity'] = (int)($availableRow['available_quantity'] ?? 0);

$related = Database::fetchAll(
    "SELECT i.item_id, i.name, i.description, i.images, $priceTypeSelect, $salePriceSelect
     FROM inventory i
     WHERE i.item_id <> ? AND i.status = 'available'" . ($hasIsForSale ? ' AND i.is_for_sale = 1' : '') .
    " ORDER BY i.created_at DESC LIMIT 8",
    [$item_id]
);

api_json(true, [
    'item' => $item,
    'related' => $related,
]);
?>
