<?php
require_once __DIR__ . '/_base.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $item_id = (int)($payload['item_id'] ?? 0);
    $action = $payload['action'] ?? '';

    if ($item_id <= 0 || $action === '') {
        api_json(false, ['message' => 'Invalid request.'], 400);
    }

    try {
        if ($action === 'update_price') {
            $price_type = $payload['price_type'] ?? 'free';
            $sale_price = (float)($payload['sale_price'] ?? 0);
            $new_name = trim((string)($payload['name'] ?? ''));
            $new_desc = trim((string)($payload['description'] ?? ''));
            $new_image = trim((string)($payload['image_path'] ?? ''));
            $remove_image = (int)($payload['remove_image'] ?? 0) === 1;

            $columns = ['price_type = ?', 'sale_price = ?'];
            $values = [$price_type, $sale_price];

            if ($new_name !== '') {
                $columns[] = 'name = ?';
                $values[] = $new_name;
            }
            if ($new_desc !== '') {
                $columns[] = 'description = ?';
                $values[] = $new_desc;
            }
            $finalImage = null;
            if ($remove_image) {
                $finalImage = 'placeholder-default.svg';
            } elseif ($new_image !== '') {
                $finalImage = ltrim($new_image, '/');
            }

            if ($finalImage !== null) {
                $columns[] = 'images = ?';
                $values[] = json_encode([$finalImage], JSON_UNESCAPED_UNICODE);
            }

            Database::execute(
                "UPDATE inventory SET " . implode(', ', $columns) . ", updated_at = NOW() WHERE item_id = ?",
                array_merge($values, [$item_id])
            );

            if ($finalImage !== null) {
                $donationId = (int)(Database::fetch(
                    "SELECT donation_id FROM inventory WHERE item_id = ?",
                    [$item_id]
                )['donation_id'] ?? 0);
                if ($donationId > 0) {
                    Database::execute(
                        "UPDATE donations SET images = ?, updated_at = NOW() WHERE donation_id = ?",
                        [json_encode([$finalImage], JSON_UNESCAPED_UNICODE), $donationId]
                    );
                }
            }
            logActivity($currentUserId, 'update_inventory', "Updated inventory item #$item_id");
            api_json(true, ['message' => 'Updated']);
        }

        if ($action === 'toggle_sale') {
            Database::execute(
                "UPDATE inventory SET is_for_sale = NOT is_for_sale, updated_at = NOW() WHERE item_id = ?",
                [$item_id]
            );
            logActivity($currentUserId, 'update_inventory', "Toggled sale for item #$item_id");
            api_json(true, ['message' => 'Updated']);
        }

        if ($action === 'delete_item') {
            Database::execute(
                "UPDATE inventory SET status = 'disposed', is_for_sale = 0, updated_at = NOW() WHERE item_id = ?",
                [$item_id]
            );
            logActivity($currentUserId, 'update_inventory', "Disposed item #$item_id");
            api_json(true, ['message' => 'Disposed']);
        }

        api_json(false, ['message' => 'Unsupported action.'], 400);
    } catch (Exception $e) {
        error_log('Admin inventory error: ' . $e->getMessage());
        api_json(false, ['message' => $e->getMessage()], 500);
    }
}

try {
    $price_type = $_GET['price_type'] ?? '';
    $category_id = (int)($_GET['category'] ?? 0);
    $status = $_GET['status'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 20);
    $perPage = max(5, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = '1=1';
    $params = [];

    if ($price_type !== '') {
        $where .= ' AND i.price_type = ?';
        $params[] = $price_type;
    }

    if ($category_id > 0) {
        $where .= ' AND i.category_id = ?';
        $params[] = $category_id;
    }

    if ($status !== '') {
        $where .= ' AND i.status = ?';
        $params[] = $status;
    }

    $categories = Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY name");

    $totalSql = "SELECT COUNT(*) as count FROM inventory i WHERE $where";
    $totalItems = (int)(Database::fetch($totalSql, $params)['count'] ?? 0);
    $totalPages = $perPage > 0 ? (int)ceil($totalItems / $perPage) : 1;

    $sql = "SELECT i.*, c.name as category_name, d.item_name as donation_name, u.name as donor_name
            FROM inventory i
            LEFT JOIN categories c ON i.category_id = c.category_id
            LEFT JOIN donations d ON i.donation_id = d.donation_id
            LEFT JOIN users u ON d.user_id = u.user_id
            WHERE $where
            ORDER BY i.created_at DESC
            LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $items = Database::fetchAll($sql, $params);

    $totalInventory = (int)(Database::fetch("SELECT COUNT(*) AS count FROM inventory")['count'] ?? 0);
    $soldInventory = (int)(Database::fetch("SELECT COUNT(*) AS count FROM inventory WHERE status = 'sold'")['count'] ?? 0);
    $inventoryStats = [
        'available' => max(0, $totalInventory - $soldInventory),
        'free' => (int)(Database::fetch("SELECT COUNT(*) AS count FROM inventory WHERE price_type = 'free'")['count'] ?? 0),
        'cheap' => (int)(Database::fetch("SELECT COUNT(*) AS count FROM inventory WHERE price_type = 'cheap'")['count'] ?? 0),
        'sold' => $soldInventory,
    ];

    api_json(true, [
        'categories' => $categories,
        'items' => $items,
        'stats' => $inventoryStats,
        'pagination' => [
            'total' => $totalItems,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages
        ]
    ]);
} catch (Exception $e) {
    error_log('Admin inventory list error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to load inventory.'], 500);
}
?>
