<?php
// Direct database test without using functions
echo "<h2>Direct Database Test</h2>";

// Database configuration
$db_host = 'localhost';
$db_name = 'event_management';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    echo "<p style='color: green;'>✓ Database connected</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Check if notifications table exists and describe it
try {
    $stmt = $pdo->query("DESCRIBE notifications");
    $columns = $stmt->fetchAll();
    
    echo "<h3>Notifications Table Structure:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
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
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Error describing notifications table: " . $e->getMessage() . "</p>";
    exit;
}

// Check if users table exists
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "<p>Total users in database: {$result['count']}</p>";
    
    if ($result['count'] > 0) {
        $stmt = $pdo->query("SELECT id, nom, email FROM users LIMIT 5");
        $users = $stmt->fetchAll();
        
        echo "<h3>Sample Users:</h3>";
        echo "<ul>";
        foreach ($users as $user) {
            echo "<li>ID: {$user['id']}, Name: {$user['nom']}, Email: {$user['email']}</li>";
        }
        echo "</ul>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Error fetching users: " . $e->getMessage() . "</p>";
}

// Test inserting a notification directly
echo "<h3>Testing Direct Notification Insert:</h3>";

if ($result['count'] > 0) {
    $test_user_id = $users[0]['id'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, ?, ?)");
        $stmt->execute([$test_user_id, 'Direct Test', 'This is a direct test notification']);
        echo "<p style='color: green;'>✓ Direct insert successful</p>";
        
        // Verify it was inserted
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND type = 'Direct Test' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$test_user_id]);
        $notif = $stmt->fetch();
        
        if ($notif) {
            echo "<p style='color: green;'>✓ Notification verified in database</p>";
            echo "<ul>";
            echo "<li>ID: {$notif['id']}</li>";
            echo "<li>User ID: {$notif['user_id']}</li>";
            echo "<li>Type: {$notif['type']}</li>";
            echo "<li>Message: {$notif['message']}</li>";
            echo "<li>Is Read: {$notif['is_read']}</li>";
            echo "<li>Created At: {$notif['created_at']}</li>";
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>✗ Notification not found after insert</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Direct insert failed: " . $e->getMessage() . "</p>";
    }
}

// Test the getUnreadNotifications query directly
echo "<h3>Testing getUnreadNotifications Query:</h3>";

if ($result['count'] > 0) {
    $test_user_id = $users[0]['id'];
    
    try {
        $limit = 5;
        $sql = "SELECT id, event_id, type, message, created_at 
                FROM notifications 
                WHERE user_id = :user_id AND is_read = 0 
                ORDER BY created_at DESC 
                LIMIT $limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $test_user_id, PDO::PARAM_INT);
        $stmt->execute();
        $unread = $stmt->fetchAll();
        
        echo "<p>Unread notifications for user {$test_user_id}: " . count($unread) . "</p>";
        
        if (count($unread) > 0) {
            echo "<ul>";
            foreach ($unread as $n) {
                echo "<li>Type: {$n['type']}, Message: {$n['message']}, Created: {$n['created_at']}</li>";
            }
            echo "</ul>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Query failed: " . $e->getMessage() . "</p>";
    }
}

echo "<h3 style='color: green;'>Test completed</h3>";
?>
