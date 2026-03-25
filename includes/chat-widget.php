<?php
/**
 * Chat Widget for Customers
 * Include this at the bottom of pages to enable chat with advisors
 */

// Prevent direct access
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    // This file is meant to be included, not accessed directly
}
?>

<!-- Chat Widget -->
<div id="chatWidget" class="chat-widget">
    <!-- Chat Toggle Button -->
    <button id="chatToggle" class="chat-toggle" onclick="toggleChat()" title="Mở chat">
        <i class="bi bi-chat-dots-fill"></i>
        <span id="chatBadge" class="chat-badge" style="display: none;">1</span>
    </button>

    <!-- Chat Window -->
    <div id="chatWindow" class="chat-window" style="display: none;">
        <div class="chat-header">
            <div>
                <h6 class="mb-0" id="advisorName">Tư vấn viên</h6>
                <small class="text-muted" id="advisorStatus">Đang kết nối...</small>
            </div>
            <button class="btn btn-sm btn-link text-white p-0" onclick="toggleChat()" title="Đóng chat">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div id="chatMessages" class="chat-messages">
            <!-- Messages will be loaded here -->
        </div>

        <div class="chat-input-area">
            <form id="chatFormWidget" onsubmit="sendChatMessage(event)">
                <div class="input-group">
                    <input type="text" 
                           class="form-control" 
                           id="chatMessageInput" 
                           placeholder="Nhập tin nhắn..."
                           autocomplete="off">
                    <button class="btn btn-success" type="submit" title="Gửi">
                        <i class="bi bi-send"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .chat-widget {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }

    .chat-toggle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background-color: #198754;
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        transition: all 0.3s ease;
        position: relative;
    }

    .chat-toggle:hover {
        background-color: #157347;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        transform: scale(1.1);
    }

    .chat-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background-color: #dc3545;
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
    }

    .chat-window {
        position: absolute;
        bottom: 80px;
        right: 0;
        width: 380px;
        height: 500px;
        background-color: white;
        border-radius: 12px;
        box-shadow: 0 5px 40px rgba(0,0,0,0.16);
        display: flex;
        flex-direction: column;
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .chat-header {
        background: linear-gradient(135deg, #198754 0%, #157347 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }

    .chat-header h6 {
        margin-bottom: 0.25rem;
        font-weight: 600;
    }

    .chat-header .text-muted {
        color: rgba(255,255,255,0.8) !important;
        font-size: 0.85rem;
    }

    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
        background-color: #f8f9fa;
    }

    .chat-message {
        margin-bottom: 1.25rem;
        display: flex;
        gap: 0.75rem;
    }

    .chat-message.user {
        justify-content: flex-end;
    }

    .chat-message-bubble {
        max-width: 70%;
        padding: 0.75rem 1rem;
        border-radius: 12px;
        word-wrap: break-word;
        font-size: 0.95rem;
        line-height: 1.4;
    }

    .chat-message:not(.user) .chat-message-bubble {
        background-color: white;
        color: #333;
        border: 1px solid #e9ecef;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    .chat-message.user .chat-message-bubble {
        background-color: #198754;
        color: white;
    }

    .chat-message-time {
        font-size: 0.75rem;
        color: #999;
        margin-top: 0.25rem;
    }

    .chat-message.user .chat-message-time {
        text-align: right;
    }

    .chat-input-area {
        padding: 1rem;
        background-color: white;
        border-top: 1px solid #e9ecef;
        border-radius: 0 0 12px 12px;
        flex-shrink: 0;
    }

    .chat-input-area .input-group {
        display: flex;
        gap: 0.5rem;
    }

    .chat-input-area .form-control {
        border-radius: 20px;
        padding: 0.5rem 1rem;
        font-size: 0.95rem;
    }

    .chat-input-area .form-control:focus {
        border-color: #198754;
        box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
    }

    .chat-input-area .btn {
        border-radius: 20px;
        padding: 0.5rem 1rem;
        min-width: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @media (max-width: 480px) {
        .chat-window {
            position: fixed;
            bottom: 0;
            right: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 0;
        }

        .chat-message-bubble {
            max-width: 85%;
        }
    }
</style>

<script>
let chatData = {
    chatId: null,
    advisorName: 'Tư vấn viên',
    isOpen: false
};

function toggleChat() {
    const window = document.getElementById('chatWindow');
    const toggle = document.getElementById('chatToggle');
    
    if (chatData.isOpen) {
        window.style.display = 'none';
        toggle.classList.remove('active');
        chatData.isOpen = false;
    } else {
        window.style.display = 'flex';
        toggle.classList.add('active');
        chatData.isOpen = true;
        
        if (!chatData.chatId) {
            initializeChat();
        } else {
            loadChatMessages();
        }
        
        // Focus on input
        setTimeout(() => {
            document.getElementById('chatMessageInput').focus();
        }, 100);
    }
}

function loadChatMessages() {
    if (!chatData.chatId) return;
    
    fetch('api/chat-get-messages.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `chat_id=${chatData.chatId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.messages) {
            displayMessages(data.messages);
        }
    })
    .catch(error => console.error('Error loading messages:', error));
}

function initializeChat() {
    fetch('api/chat-init.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                chatData.chatId = data.chat_id;
                chatData.advisorName = data.staff_name || 'Tư vấn viên';
                
                document.getElementById('advisorName').textContent = data.staff_name || 'Tư vấn viên';
                document.getElementById('advisorStatus').textContent = 'Trực tuyến';
                
                displayMessages(data.messages);
                
                // Auto-scroll to bottom
                setTimeout(() => {
                    const messagesDiv = document.getElementById('chatMessages');
                    messagesDiv.scrollTop = messagesDiv.scrollHeight;
                }, 100);
                
                // Start auto-refresh
                startChatAutoRefresh();
            } else {
                document.getElementById('advisorStatus').textContent = 'Không có tư vấn viên';
                const messagesDiv = document.getElementById('chatMessages');
                messagesDiv.innerHTML = '<div class="alert alert-warning m-2">Hãy quay lại sau. Tư vấn viên đang bận.</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('advisorStatus').textContent = 'Lỗi kết nối';
        });
}

function loadChatMessages() {
    if (!chatData.chatId) return;
    
    fetch('api/chat-get-messages.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `chat_id=${chatData.chatId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayMessages(data.messages);
        }
    })
    .catch(error => console.error('Error:', error));
}

function displayMessages(messages) {
    const container = document.getElementById('chatMessages');
    container.innerHTML = '';
    
    if (!messages || messages.length === 0) {
        return;
    }
    
    messages.forEach(msg => {
        const isUser = msg.sender_type === 'user';
        const msgDiv = document.createElement('div');
        msgDiv.className = `chat-message ${isUser ? 'user' : 'advisor'}`;
        
        const bubble = document.createElement('div');
        bubble.className = 'chat-message-bubble';
        bubble.textContent = msg.message;
        
        const timeDiv = document.createElement('div');
        timeDiv.className = 'chat-message-time';
        const msgTime = new Date(msg.created_at);
        timeDiv.textContent = msgTime.toLocaleTimeString('vi-VN', 
            { hour: '2-digit', minute: '2-digit' });
        
        msgDiv.appendChild(bubble);
        msgDiv.appendChild(timeDiv);
        container.appendChild(msgDiv);
    });
    
    // Scroll to bottom
    setTimeout(() => {
        container.scrollTop = container.scrollHeight;
    }, 50);
}

function sendChatMessage(event) {
    event.preventDefault();
    
    const message = document.getElementById('chatMessageInput').value.trim();
    if (!message) return;
    
    // Disable input while sending
    const input = document.getElementById('chatMessageInput');
    const sendBtn = document.querySelector('.chat-input-area .btn');
    input.disabled = true;
    sendBtn.disabled = true;
    
    const formData = new FormData();
    formData.append('message', message);
    
    fetch('api/chat-send.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Clear input
            input.value = '';
            input.disabled = false;
            sendBtn.disabled = false;
            
            // Update chat with new messages
            if (data.messages) {
                displayMessages(data.messages);
            }
            
            // Re-enable input and focus
            setTimeout(() => {
                input.focus();
            }, 100);
        } else {
            alert('Lỗi: ' + (data.message || 'Không gửi được tin nhắn'));
            input.disabled = false;
            sendBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Có lỗi xảy ra. Vui lòng thử lại.');
        input.disabled = false;
        sendBtn.disabled = false;
    });
}

let chatRefreshInterval = null;

function startChatAutoRefresh() {
    if (chatRefreshInterval) {
        clearInterval(chatRefreshInterval);
    }
    
    chatRefreshInterval = setInterval(() => {
        if (chatData.isOpen && chatData.chatId) {
            loadChatMessages();
        }
    }, 2000);
}

function stopChatAutoRefresh() {
    if (chatRefreshInterval) {
        clearInterval(chatRefreshInterval);
        chatRefreshInterval = null;
    }
}

// Initialize chat on page load
document.addEventListener('DOMContentLoaded', () => {
    // Chat widget is ready
});

// Clean up on page unload
window.addEventListener('beforeunload', () => {
    stopChatAutoRefresh();
});
</script>
