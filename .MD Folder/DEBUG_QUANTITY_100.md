# 🐛 DEBUG LỖI QUANTITY = 100

## 🔍 **CÁCH KIỂM TRA VÀ SỬA:**

### **1. Chạy script reset database:**
```bash
# Mở trình duyệt:
http://localhost/Cap%201%20-%202/force-reset-cart.php
```

### **2. Chạy script test API:**
```bash
# Mở trình duyệt:
http://localhost/Cap%201%20-%202/test-api.php
```

### **3. Kiểm tra database trực tiếp:**
```sql
-- Xem dữ liệu trong cart
SELECT * FROM cart ORDER BY created_at DESC LIMIT 10;

-- Xem dữ liệu trong inventory
SELECT item_id, name, quantity, is_for_sale, price_type FROM inventory WHERE is_for_sale = TRUE LIMIT 10;

-- Xóa tất cả cart
DELETE FROM cart;

-- Reset AUTO_INCREMENT
ALTER TABLE cart AUTO_INCREMENT = 1;
```

## 🔧 **CÁC NGUYÊN NHÂN CÓ THỂ:**

### **1. Dữ liệu cũ trong database:**
- Có records với quantity = 100
- Cần xóa và reset

### **2. Cache trình duyệt:**
- JavaScript cũ được cache
- Cần Ctrl+F5 để hard refresh

### **3. Session cũ:**
- Session có dữ liệu cũ
- Cần đăng xuất và đăng nhập lại

### **4. API không được gọi:**
- JavaScript lỗi
- Network error
- CORS issue

## 🚀 **CÁCH SỬA TỪNG BƯỚC:**

### **Bước 1: Reset Database**
```bash
# Chạy script:
http://localhost/Cap%201%20-%202/force-reset-cart.php
```

### **Bước 2: Clear Browser Cache**
- Nhấn Ctrl+F5 (hard refresh)
- Hoặc mở Incognito/Private mode
- Hoặc xóa cache trình duyệt

### **Bước 3: Test API**
```bash
# Chạy script:
http://localhost/Cap%201%20-%202/test-api.php
```

### **Bước 4: Test Thực Tế**
1. Đăng xuất và đăng nhập lại
2. Vào Shop Bán Hàng
3. Thêm sản phẩm vào giỏ
4. Kiểm tra quantity = 1

## 🔍 **DEBUG CHI TIẾT:**

### **1. Kiểm tra Console:**
```javascript
// Mở Developer Tools (F12)
// Xem tab Console có lỗi gì không
```

### **2. Kiểm tra Network:**
```javascript
// Tab Network
// Xem request đến api/add-to-cart.php
// Kiểm tra response
```

### **3. Kiểm tra Database:**
```sql
-- Xem cart table
SELECT * FROM cart WHERE user_id = 1;

-- Xem inventory table
SELECT * FROM inventory WHERE item_id = 1;
```

## 🎯 **KẾT QUẢ MONG ĐỢI:**

### **Sau khi sửa:**
- ✅ Nhấn "Thêm vào giỏ" → Quantity = 1
- ✅ Nhấn lần nữa → Quantity = 2
- ✅ Hiển thị "Còn lại: X cái"
- ✅ Auto refresh sau 1.5s

### **Nếu vẫn lỗi:**
- Kiểm tra Console có lỗi JavaScript
- Kiểm tra Network có request không
- Kiểm tra Database có dữ liệu đúng không

## 📞 **HỖ TRỢ:**

Nếu vẫn không sửa được, hãy:
1. Chạy `force-reset-cart.php`
2. Chụp màn hình Console (F12)
3. Chụp màn hình Network tab
4. Gửi kết quả để debug tiếp

**Made with ❤️ by Goodwill Vietnam**
