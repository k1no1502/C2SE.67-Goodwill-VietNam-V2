<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } else {
        try {
            // Get current user
            $user = Database::fetch(
                "SELECT password FROM users WHERE user_id = ?",
                [$_SESSION['user_id']]
            );
            
            // Verify current password
            if (!verifyPassword($current_password, $user['password'])) {
                $error = 'Mật khẩu hiện tại không đúng.';
            } else {
                // Update password
                $new_hash = hashPassword($new_password);
                Database::execute(
                    "UPDATE users SET password = ?, updated_at = NOW() WHERE user_id = ?",
                    [$new_hash, $_SESSION['user_id']]
                );
                
                logActivity($_SESSION['user_id'], 'change_password', 'Password changed successfully');
                
                $success = 'Đổi mật khẩu thành công!';
                
                // Clear form
                $_POST = [];
            }
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            $error = 'Có lỗi xảy ra. Vui lòng thử lại.';
        }
    }
}

$pageTitle = "Đổi mật khẩu";
include 'includes/header.php';
?>

<style>
    .password-shell {
        margin-top: 5.2rem;
        margin-bottom: 2rem;
    }
    .password-wrap {
        border: 1px solid #d9edf2;
        border-radius: 22px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 16px 40px rgba(8, 74, 92, 0.10);
    }
    .password-head {
        padding: 1.25rem 1.4rem;
        color: #fff;
        background:
            radial-gradient(circle at 85% 0%, rgba(255, 255, 255, 0.24), transparent 35%),
            linear-gradient(140deg, #0e7490 0%, #06B6D4 100%);
    }
    .password-head h4 {
        font-weight: 800;
        margin-bottom: 0.3rem;
    }
    .password-head p {
        margin-bottom: 0;
        opacity: 0.9;
    }
    .password-body {
        padding: 1.35rem;
        background: linear-gradient(180deg, #ffffff 0%, #f8fdff 100%);
    }
    .hint-box {
        background: #eff9fc;
        border: 1px solid #cceaf2;
        color: #0b6077;
        border-radius: 12px;
    }
    .password-body .form-label {
        font-weight: 700;
        color: #0f172a;
    }
    .password-body .input-group-text {
        background: #f2fbfd;
        border-color: #cceaf2;
        color: #0e7490;
    }
    .password-body .form-control {
        border-color: #cceaf2;
        padding: 0.7rem 0.82rem;
    }
    .password-body .form-control:focus {
        border-color: #06B6D4;
        box-shadow: 0 0 0 0.2rem rgba(6, 182, 212, 0.16);
    }
    .toggle-visibility-btn {
        border-color: #cceaf2;
        color: #0e7490;
        background: #fff;
    }
    .toggle-visibility-btn:hover {
        background: #eff9fc;
        color: #0e7490;
    }
    .btn-password-save {
        background: linear-gradient(135deg, #0e7490, #06B6D4);
        border: none;
        color: #fff;
        border-radius: 12px;
        font-weight: 700;
        padding: 0.72rem 1rem;
    }
    .btn-password-save:hover {
        color: #fff;
        filter: brightness(0.96);
    }
    .btn-password-back {
        border-radius: 12px;
        border-color: #a7dbe7;
        color: #0e7490;
        font-weight: 600;
    }
    .btn-password-back:hover {
        background: #eff9fc;
        color: #0e7490;
        border-color: #8ecddd;
    }
    @media (max-width: 991.98px) {
        .password-shell {
            margin-top: 4.9rem;
        }
    }
</style>

<div class="container password-shell">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-xl-6">
            <div class="password-wrap">
                <div class="password-head">
                    <h4><i class="bi bi-shield-lock me-2"></i>Đổi mật khẩu</h4>
                    <p>Cập nhật mật khẩu định kỳ để tăng độ an toàn cho tài khoản của bạn.</p>
                </div>
                <div class="password-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <div class="alert hint-box">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Lưu ý:</strong> Mật khẩu phải có ít nhất 6 ký tự và nên bao gồm chữ hoa, chữ thường, số.
                    </div>

                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mật khẩu hiện tại *</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       id="current_password" 
                                       name="current_password" 
                                       required>
                                <button class="btn toggle-visibility-btn" type="button" onclick="togglePassword('current_password')">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <div class="invalid-feedback">
                                    Vui lòng nhập mật khẩu hiện tại.
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">Mật khẩu mới *</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock-fill"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       id="new_password" 
                                       name="new_password" 
                                       minlength="6"
                                       required>
                                <button class="btn toggle-visibility-btn" type="button" onclick="togglePassword('new_password')">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <div class="invalid-feedback">
                                    Mật khẩu phải có ít nhất 6 ký tự.
                                </div>
                            </div>
                            <div class="form-text" id="passwordStrength"></div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới *</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-shield-check"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       required>
                                <button class="btn toggle-visibility-btn" type="button" onclick="togglePassword('confirm_password')">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <div class="invalid-feedback">
                                    Mật khẩu xác nhận không khớp.
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-password-save btn-lg">
                                <i class="bi bi-check-circle me-2"></i>Đổi mật khẩu
                            </button>
                            <a href="profile.php" class="btn btn-password-back">
                                <i class="bi bi-arrow-left me-2"></i>Quay lại hồ sơ
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$additionalScripts = "
<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const btn = event.target.closest('button');
    const icon = btn.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

// Password strength checker
document.getElementById('new_password').addEventListener('input', function() {
    const password = this.value;
    const strengthDiv = document.getElementById('passwordStrength');
    
    if (password.length === 0) {
        strengthDiv.innerHTML = '';
        return;
    }
    
    let strength = 0;
    let text = '';
    let color = '';
    
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;
    
    if (strength <= 2) {
        text = 'Yếu';
        color = 'text-danger';
    } else if (strength <= 3) {
        text = 'Trung bình';
        color = 'text-warning';
    } else {
        text = 'Mạnh';
        color = 'text-success';
    }
    
    strengthDiv.innerHTML = '<i class=\"bi bi-shield me-1\"></i>Độ mạnh: <strong class=\"' + color + '\">' + text + '</strong>';
});

// Confirm password validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (password !== confirmPassword) {
        this.setCustomValidity('Mật khẩu xác nhận không khớp');
    } else {
        this.setCustomValidity('');
    }
});

// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        Array.prototype.filter.call(forms, function(form) {
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
</script>
";

include 'includes/footer.php';
?>
