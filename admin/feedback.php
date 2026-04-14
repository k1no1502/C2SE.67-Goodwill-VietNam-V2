<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $fb_id = (int)($_POST['fb_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($fb_id > 0) {
        try {
            if ($action === 'reply') {
                $admin_reply = sanitize($_POST['admin_reply'] ?? '');
                
                if (empty($admin_reply)) {
                    throw new Exception('Nội dung phản hồi không được để trống.');
                }
                
                Database::execute(
                    "UPDATE feedback SET admin_reply = ?, status = 'replied', replied_by = ?, replied_at = NOW(), updated_at = NOW() 
                     WHERE fb_id = ?",
                    [$admin_reply, $_SESSION['user_id'], $fb_id]
                );
                setFlashMessage('success', 'Đã gửi phản hồi.');
                logActivity($_SESSION['user_id'], 'reply_feedback', "Replied to feedback #$fb_id");
                
            } elseif ($action === 'update_status') {
                $status = $_POST['status'];
                Database::execute(
                    "UPDATE feedback SET status = ?, updated_at = NOW() WHERE fb_id = ?",
                    [$status, $fb_id]
                );
                setFlashMessage('success', 'Đã cập nhật trạng thái.');
                logActivity($_SESSION['user_id'], 'update_feedback_status', "Updated feedback #$fb_id status to $status");
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
    
    header('Location: feedback.php');
    exit();
}

// Get filters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = "1=1";
$params = [];

if ($status !== '') {
    $where .= " AND f.status = ?";
    $params[] = $status;
}

if ($search !== '') {
    $where .= " AND (f.name LIKE ? OR f.email LIKE ? OR f.subject LIKE ? OR f.content LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Get total count
$totalSql = "SELECT COUNT(*) as count FROM feedback f WHERE $where";
$totalFeedback = Database::fetch($totalSql, $params)['count'];
$totalPages = ceil($totalFeedback / $per_page);

// Get feedback
$sql = "SELECT f.*, u.name as user_name, u.email as user_email,
               admin.name as admin_name
        FROM feedback f
        LEFT JOIN users u ON f.user_id = u.user_id
        LEFT JOIN users admin ON f.replied_by = admin.user_id
        WHERE $where
        ORDER BY f.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$feedbackList = Database::fetchAll($sql, $params);

// Get statistics
$stats = [
    'total' => Database::fetch("SELECT COUNT(*) as count FROM feedback")['count'],
    'pending' => Database::fetch("SELECT COUNT(*) as count FROM feedback WHERE status = 'pending'")['count'],
    'read' => Database::fetch("SELECT COUNT(*) as count FROM feedback WHERE status = 'read'")['count'],
    'replied' => Database::fetch("SELECT COUNT(*) as count FROM feedback WHERE status = 'replied'")['count'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý phản hồi - Admin</title>
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

        .feedback-topbar {
            background: transparent;
            border: 0;
            border-radius: 16px;
            padding: 0.15rem 0 0.25rem;
            color: #0f172a;
            margin: 0.2rem 0 1rem;
            box-shadow: none;
        }
        .feedback-head {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }
        .feedback-head-icon {
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
        .feedback-head-icon i {
            font-size: 2rem;
            line-height: 1;
        }
        .feedback-topbar h1 {
            font-size: clamp(1.7rem, 2.8vw, 2.9rem);
            font-weight: 900;
            line-height: 1.08;
            letter-spacing: 0.1px;
            margin: 0;
            color: #0f172a;
        }
        .feedback-topbar p {
            color: #58718a;
            font-size: clamp(1rem, 1.35vw, 1.15rem);
            margin: 0.35rem 0 0;
            line-height: 1.25;
            opacity: 1;
        }
        @media (max-width: 767.98px) {
            .feedback-topbar { padding: 0.05rem 0 0.25rem; }
            .feedback-head {
                gap: 0.72rem;
                align-items: flex-start;
            }
            .feedback-head-icon {
                width: 56px;
                height: 56px;
                border-radius: 14px;
            }
            .feedback-head-icon i { font-size: 1.45rem; }
            .feedback-topbar p { font-size: 1rem; }
        }

        .stat-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(14,116,144,.07);
            transition: transform .18s, box-shadow .18s;
            height: 100%;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(14,116,144,.13); }
        .stat-label {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--ink-500);
            margin-bottom: 5px;
        }
        .stat-value { font-size: 2rem; line-height: 1; font-weight: 800; margin: 0; color: var(--ink-900); }
        .stat-total .stat-value { color: var(--brand-700); }
        .stat-pending .stat-value { color: #b45309; }
        .stat-read .stat-value { color: #155e75; }
        .stat-replied .stat-value { color: #166534; }

        .filter-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 18px 22px;
            box-shadow: 0 2px 12px rgba(14,116,144,.06);
            margin-bottom: 1.5rem;
        }
        .filter-card .form-label {
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--ink-500);
            margin-bottom: 6px;
        }
        .filter-card .form-control,
        .filter-card .form-select {
            border-color: var(--line);
            border-radius: 10px;
            font-size: .9rem;
        }
        .filter-card .form-control:focus,
        .filter-card .form-select:focus {
            border-color: var(--brand-500);
            box-shadow: 0 0 0 3px rgba(6,182,212,.15);
        }
        .btn-filter {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            height: 42px;
        }
        .btn-filter:hover { color: #fff; opacity: .92; }

        .feedback-table-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(14,116,144,.07);
        }
        .feedback-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .feedback-table thead th {
            background: linear-gradient(135deg, #083344 0%, #0e7490 100%);
            color: rgba(255,255,255,.76);
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            border: none;
            padding: 14px 14px;
            white-space: nowrap;
        }
        .feedback-table tbody td {
            border-bottom: 1px solid #edf5f8;
            vertical-align: middle;
            color: var(--ink-900);
            padding: 13px 14px;
            font-size: .88rem;
        }
        .feedback-table tbody tr:hover { background: #f0fbfe; }
        .feedback-table tbody tr:last-child td { border-bottom: none; }

        .status-badge {
            border-radius: 999px;
            padding: 4px 11px;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .03em;
        }
        .status-badge.bg-warning { background: rgba(234,179,8,.16) !important; color: #854d0e; }
        .status-badge.bg-info { background: rgba(8,145,178,.14) !important; color: #155e75; }
        .status-badge.bg-success { background: rgba(22,163,74,.13) !important; color: #166534; }
        .status-badge.bg-secondary { background: #eef2f7 !important; color: #475569; }

        .feedback-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .feedback-action-btn {
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
        .feedback-action-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(6,182,212,.25);
        }
        .feedback-action-btn:hover { transform: translateY(-1px); }
        .feedback-action-btn.view { background: #0891b2; }
        .feedback-action-btn.reply { background: linear-gradient(135deg, var(--brand-700), var(--brand-500)); }
        .feedback-action-btn i { pointer-events: none; }

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
        .modal-header .btn-close { filter: invert(1) brightness(2); }
        .modal-title { font-weight: 700; font-size: 1rem; }
        .modal-body .form-label {
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: var(--ink-500);
        }
        .modal-body .form-control,
        .modal-body .form-select,
        .modal-body textarea {
            border-color: var(--line);
            border-radius: 10px;
            font-size: .9rem;
        }
        .modal-body .form-control:focus,
        .modal-body .form-select:focus,
        .modal-body textarea:focus {
            border-color: var(--brand-500);
            box-shadow: 0 0 0 3px rgba(6,182,212,.15);
        }
        .modal-footer { border-top: 1px solid var(--line); }
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

        .pagination .page-link {
            border: 1px solid var(--line);
            color: var(--brand-700);
            border-radius: 8px !important;
            margin: 0 2px;
            font-weight: 500;
            padding: 6px 14px;
        }
        .pagination .page-link:hover { background: var(--brand-50); }
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            border-color: transparent;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="feedback-topbar">
                    <div class="feedback-head">
                        <div class="feedback-head-icon">
                            <i class="bi bi-chat-dots"></i>
                        </div>
                        <div>
                            <h1>Quản lý phản hồi</h1>
                            <p>Theo dõi phản hồi người dùng và xử lý trao đổi trực tiếp từ admin</p>
                        </div>
                    </div>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Statistics -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card stat-total">
                            <div class="stat-label">Tổng phản hồi</div>
                            <h3 class="stat-value"><?php echo number_format($stats['total']); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card stat-pending">
                            <div class="stat-label">Chờ xử lý</div>
                            <h3 class="stat-value"><?php echo number_format($stats['pending']); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card stat-read">
                            <div class="stat-label">Đã đọc</div>
                            <h3 class="stat-value"><?php echo number_format($stats['read']); ?></h3>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card stat-replied">
                            <div class="stat-label">Đã phản hồi</div>
                            <h3 class="stat-value"><?php echo number_format($stats['replied']); ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-card">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tìm kiếm</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Tên, email, tiêu đề...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Trạng thái</label>
                                <select class="form-select" name="status">
                                    <option value="">Tất cả</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Chờ xử lý</option>
                                    <option value="read" <?php echo $status === 'read' ? 'selected' : ''; ?>>Đã đọc</option>
                                    <option value="replied" <?php echo $status === 'replied' ? 'selected' : ''; ?>>Đã phản hồi</option>
                                    <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Đã đóng</option>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-filter w-100">
                                    <i class="bi bi-search me-1"></i>Lọc
                                </button>
                            </div>
                        </form>
                </div>

                <!-- Feedback table -->
                <div class="feedback-table-card">
                        <div class="table-responsive">
                            <table class="feedback-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Người gửi</th>
                                        <th>Tiêu đề</th>
                                        <th>Nội dung</th>
                                        <th>Đánh giá</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày gửi</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($feedbackList)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">Không có phản hồi nào.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($feedbackList as $fb): ?>
                                            <tr>
                                                <td><?php echo $fb['fb_id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($fb['name'] ?? $fb['user_name'] ?? 'Khách'); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($fb['email'] ?? $fb['user_email'] ?? 'N/A'); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($fb['subject'] ?? 'Không có tiêu đề'); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars(substr($fb['content'], 0, 80)); ?>...
                                                </td>
                                                <td>
                                                    <?php if ($fb['rating']): ?>
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="bi bi-star<?php echo $i <= $fb['rating'] ? '-fill text-warning' : ''; ?>"></i>
                                                        <?php endfor; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusMap = [
                                                        'pending' => ['class' => 'warning', 'text' => 'Chờ xử lý'],
                                                        'read' => ['class' => 'info', 'text' => 'Đã đọc'],
                                                        'replied' => ['class' => 'success', 'text' => 'Đã phản hồi'],
                                                        'closed' => ['class' => 'secondary', 'text' => 'Đã đóng']
                                                    ];
                                                    $st = $statusMap[$fb['status']] ?? ['class' => 'secondary', 'text' => 'N/A'];
                                                    ?>
                                                    <span class="badge status-badge bg-<?php echo $st['class']; ?>">
                                                        <?php echo $st['text']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($fb['created_at']); ?></td>
                                                <td>
                                                    <div class="feedback-actions">
                                                        <button type="button" 
                                                                class="feedback-action-btn view" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#viewModal<?php echo $fb['fb_id']; ?>">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <?php if ($fb['status'] !== 'replied' && $fb['status'] !== 'closed'): ?>
                                                            <button type="button" 
                                                                    class="feedback-action-btn reply" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#replyModal<?php echo $fb['fb_id']; ?>">
                                                                <i class="bi bi-reply"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- View Modal -->
                                            <div class="modal fade" id="viewModal<?php echo $fb['fb_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Chi tiết phản hồi #<?php echo $fb['fb_id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row mb-3">
                                                                <div class="col-md-6">
                                                                    <p><strong>Người gửi:</strong> <?php echo htmlspecialchars($fb['name'] ?? $fb['user_name'] ?? 'Khách'); ?></p>
                                                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($fb['email'] ?? $fb['user_email'] ?? 'N/A'); ?></p>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <p><strong>Tiêu đề:</strong> <?php echo htmlspecialchars($fb['subject'] ?? 'Không có tiêu đề'); ?></p>
                                                                    <p><strong>Đánh giá:</strong> 
                                                                        <?php if ($fb['rating']): ?>
                                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                                <i class="bi bi-star<?php echo $i <= $fb['rating'] ? '-fill text-warning' : ''; ?>"></i>
                                                                            <?php endfor; ?>
                                                                        <?php else: ?>
                                                                            <span class="text-muted">N/A</span>
                                                                        <?php endif; ?>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <p><strong>Nội dung:</strong></p>
                                                            <div class="border p-3 rounded">
                                                                <?php echo nl2br(htmlspecialchars($fb['content'])); ?>
                                                            </div>
                                                            
                                                            <?php if ($fb['admin_reply']): ?>
                                                                <hr>
                                                                <p><strong>Phản hồi từ admin:</strong></p>
                                                                <div class="border p-3 rounded bg-light">
                                                                    <?php echo nl2br(htmlspecialchars($fb['admin_reply'])); ?>
                                                                </div>
                                                                <small class="text-muted">
                                                                    Phản hồi bởi: <?php echo htmlspecialchars($fb['admin_name'] ?? 'Admin'); ?> 
                                                                    vào <?php echo formatDate($fb['replied_at']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Reply Modal -->
                                            <div class="modal fade" id="replyModal<?php echo $fb['fb_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Trả lời phản hồi</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="fb_id" value="<?php echo $fb['fb_id']; ?>">
                                                                <input type="hidden" name="action" value="reply">
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Người gửi</label>
                                                                    <input type="text" 
                                                                           class="form-control" 
                                                                           value="<?php echo htmlspecialchars($fb['name'] ?? $fb['user_name'] ?? 'Khách'); ?>" 
                                                                           readonly>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Nội dung gốc</label>
                                                                    <textarea class="form-control" rows="3" readonly><?php echo htmlspecialchars($fb['content']); ?></textarea>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Phản hồi *</label>
                                                                    <textarea class="form-control" name="admin_reply" rows="5" required></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Hủy</button>
                                                                <button type="submit" class="btn-modal-primary">Gửi phản hồi</button>
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
                            <nav class="py-3">
                                <ul class="pagination justify-content-center mb-0">
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



