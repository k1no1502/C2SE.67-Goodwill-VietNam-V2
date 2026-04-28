<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/moderation.php';

$pageTitle = "Gửi phản hồi";
$success = '';
$error = '';

$defaultName = '';
$defaultEmail = '';
$userFeedback = [];

if (isLoggedIn()) {
    $user = Database::fetch("SELECT name, email FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
    if ($user) {
        $defaultName = $user['name'] ?? '';
        $defaultEmail = $user['email'] ?? '';
    }
    $userFeedback = Database::fetchAll(
        "SELECT fb_id, subject, content, admin_reply, status, created_at, replied_at 
         FROM feedback 
         WHERE user_id = ? 
         ORDER BY created_at DESC 
         LIMIT 50",
        [$_SESSION['user_id']]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? $defaultName);
    $email = sanitize($_POST['email'] ?? $defaultEmail);
    $subject = sanitize($_POST['subject'] ?? '');
    $content = sanitize($_POST['content'] ?? '');
    $rating = (int)($_POST['rating'] ?? 0);

    if ($name === '' || $email === '' || $subject === '' || $content === '') {
        $error = 'Vui lòng điền đầy đủ các trường bắt buộc.';
    } elseif (!validateEmail($email)) {
        $error = 'Email không hợp lệ.';
    } elseif ($rating !== 0 && ($rating < 1 || $rating > 5)) {
        $error = 'Đánh giá phải từ 1 đến 5 sao.';
    }

    // === AI TEXT MODERATION ===
    if (empty($error)) {
        $toxicWord = checkToxicTextLocal($subject . ' ' . $content);
        if ($toxicWord !== null) {
            $error = renderModerationError('Từ bị cấm: ' . htmlspecialchars($toxicWord), 'Phản hồi bị từ chối');
        } else {
            $geminiCheck = checkToxicTextGemini($subject . ' ' . $content);
            if ($geminiCheck['violate']) {
                $error = renderModerationError($geminiCheck['reason'], 'Phản hồi bị từ chối');
            }
        }
    }

    // === AI IMAGE MODERATION & UPLOAD ===
    $uploadedImages = [];
    if (empty($error) && !empty($_FILES['feedback_images']['name'][0])) {
        $files = $_FILES['feedback_images'];
        $fileCount = count($files['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $tmpPath = $files['tmp_name'][$i];
                $imgCheck = checkToxicImageGemini($tmpPath);
                if ($imgCheck['violate']) {
                    $error = renderModerationError($imgCheck['reason'], 'Phản hồi bị từ chối');
                    break;
                }
                
                $fileArr = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                $res = uploadFile($fileArr);
                if ($res['success']) {
                    $uploadedImages[] = '/uploads/' . $res['filename'];
                } else {
                    $error = $res['message'];
                    break;
                }
            }
        }
    }

    if (empty($error)) {
        try {
            $imageUrlsStr = !empty($uploadedImages) ? implode(',', $uploadedImages) : null;
            Database::execute(
                "INSERT INTO feedback (user_id, name, email, subject, content, rating, status, image_urls, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())",
                [
                    isLoggedIn() ? $_SESSION['user_id'] : null,
                    $name,
                    $email,
                    $subject,
                    $content,
                    $rating > 0 ? $rating : null,
                    $imageUrlsStr
                ]
            );

            $success = 'Cảm ơn bạn đã gửi phản hồi! Chúng tôi sẽ liên hệ lại trong thời gian sớm nhất.';
            $_POST = [];
        } catch (Exception $e) {
            $error = 'Không thể gửi phản hồi lúc này. Vui lòng thử lại sau.';
        }
    }
}

include 'includes/header.php';
?>

<!-- Hero Banner -->
<div class="py-5 text-white" style="background-color: #177385;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-2 text-center text-md-end mb-4 mb-md-0">
                <div class="d-inline-block rounded-4 p-4" style="background-color: rgba(255,255,255,0.15);">
                    <i class="bi bi-chat-left-text" style="font-size: 4rem;"></i>
                </div>
            </div>
            <div class="col-md-10 text-center text-md-start">
                <h1 class="display-4 fw-bold mb-2">Gửi phản hồi</h1>
                <p class="lead mb-0">Chia sẻ ý kiến của bạn để giúp chúng tôi phục vụ cộng đồng tốt hơn</p>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm border-0 mb-4" style="border-top: 4px solid #177385 !important;">
                <div class="card-header bg-white border-0 py-3">
                    <h4 class="mb-0" style="color: #177385;"><i class="bi bi-pencil-square me-2"></i>Nội dung phản hồi</h4>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <?php if (strpos($error, 'alert-heading') !== false): ?>
                            <?php echo $error; ?>
                        <?php else: ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form method="POST" class="row g-3" enctype="multipart/form-data">
                        <div class="col-md-6">
                            <label class="form-label">Họ và tên *</label>
                            <input type="text"
                                   class="form-control"
                                   name="name"
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? $defaultName); ?>"
                                   required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email"
                                   class="form-control"
                                   name="email"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? $defaultEmail); ?>"
                                   required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tiêu đề *</label>
                            <input type="text"
                                   class="form-control"
                                   name="subject"
                                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                                   required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nội dung *</label>
                            <textarea class="form-control"
                                      name="content"
                                      rows="5"
                                      required><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Đánh giá (tùy chọn)</label>
                            <select class="form-select" name="rating">
                                <option value="0">Chọn số sao</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ((int)($_POST['rating'] ?? 0) === $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> sao
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Hình ảnh đính kèm (tùy chọn)</label>
                            <input type="file" class="form-control" name="feedback_images[]" multiple accept="image/*">
                        </div>
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn text-white btn-lg px-4" style="background-color: #177385;">
                                <i class="bi bi-send me-2"></i>Gửi phản hồi
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="mb-3">Chúng tôi luôn lắng nghe</h5>
                    <p class="text-muted mb-0">
                        Phản hồi của bạn giúp Goodwill Vietnam cải thiện trải nghiệm và phục vụ cộng đồng tốt hơn.
                        Nếu cần hỗ trợ khẩn cấp, bạn có thể liên hệ qua email <strong>support@goodwill.vn</strong>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isLoggedIn()): ?>
<div class="container pb-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-white border-0 d-flex align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Lịch sử phản hồi của bạn</h5>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($userFeedback)): ?>
                        <div class="text-muted">Bạn chưa gửi phản hồi nào.</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($userFeedback as $fb): ?>
                                <?php
                                $statusMeta = [
                                    'pending' => ['class' => 'warning', 'text' => 'Chờ xử lý'],
                                    'read' => ['class' => 'info', 'text' => 'Đã đọc'],
                                    'replied' => ['class' => 'success', 'text' => 'Đã phản hồi'],
                                ];
                                $st = $statusMeta[$fb['status']] ?? ['class' => 'secondary', 'text' => ucfirst((string)$fb['status'])];
                                ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($fb['subject']); ?></div>
                                            <div class="text-muted small">Gửi lúc <?php echo formatDate($fb['created_at']); ?></div>
                                        </div>
                                        <span class="badge bg-<?php echo $st['class']; ?>"><?php echo $st['text']; ?></span>
                                    </div>
                                    <div class="mt-2">
                                        <div class="text-muted">Nội dung:</div>
                                        <div><?php echo nl2br(htmlspecialchars($fb['content'])); ?></div>
                                    </div>
                                    <?php if (!empty($fb['admin_reply'])): ?>
                                        <div class="mt-3 p-3 bg-light border rounded">
                                            <div class="d-flex justify-content-between">
                                                <div class="fw-semibold"><i class="bi bi-reply-fill me-1"></i>Phản hồi từ Admin</div>
                                                <?php if (!empty($fb['replied_at'])): ?>
                                                    <div class="text-muted small"><?php echo formatDate($fb['replied_at']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mt-2"><?php echo nl2br(htmlspecialchars($fb['admin_reply'])); ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="container pb-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-lock-fill text-secondary fs-4 me-3"></i>
                        <div>
                            <div class="fw-semibold">Đăng nhập để xem lịch sử phản hồi của bạn</div>
                            <div class="text-muted">Sau khi đăng nhập, bạn sẽ xem được phản hồi từ hệ thống và admin dành cho tài khoản của mình.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
