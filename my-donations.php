<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

// Get filter
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query (always prefix columns to avoid ambiguity)
$where = "d.user_id = ?";
$params = [$_SESSION['user_id']];

if ($status !== '') {
    $where .= " AND d.status = ?";
    $params[] = $status;
}

// Get total count
$countSql = "SELECT COUNT(*) as count FROM donations d WHERE $where";
$totalDonations = Database::fetch($countSql, $params)['count'];
$totalPages = ceil($totalDonations / $per_page);

// Get donations
$limit = (int)$per_page;
$offset = (int)$offset; // avoid PDO emulated prepare issue with LIMIT/OFFSET
$sql = "SELECT d.*, c.name as category_name 
        FROM donations d 
        LEFT JOIN categories c ON d.category_id = c.category_id 
        WHERE $where 
        ORDER BY d.created_at DESC 
        LIMIT $limit OFFSET $offset";
$donations = Database::fetchAll($sql, $params);

// Get statistics
$stats = [
    'total' => Database::fetch("SELECT COUNT(*) as count FROM donations WHERE user_id = ?", [$_SESSION['user_id']])['count'],
    'pending' => Database::fetch("SELECT COUNT(*) as count FROM donations WHERE user_id = ? AND status = 'pending'", [$_SESSION['user_id']])['count'],
    'approved' => Database::fetch("SELECT COUNT(*) as count FROM donations WHERE user_id = ? AND status = 'approved'", [$_SESSION['user_id']])['count'],
    'rejected' => Database::fetch("SELECT COUNT(*) as count FROM donations WHERE user_id = ? AND status = 'rejected'", [$_SESSION['user_id']])['count']
];

// Money donation history (in transactions)
$moneyPage = max(1, (int)($_GET['money_page'] ?? 1));
$moneyPerPage = 10;
$moneyOffset = ($moneyPage - 1) * $moneyPerPage;

$moneyWhere = "t.user_id = ? AND t.type = 'donation' AND t.amount > 0";
$moneyParams = [$_SESSION['user_id']];

$moneyCountSql = "SELECT COUNT(*) AS count FROM transactions t WHERE $moneyWhere";
$totalMoneyDonations = (int)(Database::fetch($moneyCountSql, $moneyParams)['count'] ?? 0);
$totalMoneyPages = max(1, (int)ceil($totalMoneyDonations / $moneyPerPage));

$moneyLimit = (int)$moneyPerPage;
$moneyOffset = (int)$moneyOffset;
$moneySql = "SELECT t.trans_id, t.amount, t.status, t.payment_method, t.payment_reference, t.notes, t.created_at
             FROM transactions t
             WHERE $moneyWhere
             ORDER BY t.created_at DESC
             LIMIT $moneyLimit OFFSET $moneyOffset";
$moneyDonations = Database::fetchAll($moneySql, $moneyParams);

// Campaign Donations History
$campItemsSql = "SELECT cd.campaign_id, cd.created_at, c.name as campaign_name, 
                 d.item_name, d.quantity, d.unit, d.status 
                 FROM campaign_donations cd
                 JOIN campaigns c ON cd.campaign_id = c.campaign_id
                 JOIN donations d ON cd.donation_id = d.donation_id
                 WHERE d.user_id = ?";
$campItemDonations = Database::fetchAll($campItemsSql, [$_SESSION['user_id']]);

$campMoneySql = "SELECT t.trans_id, t.amount, t.status, t.created_at, t.notes
                 FROM transactions t 
                 WHERE t.user_id = ? AND t.type = 'donation' AND t.notes LIKE '%[CAMPAIGN_MONEY_DONATION]%'";
$campMoneyTx = Database::fetchAll($campMoneySql, [$_SESSION['user_id']]);

$campaignDonationsHistory = [];
foreach ($campItemDonations as $citem) {
    $campaignDonationsHistory[] = [
        'is_money' => false,
        'campaign_id' => $citem['campaign_id'],
        'campaign_name' => $citem['campaign_name'],
        'item_name' => $citem['item_name'],
        'quantity' => $citem['quantity'],
        'unit' => $citem['unit'],
        'status' => $citem['status'],
        'created_at' => $citem['created_at'],
    ];
}
foreach ($campMoneyTx as $tx) {
    if (preg_match('/campaign_id=(\d+)/i', $tx['notes'], $m_id)) {
        $cid = (int)$m_id[1];
        $cname = '';
        if (preg_match('/campaign_name=([^\n]+)/i', $tx['notes'], $m_name)) {
            $cname = trim($m_name[1]);
        }
        $campaignDonationsHistory[] = [
            'is_money' => true,
            'campaign_id' => $cid,
            'campaign_name' => $cname ?: 'Chiến dịch #' . $cid,
            'amount' => $tx['amount'],
            'trans_id' => $tx['trans_id'],
            'status' => $tx['status'],
            'created_at' => $tx['created_at'],
        ];
    }
}
usort($campaignDonationsHistory, function($a, $b) {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});
$totalCampaignCount = count($campaignDonationsHistory);

$pageTitle = "Quyên góp của tôi";
include 'includes/header.php';
?>

<style>
.campaigns-page { background: #f2f7f9; min-height: 100vh; }
.campaigns-hero { background: linear-gradient(135deg, #0e7490 0%, #155e75 100%); color: #fff; padding: 50px 0 45px; position: relative; overflow: hidden; margin-top: -1px; }
.campaigns-hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 80% 50%, rgba(255,255,255,0.07) 0%, transparent 60%); }
.campaigns-hero-row { position: relative; z-index: 1; display: flex; align-items: center; gap: 1.6rem; }
.hero-main { display: flex; align-items: center; gap: 1.6rem; }
.hero-icon-box { width: 100px; height: 100px; border-radius: 28px; border: 1px solid rgba(255, 255, 255, 0.25); background: rgba(255, 255, 255, 0.15); display: flex; align-items: center; justify-content: center; flex-shrink: 0; backdrop-filter: blur(6px); }
.hero-icon-box i { font-size: 3rem; color: rgba(255, 255, 255, 0.95); }
.hero-title { font-size: clamp(2rem, 4.5vw, 3.2rem); line-height: 1.1; font-weight: 800; margin: 0; letter-spacing: -0.01em; }
.hero-sub { opacity: 0.9; margin-top: 0.5rem; margin-bottom: 0; font-size: clamp(1rem, 1.5vw, 1.2rem); max-width: 800px; }
@media (max-width: 991.98px) { .campaigns-hero { padding: 35px 0 30px; margin-top: -1px; } .hero-main { gap: 1rem; } .hero-icon-box { width: 70px; height: 70px; border-radius: 20px; } .hero-icon-box i { font-size: 2rem; } .hero-title { font-size: clamp(1.6rem, 8vw, 2.2rem); } .hero-sub { font-size: 0.95rem; } }
.mini-stat { border: 1px solid #d0e8ef; background: #ffffff; border-radius: 14px; padding: 1rem; text-align: center; box-shadow: 0 4px 14px rgba(14, 116, 144, 0.07); }
.mini-stat .num { font-size: 1.5rem; font-weight: 800; line-height: 1; }
.mini-stat .txt { color: #64748b; font-size: 0.85rem; margin-top: 0.35rem; font-weight: 500; }
.card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05) !important; overflow: hidden; }
.card-header { border-bottom: 1px solid #eef2f5; padding: 1.25rem 1.5rem; background: #fff !important; }
.table-hover tbody tr:hover { background-color: #f8fafb; }
.badge { padding: 0.4em 0.8em; font-weight: 600; border-radius: 6px; }
</style>

<div class="campaigns-hero">
    <div class="container">
        <div class="campaigns-hero-row">
            <div class="hero-main">
                <div class="hero-icon-box">
                    <i class="bi bi-box2-heart"></i>
                </div>
                <div>
                    <h1 class="hero-title">Lịch sử quyên góp</h1>
                    <p class="hero-sub">Theo dõi các vật phẩm và tài chính bạn đã đồng hành cùng tổ chức</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="campaigns-page pb-5">
<div class="container py-4">

    <!-- Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="mini-stat" style="border-left: 4px solid #0d6efd;">
                <div class="num text-primary"><?php echo $stats['total']; ?></div>
                <div class="txt">Tổng vật phẩm</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="mini-stat" style="border-left: 4px solid #198754;">
                <div class="num text-success"><?php echo $stats['approved']; ?></div>
                <div class="txt">Vật phẩm đã duyệt</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="mini-stat" style="border-left: 4px solid #ffc107;">
                <div class="num text-warning"><?php echo $stats['pending']; ?></div>
                <div class="txt">Vật phẩm chờ duyệt</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="mini-stat" style="border-left: 4px solid #0e7490;">
                <div class="num text-info" style="color: #0e7490 !important;"><?php echo (int)$totalMoneyDonations; ?></div>
                <div class="txt">Số lần QT tiền mặt</div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="d-flex align-items-center justify-content-between mb-3 mt-4 flex-wrap gap-2">
        <h5 class="mb-0 fw-bold"><i class="bi bi-box-seam text-primary me-2"></i>Quyên góp Nhóm Vật phẩm</h5>
        <div class="d-flex gap-2">
            <a href="#campaign-history" class="btn btn-sm" style="border: 1px solid #198754; color: #198754; font-weight: 500;">
                <i class="bi bi-flag me-1"></i>Chiến Dịch
            </a>
            <a href="#money-history" class="btn btn-sm" style="border: 1px solid #0e7490; color: #0e7490; font-weight: 500;">
                <i class="bi bi-arrow-down-circle me-1"></i>Quyên góp tiền
            </a>
            <a href="donate.php" class="btn btn-sm text-white" style="background:#0e7490; font-weight: 500;">
                <i class="bi bi-plus-circle me-1"></i>Thêm
            </a>
        </div>
    </div>
    
    <div class="card mb-4 shadow-sm" style="box-shadow: 0 2px 8px rgba(0,0,0,0.03)!important;">
        <div class="card-body bg-light rounded" style="padding: 12px 16px;">
            <div class="d-flex gap-2 flex-wrap">
                <a href="my-donations.php" class="btn btn-sm btn-<?php echo $status === '' ? 'primary' : 'outline-secondary bg-white text-dark'; ?>">
                    Tất cả (<?php echo $stats['total']; ?>)
                </a>
                <a href="my-donations.php?status=pending" class="btn btn-sm btn-<?php echo $status === 'pending' ? 'warning' : 'outline-secondary bg-white text-dark'; ?>">
                    Chờ duyệt (<?php echo $stats['pending']; ?>)
                </a>
                <a href="my-donations.php?status=approved" class="btn btn-sm btn-<?php echo $status === 'approved' ? 'success' : 'outline-secondary bg-white text-dark'; ?>">
                    Đã duyệt (<?php echo $stats['approved']; ?>)
                </a>
                <a href="my-donations.php?status=rejected" class="btn btn-sm btn-<?php echo $status === 'rejected' ? 'danger' : 'outline-secondary bg-white text-dark'; ?>">
                    Từ chối (<?php echo $stats['rejected']; ?>)
                </a>
            </div>
        </div>
    </div>

    <!-- Donations List -->
            <?php if (empty($donations)): ?>
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                        <h4 class="mt-3 text-muted">Chưa có quyên góp nào</h4>
                        <p class="text-muted">Hãy bắt đầu chia sẻ yêu thương với cộng đồng!</p>
                        <a href="donate.php" class="btn btn-success mt-3">
                            <i class="bi bi-heart-fill me-2"></i>Quyên góp ngay
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Vật phẩm</th>
                                        <th>Danh mục</th>
                                        <th>Số lượng</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày tạo</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($donations as $donation): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php
                                                    $images = json_decode($donation['images'] ?? '[]', true);
                                                    if (!empty($images)):
                                                        $firstImage = resolveDonationImageUrl((string)$images[0]);
                                                    ?>
                                                        <img src="<?php echo htmlspecialchars($firstImage); ?>" 
                                                             class="rounded me-2" 
                                                             style="width: 50px; height: 50px; object-fit: cover;"
                                                             onerror="this.src='uploads/donations/placeholder-default.svg'">
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($donation['item_name']); ?></strong>
                                                        <?php if ($donation['description']): ?>
                                                            <br><small class="text-muted">
                                                                <?php echo htmlspecialchars(substr($donation['description'], 0, 50)); ?>...
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($donation['category_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo $donation['quantity']; ?> <?php echo $donation['unit']; ?></td>
                                            <td>
                                                <?php
                                                $statusMap = [
                                                    'pending' => ['class' => 'warning', 'text' => 'Chờ duyệt', 'icon' => 'clock'],
                                                    'approved' => ['class' => 'success', 'text' => 'Đã duyệt', 'icon' => 'check-circle'],
                                                    'rejected' => ['class' => 'danger', 'text' => 'Từ chối', 'icon' => 'x-circle'],
                                                    'cancelled' => ['class' => 'secondary', 'text' => 'Đã hủy', 'icon' => 'dash-circle']
                                                ];
                                                $st = $statusMap[$donation['status']] ?? ['class' => 'secondary', 'text' => 'N/A', 'icon' => 'question'];
                                                ?>
                                                <span class="badge bg-<?php echo $st['class']; ?>">
                                                    <i class="bi bi-<?php echo $st['icon']; ?> me-1"></i>
                                                    <?php echo $st['text']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($donation['created_at'], 'd/m/Y H:i'); ?></td>
                                            <td class="d-flex gap-2">
                                                <button type="button" 
                                                        class="btn btn-sm btn-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#viewModal<?php echo $donation['donation_id']; ?>">
                                                    <i class="bi bi-eye"></i> Xem
                                                </button>
                                                <a href="donation-tracking.php?id=<?php echo $donation['donation_id']; ?>" 
                                                   class="btn btn-sm btn-outline-success">
                                                    <i class="bi bi-geo-alt"></i> Theo dõi
                                                </a>
                                            </td>
                                        </tr>

                                        <!-- View Modal -->
                                        <div class="modal fade" id="viewModal<?php echo $donation['donation_id']; ?>" tabindex="-1">
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
                                                                <?php if ($donation['estimated_value']): ?>
                                                                    <p><strong>Giá trị ước tính:</strong> <?php echo formatCurrency($donation['estimated_value']); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p><strong>Trạng thái:</strong> 
                                                                    <span class="badge bg-<?php echo $st['class']; ?>">
                                                                        <?php echo $st['text']; ?>
                                                                    </span>
                                                                </p>
                                                                <p><strong>Ngày tạo:</strong> <?php echo formatDate($donation['created_at']); ?></p>
                                                                <?php if ($donation['pickup_address']): ?>
                                                                    <p><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($donation['pickup_address']); ?></p>
                                                                <?php endif; ?>
                                                                <?php if ($donation['contact_phone']): ?>
                                                                    <p><strong>SĐT:</strong> <?php echo htmlspecialchars($donation['contact_phone']); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if ($donation['description']): ?>
                                                            <hr>
                                                            <p><strong>Mô tả:</strong></p>
                                                            <p><?php echo nl2br(htmlspecialchars($donation['description'])); ?></p>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($donation['admin_notes'] && $donation['status'] === 'rejected'): ?>
                                                            <hr>
                                                            <div class="alert alert-danger">
                                                                <strong>Lý do từ chối:</strong><br>
                                                                <?php echo nl2br(htmlspecialchars($donation['admin_notes'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php
                                                        $images = json_decode($donation['images'] ?? '[]', true);
                                                        if (!empty($images)):
                                                        ?>
                                                            <hr>
                                                            <p><strong>Hình ảnh:</strong></p>
                                                            <div class="row">
                                                                <?php foreach ($images as $img): ?>
                                                                    <?php $imageUrl = resolveDonationImageUrl((string)$img); ?>
                                                                    <div class="col-md-3 mb-2">
                                                                        <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                                                             class="img-fluid rounded" 
                                                                             onerror="this.src='uploads/donations/placeholder-default.svg'">
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
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
                </div>
            <?php endif; ?>

            <!-- Money Donation History -->
            <div class="card shadow-sm mt-4" id="money-history">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-cash-coin me-2" style="color: #0e7490;"></i>Quyên góp Tiền mặt
                        <span class="badge bg-success ms-2"><?php echo (int)$totalMoneyDonations; ?> giao dịch</span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($moneyDonations)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-wallet2 text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-3">Bạn chưa có giao dịch quyên góp tiền nào.</p>
                            <a href="donate.php" class="btn text-white px-4 py-2" style="background:#0e7490; border-radius: 8px; font-weight: 500;">
                                <i class="bi bi-cash-coin me-2"></i>Quyên góp tiền
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Mã GD</th>
                                        <th>Số tiền</th>
                                        <th>Phương thức</th>
                                        <th>Trạng thái</th>
                                        <th>Tham chiếu</th>
                                        <th>Ngày tạo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $moneyStatusMap = [
                                        'pending' => ['class' => 'warning', 'text' => 'Chờ xử lý'],
                                        'completed' => ['class' => 'success', 'text' => 'Thành công'],
                                        'cancelled' => ['class' => 'danger', 'text' => 'Thất bại'],
                                        'refunded' => ['class' => 'secondary', 'text' => 'Hoàn tiền']
                                    ];
                                    $methodMap = [
                                        'momo' => 'MoMo',
                                        'zalopay' => 'ZaloPay',
                                        'bank_transfer' => 'Chuyển khoản',
                                        'cash' => 'Tiền mặt',
                                        'credit_card' => 'Thẻ',
                                        'free' => 'Miễn phí'
                                    ];
                                    ?>
                                    <?php foreach ($moneyDonations as $tx): ?>
                                        <?php $txStatus = $moneyStatusMap[$tx['status']] ?? ['class' => 'secondary', 'text' => 'Không xác định']; ?>
                                        <tr>
                                            <td>#<?php echo (int)$tx['trans_id']; ?></td>
                                            <td class="fw-bold text-success"><?php echo formatCurrency((float)$tx['amount']); ?></td>
                                            <td><?php echo htmlspecialchars($methodMap[$tx['payment_method']] ?? strtoupper((string)$tx['payment_method'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $txStatus['class']; ?>">
                                                    <?php echo $txStatus['text']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars((string)($tx['payment_reference'] ?: '-')); ?></td>
                                            <td><?php echo formatDate($tx['created_at'], 'd/m/Y H:i'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalMoneyPages > 1): ?>
                            <nav class="mt-3">
                                <ul class="pagination justify-content-center">
                                    <?php for ($i = 1; $i <= $totalMoneyPages; $i++): ?>
                                        <li class="page-item <?php echo $i == $moneyPage ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['money_page' => $i])); ?>#money-history">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Campaign Donation History -->
            <div class="card shadow-sm mt-4" id="campaign-history">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="bi bi-flag-fill me-2" style="color: #198754;"></i>Lịch sử Quyên góp Chiến dịch
                        <span class="badge bg-success ms-2"><?php echo $totalCampaignCount; ?> lượt</span>
                    </h5>
                    <a href="campaigns.php" class="btn btn-sm text-white" style="background:#198754;">
                        <i class="bi bi-heart-fill me-1"></i>Tham gia Chiến dịch
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($campaignDonationsHistory)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-flag text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-3">Bạn chưa đóng góp cho chiến dịch nào.</p>
                            <a href="campaigns.php" class="btn btn-outline-success px-4 py-2" style="border-radius: 8px; font-weight: 500;">
                                <i class="bi bi-search me-2"></i>Khám phá Chiến dịch
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Chiến dịch</th>
                                        <th>Hình thức</th>
                                        <th>Mức đóng góp</th>
                                        <th>Trạng thái</th>
                                        <th>Ngày tham gia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($campaignDonationsHistory as $ch): ?>
                                        <tr>
                                            <td>
                                                <a href="campaign-detail.php?id=<?php echo $ch['campaign_id']; ?>" class="text-decoration-none fw-bold text-dark">
                                                    <?php echo htmlspecialchars($ch['campaign_name']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if ($ch['is_money']): ?>
                                                    <span class="badge bg-info text-dark"><i class="bi bi-cash me-1"></i>Tiền mặt</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary"><i class="bi bi-box me-1"></i>Vật phẩm</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($ch['is_money']): ?>
                                                    <strong class="text-success"><?php echo formatCurrency((float)$ch['amount']); ?></strong>
                                                <?php else: ?>
                                                    <strong><?php echo htmlspecialchars($ch['item_name']); ?></strong> (SL: <?php echo $ch['quantity'] . ' ' . $ch['unit']; ?>)
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                if ($ch['is_money']) {
                                                    $st = $moneyStatusMap[$ch['status']] ?? ['class' => 'secondary', 'text' => 'Không xác định'];
                                                } else {
                                                    $statusMap = [
                                                        'pending' => ['class' => 'warning', 'text' => 'Chờ duyệt', 'icon' => 'clock'],
                                                        'approved' => ['class' => 'success', 'text' => 'Đã duyệt', 'icon' => 'check-circle'],
                                                        'rejected' => ['class' => 'danger', 'text' => 'Từ chối', 'icon' => 'x-circle'],
                                                        'cancelled' => ['class' => 'secondary', 'text' => 'Đã hủy', 'icon' => 'dash-circle']
                                                    ];
                                                    $st = $statusMap[$ch['status']] ?? ['class' => 'secondary', 'text' => 'N/A'];
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $st['class']; ?>">
                                                    <?php echo $st['text']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($ch['created_at'], 'd/m/Y H:i'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
