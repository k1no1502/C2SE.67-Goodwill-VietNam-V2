<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode('recruitment.php'));
    exit();
}

$pageTitle = "Tuyen nhan vien";
$error = '';
$success = '';
$userId = (int)($_SESSION['user_id'] ?? 0);

// Get user info from session/database
$userName = $_SESSION['name'] ?? '';
$userEmail = $_SESSION['email'] ?? '';
$userPhone = '';

// Default values to avoid blank page if database tables are missing.
$positions = [];
$latestApplication = null;

try {
    // Get full user info from database
    $userInfo = Database::fetch("SELECT name, email, phone FROM users WHERE user_id = ?", [$userId]);
    if ($userInfo) {
        $userName = $userInfo['name'] ?? $userName;
        $userEmail = $userInfo['email'] ?? $userEmail;
        $userPhone = $userInfo['phone'] ?? '';
    }

    // Get recruitment positions from database
    $positions = Database::fetchAll("SELECT position_id, position_name FROM recruitment_positions WHERE is_active = 1 ORDER BY position_name");

    $latestApplication = Database::fetch(
        "SELECT application_id, status FROM recruitment_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1",
        [$userId]
    );
} catch (Exception $e) {
    error_log('Recruitment page load error: ' . $e->getMessage());
    $error = 'Chuc nang tuyen dung chua san sang. Vui long chay migration va thu lai.';
}

if (isset($_GET['submitted']) && $_GET['submitted'] === '1') {
    $success = 'Da gui don dang ky. Chung toi se lien he som.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf)) {
        $error = 'Yeu cau khong hop le. Vui long thu lai.';
    } elseif ($latestApplication && $latestApplication['status'] === 'pending') {
        $error = 'Don dang ky cua ban dang duoc xu ly.';
    } else {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $position = sanitize($_POST['position'] ?? '');
        $availability = sanitize($_POST['availability'] ?? '');
        $message = sanitize($_POST['message'] ?? '');
        $cvFilename = null;

        if ($fullName === '' || $email === '' || $phone === '' || $position === '') {
            $error = 'Vui long nhap day du thong tin bat buoc.';
        } elseif (!validateEmail($email)) {
            $error = 'Email khong hop le.';
        } else {
            if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload = uploadFile($_FILES['cv_file'], 'uploads/cv/', ['pdf', 'doc', 'docx']);
                if ($upload['success']) {
                    $cvFilename = $upload['filename'];
                } else {
                    $error = 'CV khong hop le. Vui long chon file PDF, DOC hoac DOCX (toi da 5MB).';
                }
            }
        }

        if ($error === '') {
            try {
                Database::execute(
                    "INSERT INTO recruitment_applications (user_id, full_name, email, phone, position, availability, message, cv_file, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
                    [$userId, $fullName, $email, $phone, $position, $availability, $message, $cvFilename]
                );
                logActivity($userId, 'recruitment_apply', 'Submitted recruitment application');
                $latestApplication = ['status' => 'pending'];
                header('Location: recruitment.php?submitted=1');
                exit();
            } catch (Exception $e) {
                error_log('Recruitment apply error: ' . $e->getMessage());
                $error = 'Co loi xay ra. Vui long thu lai sau khi migration hoan tat.';
            }
        }
    }
}

include 'includes/header.php';
?>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap');

    :root {
        --rc-cyan-50: #eefcff;
        --rc-cyan-100: #d4f7ff;
        --rc-cyan-300: #7ce4ff;
        --rc-cyan-500: #10c3ea;
        --rc-cyan-600: #00a8cf;
        --rc-cyan-700: #008fb2;
        --rc-text: #10303a;
        --rc-muted: #607980;
    }

    .recruitment-modern {
        font-family: 'Manrope', sans-serif;
        color: var(--rc-text);
        background:
            radial-gradient(circle at 10% 15%, rgba(16, 195, 234, 0.12) 0, rgba(16, 195, 234, 0) 28%),
            radial-gradient(circle at 90% 15%, rgba(124, 228, 255, 0.22) 0, rgba(124, 228, 255, 0) 24%),
            #ffffff;
        padding-top: 5.5rem;
        padding-bottom: 4rem;
    }

    .fade-card {
        opacity: 0;
        transform: translateY(24px);
        transition: opacity 0.55s ease, transform 0.55s ease;
    }

    .fade-card.visible {
        opacity: 1;
        transform: translateY(0);
    }

    .hero-panel,
    .soft-panel,
    .form-panel {
        border: 1px solid rgba(16, 195, 234, 0.2);
        border-radius: 24px;
        background: #ffffff;
        box-shadow: 0 16px 40px rgba(3, 74, 90, 0.09);
    }

    .hero-panel {
        padding: 2rem;
        background: linear-gradient(135deg, #ffffff 0%, #f4fcff 55%, #ecfbff 100%);
    }

    .hero-title {
        font-size: clamp(1.8rem, 2.6vw, 2.7rem);
        line-height: 1.15;
        font-weight: 800;
        margin-bottom: 0.8rem;
    }

    .hero-subtitle {
        color: var(--rc-muted);
        max-width: 620px;
    }

    .hero-badges .badge {
        background: var(--rc-cyan-100);
        color: var(--rc-cyan-700);
        border: 1px solid rgba(0, 168, 207, 0.3);
        border-radius: 999px;
        font-weight: 700;
        padding: 0.55rem 0.95rem;
    }

    .hero-icon-wrap {
        width: 120px;
        height: 120px;
        border-radius: 28px;
        margin: 0 auto;
        display: grid;
        place-items: center;
        background: linear-gradient(145deg, var(--rc-cyan-500), var(--rc-cyan-700));
        color: #ffffff;
        box-shadow: 0 20px 40px rgba(0, 168, 207, 0.35);
    }

    .hero-icon-wrap i {
        font-size: 3.2rem;
    }

    .mini-stat {
        border: 1px solid rgba(16, 195, 234, 0.22);
        background: #ffffff;
        border-radius: 16px;
        padding: 0.8rem 1rem;
        text-align: center;
    }

    .mini-stat .value {
        display: block;
        color: var(--rc-cyan-700);
        font-weight: 800;
        font-size: 1.1rem;
    }

    .mini-stat .label {
        color: var(--rc-muted);
        font-size: 0.84rem;
    }

    .soft-panel {
        padding: 1.45rem;
        height: 100%;
    }

    .soft-panel h4 {
        font-weight: 800;
        margin-bottom: 1rem;
    }

    .position-item,
    .requirement-item {
        border: 1px solid rgba(124, 228, 255, 0.45);
        border-radius: 12px;
        background: #f8fdff;
        padding: 0.65rem 0.8rem;
        margin-bottom: 0.6rem;
        display: flex;
        align-items: center;
        gap: 0.55rem;
    }

    .position-item i,
    .requirement-item i {
        color: var(--rc-cyan-600);
    }

    .form-panel {
        margin-top: 2rem;
        padding: 2rem;
        background: linear-gradient(180deg, #ffffff 0%, #f7fdff 100%);
    }

    .form-title {
        font-size: 1.65rem;
        font-weight: 800;
        margin-bottom: 0.35rem;
    }

    .form-subtitle {
        color: var(--rc-muted);
    }

    .processing-badge {
        background: linear-gradient(135deg, var(--rc-cyan-500), var(--rc-cyan-700));
        color: #fff;
        font-weight: 700;
        border-radius: 999px;
        padding: 0.55rem 0.95rem;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .recruitment-modern .form-control,
    .recruitment-modern .form-select {
        border-radius: 12px;
        border-color: rgba(0, 168, 207, 0.25);
        padding: 0.65rem 0.8rem;
    }

    .recruitment-modern .form-control:focus,
    .recruitment-modern .form-select:focus {
        border-color: var(--rc-cyan-500);
        box-shadow: 0 0 0 0.2rem rgba(16, 195, 234, 0.18);
    }

    .submit-btn {
        background: linear-gradient(135deg, var(--rc-cyan-500), var(--rc-cyan-700));
        color: #fff;
        border: none;
        border-radius: 12px;
        font-weight: 700;
        padding: 0.72rem 1.4rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .submit-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 14px 26px rgba(0, 168, 207, 0.34);
        color: #fff;
    }

    @media (max-width: 991.98px) {
        .recruitment-modern {
            padding-top: 5rem;
        }

        .hero-panel,
        .form-panel {
            padding: 1.4rem;
        }
    }
</style>

<section class="recruitment-modern">
    <div class="container">
        <div class="hero-panel fade-card">
            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <h1 class="hero-title">Tuyển nhân viên Goodwill Vietnam</h1>
                    <p class="hero-subtitle mb-3">
                        Môi trường mở, học nhanh, làm việc vì cộng đồng. Chúng tôi chào đón những ứng viên muốn tạo tác động thật sự.
                    </p>
                    <div class="hero-badges d-flex flex-wrap gap-2 mb-3">
                        <span class="badge">Full-time</span>
                        <span class="badge">Part-time</span>
                        <span class="badge">Thuc tap</span>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 col-sm-4">
                            <div class="mini-stat">
                                <span class="value"><?php echo (int)count($positions); ?>+</span>
                                <span class="label">Vi tri mo</span>
                            </div>
                        </div>
                        <div class="col-6 col-sm-4">
                            <div class="mini-stat">
                                <span class="value">48h</span>
                                <span class="label">Phan hoi ho so</span>
                            </div>
                        </div>
                        <div class="col-6 col-sm-4">
                            <div class="mini-stat">
                                <span class="value">On-site</span>
                                <span class="label">Linh hoat lich</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <div class="hero-icon-wrap">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-md-6 fade-card">
                <div class="soft-panel">
                    <h4>Vi tri dang tuyen</h4>
                    <?php if (!empty($positions)): ?>
                        <?php foreach ($positions as $pos): ?>
                            <div class="position-item">
                                <i class="bi bi-stars"></i>
                                <span><?php echo htmlspecialchars($pos['position_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="position-item mb-0">
                            <i class="bi bi-info-circle"></i>
                            <span>Chua co vi tri nao duoc mo. Vui long quay lai sau.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6 fade-card">
                <div class="soft-panel">
                    <h4>Yeu cau chung</h4>
                    <div class="requirement-item"><i class="bi bi-check2-circle"></i>Có tinh thần phục vụ cộng đồng</div>
                    <div class="requirement-item"><i class="bi bi-check2-circle"></i>Chủ động, trách nhiệm cao</div>
                    <div class="requirement-item"><i class="bi bi-check2-circle"></i>Kỹ năng giao tiếp tốt</div>
                    <div class="requirement-item mb-0"><i class="bi bi-check2-circle"></i>Ưu tiên từng tham gia CTXH</div>
                </div>
            </div>
        </div>

        <div class="form-panel fade-card">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <h2 class="form-title">Form ứng tuyển</h2>
                    <p class="form-subtitle mb-0">Điền thông tin để ứng tuyển. Đội ngũ tuyển dụng sẽ liên hệ sớm.</p>
                </div>
                <div class="col-lg-5 text-lg-end">
                    <span class="processing-badge"><i class="bi bi-lightning-charge-fill"></i>Xu ly trong 2-3 ngay</span>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger mt-4" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success mt-4" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            <?php if ($latestApplication && $latestApplication['status'] === 'pending'): ?>
                <div class="alert alert-warning mt-4" role="alert">
                    Đơn đăng ký của bạn đang được xử lý. Vui lòng chờ duyệt.
                </div>
            <?php endif; ?>

            <form class="needs-validation mt-4" method="POST" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="full_name" class="form-label">Họ và tên *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <div class="invalid-feedback">Vui lòng nhập họ tên.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <div class="invalid-feedback">Vui lòng nhập email hợp lệ.</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Số điện thoại *</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($userPhone, ENT_QUOTES, 'UTF-8'); ?>" required>
                        <div class="invalid-feedback">Vui lòng nhập số điện thoại.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="position" class="form-label">Vị trí ứng tuyển *</label>
                        <select class="form-select" id="position" name="position" required>
                            <option value="" selected disabled>Chon vi tri</option>
                            <?php foreach ($positions as $pos): ?>
                            <option value="<?php echo htmlspecialchars($pos['position_name'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($pos['position_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                            <option value="Khac">Khác</option>
                        </select>
                        <div class="invalid-feedback">Vui lòng chọn vị trí ứng tuyển.</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="availability" class="form-label">Thoi gian lam viec</label>
                        <select class="form-select" id="availability" name="availability">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Thuc tap">Thuc tap</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="message" class="form-label">Giới thiệu</label>
                    <textarea class="form-control" id="message" name="message" rows="4" placeholder="Gioi thieu ve ban va ly do ung tuyen"></textarea>
                </div>

                <div class="mb-3">
                    <label for="cv_file" class="form-label">CV (PDF, DOC, DOCX)</label>
                    <input type="file" class="form-control" id="cv_file" name="cv_file" accept=".pdf,.doc,.docx">
                    <div class="form-text">Toi da 5MB. Neu da nop don truoc do, khong can tai lai.</div>
                </div>

                <div class="d-grid d-md-flex gap-3">
                    <button type="submit" class="btn submit-btn btn-lg">
                        <i class="bi bi-send me-2"></i>Gửi ứng tuyển
                    </button>
                </div>
            </form>

            <p class="text-muted small mt-3 mb-0">
                * Don se duoc gui den admin de phe duyet.
            </p>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

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

            var revealElements = document.querySelectorAll('.fade-card');
            revealElements.forEach(function(el, idx) {
                setTimeout(function() {
                    el.classList.add('visible');
                }, 120 + (idx * 120));
            });
        }, false);
    })();
</script>
