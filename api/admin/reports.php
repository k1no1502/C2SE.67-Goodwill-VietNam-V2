<?php
require_once __DIR__ . '/_base.php';

try {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');

    $stats = getStatistics();

    $donationStats = Database::fetchAll(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count,
                SUM(quantity) as total_quantity,
                SUM(CASE WHEN status = 'approved' THEN quantity ELSE 0 END) as approved_quantity
         FROM donations
         WHERE created_at BETWEEN ? AND ?
         GROUP BY DATE_FORMAT(created_at, '%Y-%m')
         ORDER BY month ASC",
        [$start_date, $end_date . ' 23:59:59']
    );

    $donationGrowth = [];
    $previousCount = null;
    foreach ($donationStats as $stat) {
        $growth = null;
        if ($previousCount !== null) {
            $growth = $previousCount > 0
                ? round((($stat['count'] - $previousCount) / $previousCount) * 100, 2)
                : null;
        }
        $donationGrowth[] = array_merge($stat, ['growth' => $growth]);
        $previousCount = (int)$stat['count'];
    }

    $categoryStats = Database::fetchAll(
        "SELECT c.name, COUNT(*) as count, SUM(d.quantity) as total_quantity
         FROM donations d
         LEFT JOIN categories c ON d.category_id = c.category_id
         WHERE d.created_at BETWEEN ? AND ? AND d.status = 'approved'
         GROUP BY c.category_id, c.name
         ORDER BY count DESC
         LIMIT 10",
        [$start_date, $end_date . ' 23:59:59']
    );

    $topDonors = Database::fetchAll(
        "SELECT u.name, u.email, COUNT(*) as donation_count, SUM(d.quantity) as total_items
         FROM donations d
         LEFT JOIN users u ON d.user_id = u.user_id
         WHERE d.created_at BETWEEN ? AND ? AND d.status = 'approved'
         GROUP BY u.user_id, u.name, u.email
         ORDER BY donation_count DESC
         LIMIT 10",
        [$start_date, $end_date . ' 23:59:59']
    );

    $campaignStats = Database::fetchAll(
        "SELECT c.name, c.status, c.target_items, c.current_items,
                (SELECT COUNT(*) FROM campaign_donations WHERE campaign_id = c.campaign_id) as donations_count
         FROM campaigns c
         WHERE c.created_at BETWEEN ? AND ?
         ORDER BY c.created_at DESC",
        [$start_date, $end_date . ' 23:59:59']
    );

    $inventoryStats = [
        'total' => (int)(Database::fetch("SELECT COUNT(*) as count FROM inventory")['count'] ?? 0),
        'available' => (int)(Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE status = 'available'")['count'] ?? 0),
        'sold' => (int)(Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE status = 'sold'")['count'] ?? 0),
        'free' => (int)(Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE price_type = 'free' AND status = 'available'")['count'] ?? 0),
        'cheap' => (int)(Database::fetch("SELECT COUNT(*) as count FROM inventory WHERE price_type = 'cheap' AND status = 'available'")['count'] ?? 0),
    ];

    api_json(true, [
        'start_date' => $start_date,
        'end_date' => $end_date,
        'stats' => $stats,
        'donation_stats' => $donationStats,
        'donation_growth' => $donationGrowth,
        'category_stats' => $categoryStats,
        'top_donors' => $topDonors,
        'campaign_stats' => $campaignStats,
        'inventory_stats' => $inventoryStats
    ]);
} catch (Exception $e) {
    error_log('Admin reports error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to load reports.'], 500);
}
?>
