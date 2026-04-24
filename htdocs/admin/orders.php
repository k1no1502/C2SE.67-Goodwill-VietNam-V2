<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();
enforceStaffPanelAccess(['orders', 'cashier']);
$panelType = getStaffPanelKey() === 'cashier' ? 'cashier' : 'orders';

function ensureOrdersLogisticsSchema(): void
{
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

function getOrderStatusEnumValues(): array
{
    $col = Database::fetch("SHOW COLUMNS FROM orders LIKE 'status'");
    $type = $col['Type'] ?? '';
    if (!is_string($type) || stripos($type, "enum(") !== 0) {
        return [];
    }
    preg_match_all("/'([^']+)'/", $type, $m);
    return $m[1] ?? [];
}

function buildTrackingUrl(?string $carrier, ?string $trackingCode): ?string
{
    $carrier = strtolower(trim((string)$carrier));
    $trackingCode = trim((string)$trackingCode);
    if ($carrier === '' || $trackingCode === '') {
        return null;
    }

    switch ($carrier) {
        case 'viettelpost':
        case 'viettel post':
        case 'vtp':
            return "https://viettelpost.com.vn/tra-cuu-hanh-trinh-don/?id=" . rawurlencode($trackingCode);
        case 'ghtk':
            return "https://i.ghtk.vn/" . rawurlencode($trackingCode);
        case 'ghn':
            return "https://donhang.ghn.vn/?order_code=" . rawurlencode($trackingCode);
        case 'j&t':
        case 'jt':
        case 'jnt':
            return "https://www.jtexpress.vn/vi/tracking?billcode=" . rawurlencode($trackingCode);
        case 'vnpost':
            return "https://vnpost.vn/vi-vn/dinh-vi/buu-pham?key=" . rawurlencode($trackingCode);
        default:
            return null;
    }
}

function buildInternalTrackingCode(int $orderId): string
{
    return 'GW' . str_pad((string)$orderId, 10, '0', STR_PAD_LEFT);
}

function getCarrierLabel(?string $carrier): string
{
    $key = strtolower(trim((string)$carrier));
    return match ($key) {
        'viettelpost', 'viettel post', 'vtp' => 'Giao Hàng Nhanh',
        'ghn' => 'GHN',
        'ghtk' => 'GHTK',
        'j&t', 'jt', 'jnt' => 'J&T Express',
        'vnpost' => 'VNPost',
        'grab' => 'GrabExpress',
        default => $carrier ? (string)$carrier : 'Chua ch?n',
    };
}

function getCarrierStatusMeta(?string $status): array
{
    $status = strtolower(trim((string)$status));
    return match ($status) {
        'payment_completed' => ['class' => 'success', 'text' => 'Đã thanh toán'],
        'payment_pending' => ['class' => 'warning', 'text' => 'Chưa hoàn tất thanh toán'],
        'created' => ['class' => 'secondary', 'text' => 'Đã tạo vận đơn'],
        'waiting_pickup' => ['class' => 'warning', 'text' => 'Chờ lấy hàng'],
        'picked_up' => ['class' => 'info', 'text' => 'Đã lấy hàng'],
        'in_transit' => ['class' => 'primary', 'text' => 'Đang trung chuyển'],
        'out_for_delivery' => ['class' => 'primary', 'text' => 'Đang giao'],
        'delivered' => ['class' => 'success', 'text' => 'Giao thành công'],
        'failed_delivery' => ['class' => 'danger', 'text' => 'Giao thất bại'],
        'returning' => ['class' => 'warning', 'text' => 'Đang hoàn'],
        'returned' => ['class' => 'dark', 'text' => 'Đã hoàn'],
        default => ['class' => 'light text-dark', 'text' => $status !== '' ? $status : 'Không xác định'],
    };
}

ensureOrdersLogisticsSchema();
$allowedStatuses = getOrderStatusEnumValues();
$validStatuses = !empty($allowedStatuses) ? $allowedStatuses : ['pending', 'confirmed', 'shipping', 'delivered', 'cancelled'];
$shippingStatusKey = in_array('shipping', $validStatuses, true) ? 'shipping' : (in_array('processing', $validStatuses, true) ? 'processing' : 'shipping');
$deliveredStatusKey = in_array('delivered', $validStatuses, true) ? 'delivered' : (in_array('completed', $validStatuses, true) ? 'completed' : 'delivered');

// Detect history table once so we can log safely in POST handler
$historyTableExists = !empty(Database::fetchAll("SHOW TABLES LIKE 'order_status_history'"));
$trackingEventsTableExists = !empty(Database::fetchAll("SHOW TABLES LIKE 'order_tracking_events'"));

// Handle status update / cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $action   = $_POST['action'];

    if ($order_id > 0) {
        try {
            $order = Database::fetch("SELECT * FROM orders WHERE order_id = ?", [$order_id]);
            if (!$order) {
                throw new Exception('�on h�ng kh�ng t?n t?i.');
            }

            $old_status = $order['status'];
            $new_status = $old_status;

	            if ($action === 'update_logistics') {
	                // Carrier constraint: only ViettelPost
	                $shipping_carrier = 'viettelpost';

	                $last_mile_status = strtolower(trim($_POST['shipping_last_mile_status'] ?? ''));

	                // Service constraint: only 3 services; editable only when last-mile status is empty/created.
	                $existingService = trim((string)($order['shipping_service'] ?? ''));
	                $postedService = trim((string)($_POST['shipping_service'] ?? ''));
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
	                $admin_note = trim($_POST['shipping_admin_note'] ?? '');

	                // Fee/weight editable only when last-mile status is empty/created
	                if (!$canEditPricingAndService) {
	                    $shipping_fee = (float)($order['shipping_fee'] ?? 0);
	                    $weight_gram = (int)($order['shipping_weight_gram'] ?? 0);
	                } else {
	                    $shipping_fee = array_key_exists('shipping_fee', $_POST)
	                        ? (float)($_POST['shipping_fee'])
	                        : (float)($order['shipping_fee'] ?? 0);
	                    $weight_gram = array_key_exists('shipping_weight_gram', $_POST)
	                        ? (int)($_POST['shipping_weight_gram'])
	                        : (int)($order['shipping_weight_gram'] ?? 0);
	                }

                $setStatusToShipping = !empty($_POST['set_status_shipping']);
                $setStatusToDelivered = !empty($_POST['set_status_delivered']);

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
                    } elseif (in_array($last_mile_status, ['payment_completed', 'waiting_pickup', 'created'], true)) {
                        $statusToApply = $confirmedStatusKey;
                    }
                }

                if ($statusToApply !== null && $statusToApply !== $old_status && in_array($statusToApply, $validStatuses, true)) {
                    Database::execute(
                        "UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?",
                        [$statusToApply, $order_id]
                    );

                    logActivity($_SESSION['user_id'], 'update_order_logistics', "Updated logistics for order #$order_id and set status to $statusToApply");
                } else {
                    logActivity($_SESSION['user_id'], 'update_order_logistics', "Updated logistics for order #$order_id");
                }

	                // Write logistics history (avoid mixing with order status trigger)
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

                    // Store real tracking event for customer-facing tracking timeline.
                    if ($trackingEventsTableExists && $last_mile_status !== '' && $oldCarrierStatus !== $last_mile_status) {
                        $trackingTitleMap = [
                            'payment_completed' => 'Đã thanh toán',
                            'payment_pending' => 'Chưa hoàn tất thanh toán',
                            'created' => 'Đã tạo vận đơn',
                            'waiting_pickup' => 'Chờ lấy hàng',
                            'picked_up' => 'Đã lấy hàng',
                            'in_transit' => 'Đang trung chuyển',
                            'out_for_delivery' => 'Đang giao cho khách',
                            'delivered' => 'Giao hàng thành công',
                            'failed_delivery' => 'Giao hàng thất bại',
                            'returning' => 'Đang chuyển hoàn',
                            'returned' => 'Đã hoàn hàng',
                        ];

                        $trackingTitle = $trackingTitleMap[$last_mile_status] ?? ('Cập nhật: ' . $last_mile_status);
                        $trackingNote = $admin_note !== '' ? $admin_note : 'Cập nhật từ trang quản trị đơn hàng.';
                        $trackingLocation = trim((string)($order['shipping_address'] ?? ''));

                        if (in_array($last_mile_status, ['created', 'waiting_pickup', 'picked_up', 'in_transit'], true)) {
                            $logisticsConfigPath = __DIR__ . '/../config/logistics.php';
                            $logisticsConfig = file_exists($logisticsConfigPath) ? require $logisticsConfigPath : [];
                            $warehouseAddress = trim((string)($logisticsConfig['warehouse']['address'] ?? ''));
                            if ($warehouseAddress !== '') {
                                $trackingLocation = $warehouseAddress;
                            }
                        }

                        Database::execute(
                            "INSERT INTO order_tracking_events (order_id, status_code, title, note, location_address, occurred_at, created_by)
                             VALUES (?, ?, ?, ?, ?, NOW(), ?)",
                            [
                                $order_id,
                                $last_mile_status,
                                $trackingTitle,
                                $trackingNote,
                                $trackingLocation,
                                (int)$_SESSION['user_id'],
                            ]
                        );
                    }

                Database::commit();
                setFlashMessage('success', 'Da cap nhat thong tin van chuyen.');

            } elseif ($action === 'cancel_order') {
                if ($old_status === 'cancelled') {
                    throw new Exception('�on h�ng d� b? h?y tru?c d�.');
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
                        [$order_id, $old_status, $new_status, 'H?y don h�ng t? admin']
                    );
                }

                setFlashMessage('success', '�� h?y don h�ng.');
                logActivity($_SESSION['user_id'], 'cancel_order_admin', "Cancelled order #$order_id from admin");
            }
        } catch (Exception $e) {
            if (Database::getConnection()->inTransaction()) {
                Database::rollback();
            }
            setFlashMessage('error', 'C� l?i x?y ra: ' . $e->getMessage());
        }

        header('Location: orders.php');
        exit();
    }
}

// Filters
$status   = $_GET['status']   ?? '';
$user_id  = (int)($_GET['user_id'] ?? 0);
$search   = trim($_GET['search'] ?? '');
$carrier  = trim($_GET['carrier'] ?? '');
$tracking = trim($_GET['tracking'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$page     = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset   = ($page - 1) * $per_page;

if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date_from)) {
    $date_from = '';
}
if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date_to)) {
    $date_to = '';
}

$where  = "1=1";
$params = [];

if ($status !== '') {
    $where   .= " AND o.status = ?";
    $params[] = $status;
}

if ($user_id > 0) {
    $where   .= " AND o.user_id = ?";
    $params[] = $user_id;
}

if ($search !== '') {
    $where      .= " AND (u.name LIKE ? OR u.email LIKE ? OR o.shipping_name LIKE ? OR o.shipping_phone LIKE ?)";
    $searchLike  = "%$search%";
    $params[]    = $searchLike;
    $params[]    = $searchLike;
    $params[]    = $searchLike;
    $params[]    = $searchLike;
}

if ($carrier !== '') {
    $where   .= " AND o.shipping_carrier = ?";
    $params[] = $carrier;
}

if ($tracking !== '') {
    $where   .= " AND o.shipping_tracking_code LIKE ?";
    $params[] = "%" . $tracking . "%";
}

if ($date_from !== '') {
    $where   .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to !== '') {
    $where   .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

// Get total count
$totalSql   = "SELECT COUNT(*) as count FROM orders o JOIN users u ON o.user_id = u.user_id WHERE $where";
$totalRow   = Database::fetch($totalSql, $params);
$totalCount = (int)($totalRow['count'] ?? 0);
$totalPages = max(1, ceil($totalCount / $per_page));

$sql = "SELECT o.*, 
               u.name  as user_name,
               u.email as user_email,
               COUNT(oi.order_item_id)  as total_items,
               COALESCE(SUM(oi.quantity), 0) as total_quantity
        FROM orders o
        JOIN users u ON o.user_id = u.user_id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE $where
        GROUP BY o.order_id
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$orders   = Database::fetchAll($sql, $params);

// Get users for filter
$users = Database::fetchAll("SELECT user_id, name, email FROM users ORDER BY name");

$carrierOptions = [
    '' => 'Tất cả',
    'viettelpost' => 'Giao Hàng Nhanh',
];

$serviceOptions = [
    '' => '-- Chọn --',
    'VCN' => 'Chuyển (VCN)',
    'VHT' => 'Hoạt động (VHT)',
    'VTK' => 'Tiết kiệm (VTK)',
];

$orderStatusLabels = [
    'pending' => 'Chờ xử lý',
    'confirmed' => 'Đã xác nhận',
    'processing' => 'Đang xử lý',
    'shipping' => 'Đang giao',
    'delivered' => 'Đã giao',
    'completed' => 'Hoàn tất',
    'cancelled' => 'Đã hủy',
    'refunded' => 'Đã hoàn tiền',
];

// Statistics for top cards
$statusCountRows = Database::fetchAll("SELECT status, COUNT(*) AS count FROM orders GROUP BY status");
$statusCounts = [];
foreach ($statusCountRows as $row) {
    $statusCounts[$row['status']] = (int)($row['count'] ?? 0);
}
$totalRevenueRow = Database::fetch("SELECT COALESCE(SUM(total_amount), 0) AS total_revenue FROM orders");
$shippingCardLabel = $orderStatusLabels[$shippingStatusKey] ?? 'Đang giao';
$deliveredCardLabel = $orderStatusLabels[$deliveredStatusKey] ?? 'Đã giao';

$stats = [
    'total_orders' => array_sum($statusCounts),
    'pending_orders' => $statusCounts['pending'] ?? 0,
    'confirmed_orders' => $statusCounts['confirmed'] ?? 0,
    'shipping_orders' => $statusCounts[$shippingStatusKey] ?? ($statusCounts['shipping'] ?? 0),
    'delivered_orders' => $statusCounts[$deliveredStatusKey] ?? (($statusCounts['delivered'] ?? 0) + ($statusCounts['completed'] ?? 0)),
    'cancelled_orders' => $statusCounts['cancelled'] ?? 0,
    'total_revenue' => (float)($totalRevenueRow['total_revenue'] ?? 0),
];

$pageTitle = "Quản lý đơn hàng";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin</title>
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
        .orders-topbar {
            background: transparent;
            border: 0;
            border-radius: 16px;
            padding: 0.15rem 0 0.25rem;
            color: #0f172a;
            margin-top: 0.2rem;
            margin-bottom: 1rem;
            box-shadow: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .orders-head {
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }
        .orders-head-icon {
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
        .orders-head-icon i {
            font-size: 2rem;
            line-height: 1;
        }
        .orders-topbar-title {
            font-size: clamp(1.7rem, 2.8vw, 2.9rem);
            font-weight: 900;
            line-height: 1.08;
            letter-spacing: 0.1px;
            margin: 0;
            color: #0f172a;
        }
        .orders-topbar-sub  {
            color: #58718a;
            font-size: clamp(1rem, 1.35vw, 1.15rem);
            margin: 0.35rem 0 0;
            line-height: 1.25;
        }
        @media (max-width: 767.98px) {
            .orders-topbar { padding: 0.05rem 0 0.25rem; }
            .orders-head {
                gap: 0.72rem;
                align-items: flex-start;
            }
            .orders-head-icon {
                width: 56px;
                height: 56px;
                border-radius: 14px;
            }
            .orders-head-icon i { font-size: 1.45rem; }
            .orders-topbar-sub { font-size: 1rem; }
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
        .stat-value { font-size: 1.85rem; font-weight: 800; color: var(--ink-900); line-height: 1; }
        .stat-icon  { width: 48px; height: 48px; border-radius: 13px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0; }
        .stat-total     .stat-icon { background: rgba(14,116,144,.1);  color: var(--brand-700); }
        .stat-pending   .stat-icon { background: rgba(234,179,8,.14);  color: #b45309; }
        .stat-confirmed .stat-icon { background: rgba(8,145,178,.12);  color: #0891b2; }
        .stat-shipping  .stat-icon { background: rgba(99,102,241,.12); color: #6366f1; }
        .stat-delivered .stat-icon { background: rgba(22,163,74,.12);  color: #16a34a; }
        .stat-cancelled .stat-icon { background: rgba(239,68,68,.1);   color: #ef4444; }

        /* ── Filter card ── */
        .orders-filter-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 2px 12px rgba(14,116,144,.06);
            margin-bottom: 1.5rem;
        }
        .orders-filter-card .form-label { font-size: .78rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--ink-500); margin-bottom: 6px; }
        .orders-filter-card .form-select,
        .orders-filter-card .form-control { border-color: var(--line); border-radius: 10px; font-size: .9rem; }
        .orders-filter-card .form-select:focus,
        .orders-filter-card .form-control:focus { border-color: var(--brand-500); box-shadow: 0 0 0 3px rgba(6,182,212,.15); }
        .btn-orders-filter {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            color: #fff; border: none; border-radius: 10px;
            font-weight: 600; padding: 9px 20px; transition: opacity .15s;
        }
        .btn-orders-filter:hover { opacity: .9; color: #fff; }
        .btn-orders-reset {
            border: 1.5px solid var(--line); background: #fff; color: var(--ink-500);
            border-radius: 10px; font-weight: 500; padding: 8px 20px; transition: background .15s;
        }
        .btn-orders-reset:hover { background: var(--brand-50); color: var(--brand-700); }

        /* ── Table card ── */
        .orders-table-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(14,116,144,.07);
        }
        .orders-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .orders-table thead th {
            background: linear-gradient(135deg, #083344 0%, #0e7490 100%);
            color: rgba(255,255,255,.75);
            font-size: .7rem; font-weight: 700; letter-spacing: .09em; text-transform: uppercase;
            padding: 14px 14px; border: none; white-space: nowrap;
        }
        .orders-table tbody tr { transition: background .12s; }
        .orders-table tbody tr:hover { background: #f0fbfe; }
        .orders-table tbody td {
            padding: 12px 14px;
            border-bottom: 1px solid #edf5f8;
            vertical-align: middle;
            color: var(--ink-900);
            font-size: .875rem;
        }
        .orders-table tbody tr:last-child td { border-bottom: none; }

        /* ── Status badges ── */
        .status-badge {
            display: inline-block; padding: 4px 11px;
            border-radius: 999px; font-size: .72rem; font-weight: 700; letter-spacing: .03em;
        }
        .status-badge.bg-warning  { background: rgba(234,179,8,.15)  !important; color: #92400e; }
        .status-badge.bg-info     { background: rgba(8,145,178,.12)  !important; color: #155e75; }
        .status-badge.bg-primary  { background: rgba(99,102,241,.12) !important; color: #4338ca; }
        .status-badge.bg-success  { background: rgba(22,163,74,.12)  !important; color: #166534; }
        .status-badge.bg-danger   { background: rgba(239,68,68,.1)   !important; color: #991b1b; }
        .status-badge.bg-secondary{ background: #f1f5f9 !important; color: #475569; }
        .status-badge.bg-dark     { background: #1e293b !important; color: #e2e8f0; }
        .status-badge.bg-light    { background: #f8fafc !important; color: #64748b; }

        /* ── Action buttons ── */
        .orders-table th:last-child,
        .orders-table td:last-child {
            width: 98px;
            min-width: 98px;
        }
        .orders-actions {
            display: grid;
            grid-template-columns: repeat(2, 34px);
            gap: 6px;
            justify-content: start;
            align-content: start;
        }
        .orders-actions form {
            margin: 0;
            width: 34px;
            height: 34px;
        }
        .orders-action-btn {
            width: 34px; height: 34px; border-radius: 9px; border: none;
            display: flex; align-items: center; justify-content: center;
            font-size: .95rem; color: #fff;
            transition: transform .15s, opacity .15s;
            text-decoration: none;
        }
        .orders-action-btn:hover  { transform: translateY(-1px); color: #fff; }
        .orders-action-btn:focus  { outline: none; box-shadow: 0 0 0 3px rgba(6,182,212,.25); }
        .orders-action-btn.view   { background: linear-gradient(135deg,#0e7490,#06B6D4); }
        .orders-action-btn.ship   { background: #16a34a; }
        .orders-action-btn.print  { background: #0891b2; }
        .orders-action-btn.cancel { background: #ef4444; }
        .orders-action-btn i { pointer-events: none; }

        /* ── Pagination ── */
        .orders-pagination .page-link {
            border: 1px solid var(--line); color: var(--brand-700);
            border-radius: 8px !important; margin: 0 2px;
            font-weight: 500; padding: 6px 14px; transition: background .15s;
        }
        .orders-pagination .page-link:hover { background: var(--brand-50); }
        .orders-pagination .page-item.active .page-link {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            border-color: transparent; color: #fff;
        }
        .orders-pagination .page-item.disabled .page-link { color: #adb5bd; }

        /* ── Modal improvements ── */
        .modal-content { border: none; border-radius: 16px; overflow: hidden; }
        .modal-header { background: linear-gradient(135deg, #083344, #0e7490); color: #fff; border-bottom: none; padding: 18px 24px; }
        .modal-header .btn-close { filter: invert(1) brightness(2); }
        .modal-title { font-weight: 700; font-size: 1rem; }
        .modal-footer { border-top: 1px solid var(--line); }
        .btn-modal-save {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            color: #fff; border: none; border-radius: 10px;
            font-weight: 600; padding: 9px 22px;
        }
        .btn-modal-close {
            border: 1.5px solid var(--line); background: #fff; color: var(--ink-500);
            border-radius: 10px; font-weight: 500; padding: 8px 18px;
        }

        @media (max-width: 767.98px) {
            .stat-value { font-size: 1.5rem; }
            .orders-table-card { border-radius: 12px; }

            .orders-table th:last-child,
            .orders-table td:last-child {
                width: 90px;
                min-width: 90px;
            }

            .orders-actions {
                grid-template-columns: repeat(2, 30px);
                gap: 5px;
            }

            .orders-actions form {
                width: 30px;
                height: 30px;
            }

            .orders-action-btn {
                width: 30px;
                height: 30px;
                border-radius: 8px;
                font-size: 0.86rem;
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

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="orders-topbar">
                    <div class="orders-head">
                        <div class="orders-head-icon">
                            <i class="bi bi-cart-check"></i>
                        </div>
                        <div>
                            <h1 class="orders-topbar-title">Quản lý đơn hàng</h1>
                            <p class="orders-topbar-sub">Theo dõi và xử lý toàn bộ đơn hàng của hệ thống</p>
                        </div>
                    </div>
                </div>

                <?php echo displayFlashMessages(); ?>

                <!-- Stats cards -->
                <div class="row g-3 mb-4">
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="stat-card stat-total">
                            <div>
                                <div class="stat-label">Tổng đơn</div>
                                <div class="stat-value"><?php echo number_format($stats['total_orders'] ?? 0); ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-cart-check"></i></div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="stat-card stat-pending">
                            <div>
                                <div class="stat-label">Chờ xử lý</div>
                                <div class="stat-value"><?php echo number_format($stats['pending_orders'] ?? 0); ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="stat-card stat-confirmed">
                            <div>
                                <div class="stat-label">Đã xác nhận</div>
                                <div class="stat-value"><?php echo number_format($stats['confirmed_orders'] ?? 0); ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="stat-card stat-shipping">
                            <div>
                                <div class="stat-label">Đang giao</div>
                                <div class="stat-value"><?php echo number_format($stats['shipping_orders'] ?? 0); ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-truck"></i></div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="stat-card stat-delivered">
                            <div>
                                <div class="stat-label">Đã giao</div>
                                <div class="stat-value"><?php echo number_format($stats['delivered_orders'] ?? 0); ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-bag-check"></i></div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 col-6">
                        <div class="stat-card stat-cancelled">
                            <div>
                                <div class="stat-label">Bị huỷ</div>
                                <div class="stat-value"><?php echo number_format($stats['cancelled_orders'] ?? 0); ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="orders-filter-card">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Tìm kiếm</label>
                                <input type="text"
                               name="search"
                               class="form-control"
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Tên, email, người nhận, SĐT...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" class="form-select">
                                <option value="">Tất cả</option>
                                <option value="pending"   <?php echo $status === 'pending'   ? 'selected' : ''; ?>>Chờ xử lý</option>
                                <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Đã xác nhận</option>
                                <option value="shipping"  <?php echo $status === 'shipping'  ? 'selected' : ''; ?>>Đang giao</option>
                                <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Đã giao</option>
                                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Bị huỷ</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Người dùng</label>
                            <select name="user_id" class="form-select">
                                <option value="">Tất cả</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo $u['user_id']; ?>"
                                        <?php echo $user_id === (int)$u['user_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($u['name'] . ' (' . $u['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn-orders-filter w-100">
                                <i class="bi bi-search me-1"></i>Lọc
                            </button>
                        </div>
                        <div class="w-100"></div>
                        <div class="col-md-3">
                            <label class="form-label">Đơn vận chuyển</label>
                            <select name="carrier" class="form-select">
                                <?php foreach ($carrierOptions as $key => $label): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $carrier === $key ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Mã vận đơn</label>
                            <input type="text" name="tracking" class="form-control" value="<?php echo htmlspecialchars($tracking); ?>" placeholder="VD: VTP123...">
                            </div>
                        <div class="col-md-3">
                            <label class="form-label">Từ ngày</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Đến ngày</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a class="btn-orders-reset w-100 text-center text-decoration-none" href="orders.php">
                                <i class="bi bi-arrow-clockwise me-1"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Orders table -->
                <div class="orders-table-card">
                    <div class="table-responsive">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Người dùng</th>
                                    <th>Người nhận</th>
                                    <th>SĐT</th>
                                    <th>Sản phẩm</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày tạo</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orders)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            Không có đơn hàng nào.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                    $statusClass = 'secondary';
                                    $statusText  = $order['status'];
                                            switch ($order['status']) {
                                                case 'pending':
                                                    $statusClass = 'warning';
                                                    $statusText  = 'Chờ xác nhận';
                                                    break;
                                                case 'confirmed':
                                                    $statusClass = 'info';
                                                    $statusText  = 'Đã xác nhận';
                                                    break;
                                                case 'shipping':
                                                    $statusClass = 'primary';
                                                    $statusText  = 'Đang giao';
                                                    break;
                                                case 'delivered':
                                                    $statusClass = 'success';
                                                    $statusText  = 'Đã giao';
                                                    break;
                                                case 'cancelled':
                                                    $statusClass = 'danger';
                                                    $statusText  = 'Đã hủy';
                                                    break;
                                            }

	                                            $carrierLabel = getCarrierLabel(($order['shipping_carrier'] ?? '') !== '' ? $order['shipping_carrier'] : 'viettelpost');
                                            $trackingCode = (string)($order['shipping_tracking_code'] ?? '');
                                            if ($trackingCode === '') {
                                                $trackingCode = buildInternalTrackingCode((int)$order['order_id']);
                                            }
	                                            $trackingUrl = buildTrackingUrl(($order['shipping_carrier'] ?? '') !== '' ? $order['shipping_carrier'] : 'viettelpost', $trackingCode);
                                            $carrierStatusMeta = getCarrierStatusMeta($order['shipping_last_mile_status'] ?? '');
                                            $shippingFee = (float)($order['shipping_fee'] ?? 0);

                                                $shippingMethod = strtolower(trim((string)($order['shipping_method'] ?? '')));
                                                $shippingAddress = trim((string)($order['shipping_address'] ?? ''));
                                                $shippingPhone = trim((string)($order['shipping_phone'] ?? ''));
                                                $shippingPlaceId = trim((string)($order['shipping_place_id'] ?? ''));
                                                $shippingLat = (string)($order['shipping_lat'] ?? '');
                                                $shippingLng = (string)($order['shipping_lng'] ?? '');
                                                $shippingNoteRaw = trim((string)($order['shipping_note'] ?? ''));
                                                $shippingNote = function_exists('mb_strtolower')
                                                    ? mb_strtolower($shippingNoteRaw, 'UTF-8')
                                                    : strtolower($shippingNoteRaw);

                                                $hasDeliveryAddress = ($shippingAddress !== '' || $shippingPhone !== '' || $shippingPlaceId !== '' || $shippingLat !== '' || $shippingLng !== '');
                                                $isCounterOrderByNote = str_contains($shippingNote, 'trực tiếp')
                                                    || str_contains($shippingNote, 'tai quay')
                                                    || str_contains($shippingNote, 'tại quầy');

                                                // Only classify as direct order when there is no delivery destination data.
                                                $isDirectOrder = ($shippingMethod === 'pickup' && !$hasDeliveryAddress)
                                                    || ($isCounterOrderByNote && !$hasDeliveryAddress);
                                            $orderTypeLabel = $isDirectOrder ? 'Trực tiếp' : 'Online ship';
                                            ?>
                                    <tr>
                                        <td><span style="font-weight:600;color:var(--brand-700)">#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></span></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['user_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['user_email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['shipping_name']); ?></td>
                                        <td><?php echo htmlspecialchars($order['shipping_phone']); ?></td>
                                        <td>
                                            <?php echo (int)$order['total_items']; ?> loại
                                            (<?php echo (int)$order['total_quantity']; ?> cái)
                                        </td>
                                        <td>
                                            <strong style="color:#16a34a">
                                                <?php echo $order['total_amount'] > 0 ? number_format($order['total_amount']) . ' đ' : 'Miễn phí'; ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="status-badge bg-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                            <div class="mt-1" style="font-size:.78rem;color:var(--ink-500)">
                                                <i class="bi bi-tags me-1"></i>Loại đơn hàng: <strong><?php echo htmlspecialchars($orderTypeLabel); ?></strong>
                                            </div>
                                            <?php if (!$isDirectOrder): ?>
                                                <div class="mt-1" style="font-size:.78rem;color:var(--ink-500)">
                                                    <i class="bi bi-truck me-1"></i><?php echo htmlspecialchars($carrierLabel); ?>
                                                    <?php if (!empty($trackingCode)): ?>
                                                        <?php if ($trackingUrl): ?>
                                                            — <a href="<?php echo htmlspecialchars($trackingUrl); ?>" target="_blank" rel="noopener" style="color:var(--brand-700)"><?php echo htmlspecialchars($trackingCode); ?></a>
                                                        <?php else: ?>
                                                            — <?php echo htmlspecialchars($trackingCode); ?>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="font-size:.78rem;margin-top:2px">
                                                    <?php if ($shippingFee > 0): ?>
                                                        <span class="me-1" style="color:var(--ink-500)">Phí VC: <strong><?php echo number_format($shippingFee); ?>đ</strong></span>
                                                    <?php endif; ?>
                                                    <span class="status-badge bg-<?php echo htmlspecialchars($carrierStatusMeta['class']); ?>"><?php echo htmlspecialchars($carrierStatusMeta['text']); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <div class="mt-1" style="font-size:.78rem;color:var(--ink-500)">
                                                    <i class="bi bi-shop me-1"></i>Bán tại quầy, không vận chuyển.
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="white-space:nowrap;font-size:.82rem"><?php echo date('d/m/Y', strtotime($order['created_at'])); ?><br><span style="color:var(--ink-500)"><?php echo date('H:i', strtotime($order['created_at'])); ?></span></td>
                                        <td>
                                            <?php if (!$isDirectOrder): ?>
                                                <div class="orders-actions">
                                                    <a href="../order-detail.php?id=<?php echo $order['order_id']; ?>"
                                                       class="orders-action-btn view" title="Xem chi tiết">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <button type="button"
                                                            class="orders-action-btn ship"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#logisticsModal<?php echo $order['order_id']; ?>"
                                                            title="Vận chuyển">
                                                        <i class="bi bi-truck"></i>
                                                    </button>
                                                    <a href="print-shipping-label.php?order_id=<?php echo $order['order_id']; ?>"
                                                       class="orders-action-btn print"
                                                       target="_blank" rel="noopener"
                                                       title="In phiếu gửi">
                                                        <i class="bi bi-printer"></i>
                                                    </a>
                                                    <?php if ($order['status'] !== 'cancelled'): ?>
                                                        <form method="POST" class="d-inline"
                                                              onsubmit="return confirm('Huỷ đơn hàng này?');">
                                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                            <input type="hidden" name="action" value="cancel_order">
                                                            <button type="submit" class="orders-action-btn cancel" title="Hủy đơn">
                                                                <i class="bi bi-x-circle"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:.82rem">--</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                            <?php if (!$isDirectOrder): ?>
                                            <!-- Logistics Modal -->
                                            <div class="modal fade" id="logisticsModal<?php echo $order['order_id']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <form method="POST">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Logistics / Vận chuyển - Đơn #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                                <input type="hidden" name="action" value="update_logistics">

	                                                                <div class="row g-3">
	                                                                    <?php
	                                                                    $currentCarrierStatusKey = strtolower(trim((string)($order['shipping_last_mile_status'] ?? '')));
	                                                                    $feeWeightLocked = !in_array($currentCarrierStatusKey, ['', 'created'], true);
	                                                                    $serviceLocked = $feeWeightLocked;
	                                                                    ?>
	                                                                    <div class="col-md-4">
	                                                                        <label class="form-label">Đơn vị vận chuyển</label>
	                                                                        <input type="hidden" name="shipping_carrier" value="viettelpost">
                                                                            <input type="text" class="form-control" value="Giao Hàng Nhanh" disabled>
	                                                                    </div>
	                                                                    <div class="col-md-4">
	                                                                        <label class="form-label">Dịch vụ</label>
	                                                                        <?php if ($serviceLocked): ?>
	                                                                            <input type="hidden" name="shipping_service" value="<?php echo htmlspecialchars((string)($order['shipping_service'] ?? '')); ?>">
	                                                                        <?php endif; ?>
	                                                                        <select name="shipping_service" class="form-select js-service" <?php echo $serviceLocked ? 'disabled' : ''; ?>>
	                                                                            <?php foreach ($serviceOptions as $code => $label): ?>
	                                                                                <option value="<?php echo htmlspecialchars($code); ?>"
	                                                                                    <?php echo ((string)($order['shipping_service'] ?? '') === $code) ? 'selected' : ''; ?>>
	                                                                                    <?php echo htmlspecialchars($label); ?>
	                                                                                </option>
	                                                                            <?php endforeach; ?>
	                                                                        </select>
	                                                                    </div>
                                                                        <div class="col-md-4">
                                                                            <label class="form-label">Phí vận chuyển (VNĐ)</label>
	                                                                        <?php if ($feeWeightLocked): ?>
	                                                                            <input type="hidden" name="shipping_fee" value="<?php echo htmlspecialchars($order['shipping_fee'] ?? 0); ?>">
	                                                                        <?php endif; ?>
	                                                                        <input type="number"
	                                                                               step="0.01"
	                                                                               min="0"
	                                                                               name="shipping_fee"
		                                                                       class="form-control js-fee"
		                                                                       value="<?php echo htmlspecialchars($order['shipping_fee'] ?? 0); ?>"
		                                                                       <?php echo $feeWeightLocked ? 'disabled' : ''; ?>>
		                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <label class="form-label">Mã vận đơn</label>
                                                                        <?php $internalTracking = buildInternalTrackingCode((int)$order['order_id']); ?>
                                                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($internalTracking); ?>" disabled>
                                                                        <div class="form-text">Mã vận đơn tự động theo ID đơn hàng (Không thể chỉnh sửa).</div>
	                                                                        <div class="form-text">
	                                                                            <?php
	                                                                            $previewUrl = buildTrackingUrl('viettelpost', $internalTracking);
	                                                                            ?>
	                                                                            <?php if ($previewUrl): ?>
	                                                                                <a href="<?php echo htmlspecialchars($previewUrl); ?>" target="_blank" rel="noopener">Mở trang tracking</a>
                                                                            <?php else: ?>
                                                                                Tracking URL sẽ hiển thị khi có đơn vị + mã vận đơn.
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
	                                                                    <div class="col-md-3">
	                                                                        <label class="form-label">Khối lượng (gram)</label>
	                                                                        <?php if ($feeWeightLocked): ?>
	                                                                            <input type="hidden" name="shipping_weight_gram" value="<?php echo htmlspecialchars($order['shipping_weight_gram'] ?? ''); ?>">
	                                                                        <?php endif; ?>
	                                                                        <input type="number"
	                                                                               min="0"
	                                                                               name="shipping_weight_gram"
		                                                                       class="form-control js-weight"
		                                                                       value="<?php echo htmlspecialchars($order['shipping_weight_gram'] ?? ''); ?>"
		                                                                       placeholder="VD: 500"
		                                                                       <?php echo $feeWeightLocked ? 'disabled' : ''; ?>>
		                                                                    </div>
	                                                                    <div class="col-md-3">
	                                                                        <label class="form-label">Trạng thái vận chuyển</label>
	                                                                        <select name="shipping_last_mile_status" class="form-select js-lastmile-status">
                                                                            <option value="">-- Chọn --</option>
                                                                            <?php
                                                                            $carrierStatusOptions = [
                                                                                'payment_completed' => 'Đã thanh toán',
                                                                                'payment_pending' => 'Chưa hoàn tất thanh toán',
                                                                                'created' => 'Đã tạo vận đơn    ',
                                                                                'waiting_pickup' => 'Chờ lấy hàng',
                                                                                'picked_up' => 'Đã lấy hàng',
                                                                                'in_transit' => 'Đang trung chuyển',
                                                                                'out_for_delivery' => 'Đang giao',
                                                                                'delivered' => 'Giao thành công',
                                                                                'failed_delivery' => 'Giao thất bại',
                                                                                'returning' => 'Đang hoàn',
                                                                                'returned' => 'Đã hoàn',
                                                                            ];
                                                                            $currentCarrierStatus = (string)($order['shipping_last_mile_status'] ?? '');
                                                                            foreach ($carrierStatusOptions as $k => $lbl):
                                                                            ?>
                                                                                <option value="<?php echo $k; ?>" <?php echo $currentCarrierStatus === $k ? 'selected' : ''; ?>>
                                                                                    <?php echo htmlspecialchars($lbl); ?>
                                                                                </option>
                                                                            <?php endforeach; ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-12">
                                                                        <label class="form-label">Ghi chú nội bộ (logistics)</label>
                                                                        <textarea name="shipping_admin_note" class="form-control" rows="2" placeholder="VD: Hen lay hang, ghi chu giao hang..."><?php echo htmlspecialchars($order['shipping_admin_note'] ?? ''); ?></textarea>
                                                                    </div>
                                                                </div>

                                                                <hr>

                                                                <div class="row g-3">
                                                                    <div class="col-md-6">
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="checkbox" name="set_status_shipping" id="setStatusShipping<?php echo $order['order_id']; ?>" value="1">
                                                                            <label class="form-check-label" for="setStatusShipping<?php echo $order['order_id']; ?>">
                                                                                Chuyen trang thai don sang "<?php echo htmlspecialchars($orderStatusLabels[$shippingStatusKey] ?? $shippingStatusKey); ?>"
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="checkbox" name="set_status_delivered" id="setStatusDelivered<?php echo $order['order_id']; ?>" value="1">
                                                                            <label class="form-check-label" for="setStatusDelivered<?php echo $order['order_id']; ?>">
                                                                                Chuyen trang thai don sang "<?php echo htmlspecialchars($orderStatusLabels[$deliveredStatusKey] ?? $deliveredStatusKey); ?>"
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn-modal-close" data-bs-dismiss="modal">Đóng</button>
                                                                <button type="submit" class="btn-modal-save">
                                                                    <i class="bi bi-save me-1"></i>Lưu logistics
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="py-3">
                            <ul class="pagination orders-pagination justify-content-center mb-0">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link"
                                           href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
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
	    <script>
	        (function () {
	            function syncFeeWeight(form) {
	                if (!form) return;
	                const statusSelect = form.querySelector('.js-lastmile-status');
	                const feeInput = form.querySelector('.js-fee');
	                const weightInput = form.querySelector('.js-weight');
	                const serviceSelect = form.querySelector('.js-service');
	                if (!statusSelect || !feeInput || !weightInput) return;

	                const feeWeightLocked = (statusSelect.value === 'waiting_pickup');
	                feeInput.disabled = feeWeightLocked;
	                weightInput.disabled = feeWeightLocked;

	                if (serviceSelect) {
	                    const serviceLocked = (statusSelect.value === 'waiting_pickup' && (serviceSelect.value || '').trim() !== '');
	                    serviceSelect.disabled = serviceLocked;

	                    let hiddenService = form.querySelector('input[type="hidden"][name="shipping_service"]');
	                    if (serviceLocked) {
	                        if (!hiddenService) {
	                            hiddenService = document.createElement('input');
	                            hiddenService.type = 'hidden';
	                            hiddenService.name = 'shipping_service';
	                            serviceSelect.insertAdjacentElement('afterend', hiddenService);
	                        }
	                        hiddenService.value = serviceSelect.value;
	                    } else if (hiddenService) {
	                        hiddenService.remove();
	                    }
	                }
	            }

	            document.addEventListener('change', function (e) {
	                if (e.target && e.target.classList && e.target.classList.contains('js-lastmile-status')) {
	                    syncFeeWeight(e.target.closest('form'));
	                }
	            });

	            document.querySelectorAll('.modal').forEach(function (modalEl) {
	                modalEl.addEventListener('shown.bs.modal', function () {
	                    syncFeeWeight(modalEl.querySelector('form'));
	                });
	            });
	        })();
	    </script>
	</body>
	</html>
