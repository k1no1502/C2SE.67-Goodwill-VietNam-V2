# 🎯 HEADER CHUNG - HƯỚNG DẪN CUỐI CÙNG

## ✅ HEADER ĐÃ ĐƯỢC TẠO CHUNG

File: **`includes/header.php`**

Header này sẽ được dùng cho **TẤT CẢ** các trang!

---

## 📋 HIỆN TRẠNG

### ✅ **Đã dùng header chung:**
- ✅ about.php
- ✅ my-donations.php
- ✅ my-orders.php
- ✅ change-password.php
- ✅ item-detail.php
- ✅ shop-simple.php
- ✅ campaign-detail.php
- ✅ donate-to-campaign.php
- ✅ checkout.php
- ✅ order-success.php

### ❌ **Chưa cập nhật (vẫn dùng header riêng):**
- ❌ index.php
- ❌ donate.php
- ❌ shop.php
- ❌ campaigns.php
- ❌ create-campaign.php
- ❌ cart.php
- ❌ profile.php
- ❌ items.php (nếu có)

### ⚠️ **Giữ nguyên (layout đặc biệt):**
- ⚠️ login.php (layout khác)
- ⚠️ register.php (layout khác)
- ⚠️ admin/* (có sidebar riêng)

---

## 🎯 HEADER CHUNG CÓ GÌ?

File `includes/header.php` chứa:

```html
✅ Logo: "Goodwill Vietnam"
✅ Menu:
   - 🏠 Trang chủ
   - ❤️ Quyên góp
   - 🛒 Shop Bán Hàng
   - 🏆 Chiến dịch
   - ℹ️ Giới thiệu
   
✅ Giỏ hàng (nếu đã login)
✅ Menu user dropdown
✅ Link Admin (nếu là admin)
✅ Đăng nhập/Đăng ký (nếu guest)
```

**KHÔNG thay đổi gì cả - Dùng y nguyên!**

---

## 🔄 CÁCH SỬ DỤNG

### **Template chuẩn cho MỌI trang:**

```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Tên trang";
include 'includes/header.php';
?>

<!-- NỘI DUNG RIÊNG CỦA TRANG -->
<div class="container mt-5 pt-5">
    <h1>Nội dung...</h1>
</div>

<?php include 'includes/footer.php'; ?>
```

**CHỈ CẦN:**
- Đổi `$pageTitle`
- Viết nội dung riêng
- Include header/footer

---

## ✅ VÍ DỤ CỤ THỂ

### **File: shop.php (CẦN CẬP NHẬT)**

**TRƯỚC (200 dòng):**
```php
<!DOCTYPE html>
<html>
<head>
    <title>Shop</title>
    <!-- 50 dòng -->
</head>
<body>
    <nav>
        <!-- 50 dòng menu -->
    </nav>
    
    <!-- Nội dung shop -->
    
    <footer>
        <!-- 50 dòng footer -->
    </footer>
</body>
</html>
```

**SAU (30 dòng):**
```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Code xử lý shop...
$items = Database::fetchAll(...);

$pageTitle = "Shop Bán Hàng";
include 'includes/header.php';
?>

<!-- Nội dung shop -->
<div class="container mt-5 pt-5">
    <!-- Grid sản phẩm -->
</div>

<?php include 'includes/footer.php'; ?>
```

---

## 🎨 HEADER GIỐNG NHAU 100%

### **Mọi trang đều có:**
- ✅ Logo giống nhau
- ✅ Menu giống nhau (Trang chủ, Quyên góp, Shop, Chiến dịch, Giới thiệu)
- ✅ Màu sắc giống nhau (xanh #198754)
- ✅ Font giống nhau (Roboto)
- ✅ Icons giống nhau (Bootstrap Icons)
- ✅ Giỏ hàng giống nhau
- ✅ User dropdown giống nhau

**CHỈ KHÁC:** Trang nào đang xem thì trang đó có class "active"

---

## 🔍 KIỂM TRA HEADER ĐÚNG

Mở từng trang và kiểm tra:

```bash
✅ Logo có đúng "Goodwill Vietnam"?
✅ Menu có 5 mục: Trang chủ | Quyên góp | Shop | Chiến dịch | Giới thiệu?
✅ Menu có icon không?
✅ Trang hiện tại có highlight (active)?
✅ Giỏ hàng hiển thị số lượng?
✅ Dropdown user hoạt động?
✅ Màu xanh #198754?
```

Nếu **TẤT CẢ đều ✅** → Header đúng!

---

## 📝 CHECKLIST CẬP NHẬT

Cần cập nhật các file sau để dùng header chung:

```bash
☐ index.php
☐ donate.php
☐ shop.php
☐ campaigns.php
☐ create-campaign.php
☐ cart.php
☐ profile.php
```

**Tất cả đều đổi sang template trên!**

---

## 🚨 LƯU Ý QUAN TRỌNG

### ✅ LUÔN CÓ:
1. `session_start()` - Dòng đầu tiên
2. `require_once 'config/database.php'`
3. `require_once 'includes/functions.php'`
4. Code xử lý (nếu có)
5. `$pageTitle = "..."`
6. `include 'includes/header.php'`
7. Nội dung trang
8. `include 'includes/footer.php'`

### ❌ KHÔNG:
- ❌ Viết lại `<!DOCTYPE html>`
- ❌ Viết lại `<nav>`
- ❌ Viết lại `<footer>`
- ❌ Thay đổi menu
- ❌ Thay đổi logo

---

## 🎉 KẾT QUẢ SAU KHI CẬP NHẬT

**TẤT CẢ các trang sẽ có:**
- ✅ Header giống hệt nhau
- ✅ Footer giống hệt nhau
- ✅ Logo giống nhau
- ✅ Menu giống nhau
- ✅ Màu sắc giống nhau
- ✅ Responsive giống nhau

**CHỈ KHÁC:** Nội dung riêng của từng trang!

---

**Made with ❤️ by Goodwill Vietnam**
