<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$method = strtolower(trim($_GET['method'] ?? ''));
$transId = (int)($_GET['trans_id'] ?? 0);

$allowed = ['momo', 'zalopay'];
if (!in_array($method, $allowed, true) || $transId <= 0) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Yêu cầu không hợp lệ.';
    exit;
}

$transaction = Database::fetch('SELECT * FROM transactions WHERE trans_id = ? AND user_id = ?', [$transId, $_SESSION['user_id']]);
if (!$transaction) {
    header('HTTP/1.1 404 Not Found');
    echo 'Giao dịch không tìm thấy.';
    exit;
}

// Allow user to simulate success / failure. In real integration, this would be handled by provider callback.
$action = $_POST['action'] ?? '';
if ($action === 'complete') {
    Database::execute('UPDATE transactions SET status = ?, payment_reference = ?, updated_at = NOW() WHERE trans_id = ?', ['completed', 'SIM-' . uniqid(), $transId]);
    logActivity($_SESSION['user_id'], 'donation_payment', "Completed {$method} donation transaction #$transId");
    header('Location: donate.php?payment_success=1&method=' . urlencode($method));
    exit;
} elseif ($action === 'cancel') {
    Database::execute('UPDATE transactions SET status = ?, updated_at = NOW() WHERE trans_id = ?', ['cancelled', $transId]);
    logActivity($_SESSION['user_id'], 'donation_payment', "Cancelled {$method} donation transaction #$transId");
    header('Location: donate.php?payment_error=1&method=' . urlencode($method));
    exit;
}

include 'includes/header.php';
?>

<div class="container py-5 mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Thanh toán sandbox (<?php echo strtoupper(htmlspecialchars($method)); ?>)</h4>
                </div>
                <div class="card-body">
                    <p>Giao dịch số <strong><?php echo $transId; ?></strong></p>
                    <p>Số tiền: <strong><?php echo number_format($transaction['amount'], 0, ',', '.'); ?> VND</strong></p>
                    <p>Ghi chú: <?php echo nl2br(htmlspecialchars($transaction['notes'])); ?></p>
                    <p>Đây là trang mô phỏng <strong><?php echo strtoupper(htmlspecialchars($method)); ?></strong> sandbox. Nhấn "Hoàn tất" để hoàn thành giao dịch và quay về trang quyên góp.</p>

                    <form method="post">
                        <button type="submit" name="action" value="complete" class="btn btn-success me-2">Hoàn tất thanh toán</button>
                        <button type="submit" name="action" value="cancel" class="btn btn-danger">Hủy giao dịch</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php';
