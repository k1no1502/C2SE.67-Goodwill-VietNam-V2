# ☁️ Google Drive Automatic Backup for Donations

## 📌 Câu trả lời: **Hình ảnh KHÔNG bị mất, đơn giản hơn bạn nghĩ!**

---

## 🎯 Cách hoạt động

Khi bạn upload hình ảnh quyên góp:

```
Upload Hình ảnh
    ↓
📁 Lưu cục bộ: uploads/donations/
    ↓
✅ Hiển thị web tức thời (nhanh)
    ↓
☁️ Tự động backup Google Drive (background)
    ↓
🔄 Khi cập nhật code: Dữ liệu vẫn an toàn
```

---

## ✅ Những gì đã thiết lập sẵn

Tôi đã cập nhật:

1. ✅ **composer.json** - Thêm Google API Client
2. ✅ **config/google.php** - Cấu hình Google Drive
3. ✅ **includes/functions.php** - Hàm `uploadFileToGoogleDrive()`
4. ✅ **donate.php** - Tự động gọi hàm backup

---

## 🚀 Bước tiếp theo (3 bước dễ)

### 1️⃣ Cấu hình Google Cloud (15 phút)

```
Google Cloud Console
    ↓
Project: "GW_Charity_Backup"
    ↓
Enable: Google Drive API
    ↓
Create: Service Account
    ↓
Download: JSON Key → lưu vào config/google-drive-key.json
```

👉 **Chi tiết:** Xem file [GOOGLE_DRIVE_SETUP.md](GOOGLE_DRIVE_SETUP.md)

### 2️⃣ Cấu hình Google Drive Folder (5 phút)

```
Google Drive
    ↓
Tạo: Folder "Backup_Donations"
    ↓
Share: Cho email Service Account
    ↓
Copy: Folder ID
    ↓
Paste: Vào config/google.php
```

### 3️⃣ Bật tính năng (1 phút)

Mở file `config/google.php`:

```php
'drive' => [
    'enabled' => true,  // ← ĐỔI THÀNH TRUE
    'keyfile' => __DIR__ . '/google-drive-key.json',
    'donation_folder_id' => 'PASTE_FOLDER_ID_HERE',  // ← PASTE ID
]
```

Sau đó chạy:

```bash
composer install
```

---

## ✔️ Kiểm tra cấu hình

Truy cập: **http://localhost/GW_VN%20Ver%20Final/check_gdrive_setup.php**

Nó sẽ check:
- ✅ File JSON key
- ✅ Folder ID
- ✅ Google API Client
- ✅ Helper functions
- ✅ Upload directory

---

## 🎓 Q&A

**Q: Hình ảnh sẽ bị mất không nếu tôi cập nhật code?**  
A: **KHÔNG**. Hình ảnh lưu trong `uploads/donations/` - riêng biệt với code.

**Q: Tác dụng là gì?**  
A: Có 2 bản hình ảnh:
- 🖥️ Bản 1: Web cục bộ (hiển thị tức thời)
- ☁️ Bản 2: Google Drive (backup tự động)

**Q: Chậm không?**  
A: **KHÔNG**. Upload cục bộ xong rồi backup background (không chặn user).

**Q: Mất tiền không?**  
A: **TÙỞI BẠN**.
- Service Account: Miễn phí (giới hạn 15GB)
- Nếu vượt 15GB: Trả một lần ~2$/GB

**Q: Nếu không setup Google Drive?**  
A: Hình ảnh vẫn hoạt động bình thường. Chỉ mất tính backup.

---

## 📂 File thay đổi

```
✏️ composer.json                    (thêm google/apiclient)
✏️ config/google.php                (cấu hình Google Drive)
✏️ includes/functions.php           (thêm helper functions)
✏️ donate.php                        (gọi upload Google Drive)

📄 GOOGLE_DRIVE_SETUP.md            (hướng dẫn chi tiết)
📄 check_gdrive_setup.php           (kiểm tra cấu hình)
📄 README_GDRIVE.md                 (file này)
```

---

## 🔥 Lợi ích

| Lợi ích | Chi tiết |
|---------|---------|
| 🛡️ **Bảo vệ dữ liệu** | Backup tự động |
| 🔄 **Update code an toàn** | Không mất dữ liệu |
| ☁️ **Backup cloud** | Truy cập từ bất kỳ đâu |
| ⚡ **Nhanh** | Upload local tức thời |
| 🆓 **Miễn phí (>90%)** | Dùng Google Drive free tier |

---

## 📞 Gặp lỗi?

1. **Làm từng bước hướng dẫn** [GOOGLE_DRIVE_SETUP.md](GOOGLE_DRIVE_SETUP.md)
2. **Chạy kiểm tra**: http://localhost/GW_VN%20Ver%20Final/check_gdrive_setup.php
3. **Xem logs** trong thư mục `logs/` hoặc `error_log`

---

## 🎉 Tóm tắt

✅ **Hình ảnh KHÔNG bị mất** - Lưu cục bộ + backup GDrive  
✅ **Setup dễ dàng** - 3 bước, ~25 phút  
✅ **Tự động** - Không cần manual backup  
✅ **Bảo mật** - Dữ liệu an toàn trên 2 nơi  

---

**Hãy bắt đầu:** Xem [GOOGLE_DRIVE_SETUP.md](GOOGLE_DRIVE_SETUP.md) để thiết lập!
