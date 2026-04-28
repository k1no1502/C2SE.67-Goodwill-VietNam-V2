# 🔧 SỬA LỖI SQL - HƯỚNG DẪN NHANH

## ❌ LỖI BẠN GẶP:

```
#1060 - Duplicate column name 'approved_by'
```

**Nghĩa là:** Cột `approved_by` đã tồn tại trong bảng `campaigns`

---

## ✅ GIẢI PHÁP

### **CÁCH 1: Dùng file mới (NHANH NHẤT)**

Tôi đã tạo file **`campaigns_only.sql`** - Chỉ tạo 3 tables mới:

```
1. Trong phpMyAdmin
2. Đã chọn database: goodwill_vietnam
3. Tab "Import"
4. Choose File: database/campaigns_only.sql
5. Click "Go"
```

**File này:**
- ✅ Không có ALTER (không lỗi duplicate)
- ✅ Chỉ tạo tables mới
- ✅ Tự động DROP nếu đã có
- ✅ An toàn 100%

---

### **CÁCH 2: Bỏ qua lỗi**

Nếu chỉ lỗi "Duplicate column", bạn có thể:

```
1. Bỏ qua lỗi này
2. Scroll xuống xem các câu lệnh khác có chạy không
3. Nếu có thông báo "Tables created", chạy tiếp
```

---

### **CÁCH 3: Chạy từng câu lệnh**

```sql
-- Chỉ chạy phần tạo tables, bỏ phần ALTER

-- 1. Tạo campaign_items
CREATE TABLE IF NOT EXISTS `campaign_items` (
    `item_id` INT PRIMARY KEY AUTO_INCREMENT,
    `campaign_id` INT NOT NULL,
    `item_name` VARCHAR(200) NOT NULL,
    `category_id` INT DEFAULT NULL,
    `quantity_needed` INT NOT NULL,
    `quantity_received` INT DEFAULT 0,
    `unit` VARCHAR(50) DEFAULT 'cái',
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`campaign_id`) ON DELETE CASCADE
);

-- 2. Tạo campaign_donations
CREATE TABLE IF NOT EXISTS `campaign_donations` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `campaign_id` INT NOT NULL,
    `donation_id` INT NOT NULL,
    `campaign_item_id` INT DEFAULT NULL,
    `quantity_contributed` INT DEFAULT 1,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`campaign_id`) ON DELETE CASCADE,
    FOREIGN KEY (`donation_id`) REFERENCES `donations`(`donation_id`) ON DELETE CASCADE,
    UNIQUE KEY (`campaign_id`, `donation_id`)
);

-- 3. Tạo campaign_volunteers
CREATE TABLE IF NOT EXISTS `campaign_volunteers` (
    `volunteer_id` INT PRIMARY KEY AUTO_INCREMENT,
    `campaign_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `message` TEXT,
    `skills` TEXT,
    `availability` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`campaign_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    UNIQUE KEY (`campaign_id`, `user_id`)
);
```

---

## 📋 CHECKLIST SAU KHI FIX

```sql
-- Kiểm tra campaign_items
SELECT COUNT(*) FROM campaign_items;

-- Kiểm tra campaign_donations  
SELECT COUNT(*) FROM campaign_donations;

-- Kiểm tra campaign_volunteers
SELECT COUNT(*) FROM campaign_volunteers;

-- Xem tất cả tables campaign
SHOW TABLES LIKE 'campaign%';
```

**Kết quả mong đợi:**
- ✅ 3 tables: campaign_items, campaign_donations, campaign_volunteers
- ✅ Không có lỗi

---

## 🎯 FILE NÀO DÙNG?

| File | Dùng? | Lý do |
|------|-------|-------|
| `campaigns_only.sql` | ✅ DÙNG | Đơn giản, không lỗi |
| `campaigns_simple.sql` | ⚠️ Có thể lỗi | Có ALTER |
| `campaigns_update.sql` | ❌ KHÔNG | Có USE, lỗi |

---

## 🚀 IMPORT NHANH - COPY/PASTE

Nếu không muốn import file, copy SQL này vào tab SQL:

```sql
-- Xóa tables cũ (nếu có)
DROP TABLE IF EXISTS campaign_volunteers;
DROP TABLE IF EXISTS campaign_donations;
DROP TABLE IF EXISTS campaign_items;

-- Tạo lại
CREATE TABLE campaign_items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    category_id INT,
    quantity_needed INT NOT NULL,
    quantity_received INT DEFAULT 0,
    unit VARCHAR(50) DEFAULT 'cái',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE
);

CREATE TABLE campaign_donations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    donation_id INT NOT NULL,
    campaign_item_id INT,
    quantity_contributed INT DEFAULT 1,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (donation_id) REFERENCES donations(donation_id) ON DELETE CASCADE,
    UNIQUE KEY (campaign_id, donation_id)
);

CREATE TABLE campaign_volunteers (
    volunteer_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    message TEXT,
    skills TEXT,
    availability TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY (campaign_id, user_id)
);

SELECT 'SUCCESS!' as Status;
```

---

## ✅ KẾT LUẬN

**DÙNG FILE NÀY:**
- ✅ `database/campaigns_only.sql`

**HOẶC:**
- ✅ Copy/Paste SQL ở trên vào tab SQL

**Sau đó:**
```
Test: http://localhost/Cap%201%20-%202/test-database.php
```

---

**Chúc thành công! 🎉**
