<?php
require 'config/database.php';
print_r(Database::fetchAll('DESCRIBE campaign_items'));
print_r(Database::fetchAll('DESCRIBE campaigns'));
