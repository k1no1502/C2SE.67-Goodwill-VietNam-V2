<?php
require_once __DIR__ . '/_base.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $campaign_id = (int)($payload['campaign_id'] ?? 0);
    $action = $payload['action'] ?? '';

    if ($campaign_id <= 0 || $action === '') {
        api_json(false, ['message' => 'Invalid request.'], 400);
    }

    try {
        if ($action === 'approve') {
            Database::beginTransaction();
            $columns = Database::fetchAll("SHOW COLUMNS FROM campaigns LIKE 'approved_by'");
            if (!empty($columns)) {
                Database::execute(
                    "UPDATE campaigns SET status = 'active', approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE campaign_id = ?",
                    [$currentUserId, $campaign_id]
                );
            } else {
                Database::execute(
                    "UPDATE campaigns SET status = 'active', updated_at = NOW() WHERE campaign_id = ?",
                    [$campaign_id]
                );
            }
            Database::commit();
            logActivity($currentUserId, 'approve_campaign', "Approved campaign #$campaign_id");
            api_json(true, ['message' => 'Approved']);
        }

        if ($action === 'reject') {
            $reject_reason = sanitize($payload['reject_reason'] ?? 'Rejected');
            Database::execute(
                "UPDATE campaigns SET status = 'cancelled', updated_at = NOW() WHERE campaign_id = ?",
                [$campaign_id]
            );
            logActivity($currentUserId, 'reject_campaign', "Rejected campaign #$campaign_id: $reject_reason");
            api_json(true, ['message' => 'Rejected']);
        }

        if ($action === 'pause') {
            Database::execute(
                "UPDATE campaigns SET status = 'paused', updated_at = NOW() WHERE campaign_id = ?",
                [$campaign_id]
            );
            logActivity($currentUserId, 'pause_campaign', "Paused campaign #$campaign_id");
            api_json(true, ['message' => 'Paused']);
        }

        if ($action === 'resume') {
            Database::execute(
                "UPDATE campaigns SET status = 'active', updated_at = NOW() WHERE campaign_id = ?",
                [$campaign_id]
            );
            logActivity($currentUserId, 'resume_campaign', "Resumed campaign #$campaign_id");
            api_json(true, ['message' => 'Resumed']);
        }

        if ($action === 'update') {
            $name = sanitize($payload['name'] ?? '');
            $description = sanitize($payload['description'] ?? '');
            $start_date = $payload['start_date'] ?? '';
            $end_date = $payload['end_date'] ?? '';
            $target_items = (int)($payload['target_items'] ?? 0);

            if ($name === '' || $description === '' || $start_date === '' || $end_date === '') {
                throw new Exception('Missing fields.');
            }

            Database::execute(
                "UPDATE campaigns SET name = ?, description = ?, start_date = ?, end_date = ?, target_items = ?, updated_at = NOW() WHERE campaign_id = ?",
                [$name, $description, $start_date, $end_date, $target_items, $campaign_id]
            );
            logActivity($currentUserId, 'update_campaign', "Updated campaign #$campaign_id");
            api_json(true, ['message' => 'Updated']);
        }

        if ($action === 'delete') {
            $hasDonations = (int)(Database::fetch("SELECT COUNT(*) as count FROM campaign_donations WHERE campaign_id = ?", [$campaign_id])['count'] ?? 0);
            if ($hasDonations > 0) {
                throw new Exception('Campaign has donations.');
            }

            Database::beginTransaction();
            Database::execute("DELETE FROM campaign_items WHERE campaign_id = ?", [$campaign_id]);
            Database::execute("DELETE FROM campaigns WHERE campaign_id = ?", [$campaign_id]);
            Database::commit();
            logActivity($currentUserId, 'delete_campaign', "Deleted campaign #$campaign_id");
            api_json(true, ['message' => 'Deleted']);
        }

        api_json(false, ['message' => 'Unsupported action.'], 400);
    } catch (Exception $e) {
        if (Database::getConnection()->inTransaction()) {
            Database::rollback();
        }
        error_log('Admin campaigns error: ' . $e->getMessage());
        api_json(false, ['message' => $e->getMessage()], 500);
    }
}

try {
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 20);
    $perPage = max(5, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = '1=1';
    $params = [];

    if ($status !== '') {
        $where .= ' AND c.status = ?';
        $params[] = $status;
    }

    if ($search !== '') {
        $where .= ' AND (c.name LIKE ? OR c.description LIKE ?)';
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $totalSql = "SELECT COUNT(*) as count FROM campaigns c WHERE $where";
    $totalCampaigns = (int)(Database::fetch($totalSql, $params)['count'] ?? 0);
    $totalPages = $perPage > 0 ? (int)ceil($totalCampaigns / $perPage) : 1;

    $sql = "SELECT c.*, u.name as creator_name, u.email as creator_email,
                   (SELECT COUNT(*) FROM campaign_items WHERE campaign_id = c.campaign_id) as items_count,
                   (SELECT COUNT(*) FROM campaign_donations WHERE campaign_id = c.campaign_id) as donations_count
            FROM campaigns c
            LEFT JOIN users u ON c.created_by = u.user_id
            WHERE $where
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $campaigns = Database::fetchAll($sql, $params);

    $stats = [
        'total' => (int)(Database::fetch("SELECT COUNT(*) as count FROM campaigns")['count'] ?? 0),
        'pending' => (int)(Database::fetch("SELECT COUNT(*) as count FROM campaigns WHERE status = 'draft' OR status = 'pending'")['count'] ?? 0),
        'active' => (int)(Database::fetch("SELECT COUNT(*) as count FROM campaigns WHERE status = 'active'")['count'] ?? 0),
        'completed' => (int)(Database::fetch("SELECT COUNT(*) as count FROM campaigns WHERE status = 'completed'")['count'] ?? 0),
    ];

    api_json(true, [
        'campaigns' => $campaigns,
        'stats' => $stats,
        'pagination' => [
            'total' => $totalCampaigns,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages
        ]
    ]);
} catch (Exception $e) {
    error_log('Admin campaigns list error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to load campaigns.'], 500);
}
?>
