<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Liên hệ";
include 'includes/header.php';
?>
<style>
    :root {
        --contact-ink: #163246;
        --contact-muted: #607c90;
        --contact-line: #d1e7ee;
        --contact-brand: #1b8097;
        --contact-brand-dark: #166b81;
        --contact-shadow: 0 18px 42px rgba(16, 92, 112, .10);
    }

    .contact-page {
        padding-top: 0;
        padding-bottom: 3.1rem;
        background:
            radial-gradient(circle at top left, rgba(27, 128, 151, .10), transparent 24%),
            linear-gradient(180deg, #f6fafb 0%, #edf6f8 100%);
    }

    .contact-hero {
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
    .contact-hero-inner {
        max-width: 1320px;
        margin: 0 auto;
        padding: 0 1rem;
        display: flex;
        align-items: center;
        gap: 1.4rem;
    }
    .contact-hero-icon {
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
    .contact-hero h1 {
        margin: 0;
        font-size: clamp(2.2rem, 4vw, 4rem);
        font-weight: 900;
        line-height: .98;
        letter-spacing: -.04em;
    }
    .contact-hero p {
        margin: .8rem 0 0;
        font-size: clamp(1rem, 1.4vw, 1.22rem);
        color: rgba(255,255,255,.93);
        max-width: 920px;
    }
    .contact-hero-chips {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        margin-top: 1.25rem;
    }
    .contact-chip {
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

    .contact-content {
        margin-top: 1.9rem;
    }
    .contact-card {
        border: 1px solid var(--contact-line);
        border-radius: 24px;
        background: rgba(255,255,255,.98);
        box-shadow: var(--contact-shadow);
        overflow: hidden;
        position: relative;
        height: 100%;
    }
    .contact-card::before {
        content: '';
        position: absolute;
        inset: 0 0 auto 0;
        height: 4px;
        background: linear-gradient(90deg, var(--contact-brand), #4db4ca);
    }
    .contact-card-head {
        padding: 1rem 1.2rem .9rem;
        border-bottom: 1px solid #d9ecf1;
        background: linear-gradient(180deg, #fbfeff 0%, #eff8fa 100%);
    }
    .contact-card-title {
        margin: 0;
        color: var(--contact-ink);
        font-weight: 900;
        font-size: 1.22rem;
    }
    .contact-card-body {
        padding: 1.1rem 1.2rem 1.2rem;
    }

    .contact-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: .8rem;
    }
    .contact-item {
        display: flex;
        align-items: center;
        gap: .75rem;
        border: 1px solid #deedf2;
        border-radius: 14px;
        padding: .8rem .9rem;
        background: linear-gradient(180deg, #fff 0%, #f7fcfd 100%);
    }
    .contact-item i {
        width: 36px;
        height: 36px;
        border-radius: 11px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(145deg, var(--contact-brand), var(--contact-brand-dark));
        color: #fff;
        font-size: .98rem;
        flex-shrink: 0;
    }
    .contact-item-label {
        color: #1f455d;
        font-weight: 800;
        margin-bottom: .05rem;
    }
    .contact-item-value {
        color: var(--contact-muted);
        font-size: .94rem;
    }

    .contact-form-note {
        color: var(--contact-muted);
        margin-bottom: .9rem;
    }
    .form-label {
        color: #1f455d;
        font-weight: 700;
        margin-bottom: .42rem;
    }
    .form-control {
        min-height: 48px;
        border-radius: 14px;
        border: 1.5px solid #d2e8ee;
        background: #fbfeff;
        color: #17364b;
        padding: .72rem .9rem;
        box-shadow: none;
    }
    textarea.form-control {
        min-height: 140px;
    }
    .form-control:focus {
        border-color: var(--contact-brand);
        box-shadow: 0 0 0 4px rgba(27, 128, 151, .12);
        background: #fff;
    }
    .contact-submit {
        width: 100%;
        min-height: 52px;
        border-radius: 14px;
        border: 0;
        font-weight: 800;
        background: linear-gradient(145deg, var(--contact-brand), var(--contact-brand-dark));
        color: #fff;
        box-shadow: 0 12px 24px rgba(21, 100, 121, .22);
        opacity: .72;
    }
    .contact-muted-help {
        color: var(--contact-muted);
        font-size: .86rem;
        margin-top: .65rem;
        margin-bottom: 0;
    }

    @media (max-width: 991.98px) {
        .contact-hero {
            padding: 2.2rem 0 2rem;
        }
        .contact-hero-icon {
            width: 84px;
            height: 84px;
            border-radius: 22px;
            font-size: 2.35rem;
        }
    }

    @media (max-width: 767.98px) {
        .contact-hero-inner {
            align-items: flex-start;
            gap: 1rem;
        }
        .contact-hero-icon {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            font-size: 2rem;
        }
        .contact-chip {
            min-height: 46px;
            font-size: .92rem;
            padding: 0 .95rem;
        }
        .contact-content {
            margin-top: 1.25rem;
        }
    }
</style>

<main class="contact-page">
    <section class="contact-hero">
        <div class="contact-hero-inner">
            <div class="contact-hero-icon"><i class="bi bi-chat-dots-fill"></i></div>
            <div>
                <h1>Liên hệ Goodwill Vietnam</h1>
                <p>Kết nối để đồng hành cùng cộng đồng: đóng góp, hợp tác tổ chức, truyền thông hay hỗ trợ kỹ thuật.</p>
                <div class="contact-hero-chips">
                    <span class="contact-chip"><i class="bi bi-people"></i>Hợp tác cộng đồng</span>
                    <span class="contact-chip"><i class="bi bi-chat-left-text"></i>Phản hồi nhanh</span>
                    <span class="contact-chip"><i class="bi bi-headset"></i>Hỗ trợ tận tâm</span>
                </div>
            </div>
        </div>
    </section>

    <section class="container contact-content">
        <div class="row g-4">
            <div class="col-lg-5">
                <article class="contact-card">
                    <div class="contact-card-head">
                        <h3 class="contact-card-title">Thông tin liên hệ</h3>
                    </div>
                    <div class="contact-card-body">
                        <ul class="contact-list">
                            <li class="contact-item">
                                <i class="bi bi-geo-alt-fill"></i>
                                <div>
                                    <div class="contact-item-label">Văn phòng</div>
                                    <div class="contact-item-value">Hà Nội, Việt Nam</div>
                                </div>
                            </li>
                            <li class="contact-item">
                                <i class="bi bi-envelope-fill"></i>
                                <div>
                                    <div class="contact-item-label">Email</div>
                                    <div class="contact-item-value">support@goodwillvietnam.com</div>
                                </div>
                            </li>
                            <li class="contact-item">
                                <i class="bi bi-telephone-fill"></i>
                                <div>
                                    <div class="contact-item-label">Hotline</div>
                                    <div class="contact-item-value">(+84) 1900 0000</div>
                                </div>
                            </li>
                            <li class="contact-item">
                                <i class="bi bi-clock-fill"></i>
                                <div>
                                    <div class="contact-item-label">Giờ làm việc</div>
                                    <div class="contact-item-value">Thứ 2 - Thứ 6, 08:30 - 17:30</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </article>
            </div>

            <div class="col-lg-7">
                <article class="contact-card">
                    <div class="contact-card-head">
                        <h3 class="contact-card-title">Gửi lời nhắn</h3>
                    </div>
                    <div class="contact-card-body">
                        <p class="contact-form-note">Chúng tôi đang nâng cấp form tự động. Bạn vẫn có thể xem mẫu nội dung và liên hệ qua email/điện thoại.</p>
                        <form>
                            <div class="mb-3">
                                <label class="form-label">Họ tên</label>
                                <input type="text" class="form-control" placeholder="Nguyễn Văn A">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" placeholder="ban@email.com">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nội dung</label>
                                <textarea class="form-control" rows="4" placeholder="Bạn muốn trao đổi điều gì?"></textarea>
                            </div>
                            <button type="button" class="btn contact-submit" disabled>Gửi (đang cập nhật)</button>
                        </form>
                        <p class="contact-muted-help">Hiện form đang bật chế độ xem; vui lòng liên hệ qua email/điện thoại.</p>
                    </div>
                </article>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
