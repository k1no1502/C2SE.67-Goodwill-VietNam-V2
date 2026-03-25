# 💚 Goodwill Vietnam - Nền tảng Tình nguyện Số

Goodwill Vietnam là website tình nguyện trực tuyến, kết nối người tặng & người nhận & ban vận hành chủ yếu với **PHP 8 + MySQL + HTML/CSS/JS + Bootstrap 5**. Giúp các tổ chức phi lợi nhuận quản lý quyên góp, kho vật phẩm, chiến dịch và tình nguyện viên trên một hệ thống duy nhất.

## 📋 Mục lục
- [✨ Tính năng nổi bật](#-tính-năng-nổi-bật)
- [🛠 Công nghệ sử dụng](#-công-nghệ-sử-dụng)
- [⚙️ Yêu cầu hệ thống](#️-yêu-cầu-hệ-thống)
- [🚀 Hướng dẫn cài đặt nhanh](#-hướng-dẫn-cài-đặt-nhanh)
- [📁 Cấu trúc thư mục](#-cấu-trúc-thư-mục)
- [👤 Tài khoản mẫu](#-tài-khoản-mẫu)
- [💡 Chi tiết chức năng](#-chi-tiết-chức-năng)
- [🔒 Bảo mật & Tuân thủ](#-bảo-mật--tuân-thủ)
- [📊 Hướng dẫn Database Import](#-hướng-dẫn-database-import)
- [📞 Hỗ trợ & Tài liệu](#-hỗ-trợ--tài-liệu)

---

## ✨ Tính năng nổi bật

- **Form quyên góp thông minh**: Tạo nhiều vật phẩm, upload ảnh/link, nhập hàng loạt từ Excel/CSV
- **Theo dõi quyên góp**: Hiển thị tiến trình duyệt, nhập kho, phân phối bằng timeline & phần trăm hoàn thành
- **Shop tình nguyện**: Lọc danh mục, loại giá, giỏ hàng, thanh toán COD, tra cứu trạng thái giao hàng
- **Admin Dashboard**: Thống kê realtime, Chart.js, nhật ký hoạt động
- **Chiến dịch + Tình nguyện viên**: Đăng ký, cập nhật tiến độ, quản lý vật phẩm
- **Kho vật phẩm**: Duyệt quyên góp, định giá, quản lý tồn

---

## 🛠 Công nghệ sử dụng

| Tầng | Công nghệ |
|------|-----------|
| **Frontend** | HTML5, CSS3, Bootstrap 5, JavaScript, Chart.js, Leaflet |
| **Backend** | PHP 8.x (PDO, Session, REST API) |
| **Database** | MySQL 8.x (utf8mb4) |
| **Thư viện** | Bootstrap Icons, PhpSpreadsheet, ZipArchive |

---

## ⚙️ Yêu cầu hệ thống

- **Web Server**: Apache/Nginx (XAMPP, WAMP/LAMP hoặc Laragon)
- **PHP**: >= 8.0 (PDO, mbstring, zip, iconv)
- **MySQL**: >= 8.0, charset utf8mb4
- **Trình duyệt**: Chrome, Edge, Firefox (2023+)

---

## 🚀 Hướng dẫn cài đặt nhanh

### **Bước 1: Clone mã nguồn**
```bash
cd C:\xampp\htdocs
git clone <repo-url> "GW_VN Ver Final"
```

### **Bước 2: Tạo database**
1. Mở phpMyAdmin
2. Tạo database `goodwill_vietnam` (utf8mb4)
3. Import các file từ thư mục `database/`:
   - `schema.sql` (BẮT BUỘC)
   - `update_schema.sql` (BẮT BUỘC)
   - `campaigns_simple.sql` (BẮT BUỘC CHO CHIẾN DỊCH)

### **Bước 3: Cấu hình** (config/database.php)
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'goodwill_vietnam');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
```

### **Bước 4: Cấp quyền thư mục**
- Thư mục `uploads/` - CHMOD 755
- Thư mục `cache/` - CHMOD 755

### **Bước 5: Truy cập**
```
http://localhost/GW_VN%20Ver%20Final/
```

---

## 📁 Cấu trúc thư mục

```
GW_VN Ver Final/
├── admin/               # Quản trị: dashboard, donations, inventory...
├── api/                 # Endpoint AJAX/REST API
├── assets/              # CSS, JS, hình ảnh
├── config/              # database.php
├── database/            # Schema SQL
├── includes/            # header, footer, functions
├── tools/               # Công cụ hỗ trợ
├── uploads/             # Hình ảnh (ghi được)
├── Test/                # File test cases
├── donate.php           # Form quyên góp
├── shop.php             # Trang Shop
├── order-tracking.php   # Theo dõi đơn hàng
├── index.php            # Trang chủ
└── Readme.md            # Tài liệu này (Consolidated)
```

---

## 👤 Tài khoản mẫu

| Loại | Email | Mật khẩu |
|------|-------|----------|
| Admin | admin@goodwillvietnam.com | password |
| Staff | staff@goodwillvietnam.com | password |
| User | Từ đăng ký | Tự do |

> ⚠️ **Đổi mật khẩu admin ngay sau khi khởi chạy!**

---

## 💡 Chi tiết chức năng

### **👥 Người dùng**
- Quyên góp: Nhập tay atau Excel/CSV
- Shop: Lọc danh mục, giỏ hàng, COD
- Chiến dịch: Đăng ký tình nguyện
- Tài khoản: Hồ sơ, mật khẩu, lịch sử

### **🔐 Quản trị viên**
- Duyệt quyên góp, ghi chú
- Quản lý kho: Định giá, trạng thái
- Quản lý đơn hàng, chiến dịch
- Dashboard thống kê realtime
- Xuất báo cáo Excel/PDF

---

## 🔒 Bảo mật

- Mật khẩu: Băm bcrypt
- SQL: PDO Prepared Statements
- Phân quyền: Kiểm tra trên mỗi trang
- Validation: Email, số điện thoại, MIME type
- Encoding: UTF-8, charset utf8mb4

---

## 📊 Hướng dẫn Database Import

### **Thứ tự import (BẮT BUỘC)**

**Bước 1: Tạo database**
```sql
CREATE DATABASE goodwill_vietnam 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;
```

**Bước 2: Import các file (ĐÚNG THỨ TỰ)**

1. **schema.sql** (BẮT BUỘC)
   - Tạo: users, roles, donations, categories, inventory

2. **update_schema.sql** (BẮT BUỘC)
   - Thêm: cart, orders, order_items

3. **campaigns_simple.sql** (BẮT BUỘC CHO CHIẾN DỊCH)
   - Thêm: campaign_items, campaign_donations

4. **check_and_fix.sql** (TÙY CHỌN)
   - Fix: Sync quyên góp vào inventory

### **Các file trong database/**

| File | Mô tả | Status |
|------|-------|--------|
| schema.sql | Cấu trúc cơ bản | ✅ BẮT BUỘC |
| update_schema.sql | Shop + giỏ hàng | ✅ BẮT BUỘC |
| campaigns_simple.sql | Chiến dịch | ✅ BẮT BUỘC |
| check_and_fix.sql | Fix sync | ⚠️ Khi cần |

### **Kiểm tra sau import**

```sql
SELECT COUNT(*) FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'goodwill_vietnam';
```
**Kết quả:** ≥ 15 tables

### **Xử lý lỗi**

#### ❌ "Duplicate column name"
- Bỏ qua (không ảnh hưởng)

#### ❌ "Table already exists"
- Bỏ qua (SQL dùng IF NOT EXISTS)

#### ❌ "Foreign key constraint fails"
- Import sai thứ tự → Drop database & import lại

#### ❌ Vật phẩm không hiện trong Shop
- Chạy `check_and_fix.sql` để sync

---

## 🎯 Quy trình vận hành

1. **Quyên góp**: Gửi đơn → Duyệt → Nhập kho → Phân phối → Theo dõi
2. **Shop**: Chọn sản phẩm → Giỏ hàng → COD → Giao → Theo dõi
3. **Chiến dịch**: Tạo → Kêu gọi → Theo dõi → Báo cáo

---

## 📞 Hỗ trợ & Tài liệu

### **Debugging**
- Log lỗi: `apache/logs/error.log`
- Database: `config/database.php`
- Uploads: Kiểm tra quyền ghi

### **Tài liệu bổ sung**
- [INSTALL.txt](INSTALL.txt) - Checklist cài đặt
- [QUICK_START.md](QUICK_START.md) - Hướng dẫn nhanh
- [Test/](Test/) - File test cases

---

**✨ Chúc bạn triển khai nền tảng tình nguyện thành công!**

**Made with ❤️ by Goodwill Vietnam Team**

*Last updated: March 2026*
