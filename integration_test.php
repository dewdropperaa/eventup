<?php
/**
 * Integration Test - Tests all three issues
 * 1. My Tasks button visibility
 * 2. Notification creation and display
 * 3. Task assignment notifications
 */

session_start();
require_once 'database.php';
require_once 'notifications.php';
require_once 'role_check.php';

$pdo = getDatabaseConnection();
$test_results = [];

// ============================================
// TEST 1: Check if "My Tasks" button exists in header
// ============================================
$test_results['my_tasks_button'] = [
    'name' => 'My Tasks Button in Header',
    'status' => 'PENDING'
];

$header_content = file_get_contents('header.php');
if (strpos($header_content, 'my_tasks.php') !== false && strpos($header_content, 'My Tasks') !== false) {
    $test_results['my_tasks_button']['status'] = 'PASS';
    $test_results['my_tasks_button']['details'] = 'My Tasks link found in header.php';
} else {
    $test_results['my_tasks_button']['status'] = 'FAIL';
    $test_results['my_tasks_button']['details'] = 'My Tasks link NOT found in header.php';
}

// ============================================
// TEST 2: Check notification system database
// ============================================
$test_results['notifications_table'] = [
    'name' => 'Notifications Table',
    'status' => 'PENDING'
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM notifications");
    $result = $stmt->fetch();
    $test_results['notifications_table']['status'] = 'PASS';
    $test_results['notifications_table']['details'] = 'Notifications table exists with ' . $result['count'] . ' records';
} catch (Exception $e) {
    $test_results['notifications_table']['status'] = 'FAIL';
    $test_results['notifications_table']['details'] = 'Error: ' . $e->getMessage();
}

// ============================================
// TEST 3: Test createNotification function
// ============================================
$test_results['create_notification'] = [
    'name' => 'Create Notification Function',
    'status' => 'PENDING'
];

try {
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $user = $stmt->fetch();
    if ($user) {
        $result = createNotification($user['id'], 'Integration Test', 'Test notification from integration test', null);
        if ($result) {
            $test_results['create_notification']['status'] = 'PASS';
            $test_results['create_notification']['details'] = 'Successfully created test notification';
        } else {
            $test_results['create_notification']['status'] = 'FAIL';
            $test_results['create_notification']['details'] = 'createNotification returned false';
        }
    } else {
        $test_results['create_notification']['status'] = 'FAIL';
        $test_results['create_notification']['details'] = 'No users found in database';
    }
} catch (Exception $e) {
    $test_results['create_notification']['status'] = 'FAIL';
    $test_results['create_notification']['details'] = 'Error: ' . $e->getMessage();
}

// ============================================
// TEST 4: Test getUnreadNotifications function
// ============================================
$test_results['get_notifications'] = [
    'name' => 'Get Unread Notifications Function',
    'status' => 'PENDING'
];

try {
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $user = $stmt->fetch();
    if ($user) {
        $notifs = getUnreadNotifications($user['id'], 10);
        $test_results['get_notifications']['status'] = 'PASS';
        $test_results['get_notifications']['details'] = 'Retrieved ' . count($notifs) . ' unread notifications';
    } else {
        $test_results['get_notifications']['status'] = 'FAIL';
        $test_results['get_notifications']['details'] = 'No users found';
    }
} catch (Exception $e) {
    $test_results['get_notifications']['status'] = 'FAIL';
    $test_results['get_notifications']['details'] = 'Error: ' . $e->getMessage();
}

// ============================================
// TEST 5: Check AJAX handler
// ============================================
$test_results['ajax_handler'] = [
    'name' => 'AJAX Handler Endpoint',
    'status' => 'PENDING'
];

$ajax_content = file_get_contents('ajax_handler.php');
if (strpos($ajax_content, 'get_notifications') !== false) {
    $test_results['ajax_handler']['status'] = 'PASS';
    $test_results['ajax_handler']['details'] = 'get_notifications case found in ajax_handler.php';
} else {
    $test_results['ajax_handler']['status'] = 'FAIL';
    $test_results['ajax_handler']['details'] = 'get_notifications case NOT found in ajax_handler.php';
}

// ============================================
// TEST 6: Check footer.php notification JavaScript
// ============================================
$test_results['footer_js'] = [
    'name' => 'Footer JavaScript Notification Handler',
    'status' => 'PENDING'
];

$footer_content = file_get_contents('footer.php');
if (strpos($footer_content, 'notificationsDropdown') !== false && strpos($footer_content, 'ajax_handler.php') !== false) {
    $test_results['footer_js']['status'] = 'PASS';
    $test_results['footer_js']['details'] = 'Notification JavaScript handler found in footer.php';
} else {
    $test_results['footer_js']['status'] = 'FAIL';
    $test_results['footer_js']['details'] = 'Notification JavaScript handler NOT found in footer.php';
}

// ============================================
// TEST 7: Check tasks table
// ============================================
$test_results['tasks_table'] = [
    'name' => 'Tasks Table',
    'status' => 'PENDING'
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tasks");
    $result = $stmt->fetch();
    $test_results['tasks_table']['status'] = 'PASS';
    $test_results['tasks_table']['details'] = 'Tasks table exists with ' . $result['count'] . ' records';
} catch (Exception $e) {
    $test_results['tasks_table']['status'] = 'FAIL';
    $test_results['tasks_table']['details'] = 'Error: ' . $e->getMessage();
}

// ============================================
// TEST 8: Check my_tasks.php
// ============================================
$test_results['my_tasks_page'] = [
    'name' => 'My Tasks Page',
    'status' => 'PENDING'
];

if (file_exists('my_tasks.php')) {
    $content = file_get_contents('my_tasks.php');
    if (strpos($content, 'organizer_id') !== false && strpos($content, 'tasks') !== false) {
        $test_results['my_tasks_page']['status'] = 'PASS';
        $test_results['my_tasks_page']['details'] = 'my_tasks.php exists and contains task fetching logic';
    } else {
        $test_results['my_tasks_page']['status'] = 'FAIL';
        $test_results['my_tasks_page']['details'] = 'my_tasks.php exists but may be missing task logic';
    }
} else {
    $test_results['my_tasks_page']['status'] = 'FAIL';
    $test_results['my_tasks_page']['details'] = 'my_tasks.php does not exist';
}

// ============================================
// TEST 9: Verify task assignment creates notification
// ============================================
$test_results['task_notification'] = [
    'name' => 'Task Assignment Notification',
    'status' => 'PENDING'
];

$ajax_content = file_get_contents('ajax_handler.php');
if (strpos($ajax_content, 'createNotification') !== false && strpos($ajax_content, 'create_task') !== false) {
    $test_results['task_notification']['status'] = 'PASS';
    $test_results['task_notification']['details'] = 'Task creation includes notification call';
} else {
    $test_results['task_notification']['status'] = 'FAIL';
    $test_results['task_notification']['details'] = 'Task creation may not include notification';
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>EventUp - Integration Test Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .test-pass { background-color: #d4edda; }
        .test-fail { background-color: #f8d7da; }
        .test-pending { background-color: #fff3cd; }
    </style>
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4">EventUp - Integration Test Results</h1>
    
    <div class="row">
        <div class="col-md-12">
            <table class="table table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>Test Name</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $pass_count = 0;
                    $fail_count = 0;
                    
                    foreach ($test_results as $test_key => $test) {
                        $status_class = 'test-' . strtolower($test['status']);
                        if ($test['status'] === 'PASS') $pass_count++;
                        if ($test['status'] === 'FAIL') $fail_count++;
                        
                        echo "<tr class='$status_class'>";
                        echo "<td><strong>" . htmlspecialchars($test['name']) . "</strong></td>";
                        echo "<td><strong>" . $test['status'] . "</strong></td>";
                        echo "<td>" . htmlspecialchars($test['details']) . "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
            
            <div class="alert alert-info mt-4">
                <h5>Summary</h5>
                <p><strong>Passed:</strong> <?php echo $pass_count; ?> / <?php echo count($test_results); ?></p>
                <p><strong>Failed:</strong> <?php echo $fail_count; ?> / <?php echo count($test_results); ?></p>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <h3>Manual Testing Instructions</h3>
            <ol>
                <li><strong>Test My Tasks Button:</strong> Log in as an organizer and check if "My Tasks" appears in the navigation bar</li>
                <li><strong>Test Notifications:</strong> Log in and click the bell icon - it should load notifications dynamically</li>
                <li><strong>Test Task Assignment:</strong> Go to Manage Tasks, create a task and assign it to an organizer. The organizer should receive a notification</li>
                <li><strong>Test My Tasks Page:</strong> As an organizer with assigned tasks, go to "My Tasks" and verify tasks appear</li>
                <li><strong>Test Event Registration Notification:</strong> Register for an event as a regular user. Event admins should receive a notification</li>
            </ol>
        </div>
    </div>
</div>
</body>
</html>
