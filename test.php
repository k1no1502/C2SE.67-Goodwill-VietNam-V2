<?php
require 'c:/xampp/htdocs/config/database.php';
$cats = Database::fetchAll("SELECT name FROM categories WHERE status = 'active'");
echo implode(',', array_column($cats, 'name'));
