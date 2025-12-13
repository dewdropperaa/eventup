<?php
/**
 * Edit Event Page - Modern Design
 * Allows event admins/organizers to edit event details
 * Only admins can delete the event
 */

session_start();
require_once 'role_check.php';
require_once 'notifications.php';

// Check if user is logged in
requireLogin();

require_once 'database.php';

$pdo = getDatabaseConnection();
$error = '';
$success = '';
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$event = null;
$user_role = null;

// Get unread notifications count
$unreadCount = 0;
try {
    $unreadNotifications = getUnreadNotifications($_SESSION['user_id']);
    $unreadCount = count($unreadNotifications);
} catch (Exception $e) {
    error_log('Error getting notifications: ' . $e->getMessage());
    $unreadCount = 0;
}

// Verify event exists and user has permission
if ($event_id <= 0) {
    $error = 'ID d\'événement invalide.';
} else {
    try {
        // Get event and user's role
        $stmt = $pdo->prepare("
            SELECT e.*, er.role
            FROM events e
            LEFT JOIN event_roles er ON e.id = er.event_id AND er.user_id = :user_id
            WHERE e.id = :event_id
        ");
        
        $stmt->execute([
            ':event_id' => $event_id,
            ':user_id' => $_SESSION['user_id']
        ]);
        
        $event = $stmt->fetch();
        
        if (!$event) {
            $error = 'Événement non trouvé.';
        } elseif (!$event['role'] || $event['role'] !== 'admin') {
            $error = 'Vous n\'avez pas la permission de modifier cet événement.';
        } else {
            $user_role = $event['role'];
        }
    } catch (PDOException $e) {
        error_log("Event verification error: " . $e->getMessage());
        $error = 'Une erreur est survenue lors de la vérification de l\'événement.';
    }
}

// Handle delete request (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && !$error) {
    if ($user_role !== 'admin') {
        $error = 'Seul l\'administrateur de l\'événement peut supprimer l\'événement.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Delete event (cascading deletes will handle related records)
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = :event_id");
            $stmt->execute([':event_id' => $event_id]);
            
            $pdo->commit();
            
            $_SESSION['success_message'] = 'Événement supprimé avec succès!';
            header("Location: dashboard.php");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Event deletion error: " . $e->getMessage());
            $error = 'Une erreur est survenue lors de la suppression de l\'événement.';
        }
    }
}

// Handle edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action']) && !$error) {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $lieu = trim($_POST['lieu'] ?? '');
    $nb_max_participants = trim($_POST['nb_max_participants'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    
    // Validation
    if (empty($titre)) {
        $error = 'Le titre est requis.';
    } elseif (empty($description)) {
        $error = 'La description est requise.';
    } elseif (empty($date)) {
        $error = 'La date est requise.';
    } elseif (empty($lieu)) {
        $error = 'Le lieu est requis.';
    } elseif (empty($nb_max_participants)) {
        $error = 'Le nombre maximum de participants est requis.';
    } elseif (!is_numeric($nb_max_participants) || $nb_max_participants <= 0) {
        $error = 'Le nombre maximum de participants doit être un nombre positif.';
    } else {
        try {
            // Update event
            $stmt = $pdo->prepare("
                UPDATE events 
                SET titre = :titre, 
                    description = :description, 
                    date = :date, 
                    lieu = :lieu, 
                    nb_max_participants = :nb_max_participants,
                    category = :category
                WHERE id = :event_id
            ");
            
            $stmt->execute([
                ':titre' => $titre,
                ':description' => $description,
                ':date' => $date,
                ':lieu' => $lieu,
                ':nb_max_participants' => (int)$nb_max_participants,
                ':category' => $category,
                ':event_id' => $event_id
            ]);
            
            $success = 'Événement mis à jour avec succès!';
            
            // Refresh event data
            $stmt = $pdo->prepare("
                SELECT e.*, er.role
                FROM events e
                LEFT JOIN event_roles er ON e.id = er.event_id AND er.user_id = :user_id
                WHERE e.id = :event_id
            ");
            
            $stmt->execute([
                ':event_id' => $event_id,
                ':user_id' => $_SESSION['user_id']
            ]);
            
            $event = $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Event update error: " . $e->getMessage());
            $error = 'Une erreur est survenue lors de la mise à jour de l\'événement.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventUp - Modifier l'Événement</title>
    
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
   Form Card
   ============================ */
.form-card {
    background: white;
    border-radius: 16px;
    padding: 40px;
    transition: all 0.3s ease;
    border: 1px solid var(--border-color);
    position: relative;
    overflow: hidden;
}

.form-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-orange), var(--light-orange));
}

.form-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.1);
}

.form-header {
    text-align: center;
    margin-bottom: 40px;
}

.form-icon {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 36px;
    margin: 0 auto 20px;
    background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
}

.form-title {
    font-size: 28px;
    font-weight: 700;
    color: var(--primary-teal);
    margin-bottom: 8px;
}

.form-description {
    color: var(--text-muted);
    font-size: 16px;
}

/* ===========================
   Forms
   ============================ */
.form-control, .form-select {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 14px 18px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #fafbfc;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-orange);
    box-shadow: 0 0 0 0.2rem rgba(217, 74, 0, 0.25);
    background: white;
}

.form-label {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 8px;
    font-size: 15px;
}

.form-text {
    color: var(--text-muted);
    font-size: 13px;
    margin-top: 6px;
}

/* ===========================
   Buttons
   ============================ */
.btn-primary {
    background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
    border: none;
    padding: 14px 28px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 16px;
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
    padding: 12px 24px;
    font-weight: 600;
    font-size: 16px;
    transition: all 0.3s ease;
}

.btn-outline-primary:hover {
    background: var(--primary-orange);
    border-color: var(--primary-orange);
    color: white;
    transform: translateY(-2px);
}

.btn-secondary {
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    color: var(--text-muted);
    padding: 14px 28px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 16px;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background: var(--border-color);
    color: var(--text-dark);
    transform: translateY(-2px);
}

.btn-danger {
    background: linear-gradient(135deg, #ff4757, #ff6b7a);
    border: none;
    border-radius: 10px;
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
    padding: 20px 24px;
    border-radius: 16px 16px 0 0 !important;
}

.card-header h6 {
    font-weight: 700;
    color: var(--primary-teal);
    margin: 0;
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
   Alerts
   ============================ */
.alert {
    border: none;
    border-radius: 12px;
    padding: 16px 20px;
    font-size: 15px;
    margin-bottom: 24px;
}

.alert-success {
    background: rgba(46, 213, 115, 0.1);
    color: #155724;
    border-left: 4px solid var(--success-green);
}

.alert-danger {
    background: rgba(255, 71, 87, 0.1);
    color: #721c24;
    border-left: 4px solid #ff4757;
}

.alert-warning {
    background: rgba(255, 215, 0, 0.1);
    color: #856404;
    border-left: 4px solid var(--warning-yellow);
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
    
    .form-card {
        padding: 24px;
    }
    
    .form-title {
        font-size: 22px;
    }
    
    .form-icon {
        width: 60px;
        height: 60px;
        font-size: 28px;
    }
}

@media (max-width: 575px) {
    .main-content {
        padding: 16px;
    }
    
    .form-card {
        padding: 20px;
    }
    
    .btn-primary, .btn-secondary, .btn-outline-primary {
        padding: 12px 20px;
        font-size: 14px;
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
                <h1 class="page-title">Modifier l'Événement</h1>
                <p class="page-subtitle">Événement: <strong><?php echo htmlspecialchars($event['titre'] ?? ''); ?></strong></p>
            </div>
            <div class="col-md-4 text-end">
                <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-2"></i>Retour à l'événement
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($error && !$event): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <strong>Erreur!</strong> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <div class="text-center mt-4">
                        <a href="organizer_dashboard.php" class="btn btn-secondary">
                            <i class="bi bi-house me-2"></i>Retour au tableau de bord
                        </a>
                    </div>
                </div>
            </div>
        <?php elseif ($event): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="form-card">
                        <div class="form-header">
                            <div class="form-icon">
                                <i class="bi bi-pencil-square"></i>
                            </div>
                            <h2 class="form-title">Modifier l'Événement</h2>
                            <p class="form-description">Mettez à jour les informations de votre événement</p>
                            <div class="mt-3">
                                <span class="badge bg-primary">Rôle: <?php echo $user_role === 'admin' ? 'Administrateur' : 'Organisateur'; ?></span>
                            </div>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-circle me-2"></i>
                                <strong>Erreur!</strong> <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>Succès!</strong> <?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                    
                        <form method="POST" action="edit_event.php?id=<?php echo $event_id; ?>" id="edit-event-form" novalidate>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="titre" class="form-label">Titre de l'événement <span class="text-danger">*</span></label>
                                        <input 
                                            type="text" 
                                            class="form-control" 
                                            id="titre" 
                                            name="titre" 
                                            placeholder="Ex: Conférence Tech 2025"
                                            value="<?php echo htmlspecialchars($event['titre']); ?>"
                                            required
                                        >
                                        <div class="invalid-feedback">Le titre est requis.</div>
                                        <small class="form-text text-muted">Maximum 150 caractères</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Catégorie <span class="text-danger">*</span></label>
                                        <select 
                                            class="form-select" 
                                            id="category" 
                                            name="category"
                                            required
                                        >
                                            <option value="General" <?php echo ($event['category'] ?? 'General') === 'General' ? 'selected' : ''; ?>>General</option>
                                            <option value="Technology" <?php echo ($event['category'] ?? '') === 'Technology' ? 'selected' : ''; ?>>Technology</option>
                                            <option value="Business" <?php echo ($event['category'] ?? '') === 'Business' ? 'selected' : ''; ?>>Business</option>
                                            <option value="Education" <?php echo ($event['category'] ?? '') === 'Education' ? 'selected' : ''; ?>>Education</option>
                                            <option value="Sports" <?php echo ($event['category'] ?? '') === 'Sports' ? 'selected' : ''; ?>>Sports</option>
                                            <option value="Arts & Culture" <?php echo ($event['category'] ?? '') === 'Arts & Culture' ? 'selected' : ''; ?>>Arts & Culture</option>
                                            <option value="Health & Wellness" <?php echo ($event['category'] ?? '') === 'Health & Wellness' ? 'selected' : ''; ?>>Health & Wellness</option>
                                            <option value="Networking" <?php echo ($event['category'] ?? '') === 'Networking' ? 'selected' : ''; ?>>Networking</option>
                                            <option value="Other" <?php echo ($event['category'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                        <div class="invalid-feedback">La catégorie est requise.</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea 
                                    class="form-control" 
                                    id="description" 
                                    name="description" 
                                    rows="4"
                                    placeholder="Décrivez votre événement en détail..."
                                    required
                                ><?php echo htmlspecialchars($event['description']); ?></textarea>
                                <div class="invalid-feedback">La description est requise.</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="date" class="form-label">Date et heure <span class="text-danger">*</span></label>
                                        <input 
                                            type="datetime-local" 
                                            class="form-control" 
                                            id="date" 
                                            name="date"
                                            value="<?php echo htmlspecialchars(str_replace(' ', 'T', $event['date'])); ?>"
                                            required
                                        >
                                        <div class="invalid-feedback">La date et l'heure sont requises.</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="lieu" class="form-label">Lieu <span class="text-danger">*</span></label>
                                        <input 
                                            type="text" 
                                            class="form-control" 
                                            id="lieu" 
                                            name="lieu" 
                                            placeholder="Ex: Paris Convention Center"
                                            value="<?php echo htmlspecialchars($event['lieu']); ?>"
                                            required
                                        >
                                        <div class="invalid-feedback">Le lieu est requis.</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="nb_max_participants" class="form-label">Nombre maximum de participants <span class="text-danger">*</span></label>
                                <input 
                                    type="number" 
                                    class="form-control" 
                                    id="nb_max_participants" 
                                    name="nb_max_participants" 
                                    placeholder="Ex: 100"
                                    min="1"
                                    value="<?php echo htmlspecialchars($event['nb_max_participants']); ?>"
                                    required
                                >
                                <div class="invalid-feedback">Le nombre de participants doit être un nombre positif.</div>
                            </div>
                            
                            <div class="d-flex gap-3 justify-content-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle me-2"></i>Enregistrer les modifications
                                </button>
                                <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-secondary btn-lg">
                                    <i class="bi bi-x-circle me-2"></i>Annuler
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Delete Event Section (Admin Only) -->
                <?php if ($user_role === 'admin'): ?>
                    <div class="card mt-4 border-danger">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>Zone de danger
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning" role="alert">
                                <i class="bi bi-shield-exclamation me-2"></i>
                                <strong>Attention!</strong> La suppression de cet événement est irréversible.
                            </div>
                            <p class="text-muted mb-3">
                                Tous les rôles d'événement, les inscriptions et les invitations associées seront supprimés.
                            </p>
                            
                            <button 
                                type="button" 
                                class="btn btn-danger" 
                                data-bs-toggle="modal" 
                                data-bs-target="#deleteConfirmModal"
                            >
                                <i class="bi bi-trash me-2"></i>Supprimer l'événement
                            </button>
                        </div>
                    </div>
                    
                    <!-- Delete Confirmation Modal -->
                    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title" id="deleteConfirmLabel">
                                        <i class="bi bi-exclamation-triangle me-2"></i>Confirmer la suppression
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Êtes-vous sûr de vouloir supprimer l'événement <strong><?php echo htmlspecialchars($event['titre']); ?></strong>?</p>
                                    <div class="alert alert-danger" role="alert">
                                        <i class="bi bi-shield-exclamation me-2"></i>
                                        <strong>Cette action est irréversible!</strong>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="bi bi-x-circle me-2"></i>Annuler
                                    </button>
                                    <form method="POST" action="edit_event.php?id=<?php echo $event_id; ?>" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="bi bi-trash me-2"></i>Supprimer définitivement
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Notification JavaScript -->
    <script>
        // Load notifications dynamically
        document.addEventListener('DOMContentLoaded', function() {
            const notificationBtn = document.querySelector('.notification-btn');
            const notificationList = document.getElementById('notificationList');
            
            if (notificationBtn && notificationList) {
                notificationBtn.addEventListener('click', function() {
                    // Load notifications via AJAX
                    fetch('ajax_handler.php?action=get_notifications')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                let html = '';
                                if (data.notifications.length === 0) {
                                    html = '<li><div class="text-center p-3 text-muted">Aucune notification</div></li>';
                                } else {
                                    data.notifications.forEach(notification => {
                                        html += `
                                            <li class="dropdown-item">
                                                <div class="d-flex">
                                                    <div class="flex-grow-1">
                                                        <div class="fw-bold">${notification.title}</div>
                                                        <div class="small text-muted">${notification.message}</div>
                                                        <div class="small text-muted">${notification.created_at}</div>
                                                    </div>
                                                </div>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
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
                });
            }
        });
        
        // Mark all as read function
        function markAllAsRead() {
            fetch('ajax_handler.php?action=mark_all_read', {method: 'POST'})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('notificationCount').textContent = '0';
                    }
                })
                .catch(error => console.error('Error marking all as read:', error));
        }
        
        // Form validation
        (function() {
            'use strict';
            
            const form = document.getElementById('edit-event-form');
            if (form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                });
            }
        })();
    </script>
</body>
</html>
<?php
?>
