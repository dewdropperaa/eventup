<?php
session_start();
require 'database.php';
require_once 'role_check.php';

// Test if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Please log in first");
}

$userId = $_SESSION['user_id'];
$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

echo "<h2>Debug Owner Access</h2>";
echo "<p>User ID: $userId</p>";
echo "<p>Event ID: $eventId</p>";

if ($eventId <= 0) {
    die("Invalid event ID");
}

try {
    $pdo = getDatabaseConnection();
    
    // Get event details
    $stmt = $pdo->prepare('SELECT id, titre, created_by FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event) {
        die("Event not found");
    }
    
    echo "<h3>Event Details</h3>";
    echo "<p>Event Title: " . htmlspecialchars($event['titre']) . "</p>";
    echo "<p>Event Created By: " . $event['created_by'] . "</p>";
    echo "<p>Current User ID: $userId</p>";
    echo "<p>Is Event Owner: " . ($event['created_by'] == $userId ? "YES" : "NO") . "</p>";
    
    echo "<h3>Permission Checks</h3>";
    
    // Test canDo function directly
    $canManageResources = canDo($eventId, $userId, 'can_manage_resources');
    $canEditBudget = canDo($eventId, $userId, 'can_edit_budget');
    $canInviteOrganizers = canDo($eventId, $userId, 'can_invite_organizers');
    $canPublishUpdates = canDo($eventId, $userId, 'can_publish_updates');
    
    echo "<p>can_manage_resources: " . ($canManageResources ? "YES" : "NO") . "</p>";
    echo "<p>can_edit_budget: " . ($canEditBudget ? "YES" : "NO") . "</p>";
    echo "<p>can_invite_organizers: " . ($canInviteOrganizers ? "YES" : "NO") . "</p>";
    echo "<p>can_publish_updates: " . ($canPublishUpdates ? "YES" : "NO") . "</p>";
    
    // Test role checks
    $isEventAdmin = isEventAdmin($userId, $eventId);
    $isEventOrganizer = isEventOrganizer($userId, $eventId);
    
    echo "<h3>Role Checks</h3>";
    echo "<p>isEventAdmin: " . ($isEventAdmin ? "YES" : "NO") . "</p>";
    echo "<p>isEventOrganizer: " . ($isEventOrganizer ? "YES" : "NO") . "</p>";
    
    // Check permissions table directly
    echo "<h3>Permissions Table</h3>";
    $stmt = $pdo->prepare('SELECT * FROM event_permissions WHERE event_id = ? AND user_id = ?');
    $stmt->execute([$eventId, $userId]);
    $permissions = $stmt->fetchAll();
    
    if (empty($permissions)) {
        echo "<p>No explicit permissions found in event_permissions table</p>";
    } else {
        foreach ($permissions as $perm) {
            echo "<p>Permission: " . htmlspecialchars($perm['permission_name']) . " = " . $perm['granted'] . "</p>";
        }
    }
    
    // Check event_organizers table
    echo "<h3>Event Organizers Table</h3>";
    $stmt = $pdo->prepare('SELECT * FROM event_organizers WHERE event_id = ? AND user_id = ?');
    $stmt->execute([$eventId, $userId]);
    $organizerRecord = $stmt->fetch();
    
    if ($organizerRecord) {
        echo "<p>Found in event_organizers table:</p>";
        echo "<pre>" . print_r($organizerRecord, true) . "</pre>";
    } else {
        echo "<p>No record found in event_organizers table</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
