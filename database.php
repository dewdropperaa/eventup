<?php
/**
 * Database connection using PDO with proper error handling
 * Connects to the event_management MySQL database
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'event_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// PDO options for error handling and fetch mode
$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use native prepared statements
    PDO::MYSQL_ATTR_INIT_COMMAND  => "SET NAMES " . DB_CHARSET
];

/**
 * Get PDO database connection
 * 
 * @return PDO Database connection object
 * @throws PDOException If connection fails
 */
function getDatabaseConnection() {
    global $pdo_options;
    
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
        return $pdo;
    } catch (PDOException $e) {
        // Log error and display user-friendly message
        error_log("Database connection failed: " . $e->getMessage());
        
        // In production, show a generic error message
        // In development, you might want to show the actual error
        die("Database connection error. Please try again later.");
    }
}

/**
 * Test database connection
 * 
 * @return bool True if connection successful, false otherwise
 */
function testDatabaseConnection() {
    try {
        $pdo = getDatabaseConnection();
        
        // Test with a simple query
        $stmt = $pdo->query("SELECT 1");
        $result = $stmt->fetchColumn();
        
        return $result == 1;
    } catch (Exception $e) {
        error_log("Database connection test failed: " . $e->getMessage());
        return false;
    }
}

?>
