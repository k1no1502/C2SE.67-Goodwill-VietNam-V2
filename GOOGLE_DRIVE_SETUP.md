# Hướng dẫn Thiết lập Google Drive Backup cho Hình ảnh Quyên góp

## 📋 Tổng quan

Tính năng này cho phép tự động sao lưu hình ảnh quyên góp lên Google Drive của bạn:
- ✅ Khi upload hình ảnh trên trang donate.php, tự động backup lên Google Drive
- ✅ Hình ảnh được lưu **cục bộ** (uploads/donations/) để hiển thị tức thời
- ✅ Hình ảnh cũng được **backup lên Google Drive** để bảo vệ dữ liệu
- ✅ Khi code thay đổi, dữ liệu vẫn an toàn

---

## 🔧 Bước 1: Cấu hình Google Cloud Console

### 1.1 Tạo Project trong Google Cloud Console

1. Truy cập: [Google Cloud Console](https://console.cloud.google.com/)
2. Đăng nhập với tài khoản Google
3. Nếu chưa có project, hệ thống sẽ gợi ý tạo
4. Tên Project (ví dụ: "GW_Charity_Backup")
5. Chọn **Create**

### 1.2 Enable Google Drive API

1. Vào **APIs & Services** → **Library**
2. Tìm kiếm "Google Drive API"
3. Chọn **Google Drive API**
4. Nút **Enable** (xanh lá)
5. Chờ 2-3 phút để API active

### 1.3 Tạo Service Account

1. **APIs & Services** → **Credentials**
2. Nút **+ Create Credentials** → **Service Account**
3. **Service account name**: `donation-backup` (hoặc tên khác)
4. **Service account ID**: Tự động sinh
5. Chọn **Create and Continue**
6. **Grand basic Editor role** (Role)
7. Chọn **Continue**
8. **Create key** → **JSON** → **Download**

📌 **Lưu file JSON này vào:** `config/google-drive-key.json`

---

## 🔑 Bước 2: Cấu hình Folder trên Google Drive

### 2.1 Tạo Folder để lưu hình ảnh

1. Vào [Google Drive](https://drive.google.com)
2. Tạo folder mới (ví dụ: "Backup_Donations")
3. Chuột phải folder → **Share**
4. Chia sẻ cho email Service Account (trong file JSON)
   - Email sẽ có dạng: `donation-backup@project-id.iam.gserviceaccount.com`

### 2.2 Lấy Folder ID

1. Mở folder vừa tạo
2. URL sẽ có dạng: `https://drive.google.com/drive/folders/FOLDER_ID_HERE`
3. Copy `FOLDER_ID_HERE`

---

## ⚙️ Bước 3: Cấu hình File PHP

### 3.1 Cập nhật config/google.php

Mở file `config/google.php` và điền:

```php
'drive' => [
    'enabled' => true,  // ← Bật tính năng
    'keyfile' => __DIR__ . '/google-drive-key.json',
    'donation_folder_id' => 'FOLDER_ID_VỪA_SAO_CHÉP',
]
```

### 3.2 Cài đặt Google API Client

Chạy composer update:

```bash
cd c:\xampp\htdocs\GW_VN\ Ver\ Final
composer install
# hoặc
composer update
```

Nếu dùng Windows PowerShell:

```powershell
cd 'c:\xampp\htdocs\GW_VN Ver Final'
php composer.phar install
```

---

## ✅ Bước 4: Kiểm tra & Test

### 4.1 Kiểm tra File

Đảm bảo file tồn tại:
```
config/google-drive-key.json  ✓
includes/functions.php (đã cập nhật) ✓
donate.php (đã cập nhật) ✓
config/google.php (đã cập nhật) ✓
```

### 4.2 Test Upload Hình ảnh

1. Vào: `http://localhost/GW_VN%20Ver%20Final/donate.php`
2. Điền form quyên góp sản phẩm
3. Upload hình ảnh
4. Gửi form
5. Kiểm tra:
   - ✅ Hình ảnh hiển thị trong web → lưu ở `uploads/donations/`
   - ✅ File xuất hiện trong Google Drive → backup thành công

### 4.3 Kiểm tra Logs

Nếu có lỗi, kiểm tra:
```
logs/
error_log (nếu có)
```

Tìm dòng: `[Google Drive]` hoặc `[Donation]`

---

## 🐛 Xử lý Lỗi

### Lỗi 1: "Key file not found"

```
[Google Drive] Key file not found: /path/to/google-drive-key.json
```

✅ **Giải pháp:**
- Kiểm tra file JSON có trong `config/` không
- Đảm bảo quyền đọc file (chmod 644 trên Linux)

### Lỗi 2: "authentication failed" hoặc "invalid_grant"

```
[Google Drive] Upload error: Invalid Credentials
```

✅ **Giải pháp:**
- Tại Google Cloud Console, xóa key cũ
- Tạo key JSON mới
- Thay thế file `google-drive-key.json`

### Lỗi 3: "Access denied - The user has exceeded their Drive API quota"

✅ **Giải pháp:**
- Chờ 24 giờ (quota reset)
- Hoặc nâng cấp lên Google Cloud Paid Account

### Lỗi 4: Hình ảnh không backup nhưng web vẫn ok

```
enabled => false
```

✅ **Giải pháp:**
- Mở `config/google.php`
- Đổi `'enabled' => false` thành `'enabled' => true`

---

## 📊 Cách hoạt động

```
Người dùng Upload Hình ảnh
         ↓
   uploadFile() - Lưu cục bộ
         ↓
   uploads/donations/xxxxxx.jpg
         ↓
   uploadFileToGoogleDrive() - Backup
         ↓
   Google Drive (Backup_Donations/donation_xxx.jpg)
```

---

## 🔒 Bảo mật

**Các điểm cần lưu ý:**

1. ✅ File `config/google-drive-key.json` **KHÔNG chia sẻ** công khai
2. ✅ Thêm vào `.gitignore` nếu dùng Git:
   ```
   config/google-drive-key.json
   ```
3. ✅ Service Account chỉ có quyền upload, không xóa
4. ✅ Folder trên Google Drive nên **Private**

---

## 🎯 Lợi ích

| Lợi ích | Chi tiết |
|---------|---------|
| 💾 **Backup tự động** | Không cần manual backup |
| 🔄 **Cập nhật code an toàn** | Dữ liệu không mất |
| 🌐 **Truy cập từ bất kỳ đâu** | Xem backup trên Google Drive |
| 🛡️ **Bảo vệ dữ liệu** | Lưu 2 bản (local + cloud) |
| ⚡ **Hiệu suất** | Upload web tức thời, backup background |

---

## 📞 Hỗ trợ

Nếu gặp vấn đề:

1. Kiểm tra `config/google.php` → `'enabled'` = true?
2. Kiểm tra file `config/google-drive-key.json` có tồn tại?
3. Kiểm tra folder ID đúng?
4. Chạy `composer install` lại
5. Xóa cache browser & thử lại

---

**Tác giả:** GW Charity System  
**Ngày cập nhật:** 2026-03-21  
**Phiên bản:** 1.0
