    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5 class="fw-bold mb-3">
                        <i class="bi bi-heart-fill me-2"></i>Goodwill Vietnam
                    </h5>
                    <p class="text-muted">
                        Kết nối những tấm lòng nhân ái, tạo nên những điều kỳ diệu cho cộng đồng.
                    </p>
                    <div class="d-flex gap-3 mt-3">
                        <a href="https://www.facebook.com/vuphong.levan.3/" class="text-white fs-4"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="text-white fs-4"><i class="bi bi-twitter"></i></a>
                        <a href="https://www.instagram.com/_vduongg2818_" class="text-white fs-4"><i class="bi bi-instagram"></i></a>
                        <a href="https://www.youtube.com/watch?v=kLfu72cva-4" class="text-white fs-4"><i class="bi bi-youtube"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="fw-bold mb-3">Liên kết</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>index.php" class="text-muted text-decoration-none">
                                <i class="bi bi-chevron-right me-1"></i>Trang chủ
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>donate.php" class="text-muted text-decoration-none">
                                <i class="bi bi-chevron-right me-1"></i>Quyên góp
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>shop.php" class="text-muted text-decoration-none">
                                <i class="bi bi-chevron-right me-1"></i>Shop
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>campaigns.php" class="text-muted text-decoration-none">
                                <i class="bi bi-chevron-right me-1"></i>Chiến dịch
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="fw-bold mb-3">Hỗ trợ</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>about.php" class="text-muted text-decoration-none">
                                <i class="bi bi-chevron-right me-1"></i>Giới thiệu
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>contact.php" class="text-muted text-decoration-none">
                                <i class="bi bi-chevron-right me-1"></i>Liên hệ
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>help.php" class="text-muted text-decoration-none">
                                <i class="bi bi-chevron-right me-1"></i>Trợ giúp
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>faq.php" class="text-muted text-decoration-none">
                                <i class="bi bi-chevron-right me-1"></i>FAQ
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <h6 class="fw-bold mb-3">Liên hệ</h6>
                    <ul class="list-unstyled text-muted">
                        <li class="mb-2">
                            <i class="bi bi-geo-alt me-2"></i>328 Ngo Quyen, Son Tra, Da Nang, Việt Nam
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-envelope me-2"></i>info@goodwillvietnam.com
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-phone me-2"></i>+84 123 456 789
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-clock me-2"></i>T2-T6: 8:00 - 17:00
                        </li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-4 bg-secondary">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-muted mb-0">
                        &copy; <?php echo date('Y'); ?> Goodwill Vietnam. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>privacy.php" class="text-muted text-decoration-none me-3">
                        Chính sách bảo mật
                    </a>
                    <a href="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>terms.php" class="text-muted text-decoration-none">
                        Điều khoản sử dụng
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo isset($baseUrl) ? $baseUrl : ''; ?>assets/js/main.js"></script>
    
    <!-- Additional scripts if needed -->
    <?php if (isset($additionalScripts)): ?>
        <?php echo $additionalScripts; ?>
    <?php endif; ?>

    <!-- Chat widget -->
    <style>
        .chat-widget-toggle {
            position: fixed !important;
            left: 20px !important;
            right: auto !important;
            bottom: 20px !important;
            top: auto !important;
            z-index: 2147483647;
            height: 50px;
            border-radius: 25px;
            border: none;
            background: linear-gradient(135deg, #3a8d8c 0%, #2b7a78 100%);
            color: #fff;
            box-shadow: 0 4px 16px rgba(43, 122, 120, 0.5), 0 2px 6px rgba(0,0,0,0.18);
            cursor: pointer;
            padding: 0 20px;
            display: inline-flex !important;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            animation: chatTogglePulse 2.5s ease-in-out infinite;
        }
        @keyframes chatTogglePulse {
            0%, 100% { box-shadow: 0 4px 16px rgba(43, 122, 120, 0.5), 0 2px 6px rgba(0,0,0,0.18); }
            50% { box-shadow: 0 4px 24px rgba(43, 122, 120, 0.75), 0 2px 10px rgba(0,0,0,0.22); }
        }
        .chat-widget-toggle:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 24px rgba(43, 122, 120, 0.65), 0 3px 10px rgba(0,0,0,0.2);
            animation: none;
        }
        .chat-widget-toggle:active {
            transform: scale(0.97);
            animation: none;
        }
        .chat-widget-toggle:focus-visible {
            outline: 2px solid #8b95a5;
            outline-offset: 2px;
        }
        .chat-widget-toggle-label {
            font-size: 16px;
            line-height: 1;
        }
        .chat-widget-toggle i {
            font-size: 22px;
            line-height: 1;
        }
        .chat-widget-panel {
            position: fixed !important;
            left: 20px !important;
            right: auto !important;
            bottom: 80px !important;
            top: auto !important;
            z-index: 2147483647;
            width: 340px;
            height: 500px;
            max-width: calc(100vw - 40px);
            max-height: calc(100vh - 88px - env(safe-area-inset-bottom, 0px));
            min-width: 280px;
            min-height: 360px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            display: none;
            flex-direction: column;
            resize: both;
        }
        .chat-widget-header {
            background: #1f87a3;
            color: #fff;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .chat-widget-header-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            position: relative;
        }
        .chat-mode-toggle,
        .chat-widget-clear {
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.28);
            color: #fff;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            line-height: 1;
        }
        .chat-mode-toggle:hover,
        .chat-widget-clear:hover {
            background: rgba(255, 255, 255, 0.24);
        }
        .chat-mode-menu {
            position: absolute;
            right: 36px;
            top: 34px;
            width: 280px;
            background: #fff;
            border: 1px solid #bfe3eb;
            border-radius: 14px;
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.2);
            padding: 8px;
            display: block;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transform: translateY(8px) scale(0.98);
            transform-origin: top right;
            transition: opacity 0.18s ease, transform 0.2s ease, visibility 0.2s ease;
            z-index: 2;
        }
        .chat-mode-menu.show {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transform: translateY(0) scale(1);
        }
        .chat-mode-menu-title {
            font-size: 12px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #0f766e;
            font-weight: 700;
            padding: 6px 8px 4px;
        }
        .chat-mode-option {
            width: 100%;
            border: 1px solid #bfe3eb;
            text-align: left;
            border-radius: 10px;
            padding: 10px 12px;
            background: #f2fbfd;
            color: #0f172a;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 8px;
            transition: all 0.18s ease;
        }
        .chat-mode-option:last-child {
            margin-bottom: 0;
        }
        .chat-mode-option-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #d8f3f8;
            color: #0e7490;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .chat-mode-option-copy {
            line-height: 1.25;
        }
        .chat-mode-check {
            margin-left: auto;
            color: #0e7490;
            opacity: 0;
            transform: scale(0.8);
            transition: opacity 0.18s ease, transform 0.18s ease;
            padding-top: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .chat-mode-option small {
            color: #64748b;
            font-weight: 500;
        }
        .chat-mode-option:hover {
            background: #e8f7fb;
            border-color: #97dbe8;
        }
        .chat-mode-option.active {
            background: #d9f1f7;
            color: #0b5f76;
            border-color: #62bfd3;
            box-shadow: 0 0 0 1px rgba(14, 116, 144, 0.12) inset;
        }
        .chat-mode-option.active .chat-mode-option-icon {
            background: #bde9f3;
        }
        .chat-mode-option.active .chat-mode-check {
            opacity: 1;
            transform: scale(1);
        }
        .chat-widget-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chat-widget-online-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #22c55e;
            display: inline-block;
            animation: chatOnlinePulse 2s infinite ease-in-out;
            box-shadow: 0 0 4px rgba(34, 197, 94, 0.6);
        }
        @keyframes chatOnlinePulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.2);
                opacity: 0.7;
            }
        }
        .chat-widget-subtitle {
            font-size: 12px;
            opacity: 0.85;
        }
        .chat-widget-close {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 18px;
            line-height: 1;
        }
        .chat-widget-body {
            padding: 12px 14px;
            font-size: 14px;
            color: #1f2a37;
            flex: 1;
            overflow-y: auto;
            background: #f9fafb;
        }
        .chat-widget-message {
            display: flex;
            margin-bottom: 10px;
        }
        .chat-widget-message--user {
            justify-content: flex-end;
        }
        .chat-widget-bubble {
            background: #e5e7eb;
            padding: 10px 12px;
            border-radius: 12px;
            max-width: 78%;
            word-break: break-word;
        }
        .chat-widget-bubble--user {
            background: #1f87a3;
            color: #fff;
        }
        .chat-widget-bubble--typing {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-width: 58px;
            justify-content: center;
        }
        .chat-widget-typing-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #111;
            opacity: 0.45;
            animation: chatTypingPulse 1s infinite ease-in-out;
        }
        .chat-widget-typing-dot:nth-child(2) {
            animation-delay: 0.16s;
        }
        .chat-widget-typing-dot:nth-child(3) {
            animation-delay: 0.32s;
        }
        @keyframes chatTypingPulse {
            0%, 80%, 100% {
                transform: translateY(0);
                opacity: 0.4;
            }
            40% {
                transform: translateY(-3px);
                opacity: 1;
            }
        }
        .chat-widget-suggestions {
            padding: 10px 12px 8px 12px;
            background: #fff;
            display: flex;
            gap: 8px;
            overflow-x: auto;
            white-space: nowrap;
            border-top: 1px solid #e5e7eb;
        }
        .chat-widget-suggestions::-webkit-scrollbar {
            height: 4px;
        }
        .chat-widget-suggestions::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 4px;
        }
        .chat-suggestion-chip {
            background: #f0f9fb;
            border: 1px solid #b6dfe9;
            color: #0e7490;
            border-radius: 16px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .chat-suggestion-chip:hover {
            background: #0e7490;
            color: #fff;
        }
        .chat-widget-input {
            display: flex;
            gap: 8px;
            padding: 8px 12px 12px;
            background: #fff;
        }
        .chat-widget-input input {
            flex: 1;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 14px;
        }
        .chat-widget-input button {
            border: none;
            background: #1f87a3;
            color: #fff;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 14px;
        }
        .chat-widget-input button:disabled,
        .chat-widget-input input:disabled {
            opacity: 0.6;
        }

        @media (max-width: 768px) {
            .chat-widget-toggle {
                left: 14px !important;
                right: auto !important;
                bottom: 14px !important;
                height: 44px;
                padding: 0 16px;
            }

            .chat-widget-toggle-label {
                font-size: 14px;
            }

            .chat-widget-panel {
                left: 14px !important;
                right: auto !important;
                bottom: 68px !important;
                width: calc(100vw - 32px);
                height: min(70vh, 520px);
                max-width: 420px;
                max-height: calc(100vh - 98px);
                min-width: 0;
            }
        }
    </style>
    <button class="chat-widget-toggle" type="button" aria-label="Mo ho tro" id="chatWidgetToggle">
        <i class="bi bi-chat-dots-fill"></i>
        <span class="chat-widget-toggle-label">Hỗ trợ</span>
    </button>
    <div class="chat-widget-panel" id="chatWidgetPanel" role="dialog" aria-live="polite">
        <div class="chat-widget-header">
            <div>
                <div class="chat-widget-title" id="chatWidgetTitle"><span class="chat-widget-online-indicator"></span><span id="chatWidgetTitleText">Nhân viên tư vấn</span></div>
                <div class="chat-widget-subtitle" id="chatWidgetStaff">Dang ket noi...</div>
            </div>
            <div class="chat-widget-header-actions">
                <button class="chat-widget-clear" type="button" aria-label="Xoa lich su" id="chatWidgetClear" title="Xóa lịch sử trò chuyện">
                    <i class="bi bi-trash"></i>
                </button>
                <button class="chat-mode-toggle" type="button" aria-label="Chon kieu chat" id="chatWidgetModeToggle" title="Đổi kiểu Menu AI / Trực tiếp">
                    <i class="bi bi-list"></i>
                </button>
                <div class="chat-mode-menu" id="chatWidgetModeMenu">
                    <div class="chat-mode-menu-title">Chon kieu chat</div>
                    <button class="chat-mode-option" type="button" data-mode="ai" id="chatModeAi">
                        <span class="chat-mode-option-icon"><i class="bi bi-robot"></i></span>
                        <span class="chat-mode-option-copy">Chat voi AI<br><small>Giao dien mau</small></span>
                        <span class="chat-mode-check"><i class="bi bi-check-circle-fill"></i></span>
                    </button>
                    <button class="chat-mode-option" type="button" data-mode="staff" id="chatModeStaff">
                        <span class="chat-mode-option-icon"><i class="bi bi-person-badge"></i></span>
                        <span class="chat-mode-option-copy">Chat với hỗ trợ<br><small>Hỗ trợ trực tiếp</small></span>
                        <span class="chat-mode-check"><i class="bi bi-check-circle-fill"></i></span>
                    </button>
                </div>
                <button class="chat-widget-close" type="button" aria-label="Dong" id="chatWidgetClose">&times;</button>
            </div>
        </div>
        <div class="chat-widget-body" id="chatWidgetMessages"></div>
        <div class="chat-widget-suggestions" id="chatWidgetSuggestions">
            <button class="chat-suggestion-chip" type="button" data-msg="Chào bạn">Chào bạn 👋</button>
            <button class="chat-suggestion-chip" type="button" data-msg="GoodWill là gì ? Hãy tóm tắt mục đích của dự án này">GoodWill là gì? Mục đích dự án</button>
            <button class="chat-suggestion-chip" type="button" data-msg="Hướng dẫn mình cách quyên góp vật phẩm nhé!">Hướng dẫn quyên góp</button>
            <button class="chat-suggestion-chip" type="button" data-msg="Làm thế nào để tạo chiến dịch kêu gọi quyên góp mới?">Tạo chiến dịch mới</button>
        </div>
        <div class="chat-widget-input">
            <input type="text" placeholder="Nhap tin nhan..." aria-label="Nhap tin nhan" id="chatWidgetInput" disabled>
            <button type="button" id="chatWidgetSend" disabled>Gui</button>
        </div>
    </div>
    <script>
        (function () {
            var html = document.documentElement;
            var body = document.body;
            var supportsTransitions = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var leaveDurationMs = 120;

            if (!html.classList.contains('page-transition-enabled') || !body) {
                return;
            }

            function enterPage() {
                if (!supportsTransitions) {
                    body.classList.add('page-entered');
                    return;
                }

                requestAnimationFrame(function () {
                    body.classList.add('page-entered');
                });
            }

            function isInternalHttpLink(anchor) {
                if (!anchor || !anchor.href) {
                    return false;
                }
                if (anchor.target && anchor.target.toLowerCase() === '_blank') {
                    return false;
                }
                if (anchor.hasAttribute('download')) {
                    return false;
                }

                var href = anchor.getAttribute('href') || '';
                if (!href || href.charAt(0) === '#') {
                    return false;
                }
                if (/^(mailto:|tel:|javascript:)/i.test(href)) {
                    return false;
                }

                var url = new URL(anchor.href, window.location.href);
                if (url.origin !== window.location.origin) {
                    return false;
                }

                // Keep same-page hash jumps instant.
                if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
                    return false;
                }

                return true;
            }

            enterPage();

            if (!supportsTransitions) {
                return;
            }

            document.addEventListener('click', function (event) {
                if (event.defaultPrevented || event.button !== 0) {
                    return;
                }
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                var anchor = event.target.closest('a');
                if (!isInternalHttpLink(anchor)) {
                    return;
                }

                event.preventDefault();
                body.classList.remove('page-entered');
                body.classList.add('page-leaving');

                window.setTimeout(function () {
                    window.location.href = anchor.href;
                }, leaveDurationMs);
            }, true);
        })();
    </script>
    <script>
        (function () {
            var toggle = document.getElementById('chatWidgetToggle');
            var panel = document.getElementById('chatWidgetPanel');
            var closeBtn = document.getElementById('chatWidgetClose');
            var modeToggle = document.getElementById('chatWidgetModeToggle');
            var clearBtn = document.getElementById('chatWidgetClear');
            var modeMenu = document.getElementById('chatWidgetModeMenu');
            var modeAiBtn = document.getElementById('chatModeAi');
            var modeStaffBtn = document.getElementById('chatModeStaff');
            var titleEl = document.getElementById('chatWidgetTitle');
            var staffLabel = document.getElementById('chatWidgetStaff');
            var messagesEl = document.getElementById('chatWidgetMessages');
            var input = document.getElementById('chatWidgetInput');
            var sendBtn = document.getElementById('chatWidgetSend');
            var chatId = null;
            var loading = false;
            var currentMode = 'staff';
            var hasShownModeMenuOnce = false;
            var aiGreeting = 'Chào mừng bạn đến với GoodWill Việt Nam, không biết bạn cần  mình giúp gì không nhỉ ?';
            var panelResizeTimer = null;
            var staffPollTimer = null;
            var aiFallbackTimer = null;
            var lastStaffMessageSignature = '';
            var staffTypingNode = null;
            var typingDebounceTimer = null;
            var typingIdleTimer = null;
            var STORAGE_PANEL_SIZE = 'gw_chat_panel_size_v1';
            var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (!toggle || !panel || !staffLabel || !messagesEl || !input || !sendBtn || !modeToggle || !modeMenu || !modeAiBtn || !modeStaffBtn || !titleEl) {
                return;
            }

            function pinWidgetToViewport() {
                // Move widget to body to avoid parent layout/transform side effects.
                if (toggle.parentNode !== document.body) {
                    document.body.appendChild(toggle);
                }
                if (panel.parentNode !== document.body) {
                    document.body.appendChild(panel);
                }

                var isMobile = window.innerWidth <= 768;

                toggle.style.position = 'fixed';
                toggle.style.right = isMobile ? '16px' : '20px';
                toggle.style.left = isMobile ? '16px' : 'auto';
                toggle.style.top = 'auto';
                toggle.style.bottom = isMobile
                    ? 'calc(12px + env(safe-area-inset-bottom, 0px))'
                    : 'calc(16px + env(safe-area-inset-bottom, 0px))';
                toggle.style.width = isMobile ? 'calc(100vw - 32px)' : 'auto';
                toggle.style.justifyContent = isMobile ? 'center' : 'flex-start';

                panel.style.position = 'fixed';
                panel.style.right = isMobile ? '16px' : '88px';
                panel.style.left = isMobile ? '16px' : 'auto';
                panel.style.top = 'auto';
                panel.style.bottom = isMobile
                    ? 'calc(50px + max(8px, env(safe-area-inset-bottom, 0px)))'
                    : 'calc(56px + max(8px, env(safe-area-inset-bottom, 0px)))';
            }

            function setInputEnabled(enabled) {
                input.disabled = !enabled;
                sendBtn.disabled = !enabled;
            }

            function savePanelSize() {
                try {
                    localStorage.setItem(STORAGE_PANEL_SIZE, JSON.stringify({
                        width: panel.offsetWidth,
                        height: panel.offsetHeight
                    }));
                } catch (error) {
                    // Ignore storage errors.
                }
            }

            function loadPanelSize() {
                try {
                    var raw = localStorage.getItem(STORAGE_PANEL_SIZE);
                    if (!raw) {
                        return;
                    }
                    var parsed = JSON.parse(raw);
                    if (typeof parsed.width === 'number' && parsed.width > 0) {
                        panel.style.width = parsed.width + 'px';
                    }
                    if (typeof parsed.height === 'number' && parsed.height > 0) {
                        panel.style.height = parsed.height + 'px';
                    }
                } catch (error) {
                    // Ignore storage errors.
                }
            }

            function escapeHtml(text) {
                return String(text)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function formatChatMessage(text) {
                var raw = String(text || '');
                var linkRegex = /\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]+)\)/g;
                var result = '';
                var lastIndex = 0;
                var match;

                while ((match = linkRegex.exec(raw)) !== null) {
                    var label = match[1];
                    var href = match[2];

                    result += escapeHtml(raw.slice(lastIndex, match.index));
                    result += '<a href="' + escapeHtml(href) + '" target="_self" rel="noopener">' + escapeHtml(label) + '</a>';
                    lastIndex = linkRegex.lastIndex;
                }

                result += escapeHtml(raw.slice(lastIndex));
                return result;
            }

            function appendMessage(text, type) {
                var wrapper = document.createElement('div');
                var bubble = document.createElement('div');
                wrapper.className = 'chat-widget-message chat-widget-message--' + type;
                bubble.className = 'chat-widget-bubble' + (type === 'user' ? ' chat-widget-bubble--user' : '');
                bubble.innerHTML = formatChatMessage(text);
                wrapper.appendChild(bubble);
                messagesEl.appendChild(wrapper);
                messagesEl.scrollTop = messagesEl.scrollHeight;
                saveChatHistoryToStorage();
            }

            function showTypingIndicator() {
                var wrapper = document.createElement('div');
                var bubble = document.createElement('div');
                bubble.innerHTML = '<span class="chat-widget-typing-dot"></span><span class="chat-widget-typing-dot"></span><span class="chat-widget-typing-dot"></span>';
                wrapper.className = 'chat-widget-message chat-widget-message--staff';
                bubble.className = 'chat-widget-bubble chat-widget-bubble--typing';
                wrapper.appendChild(bubble);
                messagesEl.appendChild(wrapper);
                messagesEl.scrollTop = messagesEl.scrollHeight;
                return wrapper;
            }

            function appendMessageTyping(text, type) {
                var content = String(text || '');

                if (type !== 'staff' || prefersReducedMotion || content.length <= 2) {
                    appendMessage(content, type);
                    return Promise.resolve();
                }

                return new Promise(function (resolve) {
                    var wrapper = document.createElement('div');
                    var bubble = document.createElement('div');
                    var i = 0;
                    var tickMs = 16;
                    var step = content.length > 180 ? 3 : 2;

                    wrapper.className = 'chat-widget-message chat-widget-message--' + type;
                    bubble.className = 'chat-widget-bubble';
                    wrapper.appendChild(bubble);
                    messagesEl.appendChild(wrapper);

                    var timer = window.setInterval(function () {
                        i = Math.min(content.length, i + step);
                        bubble.textContent = content.slice(0, i);
                        messagesEl.scrollTop = messagesEl.scrollHeight;

                        if (i >= content.length) {
                            window.clearInterval(timer);
                            bubble.innerHTML = formatChatMessage(content);
                            saveChatHistoryToStorage();
                            resolve();
                        }
                    }, tickMs);
                });
            }

            function saveChatHistoryToStorage() {
                try {
                    var messages = [];
                    var messageNodes = messagesEl.querySelectorAll('.chat-widget-message');
                    messageNodes.forEach(function (node) {
                        var bubbleNode = node.querySelector('.chat-widget-bubble');
                        if (bubbleNode && !bubbleNode.classList.contains('chat-widget-bubble--typing')) {
                            var isUser = node.classList.contains('chat-widget-message--user');
                            var textContent = bubbleNode.textContent || bubbleNode.innerText || '';
                            messages.push({
                                type: isUser ? 'user' : 'staff',
                                text: textContent
                            });
                        }
                    });
                    if (messages.length > 0) {
                        sessionStorage.setItem('gw_chat_history', JSON.stringify({
                            mode: currentMode,
                            messages: messages,
                            timestamp: Date.now()
                        }));
                    }
                } catch (error) {
                    // Ignore storage errors
                }
            }

            function loadChatHistoryFromStorage() {
                try {
                    var stored = sessionStorage.getItem('gw_chat_history');
                    if (!stored) {
                        return null;
                    }
                    var data = JSON.parse(stored);
                    // Only restore if timestamp is recent (same session)
                    if (data && Array.isArray(data.messages) && data.messages.length > 0) {
                        return data;
                    }
                } catch (error) {
                    // Ignore storage errors
                }
                return null;
            }

            function renderMessages(messages) {
                messagesEl.innerHTML = '';
                messages.forEach(function (item) {
                    var senderType = item.sender_type === 'user' ? 'user' : 'staff';
                    appendMessage(item.message, senderType);
                });
                if (messages.length > 0) {
                    var last = messages[messages.length - 1];
                    lastStaffMessageSignature = messages.length + '|' + (last.sender_type || '') + '|' + (last.created_at || '');
                } else {
                    lastStaffMessageSignature = '0';
                }
                saveChatHistoryToStorage();
            }

            function stopStaffPolling() {
                if (staffPollTimer) {
                    window.clearInterval(staffPollTimer);
                    staffPollTimer = null;
                }
            }

            function showStaffTypingIndicator(show) {
                if (show) {
                    if (staffTypingNode) {
                        return;
                    }
                    staffTypingNode = showTypingIndicator();
                    return;
                }

                if (staffTypingNode && staffTypingNode.parentNode) {
                    staffTypingNode.parentNode.removeChild(staffTypingNode);
                }
                staffTypingNode = null;
            }

            function sendTypingState(isTyping) {
                if (currentMode !== 'staff' || !chatId) {
                    return Promise.resolve();
                }

                var typingData = new FormData();
                typingData.append('chat_id', String(chatId));
                typingData.append('is_typing', isTyping ? '1' : '0');
                return fetch('api/chat-typing.php', {
                    method: 'POST',
                    body: typingData
                }).catch(function () {});
            }

            function pollStaffMessages(forceRender) {
                if (currentMode !== 'staff' || !chatId) {
                    return;
                }

                var formBody = new URLSearchParams();
                formBody.append('chat_id', String(chatId));

                fetch('api/chat-get-messages.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: formBody.toString()
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (!data.success) {
                            return;
                        }

                        var list = Array.isArray(data.messages) ? data.messages : [];
                        var signature = '0';
                        if (list.length > 0) {
                            var last = list[list.length - 1];
                            signature = list.length + '|' + (last.sender_type || '') + '|' + (last.created_at || '');
                            if (last.sender_type === 'staff' && aiFallbackTimer) {
                                window.clearTimeout(aiFallbackTimer);
                                aiFallbackTimer = null;
                            }
                        }

                        if (forceRender || signature !== lastStaffMessageSignature) {
                            renderMessages(list);
                        }

                        showStaffTypingIndicator(!!(data.typing && data.typing.staff));
                    })
                    .catch(function () {});
            }

            function startStaffPolling() {
                stopStaffPolling();
                pollStaffMessages(true);
                staffPollTimer = window.setInterval(function () {
                    pollStaffMessages(false);
                }, 2000);
            }

            function setModeButtonState() {
                modeAiBtn.classList.toggle('active', currentMode === 'ai');
                modeStaffBtn.classList.toggle('active', currentMode === 'staff');
            }

            function showModeMenu(show) {
                modeMenu.classList.toggle('show', !!show);
            }

            function activateMode(mode) {
                currentMode = mode;
                setModeButtonState();
                showModeMenu(false);

                if (mode === 'ai') {
                    stopStaffPolling();
                    showStaffTypingIndicator(false);
                    titleEl.innerHTML = '<span class="chat-widget-online-indicator"></span><span>Tư vấn AI 24/24</span>';
                    staffLabel.textContent = 'GoodWill Viet Nam';
                    messagesEl.innerHTML = '';
                    
                    // Try to load previous chat history
                    var stored = loadChatHistoryFromStorage();
                    if (stored && stored.mode === 'ai' && stored.messages && stored.messages.length > 0) {
                        stored.messages.forEach(function (item) {
                            appendMessage(item.text, item.type);
                        });
                    } else {
                        appendMessage(aiGreeting, 'staff');
                    }
                    
                    setInputEnabled(true);
                    input.focus();
                    return;
                }

                titleEl.innerHTML = '<span class="chat-widget-online-indicator"></span><span>Nhân viên tư vấn</span>';
                loadChat();
            }

            function loadChat() {
                if (currentMode !== 'staff') {
                    activateMode('ai');
                    return;
                }
                if (loading) {
                    return;
                }
                loading = true;
                setInputEnabled(false);
                staffLabel.textContent = 'Dang ket noi...';

                fetch('api/chat-init.php', { method: 'POST' })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        loading = false;
                        if (!data.success) {
                            staffLabel.textContent = 'He thong';
                            appendMessage(data.message || 'Khong the ket noi.', 'staff');
                            return;
                        }
                        chatId = data.chat_id;
                        staffLabel.textContent = 'Nhan vien: ' + (data.staff_name || 'Tu van vien');
                        renderMessages(Array.isArray(data.messages) ? data.messages : []);
                        setInputEnabled(true);
                        input.focus();
                        startStaffPolling();
                    })
                    .catch(function () {
                        loading = false;
                        staffLabel.textContent = 'He thong';
                        appendMessage('Khong the ket noi.', 'staff');
                    });
            }

            function triggerAutoAi(text) {
                currentMode = 'ai';
                setModeButtonState();
                stopStaffPolling();
                showStaffTypingIndicator(false);
                titleEl.innerHTML = '<span class="chat-widget-online-indicator"></span><span>Tư vấn AI 24/24</span>';
                staffLabel.textContent = 'Hệ thống tự động (AI)';
                
                appendMessage('Hiện tại các tư vấn viên đang bận. Trợ lý AI sẽ tiếp tục hỗ trợ bạn nhé!', 'staff');
                
                sendBtn.disabled = true;
                input.disabled = true;
                var typingAiNode = showTypingIndicator();

                var aiFormData = new FormData();
                aiFormData.append('message', text);

                fetch('api/chat-ai-send.php', {
                    method: 'POST',
                    body: aiFormData
                })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (typingAiNode && typingAiNode.parentNode) {
                        typingAiNode.parentNode.removeChild(typingAiNode);
                        typingAiNode = null;
                    }
                    if (data.reply) {
                        return appendMessageTyping(data.reply, 'staff');
                    }
                    if (data.message) {
                        return appendMessageTyping(data.message, 'staff');
                    }
                    return appendMessageTyping('Xin loi, minh chua nhan duoc phan hoi.', 'staff');
                })
                .catch(function () {
                    if (typingAiNode && typingAiNode.parentNode) {
                        typingAiNode.parentNode.removeChild(typingAiNode);
                        typingAiNode = null;
                    }
                    return appendMessageTyping('Vui lòng liên hệ hotline: 0964821707 để được hỗ trợ thêm.', 'staff');
                })
                .finally(function () {
                    if (typingAiNode && typingAiNode.parentNode) {
                        typingAiNode.parentNode.removeChild(typingAiNode);
                    }
                    sendBtn.disabled = false;
                    input.disabled = false;
                    input.focus();
                });
            }

            function sendMessage() {
                var text = input.value.trim();
                if (!text) {
                    return;
                }
                appendMessage(text, 'user');
                input.value = '';

                if (currentMode === 'ai') {
                    sendBtn.disabled = true;
                    input.disabled = true;
                    var typingAiNode = showTypingIndicator();

                    var aiFormData = new FormData();
                    aiFormData.append('message', text);

                    fetch('api/chat-ai-send.php', {
                        method: 'POST',
                        body: aiFormData
                    })
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            if (typingAiNode && typingAiNode.parentNode) {
                                typingAiNode.parentNode.removeChild(typingAiNode);
                                typingAiNode = null;
                            }
                            if (data.reply) {
                                return appendMessageTyping(data.reply, 'staff');
                            }
                            if (data.message) {
                                return appendMessageTyping(data.message, 'staff');
                            }
                            return appendMessageTyping('Xin loi, minh chua nhan duoc phan hoi.', 'staff');
                        })
                        .catch(function () {
                            if (typingAiNode && typingAiNode.parentNode) {
                                typingAiNode.parentNode.removeChild(typingAiNode);
                                typingAiNode = null;
                            }
                            return appendMessageTyping('Vui lòng liên hệ đến số hotline: 0964821707 để được hỗ trợ thêm', 'staff');
                        })
                        .finally(function () {
                            if (typingAiNode && typingAiNode.parentNode) {
                                typingAiNode.parentNode.removeChild(typingAiNode);
                            }
                            sendBtn.disabled = false;
                            input.disabled = false;
                            input.focus();
                        });
                    return;
                }

                var formData = new FormData();
                formData.append('message', text);

                sendTypingState(false);
                showStaffTypingIndicator(false);

                fetch('api/chat-send.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (!data.success) {
                            return appendMessageTyping(data.message || 'Khong gui duoc tin nhan.', 'staff');
                        }
                        pollStaffMessages(true);
                        
                        if (aiFallbackTimer) {
                            window.clearTimeout(aiFallbackTimer);
                        }
                        aiFallbackTimer = window.setTimeout(function() {
                            triggerAutoAi(text);
                        }, 10000);
                    })
                    .catch(function () {
                        return appendMessageTyping('Khong gui duoc tin nhan.', 'staff');
                    });
            }

            toggle.addEventListener('click', function () {
                var isOpen = panel.style.display === 'flex';
                panel.style.display = isOpen ? 'none' : 'flex';
                if (isOpen) {
                    showModeMenu(false);
                    sendTypingState(false);
                    stopStaffPolling();
                    showStaffTypingIndicator(false);
                    return;
                }
                if (!isOpen) {
                    // Try to restore previous chat when reopening panel
                    var stored = loadChatHistoryFromStorage();
                    if (!stored && currentMode === 'ai') {
                        // If no history but in AI mode, start with greeting
                        if (messagesEl.innerHTML === '') {
                            appendMessage(aiGreeting, 'staff');
                        }
                    } else if (stored && currentMode === stored.mode && messagesEl.innerHTML === '') {
                        // Restore stored history
                        stored.messages.forEach(function (item) {
                            var wrapper = document.createElement('div');
                            var bubble = document.createElement('div');
                            wrapper.className = 'chat-widget-message chat-widget-message--' + item.type;
                            bubble.className = 'chat-widget-bubble' + (item.type === 'user' ? ' chat-widget-bubble--user' : '');
                            bubble.innerHTML = formatChatMessage(item.text);
                            wrapper.appendChild(bubble);
                            messagesEl.appendChild(wrapper);
                        });
                        messagesEl.scrollTop = messagesEl.scrollHeight;
                    }
                    
                    // Load staff chat only if in staff mode
                    if (currentMode === 'staff') {
                        loadChat();
                    }
                }
            });

            modeToggle.addEventListener('click', function (event) {
                event.stopPropagation();
                showModeMenu(!modeMenu.classList.contains('show'));
            });

            modeAiBtn.addEventListener('click', function () {
                activateMode('ai');
            });

            modeStaffBtn.addEventListener('click', function () {
                activateMode('staff');
            });

            // Initialize visual selected state so checkmark is visible immediately.
            setModeButtonState();

            document.addEventListener('click', function (event) {
                if (!modeMenu.contains(event.target) && event.target !== modeToggle && !modeToggle.contains(event.target)) {
                    showModeMenu(false);
                }
            });

            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    panel.style.display = 'none';
                    showModeMenu(false);
                    sendTypingState(false);
                    stopStaffPolling();
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    if (!confirm('Bạn có chắc chắn muốn xóa lịch sử trò chuyện cục bộ này không? (Nhân viên vẫn có thể xem được tin nhắn trước đó)')) return;
                    
                    // Clear frontend messages
                    messagesEl.innerHTML = '';
                    sessionStorage.removeItem('gw_chat_history');
                    
                    if (currentMode === 'staff' && chatId) {
                        var clearForm = new URLSearchParams();
                        clearForm.append('chat_id', String(chatId));
                        fetch('api/chat-clear.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: clearForm.toString()
                        });
                    } else if (currentMode === 'ai') {
                        appendMessage(aiGreeting, 'staff');
                    }
                });
            }

            if (window.ResizeObserver) {
                var observer = new ResizeObserver(function () {
                    window.clearTimeout(panelResizeTimer);
                    panelResizeTimer = window.setTimeout(function () {
                        savePanelSize();
                    }, 160);
                });
                observer.observe(panel);
            }

            pinWidgetToViewport();

            window.addEventListener('scroll', pinWidgetToViewport, { passive: true });
            window.addEventListener('resize', pinWidgetToViewport);

            loadPanelSize();

            sendBtn.addEventListener('click', sendMessage);
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    sendMessage();
                }
            });

            document.querySelectorAll('.chat-suggestion-chip').forEach(function(chip) {
                chip.addEventListener('click', function() {
                    if (input.disabled) return;
                    input.value = this.getAttribute('data-msg') || this.innerText;
                    sendMessage();
                });
            });

            input.addEventListener('input', function () {
                if (currentMode !== 'staff' || !chatId) {
                    return;
                }

                if (typingDebounceTimer) {
                    window.clearTimeout(typingDebounceTimer);
                }
                typingDebounceTimer = window.setTimeout(function () {
                    sendTypingState(true);
                }, 120);

                if (typingIdleTimer) {
                    window.clearTimeout(typingIdleTimer);
                }
                typingIdleTimer = window.setTimeout(function () {
                    sendTypingState(false);
                }, 1400);
            });

            input.addEventListener('blur', function () {
                if (currentMode === 'staff' && chatId) {
                    sendTypingState(false);
                }
            });

            window.addEventListener('beforeunload', function () {
                sendTypingState(false);
                stopStaffPolling();
            });
        })();
    </script>

</body>
</html>
