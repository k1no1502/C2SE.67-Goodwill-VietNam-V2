<?php
require_once __DIR__ . '/_base.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $cart_id = (int)($payload['cart_id'] ?? 0);
    $action = $payload['action'] ?? '';

    try {
        if ($action === 'update_quantity') {
            $quantity = (int)($payload['quantity'] ?? 1);
            if ($cart_id <= 0 || $quantity <= 0) {
                throw new Exception('Invalid quantity.');
            }

            $cart = Database::fetch(
                "SELECT c.*, i.quantity as inventory_quantity,
                        (i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id AND cart_id != ?), 0)) as available_quantity
                 FROM cart c
                 JOIN inventory i ON c.item_id = i.item_id
                 WHERE c.cart_id = ?",
                [$cart_id, $cart_id]
            );

            if (!$cart) {
                throw new Exception('Cart item not found.');
            }

            if ($quantity > (int)$cart['available_quantity']) {
                throw new Exception('Quantity exceeds available.');
            }

            Database::execute(
                "UPDATE cart SET quantity = ?, updated_at = NOW() WHERE cart_id = ?",
                [$quantity, $cart_id]
            );
            logActivity($currentUserId, 'update_cart', "Updated cart #$cart_id quantity to $quantity");
            api_json(true, ['message' => 'Updated']);
        }

        if ($action === 'delete') {
            if ($cart_id <= 0) {
                throw new Exception('Invalid cart item.');
            }
            Database::execute("DELETE FROM cart WHERE cart_id = ?", [$cart_id]);
            logActivity($currentUserId, 'delete_cart', "Deleted cart item #$cart_id");
            api_json(true, ['message' => 'Deleted']);
        }

        if ($action === 'clear_user_cart') {
            $user_id = (int)($payload['user_id'] ?? 0);
            if ($user_id <= 0) {
                throw new Exception('Invalid user.');
            }
            Database::execute("DELETE FROM cart WHERE user_id = ?", [$user_id]);
            logActivity($currentUserId, 'clear_user_cart', "Cleared cart for user #$user_id");
            api_json(true, ['message' => 'Cleared']);
        }

        api_json(false, ['message' => 'Unsupported action.'], 400);
    } catch (Exception $e) {
        error_log('Admin carts error: ' . $e->getMessage());
        api_json(false, ['message' => $e->getMessage()], 500);
    }
}

try {
    $user_id = (int)($_GET['user_id'] ?? 0);
    $search = $_GET['search'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 20);
    $perPage = max(5, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = '1=1';
    $params = [];

    if ($user_id > 0) {
        $where .= ' AND c.user_id = ?';
        $params[] = $user_id;
    }

    if ($search !== '') {
        $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR i.name LIKE ?)';
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $totalSql = "SELECT COUNT(*) as count FROM cart c
                 LEFT JOIN users u ON c.user_id = u.user_id
                 LEFT JOIN inventory i ON c.item_id = i.item_id
                 WHERE $where";
    $totalCarts = (int)(Database::fetch($totalSql, $params)['count'] ?? 0);
    $totalPages = $perPage > 0 ? (int)ceil($totalCarts / $perPage) : 1;

    $sql = "SELECT c.*, u.name as user_name, u.email as user_email,
                   i.name as item_name, i.sale_price, i.price_type, i.status as item_status,
                   i.quantity as inventory_quantity,
                   cat.name as category_name,
                   (i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id AND cart_id != c.cart_id), 0)) as available_quantity
            FROM cart c
            LEFT JOIN users u ON c.user_id = u.user_id
            LEFT JOIN inventory i ON c.item_id = i.item_id
            LEFT JOIN categories cat ON i.category_id = cat.category_id
            WHERE $where
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $cartItems = Database::fetchAll($sql, $params);

    $users = Database::fetchAll("SELECT user_id, name, email FROM users ORDER BY name");

    $stats = [
        'total_carts' => (int)(Database::fetch("SELECT COUNT(*) as count FROM cart")['count'] ?? 0),
        'total_users' => (int)(Database::fetch("SELECT COUNT(DISTINCT user_id) as count FROM cart")['count'] ?? 0),
        'total_items' => (int)(Database::fetch("SELECT SUM(quantity) as count FROM cart")['count'] ?? 0),
        'total_value' => (float)(Database::fetch(
            "SELECT SUM(c.quantity * COALESCE(i.sale_price, 0)) as total
             FROM cart c
             LEFT JOIN inventory i ON c.item_id = i.item_id"
        )['total'] ?? 0),
    ];

    api_json(true, [
        'cart_items' => $cartItems,
        'users' => $users,
        'stats' => $stats,
        'pagination' => [
            'total' => $totalCarts,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages
        ]
    ]);
} catch (Exception $e) {
    error_log('Admin carts list error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to load carts.'], 500);
}
?>
