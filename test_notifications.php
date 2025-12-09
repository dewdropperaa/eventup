<?php
session_start();
require_once 'database.php';
require_once 'notifications.php';

// Test database connection
echo "<h2>Notification System Test</h2>";

try {
    $pdo = getDatabaseConnection();
    echo "<p style='color: green;'>✓ Database connected</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Check if notifications table exists
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM notifications");
    $count = $stmt->fetchColumn();
    echo "<p style='color: green;'>✓ Notifications table exists with $count total records</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Notifications table error: " . $e->getMessage() . "</p>";
    exit;
}

// Get test user or create one
$testEmail = 'test@notification.com';
$testPassword = password_hash('password123', PASSWORD_BCRYPT);

// Clean up existing test user
$stmt = $pdo->prepare('DELETE FROM notifications WHERE user_id IN (SELECT id FROM users WHERE email = ?)');
$stmt->execute([$testEmail]);

$stmt = $pdo->prepare('DELETE FROM users WHERE email = ?');
$stmt->execute([$testEmail]);

// Create test user
$stmt = $pdo->prepare('INSERT INTO users (nom, email, mot_de_passe) VALUES (?, ?, ?)');
$stmt->execute(['Test User', $testEmail, $testPassword]);
$userId = $pdo->lastInsertId();
echo "<p style='color: green;'>✓ Created test user (ID: $userId)</p>";

// Create test notifications
echo "<h3>Creating Test Notifications:</h3>";
createNotification($userId, 'Test Type 1', 'This is a test notification message 1');
echo "<p style='color: green;'>✓ Created notification 1</p>";

createNotification($userId, 'Test Type 2', 'This is a test notification message 2');
echo "<p style='color: green;'>✓ Created notification 2</p>";

createNotification($userId, 'Test Type 3', 'This is a test notification message 3');
echo "<p style='color: green;'>✓ Created notification 3</p>";

// Verify notifications in database
echo "<h3>Verifying Notifications in Database:</h3>";
$stmt = $pdo->prepare('SELECT id, type, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$userId]);
$allNotifs = $stmt->fetchAll();
echo "<p>Total notifications for user: " . count($allNotifs) . "</p>";

if (count($allNotifs) > 0) {
    echo "<ul>";
    foreach ($allNotifs as $n) {
        echo "<li>ID: {$n['id']}, Type: {$n['type']}, Read: {$n['is_read']}, Created: {$n['created_at']}</li>";
    }
    echo "</ul>";
}

// Test getUnreadNotifications function
echo "<h3>Testing getUnreadNotifications() Function:</h3>";
$unread = getUnreadNotifications($userId, 5);
echo "<p>Unread notifications returned: " . count($unread) . "</p>";

if (count($unread) > 0) {
    echo "<p style='color: green;'>✓ Function returned notifications</p>";
    echo "<ul>";
    foreach ($unread as $n) {
        echo "<li>Type: {$n['type']}, Message: {$n['message']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>✗ Function returned empty array</p>";
}

// Test markAllNotificationsRead function
echo "<h3>Testing markAllNotificationsRead() Function:</h3>";
markAllNotificationsRead($userId);
echo "<p style='color: green;'>✓ Marked all as read</p>";

// Verify they're marked as read
$stmt = $pdo->prepare('SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$userId]);
$result = $stmt->fetch();
echo "<p>Unread count after marking all read: " . $result['unread'] . "</p>";

if ($result['unread'] == 0) {
    echo "<p style='color: green;'>✓ All notifications marked as read successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Some notifications still unread</p>";
}

// Cleanup
echo "<h3>Cleanup:</h3>";
$stmt = $pdo->prepare('DELETE FROM notifications WHERE user_id = ?');
$stmt->execute([$userId]);
$stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
$stmt->execute([$userId]);
echo "<p style='color: green;'>✓ Test data cleaned up</p>";

echo "<h3 style='color: green;'>All tests completed!</h3>";
?>
