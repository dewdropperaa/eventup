<?php
/**
 * Comprehensive Test Script for EventUp Issues
 * Tests:
 * 1. Notification creation and retrieval
 * 2. Task assignment and notification
 * 3. My Tasks page functionality
 */

session_start();
require_once 'database.php';
require_once 'notifications.php';

$pdo = getDatabaseConnection();

// Test 1: Check if notifications table exists and has data
echo "<h2>Test 1: Notifications Table Status</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM notifications");
    $result = $stmt->fetch();
    echo "Total notifications in database: " . $result['count'] . "<br>";
    
    // Show recent notifications
    $stmt = $pdo->query("SELECT id, user_id, type, message, is_read, created_at FROM notifications ORDER BY created_at DESC LIMIT 5");
    $notifs = $stmt->fetchAll();
    echo "Recent notifications:<br>";
    echo "<pre>";
    print_r($notifs);
    echo "</pre>";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

// Test 2: Check if tasks table exists and has data
echo "<h2>Test 2: Tasks Table Status</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tasks");
    $result = $stmt->fetch();
    echo "Total tasks in database: " . $result['count'] . "<br>";
    
    // Show recent tasks
    $stmt = $pdo->query("SELECT id, event_id, organizer_id, task_name, status, created_at FROM tasks ORDER BY created_at DESC LIMIT 5");
    $tasks = $stmt->fetchAll();
    echo "Recent tasks:<br>";
    echo "<pre>";
    print_r($tasks);
    echo "</pre>";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

// Test 3: Test createNotification function
echo "<h2>Test 3: Test createNotification Function</h2>";
try {
    // Get a test user
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $user = $stmt->fetch();
    if ($user) {
        $test_user_id = $user['id'];
        $result = createNotification($test_user_id, 'Test Notification', 'This is a test notification from the test script', null);
        if ($result) {
            echo "✓ Notification created successfully for user $test_user_id<br>";
        } else {
            echo "✗ Failed to create notification<br>";
        }
    } else {
        echo "No users found in database<br>";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

// Test 4: Test getUnreadNotifications function
echo "<h2>Test 4: Test getUnreadNotifications Function</h2>";
try {
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $user = $stmt->fetch();
    if ($user) {
        $test_user_id = $user['id'];
        $unread = getUnreadNotifications($test_user_id, 10);
        echo "Unread notifications for user $test_user_id: " . count($unread) . "<br>";
        echo "<pre>";
        print_r($unread);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

// Test 5: Check event_roles table
echo "<h2>Test 5: Event Roles Status</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM event_roles");
    $result = $stmt->fetch();
    echo "Total event roles: " . $result['count'] . "<br>";
    
    $stmt = $pdo->query("SELECT user_id, event_id, role FROM event_roles LIMIT 10");
    $roles = $stmt->fetchAll();
    echo "Sample event roles:<br>";
    echo "<pre>";
    print_r($roles);
    echo "</pre>";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

// Test 6: Check if My Tasks page can fetch tasks
echo "<h2>Test 6: My Tasks Functionality</h2>";
try {
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $user = $stmt->fetch();
    if ($user) {
        $test_user_id = $user['id'];
        $stmt = $pdo->prepare("
            SELECT t.id, t.task_name, t.status, t.due_date
            FROM tasks t
            WHERE t.organizer_id = :user_id
            LIMIT 5
        ");
        $stmt->execute([':user_id' => $test_user_id]);
        $tasks = $stmt->fetchAll();
        echo "Tasks for user $test_user_id: " . count($tasks) . "<br>";
        echo "<pre>";
        print_r($tasks);
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
}

// Test 7: Check AJAX handler
echo "<h2>Test 7: AJAX Handler Status</h2>";
if (file_exists('ajax_handler.php')) {
    echo "✓ ajax_handler.php exists<br>";
    $content = file_get_contents('ajax_handler.php');
    if (strpos($content, 'get_notifications') !== false) {
        echo "✓ get_notifications case found in ajax_handler.php<br>";
    } else {
        echo "✗ get_notifications case NOT found in ajax_handler.php<br>";
    }
} else {
    echo "✗ ajax_handler.php does not exist<br>";
}

// Test 8: Check header.php
echo "<h2>Test 8: Header.php Status</h2>";
if (file_exists('header.php')) {
    echo "✓ header.php exists<br>";
    $content = file_get_contents('header.php');
    if (strpos($content, 'notification-menu') !== false) {
        echo "✓ notification-menu element found in header.php<br>";
    } else {
        echo "✗ notification-menu element NOT found in header.php<br>";
    }
    if (strpos($content, 'My Tasks') !== false) {
        echo "✓ My Tasks link found in header.php<br>";
    } else {
        echo "✗ My Tasks link NOT found in header.php<br>";
    }
} else {
    echo "✗ header.php does not exist<br>";
}

?>
