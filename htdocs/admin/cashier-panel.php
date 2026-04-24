<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();
if (!isAdmin() && getStaffPanelKey() !== 'cashier') {
    header('Location: ../staff-panel.php');
    exit();
}

$pageTitle = 'Kho hàng';
$panelType = 'cashier';

if (isset($_GET['stock_snapshot']) && $_GET['stock_snapshot'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $stockRows = Database::fetchAll(
            "SELECT i.item_id,
                    i.status,
                    i.is_for_sale,
                    GREATEST(
                        i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id), 0),
                        0
                    ) AS available_quantity
             FROM inventory i
             WHERE i.is_for_sale = 1"
        );

        $snapshot = [];
        foreach ($stockRows as $row) {
            $snapshot[(int)$row['item_id']] = [
                'available_quantity' => (int)($row['available_quantity'] ?? 0),
                'status' => (string)($row['status'] ?? ''),
                'is_for_sale' => (int)($row['is_for_sale'] ?? 0),
            ];
        }
        echo json_encode(['success' => true, 'data' => $snapshot], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Cannot load stock snapshot']);
    }
    exit();
}

$products = Database::fetchAll(
    "SELECT i.item_id, i.quantity, i.name, i.sale_price, i.status, i.images,
            GREATEST(
                i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id), 0),
                0
            ) AS available_quantity,
            c.name AS category_name
     FROM inventory i
     LEFT JOIN categories c ON i.category_id = c.category_id
     WHERE i.is_for_sale = 1
     ORDER BY i.updated_at DESC, i.item_id DESC"
);

$totalProducts = count($products);
$inStock = 0;
$outOfStock = 0;

foreach ($products as &$p) {
    $imgs = json_decode((string)($p['images'] ?? '[]'), true);
    $p['img_url'] = !empty($imgs)
        ? resolveDonationImageUrl((string)$imgs[0])
        : 'uploads/donations/placeholder-default.svg';

    $qty = (int)($p['available_quantity'] ?? 0);
    if ($qty > 0 && (string)$p['status'] !== 'sold') {
        $inStock++;
    } else {
        $outOfStock++;
    }
}
unset($p);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Goodwill Vietnam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --teal: #0b728c;
            --teal-dk: #084f63;
            --bg: #f0f4f8;
            --border: #d7edf3;
            --text: #0f172a;
            --muted: #64748b;
        }
        body { background: var(--bg); }
        .admin-content { padding: 1rem 1.2rem 2rem; }

        .topbar {
            background: transparent;
            border-radius: 16px;
            padding: .15rem 0 .25rem;
            margin-bottom: .9rem;
            display: flex;
            align-items: center;
            gap: .9rem;
        }
        .topbar-icon {
            width: 74px;
            height: 74px;
            border-radius: 18px;
            background: linear-gradient(145deg, #0b728c, #095f75);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 10px 20px rgba(8,74,92,.23);
        }
        .topbar-icon i { font-size: 2rem; line-height: 1; }
        .topbar-text h1 {
            margin: 0;
            font-size: clamp(1.7rem, 2.8vw, 2.9rem);
            font-weight: 900;
            color: #0f172a;
            line-height: 1.1;
        }
        .topbar-text p {
            margin: .35rem 0 0;
            color: #58718a;
            font-size: clamp(1rem, 1.5vw, 2rem);
            line-height: 1.25;
        }
        @media (max-width: 767.98px) {
            .topbar {
                align-items: flex-start;
                gap: .72rem;
            }
            .topbar-icon {
                width: 56px;
                height: 56px;
                border-radius: 14px;
            }
            .topbar-icon i { font-size: 1.45rem; }
            .topbar-text p { font-size: 1rem; }
        }

        .stats-row { margin-bottom: .85rem; }
        .stat-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #fff;
            padding: .75rem .9rem;
            box-shadow: 0 3px 9px rgba(8,74,92,.07);
            height: 100%;
        }
        .stat-label { color: var(--muted); font-size: .78rem; margin-bottom: .2rem; }
        .stat-value { color: var(--text); font-size: 1.35rem; font-weight: 800; line-height: 1.1; }

        .layout-grid { display: grid; grid-template-columns: 1fr 330px; gap: .8rem; }
        .card-box {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 3px 10px rgba(8,74,92,.07);
            overflow: hidden;
        }
        .card-head {
            border-bottom: 1px solid var(--border);
            padding: .7rem .95rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
            flex-wrap: wrap;
        }
        .card-head h6 { margin: 0; color: var(--teal); font-size: .95rem; font-weight: 700; }

        .search-wrap { position: relative; width: 280px; max-width: 100%; }
        .search-wrap i {
            position: absolute;
            left: .66rem;
            top: 50%;
            transform: translateY(-50%);
            color: #93a4b2;
            font-size: .84rem;
        }
        .search-wrap input {
            width: 100%;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: .42rem .6rem .42rem 2rem;
            font-size: .86rem;
        }

        .products-grid {
            padding: .8rem;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(176px, 1fr));
            gap: .75rem;
            max-height: calc(100vh - 260px);
            overflow-y: auto;
        }

        .product-card {
            border: 1.5px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            transition: .16s ease;
        }
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(8,74,92,.13);
            border-color: var(--teal);
        }
        .product-img {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            background: #eef4f7;
        }
        .product-body { padding: .58rem .62rem .66rem; }
        .product-name {
            font-size: .82rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: .2rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .product-cat { font-size: .7rem; color: #8a9cab; margin-bottom: .28rem; }
        .product-price { color: var(--teal); font-size: .94rem; font-weight: 800; }
        .stock-badge {
            display: inline-block;
            margin-top: .2rem;
            font-size: .7rem;
            border-radius: 999px;
            padding: .08rem .45rem;
            font-weight: 700;
        }
        .stock-ok { background: #e8fff3; color: #047857; }
        .stock-out { background: #fff0f0; color: #b91c1c; }

        .barcode-box {
            margin: .38rem 0;
            border: 1px dashed #c8dfe8;
            border-radius: 9px;
            background: #f8fcfe;
            padding: .3rem;
            text-align: center;
        }
        .barcode-svg { width: 100%; max-width: 150px; height: 44px; }
        .barcode-code { font-size: .68rem; color: #6a8293; font-weight: 700; letter-spacing: .4px; }

        .actions { display: grid; grid-template-columns: 1fr; gap: .35rem; }
        .btn-smx {
            border-radius: 8px;
            border: 1px solid #c7deea;
            background: #fff;
            color: var(--teal);
            font-size: .75rem;
            font-weight: 700;
            padding: .31rem .42rem;
        }
        .btn-smx:hover { background: #eaf6fb; }

        .scan-body { padding: .78rem .9rem .88rem; }
        .scan-actions { display: grid; grid-template-columns: 1fr 1fr; gap: .45rem; margin-bottom: .54rem; }
        .scan-btn {
            border: 1px solid #c7deea;
            border-radius: 9px;
            background: #fff;
            color: var(--teal);
            font-size: .76rem;
            font-weight: 700;
            padding: .4rem .45rem;
        }
        .scan-btn.primary {
            background: linear-gradient(135deg, var(--teal), var(--teal-dk));
            color: #fff;
            border-color: transparent;
        }
        .scan-btn:disabled { opacity: .58; }

        .scan-manual { display: flex; gap: .35rem; margin-bottom: .45rem; }
        .scan-manual input {
            flex: 1;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-size: .82rem;
            padding: .35rem .55rem;
        }
        .scan-manual button {
            border: 1px solid #c7deea;
            border-radius: 8px;
            background: #fff;
            color: var(--teal);
            font-size: .76rem;
            font-weight: 700;
            padding: 0 .68rem;
        }

        /* ── Scanner reader + overlay ── */
        .scan-reader-wrap {
            position: relative;
            display: none;
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            background: #f3fafc;
        }
        .scan-reader-wrap.scanning { display: block; }
        #scannerReader {
            display: block;
            min-height: 170px;
            width: 100%;
        }
        /* Smooth zoom on video when camera is active */
        #scannerReader video {
            transition: transform .35s ease;
        }
        #scannerReader.barcode-found video {
            transform: scale(1.05);
        }

        /* Overlay painted on top of video */
        .scan-overlay {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 20;
            border-radius: 10px;
            overflow: visible;
        }

        /* Dark side vignette to focus on centre */
        .scan-overlay::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 55% at 50% 50%,
                transparent 45%, rgba(0,0,0,.32) 100%);
            border-radius: 10px;
        }

        /* Animated laser line */
        .scan-laser {
            position: absolute;
            left: 12%;
            right: 12%;
            height: 2px;
            top: 35%;
            background: linear-gradient(90deg,
                transparent 0%,
                rgba(0,220,255,.85) 15%,
                #00dcff 50%,
                rgba(0,220,255,.85) 85%,
                transparent 100%);
            box-shadow: 0 0 7px 2px rgba(0,220,255,.5);
            border-radius: 2px;
            animation: laserSweep 2s ease-in-out infinite;
        }

        @keyframes laserSweep {
            0%   { top: 28%; opacity: .75; }
            50%  { top: 62%; opacity: 1;   }
            100% { top: 28%; opacity: .75; }
        }

        /* Corner bracket shared base */
        .scan-corner {
            position: absolute;
            width: 22px;
            height: 22px;
            transition: border-color .2s;
        }
        .scan-corner::before,
        .scan-corner::after {
            content: '';
            position: absolute;
            background: #00dcff;
            box-shadow: 0 0 6px rgba(0,220,255,.7);
            border-radius: 1.5px;
            transition: background .2s, box-shadow .2s;
        }
        .scan-corner::before { width: 22px; height: 3px; }
        .scan-corner::after  { width: 3px;  height: 22px; }

        .scan-corner.tl { top:24%; left:9%; }
        .scan-corner.tl::before { top:0;    left:0; }
        .scan-corner.tl::after  { top:0;    left:0; }

        .scan-corner.tr { top:24%; right:9%; }
        .scan-corner.tr::before { top:0;    right:0; left:auto; }
        .scan-corner.tr::after  { top:0;    right:0; left:auto; }

        .scan-corner.bl { bottom:24%; left:9%; }
        .scan-corner.bl::before { bottom:0; left:0; top:auto; }
        .scan-corner.bl::after  { bottom:0; left:0; top:auto; }

        .scan-corner.br { bottom:24%; right:9%; }
        .scan-corner.br::before { bottom:0; right:0; left:auto; top:auto; }
        .scan-corner.br::after  { bottom:0; right:0; left:auto; top:auto; }

        .scan-reader-wrap.scanning .scan-corner::before,
        .scan-reader-wrap.scanning .scan-corner::after {
            animation: cornerPulse 2.2s ease-in-out infinite;
        }
        @keyframes cornerPulse {
            0%,100% { background: #00dcff; box-shadow: 0 0 5px rgba(0,220,255,.6); }
            50%      { background: #52f2ff; box-shadow: 0 0 10px rgba(82,242,255,.9); }
        }

        .scan-reader-wrap.barcode-found .scan-corner::before,
        .scan-reader-wrap.barcode-found .scan-corner::after {
            background: #22c55e !important;
            box-shadow: 0 0 10px rgba(34,197,94,.9) !important;
            animation: none;
        }
        .scan-reader-wrap.barcode-found .scan-laser {
            background: linear-gradient(90deg,
                transparent 0%, rgba(34,197,94,.9) 15%,
                #22c55e 50%, rgba(34,197,94,.9) 85%, transparent 100%) !important;
            box-shadow: 0 0 10px 3px rgba(34,197,94,.55) !important;
            animation: none;
        }

        .scan-success-flash {
            position: absolute;
            inset: 0;
            border-radius: 10px;
            background: transparent;
            pointer-events: none;
        }
        .scan-success-flash.active {
            animation: greenFlash .45s ease-out forwards;
        }
        @keyframes greenFlash {
            0%   { background: rgba(34,197,94,.38); }
            100% { background: transparent; }
        }

        .scan-badge-pop {
            position: absolute;
            bottom: 12%;
            left: 50%;
            transform: translateX(-50%) scale(.7);
            background: rgba(22,163,74,.92);
            color: #fff;
            font-size: .78rem;
            font-weight: 700;
            border-radius: 20px;
            padding: .28rem .9rem;
            pointer-events: none;
            opacity: 0;
            white-space: nowrap;
            box-shadow: 0 4px 16px rgba(22,163,74,.35);
            z-index: 30;
        }
        .scan-badge-pop.active {
            animation: badgePop .65s ease-out forwards;
        }
        @keyframes badgePop {
            0%   { opacity: 1; transform: translateX(-50%) scale(1);    bottom:12%; }
            65%  { opacity: 1; transform: translateX(-50%) scale(1.06); bottom:18%; }
            100% { opacity: 0; transform: translateX(-50%) scale(.9);   bottom:22%; }
        }

        .scan-status {
            margin-top: .5rem;
            font-size: .76rem;
            border-radius: 8px;
            padding: .35rem .52rem;
            background: #eef8fc;
            color: var(--teal);
            min-height: 30px;
        }

        @media (max-width: 1200px) {
            .layout-grid { grid-template-columns: 1fr; }
            .products-grid { max-height: none; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/staff-sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
            <div class="topbar">
                <div class="topbar-icon">
                    <i class="bi bi-upc-scan"></i>
                </div>
                <div class="topbar-text">
                    <h1>Kho hàng mã vạch</h1>
                    <p>Sản phẩm + mã vạch Goodwill Vietnam • Quét mã để thêm nhanh vào giỏ</p>
                </div>
            </div>

            <div class="row g-2 stats-row">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-label">Tổng sản phẩm</div>
                        <div class="stat-value" id="statTotalProducts"><?php echo (int)$totalProducts; ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-label">Còn hàng</div>
                        <div class="stat-value" id="statInStock"><?php echo (int)$inStock; ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-label">Hết hàng</div>
                        <div class="stat-value" id="statOutOfStock"><?php echo (int)$outOfStock; ?></div>
                    </div>
                </div>
            </div>

            <div class="layout-grid">
                <div class="card-box">
                    <div class="card-head">
                        <h6>Danh sách sản phẩm + mã vạch</h6>
                        <div class="search-wrap">
                            <i class="bi bi-search"></i>
                            <input type="text" id="searchInput" placeholder="Tìm theo tên, danh mục, mã vạch...">
                        </div>
                    </div>

                    <div class="products-grid" id="productsGrid">
                        <?php foreach ($products as $p): ?>
                            <?php
                                $barcodeCode = 'GWV' . str_pad((string)((int)$p['item_id']), 6, '0', STR_PAD_LEFT);
                                $qty = (int)($p['available_quantity'] ?? 0);
                                $isOut = $qty <= 0 || ($p['status'] ?? '') === 'sold';
                            ?>
                            <div class="product-card"
                                 data-id="<?php echo (int)$p['item_id']; ?>"
                                 data-name="<?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                 data-cat="<?php echo htmlspecialchars($p['category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                 data-barcode="<?php echo htmlspecialchars($barcodeCode, ENT_QUOTES, 'UTF-8'); ?>"
                                 id="product-<?php echo (int)$p['item_id']; ?>">
                                <img class="product-img"
                                     src="../<?php echo htmlspecialchars($p['img_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                     onerror="this.src='../uploads/donations/placeholder-default.svg'"
                                     alt="<?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="product-body">
                                    <div class="product-name"><?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="product-cat"><?php echo htmlspecialchars($p['category_name'] ?? 'Không phân loại', ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="product-price"><?php echo number_format((float)($p['sale_price'] ?? 0), 0, ',', '.'); ?>đ</div>
                                    <span class="stock-badge <?php echo $isOut ? 'stock-out' : 'stock-ok'; ?>" data-stock-text="<?php echo (int)$p['item_id']; ?>">
                                        <?php echo $isOut ? 'Hết hàng' : ('Tồn: ' . $qty); ?>
                                    </span>

                                    <div class="barcode-box">
                                        <svg class="barcode-svg" id="barcode-<?php echo (int)$p['item_id']; ?>"></svg>
                                        <div class="barcode-code"><?php echo htmlspecialchars($barcodeCode, ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>

                                    <div class="actions">
                                        <button class="btn-smx" type="button" data-action="download" data-item-id="<?php echo (int)$p['item_id']; ?>">
                                            <i class="bi bi-download me-1"></i>Tải PNG mã vạch
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card-box">
                    <div class="card-head">
                        <h6>Quét mã để tìm sản phẩm</h6>
                    </div>
                    <div class="scan-body">
                        <div class="scan-actions">
                            <button class="scan-btn primary" id="startScanBtn" type="button">
                                <i class="bi bi-camera-video me-1"></i>Bật camera
                            </button>
                            <button class="scan-btn" id="stopScanBtn" type="button" disabled>
                                <i class="bi bi-stop-circle me-1"></i>Dừng
                            </button>
                        </div>

                        <div class="scan-manual">
                            <input type="text" id="manualBarcodeInput" placeholder="VD: GWV000123">
                            <button type="button" id="manualFindBtn">Tìm</button>
                        </div>

                        <div class="scan-reader-wrap" id="scanReaderWrap">
                            <div id="scannerReader"></div>
                            <div class="scan-overlay" id="scanOverlay">
                                <div class="scan-corner tl"></div>
                                <div class="scan-corner tr"></div>
                                <div class="scan-corner bl"></div>
                                <div class="scan-corner br"></div>
                                <div class="scan-laser" id="scanLaser"></div>
                                <div class="scan-success-flash" id="scanFlash"></div>
                                <div class="scan-badge-pop" id="scanBadge">✓ Đã nhận diện</div>
                            </div>
                        </div>
                        <div class="scan-status" id="scanStatus">Sẵn sàng quét mã.</div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script>
(() => {
    'use strict';

    const searchInput = document.getElementById('searchInput');
    const productsGrid = document.getElementById('productsGrid');
    const startScanBtn = document.getElementById('startScanBtn');
    const stopScanBtn = document.getElementById('stopScanBtn');
    const manualBarcodeInput = document.getElementById('manualBarcodeInput');
    const manualFindBtn = document.getElementById('manualFindBtn');
    const scanStatus = document.getElementById('scanStatus');
    const scannerReader = document.getElementById('scannerReader');
    const scanReaderWrap  = document.getElementById('scanReaderWrap');
    const scanFlash       = document.getElementById('scanFlash');
    const scanBadge       = document.getElementById('scanBadge');
    const statTotalProducts = document.getElementById('statTotalProducts');
    const statInStock = document.getElementById('statInStock');
    const statOutOfStock = document.getElementById('statOutOfStock');

    const barcodeToCard = new Map();
    const stockById = new Map();
    let html5QrCode = null;
    let scanning = false;
    let lastCode = '';
    let lastAt = 0;

    const normalize = (v) => String(v || '').trim().toUpperCase();

    function setStatus(text, error = false) {
        scanStatus.textContent = text;
        scanStatus.style.background = error ? '#fef2f2' : '#eef8fc';
        scanStatus.style.color = error ? '#b91c1c' : '#0b728c';
    }

    function applySnapshot(snapshot = {}) {
        let inStock = 0;
        let outStock = 0;

        Object.entries(snapshot).forEach(([idStr, row]) => {
            const id = parseInt(idStr, 10);
            const card = document.querySelector(`.product-card[data-id="${id}"]`);
            if (!card) return;

            const qty = Math.max(0, parseInt(row.available_quantity || 0, 10));
            stockById.set(id, qty);

            const stockEl = card.querySelector(`[data-stock-text="${id}"]`);
            if (stockEl) {
                if (qty > 0) {
                    stockEl.textContent = 'Tồn: ' + qty;
                    stockEl.classList.remove('stock-out');
                    stockEl.classList.add('stock-ok');
                } else {
                    stockEl.textContent = 'Hết hàng';
                    stockEl.classList.remove('stock-ok');
                    stockEl.classList.add('stock-out');
                }
            }

            if (qty > 0) {
                inStock++;
                card.style.opacity = '1';
            } else {
                outStock++;
                card.style.opacity = '.68';
            }
        });

        if (statTotalProducts) statTotalProducts.textContent = String(Object.keys(snapshot).length || document.querySelectorAll('.product-card').length);
        if (statInStock) statInStock.textContent = String(inStock);
        if (statOutOfStock) statOutOfStock.textContent = String(outStock);
    }

    async function refreshStockSnapshot(showError = false) {
        try {
            const res = await fetch('cashier-panel.php?stock_snapshot=1', {
                cache: 'no-store',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            if (!json || json.success !== true || typeof json.data !== 'object') throw new Error('Invalid response');
            applySnapshot(json.data);
        } catch (e) {
            if (showError) setStatus('Không thể đồng bộ tồn kho thời gian thực.', true);
        }
    }

    function renderBarcodes() {
        if (typeof JsBarcode !== 'function') return;
        document.querySelectorAll('.product-card').forEach((card) => {
            const code = normalize(card.dataset.barcode);
            const svg = card.querySelector('.barcode-svg');
            if (!code || !svg) return;
            try {
                JsBarcode(svg, code, {
                    format: 'CODE128',
                    lineColor: '#0f172a',
                    width: 1.45,
                    height: 40,
                    displayValue: false,
                    margin: 0
                });
                barcodeToCard.set(code, card);
            } catch (e) {
                console.warn('Cannot render barcode', code, e);
            }
        });
    }

    function downloadBarcodePng(card) {
        const svg = card.querySelector('.barcode-svg');
        const code = normalize(card.dataset.barcode);
        const productName = String(card.dataset.name || 'San pham').trim();
        if (!svg || !code) return;

        const serializer = new XMLSerializer();
        const svgData = serializer.serializeToString(svg);
        const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const img = new Image();

        img.onload = function () {
            const canvas = document.createElement('canvas');
            const labelWidth = 720;
            const labelHeight = 330;
            canvas.width = labelWidth;
            canvas.height = labelHeight;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            // Header
            ctx.fillStyle = '#0f172a';
            ctx.font = '700 30px Arial';
            ctx.textAlign = 'center';
            const nameText = productName.length > 44 ? (productName.slice(0, 41) + '...') : productName;
            ctx.fillText(nameText, labelWidth / 2, 52);

            // Barcode
            const barcodeW = Math.min(620, labelWidth - 60);
            const barcodeH = 150;
            const x = Math.round((labelWidth - barcodeW) / 2);
            const y = 78;
            ctx.drawImage(img, x, y, barcodeW, barcodeH);

            // Code text
            ctx.fillStyle = '#1f2937';
            ctx.font = '700 28px Arial';
            ctx.fillText(code, labelWidth / 2, 265);

            // Footer
            ctx.fillStyle = '#64748b';
            ctx.font = '500 20px Arial';
            ctx.fillText('Goodwill Vietnam', labelWidth / 2, 299);
            URL.revokeObjectURL(url);

            const a = document.createElement('a');
            a.href = canvas.toDataURL('image/png');
            a.download = code + '.png';
            a.click();
        };
        img.src = url;
    }

    function highlightCard(card) {
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        card.style.boxShadow = '0 0 0 3px rgba(11,114,140,.36), 0 10px 22px rgba(8,74,92,.18)';
        setTimeout(() => { card.style.boxShadow = ''; }, 1000);
    }

    let _scanFoundTimer = null;
    function _triggerScanFound(code) {
        scanReaderWrap.classList.add('barcode-found');
        scannerReader.classList.add('barcode-found');

        if (scanFlash) {
            scanFlash.classList.remove('active');
            void scanFlash.offsetWidth;
            scanFlash.classList.add('active');
        }

        if (scanBadge) {
            const found = barcodeToCard.get(code);
            scanBadge.textContent = '✓ ' + (found ? found.dataset.name : code);
            scanBadge.classList.remove('active');
            void scanBadge.offsetWidth;
            scanBadge.classList.add('active');
        }

        clearTimeout(_scanFoundTimer);
        _scanFoundTimer = setTimeout(() => {
            scanReaderWrap.classList.remove('barcode-found');
            scannerReader.classList.remove('barcode-found');
            if (scanFlash)  scanFlash.classList.remove('active');
            if (scanBadge)  scanBadge.classList.remove('active');
        }, 600);
    }

    function findByCode(rawCode) {
        const code = normalize(rawCode);
        const card = barcodeToCard.get(code);
        if (!card) {
            setStatus('Không tìm thấy mã: ' + code, true);
            return;
        }
        const id = parseInt(card.dataset.id || '0', 10);
        if (stockById.has(id) && stockById.get(id) <= 0) {
            setStatus('Mã ' + code + ' hiện đã hết hàng.', true);
        } else {
            setStatus('Đã định vị sản phẩm: ' + code);
        }
        highlightCard(card);
    }

    async function startScanner() {
        if (scanning) return;
        if (typeof Html5Qrcode === 'undefined') {
            setStatus('Không tải được thư viện quét camera.', true);
            return;
        }

        scanReaderWrap.classList.add('scanning');
        if (!html5QrCode) {
            html5QrCode = new Html5Qrcode('scannerReader');
        }

        try {
            await html5QrCode.start(
                { facingMode: { exact: 'environment' } },
                {
                    fps: 10,
                    qrbox: { width: 280, height: 130 },
                    formatsToSupport: [
                        Html5QrcodeSupportedFormats.CODE_128,
                        Html5QrcodeSupportedFormats.CODE_39,
                        Html5QrcodeSupportedFormats.CODE_93,
                        Html5QrcodeSupportedFormats.EAN_13,
                        Html5QrcodeSupportedFormats.EAN_8,
                        Html5QrcodeSupportedFormats.UPC_A,
                        Html5QrcodeSupportedFormats.UPC_E,
                        Html5QrcodeSupportedFormats.ITF,
                        Html5QrcodeSupportedFormats.QR_CODE
                    ]
                },
                (decodedText) => {
                    const code = normalize(decodedText);
                    const now = Date.now();
                    if (code === lastCode && (now - lastAt) < 1200) return;
                    lastCode = code;
                    lastAt = now;
                    findByCode(code);
                    _triggerScanFound(code);
                },
                () => {}
            );
        } catch (err) {
            try {
                await html5QrCode.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 280, height: 130 } },
                    (decodedText) => findByCode(decodedText),
                    () => {}
                );
            } catch (err2) {
                scanReaderWrap.classList.remove('scanning');
                setStatus('Không bật được camera. Kiểm tra quyền camera.', true);
                return;
            }
        }

        scanning = true;
        startScanBtn.disabled = true;
        stopScanBtn.disabled = false;
        setStatus('Đang quét mã vạch...');
    }

    async function stopScanner() {
        if (!scanning || !html5QrCode) return;
        try {
            await html5QrCode.stop();
            await html5QrCode.clear();
        } catch (e) {}

        scanning = false;
        startScanBtn.disabled = false;
        stopScanBtn.disabled = true;
        scanReaderWrap.classList.remove('scanning', 'barcode-found');
        setStatus('Đã dừng camera.');
    }

    productsGrid.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action="download"]');
        if (!btn) return;
        const card = btn.closest('.product-card');
        if (!card) return;
        downloadBarcodePng(card);
    });

    searchInput.addEventListener('input', () => {
        const q = searchInput.value.trim().toLowerCase();
        document.querySelectorAll('.product-card').forEach((card) => {
            const hit = !q
                || card.dataset.name.toLowerCase().includes(q)
                || (card.dataset.cat || '').toLowerCase().includes(q)
                || (card.dataset.barcode || '').toLowerCase().includes(q);
            card.style.display = hit ? '' : 'none';
        });
    });

    startScanBtn.addEventListener('click', startScanner);
    stopScanBtn.addEventListener('click', stopScanner);

    manualFindBtn.addEventListener('click', () => {
        findByCode(manualBarcodeInput.value);
    });
    manualBarcodeInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            manualFindBtn.click();
        }
    });

    window.addEventListener('beforeunload', () => {
        if (scanning) stopScanner();
    });

    renderBarcodes();
    refreshStockSnapshot(false);
    setInterval(() => { refreshStockSnapshot(false); }, 7000);
})();
</script>
</body>
</html>
