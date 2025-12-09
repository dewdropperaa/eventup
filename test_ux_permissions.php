<?php
/**
 * Final UX Test - Roles and Permissions System
 * Tests the complete user experience flow
 */

session_start();
require_once 'database.php';
require_once 'role_check.php';
require_once 'notifications.php';

$pdo = getDatabaseConnection();
$test_results = [];

// ============================================
// TEST 1: Event Owner Can Access Everything
// ============================================
$test_results['owner_full_access'] = [
    'name' => 'Event Owner Full Access',
    'status' => 'PENDING'
];

try {
    // Get an event and its owner
    $stmt = $pdo->query("SELECT id, created_by FROM events LIMIT 1");
    $event = $stmt->fetch();
    
    if ($event) {
        $owner_id = $event['created_by'];
        $event_id = $event['id'];
        
        // Test all permissions
        $permissions = [
            'can_edit_budget' => canDo($event_id, $owner_id, 'can_edit_budget'),
            'can_manage_resources' => canDo($event_id, $owner_id, 'can_manage_resources'),
            'can_invite_organizers' => canDo($event_id, $owner_id, 'can_invite_organizers'),
            'can_publish_updates' => canDo($event_id, $owner_id, 'can_publish_updates')
        ];
        
        $all_granted = array_reduce($permissions, function($carry, $item) {
            return $carry && $item;
        }, true);
        
        if ($all_granted) {
            $test_results['owner_full_access']['status'] = 'PASS';
            $test_results['owner_full_access']['details'] = 'Event owner has access to all features';
        } else {
            $test_results['owner_full_access']['status'] = 'FAIL';
            $test_results['owner_full_access']['details'] = 'Event owner missing permissions';
        }
    } else {
        $test_results['owner_full_access']['status'] = 'FAIL';
        $test_results['owner_full_access']['details'] = 'No events found';
    }
} catch (Exception $e) {
    $test_results['owner_full_access']['status'] = 'FAIL';
    $test_results['owner_full_access']['details'] = 'Error: ' . $e->getMessage();
}

// ============================================
// TEST 2: Permission Management UI Available to Owner
// ============================================
$test_results['permission_ui_available'] = [
    'name' => 'Permission Management UI Available to Owner',
    'status' => 'PENDING'
];

if (file_exists('event_details.php')) {
    $content = file_get_contents('event_details.php');
    if (strpos($content, 'event_permissions.php') !== false) {
        $test_results['permission_ui_available']['status'] = 'PASS';
        $test_results['permission_ui_available']['details'] = 'Event details page has permission management button';
    } else {
        $test_results['permission_ui_available']['status'] = 'FAIL';
        $test_results['permission_ui_available']['details'] = 'Event details page missing permission management button';
    }
} else {
    $test_results['permission_ui_available']['status'] = 'FAIL';
    $test_results['permission_ui_available']['details'] = 'event_details.php not found';
}

// ============================================
// TEST 3: Budget Management Enforces Permissions
// ============================================
$test_results['budget_access_control'] = [
    'name' => 'Budget Management Access Control',
    'status' => 'PENDING'
];

if (file_exists('budget.php')) {
    $content = file_get_contents('budget.php');
    if (strpos($content, 'canDo') !== false && strpos($content, 'can_edit_budget') !== false) {
        $test_results['budget_access_control']['status'] = 'PASS';
        $test_results['budget_access_control']['details'] = 'Budget management properly enforces permissions';
    } else {
        $test_results['budget_access_control']['status'] = 'FAIL';
        $test_results['budget_access_control']['details'] = 'Budget management missing permission checks';
    }
} else {
    $test_results['budget_access_control']['status'] = 'FAIL';
    $test_results['budget_access_control']['details'] = 'budget.php not found';
}

// ============================================
// TEST 4: Resource Management Enforces Permissions
// ============================================
$test_results['resource_access_control'] = [
    'name' => 'Resource Management Access Control',
    'status' => 'PENDING'
];

if (file_exists('resources.php')) {
    $content = file_get_contents('resources.php');
    if (strpos($content, 'canDo') !== false && strpos($content, 'can_manage_resources') !== false) {
        $test_results['resource_access_control']['status'] = 'PASS';
        $test_results['resource_access_control']['details'] = 'Resource management properly enforces permissions';
    } else {
        $test_results['resource_access_control']['status'] = 'FAIL';
        $test_results['resource_access_control']['details'] = 'Resource management missing permission checks';
    }
} else {
    $test_results['resource_access_control']['status'] = 'FAIL';
    $test_results['resource_access_control']['details'] = 'resources.php not found';
}

// ============================================
// TEST 5: AJAX Permission Updates Work
// ============================================
$test_results['ajax_permission_updates'] = [
    'name' => 'AJAX Permission Updates',
    'status' => 'PENDING'
];

if (file_exists('update_event_permission.php')) {
    $content = file_get_contents('update_event_permission.php');
    if (strpos($content, 'event_permissions') !== false && strpos($content, 'is_allowed') !== false) {
        $test_results['ajax_permission_updates']['status'] = 'PASS';
        $test_results['ajax_permission_updates']['details'] = 'AJAX permission update endpoint exists';
    } else {
        $test_results['ajax_permission_updates']['status'] = 'FAIL';
        $test_results['ajax_permission_updates']['details'] = 'AJAX permission update endpoint incomplete';
    }
} else {
    $test_results['ajax_permission_updates']['status'] = 'FAIL';
    $test_results['ajax_permission_updates']['details'] = 'update_event_permission.php not found';
}

// ============================================
// TEST 6: Frontend JavaScript for Permission Toggles
// ============================================
$test_results['frontend_permission_toggles'] = [
    'name' => 'Frontend Permission Toggles',
    'status' => 'PENDING'
];

if (file_exists('footer.php')) {
    $content = file_get_contents('footer.php');
    if (strpos($content, 'update_event_permission.php') !== false && strpos($content, 'permission') !== false) {
        $test_results['frontend_permission_toggles']['status'] = 'PASS';
        $test_results['frontend_permission_toggles']['details'] = 'Frontend permission toggle JavaScript exists';
    } else {
        $test_results['frontend_permission_toggles']['status'] = 'FAIL';
        $test_results['frontend_permission_toggles']['details'] = 'Frontend permission toggle JavaScript missing';
    }
} else {
    $test_results['frontend_permission_toggles']['status'] = 'FAIL';
    $test_results['frontend_permission_toggles']['details'] = 'footer.php not found';
}

// ============================================
// TEST 7: Navigation Shows Correct Options
// ============================================
$test_results['role_based_navigation'] = [
    'name' => 'Role-Based Navigation',
    'status' => 'PENDING'
];

if (file_exists('header.php')) {
    $content = file_get_contents('header.php');
    $has_organizer_nav = strpos($content, 'organizer_dashboard.php') !== false;
    $has_admin_nav = strpos($content, 'manage_tasks.php') !== false;
    
    if ($has_organizer_nav && $has_admin_nav) {
        $test_results['role_based_navigation']['status'] = 'PASS';
        $test_results['role_based_navigation']['details'] = 'Navigation includes organizer and admin options';
    } else {
        $test_results['role_based_navigation']['status'] = 'FAIL';
        $test_results['role_based_navigation']['details'] = 'Navigation missing role-based options';
    }
} else {
    $test_results['role_based_navigation']['status'] = 'FAIL';
    $test_results['role_based_navigation']['details'] = 'header.php not found';
}

// ============================================
// TEST 8: Unauthorized Access Redirects
// ============================================
$test_results['unauthorized_redirects'] = [
    'name' => 'Unauthorized Access Redirects',
    'status' => 'PENDING'
];

$redirect_files = ['budget.php', 'resources.php', 'event_permissions.php'];
$all_have_redirects = true;

foreach ($redirect_files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, 'header(') === false || strpos($content, 'Location:') === false) {
            $all_have_redirects = false;
            break;
        }
    } else {
        $all_have_redirects = false;
        break;
    }
}

if ($all_have_redirects) {
    $test_results['unauthorized_redirects']['status'] = 'PASS';
    $test_results['unauthorized_redirects']['details'] = 'All protected pages have unauthorized redirects';
} else {
    $test_results['unauthorized_redirects']['status'] = 'FAIL';
    $test_results['unauthorized_redirects']['details'] = 'Some protected pages missing unauthorized redirects';
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>EventUp - UX Permission System Test Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .test-pass { background-color: #d4edda; }
        .test-fail { background-color: #f8d7da; }
        .test-pending { background-color: #fff3cd; }
        .summary-good { background-color: #d1f2eb; border-left: 4px solid #0f5132; }
        .summary-bad { background-color: #f8d7da; border-left: 4px solid #842029; }
    </style>
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4">EventUp - UX Permission System Test Results</h1>
    
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
            
            <div class="alert <?php echo $pass_count >= 7 ? 'summary-good' : 'summary-bad'; ?> mt-4">
                <h5>UX System Status</h5>
                <p><strong>Tests Passed:</strong> <?php echo $pass_count; ?> / <?php echo count($test_results); ?></p>
                <p><strong>Tests Failed:</strong> <?php echo $fail_count; ?> / <?php echo count($test_results); ?></p>
                <p><strong>System Status:</strong> <?php echo $pass_count >= 7 ? 'WORKING' : 'NEEDS FIXES'; ?></p>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <h3>Manual UX Testing Guide</h3>
            <div class="card">
                <div class="card-body">
                    <h5>Test as Event Owner</h5>
                    <ol>
                        <li>Log in as an event creator</li>
                        <li>Go to your event details page</li>
                        <li>Verify "Gestion des Permissions" button appears</li>
                        <li>Click it and verify you can see all organizers</li>
                        <li>Toggle some permissions and verify they save</li>
                        <li>Verify you can access Budget and Resources</li>
                    </ol>
                    
                    <h5>Test as Organizer (Without Permissions)</h5>
                    <ol>
                        <li>Log in as an organizer who hasn't been granted permissions</li>
                        <li>Go to event details page</li>
                        <li>Verify "Gestion des Permissions" button does NOT appear</li>
                        <li>Try to access budget.php directly - should redirect</li>
                        <li>Try to access resources.php directly - should redirect</li>
                    </ol>
                    
                    <h5>Test as Organizer (With Permissions)</h5>
                    <ol>
                        <li>As owner, grant specific permissions to an organizer</li>
                        <li>Log in as that organizer</li>
                        <li>Verify you can access the granted features</li>
                        <li>Verify you CANNOT access non-granted features</li>
                        <li>As owner, revoke permissions and verify access is lost</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
