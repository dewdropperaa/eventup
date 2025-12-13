<?php
require 'database.php';

echo "=== Permission System Debug ===\n\n";

$pdo = getDatabaseConnection();

// Check event_permissions table structure
echo "1. event_permissions table structure:\n";
try {
    $stmt = $pdo->query('DESCRIBE event_permissions');
    while ($row = $stmt->fetch()) {
        echo "   " . $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n2. Current permissions in database:\n";
try {
    $stmt = $pdo->query('SELECT * FROM event_permissions LIMIT 10');
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($permissions)) {
        echo "   No permissions found in database\n";
    } else {
        foreach ($permissions as $perm) {
            echo "   Event {$perm['event_id']}, User {$perm['user_id']}, {$perm['permission_name']} = " . ($perm['is_allowed'] ? 'ALLOWED' : 'DENIED') . "\n";
        }
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n3. Test canDo() function:\n";
// Test with sample data
$test_event_id = 1; // Adjust this to a real event ID
$test_user_id = 1;  // Adjust this to a real user ID

echo "   Testing canDo({$test_event_id}, {$test_user_id}, 'can_edit_budget'): ";
require_once 'role_check.php';
$result = canDo($test_event_id, $test_user_id, 'can_edit_budget');
echo $result ? 'TRUE' : 'FALSE';
echo "\n";

echo "\n4. Check if user is event owner:\n";
try {
    $stmt = $pdo->prepare('SELECT id, titre, created_by FROM events WHERE id = ?');
    $stmt->execute([$test_event_id]);
    $event = $stmt->fetch();
    if ($event) {
        echo "   Event {$test_event_id} ({$event['titre']}) is owned by user {$event['created_by']}\n";
        echo "   Test user {$test_user_id} is " . ($event['created_by'] == $test_user_id ? 'OWNER' : 'NOT OWNER') . "\n";
    } else {
        echo "   Event {$test_event_id} not found\n";
    }
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Debug Complete ===\n";
?>
