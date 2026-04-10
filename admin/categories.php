<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();
enforceStaffPanelAccess(['warehouse', 'cashier']);
$panelType = getStaffPanelKey() === 'cashier' ? 'cashier' : 'warehouse';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $category_id = (int)($_POST['category_id'] ?? 0);
    $action = $_POST['action'];
    
    try {
        if ($action === 'create') {
            $name = sanitize($_POST['name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $status = $_POST['status'] ?? 'active';
            
            if (empty($name)) {
                throw new Exception('Tên danh mục không được để trống.');
            }
            
            Database::execute(
                "INSERT INTO categories (name, description, parent_id, sort_order, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$name, $description, $parent_id, $sort_order, $status]
            );
            setFlashMessage('success', 'Đã tạo danh mục mới.');
            logActivity($_SESSION['user_id'], 'create_category', "Created category: $name");
            
        } elseif ($action === 'update') {
            $name = sanitize($_POST['name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $status = $_POST['status'] ?? 'active';
            
            if (empty($name)) {
                throw new Exception('Tên danh mục không được để trống.');
            }
            
            Database::execute(
                "UPDATE categories SET name = ?, description = ?, parent_id = ?, sort_order = ?, status = ?, updated_at = NOW() 
                 WHERE category_id = ?",
                [$name, $description, $parent_id, $sort_order, $status, $category_id]
            );
            setFlashMessage('success', 'Đã cập nhật danh mục.');
            logActivity($_SESSION['user_id'], 'update_category', "Updated category #$category_id");
            
        } elseif ($action === 'delete') {
            // Check if category has children or items
            $hasChildren = Database::fetch("SELECT COUNT(*) as count FROM categories WHERE parent_id = ?", [$category_id])['count'];
            $hasItems = Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE category_id = ?", [$category_id])['count'];
            
            if ($hasChildren > 0) {
                throw new Exception('Không thể xóa danh mục có danh mục con.');
            }
            if ($hasItems > 0) {
                throw new Exception('Không thể xóa danh mục đang có vật phẩm.');
            }
            
            Database::execute("DELETE FROM categories WHERE category_id = ?", [$category_id]);
            setFlashMessage('success', 'Đã xóa danh mục.');
            logActivity($_SESSION['user_id'], 'delete_category', "Deleted category #$category_id");
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
    }
    
    header('Location: categories.php');
    exit();
}

// Get all categories
$categories = Database::fetchAll("
    SELECT c.*, 
           (SELECT COUNT(*) FROM categories WHERE parent_id = c.category_id) as children_count,
           (SELECT COUNT(*) FROM inventory WHERE category_id = c.category_id) as items_count
    FROM categories c
    ORDER BY c.sort_order, c.name
");

// Get parent categories for dropdown
$parentCategories = Database::fetchAll("
    SELECT * FROM categories 
    WHERE parent_id IS NULL 
    ORDER BY sort_order, name
");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý danh mục - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --brand-700: #0e7490;
            --brand-500: #06b6d4;
            --brand-50: #ecfeff;
            --ink-900: #23324a;
            --ink-500: #62718a;
            --line: #d4e8f0;
        }

        body {
            background: #f3f9fc;
        }

        .categories-topbar {
            background: linear-gradient(140deg, #f7fcfe 0%, #ecf7fb 100%);
            border: 1px solid #d7edf3;
            border-radius: 16px;
            padding: 1rem 1.1rem;
            color: #0f172a;
            margin: 0.35rem 0 1.2rem;
            box-shadow: 0 10px 24px rgba(8, 74, 92, 0.07);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .categories-title {
            font-size: clamp(2rem, 3vw, 3.4rem);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: 0.2px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.7rem;
            color: #0f172a;
        }

        .categories-title i {
            font-size: 0.9em;
        }

        .categories-subtitle {
            color: #64748b;
            font-size: clamp(0.95rem, 1.2vw, 1.08rem);
            margin: 0.45rem 0 0;
        }

        .btn-create-category {
            border: 1.5px solid #9fd8e6;
            border-radius: 16px;
            background: #fff;
            color: var(--brand-700);
            font-weight: 700;
            padding: 0.95rem 1.8rem;
            min-height: 76px;
            font-size: 1rem;
        }

        .btn-create-category:hover {
            background: #ecf7fb;
            color: #0b6179;
            border-color: #85c9da;
        }

        @media (max-width: 767.98px) {
            .categories-topbar {
                padding: 1rem;
            }

            .categories-title {
                font-size: 2rem;
            }

            .btn-create-category {
                min-height: 52px;
                padding: 0.7rem 1rem;
                border-radius: 12px;
            }
        }

        .categories-table-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(14, 116, 144, .07);
        }

        .categories-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .categories-table thead th {
            background: linear-gradient(135deg, #083344 0%, #0e7490 100%);
            color: rgba(255, 255, 255, .78);
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            border: none;
            padding: 14px 14px;
            white-space: nowrap;
        }

        .categories-table tbody td {
            border-bottom: 1px solid #edf5f8;
            vertical-align: middle;
            color: var(--ink-900);
            padding: 13px 14px;
            font-size: .88rem;
        }

        .categories-table tbody tr:hover {
            background: #f0fbfe;
        }

        .categories-table tbody tr:last-child td {
            border-bottom: none;
        }

        .status-badge,
        .count-badge {
            border-radius: 999px;
            padding: 4px 11px;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .03em;
        }

        .count-badge {
            background: rgba(14, 116, 144, .12) !important;
            color: var(--brand-700);
        }

        .status-badge.bg-success { background: rgba(22, 163, 74, .13) !important; color: #166534; }
        .status-badge.bg-secondary { background: #eef2f7 !important; color: #475569; }

        .categories-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .categories-actions form {
            margin: 0;
        }

        .categories-action-btn {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .95rem;
            color: #fff;
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .categories-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(6, 182, 212, .25);
        }

        .categories-action-btn:hover {
            transform: translateY(-1px);
        }

        .categories-action-btn.edit {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
        }

        .categories-action-btn.delete {
            background: #ef4444;
        }

        .categories-action-btn i {
            pointer-events: none;
        }

        .modal-content {
            border: none;
            border-radius: 16px;
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, #083344, #0e7490);
            color: #fff;
            border-bottom: none;
            padding: 18px 24px;
        }

        .modal-header .btn-close {
            filter: invert(1) brightness(2);
        }

        .modal-title {
            font-weight: 700;
            font-size: 1rem;
        }

        .modal-body .form-label {
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--ink-500);
        }

        .modal-body .form-control,
        .modal-body .form-select {
            border-color: var(--line);
            border-radius: 10px;
            font-size: .9rem;
        }

        .modal-body .form-control:focus,
        .modal-body .form-select:focus {
            border-color: var(--brand-500);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, .15);
        }

        .modal-footer {
            border-top: 1px solid var(--line);
        }

        .btn-modal-cancel {
            border: 1.5px solid var(--line);
            background: #fff;
            color: var(--ink-500);
            border-radius: 10px;
            font-weight: 500;
            padding: 8px 18px;
        }

        .btn-modal-primary {
            border: none;
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            color: #fff;
            border-radius: 10px;
            font-weight: 600;
            padding: 8px 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php
                if (isStaff() && !isAdmin()) {
                    include 'includes/staff-sidebar.php';
                } else {
                    include 'includes/sidebar.php';
                }
            ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="categories-topbar">
                    <div>
                        <h1 class="categories-title"><i class="bi bi-tags me-2"></i>Quản lý danh mục</h1>
                        <p class="categories-subtitle">Sắp xếp danh mục và cấu trúc phân cấp cho kho vật phẩm</p>
                    </div>
                    <button type="button" class="btn-create-category" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="bi bi-plus-circle me-1"></i>Thêm danh mục
                    </button>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Categories table -->
                <div class="categories-table-card">
                        <div class="table-responsive">
                            <table class="categories-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tên danh mục</th>
                                        <th>Mô tả</th>
                                        <th>Danh mục cha</th>
                                        <th>Thứ tự</th>
                                        <th>Số vật phẩm</th>
                                        <th>Trạng thái</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($categories)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Không có danh mục nào.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td><?php echo $category['category_id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                                    <?php if ($category['children_count'] > 0): ?>
                                                        <br><small class="text-info">
                                                            <i class="bi bi-folder"></i> <?php echo $category['children_count']; ?> danh mục con
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars(substr($category['description'] ?? '', 0, 50)); ?>...</td>
                                                <td>
                                                    <?php 
                                                    if ($category['parent_id']): 
                                                        $parent = Database::fetch("SELECT name FROM categories WHERE category_id = ?", [$category['parent_id']]);
                                                        echo htmlspecialchars($parent['name'] ?? 'N/A');
                                                    else:
                                                        echo '<span class="text-muted">Danh mục gốc</span>';
                                                    endif;
                                                    ?>
                                                </td>
                                                <td><?php echo $category['sort_order']; ?></td>
                                                <td>
                                                    <span class="badge count-badge"><?php echo $category['items_count']; ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge status-badge bg-<?php echo $category['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo $category['status'] === 'active' ? 'Hoạt động' : 'Không hoạt động'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                                                                        <div class="categories-actions">
                                                        <button type="button" 
                                                                class="categories-action-btn edit" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editModal<?php echo $category['category_id']; ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <?php if ($category['items_count'] == 0 && $category['children_count'] == 0): ?>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Xóa danh mục này?');">
                                                                <input type="hidden" name="category_id" value="<?php echo $category['category_id']; ?>">
                                                                <input type="hidden" name="action" value="delete">
                                                                <button type="submit" class="categories-action-btn delete">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Edit Modal -->
                                            <div class="modal" id="editModal<?php echo $category['category_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Chỉnh sửa danh mục</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="category_id" value="<?php echo $category['category_id']; ?>">
                                                                <input type="hidden" name="action" value="update">
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Tên danh mục *</label>
                                                                    <input type="text" 
                                                                           class="form-control" 
                                                                           name="name" 
                                                                           value="<?php echo htmlspecialchars($category['name']); ?>" 
                                                                           required>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Mô tả</label>
                                                                    <textarea class="form-control" 
                                                                              name="description" 
                                                                              rows="3"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Danh mục cha</label>
                                                                    <select class="form-select" name="parent_id">
                                                                        <option value="">Không có (Danh mục gốc)</option>
                                                                        <?php foreach ($parentCategories as $parent): ?>
                                                                            <?php if ($parent['category_id'] != $category['category_id']): ?>
                                                                                <option value="<?php echo $parent['category_id']; ?>" 
                                                                                        <?php echo $category['parent_id'] == $parent['category_id'] ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($parent['name']); ?>
                                                                                </option>
                                                                            <?php endif; ?>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label">Thứ tự</label>
                                                                        <input type="number" 
                                                                               class="form-control" 
                                                                               name="sort_order" 
                                                                               value="<?php echo $category['sort_order']; ?>">
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label">Trạng thái</label>
                                                                        <select class="form-select" name="status">
                                                                            <option value="active" <?php echo $category['status'] === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                                                                            <option value="inactive" <?php echo $category['status'] === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Hủy</button>
                                                                <button type="submit" class="btn-modal-primary">Cập nhật</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Modal -->
    <div class="modal" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Thêm danh mục mới</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label class="form-label">Tên danh mục *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Danh mục cha</label>
                            <select class="form-select" name="parent_id">
                                <option value="">Không có (Danh mục gốc)</option>
                                <?php foreach ($parentCategories as $parent): ?>
                                    <option value="<?php echo $parent['category_id']; ?>">
                                        <?php echo htmlspecialchars($parent['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Thứ tự</label>
                                <input type="number" class="form-control" name="sort_order" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" name="status">
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Không hoạt động</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn-modal-primary">Tạo mới</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



