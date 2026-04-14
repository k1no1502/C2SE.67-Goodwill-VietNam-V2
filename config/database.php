<?php
/**
 * Database Configuration
 * Cấu hình kết nối cơ sở dữ liệu MySQL
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'goodwill_vietnam');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// PDO options
$pdo_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
];

try {
    // Create PDO connection
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
    
    // Set timezone
    $pdo->exec("SET time_zone = '+07:00'");
    
} catch (PDOException $e) {
        // Log error and serve an HTML fallback for browser requests.
    error_log("Database connection failed: " . $e->getMessage());

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isApiRequest = str_starts_with($requestUri, '/api/') || stripos($acceptHeader, 'application/json') !== false;

        if (!headers_sent()) {
            http_response_code(500);
        }

        if ($isApiRequest) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }

            echo json_encode([
                'success' => false,
                'message' => 'Cơ sở dữ liệu chưa được bật hoặc chưa tồn tại.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $errorPage = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'db-error.html';

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        if (is_file($errorPage)) {
            readfile($errorPage);
        } else {
            echo 'Cơ sở dữ liệu chưa được bật hoặc chưa tồn tại.';
        }

        exit();
}

/**
 * Database helper functions
 */
class Database {
    private static $pdo = null;
    
    public static function getConnection() {
        global $pdo;
        return $pdo;
    }
    
    /**
     * Execute a prepared statement
     */
    public static function execute($sql, $params = []) {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     Fetch single row
     */
    public static function fetch($sql, $params = []) {
        $stmt = self::execute($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Fetch all rows
     */
    public static function fetchAll($sql, $params = []) {
        $stmt = self::execute($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get last insert ID
     */
    public static function lastInsertId() {
        $pdo = self::getConnection();
        return $pdo->lastInsertId();
    }
    
    /**
     * Begin transaction
     */
    public static function beginTransaction() {
        $pdo = self::getConnection();
        return $pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public static function commit() {
        $pdo = self::getConnection();
        return $pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public static function rollback() {
        $pdo = self::getConnection();
        return $pdo->rollback();
    }
}
?>
