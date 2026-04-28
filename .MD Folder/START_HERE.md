# 🚀 BẮT ĐẦU TẠI ĐÂY - GOODWILL VIETNAM

## ⚡ HƯỚNG DẪN NHANH 5 PHÚT

---

## BƯỚC 1: IMPORT DATABASE (QUAN TRỌNG!)

### **Mở phpMyAdmin:**
```
http://localhost/phpmyadmin
```

### **Tạo database:**
```
Tên: goodwill_vietnam
Collation: utf8mb4_unicode_ci
```

### **Import SQL theo thứ tự:**

**📁 CHỌN database `goodwill_vietnam` trước rồi mới import!**

```
1️⃣ Import: database/schema.sql
   → Đợi xong → ✅

2️⃣ Import: database/update_schema.sql
   → Đợi xong → ✅

3️⃣ Import: database/campaigns_simple.sql
   → Đợi xong → ✅
```

**⚠️ LƯU Ý:**
- Đã CHỌN database trước khi import
- Không dùng file `campaigns_update.sql` (có lỗi USE)
- Dùng file `campaigns_simple.sql` (không lỗi)

---

## BƯỚC 2: KIỂM TRA

### **Test database:**
```
http://localhost/Cap%201%20-%202/test-database.php
```

**Kết quả mong đợi:**
- ✅ Kết nối thành công
- ✅ 15+ bảng hiển thị
- ✅ Có dữ liệu mẫu

**Nếu có lỗi sync:**
- Click nút "🔄 Sync vật phẩm vào kho"

---

## BƯỚC 3: ĐĂNG NHẬP

### **Admin:**
```
URL: http://localhost/Cap%201%20-%202/admin/dashboard.php

Email: admin@goodwillvietnam.com
Password: password
```

### **Hoặc tạo User mới:**
```
http://localhost/Cap%201%20-%202/register.php
```

---

## BƯỚC 4: TEST CHỨC NĂNG

### ✅ Test Quyên góp → Shop:
```
1. Login user
2. Quyên góp vật phẩm (số lượng: 1)
3. Login admin → Duyệt
4. Vào Shop → Thấy vật phẩm ✅
5. Thêm vào giỏ → Thanh toán
6. Vào Shop → Vật phẩm BIẾN MẤT (Hết hàng) ✅
```

### ✅ Test Chiến dịch:
```
1. Login user A
2. Tạo chiến dịch (50 áo, 30 quần)
3. Login admin → Duyệt chiến dịch
4. Login user B
5. Quyên góp vào chiến dịch → Tiến độ cập nhật ✅
6. Đăng ký tình nguyện viên ✅
```

---

## 🎯 CÁC TRANG CHÍNH

### **Trang công khai:**
```
Trang chủ:     http://localhost/Cap%201%20-%202/
Shop:          http://localhost/Cap%201%20-%202/shop.php
Chiến dịch:    http://localhost/Cap%201%20-%202/campaigns.php
Giới thiệu:    http://localhost/Cap%201%20-%202/about.php
```

### **Trang User:**
```
Giỏ hàng:      http://localhost/Cap%201%20-%202/cart.php
Hồ sơ:         http://localhost/Cap%201%20-%202/profile.php
Quyên góp:     http://localhost/Cap%201%20-%202/my-donations.php
Đơn hàng:      http://localhost/Cap%201%20-%202/my-orders.php
Đổi MK:        http://localhost/Cap%201%20-%202/change-password.php
```

### **Admin:**
```
Dashboard:     http://localhost/Cap%201%20-%202/admin/dashboard.php
Quyên góp:     http://localhost/Cap%201%20-%202/admin/donations.php
Kho hàng:      http://localhost/Cap%201%20-%202/admin/inventory.php
```

---

## 🎨 TÍNH NĂNG NỔI BẬT

### 1️⃣ **Header chung toàn trang**
- ✅ Logo + Menu giống hệt nhau
- ✅ Tự động active trang hiện tại
- ✅ Giỏ hàng (số lượng realtime)

### 2️⃣ **Shop bán hàng**
- ✅ 2 loại giá: Miễn phí / Giá rẻ
- ✅ Bộ lọc: Danh mục + Loại giá + Tìm kiếm

### 3️⃣ **Giỏ hàng đẹp**
- ✅ UI hiện đại, hover effect
- ✅ Tăng/giảm số lượng
- ✅ Hiển thị "Còn lại: X"
- ✅ Logic hết hàng

### 4️⃣ **Chiến dịch**
- ✅ User tạo (liệt kê vật phẩm cần)
- ✅ Admin duyệt
- ✅ Quyên góp trực tiếp vào chiến dịch
- ✅ Đăng ký tình nguyện viên

---

## 🐛 LỖI THƯỜNG GẶP

### ❌ Lỗi: "USE goodwill_vietnam syntax error"

**GIẢI PHÁP:**
```
✅ ĐÚNG: Import campaigns_simple.sql
❌ SAI: Import campaigns_update.sql
```

### ❌ Lỗi: "Table doesn't exist"

**GIẢI PHÁP:**
```
Import lại ĐÚNG THỨ TỰ:
1. schema.sql
2. update_schema.sql
3. campaigns_simple.sql
```

### ❌ Lỗi: "Quyên góp không hiện shop"

**GIẢI PHÁP:**
```
1. Vào: test-database.php
2. Click: "🔄 Sync vật phẩm vào kho"
```

### ❌ Lỗi: "Cannot modify header"

**GIẢI PHÁP:**
```
Đảm bảo session_start() ở dòng đầu tiên
Không có khoảng trắng/BOM trước <?php
```

---

## 📚 TÀI LIỆU HƯỚNG DẪN

| File | Mô tả |
|------|-------|
| `START_HERE.md` | Bắt đầu nhanh (file này) |
| `DATABASE_IMPORT_GUIDE.md` | Hướng dẫn import DB chi tiết |
| `COMPLETE_GUIDE.md` | Tổng quan toàn bộ dự án |
| `README.md` | Tài liệu đầy đủ |
| `QUICKSTART.md` | Quick start 5 phút |

---

## 🎯 CHECKLIST HOÀN THÀNH

```bash
✅ Cài XAMPP
✅ Start Apache + MySQL
✅ Copy dự án vào htdocs
✅ Tạo database goodwill_vietnam
✅ Import schema.sql
✅ Import update_schema.sql
✅ Import campaigns_simple.sql
✅ Test: test-database.php
✅ Sync vật phẩm (nếu cần)
✅ Login admin thành công
✅ Test quyên góp → shop
✅ Test giỏ hàng → hết hàng
✅ Test chiến dịch
```

---

## 🎉 HOÀN TẤT!

Website đã sẵn sàng sử dụng!

**URLs:**
- 🏠 Trang chủ: `http://localhost/Cap%201%20-%202/`
- 👨‍💼 Admin: `http://localhost/Cap%201%20-%202/admin/`
- 🧪 Test DB: `http://localhost/Cap%201%20-%202/test-database.php`

**Login Admin:**
- Email: `admin@goodwillvietnam.com`
- Password: `password`

---

**Made with ❤️ by Goodwill Vietnam**
