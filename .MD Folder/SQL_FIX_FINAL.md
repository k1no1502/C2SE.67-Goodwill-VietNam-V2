# 🔧 SỬA LỖI SQL - HƯỚNG DẪN CUỐI CÙNG

## ❌ LỖI: Duplicate column name 'approved_by'

**NGUYÊN NHÂN:** Bảng `campaigns` đã có cột `approved_by` rồi (từ schema.sql)

---

## ✅ GIẢI PHÁP - IMPORT FILE NÀY:

### **`database/campaigns_only.sql`** ← DÙNG FILE NÀY

File này:
- ✅ Không có ALTER TABLE
- ✅ Chỉ tạo 3 tables MỚI
- ✅ Tự động DROP nếu đã có
- ✅ KHÔNG bị lỗi duplicate

---

## 🚀 CÁCH IMPORT (ĐÚNG 100%)

### **Bước 1: Mở phpMyAdmin**
```
http://localhost/phpmyadmin
```

### **Bước 2: Chọn database**
```
Click vào: goodwill_vietnam (bên trái)
```

### **Bước 3: Import file**
```
1. Click tab "Import" (hoặc "Nhập")
2. Click "Choose File"
3. Chọn: database/campaigns_only.sql
4. Click "Go" (hoặc "Thực hiện")
5. Đợi...
```

### **Bước 4: Kiểm tra kết quả**
```
Thông báo: "SUCCESS! Campaigns tables created!"
```

**Nếu thấy thông báo này → ✅ THÀNH CÔNG!**

---

## 📋 CHECKLIST SAU KHI IMPORT

### **Kiểm tra tables:**
```sql
SHOW TABLES LIKE 'campaign%';
```

**Phải có 4 tables:**
- ✅ campaigns (đã có từ trước)
- ✅ campaign_items (mới tạo)
- ✅ campaign_donations (mới tạo)
- ✅ campaign_volunteers (mới tạo)

### **Kiểm tra cấu trúc:**
```sql
DESCRIBE campaign_items;
DESCRIBE campaign_donations;
DESCRIBE campaign_volunteers;
```

### **Test website:**
```
http://localhost/Cap%201%20-%202/campaigns.php
```

---

## 📂 CÁC FILE SQL - TÓM TẮT

| File | Khi nào import? | Trạng thái |
|------|----------------|-----------|
| `schema.sql` | Import đầu tiên | ✅ BẮT BUỘC |
| `update_schema.sql` | Import thứ 2 | ✅ BẮT BUỘC |
| `campaigns_only.sql` | Import thứ 3 | ✅ DÙNG FILE NÀY |
| `check_and_fix.sql` | Khi cần sync | ⚠️ Tùy chọn |
| ~~`campaigns_simple.sql`~~ | ❌ | Có lỗi duplicate |
| ~~`campaigns_update.sql`~~ | ❌ | Có lỗi USE |

---

## 🎯 THỨ TỰ IMPORT ĐÚNG

```
1️⃣ schema.sql
   → Tạo: users, donations, categories, campaigns...
   → Status: ✅

2️⃣ update_schema.sql
   → Thêm: cart, orders, inventory.price_type...
   → Status: ✅

3️⃣ campaigns_only.sql
   → Thêm: campaign_items, campaign_donations, campaign_volunteers
   → Status: ✅ DÙNG FILE NÀY

4️⃣ check_and_fix.sql (optional)
   → Fix: Sync quyên góp vào shop
   → Status: ⚠️ Chỉ khi cần
```

---

## 🧪 TEST SAU KHI FIX

### **Test 1: Kiểm tra database**
```
http://localhost/Cap%201%20-%202/test-database.php
```

Phải thấy:
- ✅ campaign_items: 0 bản ghi
- ✅ campaign_donations: 0 bản ghi
- ✅ campaign_volunteers: 0 bản ghi

### **Test 2: Test tạo chiến dịch**
```
1. Login: http://localhost/Cap%201%20-%202/login.php
2. Tạo chiến dịch: http://localhost/Cap%201%20-%202/create-campaign.php
3. Nhập thông tin
4. Click "Gửi chiến dịch"
5. Kiểm tra: my-campaigns.php
```

### **Test 3: Test website**
```
http://localhost/Cap%201%20-%202/campaigns.php
```

---

## 🎉 HOÀN TẤT

Sau khi import `campaigns_only.sql`:
- ✅ Không còn lỗi duplicate
- ✅ 3 tables mới được tạo
- ✅ Chức năng chiến dịch hoạt động
- ✅ Có thể tạo chiến dịch
- ✅ Có thể đăng ký tình nguyện viên
- ✅ Có thể quyên góp vào chiến dịch

---

## 📞 NẾU VẪN GẶP LỖI

### **Reset toàn bộ database:**

```sql
-- 1. Drop database
DROP DATABASE IF EXISTS goodwill_vietnam;

-- 2. Tạo lại
CREATE DATABASE goodwill_vietnam 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- 3. Import lại từ đầu:
--    ① schema.sql
--    ② update_schema.sql
--    ③ campaigns_only.sql
```

---

**Chúc bạn import thành công! 🚀**
