<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'event_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND  => "SET NAMES " . DB_CHARSET
];

function getDatabaseConnection() {
    global $pdo_options;
    
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdo_options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        
        die("Database connection error. Please try again later.");
    }
}

function testDatabaseConnection() {
    try {
        $pdo = getDatabaseConnection();
        
        $stmt = $pdo->query("SELECT 1");
        $result = $stmt->fetchColumn();
        
        return $result == 1;
    } catch (Exception $e) {
        error_log("Database connection test failed: " . $e->getMessage());
        return false;
    }
}

?>
