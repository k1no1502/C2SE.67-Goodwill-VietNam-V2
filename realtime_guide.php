<?php
require 'config/Database.php';
require 'includes/functions.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real-time Updates - Hướng Dẫn & Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container my-4">
        <div class="row">
            <div class="col-md-12">
                <h1 class="mb-4">🔄 Real-time Updates System</h1>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card border-success shadow">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">✅ Tính Năng</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success"></i>
                                <strong>Real-time Notifications</strong><br>
                                <small class="text-muted">Nhận thông báo ngay khi có quyên góp mới</small>
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success"></i>
                                <strong>Auto-refresh Dashboard</strong><br>
                                <small class="text-muted">Dashboard tự động cập nhật không cần F5</small>
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success"></i>
                                <strong>Live Pending Count</strong><br>
                                <small class="text-muted">Số lượng quyên góp cần duyệt cập nhật live</small>
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success"></i>
                                <strong>WebSocket Support</strong><br>
                                <small class="text-muted">Dùng Server-Sent Events (SSE) - tương thích 99.9%</small>
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success"></i>
                                <strong>Auto Reconnect</strong><br>
                                <small class="text-muted">Tự động reconnect nếu mất kết nối</small>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card border-primary shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">🚀 Cách Hoạt Động</h5>
                    </div>
                    <div class="card-body">
                        <ol class="list-unstyled">
                            <li class="mb-2">
                                <span class="badge bg-primary">1</span>
                                Admin mở dashboard
                            </li>
                            <li class="mb-2">
                                <span class="badge bg-primary">2</span>
                                Browser kết nối SSE stream
                            </li>
                            <li class="mb-2">
                                <span class="badge bg-primary">3</span>
                                Người dùng tạo quyên góp mới
                            </li>
                            <li class="mb-2">
                                <span class="badge bg-primary">4</span>
                                Server gửi event đến admin
                            </li>
                            <li class="mb-2">
                                <span class="badge bg-primary">5</span>
                                Admin nhận notification
                            </li>
                            <li class="mb-2">
                                <span class="badge bg-primary">6</span>
                                Dashboard auto-refresh
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-warning shadow">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">📝 Hướng Dẫn Test</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="fw-bold">Tab 1 - Admin Dashboard:</h6>
                                <code>
                                    <a href="admin/donations.php" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-box-arrow-up-right"></i> Mở Admin Dashboard
                                    </a>
                                </code>
                                <ul class="mt-2 small">
                                    <li>Mở trang </li>
                                    <li>Xem tab "Chờ duyệt"</li>
                                    <li>Tìm green indicator ở góc dưới phải (SSE active)</li>
                                    <li>Đợi updates từ Tab 2</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold">Tab 2 - Test Form:</h6>
                                <code>
                                    <a href="test_realtime_donations.php" target="_blank" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-box-arrow-up-right"></i> Mở Test Form
                                    </a>
                                </code>
                                <ul class="mt-2 small">
                                    <li>Mở form tạo quyên góp test</li>
                                    <li>Nhấn "Tạo Quyên Góp Test"</li>
                                    <li>Tab 1 sẽ tự động update</li>
                                    <li>Repeat nhiều lần để test</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">⚙️ Technical Stack</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Protocol:</strong></td>
                                <td>Server-Sent Events (SSE)</td>
                            </tr>
                            <tr>
                                <td><strong>Endpoint:</strong></td>
                                <td><code>/api/donations-stream.php</code></td>
                            </tr>
                            <tr>
                                <td><strong>Update Interval:</strong></td>
                                <td>2 giây</td>
                            </tr>
                            <tr>
                                <td><strong>Connection Timeout:</strong></td>
                                <td>55 giây (auto reconnect sau)</td>
                            </tr>
                            <tr>
                                <td><strong>Browser Support:</strong></td>
                                <td>99.9% (không IE)</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">📊 System Status</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>SSE Endpoint:</strong></td>
                                <td>
                                    <span class="badge bg-success" id="sse-status">✓ Available</span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Current Pending:</strong></td>
                                <td>
                                    <span id="pending-count">-</span> donations
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Total Donations:</strong></td>
                                <td>
                                    <span id="total-count">-</span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Last Check:</strong></td>
                                <td>
                                    <span id="last-check">-</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Check system status on page load
        async function checkSystemStatus() {
            try {
                const response = await fetch('api/donations-stream.php?test=1', { 
                    signal: AbortSignal.timeout(3000)  
                });
                document.getElementById('sse-status').textContent = '✓ Available';
            } catch (e) {
                document.getElementById('sse-status').innerHTML = '⚠ Check your connection';
            }

            // Get stats
            try {
                const response = await fetch('api/get-statistics.php');
                const data = await response.json();
                if (data.donation_stats) {
                    document.getElementById('pending-count').textContent = data.donation_stats.pending_count || 0;
                    document.getElementById('total-count').textContent = data.donation_stats.total_donations || 0;
                }
            } catch (e) {
                console.error('Error fetching stats:', e);
            }

            document.getElementById('last-check').textContent = new Date().toLocaleTimeString();
        }

        checkSystemStatus();
        setInterval(checkSystemStatus, 10000); // Check every 10 seconds
    </script>
</body>
</html>
