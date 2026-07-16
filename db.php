<?php
/**
 * Database Connection Handler
 * 
 * SECURITY: All credentials should be loaded from environment variables.
 * For development, you can set them directly in .env file or php.ini
 */

// Try to load .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env');
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip empty lines and comments
        if (empty($line) || $line[0] === '#') continue;
        
        // Parse KEY=VALUE format
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
        }
    }
}

// Get database configuration from environment or defaults
$host = isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : 'localhost';
$db   = isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : 'automagic_erp';
$user = isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : 'root';
$pass = isset($_ENV['DB_PASS']) ? $_ENV['DB_PASS'] : '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 5,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Log error securely - never expose database credentials
    error_log("Database Connection Error: " . $e->getMessage());
    
    // Display generic error to user
    die('Database connection failed. Please contact the administrator.');
}

// Set timezone for all database operations
try {
    $pdo->exec("SET time_zone = '+00:00'");
} catch (\PDOException $e) {
    error_log("Database timezone error: " . $e->getMessage());
}
?>
