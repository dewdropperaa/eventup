<?php
/**
 * Task Management Page
 * Allows event admins to create and manage tasks for organizers
 */

session_start();
require_once 'role_check.php';

// Check if user is logged in
requireLogin();

require_once 'database.php';

$pdo = getDatabaseConnection();
$user_id = $_SESSION['user_id'];
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$error = '';
$success = '';

// Validate event_id
if (!$event_id) {
    header("Location: organizer_dashboard.php");
    exit();
}

// Check if user is admin of this event
requireEventAdmin($user_id, $event_id);

// Fetch event details
try {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = :event_id");
    $stmt->execute([':event_id' => $event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        header("Location: organizer_dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching event: " . $e->getMessage());
    header("Location: organizer_dashboard.php");
    exit();
}


// Fetch all organizers for this event
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.nom, u.email
        FROM event_roles er
        INNER JOIN users u ON er.user_id = u.id
        WHERE er.event_id = :event_id AND er.role IN ('admin', 'organizer')
        ORDER BY u.nom ASC
    ");
    
    $stmt->execute([':event_id' => $event_id]);
    $organizers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching organizers: " . $e->getMessage());
    $organizers = [];
}

// Fetch all tasks for this event
try {
    $stmt = $pdo->prepare("
        SELECT t.id, t.task_name, t.description, t.status, t.due_date, t.created_at,
               u.nom as organizer_name, u.email as organizer_email
        FROM tasks t
        INNER JOIN users u ON t.organizer_id = u.id
        WHERE t.event_id = :event_id
        ORDER BY t.due_date ASC, t.status ASC
    ");
    
    $stmt->execute([':event_id' => $event_id]);
    $tasks = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching tasks: " . $e->getMessage());
    $tasks = [];
}

include 'header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2>Manage Tasks</h2>
        <p class="text-muted">Event: <strong><?php echo htmlspecialchars($event['titre']); ?></strong></p>
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

<div class="row">
    <!-- Create Task Form -->
    <div class="col-md-5 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Create New Task</h5>
            </div>
            <div class="card-body">
                <?php if (empty($organizers)): ?>
                    <div class="alert alert-warning" role="alert">
                        No organizers assigned to this event. Please assign organizers first.
                    </div>
                <?php else: ?>
                    <form id="create-task-form" novalidate>
                        <input type="hidden" name="action" value="create_task">
                        
                        <div class="mb-3">
                            <label for="task_name" class="form-label">Task Name <span class="text-danger">*</span></label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="task_name" 
                                name="task_name" 
                                placeholder="e.g., Setup registration desk"
                                required
                            >
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea 
                                class="form-control" 
                                id="description" 
                                name="description" 
                                rows="3"
                                placeholder="Describe the task details..."
                            ></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="organizer_id" class="form-label">Assign to Organizer <span class="text-danger">*</span></label>
                            <select class="form-select" id="organizer_id" name="organizer_id" required>
                                <option value="">-- Select Organizer --</option>
                                <?php foreach ($organizers as $org): ?>
                                    <option value="<?php echo $org['id']; ?>">
                                        <?php echo htmlspecialchars($org['nom']); ?> (<?php echo htmlspecialchars($org['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="due_date" class="form-label">Due Date <span class="text-danger">*</span></label>
                            <input 
                                type="datetime-local" 
                                class="form-control" 
                                id="due_date" 
                                name="due_date"
                                required
                            >
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Create Task</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Tasks List -->
    <div class="col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Tasks (<?php echo count($tasks); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($tasks)): ?>
                    <p class="text-muted text-center py-4">No tasks yet. Create one to get started!</p>
                <?php else: ?>
                    <div class="list-group list-group-flush" id="task-list">
                        <?php foreach ($tasks as $task): ?>
                            <div class="list-group-item" id="task-<?php echo $task['id']; ?>">
                                <div class="row align-items-start">
                                    <div class="col-md-8">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($task['task_name']); ?></h6>
                                        <?php if ($task['description']): ?>
                                            <p class="mb-2 text-muted small"><?php echo htmlspecialchars($task['description']); ?></p>
                                        <?php endif; ?>
                                        <p class="mb-1 small">
                                            <strong>Assigned to:</strong> <?php echo htmlspecialchars($task['organizer_name']); ?>
                                        </p>
                                        <p class="mb-0 small text-muted">
                                            <strong>Due:</strong> <?php echo date('M d, Y g:i A', strtotime($task['due_date'])); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-2">
                                            <select class="form-select form-select-sm task-status-select" data-task-id="<?php echo $task['id']; ?>">
                                                <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            </select>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger w-100 delete-task-btn" data-task-id="<?php echo $task['id']; ?>">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<!-- Task Assignment Confirmation Modal -->
<div class="modal fade" id="assignTaskModal" tabindex="-1" aria-labelledby="assignTaskLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="assignTaskLabel">Confirm Task Assignment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Assign task <strong id="assignTaskNameDisplay"></strong> to <strong id="assignOrganizerDisplay"></strong>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="confirmAssignBtn">Confirm Assignment</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const createTaskForm = document.getElementById('create-task-form');
    if (createTaskForm) {
        createTaskForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('event_id', <?php echo $event_id; ?>);

            fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Task created successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An unexpected error occurred.');
            });
        });
    }
});
</script>

<?php include 'footer.php'; ?>
