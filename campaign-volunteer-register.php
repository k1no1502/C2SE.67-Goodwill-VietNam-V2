<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$campaign_id = (int)($_GET['campaign_id'] ?? $_POST['campaign_id'] ?? 0);
if ($campaign_id <= 0) {
    header('Location: campaigns.php');
    exit();
}

$campaign = Database::fetch(
    "SELECT campaign_id, name, status, start_date, end_date FROM campaigns WHERE campaign_id = ?",
    [$campaign_id]
);

if (!$campaign || !in_array((string)$campaign['status'], ['active', 'pending'], true)) {
    setFlashMessage('error', 'Chiến dịch không hợp lệ hoặc đã ngừng nhận đăng ký.');
    header('Location: campaigns.php');
    exit();
}

$existing = Database::fetch(
    "SELECT volunteer_id FROM campaign_volunteers WHERE campaign_id = ? AND user_id = ? LIMIT 1",
    [$campaign_id, (int)$_SESSION['user_id']]
);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $dateOfBirth = sanitize($_POST['date_of_birth'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $gender = sanitize($_POST['gender'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $skills = sanitize($_POST['skills'] ?? '');
    $availability = sanitize($_POST['availability'] ?? '');
    $role = sanitize($_POST['role'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    if ($fullName === '' || $dateOfBirth === '' || $age <= 0 || $gender === '' || $email === '' || $phone === '') {
        $error = 'Vui lòng điền đầy đủ thông tin bắt buộc.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } elseif (!preg_match('/^\d{9,15}$/', preg_replace('/\D+/', '', $phone))) {
        $error = 'Số điện thoại không hợp lệ.';
    } else {
        $profileBlock = implode("\n", [
            'Thong tin dang ky:',
            '- Ten: ' . $fullName,
            '- Ngay sinh: ' . $dateOfBirth,
            '- Tuoi: ' . $age,
            '- Gioi tinh: ' . ($gender === 'male' ? 'Nam' : 'Nu'),
            '- Email: ' . $email,
            '- SDT: ' . $phone,
        ]);

        $finalMessage = $message !== '' ? ($profileBlock . "\n\n" . $message) : $profileBlock;

        try {
            $columns = Database::fetchAll("SHOW COLUMNS FROM campaign_volunteers LIKE 'role'");
            if (!empty($columns)) {
                Database::execute(
                    "INSERT INTO campaign_volunteers (campaign_id, user_id, skills, availability, role, message, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, 'approved', NOW())",
                    [$campaign_id, (int)$_SESSION['user_id'], $skills, $availability, $role, $finalMessage]
                );
            } else {
                Database::execute(
                    "INSERT INTO campaign_volunteers (campaign_id, user_id, skills, availability, message, status, created_at)
                     VALUES (?, ?, ?, ?, ?, 'approved', NOW())",
                    [$campaign_id, (int)$_SESSION['user_id'], $skills, $availability, $finalMessage]
                );
            }

            logActivity((int)$_SESSION['user_id'], 'register_volunteer', "Registered volunteer for campaign #$campaign_id");
            setFlashMessage('success', 'Đăng ký tình nguyện viên thành công!');
            header('Location: campaign-detail.php?id=' . $campaign_id);
            exit();
        } catch (Exception $e) {
            $error = 'Không thể đăng ký: ' . $e->getMessage();
            error_log('Campaign volunteer register error: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'Đăng ký tình nguyện viên';
include 'includes/header.php';
?>

<style>
.cv-page {
    background:
        radial-gradient(circle at 8% 4%, rgba(6, 182, 212, 0.11), transparent 25%),
        radial-gradient(circle at 92% 12%, rgba(14, 116, 144, 0.08), transparent 24%),
        #eef6f9;
    min-height: 100vh;
}
.cv-hero {
    background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
    color: #fff;
    padding: 42px 0 34px;
    position: relative;
    overflow: hidden;
}
.cv-hero::before {
    content: "";
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 80% 30%, rgba(255, 255, 255, 0.09), transparent 42%);
}
.cv-hero-inner {
    position: relative;
    z-index: 1;
}
.cv-hero-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.8rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.cv-back-btn {
    border: 1px solid rgba(255, 255, 255, 0.52);
    color: #fff;
    border-radius: 999px;
    padding: 0.42rem 0.95rem;
    font-weight: 700;
    background: rgba(255, 255, 255, 0.1);
}
.cv-back-btn:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.2);
}
.cv-hero-main {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.cv-hero-icon {
    width: 86px;
    height: 86px;
    border-radius: 22px;
    border: 1px solid rgba(255, 255, 255, 0.35);
    background: rgba(255, 255, 255, 0.15);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.cv-hero-icon i {
    font-size: 2.55rem;
}
.cv-hero-title {
    margin: 0;
    font-size: clamp(1.65rem, 3vw, 2.45rem);
    font-weight: 900;
    line-height: 1.15;
}
.cv-hero-sub {
    margin: 0.34rem 0 0;
    color: rgba(225, 244, 249, 0.92);
    font-size: 1rem;
}
.cv-shell {
    margin-top: 14px;
    padding: 0 0 34px;
}
.cv-card {
    border: 1px solid #c8e4ec;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 16px 34px rgba(10, 71, 90, 0.13);
}
.cv-card-header {
    background: linear-gradient(140deg, #f6fcfe 0%, #edf8fb 100%);
    border-bottom: 1px solid #d3eaf1;
    padding: 1rem 1.2rem;
}
.cv-card-title {
    margin: 0;
    color: #0f172a;
    font-size: 1.2rem;
    font-weight: 800;
}
.cv-card-meta {
    margin-top: 0.28rem;
    color: #54707d;
    font-size: 0.9rem;
    font-weight: 600;
}
.cv-card-body {
    background: #fff;
    padding: 1.25rem;
}
.cv-section-title {
    color: #0e7490;
    font-size: 0.98rem;
    font-weight: 800;
    margin-bottom: 0.8rem;
    padding-left: 0.72rem;
    border-left: 3px solid #0e7490;
}
.cv-form .form-label {
    color: #244555;
    font-weight: 700;
    font-size: 0.9rem;
}
.cv-form .form-control,
.cv-form .form-select {
    border-color: #bfdeea;
    border-radius: 10px;
    padding-top: 0.56rem;
    padding-bottom: 0.56rem;
}
.cv-form .form-control:focus,
.cv-form .form-select:focus {
    border-color: #0e7490;
    box-shadow: 0 0 0 0.18rem rgba(14, 116, 144, 0.16);
}
.cv-divider {
    border-color: #dcecf2;
    margin: 1.15rem 0 1rem;
}
.cv-btn-cancel,
.cv-btn-submit {
    border-radius: 999px;
    font-weight: 700;
    min-width: 140px;
}
.cv-btn-submit {
    background: linear-gradient(135deg, #0e7490 0%, #06b6d4 100%);
    color: #fff;
    border: none;
}
.cv-btn-submit:hover {
    color: #fff;
    filter: brightness(1.03);
}
.cv-btn-cancel {
    border: 1px solid #c1dbe5;
    color: #334155;
    background: #fff;
}
.cv-btn-cancel:hover {
    background: #f8fdff;
    color: #334155;
}

@media (max-width: 767.98px) {
    .cv-page {
        min-height: 100vh;
    }
    .cv-hero {
        padding: 28px 0 24px;
    }
    .cv-hero-main {
        align-items: flex-start;
    }
    .cv-hero-icon {
        width: 68px;
        height: 68px;
        border-radius: 16px;
    }
    .cv-hero-icon i {
        font-size: 2rem;
    }
    .cv-shell {
        margin-top: 10px;
        padding-bottom: 24px;
    }
    .cv-card-body {
        padding: 1rem;
    }
}
</style>

<div class="cv-page">
    <section class="cv-hero">
        <div class="container cv-hero-inner">
            <div class="cv-hero-top">
                <a href="campaign-detail.php?id=<?php echo $campaign_id; ?>" class="btn cv-back-btn">
                    <i class="bi bi-arrow-left me-1"></i>Quay lại chiến dịch
                </a>
            </div>
            <div class="cv-hero-main">
                <span class="cv-hero-icon" aria-hidden="true"><i class="bi bi-people"></i></span>
                <div>
                    <h1 class="cv-hero-title">Đăng ký tình nguyện viên</h1>
                    <p class="cv-hero-sub">Chiến dịch: <?php echo htmlspecialchars($campaign['name']); ?></p>
                </div>
            </div>
        </div>
    </section>

    <div class="container cv-shell">
        <div class="row justify-content-center">
            <div class="col-xl-9">
                <div class="card cv-card border-0">
                    <div class="cv-card-header">
                        <h2 class="cv-card-title">Thông tin tham gia chiến dịch</h2>
                        <div class="cv-card-meta">Điền thông tin đầy đủ để hệ thống xác nhận nhanh hơn.</div>
                    </div>
                    <div class="cv-card-body">
                        <?php if ($existing): ?>
                            <div class="alert alert-success mb-0">
                                <i class="bi bi-check-circle me-1"></i>Bạn đã đăng ký làm tình nguyện viên cho chiến dịch này.
                            </div>
                            <div class="mt-3">
                                <a href="campaign-detail.php?id=<?php echo $campaign_id; ?>" class="btn cv-btn-submit">
                                    <i class="bi bi-arrow-left me-1"></i>Quay lại chi tiết chiến dịch
                                </a>
                            </div>
                        <?php else: ?>
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>

                            <form method="POST" class="cv-form">
                                <input type="hidden" name="campaign_id" value="<?php echo $campaign_id; ?>">

                                <div class="cv-section-title">Thông tin cá nhân</div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Tên *</label>
                                        <input type="text" class="form-control" name="full_name" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ($_SESSION['username'] ?? '')); ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Ngày sinh *</label>
                                        <input type="date" class="form-control" name="date_of_birth" id="volunteerDobPage" required value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Tuổi *</label>
                                        <input type="number" class="form-control" name="age" id="volunteerAgePage" min="1" max="120" required value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Giới tính *</label>
                                        <select class="form-select" name="gender" required>
                                            <option value="">-- Chọn giới tính --</option>
                                            <option value="male" <?php echo (($_POST['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Nam</option>
                                            <option value="female" <?php echo (($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Nữ</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Email *</label>
                                        <input type="email" class="form-control" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">SĐT *</label>
                                        <input type="tel" class="form-control" name="phone" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                    </div>
                                </div>

                                <hr class="cv-divider">

                                <div class="cv-section-title">Khả năng đóng góp</div>
                                <div class="mb-3">
                                    <label class="form-label">Kỹ năng bạn có thể đóng góp</label>
                                    <textarea class="form-control" name="skills" rows="2" placeholder="VD: Có xe máy, biết dùng máy tính..."><?php echo htmlspecialchars($_POST['skills'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Thời gian bạn có thể tham gia</label>
                                    <textarea class="form-control" name="availability" rows="2" placeholder="VD: Thứ 7, Chủ nhật..."><?php echo htmlspecialchars($_POST['availability'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Vai trò mong muốn</label>
                                    <input type="text" class="form-control" name="role" placeholder="VD: Tổ chức, Vận chuyển, Phân phát..." value="<?php echo htmlspecialchars($_POST['role'] ?? ''); ?>">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Lời nhắn</label>
                                    <textarea class="form-control" name="message" rows="3" placeholder="Tại sao bạn muốn tham gia chiến dịch này?"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                </div>

                                <div class="d-flex justify-content-end flex-wrap gap-2">
                                    <a href="campaign-detail.php?id=<?php echo $campaign_id; ?>" class="btn cv-btn-cancel">Hủy</a>
                                    <button type="submit" class="btn cv-btn-submit">
                                        <i class="bi bi-send me-1"></i>Gửi đăng ký
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const volunteerDobPage = document.getElementById('volunteerDobPage');
const volunteerAgePage = document.getElementById('volunteerAgePage');

function calculateAgeFromDobPage(dobValue) {
    if (!dobValue) return '';
    const dob = new Date(dobValue);
    if (Number.isNaN(dob.getTime())) return '';
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const monthDiff = today.getMonth() - dob.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age -= 1;
    }
    return age >= 0 ? String(age) : '';
}

if (volunteerDobPage && volunteerAgePage) {
    volunteerDobPage.addEventListener('change', function() {
        volunteerAgePage.value = calculateAgeFromDobPage(this.value);
    });
}
</script>

<?php include 'includes/footer.php'; ?>
