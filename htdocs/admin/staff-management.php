<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAdmin();

$pageTitle = 'Quản lý nhân viên';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $staffId = (int)($_POST['staff_id'] ?? 0);

    if ($action === 'update_staff' && $staffId > 0) {
        $position = trim((string)($_POST['position'] ?? ''));
        $department = trim((string)($_POST['department'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $assignedArea = trim((string)($_POST['assigned_area'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'active'));

        $allowedStatus = ['active', 'inactive', 'terminated'];
        if (!in_array($status, $allowedStatus, true)) {
            setFlashMessage('error', 'Trạng thái nhân viên không hợp lệ.');
            header('Location: staff-management.php');
            exit();
        }

        try {
            Database::execute(
                "UPDATE staff
                 SET position = ?,
                     department = ?,
                     phone = ?,
                     assigned_area = ?,
                     address = ?,
                     status = ?,
                     updated_at = NOW()
                 WHERE staff_id = ?",
                [
                    $position !== '' ? $position : null,
                    $department !== '' ? $department : null,
                    $phone !== '' ? $phone : null,
                    $assignedArea !== '' ? $assignedArea : null,
                    $address !== '' ? $address : null,
                    $status,
                    $staffId,
                ]
            );

            logActivity((int)($_SESSION['user_id'] ?? 0), 'update_staff_profile', "Updated staff #{$staffId}");
            setFlashMessage('success', 'Đã cập nhật thông tin nhân viên.');
        } catch (Exception $e) {
            error_log('staff-management update error: ' . $e->getMessage());
            setFlashMessage('error', 'Không thể cập nhật nhân viên. Vui lòng thử lại.');
        }

        header('Location: staff-management.php');
        exit();
    }
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$searchFilter = trim((string)($_GET['search'] ?? ''));
$departmentFilter = trim((string)($_GET['department'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = '1=1';
$params = [];

if ($statusFilter !== '') {
    $where .= ' AND s.status = ?';
    $params[] = $statusFilter;
}

if ($departmentFilter !== '') {
    $where .= ' AND s.department = ?';
    $params[] = $departmentFilter;
}

if ($searchFilter !== '') {
    $like = '%' . $searchFilter . '%';
    $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR s.employee_id LIKE ? OR s.position LIKE ? OR s.phone LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$totalRow = Database::fetch(
    "SELECT COUNT(*) AS count
     FROM staff s
     JOIN users u ON u.user_id = s.user_id
     WHERE {$where}",
    $params
);
$totalCount = (int)($totalRow['count'] ?? 0);
$totalPages = max(1, (int)ceil($totalCount / $perPage));

$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;

$staffRows = Database::fetchAll(
    "SELECT s.*, u.name AS user_name, u.email AS user_email, u.status AS user_status,
            app.approved_at
     FROM staff s
     JOIN users u ON u.user_id = s.user_id
     LEFT JOIN (
        SELECT user_id, MAX(reviewed_at) AS approved_at
        FROM recruitment_applications
        WHERE status = 'approved'
        GROUP BY user_id
     ) app ON app.user_id = s.user_id
     WHERE {$where}
     ORDER BY s.created_at DESC
     LIMIT ? OFFSET ?",
    $listParams
);

$departmentRows = Database::fetchAll(
    "SELECT DISTINCT department
     FROM staff
     WHERE department IS NOT NULL AND department <> ''
     ORDER BY department"
);

$stats = [
    'total' => (int)(Database::fetch("SELECT COUNT(*) AS c FROM staff")['c'] ?? 0),
    'active' => (int)(Database::fetch("SELECT COUNT(*) AS c FROM staff WHERE status = 'active'")['c'] ?? 0),
    'inactive' => (int)(Database::fetch("SELECT COUNT(*) AS c FROM staff WHERE status = 'inactive'")['c'] ?? 0),
    'terminated' => (int)(Database::fetch("SELECT COUNT(*) AS c FROM staff WHERE status = 'terminated'")['c'] ?? 0),
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Admin</title>
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

        body { background: #f3f9fc; }

        .staff-topbar {
            background: transparent;
            border: 0;
            border-radius: 16px;
            padding: 0.15rem 0 0.25rem;
            color: #0f172a;
            margin: 0.2rem 0 1rem;
            box-shadow: none;
        }

        .staff-head {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }

        .staff-head-icon {
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

        .staff-head-icon i {
            font-size: 2rem;
            line-height: 1;
        }

        .staff-topbar-title {
            margin: 0;
            color: #0f172a;
            font-weight: 900;
            letter-spacing: 0.1px;
            font-size: clamp(1.7rem, 2.8vw, 2.9rem);
            line-height: 1.08;
        }

        .staff-topbar-sub {
            color: #58718a;
            font-size: clamp(1rem, 1.35vw, 1.15rem);
            margin: 0.35rem 0 0;
            line-height: 1.25;
        }

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
        }

        .stat-label {
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--ink-500);
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 1.9rem;
            font-weight: 800;
            color: var(--ink-900);
            line-height: 1;
        }

        .staff-filter-card,
        .staff-table-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(14,116,144,.07);
        }

        .staff-filter-card {
            padding: 20px 24px;
            margin-bottom: 1.2rem;
        }

        .staff-filter-card .form-label {
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--ink-500);
            margin-bottom: 6px;
        }

        .staff-filter-card .form-control,
        .staff-filter-card .form-select {
            border-color: var(--line);
            border-radius: 10px;
            font-size: .9rem;
        }

        .staff-filter-card .form-control:focus,
        .staff-filter-card .form-select:focus {
            border-color: var(--brand-500);
            box-shadow: 0 0 0 3px rgba(6,182,212,.15);
        }

        .btn-staff-filter {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            padding: 9px 20px;
        }

        .btn-staff-reset {
            border: 1.5px solid var(--line);
            background: #fff;
            color: var(--ink-500);
            border-radius: 10px;
            font-weight: 500;
            padding: 8px 20px;
        }

        .staff-table-card { overflow: hidden; }

        .staff-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .staff-table thead th {
            background: linear-gradient(135deg, #083344 0%, #0e7490 100%);
            color: rgba(255,255,255,.75);
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            padding: 14px 14px;
            border: none;
            white-space: nowrap;
        }

        .staff-table tbody td {
            padding: 12px 14px;
            border-bottom: 1px solid #edf5f8;
            vertical-align: middle;
            color: var(--ink-900);
            font-size: .875rem;
        }

        .staff-table tbody tr:last-child td { border-bottom: none; }
        .staff-table tbody tr:hover { background: #f0fbfe; }

        .status-badge {
            display: inline-block;
            padding: 4px 11px;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .03em;
        }

        .status-badge.bg-success { background: rgba(22,163,74,.12)!important; color: #166534; }
        .status-badge.bg-warning { background: rgba(234,179,8,.15)!important; color: #92400e; }
        .status-badge.bg-danger { background: rgba(239,68,68,.1)!important; color: #991b1b; }

        .staff-action-btn {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .95rem;
            color: #fff;
            background: linear-gradient(135deg,#0e7490,#06B6D4);
        }

        .pagination .page-link {
            border: 1px solid var(--line);
            color: var(--brand-700);
            border-radius: 8px !important;
            margin: 0 2px;
            font-weight: 500;
            padding: 6px 14px;
        }

        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            border-color: transparent;
            color: #fff;
        }

        @media (max-width: 767.98px) {
            .staff-head {
                gap: 0.72rem;
                align-items: flex-start;
            }

            .staff-head-icon {
                width: 56px;
                height: 56px;
                border-radius: 14px;
            }

            .staff-head-icon i { font-size: 1.45rem; }
            .staff-topbar-sub { font-size: 1rem; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
            <div class="staff-topbar">
                <div class="staff-head">
                    <div class="staff-head-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <h1 class="staff-topbar-title">Quản lý nhân viên</h1>
                        <p class="staff-topbar-sub">Quản lý hồ sơ nhân viên đã được duyệt tuyển dụng và điều phối công việc chuyên nghiệp.</p>
                    </div>
                </div>
            </div>

            <?php echo displayFlashMessages(); ?>

            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div>
                            <div class="stat-label">Tổng nhân viên</div>
                            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                        </div>
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div>
                            <div class="stat-label">Đang hoạt động</div>
                            <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
                        </div>
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div>
                            <div class="stat-label">Tạm ngưng</div>
                            <div class="stat-value"><?php echo number_format($stats['inactive']); ?></div>
                        </div>
                        <i class="bi bi-person-dash-fill"></i>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div>
                            <div class="stat-label">Đã nghỉ</div>
                            <div class="stat-value"><?php echo number_format($stats['terminated']); ?></div>
                        </div>
                        <i class="bi bi-person-x-fill"></i>
                    </div>
                </div>
            </div>

            <div class="staff-filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tìm kiếm</label>
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($searchFilter, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Tên, email, mã nhân viên, vị trí...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" class="form-select">
                            <option value="">Tất cả</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Đang hoạt động</option>
                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Tạm ngưng</option>
                            <option value="terminated" <?php echo $statusFilter === 'terminated' ? 'selected' : ''; ?>>Đã nghỉ</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Phòng ban</label>
                        <select name="department" class="form-select">
                            <option value="">Tất cả</option>
                            <?php foreach ($departmentRows as $dep): ?>
                                <?php $depName = (string)($dep['department'] ?? ''); ?>
                                <option value="<?php echo htmlspecialchars($depName, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $departmentFilter === $depName ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($depName, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn-staff-filter w-100"><i class="bi bi-search me-1"></i>Lọc</button>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <a href="staff-management.php" class="btn-staff-reset w-100 text-center text-decoration-none"><i class="bi bi-arrow-clockwise me-1"></i>Reset</a>
                    </div>
                </form>
            </div>

            <div class="staff-table-card">
                <div class="table-responsive">
                    <table class="staff-table">
                        <thead>
                            <tr>
                                <th>Mã NV</th>
                                <th>Nhân viên</th>
                                <th>Vị trí / Phòng ban</th>
                                <th>Liên hệ</th>
                                <th>Khu vực</th>
                                <th>Trạng thái</th>
                                <th>Ngày vào</th>
                                <th>Tác vụ</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($staffRows)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Chưa có dữ liệu nhân viên.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staffRows as $staff): ?>
                                <?php
                                $statusClass = 'warning';
                                $statusLabel = 'Tạm ngưng';
                                if (($staff['status'] ?? '') === 'active') {
                                    $statusClass = 'success';
                                    $statusLabel = 'Đang hoạt động';
                                } elseif (($staff['status'] ?? '') === 'terminated') {
                                    $statusClass = 'danger';
                                    $statusLabel = 'Đã nghỉ';
                                }
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars((string)($staff['employee_id'] ?? '---'), ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars((string)($staff['user_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars((string)($staff['user_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars((string)($staff['position'] ?? 'Chưa cập nhật'), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars((string)($staff['department'] ?? 'Chưa phân phòng ban'), ENT_QUOTES, 'UTF-8'); ?></small>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars((string)($staff['phone'] ?? 'Chưa có'), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <small class="text-muted">TK: <?php echo htmlspecialchars((string)($staff['user_status'] ?? 'unknown'), ENT_QUOTES, 'UTF-8'); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars((string)($staff['assigned_area'] ?? 'Chưa phân công'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="status-badge bg-<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span></td>
                                    <td>
                                        <?php
                                        $hireDate = (string)($staff['hire_date'] ?? '');
                                        echo $hireDate !== '' ? date('d/m/Y', strtotime($hireDate)) : '-';
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="staff-action-btn" data-bs-toggle="modal" data-bs-target="#editStaff<?php echo (int)$staff['staff_id']; ?>" title="Cập nhật">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editStaff<?php echo (int)$staff['staff_id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Cập nhật nhân viên: <?php echo htmlspecialchars((string)($staff['user_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="action" value="update_staff">
                                                    <input type="hidden" name="staff_id" value="<?php echo (int)$staff['staff_id']; ?>">

                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Vị trí</label>
                                                            <input type="text" class="form-control" name="position" value="<?php echo htmlspecialchars((string)($staff['position'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Phòng ban</label>
                                                            <input type="text" class="form-control" name="department" value="<?php echo htmlspecialchars((string)($staff['department'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Số điện thoại</label>
                                                            <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars((string)($staff['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Khu vực phụ trách</label>
                                                            <input type="text" class="form-control" name="assigned_area" value="<?php echo htmlspecialchars((string)($staff['assigned_area'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Trạng thái nhân viên</label>
                                                            <select name="status" class="form-select">
                                                                <option value="active" <?php echo ($staff['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Đang hoạt động</option>
                                                                <option value="inactive" <?php echo ($staff['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Tạm ngưng</option>
                                                                <option value="terminated" <?php echo ($staff['status'] ?? '') === 'terminated' ? 'selected' : ''; ?>>Đã nghỉ</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Mã nhân viên</label>
                                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars((string)($staff['employee_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" disabled>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Địa chỉ</label>
                                                            <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars((string)($staff['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
                                                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
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

                <?php if ($totalPages > 1): ?>
                    <nav class="py-3">
                        <ul class="pagination justify-content-center mb-0">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
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
