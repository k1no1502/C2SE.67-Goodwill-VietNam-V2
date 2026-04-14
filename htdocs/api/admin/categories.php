<?php
require_once __DIR__ . '/_base.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $category_id = (int)($payload['category_id'] ?? 0);
    $action = $payload['action'] ?? '';

    try {
        if ($action === 'create') {
            $name = sanitize($payload['name'] ?? '');
            $description = sanitize($payload['description'] ?? '');
            $parent_id = !empty($payload['parent_id']) ? (int)$payload['parent_id'] : null;
            $sort_order = (int)($payload['sort_order'] ?? 0);
            $status = $payload['status'] ?? 'active';

            if ($name === '') {
                throw new Exception('Name required.');
            }

            Database::execute(
                "INSERT INTO categories (name, description, parent_id, sort_order, status, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$name, $description, $parent_id, $sort_order, $status]
            );
            logActivity($currentUserId, 'create_category', "Created category: $name");
            api_json(true, ['message' => 'Created']);
        }

        if ($action === 'update') {
            if ($category_id <= 0) {
                throw new Exception('Invalid category.');
            }
            $name = sanitize($payload['name'] ?? '');
            $description = sanitize($payload['description'] ?? '');
            $parent_id = !empty($payload['parent_id']) ? (int)$payload['parent_id'] : null;
            $sort_order = (int)($payload['sort_order'] ?? 0);
            $status = $payload['status'] ?? 'active';

            if ($name === '') {
                throw new Exception('Name required.');
            }

            Database::execute(
                "UPDATE categories SET name = ?, description = ?, parent_id = ?, sort_order = ?, status = ?, updated_at = NOW()
                 WHERE category_id = ?",
                [$name, $description, $parent_id, $sort_order, $status, $category_id]
            );
            logActivity($currentUserId, 'update_category', "Updated category #$category_id");
            api_json(true, ['message' => 'Updated']);
        }

        if ($action === 'delete') {
            if ($category_id <= 0) {
                throw new Exception('Invalid category.');
            }
            $hasChildren = (int)(Database::fetch("SELECT COUNT(*) as count FROM categories WHERE parent_id = ?", [$category_id])['count'] ?? 0);
            $hasItems = (int)(Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE category_id = ?", [$category_id])['count'] ?? 0);

            if ($hasChildren > 0) {
                throw new Exception('Category has children.');
            }
            if ($hasItems > 0) {
                throw new Exception('Category has items.');
            }

            Database::execute("DELETE FROM categories WHERE category_id = ?", [$category_id]);
            logActivity($currentUserId, 'delete_category', "Deleted category #$category_id");
            api_json(true, ['message' => 'Deleted']);
        }

        api_json(false, ['message' => 'Unsupported action.'], 400);
    } catch (Exception $e) {
        error_log('Admin categories error: ' . $e->getMessage());
        api_json(false, ['message' => $e->getMessage()], 500);
    }
}

try {
    $categories = Database::fetchAll(
        "SELECT c.*, 
               (SELECT COUNT(*) FROM categories WHERE parent_id = c.category_id) as children_count,
               (SELECT COUNT(*) FROM inventory WHERE category_id = c.category_id) as items_count
         FROM categories c
         ORDER BY c.sort_order, c.name"
    );

    $parentCategories = Database::fetchAll(
        "SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort_order, name"
    );

    api_json(true, [
        'categories' => $categories,
        'parents' => $parentCategories
    ]);
} catch (Exception $e) {
    error_log('Admin categories list error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to load categories.'], 500);
}
?>
