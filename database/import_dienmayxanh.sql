-- import_dienmayxanh.sql
-- Nguon: Du lieu thu thap tu dienmayxanh.com (snapshot)
-- Muc tieu: bang import don gian gom ten, chi tiet, gia, link anh, link san pham

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS goodwill_vietnam
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE goodwill_vietnam;

CREATE TABLE IF NOT EXISTS import_dienmayxanh_products (
    import_id INT PRIMARY KEY AUTO_INCREMENT,
    product_name VARCHAR(255) NOT NULL,
    product_details TEXT NOT NULL,
    product_price DECIMAL(15,0) NOT NULL DEFAULT 0,
    image_url TEXT NOT NULL,
    product_url TEXT NOT NULL,
    source_website VARCHAR(100) NOT NULL DEFAULT 'dienmayxanh.com',
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dmx_name (product_name(120)),
    INDEX idx_dmx_price (product_price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

TRUNCATE TABLE import_dienmayxanh_products;

INSERT INTO import_dienmayxanh_products
(product_name, product_details, product_price, image_url, product_url)
VALUES
('May lanh Midea Inverter 1 HP MAFA-09CDN8',
 '1 chieu, cong suat 1 HP - 9.000 BTU, phu hop phong duoi 15m2, inverter tiet kiem dien.',
 5190000,
 'https://cdn.tgdd.vn/Products/Images/2002/320893/midea-inverter-1-hp-mafa-09cdn8-1-700x467.jpg',
 'https://www.dienmayxanh.com/may-lanh/midea-inverter-1-hp-mafa-09cdn8'),

('Tu dong Sanaky 150 lit TD.VH180VD',
 'Dung tich su dung 150 lit, 1 ngan dong, 1 cua, cong nghe lam lanh truc tiep.',
 6490000,
 'https://cdn.tgdd.vn/Products/Images/166/283530/tu-dong-sanaky-150lit-td.vh180vd-1-1-700x467.jpg',
 'https://www.dienmayxanh.com/tu-dong/tu-dong-sanaky-150-lit-tdvh180vd'),

('May giat Toshiba Inverter 9.5 kg TW-T21BU105UWV(MG)',
 'May giat cua truoc, khoi luong giat 9.5 kg, toc do vat toi da 1200 vong/phut, san xuat Thai Lan.',
 7190000,
 'https://cdn.tgdd.vn/Products/Images/1944/316764/may-giat-toshiba-tw-t21bu105uwv-mg-1-2-700x467.jpg',
 'https://www.dienmayxanh.com/may-giat/may-giat-toshiba-tw-t21bu105uwv-mg'),

('Smart Tivi QLED Samsung 4K 55 inch QA55Q65D',
 'Man hinh 55 inch 4K, cong nghe Quantum Dot, he dieu hanh Tizen, am thanh OTS Lite.',
 10990000,
 'https://cdnv2.tgdd.vn/mwg-static/dmx/Products/Images/1942/322674/tivi-qled-samsung-4k-55-inch-qa55q65d-1-638694616258062219-700x467.jpg',
 'https://www.dienmayxanh.com/tivi/tivi-qled-samsung-4k-55-inch-qa55q65d'),

('May loc nuoc RO Sunhouse SHA8866K 7 loi',
 'May loc nuoc RO 7 loi, dung tich binh 7 lit, cong suat loc 10-15 lit/gio, loai tu dung.',
 3290000,
 'https://cdn.tgdd.vn/Products/Images/3385/255987/ro-sunhouse-sha8866k-7-loi-1-700x467.jpg',
 'https://www.dienmayxanh.com/may-loc-nuoc/ro-sunhouse-sha8866k-7-loi'),

('Tu lanh LG Inverter 374 lit LTD37BLM',
 'Dung tich su dung 374 lit, ngan lay nuoc ngoai, cong nghe LINEARCooling va DoorCooling+.',
 11690000,
 'https://cdnv2.tgdd.vn/mwg-static/dmx/Products/Images/1943/327795/tu-lanh-lg-inverter-374-lit-ltd37blm-1-639020944185619960-700x467.jpg',
 'https://www.dienmayxanh.com/tu-lanh/tu-lanh-lg-inverter-374-lit-ltd37blm'),

('May say thong hoi Electrolux UltimateCare 8 kg EDV804H3WC',
 'May say thong hoi 8 kg, co Reverse Tumbling, chuong trinh Hygiene, phu hop gia dinh 3-5 nguoi.',
 8990000,
 'https://cdnv2.tgdd.vn/mwg-static/dmx/Products/Images/2202/329627/may-say-thong-hoi-electrolux-ultimatecare-8-kg-edv804h3wc-1-638732431791032948-700x467.jpg',
 'https://www.dienmayxanh.com/may-say-quan-ao/may-say-thong-hoi-electrolux-ultimatecare-8-kg-edv804h3wc'),

('May giat Samsung Inverter 9.5 kg WA95CG4545BDSV',
 'May giat cua tren 9.5 kg, dong co truyen dong truc tiep, Eco Bubble, Super Speed, VRT Plus.',
 5290000,
 'https://cdnv2.tgdd.vn/mwg-static/dmx/Products/Images/1944/302754/may-giat-samsung-9-5kg-wa95cg4545bdsv-1-639111813268659032-700x467.jpg',
 'https://www.dienmayxanh.com/may-giat/may-giat-samsung-9-5kg-wa95cg4545bdsv'),

('Smart Tivi NanoCell LG AI 4K 55 inch 55NANO80ASA',
 'Smart Tivi NanoCell 55 inch 4K, bo xu ly a7 AI Processor 4K Gen8, he dieu hanh webOS 25.',
 12990000,
 'https://cdnv2.tgdd.vn/mwg-static/dmx/Products/Images/1942/337726/smart-tivi-nanocell-lg-ai-4k-55-inch-55nano80asa-1-638822265956085716-700x467.jpg',
 'https://www.dienmayxanh.com/tivi/smart-tivi-nanocell-lg-ai-4k-55-inch-55nano80asa'),

('Quat dieu hoa Rapido 6000D 80W',
 'Cong suat 80W, lam mat phong 20-25m2, binh nuoc 45 lit, co remote va hen gio.',
 2390000,
 'https://cdn.tgdd.vn/Products/Images/7498/235380/rapido-6000d-1-1-700x467.jpg',
 'https://www.dienmayxanh.com/quat-dieu-hoa/rapido-6000d');

-- Kiem tra nhanh
-- SELECT import_id, product_name, product_price, image_url FROM import_dienmayxanh_products ORDER BY import_id;
