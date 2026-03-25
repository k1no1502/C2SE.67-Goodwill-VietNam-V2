<?php
require_once __DIR__ . '/_base.php';

function ensureOrdersLogisticsSchema(): void {
    $cols = Database::fetchAll("SHOW COLUMNS FROM orders");
    $existing = array_fill_keys(array_map(fn($c) => $c['Field'], $cols), true);

    $add = [];
    if (!isset($existing['shipping_carrier'])) {
        $add[] = "ADD COLUMN shipping_carrier VARCHAR(50) NULL";
    }
    if (!isset($existing['shipping_service'])) {
        $add[] = "ADD COLUMN shipping_service VARCHAR(50) NULL";
    }
    if (!isset($existing['shipping_tracking_code'])) {
        $add[] = "ADD COLUMN shipping_tracking_code VARCHAR(100) NULL";
    }
    if (!isset($existing['shipping_fee'])) {
        $add[] = "ADD COLUMN shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0";
    }
    if (!isset($existing['shipping_weight_gram'])) {
        $add[] = "ADD COLUMN shipping_weight_gram INT NULL";
    }
    if (!isset($existing['shipping_last_mile_status'])) {
        $add[] = "ADD COLUMN shipping_last_mile_status VARCHAR(50) NULL";
    }
    if (!isset($existing['shipping_last_mile_updated_at'])) {
        $add[] = "ADD COLUMN shipping_last_mile_updated_at TIMESTAMP NULL";
    }
    if (!isset($existing['shipped_at'])) {
        $add[] = "ADD COLUMN shipped_at TIMESTAMP NULL";
    }
    if (!isset($existing['delivered_at'])) {
        $add[] = "ADD COLUMN delivered_at TIMESTAMP NULL";
    }
    if (!isset($existing['shipping_admin_note'])) {
        $add[] = "ADD COLUMN shipping_admin_note TEXT NULL";
    }

    if (empty($add)) {
        return;
    }

    try {
        Database::execute("ALTER TABLE orders " . implode(", ", $add));
    } catch (Exception $e) {
        error_log('ensureOrdersLogisticsSchema failed: ' . $e->getMessage());
    }
}

function getOrderStatusEnumValues(): array {
    $col = Database::fetch("SHOW COLUMNS FROM orders LIKE 'status'");
    $type = $col['Type'] ?? '';
    if (!is_string($type) || stripos($type, "enum(") !== 0) {
        return [];
    }
    preg_match_all("/'([^']+)'/", $type, $m);
    return $m[1] ?? [];
}

function buildInternalTrackingCode(int $orderId): string {
    return 'GW' . str_pad((string)$orderId, 10, '0', STR_PAD_LEFT);
}

ensureOrdersLogisticsSchema();
$allowedStatuses = getOrderStatusEnumValues();
$validStatuses = !empty($allowedStatuses) ? $allowedStatuses : ['pending', 'confirmed', 'shipping', 'delivered', 'cancelled'];
$shippingStatusKey = in_array('shipping', $validStatuses, true) ? 'shipping' : (in_array('processing', $validStatuses, true) ? 'processing' : 'shipping');
$deliveredStatusKey = in_array('delivered', $validStatuses, true) ? 'delivered' : (in_array('completed', $validStatuses, true) ? 'completed' : 'delivered');
$historyTableExists = !empty(Database::fetchAll("SHOW TABLES LIKE 'order_status_history'"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $order_id = (int)($payload['order_id'] ?? 0);
    $action = $payload['action'] ?? '';

    if ($order_id <= 0 || $action === '') {
        api_json(false, ['message' => 'Invalid request.'], 400);
    }

    try {
        $order = Database::fetch("SELECT * FROM orders WHERE order_id = ?", [$order_id]);
        if (!$order) {
            throw new Exception('Order not found.');
        }

        $old_status = $order['status'];

        if ($action === 'update_logistics') {
            $shipping_carrier = 'viettelpost';
            $last_mile_status = strtolower(trim($payload['shipping_last_mile_status'] ?? ''));

            $existingService = trim((string)($order['shipping_service'] ?? ''));
            $postedService = trim((string)($payload['shipping_service'] ?? ''));
            $allowedServices = ['VCN', 'VHT', 'VTK'];

            $oldCarrierStatus = strtolower(trim((string)($order['shipping_last_mile_status'] ?? '')));
            $canEditPricingAndService = in_array($oldCarrierStatus, ['', 'created'], true);

            if (!$canEditPricingAndService) {
                $shipping_service = $existingService;
            } elseif ($postedService !== '' && in_array($postedService, $allowedServices, true)) {
                $shipping_service = $postedService;
            } else {
                $shipping_service = '';
            }

            $tracking_code = buildInternalTrackingCode($order_id);
            $admin_note = trim((string)($payload['shipping_admin_note'] ?? ''));

            if (!$canEditPricingAndService) {
                $shipping_fee = (float)($order['shipping_fee'] ?? 0);
                $weight_gram = (int)($order['shipping_weight_gram'] ?? 0);
            } else {
                $shipping_fee = array_key_exists('shipping_fee', $payload)
                    ? (float)($payload['shipping_fee'])
                    : (float)($order['shipping_fee'] ?? 0);
                $weight_gram = array_key_exists('shipping_weight_gram', $payload)
                    ? (int)($payload['shipping_weight_gram'])
                    : (int)($order['shipping_weight_gram'] ?? 0);
            }

            $setStatusToShipping = !empty($payload['set_status_shipping']);
            $setStatusToDelivered = !empty($payload['set_status_delivered']);

            Database::beginTransaction();
            Database::execute(
                "UPDATE orders
                 SET shipping_carrier = ?,
                     shipping_service = ?,
                     shipping_tracking_code = ?,
                     shipping_fee = ?,
                     shipping_weight_gram = ?,
                     shipping_last_mile_status = ?,
                     shipping_last_mile_updated_at = NOW(),
                     shipping_admin_note = ?,
                     shipped_at = CASE WHEN ? <> '' AND shipped_at IS NULL THEN NOW() ELSE shipped_at END,
                     delivered_at = CASE WHEN ? = 'delivered' AND delivered_at IS NULL THEN NOW() ELSE delivered_at END,
                     updated_at = NOW()
                 WHERE order_id = ?",
                [
                    $shipping_carrier,
                    $shipping_service !== '' ? $shipping_service : null,
                    $tracking_code,
                    max(0, $shipping_fee),
                    $weight_gram > 0 ? $weight_gram : null,
                    $last_mile_status !== '' ? $last_mile_status : null,
                    $admin_note !== '' ? $admin_note : null,
                    $tracking_code,
                    strtolower($last_mile_status),
                    $order_id
                ]
            );

            $statusToApply = null;
            if ($setStatusToDelivered || strtolower($last_mile_status) === 'delivered') {
                $statusToApply = $deliveredStatusKey;
            } else {
                $confirmedStatusKey = in_array('confirmed', $validStatuses, true)
                    ? 'confirmed'
                    : (in_array('processing', $validStatuses, true) ? 'processing' : 'pending');

                if ($setStatusToShipping) {
                    $statusToApply = $shippingStatusKey;
                } elseif (in_array($last_mile_status, ['out_for_delivery', 'in_transit', 'picked_up', 'failed_delivery', 'returning', 'returned'], true)) {
                    $statusToApply = $shippingStatusKey;
                } elseif (in_array($last_mile_status, ['waiting_pickup', 'created'], true)) {
                    $statusToApply = $confirmedStatusKey;
                }
            }

            if ($statusToApply !== null && $statusToApply !== $old_status && in_array($statusToApply, $validStatuses, true)) {
                Database::execute(
                    "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?",
                    [$statusToApply, $order_id]
                );
                logActivity($currentUserId, 'update_order_logistics', "Updated logistics for order #$order_id and set status to $statusToApply");
            } else {
                logActivity($currentUserId, 'update_order_logistics', "Updated logistics for order #$order_id");
            }

            if ($historyTableExists && $last_mile_status !== '') {
                if ($oldCarrierStatus !== $last_mile_status) {
                    Database::execute(
                        "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
                         VALUES (?, ?, ?, ?, NOW())",
                        [
                            $order_id,
                            'logistics:' . ($oldCarrierStatus !== '' ? $oldCarrierStatus : 'created'),
                            'logistics:' . $last_mile_status,
                            'Logistics status update'
                        ]
                    );
                }
            }

            Database::commit();
            api_json(true, ['message' => 'Updated']);
        }

        if ($action === 'cancel_order') {
            if ($old_status === 'cancelled') {
                throw new Exception('Order already cancelled.');
            }
            $new_status = 'cancelled';
            Database::execute(
                "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?",
                [$new_status, $order_id]
            );

            if ($historyTableExists) {
                Database::execute(
                    "INSERT INTO order_status_history (order_id, old_status, new_status, note, created_at)
                     VALUES (?, ?, ?, ?, NOW())",
                    [$order_id, $old_status, $new_status, 'Admin cancel']
                );
            }

            logActivity($currentUserId, 'cancel_order_admin', "Cancelled order #$order_id from admin");
            api_json(true, ['message' => 'Cancelled']);
        }

        api_json(false, ['message' => 'Unsupported action.'], 400);
    } catch (Exception $e) {
        if (Database::getConnection()->inTransaction()) {
            Database::rollback();
        }
        error_log('Admin orders error: ' . $e->getMessage());
        api_json(false, ['message' => $e->getMessage()], 500);
    }
}

try {
    $status = $_GET['status'] ?? '';
    $user_id = (int)($_GET['user_id'] ?? 0);
    $search = trim((string)($_GET['search'] ?? ''));
    $carrier = trim((string)($_GET['carrier'] ?? ''));
    $tracking = trim((string)($_GET['tracking'] ?? ''));
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 20);
    $perPage = max(5, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        $date_from = '';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $date_to = '';
    }

    $where = '1=1';
    $params = [];

    if ($status !== '') {
        $where .= ' AND o.status = ?';
        $params[] = $status;
    }
    if ($user_id > 0) {
        $where .= ' AND o.user_id = ?';
        $params[] = $user_id;
    }
    if ($search !== '') {
        $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR o.shipping_name LIKE ? OR o.shipping_phone LIKE ?)';
        $searchLike = "%$search%";
        $params[] = $searchLike;
        $params[] = $searchLike;
        $params[] = $searchLike;
        $params[] = $searchLike;
    }
    if ($carrier !== '') {
        $where .= ' AND o.shipping_carrier = ?';
        $params[] = $carrier;
    }
    if ($tracking !== '') {
        $where .= ' AND o.shipping_tracking_code LIKE ?';
        $params[] = '%' . $tracking . '%';
    }
    if ($date_from !== '') {
        $where .= ' AND DATE(o.created_at) >= ?';
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $where .= ' AND DATE(o.created_at) <= ?';
        $params[] = $date_to;
    }

    $totalSql = "SELECT COUNT(*) as count FROM orders o JOIN users u ON o.user_id = u.user_id WHERE $where";
    $totalRow = Database::fetch($totalSql, $params);
    $totalCount = (int)($totalRow['count'] ?? 0);
    $totalPages = $perPage > 0 ? (int)ceil($totalCount / $perPage) : 1;

    $sql = "SELECT o.*, u.name as user_name, u.email as user_email,
                   COUNT(oi.order_item_id) as total_items,
                   COALESCE(SUM(oi.quantity), 0) as total_quantity
            FROM orders o
            JOIN users u ON o.user_id = u.user_id
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            WHERE $where
            GROUP BY o.order_id
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $orders = Database::fetchAll($sql, $params);

    $users = Database::fetchAll("SELECT user_id, name, email FROM users ORDER BY name");

    $statusCountRows = Database::fetchAll("SELECT status, COUNT(*) AS count FROM orders GROUP BY status");
    $statusCounts = [];
    foreach ($statusCountRows as $row) {
        $statusCounts[$row['status']] = (int)($row['count'] ?? 0);
    }
    $totalRevenueRow = Database::fetch("SELECT COALESCE(SUM(total_amount), 0) AS total_revenue FROM orders");

    $stats = [
        'total_orders' => array_sum($statusCounts),
        'pending_orders' => $statusCounts['pending'] ?? 0,
        'confirmed_orders' => $statusCounts['confirmed'] ?? 0,
        'shipping_orders' => $statusCounts[$shippingStatusKey] ?? ($statusCounts['shipping'] ?? 0),
        'delivered_orders' => $statusCounts[$deliveredStatusKey] ?? (($statusCounts['delivered'] ?? 0) + ($statusCounts['completed'] ?? 0)),
        'cancelled_orders' => $statusCounts['cancelled'] ?? 0,
        'total_revenue' => (float)($totalRevenueRow['total_revenue'] ?? 0),
    ];

    api_json(true, [
        'orders' => $orders,
        'users' => $users,
        'stats' => $stats,
        'valid_statuses' => $validStatuses,
        'shipping_status_key' => $shippingStatusKey,
        'delivered_status_key' => $deliveredStatusKey,
        'pagination' => [
            'total' => $totalCount,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages
        ]
    ]);
} catch (Exception $e) {
    error_log('Admin orders list error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to load orders.'], 500);
}
?>
