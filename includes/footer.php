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
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 1050;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            border: none;
            background: #198754;
            color: #fff;
            font-size: 22px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
        }
        .chat-widget-panel {
            position: fixed;
            right: 20px;
            bottom: 88px;
            z-index: 1050;
            width: 340px;
            max-width: calc(100vw - 40px);
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            display: none;
        }
        .chat-widget-header {
            background: #198754;
            color: #fff;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .chat-widget-title {
            font-weight: 600;
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
            max-height: 320px;
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
            background: #198754;
            color: #fff;
        }
        .chat-widget-input {
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 8px;
            padding: 10px 12px;
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
            background: #198754;
            color: #fff;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 14px;
        }
        .chat-widget-input button:disabled,
        .chat-widget-input input:disabled {
            opacity: 0.6;
        }
    </style>
    <button class="chat-widget-toggle" type="button" aria-label="Chat voi nhan vien" id="chatWidgetToggle">
        <i class="bi bi-chat-dots"></i>
    </button>
    <div class="chat-widget-panel" id="chatWidgetPanel" role="dialog" aria-live="polite">
        <div class="chat-widget-header">
            <div>
                <div class="chat-widget-title">Nhan vien tu van</div>
                <div class="chat-widget-subtitle" id="chatWidgetStaff">Dang ket noi...</div>
            </div>
            <button class="chat-widget-close" type="button" aria-label="Dong" id="chatWidgetClose">&times;</button>
        </div>
        <div class="chat-widget-body" id="chatWidgetMessages"></div>
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
            var staffLabel = document.getElementById('chatWidgetStaff');
            var messagesEl = document.getElementById('chatWidgetMessages');
            var input = document.getElementById('chatWidgetInput');
            var sendBtn = document.getElementById('chatWidgetSend');
            var chatId = null;
            var loading = false;

            if (!toggle || !panel || !staffLabel || !messagesEl || !input || !sendBtn) {
                return;
            }

            function setInputEnabled(enabled) {
                input.disabled = !enabled;
                sendBtn.disabled = !enabled;
            }

            function appendMessage(text, type) {
                var wrapper = document.createElement('div');
                var bubble = document.createElement('div');
                wrapper.className = 'chat-widget-message chat-widget-message--' + type;
                bubble.className = 'chat-widget-bubble' + (type === 'user' ? ' chat-widget-bubble--user' : '');
                bubble.textContent = text;
                wrapper.appendChild(bubble);
                messagesEl.appendChild(wrapper);
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }

            function renderMessages(messages) {
                messagesEl.innerHTML = '';
                messages.forEach(function (item) {
                    var senderType = item.sender_type === 'user' ? 'user' : 'staff';
                    appendMessage(item.message, senderType);
                });
            }

            function loadChat() {
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
                    })
                    .catch(function () {
                        loading = false;
                        staffLabel.textContent = 'He thong';
                        appendMessage('Khong the ket noi.', 'staff');
                    });
            }

            function sendMessage() {
                var text = input.value.trim();
                if (!text) {
                    return;
                }
                appendMessage(text, 'user');
                input.value = '';
                var formData = new FormData();
                formData.append('message', text);

                fetch('api/chat-send.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (!data.success) {
                            appendMessage(data.message || 'Khong gui duoc tin nhan.', 'staff');
                            return;
                        }
                        if (Array.isArray(data.replies)) {
                            data.replies.forEach(function (reply) {
                                appendMessage(reply.message, 'staff');
                            });
                        }
                    })
                    .catch(function () {
                        appendMessage('Khong gui duoc tin nhan.', 'staff');
                    });
            }

            toggle.addEventListener('click', function () {
                var isOpen = panel.style.display === 'block';
                panel.style.display = isOpen ? 'none' : 'block';
                if (!isOpen) {
                    loadChat();
                }
            });

            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    panel.style.display = 'none';
                });
            }

            sendBtn.addEventListener('click', sendMessage);
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    sendMessage();
                }
            });
        })();
    </script>
    
    <!-- Include improved chat widget -->
    <?php include __DIR__ . '/chat-widget.php'; ?>
</body>
</html>
