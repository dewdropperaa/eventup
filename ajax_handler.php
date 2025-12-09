<?php
session_start();
require_once 'role_check.php';
require_once 'database.php';
require_once 'notifications.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$pdo = getDatabaseConnection();
$user_id = $_SESSION['user_id'];
$event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : (isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0);
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Basic validation
if (!$action) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// For get_notifications, we don't need event_id or admin check
if ($action !== 'get_notifications') {
    if (!$event_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }
    
    // Check if user is admin of this event
    if (!isEventAdmin($user_id, $event_id)) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action.']);
        exit;
    }
}

switch ($action) {
    case 'create_task':
        $task_name = trim($_POST['task_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $organizer_id = (int)($_POST['organizer_id'] ?? 0);
        $due_date = trim($_POST['due_date'] ?? '');

        if (empty($task_name) || empty($organizer_id) || empty($due_date)) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO tasks (event_id, organizer_id, task_name, description, due_date) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$event_id, $organizer_id, $task_name, $description, $due_date]);
            $task_id = $pdo->lastInsertId();

            // Notify assigned organizer
            $msg = 'You have been assigned a new task "' . $task_name . '".';
            createNotification($organizer_id, 'Task assigned', $msg, $event_id);

            echo json_encode(['success' => true, 'message' => 'Task created successfully.', 'task_id' => $task_id]);
        } catch (PDOException $e) {
            error_log('Task creation failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'An error occurred while creating the task.']);
        }
        break;

    case 'update_task_status':
        $task_id = (int)($_POST['task_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (!$task_id || !in_array($status, ['pending', 'in_progress', 'completed'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid task ID or status.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('UPDATE tasks SET status = ? WHERE id = ? AND event_id = ?');
            $stmt->execute([$status, $task_id, $event_id]);
            echo json_encode(['success' => true, 'message' => 'Task status updated.']);
        } catch (PDOException $e) {
            error_log('Task update failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'An error occurred while updating the task.']);
        }
        break;

    case 'delete_task':
        $task_id = (int)($_POST['task_id'] ?? 0);

        if (!$task_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid task ID.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = ? AND event_id = ?');
            $stmt->execute([$task_id, $event_id]);
            echo json_encode(['success' => true, 'message' => 'Task deleted successfully.']);
        } catch (PDOException $e) {
            error_log('Task deletion failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the task.']);
        }
        break;

    case 'get_notifications':
        $notifications = getUnreadNotifications($user_id, 7);
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}
