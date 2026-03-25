<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Require admin access
requireStaffOrAdmin();

$pageTitle = 'Dashboard - Quản trị';

// Get statistics
$stats = getStatistics();
$donationTrend = getDonationTrendData();
$categoryDistribution = getCategoryDistributionData();

// Get recent activities
$recentActivities = Database::fetchAll("
    SELECT al.*, u.name as user_name 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.user_id 
    ORDER BY al.created_at DESC 
    LIMIT 10
");

// Get recent donations
$recentDonations = Database::fetchAll("
    SELECT d.*, u.name as donor_name, c.name as category_name
    FROM donations d 
    LEFT JOIN users u ON d.user_id = u.user_id 
    LEFT JOIN categories c ON d.category_id = c.category_id 
    ORDER BY d.created_at DESC 
    LIMIT 5
");

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Goodwill Vietnam</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background: #f3f9fc;
        }

        .admin-content {
            padding-top: 1rem;
            padding-bottom: 1.5rem;
        }

        .dashboard-topbar {
            background: linear-gradient(140deg, #f7fcfe 0%, #ecf7fb 100%);
            border: 1px solid #d7edf3;
            border-radius: 22px;
            padding: 1rem 1.45rem;
            margin-top: 0.35rem;
            margin-bottom: 1.2rem;
            box-shadow: 0 10px 24px rgba(8, 74, 92, 0.07);
        }

        .dashboard-topbar .h2 {
            margin: 0;
            color: #0f172a;
            font-weight: 800;
            letter-spacing: 0.2px;
            display: flex;
            align-items: center;
            gap: 0.7rem;
            font-size: clamp(2rem, 3vw, 3.4rem);
            line-height: 1.05;
        }

        .dashboard-topbar .h2 i {
            font-size: 0.9em;
        }

        .dashboard-note {
            color: #64748b;
            font-size: clamp(0.95rem, 1.2vw, 1.08rem);
            margin-top: 0.45rem;
        }

        .btn-dashboard-outline {
            border-color: #9fd8e6;
            color: #0b728c;
            background: #fff;
            border-radius: 16px;
            font-weight: 700;
            min-height: 76px;
            padding: 0.95rem 1.8rem;
            font-size: 1rem;
        }

        .btn-dashboard-outline:hover {
            background: #ecf7fb;
            color: #0b728c;
            border-color: #85c9da;
        }

        .btn-dashboard-primary {
            background: linear-gradient(135deg, #0e7490, #06B6D4);
            color: #fff;
            border: none;
            border-radius: 16px;
            font-weight: 700;
            min-height: 76px;
            padding: 0.95rem 1.8rem;
            font-size: 1rem;
        }

        .btn-dashboard-primary:hover {
            color: #fff;
            filter: brightness(0.97);
        }
        @media (max-width: 767.98px) {
            .dashboard-topbar {
                padding: 1rem;
            }
            .dashboard-topbar .h2 {
                font-size: 2rem;
            }
            .btn-dashboard-outline,
            .btn-dashboard-primary {
                min-height: 52px;
                padding: 0.7rem 1rem;
                border-radius: 12px;
            }
        }

        .stat-card {
            border: 1px solid #d7edf3;
            border-radius: 16px;
            box-shadow: 0 10px 24px rgba(8, 74, 92, 0.08);
            background: #fff;
        }

        .stat-card .card-body {
            padding: 1rem 1.1rem;
        }

        .stat-label {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 700;
            color: #0b728c;
            margin-bottom: 0.28rem;
        }

        .stat-value {
            font-size: 1.65rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-users .stat-icon { background: #e0f4fa; color: #0891b2; }
        .stat-donations .stat-icon { background: #dcfce7; color: #059669; }
        .stat-items .stat-icon { background: #dbeafe; color: #2563eb; }
        .stat-campaigns .stat-icon { background: #fef3c7; color: #d97706; }

        .dashboard-card {
            border: 1px solid #d7edf3;
            border-radius: 16px;
            box-shadow: 0 10px 24px rgba(8, 74, 92, 0.08);
            overflow: hidden;
        }

        .dashboard-card-header {
            background: linear-gradient(140deg, #fbfeff 0%, #edf8fb 100%);
            border-bottom: 1px solid #d7edf3;
            padding: 0.82rem 1rem;
        }

        .dashboard-card-title {
            font-weight: 700;
            color: #0b728c;
            margin: 0;
        }

        .chart-area,
        .chart-pie {
            min-height: 280px;
        }

        .activity-item {
            border: 1px solid #ebf6fa;
            border-radius: 12px;
            margin-bottom: 0.55rem;
            background: #fff;
        }

        .activity-item:last-child {
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="dashboard-topbar d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center gap-3">
                    <div>
                        <h1 class="h2"><i class="bi bi-heart-fill"></i>Dashboard</h1>
                        <div class="dashboard-note">Tổng quan nhanh về hoạt động hệ thống Goodwill Vietnam</div>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="dashboard_export.php" class="btn btn-dashboard-outline d-inline-flex align-items-center justify-content-center">
                                <i class="bi bi-download me-1"></i>Xuất Excel
                            </a>
                        </div>
                        <button type="button" class="btn btn-dashboard-primary d-inline-flex align-items-center justify-content-center">
                            <i class="bi bi-plus me-1"></i>Thêm mới
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card stat-users h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="stat-label">
                                            Tổng người dùng
                                        </div>
                                        <div class="stat-value">
                                            <?php echo number_format($stats['users']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <i class="bi bi-people-fill stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card stat-donations h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="stat-label">
                                            Quyên góp
                                        </div>
                                        <div class="stat-value">
                                            <?php echo number_format($stats['donations']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <i class="bi bi-heart-fill stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card stat-items h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="stat-label">
                                            Vật phẩm
                                        </div>
                                        <div class="stat-value">
                                            <?php echo number_format($stats['items']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <i class="bi bi-box-seam stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card stat-campaigns h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="stat-label">
                                            Chiến dịch
                                        </div>
                                        <div class="stat-value">
                                            <?php echo number_format($stats['campaigns']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <i class="bi bi-trophy-fill stat-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-xl-8 col-lg-7">
                        <div class="card dashboard-card mb-4">
                            <div class="card-header dashboard-card-header d-flex flex-row align-items-center justify-content-between">
                                <h6 class="dashboard-card-title">Thống kê quyên góp theo tháng</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-area">
                                    <canvas id="donationChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-lg-5">
                        <div class="card dashboard-card mb-4">
                            <div class="card-header dashboard-card-header d-flex flex-row align-items-center justify-content-between">
                                <h6 class="dashboard-card-title">Phân bố danh mục</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-pie pt-4 pb-2">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities and Donations -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header dashboard-card-header">
                                <h6 class="dashboard-card-title">Hoạt động gần đây</h6>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recentActivities as $activity): ?>
                                        <div class="list-group-item activity-item d-flex justify-content-between align-items-start">
                                            <div class="ms-2 me-auto">
                                                <div class="fw-bold"><?php echo htmlspecialchars($activity['action']); ?></div>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($activity['user_name'] ?? 'Hệ thống'); ?>
                                                </small>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo formatDate($activity['created_at'], 'H:i d/m'); ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header dashboard-card-header">
                                <h6 class="dashboard-card-title">Quyên góp gần đây</h6>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recentDonations as $donation): ?>
                                        <div class="list-group-item activity-item d-flex justify-content-between align-items-start">
                                            <div class="ms-2 me-auto">
                                                <div class="fw-bold"><?php echo htmlspecialchars($donation['item_name']); ?></div>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($donation['donor_name']); ?> - 
                                                    <?php echo htmlspecialchars($donation['category_name'] ?? 'Không xác định'); ?>
                                                </small>
                                            </div>
                                            <span class="badge bg-<?php echo $donation['status'] === 'pending' ? 'warning' : 'success'; ?>">
                                                <?php echo ucfirst($donation['status']); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/main.js"></script>
    <script>
        // Donation Chart
        const donationTrend = <?php echo json_encode($donationTrend, JSON_UNESCAPED_UNICODE); ?>;
        const donationLabels = donationTrend.map(item => item.label);
        const donationValues = donationTrend.map(item => item.total);
        const donationCtx = document.getElementById('donationChart').getContext('2d');
        new Chart(donationCtx, {
            type: 'line',
            data: {
                labels: donationLabels,
                datasets: [{
                    label: 'So quyen gop',
                    data: donationValues,
                    borderColor: '#0891b2',
                    backgroundColor: 'rgba(8, 145, 178, 0.16)',
                    fill: true,
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#334155'
                        }
                    }
                },
                scales: {
                    y: {
                        grid: {
                            color: 'rgba(148, 163, 184, 0.25)'
                        },
                        ticks: {
                            color: '#64748b'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#64748b'
                        }
                    }
                }
            }
        });

        // Category Chart
        const categoryData = <?php echo json_encode($categoryDistribution, JSON_UNESCAPED_UNICODE); ?>;
        const categoryLabels = categoryData.map(item => item.label);
        const categoryValues = categoryData.map(item => item.total);
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryValues,
                    backgroundColor: [
                        '#0891b2',
                        '#06B6D4',
                        '#14b8a6',
                        '#38bdf8',
                        '#0ea5e9',
                        '#22d3ee',
                        '#67e8f9'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#334155'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
