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
    <title>Test Real-time Updates</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">🧪 Test: Real-time Donations Updates</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong>📝 Cách test:</strong>
                            <ol class="mb-0 mt-2">
                                <li>Mở 2 tab browser:
                                    <ul>
                                        <li><strong>Tab 1:</strong> <a href="admin/donations.php" target="_blank">Admin Dashboard - Quản lý quyên góp</a></li>
                                        <li><strong>Tab 2:</strong> Trang này (test form)</li>
                                    </ul>
                                </li>
                                <li>Trên Tab 1: Nhìn vào phần quyên góp pending</li>
                                <li>Trên Tab 2: Nhấn "Tạo quyên góp test" dưới đây</li>
                                <li>Tab 1 sẽ tự động update với quyên góp mới (không cần F5)</li>
                            </ol>
                        </div>

                        <hr>

                        <h5 class="fw-bold mb-3">Tạo Quyên Góp Test</h5>

                        <form method="POST" id="testDonationForm">
                            <div class="mb-3">
                                <label class="form-label">Tên vật phẩm</label>
                                <input type="text" class="form-control" name="item_name" 
                                       value="Test sản phẩm - <?php echo date('H:i:s'); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Danh mục</label>
                                <select class="form-control" name="category_id" required>
                                    <option value="">-- Chọn --</option>
                                    <option value="1">Quần áo</option>
                                    <option value="2">Điện tử</option>
                                    <option value="3">Sách</option>
                                    <option value="4">Gia dụng</option>
                                    <option value="5">Đồ chơi</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Mô tả</label>
                                <textarea class="form-control" name="description" rows="3" 
                                          placeholder="Mô tả vật phẩm">Test description - Tự động tạo bởi test script</textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Số lượng</label>
                                    <input type="number" class="form-control" name="quantity" value="1" min="1">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Đơn vị</label>
                                    <input type="text" class="form-control" name="unit" value="cái">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tình trạng</label>
                                <select class="form-control" name="condition_status">
                                    <option value="good">Tốt</option>
                                    <option value="like_new">Như mới</option>
                                    <option value="fair">Khá</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Giá trị ước tính (VND)</label>
                                <input type="number" class="form-control" name="estimated_value" value="100000" min="0">
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-plus-circle"></i> Tạo Quyên Góp Test
                            </button>
                        </form>

                        <div id="result" style="margin-top: 20px;"></div>
                    </div>
                </div>

                <div class="mt-3 text-center text-muted">
                    <small>💡 Sau khi tạo, admin dashboard sẽ tự động nhận và hiển thị quyên góp mới này!</small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('testDonationForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const form = e.target;
            const resultDiv = document.getElementById('result');
            const btn = form.querySelector('button[type="submit"]');
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang tạo...';
            resultDiv.innerHTML = '';

            try {
                const formData = new FormData(form);
                formData.append('action', 'create_test_donation');

                const response = await fetch('test_donation_api.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success alert-dismissible fade show">
                            <strong>✅ Thành công!</strong><br>
                            Quyên góp ID ${data.donation_id} đã được tạo.<br>
                            <small>Admin dashboard sẽ tự động cập nhật trong 2-3 giây...</small>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                    
                    form.reset();
                    setTimeout(() => {
                        form.querySelector('input[name="item_name"]').value = 'Test sản phẩm - ' + new Date().toLocaleTimeString();
                    }, 100);
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>❌ Lỗi:</strong> ${data.message}
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>❌ Lỗi:</strong> ${error.message}
                    </div>
                `;
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-plus-circle"></i> Tạo Quyên Góp Test';
            }
        });
    </script>
</body>
</html>
