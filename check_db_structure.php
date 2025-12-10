<?php
require_once 'database.php';

echo "<h2>Database Structure Check</h2>";

try {
    $pdo = getDatabaseConnection();
    echo "<p style='color: green;'>✓ Database connected</p>";
    
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Tables in database:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li><strong>$table</strong></li>";
    }
    echo "</ul>";
    
    // Check specific tables we need
    $required_tables = ['users', 'events'];
    
    foreach ($required_tables as $table) {
        if (in_array($table, $tables)) {
            echo "<h3>Structure of '$table' table:</h3>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll();
            
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td>{$col['Field']}</td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Key']}</td>";
                echo "<td>{$col['Default']}</td>";
                echo "<td>{$col['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Check for primary key
            $stmt = $pdo->query("SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY'");
            $primary_key = $stmt->fetch();
            if ($primary_key) {
                echo "<p style='color: green;'>✓ Primary key: {$primary_key['Column_name']}</p>";
            } else {
                echo "<p style='color: red;'>✗ No primary key found</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ Table '$table' does not exist</p>";
        }
    }
    
    // Check if notifications table exists
    if (in_array('notifications', $tables)) {
        echo "<h3>Notifications table already exists</h3>";
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM notifications");
        $result = $stmt->fetch();
        echo "<p>Notifications in table: {$result['count']}</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Notifications table does not exist</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}
?>
