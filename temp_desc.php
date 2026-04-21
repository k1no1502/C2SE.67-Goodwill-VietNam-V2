<?php
require 'config/database.php';
print_r(Database::fetchAll('DESCRIBE inventory'));
