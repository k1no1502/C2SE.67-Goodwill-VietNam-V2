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
$infoMessage = '';
$redirectQuery = '';

if (isset($_GET['redirect']) && $_GET['redirect'] !== '') {
    $redirectQuery = '&redirect=' . urlencode($_GET['redirect']);
}

// Show message when account was locked/disabled
if (isset($_GET['message']) && $_GET['message'] === 'account_locked') {
    $infoMessage = 'Tài khoản của bạn đã bị khóa hoặc tạm ngưng. Vui lòng liên hệ quản trị viên để được hỗ trợ.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validate input
    if (empty($email) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    } elseif (!validateEmail($email)) {
        $error = 'Email không hợp lệ.';
    } else {
        try {
            // Get user from database
            $sql = "SELECT u.*, r.role_name FROM users u 
                    LEFT JOIN roles r ON u.role_id = r.role_id 
                    WHERE u.email = ? AND u.status = 'active'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role_name'];
                $_SESSION['role_id'] = (int) $user['role_id'];
                $_SESSION['avatar'] = $user['avatar'];
                
                // Update last login
                $sql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user['user_id']]);
                
                // Log activity
                logActivity($user['user_id'], 'login', 'User logged in');
                
                // Set remember me cookie
                if ($remember) {
                    $token = generateToken();
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/'); // 30 days
                    
                    // Store token in database
                    $sql = "UPDATE users SET remember_token = ? WHERE user_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$token, $user['user_id']]);
                }
                
                // Redirect based on role
                $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
                if ((int) $user['role_id'] === 1 || $user['role_name'] === 'admin') {
                    $redirect = 'admin/dashboard.php';
                }
                
                header('Location: ' . $redirect);
                exit();
            } else {
                $error = 'Email hoặc mật khẩu không đúng.';
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
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
    <title>Đăng nhập - Goodwill Vietnam</title>
    
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
            --brand-300: #67e8f9;
            --ink-900: #23324a;
            --ink-500: #62718a;
            --line: #d4e8f0;
            --surface: #f6fcfe;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            background:
                radial-gradient(circle at 10% 20%, #e6f7fb 0%, transparent 36%),
                radial-gradient(circle at 88% 82%, #e4f6fa 0%, transparent 34%),
                #f2f9fc;
        }

        .login-container {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            min-height: 100vh;
        }

        .login-left {
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

        .login-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(130deg, rgba(8, 123, 148, 0.50), rgba(7, 101, 124, 0.62));
            z-index: 1;
            pointer-events: none;
        }

        .showcase-image-bg {
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

        .login-left-content {
            position: relative;
            z-index: 2;
            text-align: center;
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

        .login-left-content h1 {
            font-size: clamp(2rem, 4.4vw, 3.15rem);
            font-weight: 800;
            line-height: 1.16;
            margin-bottom: 1rem;
            letter-spacing: 0.2px;
        }

        .login-left-content p {
            font-size: clamp(1rem, 1.35vw, 1.36rem);
            line-height: 1.6;
            opacity: 0.95;
            margin-bottom: 0;
        }

        .login-right {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(22px, 3.2vw, 40px);
        }

        .login-form-wrapper {
            width: 100%;
            max-width: 460px;
            border: 1px solid var(--line);
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.86);
            box-shadow: 0 20px 50px rgba(13, 82, 101, 0.12);
            padding: clamp(22px, 2.4vw, 32px);
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

        .form-brand i {
            font-size: 1.2rem;
        }

        .login-form-wrapper h2 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.2rem;
            color: var(--ink-900);
        }

        .login-form-wrapper .subtitle {
            color: var(--ink-500);
            margin-bottom: 1.25rem;
            font-size: 1.04rem;
        }

        .form-label {
            font-weight: 700;
            color: var(--ink-900);
            margin-bottom: 0.5rem;
        }

        .form-control,
        .form-check-input,
        .input-group-text,
        #togglePassword {
            border: 1.6px solid var(--line);
        }

        .input-group-text {
            background: #f6fcfe;
            color: #6d8097;
            border-right: none;
        }

        .input-group .form-control {
            border-left: none;
            border-right: none;
        }

        .form-control {
            border-radius: 12px;
            padding: 0.83rem 0.95rem;
            font-size: 1rem;
            transition: all 0.22s ease;
            background: #fdfefe;
        }

        .form-control::placeholder { color: #95a6bc; }

        .form-control:focus {
            border-color: var(--brand-500);
            box-shadow: 0 0 0 0.23rem rgba(6, 182, 212, 0.16);
            background: #fff;
        }

        #togglePassword {
            background: #fdfefe;
            color: #7e8fa5;
            border-left: none;
            border-radius: 0 12px 12px 0;
        }

        #togglePassword:hover {
            background: #edf9fc;
            color: var(--brand-700);
        }

        .form-check-input {
            width: 1.15rem;
            height: 1.15rem;
            border-radius: 0.35rem;
            cursor: pointer;
            margin-top: 0.16rem;
        }

        .form-check-input:checked {
            background-color: var(--brand-600);
            border-color: var(--brand-600);
        }

        .form-check-label {
            color: #4b5c72;
            font-size: 0.98rem;
            margin-left: 0.4rem;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
            border: none;
            border-radius: 12px;
            padding: 0.84rem 1rem;
            font-weight: 700;
            font-size: 1.06rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
            color: #fff;
            width: 100%;
        }

        .btn-login:hover {
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(8, 111, 137, 0.25);
            filter: brightness(0.98);
        }

        .forgot-wrap {
            text-align: center;
            margin: 1.2rem 0 0.9rem;
        }

        .forgot-link {
            color: #5a72ff;
            text-decoration: none;
            font-size: 1.02rem;
            font-weight: 700;
        }

        .forgot-link:hover {
            color: #4257d6;
            text-decoration: underline;
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
        }

        .form-footer p {
            color: var(--ink-500);
            margin: 0;
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
            .login-container { grid-template-columns: 1fr; }
            .login-left {
                min-height: 260px;
                padding: 30px 20px;
            }
            .login-right { padding: 24px 16px 30px; }
            .login-form-wrapper { max-width: 620px; }
        }

        @media (max-width: 576px) {
            .showcase-image-bg {
                opacity: 0.58;
            }
            .login-left-content p {
                font-size: 0.98rem;
                line-height: 1.5;
            }
            .login-form-wrapper {
                border-radius: 16px;
                padding: 18px 14px;
            }
            .login-form-wrapper h2 {
                font-size: 1.7rem;
            }
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
    <!-- Login Container -->
    <div class="login-container">
        <!-- Left Side - Decorative -->
        <div class="login-left">
            <img class="showcase-image-bg"
                 src="https://encrypted-tbn3.gstatic.com/shopping?q=tbn:ANd9GcQPQXn_GgKBFI5Hzq6l550Z0v4-S5jJRPY_NKpjdyEJTP9f3jK4foV_uJl5xFLMxOywzX97LuTBlkvOooHxSjmDA29xVDmTYpzoHZR0O_ZYT1TirpDzg9Fb&amp;usqp=CAc"
                 alt="Ảnh nền minh họa"
                 loading="eager"
                 onerror="this.style.display='none';">
            <div class="ambient-orb orb-1"></div>
            <div class="ambient-orb orb-2"></div>
            <div class="ambient-orb orb-3"></div>
            <div class="login-left-content">
                <div class="brand-tag">
                    <i class="bi bi-heart-fill"></i>
                    Goodwill Vietnam
                </div>
                <h1>Chào mừng bạn</h1>
                <p>Đây là nền tảng tình nguyện toàn diện của Goodwill Vietnam. Hãy cùng chúng tôi xây dựng một cộng đồng tốt lành.</p>
            </div>
        </div>
        
        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="login-form-wrapper">
                <div class="form-brand">
                    <i class="bi bi-heart-fill"></i>
                    Goodwill Vietnam
                </div>
                <h2>Đăng nhập</h2>
                <p class="subtitle">Nhập thông tin của bạn để tiếp tục</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <?php if ($infoMessage): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-info-circle me-2"></i><?php echo htmlspecialchars($infoMessage); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <div class="mb-4">
                        <label for="email" class="form-label">
                            <i class="bi bi-envelope me-2"></i>Email
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

                    <div class="mb-4">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock me-2"></i>Mật khẩu
                        </label>
                        <div class="input-group">
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Nhập mật khẩu của bạn"
                                   required>
                            <button class="btn btn-outline-secondary" 
                                    type="button" 
                                    id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                            <div class="invalid-feedback">
                                Vui lòng nhập mật khẩu.
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 form-check">
                        <input type="checkbox" 
                               class="form-check-input" 
                               id="remember" 
                               name="remember">
                        <label class="form-check-label" for="remember">
                            Ghi nhớ thông tin đăng nhập
                        </label>
                    </div>

                    <button type="submit" class="btn btn-login mb-3">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
                    </button>
                </form>

                <p class="forgot-wrap">
                    <a href="forgot-password.php" class="forgot-link">
                        Quên mật khẩu?
                    </a>
                </p>

                <div class="divider">
                    <span>Hoặc</span>
                </div>

                <a class="btn btn-social mb-3" href="social-auth.php?provider=google<?php echo $redirectQuery; ?>">
                    <i class="bi bi-google"></i>Đăng nhập bằng Google
                </a>

                <div class="form-footer">
                    <p>
                        Chưa có tài khoản?
                        <a href="register.php">
                            Đăng ký ngay
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
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
