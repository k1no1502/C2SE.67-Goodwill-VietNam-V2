<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/donation_tracking_helpers.php';

requireStaffOrAdmin();
enforceStaffPanelAccess(['warehouse']);
$panelType = 'warehouse';
ensureDonationTrackingTable();
$trackingTemplates = getDonationTrackingTemplates();
$trackingStatusOptions = [
    'pending'     => 'Chờ xử lý',
    'in_progress' => 'Đang xử lý',
    'completed'   => 'Hoàn thành'
];
$finalTrackingStepKey = array_key_last($trackingTemplates);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $donation_id = (int)($_POST['donation_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($donation_id > 0) {
        try {
            if ($action === 'approve') {
                Database::beginTransaction();

                // Get donation details up front so we can validate status & reuse data
                $donation = Database::fetch("SELECT * FROM donations WHERE donation_id = ?", [$donation_id]);
                if (!$donation) {
                    throw new Exception('Donation not found.');
                }

                // Only update status when needed to avoid duplicate inventory rows on re-approval
                if ($donation['status'] !== 'approved') {
                    Database::execute(
                        "UPDATE donations SET status = 'approved', updated_at = NOW() WHERE donation_id = ?",
                        [$donation_id]
                    );
                }

                // Add to inventory once per donation
                $existingInventory = Database::fetch(
                    "SELECT item_id FROM inventory WHERE donation_id = ? LIMIT 1",
                    [$donation_id]
                );

                $estimatedValue = isset($donation['estimated_value']) ? (float)$donation['estimated_value'] : 0;
                if ($estimatedValue <= 0) {
                    $priceType = 'free';
                    $salePrice = 0;
                } elseif ($estimatedValue < 100000) {
                    $priceType = 'cheap';
                    $salePrice = $estimatedValue;
                } else {
                    // High-value items should be sold at normal price
                    $priceType = 'normal';
                    $salePrice = $estimatedValue;
                }

                if ($existingInventory) {
                    // Sync pricing and estimated values if the inventory item already exists
                    Database::execute(
                        "UPDATE inventory 
                         SET price_type = ?, sale_price = ?, estimated_value = ?, actual_value = ?, updated_at = NOW()
                         WHERE donation_id = ?",
                        [$priceType, $salePrice, $donation['estimated_value'], $donation['estimated_value'], $donation_id]
                    );
                } else {
                    Database::execute(
                        "INSERT INTO inventory (donation_id, name, description, category_id, quantity, unit, 
                         condition_status, estimated_value, actual_value, images, status, price_type, sale_price, is_for_sale, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', ?, ?, TRUE, NOW())",
                        [
                            $donation_id,
                            $donation['item_name'],
                            $donation['description'],
                            $donation['category_id'],
                            $donation['quantity'],
                            $donation['unit'],
                            $donation['condition_status'],
                            $donation['estimated_value'],
                            $donation['estimated_value'],
                            $donation['images'],
                            $priceType,
                            $salePrice
                        ]
                    );
                }

                Database::commit();
                $message = $existingInventory
                    ? 'Donation was already approved earlier. Inventory kept as-is.'
                    : 'Approved donation and added item to inventory.';
                setFlashMessage('success', $message);
                logActivity($_SESSION['user_id'], 'approve_donation', "Approved donation #$donation_id");

            } elseif ($action === 'reject') {
                Database::execute(
                    "UPDATE donations SET status = 'rejected', admin_notes = ?, updated_at = NOW() WHERE donation_id = ?",
                    [$_POST['reject_reason'] ?? 'Không đạt yêu cầu', $donation_id]
                );
                setFlashMessage('success', 'Đã từ chối quyên góp.');
                logActivity($_SESSION['user_id'], 'reject_donation', "Rejected donation #$donation_id");
            } elseif ($action === 'update_tracking') {
                $steps = $_POST['steps'] ?? [];
                $isJourneyLocked = false;
                if ($finalTrackingStepKey) {
                    $finalStepRow = Database::fetch(
                        "SELECT step_status FROM donation_tracking_steps WHERE donation_id = ? AND step_key = ?",
                        [$donation_id, $finalTrackingStepKey]
                    );
                    if ($finalStepRow && $finalStepRow['step_status'] === 'completed') {
                        $isJourneyLocked = true;
                    }
                }

                if ($isJourneyLocked) {
                    setFlashMessage('error', 'Hành trình đã hoàn tất, không thể chỉnh sửa trạng thái.');
                } else {
                    ensureDonationTrackingTable();
                    Database::beginTransaction();

                    foreach ($trackingTemplates as $stepKey => $template) {
                        $input = $steps[$stepKey] ?? [];
                        $statusValue = array_key_exists(($input['status'] ?? ''), $trackingStatusOptions)
                            ? $input['status']
                            : $template['default_status'];
                        $eventTimeRaw = trim($input['event_time'] ?? '');
                        $eventTime = $eventTimeRaw !== '' ? date('Y-m-d H:i:s', strtotime($eventTimeRaw)) : null;
                        $note = trim($input['note'] ?? '');

                        $existingStep = Database::fetch(
                            "SELECT id FROM donation_tracking_steps WHERE donation_id = ? AND step_key = ?",
                            [$donation_id, $stepKey]
                        );

                        if ($existingStep) {
                            Database::execute(
                                "UPDATE donation_tracking_steps 
                                 SET step_status = ?, event_time = ?, note = ?, updated_at = NOW()
                                 WHERE donation_id = ? AND step_key = ?",
                                [$statusValue, $eventTime, $note ?: null, $donation_id, $stepKey]
                            );
                        } else {
                            Database::execute(
                                "INSERT INTO donation_tracking_steps 
                                    (donation_id, step_key, step_label, description, step_order, step_status, event_time, note)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                                [
                                    $donation_id,
                                    $stepKey,
                                    $template['label'],
                                    $template['description'],
                                    $template['order'],
                                    $statusValue,
                                    $eventTime,
                                    $note ?: null
                                ]
                            );
                        }
                    }

                    Database::commit();
                    setFlashMessage('success', 'Đã cập nhật hành trình quyên góp.');
                    logActivity($_SESSION['user_id'], 'update_donation_tracking', "Updated tracking for donation #$donation_id");
                }
            }
        } catch (Exception $e) {
            Database::rollback();
            setFlashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
    
    header('Location: donations.php');
    exit();
}

// Get donations
$status = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$countsSql = "SELECT 
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
              FROM donations";
$countsData = Database::fetch($countsSql) ?: [
    'total' => 0,
    'pending_count' => 0,
    'approved_count' => 0,
    'rejected_count' => 0
];

$where = "1=1";
$params = [];

if ($status !== '') {
    $where .= " AND d.status = ?";
    $params[] = $status;
}

$totalSql = "SELECT COUNT(*) as count FROM donations d WHERE $where";
$totalDonations = Database::fetch($totalSql, $params)['count'];
$totalPages = ceil($totalDonations / $per_page);

$sql = "SELECT d.*, u.name as donor_name, u.email as donor_email, c.name as category_name,
           cp.campaign_id,
           cp.name as campaign_name
        FROM donations d
        LEFT JOIN users u ON d.user_id = u.user_id
        LEFT JOIN categories c ON d.category_id = c.category_id
    LEFT JOIN campaign_donations cd ON d.donation_id = cd.donation_id
    LEFT JOIN campaigns cp ON cd.campaign_id = cp.campaign_id
        WHERE $where
        ORDER BY d.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$donations = Database::fetchAll($sql, $params);
$donationIds = array_column($donations, 'donation_id');
$trackingMap = getDonationTrackingMap($donationIds);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý quyên góp - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: #f3f9fc;
        }

        .admin-donations {
            padding-top: 1rem;
            padding-bottom: 1.5rem;
        }

        .donations-topbar {
            background: linear-gradient(140deg, #f7fcfe 0%, #ecf7fb 100%);
            border: 1px solid #d7edf3;
            border-radius: 16px;
            padding: 1rem 1.1rem;
            margin-top: 0.35rem;
            margin-bottom: 1rem;
            box-shadow: 0 10px 24px rgba(8, 74, 92, 0.07);
        }

        .donations-topbar .h2 {
            margin: 0;
            color: #0f172a;
            font-weight: 800;
            letter-spacing: 0.2px;
        }

        .donations-topbar-subtitle {
            color: #64748b;
            font-size: 0.92rem;
            margin-top: 0.2rem;
        }

        .donations-tabs-wrap {
            background: #fff;
            border: 1px solid #d7edf3;
            border-radius: 14px;
            box-shadow: 0 8px 20px rgba(8, 74, 92, 0.07);
            padding: 0.55rem;
            margin-bottom: 1rem;
        }

        .donations-tabs {
            margin: 0;
            border-bottom: none;
            gap: 0.4rem;
        }

        .donations-tabs .nav-link {
            border: 1px solid #d7edf3;
            border-radius: 10px;
            color: #0b728c;
            font-weight: 600;
            padding: 0.52rem 0.8rem;
            background: #fff;
        }

        .donations-tabs .nav-link:hover {
            border-color: #b9e0ea;
            background: #f2fbfe;
        }

        .donations-tabs .nav-link.active {
            color: #fff;
            border-color: transparent;
            background: linear-gradient(135deg, #0e7490, #06B6D4);
            box-shadow: 0 8px 16px rgba(8, 111, 137, 0.18);
        }

        .donation-table-card {
            border: 1px solid #d7edf3;
            border-radius: 16px;
            box-shadow: 0 10px 24px rgba(8, 74, 92, 0.08);
            overflow: hidden;
        }

        .donation-table {
            margin-bottom: 0;
        }

        .donation-table thead th {
            background: linear-gradient(140deg, #fbfeff 0%, #edf8fb 100%);
            border-bottom: 1px solid #d7edf3;
            color: #0b728c;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
            padding-top: 0.9rem;
            padding-bottom: 0.9rem;
        }

        .donation-table tbody td {
            border-color: #ecf6fa;
            vertical-align: middle;
            padding-top: 0.9rem;
            padding-bottom: 0.9rem;
        }

        .donation-table tbody tr:hover {
            background: #f8fdff;
        }

        .status-badge {
            border-radius: 999px;
            padding: 0.4rem 0.68rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .donation-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .donation-actions form {
            margin: 0;
        }
        .donation-action-btn {
            width: 52px;
            height: 40px;
            border-radius: 14px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            color: #fff;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .donation-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.25);
        }
        .donation-action-btn:hover {
            transform: translateY(-1px);
        }
        .donation-action-btn.view {
            background-color: #19c1ff;
            color: #00354d;
        }
        .donation-action-btn.approve {
            background-color: #198754;
        }
        .donation-action-btn.edit {
            background-color: #0d6efd;
        }
        .donation-action-btn.track {
            background-color: #0aa1ae;
        }
        .donation-action-btn.reject {
            background-color: #dc3545;
        }
        .donation-action-btn i {
            pointer-events: none;
        }

        .modal-action-btn {
            width: 48px;
            height: 40px;
            border: none;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.2rem;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .modal-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        }
        .modal-action-btn:hover {
            transform: translateY(-1px);
        }
        .modal-action-btn.cancel {
            background-color: #6c757d;
        }
        .modal-action-btn.confirm-reject {
            background-color: #dc3545;
        }

        .tracking-modal .modal-dialog {
            max-width: 920px;
        }

        .tracking-modal .modal-content {
            border: 1px solid #cfe7ef;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 42px rgba(6, 79, 99, 0.2);
        }

        .tracking-modal .modal-header {
            background: linear-gradient(135deg, #f4fbff 0%, #edf7fb 100%);
            border-bottom: 1px solid #d8ebf1;
            padding: 1rem 1.25rem;
        }

        .tracking-modal .modal-title {
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.2px;
        }

        .tracking-modal .modal-body {
            padding: 1rem 1.25rem;
            background: #f8fcfe;
        }

        .tracking-step-card {
            border: 1px solid #d7eaf0;
            border-radius: 12px;
            background: #fff;
            padding: 1rem;
            margin-bottom: 0.85rem;
        }

        .tracking-step-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.9rem;
            margin-bottom: 0.7rem;
        }

        .tracking-step-title {
            font-size: 1.03rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.2rem;
        }

        .tracking-step-desc {
            color: #64748b;
            margin: 0;
            font-size: 0.96rem;
            line-height: 1.45;
        }

        .tracking-field-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.35rem;
        }

        .tracking-modal .form-select,
        .tracking-modal .form-control {
            border-color: #cbe4ec;
            border-radius: 10px;
            padding-top: 0.56rem;
            padding-bottom: 0.56rem;
            box-shadow: none;
        }

        .tracking-modal .form-select:focus,
        .tracking-modal .form-control:focus {
            border-color: #0ea5c4;
            box-shadow: 0 0 0 3px rgba(14, 165, 196, 0.15);
        }

        .tracking-modal .modal-footer {
            background: #f1f8fc;
            border-top: 1px solid #d8ebf1;
            padding: 0.9rem 1.25rem 1rem;
            gap: 0.7rem;
        }

        .tracking-modal .btn-track-cancel,
        .tracking-modal .btn-track-save {
            min-width: 160px;
            border-radius: 999px;
            font-weight: 700;
            padding: 0.6rem 1rem;
        }

        .tracking-modal .btn-track-cancel {
            border: 1px solid #b9dbe7;
            color: #0f172a;
            background: #fff;
        }

        .tracking-modal .btn-track-cancel:hover {
            background: #f7fcff;
            border-color: #95cbdd;
        }

        .tracking-modal .btn-track-save {
            border: none;
            color: #fff;
            background: linear-gradient(135deg, #0f766e 0%, #06b6d4 100%);
            box-shadow: 0 8px 18px rgba(8, 123, 146, 0.24);
        }

        .tracking-modal .btn-track-save:hover {
            color: #fff;
            filter: brightness(1.03);
        }

        .pagination .page-link {
            color: #0b728c;
            border-color: #cce8f1;
            border-radius: 10px !important;
            margin: 0 3px;
            font-weight: 600;
        }

        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #0e7490, #06B6D4);
            border-color: transparent;
        }

        .pagination .page-link:hover {
            background: #ecf7fb;
            color: #0b728c;
        }

        @media (max-width: 768px) {
            .donation-actions {
                gap: 6px;
            }

            .donation-action-btn {
                width: 46px;
                height: 36px;
                border-radius: 12px;
            }

            .donations-tabs-wrap {
                overflow-x: auto;
            }

            .tracking-step-head {
                flex-direction: column;
            }

            .tracking-modal .btn-track-cancel,
            .tracking-modal .btn-track-save {
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

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content admin-donations">
                <div class="donations-topbar d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center gap-2">
                    <div>
                        <h1 class="h2"><i class="bi bi-heart-fill me-2"></i>Quản lý quyên góp</h1>
                        <div class="donations-topbar-subtitle">Theo dõi, duyệt và cập nhật hành trình xử lý quyên góp</div>
                    </div>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Filter tabs -->
                <div class="donations-tabs-wrap">
                    <ul class="nav nav-tabs donations-tabs flex-nowrap">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status === '' ? 'active' : ''; ?>" href="donations.php">
                                Tất cả (<?php echo (int)$countsData['total']; ?>)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status === 'pending' ? 'active' : ''; ?>" href="donations.php?status=pending">
                                Chờ duyệt (<?php echo (int)$countsData['pending_count']; ?>)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status === 'approved' ? 'active' : ''; ?>" href="donations.php?status=approved">
                                Đã duyệt (<?php echo (int)$countsData['approved_count']; ?>)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $status === 'rejected' ? 'active' : ''; ?>" href="donations.php?status=rejected">
                                Từ chối (<?php echo (int)$countsData['rejected_count']; ?>)
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Donations table -->
                <div class="card donation-table-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover donation-table align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Vật phẩm</th>
                                        <th>Người quyên góp</th>
                                        <th>Chiến dịch</th>
                                        <th>Danh mục</th>
                                        <th>Số lượng</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày tạo</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($donations)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">Không có quyên góp nào.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($donations as $donation): ?>
                                            <tr>
                                                <td><?php echo $donation['donation_id']; ?></td>
                                                <?php
                                                    $existingSteps = $trackingMap[$donation['donation_id']] ?? [];
                                                    $finalStepCompleted = $finalTrackingStepKey
                                                        && isset($existingSteps[$finalTrackingStepKey])
                                                        && ($existingSteps[$finalTrackingStepKey]['step_status'] === 'completed');
                                                ?>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($donation['item_name']); ?></strong>
                                                    <?php if ($donation['description']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($donation['description'], 0, 50)); ?>...</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($donation['donor_name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($donation['donor_email']); ?></small>
                                                </td>
                                                <td>
                                                    <?php if (!empty($donation['campaign_id']) && !empty($donation['campaign_name'])): ?>
                                                        <a href="../campaign-detail.php?id=<?php echo (int)$donation['campaign_id']; ?>" target="_blank">
                                                            <?php echo htmlspecialchars($donation['campaign_name']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Không thuộc chiến dịch</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($donation['category_name'] ?? 'Không xác định'); ?></td>
                                                <td><?php echo $donation['quantity']; ?> <?php echo $donation['unit']; ?></td>
                                                <td>
                                                    <?php
                                                    $statusMap = [
                                                        'pending' => ['class' => 'warning', 'text' => 'Chờ duyệt'],
                                                        'approved' => ['class' => 'success', 'text' => 'Đã duyệt'],
                                                        'rejected' => ['class' => 'danger', 'text' => 'Từ chối'],
                                                        'cancelled' => ['class' => 'secondary', 'text' => 'Đã hủy']
                                                    ];
                                                    $status = $statusMap[$donation['status']] ?? ['class' => 'secondary', 'text' => 'Không xác định'];
                                                    ?>
                                                    <span class="badge status-badge bg-<?php echo $status['class']; ?>">
                                                        <?php echo $status['text']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($donation['created_at']); ?></td>
                                                <td>
                                                    <div class="donation-actions">
                                                        <?php if ($finalStepCompleted): ?>
                                                            <button type="button"
                                                                    class="donation-action-btn edit"
                                                                    title="Hành trình đã hoàn tất"
                                                                    disabled>
                                                                <i class="bi bi-pencil-square"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button"
                                                                    class="donation-action-btn edit"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#trackingModal<?php echo $donation['donation_id']; ?>"
                                                                    title="Chỉnh sửa hành trình">
                                                                <i class="bi bi-pencil-square"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <a class="donation-action-btn track"
                                                           href="../donation-tracking.php?id=<?php echo $donation['donation_id']; ?>"
                                                           target="_blank"
                                                           title="Xem trang theo dAõi hAÿnh trAªnh">
                                                            <i class="bi bi-geo-alt"></i>
                                                        </a>
                                                        <?php if ($donation['status'] === 'pending'): ?>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Duyệt quyên góp này?');">
                                                                <input type="hidden" name="donation_id" value="<?php echo $donation['donation_id']; ?>">
                                                                <input type="hidden" name="action" value="approve">
                                                                <button type="submit" class="donation-action-btn approve">
                                                                    <i class="bi bi-check"></i>
                                                                </button>
                                                            </form>
                                                            <button type="button" 
                                                                    class="donation-action-btn reject" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#rejectModal<?php echo $donation['donation_id']; ?>">
                                                                <i class="bi bi-x"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- View Modal -->
                                            <div class="modal" id="viewModal<?php echo $donation['donation_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Chi tiết quyên góp #<?php echo $donation['donation_id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <p><strong>Vật phẩm:</strong> <?php echo htmlspecialchars($donation['item_name']); ?></p>
                                                                    <p><strong>Danh mục:</strong> <?php echo htmlspecialchars($donation['category_name'] ?? 'N/A'); ?></p>
                                                                    <p><strong>Số lượng:</strong> <?php echo $donation['quantity']; ?> <?php echo $donation['unit']; ?></p>
                                                                    <p><strong>Tình trạng:</strong> <?php echo htmlspecialchars($donation['condition_status']); ?></p>
                                                                    <p><strong>Giá trị ước tính:</strong> <?php echo formatCurrency($donation['estimated_value']); ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <p><strong>Người quyên góp:</strong> <?php echo htmlspecialchars($donation['donor_name']); ?></p>
                                                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($donation['donor_email']); ?></p>
                                                                    <p><strong>SĐT:</strong> <?php echo htmlspecialchars($donation['contact_phone'] ?? 'N/A'); ?></p>
                                                                    <p><strong>Địa chỉ nhận:</strong> <?php echo htmlspecialchars($donation['pickup_address'] ?? 'N/A'); ?></p>
                                                                </div>
                                                            </div>
                                                            <p><strong>Mô tả:</strong></p>
                                                            <p><?php echo nl2br(htmlspecialchars($donation['description'] ?? 'Không có mô tả')); ?></p>
                                                            
                                                            <?php
                                                            $images = json_decode($donation['images'] ?? '[]', true);
                                                            if (!empty($images)):
                                                            ?>
                                                                <p><strong>Hình ảnh:</strong></p>
                                                                <div class="row">
                                                                    <?php foreach ($images as $img): ?>
                                                                        <div class="col-md-3 mb-2">
                                                                            <img src="../uploads/donations/<?php echo $img; ?>" 
                                                                                 class="img-fluid rounded" 
                                                                                 alt="Image">
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Tracking Modal -->
                                            <div class="modal fade tracking-modal" id="trackingModal<?php echo $donation['donation_id']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Chỉnh sửa hành trình #<?php echo $donation['donation_id']; ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="donation_id" value="<?php echo $donation['donation_id']; ?>">
                                                                <input type="hidden" name="action" value="update_tracking">
                                                                <?php
                                                                $existingSteps = $trackingMap[$donation['donation_id']] ?? [];
                                                                foreach ($trackingTemplates as $stepKey => $template):
                                                                    $current = $existingSteps[$stepKey] ?? null;
                                                                    $currentStatus = $current['step_status'] ?? $template['default_status'];
                                                                    $currentEventTime = $current && !empty($current['event_time']) ? date('Y-m-d\\TH:i', strtotime($current['event_time'])) : '';
                                                                    $currentNote = $current['note'] ?? '';
                                                                ?>
                                                                    <div class="tracking-step-card">
                                                                        <div class="tracking-step-head">
                                                                            <div>
                                                                                <div class="tracking-step-title"><?php echo htmlspecialchars($template['label']); ?></div>
                                                                                <p class="tracking-step-desc"><?php echo htmlspecialchars($template['description']); ?></p>
                                                                            </div>
                                                                            <div class="tracking-status-col" style="min-width:220px;">
                                                                                <label class="tracking-field-label">Trạng thái</label>
                                                                                <select class="form-select" name="steps[<?php echo $stepKey; ?>][status]">
                                                                                    <?php foreach ($trackingStatusOptions as $statusKey => $statusLabel): ?>
                                                                                        <option value="<?php echo $statusKey; ?>" <?php echo $statusKey === $currentStatus ? 'selected' : ''; ?>>
                                                                                            <?php echo $statusLabel; ?>
                                                                                        </option>
                                                                                    <?php endforeach; ?>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                        <div class="row g-3">
                                                                            <div class="col-md-6">
                                                                                <label class="tracking-field-label">Thời gian cập nhật</label>
                                                                                <input type="datetime-local"
                                                                                       class="form-control"
                                                                                       name="steps[<?php echo $stepKey; ?>][event_time]"
                                                                                       value="<?php echo $currentEventTime; ?>">
                                                                            </div>
                                                                            <div class="col-md-6">
                                                                                <label class="tracking-field-label">Ghi chú</label>
                                                                                <input type="text"
                                                                                       class="form-control"
                                                                                       name="steps[<?php echo $stepKey; ?>][note]"
                                                                                       value="<?php echo htmlspecialchars($currentNote); ?>">
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <div class="modal-footer justify-content-end">
                                                                <button type="button" class="btn btn-track-cancel" data-bs-dismiss="modal">Huỷ bỏ</button>
                                                                <button type="submit" class="btn btn-track-save">Lưu hành trình</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Reject Modal -->
                                            <div class="modal" id="rejectModal<?php echo $donation['donation_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Từ chối quyên góp</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="donation_id" value="<?php echo $donation['donation_id']; ?>">
                                                                <input type="hidden" name="action" value="reject">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Lý do từ chối:</label>
                                                                    <textarea class="form-control" name="reject_reason" rows="3" required></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer gap-2">
                                                                <button type="button" class="modal-action-btn cancel" data-bs-dismiss="modal">
                                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                                </button>
                                                                <button type="submit" class="modal-action-btn confirm-reject">
                                                                    <i class="bi bi-x-lg"></i>
                                                                </button>
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

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?status=<?php echo $status; ?>&page=<?php echo $i; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>





