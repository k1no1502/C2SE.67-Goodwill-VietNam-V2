<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $address = sanitize($_POST['address'] ?? '');
    $agree_terms = isset($_POST['agree_terms']);
    
    // Validate input
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin bắt buộc.';
    } elseif (!validateEmail($email)) {
        $error = 'Email không hợp lệ.';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } elseif ($password !== $confirm_password) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } elseif (!$agree_terms) {
        $error = 'Vui lòng đồng ý với điều khoản sử dụng.';
    } else {
        try {
            // Check if email already exists
            $sql = "SELECT user_id FROM users WHERE email = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'Email này đã được sử dụng. Vui lòng chọn email khác.';
            } else {
                // Generate verification token
                $verification_token = generateToken();
                
                // Insert new user
                $sql = "INSERT INTO users (name, email, password, phone, address, role_id, status, email_verified, verification_token, created_at) 
                        VALUES (?, ?, ?, ?, ?, 2, 'active', FALSE, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $hashed_password = hashPassword($password);
                
                if ($stmt->execute([$name, $email, $hashed_password, $phone, $address, $verification_token])) {
                    $user_id = $pdo->lastInsertId();
                    
                    // Log activity
                    logActivity($user_id, 'register', 'New user registered');
                    
                    // Send verification email (in production)
                    // sendVerificationEmail($email, $verification_token);
                    
                    $success = 'Đăng ký thành công! Bạn có thể đăng nhập ngay bây giờ.';
                    
                    // Auto login after registration
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['name'] = $name;
                    $_SESSION['email'] = $email;
                    $_SESSION['role'] = 'user';
                    $_SESSION['role_id'] = 2;
                    $_SESSION['avatar'] = '';
                    
                    // Redirect to home page
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Có lỗi xảy ra khi tạo tài khoản. Vui lòng thử lại.';
                }
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = 'Có lỗi xảy ra. Vui lòng thử lại sau.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - Goodwill Vietnam</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">

    <style>
        :root {
            --brand-700: #0e7490;
            --brand-600: #0f869f;
            --brand-500: #06B6D4;
            --ink-900: #23324a;
            --ink-500: #62718a;
            --line: #d4e8f0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background:
                radial-gradient(circle at 12% 18%, #e6f7fb 0%, transparent 36%),
                radial-gradient(circle at 84% 80%, #e4f6fa 0%, transparent 34%),
                #f2f9fc;
        }

        .register-container {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            min-height: 100vh;
        }

        .register-left {
            position: relative;
            overflow: hidden;
            background: linear-gradient(145deg, #0891b2 0%, #0e7490 46%, #0a6780 100%);
            color: #fff;
            padding: clamp(32px, 6vw, 60px);
            display: flex;
            align-items: center;
            justify-content: center;
            isolation: isolate;
        }

        .register-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(130deg, rgba(8, 123, 148, 0.50), rgba(7, 101, 124, 0.62));
            z-index: 1;
            pointer-events: none;
        }

        .register-image-bg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.64;
            filter: saturate(1.02) contrast(1.08) brightness(0.93);
            z-index: 0;
            animation: slow-pan 16s ease-in-out infinite;
            transform-origin: center;
        }

        .ambient-orb {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            z-index: 2;
            opacity: 0.35;
            filter: blur(1px);
            animation: drift 10s ease-in-out infinite;
        }

        .orb-1 {
            width: 420px;
            height: 420px;
            background: radial-gradient(circle, rgba(255,255,255,0.32), rgba(255,255,255,0.05));
            top: -140px;
            right: -110px;
        }

        .orb-2 {
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(103,232,249,0.5), rgba(103,232,249,0.05));
            bottom: -70px;
            left: -65px;
            animation-delay: -4s;
        }

        .orb-3 {
            width: 120px;
            height: 120px;
            background: radial-gradient(circle, rgba(255,255,255,0.22), rgba(255,255,255,0.02));
            top: 26%;
            left: 38%;
            border-radius: 24px;
            transform: rotate(45deg);
            animation: float-square 7s ease-in-out infinite;
        }

        .register-left-content {
            position: relative;
            z-index: 3;
            text-align: center;
            color: #fff;
            max-width: 560px;
            animation: reveal-left 0.75s ease-out;
        }

        .brand-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.45rem 0.85rem;
            border: 1px solid rgba(255,255,255,0.34);
            border-radius: 999px;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(4px);
            margin-bottom: 1.2rem;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .register-left-content h1 {
            font-size: clamp(2rem, 4.4vw, 3.15rem);
            font-weight: 800;
            line-height: 1.16;
            margin-bottom: 1rem;
            letter-spacing: 0.2px;
        }

        .register-left-content p {
            font-size: clamp(1rem, 1.35vw, 1.2rem);
            line-height: 1.6;
            opacity: 0.95;
            margin-bottom: 0;
        }

        .register-right {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(22px, 3.2vw, 40px);
        }

        .register-form-wrapper {
            width: 100%;
            max-width: 560px;
            border: 1px solid var(--line);
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 20px 50px rgba(13, 82, 101, 0.12);
            padding: clamp(20px, 2.2vw, 30px);
            animation: reveal-right 0.75s ease-out;
            backdrop-filter: blur(8px);
        }

        .form-brand {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1rem;
            color: var(--brand-700);
            font-weight: 800;
            font-size: 1.05rem;
        }

        .register-form-wrapper h2 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.2rem;
            color: var(--ink-900);
        }

        .register-form-wrapper .subtitle {
            color: var(--ink-500);
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .form-label {
            font-weight: 700;
            color: var(--ink-900);
            margin-bottom: 0.45rem;
            font-size: 0.95rem;
        }

        .form-control,
        .form-check-input,
        .input-group-text,
        .btn-toggle-password {
            border: 1.6px solid var(--line);
            transition: all 0.22s ease;
        }

        .form-control {
            border-radius: 12px;
            padding: 0.72rem 0.88rem;
            font-size: 0.98rem;
            background: #fdfefe;
        }

        .form-control:focus {
            border-color: var(--brand-500);
            box-shadow: 0 0 0 0.23rem rgba(6, 182, 212, 0.16);
            background: #fff;
        }

        .form-control::placeholder { color: #95a6bc; }

        .input-group-text {
            background: #f6fcfe;
            color: #6d8097;
            border-right: none;
            border-radius: 12px 0 0 12px;
        }

        .input-group .form-control {
            border-left: none;
            border-right: none;
            border-radius: 0;
        }

        .btn-toggle-password {
            border-left: none;
            background: #fdfefe;
            color: #7e8fa5;
            padding: 0 14px;
            border-radius: 0 12px 12px 0;
        }

        .btn-toggle-password:hover {
            color: var(--brand-700);
            background: #edf9fc;
        }

        .form-check-input {
            width: 1.1rem;
            height: 1.1rem;
            border-radius: 0.35rem;
            margin-top: 0.16rem;
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: var(--brand-600);
            border-color: var(--brand-600);
        }

        .form-check-label {
            color: #4b5c72;
            font-size: 0.95rem;
            margin-left: 0.35rem;
        }

        .form-check-label a {
            color: var(--brand-700);
            font-weight: 700;
            text-decoration: none;
        }

        .form-check-label a:hover {
            text-decoration: underline;
        }

        .btn-register {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            border: none;
            border-radius: 12px;
            padding: 0.8rem 1rem;
            font-weight: 700;
            font-size: 1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
            color: #fff;
            width: 100%;
        }

        .btn-register:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(8, 111, 137, 0.25);
            color: #fff;
            filter: brightness(0.98);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 1rem 0;
            color: #90a0b5;
            font-size: 0.94rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--line);
        }

        .divider span {
            padding: 0 0.7rem;
            font-weight: 600;
        }

        .btn-social {
            border-radius: 12px;
            border: 1.6px solid var(--line);
            padding: 0.78rem;
            font-weight: 700;
            color: var(--ink-900);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            width: 100%;
            transition: all 0.22s ease;
            background: #fff;
        }

        .btn-social:hover {
            border-color: #a6dbe8;
            background: #f5fcfe;
            color: var(--brand-700);
        }

        .form-footer {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.98rem;
            color: var(--ink-500);
        }

        .form-footer a {
            color: var(--brand-700);
            text-decoration: none;
            font-weight: 700;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .alert {
            border-radius: 12px;
            border: 1px solid transparent;
            margin-bottom: 1rem;
            animation: pop-in 0.24s ease-out;
        }

        @keyframes reveal-left {
            from { opacity: 0; transform: translateX(-26px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes reveal-right {
            from { opacity: 0; transform: translateX(26px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes pop-in {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes drift {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-14px); }
        }

        @keyframes float-square {
            0%, 100% { transform: rotate(45deg) translateY(0); }
            50% { transform: rotate(45deg) translateY(-18px); }
        }

        @keyframes slow-pan {
            0%, 100% { transform: scale(1.04) translateX(0); }
            50% { transform: scale(1.08) translateX(-1.2%); }
        }

        @media (max-width: 992px) {
            .register-container { grid-template-columns: 1fr; }
            .register-left {
                min-height: 280px;
                padding: 30px 20px;
            }
            .register-right { padding: 24px 16px 30px; }
            .register-form-wrapper { max-width: 620px; }
        }

        @media (max-width: 576px) {
            .register-image-bg { opacity: 0.58; }
            .register-left-content p {
                font-size: 0.98rem;
                line-height: 1.5;
            }
            .register-form-wrapper {
                border-radius: 16px;
                padding: 18px 14px;
            }
            .register-form-wrapper h2 { font-size: 1.7rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation: none !important;
                transition: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-left">
            <img class="register-image-bg"
                 src="https://encrypted-tbn3.gstatic.com/shopping?q=tbn:ANd9GcQPQXn_GgKBFI5Hzq6l550Z0v4-S5jJRPY_NKpjdyEJTP9f3jK4foV_uJl5xFLMxOywzX97LuTBlkvOooHxSjmDA29xVDmTYpzoHZR0O_ZYT1TirpDzg9Fb&amp;usqp=CAc"
                 alt="Ảnh nền minh họa"
                 loading="eager"
                 onerror="this.style.display='none';">
            <div class="ambient-orb orb-1"></div>
            <div class="ambient-orb orb-2"></div>
            <div class="ambient-orb orb-3"></div>
            <div class="register-left-content">
                <div class="brand-tag">
                    <i class="bi bi-heart-fill"></i>
                    Goodwill Vietnam
                </div>
                <h1>Tạo tài khoản mới</h1>
                <p>Tham gia Goodwill Vietnam để kết nối cộng đồng, tham gia hoạt động ý nghĩa và lan tỏa giá trị tích cực mỗi ngày.</p>
            </div>
        </div>

        <div class="register-right">
            <div class="register-form-wrapper">
                <div class="form-brand">
                    <i class="bi bi-heart-fill"></i>
                    Goodwill Vietnam
                </div>
                <h2>Đăng ký</h2>
                <p class="subtitle">Điền thông tin để bắt đầu hành trình cùng Goodwill Vietnam</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">
                                <i class="bi bi-person me-2"></i>Họ và tên *
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="name"
                                   name="name"
                                   value="<?php echo htmlspecialchars($name ?? ''); ?>"
                                   placeholder="Nguyễn Văn A"
                                   required>
                            <div class="invalid-feedback">
                                Vui lòng nhập họ và tên.
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">
                                <i class="bi bi-envelope me-2"></i>Email *
                            </label>
                            <input type="email"
                                   class="form-control"
                                   id="email"
                                   name="email"
                                   value="<?php echo htmlspecialchars($email ?? ''); ?>"
                                   placeholder="your@email.com"
                                   required>
                            <div class="invalid-feedback">
                                Vui lòng nhập email hợp lệ.
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">
                                <i class="bi bi-phone me-2"></i>Số điện thoại
                            </label>
                            <input type="tel"
                                   class="form-control"
                                   id="phone"
                                   name="phone"
                                   value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                                   placeholder="09xxxxxxxx">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="address" class="form-label">
                                <i class="bi bi-geo-alt me-2"></i>Địa chỉ
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="address"
                                   name="address"
                                   value="<?php echo htmlspecialchars($address ?? ''); ?>"
                                   placeholder="Quận/Huyện, Tỉnh/TP">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">
                                <i class="bi bi-lock me-2"></i>Mật khẩu *
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-shield-lock"></i>
                                </span>
                                <input type="password"
                                       class="form-control"
                                       id="password"
                                       name="password"
                                       placeholder="Ít nhất 6 ký tự"
                                       minlength="6"
                                       required>
                                <button class="btn btn-toggle-password" type="button" id="togglePassword" aria-label="Hiện hoặc ẩn mật khẩu">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <div class="invalid-feedback">
                                    Mật khẩu phải có ít nhất 6 ký tự.
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">
                                <i class="bi bi-lock-fill me-2"></i>Xác nhận mật khẩu *
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-shield-lock"></i>
                                </span>
                                <input type="password"
                                       class="form-control"
                                       id="confirm_password"
                                       name="confirm_password"
                                       placeholder="Nhập lại mật khẩu"
                                       required>
                                <button class="btn btn-toggle-password" type="button" id="toggleConfirmPassword" aria-label="Hiện hoặc ẩn xác nhận mật khẩu">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <div class="invalid-feedback">
                                    Mật khẩu xác nhận không khớp.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox"
                               class="form-check-input"
                               id="agree_terms"
                               name="agree_terms"
                               <?php echo !empty($agree_terms) ? 'checked' : ''; ?>
                               required>
                        <label class="form-check-label" for="agree_terms">
                            Tôi đồng ý với <a href="terms.php" target="_blank">điều khoản sử dụng</a> và <a href="privacy.php" target="_blank">chính sách bảo mật</a>
                        </label>
                        <div class="invalid-feedback">
                            Vui lòng đồng ý với điều khoản sử dụng.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-register mb-2">
                        <i class="bi bi-person-plus me-2"></i>Tạo tài khoản
                    </button>
                </form>

                <div class="divider">
                    <span>Hoặc</span>
                </div>

                <a class="btn btn-social" href="social-auth.php?provider=google">
                    <i class="bi bi-google"></i>Đăng ký bằng Google
                </a>

                <div class="form-footer">
                    Đã có tài khoản?
                    <a href="login.php">Đăng nhập ngay</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    
    <script>
        function setupTogglePassword(buttonId, inputId) {
            const toggleButton = document.getElementById(buttonId);
            const input = document.getElementById(inputId);
            if (!toggleButton || !input) {
                return;
            }

            toggleButton.addEventListener('click', function() {
                const icon = this.querySelector('i');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            });
        }

        setupTogglePassword('togglePassword', 'password');
        setupTogglePassword('toggleConfirmPassword', 'confirm_password');

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
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
    </script>
</body>
</html>
