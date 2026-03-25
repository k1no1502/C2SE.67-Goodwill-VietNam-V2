<?php
require_once __DIR__ . '/../../config/database.php';

$products = [
    1 => ['name' => 'Áo thun nam basic', 'description' => 'Áo thun cotton còn tốt, phù hợp mặc hằng ngày.', 'unit' => 'chiếc', 'image' => 'placeholder-ao.svg'],
    2 => ['name' => 'Bàn ủi hơi nước mini', 'description' => 'Bàn ủi gia dụng còn hoạt động tốt, dễ sử dụng.', 'unit' => 'cái', 'image' => 'placeholder-gia-dung.svg'],
    3 => ['name' => 'Bộ lắp ráp sáng tạo', 'description' => 'Bộ đồ chơi lắp ráp còn đủ chi tiết, phù hợp cho trẻ em.', 'unit' => 'bộ', 'image' => 'placeholder-do-choi.svg'],
    4 => ['name' => 'Áo hoodie nỉ mềm', 'description' => 'Áo hoodie form rộng, chất nỉ mềm, còn sử dụng tốt.', 'unit' => 'chiếc', 'image' => 'placeholder-ao.svg'],
    5 => ['name' => 'Máy hút bụi gia đình', 'description' => 'Máy hút bụi còn chạy ổn định, phù hợp dọn dẹp nhà cửa.', 'unit' => 'cái', 'image' => 'placeholder-gia-dung.svg'],
    6 => ['name' => 'Bộ cờ cá ngựa', 'description' => 'Bộ trò chơi gia đình còn đầy đủ quân cờ và bàn chơi.', 'unit' => 'bộ', 'image' => 'placeholder-do-choi.svg'],
    7 => ['name' => 'Áo len giữ ấm', 'description' => 'Áo len còn đẹp, giữ ấm tốt cho mùa lạnh.', 'unit' => 'chiếc', 'image' => 'placeholder-ao.svg'],
    8 => ['name' => 'Máy lọc không khí mini', 'description' => 'Máy lọc không khí còn hoạt động tốt, hợp không gian nhỏ.', 'unit' => 'cái', 'image' => 'placeholder-gia-dung.svg'],
    9 => ['name' => 'Gấu bông mềm', 'description' => 'Thú bông mềm mại, sạch sẽ, phù hợp làm quà tặng.', 'unit' => 'con', 'image' => 'placeholder-do-choi.svg'],
    10 => ['name' => 'Áo khoác dạ', 'description' => 'Áo khoác dày dặn, giữ ấm tốt, còn ở tình trạng đẹp.', 'unit' => 'chiếc', 'image' => 'placeholder-ao.svg'],
    11 => ['name' => 'Máy giặt cửa trước', 'description' => 'Máy giặt gia đình vẫn vận hành ổn định.', 'unit' => 'cái', 'image' => 'placeholder-gia-dung.svg'],
    12 => ['name' => 'Búp bê vải', 'description' => 'Búp bê cho bé, còn sạch và nguyên vẹn.', 'unit' => 'con', 'image' => 'placeholder-do-choi.svg'],
    13 => ['name' => 'Áo khoác gió', 'description' => 'Áo khoác nhẹ, dễ mặc đi học hoặc đi chơi.', 'unit' => 'chiếc', 'image' => 'placeholder-ao.svg'],
    14 => ['name' => 'Nồi cơm điện', 'description' => 'Nồi cơm điện gia đình còn hoạt động tốt và đầy đủ phụ kiện.', 'unit' => 'cái', 'image' => 'placeholder-gia-dung.svg'],
    15 => ['name' => 'Mô hình siêu anh hùng', 'description' => 'Mô hình đồ chơi còn đẹp, phù hợp trưng bày hoặc sưu tầm.', 'unit' => 'mẫu', 'image' => 'placeholder-do-choi.svg'],
    16 => ['name' => 'Quần short kaki', 'description' => 'Quần short mặc mát, còn mới và dễ phối đồ.', 'unit' => 'chiếc', 'image' => 'placeholder-quan.svg'],
];

$pdo = Database::getConnection();
$updatedDonations = 0;
$updatedInventory = 0;

try {
    $pdo->beginTransaction();

    $donationStmt = $pdo->prepare('UPDATE donations SET item_name = ?, description = ?, unit = ?, images = ? WHERE donation_id = ?');
    $inventoryStmt = $pdo->prepare('UPDATE inventory SET name = ?, description = ?, unit = ?, images = ? WHERE item_id = ?');

    foreach ($products as $id => $product) {
        $images = json_encode([$product['image']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $donationStmt->execute([
            $product['name'],
            $product['description'],
            $product['unit'],
            $images,
            $id,
        ]);
        $updatedDonations += $donationStmt->rowCount();

        $inventoryStmt->execute([
            $product['name'],
            $product['description'],
            $product['unit'],
            $images,
            $id,
        ]);
        $updatedInventory += $inventoryStmt->rowCount();
    }

    $pdo->commit();
    echo 'Updated donations: ' . $updatedDonations . PHP_EOL;
    echo 'Updated inventory: ' . $updatedInventory . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
