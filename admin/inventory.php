<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();
enforceStaffPanelAccess(['warehouse']);
$panelType = 'warehouse';

// Handle price type update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $action = $_POST['action'];
    if ($item_id > 0) {
        try {
            if ($action === 'update_price') {
                $price_type   = $_POST['price_type'];
                $sale_price   = (float)($_POST['sale_price'] ?? 0);
                $new_name     = trim($_POST['name'] ?? '');
                $new_desc     = trim($_POST['description'] ?? '');
                $new_image    = trim($_POST['image_path'] ?? '');
                $remove_image = (int)($_POST['remove_image'] ?? 0) === 1;

                $columns   = ['price_type = ?', 'sale_price = ?'];
                $values    = [$price_type, $sale_price];

                if ($new_name !== '') {
                    $columns[] = 'name = ?';
                    $values[]  = $new_name;
                }
                if ($new_desc !== '') {
                    $columns[] = 'description = ?';
                    $values[]  = $new_desc;
                }

                $finalImage = null;
                if (isset($_FILES['image_file']) && ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $uploadResult = uploadFile($_FILES['image_file'], '../uploads/donations/', ['jpg', 'jpeg', 'png', 'gif']);
                    if (!($uploadResult['success'] ?? false)) {
                        throw new Exception('Upload ảnh thất bại: ' . ($uploadResult['message'] ?? 'Unknown error'));
                    }
                    $finalImage = 'uploads/donations/' . $uploadResult['filename'];
                } elseif ($remove_image) {
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
                setFlashMessage('success', 'Đã cập nhật thông tin vật phẩm.');
            } elseif ($action === 'toggle_sale') {
                Database::execute(
                    "UPDATE inventory SET is_for_sale = NOT is_for_sale, updated_at = NOW() WHERE item_id = ?",
                    [$item_id]
                );
                setFlashMessage('success', 'Da cap nhat trang thai ban hang.');
            } elseif ($action === 'delete_item') {
                // Đánh dấu đã loại bỏ để tránh vướng FK tới order_items
                Database::execute(
                    "UPDATE inventory SET status = 'disposed', is_for_sale = 0, updated_at = NOW() WHERE item_id = ?",
                    [$item_id]
                );
                setFlashMessage('success', 'Đã đánh dấu vật phẩm là đã loại bỏ.');
            }
            logActivity($_SESSION['user_id'], 'update_inventory', "Updated inventory item #$item_id");
        } catch (Exception $e) {
            setFlashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
    
    header('Location: inventory.php');
    exit();
}

// Get filters
$price_type = $_GET['price_type'] ?? '';
$category_id = (int)($_GET['category'] ?? 0);
$status = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = "1=1";
$params = [];

if ($price_type !== '') {
    $where .= " AND i.price_type = ?";
    $params[] = $price_type;
}

if ($category_id > 0) {
    $where .= " AND i.category_id = ?";
    $params[] = $category_id;
}

if ($status !== '') {
    $where .= " AND i.status = ?";
    $params[] = $status;
}

// Get categories
$categories = Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY name");

// Get total count
$totalSql = "SELECT COUNT(*) as count FROM inventory i WHERE $where";
$totalItems = Database::fetch($totalSql, $params)['count'];
$totalPages = ceil($totalItems / $per_page);

// Get items
$sql = "SELECT i.*, c.name as category_name, d.item_name as donation_name, u.name as donor_name
        FROM inventory i
        LEFT JOIN categories c ON i.category_id = c.category_id
        LEFT JOIN donations d ON i.donation_id = d.donation_id
        LEFT JOIN users u ON d.user_id = u.user_id
        WHERE $where
        ORDER BY i.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$items = Database::fetchAll($sql, $params);

// Pre-calc stats to display in header cards without repeated queries
$totalInventory = (int)Database::fetch("SELECT COUNT(*) AS count FROM inventory")['count'];
$soldInventory = (int)Database::fetch("SELECT COUNT(*) AS count FROM inventory WHERE status = 'sold'")['count'];
$inventoryStats = [
    'available' => max(0, $totalInventory - $soldInventory),
    'free' => (int)Database::fetch("SELECT COUNT(*) AS count FROM inventory WHERE price_type = 'free'")['count'],
    'cheap' => (int)Database::fetch("SELECT COUNT(*) AS count FROM inventory WHERE price_type = 'cheap'")['count'],
    'sold' => $soldInventory,
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý kho hàng - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --brand-700: #0e7490;
            --brand-600: #0f869f;
            --brand-500: #06B6D4;
            --brand-50:  #ecfeff;
            --ink-900:   #23324a;
            --ink-500:   #62718a;
            --line:      #d4e8f0;
        }
        body {
            font-family: 'Manrope', 'Segoe UI', sans-serif;
            background:
                radial-gradient(circle at 10% 8%, rgba(6, 182, 212, 0.08), transparent 28%),
                radial-gradient(circle at 92% 2%, rgba(14, 116, 144, 0.07), transparent 26%),
                #f3f9fc;
        }

        /* ── Topbar ── */
        .inventory-topbar {
            background: linear-gradient(140deg, #f7fcfe 0%, #ecf7fb 100%);
            border: 1px solid #d7edf3;
            border-radius: 16px;
            padding: 1rem 1.1rem;
            color: #0f172a;
            margin-top: 0.35rem;
            margin-bottom: 1.2rem;
            box-shadow: 0 10px 24px rgba(8, 74, 92, 0.07);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .inventory-topbar-title {
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
        .inventory-topbar-title i {
            font-size: 0.9em;
        }
        .inventory-topbar-sub  {
            color: #64748b;
            font-size: clamp(0.95rem, 1.2vw, 1.08rem);
            margin: 0.45rem 0 0;
        }
        @media (max-width: 767.98px) {
            .inventory-topbar {
                padding: 1rem;
            }
            .inventory-topbar-title {
                font-size: 2rem;
            }
        }

        /* ── Stat cards ── */
        .stat-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 22px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            box-shadow: 0 2px 12px rgba(14,116,144,.07);
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(14,116,144,.13); }
        .stat-label { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-500); margin-bottom: 4px; }
        .stat-value { font-size: 2rem; font-weight: 800; color: var(--ink-900); line-height: 1; }
        .stat-icon  { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
        .stat-available .stat-icon { background: rgba(14,116,144,.1); color: var(--brand-700); }
        .stat-free      .stat-icon { background: rgba(22,163,74,.1);  color: #16a34a; }
        .stat-cheap     .stat-icon { background: rgba(234,179,8,.12);  color: #ca8a04; }
        .stat-sold      .stat-icon { background: rgba(8,145,178,.1);   color: #0891b2; }

        /* ── Filter card ── */
        .inv-filter-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 2px 12px rgba(14,116,144,.06);
            margin-bottom: 1.5rem;
        }
        .inv-filter-card .form-label { font-size: .8rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--ink-500); margin-bottom: 6px; }
        .inv-filter-card .form-select,
        .inv-filter-card .form-control { border-color: var(--line); border-radius: 10px; font-size: .9rem; }
        .inv-filter-card .form-select:focus,
        .inv-filter-card .form-control:focus { border-color: var(--brand-500); box-shadow: 0 0 0 3px rgba(6,182,212,.15); }
        .btn-inv-filter {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            padding: 9px 20px;
            transition: opacity .15s;
        }
        .btn-inv-filter:hover { opacity: .9; color: #fff; }

        /* ── Table card ── */
        .inv-table-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(14,116,144,.07);
        }
        .inv-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .inv-table thead th {
            background: linear-gradient(135deg, #083344 0%, #0e7490 100%);
            color: rgba(255,255,255,.75);
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            padding: 14px 16px;
            border: none;
            white-space: nowrap;
        }
        .inv-table thead th:first-child { border-radius: 0; }
        .inv-table tbody tr { transition: background .12s; }
        .inv-table tbody tr:hover { background: #f0fbfe; }
        .inv-table tbody td {
            padding: 13px 16px;
            border-bottom: 1px solid #edf5f8;
            vertical-align: middle;
            color: var(--ink-900);
            font-size: .9rem;
        }
        .inv-table tbody tr:last-child td { border-bottom: none; }

        /* ── Status badge ── */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .03em;
        }
        .status-badge.bg-success { background: rgba(22,163,74,.12)  !important; color: #166534; }
        .status-badge.bg-warning { background: rgba(234,179,8,.15)  !important; color: #854d0e; }
        .status-badge.bg-primary { background: rgba(14,116,144,.12) !important; color: var(--brand-700); }
        .status-badge.bg-info    { background: rgba(8,145,178,.12)  !important; color: #155e75; }
        .status-badge.bg-danger  { background: rgba(220,38,38,.1)   !important; color: #991b1b; }
        .status-badge.bg-secondary { background: #f1f5f9 !important; color: #64748b; }

        /* ── Action buttons ── */
        .inventory-actions { display: flex; align-items: center; gap: 8px; }
        .inventory-actions form { margin: 0; }
        .inventory-action-btn {
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: #fff;
            transition: transform .15s, box-shadow .15s;
        }
        .inventory-action-btn:hover  { transform: translateY(-1px); }
        .inventory-action-btn:focus  { outline: none; box-shadow: 0 0 0 3px rgba(6,182,212,.25); }
        .inventory-action-btn.edit       { background: linear-gradient(135deg,#0e7490,#06B6D4); }
        .inventory-action-btn.toggle-on  { background: #16a34a; }
        .inventory-action-btn.toggle-off { background: #d97706; }
        .inventory-action-btn.delete     { background: #ef4444; }
        .inventory-action-btn i { pointer-events: none; }

        /* ── Modal ── */
        .inventory-edit-modal .modal-dialog {
            max-width: min(760px, calc(100vw - 1.5rem));
            margin: 0.75rem auto;
        }
        .inventory-edit-modal .modal-content {
            border: 1px solid #d6eaf1;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 44px rgba(5, 73, 92, 0.22);
            max-height: calc(100vh - 1.5rem);
        }
        .inventory-edit-modal .modal-header {
            background: linear-gradient(140deg, #f2fbfe 0%, #e8f4fb 100%);
            border-bottom: 1px solid #d6eaf1;
            padding: 0.95rem 1.25rem;
        }
        .inventory-edit-modal .modal-title {
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }
        .inventory-edit-modal .modal-subtitle {
            color: #64748b;
            font-size: 0.85rem;
            margin-top: 0.2rem;
            font-weight: 600;
        }
        .inventory-edit-modal .modal-body {
            background: #f8fcfe;
            padding: 1.15rem 1.25rem;
            overflow-y: auto;
            overflow-x: hidden;
            max-height: calc(100vh - 220px);
        }
        .inventory-edit-modal .edit-form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.95rem;
        }
        .inventory-edit-modal .edit-form-grid .form-label {
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #334155;
            margin-bottom: 0.35rem;
        }
        .inventory-edit-modal .form-control,
        .inventory-edit-modal .form-select {
            border-color: #c8e3ec;
            border-radius: 10px;
            padding-top: 0.62rem;
            padding-bottom: 0.62rem;
            box-shadow: none;
        }
        .inventory-edit-modal .form-control:focus,
        .inventory-edit-modal .form-select:focus {
            border-color: #0ea5c4;
            box-shadow: 0 0 0 3px rgba(14, 165, 196, 0.15);
        }
        .inventory-edit-modal .input-group-text {
            border-color: #c8e3ec;
            background: #eef8fc;
            color: #0e7490;
            font-weight: 700;
        }
        .inventory-edit-modal .image-editor {
            display: grid;
            gap: 0.55rem;
        }
        .inventory-edit-modal .image-editor-preview {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .inventory-edit-modal .image-editor-actions {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 0.55rem;
            align-items: center;
        }
        .inventory-edit-modal .modal-footer {
            border-top: 1px solid #d6eaf1;
            background: #eff8fc;
            padding: 0.85rem 1.25rem 1rem;
            gap: 0.6rem;
        }
        .inventory-edit-modal .btn-modal-cancel,
        .inventory-edit-modal .btn-modal-submit {
            min-width: 140px;
            border-radius: 999px;
            font-weight: 700;
            padding: 0.58rem 1rem;
        }
        .inventory-edit-modal .btn-modal-cancel {
            background: #fff;
            border: 1px solid #bfdbe6;
            color: #334155;
        }
        .inventory-edit-modal .btn-modal-cancel:hover {
            background: #f8fdff;
            border-color: #9ecad9;
        }
        .inventory-edit-modal .btn-modal-submit {
            border: none;
            color: #fff;
            background: linear-gradient(135deg,#0e7490,#06B6D4);
            box-shadow: 0 10px 20px rgba(14, 116, 144, 0.24);
        }
        .inventory-edit-modal .btn-modal-submit:hover {
            color: #fff;
            filter: brightness(1.03);
        }

        /* ── Pagination ── */
        .inv-pagination .page-link {
            border: 1px solid var(--line);
            color: var(--brand-700);
            border-radius: 8px !important;
            margin: 0 2px;
            font-weight: 500;
            padding: 6px 14px;
            transition: background .15s, color .15s;
        }
        .inv-pagination .page-link:hover    { background: var(--brand-50); color: var(--brand-700); }
        .inv-pagination .page-item.active .page-link {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            border-color: transparent;
            color: #fff;
        }
        .inv-pagination .page-item.disabled .page-link { color: #adb5bd; }

        @media (max-width: 767.98px) {
            .inv-table-card { border-radius: 12px; }
            .stat-value { font-size: 1.6rem; }

            .inventory-edit-modal .modal-dialog {
                margin: 0.7rem;
            }
            .inventory-edit-modal .modal-content {
                max-height: calc(100vh - 1.4rem);
            }
            .inventory-edit-modal .modal-body {
                max-height: calc(100vh - 190px);
            }
            .inventory-edit-modal .image-editor-actions {
                grid-template-columns: 1fr;
            }

            .inventory-edit-modal .btn-modal-cancel,
            .inventory-edit-modal .btn-modal-submit {
                min-width: 0;
                width: 100%;
            }
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
                <div class="inventory-topbar">
                    <div>
                        <h1 class="inventory-topbar-title"><i class="bi bi-box-seam me-2"></i>Quản lý kho hàng</h1>
                        <p class="inventory-topbar-sub">Theo dõi và cập nhật toàn bộ vật phẩm trong kho</p>
                    </div>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Filters -->
                <div class="inv-filter-card">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Loại giá</label>
                            <select class="form-select" name="price_type">
                                <option value="">Tất cả</option>
                                <option value="free" <?php echo $price_type === 'free' ? 'selected' : ''; ?>>Miễn phí</option>
                                <option value="cheap" <?php echo $price_type === 'cheap' ? 'selected' : ''; ?>>Giá rẻ</option>
                                <option value="normal" <?php echo $price_type === 'normal' ? 'selected' : ''; ?>>Giá thường</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Danh mục</label>
                            <select class="form-select" name="category">
                                <option value="">Tất cả danh mục</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>" 
                                            <?php echo $category_id == $cat['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Trạng thái</label>
                            <select class="form-select" name="status">
                                <option value="">Tất cả</option>
                                <option value="available" <?php echo $status === 'available' ? 'selected' : ''; ?>>Có sẵn</option>
                                <option value="reserved" <?php echo $status === 'reserved' ? 'selected' : ''; ?>>Đã đặt</option>
                                <option value="sold" <?php echo $status === 'sold' ? 'selected' : ''; ?>>Đã bán</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn-inv-filter w-100">
                                <i class="bi bi-search me-1"></i>Lọc
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Statistics -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-available">
                            <div>
                                <div class="stat-label">Có sẵn</div>
                                <div class="stat-value"><?php echo number_format($inventoryStats['available']); ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-free">
                            <div>
                                <div class="stat-label">Miễn phí</div>
                                <div class="stat-value"><?php echo number_format($inventoryStats['free']); ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-gift"></i></div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-cheap">
                            <div>
                                <div class="stat-label">Giá rẻ</div>
                                <div class="stat-value"><?php echo number_format($inventoryStats['cheap']); ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card stat-sold">
                            <div>
                                <div class="stat-label">Đã bán</div>
                                <div class="stat-value"><?php echo number_format($inventoryStats['sold']); ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-check2-circle"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Items table -->
                <div class="inv-table-card">
                    <div class="table-responsive">
                        <table class="inv-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Ảnh</th>
                                    <th>Vật phẩm</th>
                                    <th>Danh mục</th>
                                    <th>Số lượng</th>
                                    <th>Loại giá</th>
                                    <th>Giá bán</th>
                                    <th>Trạng thái</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">Không có vật phẩm nào.</td>
                                    </tr>
                                <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo $item['item_id']; ?></td>
                                    <td>
                                        <?php
                                        $images = json_decode($item['images'] ?? '[]', true);
                                        $firstImage = !empty($images)
                                            ? resolveDonationImageUrl((string)$images[0])
                                            : 'uploads/donations/placeholder-default.svg';
                                        ?>
                                        <img src="<?php echo htmlspecialchars($firstImage); ?>"
                                             alt="Ảnh sản phẩm"
                                             style="width: 56px; height: 56px; object-fit: cover; border-radius: 10px; border: 1px solid #d7e9f0;"
                                             onerror="this.src='../uploads/donations/placeholder-default.svg'">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                        <br><small class="text-muted">Từ: <?php echo htmlspecialchars($item['donor_name']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo $item['quantity']; ?> <?php echo $item['unit']; ?></td>
                                    <td>
                                        <?php
                                        $typeMap = [
                                            'free' => ['class' => 'success', 'text' => 'Miễn phí'],
                                            'cheap' => ['class' => 'warning', 'text' => 'Giá rẻ'],
                                            'normal' => ['class' => 'primary', 'text' => 'Giá thường']
                                        ];
                                        $type = $typeMap[$item['price_type']] ?? ['class' => 'secondary', 'text' => 'N/A'];
                                        ?>
                                        <span class="status-badge bg-<?php echo $type['class']; ?>">
                                            <?php echo $type['text']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $item['price_type'] === 'free' ? 'Miễn phí' : formatCurrency($item['sale_price']); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusMap = [
                                            'available' => ['class' => 'success', 'text' => 'Có sẵn'],
                                            'reserved' => ['class' => 'warning', 'text' => 'Đã đặt'],
                                            'sold' => ['class' => 'info', 'text' => 'Đã bán'],
                                            'damaged' => ['class' => 'danger', 'text' => 'Hư hỏng']
                                        ];
                                        $st = $statusMap[$item['status']] ?? ['class' => 'secondary', 'text' => 'N/A'];
                                        ?>
                                        <span class="status-badge bg-<?php echo $st['class']; ?>">
                                            <?php echo $st['text']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="inventory-actions">
                                            <button type="button"
                                                    class="inventory-action-btn edit"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editModal<?php echo $item['item_id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                <input type="hidden" name="action" value="toggle_sale">
                                                <button type="submit"
                                                        class="inventory-action-btn <?php echo $item['is_for_sale'] ? 'toggle-off' : 'toggle-on'; ?>"
                                                        title="<?php echo $item['is_for_sale'] ? 'Ẩn khỏi shop' : 'Hiển thị trong shop'; ?>">
                                                    <i class="bi bi-<?php echo $item['is_for_sale'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Xóa vật phẩm này?');">
                                                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                <input type="hidden" name="action" value="delete_item">
                                                <button type="submit" class="inventory-action-btn delete" title="Xóa">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($items)): ?>
                            <?php foreach ($items as $item): ?>
                                <div class="modal fade inventory-edit-modal" id="editModal<?php echo $item['item_id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" enctype="multipart/form-data">
                                                <div class="modal-header">
                                                    <div>
                                                        <h5 class="modal-title">Cập nhật giá bán</h5>
                                                        <div class="modal-subtitle">Vật phẩm #<?php echo $item['item_id']; ?> • Tùy chỉnh thông tin hiển thị trong kho</div>
                                                    </div>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                    <input type="hidden" name="action" value="update_price">

                                                    <div class="edit-form-grid">
                                                    <div>
                                                        <label class="form-label">Tên vật phẩm</label>
                                                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($item['name']); ?>">
                                                    </div>

                                                    <div>
                                                        <label class="form-label">Mô tả</label>
                                                        <textarea class="form-control" name="description" rows="3" placeholder="Cập nhật mô tả sản phẩm"><?php echo htmlspecialchars($item['description']); ?></textarea>
                                                    </div>
                                                    <?php
                                                    $editImages = json_decode($item['images'] ?? '[]', true);
                                                    $editImageValue = !empty($editImages) ? (string)$editImages[0] : '';
                                                    $editImagePreview = $editImageValue !== ''
                                                        ? resolveDonationImageUrl($editImageValue)
                                                        : 'uploads/donations/placeholder-default.svg';
                                                    ?>
                                                    <div>
                                                        <label class="form-label">Hình ảnh sản phẩm</label>
                                                        <input type="hidden" name="remove_image" id="removeImage<?php echo $item['item_id']; ?>" value="0">
                                                        <div class="image-editor">
                                                            <div class="image-editor-preview">
                                                                <img src="<?php echo htmlspecialchars($editImagePreview); ?>"
                                                                     id="imagePreview<?php echo $item['item_id']; ?>"
                                                                     alt="Preview"
                                                                     style="width: 72px; height: 72px; object-fit: cover; border-radius: 12px; border: 1px solid #d7e9f0;"
                                                                     onerror="this.src='../uploads/donations/placeholder-default.svg'">
                                                                <small class="text-muted">Nhập link đầy đủ hoặc path như picture_Database/ten-anh.jpg</small>
                                                            </div>
                                                            <input type="text"
                                                                   class="form-control"
                                                                   name="image_path"
                                                                   id="imagePath<?php echo $item['item_id']; ?>"
                                                                   value="<?php echo htmlspecialchars($editImageValue); ?>"
                                                                   placeholder="https://... hoặc picture_Database/ten-anh.jpg">
                                                            <div class="image-editor-actions">
                                                                <input type="file"
                                                                       class="form-control"
                                                                       name="image_file"
                                                                       accept="image/png,image/jpeg,image/gif"
                                                                       onchange="previewUploadedImage(this, '<?php echo $item['item_id']; ?>')">
                                                                <button type="button"
                                                                        class="btn btn-sm btn-outline-danger"
                                                                        onclick="markDeleteImage('<?php echo $item['item_id']; ?>')">
                                                                    <i class="bi bi-trash me-1"></i>Xóa ảnh
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Loại giá *</label>
                                                            <select class="form-select" name="price_type" required>
                                                                <option value="free" <?php echo $item['price_type'] === 'free' ? 'selected' : ''; ?>>Miễn phí</option>
                                                                <option value="cheap" <?php echo $item['price_type'] === 'cheap' ? 'selected' : ''; ?>>Giá rẻ</option>
                                                                <option value="normal" <?php echo $item['price_type'] === 'normal' ? 'selected' : ''; ?>>Giá thường</option>
                                                            </select>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <label class="form-label">Giá bán (VNĐ)</label>
                                                            <div class="input-group">
                                                                <span class="input-group-text">VND</span>
                                                                <input type="number"
                                                                       class="form-control"
                                                                       name="sale_price"
                                                                       value="<?php echo $item['sale_price']; ?>"
                                                                       min="0"
                                                                       step="1000">
                                                            </div>
                                                            <small class="text-muted">Để 0 nếu miễn phí</small>
                                                        </div>
                                                    </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">
                                                        <i class="bi bi-x-lg me-1"></i>Huỷ
                                                    </button>
                                                    <button type="submit" class="btn btn-modal-submit">
                                                        <i class="bi bi-check2-circle me-1"></i>Lưu cập nhật
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-4 pb-3">
                            <ul class="pagination inv-pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Ngăn tình trạng hiện nhiều backdrop/modal chồng nhau khi hover/scroll
    document.addEventListener('show.bs.modal', function (event) {
        // Đóng các modal khác đang mở (nếu có)
        document.querySelectorAll('.modal.show').forEach(function (opened) {
            if (opened !== event.target) {
                const instance = bootstrap.Modal.getInstance(opened);
                instance && instance.hide();
            }
        });
        // Xóa bớt backdrop thừa (Bootstrap đẩy khi tạo 2 lớp)
        const backdrops = Array.from(document.querySelectorAll('.modal-backdrop'));
        backdrops.forEach(function (bd, idx) {
            if (idx > 0) bd.remove();
        });
    });

    function markDeleteImage(itemId) {
        const removeInput = document.getElementById('removeImage' + itemId);
        const pathInput = document.getElementById('imagePath' + itemId);
        const preview = document.getElementById('imagePreview' + itemId);
        if (removeInput) removeInput.value = '1';
        if (pathInput) pathInput.value = '';
        if (preview) preview.src = '../uploads/donations/placeholder-default.svg';
    }

    function previewUploadedImage(input, itemId) {
        const removeInput = document.getElementById('removeImage' + itemId);
        const preview = document.getElementById('imagePreview' + itemId);
        if (removeInput) removeInput.value = '0';
        if (!preview || !input.files || !input.files[0]) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
    </script>
</body>
</html>










