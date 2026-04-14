<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Require login
requireLogin();

$response = ['success' => false, 'message' => 'Unknown error'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $action = $_POST['action'] ?? '';
    
    if ($action !== 'create_test_donation') {
        throw new Exception('Invalid action');
    }

    // Validate input
    $item_name = sanitize($_POST['item_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    $unit = sanitize($_POST['unit'] ?? 'cái');
    $condition_status = sanitize($_POST['condition_status'] ?? 'good');
    $estimated_value = (float)($_POST['estimated_value'] ?? 0);

    if (!$item_name) {
        throw new Exception('Item name is required');
    }
    if ($category_id <= 0) {
        throw new Exception('Category is required');
    }
    if ($quantity <= 0) {
        throw new Exception('Quantity must be greater than 0');
    }

    // Create donation with pending status (so admin needs to approve)
    $sql = "INSERT INTO donations (
                user_id, item_name, description, category_id, quantity, unit,
                condition_status, estimated_value, images, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

    Database::execute($sql, [
        $_SESSION['user_id'],
        $item_name,
        $description,
        $category_id,
        $quantity,
        $unit,
        $condition_status,
        $estimated_value,
        json_encode(['placeholder' => getCategoryPlaceholderImage($category_id)])
    ]);

    $donation_id = Database::lastInsertId();

    // Log activity
    logActivity($_SESSION['user_id'], 'donate', "Created test donation #$donation_id: {$item_name}");

    $response = [
        'success' => true,
        'message' => 'Donation created successfully',
        'donation_id' => $donation_id
    ];

} catch (Exception $e) {
    error_log("Test donation error: " . $e->getMessage());
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);
?>
