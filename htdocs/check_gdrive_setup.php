<?php
/**
 * Google Drive Backup Setup Verification
 * Kiểm tra cấu hình Google Drive backup
 */

header('Content-Type: text/html; charset=utf-8');

require_once 'config/database.php';

echo <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Drive Setup Check</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background-color: #f8f9fa; }
        .check-item { margin-bottom: 15px; padding: 15px; background: white; border-radius: 8px; border-left: 4px solid #ddd; }
        .check-item.ok { border-left-color: #28a745; }
        .check-item.error { border-left-color: #dc3545; }
        .check-item.warning { border-left-color: #ffc107; }
        .icon { font-size: 20px; margin-right: 10px; }
        h2 { margin-top: 30px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <h1>🔍 Kiểm tra Cấu hình Google Drive Backup</h1>
                <hr>
HTML;

// Check 1: Google config file
echo '<h2>1️⃣ Kiểm tra File Cấu hình</h2>';

$configFile = __DIR__ . '/config/google.php';
if (file_exists($configFile)) {
    echo '<div class="check-item ok">';
    echo '<span class="icon">✅</span><strong>config/google.php tồn tại</strong>';
    
    $config = require $configFile;
    $driveConfig = $config['drive'] ?? [];
    
    echo '<pre style="margin-top: 10px; font-size: 12px;">';
    echo htmlspecialchars(json_encode($driveConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo '</pre>';
    echo '</div>';
} else {
    echo '<div class="check-item error">';
    echo '<span class="icon">❌</span><strong>config/google.php KHÔNG tồn tại</strong>';
    echo '<p class="text-muted mt-2">Hệ thống cần file này để cấu hình Google Drive.</p>';
    echo '</div>';
}

// Check 2: Google Drive Enabled
echo '<h2>2️⃣ Kiểm tra Trạng thái Bật/Tắt</h2>';

if (isset($config['drive']['enabled']) && $config['drive']['enabled']) {
    echo '<div class="check-item ok">';
    echo '<span class="icon">✅</span><strong>Google Drive Backup: BẬT</strong>';
    echo '<p class="text-muted mt-2">Hình ảnh sẽ tự động backup lên Google Drive.</p>';
    echo '</div>';
} else {
    echo '<div class="check-item warning">';
    echo '<span class="icon">⚠️</span><strong>Google Drive Backup: TẮT</strong>';
    echo '<p class="text-muted mt-2">Để bật, mở config/google.php và đổi enabled => true</p>';
    echo '</div>';
}

// Check 3: Key file
echo '<h2>3️⃣ Kiểm tra File JSON Key</h2>';

$keyFile = $config['drive']['keyfile'] ?? '';
if ($keyFile && file_exists($keyFile)) {
    $fileSize = filesize($keyFile);
    echo '<div class="check-item ok">';
    echo '<span class="icon">✅</span><strong>File key tồn tại: ' . basename($keyFile) . '</strong>';
    echo '<p class="text-muted mt-2">Kích thước: ' . number_format($fileSize) . ' bytes</p>';
    echo '</div>';
} elseif ($keyFile) {
    echo '<div class="check-item error">';
    echo '<span class="icon">❌</span><strong>File key không tìm thấy</strong>';
    echo '<p class="text-muted mt-2">Đường dẫn: ' . htmlspecialchars($keyFile) . '</p>';
    echo '<p class="mt-2"><strong>Giải pháp:</strong> Tải file JSON từ Google Cloud Console và lưu vào config/google-drive-key.json</p>';
    echo '</div>';
} else {
    echo '<div class="check-item warning">';
    echo '<span class="icon">⚠️</span><strong>Chưa cấu hình file key</strong>';
    echo '<p class="text-muted mt-2">Cần điền keyfile trong config/google.php</p>';
    echo '</div>';
}

// Check 4: Folder ID
echo '<h2>4️⃣ Kiểm tra Google Drive Folder ID</h2>';

$folderId = $config['drive']['donation_folder_id'] ?? '';
if ($folderId && strlen($folderId) > 10) {
    echo '<div class="check-item ok">';
    echo '<span class="icon">✅</span><strong>Folder ID đã cấu hình</strong>';
    echo '<p class="text-muted mt-2">ID: <code>' . htmlspecialchars($folderId) . '</code></p>';
    echo '</div>';
} else {
    echo '<div class="check-item warning">';
    echo '<span class="icon">⚠️</span><strong>Chưa cấu hình Folder ID</strong>';
    echo '<p class="text-muted mt-2">Cần tạo folder trên Google Drive và lấy ID của nó.</p>';
    echo '</div>';
}

// Check 5: Google API Client
echo '<h2>5️⃣ Kiểm tra Google API Client Library</h2>';

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    if (class_exists('Google_Client')) {
        echo '<div class="check-item ok">';
        echo '<span class="icon">✅</span><strong>Google API Client đã cài đặt</strong>';
        echo '<p class="text-muted mt-2">Thư viện sẵn sàng sử dụng.</p>';
        echo '</div>';
    } else {
        echo '<div class="check-item error">';
        echo '<span class="icon">❌</span><strong>Google API Client chưa load</strong>';
        echo '<p class="text-muted mt-2">Chạy: <code>composer install</code></p>';
        echo '</div>';
    }
} else {
    echo '<div class="check-item error">';
    echo '<span class="icon">❌</span><strong>Autoloader không tìm thấy</strong>';
    echo '<p class="text-muted mt-2">Chạy: <code>composer install</code> trong thư mục gốc</p>';
    echo '</div>';
}

// Check 6: Helper functions
echo '<h2>6️⃣ Kiểm tra Helper Functions</h2>';

$functionsFile = __DIR__ . '/includes/functions.php';
if (file_exists($functionsFile)) {
    $content = file_get_contents($functionsFile);
    if (strpos($content, 'uploadFileToGoogleDrive') !== false) {
        echo '<div class="check-item ok">';
        echo '<span class="icon">✅</span><strong>uploadFileToGoogleDrive() function có sẵn</strong>';
        echo '</div>';
    } else {
        echo '<div class="check-item error">';
        echo '<span class="icon">❌</span><strong>uploadFileToGoogleDrive() function chưa cập nhật</strong>';
        echo '<p class="text-muted mt-2">Cần cập nhật includes/functions.php</p>';
        echo '</div>';
    }
} else {
    echo '<div class="check-item error">';
    echo '<span class="icon">❌</span><strong>includes/functions.php không tìm thấy</strong>';
    echo '</div>';
}

// Check 7: Upload directory
echo '<h2>7️⃣ Kiểm tra Thư mục Upload Cục bộ</h2>';

$uploadDir = __DIR__ . '/uploads/donations/';
if (is_dir($uploadDir)) {
    $permissions = substr(sprintf('%o', fileperms($uploadDir)), -4);
    echo '<div class="check-item ok">';
    echo '<span class="icon">✅</span><strong>uploads/donations/ tồn tại</strong>';
    echo '<p class="text-muted mt-2">Quyền: ' . $permissions . '</p>';
    echo '</div>';
} else {
    echo '<div class="check-item warning">';
    echo '<span class="icon">⚠️</span><strong>uploads/donations/ chưa tạo</strong>';
    echo '<p class="text-muted mt-2">Sẽ tự động tạo khi upload hình ảnh đầu tiên.</p>';
    echo '</div>';
}

// Summary
echo '<h2>📋 Tóm tắt</h2>';
echo '<div class="alert alert-info">';
echo '<strong>✅ Chuẩn bị hoàn tất khi:</strong><br>';
echo '1. Google Drive Backup: <strong>BẬT</strong><br>';
echo '2. File JSON key tồn tại<br>';
echo '3. Folder ID cấu hình<br>';
echo '4. Google API Client cài đặt<br>';
echo '5. Helper functions có sẵn<br>';
echo '</div>';

echo '<hr>';
echo '<p class="text-muted">📖 Xem hướng dẫn chi tiết: <a href="GOOGLE_DRIVE_SETUP.md" target="_blank">GOOGLE_DRIVE_SETUP.md</a></p>';

echo <<<HTML
            </div>
        </div>
    </div>
</body>
</html>
HTML;
?>
