<?php
require 'config/database.php';
try {
    Database::execute('ALTER TABLE campaign_items ADD quantity_transferred INT DEFAULT 0 AFTER quantity_received');
    echo "Success";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
