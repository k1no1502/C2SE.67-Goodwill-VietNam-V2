<?php
require_once __DIR__ . '/../_auth.php';

$currentUser = api_require_staff_or_admin();
$currentUserId = (int)($currentUser['user_id'] ?? 0);
?>
