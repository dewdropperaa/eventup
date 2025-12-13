<?php
/**
 * Test script to debug permissions system
 */

require_once 'database.php';
require_once 'role_check.php';

session_start();

// Test database structure
echo "<h1>EventUp Permissions Debug</h1>";

echo "<h2>1. Database Structure</h2>";
try {
    $pdo = getDatabaseConnection();
    
    // Check event_permissions table
    $stmt = $pdo->query("DESCRIBE event_permissions");
    echo "<h3>event_permissions table structure:</h3>";
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
    }
    echo "</table>";
    
    // Check event_organizers table
    $stmt = $pdo->query("DESCRIBE event_organizers");
    echo "<h3>event_organizers table structure:</h3>";
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}

echo "<h2>2. Current Permissions Data</h2>";
try {
    $pdo = getDatabaseConnection();
    
    // Show all permissions
    $stmt = $pdo->query("SELECT * FROM event_permissions ORDER BY event_id, user_id");
    $permissions = $stmt->fetchAll();
    
    if (empty($permissions)) {
        echo "<p>No permissions found in database.</p>";
    } else {
        echo "<table border='1'><tr><th>Event ID</th><th>User ID</th><th>Permission</th><th>Allowed</th></tr>";
        foreach ($permissions as $perm) {
            echo "<tr><td>{$perm['event_id']}</td><td>{$perm['user_id']}</td><td>{$perm['permission_name']}</td><td>{$perm['is_allowed']}</td></tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error fetching permissions: " . $e->getMessage() . "</p>";
}

echo "<h2>3. Test canDo() Function</h2>";
if (isset($_SESSION['user_id'])) {
    echo "<p>Logged in user ID: " . $_SESSION['user_id'] . "</p>";
    
    // Get events for testing
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("SELECT id, titre, created_by FROM events ORDER BY id LIMIT 5");
        $stmt->execute();
        $events = $stmt->fetchAll();
        
        foreach ($events as $event) {
            echo "<h4>Event: {$event['titre']} (ID: {$event['id']})</h4>";
            echo "<p>Created by: {$event['created_by']}</p>";
            
            $permissions = ['can_edit_budget', 'can_manage_resources', 'can_invite_organizers', 'can_publish_updates'];
            foreach ($permissions as $perm) {
                $result = canDo($event['id'], $_SESSION['user_id'], $perm);
                echo "<p>$perm: " . ($result ? "<span style='color: green;'>ALLOWED</span>" : "<span style='color: red;'>DENIED</span>") . "</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error testing permissions: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: orange;'>Not logged in. <a href='login.php'>Login</a> to test permissions.</p>";
}

echo "<h2>4. Test Permission Update</h2>";
if (isset($_GET['test_update']) && isset($_SESSION['user_id'])) {
    $eventId = (int)($_GET['event_id'] ?? 1);
    $userId = (int)($_GET['user_id'] ?? $_SESSION['user_id']);
    $permission = $_GET['permission'] ?? 'can_edit_budget';
    $allowed = (int)($_GET['allowed'] ?? 1);
    
    echo "<p>Testing update: Event $eventId, User $userId, Permission $permission, Allowed $allowed</p>";
    
    try {
        $pdo = getDatabaseConnection();
        
        // Verify user is event owner
        $stmt = $pdo->prepare('SELECT created_by FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();
        
        if (!$event) {
            echo "<p style='color: red;'>Event not found</p>";
        } elseif ($event['created_by'] != $_SESSION['user_id']) {
            echo "<p style='color: red;'>You are not the owner of this event</p>";
        } else {
            // Update permission
            $sql = "INSERT INTO event_permissions (event_id, user_id, permission_name, is_allowed) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$eventId, $userId, $permission, $allowed]);
            
            echo "<p style='color: green;'>Permission updated successfully!</p>";
            
            // Test the permission
            $result = canDo($eventId, $userId, $permission);
            echo "<p>After update, canDo() returns: " . ($result ? "<span style='color: green;'>ALLOWED</span>" : "<span style='color: red;'>DENIED</span>") . "</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>To test permission updates, use: ?test_update=1&event_id=1&user_id=2&permission=can_edit_budget&allowed=1</p>";
}

echo "<h2>5. Event Organizers</h2>";
try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->query("SELECT eo.*, u.nom, u.email FROM event_organizers eo JOIN users u ON eo.user_id = u.id ORDER BY eo.event_id, u.nom");
    $organizers = $stmt->fetchAll();
    
    if (empty($organizers)) {
        echo "<p>No organizers found in database.</p>";
    } else {
        echo "<table border='1'><tr><th>Event ID</th><th>User ID</th><th>Name</th><th>Email</th><th>Role</th></tr>";
        foreach ($organizers as $org) {
            echo "<tr><td>{$org['event_id']}</td><td>{$org['user_id']}</td><td>{$org['nom']}</td><td>{$org['email']}</td><td>{$org['role']}</td></tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error fetching organizers: " . $e->getMessage() . "</p>";
}
?>
