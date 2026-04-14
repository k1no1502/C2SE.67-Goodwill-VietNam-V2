-- Pokemon.sql
-- Auto-generated from https://www.nshop.com.vn/collections/tcg-tin-box
-- Based on importItem1.sql structure: import_items + sync to donations/inventory

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS goodwill_vietnam
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE goodwill_vietnam;

CREATE TABLE IF NOT EXISTS import_items (
    import_item_id INT PRIMARY KEY AUTO_INCREMENT,
    product_name VARCHAR(255) NOT NULL,
    brand VARCHAR(100) DEFAULT NULL,
    category_name VARCHAR(100) DEFAULT 'Pokemon TCG',
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
('PT32 - Pokémon TCG: Shining Legends Collector Chest (Pokémon TCG)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1960000, 'https://product.hstatic.net/1000231532/product/shining_legends_collector_chest1.jpg', 'https://www.nshop.com.vn/products/pokemon-tcg-shining-legends-collector-chest', 'Pokemon TCG Tin Box - PT32 - Pokémon TCG: Shining Legends Collector Chest (Pokémon TCG)'),
('PT02 - BLASTOISE-EX POWER TRIO TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1420000, 'https://product.hstatic.net/1000231532/product/upload_f64f7568eae343e3902bbdfe86d05cfd_grande_f5a290c77ec341b2b119b97d70ef38ff.jpg', 'https://www.nshop.com.vn/products/pt02-blastoise-ex-power-trio-tin-pokemon', 'Pokemon TCG Tin Box - PT02 - BLASTOISE-EX POWER TRIO TIN (POKÉMON TRADING CARD GAME)'),
('PT08 - BLAZIKEN-EX HOENN POWER TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1640000, 'https://product.hstatic.net/1000231532/product/upload_51428d089f704b68a4ca5b17c3fe394a_grande_291001f340534c398bbf7df81c284cbc.jpg', 'https://www.nshop.com.vn/products/pt08-blaziken-ex-hoenn-power-tin-pokemon', 'Pokemon TCG Tin Box - PT08 - BLAZIKEN-EX HOENN POWER TIN (POKÉMON TRADING CARD GAME)'),
('PT11 - SHINY GYARADOS-EX COLLECTOR TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1440000, 'https://product.hstatic.net/1000231532/product/upload_22491a55443a4c119331055e046fff7a_grande_c783d72d6adc433fb09665e580155e44.jpg', 'https://www.nshop.com.vn/products/pt11-shiny-gyarados-ex-collector-tin-pokemon', 'Pokemon TCG Tin Box - PT11 - SHINY GYARADOS-EX COLLECTOR TIN (POKÉMON TRADING CARD GAME)'),
('PT19 - MAGEARNA BATTLE HEART TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1440000, 'https://product.hstatic.net/1000231532/product/upload_9f64c8bab2ed49f9b4d504cc17d731b3_grande_58e2ed09cd3b44c98217daf78b0721c1.jpg', 'https://www.nshop.com.vn/products/pokemon-pt19-magearna-battle-heart-tin', 'Pokemon TCG Tin Box - PT19 - MAGEARNA BATTLE HEART TIN (POKÉMON TRADING CARD GAME)'),
('PT21 - HOOPA-EX BEYOND THE ORDINARY TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1440000, 'https://product.hstatic.net/1000231532/product/upload_083aa2ae5cf942a5b381c30680dc5f2d_grande_a821e4bf9cac4537822a6e95deb7d8e4.jpg', 'https://www.nshop.com.vn/products/pokemon-pt21-hoopa-ex-beyond-the-ordinary-tin', 'Pokemon TCG Tin Box - PT21 - HOOPA-EX BEYOND THE ORDINARY TIN (POKÉMON TRADING CARD GAME)'),
('PT28 - TAPU BULU-GX ISLAND GUARDIANS TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1380000, 'https://product.hstatic.net/1000231532/product/upload_0026216d9f414fa1b501b8340038f44f_grande_bdbaaf056aef48df9343eece15ff6ac2.jpg', 'https://www.nshop.com.vn/products/pokemon-pt28-tapu-bulu-gx-island-guardians-tin', 'Pokemon TCG Tin Box - PT28 - TAPU BULU-GX ISLAND GUARDIANS TIN (POKÉMON TRADING CARD GAME)'),
('PT03 - VENUSAUR-EX POWER TRIO TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1420000, 'https://product.hstatic.net/1000231532/product/upload_f088e6877d4642ae8bf0bdc51fce4a73_grande_34496cc30e34407ea3aae6700fb42db1.jpg', 'https://www.nshop.com.vn/products/pt03-venusaur-ex-power-trio-tin-pokemon', 'Pokemon TCG Tin Box - PT03 - VENUSAUR-EX POWER TRIO TIN (POKÉMON TRADING CARD GAME)'),
('PT05 - DELPHOX-EX KALOS POWER TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1840000, 'https://product.hstatic.net/1000231532/product/upload_b74224e6d3544147a16703e409b9ec9f_grande_3faf75d4da5a41c98b1422838f108521.jpg', 'https://www.nshop.com.vn/products/pt05-delphox-ex-kalos-power-tin-pokemon', 'Pokemon TCG Tin Box - PT05 - DELPHOX-EX KALOS POWER TIN (POKÉMON TRADING CARD GAME)'),
('PT07 - SCEPTILE-EX HOENN POWER TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1500000, 'https://product.hstatic.net/1000231532/product/upload_8071e27507dd4ff6a47b6ab0488396cc_grande_9e18f283c67049d0af48d620bfbdc9b0.jpg', 'https://www.nshop.com.vn/products/pt07-sceptile-ex-hoenn-power-tin-pokemon', 'Pokemon TCG Tin Box - PT07 - SCEPTILE-EX HOENN POWER TIN (POKÉMON TRADING CARD GAME)'),
('PT18 - VOLCANION BATTLE HEART TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1540000, 'https://product.hstatic.net/1000231532/product/upload_c4e6e0f395524d53b32ede9d34ac98ca_grande_b7aff6b1641445ce9a9dbb01d8067809.jpg', 'https://www.nshop.com.vn/products/pt18-volcanion-battle-heart-tin', 'Pokemon TCG Tin Box - PT18 - VOLCANION BATTLE HEART TIN (POKÉMON TRADING CARD GAME)'),
('PT20 - POKEMON TCG COLLECTOR’S CHEST 2016 (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 3240000, 'https://product.hstatic.net/1000231532/product/upload_c4e6e0f395524d53b32ede9d34ac98ca_grande_ca68c056687e44c38939143d2a230ea3.jpg', 'https://www.nshop.com.vn/products/pt20-pokemon-tcg-collector-chest-2016', 'Pokemon TCG Tin Box - PT20 - POKEMON TCG COLLECTOR’S CHEST 2016 (POKÉMON TRADING CARD GAME)'),
('PT25 - LUNALA-GX LEGENDS OF ALOLA TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1480000, 'https://product.hstatic.net/1000231532/product/upload_bec876d3b7444f9aba4dd2e2fbac7964_grande_24723e2115904c0bb18d524fb0dcb198.jpg', 'https://www.nshop.com.vn/products/pokemon-pt25-lunala-gx-legends-of-alola-tin', 'Pokemon TCG Tin Box - PT25 - LUNALA-GX LEGENDS OF ALOLA TIN (POKÉMON TRADING CARD GAME)'),
('PT09 - SWAMPERT-EX HOENN POWER TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1480000, 'https://product.hstatic.net/1000231532/product/upload_3f768e031389423aa0bc5a76fa82590e_grande_e8018513a9d94579b1defaccc9fb6b4c.jpg', 'https://www.nshop.com.vn/products/pt09-swampert-ex-hoenn-power-tin-pokemon', 'Pokemon TCG Tin Box - PT09 - SWAMPERT-EX HOENN POWER TIN (POKÉMON TRADING CARD GAME)'),
('PT12 - MACHAMP-EX COLLECTOR TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1220000, 'https://product.hstatic.net/1000231532/product/upload_ea769bb4f9f3405089c4552c9fccf7fc_grande_a35b7a3fba1947f4bccdabe9aeb5cc90.jpg', 'https://www.nshop.com.vn/products/pt12-machamp-ex-collector-tin-pokemon', 'Pokemon TCG Tin Box - PT12 - MACHAMP-EX COLLECTOR TIN (POKÉMON TRADING CARD GAME)'),
('PT24 - SOLGALEO-GX LEGENDS OF ALOLA TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1480000, 'https://product.hstatic.net/1000231532/product/upload_1295d2d41a054936a1bab20249603ad9_grande_db95828f0c8e4ff9a61f1eecc1a7ec59.jpg', 'https://www.nshop.com.vn/products/pokemon-pt24-solgaleo-gx-legends-of-alola-tin', 'Pokemon TCG Tin Box - PT24 - SOLGALEO-GX LEGENDS OF ALOLA TIN (POKÉMON TRADING CARD GAME)'),
('PT15 - ZYGARDE-EX SHINY KALOS TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1440000, 'https://product.hstatic.net/1000231532/product/upload_9cec1585ba644ed690cca29f063f3750_grande_14f206adf7c9417a93adb497757d926b.jpg', 'https://www.nshop.com.vn/products/pokemon-pt15-zygarde-ex-shiny-kalos-tin', 'Pokemon TCG Tin Box - PT15 - ZYGARDE-EX SHINY KALOS TIN (POKÉMON TRADING CARD GAME)'),
('PT23 - RAYQUAZA-EX BEYOND THE ORDINARY TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1520000, 'https://product.hstatic.net/1000231532/product/upload_a36e5d04aae047ec8d9f2946b1cfc601_grande_2872e9051f4f4f98b72e23ef938f0789.jpg', 'https://www.nshop.com.vn/products/pokemon-pt23-rayquaza-ex-beyond-the-ordinary-tin', 'Pokemon TCG Tin Box - PT23 - RAYQUAZA-EX BEYOND THE ORDINARY TIN (POKÉMON TRADING CARD GAME)'),
('PT26 - POKEMON TCG: SUN & MOON COLLECTOR CHEST 2017 (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 2540000, 'https://product.hstatic.net/1000231532/product/upload_c05fd3181efd4be681074c5449c5e0b8.jpg', 'https://www.nshop.com.vn/products/pokemon-pt26-pokemon-tcg-sun-moon-collector-chest-2017', 'Pokemon TCG Tin Box - PT26 - POKEMON TCG: SUN & MOON COLLECTOR CHEST 2017 (POKÉMON TRADING CARD GAME)'),
('PT10 - MEWTWO-EX COLLECTOR TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1540000, 'https://product.hstatic.net/1000231532/product/upload_8f824ffe526a4976bea593fc1aac66fe_grande_c9336b9cc6d04936b9be4b174b3fb9c8.jpg', 'https://www.nshop.com.vn/products/pt10-mewtwo-ex-collector-tin', 'Pokemon TCG Tin Box - PT10 - MEWTWO-EX COLLECTOR TIN (POKÉMON TRADING CARD GAME)'),
('PT30 - NECROZMA-GX MYSTERIOUS POWERS TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1420000, 'https://product.hstatic.net/1000231532/product/necrozma-gx-tin1-800x800.jpg', 'https://www.nshop.com.vn/products/pt30-necrozma-gx-mysterious-powers-tin', 'Pokemon TCG Tin Box - PT30 - NECROZMA-GX MYSTERIOUS POWERS TIN (POKÉMON TRADING CARD GAME)'),
('PT31 - HO-OH-GX MYSTERIOUS POWERS TIN (POKÉMON TRADING CARD GAME)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1420000, 'https://product.hstatic.net/1000231532/product/ho-oh-gx-tin1-800x800.jpg', 'https://www.nshop.com.vn/products/pt31-ho-oh-gx-mysterious-powers-tin', 'Pokemon TCG Tin Box - PT31 - HO-OH-GX MYSTERIOUS POWERS TIN (POKÉMON TRADING CARD GAME)'),
('PT33 - Dusk Mane Necrozma-GX Prism Tin (Pokemon TCG - Sun & Moon)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1700000, 'https://product.hstatic.net/1000231532/product/dusk_mane_prism_tin_1_grande_08def1d40d654817a537f00c85bd5d83.jpg', 'https://www.nshop.com.vn/products/dusk-mane-necrozma-gx-tin-pokemon', 'Pokemon TCG Tin Box - PT33 - Dusk Mane Necrozma-GX Prism Tin (Pokemon TCG - Sun & Moon)'),
('Thẻ bài Pokemon Kanto Friends Mini Tin - Pikachu', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 760000, 'https://product.hstatic.net/1000231532/product/pokemon_kanto_friends_mini_tin_pikachu_884cf2b28f4f4c3c887c2ac4bdc91baf.jpg', 'https://www.nshop.com.vn/products/pokemon-kanto-friends-mini-tin-pikachu', 'Pokemon TCG Tin Box - Thẻ bài Pokemon Kanto Friends Mini Tin - Pikachu'),
('Thẻ bài Pokemon Kanto Friends Mini Tin - Bulbasuar', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 760000, 'https://product.hstatic.net/1000231532/product/pokemon_kanto_friends_mini_tin_bulbasuar_c4bf956ddec746028deef1947bec823a.jpg', 'https://www.nshop.com.vn/products/pokemon-kanto-friends-mini-tin-bulbasuar', 'Pokemon TCG Tin Box - Thẻ bài Pokemon Kanto Friends Mini Tin - Bulbasuar'),
('Thẻ bài Pokemon Kanto Friends Mini Tin - Squirtle', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 760000, 'https://product.hstatic.net/1000231532/product/pokemon_kanto_friends_mini_tin_squirtle_34f625079fb74bb9a502078dde17f070.jpg', 'https://www.nshop.com.vn/products/pokemon-kanto-friends-mini-tin-squirtle', 'Pokemon TCG Tin Box - Thẻ bài Pokemon Kanto Friends Mini Tin - Squirtle'),
('Thẻ bài Pokemon Kanto Friends Mini Tin - Eevee', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 760000, 'https://product.hstatic.net/1000231532/product/pokemon_kanto_friends_mini_tin_eevee_f068c78bfa1b4c829422f1b283ce45a1.jpg', 'https://www.nshop.com.vn/products/pokemon-kanto-friends-mini-tin-eevee', 'Pokemon TCG Tin Box - Thẻ bài Pokemon Kanto Friends Mini Tin - Eevee'),
('Thẻ bài Pokemon Kanto Friends Mini Tin - Charmander', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 760000, 'https://product.hstatic.net/1000231532/product/pokemon_kanto_friends_mini_tin_charmander_bc0b1e633ed34a7496363f31ab43e3b0.jpg', 'https://www.nshop.com.vn/products/pokemon-kanto-friends-mini-tin-charmander', 'Pokemon TCG Tin Box - Thẻ bài Pokemon Kanto Friends Mini Tin - Charmander'),
('Thẻ bài Pokemon Kanto Power Mini Tin - Mewtwo', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 760000, 'https://product.hstatic.net/1000231532/product/pokemon_kanto_power_mini_tin_mewtwo_86ec44e3127e40b0abed6f9270a092d7.jpg', 'https://www.nshop.com.vn/products/pokemon-kanto-power-mini-tin-mewtwo', 'Pokemon TCG Tin Box - Thẻ bài Pokemon Kanto Power Mini Tin - Mewtwo'),
('Thẻ bài Pokemon Kanto Power Mini Tin - Charizard', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 760000, 'https://product.hstatic.net/1000231532/product/pokemon_kanto_power_mini_tin_charizard_0bbcb255ada749a2ae126be2a2eccc8e.jpg', 'https://www.nshop.com.vn/products/pokemon-kanto-power-mini-tin-charizard', 'Pokemon TCG Tin Box - Thẻ bài Pokemon Kanto Power Mini Tin - Charizard'),
('Thẻ bài Pokemon Kanto Power Mini Tin - Pikachu', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 760000, 'https://product.hstatic.net/1000231532/product/pokemon_kanto_power_mini_tin_pikachu_bbc7899d24e84506bf57641cf7b6a945.jpg', 'https://www.nshop.com.vn/products/pokemon-kanto-power-mini-tin-pikachu', 'Pokemon TCG Tin Box - Thẻ bài Pokemon Kanto Power Mini Tin - Pikachu'),
('Thẻ bài Pokemon Kanto Power Mini Tin - Dragonite', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 760000, 'https://product.hstatic.net/1000231532/product/pokemon_kanto_power_mini_tin_dragonite_bdeb0710634d4f968786628df2ab620a.jpg', 'https://www.nshop.com.vn/products/pokemon-kanto-power-mini-tin-dragonite', 'Pokemon TCG Tin Box - Thẻ bài Pokemon Kanto Power Mini Tin - Dragonite'),
('Thẻ bài Pokemon Kanto Power Mini Tin - Mew', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 760000, 'https://product.hstatic.net/1000231532/product/pokemon_kanto_power_mini_tin_mew_a26b8ebc45244b68a9ab6e99cbbc6113.jpg', 'https://www.nshop.com.vn/products/pokemon-kanto-power-mini-tin-mew', 'Pokemon TCG Tin Box - Thẻ bài Pokemon Kanto Power Mini Tin - Mew'),
('PT51 - Thẻ bài Pokemon Raichu-GX Hidden Fates Tin', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 2520000, 'https://product.hstatic.net/1000231532/product/bai_pokemon_raichu-gx_hidden_fates_tin_724562a7949a4cebbce9f0af627e2341.jpg', 'https://www.nshop.com.vn/products/bai-pokemon-raichu-gx-hidden-fates-tin', 'Pokemon TCG Tin Box - PT51 - Thẻ bài Pokemon Raichu-GX Hidden Fates Tin'),
('PT52 - Thẻ bài Pokemon Collector Chest Mewtwo Strikes Back Evolution', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 2900000, 'https://product.hstatic.net/1000231532/product/bai_pokemon_collector_chest_mewtwo_strikes_back_evolution_4f82a2148a044d118c3b5dc0b51b322c.jpg', 'https://www.nshop.com.vn/products/bai-pokemon-chest-mewtwo-evolution', 'Pokemon TCG Tin Box - PT52 - Thẻ bài Pokemon Collector Chest Mewtwo Strikes Back Evolution'),
('PT49 - Thẻ bài Pokemon Charizard-GX Hidden Fates Tin', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 2520000, 'https://product.hstatic.net/1000231532/product/bai_pokemon_charizard-gx_hidden_fates_tin_3aca7dbb18114e4e967ced9254d66569.jpg', 'https://www.nshop.com.vn/products/bai-pokemon-charizard-gx-hidden-fates-tin', 'Pokemon TCG Tin Box - PT49 - Thẻ bài Pokemon Charizard-GX Hidden Fates Tin'),
('PT50 - Thẻ bài Pokemon Gyarados-GX Hidden Fates Tin', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 2520000, 'https://product.hstatic.net/1000231532/product/bai_pokemon_gyarados-gx_hidden_fates_tin_e6a46330b4e74d81becd0a34c1809148.jpg', 'https://www.nshop.com.vn/products/bai-pokemon-gyarados-gx-hidden-fates-tin', 'Pokemon TCG Tin Box - PT50 - Thẻ bài Pokemon Gyarados-GX Hidden Fates Tin'),
('PT47 - Thẻ bài Pokemon Lucario & Melmetal-GX Power Partnership Tin', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 2520000, 'https://product.hstatic.net/1000231532/product/bai_pokemon_lucario_melmetal-gx_power_partnership_tin_85972f3485584cc0b01834b6d015540a.jpg', 'https://www.nshop.com.vn/products/bai-pokemon-lucario-melmetal-gx-power-partnership-tin', 'Pokemon TCG Tin Box - PT47 - Thẻ bài Pokemon Lucario & Melmetal-GX Power Partnership Tin'),
('PT46 - Thẻ bài Pokemon Mewtwo & Mew-GX Power Partnership Tin', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 2520000, 'https://product.hstatic.net/1000231532/product/bai_pokemon_mewtwo_mew-gx_power_partnership_tin_de801d911bb44809a4fa38e3cdf34bb4.jpg', 'https://www.nshop.com.vn/products/bai-pokemon-mewtwo-mew-gx-power-partnership-tin', 'Pokemon TCG Tin Box - PT46 - Thẻ bài Pokemon Mewtwo & Mew-GX Power Partnership Tin'),
('PT48 - Thẻ bài Pokemon Garchomp & Giratina-GX Power Partnership Tin', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 2520000, 'https://product.hstatic.net/1000231532/product/bai_pokemon_garchomp_giratina-gx_power_partnership_tin_f2c63f2503e641a0ae7207bfbb414e56.jpg', 'https://www.nshop.com.vn/products/bai-pokemon-garchomp-giratina-gx-power-partnership-tin', 'Pokemon TCG Tin Box - PT48 - Thẻ bài Pokemon Garchomp & Giratina-GX Power Partnership Tin'),
('PT38 - Glaceon-GX Evolution Celebration Tin (Pokemon TCG)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1700000, 'https://product.hstatic.net/1000231532/product/bai_pokemon_glaceon-gx_evolution_celebration_tin_grande_0e5a35def455471db0c08b016600ed55.jpg', 'https://www.nshop.com.vn/products/glaceon-gx-evolution-celebration-tin-pokemon-tcg', 'Pokemon TCG Tin Box - PT38 - Glaceon-GX Evolution Celebration Tin (Pokemon TCG)'),
('PT36 - Sylveon-GX Evolution Celebration Tin (Pokemon TCG)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1700000, 'https://product.hstatic.net/1000231532/product/bai_pokemon_sylveon-gx_evolution_celebration_tin_grande_c1aaf4c824664fb9a78e6d2448816c92.jpg', 'https://www.nshop.com.vn/products/sylveon-gx-evolution-celebration-tin-pokemon-tcg', 'Pokemon TCG Tin Box - PT36 - Sylveon-GX Evolution Celebration Tin (Pokemon TCG)'),
('PT37 - Leafeon-GX Evolution Celebration Tin (Pokemon TCG)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1700000, 'https://product.hstatic.net/1000231532/product/bai_pokemon_tcg_leafeon-gx_evolution_celebration_tin_grande_f21cb108f82b47afaafd23863d9b682c.jpg', 'https://www.nshop.com.vn/products/leafeon-gx-evolution-celebration-tin-pokemon-tcg', 'Pokemon TCG Tin Box - PT37 - Leafeon-GX Evolution Celebration Tin (Pokemon TCG)'),
('PT34 - Dawn Wings Necrozma-GX Prism Tin (Pokemon TCG - Sun & Moon)', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1700000, 'https://product.hstatic.net/1000231532/product/dawn_wings_tin_1_grande_0935e6e23d3340c6872a3cf4b3c94227.jpg', 'https://www.nshop.com.vn/products/dawn-wings-necrozma-gx-tin-pokemon', 'Pokemon TCG Tin Box - PT34 - Dawn Wings Necrozma-GX Prism Tin (Pokemon TCG - Sun & Moon)'),
('PT35 - Pokemon TCG Collector Chest 2018', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1880000, 'https://product.hstatic.net/1000231532/product/pokemon_tcg_collector_chest_2018.jpg', 'https://www.nshop.com.vn/products/pokemon-tcg-collector-chest-2018', 'Pokemon TCG Tin Box - PT35 - Pokemon TCG Collector Chest 2018'),
('PT40 - Thẻ bài Pokemon Vaporeon-GX Elemental Power Tin', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1840000, 'https://product.hstatic.net/1000231532/product/aporeon_gx_elemental_power_tin_a6fd674ce5e34f7dac9fad5175521c7c_grande_ef97f8620bd442aca084687c4eef4a95.jpg', 'https://www.nshop.com.vn/products/bai-pokemon-vaporeon-gx-elemental-power-tin', 'Pokemon TCG Tin Box - PT40 - Thẻ bài Pokemon Vaporeon-GX Elemental Power Tin'),
('PT44 - Thẻ bài Pokemon Celebi & Venusaur-GX TAG TEAM Tin', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 2520000, 'https://product.hstatic.net/1000231532/product/the_bai_pokemon_celebi_venusaur_gx_tag_team_tin_d058c67a35d3487a9f5a1fbb04c85940.jpg', 'https://www.nshop.com.vn/products/bai-pokemon-celebi-venusaur-gx-tag-team-tin', 'Pokemon TCG Tin Box - PT44 - Thẻ bài Pokemon Celebi & Venusaur-GX TAG TEAM Tin'),
('PT41 - Thẻ bài Pokemon Jolteon-GX Elemental Power Tin', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 1840000, 'https://product.hstatic.net/1000231532/product/jolteon_gx_elemental_power_tin_35ea005b148b497d9db3e7ce477e6ea4_grande_d51a639f718f4e619762eac0f955c190.jpg', 'https://www.nshop.com.vn/products/bai-pokemon-jolteon-gx-elemental-power-tin', 'Pokemon TCG Tin Box - PT41 - Thẻ bài Pokemon Jolteon-GX Elemental Power Tin'),
('PT42 - Thẻ bài Pokemon Pikachu & Zekrom-GX TAG TEAM Tin', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 2520000, 'https://product.hstatic.net/1000231532/product/the_bai_pokemon_pikachu_zekrom_gx_tag_team_tin_78b09508178f4624bc4601e1d21b2680.jpg', 'https://www.nshop.com.vn/products/bai-pokemon-pikachu-zekrom-gx-tag-team-tin', 'Pokemon TCG Tin Box - PT42 - Thẻ bài Pokemon Pikachu & Zekrom-GX TAG TEAM Tin'),
('PT45 - Thẻ bài Pokemon Detective Pikachu Collector Chest', 'Pokemon', 'Pokemon TCG', 'new', 1000, 'cái', 2960000, 'https://product.hstatic.net/1000231532/product/the_bai_pokemon_detective_pikachu_collector_chest_979c1736934d437e865548a3d3e62e88.jpg', 'https://www.nshop.com.vn/products/bai-pokemon-detective-pikachu-collector-chest', 'Pokemon TCG Tin Box - PT45 - Thẻ bài Pokemon Detective Pikachu Collector Chest');

-- Sync import_items into system tables so products appear in app
START TRANSACTION;

INSERT INTO roles (role_name, description)
SELECT 'donor', 'Auto-created role for Pokemon import'
WHERE NOT EXISTS (
  SELECT 1 FROM roles
  WHERE role_name COLLATE utf8mb4_unicode_ci = 'donor' COLLATE utf8mb4_unicode_ci
);

SET @role_donor := (
  SELECT role_id FROM roles
  WHERE role_name COLLATE utf8mb4_unicode_ci = 'donor' COLLATE utf8mb4_unicode_ci
  LIMIT 1
);

SET @import_user_id := (SELECT user_id FROM users ORDER BY user_id LIMIT 1);
SET @import_email := CONCAT('pokemon.import.bot.', UNIX_TIMESTAMP(), '@goodwill.local');

INSERT INTO users (name, email, password, role_id, status, email_verified)
SELECT 'Pokemon Import Bot', @import_email, '$2y$10$abcdefghijklmnopqrstuvABCDEFGHIJKLMNOpqrstuvwxyz12345', @role_donor, 'active', 1
WHERE @import_user_id IS NULL;

SET @import_user_id := (SELECT user_id FROM users ORDER BY user_id LIMIT 1);

INSERT INTO categories (name, description, status)
SELECT DISTINCT
  i.category_name,
  'Imported from Pokemon.sql',
  'active'
FROM import_items i
LEFT JOIN categories c
  ON c.name COLLATE utf8mb4_unicode_ci = i.category_name COLLATE utf8mb4_unicode_ci
WHERE c.category_id IS NULL;

SET @batch_tag := CONVERT(CONCAT('POKEMON_BATCH_', DATE_FORMAT(NOW(), '%Y%m%d_%H%i%s')) USING utf8mb4);

-- Clean only old Pokemon batches
DELETE i
FROM inventory i
JOIN donations d ON d.donation_id = i.donation_id
WHERE d.admin_notes LIKE 'POKEMON_BATCH_%';

DELETE FROM donations
WHERE admin_notes LIKE 'POKEMON_BATCH_%';

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
-- SELECT COUNT(*) FROM import_items;
-- SELECT item_id, name, sale_price, images FROM inventory ORDER BY item_id DESC LIMIT 20;
