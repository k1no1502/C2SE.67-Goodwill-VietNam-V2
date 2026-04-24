<?php
declare(strict_types=1);

$root = 'c:/xampp/htdocs';
$sqlPath = $root . '/database/Database.sql';
$dbHost = 'localhost';
$dbName = 'goodwill_vietnam';
$dbUser = 'root';
$dbPass = '';

if (!file_exists($sqlPath)) {
    fwrite(STDERR, "Missing file: $sqlPath\n");
    exit(1);
}

$map = [
    'Am sieu toc inox gia dinh lon' => 'Ấm siêu tốc inox gia đình lớn',
    'Am sieu toc inox gia dinh nho' => 'Ấm siêu tốc inox gia đình nhỏ',
    'Am sieu toc inox gia dinh vua' => 'Ấm siêu tốc inox gia đình vừa',
    'Ao khoac hoodie mau trang' => 'Áo khoác hoodie màu trắng',
    'Ao khoac jean mau xanh navy' => 'Áo khoác jean màu xanh navy',
    'Ao len mong mau xanh navy' => 'Áo len mỏng màu xanh navy',
    'Ao so mi tay dai mau trang' => 'Áo sơ mi tay dài màu trắng',
    'Ao the thao dry fit mau xanh navy' => 'Áo thể thao Dry Fit màu xanh navy',
    'Ao thun cotton mau den' => 'Áo thun cotton màu đen',
    'Ban phim co gaming phien ban co ban' => 'Bàn phím cơ gaming phiên bản cơ bản',
    'Ban phim co gaming phien ban nang cao' => 'Bàn phím cơ gaming phiên bản nâng cao',
    'Ban phim co gaming phien ban pin lon' => 'Bàn phím cơ gaming phiên bản pin lớn',
    'Ban ui hoi nuoc gia dinh lon' => 'Bàn ủi hơi nước gia đình lớn',
    'Ban ui hoi nuoc gia dinh nho' => 'Bàn ủi hơi nước gia đình nhỏ',
    'Ban ui hoi nuoc gia dinh vua' => 'Bàn ủi hơi nước gia đình vừa',
    'Bep tu don gia dinh lon' => 'Bếp từ đơn gia đình lớn',
    'Bep tu don gia dinh nho' => 'Bếp từ đơn gia đình nhỏ',
    'Bep tu don gia dinh vua' => 'Bếp từ đơn gia đình vừa',
    'Bo noi inox 5 mon gia dinh lon' => 'Bộ nồi inox 5 món gia đình lớn',
    'Bo noi inox 5 mon gia dinh nho' => 'Bộ nồi inox 5 món gia đình nhỏ',
    'Bo noi inox 5 mon gia dinh vua' => 'Bộ nồi inox 5 món gia đình vừa',
    'Camera an ninh WiFi phien ban co ban' => 'Camera an ninh WiFi phiên bản cơ bản',
    'Camera an ninh WiFi phien ban nang cao' => 'Camera an ninh WiFi phiên bản nâng cao',
    'Camera an ninh WiFi phien ban pin lon' => 'Camera an ninh WiFi phiên bản pin lớn',
    'Chan vay xep ly mau trang' => 'Chân váy xếp ly màu trắng',
    'Chuot khong day phien ban co ban' => 'Chuột không dây phiên bản cơ bản',
    'Chuot khong day phien ban nang cao' => 'Chuột không dây phiên bản nâng cao',
    'Chuot khong day phien ban pin lon' => 'Chuột không dây phiên bản pin lớn',
    'Dam cong so mau trang' => 'Đầm công sở màu trắng',
    'Dong ho thong minh phien ban co ban' => 'Đồng hồ thông minh phiên bản cơ bản',
    'Dong ho thong minh phien ban nang cao' => 'Đồng hồ thông minh phiên bản nâng cao',
    'Dong ho thong minh phien ban pin lon' => 'Đồng hồ thông minh phiên bản pin lớn',
    'Lo vi song mini gia dinh lon' => 'Lò vi sóng mini gia đình lớn',
    'Lo vi song mini gia dinh nho' => 'Lò vi sóng mini gia đình nhỏ',
    'Lo vi song mini gia dinh vua' => 'Lò vi sóng mini gia đình vừa',
    'Loa mini khong day phien ban co ban' => 'Loa mini không dây phiên bản cơ bản',
    'Loa mini khong day phien ban nang cao' => 'Loa mini không dây phiên bản nâng cao',
    'Loa mini khong day phien ban pin lon' => 'Loa mini không dây phiên bản pin lớn',
    'May doc sach e-ink phien ban co ban' => 'Máy đọc sách e-ink phiên bản cơ bản',
    'May doc sach e-ink phien ban nang cao' => 'Máy đọc sách e-ink phiên bản nâng cao',
    'May doc sach e-ink phien ban pin lon' => 'Máy đọc sách e-ink phiên bản pin lớn',
    'May hut bui cam tay gia dinh lon' => 'Máy hút bụi cầm tay gia đình lớn',
    'May hut bui cam tay gia dinh nho' => 'Máy hút bụi cầm tay gia đình nhỏ',
    'May hut bui cam tay gia dinh vua' => 'Máy hút bụi cầm tay gia đình vừa',
    'May in mini phien ban co ban' => 'Máy in mini phiên bản cơ bản',
    'May in mini phien ban nang cao' => 'Máy in mini phiên bản nâng cao',
    'May in mini phien ban pin lon' => 'Máy in mini phiên bản pin lớn',
    'May loc khong khi gia dinh lon' => 'Máy lọc không khí gia đình lớn',
    'May loc khong khi gia dinh nho' => 'Máy lọc không khí gia đình nhỏ',
    'May loc khong khi gia dinh vua' => 'Máy lọc không khí gia đình vừa',
    'May tinh bang Android phien ban co ban' => 'Máy tính bảng Android phiên bản cơ bản',
    'May tinh bang Android phien ban nang cao' => 'Máy tính bảng Android phiên bản nâng cao',
    'May tinh bang Android phien ban pin lon' => 'Máy tính bảng Android phiên bản pin lớn',
    'May xay sinh to gia dinh lon' => 'Máy xay sinh tố gia đình lớn',
    'May xay sinh to gia dinh nho' => 'Máy xay sinh tố gia đình nhỏ',
    'May xay sinh to gia dinh vua' => 'Máy xay sinh tố gia đình vừa',
    'Noi chien khong dau gia dinh lon' => 'Nồi chiên không dầu gia đình lớn',
    'Noi chien khong dau gia dinh nho' => 'Nồi chiên không dầu gia đình nhỏ',
    'Noi chien khong dau gia dinh vua' => 'Nồi chiên không dầu gia đình vừa',
    'Noi com dien da nang gia dinh lon' => 'Nồi cơm điện đa năng gia đình lớn',
    'Noi com dien da nang gia dinh nho' => 'Nồi cơm điện đa năng gia đình nhỏ',
    'Noi com dien da nang gia dinh vua' => 'Nồi cơm điện đa năng gia đình vừa',
    'O cung SSD phien ban co ban' => 'Ổ cứng SSD phiên bản cơ bản',
    'O cung SSD phien ban nang cao' => 'Ổ cứng SSD phiên bản nâng cao',
    'O cung SSD phien ban pin lon' => 'Ổ cứng SSD phiên bản pin lớn',
    'Quan jean slim fit mau xanh navy' => 'Quần jean slim fit màu xanh navy',
    'Quan short kaki mau den' => 'Quần short kaki màu đen',
    'Quan tay cong so mau den' => 'Quần tây công sở màu đen',
    'Quat dung tiet kiem dien gia dinh lon' => 'Quạt đứng tiết kiệm điện gia đình lớn',
    'Quat dung tiet kiem dien gia dinh nho' => 'Quạt đứng tiết kiệm điện gia đình nhỏ',
    'Quat dung tiet kiem dien gia dinh vua' => 'Quạt đứng tiết kiệm điện gia đình vừa',
    'Tai nghe Bluetooth phien ban co ban' => 'Tai nghe Bluetooth phiên bản cơ bản',
    'Tai nghe Bluetooth phien ban nang cao' => 'Tai nghe Bluetooth phiên bản nâng cao',
    'Tai nghe Bluetooth phien ban pin lon' => 'Tai nghe Bluetooth phiên bản pin lớn',
    'Vay midi cong so mau den' => 'Váy midi công sở màu đen',
    'Webcam HD phien ban co ban' => 'Webcam HD phiên bản cơ bản',
    'Webcam HD phien ban nang cao' => 'Webcam HD phiên bản nâng cao',
];

$text = file_get_contents($sqlPath);
if ($text === false) {
    fwrite(STDERR, "Cannot read SQL file\n");
    exit(1);
}

foreach ($map as $old => $new) {
    $text = str_replace("'{$old}'", "'{$new}'", $text);
}

file_put_contents($sqlPath, $text);

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $stmtDonation = $pdo->prepare('UPDATE donations SET item_name = :new WHERE item_name = :old');
    $stmtInventory = $pdo->prepare('UPDATE inventory SET name = :new WHERE name = :old');

    foreach ($map as $old => $new) {
        $stmtDonation->execute([':old' => $old, ':new' => $new]);
        $stmtInventory->execute([':old' => $old, ':new' => $new]);
    }

    echo 'updated_names=' . count($map) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'DB update error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
