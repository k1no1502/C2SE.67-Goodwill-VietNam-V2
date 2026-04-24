<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

if (!isAdmin() && getStaffPanelKey() !== 'support') {
    header('Location: index.php');
    exit();
}

header('Location: admin/advisor-panel.php');
exit();
