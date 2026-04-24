<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Nếu đã đăng nhập thì quay lại trang chủ
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$status = '';
$message = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');

    if (empty($email)) {
        $status = 'error';
        $message = 'Vui lòng nhập email.';
    } elseif (!validateEmail($email)) {
        $status = 'error';
        $message = 'Email không hợp lệ.';
    } else {
        try {
            // Tìm người dùng theo email
            $sql = "SELECT user_id, name, email FROM users WHERE email = ? AND status = 'active' LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Luôn hiển thị thông báo chung để tránh lộ thông tin tài khoản
            $status = 'success';
            $message = 'Nếu email tồn tại, chúng tôi đã gửi mã OTP và hướng dẫn đặt lại mật khẩu. Vui lòng kiểm tra hộp thư (hoặc mục spam).';

            if ($user) {
                $otpCode = generateOtpCode();
                setOtp('reset', $user['email'], $otpCode, 120);
                $_SESSION['pending_reset_email'] = $user['email'];
                $_SESSION['pending_reset_user_id'] = $user['user_id'];

                // Tạo link đặt lại mật khẩu (không dùng token, xác thực bằng OTP)
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                $resetLink = $scheme . $host . $basePath . '/reset-password.php?email=' . urlencode($user['email']);

                // Gửi email kèm OTP
                $subject = 'Mã OTP đặt lại mật khẩu - Goodwill Vietnam';
                $emailBody = "
                    <h3>Chào " . htmlspecialchars($user['name']) . ",</h3>
                    <p>Mã OTP đặt lại mật khẩu của bạn là: <strong>{$otpCode}</strong></p>
                    <p>Mã có hiệu lực trong 2 phút.</p>
                    <p>Truy cập liên kết sau để nhập OTP và đặt mật khẩu mới:</p>
                    <p><a href=\"{$resetLink}\">Đặt lại mật khẩu</a></p>
                    <p>Nếu bạn không yêu cầu, vui lòng bỏ qua email này.</p>
                    <p>Trân trọng,<br>Goodwill Vietnam</p>
                ";

                // Gửi nhưng không tiết lộ kết quả cụ thể ra ngoài
                sendEmail($user['email'], $subject, $emailBody);
            }
        } catch (Exception $e) {
            error_log('Forgot password error: ' . $e->getMessage());
            $status = 'error';
            $message = 'Có lỗi xảy ra. Vui lòng thử lại sau.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu - Goodwill Vietnam</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
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

        .forgot-container {
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            min-height: 100vh;
        }

        .forgot-left {
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

        .forgot-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(130deg, rgba(8, 123, 148, 0.50), rgba(7, 101, 124, 0.62));
            z-index: 1;
            pointer-events: none;
        }

        .forgot-image-bg {
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

        .forgot-left-content {
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

        .forgot-left-content h1 {
            font-size: clamp(2rem, 4.4vw, 3.15rem);
            font-weight: 800;
            line-height: 1.16;
            margin-bottom: 1rem;
            letter-spacing: 0.2px;
        }

        .forgot-left-content p {
            font-size: clamp(1rem, 1.35vw, 1.2rem);
            line-height: 1.6;
            opacity: 0.95;
            margin-bottom: 0;
        }

        .forgot-right {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(22px, 3.2vw, 40px);
        }

        .forgot-form-wrapper {
            width: 100%;
            max-width: 460px;
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

        .forgot-form-wrapper h2 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.2rem;
            color: var(--ink-900);
        }

        .forgot-form-wrapper .subtitle {
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

        .form-control {
            border: 1.6px solid var(--line);
            border-radius: 12px;
            padding: 0.72rem 0.88rem;
            font-size: 0.98rem;
            background: #fdfefe;
            transition: all 0.22s ease;
        }

        .form-control:focus {
            border-color: var(--brand-500);
            box-shadow: 0 0 0 0.23rem rgba(6, 182, 212, 0.16);
            background: #fff;
        }

        .form-control::placeholder { color: #95a6bc; }

        .btn-send {
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

        .btn-send:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(8, 111, 137, 0.25);
            color: #fff;
            filter: brightness(0.98);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--brand-700);
            text-decoration: none;
            font-weight: 700;
            margin-top: 12px;
            transition: all 0.2s ease;
        }

        .back-link:hover {
            color: #0a5f77;
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
            .forgot-container { grid-template-columns: 1fr; }
            .forgot-left {
                min-height: 280px;
                padding: 30px 20px;
            }
            .forgot-right { padding: 24px 16px 30px; }
            .forgot-form-wrapper { max-width: 620px; }
        }

        @media (max-width: 576px) {
            .forgot-image-bg { opacity: 0.58; }
            .forgot-left-content p {
                font-size: 0.98rem;
                line-height: 1.5;
            }
            .forgot-form-wrapper {
                border-radius: 16px;
                padding: 18px 14px;
            }
            .forgot-form-wrapper h2 { font-size: 1.7rem; }
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
    <div class="forgot-container">
        <div class="forgot-left">
            <img class="forgot-image-bg"
                 src="https://encrypted-tbn3.gstatic.com/shopping?q=tbn:ANd9GcQPQXn_GgKBFI5Hzq6l550Z0v4-S5jJRPY_NKpjdyEJTP9f3jK4foV_uJl5xFLMxOywzX97LuTBlkvOooHxSjmDA29xVDmTYpzoHZR0O_ZYT1TirpDzg9Fb&amp;usqp=CAc"
                 alt="Ảnh nền minh họa"
                 loading="eager"
                 onerror="this.style.display='none';">
            <div class="ambient-orb orb-1"></div>
            <div class="ambient-orb orb-2"></div>
            <div class="ambient-orb orb-3"></div>
            <div class="forgot-left-content">
                <div class="brand-tag">
                    <i class="bi bi-heart-fill"></i>
                    Goodwill Vietnam
                </div>
                <h1>Quên mật khẩu?</h1>
                <p>Đừng lo lắng, hãy nhập email đã đăng ký. Chúng tôi sẽ gửi mã OTP để bạn đặt lại mật khẩu nhanh chóng và an toàn.</p>
            </div>
        </div>

        <div class="forgot-right">
            <div class="forgot-form-wrapper">
                <div class="form-brand">
                    <i class="bi bi-heart-fill"></i>
                    Goodwill Vietnam
                </div>
                <h2>Khôi phục tài khoản</h2>
                <p class="subtitle">Nhập email để nhận mã OTP và hướng dẫn đặt lại mật khẩu</p>

                <?php if (!empty($status)): ?>
                    <div class="alert alert-<?php echo $status === 'success' ? 'success' : 'danger'; ?>" role="alert">
                        <i class="bi <?php echo $status === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
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
                               value="<?php echo htmlspecialchars($email); ?>"
                               placeholder="your@email.com"
                               required>
                        <div class="invalid-feedback">
                            Vui lòng nhập email hợp lệ.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-send mb-2">
                        <i class="bi bi-send me-2"></i>Gửi hướng dẫn
                    </button>
                </form>

                <a href="login.php" class="back-link">
                    <i class="bi bi-arrow-left"></i>Quay lại đăng nhập
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Bootstrap validation
        (function() {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>
