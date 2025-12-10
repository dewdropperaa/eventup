<?php
/**
 * My Tasks Page
 * Allows organizers to view and mark their assigned tasks as complete
 */

session_start();
require_once 'role_check.php';

// Check if user is logged in
requireLogin();

require_once 'database.php';
require_once 'notifications.php';

$pdo = getDatabaseConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $task_id = (int)($_POST['task_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        
        if (!in_array($status, ['pending', 'in_progress', 'completed'])) {
            $error = 'Invalid status.';
        } else {
            try {
                // Verify task is assigned to current user and get event
                $stmt = $pdo->prepare("
                    SELECT id, event_id, status, task_name FROM tasks
                    WHERE id = :task_id AND organizer_id = :user_id
                ");
                
                $stmt->execute([':task_id' => $task_id, ':user_id' => $user_id]);
                $task = $stmt->fetch();
                
                if (!$task) {
                    $error = 'Task not found or you do not have permission to update it.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE tasks SET status = :status WHERE id = :task_id
                    ");
                    
                    $stmt->execute([':status' => $status, ':task_id' => $task_id]);
                    $success = 'Task status updated successfully!';

                    // If task just moved to completed, notify all event admins
                    if ($status === 'completed' && $task['status'] !== 'completed') {
                        $adminsStmt = $pdo->prepare("
                            SELECT user_id FROM event_roles
                            WHERE event_id = :event_id AND role = 'admin'
                        ");
                        $adminsStmt->execute([':event_id' => $task['event_id']]);
                        $admins = $adminsStmt->fetchAll();
                        foreach ($admins as $admin) {
                            $msg = 'Task "' . $task['task_name'] . '" has been marked as completed.';
                            createNotification($admin['user_id'], 'Task completed', $msg, $task['event_id']);
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log("Error updating task: " . $e->getMessage());
                $error = 'An error occurred while updating the task.';
            }
        }
    }
}

// Fetch all tasks assigned to current user, grouped by event
try {
    $stmt = $pdo->prepare("
        SELECT t.id, t.task_name, t.description, t.status, t.due_date, t.created_at,
               e.id as event_id, e.titre as event_title, e.date as event_date
        FROM tasks t
        INNER JOIN events e ON t.event_id = e.id
        WHERE t.organizer_id = :user_id
        ORDER BY e.date DESC, t.due_date ASC
    ");
    
    $stmt->execute([':user_id' => $user_id]);
    $all_tasks = $stmt->fetchAll();
    
    // Group tasks by event
    $tasks_by_event = [];
    foreach ($all_tasks as $task) {
        $event_id = $task['event_id'];
        if (!isset($tasks_by_event[$event_id])) {
            $tasks_by_event[$event_id] = [
                'event_title' => $task['event_title'],
                'event_date' => $task['event_date'],
                'tasks' => []
            ];
        }
        $tasks_by_event[$event_id]['tasks'][] = $task;
    }
} catch (PDOException $e) {
    error_log("Error fetching tasks: " . $e->getMessage());
    $tasks_by_event = [];
}

// Get task statistics
$stats = [
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'total' => count($all_tasks)
];

foreach ($all_tasks as $task) {
    $stats[$task['status']]++;
}

include 'header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2>My Tasks</h2>
        <p class="text-muted">View and manage your assigned tasks</p>
    </div>
    <div class="col-md-4 text-end">
        <a href="organizer_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle"></i>
        <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i>
        <strong>Success!</strong> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-muted">Total Tasks</h5>
                <h2 class="text-primary"><?php echo $stats['total']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-muted">Pending</h5>
                <h2 class="text-warning"><?php echo $stats['pending']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-muted">In Progress</h5>
                <h2 class="text-info"><?php echo $stats['in_progress']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-muted">Completed</h5>
                <h2 class="text-success"><?php echo $stats['completed']; ?></h2>
            </div>
        </div>
    </div>
</div>

<?php if (empty($tasks_by_event)): ?>
    <div class="alert alert-info" role="alert">
        <strong>No tasks assigned!</strong> You don't have any tasks assigned yet.
    </div>
<?php else: ?>
    <?php foreach ($tasks_by_event as $event_id => $event_data): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-0"><?php echo htmlspecialchars($event_data['event_title']); ?></h5>
                        <small class="text-light">
                            Event Date: <?php echo date('F j, Y \a\t g:i A', strtotime($event_data['event_date'])); ?>
                        </small>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-light text-dark">
                            <?php echo count($event_data['tasks']); ?> task<?php echo count($event_data['tasks']) !== 1 ? 's' : ''; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($event_data['tasks'] as $task): ?>
                        <div class="list-group-item">
                            <div class="row align-items-start">
                                <div class="col-md-7">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($task['task_name']); ?></h6>
                                    <?php if ($task['description']): ?>
                                        <p class="mb-2 text-muted small"><?php echo htmlspecialchars($task['description']); ?></p>
                                    <?php endif; ?>
                                    <p class="mb-0 small text-muted">
                                        <strong>Due:</strong> <?php echo date('M d, Y g:i A', strtotime($task['due_date'])); ?>
                                        <?php 
                                            $due_date = new DateTime($task['due_date']);
                                            $now = new DateTime();
                                            if ($due_date < $now && $task['status'] !== 'completed') {
                                                echo ' <span class="badge bg-danger">Overdue</span>';
                                            }
                                        ?>
                                    </p>
                                </div>
                                
                                <div class="col-md-5">
                                    <div class="d-flex gap-2 align-items-center justify-content-end">
                                        <!-- Status Badge -->
                                        <div>
                                            <?php
                                                $badge_class = match($task['status']) {
                                                    'pending' => 'bg-warning text-dark',
                                                    'in_progress' => 'bg-info text-white',
                                                    'completed' => 'bg-success text-white',
                                                    default => 'bg-secondary'
                                                };
                                                $status_label = match($task['status']) {
                                                    'pending' => 'Pending',
                                                    'in_progress' => 'In Progress',
                                                    'completed' => 'Completed',
                                                    default => ucfirst($task['status'])
                                                };
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo $status_label; ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Status Update Form -->
                                        <form method="POST" action="my_tasks.php" class="d-inline">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                            <select class="form-select form-select-sm" name="status" style="width: auto;" onchange="this.form.submit()">
                                                <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            </select>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include 'footer.php'; ?>
