# ⚡ QUICK REFERENCE - Hệ Thống Chat Tư Vấn Viên

## 🔑 Thông Tin Đăng Nhập

### Tư Vấn Viên (Advisor)
```
Email:    advisor1@gwvn.test
Password: 123456
Role:     Staff (Nhân Viên)
```

### Khách Hàng Test (10 tài khoản)
```
Email:    test1@gmail.com - test10@gmail.com
Password: 123456 (tất cả)
Role:     Customer (Khách Hàng)
```

---

## 🌐 URLs Chính

| Chức Năng | URL |
|-----------|-----|
| 📱 Dashboard Tư Vấn | `/chat-advisor.php` |
| 💬 Chat Widget (Khách) | Tự động ở tất cả trang |
| 🧪 Test Hệ Thống | `/test_advisor_flow.php` |
| 🧪 Test Tin Nhắn | `/test_customer_message.php` |
| 🧪 Test Đầy Đủ | `/full_chat_test.php` |
| 📖 Hướng Dẫn Test | `/CHAT_TEST_GUIDE.md` |
| 📋 Tóm Tắt Sửa | `/CHAT_FIXES_SUMMARY.md` |

---

## 🔄 Luồng Hoạt Động

```
KHÁCH HÀNG                          TƯ VẤN VIÊN
├─ Vào chat widget                  ├─ Vào /chat-advisor.php
├─ Nhập tin nhắn                    ├─ Xem danh sách chat
├─ Nhấp Gửi                         ├─ Chọn chat
│  └─ POST api/chat-send.php        ├─ Xem tin nhắn (auto refresh)
│     └─ Lưu vào DB                 └─ Nhập & gửi trả lời
│                                      └─ POST api/chat-send-staff.php
│                                         └─ Lưu vào DB
│
├─ Widget auto-refresh (2 giây)     └─ Dashboard auto-refresh (2 giây)
│  └─ GET api/chat-get-messages.php    └─ GET api/chat-get-messages.php
│     └─ Hiển thị trả lời               └─ Hiển thị tin mới
```

---

## 📊 Cơ Sở Dữ Liệu

### Bảng Chính
- `chat_sessions` - Quản lý các cuộc trò chuyện
- `chat_messages` - Lưu trữ tin nhắn
- `users` - Thông tin người dùng
- `staff` - Thông tin nhân viên

### Truy Vấn Hữu Ích
```sql
-- Xem tất cả chat
SELECT * FROM chat_sessions WHERE status = 'open';

-- Xem tin nhắn trong 1 chat
SELECT * FROM chat_messages WHERE chat_id = 1 ORDER BY created_at;

-- Xem tư vấn viên
SELECT * FROM users WHERE email = 'advisor1@gwvn.test';

-- Xem staff assignment
SELECT * FROM staff WHERE user_id = 14;
```

---

## 🔧 API Endpoints

| Endpoint | Method | Mục Đích |
|----------|--------|---------|
| `/api/chat-init.php` | POST | Khởi tạo chat |
| `/api/chat-send.php` | POST | Khách gửi tin |
| `/api/chat-get-messages.php` | POST | Lấy tin nhắn |
| `/api/chat-send-staff.php` | POST | Tư vấn gửi tin |
| `/api/chat-get-status.php` | GET | Kiểm tra trạng thái |

### Ví Dụ Request
```javascript
// Chat get messages
fetch('/api/chat-get-messages.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'chat_id=1'
})
.then(r => r.json())
.then(data => console.log(data));

// Expected Response
{
    "success": true,
    "chat_id": 1,
    "messages": [
        {
            "sender_type": "user",
            "sender_id": 4,
            "message": "Xin chào",
            "created_at": "2026-03-07 10:39:33"
        },
        ...
    ],
    "customer_name": "test1",
    "customer_email": "test1@gmail.com"
}
```

---

## 🧪 Cách Test Nhanh

### 1️⃣ Kiểm Tra Backend (30 giây)
```bash
php test_advisor_flow.php
# Sẽ in ra:
# ✓ Advisor found
# ✓ Found 2 open chat(s)
# ✓ Retrieved 13 messages
# ✓ API response would be...
```

### 2️⃣ Kiểm Tra Frontend (5 phút)
```
1. Mở 2 browser window
2. Window 1: Login as advisor1@gwvn.test → /chat-advisor.php
3. Window 2: Login as test1@gmail.com → click chat widget
4. Window 2: Gửi tin nhắn
5. Window 1: Nhìn thấy tin nhắn mới (check console F12)
6. Window 1: Trả lời
7. Window 2: Nhìn thấy trả lời
```

---

## 🔍 Debug Console (F12)

Mở Browser Development Tools → Console tab

Sẽ thấy:
```
[CHAT-ADVISOR] Loading messages for chat: 1
[CHAT-ADVISOR] API response status: 200
[CHAT-ADVISOR] API response data: {success: true, messages: [...], ...}
[CHAT-ADVISOR] Messages found: 7
```

---

## ⚠️ Nếu Có Lỗi

### "Chat không tồn tại"
→ Kiểm tra chat_id trong database

### "Không có quyền truy cập"
→ Xác nhận role: `advisor1@gwvn.test` có role_id = 4

### "Tin nhắn không gửi được"
→ Xem Network tab trong F12 → kiểm tra response

### "Auto-refresh không hoạt động"
→ Refresh trang (F5) hoặc reload browser

---

## 📝 Các Tệp Quan Trọng

```
Root/
├── chat-advisor.php              ← Bảng điều khiển tư vấn viên
├── includes/chat-widget.php      ← Chat widget khách hàng
├── includes/header.php           ← Header có role checking
├── api/
│   ├── chat-init.php            ← Khởi tạo chat
│   ├── chat-send.php            ← Khách gửi tin
│   ├── chat-send-staff.php      ← Tư vấn gửi tin
│   ├── chat-get-messages.php    ← Lấy tin nhắn
│   └── chat-get-status.php      ← Trạng thái hệ thống
├── test_advisor_flow.php         ← Test backend
├── test_customer_message.php     ← Test tin nhắn
├── full_chat_test.php            ← Test đầy đủ
├── CHAT_TEST_GUIDE.md            ← Hướng dẫn chi tiết
└── CHAT_FIXES_SUMMARY.md         ← Tóm tắt các sửa
```

---

## ✅ Checklist Sau Sửa Chữa

- ✅ Advisor account: active & valid
- ✅ Role checking: accepts both 'staff' and 'nhân viên'
- ✅ Duplicate staff: deleted (ID 1, 2)
- ✅ Chats: reassigned to correct advisor (ID 3)
- ✅ Messages: storing in database correctly
- ✅ APIs: all endpoints responding
- ✅ Auto-refresh: working on both sides
- ✅ Database: UTF-8MB4 for Vietnamese
- ✅ Console logging: enabled for debugging
- ✅ Test scripts: all passing

---

## 🎯 Tình Trạng: ✅ SẴN DÙNG

Hệ thống chat hoàn toàn hoạt động. Bạn có thể bắt đầu sử dụng ngay!

---

**Questions?** Xem `/CHAT_TEST_GUIDE.md`  
**Need details?** Xem `/CHAT_FIXES_SUMMARY.md`  
**Want to test?** Chạy `/test_advisor_flow.php`
