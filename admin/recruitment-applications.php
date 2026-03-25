<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();

$error = '';
$success = '';

function getStaffRoleId() {
    $role = Database::fetch("SELECT role_id FROM roles WHERE role_name = 'staff' LIMIT 1");
    if ($role && isset($role['role_id'])) {
        return (int)$role['role_id'];
    }
    Database::execute(
        "INSERT INTO roles (role_id, role_name, description, permissions)
         VALUES (4, 'staff', 'Staff member', '{\"staff\": true}')
         ON DUPLICATE KEY UPDATE role_name = VALUES(role_name), description = VALUES(description), permissions = VALUES(permissions)"
    );
    $role = Database::fetch("SELECT role_id FROM roles WHERE role_name = 'staff' LIMIT 1");
    return (int)($role['role_id'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $note = sanitize($_POST['admin_note'] ?? '');

    if ($applicationId <= 0) {
        $error = 'Dữ liệu không hợp lệ.';
    } else {
        $application = Database::fetch(
            "SELECT * FROM recruitment_applications WHERE application_id = ?",
            [$applicationId]
        );
        if (!$application) {
            $error = 'Không tìm thấy đơn.';
        } elseif (!in_array($application['status'], ['pending'], true)) {
            $error = 'Đơn đã được xử lý.';
        } else {
            try {
                Database::beginTransaction();

                if ($action === 'approve') {
                    $staffRoleId = getStaffRoleId();
                    if ($staffRoleId === 0) {
                        throw new Exception('Missing staff role.');
                    }

                    Database::execute(
                        "UPDATE users SET role_id = ? WHERE user_id = ?",
                        [$staffRoleId, $application['user_id']]
                    );

                    $staff = Database::fetch("SELECT staff_id FROM staff WHERE user_id = ? LIMIT 1", [$application['user_id']]);
                    if (!$staff) {
                        $employeeId = 'GW' . date('ymd') . str_pad((string)$application['user_id'], 4, '0', STR_PAD_LEFT);
                        Database::execute(
                            "INSERT INTO staff (user_id, employee_id, position, phone, hire_date, status, created_at)
                             VALUES (?, ?, ?, ?, CURDATE(), 'active', NOW())",
                            [$application['user_id'], $employeeId, $application['position'], $application['phone']]
                        );
                    }

                    Database::execute(
                        "UPDATE recruitment_applications
                         SET status = 'approved', admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
                         WHERE application_id = ?",
                        [$note, $_SESSION['user_id'], $applicationId]
                    );

                    logActivity($_SESSION['user_id'], 'recruitment_approve', "Approved recruitment application #{$applicationId}");
                    $success = 'Đã phê duyệt đơn. Tài khoản đã chuyển thành nhân viên.';
                } elseif ($action === 'reject') {
                    Database::execute(
                        "UPDATE recruitment_applications
                         SET status = 'rejected', admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
                         WHERE application_id = ?",
                        [$note, $_SESSION['user_id'], $applicationId]
                    );
                    logActivity($_SESSION['user_id'], 'recruitment_reject', "Rejected recruitment application #{$applicationId}");
                    $success = 'Đã từ chối đơn.';
                } else {
                    throw new Exception('Invalid action.');
                }

                Database::commit();
            } catch (Exception $e) {
                Database::rollback();
                error_log('Recruitment approval error: ' . $e->getMessage());
                $error = 'Có lỗi xảy ra. Vui lòng thử lại.';
            }
        }
    }
}

$applications = Database::fetchAll("
    SELECT ra.*, u.name AS account_name, u.email AS account_email
    FROM recruitment_applications ra
    LEFT JOIN users u ON ra.user_id = u.user_id
    ORDER BY FIELD(ra.status, 'pending', 'approved', 'rejected'), ra.created_at DESC
");

$pageTitle = 'Phê duyệt tuyển dụng';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Goodwill Vietnam</title>

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

        .recruitment-topbar {
            background: linear-gradient(140deg, #f7fcfe 0%, #ecf7fb 100%);
            border: 1px solid #d7edf3;
            border-radius: 16px;
            padding: 1rem 1.1rem;
            color: #0f172a;
            margin: 0.35rem 0 1.2rem;
            box-shadow: 0 10px 24px rgba(8, 74, 92, 0.07);
        }

        .recruitment-topbar h1 {
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

        .recruitment-topbar h1 i {
            font-size: 0.9em;
        }

        .recruitment-topbar p {
            color: #64748b;
            font-size: clamp(0.95rem, 1.2vw, 1.08rem);
            margin: 0.45rem 0 0;
            opacity: 1;
        }

        @media (max-width: 767.98px) {
            .recruitment-topbar {
                padding: 1rem;
            }

            .recruitment-topbar h1 {
                font-size: 2rem;
            }
        }

        .application-table-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(14, 116, 144, .07);
        }

        .application-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .application-table thead th {
            background: linear-gradient(135deg, #083344 0%, #0e7490 100%);
            color: rgba(255, 255, 255, .76);
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            border: none;
            padding: 14px 14px;
            white-space: nowrap;
        }

        .application-table tbody td {
            border-bottom: 1px solid #edf5f8;
            vertical-align: middle;
            color: var(--ink-900);
            padding: 13px 14px;
            font-size: .88rem;
        }

        .application-table tbody tr:hover {
            background: #f0fbfe;
        }

        .application-table tbody tr:last-child td {
            border-bottom: none;
        }

        .pending-row {
            background: #fffbeb;
        }

        .status-badge {
            border-radius: 999px;
            padding: 4px 11px;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .03em;
        }

        .status-badge.bg-warning {
            background: rgba(234, 179, 8, .16) !important;
            color: #854d0e;
        }

        .status-badge.bg-success {
            background: rgba(22, 163, 74, .13) !important;
            color: #166534;
        }

        .status-badge.bg-secondary {
            background: #eef2f7 !important;
            color: #475569;
        }

        .btn-cv {
            border: 1.5px solid var(--line);
            color: var(--brand-700);
            border-radius: 10px;
            font-weight: 600;
            font-size: .78rem;
            padding: 4px 10px;
            background: #fff;
        }

        .btn-cv:hover {
            background: var(--brand-50);
            color: var(--brand-700);
        }

        .application-note-input {
            border-color: var(--line);
            border-radius: 10px;
            font-size: .8rem;
            min-width: 170px;
        }

        .application-note-input:focus {
            border-color: var(--brand-500);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, .15);
        }

        .application-action-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .application-action-btn {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .95rem;
            color: #fff;
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .application-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(6, 182, 212, .25);
        }

        .application-action-btn:hover {
            transform: translateY(-1px);
        }

        .application-action-btn.approve {
            background: #16a34a;
        }

        .application-action-btn.reject {
            background: #ef4444;
        }

        .application-action-btn i {
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="recruitment-topbar">
                    <h1>Phê duyệt tuyển dụng</h1>
                    <p>Quản lý và xử lý các đơn ứng tuyển mới cho đội ngũ vận hành.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <div class="application-table-card">
                    <div class="p-3 p-md-4">
                        <?php if (empty($applications)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-people display-4 text-muted"></i>
                                <p class="text-muted mt-3 mb-0">Chưa có đơn ứng tuyển.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="application-table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Ứng viên</th>
                                            <th>Vị trí</th>
                                            <th>Liên hệ</th>
                                            <th>CV</th>
                                            <th>Trạng thái</th>
                                            <th>Ngày gửi</th>
                                            <th>Xử lý</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($applications as $app): ?>
                                            <tr class="<?php echo $app['status'] === 'pending' ? 'pending-row' : ''; ?>">
                                                <td>
                                                    <strong><?php echo htmlspecialchars($app['full_name']); ?></strong><br>
                                                    <small class="text-muted">Account: <?php echo htmlspecialchars($app['account_name'] ?? 'N/A'); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($app['position']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($app['email']); ?><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($app['phone']); ?></small>
                                                </td>
                                                <td>
                                                    <?php if (!empty($app['cv_file'])): ?>
                                                        <a class="btn-cv" href="../uploads/cv/<?php echo htmlspecialchars($app['cv_file']); ?>" target="_blank" rel="noopener">
                                                            Xem CV
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Không có</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($app['status'] === 'pending'): ?>
                                                        <span class="badge status-badge bg-warning">Chờ duyệt</span>
                                                    <?php elseif ($app['status'] === 'approved'): ?>
                                                        <span class="badge status-badge bg-success">Đã duyệt</span>
                                                    <?php else: ?>
                                                        <span class="badge status-badge bg-secondary">Đã từ chối</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($app['created_at']); ?></td>
                                                <td>
                                                    <?php if ($app['status'] === 'pending'): ?>
                                                        <form method="POST" class="d-grid gap-2">
                                                            <input type="hidden" name="application_id" value="<?php echo (int)$app['application_id']; ?>">
                                                            <input type="text" name="admin_note" class="form-control form-control-sm application-note-input" placeholder="Ghi chú (tùy chọn)">
                                                            <div class="application-action-group">
                                                                <button type="submit" name="action" value="approve" class="application-action-btn approve" title="Duyệt">
                                                                    <i class="bi bi-check"></i>
                                                                </button>
                                                                <button type="submit" name="action" value="reject" class="application-action-btn reject" title="Từ chối">
                                                                    <i class="bi bi-x"></i>
                                                                </button>
                                                            </div>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted">Đã xử lý</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
