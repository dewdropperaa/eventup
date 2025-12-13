<?php
/**
 * Task Management Page
 * Allows event admins to create and manage tasks for organizers
 */

session_start();
require_once 'role_check.php';
require_once 'notifications.php';

requireLogin();

require_once 'database.php';

$pdo = getDatabaseConnection();
$user_id = $_SESSION['user_id'];
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$error = '';
$success = '';

if (!$event_id) {
    header("Location: organizer_dashboard.php");
    exit();
}

requireEventAdmin($user_id, $event_id);

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

$taskStats = [
    'total_tasks' => count($tasks),
    'pending_tasks' => count(array_filter($tasks, function($t) { return $t['status'] === 'pending'; })),
    'in_progress_tasks' => count(array_filter($tasks, function($t) { return $t['status'] === 'in_progress'; })),
    'completed_tasks' => count(array_filter($tasks, function($t) { return $t['status'] === 'completed'; })),
    'overdue_tasks' => count(array_filter($tasks, function($t) { return $t['status'] !== 'completed' && $t['due_date'] < date('Y-m-d H:i:s'); }))
];

$unreadCount = 0;
try {
    $unreadNotifications = getUnreadNotifications($_SESSION['user_id']);
    $unreadCount = count($unreadNotifications);
} catch (Exception $e) {
    error_log('Error getting notifications: ' . $e->getMessage());
    $unreadCount = 0;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventUp - Gestion des Tâches</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom CSS -->
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
   Base Styles
   ============================ */
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background-color: var(--bg-light);
    color: var(--text-dark);
    padding-top: 76px;
}

/* ===========================
   Navbar Styles
   ============================ */
.navbar {
    height: 76px;
    backdrop-filter: blur(10px);
    background-color: rgba(255, 255, 255, 0.98) !important;
    border-bottom: 1px solid var(--border-color);
}

.logo-icon {
    width: 92px;
    height: 92px;
    background: transparent;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 22px;
}

.brand-text {
    font-weight: 700;
    font-size: 20px;
    color: var(--primary-teal);
    letter-spacing: -0.5px;
}

.notification-btn {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-light);
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 20px;
    color: var(--text-dark);
}

.notification-btn:hover {
    background: var(--primary-orange);
    color: white;
    transform: scale(1.05);
}

.notification-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: var(--primary-orange);
    color: white;
    font-size: 11px;
    font-weight: 700;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
}

.admin-profile-btn {
    display: flex;
    align-items: center;
    background: transparent;
    border: none;
    padding: 6px 12px;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.admin-profile-btn:hover {
    background: var(--bg-light);
}

.admin-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
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

.red-gradient {
    background: linear-gradient(135deg, #ff4757, #ff6b7a);
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
    margin-bottom: 16px;
}

.task-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.task-header {
    background: linear-gradient(135deg, var(--primary-teal), var(--light-teal));
    color: white;
    padding: 20px 24px;
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
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 4px;
}

.task-meta {
    font-size: 14px;
    opacity: 0.9;
}

.task-body {
    padding: 24px;
}

.task-description {
    color: var(--text-muted);
    margin-bottom: 16px;
    font-size: 14px;
    line-height: 1.5;
}

.task-info {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    font-size: 14px;
}

.task-info i {
    color: var(--primary-orange);
    margin-right: 8px;
    width: 16px;
}

.task-status {
    padding: 6px 14px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pending {
    background: rgba(255, 215, 0, 0.15);
    color: #856404;
}

.status-in-progress {
    background: rgba(74, 144, 226, 0.15);
    color: #004085;
}

.status-completed {
    background: rgba(46, 213, 115, 0.15);
    color: #155724;
}

.status-overdue {
    background: rgba(255, 71, 87, 0.15);
    color: #721c24;
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

.btn-danger {
    background: linear-gradient(135deg, #ff4757, #ff6b7a);
    border: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #ff6b7a, #ff4757);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 71, 87, 0.3);
}

/* ===========================
   Cards
   ============================ */
.card {
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.card-header {
    background: white;
    border-bottom: 1px solid var(--border-color);
    padding: 16px 20px;
    border-radius: 16px 16px 0 0 !important;
}

.card-header h6 {
    font-weight: 700;
    color: var(--primary-teal);
    margin: 0;
}

/* ===========================
   Forms
   ============================ */
.form-control, .form-select {
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-orange);
    box-shadow: 0 0 0 0.2rem rgba(217, 74, 0, 0.25);
}

.form-label {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 8px;
    font-size: 14px;
}

/* ===========================
   Modal
   ============================ */
.modal-content {
    border: none;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
}

.modal-header {
    background: var(--bg-light);
    border-bottom: 1px solid var(--border-color);
    border-radius: 16px 16px 0 0;
    padding: 20px 24px;
}

.modal-title {
    color: var(--primary-teal);
    font-weight: 700;
    font-size: 20px;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    border-top: 1px solid var(--border-color);
    padding: 16px 24px;
}

/* ===========================
   Responsive Design
   ============================ */
@media (max-width: 767px) {
    body {
        padding-top: 64px;
    }
    
    .navbar {
        height: 64px;
    }
    
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
        padding: 16px 20px;
    }
    
    .task-body {
        padding: 20px;
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
}
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center" href="organizer_dashboard.php">
                <div class="logo-icon me-2">
                    <img src="assets/EventUp_logo.png" alt="EventUp Logo" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <span class="brand-text">EventUp</span>
            </a>
            
            <div class="d-flex align-items-center ms-auto">
                <div class="dropdown me-3">
                    <div class="notification-btn position-relative" data-bs-toggle="dropdown" style="cursor: pointer;">
                        <i class="bi bi-bell"></i>
                        <span class="notification-badge" id="notificationCount"><?php echo $unreadCount; ?></span>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end" id="notificationDropdown" style="width: 350px; max-height: 400px; overflow-y: auto;">
                        <li class="dropdown-header d-flex justify-content-between align-items-center">
                            <span>Notifications</span>
                            <a href="#" class="text-decoration-none" onclick="markAllAsRead()">Tout marquer comme lu</a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li id="notificationList">
                            <div class="text-center p-3">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
                
                <div class="dropdown">
                    <button class="btn admin-profile-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <div class="admin-avatar me-2"><?php echo strtoupper(substr($_SESSION['user_nom'] ?? 'A', 0, 1)); ?></div>
                        <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['user_nom'] ?? 'Admin'); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-house me-2"></i>Tableau de bord</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profil</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Paramètres</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container-fluid main-content">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="page-title">Gestion des Tâches</h1>
                <p class="page-subtitle">Événement: <strong><?php echo htmlspecialchars($event['titre']); ?></strong></p>
            </div>
            <div class="col-md-4 text-end">
                <a href="organizer_dashboard.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-2"></i>Retour au tableau de bord
                </a>
            </div>
        </div>

        <!-- Success Message -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i>
                <strong>Succès!</strong> <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i>
                <strong>Erreur!</strong> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i>
                <strong>Succès!</strong> <?php echo htmlspecialchars($success); ?>
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
                    <div class="stats-number"><?php echo (int) $taskStats['total_tasks']; ?></div>
                    <div class="stats-label">Total Tâches</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon blue-gradient">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stats-number"><?php echo (int) $taskStats['pending_tasks']; ?></div>
                    <div class="stats-label">En Attente</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon teal-gradient">
                        <i class="bi bi-arrow-repeat"></i>
                    </div>
                    <div class="stats-number"><?php echo (int) $taskStats['in_progress_tasks']; ?></div>
                    <div class="stats-label">En Cours</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon green-gradient">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stats-number"><?php echo (int) $taskStats['completed_tasks']; ?></div>
                    <div class="stats-label">Terminées</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Create Task Form -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-plus-circle me-2"></i>Créer une Nouvelle Tâche
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($organizers)): ?>
                            <div class="alert alert-warning" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Aucun organisateur assigné à cet événement. Veuillez d'abord assigner des organisateurs.
                            </div>
                        <?php else: ?>
                            <form id="create-task-form" novalidate>
                                <input type="hidden" name="action" value="create_task">
                                
                                <div class="mb-3">
                                    <label for="task_name" class="form-label">Nom de la Tâche <span class="text-danger">*</span></label>
                                    <input 
                                        type="text" 
                                        class="form-control" 
                                        id="task_name" 
                                        name="task_name" 
                                        placeholder="ex: Configurer le bureau d'enregistrement"
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
                                        placeholder="Décrivez les détails de la tâche..."
                                    ></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="organizer_id" class="form-label">Assigner à l'Organisateur <span class="text-danger">*</span></label>
                                    <select class="form-select" id="organizer_id" name="organizer_id" required>
                                        <option value="">-- Sélectionner un Organisateur --</option>
                                        <?php foreach ($organizers as $org): ?>
                                            <option value="<?php echo $org['id']; ?>">
                                                <?php echo htmlspecialchars($org['nom']); ?> (<?php echo htmlspecialchars($org['email']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="due_date" class="form-label">Date d'Échéance <span class="text-danger">*</span></label>
                                    <input 
                                        type="datetime-local" 
                                        class="form-control" 
                                        id="due_date" 
                                        name="due_date"
                                        required
                                    >
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-plus-circle me-2"></i>Créer la Tâche
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Tasks List -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-list-task me-2"></i>Liste des Tâches (<?php echo count($tasks); ?>)
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tasks)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-clipboard-x display-1 text-muted"></i>
                                <h5 class="text-muted mt-3">Aucune Tâche</h5>
                                <p class="text-muted">Aucune tâche n'a été créée pour cet événement.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
                                    <i class="bi bi-plus-circle me-2"></i>Créer votre première tâche
                                </button>
                            </div>
                        <?php else: ?>
                            <div id="task-list">
                                <?php foreach ($tasks as $task): ?>
                                    <?php 
                                    $isOverdue = $task['status'] !== 'completed' && $task['due_date'] < date('Y-m-d H:i:s');
                                    $statusClass = $isOverdue ? 'status-overdue' : 'status-' . str_replace('_', '-', $task['status']);
                                    $statusText = $isOverdue ? 'En Retard' : ucfirst(str_replace('_', ' ', $task['status']));
                                    ?>
                                    <div class="task-card" id="task-<?php echo $task['id']; ?>">
                                        <div class="task-header">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <div class="task-title"><?php echo htmlspecialchars($task['task_name']); ?></div>
                                                    <div class="task-meta">
                                                        <i class="bi bi-person me-1"></i>
                                                        <?php echo htmlspecialchars($task['organizer_name']); ?>
                                                    </div>
                                                </div>
                                                <span class="task-status <?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="task-body">
                                            <?php if ($task['description']): ?>
                                                <div class="task-description">
                                                    <?php echo htmlspecialchars($task['description']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="task-info">
                                                <i class="bi bi-calendar-event"></i>
                                                <span>Échéance: <?php echo date('d M Y à H:i', strtotime($task['due_date'])); ?></span>
                                            </div>
                                            
                                            <div class="task-info">
                                                <i class="bi bi-clock-history"></i>
                                                <span>Créée le: <?php echo date('d M Y à H:i', strtotime($task['created_at'])); ?></span>
                                            </div>
                                            
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <select class="form-select form-select-sm task-status-select" data-task-id="<?php echo $task['id']; ?>">
                                                        <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>En Attente</option>
                                                        <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>En Cours</option>
                                                        <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Terminée</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <button type="button" class="btn btn-sm btn-danger w-100 delete-task-btn" data-task-id="<?php echo $task['id']; ?>">
                                                        <i class="bi bi-trash me-1"></i>Supprimer
                                                    </button>
                                                </div>
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
    </main>

    <!-- Task Assignment Confirmation Modal -->
    <div class="modal fade" id="assignTaskModal" tabindex="-1" aria-labelledby="assignTaskLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignTaskLabel">
                        <i class="bi bi-person-check me-2"></i>Confirmer l'Assignation de Tâche
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Assigner la tâche <strong id="assignTaskNameDisplay"></strong> à <strong id="assignOrganizerDisplay"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Annuler
                    </button>
                    <button type="button" class="btn btn-primary" id="confirmAssignBtn">
                        <i class="bi bi-check-circle me-2"></i>Confirmer l'Assignation
                    </button>
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
                        showToast('Tâche créée avec succès!', 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        showToast('Erreur: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Une erreur inattendue est survenue.', 'error');
                });
            });
        }

        document.querySelectorAll('.task-status-select').forEach(select => {
            select.addEventListener('change', function() {
                const taskId = this.dataset.taskId;
                const newStatus = this.value;
                
                const formData = new FormData();
                formData.append('action', 'update_task_status');
                formData.append('task_id', taskId);
                formData.append('status', newStatus);

                fetch('ajax_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Statut de la tâche mis à jour!', 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showToast('Erreur: ' + data.message, 'error');
                        this.value = this.dataset.originalValue || 'pending';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Une erreur inattendue est survenue.', 'error');
                    this.value = this.dataset.originalValue || 'pending';
                });
            });

            select.dataset.originalValue = select.value;
        });

        document.querySelectorAll('.delete-task-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const taskId = this.dataset.taskId;
                
                if (confirm('Êtes-vous sûr de vouloir supprimer cette tâche?')) {
                    const formData = new FormData();
                    formData.append('action', 'delete_task');
                    formData.append('task_id', taskId);

                    fetch('ajax_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Tâche supprimée avec succès!', 'success');
                            const taskCard = document.getElementById('task-' + taskId);
                            taskCard.style.transition = 'all 0.3s ease';
                            taskCard.style.opacity = '0';
                            taskCard.style.transform = 'translateX(100%)';
                            
                            setTimeout(() => {
                                taskCard.remove();
                                updateTaskStats();
                            }, 300);
                        } else {
                            showToast('Erreur: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('Une erreur inattendue est survenue.', 'error');
                    });
                }
            });
        });
    });

    function showToast(message, type = 'info') {
        const toastHtml = `
            <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'primary'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        const toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.innerHTML = toastHtml;
        document.body.appendChild(toastContainer);
        
        const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'));
        toast.show();
        
        toastContainer.querySelector('.toast').addEventListener('hidden.bs.toast', () => {
            toastContainer.remove();
        });
    }

    function updateTaskStats() {
        const totalTasks = document.querySelectorAll('.task-card').length;
        const completedTasks = document.querySelectorAll('.status-completed').length;
        const pendingTasks = document.querySelectorAll('.status-pending').length;
        const inProgressTasks = document.querySelectorAll('.status-in-progress').length;
        
        // Update stats cards if they exist
        const totalElement = document.querySelector('.stats-number');
        if (totalElement) {
            totalElement.textContent = totalTasks;
        }
    }

    // Notification functions
    function markAllAsRead() {
        fetch('ajax_handler.php', {
            method: 'POST',
            body: new FormData().append('action', 'mark_all_read')
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('notificationCount').textContent = '0';
                document.getElementById('notificationList').innerHTML = '<li><div class="text-center p-3 text-muted">Aucune notification non lue</div></li>';
            }
        })
        .catch(error => console.error('Error marking all as read:', error));
    }

    // Load notifications when dropdown is opened
    document.querySelector('.notification-btn').addEventListener('click', function() {
        const notificationList = document.getElementById('notificationList');
        
        if (notificationList.querySelector('.spinner-border')) {
            fetch('ajax_handler.php?action=get_notifications', {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '';
                    if (data.notifications.length === 0) {
                        html = '<li><div class="text-center p-3 text-muted">Aucune notification non lue</div></li>';
                    } else {
                        data.notifications.forEach(notification => {
                            html += `
                                <li class="dropdown-item">
                                    <div class="d-flex">
                                        <div class="flex-grow-1">
                                            <strong>${notification.title}</strong>
                                            <div class="small text-muted">${notification.message}</div>
                                            <div class="small text-muted">${notification.time_ago}</div>
                                        </div>
                                    </div>
                                </li>
                            `;
                        });
                    }
                    notificationList.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                notificationList.innerHTML = '<li><div class="text-center p-3 text-danger">Erreur de chargement</div></li>';
            });
        }
    });
    </script>
</body>
</html>
