<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode('recruitment.php'));
    exit();
}

$pageTitle = "Tuyển nhân viên";
$error = '';
$success = '';
$userId = (int)($_SESSION['user_id'] ?? 0);

// Get user info from session/database
$userName = $_SESSION['name'] ?? '';
$userEmail = $_SESSION['email'] ?? '';
$userPhone = '';
$selectedPosition = sanitize($_POST['position'] ?? '');

$defaultPositionNames = [
    'Quản lý kho hàng',
    'Quản lý đơn hàng',
    'Quản lý chiến dịch',
    'Tư vấn chăm sóc khách hàng',
    'Thu ngân'
];

$positions = [];
foreach ($defaultPositionNames as $idx => $name) {
    $positions[] = [
        'position_id' => $idx + 1,
        'position_name' => $name
    ];
}

$positionDetailMap = [
    'Quản lý kho hàng' => [
        'summary' => 'Theo dõi nhập/xuất kho và đảm bảo tồn kho chính xác theo ngày.',
        'tasks' => [
            'Kiểm kê hàng hóa định kỳ',
            'Cập nhật số liệu kho trên hệ thống',
            'Phối hợp xử lý thiếu/hỏng hàng'
        ]
    ],
    'Quản lý đơn hàng' => [
        'summary' => 'Điều phối quy trình đơn hàng từ xác nhận đến bàn giao đúng hạn.',
        'tasks' => [
            'Xác nhận và theo dõi trạng thái đơn',
            'Làm việc với kho và vận chuyển',
            'Xử lý phát sinh trong quá trình giao'
        ]
    ],
    'Quản lý chiến dịch' => [
        'summary' => 'Lập kế hoạch và theo dõi hiệu quả các chiến dịch cộng đồng.',
        'tasks' => [
            'Lên timeline và mục tiêu chiến dịch',
            'Phối hợp các bộ phận triển khai',
            'Báo cáo kết quả và đề xuất cải tiến'
        ]
    ],
    'Tư vấn chăm sóc khách hàng' => [
        'summary' => 'Hỗ trợ người dùng nhanh chóng, đúng thông tin và đúng quy trình.',
        'tasks' => [
            'Tiếp nhận và phản hồi thắc mắc',
            'Theo dõi yêu cầu đến khi hoàn tất',
            'Ghi nhận phản hồi để cải thiện dịch vụ'
        ]
    ],
    'Thu ngân' => [
        'summary' => 'Thực hiện thu chi chính xác, minh bạch và đối soát cuối ngày.',
        'tasks' => [
            'Xử lý thanh toán tại quầy',
            'Đối soát tiền mặt và hóa đơn',
            'Báo cáo số liệu doanh thu cuối ca'
        ]
    ]
];

$defaultPositionDetail = [
    'summary' => 'Hỗ trợ vận hành theo đúng quy trình của đội ngũ Goodwill Vietnam.',
    'tasks' => [
        'Thực hiện công việc theo phân công',
        'Phối hợp với các bộ phận liên quan',
        'Báo cáo tiến độ công việc hằng ngày'
    ]
];

$positionDetails = [];
foreach ($positions as $pos) {
    $name = $pos['position_name'] ?? '';
    if ($name === '') {
        continue;
    }
    $positionDetails[$name] = $positionDetailMap[$name] ?? $defaultPositionDetail;
}

$firstPositionName = !empty($positions) ? ($positions[0]['position_name'] ?? '') : '';
$initialPositionName = ($selectedPosition !== '' && isset($positionDetails[$selectedPosition])) ? $selectedPosition : $firstPositionName;
$initialPositionDetail = $positionDetails[$initialPositionName] ?? $defaultPositionDetail;

// Default values to avoid blank page if database tables are missing.
$latestApplication = null;

try {
    // Get full user info from database
    $userInfo = Database::fetch("SELECT name, email, phone FROM users WHERE user_id = ?", [$userId]);
    if ($userInfo) {
        $userName = $userInfo['name'] ?? $userName;
        $userEmail = $userInfo['email'] ?? $userEmail;
        $userPhone = $userInfo['phone'] ?? '';
    }

    // Get recruitment positions from database (fallback to defaults if empty)
    $dbPositions = Database::fetchAll("SELECT position_id, position_name FROM recruitment_positions WHERE is_active = 1 ORDER BY sort_order ASC, position_name ASC");
    if (!empty($dbPositions)) {
        $positions = $dbPositions;
    }

    $latestApplication = Database::fetch(
        "SELECT application_id, status FROM recruitment_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1",
        [$userId]
    );
} catch (Exception $e) {
    error_log('Recruitment page load error: ' . $e->getMessage());
}

if (isset($_GET['submitted']) && $_GET['submitted'] === '1') {
    $success = 'Đã gửi đơn đăng ký. Chúng tôi sẽ liên hệ sớm.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } elseif ($latestApplication && $latestApplication['status'] === 'pending') {
        $error = 'Đơn đăng ký của bạn đang được xử lý.';
    } else {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $position = sanitize($_POST['position'] ?? '');
        $availability = sanitize($_POST['availability'] ?? '');
        $message = sanitize($_POST['message'] ?? '');
        $cvFilename = null;

        if ($fullName === '' || $email === '' || $phone === '' || $position === '') {
            $error = 'Vui lòng nhập đầy đủ thông tin bắt buộc.';
        } elseif (!validateEmail($email)) {
            $error = 'Email không hợp lệ.';
        } else {
            if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload = uploadFile($_FILES['cv_file'], 'uploads/cv/', ['pdf', 'doc', 'docx']);
                if ($upload['success']) {
                    $cvFilename = $upload['filename'];
                } else {
                    $error = 'CV không hợp lệ. Vui lòng chọn file PDF, DOC hoặc DOCX (tối đa 5MB).';
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
                $error = 'Có lỗi xảy ra. Vui lòng thử lại sau khi migration hoàn tất.';
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
        background: #d0d8de;
        padding-top: 0;
        padding-bottom: 4rem;
        min-height: 100vh;
    }

    .recruitment-hero {
        background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
        padding: 64px 0 48px;
        color: #fff;
        position: relative;
        overflow: hidden;
    }

    .recruitment-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 80% 50%, rgba(255,255,255,0.07) 0%, transparent 60%);
    }

    .recruitment-hero-content {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 1.6rem;
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

    .soft-panel,
    .form-panel {
        border: 1px solid rgba(120, 208, 232, 0.48);
        border-radius: 24px;
        background: #ffffff;
        box-shadow: 0 12px 30px rgba(3, 74, 90, 0.08);
    }

    .hero-title {
        font-size: clamp(1.9rem, 4vw, 2.95rem);
        line-height: 1.12;
        font-weight: 900;
        margin-bottom: 0.55rem;
        color: #ffffff;
    }

    .hero-subtitle {
        color: rgba(255, 255, 255, 0.88);
        max-width: 860px;
        font-size: 1.03rem;
        line-height: 1.5;
        margin-bottom: 0;
    }

    .hero-icon-wrap {
        width: 112px;
        height: 112px;
        border-radius: 28px;
        margin: 0;
        display: grid;
        place-items: center;
        background: rgba(255, 255, 255, 0.15);
        color: #ffffff;
        box-shadow: none;
        border: 1px solid rgba(255, 255, 255, 0.26);
        backdrop-filter: blur(6px);
    }

    .hero-icon-wrap i {
        font-size: 3.25rem;
    }

    .recruitment-content {
        padding-top: 2.3rem;
    }

    .mini-stat {
        border: 1px solid rgba(120, 208, 232, 0.45);
        background: #ffffff;
        border-radius: 24px;
        padding: 1.2rem 1.1rem;
        text-align: center;
    }

    .mini-stat .value {
        display: block;
        color: var(--rc-cyan-700);
        font-weight: 800;
        font-size: 2.2rem;
        line-height: 1.05;
    }

    .mini-stat .label {
        color: var(--rc-muted);
        font-size: 0.95rem;
        margin-top: 0.25rem;
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

    .position-item-selectable {
        cursor: pointer;
        transition: border-color 0.2s ease, background-color 0.2s ease, transform 0.2s ease;
    }

    .position-item-selectable:hover,
    .position-item-selectable:focus-visible {
        border-color: rgba(0, 168, 207, 0.58);
        background: var(--rc-cyan-50);
        transform: translateY(-1px);
        outline: none;
    }

    .position-item-active {
        border-color: rgba(0, 168, 207, 0.8);
        background: var(--rc-cyan-100);
    }

    .position-preview,
    .position-form-preview {
        border: 1px solid rgba(124, 228, 255, 0.45);
        border-radius: 14px;
        background: #f8fdff;
        padding: 0.85rem 0.95rem;
    }

    .position-preview-title,
    .position-form-preview-title {
        font-size: 1rem;
        font-weight: 800;
        margin-bottom: 0.45rem;
    }

    .position-preview-summary,
    .position-form-preview-summary {
        color: var(--rc-muted);
        font-size: 0.92rem;
        margin-bottom: 0.45rem;
    }

    .position-preview-tasks,
    .position-form-preview-tasks {
        margin: 0;
        padding-left: 1rem;
        color: var(--rc-text);
        font-size: 0.9rem;
    }

    .position-preview-tasks li,
    .position-form-preview-tasks li {
        margin-bottom: 0.2rem;
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
            padding-top: 0;
        }

        .form-panel {
            padding: 1.4rem;
        }

        .recruitment-hero {
            padding: 42px 0 34px;
        }

        .recruitment-hero-content {
            gap: 1rem;
        }

        .hero-icon-wrap {
            width: 90px;
            height: 90px;
            border-radius: 22px;
        }

        .hero-icon-wrap i {
            font-size: 2.45rem;
        }

        .mini-stat .value {
            font-size: 1.6rem;
        }
    }
</style>

<section class="recruitment-modern">
    <div class="recruitment-hero">
        <div class="container">
            <div class="recruitment-hero-content">
                <div class="hero-icon-wrap">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <h1 class="hero-title">Tuyển nhân viên Goodwill Vietnam</h1>
                    <p class="hero-subtitle">Môi trường mở, học nhanh, làm việc vì cộng đồng. Chúng tôi chào đón những ứng viên muốn tạo tác động thật sự.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container recruitment-content">
        <div class="row g-2 mb-4">
            <div class="col-6 col-md-4">
                <div class="mini-stat">
                    <span class="value"><?php echo (int)count($positions); ?>+</span>
                    <span class="label">Vị trí mở</span>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="mini-stat">
                    <span class="value">48h</span>
                    <span class="label">Phản hồi hồ sơ</span>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="mini-stat">
                    <span class="value">On-site</span>
                    <span class="label">Linh hoạt lịch</span>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-md-6 fade-card">
                <div class="soft-panel">
                    <h4>Vị trí đang tuyển</h4>
                    <?php if (!empty($positions)): ?>
                        <?php foreach ($positions as $pos): ?>
                            <?php $positionName = $pos['position_name']; ?>
                            <div class="position-item position-item-selectable<?php echo ($positionName === $initialPositionName) ? ' position-item-active' : ''; ?>"
                                tabindex="0"
                                role="button"
                                aria-label="Chọn vị trí <?php echo htmlspecialchars($positionName, ENT_QUOTES, 'UTF-8'); ?>"
                                data-position-name="<?php echo htmlspecialchars($positionName, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="bi bi-stars"></i>
                                <span><?php echo htmlspecialchars($positionName, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        <?php endforeach; ?>

                        <div class="position-preview mt-2" id="position-preview-top" aria-live="polite">
                            <div class="position-preview-title" id="position-preview-title"><?php echo htmlspecialchars($initialPositionName ?: 'Vị trí tuyển dụng', ENT_QUOTES, 'UTF-8'); ?></div>
                            <p class="position-preview-summary" id="position-preview-summary"><?php echo htmlspecialchars($initialPositionDetail['summary'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <ul class="position-preview-tasks" id="position-preview-tasks">
                                <?php foreach (($initialPositionDetail['tasks'] ?? []) as $task): ?>
                                    <li><?php echo htmlspecialchars($task, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="position-item mb-0">
                            <i class="bi bi-info-circle"></i>
                            <span>Chưa có vị trí nào được mở. Vui lòng quay lại sau.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6 fade-card">
                <div class="soft-panel">
                    <h4>Yêu cầu chung</h4>
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
                    <span class="processing-badge"><i class="bi bi-lightning-charge-fill"></i>Xử lý trong 2-3 ngày</span>
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
                            <option value="" <?php echo $selectedPosition === '' ? 'selected' : ''; ?> disabled>Chọn vị trí</option>
                            <?php foreach ($positions as $pos): ?>
                            <?php $optionName = $pos['position_name']; ?>
                            <option value="<?php echo htmlspecialchars($optionName, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedPosition === $optionName ? 'selected' : ''; ?>><?php echo htmlspecialchars($optionName, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                            <option value="Khác" <?php echo $selectedPosition === 'Khác' ? 'selected' : ''; ?>>Khác</option>
                        </select>
                        <div class="invalid-feedback">Vui lòng chọn vị trí ứng tuyển.</div>
                        <div class="position-form-preview mt-3" id="position-preview-form" aria-live="polite">
                            <div class="position-form-preview-title" id="position-form-preview-title"><?php echo htmlspecialchars($initialPositionName ?: 'Vị trí đã chọn', ENT_QUOTES, 'UTF-8'); ?></div>
                            <p class="position-form-preview-summary" id="position-form-preview-summary"><?php echo htmlspecialchars($initialPositionDetail['summary'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <ul class="position-form-preview-tasks" id="position-form-preview-tasks">
                                <?php foreach (($initialPositionDetail['tasks'] ?? []) as $task): ?>
                                    <li><?php echo htmlspecialchars($task, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="availability" class="form-label">Thời gian làm việc</label>
                        <select class="form-select" id="availability" name="availability">
                            <option value="Full-time">Full-time</option>
                            <option value="Part-time">Part-time</option>
                            <option value="Thực tập">Thực tập</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="message" class="form-label">Giới thiệu</label>
                    <textarea class="form-control" id="message" name="message" rows="4" placeholder="Giới thiệu về bạn và lý do ứng tuyển"></textarea>
                </div>

                <div class="mb-3">
                    <label for="cv_file" class="form-label">CV (PDF, DOC, DOCX)</label>
                    <input type="file" class="form-control" id="cv_file" name="cv_file" accept=".pdf,.doc,.docx">
                    <div class="form-text">Tối đa 5MB. Nếu đã nộp đơn trước đó, không cần tải lại.</div>
                </div>

                <div class="d-grid d-md-flex gap-3">
                    <button type="submit" class="btn submit-btn btn-lg">
                        <i class="bi bi-send me-2"></i>Gửi ứng tuyển
                    </button>
                </div>
            </form>

            <p class="text-muted small mt-3 mb-0">
                * Đơn sẽ được gửi đến admin để phê duyệt.
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

            var positionDetails = <?php echo json_encode($positionDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || {};
            var defaultPositionDetail = <?php echo json_encode($defaultPositionDetail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || { summary: '', tasks: [] };
            var positionSelect = document.getElementById('position');
            var positionItems = document.querySelectorAll('.position-item-selectable');

            var previewTitleTop = document.getElementById('position-preview-title');
            var previewSummaryTop = document.getElementById('position-preview-summary');
            var previewTasksTop = document.getElementById('position-preview-tasks');

            var previewTitleForm = document.getElementById('position-form-preview-title');
            var previewSummaryForm = document.getElementById('position-form-preview-summary');
            var previewTasksForm = document.getElementById('position-form-preview-tasks');
            var messageField = document.getElementById('message');

            function renderTasks(target, tasks) {
                if (!target) {
                    return;
                }

                target.innerHTML = '';
                (tasks || []).forEach(function(task) {
                    var li = document.createElement('li');
                    li.textContent = task;
                    target.appendChild(li);
                });
            }

            function getPositionDetail(name) {
                if (name && positionDetails[name]) {
                    return positionDetails[name];
                }
                return defaultPositionDetail;
            }

            function setActiveItem(positionName) {
                positionItems.forEach(function(item) {
                    var isActive = item.getAttribute('data-position-name') === positionName;
                    item.classList.toggle('position-item-active', isActive);
                });
            }

            function updatePositionPreview(positionName) {
                var detail = getPositionDetail(positionName);
                var previewTitle = positionName || 'Vị trí ứng tuyển';

                if (previewTitleTop) {
                    previewTitleTop.textContent = previewTitle;
                }
                if (previewSummaryTop) {
                    previewSummaryTop.textContent = detail.summary || '';
                }
                renderTasks(previewTasksTop, detail.tasks || []);

                if (previewTitleForm) {
                    previewTitleForm.textContent = previewTitle;
                }
                if (previewSummaryForm) {
                    previewSummaryForm.textContent = detail.summary || '';
                }
                renderTasks(previewTasksForm, detail.tasks || []);

                if (messageField) {
                    messageField.placeholder = positionName
                        ? 'Giới thiệu ngắn vì sao bạn phù hợp vị trí "' + positionName + '"'
                        : 'Giới thiệu về bạn và lý do ứng tuyển';
                }

                setActiveItem(positionName);
            }

            function syncSelect(positionName) {
                if (!positionSelect || !positionName) {
                    return;
                }

                var targetOption = Array.prototype.find.call(positionSelect.options, function(option) {
                    return option.value === positionName;
                });

                if (targetOption) {
                    positionSelect.value = positionName;
                }
            }

            var committedPosition = positionSelect ? positionSelect.value : '';
            if (!committedPosition && positionItems.length > 0) {
                committedPosition = positionItems[0].getAttribute('data-position-name') || '';
            }

            updatePositionPreview(committedPosition);
            syncSelect(committedPosition);

            positionItems.forEach(function(item) {
                var itemPositionName = item.getAttribute('data-position-name') || '';

                item.addEventListener('mouseenter', function() {
                    updatePositionPreview(itemPositionName);
                });

                item.addEventListener('mouseleave', function() {
                    updatePositionPreview(committedPosition);
                });

                item.addEventListener('focus', function() {
                    updatePositionPreview(itemPositionName);
                });

                item.addEventListener('blur', function() {
                    updatePositionPreview(committedPosition);
                });

                item.addEventListener('click', function() {
                    committedPosition = itemPositionName;
                    syncSelect(committedPosition);
                    updatePositionPreview(committedPosition);
                });

                item.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        committedPosition = itemPositionName;
                        syncSelect(committedPosition);
                        updatePositionPreview(committedPosition);
                    }
                });
            });

            if (positionSelect) {
                positionSelect.addEventListener('change', function() {
                    committedPosition = positionSelect.value;
                    updatePositionPreview(committedPosition);
                });
            }
        }, false);
    })();
</script>
