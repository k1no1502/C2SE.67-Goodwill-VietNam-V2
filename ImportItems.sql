-- ImportItems.sql
-- Purpose: create a ready-to-import household items dataset with online image links
-- Compatible: MySQL 8+

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS goodwill_vietnam
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE goodwill_vietnam;

-- 1) Create import table
CREATE TABLE IF NOT EXISTS import_items (
    import_item_id INT PRIMARY KEY AUTO_INCREMENT,
    product_name VARCHAR(200) NOT NULL,
    brand VARCHAR(100) DEFAULT NULL,
    category_name VARCHAR(100) DEFAULT 'Do gia dung',
    condition_status ENUM('new', 'like_new', 'good', 'fair', 'poor') DEFAULT 'new',
    quantity INT NOT NULL DEFAULT 1,
    unit VARCHAR(20) NOT NULL DEFAULT 'cai',
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

-- 2) Clear old data (optional)
TRUNCATE TABLE import_items;

-- 3) Seed sample household items with image links from the internet
INSERT INTO import_items
(product_name, brand, category_name, condition_status, quantity, unit, estimated_value, image_url, source_page_url, description)
VALUES
('May giat Panasonic Inverter 9kg', 'Panasonic', 'Dien gia dung', 'like_new', 2, 'cai', 8500000,
 'https://source.unsplash.com/1600x900/?panasonic,washing-machine',
 'https://www.panasonic.com/vn/',
 'May giat cua truoc, tiet kiem dien, phu hop gia dinh 3-5 nguoi.'),

('Tu lanh Samsung 2 cua', 'Samsung', 'Dien gia dung', 'good', 1, 'cai', 7200000,
 'https://source.unsplash.com/1600x900/?samsung,refrigerator',
 'https://www.samsung.com/vn/refrigerators/',
 'Dung tich tam trung, van hanh on dinh.'),

('Noi com dien Sharp 1.8L', 'Sharp', 'Nha bep', 'new', 3, 'cai', 950000,
 'https://source.unsplash.com/1600x900/?rice-cooker,kitchen',
 'https://global.sharp/',
 'Noi com dien da nang, de su dung.'),

('Lo vi song Electrolux', 'Electrolux', 'Nha bep', 'good', 2, 'cai', 2100000,
 'https://source.unsplash.com/1600x900/?microwave,oven',
 'https://www.electrolux.vn/',
 'Lo vi song lam nong nhanh, dung tot.'),

('May hut bui Philips', 'Philips', 'Do ve sinh', 'like_new', 2, 'cai', 2400000,
 'https://source.unsplash.com/1600x900/?vacuum-cleaner,home',
 'https://www.philips.com.vn/',
 'Cong suat manh, dung cho nha pho va can ho.'),

('May loc khong khi Xiaomi', 'Xiaomi', 'Dien gia dung', 'new', 2, 'cai', 3200000,
 'https://source.unsplash.com/1600x900/?air-purifier,home',
 'https://www.mi.com/vn/',
 'Loc bui min, phu hop phong ngu va phong khach.'),

('May lanh Daikin 1HP', 'Daikin', 'Dien lanh', 'good', 1, 'cai', 9800000,
 'https://source.unsplash.com/1600x900/?air-conditioner,room',
 'https://www.daikin.com.vn/',
 'May lanh 1 chieu, lam mat nhanh.'),

('Bep tu doi Sunhouse', 'Sunhouse', 'Nha bep', 'new', 2, 'bo', 4600000,
 'https://source.unsplash.com/1600x900/?induction-cooktop,kitchen',
 'https://sunhouse.com.vn/',
 'Bep tu doi mat kinh, an toan khi su dung.'),

('May xay sinh to Bluestone', 'Bluestone', 'Nha bep', 'like_new', 3, 'cai', 850000,
 'https://source.unsplash.com/1600x900/?blender,kitchen-appliance',
 'https://www.bluestone.com.vn/',
 'May xay gon nhe, de ve sinh.'),

('Am sieu toc Elmich', 'Elmich', 'Nha bep', 'good', 4, 'cai', 420000,
 'https://source.unsplash.com/1600x900/?electric-kettle,kitchen',
 'https://elmich.vn/',
 'Am dun nuoc nhanh, ruot inox.'),

('Ban ui hoi nuoc Tefal', 'Tefal', 'Do gia dung', 'like_new', 2, 'cai', 1100000,
 'https://source.unsplash.com/1600x900/?steam-iron,home',
 'https://www.tefal.com/',
 'Ban ui hoi nuoc, mat de chong dinh.'),

('May say toc Panasonic', 'Panasonic', 'Do ca nhan', 'new', 5, 'cai', 650000,
 'https://source.unsplash.com/1600x900/?hair-dryer,panasonic',
 'https://www.panasonic.com/vn/',
 'May say toc cong suat vua phai, de dung.'),

('Quat dung Asia', 'Asia', 'Dien gia dung', 'good', 6, 'cai', 690000,
 'https://source.unsplash.com/1600x900/?standing-fan,home',
 'https://quatdienasia.com.vn/',
 'Quat dung 3 toc do, chan de chac chan.'),

('Den ban hoc Rạng Dong LED', 'Rang Dong', 'Den chieu sang', 'new', 8, 'cai', 390000,
 'https://source.unsplash.com/1600x900/?desk-lamp,led',
 'https://rangdong.com.vn/',
 'Den ban hoc anh sang diu, tiet kiem dien.'),

('Noi chien khong dau LocknLock', 'LocknLock', 'Nha bep', 'like_new', 2, 'cai', 1850000,
 'https://source.unsplash.com/1600x900/?air-fryer,kitchen',
 'https://www.locknlock.com/vn/',
 'Noi chien dung tich vua, phu hop gia dinh nho.'),

('May rua chen Bosch', 'Bosch', 'Nha bep', 'good', 1, 'cai', 13500000,
 'https://source.unsplash.com/1600x900/?dishwasher,kitchen',
 'https://www.bosch-home.com.vn/',
 'May rua chen doc lap, tiet kiem nuoc.'),

('May pha ca phe Delonghi', 'Delonghi', 'Nha bep', 'like_new', 1, 'cai', 4200000,
 'https://source.unsplash.com/1600x900/?coffee-machine,kitchen',
 'https://www.delonghi.com/',
 'May pha ca phe mini cho gia dinh.'),

('May nuoc nong Ariston', 'Ariston', 'Phong tam', 'good', 2, 'cai', 2600000,
 'https://source.unsplash.com/1600x900/?water-heater,bathroom',
 'https://www.ariston.com/vi-vn/',
 'May nuoc nong gian tiep, an toan.'),

('Robot hut bui Ecovacs', 'Ecovacs', 'Do ve sinh', 'new', 2, 'cai', 6900000,
 'https://source.unsplash.com/1600x900/?robot-vacuum,home',
 'https://www.ecovacs.com/',
 'Robot hut bui thong minh, tu dong quay ve dock.'),

('Tu say quan ao mini', 'Generic', 'Do gia dung', 'fair', 1, 'cai', 1450000,
 'https://source.unsplash.com/1600x900/?clothes-dryer,home',
 'https://www.google.com/search?q=tu+say+quan+ao+mini',
 'Phu hop can ho nho, tinh trang su dung on dinh.'),

('May loc nuoc Karofi', 'Karofi', 'Nha bep', 'good', 2, 'cai', 3600000,
 'https://source.unsplash.com/1600x900/?water-purifier,kitchen',
 'https://karofi.com/',
 'May loc nuoc RO cho gia dinh.'),

('May pha sua hat Unie', 'Unie', 'Nha bep', 'like_new', 2, 'cai', 1850000,
 'https://source.unsplash.com/1600x900/?soy-milk-maker,appliance',
 'https://unie.com.vn/',
 'May xay nau da nang, co che do tu dong.'),

('May ep cham Kuvings', 'Kuvings', 'Nha bep', 'good', 1, 'cai', 3500000,
 'https://source.unsplash.com/1600x900/?slow-juicer,kitchen',
 'https://www.kuvings.com/',
 'May ep trai cay toc do cham, giu duoc nhieu duong chat.'),

('Can nong lanh Toshiba', 'Toshiba', 'Dien gia dung', 'fair', 2, 'cai', 1650000,
 'https://source.unsplash.com/1600x900/?water-dispenser,office',
 'https://www.toshiba-lifestyle.com/vn',
 'Can nong lanh cho gia dinh va van phong nho.');

-- 4) Quick check
-- SELECT import_item_id, product_name, brand, category_name, image_url FROM import_items ORDER BY import_item_id;
