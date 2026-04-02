<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireStaffOrAdmin();

// Staff cashier only (admin is always allowed)
if (!isAdmin() && getStaffPanelKey() !== 'cashier') {
    header('Location: ../staff-panel.php');
    exit();
}

$pageTitle = 'Panel Thu ngan';
$panelType = 'cashier';
$error = '';
$success = '';
$receipt = null;

$products = Database::fetchAll(
    "SELECT i.item_id, i.name, i.quantity, i.sale_price, i.price_type, i.images,
            c.name AS category_name
     FROM inventory i
     LEFT JOIN categories c ON i.category_id = c.category_id
     WHERE i.status = 'available' AND i.is_for_sale = 1 AND i.quantity > 0
     ORDER BY i.updated_at DESC, i.item_id DESC
     LIMIT 250"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf)) {
        $error = 'Yeu cau khong hop le. Vui long thu lai.';
    } else {
        $rawCart = $_POST['cart_json'] ?? '[]';
        $cart = json_decode($rawCart, true);
        $paymentMethod = $_POST['payment_method'] ?? 'cash';
        $customerName = trim((string)($_POST['customer_name'] ?? 'Khach mua tai quay'));

        if (!is_array($cart) || empty($cart)) {
            $error = 'Gio hang dang rong.';
        } elseif (!in_array($paymentMethod, ['cash', 'bank_transfer'], true)) {
            $error = 'Phuong thuc thanh toan khong hop le.';
        } else {
            try {
                Database::beginTransaction();

                $normalizedPayment = $paymentMethod === 'cash' ? 'cod' : 'bank_transfer';
                $totalAmount = 0;
                $lineItems = [];

                foreach ($cart as $line) {
                    $itemId = (int)($line['item_id'] ?? 0);
                    $qty = (int)($line['quantity'] ?? 0);
                    if ($itemId <= 0 || $qty <= 0) {
                        continue;
                    }

                    $item = Database::fetch(
                        "SELECT item_id, name, quantity, sale_price, is_for_sale, status
                         FROM inventory
                         WHERE item_id = ?
                         FOR UPDATE",
                        [$itemId]
                    );

                    if (!$item || (int)$item['quantity'] < $qty || (int)$item['is_for_sale'] !== 1 || $item['status'] !== 'available') {
                        throw new Exception('San pham #' . $itemId . ' khong du ton kho hoac khong con ban.');
                    }

                    $price = (float)($item['sale_price'] ?? 0);
                    $subtotal = $price * $qty;
                    $totalAmount += $subtotal;

                    $lineItems[] = [
                        'item_id' => (int)$item['item_id'],
                        'item_name' => (string)$item['name'],
                        'quantity' => $qty,
                        'price' => $price,
                        'subtotal' => $subtotal,
                    ];
                }

                if (empty($lineItems)) {
                    throw new Exception('Khong co san pham hop le de thanh toan.');
                }

                Database::execute(
                    "INSERT INTO orders (
                        user_id, shipping_name, shipping_phone, shipping_address,
                        shipping_method, shipping_note,
                        payment_method, payment_status,
                        total_amount, total_items, status
                    ) VALUES (?, ?, ?, ?, 'pickup', ?, ?, 'paid', ?, ?, 'delivered')",
                    [
                        (int)($_SESSION['user_id'] ?? 0),
                        $customerName !== '' ? $customerName : 'Khach mua tai quay',
                        null,
                        'Mua tai quay Goodwill Vietnam',
                        'Ban tai quay (POS thu ngan)',
                        $normalizedPayment,
                        $totalAmount,
                        array_sum(array_column($lineItems, 'quantity')),
                    ]
                );

                $orderId = (int)Database::lastInsertId();

                foreach ($lineItems as $line) {
                    Database::execute(
                        "INSERT INTO order_items (order_id, item_id, item_name, quantity, price, price_type, subtotal)
                         VALUES (?, ?, ?, ?, ?, 'normal', ?)",
                        [
                            $orderId,
                            $line['item_id'],
                            $line['item_name'],
                            $line['quantity'],
                            $line['price'],
                            $line['subtotal'],
                        ]
                    );

                    Database::execute(
                        "UPDATE inventory
                         SET quantity = quantity - ?,
                             status = CASE WHEN quantity - ? <= 0 THEN 'sold' ELSE status END,
                             is_for_sale = CASE WHEN quantity - ? <= 0 THEN 0 ELSE is_for_sale END,
                             updated_at = NOW()
                         WHERE item_id = ?",
                        [$line['quantity'], $line['quantity'], $line['quantity'], $line['item_id']]
                    );
                }

                logActivity((int)($_SESSION['user_id'] ?? 0), 'cashier_checkout', 'Checkout bill #' . $orderId . ' at cashier panel');

                Database::commit();

                $success = 'Da tao hoa don va thanh toan thanh cong.';
                $receipt = [
                    'order_id' => $orderId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'payment_method' => $paymentMethod,
                    'customer_name' => $customerName !== '' ? $customerName : 'Khach mua tai quay',
                    'items' => $lineItems,
                    'total_amount' => $totalAmount,
                ];
            } catch (Exception $e) {
                Database::rollback();
                $error = 'Khong the thanh toan: ' . $e->getMessage();
            }
        }
    }
}

$todayBills = Database::fetch(
    "SELECT COUNT(*) AS c
     FROM orders
     WHERE DATE(created_at) = CURDATE() AND shipping_note = 'Ban tai quay (POS thu ngan)'"
);
$todayRevenue = Database::fetch(
    "SELECT COALESCE(SUM(total_amount), 0) AS total
     FROM orders
     WHERE DATE(created_at) = CURDATE() AND shipping_note = 'Ban tai quay (POS thu ngan)'"
);
$availableCount = Database::fetch("SELECT COUNT(*) AS c FROM inventory WHERE status = 'available' AND is_for_sale = 1 AND quantity > 0");
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
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body { background: #f3f9fc; }
        .admin-content { padding-top: 1rem; padding-bottom: 1.5rem; }

        .dashboard-topbar {
            background: linear-gradient(140deg, #f7fcfe 0%, #ecf7fb 100%);
            border: 1px solid #d7edf3;
            border-radius: 22px;
            padding: 1rem 1.45rem;
            margin-top: .35rem;
            margin-bottom: 1.2rem;
            box-shadow: 0 10px 24px rgba(8,74,92,.07);
        }
        .dashboard-topbar h1 {
            margin: 0;
            color: #0f172a;
            font-weight: 800;
            font-size: clamp(1.6rem, 2.5vw, 2.4rem);
            line-height: 1.1;
        }
        .dashboard-note { color: #64748b; font-size: .95rem; margin-top: .4rem; }

        .stat-card {
            border: 1px solid #d7edf3;
            border-radius: 16px;
            box-shadow: 0 10px 24px rgba(8,74,92,.08);
            background: #fff;
        }
        .stat-card .card-body { padding: 1rem 1.1rem; }
        .stat-label { font-size: .78rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; color: #0b728c; margin-bottom: .28rem; }
        .stat-value { font-size: 1.65rem; font-weight: 800; color: #0f172a; line-height: 1; }

        .dashboard-card {
            border: 1px solid #d7edf3;
            border-radius: 16px;
            box-shadow: 0 10px 24px rgba(8,74,92,.08);
            overflow: hidden;
            background: #fff;
        }
        .dashboard-card-header {
            background: linear-gradient(140deg, #fbfeff 0%, #edf8fb 100%);
            border-bottom: 1px solid #d7edf3;
            padding: .82rem 1rem;
        }
        .dashboard-card-title { font-weight: 700; color: #0b728c; margin: 0; }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: .85rem;
        }
        .product-card {
            border: 1px solid #d7edf3;
            border-radius: 14px;
            padding: .75rem;
            background: #fff;
        }
        .product-title { font-weight: 700; color: #0f172a; margin-bottom: .25rem; }
        .product-meta { color: #64748b; font-size: .85rem; margin-bottom: .45rem; }
        .barcode-wrap {
            border: 1px dashed #9fd8e6;
            border-radius: 10px;
            background: #f8fdff;
            padding: .35rem;
            text-align: center;
            margin-bottom: .55rem;
        }

        .cart-table td, .cart-table th { vertical-align: middle; font-size: .88rem; }
        .qty-input { width: 72px; }
        .scanner-box {
            border: 1px dashed #9fd8e6;
            border-radius: 12px;
            padding: .8rem;
            background: #f9fdff;
        }

        .receipt-print {
            background: #fff;
            border: 1px dashed #c5d9df;
            border-radius: 12px;
            padding: 1rem;
        }

        @media print {
            body * { visibility: hidden; }
            #receiptArea, #receiptArea * { visibility: visible; }
            #receiptArea { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/staff-sidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
            <div class="dashboard-topbar d-flex justify-content-between flex-wrap align-items-center gap-3">
                <div>
                    <h1><i class="bi bi-upc-scan me-2"></i>Panel Thu ngan</h1>
                    <div class="dashboard-note">Ban hang tai quay, quet ma vach va xuat bill ngay.</div>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="printReceiptBtnTop" style="display:none;">
                        <i class="bi bi-printer me-1"></i>In bill
                    </button>
                </div>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-xl-4 col-md-6 mb-3">
                    <div class="card stat-card h-100"><div class="card-body"><div class="stat-label">Hoa don hom nay</div><div class="stat-value"><?php echo number_format((int)($todayBills['c'] ?? 0)); ?></div></div></div>
                </div>
                <div class="col-xl-4 col-md-6 mb-3">
                    <div class="card stat-card h-100"><div class="card-body"><div class="stat-label">Doanh thu hom nay</div><div class="stat-value"><?php echo number_format((float)($todayRevenue['total'] ?? 0), 0, ',', '.'); ?> đ</div></div></div>
                </div>
                <div class="col-xl-4 col-md-6 mb-3">
                    <div class="card stat-card h-100"><div class="card-body"><div class="stat-label">San pham dang ban</div><div class="stat-value"><?php echo number_format((int)($availableCount['c'] ?? 0)); ?></div></div></div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-xl-8">
                    <div class="card dashboard-card mb-3">
                        <div class="card-header dashboard-card-header d-flex justify-content-between align-items-center">
                            <h6 class="dashboard-card-title">Danh muc san pham tai quay</h6>
                            <input type="text" id="searchProduct" class="form-control form-control-sm" placeholder="Tim ten san pham..." style="max-width: 240px;">
                        </div>
                        <div class="card-body">
                            <div class="product-grid" id="productGrid">
                                <?php foreach ($products as $p): ?>
                                    <?php
                                        $barcode = 'GWV-' . (int)$p['item_id'];
                                        $price = (float)($p['sale_price'] ?? 0);
                                    ?>
                                    <div class="product-card"
                                        data-id="<?php echo (int)$p['item_id']; ?>"
                                        data-name="<?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-price="<?php echo $price; ?>"
                                        data-qty="<?php echo (int)$p['quantity']; ?>"
                                        data-barcode="<?php echo $barcode; ?>">
                                        <div class="product-title"><?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="product-meta">
                                            <?php echo htmlspecialchars($p['category_name'] ?? 'Khong phan loai', ENT_QUOTES, 'UTF-8'); ?>
                                            | Ton: <?php echo (int)$p['quantity']; ?>
                                        </div>
                                        <div class="barcode-wrap">
                                            <svg id="bc-<?php echo (int)$p['item_id']; ?>"></svg>
                                            <div class="small text-muted"><?php echo $barcode; ?></div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-primary flex-fill addToCartBtn">Them vao gio</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary printBarcodeBtn">In ma</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card dashboard-card">
                        <div class="card-header dashboard-card-header d-flex justify-content-between align-items-center">
                            <h6 class="dashboard-card-title">Quet ma vach (camera)</h6>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="startScanBtn">Bat camera</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="stopScanBtn" disabled>Tat camera</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="scanner-box mb-2">
                                <div id="reader" style="width: 100%; min-height: 280px;"></div>
                            </div>
                            <div class="small text-muted">
                                Ma vach Goodwill su dung dinh dang: <strong>GWV-{item_id}</strong>.
                                Khi quet dung ma, san pham se tu dong them vao gio.
                            </div>
                            <div id="scanStatus" class="mt-2 small"></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card dashboard-card mb-3">
                        <div class="card-header dashboard-card-header">
                            <h6 class="dashboard-card-title">Gio hang / Thanh toan</h6>
                        </div>
                        <div class="card-body">
                            <form method="post" id="checkoutForm">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="cart_json" id="cartJson" value="[]">

                                <div class="mb-2">
                                    <label class="form-label form-label-sm">Ten khach (tuy chon)</label>
                                    <input type="text" name="customer_name" class="form-control form-control-sm" placeholder="Khach mua tai quay">
                                </div>

                                <div class="table-responsive mb-2">
                                    <table class="table table-sm cart-table" id="cartTable">
                                        <thead>
                                            <tr>
                                                <th>SP</th>
                                                <th>SL</th>
                                                <th>Gia</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr id="emptyCartRow"><td colspan="4" class="text-muted text-center">Chua co san pham</td></tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="fw-semibold">Tam tinh:</span>
                                    <span id="cartSubtotal">0 đ</span>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label form-label-sm d-block">Phuong thuc thanh toan</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="payCash" value="cash" checked>
                                        <label class="form-check-label" for="payCash">Tien mat</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="payBank" value="bank_transfer">
                                        <label class="form-check-label" for="payBank">Chuyen khoan</label>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100" id="checkoutBtn">
                                    <i class="bi bi-receipt-cutoff me-1"></i>Thanh toan va xuat bill
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card dashboard-card" id="receiptArea" <?php echo $receipt ? '' : 'style="display:none;"'; ?>>
                        <div class="card-header dashboard-card-header d-flex justify-content-between align-items-center">
                            <h6 class="dashboard-card-title">Bill vua tao</h6>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="printReceiptBtn">
                                <i class="bi bi-printer"></i>
                            </button>
                        </div>
                        <div class="card-body receipt-print" id="receiptBody">
                            <?php if ($receipt): ?>
                                <div class="fw-bold">Goodwill Vietnam</div>
                                <div class="small text-muted mb-2">Bill #<?php echo (int)$receipt['order_id']; ?> - <?php echo htmlspecialchars($receipt['created_at'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="small mb-1">Khach: <?php echo htmlspecialchars($receipt['customer_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="small mb-2">Thanh toan: <?php echo $receipt['payment_method'] === 'cash' ? 'Tien mat' : 'Chuyen khoan'; ?></div>
                                <hr>
                                <?php foreach ($receipt['items'] as $item): ?>
                                    <div class="d-flex justify-content-between small">
                                        <span><?php echo htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8'); ?> x<?php echo (int)$item['quantity']; ?></span>
                                        <span><?php echo number_format((float)$item['subtotal'], 0, ',', '.'); ?> đ</span>
                                    </div>
                                <?php endforeach; ?>
                                <hr>
                                <div class="d-flex justify-content-between fw-bold">
                                    <span>Tong cong</span>
                                    <span><?php echo number_format((float)$receipt['total_amount'], 0, ',', '.'); ?> đ</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="barcodeModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">In ma vach san pham</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <div id="barcodeModalName" class="fw-semibold mb-2"></div>
                            <svg id="barcodeModalSvg"></svg>
                            <div id="barcodeModalText" class="small text-muted mt-2"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Dong</button>
                            <button type="button" class="btn btn-primary" id="printBarcodeConfirmBtn">In ma</button>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
<script>
(function () {
    const currency = (v) => new Intl.NumberFormat('vi-VN').format(v || 0) + ' đ';
    const cart = new Map();

    const grid = document.getElementById('productGrid');
    const cartTableBody = document.querySelector('#cartTable tbody');
    const cartSubtotal = document.getElementById('cartSubtotal');
    const cartJson = document.getElementById('cartJson');
    const checkoutForm = document.getElementById('checkoutForm');
    const searchInput = document.getElementById('searchProduct');

    const barcodeModal = new bootstrap.Modal(document.getElementById('barcodeModal'));
    const barcodeModalSvg = document.getElementById('barcodeModalSvg');
    const barcodeModalName = document.getElementById('barcodeModalName');
    const barcodeModalText = document.getElementById('barcodeModalText');
    const printBarcodeConfirmBtn = document.getElementById('printBarcodeConfirmBtn');

    let currentBarcodeText = '';

    function renderCart() {
        cartTableBody.innerHTML = '';

        let subtotal = 0;
        if (cart.size === 0) {
            cartTableBody.innerHTML = '<tr id="emptyCartRow"><td colspan="4" class="text-muted text-center">Chua co san pham</td></tr>';
        } else {
            cart.forEach((item) => {
                subtotal += item.price * item.quantity;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.name}</td>
                    <td>
                        <input type="number" min="1" max="${item.maxQty}" value="${item.quantity}" class="form-control form-control-sm qty-input" data-id="${item.id}">
                    </td>
                    <td>${currency(item.price * item.quantity)}</td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-item" data-id="${item.id}"><i class="bi bi-x"></i></button></td>
                `;
                cartTableBody.appendChild(tr);
            });
        }

        cartSubtotal.textContent = currency(subtotal);
        cartJson.value = JSON.stringify(Array.from(cart.values()).map(i => ({ item_id: i.id, quantity: i.quantity })));
    }

    function addToCart(id, name, price, maxQty) {
        if (!id || maxQty <= 0) return;
        const existing = cart.get(id);
        if (existing) {
            existing.quantity = Math.min(existing.quantity + 1, existing.maxQty);
            cart.set(id, existing);
        } else {
            cart.set(id, { id, name, price, quantity: 1, maxQty });
        }
        renderCart();
    }

    grid.querySelectorAll('.product-card').forEach((card) => {
        const id = parseInt(card.dataset.id, 10);
        const name = card.dataset.name;
        const price = parseFloat(card.dataset.price || '0');
        const maxQty = parseInt(card.dataset.qty || '0', 10);
        const barcode = card.dataset.barcode;

        const svg = card.querySelector('svg');
        try {
            JsBarcode(svg, barcode, { format: 'CODE128', width: 1.5, height: 35, displayValue: false, margin: 0 });
        } catch (e) {}

        card.querySelector('.addToCartBtn').addEventListener('click', () => addToCart(id, name, price, maxQty));

        card.querySelector('.printBarcodeBtn').addEventListener('click', () => {
            currentBarcodeText = barcode;
            barcodeModalName.textContent = name;
            barcodeModalText.textContent = barcode;
            try {
                JsBarcode(barcodeModalSvg, barcode, { format: 'CODE128', width: 2, height: 80, displayValue: true, margin: 10 });
            } catch (e) {}
            barcodeModal.show();
        });
    });

    cartTableBody.addEventListener('input', (e) => {
        if (!e.target.classList.contains('qty-input')) return;
        const id = parseInt(e.target.dataset.id, 10);
        const v = parseInt(e.target.value || '1', 10);
        if (!cart.has(id)) return;
        const row = cart.get(id);
        row.quantity = Math.max(1, Math.min(v, row.maxQty));
        cart.set(id, row);
        renderCart();
    });

    cartTableBody.addEventListener('click', (e) => {
        const btn = e.target.closest('.remove-item');
        if (!btn) return;
        const id = parseInt(btn.dataset.id, 10);
        cart.delete(id);
        renderCart();
    });

    searchInput.addEventListener('input', () => {
        const keyword = searchInput.value.trim().toLowerCase();
        grid.querySelectorAll('.product-card').forEach((card) => {
            const name = (card.dataset.name || '').toLowerCase();
            const code = (card.dataset.barcode || '').toLowerCase();
            const show = keyword === '' || name.includes(keyword) || code.includes(keyword);
            card.style.display = show ? '' : 'none';
        });
    });

    checkoutForm.addEventListener('submit', (e) => {
        if (cart.size === 0) {
            e.preventDefault();
            alert('Gio hang dang rong.');
        }
    });

    printBarcodeConfirmBtn.addEventListener('click', () => {
        if (!currentBarcodeText) return;
        const w = window.open('', '_blank', 'width=420,height=320');
        if (!w) return;
        const svgHtml = barcodeModalSvg.outerHTML;
        w.document.write(`
            <html><head><title>In ma vach</title></head>
            <body style="font-family:Arial,sans-serif;text-align:center;padding:24px;">
                ${svgHtml}
                <div style="margin-top:8px;">${currentBarcodeText}</div>
                <script>window.print();<\/script>
            </body></html>
        `);
        w.document.close();
    });

    const printBtn = document.getElementById('printReceiptBtn');
    const printBtnTop = document.getElementById('printReceiptBtnTop');
    const receiptArea = document.getElementById('receiptArea');
    if (receiptArea && receiptArea.style.display !== 'none') {
        if (printBtnTop) printBtnTop.style.display = '';
    }

    [printBtn, printBtnTop].forEach((btn) => {
        if (!btn) return;
        btn.addEventListener('click', () => window.print());
    });

    // Barcode scanner with camera
    const startBtn = document.getElementById('startScanBtn');
    const stopBtn = document.getElementById('stopScanBtn');
    const scanStatus = document.getElementById('scanStatus');
    let scanner = null;

    function updateScanStatus(text, cls) {
        scanStatus.className = 'mt-2 small ' + (cls || 'text-muted');
        scanStatus.textContent = text;
    }

    async function startScanner() {
        if (scanner) return;
        scanner = new Html5Qrcode('reader');

        try {
            await scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 250, height: 120 } },
                (decodedText) => {
                    const m = String(decodedText || '').trim().match(/^GWV-(\d+)$/i);
                    if (!m) {
                        updateScanStatus('Ma vua quet khong dung dinh dang Goodwill (GWV-id).', 'text-danger');
                        return;
                    }

                    const id = parseInt(m[1], 10);
                    const card = grid.querySelector(`.product-card[data-id="${id}"]`);
                    if (!card) {
                        updateScanStatus('Khong tim thay san pham tu ma vach: ' + decodedText, 'text-danger');
                        return;
                    }

                    addToCart(
                        parseInt(card.dataset.id, 10),
                        card.dataset.name,
                        parseFloat(card.dataset.price || '0'),
                        parseInt(card.dataset.qty || '0', 10)
                    );

                    updateScanStatus('Da them san pham vao gio: ' + (card.dataset.name || ''), 'text-success');
                },
                () => {}
            );

            startBtn.disabled = true;
            stopBtn.disabled = false;
            updateScanStatus('Camera dang hoat dong. Dua ma vach vao khung quet...', 'text-primary');
        } catch (err) {
            scanner = null;
            updateScanStatus('Khong the mo camera. Vui long cap quyen camera cho trang web.', 'text-danger');
        }
    }

    async function stopScanner() {
        if (!scanner) return;
        try {
            await scanner.stop();
            await scanner.clear();
        } catch (e) {}
        scanner = null;
        startBtn.disabled = false;
        stopBtn.disabled = true;
        updateScanStatus('Da tat camera.', 'text-muted');
    }

    startBtn.addEventListener('click', startScanner);
    stopBtn.addEventListener('click', stopScanner);

    renderCart();
})();
</script>
</body>
</html>
