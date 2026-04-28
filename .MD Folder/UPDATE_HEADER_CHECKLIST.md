# ✅ CHECKLIST CẬP NHẬT HEADER CHUNG

## 🎯 MỤC TIÊU
**TẤT CẢ các trang đều dùng CHUNG 1 header từ `includes/header.php`**

---

## 📝 DANH SÁCH FILE CẦN CẬP NHẬT

### ✅ ĐÃ CÓ HEADER CHUNG:
- ✅ `includes/header.php` - Header chung
- ✅ `includes/footer.php` - Footer chung
- ✅ `shop-simple.php` - Ví dụ mẫu
- ✅ `campaign-detail.php` - Chi tiết chiến dịch
- ✅ `donate-to-campaign.php` - Quyên góp vào chiến dịch

### ❌ CẦN CẬP NHẬT (Đang dùng header riêng):
- ❌ `index.php` - Trang chủ
- ❌ `donate.php` - Quyên góp
- ❌ `shop.php` - Shop bán hàng  
- ❌ `campaigns.php` - Danh sách chiến dịch
- ❌ `create-campaign.php` - Tạo chiến dịch
- ❌ `cart.php` - Giỏ hàng
- ❌ `profile.php` - Hồ sơ
- ❌ `items.php` - Vật phẩm (nếu có)

### ⚠️ GIỮ NGUYÊN (Layout đặc biệt):
- ⚠️ `login.php` - Đăng nhập (layout riêng)
- ⚠️ `register.php` - Đăng ký (layout riêng)
- ⚠️ `404.php` - Error page

### 🔧 ADMIN (Cần baseUrl):
- ❌ `admin/dashboard.php`
- ❌ `admin/donations.php`
- ❌ `admin/inventory.php`
- Tất cả file trong `admin/` folder

---

## 🔄 CÁCH CHUYỂN ĐỔI

### **TRƯỚC (Mỗi file tự viết header):**

```php
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Trang chủ</title>
    <link href="bootstrap.css">
    <!-- 50+ dòng lặp lại -->
</head>
<body>
    <nav class="navbar">
        <a href="index.php">Trang chủ</a>
        <a href="donate.php">Quyên góp</a>
        <!-- 30+ dòng navbar lặp lại -->
    </nav>
    
    <!-- NỘI DUNG -->
    
    <footer>
        <!-- 40+ dòng footer lặp lại -->
    </footer>
</body>
</html>
```

**Vấn đề:**
- ❌ Lặp code 80%
- ❌ Thay đổi phải sửa 10+ files
- ❌ Dễ sai sót, không đồng nhất

---

### **SAU (Dùng header chung):**

```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Trang chủ";
include 'includes/header.php';
?>

<!-- NỘI DUNG -->
<div class="container mt-5 pt-5">
    <h1>Nội dung trang...</h1>
</div>

<?php include 'includes/footer.php'; ?>
```

**Lợi ích:**
- ✅ Code ngắn hơn 80%
- ✅ Thay đổi 1 lần → Áp dụng tất cả
- ✅ 100% đồng nhất
- ✅ Dễ maintain

---

## 📊 SO SÁNH

| Tiêu chí | Cũ | Mới |
|----------|-----|-----|
| **Số dòng/trang** | ~200 dòng | ~30 dòng |
| **Header giống nhau?** | ❌ Khác nhau | ✅ 100% giống |
| **Thay logo/menu** | Sửa 10 files | Sửa 1 file |
| **Active menu** | Code thủ công | Tự động |
| **Maintain** | Khó | Dễ |

---

## 🎯 HEADER CHUNG CÓ GÌ?

File `includes/header.php` chứa:

### ✅ HTML Head:
- Meta tags
- Title
- CSS (Bootstrap, Icons, Custom)
- Chart.js (nếu cần)

### ✅ Navigation Bar:
- Logo: Goodwill Vietnam
- Menu:
  - 🏠 Trang chủ
  - ❤️ Quyên góp
  - 🛒 Shop Bán Hàng
  - 🏆 Chiến dịch
  - ℹ️ Giới thiệu
- **Tự động đánh dấu trang hiện tại (active)**

### ✅ User Menu (nếu đã login):
- 🛒 Giỏ hàng (+ số lượng)
- ⚙️ Quản trị (nếu admin)
- 👤 Dropdown:
  - Hồ sơ
  - Quyên góp của tôi
  - Đơn hàng của tôi
  - Chiến dịch của tôi
  - Đăng xuất

### ✅ Guest Menu (chưa login):
- Đăng nhập
- Đăng ký

---

## 🚀 HƯỚNG DẪN CẬP NHẬT

### **Bước 1: Test header chung**
```
http://localhost/Cap%201%20-%202/shop-simple.php
```
→ Kiểm tra header có đúng không

### **Bước 2: Cập nhật từng file**

**Ví dụ: `index.php`**

1. Mở file `index.php`
2. XÓA phần `<!DOCTYPE>` đến `</nav>` 
3. THÊM ở đầu file:
```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = "Trang chủ";
include 'includes/header.php';
?>
```
4. GIỮ NGUYÊN phần nội dung giữa
5. XÓA phần `<footer>` đến `</html>`
6. THÊM ở cuối file:
```php
<?php include 'includes/footer.php'; ?>
```

### **Bước 3: Test từng trang**
- Mở trang vừa sửa
- Kiểm tra header hiển thị đúng
- Kiểm tra menu active đúng trang
- Kiểm tra footer đầy đủ

---

## 💡 LƯU Ý QUAN TRỌNG

### ✅ BẮT BUỘC:
1. `session_start()` - Dòng đầu tiên
2. `require_once 'config/database.php'`
3. `require_once 'includes/functions.php'`
4. `include 'includes/header.php'`
5. Nội dung với `class="container mt-5 pt-5"`
6. `include 'includes/footer.php'`

### ✅ TÙY CHỌN:
- `$pageTitle` - Tiêu đề trang
- `$includeChartJS = true` - Nếu dùng Chart.js
- `$additionalScripts` - JS bổ sung

### ⚠️ ADMIN FILES:
```php
$baseUrl = '../'; // Quan trọng!
include '../includes/header.php';
```

---

## 📦 KẾT QUẢ MONG MUỐN

Sau khi cập nhật, TẤT CẢ các trang sẽ có:
- ✅ Header giống hệt nhau
- ✅ Logo giống nhau
- ✅ Menu giống nhau
- ✅ Màu sắc giống nhau
- ✅ Font chữ giống nhau
- ✅ Footer giống nhau
- ✅ Responsive giống nhau

**CHỈ KHÁC NHAU:** Nội dung phần giữa của từng trang!

---

## 🎨 VÍ DỤ CỤ THỂ

### **File: `donate.php`**

```php
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

requireLogin(); // Yêu cầu đăng nhập

// Xử lý form...
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Logic...
}

$pageTitle = "Quyên góp";
include 'includes/header.php';
?>

<!-- NỘI DUNG RIÊNG CỦA TRANG DONATE -->
<div class="container mt-5 pt-5">
    <div class="card">
        <div class="card-header bg-success text-white">
            <h2>Quyên góp vật phẩm</h2>
        </div>
        <div class="card-body">
            <form method="POST">
                <!-- Form fields -->
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
```

---

## 🔍 KIỂM TRA CUỐI CÙNG

Sau khi cập nhật tất cả, kiểm tra:

```bash
✅ Logo giống nhau trên mọi trang?
✅ Menu giống nhau?
✅ Menu tự động active đúng trang?
✅ Giỏ hàng hiển thị số lượng?
✅ Dropdown user hoạt động?
✅ Footer giống nhau?
✅ Responsive trên mobile?
```

---

## 📞 NẾU CÓ LỖI

### Lỗi: "Cannot modify header"
→ Đảm bảo `session_start()` ở dòng đầu, không có khoảng trắng/BOM

### Lỗi: CSS không load
→ Kiểm tra `$baseUrl` nếu ở thư mục con

### Lỗi: Menu không active
→ Header tự động xử lý, không cần làm gì

---

**TÓM LẠI:** 
- 1 header duy nhất: `includes/header.php`
- 1 footer duy nhất: `includes/footer.php`
- Tất cả trang đều include 2 file này
- KẾT QUẢ: 100% giống nhau!

**Made with ❤️ by Goodwill Vietnam**
