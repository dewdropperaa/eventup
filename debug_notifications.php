<?php
session_start();
require_once 'database.php';
require_once 'notifications.php';

echo "<h2>Debug: Notification System</h2>";

// Get current user
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>Not logged in. Please login first.</p>";
    echo "<a href='login.php'>Go to login</a>";
    exit;
}

$user_id = $_SESSION['user_id'];
echo "<p>Current User ID: $user_id</p>";

try {
    $pdo = getDatabaseConnection();
    
    // Check user exists
    $stmt = $pdo->prepare('SELECT nom, email FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "<p style='color: red;'>User not found in database!</p>";
        exit;
    }
    
    echo "<p style='color: green;'>✓ User found: {$user['nom']} ({$user['email']})</p>";
    
    // Check existing notifications
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM notifications WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    $notif_count = $result['count'];
    
    echo "<p>Total notifications in database: $notif_count</p>";
    
    if ($notif_count > 0) {
        $stmt = $pdo->prepare('SELECT id, type, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
        $stmt->execute([$user_id]);
        $notifs = $stmt->fetchAll();
        
        echo "<h3>Recent Notifications:</h3>";
        echo "<ul>";
        foreach ($notifs as $n) {
            $read_status = $n['is_read'] ? 'READ' : 'UNREAD';
            echo "<li>ID: {$n['id']} | Type: {$n['type']} | Status: $read_status | Created: {$n['created_at']}</li>";
        }
        echo "</ul>";
    }
    
    // Test creating a notification manually
    echo "<h3>Testing Manual Notification Creation:</h3>";
    $test_result = createNotification($user_id, 'Test Notification', 'This is a test notification from debug script');
    
    if ($test_result) {
        echo "<p style='color: green;'>✓ Test notification created successfully</p>";
        
        // Verify it was created
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND type = "Test Notification"');
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        echo "<p>Notifications with type 'Test Notification': {$result['count']}</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create test notification</p>";
    }
    
    // Test getUnreadNotifications
    echo "<h3>Testing getUnreadNotifications():</h3>";
    $unread = getUnreadNotifications($user_id, 10);
    echo "<p>Unread notifications returned: " . count($unread) . "</p>";
    
    if (count($unread) > 0) {
        echo "<ul>";
        foreach ($unread as $n) {
            echo "<li>Type: {$n['type']} | Message: {$n['message']}</li>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
