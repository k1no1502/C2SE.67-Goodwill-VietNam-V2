<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hướng dẫn Test Chat System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card border-0 shadow">
                    <div class="card-header bg-success text-white">
                        <h3 class="mb-0"><i class="bi bi-chat-dots me-2"></i>Hướng dẫn Test Hệ Thống Chat</h3>
                    </div>
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">📋 Thực hiện các bước sau:</h5>

                        <div class="accordion" id="testGuide">
                            <!-- Step 1: Advisor Login -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#step1">
                                        <span class="badge bg-primary me-2">Bước 1</span> Đăng nhập với tư vấn viên
                                    </button>
                                </h2>
                                <div id="step1" class="accordion-collapse collapse show" data-bs-parent="#testGuide">
                                    <div class="accordion-body">
                                        <p>Mở tab trình duyệt mới (hoặc cửa sổ ẩn) và đăng nhập với tài khoản tư vấn viên:</p>
                                        <div class="bg-light p-3 rounded mb-3">
                                            <p class="mb-1"><strong>Email:</strong> <code>advisor1@gwvn.test</code></p>
                                            <p class="mb-0"><strong>Mật khẩu:</strong> <code>123456</code></p>
                                        </div>
                                        <p class="text-muted small">Sau khi đăng nhập, truy cập trang <code>/chat-advisor.php</code></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 2: Customer Login -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#step2">
                                        <span class="badge bg-primary me-2">Bước 2</span> Đăng nhập với khách hàng
                                    </button>
                                </h2>
                                <div id="step2" class="accordion-collapse collapse" data-bs-parent="#testGuide">
                                    <div class="accordion-body">
                                        <p>Trong tab/cửa sổ hiện tại (hoặc tab khác), đăng xuất và đăng nhập với một tài khoản khách hàng test:</p>
                                        <div class="bg-light p-3 rounded mb-3">
                                            <p class="mb-1"><strong>Email:</strong> <code>test1@gmail.com</code> (hoặc test2, test3, ...)</p>
                                            <p class="mb-0"><strong>Mật khẩu:</strong> <code>123456</code></p>
                                        </div>
                                        <p class="text-muted small">Sau khi đăng nhập, truy cập trang bất kỳ có chat widget</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 3: Open Chat -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#step3">
                                        <span class="badge bg-primary me-2">Bước 3</span> Mở chat widget ở tab khách hàng
                                    </button>
                                </h2>
                                <div id="step3" class="accordion-collapse collapse" data-bs-parent="#testGuide">
                                    <div class="accordion-body">
                                        <p>Ở tab có đăng nhập tài khoản khách hàng:</p>
                                        <ol>
                                            <li>Tìm nút chat <strong>xanh lá</strong> ở góc dưới bên phải</li>
                                            <li>Nhấp vào nút để mở chat widget</li>
                                            <li>Bạn sẽ thấy một tin nhắn từ tư vấn viên: "Xin chào! Tôi là Tư Vấn Viên 1..."</li>
                                        </ol>
                                        <div class="alert alert-info">
                                            <strong><i class="bi bi-info-circle me-2"></i>Lưu ý:</strong> Nếu không thấy nút chat, hãy kiểm tra xem trang có include footer.php không
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 4: Customer Sends Message -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#step4">
                                        <span class="badge bg-primary me-2">Bước 4</span> Khách hàng gửi tin nhắn
                                    </button>
                                </h2>
                                <div id="step4" class="accordion-collapse collapse" data-bs-parent="#testGuide">
                                    <div class="accordion-body">
                                        <p>Ở chat widget của khách hàng:</p>
                                        <ol>
                                            <li>Nhập tin nhắn vào ô "Nhập tin nhắn..."</li>
                                            <li>Nhấp nút "Gửi" hoặc nhấn Enter</li>
                                            <li>Tin nhắn sẽ xuất hiện bên phải (màu xanh)</li>
                                        </ol>
                                        <p><strong>Ví dụ:</strong> Gửi tin nhắn "Xin chào, tôi có thể giúp được gì?"</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 5: Advisor Sees Chat -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#step5">
                                        <span class="badge bg-primary me-2">Bước 5</span> Tư vấn viên nhìn thấy chat mới
                                    </button>
                                </h2>
                                <div id="step5" class="accordion-collapse collapse" data-bs-parent="#testGuide">
                                    <div class="accordion-body">
                                        <p>Ở tab tư vấn viên (<code>/chat-advisor.php</code>):</p>
                                        <ol>
                                            <li><strong>Danh sách chat</strong> (bên trái): Bạn sẽ thấy khách hàng xuất hiện trong danh sách</li>
                                            <li><strong>Tên khách hàng:</strong> "test1" (hoặc email của khách hàng)</li>
                                            <li><strong>Tin nhắn mới nhất:</strong> Hiển thị của khách hàng</li>
                                            <li><strong>Thời gian:</strong> Khi tin nhắn được gửi</li>
                                        </ol>
                                        <p class="text-danger"><strong>⚠️ Lưu ý:</strong> Nếu không thấy chat mới, hãy làm mới trang (F5) hoặc chờ 3 giây để danh sách cập nhật tự động</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 6: Advisor Responds -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#step6">
                                        <span class="badge bg-primary me-2">Bước 6</span> Tư vấn viên trả lời
                                    </button>
                                </h2>
                                <div id="step6" class="accordion-collapse collapse" data-bs-parent="#testGuide">
                                    <div class="accordion-body">
                                        <p>Ở trang tư vấn viên:</p>
                                        <ol>
                                            <li>Nhấp vào khách hàng trong danh sách để mở cuộc trò chuyện</li>
                                            <li>Cửa sổ chat sẽ hiển thị toàn bộ tin nhắn</li>
                                            <li>Nhập phản hồi vào ô "Nhập tin nhắn..." phía dưới</li>
                                            <li>Nhấp "Gửi" hoặc nhấn Enter</li>
                                        </ol>
                                        <p><strong>Ví dụ phản hồi:</strong> "Xin chào! Tôi rất vui được giúp bạn. Bạn cần tư vấn về vấn đề gì?"</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 7: Customer Sees Response -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#step7">
                                        <span class="badge bg-primary me-2">Bước 7</span> Khách hàng nhìn thấy phản hồi
                                    </button>
                                </h2>
                                <div id="step7" class="accordion-collapse collapse" data-bs-parent="#testGuide">
                                    <div class="accordion-body">
                                        <p>Ở chat widget của khách hàng:</p>
                                        <ol>
                                            <li>Chat widget sẽ tự động cập nhật mỗi 2 giây</li>
                                            <li>Bạn sẽ thấy tin nhắn từ tư vấn viên xuất hiện</li>
                                            <li>Tin nhắn từ tư vấn viên sẽ hiển thị bên trái (màu trắng/xám) với tên "Tư vấn viên"</li>
                                        </ol>
                                        <p class="text-success"><strong>✓ Chúc mừng!</strong> Hệ thống chat đã hoạt động thành công!</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h5 class="fw-bold mb-3">🧪 Kiểm tra nhanh</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <strong>Tư vấn viên</strong>
                                    </div>
                                    <div class="card-body small">
                                        <p><strong>URL:</strong> /chat-advisor.php</p>
                                        <p><strong>Email:</strong> advisor1@gwvn.test</p>
                                        <p><strong>Pass:</strong> 123456</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card border-info">
                                    <div class="card-header bg-info text-white">
                                        <strong>Khách hàng Test</strong>
                                    </div>
                                    <div class="card-body small">
                                        <p><strong>Email:</strong> test1@gmail.com - test10@gmail.com</p>
                                        <p><strong>Pass:</strong> 123456</p>
                                        <p><strong>Chat:</strong> Nút xanh dưới phải</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h5 class="fw-bold mb-3 mt-4">❓ Khắc phục sự cố</h5>
                        <div class="list-group">
                            <div class="list-group-item">
                                <h6 class="mb-2">❌ Không thấy chat mới ở tru vấn viên</h6>
                                <p class="mb-0 text-muted small">→ Hãy làm mới trang (F5) hoặc chờ 3 giây. Danh sách chat tự cập nhật mỗi 3 giây</p>
                            </div>
                            <div class="list-group-item">
                                <h6 class="mb-2">❌ Khách hàng không thấy nút chat</h6>
                                <p class="mb-0 text-muted small">→ Kiểm tra xem trang có include <code>includes/footer.php</code> không</p>
                            </div>
                            <div class="list-group-item">
                                <h6 class="mb-2">❌ Tin nhắn không được gửi</h6>
                                <p class="mb-0 text-muted small">→ Kiểm tra xem bạn đã đăng nhập chưa. Hãy mở cửa sổ developer (F12) để xem lỗi</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mt-4">
                    <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle me-2"></i>Lưu ý quan trọng</h6>
                    <ul class="mb-0">
                        <li>Sử dụng <strong>2 trình duyệt khác nhau</strong> hoặc <strong>tab ẩn danh (Incognito)</strong> để test tư vấn viên và khách hàng</li>
                        <li>Tư vấn viên <strong>phải đăng nhập</strong> trước để nhận chats</li>
                        <li>Chat được gửi lên server được lưu trong database</li>
                        <li>Danh sách chat tự động cập nhật mỗi 2-3 giây</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
