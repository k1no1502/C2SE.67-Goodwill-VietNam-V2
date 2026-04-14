<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$success = '';
$error = '';

$pageTitle = "Hồ sơ cá nhân";

// Get user data
$user = getUserById($_SESSION['user_id']);
$displayRole = getUserDisplayRole((int)$_SESSION['user_id'], isset($user['role_id']) ? (int)$user['role_id'] : null);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $avatarPath = $user['avatar'] ?? null;
    
    // Handle avatar upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['size'] > 0) {
        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            $error = 'Định dạng file không hợp lệ. Chỉ chấp nhận JPG, PNG, GIF, WebP.';
        } elseif ($file['size'] > $maxSize) {
            $error = 'Kích thước file quá lớn. Tối đa 5MB.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Lỗi upload file. Vui lòng thử lại.';
        } else {
            try {
                // Create uploads directory if not exists
                if (!is_dir('uploads/avatars')) {
                    mkdir('uploads/avatars', 0755, true);
                }
                
                // Delete old avatar if exists
                if ($user['avatar'] && file_exists($user['avatar'])) {
                    unlink($user['avatar']);
                }
                
                // Generate unique filename
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newFilename = 'uploads/avatars/avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
                
                if (move_uploaded_file($file['tmp_name'], $newFilename)) {
                    $avatarPath = $newFilename;
                } else {
                    $error = 'Không thể lưu file avatar. Vui lòng thử lại.';
                }
            } catch (Exception $e) {
                error_log("Avatar upload error: " . $e->getMessage());
                $error = 'Có lỗi xảy ra khi upload avatar.';
            }
        }
    }
    
    if (empty($name)) {
        $error = 'Vui lòng nhập họ và tên.';
    } else if (!$error) {
        try {
            Database::execute(
                "UPDATE users SET name = ?, phone = ?, address = ?, avatar = ?, updated_at = NOW() WHERE user_id = ?",
                [$name, $phone, $address, $avatarPath, $_SESSION['user_id']]
            );
            
            $_SESSION['name'] = $name;
            $success = 'Cập nhật thông tin thành công!';
            $user = getUserById($_SESSION['user_id']); // Refresh user data
            $displayRole = getUserDisplayRole((int)$_SESSION['user_id'], isset($user['role_id']) ? (int)$user['role_id'] : null);
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            $error = 'Có lỗi xảy ra khi cập nhật thông tin.';
        }
    }
}

// Get user statistics
$stats = [
    'total_donations' => Database::fetch(
        "SELECT COUNT(*) as count FROM donations WHERE user_id = ?",
        [$_SESSION['user_id']]
    )['count'],
    'approved_donations' => Database::fetch(
        "SELECT COUNT(*) as count FROM donations WHERE user_id = ? AND status = 'approved'",
        [$_SESSION['user_id']]
    )['count'],
    'total_orders' => Database::fetch(
        "SELECT COUNT(*) as count FROM orders WHERE user_id = ?",
        [$_SESSION['user_id']]
    )['count'],
    'pending_donations' => Database::fetch(
        "SELECT COUNT(*) as count FROM donations WHERE user_id = ? AND status = 'pending'",
        [$_SESSION['user_id']]
    )['count']
];

include 'includes/header.php';
?>

<style>
    .profile-shell {
        position: relative;
        margin-top: 5.5rem;
        margin-bottom: 2.5rem;
    }
    .profile-shell::before {
        content: "";
        position: absolute;
        inset: -20px 0 auto;
        height: 280px;
        background: radial-gradient(circle at 20% 20%, rgba(15, 126, 153, 0.18), transparent 58%),
                    radial-gradient(circle at 85% 10%, rgba(7, 148, 170, 0.2), transparent 52%),
                    linear-gradient(130deg, #f2fbfd 0%, #e7f6fa 50%, #f7fcfd 100%);
        border-radius: 24px;
        z-index: 0;
    }
    .profile-content {
        position: relative;
        z-index: 1;
    }
    .profile-panel {
        border: 1px solid #d6edf3;
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 0 18px 42px rgba(11, 89, 107, 0.10);
        overflow: hidden;
    }
    .profile-head {
        background: linear-gradient(140deg, #0f7e99 0%, #1198b0 100%);
        color: #fff;
        padding: 1.4rem;
    }
    .profile-avatar {
        width: 128px;
        height: 128px;
        border-radius: 50%;
        border: 4px solid rgba(255, 255, 255, 0.65);
        box-shadow: 0 10px 30px rgba(9, 67, 83, 0.35);
        object-fit: cover;
    }
    .profile-avatar-fallback {
        width: 128px;
        height: 128px;
        border-radius: 50%;
        border: 4px solid rgba(255, 255, 255, 0.65);
        background: linear-gradient(140deg, #0f7e99 0%, #10a7bf 100%);
        box-shadow: 0 10px 30px rgba(9, 67, 83, 0.35);
    }
    .profile-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        background: rgba(255, 255, 255, 0.18);
        border: 1px solid rgba(255, 255, 255, 0.32);
        color: #fff;
        border-radius: 999px;
        padding: 0.4rem 0.8rem;
        font-size: 0.88rem;
        font-weight: 600;
    }
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.8rem;
    }
    .stat-item {
        border: 1px solid #d6edf3;
        border-radius: 14px;
        background: linear-gradient(180deg, #ffffff 0%, #f6fcfd 100%);
        padding: 1rem;
    }
    .stat-value {
        color: #0b6f89;
        font-weight: 800;
        font-size: 1.3rem;
        margin-bottom: 0.1rem;
    }
    .stat-label {
        color: #64748b;
        font-size: 0.86rem;
    }
    .profile-form-wrap {
        border: 1px solid #d6edf3;
        border-radius: 20px;
        background: #fff;
        box-shadow: 0 16px 40px rgba(11, 89, 107, 0.08);
    }
    .profile-form-head {
        padding: 1.1rem 1.3rem;
        background: linear-gradient(120deg, #edf8fb 0%, #dff2f7 100%);
        border-bottom: 1px solid #d6edf3;
    }
    .profile-form-head h5 {
        margin: 0;
        color: #0b6f89;
        font-weight: 800;
    }
    .profile-form-body {
        padding: 1.3rem;
    }
    .profile-form-body .form-label {
        color: #0f172a;
        font-weight: 700;
    }
    .profile-form-body .form-control {
        border-radius: 12px;
        border: 1px solid #c8e7ef;
        padding: 0.68rem 0.82rem;
    }
    .profile-form-body .form-control:focus {
        border-color: #1198b0;
        box-shadow: 0 0 0 0.2rem rgba(17, 152, 176, 0.16);
    }
    .profile-save-btn {
        border: none;
        border-radius: 12px;
        background: linear-gradient(135deg, #0f7e99, #1198b0);
        color: #fff;
        font-weight: 700;
        padding: 0.78rem 1rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .profile-save-btn:hover {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(9, 83, 102, 0.24);
    }
    .quick-actions {
        margin-top: 1rem;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }
    .quick-action-link {
        border: 1px solid #c8e7ef;
        border-radius: 12px;
        padding: 0.76rem 0.9rem;
        text-decoration: none;
        color: #0b6f89;
        font-weight: 700;
        background: #f8fdfe;
        transition: all 0.2s ease;
    }
    .quick-action-link:hover {
        color: #fff;
        background: linear-gradient(135deg, #0f7e99, #1198b0);
        border-color: transparent;
    }
    @media (max-width: 991.98px) {
        .profile-shell {
            margin-top: 5rem;
        }
        .profile-shell::before {
            height: 220px;
            border-radius: 18px;
        }
        .profile-panel {
            margin-bottom: 1rem;
        }
    }
    @media (max-width: 575.98px) {
        .stat-grid,
        .quick-actions {
            grid-template-columns: 1fr;
        }
        .profile-form-body,
        .profile-head {
            padding: 1rem;
        }
    }
</style>

<!-- Main Content -->
<div class="container profile-shell">
    <div class="row g-4 profile-content">
        <div class="col-lg-4">
            <div class="profile-panel h-100">
                <div class="profile-head text-center">
                    <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar" class="profile-avatar mb-3" id="avatarPreview">
                    <?php else: ?>
                        <div class="profile-avatar-fallback d-inline-flex align-items-center justify-content-center mb-3" id="avatarFallback">
                            <i class="bi bi-person-fill text-white" style="font-size: 2.8rem;"></i>
                        </div>
                        <img src="" alt="Avatar" class="profile-avatar mb-3 d-none" id="avatarPreview">
                    <?php endif; ?>

                    <h4 class="mb-1 fw-bold"><?php echo htmlspecialchars($user['name']); ?></h4>
                    <p class="mb-3 opacity-75"><?php echo htmlspecialchars($user['email']); ?></p>
                    <span class="profile-chip">
                        <i class="bi bi-calendar-check"></i>
                        Tham gia <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                    </span>
                </div>

                <div class="p-3 p-md-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-bar-chart-line-fill me-2" style="color: #0b6f89;"></i>
                        <h6 class="mb-0 fw-bold" style="color: #0b6f89;">Thống kê tài khoản</h6>
                    </div>
                    <div class="stat-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['total_donations']; ?></div>
                            <div class="stat-label">Quyên góp</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['approved_donations']; ?></div>
                            <div class="stat-label">Đã duyệt</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
                            <div class="stat-label">Đơn hàng</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" style="color: #d97706;"><?php echo $stats['pending_donations']; ?></div>
                            <div class="stat-label">Chờ duyệt</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="profile-form-wrap">
                <div class="profile-form-head">
                    <h5><i class="bi bi-person-gear me-2"></i>Thông tin cá nhân</h5>
                </div>
                <div class="profile-form-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success border-0" role="alert" style="background: #e8f8ec; color: #0f5132;">
                            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger border-0" role="alert" style="background: #fdecec; color: #842029;">
                            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="avatar" class="form-label"><i class="bi bi-image me-2"></i>Ảnh đại diện</label>
                            <input type="file" class="form-control" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
                            <div class="form-text">Định dạng JPG, PNG, GIF, WebP. Kích thước tối đa 5MB.</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Họ và tên *</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                <div class="invalid-feedback">Vui lòng nhập họ và tên.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                <div class="form-text">Email không thể thay đổi.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Số điện thoại</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Nhập số điện thoại">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Vai trò</label>
                                <input type="text" class="form-control" id="role" value="<?php echo htmlspecialchars($displayRole, ENT_QUOTES, 'UTF-8'); ?>" disabled>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Địa chỉ</label>
                            <textarea class="form-control" id="address" name="address" rows="3" placeholder="Nhập địa chỉ chi tiết"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn profile-save-btn">
                                <i class="bi bi-check-circle me-2"></i>Cập nhật thông tin
                            </button>
                        </div>
                    </form>

                    <div class="quick-actions">
                        <a href="my-donations.php" class="quick-action-link"><i class="bi bi-heart me-2"></i>Quyên góp của tôi</a>
                        <a href="my-orders.php" class="quick-action-link"><i class="bi bi-bag me-2"></i>Đơn hàng của tôi</a>
                        <a href="donate.php" class="quick-action-link"><i class="bi bi-plus-circle me-2"></i>Quyên góp mới</a>
                        <a href="change-password.php" class="quick-action-link"><i class="bi bi-key me-2"></i>Đổi mật khẩu</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Avatar preview for instant feedback before submit
document.getElementById('avatar')?.addEventListener('change', function(event) {
    const file = event.target.files && event.target.files[0];
    if (!file) return;

    const preview = document.getElementById('avatarPreview');
    const fallback = document.getElementById('avatarFallback');
    if (!preview) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        preview.src = e.target.result;
        preview.classList.remove('d-none');
        if (fallback) fallback.classList.add('d-none');
    };
    reader.readAsDataURL(file);
});
</script>

<?php include 'includes/footer.php'; ?>
