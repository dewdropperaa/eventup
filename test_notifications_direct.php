<?php
/**
 * Direct test of notification system
 */

session_start();
require_once 'database.php';
require_once 'notifications.php';

$pdo = getDatabaseConnection();

echo "<h1>Notification System Direct Test</h1>";

// Test 1: Create a test notification
echo "<h2>Test 1: Creating Test Notification</h2>";
try {
    // Get first user
    $stmt = $pdo->query("SELECT id, nom, email FROM users LIMIT 1");
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "ERROR: No users found in database<br>";
    } else {
        echo "Test user: " . $user['nom'] . " (" . $user['email'] . ")<br>";
        
        $result = createNotification($user['id'], 'Test Notification', 'This is a test notification', null);
        if ($result) {
            echo "✓ Notification created successfully<br>";
        } else {
            echo "✗ Failed to create notification<br>";
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

// Test 2: Verify notification was inserted
echo "<h2>Test 2: Verify Notification in Database</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM notifications WHERE type = 'Test Notification'");
    $result = $stmt->fetch();
    echo "Test notifications in database: " . $result['count'] . "<br>";
    
    if ($result['count'] > 0) {
        $stmt = $pdo->query("SELECT id, user_id, type, message, is_read, created_at FROM notifications WHERE type = 'Test Notification' ORDER BY created_at DESC LIMIT 1");
        $notif = $stmt->fetch();
        echo "<pre>";
        print_r($notif);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

// Test 3: Test getUnreadNotifications
echo "<h2>Test 3: Test getUnreadNotifications Function</h2>";
try {
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $user = $stmt->fetch();
    
    if ($user) {
        $unread = getUnreadNotifications($user['id'], 10);
        echo "Unread notifications for user " . $user['id'] . ": " . count($unread) . "<br>";
        
        if (count($unread) > 0) {
            echo "<pre>";
            print_r($unread);
            echo "</pre>";
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

// Test 4: Test AJAX endpoint
echo "<h2>Test 4: Test AJAX Endpoint (Simulated)</h2>";
echo "Testing: ajax_handler.php?action=get_notifications<br>";

// Simulate the AJAX call
$_SESSION['user_id'] = 1; // Set a test user ID
$_GET['action'] = 'get_notifications';

ob_start();
include 'ajax_handler.php';
$output = ob_get_clean();

echo "Response from ajax_handler.php:<br>";
echo "<pre>";
echo htmlspecialchars($output);
echo "</pre>";

// Test 5: Check My Tasks
echo "<h2>Test 5: Check My Tasks Functionality</h2>";
try {
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $user = $stmt->fetch();
    
    if ($user) {
        $stmt = $pdo->prepare("
            SELECT t.id, t.task_name, t.status, t.due_date, e.titre as event_title
            FROM tasks t
            INNER JOIN events e ON t.event_id = e.id
            WHERE t.organizer_id = :user_id
            LIMIT 5
        ");
        $stmt->execute([':user_id' => $user['id']]);
        $tasks = $stmt->fetchAll();
        
        echo "Tasks for user " . $user['id'] . ": " . count($tasks) . "<br>";
        
        if (count($tasks) > 0) {
            echo "<pre>";
            print_r($tasks);
            echo "</pre>";
        } else {
            echo "No tasks assigned to this user<br>";
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

?>
