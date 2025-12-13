<?php
/**
 * Page Mes Tâches
 * Permet aux organisateurs de voir et marquer leurs tâches assignées comme terminées
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
            $error = 'Statut invalide.';
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
                    $error = 'Tâche non trouvée ou vous n\'avez pas la permission de la mettre à jour.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE tasks SET status = :status WHERE id = :task_id
                    ");
                    
                    $stmt->execute([':status' => $status, ':task_id' => $task_id]);
                    $success = 'Statut de la tâche mis à jour avec succès !';

                    // If task just moved to completed, notify all event admins
                    if ($status === 'completed' && $task['status'] !== 'completed') {
                        $adminsStmt = $pdo->prepare("
                            SELECT user_id FROM event_roles
                            WHERE event_id = :event_id AND role = 'admin'
                        ");
                        $adminsStmt->execute([':event_id' => $task['event_id']]);
                        $admins = $adminsStmt->fetchAll();
                        foreach ($admins as $admin) {
                            $msg = 'La tâche "' . $task['task_name'] . '" a été marquée comme terminée.';
                            createNotification($admin['user_id'], 'Tâche terminée', $msg, $task['event_id']);
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log("Error updating task: " . $e->getMessage());
                $error = 'Une erreur est survenue lors de la mise à jour de la tâche.';
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

// Set variables for header
$useAppHeader = true;
$activeNav = 'my_tasks';
$pageTitle = 'EventUp - Mes Tâches';

require_once 'header.php';
?>
<style>
/* ===========================
   Custom CSS Variables
   ============================ */
:root {
    --primary-orange: #D94A00;
    --primary-teal: #1B5E52;
    --light-orange: #ff6b2c;
    --light-teal: #267061;
    --warning-yellow: #FFD700;
    --info-blue: #4A90E2;
    --success-green: #2ed573;
    --text-dark: #2c3e50;
    --text-muted: #657786;
    --bg-light: #f5f7fa;
    --border-color: #e1e8ed;
}

/* ===========================
   Main Content
   ============================ */
.main-content {
    padding-top: 32px;
    padding-bottom: 48px;
}

.page-title {
    font-size: 32px;
    font-weight: 700;
    color: var(--primary-teal);
    margin-bottom: 8px;
}

.page-subtitle {
    color: var(--text-muted);
    font-size: 16px;
    margin-bottom: 32px;
}

/* ===========================
   Stats Cards
   ============================ */
.stats-card {
    background: white;
    border-radius: 16px;
    padding: 32px;
    transition: all 0.3s ease;
    border: 1px solid var(--border-color);
    height: 100%;
    position: relative;
    overflow: hidden;
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-orange), var(--light-orange));
}

.stats-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.1);
}

.stats-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
    margin-bottom: 20px;
}

.orange-gradient {
    background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
}

.teal-gradient {
    background: linear-gradient(135deg, var(--primary-teal), var(--light-teal));
}

.blue-gradient {
    background: linear-gradient(135deg, #4A90E2, #357ABD);
}

.green-gradient {
    background: linear-gradient(135deg, #2ed573, #26de81);
}

.yellow-gradient {
    background: linear-gradient(135deg, #FFD700, #FFC107);
}

.stats-number {
    font-size: 36px;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 8px;
}

.stats-label {
    color: var(--text-muted);
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ===========================
   Task Cards
   ============================ */
.task-card {
    background: white;
    border-radius: 16px;
    transition: all 0.3s ease;
    border: 1px solid var(--border-color);
    overflow: hidden;
    margin-bottom: 24px;
}

.task-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
}

.task-header {
    background: linear-gradient(135deg, var(--primary-teal), var(--light-teal));
    color: white;
    padding: 24px;
    position: relative;
}

.task-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-orange), var(--light-orange));
}

.task-title {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 8px;
}

.task-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.task-meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    opacity: 0.9;
}

.task-body {
    padding: 24px;
}

.task-item {
    background: var(--bg-light);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    border-left: 4px solid transparent;
    transition: all 0.3s ease;
}

.task-item:hover {
    background: white;
    border-left-color: var(--primary-orange);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.task-item:last-child {
    margin-bottom: 0;
}

.task-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 8px;
}

.task-description {
    color: var(--text-muted);
    font-size: 14px;
    margin-bottom: 12px;
    line-height: 1.5;
}

.task-due {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--text-muted);
    margin-bottom: 12px;
}

.task-controls {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

/* ===========================
   Status Badges
   ============================ */
.status-pending {
    background: rgba(255, 193, 7, 0.15);
    color: #856404;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.status-progress {
    background: rgba(74, 144, 226, 0.15);
    color: #004085;
    border: 1px solid rgba(74, 144, 226, 0.3);
}

.status-completed {
    background: rgba(46, 213, 115, 0.15);
    color: #155724;
    border: 1px solid rgba(46, 213, 115, 0.3);
}

.overdue-badge {
    background: rgba(220, 53, 69, 0.15);
    color: #721c24;
    border: 1px solid rgba(220, 53, 69, 0.3);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

/* ===========================
   Buttons
   ============================ */
.btn-primary {
    background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: linear-gradient(135deg, var(--light-orange), var(--primary-orange));
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(217, 74, 0, 0.3);
}

.btn-outline-primary {
    color: var(--primary-orange);
    border-color: var(--primary-orange);
    border-radius: 10px;
    padding: 8px 16px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-outline-primary:hover {
    background: var(--primary-orange);
    border-color: var(--primary-orange);
    color: white;
}

.btn-sm {
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
}

.form-select {
    border-radius: 8px;
    border: 1px solid var(--border-color);
    padding: 8px 12px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-select:focus {
    border-color: var(--primary-orange);
    box-shadow: 0 0 0 0.2rem rgba(217, 74, 0, 0.25);
}

/* ===========================
   Empty State
   ============================ */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 16px;
    border: 1px solid var(--border-color);
}

.empty-state-icon {
    font-size: 80px;
    color: var(--text-muted);
    margin-bottom: 20px;
}

.empty-state h3 {
    color: var(--text-muted);
    margin-bottom: 12px;
}

.empty-state p {
    color: var(--text-muted);
    margin-bottom: 24px;
}

/* ===========================
   Responsive Design
   ============================ */
@media (max-width: 767px) {
    .page-title {
        font-size: 24px;
    }
    
    .stats-card {
        padding: 24px;
    }
    
    .stats-number {
        font-size: 28px;
    }
    
    .task-header {
        padding: 20px;
    }
    
    .task-body {
        padding: 20px;
    }
    
    .task-controls {
        flex-direction: column;
        align-items: stretch;
    }
}

@media (max-width: 575px) {
    .main-content {
        padding: 16px;
    }
    
    .stats-card {
        padding: 20px;
        margin-bottom: 16px;
    }
    
    .task-card {
        margin-bottom: 16px;
    }
    
    .task-meta {
        flex-direction: column;
        gap: 8px;
    }
}
</style>

<!-- Main Content -->
<main class="container-fluid main-content">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="page-title">Mes Tâches</h1>
                <p class="page-subtitle">Voir et gérer vos tâches assignées</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="organizer_dashboard.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-2"></i>Retour au Tableau de Bord
                </a>
            </div>
        </div>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i>
                <strong>Succès !</strong> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i>
                <strong>Erreur !</strong> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon orange-gradient">
                        <i class="bi bi-list-task"></i>
                    </div>
                    <div class="stats-number"><?php echo (int) $stats['total']; ?></div>
                    <div class="stats-label">Total des Tâches</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon yellow-gradient">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stats-number"><?php echo (int) $stats['pending']; ?></div>
                    <div class="stats-label">En Attente</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon blue-gradient">
                        <i class="bi bi-arrow-repeat"></i>
                    </div>
                    <div class="stats-number"><?php echo (int) $stats['in_progress']; ?></div>
                    <div class="stats-label">En Cours</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon green-gradient">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stats-number"><?php echo (int) $stats['completed']; ?></div>
                    <div class="stats-label">Terminées</div>
                </div>
            </div>
        </div>

        <!-- Tasks List -->
        <?php if (empty($tasks_by_event)): ?>
            <div class="empty-state">
                <i class="bi bi-clipboard-x empty-state-icon"></i>
                <h3>Aucune tâche assignée !</h3>
                <p>Vous n\'avez aucune tâche assignée pour le moment.</p>
                <a href="organizer_dashboard.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-2"></i>Retour au Tableau de Bord
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($tasks_by_event as $event_id => $event_data): ?>
                <div class="task-card">
                    <div class="task-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="task-title"><?php echo htmlspecialchars($event_data['event_title']); ?></h3>
                                <div class="task-meta">
                                    <div class="task-meta-item">
                                        <i class="bi bi-calendar"></i>
                                        <?php echo date('F j, Y \a\t g:i A', strtotime($event_data['event_date'])); ?>
                                    </div>
                                    <div class="task-meta-item">
                                        <i class="bi bi-list-check"></i>
                                        <?php echo count($event_data['tasks']); ?> tâche<?php echo count($event_data['tasks']) !== 1 ? 's' : ''; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="task-body">
                        <?php foreach ($event_data['tasks'] as $task): ?>
                            <div class="task-item">
                                <div class="task-name"><?php echo htmlspecialchars($task['task_name']); ?></div>
                                
                                <?php if ($task['description']): ?>
                                    <div class="task-description"><?php echo htmlspecialchars($task['description']); ?></div>
                                <?php endif; ?>
                                
                                <div class="task-due">
                                    <i class="bi bi-clock"></i>
                                    <strong>Échéance :</strong> <?php echo date('M d, Y g:i A', strtotime($task['due_date'])); ?>
                                    <?php 
                                        $due_date = new DateTime($task['due_date']);
                                        $now = new DateTime();
                                        if ($due_date < $now && $task['status'] !== 'completed') {
                                            echo ' <span class="badge overdue-badge">En Retard</span>';
                                        }
                                    ?>
                                </div>
                                
                                <div class="task-controls">
                                    <div>
                                        <?php
                                            $badge_class = match($task['status']) {
                                                'pending' => 'status-pending',
                                                'in_progress' => 'status-progress',
                                                'completed' => 'status-completed',
                                                default => 'bg-secondary'
                                            };
                                            $status_label = match($task['status']) {
                                                'pending' => 'En Attente',
                                                'in_progress' => 'En Cours',
                                                'completed' => 'Terminée',
                                                default => ucfirst($task['status'])
                                            };
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </div>
                                    
                                    <form method="POST" action="my_tasks.php" class="d-inline">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <select class="form-select form-select-sm" name="status" style="width: auto;" onchange="this.form.submit()">
                                            <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>En Attente</option>
                                            <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>En Cours</option>
                                            <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Terminée</option>
                                        </select>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

<?php include 'footer.php'; ?>
