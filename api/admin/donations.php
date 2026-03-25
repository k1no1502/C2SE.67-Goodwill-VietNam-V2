<?php
require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../../includes/donation_tracking_helpers.php';

ensureDonationTrackingTable();
$trackingTemplates = getDonationTrackingTemplates();
$trackingStatusOptions = [
    'pending' => 'pending',
    'in_progress' => 'in_progress',
    'completed' => 'completed'
];
$finalTrackingStepKey = array_key_last($trackingTemplates);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $donation_id = (int)($payload['donation_id'] ?? 0);
    $action = $payload['action'] ?? '';

    if ($donation_id <= 0 || $action === '') {
        api_json(false, ['message' => 'Invalid request.'], 400);
    }

    try {
        if ($action === 'approve') {
            Database::beginTransaction();
            $donation = Database::fetch("SELECT * FROM donations WHERE donation_id = ?", [$donation_id]);
            if (!$donation) {
                throw new Exception('Donation not found.');
            }

            if ($donation['status'] !== 'approved') {
                Database::execute(
                    "UPDATE donations SET status = 'approved', updated_at = NOW() WHERE donation_id = ?",
                    [$donation_id]
                );
            }

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
                $priceType = 'normal';
                $salePrice = $estimatedValue;
            }

            if ($existingInventory) {
                Database::execute(
                    "UPDATE inventory SET price_type = ?, sale_price = ?, estimated_value = ?, actual_value = ?, updated_at = NOW()
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
            logActivity($currentUserId, 'approve_donation', "Approved donation #$donation_id");
            api_json(true, ['message' => 'Approved']);
        }

        if ($action === 'reject') {
            $reason = $payload['reject_reason'] ?? 'Rejected';
            Database::execute(
                "UPDATE donations SET status = 'rejected', admin_notes = ?, updated_at = NOW() WHERE donation_id = ?",
                [$reason, $donation_id]
            );
            logActivity($currentUserId, 'reject_donation', "Rejected donation #$donation_id");
            api_json(true, ['message' => 'Rejected']);
        }

        if ($action === 'update_tracking') {
            $steps = $payload['steps'] ?? [];
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
                api_json(false, ['message' => 'Tracking locked.'], 400);
            }

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
            logActivity($currentUserId, 'update_donation_tracking', "Updated tracking for donation #$donation_id");
            api_json(true, ['message' => 'Tracking updated']);
        }

        api_json(false, ['message' => 'Unsupported action.'], 400);
    } catch (Exception $e) {
        if (Database::getConnection()->inTransaction()) {
            Database::rollback();
        }
        error_log('Admin donations error: ' . $e->getMessage());
        api_json(false, ['message' => $e->getMessage()], 500);
    }
}

try {
    $status = $_GET['status'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 20);
    $perPage = max(5, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $countsSql = "SELECT COUNT(*) AS total,
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

    $where = '1=1';
    $params = [];
    if ($status !== '') {
        $where .= ' AND d.status = ?';
        $params[] = $status;
    }

    $totalSql = "SELECT COUNT(*) as count FROM donations d WHERE $where";
    $totalDonations = (int)(Database::fetch($totalSql, $params)['count'] ?? 0);
    $totalPages = $perPage > 0 ? (int)ceil($totalDonations / $perPage) : 1;

    $sql = "SELECT d.*, u.name as donor_name, u.email as donor_email, c.name as category_name
            FROM donations d
            LEFT JOIN users u ON d.user_id = u.user_id
            LEFT JOIN categories c ON d.category_id = c.category_id
            WHERE $where
            ORDER BY d.created_at DESC
            LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $donations = Database::fetchAll($sql, $params);
    $donationIds = array_column($donations, 'donation_id');
    $trackingMap = getDonationTrackingMap($donationIds);

    api_json(true, [
        'counts' => $countsData,
        'donations' => $donations,
        'tracking_map' => $trackingMap,
        'tracking_templates' => $trackingTemplates,
        'pagination' => [
            'total' => $totalDonations,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages
        ]
    ]);
} catch (Exception $e) {
    error_log('Admin donations list error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to load donations.'], 500);
}
?>
