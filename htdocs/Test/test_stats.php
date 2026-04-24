<?php
require 'config/database.php';
require 'includes/functions.php';

$stats = getStatistics();
echo "<pre>";
echo "Test Statistics:\n";
print_r($stats);
echo "</pre>";

// Test individual queries
echo "<h3>Test Individual Queries:</h3>";
echo "<pre>";

$users = Database::fetch("SELECT COUNT(*) AS total FROM users");
echo "Users: " . json_encode($users) . "\n";

$donations = Database::fetch("SELECT COUNT(*) AS total FROM donations");
echo "Donations: " . json_encode($donations) . "\n";

$inventory = Database::fetch("SELECT COALESCE(SUM(quantity), 0) AS total FROM inventory");
echo "Inventory Sum: " . json_encode($inventory) . "\n";

$campaigns = Database::fetch("SELECT COUNT(*) AS total FROM campaigns");
echo "Campaigns: " . json_encode($campaigns) . "\n";

echo "</pre>";
?>
