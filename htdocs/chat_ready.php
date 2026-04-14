<?php
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat System OK - Hướng dẫn sử dụng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #06B6D4 0%, #22d3ee 100%); min-height: 100vh; display: flex; align-items: center; }
        .card { border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .success-icon { font-size: 3rem; color: #28a745; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body class="p-4">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Success Message -->
                <div class="card mb-4">
                    <div class="card-body text-center py-4">
                        <div class="success-icon mb-3">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h2 class="fw-bold mb-2">✓ Hệ Thống Chat Đã Sẵn Sàng!</h2>
                        <p class="text-muted mb-0">Tất cả các thành phần hoạt động bình thường</p>
                    </div>
                </div>

                <!-- Status Info -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">📊 Trạng Thái Hệ Thống</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="text-center">
                                    <i class="bi bi-database" style="font-size: 2rem; color: #007bff;"></i>
                                    <h6 class="mt-2">Database</h6>
                                    <p class="mb-0">✓ Kết nối thành công</p>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="text-center">
                                    <i class="bi bi-person-check" style="font-size: 2rem; color: #28a745;"></i>
                                    <h6 class="mt-2">Tư Vấn Viên</h6>
                                    <p class="mb-0">✓ advisor1@gwvn.test (Ready)</p>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="text-center">
                                    <i class="bi bi-chat-dots" style="font-size: 2rem; color: #17a2b8;"></i>
                                    <h6 class="mt-2">Chat Sessions</h6>
                                    <p class="mb-0">✓ 2 cuộc trò chuyện</p>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="text-center">
                                    <i class="bi bi-envelope" style="font-size: 2rem; color: #ffc107;"></i>
                                    <h6 class="mt-2">Messages</h6>
                                    <p class="mb-0">✓ 8 tin nhắn</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Start -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">🚀 Bắt Đầu Nhanh</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Cách 1: Test Toàn Bộ (2 Tab)</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="text-muted small mb-1">Tab 1 - Tư vấn viên:</p>
                                <button class="btn btn-sm btn-outline-primary w-100" onclick="copyText('advisor1@gwvn.test')">
                                    <i class="bi bi-clipboard me-1"></i>advisor1@gwvn.test
                                </button>
                                <button class="btn btn-sm btn-outline-secondary w-100 mt-1" onclick="copyText('123456')">
                                    <i class="bi bi-clipboard me-1"></i>123456
                                </button>
                                <a href="login.php" class="btn btn-sm btn-success w-100 mt-2" target="_blank">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>Đăng nhập
                                </a>
                            </div>
                            <div class="col-md-6">
                                <p class="text-muted small mb-1">Tab 2 - Khách hàng:</p>
                                <button class="btn btn-sm btn-outline-primary w-100" onclick="copyText('test1@gmail.com')">
                                    <i class="bi bi-clipboard me-1"></i>test1@gmail.com
                                </button>
                                <button class="btn btn-sm btn-outline-secondary w-100 mt-1" onclick="copyText('123456')">
                                    <i class="bi bi-clipboard me-1"></i>123456
                                </button>
                                <a href="login.php" class="btn btn-sm btn-primary w-100 mt-2" target="_blank">
                                    <i class="bi bi-box-arrow-up-right me-1"></i>Đăng nhập
                                </a>
                            </div>
                        </div>

                        <h6 class="fw-bold mb-3 mt-4">Cách 2: Dùng Cửa Sổ Ẩn Danh (Incognito)</h6>
                        <p class="text-muted small">
                            Mở hai cửa sổ ẩn danh (Ctrl+Shift+N trên Chrome), mỗi cái đăng nhập một tài khoản khác nhau
                        </p>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">📝 Hướng dẫn Chi Tiết</h5>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li class="mb-3">
                                <strong>Tab 1 - Tư vấn viên:</strong> Đăng nhập và truy cập <code>/chat-advisor.php</code> để xem danh sách chats
                            </li>
                            <li class="mb-3">
                                <strong>Tab 2 - Khách hàng:</strong> Đăng nhập với test1@gmail.com (hoặc test2, test3, ...)
                            </li>
                            <li class="mb-3">
                                <strong>Mở Chat:</strong> Nhấp nút chat xanh ở góc dưới phải của bất kỳ trang nào
                            </li>
                            <li class="mb-3">
                                <strong>Gửi Tin Nhắn:</strong> Khách hàng nhập tin nhắn và nhấn "Gửi"
                            </li>
                            <li class="mb-3">
                                <strong>Tư Vấn Viên Phản Hồi:</strong> Danh sách chat sẽ cập nhật mỗi 3 giây. Nhấp khách hàng và trả lời
                            </li>
                            <li>
                                <strong>Xem Phản Hồi:</strong> Chat widget của khách hàng sẽ cập nhật mỗi 2 giây
                            </li>
                        </ol>

                        <div class="alert alert-light mt-3 mb-0">
                            <strong>💡 Mẹo:</strong> Giữ cửa sổ tư vấn viên mở để nhìn danh sách chat cập nhật real-time
                        </div>
                    </div>
                </div>

                <!-- Test URLs -->
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">🔗 Liên Kết Nhanh</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="login.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-box-arrow-up-right me-2"></i>Trang Đăng Nhập
                            </a>
                            <a href="chat-advisor.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-box-arrow-up-right me-2"></i>Bảng Điều Khiển Tư Vấn Viên
                            </a>
                            <a href="test_chat_guide.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-box-arrow-up-right me-2"></i>Hướng Dẫn Chi Tiết
                            </a>
                            <a href="index.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-box-arrow-up-right me-2"></i>Trang Chủ (Có Chat Widget)
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Troubleshooting -->
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">❓ Khắc Phục Sự Cố</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6><strong>❌ Không thấy chat mới</strong></h6>
                            <p class="text-muted mb-0">→ Làm mới trang (F5) hoặc chờ 3 giây. Danh sách tự động cập nhật</p>
                        </div>
                        <div class="mb-3">
                            <h6><strong>❌ Không thấy nút chat</strong></h6>
                            <p class="text-muted mb-0">→ Kiểm tra footer.php có include chat-widget không. Nút ở góc dưới bên phải</p>
                        </div>
                        <div class="mb-3">
                            <h6><strong>❌ Tin nhắn bị lỗi</strong></h6>
                            <p class="text-muted mb-0">→ Mở console (F12) để xem chi tiết lỗi. Kiểm tra kết nối internet</p>
                        </div>
                        <div>
                            <h6><strong>❌ Tư vấn viên không nhìn thấy gì</strong></h6>
                            <p class="text-muted mb-0">→ Chắc chắn đã đăng nhập với tài khoản <code>advisor1@gwvn.test</code></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function copyText(text) {
            navigator.clipboard.writeText(text);
            alert('Đã sao chép: ' + text);
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
