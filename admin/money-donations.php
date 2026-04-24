<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();
enforceStaffPanelAccess(['warehouse']);
$panelType = 'warehouse';

$status = trim((string)($_GET['status'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$allowedStatuses = ['pending', 'completed', 'cancelled', 'refunded'];
$where = "t.type = 'donation' AND t.amount > 0";
$params = [];
if ($status !== '' && in_array($status, $allowedStatuses, true)) {
    $where .= " AND t.status = ?";
    $params[] = $status;
}

$counts = Database::fetch(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
        SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) AS refunded_count
     FROM transactions
     WHERE type = 'donation' AND amount > 0"
) ?: [
    'total' => 0,
    'pending_count' => 0,
    'completed_count' => 0,
    'cancelled_count' => 0,
    'refunded_count' => 0,
];

$totalRows = (int)(Database::fetch("SELECT COUNT(*) AS count FROM transactions t WHERE $where", $params)['count'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$limit = (int)$perPage;
$offset = (int)$offset;
$rows = Database::fetchAll(
    "SELECT t.trans_id, t.user_id, t.amount, t.status, t.payment_method, t.payment_reference, t.notes, t.created_at,
            u.name AS donor_name, u.email AS donor_email
     FROM transactions t
     LEFT JOIN users u ON t.user_id = u.user_id
     WHERE $where
     ORDER BY t.created_at DESC
     LIMIT $limit OFFSET $offset",
    $params
);

$methodMap = [
    'momo' => 'MoMo',
    'zaopay' => 'ZaloPay',
    'zalopay' => 'ZaloPay',
];

$statusMap = [
    'pending' => ['class' => 'warning', 'text' => 'Chờ xử lý'],
    'completed' => ['class' => 'success', 'text' => 'Thành công'],
    'cancelled' => ['class' => 'danger', 'text' => 'Thất bại'],
    'refunded' => ['class' => 'secondary', 'text' => 'Hoàn tiền'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử quyên góp tiền - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: #f3f9fc;
        }

        .admin-money-page {
            padding-top: 1rem;
            padding-bottom: 1.5rem;
        }

        .money-topbar {
            background: transparent;
            border: 0;
            border-radius: 16px;
            padding: 0.15rem 0 0.25rem;
            margin-top: 0.2rem;
            margin-bottom: 1rem;
            box-shadow: none;
        }

        .money-head {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }

        .money-head-icon {
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

        .money-head-icon i {
            font-size: 2rem;
            line-height: 1;
        }

        .money-head-title {
            margin: 0;
            color: #0f172a;
            font-weight: 900;
            letter-spacing: 0.1px;
            font-size: clamp(1.7rem, 2.8vw, 2.9rem);
            line-height: 1.08;
        }

        .money-topbar-subtitle {
            color: #58718a;
            font-size: clamp(1rem, 1.35vw, 1.15rem);
            margin-top: 0.32rem;
            line-height: 1.25;
        }

        .btn-back-donations {
            border-radius: 999px;
            border: 1px solid #9fd0dc;
            color: #0d5f75;
            font-weight: 700;
            background: #fff;
        }

        .btn-back-donations:hover {
            background: #eef8fb;
            border-color: #7fc1d1;
            color: #08485a;
        }

        @media (max-width: 768px) {
            .money-head {
                gap: 0.72rem;
                align-items: flex-start;
            }

            .money-head-icon {
                width: 56px;
                height: 56px;
                border-radius: 14px;
            }

            .money-head-icon i {
                font-size: 1.45rem;
            }

            .money-topbar-subtitle {
                font-size: 1rem;
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

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content admin-money-page">
            <div class="money-topbar d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center gap-2">
                <div class="money-head">
                    <div class="money-head-icon">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <div>
                        <h1 class="money-head-title">Lịch sử quyên góp tiền</h1>
                        <div class="money-topbar-subtitle">Theo dõi toàn bộ giao dịch quyên góp tiền của người dùng</div>
                    </div>
                </div>
                <a href="donations.php" class="btn btn-back-donations">
                    <i class="bi bi-arrow-left me-1"></i>Về quản lý quyên góp vật phẩm
                </a>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body d-flex gap-2 flex-wrap">
                    <a class="btn btn-<?php echo $status === '' ? 'primary' : 'outline-primary'; ?>" href="money-donations.php">
                        Tất cả (<?php echo (int)$counts['total']; ?>)
                    </a>
                    <a class="btn btn-<?php echo $status === 'pending' ? 'warning' : 'outline-warning'; ?>" href="money-donations.php?status=pending">
                        Chờ xử lý (<?php echo (int)$counts['pending_count']; ?>)
                    </a>
                    <a class="btn btn-<?php echo $status === 'completed' ? 'success' : 'outline-success'; ?>" href="money-donations.php?status=completed">
                        Thành công (<?php echo (int)$counts['completed_count']; ?>)
                    </a>
                    <a class="btn btn-<?php echo $status === 'cancelled' ? 'danger' : 'outline-danger'; ?>" href="money-donations.php?status=cancelled">
                        Thất bại (<?php echo (int)$counts['cancelled_count']; ?>)
                    </a>
                    <a class="btn btn-<?php echo $status === 'refunded' ? 'secondary' : 'outline-secondary'; ?>" href="money-donations.php?status=refunded">
                        Hoàn tiền (<?php echo (int)$counts['refunded_count']; ?>)
                    </a>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Mã GD</th>
                                    <th>Người quyên góp</th>
                                    <th>Số tiền</th>
                                    <th>Phương thức</th>
                                    <th>Trạng thái</th>
                                    <th>Tham chiếu</th>
                                    <th>Ghi chú</th>
                                    <th>Ngày tạo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">Chưa có giao dịch quyên góp tiền.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $row): ?>
                                        <?php $meta = $statusMap[$row['status']] ?? ['class' => 'secondary', 'text' => 'Không xác định']; ?>
                                        <?php
                                        $methodKey = strtolower(trim((string)($row['payment_method'] ?? '')));
                                        $reference = strtoupper(trim((string)($row['payment_reference'] ?? '')));

                                        if ($methodKey === '' || !isset($methodMap[$methodKey])) {
                                            if (strpos($reference, 'MOMO-') === 0 || strpos($reference, 'MOMO') === 0) {
                                                $methodKey = 'momo';
                                            } elseif (strpos($reference, 'ZALOPAY-') === 0 || strpos($reference, 'ZAOPAY-') === 0 || strpos($reference, 'ZALOPAY') === 0 || strpos($reference, 'ZAOPAY') === 0) {
                                                $methodKey = 'zalopay';
                                            }
                                        }

                                        $methodLabel = $methodMap[$methodKey] ?? '-';
                                        ?>
                                        <tr>
                                            <td>#<?php echo (int)$row['trans_id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars((string)($row['donor_name'] ?? 'N/A')); ?></strong>
                                                <div class="text-muted small"><?php echo htmlspecialchars((string)($row['donor_email'] ?? '')); ?></div>
                                            </td>
                                            <td class="fw-bold text-success"><?php echo formatCurrency((float)$row['amount']); ?></td>
                                            <td><?php echo htmlspecialchars($methodLabel); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $meta['class']; ?>"><?php echo $meta['text']; ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars((string)($row['payment_reference'] ?: '-')); ?></td>
                                            <td><?php echo htmlspecialchars((string)($row['notes'] ?: '-')); ?></td>
                                            <td><?php echo formatDate($row['created_at'], 'd/m/Y H:i'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-3">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
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
