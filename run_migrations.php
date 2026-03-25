<?php
/**
 * Migration Runner
 * Execute pending SQL migration files
 */

require_once __DIR__ . '/config/database.php';

$migrationDir = __DIR__ . '/database/migrations';
$migrationsToRun = [
    '20260307_add_recruitment_positions.sql',
    '20260322_add_campaign_video.sql',
    '20260322_add_product_ratings.sql',
    '20260323_add_donation_pickup_fields.sql',
    '20260323_add_donation_location_fields.sql',
    '20260323_add_donation_value_fields.sql',
    '20260323_add_campaign_items_image.sql',
    '20260323_add_campaign_items_condition.sql'
];

foreach ($migrationsToRun as $migration) {
    $filePath = $migrationDir . '/' . $migration;
    
    if (!file_exists($filePath)) {
        echo "Migration file not found: $migration\n";
        continue;
    }
    
    $sql = file_get_contents($filePath);
    
    try {
        // Split by semicolon for multiple statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
                echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
            }
        }
        
        echo "✓ Migration completed: $migration\n";
    } catch (Exception $e) {
        echo "✗ Migration failed: $migration\n";
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\nAll migrations completed!\n";
?>
