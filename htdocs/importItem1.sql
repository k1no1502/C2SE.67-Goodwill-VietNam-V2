-- importItem1.sql
-- Way 1: Create a standalone import table for household items with image links.
-- Safe for testing because it does not modify existing inventory tables.

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS goodwill_vietnam
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE goodwill_vietnam;

CREATE TABLE IF NOT EXISTS import_items (
    import_item_id INT PRIMARY KEY AUTO_INCREMENT,
    product_name VARCHAR(200) NOT NULL,
    brand VARCHAR(100) DEFAULT NULL,
    category_name VARCHAR(100) DEFAULT 'Đồ gia dụng',
    condition_status ENUM('new', 'like_new', 'good', 'fair', 'poor') DEFAULT 'new',
    quantity INT NOT NULL DEFAULT 1,
    unit VARCHAR(20) NOT NULL DEFAULT 'cái',
    estimated_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    image_url TEXT NOT NULL,
    source_page_url TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_import_items_brand (brand),
    INDEX idx_import_items_category (category_name),
    INDEX idx_import_items_condition (condition_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE TABLE import_items;

INSERT INTO import_items
(product_name, brand, category_name, condition_status, quantity, unit, estimated_value, image_url, source_page_url, description)
VALUES
('Máy giặt Panasonic Inverter 9kg', 'Panasonic', 'Đồ gia dụng', 'like_new', 2, 'cái', 8500000,
 'picture_Database/may-giat-panasonic.jpg',
 'https://www.panasonic.com/',
 'Máy giặt cửa trước inverter, tiết kiệm điện.'),

('Tủ lạnh Samsung 2 cửa', 'Samsung', 'Đồ gia dụng', 'good', 1, 'cái', 7200000,
 'picture_Database/tu-lanh-samsung.jpg',
 'https://www.samsung.com/',
 'Tủ lạnh dung tích trung bình cho gia đình.'),

('Nồi cơm điện Sharp 1.8L', 'Sharp', 'Nhà bếp', 'new', 3, 'cái', 950000,
 'picture_Database/noi-com-sharp.jpg',
 'https://global.sharp/',
 'Nồi cơm điện dùng hằng ngày, dễ vệ sinh.'),

('Lò vi sóng Electrolux', 'Electrolux', 'Nhà bếp', 'good', 2, 'cái', 2100000,
 'picture_Database/lo-vi-song-electrolux.jpg',
 'https://www.electrolux.com/',
 'Lò vi sóng hâm nóng nhanh, có chế độ nướng.'),

('Máy hút bụi Philips', 'Philips', 'Vệ sinh', 'like_new', 2, 'cái', 2400000,
 'picture_Database/may-hut-bui-philips.jpg',
 'https://www.philips.com/',
 'Máy hút bụi công suất mạnh, dùng gia đình.'),

('Máy lọc không khí Xiaomi', 'Xiaomi', 'Đồ gia dụng', 'new', 2, 'cái', 3200000,
 'picture_Database/may-loc-khong-khi-xiaomi.jpg',
 'https://www.mi.com/',
 'Máy lọc không khí phù hợp phòng ngủ và phòng khách.'),

('Máy lạnh Daikin 1HP', 'Daikin', 'Điện lạnh', 'good', 1, 'cái', 9800000,
 'picture_Database/may-lanh-daikin.jpg',
 'https://www.daikin.com/',
 'Máy lạnh treo tường 1HP, làm mát nhanh.'),

('Bếp từ đôi Sunhouse', 'Sunhouse', 'Nhà bếp', 'new', 2, 'bộ', 4600000,
 'picture_Database/bep-tu-sunhouse.jpg',
 'https://sunhouse.com.vn/',
 'Bếp từ đôi mặt kính, có hẹn giờ.'),

('Nồi chiên không dầu LocknLock', 'LocknLock', 'Nhà bếp', 'like_new', 2, 'cái', 1850000,
 'picture_Database/noi-chien-locknlock.jpg',
 'https://www.locknlock.com/',
 'Nồi chiên không dầu dung tích vừa cho gia đình.'),

('Robot hút bụi Ecovacs', 'Ecovacs', 'Vệ sinh', 'new', 2, 'cái', 6900000,
 'picture_Database/robot-hut-bui-ecovacs.jpg',
 'https://www.ecovacs.com/',
 'Robot hút bụi thông minh, tự quay về dock sạc.');

-- Check data:
-- SELECT * FROM import_items ORDER BY import_item_id;

-- Way 1B: Sync import_items into system tables so products appear in the app
START TRANSACTION;

-- Ensure a donor role exists
INSERT INTO roles (role_name, description)
SELECT 'donor', 'Auto-created role for data import'
WHERE NOT EXISTS (
  SELECT 1
  FROM roles
  WHERE role_name COLLATE utf8mb4_unicode_ci = 'donor' COLLATE utf8mb4_unicode_ci
);

SET @role_donor := (
  SELECT role_id
  FROM roles
  WHERE role_name COLLATE utf8mb4_unicode_ci = 'donor' COLLATE utf8mb4_unicode_ci
  LIMIT 1
);

-- Pick an existing user; create one if missing
SET @import_user_id := (
  SELECT user_id FROM users ORDER BY user_id LIMIT 1
);

SET @import_email := CONCAT('import.bot.', UNIX_TIMESTAMP(), '@goodwill.local');

INSERT INTO users (name, email, password, role_id, status, email_verified)
SELECT 'Import Bot', @import_email, '$2y$10$abcdefghijklmnopqrstuvABCDEFGHIJKLMNOpqrstuvwxyz12345', @role_donor, 'active', 1
WHERE @import_user_id IS NULL;

SET @import_user_id := (
  SELECT user_id FROM users ORDER BY user_id LIMIT 1
);

-- Ensure categories from import table exist
INSERT INTO categories (name, description, status)
SELECT DISTINCT
  i.category_name,
  'Imported from import_items',
  'active'
FROM import_items i
LEFT JOIN categories c
  ON c.name COLLATE utf8mb4_unicode_ci = i.category_name COLLATE utf8mb4_unicode_ci
WHERE c.category_id IS NULL;

SET @batch_tag := CONVERT(CONCAT('IMPORT1_BATCH_', DATE_FORMAT(NOW(), '%Y%m%d_%H%i%s')) USING utf8mb4);

-- Cleanup previous import batches to avoid duplicate/old random-image rows
DELETE i
FROM inventory i
JOIN donations d ON d.donation_id = i.donation_id
WHERE d.admin_notes LIKE 'IMPORT%';

DELETE FROM donations
WHERE admin_notes LIKE 'IMPORT%';

-- Push to donations
INSERT INTO donations
(user_id, item_name, description, category_id, quantity, unit, condition_status, estimated_value, images, status, admin_notes)
SELECT
  @import_user_id,
  i.product_name,
  i.description,
  c.category_id,
  i.quantity,
  i.unit,
  i.condition_status,
  i.estimated_value,
  JSON_ARRAY(i.image_url),
  'approved',
  CONCAT(@batch_tag, '#', i.import_item_id)
FROM import_items i
LEFT JOIN categories c
  ON c.name COLLATE utf8mb4_unicode_ci = i.category_name COLLATE utf8mb4_unicode_ci
ORDER BY i.import_item_id;

-- Push to inventory
INSERT INTO inventory
(donation_id, name, description, category_id, quantity, unit, condition_status, price_type, sale_price, estimated_value, actual_value, images, location, status, is_for_sale)
SELECT
  d.donation_id,
  i.product_name,
  i.description,
  c.category_id,
  i.quantity,
  i.unit,
  i.condition_status,
  'normal',
  i.estimated_value,
  i.estimated_value,
  i.estimated_value,
  JSON_ARRAY(i.image_url),
  'Main Warehouse',
  'available',
  1
FROM import_items i
LEFT JOIN categories c
  ON c.name COLLATE utf8mb4_unicode_ci = i.category_name COLLATE utf8mb4_unicode_ci
JOIN donations d
  ON d.admin_notes COLLATE utf8mb4_unicode_ci = CONCAT(@batch_tag, '#', i.import_item_id) COLLATE utf8mb4_unicode_ci
ORDER BY i.import_item_id;

COMMIT;

-- Verify:
-- SELECT item_id, name, images, status FROM inventory ORDER BY item_id DESC LIMIT 20;
-- SELECT donation_id, item_name, images, status FROM donations ORDER BY donation_id DESC LIMIT 20;
