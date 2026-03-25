<?php
require_once 'config/database.php';

$roles = Database::fetchAll("SELECT role_id, role_name FROM roles ORDER BY role_id");
echo json_encode($roles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
