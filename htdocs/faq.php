<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "FAQ";
include 'includes/header.php';
?>
<style>
    :root {
        --faq-ink: #163246;
        --faq-muted: #607c90;
        --faq-line: #d1e7ee;
        --faq-brand: #1b8097;
        --faq-brand-dark: #176b81;
        --faq-shadow: 0 18px 42px rgba(16, 92, 112, .10);
    }

    .faq-page {
        padding-top: 0;
        padding-bottom: 3.1rem;
        background:
            radial-gradient(circle at top left, rgba(27, 128, 151, .10), transparent 24%),
            linear-gradient(180deg, #f6fafb 0%, #edf6f8 100%);
    }

    .faq-hero {
        margin-left: calc(50% - 50vw);
        margin-right: calc(50% - 50vw);
        padding: 3.1rem 0 2.7rem;
        background:
            radial-gradient(circle at top right, rgba(255,255,255,.14), transparent 24%),
            linear-gradient(140deg, #1b8097 0%, #187086 52%, #225e73 100%);
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    .faq-hero-inner {
        max-width: 1320px;
        margin: 0 auto;
        padding: 0 1rem;
        display: flex;
        align-items: center;
        gap: 1.4rem;
    }
    .faq-hero-icon {
        width: 108px;
        height: 108px;
        border-radius: 26px;
        border: 1px solid rgba(255,255,255,.24);
        background: linear-gradient(180deg, rgba(255,255,255,.17), rgba(255,255,255,.08));
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 3.1rem;
        box-shadow: 0 18px 34px rgba(7, 49, 59, .2);
        flex-shrink: 0;
    }
    .faq-hero h1 {
        margin: 0;
        font-size: clamp(2.2rem, 4vw, 4rem);
        font-weight: 900;
        line-height: .98;
        letter-spacing: -.04em;
    }
    .faq-hero p {
        margin: .8rem 0 0;
        font-size: clamp(1rem, 1.4vw, 1.24rem);
        color: rgba(255,255,255,.93);
        max-width: 920px;
    }
    .faq-hero-chips {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        margin-top: 1.25rem;
    }
    .faq-chip {
        min-height: 50px;
        padding: 0 1.15rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: .6rem;
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.27);
        color: #fff;
        font-weight: 800;
        font-size: .96rem;
        backdrop-filter: blur(3px);
    }

    .faq-content {
        margin-top: 1.9rem;
    }
    .faq-card {
        border: 1px solid var(--faq-line);
        border-radius: 24px;
        background: rgba(255,255,255,.98);
        box-shadow: var(--faq-shadow);
        overflow: hidden;
        position: relative;
    }
    .faq-card::before {
        content: '';
        position: absolute;
        inset: 0 0 auto 0;
        height: 4px;
        background: linear-gradient(90deg, var(--faq-brand), #4db4ca);
    }
    .faq-card-head {
        padding: 1rem 1.2rem .9rem;
        border-bottom: 1px solid #d9ecf1;
        background: linear-gradient(180deg, #fbfeff 0%, #eff8fa 100%);
    }
    .faq-card-title {
        margin: 0;
        color: var(--faq-ink);
        font-weight: 900;
        font-size: 1.22rem;
    }
    .faq-card-body {
        padding: 1.1rem 1.2rem 1.2rem;
    }

    .accordion-item {
        border: 1px solid #dcebF1;
        border-radius: 14px !important;
        overflow: hidden;
        margin-bottom: .7rem;
    }
    .accordion-button {
        font-weight: 700;
        color: #1a3a50;
        background: #fbfeff;
        box-shadow: none !important;
        padding: .95rem 1rem;
    }
    .accordion-button:not(.collapsed) {
        color: #145f77;
        background: #ecf8fb;
    }
    .accordion-body {
        color: #3f5f74;
        line-height: 1.62;
        background: #fff;
    }

    .faq-support-grid {
        display: grid;
        gap: 1rem;
    }
    .faq-support-note {
        color: var(--faq-muted);
        margin-bottom: .95rem;
    }
    .faq-contact-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        gap: .75rem;
    }
    .faq-contact-list li {
        display: flex;
        align-items: center;
        gap: .7rem;
        border: 1px solid #deedf2;
        border-radius: 14px;
        padding: .76rem .85rem;
        background: linear-gradient(180deg, #fff 0%, #f7fcfd 100%);
        color: #35546a;
        font-weight: 600;
    }
    .faq-contact-list i {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(145deg, var(--faq-brand), var(--faq-brand-dark));
        color: #fff;
        font-size: .95rem;
    }

    .faq-quick-links {
        display: grid;
        gap: .62rem;
        margin-top: .9rem;
    }
    .faq-quick-link {
        border: 1px solid #d7eaf0;
        border-radius: 12px;
        padding: .68rem .8rem;
        background: linear-gradient(180deg, #ffffff, #f6fbfd);
        text-decoration: none;
        color: #2d5369;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .faq-quick-link:hover {
        color: #1b8097;
        border-color: #b9dce6;
    }

    .faq-cta {
        border-radius: 14px;
        padding: .72rem 1.2rem;
        border: 0;
        font-weight: 800;
        background: linear-gradient(145deg, var(--faq-brand), var(--faq-brand-dark));
        color: #fff;
        box-shadow: 0 12px 24px rgba(21, 100, 121, .22);
    }
    .faq-cta:hover {
        color: #fff;
        filter: brightness(.97);
    }

    @media (max-width: 991.98px) {
        .faq-hero {
            padding: 2.2rem 0 2rem;
        }
        .faq-hero-icon {
            width: 84px;
            height: 84px;
            border-radius: 22px;
            font-size: 2.35rem;
        }
    }

    @media (max-width: 767.98px) {
        .faq-hero-inner {
            align-items: flex-start;
            gap: 1rem;
        }
        .faq-hero-icon {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            font-size: 2rem;
        }
        .faq-chip {
            min-height: 46px;
            font-size: .92rem;
            padding: 0 .95rem;
        }
        .faq-content {
            margin-top: 1.25rem;
        }
    }
</style>

<main class="faq-page">
    <section class="faq-hero">
        <div class="faq-hero-inner">
            <div class="faq-hero-icon"><i class="bi bi-question-circle-fill"></i></div>
            <div>
                <h1>Câu hỏi thường gặp</h1>
                <p>Giải đáp nhanh về quyên góp, mua sắm thiện nguyện, chiến dịch và quản lý tài khoản trên Goodwill Vietnam.</p>
                <div class="faq-hero-chips">
                    <span class="faq-chip"><i class="bi bi-search"></i>Tra cứu nhanh</span>
                    <span class="faq-chip"><i class="bi bi-check2-circle"></i>Nội dung rõ ràng</span>
                    <span class="faq-chip"><i class="bi bi-chat-dots"></i>Hỗ trợ khi cần</span>
                </div>
            </div>
        </div>
    </section>

    <section class="container faq-content">
        <div class="row g-4">
            <div class="col-lg-8">
                <article class="faq-card">
                    <div class="faq-card-head">
                        <h3 class="faq-card-title">Kho câu hỏi chính</h3>
                    </div>
                    <div class="faq-card-body">
                        <div class="accordion" id="faqAccordionMain">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqDonate">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqDonateBody">
                                        Quyên góp vật phẩm thế nào?
                                    </button>
                                </h2>
                                <div id="faqDonateBody" class="accordion-collapse collapse show" data-bs-parent="#faqAccordionMain">
                                    <div class="accordion-body">
                                        Mở trang <a href="donate.php">Quyên góp</a>, nhập thông tin vật phẩm, tối đa 5 ảnh, chọn danh mục và thời gian nhận. Đơn sẽ chờ admin duyệt trước khi vào kho.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqShop">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqShopBody">
                                        Mua hàng thiện nguyện hoạt động ra sao?
                                    </button>
                                </h2>
                                <div id="faqShopBody" class="accordion-collapse collapse" data-bs-parent="#faqAccordionMain">
                                    <div class="accordion-body">
                                        Xem <a href="items.php">Vật phẩm</a> (lọc danh mục/loại giá), thêm vào giỏ, checkout với địa chỉ nhận. Hệ thống trừ kho, đánh dấu sold khi hết, tạo đơn; theo dõi tại <a href="my-orders.php">Đơn của tôi</a>.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqCampaign">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCampaignBody">
                                        Chiến dịch và tình nguyện viên?
                                    </button>
                                </h2>
                                <div id="faqCampaignBody" class="accordion-collapse collapse" data-bs-parent="#faqAccordionMain">
                                    <div class="accordion-body">
                                        Vào <a href="campaigns.php">Chiến dịch</a> hoặc trang chi tiết để xem nhu cầu, tiến độ. Bạn có thể đăng ký tình nguyện viên và đóng góp vật phẩm cần thiết trực tiếp từ danh sách.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqAccount">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqAccountBody">
                                        Vấn đề đăng nhập / tài khoản?
                                    </button>
                                </h2>
                                <div id="faqAccountBody" class="accordion-collapse collapse" data-bs-parent="#faqAccordionMain">
                                    <div class="accordion-body">
                                        Dùng <a href="forgot-password.php">Quên mật khẩu</a> để đặt lại. Nếu vẫn lỗi, liên hệ <a href="contact.php">Liên hệ</a>. Đăng ký tài khoản mới tại <a href="register.php">Đăng ký</a>.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqStatus">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqStatusBody">
                                        Theo dõi tiến trình đơn/đóng góp ở đâu?
                                    </button>
                                </h2>
                                <div id="faqStatusBody" class="accordion-collapse collapse" data-bs-parent="#faqAccordionMain">
                                    <div class="accordion-body">
                                        Đơn hàng: xem <a href="order-tracking.php">Theo dõi đơn</a> hoặc <a href="my-orders.php">Đơn của tôi</a>. Quyên góp: xem <a href="donation-tracking.php">Timeline xử lý</a> và <a href="my-donations.php">Lịch sử quyên góp</a>.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqSupport">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqSupportBody">
                                        Không tìm thấy câu trả lời?
                                    </button>
                                </h2>
                                <div id="faqSupportBody" class="accordion-collapse collapse" data-bs-parent="#faqAccordionMain">
                                    <div class="accordion-body">
                                        Xem thêm tại <a href="help.php">Trợ giúp</a> hoặc liên hệ trực tiếp: support@goodwillvietnam.com, hotline (+84) 1900 0000.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <div class="col-lg-4 faq-support-grid">
                <article class="faq-card">
                    <div class="faq-card-head">
                        <h3 class="faq-card-title">Kênh hỗ trợ</h3>
                    </div>
                    <div class="faq-card-body">
                        <p class="faq-support-note">Gặp lỗi kỹ thuật hoặc cần hợp tác, hãy liên hệ đội ngũ Goodwill Vietnam.</p>
                        <ul class="faq-contact-list mb-0">
                            <li><i class="bi bi-envelope-fill"></i>support@goodwillvietnam.com</li>
                            <li><i class="bi bi-telephone-fill"></i>(+84) 1900 0000</li>
                            <li><i class="bi bi-geo-alt-fill"></i>Hà Nội, Việt Nam</li>
                        </ul>
                        <a href="contact.php" class="btn faq-cta w-100 mt-3">Đi đến trang Liên hệ</a>
                    </div>
                </article>

                <article class="faq-card">
                    <div class="faq-card-head">
                        <h3 class="faq-card-title">Lối tắt hữu ích</h3>
                    </div>
                    <div class="faq-card-body">
                        <div class="faq-quick-links">
                            <a class="faq-quick-link" href="help.php">Trung tâm trợ giúp <i class="bi bi-arrow-right"></i></a>
                            <a class="faq-quick-link" href="donation-tracking.php">Theo dõi quyên góp <i class="bi bi-arrow-right"></i></a>
                            <a class="faq-quick-link" href="order-tracking.php">Theo dõi đơn hàng <i class="bi bi-arrow-right"></i></a>
                            <a class="faq-quick-link" href="profile.php">Quản lý hồ sơ <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
