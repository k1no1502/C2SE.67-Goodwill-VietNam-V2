<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function api_json($success, array $payload = [], $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(['success' => $success], $payload));
    exit();
}

function api_get_header($name) {
    $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (isset($_SERVER[$normalized])) {
        return $_SERVER[$normalized];
    }
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
    }
    return null;
}
?>
