<?php
require_once __DIR__ . '/_base.php';

try {
    $stats = getStatistics();
    $pendingDonations = (int)(Database::fetch("SELECT COUNT(*) as count FROM donations WHERE status = 'pending'")['count'] ?? 0);
    $pendingFeedback = (int)(Database::fetch("SELECT COUNT(*) as count FROM feedback WHERE status = 'pending'")['count'] ?? 0);
    $pendingCampaigns = (int)(Database::fetch("SELECT COUNT(*) as count FROM campaigns WHERE status = 'pending' OR status = 'draft'")['count'] ?? 0);
    $pendingRecruitment = 0;
    try {
        $pendingRecruitment = (int)(Database::fetch("SELECT COUNT(*) as count FROM recruitment_applications WHERE status = 'pending'")['count'] ?? 0);
    } catch (Exception $e) {
        $pendingRecruitment = 0;
    }

    $trend = getDonationTrendData(6);
    $categoryDistribution = getCategoryDistributionData();

    api_json(true, [
        'stats' => $stats,
        'pending' => [
            'donations' => $pendingDonations,
            'feedback' => $pendingFeedback,
            'campaigns' => $pendingCampaigns,
            'recruitment' => $pendingRecruitment
        ],
        'donation_trend' => $trend,
        'category_distribution' => $categoryDistribution
    ]);
} catch (Exception $e) {
    error_log('Admin overview error: ' . $e->getMessage());
    api_json(false, ['message' => 'Failed to load overview.'], 500);
}
?>
