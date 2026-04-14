<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$profile = $_SESSION['social_profile'] ?? null;
if (!$profile || empty($profile['provider']) || empty($profile['id'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$name = $profile['name'] ?? '';
$email = $profile['email'] ?? '';
$phone = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');

    if ($name === '' || $email === '') {
        $error = 'Vui long nhap day du thong tin bat buoc.';
    } elseif (!validateEmail($email)) {
        $error = 'Email khong hop le.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email da ton tai. Vui long dang nhap.';
            } else {
                $randomPassword = generateToken(16);
                $hashedPassword = hashPassword($randomPassword);

                $sql = "INSERT INTO users (name, email, password, phone, address, role_id, status, email_verified, avatar, created_at)
                        VALUES (?, ?, ?, ?, ?, 2, 'active', TRUE, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $name,
                    $email,
                    $hashedPassword,
                    $phone,
                    $address,
                    $profile['avatar'] ?? '',
                ]);

                $userId = $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO social_accounts (user_id, provider, provider_user_id, email, name, avatar)
                                       VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $userId,
                    $profile['provider'],
                    $profile['id'],
                    $email,
                    $name,
                    $profile['avatar'] ?? '',
                ]);

                logActivity($userId, 'register_social', 'User registered via ' . $profile['provider']);

                $_SESSION['user_id'] = $userId;
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = 'user';
                $_SESSION['role_id'] = 2;
                $_SESSION['avatar'] = $profile['avatar'] ?? '';

                unset($_SESSION['social_profile']);
                $redirect = $_SESSION['social_redirect'] ?? 'index.php';
                unset($_SESSION['social_redirect']);
                header('Location: ' . $redirect);
                exit();
            }
        } catch (Exception $e) {
            error_log("Social register error: " . $e->getMessage());
            $error = 'Co loi xay ra. Vui long thu lai.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Register - Goodwill Vietnam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-heart-fill me-2"></i>Goodwill Vietnam
            </a>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <i class="bi bi-person-check text-success display-4"></i>
                            <h2 class="fw-bold mt-3">Social Register</h2>
                            <p class="text-muted">Hoan tat thong tin tai khoan</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Ho va ten *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-person"></i>
                                        </span>
                                        <input type="text"
                                               class="form-control"
                                               id="name"
                                               name="name"
                                               value="<?php echo htmlspecialchars($name); ?>"
                                               placeholder="Nhap ho va ten"
                                               required>
                                        <div class="invalid-feedback">
                                            Vui long nhap ho va ten.
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-envelope"></i>
                                        </span>
                                        <input type="email"
                                               class="form-control"
                                               id="email"
                                               name="email"
                                               value="<?php echo htmlspecialchars($email); ?>"
                                               placeholder="Nhap email"
                                               required>
                                        <div class="invalid-feedback">
                                            Vui long nhap email hop le.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">So dien thoai</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-phone"></i>
                                        </span>
                                        <input type="tel"
                                               class="form-control"
                                               id="phone"
                                               name="phone"
                                               value="<?php echo htmlspecialchars($phone); ?>"
                                               placeholder="Nhap so dien thoai">
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="address" class="form-label">Dia chi</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-geo-alt"></i>
                                        </span>
                                        <input type="text"
                                               class="form-control"
                                               id="address"
                                               name="address"
                                               value="<?php echo htmlspecialchars($address); ?>"
                                               placeholder="Nhap dia chi">
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-check-circle me-2"></i>Hoan tat
                                </button>
                            </div>
                        </form>

                        <div class="text-center">
                            <p class="text-muted">
                                Da co tai khoan?
                                <a href="login.php" class="text-success fw-bold text-decoration-none">
                                    Dang nhap
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
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
