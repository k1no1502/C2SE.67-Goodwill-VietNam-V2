<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Params
$category_id = (int)($_GET['category'] ?? 0);
$price_type = $_GET['price_type'] ?? '';
$search = sanitize($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Detect optional inventory columns (compatibility with older DB)
$inventoryCols = Database::fetchAll(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory'
           AND COLUMN_NAME IN ('is_for_sale','price_type','sale_price','unit')"
);
$hasCol = array_column($inventoryCols, 'COLUMN_NAME', 'COLUMN_NAME');
$hasIsForSale = isset($hasCol['is_for_sale']);
$hasPriceType = isset($hasCol['price_type']);
$hasSalePrice = isset($hasCol['sale_price']);
$hasUnit = isset($hasCol['unit']);

// Data
$categories = Database::fetchAll("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order, name");
$pageTitle = "Shop Ban Hang";

// Filters
$where = ["i.status = 'available'"];
$params = [];
if ($hasIsForSale) {
    $where[] = "i.is_for_sale = 1";
}
if ($category_id > 0) {
    $where[] = "i.category_id = ?";
    $params[] = $category_id;
}
if ($price_type !== '' && $hasPriceType) {
    $where[] = "i.price_type = ?";
    $params[] = $price_type;
}
if (!empty($search)) {
    $where[] = "(i.name LIKE ? OR i.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereClause = implode(' AND ', $where);

// Count
$countSql = "SELECT COUNT(*) as count FROM inventory i WHERE $whereClause";
$totalItems = (int)Database::fetch($countSql, $params)['count'];
$totalPages = max(1, (int)ceil($totalItems / $per_page));

// Sort
$orderBy = 'i.created_at DESC';
switch ($sort) {
    case 'oldest':
        $orderBy = 'i.created_at ASC';
        break;
    case 'name':
        $orderBy = 'i.name ASC';
        break;
    case 'price_asc':
        if ($hasSalePrice) $orderBy = 'i.sale_price ASC';
        break;
    case 'price_desc':
        if ($hasSalePrice) $orderBy = 'i.sale_price DESC';
        break;
    default:
        $orderBy = 'i.created_at DESC';
}

// Select fields with fallbacks if columns missing
$priceTypeSelect = $hasPriceType ? "i.price_type" : "'free' AS price_type";
$salePriceSelect = $hasSalePrice ? "i.sale_price" : "0 AS sale_price";
$unitSelect = $hasUnit ? "IFNULL(i.unit, 'Cai')" : "'Cai' AS unit";

// Items
$sql = "SELECT 
            i.*,
            $priceTypeSelect,
            $salePriceSelect,
            $unitSelect,
            c.name AS category_name,
            c.icon AS category_icon,
            GREATEST(i.quantity - COALESCE((SELECT SUM(quantity) FROM cart WHERE item_id = i.item_id), 0), 0) AS available_quantity
        FROM inventory i
        LEFT JOIN categories c ON i.category_id = c.category_id
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";
$paramsWithLimit = array_merge($params, [$per_page, $offset]);
$items = Database::fetchAll($sql, $paramsWithLimit);

include 'includes/header.php';
?>

<style>
/* ===== SHOP PAGE ===== */
.shop-page { background: #f4fafd; min-height: 100vh; }

/* Hero */
.shop-hero {
    background: linear-gradient(135deg, #0e7490 0%, #155e75 100%);
    padding: 64px 0 48px;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.shop-hero::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(circle at 80% 50%, rgba(255,255,255,0.07) 0%, transparent 60%);
}
.shop-hero h1 { font-size: clamp(1.8rem,4vw,2.8rem); font-weight: 900; }
.shop-hero p  { opacity: .88; font-size: 1.05rem; }
.shop-hero .hero-icon {
    width: 90px; height: 90px;
    background: rgba(255,255,255,0.15);
    border-radius: 24px;
    display: flex; align-items: center; justify-content: center;
    font-size: 2.6rem;
    backdrop-filter: blur(6px);
    border: 1px solid rgba(255,255,255,0.25);
}

/* Sidebar */
.shop-sidebar .filter-box {
    background: #fff;
    border: 1px solid #cce6ef;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 18px rgba(14,116,144,0.07);
    position: sticky;
    top: 96px;
    max-height: calc(100vh - 112px);
    overflow-y: auto;
}
.shop-sidebar { align-self: flex-start; }
.shop-sidebar .filter-title {
    font-size: .75rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .08em;
    color: #0e7490; margin-bottom: .6rem;
}
.shop-sidebar .form-control:focus,
.shop-sidebar .form-select:focus {
    border-color: #0e7490;
    box-shadow: 0 0 0 .18rem rgba(14,116,144,.18);
}
.btn-filter-apply {
    background: linear-gradient(135deg, #0e7490, #155e75);
    color: #fff; border: none; border-radius: 10px;
    font-weight: 700; width: 100%; padding: .65rem;
    transition: filter .2s;
}
.btn-filter-apply:hover { filter: brightness(.92); color: #fff; }
.btn-filter-reset {
    border: 1.5px solid #cce6ef; color: #64748b;
    border-radius: 10px; font-weight: 600;
    width: 100%; padding: .6rem;
    background: #fff; transition: all .2s;
}
.btn-filter-reset:hover { border-color: #0e7490; color: #0e7490; }

/* Keep sticky sidebar usable on small screens */
@media (max-width: 991.98px) {
    .shop-sidebar .filter-box {
        position: static;
        max-height: none;
        overflow: visible;
    }
}

/* Results bar */
.results-bar {
    background: #fff; border: 1px solid #cce6ef;
    border-radius: 12px; padding: .8rem 1.2rem;
    box-shadow: 0 2px 10px rgba(14,116,144,0.06);
}

/* Product card */
.product-card {
    background: #fff;
    border: 1px solid #cce6ef;
    border-radius: 16px;
    overflow: hidden;
    transition: box-shadow .25s, transform .25s;
    height: 100%;
    display: flex; flex-direction: column;
}
.product-card:hover {
    box-shadow: 0 14px 36px rgba(14,116,144,0.14);
    transform: translateY(-4px);
}
.product-card .img-wrap {
    position: relative; overflow: hidden;
    background: #f0f8fb;
}
.product-card .img-wrap img {
    width: 100%; height: 200px; object-fit: cover;
    transition: transform .35s;
}
.product-card:hover .img-wrap img { transform: scale(1.05); }
.product-card .price-badge {
    position: absolute; top: 10px; left: 10px;
    padding: .28rem .7rem; border-radius: 999px;
    font-size: .78rem; font-weight: 700;
}
.product-card .qty-badge {
    position: absolute; top: 10px; right: 10px;
    background: rgba(0,0,0,0.55); color: #fff;
    padding: .25rem .6rem; border-radius: 999px;
    font-size: .75rem; font-weight: 600;
    backdrop-filter: blur(4px);
}
.product-card .card-body {
    padding: 1rem 1.1rem 1.1rem;
    flex: 1; display: flex; flex-direction: column;
}
.product-card .item-name {
    font-weight: 700; font-size: .95rem; color: #0d1f27;
    margin-bottom: .3rem;
    display: -webkit-box; -webkit-line-clamp: 2;
    -webkit-box-orient: vertical; overflow: hidden;
}
.product-card .item-cat {
    font-size: .78rem; color: #64748b; margin-bottom: .5rem;
}
.product-card .item-desc {
    font-size: .82rem; color: #94a3b8;
    flex: 1; margin-bottom: .8rem;
    display: -webkit-box; -webkit-line-clamp: 2;
    -webkit-box-orient: vertical; overflow: hidden;
}
.product-card .item-price {
    font-size: 1.1rem; font-weight: 800;
    color: #0e7490; margin-bottom: .75rem;
}
.btn-card-view {
    flex: 1; border: 1.5px solid #0e7490; color: #0e7490;
    border-radius: 9px; font-weight: 600; font-size: .85rem;
    padding: .5rem; background: transparent; transition: all .2s;
    text-align: center; text-decoration: none; display: inline-flex;
    align-items: center; justify-content: center;
}
.btn-card-view:hover { background: #0e7490; color: #fff; }
.btn-card-cart {
    width: 42px; height: 42px; border-radius: 9px;
    background: linear-gradient(135deg,#0e7490,#155e75);
    color: #fff; border: none; font-size: 1.05rem;
    transition: all .2s; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center;
    text-decoration: none;
}
.btn-card-cart:hover { filter: brightness(.9); transform: translateY(-2px); color: #fff; }
.btn-card-cart:disabled { background: #cbd5e1; cursor: not-allowed; transform: none; }

/* Empty state */
.empty-state { background: #fff; border: 1px solid #cce6ef; border-radius: 18px; padding: 4rem 2rem; }

/* Pagination */
.pagination .page-link {
    color: #0e7490; border-color: #cce6ef; border-radius: 8px !important;
    margin: 0 2px; font-weight: 600;
}
.pagination .page-item.active .page-link {
    background: linear-gradient(135deg,#0e7490,#155e75);
    border-color: #0e7490; color: #fff;
}
.pagination .page-link:hover { background: #f0f8fb; color: #0e7490; }
</style>

<!-- Hero -->
<div class="shop-hero">
    <div class="container">
        <div class="d-flex align-items-center gap-4">
            <div class="hero-icon d-none d-md-flex">
                <i class="bi bi-shop"></i>
            </div>
            <div>
                <h1 class="mb-1">Shop Bán Hàng</h1>
                <p class="mb-0">Khám phá và nhận các vật phẩm hỗ trợ từ cộng đồng Goodwill</p>
            </div>
        </div>
    </div>
</div>

<div class="shop-page py-5">
<div class="container">
<div class="row g-4">

    <!-- Sidebar Filters -->
    <div class="col-xl-3 col-lg-3 shop-sidebar">
        <div class="filter-box">
            <form method="GET" id="filterForm">
                <div class="filter-title"><i class="bi bi-search me-1"></i>Tìm kiếm</div>
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0" style="border-color:#cce6ef;">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-0" name="search"
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Tên sản phẩm..."
                               style="border-color:#cce6ef;">
                    </div>
                </div>

                <div class="filter-title"><i class="bi bi-grid me-1"></i>Danh mục</div>
                <div class="mb-3">
                    <select class="form-select form-select-sm" name="category" style="border-color:#cce6ef;">
                        <option value="">Tất cả danh mục</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>"
                                <?php echo ($category_id == $cat['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($hasPriceType): ?>
                <div class="filter-title"><i class="bi bi-tag me-1"></i>Loại giá</div>
                <div class="mb-3">
                    <select class="form-select form-select-sm" name="price_type" style="border-color:#cce6ef;">
                        <option value="">Tất cả</option>
                        <option value="free"   <?php echo ($price_type==='free')  ?'selected':''; ?>>Miễn phí</option>
                        <option value="cheap"  <?php echo ($price_type==='cheap') ?'selected':''; ?>>Giá rẻ</option>
                        <option value="normal" <?php echo ($price_type==='normal')?'selected':''; ?>>Giá thường</option>
                    </select>
                </div>
                <?php endif; ?>

                <div class="filter-title"><i class="bi bi-sort-down me-1"></i>Sắp xếp</div>
                <div class="mb-4">
                    <select class="form-select form-select-sm" name="sort" style="border-color:#cce6ef;">
                        <option value="newest" <?php echo ($sort==='newest')?'selected':''; ?>>Mới nhất</option>
                        <option value="oldest" <?php echo ($sort==='oldest')?'selected':''; ?>>Cũ nhất</option>
                        <option value="name"   <?php echo ($sort==='name')  ?'selected':''; ?>>Tên A-Z</option>
                        <?php if ($hasSalePrice): ?>
                        <option value="price_asc"  <?php echo ($sort==='price_asc') ?'selected':''; ?>>Giá thấp → cao</option>
                        <option value="price_desc" <?php echo ($sort==='price_desc')?'selected':''; ?>>Giá cao → thấp</option>
                        <?php endif; ?>
                    </select>
                </div>

                <button type="submit" class="btn-filter-apply mb-2">
                    <i class="bi bi-funnel-fill me-2"></i>Áp dụng bộ lọc
                </button>
                <a href="shop.php" class="btn-filter-reset text-center d-block text-decoration-none">
                    <i class="bi bi-x-circle me-1"></i>Xóa bộ lọc
                </a>
            </form>
        </div>
    </div>

    <!-- Main Content -->
    <div class="col-xl-9 col-lg-9">
        <!-- Results bar -->
        <div class="results-bar d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <span class="text-muted" style="font-size:.9rem;">
                Hiển thị <strong class="text-dark"><?php echo count($items); ?></strong>
                / <strong class="text-dark"><?php echo $totalItems; ?></strong> sản phẩm
                <?php if (!empty($search)): ?>
                    cho &ldquo;<strong><?php echo htmlspecialchars($search); ?></strong>&rdquo;
                <?php endif; ?>
            </span>
            <?php if (!empty($search) || $category_id || $price_type): ?>
            <div class="d-flex flex-wrap gap-2">
                <?php if (!empty($search)): ?>
                    <span class="badge rounded-pill" style="background:#e0f5fa;color:#0e7490;">
                        <i class="bi bi-search me-1"></i><?php echo htmlspecialchars($search); ?>
                    </span>
                <?php endif; ?>
                <?php if ($category_id):
                    $activeCat = array_filter($categories, fn($c)=>$c['category_id']==$category_id);
                    $activeCat = reset($activeCat); ?>
                    <span class="badge rounded-pill" style="background:#e0f5fa;color:#0e7490;">
                        <i class="bi bi-grid me-1"></i><?php echo htmlspecialchars($activeCat['name'] ?? ''); ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

    <!-- Items Grid -->
        <!-- Items Grid -->
            <?php if (empty($items)): ?>
                <div class="empty-state text-center">
                    <i class="bi bi-search display-2 mb-3 d-block" style="color:#cce6ef;"></i>
                    <h4 class="fw-bold mb-2">Không tìm thấy sản phẩm nào</h4>
                    <p class="text-muted mb-4">Thử thay đổi bộ lọc hoặc từ khoá tìm kiếm</p>
                    <a href="shop.php" class="btn px-4 py-2 fw-bold"
                       style="background:linear-gradient(135deg,#0e7490,#155e75);color:#fff;border-radius:10px;">
                        Xem tất cả sản phẩm
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($items as $item):
                        $images = json_decode($item['images'] ?? '[]', true);
                        $firstImage = !empty($images) ? 'uploads/donations/' . $images[0] : 'uploads/donations/placeholder-default.svg';
                        $priceDisplay = 'Liên hệ';
                        $badgeBg = '#0ea5e9'; $badgeColor = '#fff';
                        if ($item['price_type'] === 'free') {
                            $priceDisplay = 'Miễn phí'; $badgeBg = '#10b981';
                        } elseif (in_array($item['price_type'], ['cheap','normal'])) {
                            if ((float)$item['sale_price'] > 0) {
                                $priceDisplay = number_format($item['sale_price']) . ' ₫';
                            }
                            $badgeBg = ($item['price_type'] === 'cheap') ? '#f59e0b' : '#6366f1';
                            if ($item['price_type'] === 'cheap') $badgeColor = '#1e1a03';
                        }
                        $availableQty = max(0, (int)$item['available_quantity']);
                    ?>
                    <div class="col-lg-4 col-md-6 col-sm-6">
                        <div class="product-card">
                            <div class="img-wrap">
                                <img src="<?php echo htmlspecialchars($firstImage); ?>"
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     onerror="this.src='uploads/donations/placeholder-default.svg'">
                                <span class="price-badge" style="background:<?php echo $badgeBg;?>;color:<?php echo $badgeColor;?>;">
                                    <?php echo $priceDisplay; ?>
                                </span>
                                <span class="qty-badge">
                                    <i class="bi bi-box me-1"></i><?php echo $availableQty; ?> <?php echo htmlspecialchars($item['unit']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="item-cat">
                                    <i class="bi bi-tag me-1"></i><?php echo htmlspecialchars($item['category_name'] ?? 'Khác'); ?>
                                </div>
                                <div class="item-desc"><?php echo htmlspecialchars($item['description'] ?? ''); ?></div>
                                <div class="item-price"><?php echo $priceDisplay; ?></div>
                                <div class="d-flex gap-2">
                                    <a href="item-detail.php?id=<?php echo $item['item_id']; ?>" class="btn-card-view">
                                        <i class="bi bi-eye me-1"></i>Xem chi tiết
                                    </a>
                                    <?php if (isLoggedIn()): ?>
                                        <?php if ($availableQty > 0): ?>
                                            <button type="button" class="btn-card-cart add-to-cart"
                                                    data-item-id="<?php echo $item['item_id']; ?>" title="Thêm vào giỏ">
                                                <i class="bi bi-cart-plus"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn-card-cart" disabled title="Hết hàng">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="login.php?redirect=shop.php" class="btn-card-cart" title="Đăng nhập để mua">
                                            <i class="bi bi-lock"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="mt-5" aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page<=1)?'disabled':''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page-1])); ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            <?php for ($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?>
                                <li class="page-item <?php echo ($i==$page)?'active':''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo ($page>=$totalPages)?'disabled':''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET,['page'=>$page+1])); ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                        <p class="text-center text-muted mt-2" style="font-size:.85rem;">
                            Trang <?php echo $page; ?> / <?php echo $totalPages; ?>
                        </p>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>

        </div><!-- end main col -->
    </div><!-- end row -->
    </div><!-- end container -->
    </div><!-- end shop-page -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.dataset.itemId;
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
            fetch('api/add-to-cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ item_id: itemId, quantity: 1 })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Đã thêm vào giỏ hàng!', 'success');
                    updateCartCount();
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showToast('Lỗi: ' + (data.message || 'Không thể thêm vào giỏ hàng'), 'danger');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-cart-plus"></i>';
                }
            })
            .catch(() => {
                showToast('Có lỗi xảy ra', 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-cart-plus"></i>';
            });
        });
    });

    function showToast(message, type) {
        const el = document.createElement('div');
        el.className = `alert alert-${type} alert-dismissible fade show position-fixed shadow`;
        el.style.cssText = 'top:80px;right:20px;z-index:9999;min-width:260px;border-radius:12px;';
        el.innerHTML = `<i class="bi bi-${type==='success'?'check-circle':'exclamation-triangle'} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3000);
    }

    function updateCartCount() {
        fetch('api/get-cart-count.php')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('cart-count');
                    if (badge) badge.textContent = data.count;
                }
            });
    }
    updateCartCount();
});
</script>

<?php include 'includes/footer.php'; ?>
