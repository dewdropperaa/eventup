<?php
require_once 'database.php';

echo "<h2>Debug Event Organizers Table</h2>";

$pdo = getDatabaseConnection();

// Check if event_organizers table exists
echo "<h3>1. Checking event_organizers table structure:</h3>";
try {
    $stmt = $pdo->prepare("DESCRIBE event_organizers");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color: red;'>ERROR: " . $e->getMessage() . "</p>";
}

// Check if event_roles table exists
echo "<h3>2. Checking event_roles table structure:</h3>";
try {
    $stmt = $pdo->prepare("DESCRIBE event_roles");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color: red;'>ERROR: " . $e->getMessage() . "</p>";
}

// Check recent events and their organizer assignments
echo "<h3>3. Recent events and organizer assignments:</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT e.id, e.titre, e.created_by, e.created_at as date_creation,
               er.role as event_role,
               eo.role as organizer_role,
               eo.id as organizer_id
        FROM events e
        LEFT JOIN event_roles er ON e.id = er.event_id AND er.user_id = e.created_by
        LEFT JOIN event_organizers eo ON e.id = eo.event_id AND eo.user_id = e.created_by
        ORDER BY e.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'><tr><th>Event ID</th><th>Title</th><th>Created By</th><th>Event Role</th><th>Organizer Role</th><th>Organizer ID</th></tr>";
    foreach ($events as $event) {
        $event_role = $event['event_role'] ?? 'NULL';
        $organizer_role = $event['organizer_role'] ?? 'NULL';
        $organizer_id = $event['organizer_id'] ?? 'NULL';
        
        echo "<tr><td>{$event['id']}</td><td>{$event['titre']}</td><td>{$event['created_by']}</td><td>$event_role</td><td>$organizer_role</td><td>$organizer_id</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color: red;'>ERROR: " . $e->getMessage() . "</p>";
}

// Test permission check for a recent event
echo "<h3>4. Testing permission functions:</h3>";
if (!empty($events)) {
    $test_event = $events[0];
    $event_id = $test_event['id'];
    $user_id = $test_event['created_by'];
    
    echo "<p>Testing Event ID: $event_id, User ID: $user_id</p>";
    
    // Test isEventAdmin
    try {
        require_once 'role_check.php';
        $is_admin = isEventAdmin($user_id, $event_id);
        echo "<p>isEventAdmin(): " . ($is_admin ? "TRUE" : "FALSE") . "</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>isEventAdmin ERROR: " . $e->getMessage() . "</p>";
    }
    
    // Test canDo function
    try {
        $can_edit_budget = canDo($event_id, $user_id, 'can_edit_budget');
        echo "<p>canDo(can_edit_budget): " . ($can_edit_budget ? "TRUE" : "FALSE") . "</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>canDo ERROR: " . $e->getMessage() . "</p>";
    }
}

echo "<h3>5. Check if event_organizers table needs to be created:</h3>";
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'event_organizers'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        echo "<p style='color: red;'>event_organizers table does NOT exist! Need to run create_permissions_tables.sql</p>";
        echo "<p><a href='create_permissions_tables.sql'>Click here to view the SQL file</a></p>";
    } else {
        echo "<p style='color: green;'>event_organizers table exists</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>ERROR checking table existence: " . $e->getMessage() . "</p>";
}
?>
