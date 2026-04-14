<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($user_id > 0) {
        try {
            if ($action === 'update_status') {
                $status = $_POST['status'];
                $allowedStatuses = ['active', 'inactive', 'banned'];

                if (!in_array($status, $allowedStatuses, true)) {
                    throw new Exception('Trạng thái không hợp lệ.');
                }

                Database::execute(
                    "UPDATE users SET status = ?, updated_at = NOW() WHERE user_id = ?",
                    [$status, $user_id]
                );
                setFlashMessage('success', 'Đã cập nhật trạng thái người dùng.');
                logActivity($_SESSION['user_id'], 'update_user_status', "Updated user #$user_id status to $status");
                
            } elseif ($action === 'update_role') {
                $role_id = (int)$_POST['role_id'];
                Database::execute(
                    "UPDATE users SET role_id = ?, updated_at = NOW() WHERE user_id = ?",
                    [$role_id, $user_id]
                );
                setFlashMessage('success', 'Đã cập nhật vai trò người dùng.');
                logActivity($_SESSION['user_id'], 'update_user_role', "Updated user #$user_id role to $role_id");
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
    
    header('Location: users.php');
    exit();
}

// Get filters
$status = $_GET['status'] ?? '';
$role_id = (int)($_GET['role'] ?? 0);
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = "1=1";
$params = [];

if ($status !== '') {
    $where .= " AND u.status = ?";
    $params[] = $status;
}

if ($role_id > 0) {
    $where .= " AND u.role_id = ?";
    $params[] = $role_id;
}

if ($search !== '') {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Get roles
$roles = Database::fetchAll("SELECT * FROM roles ORDER BY role_id");

// Get total count
$totalSql = "SELECT COUNT(*) as count FROM users u WHERE $where";
$totalUsers = Database::fetch($totalSql, $params)['count'];
$totalPages = ceil($totalUsers / $per_page);

// Get users
$sql = "SELECT u.*, r.role_name 
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.role_id
        WHERE $where
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$users = Database::fetchAll($sql, $params);

// Get statistics
$stats = [
    'total' => Database::fetch("SELECT COUNT(*) as count FROM users")['count'],
    'active' => Database::fetch("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'],
    'inactive' => Database::fetch("SELECT COUNT(*) as count FROM users WHERE status = 'inactive'")['count'],
    'banned' => Database::fetch("SELECT COUNT(*) as count FROM users WHERE status = 'banned'")['count'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý người dùng - Admin</title>
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
        body { background: #f3f9fc; }

        /* ── Topbar ── */
        .users-topbar {
            background: transparent;
            border: 0;
            border-radius: 16px;
            padding: 0.15rem 0 0.25rem;
            color: #0f172a;
            margin-top: 0.2rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .users-head {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }
        .users-head-icon {
            width: 74px;
            height: 74px;
            border-radius: 18px;
            background: linear-gradient(145deg, #0b728c, #095f75);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(8, 74, 92, 0.23);
            flex-shrink: 0;
        }
        .users-head-icon i {
            font-size: 2rem;
            line-height: 1;
        }
        .users-topbar-title {
            font-size: clamp(1.7rem, 2.8vw, 2.9rem);
            font-weight: 900;
            line-height: 1.08;
            letter-spacing: 0.1px;
            margin: 0;
            color: #0f172a;
        }
        .users-topbar-sub  {
            color: #58718a;
            font-size: clamp(1rem, 1.35vw, 1.15rem);
            margin: 0.35rem 0 0;
            line-height: 1.25;
        }

        /* ── Stat cards ── */
        .stat-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 20px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            box-shadow: 0 2px 12px rgba(14,116,144,.07);
            transition: transform .18s, box-shadow .18s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(14,116,144,.13); }
        .stat-label { font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-500); margin-bottom: 4px; }
        .stat-value { font-size: 1.9rem; font-weight: 800; color: var(--ink-900); line-height: 1; }
        .stat-icon  { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0; }
        .stat-total    .stat-icon { background: rgba(14,116,144,.1);  color: var(--brand-700); }
        .stat-active   .stat-icon { background: rgba(22,163,74,.12);  color: #16a34a; }
        .stat-inactive .stat-icon { background: rgba(234,179,8,.15);  color: #b45309; }
        .stat-banned   .stat-icon { background: rgba(239,68,68,.1);   color: #ef4444; }

        /* ── Filter card ── */
        .users-filter-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 2px 12px rgba(14,116,144,.06);
            margin-bottom: 1.5rem;
        }
        .users-filter-card .form-label { font-size: .78rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--ink-500); margin-bottom: 6px; }
        .users-filter-card .form-select,
        .users-filter-card .form-control { border-color: var(--line); border-radius: 10px; font-size: .9rem; }
        .users-filter-card .form-select:focus,
        .users-filter-card .form-control:focus { border-color: var(--brand-500); box-shadow: 0 0 0 3px rgba(6,182,212,.15); }
        .btn-users-filter {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            color: #fff; border: none; border-radius: 10px;
            font-weight: 600; padding: 9px 20px; transition: opacity .15s;
        }
        .btn-users-filter:hover { opacity: .9; color: #fff; }

        /* ── Table card ── */
        .users-table-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(14,116,144,.07);
        }
        .users-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .users-table thead th {
            background: linear-gradient(135deg, #083344 0%, #0e7490 100%);
            color: rgba(255,255,255,.75);
            font-size: .7rem; font-weight: 700; letter-spacing: .09em; text-transform: uppercase;
            padding: 14px 16px; border: none; white-space: nowrap;
        }
        .users-table tbody tr { transition: background .12s; }
        .users-table tbody tr:hover { background: #f0fbfe; }
        .users-table tbody td {
            padding: 13px 16px;
            border-bottom: 1px solid #edf5f8;
            vertical-align: middle;
            color: var(--ink-900);
            font-size: .875rem;
        }
        .users-table tbody tr:last-child td { border-bottom: none; }

        /* ── Avatar initials ── */
        .user-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            object-fit: cover; flex-shrink: 0;
        }
        .user-avatar-initials {
            width: 36px; height: 36px; border-radius: 50%;
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            color: #fff; font-size: .8rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .user-info { display: flex; align-items: center; gap: 10px; }

        /* ── Status & role badges ── */
        .status-badge {
            display: inline-block; padding: 4px 11px;
            border-radius: 999px; font-size: .72rem; font-weight: 700; letter-spacing: .03em;
        }
        .status-badge.bg-success   { background: rgba(22,163,74,.12)  !important; color: #166534; }
        .status-badge.bg-warning   { background: rgba(234,179,8,.15)   !important; color: #92400e; }
        .status-badge.bg-danger    { background: rgba(239,68,68,.1)    !important; color: #991b1b; }
        .status-badge.bg-secondary { background: #f1f5f9 !important; color: #475569; }
        .status-badge.bg-info      { background: rgba(8,145,178,.12)   !important; color: #155e75; }
        .role-badge {
            display: inline-block; padding: 3px 10px;
            border-radius: 999px; font-size: .7rem; font-weight: 700;
            background: rgba(14,116,144,.1); color: var(--brand-700);
        }

        /* ── Action button ── */
        .users-action-btn {
            width: 36px; height: 36px; border-radius: 10px; border: none;
            display: flex; align-items: center; justify-content: center;
            font-size: .95rem; color: #fff;
            transition: transform .15s, opacity .15s;
        }
        .users-action-btn:hover { transform: translateY(-1px); }
        .users-action-btn:focus { outline: none; box-shadow: 0 0 0 3px rgba(6,182,212,.25); }
        .users-action-btn.edit { background: linear-gradient(135deg,#0e7490,#06B6D4); }
        .users-action-btn i { pointer-events: none; }

        /* ── Modal ── */
        .modal-content { border: none; border-radius: 16px; overflow: hidden; }
        .modal-header { background: linear-gradient(135deg, #083344, #0e7490); color: #fff; border-bottom: none; padding: 18px 24px; }
        .modal-header .btn-close { filter: invert(1) brightness(2); }
        .modal-title { font-weight: 700; font-size: 1rem; }
        .modal-footer { border-top: 1px solid var(--line); }
        .modal-action-group { display: flex; gap: 10px; flex-wrap: wrap; justify-content: flex-end; align-items: center; }
        .modal-action-btn {
            height: 40px; padding: 0 18px; border: none; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; gap: 6px;
            color: #fff; font-size: .875rem; font-weight: 600;
            transition: transform .15s, opacity .15s;
        }
        .modal-action-btn:hover { transform: translateY(-1px); opacity: .92; }
        .modal-action-btn:focus { outline: none; box-shadow: 0 0 0 3px rgba(6,182,212,.25); }
        .modal-action-btn.cancel { background: #94a3b8; }
        .modal-action-btn.role   { background: linear-gradient(135deg,#0e7490,#06B6D4); }
        .modal-action-btn.status { background: #d97706; }
        .modal-body .form-label { font-size: .78rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--ink-500); }
        .modal-body .form-control,
        .modal-body .form-select { border-color: var(--line); border-radius: 10px; font-size: .9rem; }
        .modal-body .form-control:focus,
        .modal-body .form-select:focus { border-color: var(--brand-500); box-shadow: 0 0 0 3px rgba(6,182,212,.15); }

        /* ── Pagination ── */
        .users-pagination .page-link {
            border: 1px solid var(--line); color: var(--brand-700);
            border-radius: 8px !important; margin: 0 2px;
            font-weight: 500; padding: 6px 14px; transition: background .15s;
        }
        .users-pagination .page-link:hover { background: var(--brand-50); }
        .users-pagination .page-item.active .page-link {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            border-color: transparent; color: #fff;
        }
        .users-pagination .page-item.disabled .page-link { color: #adb5bd; }

        @media (max-width: 767.98px) {
            .users-topbar { padding: 0.05rem 0 0.25rem; }
            .users-head {
                gap: 0.72rem;
                align-items: flex-start;
            }
            .users-head-icon {
                width: 56px;
                height: 56px;
                border-radius: 14px;
            }
            .users-head-icon i { font-size: 1.45rem; }
            .users-topbar-sub { font-size: 1rem; }
            .stat-value { font-size: 1.5rem; }
            .users-table-card { border-radius: 12px; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="users-topbar">
                    <div class="users-head">
                        <div class="users-head-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <h1 class="users-topbar-title">Quản lý người dùng</h1>
                            <p class="users-topbar-sub">Quản lý tài khoản, vai trò và trạng thái người dùng</p>
                        </div>
                    </div>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Statistics -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="stat-card stat-total">
                            <div>
                                <div class="stat-label">Tổng người dùng</div>
                                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-people"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stat-card stat-active">
                            <div>
                                <div class="stat-label">Đang hoạt động</div>
                                <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-person-check"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stat-card stat-inactive">
                            <div>
                                <div class="stat-label">Không hoạt động</div>
                                <div class="stat-value"><?php echo number_format($stats['inactive']); ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-person-dash"></i></div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stat-card stat-banned">
                            <div>
                                <div class="stat-label">Đã khóa</div>
                                <div class="stat-value"><?php echo number_format($stats['banned']); ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-person-slash"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="users-filter-card">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Tìm kiếm</label>
                            <input type="text"
                                   class="form-control"
                                   name="search"
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   placeholder="Tên, email, SĐT...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Trạng thái</label>
                            <select class="form-select" name="status">
                                <option value="">Tất cả</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Đang hoạt động</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                                <option value="banned" <?php echo $status === 'banned' ? 'selected' : ''; ?>>Đã khóa</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Vai trò</label>
                            <select class="form-select" name="role">
                                <option value="">Tất cả</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['role_id']; ?>"
                                            <?php echo $role_id == $role['role_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn-users-filter w-100">
                                <i class="bi bi-search me-1"></i>Lọc
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Users table -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Người dùng</th>
                                        <th>Email</th>
                                        <th>SĐT</th>
                                        <th>Vai trò</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày đăng ký</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Không có người dùng nào.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo $user['user_id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                    <?php if ($user['avatar']): ?>
                                                        <br><img src="../uploads/avatars/<?php echo $user['avatar']; ?>" 
                                                                 class="rounded-circle" 
                                                                 width="30" 
                                                                 height="30" 
                                                                 alt="Avatar">
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($user['role_name'] ?? 'User'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusMap = [
                                                        'active' => ['class' => 'success', 'text' => 'Hoạt động'],
                                                        'inactive' => ['class' => 'warning', 'text' => 'Không hoạt động'],
                                                        'banned' => ['class' => 'danger', 'text' => 'Đã khóa']
                                                    ];
                                                    $st = $statusMap[$user['status']] ?? ['class' => 'secondary', 'text' => 'N/A'];
                                                    ?>
                                                    <span class="badge bg-<?php echo $st['class']; ?>">
                                                        <?php echo $st['text']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($user['created_at']); ?></td>
                                                <td>
                                                    <div class="users-actions">
                                                        <button type="button" 
                                                                class="users-action-btn edit" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editModal<?php echo $user['user_id']; ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Edit Modal -->
                                            <div class="modal" id="editModal<?php echo $user['user_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Chỉnh sửa người dùng</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Tên</label>
                                                                    <input type="text" 
                                                                           class="form-control" 
                                                                           value="<?php echo htmlspecialchars($user['name']); ?>" 
                                                                           readonly>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Email</label>
                                                                    <input type="email" 
                                                                           class="form-control" 
                                                                           value="<?php echo htmlspecialchars($user['email']); ?>" 
                                                                           readonly>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Vai trò *</label>
                                                                    <select class="form-select" name="role_id" required>
                                                                        <?php foreach ($roles as $role): ?>
                                                                            <option value="<?php echo $role['role_id']; ?>" 
                                                                                    <?php echo $user['role_id'] == $role['role_id'] ? 'selected' : ''; ?>>
                                                                                <?php echo htmlspecialchars($role['role_name']); ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Trạng thái *</label>
                                                                    <select class="form-select" name="status" required>
                                                                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                                                                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Không hoạt động</option>
                                                                        <option value="banned" <?php echo $user['status'] === 'banned' ? 'selected' : ''; ?>>Đã khóa</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <div class="modal-action-group">
                                                                    <button type="button" class="modal-action-btn cancel" data-bs-dismiss="modal" title="Há»§y">
                                                                        <i class="bi bi-x-lg"></i>
                                                                    </button>
                                                                    <button type="submit" name="action" value="update_role" class="modal-action-btn role" title="Cáº­p nháº­t vai trá»">
                                                                        <i class="bi bi-pencil-square"></i>
                                                                    </button>
                                                                    <button type="submit" name="action" value="update_status" class="modal-action-btn status" title="Cáº­p nháº­t tráº¡ng thÃ¡i">
                                                                        <i class="bi bi-eye"></i>
                                                                    </button>
                                                                </div>
                                                            </div></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="py-3">
                            <ul class="pagination users-pagination justify-content-center mb-0">
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
</body>
</html>
