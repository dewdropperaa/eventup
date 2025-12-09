<?php
/**
 * Permission System Test Suite
 * Tests the complete roles and permissions system
 */

session_start();
require_once 'database.php';
require_once 'role_check.php';
require_once 'notifications.php';

$pdo = getDatabaseConnection();
$test_results = [];

// ============================================
// TEST 1: Check if permissions tables exist
// ============================================
$test_results['permissions_tables'] = [
    'name' => 'Permissions Tables Exist',
    'status' => 'PENDING'
];

try {
    $tables_to_check = ['event_organizers', 'event_permissions'];
    $all_exist = true;
    $details = [];
    
    foreach ($tables_to_check as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        $details[] = "$table: " . ($exists ? "EXISTS" : "MISSING");
        if (!$exists) $all_exist = false;
    }
    
    if ($all_exist) {
        $test_results['permissions_tables']['status'] = 'PASS';
        $test_results['permissions_tables']['details'] = implode(', ', $details);
    } else {
        $test_results['permissions_tables']['status'] = 'FAIL';
        $test_results['permissions_tables']['details'] = implode(', ', $details);
    }
} catch (Exception $e) {
    $test_results['permissions_tables']['status'] = 'FAIL';
    $test_results['permissions_tables']['details'] = 'Error: ' . $e->getMessage();
}

// ============================================
// TEST 2: Check canDo function exists
// ============================================
$test_results['cando_function'] = [
    'name' => 'canDo Function Exists',
    'status' => 'PENDING'
];

if (function_exists('canDo')) {
    $test_results['cando_function']['status'] = 'PASS';
    $test_results['cando_function']['details'] = 'canDo function found in role_check.php';
} else {
    $test_results['cando_function']['status'] = 'FAIL';
    $test_results['cando_function']['details'] = 'canDo function NOT found';
}

// ============================================
// TEST 3: Test event owner permissions
// ============================================
$test_results['owner_permissions'] = [
    'name' => 'Event Owner Permissions',
    'status' => 'PENDING'
];

try {
    $stmt = $pdo->query("SELECT e.id, e.created_by FROM events e LIMIT 1");
    $event = $stmt->fetch();
    
    if ($event) {
        $owner_id = $event['created_by'];
        $event_id = $event['id'];
        
        // Test if owner has all permissions
        $can_edit_budget = canDo($event_id, $owner_id, 'can_edit_budget');
        $can_manage_resources = canDo($event_id, $owner_id, 'can_manage_resources');
        $can_invite_organizers = canDo($event_id, $owner_id, 'can_invite_organizers');
        $can_publish_updates = canDo($event_id, $owner_id, 'can_publish_updates');
        
        if ($can_edit_budget && $can_manage_resources && $can_invite_organizers && $can_publish_updates) {
            $test_results['owner_permissions']['status'] = 'PASS';
            $test_results['owner_permissions']['details'] = "Event owner has all permissions for event $event_id";
        } else {
            $test_results['owner_permissions']['status'] = 'FAIL';
            $test_results['owner_permissions']['details'] = "Owner missing permissions: edit_budget=$can_edit_budget, manage_resources=$can_manage_resources, invite=$can_invite_organizers, publish=$can_publish_updates";
        }
    } else {
        $test_results['owner_permissions']['status'] = 'FAIL';
        $test_results['owner_permissions']['details'] = 'No events found in database';
    }
} catch (Exception $e) {
    $test_results['owner_permissions']['status'] = 'FAIL';
    $test_results['owner_permissions']['details'] = 'Error: ' . $e->getMessage();
}

// ============================================
// TEST 4: Test organizer permissions (without explicit grants)
// ============================================
$test_results['organizer_default_permissions'] = [
    'name' => 'Organizer Default Permissions (Deny by Default)',
    'status' => 'PENDING'
];

try {
    // Find an organizer who is NOT the event owner AND has no explicit permissions
    $stmt = $pdo->query("
        SELECT DISTINCT e.id as event_id, eo.user_id as organizer_id 
        FROM events e 
        JOIN event_organizers eo ON e.id = eo.event_id 
        WHERE eo.user_id != e.created_by 
        AND eo.user_id NOT IN (
            SELECT DISTINCT user_id FROM event_permissions WHERE event_id = e.id
        )
        LIMIT 1
    ");
    $organizer = $stmt->fetch();
    
    if ($organizer) {
        $event_id = $organizer['event_id'];
        $organizer_id = $organizer['organizer_id'];
        
        // Test if organizer has no permissions by default
        $can_edit_budget = canDo($event_id, $organizer_id, 'can_edit_budget');
        $can_manage_resources = canDo($event_id, $organizer_id, 'can_manage_resources');
        
        if (!$can_edit_budget && !$can_manage_resources) {
            $test_results['organizer_default_permissions']['status'] = 'PASS';
            $test_results['organizer_default_permissions']['details'] = "Organizer correctly denied permissions by default";
        } else {
            $test_results['organizer_default_permissions']['status'] = 'FAIL';
            $test_results['organizer_default_permissions']['details'] = "Organizer should not have permissions by default but has: edit_budget=$can_edit_budget, manage_resources=$can_manage_resources";
        }
    } else {
        $test_results['organizer_default_permissions']['status'] = 'FAIL';
        $test_results['organizer_default_permissions']['details'] = 'No non-owner organizers found';
    }
} catch (Exception $e) {
    $test_results['organizer_default_permissions']['status'] = 'FAIL';
    $test_results['organizer_default_permissions']['details'] = 'Error: ' . $e->getMessage();
}

// ============================================
// TEST 5: Test explicit permission grants
// ============================================
$test_results['explicit_permissions'] = [
    'name' => 'Explicit Permission Grants',
    'status' => 'PENDING'
];

try {
    // Check if there are any explicit permission grants
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM event_permissions");
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        // Test one explicit permission
        $stmt = $pdo->query("
            SELECT ep.event_id, ep.user_id, ep.permission_name 
            FROM event_permissions ep 
            LIMIT 1
        ");
        $permission = $stmt->fetch();
        
        if ($permission) {
            $has_permission = canDo($permission['event_id'], $permission['user_id'], $permission['permission_name']);
            
            if ($has_permission) {
                $test_results['explicit_permissions']['status'] = 'PASS';
                $test_results['explicit_permissions']['details'] = "Explicit permission '{$permission['permission_name']}' correctly granted";
            } else {
                $test_results['explicit_permissions']['status'] = 'FAIL';
                $test_results['explicit_permissions']['details'] = "Explicit permission '{$permission['permission_name']}' not working";
            }
        } else {
            $test_results['explicit_permissions']['status'] = 'FAIL';
            $test_results['explicit_permissions']['details'] = 'Error reading explicit permission';
        }
    } else {
        $test_results['explicit_permissions']['status'] = 'FAIL';
        $test_results['explicit_permissions']['details'] = 'No explicit permission grants found in database';
    }
} catch (Exception $e) {
    $test_results['explicit_permissions']['status'] = 'FAIL';
    $test_results['explicit_permissions']['details'] = 'Error: ' . $e->getMessage();
}

// ============================================
// TEST 6: Check event_permissions.php exists
// ============================================
$test_results['permissions_page'] = [
    'name' => 'Event Permissions Management Page',
    'status' => 'PENDING'
];

if (file_exists('event_permissions.php')) {
    $content = file_get_contents('event_permissions.php');
    if (strpos($content, 'event_permissions') !== false && strpos($content, 'permission_name') !== false) {
        $test_results['permissions_page']['status'] = 'PASS';
        $test_results['permissions_page']['details'] = 'event_permissions.php exists and contains permission logic';
    } else {
        $test_results['permissions_page']['status'] = 'FAIL';
        $test_results['permissions_page']['details'] = 'event_permissions.php exists but may be missing permission logic';
    }
} else {
    $test_results['permissions_page']['status'] = 'FAIL';
    $test_results['permissions_page']['details'] = 'event_permissions.php does not exist';
}

// ============================================
// TEST 7: Check update_event_permission.php exists
// ============================================
$test_results['permission_update_endpoint'] = [
    'name' => 'Permission Update AJAX Endpoint',
    'status' => 'PENDING'
];

if (file_exists('update_event_permission.php')) {
    $content = file_get_contents('update_event_permission.php');
    if (strpos($content, 'event_permissions') !== false && strpos($content, 'POST') !== false) {
        $test_results['permission_update_endpoint']['status'] = 'PASS';
        $test_results['permission_update_endpoint']['details'] = 'update_event_permission.php exists and handles POST requests';
    } else {
        $test_results['permission_update_endpoint']['status'] = 'FAIL';
        $test_results['permission_update_endpoint']['details'] = 'update_event_permission.php exists but may be missing AJAX logic';
    }
} else {
    $test_results['permission_update_endpoint']['status'] = 'FAIL';
    $test_results['permission_update_endpoint']['details'] = 'update_event_permission.php does not exist';
}

// ============================================
// TEST 8: Test permission enforcement in budget.php
// ============================================
$test_results['budget_permission_enforcement'] = [
    'name' => 'Budget Permission Enforcement',
    'status' => 'PENDING'
];

if (file_exists('budget.php')) {
    $content = file_get_contents('budget.php');
    if (strpos($content, 'canDo') !== false && strpos($content, 'can_edit_budget') !== false) {
        $test_results['budget_permission_enforcement']['status'] = 'PASS';
        $test_results['budget_permission_enforcement']['details'] = 'budget.php enforces can_edit_budget permission';
    } else {
        $test_results['budget_permission_enforcement']['status'] = 'FAIL';
        $test_results['budget_permission_enforcement']['details'] = 'budget.php does not enforce can_edit_budget permission';
    }
} else {
    $test_results['budget_permission_enforcement']['status'] = 'FAIL';
    $test_results['budget_permission_enforcement']['details'] = 'budget.php does not exist';
}

// ============================================
// TEST 9: Test permission enforcement in resources.php
// ============================================
$test_results['resource_permission_enforcement'] = [
    'name' => 'Resource Permission Enforcement',
    'status' => 'PENDING'
];

if (file_exists('resources.php')) {
    $content = file_get_contents('resources.php');
    if (strpos($content, 'canDo') !== false && strpos($content, 'can_manage_resources') !== false) {
        $test_results['resource_permission_enforcement']['status'] = 'PASS';
        $test_results['resource_permission_enforcement']['details'] = 'resources.php enforces can_manage_resources permission';
    } else {
        $test_results['resource_permission_enforcement']['status'] = 'FAIL';
        $test_results['resource_permission_enforcement']['details'] = 'resources.php does not enforce can_manage_resources permission';
    }
} else {
    $test_results['resource_permission_enforcement']['status'] = 'FAIL';
    $test_results['resource_permission_enforcement']['details'] = 'resources.php does not exist';
}

// ============================================
// TEST 10: Check database structure for permissions
// ============================================
$test_results['permission_db_structure'] = [
    'name' => 'Permission Database Structure',
    'status' => 'PENDING'
];

try {
    // Check event_organizers table structure
    $stmt = $pdo->query("DESCRIBE event_organizers");
    $organizer_columns = $stmt->fetchAll();
    
    // Check event_permissions table structure  
    $stmt = $pdo->query("DESCRIBE event_permissions");
    $permission_columns = $stmt->fetchAll();
    
    $organizer_has_required = false;
    $permission_has_required = false;
    
    // Check for required columns
    foreach ($organizer_columns as $col) {
        if ($col['Field'] === 'event_id' || $col['Field'] === 'user_id') {
            $organizer_has_required = true;
        }
    }
    
    foreach ($permission_columns as $col) {
        if ($col['Field'] === 'event_id' || $col['Field'] === 'user_id' || $col['Field'] === 'permission_name') {
            $permission_has_required = true;
        }
    }
    
    if ($organizer_has_required && $permission_has_required) {
        $test_results['permission_db_structure']['status'] = 'PASS';
        $test_results['permission_db_structure']['details'] = 'Permission tables have correct structure';
    } else {
        $test_results['permission_db_structure']['status'] = 'FAIL';
        $test_results['permission_db_structure']['details'] = 'Permission tables missing required columns';
    }
} catch (Exception $e) {
    $test_results['permission_db_structure']['status'] = 'FAIL';
    $test_results['permission_db_structure']['details'] = 'Error checking structure: ' . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>EventUp - Permission System Test Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .test-pass { background-color: #d4edda; }
        .test-fail { background-color: #f8d7da; }
        .test-pending { background-color: #fff3cd; }
    </style>
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4">EventUp - Permission System Test Results</h1>
    
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
                <li><strong>Test Event Owner Access:</strong> Log in as an event creator and verify you can access budget, resources, and permissions management</li>
                <li><strong>Test Organizer Default Deny:</strong> Log in as an organizer without explicit permissions and verify you cannot access budget/resources</li>
                <li><strong>Test Permission Grant:</strong> As event owner, go to Event Permissions and grant permissions to an organizer</li>
                <li><strong>Test Permission Enforcement:</strong> As the granted organizer, verify you now have access to the specific features</li>
                <li><strong>Test Permission Revoke:</strong> As owner, revoke permissions and verify organizer loses access</li>
            </ol>
        </div>
    </div>
</div>
</body>
</html>
