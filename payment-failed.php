<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

$message = trim((string)($_GET['message'] ?? ''));
if ($message === '') {
    $message = 'Quyên góp không thành công';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giao dịch thất bại - Goodwill Vietnam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #ffe3e3 0%, #fff1f1 45%, #fff9f9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .failed-card {
            width: 100%;
            max-width: 640px;
            border: 1px solid #ffd1d1;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 18px 36px rgba(180, 35, 24, 0.12);
            overflow: hidden;
        }

        .failed-header {
            padding: 28px 26px 18px;
            text-align: center;
            background: linear-gradient(180deg, #fff0f0 0%, #ffffff 100%);
        }

        .failed-icon {
            font-size: 66px;
            line-height: 1;
            color: #dc3545;
            margin-bottom: 10px;
        }

        .failed-title {
            margin: 0;
            color: #b02a37;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .failed-body {
            padding: 0 26px 30px;
            text-align: center;
        }

        .failed-subtitle {
            font-size: 1.12rem;
            color: #6c1f29;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .failed-message {
            margin: 0 auto 26px;
            max-width: 520px;
            color: #6b7280;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-main {
            min-width: 180px;
            border-radius: 999px;
            padding: 10px 18px;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="failed-card">
        <div class="failed-header">
            <div class="failed-icon" aria-hidden="true">
                <i class="bi bi-emoji-frown-fill"></i>
            </div>
            <h1 class="failed-title">Giao dịch thất bại</h1>
        </div>
        <div class="failed-body">
            <p class="failed-subtitle">Quyên góp không thành công</p>
            <p class="failed-message"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="action-buttons">
                <a href="index.php" class="btn btn-danger btn-main">
                    <i class="bi bi-house-door me-1"></i>Về trang chủ
                </a>
                <a href="donate.php" class="btn btn-outline-danger btn-main">
                    <i class="bi bi-heart me-1"></i>Trang quyên góp
                </a>
            </div>
        </div>
    </div>
</body>
</html>
