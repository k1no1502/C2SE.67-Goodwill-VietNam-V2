<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is a staff member
if (!isLoggedIn() || (!hasRole('staff') && !hasRole('nhân viên'))) {
    header('Location: ' . (empty($_SERVER['HTTP_REFERER']) ? '../login.php' : $_SERVER['HTTP_REFERER']));
    exit();
}

$pageTitle = "Quản lý chat tư vấn";
$userId = (int)($_SESSION['user_id'] ?? 0);

// Get staff info
$staff = Database::fetch(
    "SELECT staff_id FROM staff WHERE user_id = ? AND status = 'active'",
    [$userId]
);

if (!$staff) {
    die('Bạn không có quyền truy cập trang này.');
}

$staffId = (int)$staff['staff_id'];

// Get active chats assigned to this staff member
$chats = Database::fetchAll(
    "SELECT 
        cs.chat_id,
        cs.user_id,
        cs.guest_token,
        cs.status,
        cs.last_message_at,
        cs.created_at,
        COALESCE(u.name, 'Khách hàng') as customer_name,
        COALESCE(u.email, cs.guest_token) as customer_email,
        COALESCE(u.phone, '') as customer_phone,
        (SELECT COUNT(*) FROM chat_messages WHERE chat_id = cs.chat_id AND sender_type = 'user') as message_count,
        (SELECT message FROM chat_messages WHERE chat_id = cs.chat_id ORDER BY created_at DESC LIMIT 1) as last_message
     FROM chat_sessions cs
     LEFT JOIN users u ON cs.user_id = u.user_id
     WHERE cs.staff_id = ? AND cs.status = 'open'
     ORDER BY COALESCE(cs.last_message_at, cs.created_at) DESC, cs.created_at DESC",
    [$staffId]
);

include __DIR__ . '/../includes/header.php';
?>

<section class="py-5 mt-5">
    <div class="container-fluid">
        <h1 class="fw-bold mb-4">
            <i class="bi bi-chat-dots me-2"></i>Quản lý Chat Tư Vấn
        </h1>

        <div class="row">
            <!-- Chat List -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm" style="height: 70vh; overflow-y: auto;">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-chat-left-quote me-2"></i>Danh sách cuộc trò chuyện
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($chats)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-chat-left-dots display-4 d-block mb-3"></i>
                                <p>Chưa có cuộc trò chuyện nào</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($chats as $chat): ?>
                                <button class="list-group-item list-group-item-action text-start border-0 chat-item" 
                                        data-chat-id="<?php echo (int)$chat['chat_id']; ?>"
                                        onclick="selectChat(this)">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 fw-bold">
                                                <?php echo htmlspecialchars($chat['customer_name'] ?? 'Khách hàng', ENT_QUOTES, 'UTF-8'); ?>
                                            </h6>
                                            <small class="text-muted d-block">
                                                <?php echo htmlspecialchars($chat['customer_email'] ?? $chat['guest_token'], ENT_QUOTES, 'UTF-8'); ?>
                                            </small>
                                            <small class="text-truncate d-block text-muted" style="font-size: 0.85em;">
                                                <?php echo htmlspecialchars(substr($chat['last_message'] ?? 'Không có tin nhắn', 0, 40), ENT_QUOTES, 'UTF-8'); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted d-block" style="font-size: 0.75em;">
                                                <?php 
                                                    $time = strtotime($chat['last_message_at'] ?? $chat['created_at']);
                                                    echo date('H:i', $time); 
                                                ?>
                                            </small>
                                            <span class="badge bg-success"><?php echo $chat['message_count']; ?></span>
                                        </div>
                                    </div>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Chat Window -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm" style="height: 70vh; display: flex; flex-direction: column;">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1 fw-bold" id="chatTitle">Chọn cuộc trò chuyện</h5>
                            <small id="chatSubtitle">Nhấp vào cuộc trò chuyện để bắt đầu</small>
                        </div>
                        <button class="btn btn-sm btn-light" id="closeChat" onclick="closeChat()" style="display: none;">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <!-- Messages Area -->
                    <div class="card-body flex-grow-1 overflow-y-auto" id="messagesContainer" style="display: none;">
                        <!-- Messages will be loaded here -->
                    </div>

                    <!-- Input Area -->
                    <div class="card-footer" id="inputArea" style="display: none;">
                        <form id="chatForm" onsubmit="sendMessage(event)">
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control form-control-lg" 
                                       id="messageInput" 
                                       placeholder="Nhập tin nhắn..."
                                       autocomplete="off">
                                <button class="btn btn-success btn-lg" type="submit">
                                    <i class="bi bi-send me-1"></i>Gửi
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Empty State -->
                    <div class="card-body d-flex align-items-center justify-content-center" id="emptyState">
                        <div class="text-center text-muted">
                            <i class="bi bi-chat-left-dots display-1 d-block mb-3"></i>
                            <h5>Chọn cuộc trò chuyện để bắt đầu</h5>
                            <p>Nhấp vào một khách hàng từ danh sách bên trái</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<style>
    .chat-item {
        padding: 1rem !important;
        border-bottom: 1px solid #e9ecef !important;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .chat-item:hover {
        background-color: #f8f9fa;
    }

    .chat-item.active {
        background-color: #e7f3ff;
        border-left: 4px solid #0d6efd;
    }

    #messagesContainer {
        background-color: #f8f9fa;
        padding: 1.5rem;
    }

    .message-group {
        margin-bottom: 1.5rem;
        display: flex;
        gap: 0.75rem;
    }

    .message-group.customer {
        justify-content: flex-end;
    }

    .message-bubble {
        max-width: 70%;
        padding: 0.75rem 1rem;
        border-radius: 1rem;
        word-wrap: break-word;
    }

    .message-group:not(.customer) .message-bubble {
        background-color: white;
        border: 1px solid #dee2e6;
        color: #333;
    }

    .message-group.customer .message-bubble {
        background-color: #0d6efd;
        color: white;
    }

    .message-time {
        font-size: 0.8rem;
        color: #999;
        margin-top: 0.25rem;
        text-align: right;
    }

    .message-group.customer .message-time {
        text-align: right;
    }
</style>

<script>
let currentChatId = null;
let messageRefreshInterval = null;

function selectChat(element) {
    // Remove active class from all items
    document.querySelectorAll('.chat-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Add active class to clicked item
    element.classList.add('active');
    
    currentChatId = parseInt(element.getAttribute('data-chat-id'));
    loadChatMessages();
}

function loadChatMessages() {
    if (!currentChatId) return;

    console.log('[CHAT-ADVISOR] Loading messages for chat:', currentChatId);
    
    fetch(`../api/chat-get-messages.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `chat_id=${currentChatId}`
    })
    .then(response => {
        console.log('[CHAT-ADVISOR] API response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('[CHAT-ADVISOR] API response data:', data);
        
        if (data.success) {
            console.log('[CHAT-ADVISOR] Messages found:', data.messages.length);
            displayMessages(data);
            
            // Update chat info
            document.getElementById('chatTitle').textContent = data.customer_name || 'Khách hàng';
            document.getElementById('chatSubtitle').innerHTML = 
                `<small class="text-whitespace-nowrap">${data.customer_email || 'Khách'}</small>`;
            
            // Show message area
            document.getElementById('emptyState').style.display = 'none';
            document.getElementById('messagesContainer').style.display = 'block';
            document.getElementById('inputArea').style.display = 'block';
            document.getElementById('closeChat').style.display = 'block';
            
            // Scroll to bottom
            setTimeout(() => {
                document.getElementById('messagesContainer').scrollTop = 
                    document.getElementById('messagesContainer').scrollHeight;
            }, 100);
        } else {
            console.error('[CHAT-ADVISOR] API error:', data.message);
            alert('Lỗi: ' + data.message);
        }
    })
    .catch(error => {
        console.error('[CHAT-ADVISOR] Fetch error:', error);
    });
}

function displayMessages(data) {
    const container = document.getElementById('messagesContainer');
    container.innerHTML = '';
    
    data.messages.forEach(msg => {
        const isStaff = msg.sender_type === 'staff';
        const messageDiv = document.createElement('div');
        messageDiv.className = `message-group ${isStaff ? 'staff' : 'customer'}`;
        
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.textContent = msg.message;
        
        const timeDiv = document.createElement('small');
        timeDiv.className = 'message-time';
        timeDiv.textContent = new Date(msg.created_at).toLocaleTimeString('vi-VN', 
            { hour: '2-digit', minute: '2-digit' });
        
        messageDiv.appendChild(bubble);
        messageDiv.appendChild(timeDiv);
        container.appendChild(messageDiv);
    });
}

function sendMessage(event) {
    event.preventDefault();
    
    if (!currentChatId) {
        alert('Vui lòng chọn cuộc trò chuyện');
        return;
    }
    
    const message = document.getElementById('messageInput').value.trim();
    if (!message) return;
    
    console.log('[CHAT-ADVISOR] Sending message to chat:', currentChatId);
    console.log('[CHAT-ADVISOR] Message content:', message);
    
    const formData = new FormData();
    formData.append('chat_id', currentChatId);
    formData.append('message', message);
    
    fetch(`../api/chat-send-staff.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('[CHAT-ADVISOR] Send response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('[CHAT-ADVISOR] Send response:', data);
        
        if (data.success) {
            document.getElementById('messageInput').value = '';
            console.log('[CHAT-ADVISOR] Message sent successfully, reloading messages');
            loadChatMessages();
        } else {
            console.error('[CHAT-ADVISOR] Send failed:', data.message);
            alert('Lỗi: ' + data.message);
        }
    })
    .catch(error => {
        console.error('[CHAT-ADVISOR] Send error:', error);
        alert('Có lỗi xảy ra. Vui lòng thử lại.');
    });
}

function closeChat() {
    currentChatId = null;
    document.getElementById('emptyState').style.display = 'flex';
    document.getElementById('messagesContainer').style.display = 'none';
    document.getElementById('inputArea').style.display = 'none';
    document.getElementById('closeChat').style.display = 'none';
    document.querySelectorAll('.chat-item').forEach(item => {
        item.classList.remove('active');
    });
}

// Auto-refresh messages every 2 seconds
function autoRefreshMessages() {
    if (currentChatId) {
        loadChatMessages();
    } else {
        // If no chat selected, refresh the chat list
        location.reload();
    }
}

// Auto-refresh chat list every 3 seconds
function autoRefreshChatList() {
    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            // Parse the new chat list
            const parser = new DOMParser();
            const newDoc = parser.parseFromString(html, 'text/html');
            const newList = newDoc.querySelector('.list-group');
            const currentList = document.querySelector('.list-group');
            
            if (newList && currentList) {
                // Replace the list with new content
                const oldHTML = currentList.innerHTML;
                const newHTML = newList.innerHTML;
                
                if (oldHTML !== newHTML) {
                    currentList.innerHTML = newHTML;
                    
                    // Re-attach event listeners to chat items
                    document.querySelectorAll('.chat-item').forEach(item => {
                        item.addEventListener('click', function() {
                            selectChat(this);
                        });
                    });
                    
                    // If current chat is selected, keep it selected
                    if (currentChatId) {
                        const activeItem = document.querySelector(`[data-chat-id="${currentChatId}"]`);
                        if (activeItem) {
                            activeItem.classList.add('active');
                        }
                    }
                }
            }
        })
        .catch(error => console.error('Error refreshing chat list:', error));
}

setInterval(autoRefreshMessages, 2000);
setInterval(autoRefreshChatList, 3000);

// Initial load
document.addEventListener('DOMContentLoaded', () => {
    // Auto-select first chat if available
    const firstChat = document.querySelector('.chat-item');
    if (firstChat) {
        firstChat.click();
    }
});
</script>
