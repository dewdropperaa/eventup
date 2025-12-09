<?php
session_start();
header('Content-Type: application/json');

require 'database.php';
require 'role_check.php';

$response = ['status' => 'error', 'message' => 'An unknown error occurred.'];

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Authentication required.';
    echo json_encode($response);
    exit;
}

// Basic validation of POST data
if (!isset($_POST['event_id'], $_POST['user_id'], $_POST['permission_name'], $_POST['is_allowed'])) {
    $response['message'] = 'Invalid request. Missing parameters.';
    echo json_encode($response);
    exit;
}

$eventId = (int)$_POST['event_id'];
$userId = (int)$_POST['user_id'];
$permissionName = trim($_POST['permission_name']);
$isAllowed = (int)$_POST['is_allowed'];

if ($eventId <= 0 || $userId <= 0 || empty($permissionName)) {
    $response['message'] = 'Invalid data provided.';
    echo json_encode($response);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // Security Check: Verify that the current user is the owner of the event
    $stmt = $pdo->prepare('SELECT created_by FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();

    if (!$event || $event['created_by'] != $_SESSION['user_id']) {
        $response['message'] = 'Unauthorized. You are not the owner of this event.';
        echo json_encode($response);
        exit;
    }

    // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both new and existing permissions
    $sql = "
        INSERT INTO event_permissions (event_id, user_id, permission_name, is_allowed)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed);
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$eventId, $userId, $permissionName, $isAllowed]);

    if ($stmt->rowCount() > 0) {
        $response['status'] = 'success';
        $response['message'] = 'Permission updated successfully.';
    } else {
        // This can happen if the value is the same as before, which is not an error.
        $response['status'] = 'success';
        $response['message'] = 'Permission state was already up to date.';
    }

} catch (PDOException $e) {
    error_log('Permission update error: ' . $e->getMessage());
    $response['message'] = 'Database error occurred while updating permission.';
} catch (Exception $e) {
    error_log('Generic error in update_event_permission: ' . $e->getMessage());
    $response['message'] = 'A server error occurred.';
}

echo json_encode($response);
