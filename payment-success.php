<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

$message = trim((string)($_GET['message'] ?? ''));
if ($message === '') {
    $message = 'Quyên góp thành công';
}

$method = strtolower(trim((string)($_GET['method'] ?? '')));
$methodLabel = match ($method) {
    'momo' => 'MoMo',
    'bank_transfer' => 'Chuyển khoản',
    'zalopay' => 'ZaloPay',
    default => 'Thanh toán',
};

$transId = (int)($_GET['trans_id'] ?? 0);
$campaignId = (int)($_GET['campaign_id'] ?? 0);
$campaignName = trim((string)($_GET['campaign_name'] ?? ''));
$returnTo = trim((string)($_GET['return_to'] ?? ''));
$returnLabel = trim((string)($_GET['return_label'] ?? ''));

$isCampaignDonation = $campaignId > 0 || $campaignName !== '';
if ($campaignName === '' && $campaignId > 0) {
    $campaignName = 'Chiến dịch #' . $campaignId;
}

$subtitle = 'Cảm ơn bạn đã quyên góp cho GoodWill Việt Nam';
if ($isCampaignDonation) {
    $subtitle = 'Cảm ơn bạn đã quyên góp cho chiến dịch ' . $campaignName;
}

if ($message === 'Quyên góp thành công') {
    if ($isCampaignDonation) {
        $message = 'Thanh toán ' . $methodLabel . ' thành công! Cảm ơn bạn đã quyên góp cho chiến dịch ' . $campaignName . '.';
    } else {
        $message = 'Thanh toán ' . $methodLabel . ' thành công! Cảm ơn bạn đã quyên góp cho GoodWill Việt Nam.';
    }
}

$secondaryUrl = 'donate.php';
$secondaryLabel = 'Trang quyên góp';
if ($isCampaignDonation) {
    $secondaryUrl = 'donate-to-campaign.php' . ($campaignId > 0 ? '?campaign_id=' . $campaignId : '');
    $secondaryLabel = 'Trang quyên góp chiến dịch';
}

if ($returnTo !== '' && !preg_match('#^https?://#i', $returnTo)) {
    $secondaryUrl = $returnTo;
}
if ($returnLabel !== '') {
    $secondaryLabel = $returnLabel;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giao dịch thành công - Goodwill Vietnam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --brand-900: #0f5f76;
            --brand-700: #167a95;
            --brand-600: #1f8aa5;
            --brand-500: #2aa2bc;
            --brand-100: #dff4f8;
            --brand-050: #f1fbfd;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 16% 12%, rgba(42, 162, 188, 0.21) 0%, rgba(42, 162, 188, 0) 40%),
                radial-gradient(circle at 88% 86%, rgba(31, 138, 165, 0.16) 0%, rgba(31, 138, 165, 0) 44%),
                linear-gradient(150deg, #e8f8fb 0%, #f5fcfe 44%, #ffffff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .success-card {
            width: 100%;
            max-width: 700px;
            border: 1px solid #b9e3ed;
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 20px 44px rgba(16, 92, 114, 0.15);
            overflow: hidden;
        }

        .success-header {
            padding: 122px 26px 16px;
            text-align: center;
            background: linear-gradient(180deg, #edf9fc 0%, #ffffff 100%);
        }

        .thanks-effect {
            position: relative;
            display: inline-block;
            margin-bottom: 14px;
        }

        .pull-banner {
            position: absolute;
            left: 50%;
            top: -62px;
            transform: translate(-50%, 0);
            width: 246px;
            height: 52px;
            pointer-events: none;
            z-index: 4;
        }

        .pull-board {
            position: absolute;
            inset: 0;
            background: #ffffff;
            border: 2px solid #98d6e4;
            border-radius: 14px;
            color: var(--brand-700);
            font-weight: 800;
            font-size: 1.05rem;
            letter-spacing: 0.01em;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 22px rgba(14, 95, 116, 0.18);
            transform-origin: center;
            animation: boardPull 3.4s cubic-bezier(0.22, 1, 0.36, 1) infinite;
            z-index: 3;
        }

        .success-icon {
            position: relative;
            z-index: 1;
            width: 108px;
            height: 108px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 70px;
            line-height: 1;
            color: #ffffff;
            background: linear-gradient(145deg, var(--brand-500) 0%, var(--brand-700) 100%);
            box-shadow: 0 14px 30px rgba(31, 138, 165, 0.35);
            animation: smilePulse 3s cubic-bezier(0.22, 1, 0.36, 1) infinite;
        }

        .thanks-effect::before,
        .thanks-effect::after {
            content: "";
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #8ed4e3;
            opacity: 0.7;
            animation: sparkle 3s ease-in-out infinite;
        }

        .thanks-effect::before {
            left: -12px;
            top: 26px;
        }

        .thanks-effect::after {
            right: -10px;
            top: 14px;
            animation-delay: 0.7s;
        }

        .success-title {
            margin: 0;
            color: var(--brand-900);
            font-weight: 800;
            letter-spacing: 0.2px;
        }

        .success-body {
            padding: 0 26px 30px;
            text-align: center;
        }

        .success-subtitle {
            font-size: 1.1rem;
            color: var(--brand-700);
            margin-bottom: 8px;
            font-weight: 700;
        }

        .success-message {
            margin: 0 auto 18px;
            max-width: 560px;
            color: #4b5563;
        }

        .meta {
            margin: 0 auto 22px;
            max-width: 560px;
            border: 1px dashed #acdbe7;
            border-radius: 12px;
            padding: 10px 12px;
            color: var(--brand-900);
            background: var(--brand-050);
            font-size: 0.95rem;
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

        .btn-brand {
            background: linear-gradient(135deg, var(--brand-700) 0%, var(--brand-500) 100%);
            border: none;
            color: #fff;
        }

        .btn-brand:hover {
            color: #fff;
            filter: brightness(1.04);
        }

        .btn-outline-brand {
            border: 1px solid var(--brand-600);
            color: var(--brand-700);
            background: #fff;
        }

        .btn-outline-brand:hover {
            background: var(--brand-050);
            color: var(--brand-900);
            border-color: var(--brand-700);
        }

        @keyframes smilePulse {
            0%,
            100% {
                transform: translateY(0) scale(1);
                box-shadow: 0 14px 30px rgba(31, 138, 165, 0.35);
            }
            50% {
                transform: translateY(-2px) scale(1.02);
                box-shadow: 0 16px 32px rgba(31, 138, 165, 0.4);
            }
        }

        @keyframes boardPull {
            0%,
            16%,
            100% {
                transform: scaleX(0.52) translateY(0);
                opacity: 0.85;
            }
            36%,
            72% {
                transform: scaleX(1) translateY(-1px);
                opacity: 1;
            }
        }

        @keyframes sparkle {
            0%,
            100% {
                transform: scale(0.8);
                opacity: 0.3;
            }
            50% {
                transform: scale(1.3);
                opacity: 0.9;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .success-icon,
            .pull-board,
            .thanks-effect::before,
            .thanks-effect::after {
                animation: none !important;
            }
        }

        @media (max-width: 576px) {
            .pull-banner {
                width: 180px;
                height: 44px;
                top: -42px;
            }

            .pull-board {
                font-size: 0.88rem;
                border-radius: 11px;
            }
        }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="success-header">
            <div class="thanks-effect" aria-hidden="true">
                <div class="pull-banner">
                    <div class="pull-board">Xin cảm ơn</div>
                </div>
                <div class="success-icon">
                    <i class="bi bi-emoji-smile-fill"></i>
                </div>
            </div>
            <h1 class="success-title">Giao dịch thành công</h1>
        </div>
        <div class="success-body">
            <p class="success-subtitle"><?php echo htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="success-message"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="meta">
                <div><strong>Phương thức:</strong> <?php echo htmlspecialchars($methodLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if ($transId > 0): ?>
                    <div><strong>Mã giao dịch:</strong> #<?php echo (int)$transId; ?></div>
                <?php endif; ?>
            </div>
            <div class="action-buttons">
                <a href="index.php" class="btn btn-brand btn-main">
                    <i class="bi bi-house-door me-1"></i>Về trang chủ
                </a>
                <a href="<?php echo htmlspecialchars($secondaryUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-brand btn-main">
                    <i class="bi bi-heart me-1"></i><?php echo htmlspecialchars($secondaryLabel, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
