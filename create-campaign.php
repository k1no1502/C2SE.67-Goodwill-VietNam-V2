<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin();

$success = '';
$error = '';

// Get categories
$categories = Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order, name");

$pageTitle = "Tạo chiến dịch";

function extractYoutubeVideoId($input) {
    $input = trim((string)$input);
    if ($input === '') {
        return '';
    }

    // Accept already-normalized IDs.
    if (preg_match('/^[a-zA-Z0-9_-]{6,}$/', $input) && stripos($input, 'http') !== 0) {
        return $input;
    }

    $parts = @parse_url($input);
    if (!$parts || empty($parts['host'])) {
        return '';
    }

    $host = strtolower($parts['host']);
    $path = $parts['path'] ?? '';

    if (strpos($host, 'youtu.be') !== false) {
        $id = trim($path, '/');
        return preg_match('/^[a-zA-Z0-9_-]{6,}$/', $id) ? $id : '';
    }

    if (strpos($host, 'youtube.com') !== false || strpos($host, 'youtube-nocookie.com') !== false) {
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (!empty($query['v']) && preg_match('/^[a-zA-Z0-9_-]{6,}$/', $query['v'])) {
                return $query['v'];
            }
        }

        if (preg_match('#/(?:embed|shorts|live|reel|reels)/([a-zA-Z0-9_-]{6,})#i', $path, $matches)) {
            return $matches[1];
        }
    }

    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    
    // Validate
    if (empty($name) || empty($description) || empty($start_date) || empty($end_date)) {
        $error = 'Vui lòng điền đầy đủ thông tin bắt buộc.';
    } elseif (strtotime($start_date) < time()) {
        $error = 'Ngày bắt đầu phải từ hôm nay trở đi.';
    } elseif (strtotime($end_date) <= strtotime($start_date)) {
        $error = 'Ngày kết thúc phải sau ngày bắt đầu.';
    } else {
        try {
            Database::beginTransaction();
            
            // Handle image upload
            $imagePath = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/campaigns/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $uploadResult = uploadFile($_FILES['image'], $uploadDir);
                if ($uploadResult['success']) {
                    $imagePath = $uploadResult['filename'];
                }
            }
            
            // Handle multiple video types
            $videoPath = '';
            $youtubeLink = '';
            $facebookLink = '';
            $tiktokLink = '';
            $videoType = 'none';
            
            $uploadEnabled = isset($_POST['video_upload_enabled']) && $_POST['video_upload_enabled'] === '1';
            $youtubeEnabled = isset($_POST['video_youtube_enabled']) && $_POST['video_youtube_enabled'] === '1';
            $facebookEnabled = isset($_POST['video_facebook_enabled']) && $_POST['video_facebook_enabled'] === '1';
            $tiktokEnabled = isset($_POST['video_tiktok_enabled']) && $_POST['video_tiktok_enabled'] === '1';
            
            // Count enabled types
            $enabledCount = ($uploadEnabled ? 1 : 0) + ($youtubeEnabled ? 1 : 0) + ($facebookEnabled ? 1 : 0) + ($tiktokEnabled ? 1 : 0);
            
            // Handle upload video
            if ($uploadEnabled) {
                if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = 'uploads/campaigns/videos/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $uploadResult = uploadFile($_FILES['video'], $uploadDir, ['mp4', 'avi', 'mov', 'webm', 'mkv', 'flv']);
                    if ($uploadResult['success']) {
                        $videoPath = $uploadResult['filename'];
                    } else {
                        throw new Exception('Lỗi upload video: ' . ($uploadResult['message'] ?? 'Unknown error'));
                    }
                } else {
                    throw new Exception('Vui lòng chọn tệp video để upload.');
                }
            }
            
            // Handle YouTube link
            if ($youtubeEnabled) {
                $youtubeLink = trim(sanitize($_POST['youtube_link'] ?? ''));
                if (empty($youtubeLink)) {
                    throw new Exception('Vui lòng nhập đường link YouTube.');
                }
                
                // Support regular YouTube videos, Shorts/Reels, and livestream links.
                $youtubeId = extractYoutubeVideoId($youtubeLink);
                if ($youtubeId !== '') {
                    $youtubeLink = $youtubeId;
                } else {
                    throw new Exception('Đường link YouTube không hợp lệ. Hỗ trợ video thường, Shorts/Reels và livestream.');
                }
            }
            
            // Handle Facebook livestream link
            if ($facebookEnabled) {
                $facebookLink = trim(sanitize($_POST['facebook_live_link'] ?? ''));
                if (empty($facebookLink)) {
                    throw new Exception('Vui lòng nhập đường link Facebook livestream.');
                }
                
                // Validate Facebook URL
                if (!preg_match('/(?:facebook\.com|fb\.watch)/', $facebookLink)) {
                    throw new Exception('Đường link Facebook không hợp lệ. Vui lòng kiểm tra lại.');
                }
            }
            
            // Handle TikTok video link
            if ($tiktokEnabled) {
                $tiktokLink = trim(sanitize($_POST['tiktok_video_link'] ?? ''));
                if (empty($tiktokLink)) {
                    throw new Exception('Vui lòng nhập đường link TikTok.');
                }
                
                // Validate TikTok URL (video or livestream)
                if (!preg_match('/(?:tiktok\.com|vt\.tiktok\.com|vm\.tiktok\.com)/', $tiktokLink)) {
                    throw new Exception('Đường link TikTok không hợp lệ. Vui lòng dùng link video hoặc livestream TikTok.');
                }
            }
            
            // Determine video_type
            if ($enabledCount > 1) {
                $videoType = 'multi';
            } elseif ($uploadEnabled) {
                $videoType = 'upload';
            } elseif ($youtubeEnabled) {
                $videoType = 'youtube';
            } elseif ($facebookEnabled) {
                $videoType = 'facebook';
            } elseif ($tiktokEnabled) {
                $videoType = 'tiktok';
            }
            
            // Insert campaign with pending status for admin approval
            $sql = "INSERT INTO campaigns (name, description, image, video_type, video_file, video_youtube, video_facebook, video_tiktok,
                    start_date, end_date, target_items, status, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'pending', ?, NOW())";
            Database::execute($sql, [
                $name,
                $description,
                $imagePath,
                $videoType,
                $videoPath,
                $youtubeLink,
                $facebookLink,
                $tiktokLink,
                $start_date,
                $end_date,
                $_SESSION['user_id']
            ]);
            
            $campaign_id = Database::lastInsertId();
            
            // Insert campaign items
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item) {
                    if (!empty($item['name']) && !empty($item['quantity'])) {
                        $sql = "INSERT INTO campaign_items (campaign_id, item_name, category_id, quantity_needed, description) 
                                VALUES (?, ?, ?, ?, ?)";
                        Database::execute($sql, [
                            $campaign_id,
                            sanitize($item['name']),
                            (int)($item['category'] ?? 0),
                            (int)$item['quantity'],
                            sanitize($item['description'] ?? '')
                        ]);
                    }
                }
            }
            
            Database::commit();
            
            $success = 'Chiến dịch đã được tạo thành công và đang chờ phê duyệt từ quản trị viên.';
            
            // Clear form
            $_POST = [];
            
        } catch (Exception $e) {
            Database::rollback();
            error_log("Create campaign error: " . $e->getMessage());
            $error = 'Có lỗi xảy ra khi tạo chiến dịch. Vui lòng thử lại.';
        }
    }
}

include 'includes/header.php';
?>

<style>
    .cc-page {
        background: radial-gradient(circle at 8% 8%, rgba(14,116,144,0.12), transparent 30%), #f6fbfd;
        min-height: 100vh;
    }
    .cc-hero {
        background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
        color: #fff;
        padding: 56px 0 40px;
    }
    .cc-hero h1 { font-size: 2rem; font-weight: 800; }
    .cc-hero p  { opacity: .85; font-size: 1.05rem; }

    .cc-sidebar .sidebar-box {
        background: #fff;
        border: 1px solid #cde8f0;
        border-radius: 16px;
        padding: 1.6rem;
        box-shadow: 0 6px 20px rgba(14,116,144,0.08);
    }
    .cc-sidebar .step-num {
        width: 32px; height: 32px;
        background: linear-gradient(135deg, #0e7490, #155e75);
        color: #fff; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: .85rem; flex-shrink: 0;
    }

    .cc-card {
        border: 1px solid #cde8f0;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(14,116,144,0.10);
        background: #fff;
    }
    .cc-card-header {
        background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
        color: #fff;
        padding: 1.6rem 2rem;
    }
    .cc-card-header h2 { font-size: 1.45rem; font-weight: 800; margin: 0; }
    .cc-card-header p  { margin: .35rem 0 0; opacity: .88; font-size: .95rem; }

    .cc-section-title {
        font-size: 1rem; font-weight: 700; color: #0e7490;
        border-left: 4px solid #0e7490;
        padding-left: .75rem;
        margin-bottom: 1.2rem;
    }

    .item-row {
        background: #f3fbfe;
        border: 1px solid #cde8f0 !important;
        border-radius: 12px !important;
        transition: box-shadow .2s;
    }
    .item-row:hover { box-shadow: 0 4px 14px rgba(14,116,144,0.10); }

    .btn-add-item {
        border: 2px dashed #0e7490;
        color: #0e7490;
        background: transparent;
        border-radius: 10px;
        font-weight: 600;
        transition: all .2s;
    }
    .btn-add-item:hover {
        background: #0e7490;
        color: #fff;
        border-style: solid;
    }

    .btn-submit-cc {
        background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
        border: none;
        color: #fff;
        font-weight: 700;
        font-size: 1.1rem;
        border-radius: 12px;
        padding: .85rem 2rem;
        box-shadow: 0 4px 18px rgba(14,116,144,0.25);
        transition: filter .2s;
    }
    .btn-submit-cc:hover { filter: brightness(.92); color: #fff; }

    .video-tab-group .btn-check:checked + .btn {
        background: linear-gradient(135deg, #0e7490, #155e75);
        color: #fff;
        border-color: #0e7490;
    }
    .video-tab-group .btn {
        border-color: #9fc8d4;
        color: #0e7490;
        font-weight: 600;
    }
    .video-tab-group .btn:hover:not(:has(input:checked)) {
        border-color: #0e7490;
        background: #f0f9fb;
    }

    .form-control:focus, .form-select:focus {
        border-color: #0e7490;
        box-shadow: 0 0 0 .18rem rgba(14,116,144,.2);
    }
    .form-label { font-weight: 600; font-size: .9rem; color: #1e5a6a; }

    .alert-note {
        background: #edf8fb;
        border: 1px solid #b6dfe9;
        border-radius: 10px;
        color: #155e75;
        font-size: .88rem;
    }
</style>

<div class="cc-page pb-5">
    <!-- Hero -->
    <div class="cc-hero mb-4">
        <div class="container">
            <a href="campaigns.php" class="btn btn-outline-light btn-sm mb-3 rounded-pill">
                <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
            </a>
            <h1><i class="bi bi-megaphone-fill me-2"></i>Tạo chiến dịch mới</h1>
            <p>Khởi tạo chiến dịch thiện nguyện để kêu gọi sự hỗ trợ từ cộng đồng</p>
        </div>
    </div>

    <div class="container">
        <div class="row g-4">
            <!-- Sidebar -->
            <div class="col-xl-3 col-lg-4 cc-sidebar">
                <div class="sidebar-box mb-4">
                    <h6 class="fw-bold mb-3" style="color:#0e7490;">
                        <i class="bi bi-lightbulb-fill me-2"></i>Hướng dẫn tạo chiến dịch
                    </h6>
                    <div class="d-flex gap-3 mb-3">
                        <div class="step-num">1</div>
                        <div>
                            <div class="fw-semibold" style="font-size:.9rem;">Thông tin cơ bản</div>
                            <div class="text-muted" style="font-size:.82rem;">Đặt tên, mô tả rõ ràng về mục tiêu chiến dịch</div>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-3">
                        <div class="step-num">2</div>
                        <div>
                            <div class="fw-semibold" style="font-size:.9rem;">Hình ảnh & Video</div>
                            <div class="text-muted" style="font-size:.82rem;">Thêm ảnh đại diện hoặc video giới thiệu hấp dẫn</div>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-3">
                        <div class="step-num">3</div>
                        <div>
                            <div class="fw-semibold" style="font-size:.9rem;">Thời gian</div>
                            <div class="text-muted" style="font-size:.82rem;">Xác định ngày bắt đầu và kết thúc chiến dịch</div>
                        </div>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="step-num">4</div>
                        <div>
                            <div class="fw-semibold" style="font-size:.9rem;">Vật phẩm cần thiết</div>
                            <div class="text-muted" style="font-size:.82rem;">Liệt kê và số lượng vật phẩm cần quyên góp</div>
                        </div>
                    </div>
                </div>

                <div class="sidebar-box">
                    <h6 class="fw-bold mb-3" style="color:#0e7490;">
                        <i class="bi bi-info-circle-fill me-2"></i>Lưu ý quan trọng
                    </h6>
                    <ul class="ps-3 mb-0 text-muted" style="font-size:.85rem; line-height:1.8;">
                        <li>Chiến dịch sẽ chờ quản trị viên <strong>phê duyệt</strong> trước khi công khai</li>
                        <li>Tên chiến dịch nên rõ ràng và dễ nhớ</li>
                        <li>Hình ảnh nên có tỷ lệ 16:9 để hiển thị đẹp</li>
                        <li>Video YouTube giúp tăng độ tin cậy</li>
                    </ul>
                </div>
            </div>

            <!-- Main Form -->
            <div class="col-xl-9 col-lg-8">
                <div class="cc-card">
                    <div class="cc-card-header">
                        <h2><i class="bi bi-plus-circle-fill me-2"></i>Chi tiết chiến dịch</h2>
                        <p>Điền đầy đủ thông tin bên dưới để tạo chiến dịch mới</p>
                    </div>

                    <div class="p-4">
                    <?php if ($success): ?>
                        <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
                            <i class="bi bi-check-circle-fill fs-5"></i>
                            <span><?php echo htmlspecialchars($success); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                            <span><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <!-- Basic Information -->
                        <div class="mb-4">
                            <div class="cc-section-title"><i class="bi bi-pencil-fill me-2"></i>Thông tin cơ bản</div>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Tên chiến dịch *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="name" 
                                       name="name" 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                       placeholder="Ví dụ: Hỗ trợ trẻ em vùng cao"
                                       required>
                                <div class="invalid-feedback">
                                    Vui lòng nhập tên chiến dịch.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Mô tả chi tiết *</label>
                                <textarea class="form-control" 
                                          id="description" 
                                          name="description" 
                                          rows="4" 
                                          placeholder="Mô tả mục đích, đối tượng hưởng lợi, và cách thức thực hiện chiến dịch..."
                                          required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                <div class="invalid-feedback">
                                    Vui lòng nhập mô tả chi tiết.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="image" class="form-label">Hình ảnh chiến dịch</label>
                                <input type="file" 
                                       class="form-control" 
                                       id="image" 
                                       name="image" 
                                       accept="image/*">
                                <div class="form-text">Chọn hình ảnh đại diện cho chiến dịch (JPG, PNG, GIF)</div>
                            </div>

                            <!-- Video Section -->
                            <div class="mb-3">
                                <label class="form-label">Video chiến dịch (có thể chọn nhiều)</label>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check p-3 border rounded" style="background-color: #f8fbfd;">
                                            <input class="form-check-input video-checkbox" type="checkbox" name="video_upload_enabled" id="video_upload" value="1">
                                            <label class="form-check-label" for="video_upload">
                                                <i class="bi bi-upload me-2"></i><strong>Upload video</strong>
                                                <small class="d-block text-muted mt-1">MP4, AVI, MOV, WebM, MKV, FLV (tối đa 500MB)</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check p-3 border rounded" style="background-color: #f8fbfd;">
                                            <input class="form-check-input video-checkbox" type="checkbox" name="video_youtube_enabled" id="video_youtube" value="1">
                                            <label class="form-check-label" for="video_youtube">
                                                <i class="bi bi-youtube me-2"></i><strong>YouTube</strong>
                                                <small class="d-block text-muted mt-1">Nhúng video từ YouTube</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check p-3 border rounded" style="background-color: #f8fbfd;">
                                            <input class="form-check-input video-checkbox" type="checkbox" name="video_facebook_enabled" id="video_facebook" value="1">
                                            <label class="form-check-label" for="video_facebook">
                                                <i class="bi bi-facebook me-2"></i><strong>Facebook Livestream</strong>
                                                <small class="d-block text-muted mt-1">Link livestream từ Facebook</small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check p-3 border rounded" style="background-color: #f8fbfd;">
                                            <input class="form-check-input video-checkbox" type="checkbox" name="video_tiktok_enabled" id="video_tiktok" value="1">
                                            <label class="form-check-label" for="video_tiktok">
                                                <i class="bi bi-play-circle me-2"></i><strong>TikTok</strong>
                                                <small class="d-block text-muted mt-1">Video TikTok</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Upload Video Input -->
                                <div id="video_upload_section" class="mb-3" style="display: none;">
                                    <label class="form-label">Chọn file video</label>
                                    <input type="file" class="form-control" id="video_file" name="video" 
                                           accept="video/*" data-max-size="524288000">
                                    <div class="form-text">Tải lên tệp video từ máy tính của bạn</div>
                                </div>

                                <!-- YouTube Link Input -->
                                <div id="video_youtube_section" class="mb-3" style="display: none;">
                                    <label class="form-label">Đường link YouTube</label>
                                    <input type="text" class="form-control" id="youtube_link" name="youtube_link" 
                                           placeholder="Ví dụ: https://www.youtube.com/watch?v=..., /shorts/... hoặc /live/...">
                                    <div class="form-text">Hỗ trợ video thường, Shorts/Reels và livestream YouTube.</div>
                                </div>

                                <!-- Facebook Livestream Input -->
                                <div id="video_facebook_section" class="mb-3" style="display: none;">
                                    <label class="form-label">Đường link Facebook Livestream</label>
                                    <input type="text" class="form-control" id="facebook_live_link" name="facebook_live_link" 
                                           placeholder="Ví dụ: https://www.facebook.com/...">
                                    <div class="form-text">Nhập đường link Facebook livestream</div>
                                </div>

                                <!-- TikTok Video Link Input -->
                                <div id="video_tiktok_section" class="mb-3" style="display: none;">
                                    <label class="form-label">Đường link TikTok</label>
                                    <input type="text" class="form-control" id="tiktok_video_link" name="tiktok_video_link" 
                                           placeholder="Ví dụ: https://www.tiktok.com/@username/video/... hoặc /live hoặc https://vt.tiktok.com/...">
                                    <div class="form-text">Hỗ trợ video TikTok thường và TikTok livestream.</div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4" style="border-color:#cde8f0;">

                        <!-- Campaign Details -->
                        <div class="mb-4">
                            <div class="cc-section-title"><i class="bi bi-calendar3 me-2"></i>Thời gian chiến dịch</div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="start_date" class="form-label">Ngày bắt đầu *</label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="start_date" 
                                           name="start_date" 
                                           value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>"
                                           min="<?php echo date('Y-m-d'); ?>"
                                           required>
                                    <div class="invalid-feedback">Vui lòng chọn ngày bắt đầu.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="end_date" class="form-label">Ngày kết thúc *</label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="end_date" 
                                           name="end_date" 
                                           value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>"
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                           required>
                                    <div class="invalid-feedback">Vui lòng chọn ngày kết thúc.</div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4" style="border-color:#cde8f0;">

                        <!-- Required Items -->
                        <div class="mb-4">
                            <div class="cc-section-title"><i class="bi bi-box-seam me-2"></i>Vật phẩm cần thiết</div>
                            <p class="text-muted mb-3" style="font-size:.9rem;">Liệt kê các vật phẩm cụ thể mà chiến dịch cần quyên góp</p>
                            
                            <div id="items-container">
                                <div class="item-row mb-3 p-3 border rounded">
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <label class="form-label">Tên vật phẩm</label>
                                            <input type="text" class="form-control" name="items[0][name]" 
                                                   placeholder="Ví dụ: Áo ấm">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Danh mục</label>
                                            <select class="form-select" name="items[0][category]">
                                                <option value="">Chọn danh mục</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['category_id']; ?>">
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Số lượng</label>
                                            <input type="number" class="form-control" name="items[0][quantity]" 
                                                   min="1" placeholder="10">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Mô tả</label>
                                            <input type="text" class="form-control" name="items[0][description]" 
                                                   placeholder="Mô tả (tùy chọn)">
                                        </div>
                                    </div>
                                    <div class="mt-2 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-item">
                                            <i class="bi bi-trash me-1"></i>Xóa
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-add-item px-4 py-2 mt-1" id="add-item">
                                <i class="bi bi-plus-circle me-2"></i>Thêm vật phẩm
                            </button>
                        </div>

                        <hr class="my-4" style="border-color:#cde8f0;">

                        <div class="d-grid">
                            <button type="submit" class="btn btn-submit-cc">
                                <i class="bi bi-send-fill me-2"></i>Gửi yêu cầu tạo chiến dịch
                            </button>
                            <p class="text-center text-muted mt-2 mb-0" style="font-size:.84rem;">
                                <i class="bi bi-shield-check me-1"></i>Chiến dịch sẽ được quản trị viên xét duyệt trước khi công khai
                            </p>
                        </div>
                    </form>
                    </div><!-- end p-4 -->
                </div><!-- end cc-card -->
            </div><!-- end col main -->
        </div><!-- end row -->
    </div><!-- end container -->
</div><!-- end cc-page -->

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

// Add/Remove items
let itemIndex = 1;

const categoryOptions = `<?php foreach ($categories as $category): ?><option value="<?php echo $category['category_id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option><?php endforeach; ?>`;

document.getElementById('add-item').addEventListener('click', function() {
    const container = document.getElementById('items-container');
    const newItem = document.createElement('div');
    newItem.className = 'item-row mb-3 p-3 border rounded';
    newItem.innerHTML = `
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label">Tên vật phẩm</label>
                <input type="text" class="form-control" name="items[${itemIndex}][name]" placeholder="Ví dụ: Áo ấm">
            </div>
            <div class="col-md-3">
                <label class="form-label">Danh mục</label>
                <select class="form-select" name="items[${itemIndex}][category]">
                    <option value="">Chọn danh mục</option>
                    ${categoryOptions}
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Số lượng</label>
                <input type="number" class="form-control" name="items[${itemIndex}][quantity]" min="1" placeholder="10">
            </div>
            <div class="col-md-3">
                <label class="form-label">Mô tả</label>
                <input type="text" class="form-control" name="items[${itemIndex}][description]" placeholder="Mô tả (tùy chọn)">
            </div>
        </div>
        <div class="mt-2 text-end">
            <button type="button" class="btn btn-sm btn-outline-danger remove-item">
                <i class="bi bi-trash me-1"></i>Xóa
            </button>
        </div>
    `;
    
    container.appendChild(newItem);
    itemIndex++;
});

document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-item')) {
        e.target.closest('.item-row').remove();
    }
});

// Video type handling with checkboxes
function updateVideoSections() {
    const uploadChecked = document.getElementById('video_upload').checked;
    const youtubeChecked = document.getElementById('video_youtube').checked;
    const facebookChecked = document.getElementById('video_facebook').checked;
    const tiktokChecked = document.getElementById('video_tiktok').checked;
    
    document.getElementById('video_upload_section').style.display = uploadChecked ? 'block' : 'none';
    document.getElementById('video_youtube_section').style.display = youtubeChecked ? 'block' : 'none';
    document.getElementById('video_facebook_section').style.display = facebookChecked ? 'block' : 'none';
    document.getElementById('video_tiktok_section').style.display = tiktokChecked ? 'block' : 'none';
    
    // Clear inputs when unchecked
    if (!uploadChecked) {
        document.getElementById('video_file').value = '';
    }
    if (!youtubeChecked) {
        document.getElementById('youtube_link').value = '';
    }
    if (!facebookChecked) {
        document.getElementById('facebook_live_link').value = '';
    }
    if (!tiktokChecked) {
        document.getElementById('tiktok_video_link').value = '';
    }
}

// Add event listeners for video checkboxes
document.querySelectorAll('.video-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', updateVideoSections);
});

// Validate video file size on selection
document.getElementById('video_file').addEventListener('change', function() {
    const maxSize = 524288000; // 500MB
    if (this.files.length > 0) {
        const fileSize = this.files[0].size;
        if (fileSize > maxSize) {
            alert('Tệp quá lớn! Vui lòng chọn tệp nhỏ hơn 500MB.');
            this.value = '';
        }
    }
});

</script>

<?php include 'includes/footer.php'; ?>
