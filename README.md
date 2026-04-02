# 🌱 Goodwill Vietnam  
## 🎓 Capstone 2 – Duy Tan University (DTU)

<p align="center">
  <b>✨ Digital charity platform — Connecting donations, transparency, and impact ✨</b><br/>
  <i>✨ Nền tảng thiện nguyện số — Kết nối quyên góp, minh bạch tiến độ, lan tỏa giá trị ✨</i>
</p>

<p align="center">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white"/>
  <img alt="MySQL" src="https://img.shields.io/badge/MySQL-8.x-4479A1?logo=mysql&logoColor=white"/>
  <img alt="phpMyAdmin" src="https://img.shields.io/badge/phpMyAdmin-Database%20Admin-6C78AF?logo=phpmyadmin&logoColor=white"/>
  <img alt="Scrum" src="https://img.shields.io/badge/Scrum-Agile-0A66C2?logo=scrumalliance&logoColor=white"/>
  <img alt="Capstone" src="https://img.shields.io/badge/Capstone%202-DTU-2EA44F?logo=graduationcap&logoColor=white"/>
</p>

---

## 📌 Project Overview | Tổng quan dự án

**EN**: **Goodwill Vietnam** is a web-based charity platform designed to **connect donors, recipients, and administrators** in one centralized system. The project supports the full lifecycle of donations: **submission → approval → warehouse intake → distribution**, helping improve transparency, operational efficiency, and tracking.

**VI**: **Goodwill Vietnam** là website thiện nguyện nhằm **kết nối người quyên góp, người nhận và quản trị viên** trên một hệ thống tập trung. Hệ thống hỗ trợ toàn bộ vòng đời quyên góp: **gửi → duyệt → nhập kho → phân phối**, tăng tính minh bạch, tối ưu vận hành và dễ theo dõi tiến độ.

---

## 🎓 Academic Information | Thông tin đồ án

- **EN**: Capstone 2 — **Duy Tan University (DTU)**
- **VI**: Đồ án **Capstone 2 — Đại học Duy Tân**

### 👥 Team | Nhóm thực hiện: **C2SE.67**
**Members | Thành viên**
- **Lê Văn Vũ Phong**
- **Võ Đình Dương**
- **Huỳnh Như Lộc**
- **DNguyễn Thành Đạt**
- **Hằng Gia Bảo**

---

## ✨ Key Features | Tính năng nổi bật

### ❤️ Donation Management | Quản lý quyên góp
- **EN**: Create donation items, upload images, and import from Excel/CSV.
- **VI**: Tạo nhiều vật phẩm, upload ảnh, nhập nhanh bằng Excel/CSV.

### 📦 Donation Tracking | Theo dõi tiến trình quyên góp
- **EN**: Track progress by status: **Submitted → Approved → Stored → Distributed**.
- **VI**: Theo dõi theo trạng thái: **Gửi → Duyệt → Nhập kho → Phân phối**.

### 🏪 Inventory & Warehouse | Kho vật phẩm
- **EN**: Categorize items, set item type/price (free/low-cost/normal), assign to campaigns.
- **VI**: Phân loại, định giá (miễn phí/giá rẻ/giá thường), gắn với chiến dịch.

### 🎯 Campaigns & Volunteers | Chiến dịch & Tình nguyện viên
- **EN**: Online campaign registration and campaign progress updates.
- **VI**: Đăng ký tham gia trực tuyến, cập nhật và theo dõi tiến độ chiến dịch.

### 📊 Admin Dashboard | Bảng điều khiển quản trị
- **EN**: Visual insights with charts; manage users, donations, inventory, and campaigns.
- **VI**: Thống kê trực quan bằng biểu đồ; quản lý người dùng, quyên góp, kho, chiến dịch.

### 🛒 Charity Shop | Shop thiện nguyện
- **EN**: Product browsing, cart, **COD checkout**, and order tracking.
- **VI**: Duyệt sản phẩm, giỏ hàng, **thanh toán COD**, theo dõi đơn hàng.

---

## 🛠️ Technologies | Công nghệ sử dụng

| Layer / Tầng | EN | VI |
|---|---|---|
| Frontend | HTML5, CSS3, Bootstrap 5, JavaScript | Giao diện web responsive, dễ dùng |
| Backend | PHP 8.x (PDO, Session) | Xử lý nghiệp vụ, phân quyền, API/AJAX |
| Database | MySQL 8.x (**managed via phpMyAdmin**) | MySQL 8.x (**quản lý bằng phpMyAdmin**) |
| Charts | Chart.js | Biểu đồ thống kê |
| Architecture | Simple MVC + Admin/API modules | MVC đơn giản + module Admin/API |

---

## ⚙️ System Requirements | Yêu cầu hệ thống

**EN**
- Apache/Nginx (XAMPP/WAMP/LAMP/Laragon supported)
- PHP **>= 8.0** (enable: `pdo_mysql`, `mbstring`, `zip`)
- MySQL **>= 8.0**
- Modern browser: Chrome / Edge / Firefox

**VI**
- Apache/Nginx (hỗ trợ XAMPP/WAMP/LAMP/Laragon)
- PHP **>= 8.0** (bật: `pdo_mysql`, `mbstring`, `zip`)
- MySQL **>= 8.0**
- Trình duyệt hiện đại: Chrome / Edge / Firefox

---

## 🚀 Quick Start | Cài đặt nhanh

### 1) Clone Source Code | Tải mã nguồn
```bash
cd C:\laragon\www
git clone <repo-url> goodwill-vietnam
```

### 2) Create Database via phpMyAdmin | Tạo CSDL bằng phpMyAdmin
- Open | Mở: `http://localhost/phpmyadmin`
- Create DB | Tạo DB: `goodwill_vietnam`
- Charset | Bộ mã: `utf8mb4_unicode_ci`
- Import | Import: `database/schema.sql`

### 3) Configure DB | Cấu hình DB
File: `config/database.php`
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'goodwill_vietnam');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4) Permissions | Phân quyền
- Ensure folder is writable | Cấp quyền ghi: `uploads/`

### 5) Run | Chạy hệ thống
```
http://localhost/goodwill-vietnam
```

---

## 📂 Project Structure | Cấu trúc thư mục

```
goodwill-vietnam/
│
├── admin/              # Admin: dashboard, donations, inventory...
├── api/                # Lightweight endpoints for AJAX
├── assets/             # CSS, JS, images, templates
├── config/             # database.php
├── database/           # schema, seed
├── includes/           # common header/footer/functions
├── uploads/            # donation/campaign images
│
├── donate.php
├── donation-tracking.php
├── order-tracking.php
└── index.php
```

---

## 🔐 Demo Account | Tài khoản mẫu

| Role / Vai trò | Email | Password / Mật khẩu |
|---|---|---|
| Admin | admin@goodwillvietnam.com | password |

> **EN**: Please change the admin password after first run.  
> **VI**: Nên đổi mật khẩu admin sau khi chạy lần đầu.

---

## 🔒 Security Notes | Ghi chú bảo mật

**EN**
- Password hashing with `password_hash`
- PDO Prepared Statements to prevent SQL Injection
- Session-based authentication & authorization
- File upload validation (type/size)

**VI**
- Mã hóa mật khẩu bằng `password_hash`
- PDO Prepared Statements chống SQL Injection
- Xác thực & phân quyền bằng Session
- Kiểm tra upload (định dạng/kích thước)

---

## 🔄 Operational Flow | Luồng vận hành

**EN**
1. User submits a donation  
2. Admin reviews and approves  
3. Items are received into warehouse  
4. Items are distributed to campaigns/recipients  
5. Donor can track progress online

**VI**
1. Người dùng gửi quyên góp  
2. Admin duyệt và xác nhận  
3. Nhập kho vật phẩm  
4. Phân phối theo chiến dịch/đối tượng nhận  
5. Người quyên góp theo dõi tiến trình trực tuyến

---

## 📈 Future Improvements | Hướng phát triển

- Online payments (VNPay/MoMo) | Thanh toán online (VNPay/MoMo)
- Real-time notifications | Thông báo realtime
- Export reports (PDF/Excel) | Xuất báo cáo (PDF/Excel)
- Public RESTful API | API RESTful công khai
- Mobile app | Ứng dụng mobile

---

## 📜 License | Giấy phép

**EN**: This project is developed for **educational purposes (Capstone 2 — DTU)** and is not intended for commercial use.  
**VI**: Dự án phục vụ **mục đích học tập (Capstone 2 — DTU)**, không dùng cho mục đích thương mại.

---

<p align="center">
  💖 <b>Goodwill Vietnam — Share kindness, build impact.</b><br/>
  💖 <b>Goodwill Vietnam — Kết nối sẻ chia, lan tỏa yêu thương.</b>
</p>
