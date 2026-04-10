<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Trợ giúp";
include 'includes/header.php';
?>
<style>
    :root {
        --help-ink: #163246;
        --help-muted: #5f7b90;
        --help-line: #cfe5ec;
        --help-brand: #1b8097;
        --help-brand-dark: #166b81;
        --help-bg-soft: #eef7f9;
        --help-shadow: 0 18px 42px rgba(16, 92, 112, .10);
    }

    .help-page {
        padding-top: 0;
        padding-bottom: 3.2rem;
        background:
            radial-gradient(circle at top left, rgba(27, 128, 151, .10), transparent 22%),
            linear-gradient(180deg, #f5fafb 0%, #edf6f8 100%);
    }

    .help-hero {
        margin-left: calc(50% - 50vw);
        margin-right: calc(50% - 50vw);
        background:
            radial-gradient(circle at top left, rgba(255,255,255,.11), transparent 25%),
            linear-gradient(140deg, #1b8097 0%, #187288 50%, #205f73 100%);
        color: #fff;
        padding: 3.2rem 0 2.8rem;
        position: relative;
        overflow: hidden;
        box-shadow: inset 0 -1px 0 rgba(255,255,255,.12);
    }
    .help-hero::before {
        content: '';
        position: absolute;
        right: -120px;
        top: -120px;
        width: 320px;
        height: 320px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255,255,255,.15) 0%, rgba(255,255,255,0) 70%);
        pointer-events: none;
    }
    .help-hero-inner {
        max-width: 1320px;
        margin: 0 auto;
        padding: 0 1rem;
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    .help-hero-icon {
        width: 112px;
        height: 112px;
        border-radius: 28px;
        border: 1px solid rgba(255,255,255,.24);
        background: linear-gradient(180deg, rgba(255,255,255,.16), rgba(255,255,255,.08));
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 3.2rem;
        box-shadow: 0 20px 36px rgba(8, 53, 64, .18);
        flex-shrink: 0;
    }
    .help-hero h1 {
        margin: 0;
        font-size: clamp(2.2rem, 4vw, 4rem);
        font-weight: 900;
        line-height: .98;
        letter-spacing: -.04em;
    }
    .help-hero p {
        margin: .85rem 0 0;
        font-size: clamp(1rem, 1.45vw, 1.3rem);
        color: rgba(255,255,255,.92);
        max-width: 900px;
    }
    .help-hero-chips {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        margin-top: 1.35rem;
    }
    .help-chip {
        min-height: 50px;
        border-radius: 999px;
        padding: 0 1.15rem;
        display: inline-flex;
        align-items: center;
        gap: .6rem;
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.28);
        color: #fff;
        font-weight: 800;
        font-size: .98rem;
        backdrop-filter: blur(3px);
    }

    .help-content {
        margin-top: 2rem;
    }
    .help-card {
        border: 1px solid var(--help-line);
        border-radius: 24px;
        overflow: hidden;
        background: rgba(255,255,255,.98);
        box-shadow: var(--help-shadow);
        height: 100%;
        position: relative;
    }
    .help-card::before {
        content: '';
        position: absolute;
        inset: 0 0 auto 0;
        height: 4px;
        background: linear-gradient(90deg, var(--help-brand), #48b2c8);
    }
    .help-card-head {
        padding: 1rem 1.2rem .9rem;
        border-bottom: 1px solid #d9ebf0;
        background: linear-gradient(180deg, #fbfeff 0%, #f0f8fa 100%);
    }
    .help-card-title {
        margin: 0;
        color: var(--help-ink);
        font-weight: 900;
        font-size: 1.24rem;
    }
    .help-card-body {
        padding: 1.15rem 1.2rem 1.2rem;
    }

    .accordion-item {
        border: 1px solid #dbeaf0;
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

    .help-contact-lead {
        color: var(--help-muted);
        margin-bottom: 1rem;
    }
    .help-contact-list {
        list-style: none;
        margin: 0 0 1.2rem;
        padding: 0;
        display: grid;
        gap: .75rem;
    }
    .help-contact-list li {
        display: flex;
        align-items: center;
        gap: .7rem;
        padding: .78rem .85rem;
        border-radius: 14px;
        border: 1px solid #dfeef3;
        background: linear-gradient(180deg, #ffffff 0%, #f6fbfd 100%);
        color: #35546a;
        font-weight: 600;
    }
    .help-contact-list i {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(145deg, var(--help-brand), var(--help-brand-dark));
        color: #fff;
        font-size: .96rem;
    }
    .help-cta {
        border-radius: 14px;
        padding: .72rem 1.2rem;
        border: 0;
        font-weight: 800;
        background: linear-gradient(145deg, var(--help-brand), var(--help-brand-dark));
        color: #fff;
        box-shadow: 0 12px 24px rgba(21, 100, 121, .22);
    }
    .help-cta:hover {
        color: #fff;
        filter: brightness(.97);
    }

    @media (max-width: 991.98px) {
        .help-hero {
            padding: 2.3rem 0 2rem;
        }
        .help-hero-inner {
            gap: 1rem;
        }
        .help-hero-icon {
            width: 86px;
            height: 86px;
            border-radius: 22px;
            font-size: 2.4rem;
        }
    }

    @media (max-width: 767.98px) {
        .help-hero-inner {
            align-items: flex-start;
        }
        .help-hero-icon {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            font-size: 2rem;
        }
        .help-chip {
            min-height: 46px;
            font-size: .92rem;
            padding: 0 .95rem;
        }
        .help-content {
            margin-top: 1.3rem;
        }
    }
</style>

<main class="help-page">
    <section class="help-hero">
        <div class="help-hero-inner">
            <div class="help-hero-icon">
                <i class="bi bi-life-preserver"></i>
            </div>
            <div>
                <h1>Trung tâm trợ giúp</h1>
                <p>Hướng dẫn nhanh về quyên góp, mua hàng thiện nguyện và quản lý tài khoản trên Goodwill Vietnam.</p>
                <div class="help-hero-chips">
                    <span class="help-chip"><i class="bi bi-shield-check"></i>Hướng dẫn minh bạch</span>
                    <span class="help-chip"><i class="bi bi-lightning-charge"></i>Tra cứu nhanh</span>
                    <span class="help-chip"><i class="bi bi-headset"></i>Hỗ trợ kịp thời</span>
                </div>
            </div>
        </div>
    </section>

    <section class="container help-content">
        <div class="row g-4">
            <div class="col-lg-7">
                <article class="help-card">
                    <div class="help-card-head">
                        <h3 class="help-card-title">Câu hỏi thường gặp</h3>
                    </div>
                    <div class="help-card-body">
                        <div class="accordion" id="faqAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faq1">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse1">
                                        Làm sao để gửi quyên góp?
                                    </button>
                                </h2>
                                <div id="faqCollapse1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Vào trang <a href="donate.php">Quyên góp</a>, điền thông tin vật phẩm, tối đa 5 ảnh và chọn thời gian nhận. Đơn sẽ chờ admin duyệt trước khi vào kho.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faq2">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse2">
                                        Mua hàng trên shop hoạt động thế nào?
                                    </button>
                                </h2>
                                <div id="faqCollapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Xem <a href="items.php">Vật phẩm</a>, thêm vào giỏ, checkout với địa chỉ nhận. Hệ thống trừ kho và tạo đơn; theo dõi tại <a href="my-orders.php">Đơn của tôi</a>.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faq3">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse3">
                                        Tôi muốn tham gia tình nguyện?
                                    </button>
                                </h2>
                                <div id="faqCollapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Chọn chiến dịch tại <a href="campaigns.php">Chiến dịch</a> hoặc chi tiết chiến dịch, đăng ký tình nguyện viên và để lại kỹ năng/thời gian.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faq4">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse4">
                                        Không nhận được email / thông báo?
                                    </button>
                                </h2>
                                <div id="faqCollapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Kiểm tra spam hoặc liên hệ <a href="contact.php">Liên hệ</a>. Hệ thống hiện chưa bật gửi mail tự động, team sẽ hỗ trợ thủ công.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <div class="col-lg-5">
                <article class="help-card">
                    <div class="help-card-head">
                        <h3 class="help-card-title">Liên hệ hỗ trợ</h3>
                    </div>
                    <div class="help-card-body">
                        <p class="help-contact-lead">Nếu câu hỏi chưa có ở trên, đội ngũ Goodwill Vietnam luôn sẵn sàng hỗ trợ bạn.</p>
                        <ul class="help-contact-list">
                            <li><i class="bi bi-envelope-fill"></i>support@goodwillvietnam.com</li>
                            <li><i class="bi bi-telephone-fill"></i>(+84) 1900 0000</li>
                            <li><i class="bi bi-geo-alt-fill"></i>Hà Nội, Việt Nam</li>
                        </ul>
                        <a href="contact.php" class="btn help-cta">Đi đến trang Liên hệ</a>
                    </div>
                </article>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
