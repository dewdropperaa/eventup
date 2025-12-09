<?php
/**
 * Verification Script - Confirms all fixes are in place
 */

$checks = [];

// Check 1: My Tasks button in header
$checks['my_tasks_button'] = [
    'name' => '✓ My Tasks Button',
    'file' => 'header.php',
    'search' => 'my_tasks.php',
    'description' => 'My Tasks link for organizers'
];

// Check 2: Notification dropdown in header
$checks['notification_dropdown'] = [
    'name' => '✓ Notification Dropdown',
    'file' => 'header.php',
    'search' => 'notification-menu',
    'description' => 'Dynamic notification dropdown'
];

// Check 3: AJAX notification handler in footer
$checks['ajax_notifications'] = [
    'name' => '✓ AJAX Notification Handler',
    'file' => 'footer.php',
    'search' => 'ajax_handler.php?action=get_notifications',
    'description' => 'JavaScript AJAX call for notifications'
];

// Check 4: get_notifications case in ajax_handler
$checks['get_notifications_case'] = [
    'name' => '✓ Get Notifications Case',
    'file' => 'ajax_handler.php',
    'search' => "case 'get_notifications'",
    'description' => 'AJAX endpoint for fetching notifications'
];

// Check 5: Task notification in ajax_handler
$checks['task_notification'] = [
    'name' => '✓ Task Notification',
    'file' => 'ajax_handler.php',
    'search' => 'createNotification($organizer_id',
    'description' => 'Notification creation on task assignment'
];

// Check 6: AJAX form submission in manage_tasks
$checks['ajax_form_submission'] = [
    'name' => '✓ AJAX Form Submission',
    'file' => 'manage_tasks.php',
    'search' => "fetch('ajax_handler.php'",
    'description' => 'AJAX task form submission'
];

// Check 7: Form action value
$checks['form_action_value'] = [
    'name' => '✓ Form Action Value',
    'file' => 'manage_tasks.php',
    'search' => 'value="create_task"',
    'description' => 'Correct form action value'
];

// Check 8: Notification badge handling
$checks['badge_handling'] = [
    'name' => '✓ Badge Handling',
    'file' => 'footer.php',
    'search' => 'badge.textContent = notifications.length',
    'description' => 'Dynamic badge count update'
];

// Check 9: My Tasks page
$checks['my_tasks_page'] = [
    'name' => '✓ My Tasks Page',
    'file' => 'my_tasks.php',
    'search' => 'organizer_id',
    'description' => 'My Tasks page functionality'
];

// Check 10: Notification functions
$checks['notification_functions'] = [
    'name' => '✓ Notification Functions',
    'file' => 'notifications.php',
    'search' => 'function createNotification',
    'description' => 'Notification creation function'
];

$results = [];

foreach ($checks as $key => $check) {
    $file_path = $check['file'];
    $search_term = $check['search'];
    
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        if (strpos($content, $search_term) !== false) {
            $results[$key] = [
                'status' => 'PASS',
                'message' => $check['name'] . ' - Found in ' . $file_path
            ];
        } else {
            $results[$key] = [
                'status' => 'FAIL',
                'message' => $check['name'] . ' - NOT found in ' . $file_path . ' (searching for: ' . $search_term . ')'
            ];
        }
    } else {
        $results[$key] = [
            'status' => 'FAIL',
            'message' => $check['name'] . ' - File not found: ' . $file_path
        ];
    }
}

$pass_count = 0;
$fail_count = 0;

foreach ($results as $result) {
    if ($result['status'] === 'PASS') {
        $pass_count++;
    } else {
        $fail_count++;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>EventUp - Verification Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .pass { background-color: #d4edda; border-left: 5px solid #28a745; }
        .fail { background-color: #f8d7da; border-left: 5px solid #dc3545; }
        .verification-item { padding: 15px; margin-bottom: 10px; border-radius: 5px; }
        .summary { margin-top: 30px; }
        .summary-card { text-align: center; padding: 20px; }
        .pass-count { color: #28a745; font-size: 2em; font-weight: bold; }
        .fail-count { color: #dc3545; font-size: 2em; font-weight: bold; }
    </style>
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4">EventUp - Verification Results</h1>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <h5>Verification Status</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($results as $key => $result): ?>
                        <div class="verification-item <?php echo strtolower($result['status']); ?>">
                            <strong><?php echo $result['message']; ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row summary">
        <div class="col-md-6">
            <div class="card summary-card bg-success text-white">
                <div class="pass-count"><?php echo $pass_count; ?></div>
                <p>Checks Passed</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card summary-card bg-danger text-white">
                <div class="fail-count"><?php echo $fail_count; ?></div>
                <p>Checks Failed</p>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5>Next Steps</h5>
                </div>
                <div class="card-body">
                    <?php if ($fail_count === 0): ?>
                        <div class="alert alert-success">
                            <h5>✓ All fixes are in place!</h5>
                            <p>You can now proceed with manual testing:</p>
                            <ol>
                                <li>Log in as an organizer and verify "My Tasks" button appears</li>
                                <li>Click the bell icon and verify notifications load</li>
                                <li>Create a task and verify notification is sent</li>
                                <li>Check "My Tasks" page for assigned tasks</li>
                            </ol>
                            <p><strong>Test Pages:</strong></p>
                            <ul>
                                <li><a href="integration_test.php">Integration Test</a> - Automated checks</li>
                                <li><a href="test_complete_flow.php">Complete Flow Test</a> - Manual testing interface</li>
                                <li><a href="test_ajax_direct.php">AJAX Direct Test</a> - Test AJAX endpoint</li>
                                <li><a href="MANUAL_TEST_GUIDE.md">Manual Test Guide</a> - Detailed testing instructions</li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <h5>✗ Some checks failed</h5>
                            <p>Please review the failed checks above and ensure all files have been properly modified.</p>
                            <p>Refer to <strong>FIXES_APPLIED.md</strong> for detailed information about each fix.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4 mb-5">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5>Documentation</h5>
                </div>
                <div class="card-body">
                    <ul>
                        <li><a href="FIXES_APPLIED.md">FIXES_APPLIED.md</a> - Detailed list of all fixes</li>
                        <li><a href="MANUAL_TEST_GUIDE.md">MANUAL_TEST_GUIDE.md</a> - Complete testing guide</li>
                        <li><a href="DEVELOPER_GUIDE.md">DEVELOPER_GUIDE.md</a> - Developer documentation</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
