<?php
require_once __DIR__ . '/config/database.php';

$categories = Database::fetchAll("SELECT category_id, name FROM categories WHERE status = 'active' ORDER BY name");

echo "DANH MỤC\n";
echo "========\n";
foreach ($categories as $cat) {
    echo $cat['name'] . "\n";
}
?>
