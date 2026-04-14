# 🎯 Hướng Dẫn Test Hệ Thống Chat Tư Vấn Viên

## ✅ Trạng Thái Hiện Tại
Hệ thống chat đã được sửa chữa và hoạt động chính xác. Tất cả các vấn đề về vai trò (role) và định tuyến tin nhắn đã được khắc phục.

### Những gì đã sửa:
1. ✓ **Vai trò (Role)** - Thay đổi từ so sánh chuỗi sang kiểm tra role_id (số)
2. ✓ **Định tuyến tư vấn viên** - Sử dụng `role_id = 4` thay vì tên vị trí
3. ✓ **Nhiều tài khoản nhân viên** - Xóa tài khoản test, giữ lại 1 tư vấn viên chính
4. ✓ **Gán chat** - Tất cả chat hiện được gán cho tư vấn viên đúng (Staff ID: 3)
5. ✓ **Kiểm tra vai trò** - Tất cả API đều chấp nhận cả 'staff' và 'nhân viên'

---

## 🧪 Cách Test Hệ Thống (Từng Bước)

### **Bước 1: Chuẩn Bị 2 Cửa Sổ Trình Duyệt**
- Cửa sổ 1: Đăng nhập tài khoản tư vấn viên
- Cửa sổ 2: Đăng nhập tài khoản khách hàng

### **Bước 2: Đăng Nhập Tư Vấn Viên (Cửa Sổ 1)**

**Thông tin tài khoản:**
- Email: `advisor1@gwvn.test`
- Mật khẩu: `123456`

**Sau khi đăng nhập:**
1. Vào trang: `http://localhost/GW_VN%20Ver%20Final/chat-advisor.php`
2. Bạn sẽ thấy danh sách các cuộc trò chuyện:
   - **Chat 1** - test1@gmail.com (7 tin nhắn)
   - **Chat 2** - test2@gmail.com (13 tin nhắn)

3. **Mở Developer Tools** (F12):
   - Chuyển đến tab **Console**
   - Bạn sẽ thấy các thông báo debug từ hệ thống

### **Bước 3: Đăng Nhập Khách Hàng (Cửa Sổ 2)**

**Chọn một tài khoản khách hàng từ danh sách sau:**
```
test1@gmail.com - mật khẩu: 123456
test2@gmail.com - mật khẩu: 123456
test3@gmail.com - mật khẩu: 123456
... tới test10@gmail.com
```

**Ví dụ sử dụng test1:**
- Email: `test1@gmail.com`
- Mật khẩu: `123456`

### **Bước 4: Khách Hàng Gửi Tin Nhắn**

**Trên bất kỳ trang nào:**
1. Tìm **nút chat** hình tròn ở góc dưới bên phải
2. Nhấp vào để mở cửa sổ chat
3. Gõ tin nhắn, ví dụ: "Xin chào, tôi cần hỗ trợ"
4. Nhấp **Gửi**

**Khi gửi tin nhắn, bạn sẽ thấy:**
- Tin nhắn xuất hiện trong cửa sổ chat của mình
- Tin nhắn được lưu vào cơ sở dữ liệu ngay lập tức

### **Bước 5: Kiểm Tra Trên Tư Vấn Viên (Cửa Sổ 1)**

**Trong console của trình duyệt, bạn sẽ thấy:**
```
[CHAT-ADVISOR] Loading messages for chat: 1
[CHAT-ADVISOR] API response status: 200
[CHAT-ADVISOR] API response data: {success: true, messages: [...]}
[CHAT-ADVISOR] Messages found: 8
```

**Trên giao diện:**
1. Danh sách chat sẽ cập nhật với dấu thời gian mới
2. Nhấp vào chat để mở nó
3. Bạn sẽ thấy tin nhắn mới từ khách hàng

### **Bước 6: Tư Vấn Viên Gửi Trả Lời**

1. Gõ tin nhắn trả lời, ví dụ: "Xin chào! Mình sẵn sàng giúp bạn"
2. Nhấp **Gửi**
3. Tin nhắn sẽ xuất hiện trong cuộc trò chuyện

**Trong console sẽ thấy:**
```
[CHAT-ADVISOR] Sending message to chat: 1
[CHAT-ADVISOR] Message content: Xin chào! Mình sẵn sàng giúp bạn
[CHAT-ADVISOR] Send response status: 200
[CHAT-ADVISOR] Send response: {success: true, ...}
```

### **Bước 7: Kiểm Tra Khách Hàng Nhận Được**

**Trên cửa sổ khách hàng:**
1. Cửa sổ chat sẽ tự động cập nhật mỗi 2 giây
2. Bạn sẽ thấy tin nhắn trả lời từ tư vấn viên
3. Trò chuyện sẽ hiển thị đầy đủ

---

## 🔍 Nếu Có Vấn Đề - Kiểm Tra Danh Sách Này

### **Vấn Đề: Tư vấn viên không thấy tin nhắn mới**

**Kiểm tra 1: Browser Console**
- Mở F12 → Console tab
- Tìm thông báo lỗi trong `[CHAT-ADVISOR]`
- Nếu có lỗi, ghi lại và kiểm tra mục tương ứng dưới đây

**Kiểm tra 2: Network Tab**
- Mở F12 → Network tab
- Lọc theo "XHR" (Ajax requests)
- Kiểm tra:
  - `chat-get-messages.php` có trả về status 200 không?
  - Response có chứa messages không?

**Kiểm tra 3: Đăng Nhập**
- Bạn có đảm bảo đã đăng nhập với `advisor1@gwvn.test` không?
- Mật khẩu: `123456`

**Kiểm tra 4: Refresh Trang**
- Thu nhập `F5` hoặc Ctrl+R để refresh
- Một số lần có thể cần refresh vài lần

### **Vấn Đề: Auto-refresh không hoạt động**

**Giải pháp:**
1. Mở Console (F12)
2. Gõ: `setInterval(() => loadChatMessages(), 2000);`
3. Hoặc refresh trang (F5)

### **Vấn Đề: Khách hàng không thấy cửa sổ chat**

**Kiểm tra:**
1. Cược sổ trình duyệt có mở trang đầy đủ không?
2. Nút chat có tồn tại ở góc dưới phải không?
3. Bạn có scroll xuống để tìm thấy nó không?

### **Vấn Đề: Tin nhắn không gửi được**

**Kiểm tra:**
1. Tin nhắn có trống không? Nhập nội dung nhé!
2. Mở Console (F12) để xem lỗi chi tiết
3. Kiểm tra Network tab để xem API response

---

## 📊 Thông Tin Hữu Ích

### **Tài Khoản Test Có Sẵn**

**Tư Vấn Viên:**
```
Email: advisor1@gwvn.test
Pass: 123456
Role: Staff (Nhân Viên)
```

**Khách Hàng Test (10 tài khoản):**
```
test1@gmail.com - Pass: 123456
test2@gmail.com - Pass: 123456
test3@gmail.com - Pass: 123456
test4@gmail.com - Pass: 123456
test5@gmail.com - Pass: 123456
test6@gmail.com - Pass: 123456
test7@gmail.com - Pass: 123456
test8@gmail.com - Pass: 123456
test9@gmail.com - Pass: 123456
test10@gmail.com - Pass: 123456
```

### **Các Script Test Có Sẵn**

```bash
# Kiểm tra toàn bộ hệ thống
php test_advisor_flow.php

# Kiểm tra tin nhắn khách hàng
php test_customer_message.php

# Danh sách các chat hiện tại
php full_chat_test.php
```

---

## 🎨 Giao Diện

### **Tư Vấn Viên Dashboard**
```
┌─────────────────────────────────────────────┐
│ Quản lý Chat Tư Vấn                         │
├──────────────┬──────────────────────────────┤
│ Danh sách    │                              │
│ ────────────│ Chọn cuộc trò chuyện để      │
│ • test1     │ bắt đầu                      │
│ • test2     │                              │
│             │                              │
│             │ Nhấp vào một khách hàng từ  │
│             │ danh sách bên trái           │
└──────────────┴──────────────────────────────┘
```

Khi chọn một chat:
```
┌──────────────────────────────────────────────┐
│ test1 (test1@gmail.com)        [x]           │
├──────────────────────────────────────────────┤
│                                              │
│ [Tư vấn viên]: Xin chào!                    │
│                                              │
│                   [Khách hàng]: Xin chào!   │
│                                              │
│ [Nhập tin nhắn...]        [Gửi]            │
└──────────────────────────────────────────────┘
```

### **Cửa Sổ Chat Của Khách Hàng**
```
╭─────────────────────╮
│ 💬 Hỗ Trợ           │
├─────────────────────┤
│                     │
│ [Tư vấn viên]: Hi!  │
│                     │
│        [Bạn]: Xin! │
│                     │
│ [Nhập...]   [Gửi]  │
╰─────────────────────╯
```

---

## 🔧 Kỹ Thuật Chi Tiết

### **Luồng Tin Nhắn**
```
Khách hàng gửi → chat-send.php → chat_messages table
      ↓
Tư vấn viên nhìn → chat-advisor.php auto-refresh → chat-get-messages.php
      ↓
Hiển thị trong dashboard
      ↓
Tư vấn viên trả lời → chat-send-staff.php → chat_messages table
      ↓
Khách hàng widget auto-refresh → chat-get-messages.php
      ↓
Hiển thị trong cửa sổ chat
```

### **Auto-Refresh Timing**
- Khách hàng widget: **2 giây**
- Tư vấn viên dashboard: **2 giây** (messages), **3 giây** (chat list)

### **Cơ Sở Dữ Liệu**
- **Bảng**: `chat_sessions`, `chat_messages`
- **Mã hóa**: UTF-8MB4 (hỗ trợ Tiếng Việt)
- **Cơ chế**: Polling (không dùng WebSocket)

---

## ✨ Tính Năng Hiện Tại

✅ Khách hàng gửi tin nhắn  
✅ Tư vấn viên nhận tin nhắn  
✅ Tư vấn viên trả lời  
✅ Khách hàng nhận trả lời  
✅ Auto-refresh cả hai phía  
✅ Lưu lịch sử trò chuyện  
✅ Hỗ trợ Tiếng Việt đầy đủ  
✅ Debug console logging  

---

## 🚀 Các Bước Tiếp Theo (Tùy Chọn)

Nếu muốn thêm tính năng:
1. **Thông báo âm thanh** khi có tin nhắn mới
2. **Đánh dấu đã đọc** tin nhắn
3. **Tìm kiếm** tin nhắn cũ
4. **Tệp đính kèm** (ảnh, tài liệu)
5. **Bot tự động** trả lời tin nhắn phổ biến

---

## 📞 Support

Nếu gặp vấn đề:
1. Kiểm tra console.log (F12)
2. Chạy `test_advisor_flow.php`
3. Xem Network tab để kiểm tra API calls
4. Liên hệ hỗ trợ nếu cần

**Hệ thống hiện đang hoạt động chính xác! 🎉**
