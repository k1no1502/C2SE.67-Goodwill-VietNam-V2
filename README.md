# ?? Goodwill Vietnam  
## ?? Capstone 2 – Duy Tan University (DTU)

<p align="center">
  <b>? Digital charity platform — Connecting donations, transparency, and impact ?</b><br/>
  <i>? N?n t?ng thi?n nguy?n s? — K?t n?i quyên góp, minh b?ch ti?n d?, lan t?a giá tr? ?</i>
</p>

<p align="center">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white"/>
  <img alt="MySQL" src="https://img.shields.io/badge/MySQL-8.x-4479A1?logo=mysql&logoColor=white"/>
  <img alt="phpMyAdmin" src="https://img.shields.io/badge/phpMyAdmin-Database%20Admin-6C78AF?logo=phpmyadmin&logoColor=white"/>
  <img alt="Scrum" src="https://img.shields.io/badge/Scrum-Agile-0A66C2?logo=scrumalliance&logoColor=white"/>
  <img alt="Capstone" src="https://img.shields.io/badge/Capstone%201-DTU-2EA44F?logo=graduationcap&logoColor=white"/>
</p>

---

## ?? Project Overview | T?ng quan d? án

**EN**: **Goodwill Vietnam** is a web-based charity platform designed to **connect donors, recipients, and administrators** in one centralized system. The project supports the full lifecycle of donations: **submission ? approval ? warehouse intake ? distribution**, helping improve transparency, operational efficiency, and tracking.

**VI**: **Goodwill Vietnam** là website thi?n nguy?n nh?m **k?t n?i ngu?i quyên góp, ngu?i nh?n và qu?n tr? viên** trên m?t h? th?ng t?p trung. H? th?ng h? tr? toàn b? vòng d?i quyên góp: **g?i ? duy?t ? nh?p kho ? phân ph?i**, tang tính minh b?ch, t?i uu v?n hành và d? theo dõi ti?n d?.

---

## ?? Academic Information | Thông tin d? án

- **EN**: Capstone 1 — **Duy Tan University (DTU)**
- **VI**: Ð? án **Capstone 1 — Ð?i h?c Duy Tân**

### ?? Team | Nhóm th?c hi?n: **C1SE.30**
**Members | Thành viên**
- **Lê Van Vu Phong**
- **Võ Ðình Duong**
- **Hu?nh Nhu L?c**
- **Nguy?n Thành Ð?t**
- **H?ng Gia B?o**

---

## ? Key Features | Tính nang n?i b?t

### ?? Donation Management | Qu?n lý quyên góp
- **EN**: Create donation items, upload images, and import from Excel/CSV.
- **VI**: T?o nhi?u v?t ph?m, upload ?nh, nh?p nhanh b?ng Excel/CSV.

### ?? Donation Tracking | Theo dõi ti?n trình quyên góp
- **EN**: Track progress by status: **Submitted ? Approved ? Stored ? Distributed**.
- **VI**: Theo dõi theo tr?ng thái: **G?i ? Duy?t ? Nh?p kho ? Phân ph?i**.

### ?? Inventory & Warehouse | Kho v?t ph?m
- **EN**: Categorize items, set item type/price (free/low-cost/normal), assign to campaigns.
- **VI**: Phân lo?i, d?nh giá (mi?n phí/giá r?/giá thu?ng), g?n v?i chi?n d?ch.

### ?? Campaigns & Volunteers | Chi?n d?ch & Tình nguy?n viên
- **EN**: Online campaign registration and campaign progress updates.
- **VI**: Ðang ký tham gia tr?c tuy?n, c?p nh?t và theo dõi ti?n d? chi?n d?ch.

### ?? Admin Dashboard | B?ng di?u khi?n qu?n tr?
- **EN**: Visual insights with charts; manage users, donations, inventory, and campaigns.
- **VI**: Th?ng kê tr?c quan b?ng bi?u d?; qu?n lý ngu?i dùng, quyên góp, kho, chi?n d?ch.

### ?? Charity Shop | Shop thi?n nguy?n
- **EN**: Product browsing, cart, **COD checkout**, and order tracking.
- **VI**: Duy?t s?n ph?m, gi? hàng, **thanh toán COD**, theo dõi don hàng.

---

## ??? Technologies | Công ngh? s? d?ng

| Layer / T?ng | EN | VI |
|---|---|---|
| Frontend | HTML5, CSS3, Bootstrap 5, JavaScript | Giao di?n web responsive, d? dùng |
| Backend | PHP 8.x (PDO, Session) | X? lý nghi?p v?, phân quy?n, API/AJAX |
| Database | MySQL 8.x (**managed via phpMyAdmin**) | MySQL 8.x (**qu?n lý b?ng phpMyAdmin**) |
| Charts | Chart.js | Bi?u d? th?ng kê |
| Architecture | Simple MVC + Admin/API modules | MVC don gi?n + module Admin/API |

---

## ?? System Requirements | Yêu c?u h? th?ng

**EN**
- Apache/Nginx (XAMPP/WAMP/LAMP/Laragon supported)
- PHP **>= 8.0** (enable: `pdo_mysql`, `mbstring`, `zip`)
- MySQL **>= 8.0**
- Modern browser: Chrome / Edge / Firefox

**VI**
- Apache/Nginx (h? tr? XAMPP/WAMP/LAMP/Laragon)
- PHP **>= 8.0** (b?t: `pdo_mysql`, `mbstring`, `zip`)
- MySQL **>= 8.0**
- Trình duy?t hi?n d?i: Chrome / Edge / Firefox

---

## ?? Quick Start | Cài d?t nhanh

### 1) Clone Source Code | T?i mã ngu?n
```bash
cd C:\laragon\www
git clone <repo-url> goodwill-vietnam
```

### 2) Create Database via phpMyAdmin | T?o CSDL b?ng phpMyAdmin
- Open | M?: `http://localhost/phpmyadmin`
- Create DB | T?o DB: `goodwill_vietnam`
- Charset | B? mã: `utf8mb4_unicode_ci`
- Import | Import: `database/schema.sql`

### 3) Configure DB | C?u hình DB
File: `config/database.php`
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'goodwill_vietnam');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4) Permissions | Phân quy?n
- Ensure folder is writable | C?p quy?n ghi: `uploads/`

### 5) Run | Ch?y h? th?ng
```
http://localhost/goodwill-vietnam
```

---

## ?? Project Structure | C?u trúc thu m?c

```
goodwill-vietnam/
¦
+-- admin/              # Admin: dashboard, donations, inventory...
+-- api/                # Lightweight endpoints for AJAX
+-- assets/             # CSS, JS, images, templates
+-- config/             # database.php
+-- database/           # schema, seed
+-- includes/           # common header/footer/functions
+-- uploads/            # donation/campaign images
¦
+-- donate.php
+-- donation-tracking.php
+-- order-tracking.php
+-- index.php
```

---

## ?? Demo Account | Tài kho?n m?u

| Role / Vai trò | Email | Password / M?t kh?u |
|---|---|---|
| Admin | admin@goodwillvietnam.com | password |

> **EN**: Please change the admin password after first run.  
> **VI**: Nên d?i m?t kh?u admin sau khi ch?y l?n d?u.

---

## ?? Security Notes | Ghi chú b?o m?t

**EN**
- Password hashing with `password_hash`
- PDO Prepared Statements to prevent SQL Injection
- Session-based authentication & authorization
- File upload validation (type/size)

**VI**
- Mã hóa m?t kh?u b?ng `password_hash`
- PDO Prepared Statements ch?ng SQL Injection
- Xác th?c & phân quy?n b?ng Session
- Ki?m tra upload (d?nh d?ng/kích thu?c)

---

## ?? Operational Flow | Lu?ng v?n hành

**EN**
1. User submits a donation  
2. Admin reviews and approves  
3. Items are received into warehouse  
4. Items are distributed to campaigns/recipients  
5. Donor can track progress online

**VI**
1. Ngu?i dùng g?i quyên góp  
2. Admin duy?t và xác nh?n  
3. Nh?p kho v?t ph?m  
4. Phân ph?i theo chi?n d?ch/d?i tu?ng nh?n  
5. Ngu?i quyên góp theo dõi ti?n trình tr?c tuy?n

---

## ?? Future Improvements | Hu?ng phát tri?n

- Online payments (VNPay/MoMo) | Thanh toán online (VNPay/MoMo)
- Real-time notifications | Thông báo realtime
- Export reports (PDF/Excel) | Xu?t báo cáo (PDF/Excel)
- Public RESTful API | API RESTful công khai
- Mobile app | ?ng d?ng mobile

---

## ?? License | Gi?y phép

**EN**: This project is developed for **educational purposes (Capstone 1 — DTU)** and is not intended for commercial use.  
**VI**: D? án ph?c v? **m?c dích h?c t?p (Capstone 1 — DTU)**, không dùng cho m?c dích thuong m?i.

---

<p align="center">
  ?? <b>Goodwill Vietnam — Share kindness, build impact.</b><br/>
  ?? <b>Goodwill Vietnam — K?t n?i s? chia, lan t?a yêu thuong.</b>
</p>
