<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
    exit();
}

$campaign_id = (int)($_POST['campaign_id'] ?? 0);
$transfer_items = $_POST['transfer_items'] ?? [];

if ($campaign_id <= 0 || empty($transfer_items) || !is_array($transfer_items)) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Check campaign
    $stmt = $db->prepare("SELECT * FROM campaigns WHERE campaign_id = ?");
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy chiến dịch.']);
        exit();
    }

    if ($campaign['created_by'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thực hiện thao tác này.']);
        exit();
    }

    if (!in_array($campaign['status'], ['active', 'completed'])) {
        echo json_encode(['success' => false, 'message' => 'Chỉ có thể chuyển vật phẩm khi chiến dịch đang hoạt động hoặc đã hoàn thành.']);
        exit();
    }

    $db->beginTransaction();

    $transferred_count = 0;

    foreach ($transfer_items as $item_id => $qtyStr) {
        $item_id = (int)$item_id;
        $qty = (int)$qtyStr;

        if ($qty <= 0) continue;

        // Fetch campaign item
        $stmtItem = $db->prepare("SELECT * FROM campaign_items WHERE item_id = ? AND campaign_id = ? FOR UPDATE");
        $stmtItem->execute([$item_id, $campaign_id]);
        $cItem = $stmtItem->fetch(PDO::FETCH_ASSOC);

        if (!$cItem) continue;

        $received = (int)$cItem['quantity_received'];
        $needed = (int)$cItem['quantity_needed'];
        $transferred = (int)$cItem['quantity_transferred'];
        
        $leftover = max(0, $received - $needed - $transferred);

        if ($qty > $leftover) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Số lượng chuyển vượt quá số lượng còn dư hợp lệ của vật phẩm: ' . $cItem['item_name']]);
            exit();
        }

        $description = "Vật phẩm dư từ chiến dịch: " . $campaign['name'];
        if (!empty($cItem['description'])) {
            $description .= "\nChi tiết: " . $cItem['description'];
        }

        // 1. Create a donation record
        $stmtDonation = $db->prepare("
            INSERT INTO donations (user_id, item_name, description, category_id, quantity, unit, condition_status, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'good', 'approved', NOW())
        ");
        $stmtDonation->execute([
            $_SESSION['user_id'],
            $cItem['item_name'],
            $description,
            $cItem['category_id'],
            $qty,
            $cItem['unit'] ?? 'cái'
        ]);
        
        $donation_id = $db->lastInsertId();

        // 2. Create an inventory record
        $stmtInventory = $db->prepare("
            INSERT INTO inventory (donation_id, name, description, category_id, quantity, unit, condition_status, price_type, status, is_for_sale, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 'good', 'free', 'available', 1, NOW(), NOW())
        ");
        $stmtInventory->execute([
            $donation_id,
            $cItem['item_name'],
            $description,
            $cItem['category_id'],
            $qty,
            $cItem['unit'] ?? 'cái'
        ]);

        // 3. Update campaign_items
        $stmtUpdate = $db->prepare("UPDATE campaign_items SET quantity_transferred = quantity_transferred + ? WHERE item_id = ?");
        $stmtUpdate->execute([$qty, $item_id]);

        $transferred_count++;
    }

    if ($transferred_count === 0) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Không có vật phẩm nào được chuyển.']);
        exit();
    }

    $db->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Transfer Items Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi hệ thống: ' . $e->getMessage()]);
}
