-- importItem2.sql
-- Way 2: Import directly into existing workflow tables (donations -> inventory)
-- This script creates a temporary staging table, then pushes rows to donations and inventory.

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS goodwill_vietnam
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE goodwill_vietnam;

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

-- Ensure categories exist
INSERT INTO categories (name, description, status)
SELECT 'Đồ gia dụng', 'Danh mục đồ gia dụng nhập tự động', 'active'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name COLLATE utf8mb4_unicode_ci = 'Đồ gia dụng' COLLATE utf8mb4_unicode_ci);

INSERT INTO categories (name, description, status)
SELECT 'Nhà bếp', 'Danh mục đồ nhà bếp nhập tự động', 'active'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name COLLATE utf8mb4_unicode_ci = 'Nhà bếp' COLLATE utf8mb4_unicode_ci);

INSERT INTO categories (name, description, status)
SELECT 'Vệ sinh', 'Danh mục đồ vệ sinh nhập tự động', 'active'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name COLLATE utf8mb4_unicode_ci = 'Vệ sinh' COLLATE utf8mb4_unicode_ci);

INSERT INTO categories (name, description, status)
SELECT 'Điện lạnh', 'Danh mục điện lạnh nhập tự động', 'active'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name COLLATE utf8mb4_unicode_ci = 'Điện lạnh' COLLATE utf8mb4_unicode_ci);

SET @cat_home := (SELECT category_id FROM categories WHERE name COLLATE utf8mb4_unicode_ci = 'Đồ gia dụng' COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @cat_kitchen := (SELECT category_id FROM categories WHERE name COLLATE utf8mb4_unicode_ci = 'Nhà bếp' COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @cat_clean := (SELECT category_id FROM categories WHERE name COLLATE utf8mb4_unicode_ci = 'Vệ sinh' COLLATE utf8mb4_unicode_ci LIMIT 1);
SET @cat_cool := (SELECT category_id FROM categories WHERE name COLLATE utf8mb4_unicode_ci = 'Điện lạnh' COLLATE utf8mb4_unicode_ci LIMIT 1);

-- Temporary staging table
DROP TEMPORARY TABLE IF EXISTS tmp_import_items;
CREATE TEMPORARY TABLE tmp_import_items (
    row_id INT PRIMARY KEY AUTO_INCREMENT,
    product_name VARCHAR(200) NOT NULL,
    category_id INT NOT NULL,
    quantity INT NOT NULL,
    unit VARCHAR(20) NOT NULL,
    condition_status ENUM('new', 'like_new', 'good', 'fair', 'poor') NOT NULL,
    estimated_value DECIMAL(12,2) NOT NULL,
    image_url TEXT NOT NULL,
    description TEXT
);

INSERT INTO tmp_import_items
(product_name, category_id, quantity, unit, condition_status, estimated_value, image_url, description)
VALUES
('Máy giặt Panasonic Inverter 9kg', @cat_home, 2, 'cái', 'like_new', 8500000,
 'picture_Database/may-giat-panasonic.jpg',
 'Máy giặt cửa trước inverter, tiết kiệm điện.'),

('Tủ lạnh Samsung 2 cửa', @cat_home, 1, 'cái', 'good', 7200000,
 'picture_Database/tu-lanh-samsung.jpg',
 'Tủ lạnh dung tích trung bình cho gia đình.'),

('Nồi cơm điện Sharp 1.8L', @cat_kitchen, 3, 'cái', 'new', 950000,
 'picture_Database/noi-com-sharp.jpg',
 'Nồi cơm điện dùng hằng ngày, dễ vệ sinh.'),

('Lò vi sóng Electrolux', @cat_kitchen, 2, 'cái', 'good', 2100000,
 'picture_Database/lo-vi-song-electrolux.jpg',
 'Lò vi sóng hâm nóng nhanh, có chế độ nướng.'),

('Máy hút bụi Philips', @cat_clean, 2, 'cái', 'like_new', 2400000,
 'picture_Database/may-hut-bui-philips.jpg',
 'Máy hút bụi công suất mạnh, dùng gia đình.'),

('Máy lạnh Daikin 1HP', @cat_cool, 1, 'cái', 'good', 9800000,
 'picture_Database/may-lanh-daikin.jpg',
 'Máy lạnh treo tường 1HP, làm mát nhanh.'),

('Nồi chiên không dầu LocknLock', @cat_kitchen, 2, 'cái', 'like_new', 1850000,
 'picture_Database/noi-chien-locknlock.jpg',
 'Nồi chiên không dầu dung tích vừa cho gia đình.'),

('Robot hút bụi Ecovacs', @cat_clean, 2, 'cái', 'new', 6900000,
 'picture_Database/robot-hut-bui-ecovacs.jpg',
 'Robot hút bụi thông minh, tự quay về dock sạc.');

-- Cleanup previous import batches to avoid duplicate/old random-image rows
DELETE i
FROM inventory i
JOIN donations d ON d.donation_id = i.donation_id
WHERE d.admin_notes LIKE 'IMPORT%';

DELETE FROM donations
WHERE admin_notes LIKE 'IMPORT%';

-- Insert to donations first
SET @batch_tag := CONVERT(CONCAT('IMPORT_BATCH_', DATE_FORMAT(NOW(), '%Y%m%d_%H%i%s')) USING utf8mb4);

INSERT INTO donations
(user_id, item_name, description, category_id, quantity, unit, condition_status, estimated_value, images, status, admin_notes)
SELECT
    @import_user_id,
    t.product_name,
    t.description,
    t.category_id,
    t.quantity,
    t.unit,
    t.condition_status,
    t.estimated_value,
    JSON_ARRAY(t.image_url),
    'approved',
    @batch_tag
FROM tmp_import_items t
ORDER BY t.row_id;

-- Map donation rows in order to staging rows
SET @rn := 0;
DROP TEMPORARY TABLE IF EXISTS tmp_donation_map;
CREATE TEMPORARY TABLE tmp_donation_map AS
SELECT d.donation_id, (@rn := @rn + 1) AS rn
FROM donations d
WHERE d.admin_notes COLLATE utf8mb4_unicode_ci = @batch_tag COLLATE utf8mb4_unicode_ci
ORDER BY d.donation_id;

-- Insert into inventory
INSERT INTO inventory
(donation_id, name, description, category_id, quantity, unit, condition_status, price_type, sale_price, estimated_value, actual_value, images, location, status, is_for_sale)
SELECT
    m.donation_id,
    t.product_name,
    t.description,
    t.category_id,
    t.quantity,
    t.unit,
    t.condition_status,
    'normal',
    t.estimated_value,
    t.estimated_value,
    t.estimated_value,
    JSON_ARRAY(t.image_url),
    'Main Warehouse',
    'available',
    1
FROM tmp_import_items t
JOIN tmp_donation_map m ON m.rn = t.row_id
ORDER BY t.row_id;

COMMIT;

-- Verify:
-- SELECT item_id, name, images, status FROM inventory ORDER BY item_id DESC LIMIT 20;
-- SELECT donation_id, item_name, images, status FROM donations ORDER BY donation_id DESC LIMIT 20;
