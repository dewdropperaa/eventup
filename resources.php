<?php
session_start();

require 'database.php';
require_once 'role_check.php';
require_once 'notifications.php';

requireLogin();

$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

if ($eventId <= 0) {
    header('Location: index.php');
    exit;
}

// Check if user is event owner or has permission to manage resources
// Event owners should always have access to resources
$event = null;
try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare('SELECT id, titre, created_by FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event || ($event['created_by'] != $_SESSION['user_id'] && !canDo($eventId, $_SESSION['user_id'], 'can_manage_resources'))) {
        header('Location: event_details.php?id=' . $eventId);
        exit;
    }
} catch (Exception $e) {
    error_log('Error checking event owner: ' . $e->getMessage());
    header('Location: event_details.php?id=' . $eventId);
    exit;
}

$error = '';
$success = '';

$unreadCount = 0;
try {
    $unreadNotifications = getUnreadNotifications($_SESSION['user_id']);
    $unreadCount = count($unreadNotifications);
} catch (Exception $e) {
    error_log('Error getting notifications: ' . $e->getMessage());
    $unreadCount = 0;
}

try {
    $stmt = $pdo->prepare('SELECT id, titre, created_by FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event) {
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    error_log('Error fetching event: ' . $e->getMessage());
    $error = 'Erreur de chargement de l\'événement.';
}

$isEventOwner = isset($_SESSION['user_id']) && $event && $_SESSION['user_id'] == $event['created_by'];
$isEventAdmin = isEventAdmin($_SESSION['user_id'], $eventId);
$isEventOrganizer = isEventOrganizer($_SESSION['user_id'], $eventId);

try {
    $stmt = $pdo->prepare('
        SELECT id, nom, type, quantite_totale, description, 
               date_disponibilite_debut, date_disponibilite_fin, 
               image_path, statut, created_at
        FROM event_resources 
        WHERE event_id = ? 
        ORDER BY created_at DESC
    ');
    $stmt->execute([$eventId]);
    $resources = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching resources: ' . $e->getMessage());
}

try {
    $stmt = $pdo->prepare('
        SELECT rb.id, rb.resource_id, rb.date_debut, rb.date_fin, 
               rb.statut, rb.notes, u.nom as user_nom, er.nom as resource_nom
        FROM resource_bookings rb
        JOIN users u ON rb.user_id = u.id
        JOIN event_resources er ON rb.resource_id = er.id
        WHERE rb.event_id = ? 
        ORDER BY rb.date_debut DESC
    ');
    $stmt->execute([$eventId]);
    $bookings = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching bookings: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_resource') {
    $resourceId = isset($_POST['resource_id']) ? (int) $_POST['resource_id'] : 0;
    
    if ($resourceId > 0) {
        try {
            $stmt = $pdo->prepare('SELECT id FROM event_resources WHERE id = ? AND event_id = ?');
            $stmt->execute([$resourceId, $eventId]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare('DELETE FROM event_resources WHERE id = ?');
                $stmt->execute([$resourceId]);
                $success = 'Ressource supprimée avec succès.';
                
                $stmt = $pdo->prepare('
                    SELECT id, nom, type, quantite_totale, description, 
                           date_disponibilite_debut, date_disponibilite_fin, 
                           image_path, statut, created_at
                    FROM event_resources 
                    WHERE event_id = ? 
                    ORDER BY created_at DESC
                ');
                $stmt->execute([$eventId]);
                $resources = $stmt->fetchAll();
            }
        } catch (Exception $e) {
            error_log('Error deleting resource: ' . $e->getMessage());
            $error = 'Error deleting resource.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
    $bookingId = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
    
    if ($bookingId > 0) {
        try {
            $stmt = $pdo->prepare('UPDATE resource_bookings SET statut = "Annulée" WHERE id = ? AND event_id = ?');
            $stmt->execute([$bookingId, $eventId]);
            $success = 'Réservation annulée avec succès.';
            
            $stmt = $pdo->prepare('
                SELECT rb.id, rb.resource_id, rb.date_debut, rb.date_fin, 
                       rb.statut, rb.notes, u.nom as user_nom, er.nom as resource_nom
                FROM resource_bookings rb
                JOIN users u ON rb.user_id = u.id
                JOIN event_resources er ON rb.resource_id = er.id
                WHERE rb.event_id = ? 
                ORDER BY rb.date_debut DESC
            ');
            $stmt->execute([$eventId]);
            $bookings = $stmt->fetchAll();
        } catch (Exception $e) {
            error_log('Error cancelling booking: ' . $e->getMessage());
            $error = 'Erreur lors de l\'annulation de la réservation.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventUp - Gestion des Ressources</title>
    
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
   Sidebar Styles
   ============================ */


.sidebar .nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 24px;
    color: var(--text-muted);
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 500;
    border-left: 3px solid transparent;
    margin: 4px 0;
}


.sidebar .nav-link i {
    font-size: 20px;
    width: 24px;
}


.sidebar .nav-link:hover {
    background: linear-gradient(90deg, rgba(217, 74, 0, 0.05), transparent);
    color: var(--primary-orange);
}


.sidebar .nav-link.active {
    background: linear-gradient(90deg, rgba(217, 74, 0, 0.1), transparent);
    color: var(--primary-orange);
    border-left-color: var(--primary-orange);
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
    margin-bottom: 0;
}


/* ===========================
   Tabs
   ============================ */
.nav-tabs {
    border-bottom: 2px solid var(--border-color);
    margin-bottom: 32px;
}


.nav-tabs .nav-link {
    color: var(--text-muted);
    border: none;
    border-bottom: 3px solid transparent;
    padding: 12px 24px;
    font-weight: 600;
    transition: all 0.3s ease;
    background: transparent;
}


.nav-tabs .nav-link:hover {
    color: var(--primary-orange);
    border-color: transparent;
    background: transparent;
}


.nav-tabs .nav-link.active {
    color: var(--primary-orange);
    border-color: transparent transparent var(--primary-orange) transparent;
    background-color: transparent;
}


.nav-tabs .nav-link i {
    font-size: 18px;
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


.btn-outline-danger {
    color: #dc3545;
    border-color: #dc3545;
}


.btn-outline-danger:hover {
    background: #dc3545;
    border-color: #dc3545;
    color: white;
}


/* ===========================
   Resource Cards
   ============================ */
.resource-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    transition: all 0.3s ease;
    border: 1px solid var(--border-color);
    height: 100%;
}


.resource-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
}


.resource-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 26px;
    flex-shrink: 0;
}


.orange-gradient {
    background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
}


.teal-gradient {
    background: linear-gradient(135deg, var(--primary-teal), var(--light-teal));
}


.yellow-gradient {
    background: linear-gradient(135deg, #FFD700, #FFA500);
}


.blue-gradient {
    background: linear-gradient(135deg, #4A90E2, #357ABD);
}


.resource-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary-teal);
    margin-bottom: 8px;
}


.resource-subtitle {
    color: var(--text-muted);
    font-size: 14px;
    margin-bottom: 12px;
}


.resource-details {
    min-height: 40px;
    display: flex;
    align-items: center;
}


/* ===========================
   Badges
   ============================ */
.badge {
    padding: 6px 14px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 12px;
}


.badge-success {
    background: rgba(46, 213, 115, 0.15);
    color: #2ed573;
}


.badge-warning {
    background: rgba(255, 215, 0, 0.2);
    color: #d4a000;
}


.badge-danger {
    background: rgba(220, 53, 69, 0.15);
    color: #dc3545;
}


.role-admin {
    background: rgba(217, 74, 0, 0.15);
    color: var(--primary-orange);
}


.role-organizer {
    background: rgba(27, 94, 82, 0.15);
    color: var(--primary-teal);
}


.role-participant {
    background: rgba(108, 117, 125, 0.15);
    color: #6c757d;
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


.card-header h5 {
    font-weight: 700;
    color: var(--primary-teal);
}


/* ===========================
   Table Styles
   ============================ */
.table {
    margin-bottom: 0;
}


.table thead th {
    background: var(--bg-light);
    color: var(--primary-teal);
    font-weight: 700;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border-color);
    padding: 16px;
}


.table tbody td {
    padding: 16px;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-color);
}


.table tbody tr {
    transition: all 0.3s ease;
}


.table tbody tr:hover {
    background: var(--bg-light);
    transform: scale(1.01);
}


.table tbody td i.fs-5 {
    font-size: 24px;
}


.text-danger {
    color: #dc3545 !important;
}


.text-primary {
    color: #0d6efd !important;
}


.text-success {
    color: #198754 !important;
}


.text-info {
    color: #0dcaf0 !important;
}


/* ===========================
   Pagination
   ============================ */
.pagination .page-link {
    color: var(--primary-orange);
    border-radius: 8px;
    margin: 0 4px;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}


.pagination .page-link:hover {
    background: var(--primary-orange);
    color: white;
    border-color: var(--primary-orange);
}


.pagination .page-item.active .page-link {
    background: var(--primary-orange);
    border-color: var(--primary-orange);
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
   Forms
   ============================ */
.form-label {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 8px;
    font-size: 14px;
}


.form-control,
.form-select {
    border-radius: 10px;
    border: 1px solid var(--border-color);
    padding: 10px 14px;
    transition: all 0.3s ease;
    font-size: 14px;
}


.form-control:focus,
.form-select:focus {
    border-color: var(--primary-orange);
    box-shadow: 0 0 0 3px rgba(217, 74, 0, 0.1);
    outline: none;
}


/* ===========================
   Responsive Design
   ============================ */
@media (max-width: 991px) {
    .sidebar {
        width: 70px;
    }
    
    .sidebar .nav-link span {
        display: none;
    }
    
    .sidebar .nav-link {
        justify-content: center;
        padding: 12px 8px;
    }
}


@media (max-width: 767px) {
    body {
        padding-top: 64px;
    }
    
    .navbar {
        height: 64px;
    }
    
    .sidebar {
        top: 64px;
        width: 60px;
    }
    
        
    .page-title {
        font-size: 24px;
    }


    .nav-tabs .nav-link {
        padding: 10px 16px;
        font-size: 14px;
    }
    
    .resource-icon {
        width: 48px;
        height: 48px;
        font-size: 22px;
    }
    
    .resource-title {
        font-size: 16px;
    }
}


@media (max-width: 575px) {
    .main-content {
        padding: 16px;
    }
    
    .nav-tabs .nav-link span {
        display: none;
    }
    
    .resource-card .d-flex.gap-2 {
        flex-direction: column;
        gap: 8px !important;
    }
    
    .resource-card .btn-sm {
        width: 100%;
    }


    .btn-primary {
        width: 100%;
        margin-bottom: 8px;
    }
}
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center" href="event_details.php?id=<?php echo $eventId; ?>">
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

    <div class="container-fluid">
        <div class="row">
            <?php include 'event_nav.php'; ?>

            <!-- Main Content -->
            <main class="col-lg-9 px-md-4 main-content">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="page-title">Gestion des ressources</h1>
                    <div>
                        <small class="text-muted me-2">Événement:</small>
                        <strong><?php echo htmlspecialchars($event['titre']); ?></strong>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="resourceTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="bookable-tab" data-bs-toggle="tab" data-bs-target="#bookable" type="button" role="tab">
                            <i class="bi bi-calendar-check me-2"></i>Ressources réservables
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="bookings-tab" data-bs-toggle="tab" data-bs-target="#bookings" type="button" role="tab">
                            <i class="bi bi-calendar-week me-2"></i>Réservations
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab">
                            <i class="bi bi-bar-chart me-2"></i>Statistiques
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="resourceTabContent">
                    <!-- Bookable Resources Tab -->
                    <div class="tab-pane fade show active" id="bookable" role="tabpanel">
                        <div class="mb-4">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookableResourceModal">
                                <i class="bi bi-plus-circle me-2"></i>Ajouter une ressource
                            </button>
                        </div>

                        <?php if (empty($resources)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox display-1 text-muted"></i>
                                <h4 class="text-muted mt-3">Aucune ressource</h4>
                                <p class="text-muted">Cliquez sur "Ajouter une ressource" pour commencer.</p>
                            </div>
                        <?php else: ?>
                            <!-- Resources Grid -->
                            <div class="row g-4">
                                <?php foreach ($resources as $resource): 
                                    $iconClass = 'bi-box';
                                    $gradientClass = 'orange-gradient';
                                    
                                    if (strpos($resource['type'], 'Salle') !== false) {
                                        $iconClass = 'bi-door-closed';
                                        $gradientClass = 'teal-gradient';
                                    } elseif (strpos($resource['type'], 'Matériel') !== false) {
                                        $iconClass = 'bi-tools';
                                        $gradientClass = 'blue-gradient';
                                    } elseif (strpos($resource['type'], 'Véhicule') !== false) {
                                        $iconClass = 'bi-car-front';
                                        $gradientClass = 'yellow-gradient';
                                    }
                                    
                                    $statusClass = 'badge-success';
                                    if ($resource['statut'] === 'Indisponible') {
                                        $statusClass = 'badge-danger';
                                    } elseif ($resource['statut'] === 'En maintenance') {
                                        $statusClass = 'badge-warning';
                                    }
                                ?>
                                    <div class="col-xl-3 col-md-6">
                                        <div class="resource-card">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div class="resource-icon <?php echo $gradientClass; ?>">
                                                    <i class="bi <?php echo $iconClass; ?>"></i>
                                                </div>
                                                <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($resource['statut']); ?></span>
                                            </div>
                                            <h5 class="resource-title"><?php echo htmlspecialchars($resource['nom']); ?></h5>
                                            <p class="resource-subtitle"><?php echo htmlspecialchars($resource['type']); ?></p>
                                            <div class="resource-details mb-3">
                                                <small class="text-muted">
                                                    <?php if ($resource['quantite_totale']): ?>
                                                        <i class="bi bi-box me-1"></i>Quantité : <?php echo (int) $resource['quantite_totale']; ?>
                                                    <?php endif; ?>
                                                    <?php if ($resource['description']): ?>
                                                        <br><i class="bi bi-info-circle me-1"></i><?php echo htmlspecialchars(substr($resource['description'], 0, 60)); ?>...
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-outline-primary flex-grow-1" onclick="viewAvailability(<?php echo $resource['id']; ?>)">
                                                    <i class="bi bi-calendar"></i> Disponibilité
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary" onclick="editBookableResource(<?php echo $resource['id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" style="display: contents;" onsubmit="return confirm('Supprimer cette ressource?');">
                                                    <input type="hidden" name="action" value="delete_resource">
                                                    <input type="hidden" name="resource_id" value="<?php echo $resource['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Bookings Tab -->
                    <div class="tab-pane fade" id="bookings" role="tabpanel">
                        <div class="mb-4">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookResourceModal">
                                <i class="bi bi-plus-circle me-2"></i>Nouvelle réservation
                            </button>
                        </div>

                        <?php if (empty($bookings)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-calendar-x display-1 text-muted"></i>
                                <h4 class="text-muted mt-3">Aucune réservation</h4>
                                <p class="text-muted">Cliquez sur "Nouvelle réservation" pour en créer une.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ressource</th>
                                            <th>Réservé par</th>
                                            <th>Début</th>
                                            <th>Fin</th>
                                            <th>Statut</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bookings as $booking): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($booking['resource_nom']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($booking['user_nom']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($booking['date_debut'])); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($booking['date_fin'])); ?></td>
                                                <td>
                                                    <span class="badge <?php 
                                                        echo $booking['statut'] === 'Confirmée' ? 'badge-success' : 
                                                             ($booking['statut'] === 'En attente' ? 'badge-warning' : 'badge-danger'); 
                                                    ?>">
                                                        <?php echo htmlspecialchars($booking['statut']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($booking['statut'] !== 'Annulée'): ?>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Annuler cette réservation?');">
                                                            <input type="hidden" name="action" value="cancel_booking">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="bi bi-x-circle"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Statistics Tab -->
                    <div class="tab-pane fade" id="stats" role="tabpanel">
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <div class="resource-icon orange-gradient mx-auto mb-3">
                                            <i class="bi bi-box"></i>
                                        </div>
                                        <h6 class="text-muted">Total Ressources</h6>
                                        <h2 class="text-primary"><?php echo count($resources); ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <div class="resource-icon teal-gradient mx-auto mb-3">
                                            <i class="bi bi-calendar-check"></i>
                                        </div>
                                        <h6 class="text-muted">Réservations</h6>
                                        <h2 class="text-primary"><?php echo count($bookings); ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <div class="resource-icon blue-gradient mx-auto mb-3">
                                            <i class="bi bi-check-circle"></i>
                                        </div>
                                        <h6 class="text-muted">Disponibles</h6>
                                        <h2 class="text-primary">
                                            <?php 
                                            $available = count(array_filter($resources, fn($r) => $r['statut'] === 'Disponible'));
                                            echo $available;
                                            ?>
                                        </h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <div class="resource-icon yellow-gradient mx-auto mb-3">
                                            <i class="bi bi-percent"></i>
                                        </div>
                                        <h6 class="text-muted">Taux d'utilisation</h6>
                                        <h2 class="text-primary">
                                            <?php 
                                            $usage = count($resources) > 0 ? round((count($bookings) / count($resources)) * 100) : 0;
                                            echo $usage . '%';
                                            ?>
                                        </h2>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Ressources les plus réservées</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php 
                                        $resourceBookingCounts = [];
                                        foreach ($bookings as $booking) {
                                            $resourceId = $booking['resource_id'];
                                            if (!isset($resourceBookingCounts[$resourceId])) {
                                                $resourceBookingCounts[$resourceId] = ['name' => $booking['resource_nom'], 'count' => 0];
                                            }
                                            $resourceBookingCounts[$resourceId]['count']++;
                                        }
                                        arsort($resourceBookingCounts);
                                        ?>
                                        <?php if (empty($resourceBookingCounts)): ?>
                                            <p class="text-muted">Aucune réservation</p>
                                        <?php else: ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach (array_slice($resourceBookingCounts, 0, 5) as $resourceId => $data): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><?php echo htmlspecialchars($data['name']); ?></span>
                                                        <span class="badge badge-success"><?php echo $data['count']; ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Statut des ressources</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php 
                                        $statusCounts = [
                                            'Disponible' => 0,
                                            'Indisponible' => 0,
                                            'En maintenance' => 0
                                        ];
                                        foreach ($resources as $resource) {
                                            $statusCounts[$resource['statut']]++;
                                        }
                                        ?>
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="bi bi-check-circle text-success me-2"></i> Disponible</span>
                                                <span class="badge badge-success"><?php echo $statusCounts['Disponible']; ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="bi bi-x-circle text-danger me-2"></i> Indisponible</span>
                                                <span class="badge badge-danger"><?php echo $statusCounts['Indisponible']; ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span><i class="bi bi-exclamation-circle text-warning me-2"></i> En maintenance</span>
                                                <span class="badge badge-warning"><?php echo $statusCounts['En maintenance']; ?></span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bookable Resource Modal -->
    <div class="modal fade" id="bookableResourceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter une ressource réservable</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="add_resource.php" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="resourceName" class="form-label">Nom de la ressource</label>
                                <input type="text" class="form-control" id="resourceName" name="nom" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="resourceType" class="form-label">Type</label>
                                <select class="form-select" id="resourceType" name="type">
                                    <option value="Salle">Salle</option>
                                    <option value="Matériel">Matériel</option>
                                    <option value="Véhicule">Véhicule</option>
                                    <option value="Autre">Autre</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="resourceDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="resourceDescription" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="resourceCapacity" class="form-label">Capacité/Quantité</label>
                                <input type="number" class="form-control" id="resourceCapacity" name="quantite_totale" min="1" value="1">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="resourceLocation" class="form-label">Localisation</label>
                                <input type="text" class="form-control" id="resourceLocation" placeholder="Optionnel">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date_debut" class="form-label">Disponible du</label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="date_fin" class="form-label">Disponible jusqu'au</label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="image" class="form-label">Image (optionnel)</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <small class="text-muted">Max 2MB. Formats: JPG, PNG, GIF</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Resource Modal -->
    <div class="modal fade" id="editResourceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier la ressource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="edit_resource.php" enctype="multipart/form-data">
                    <div class="modal-body" id="editResourceContent">
                        <p class="text-muted">Chargement...</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Book Resource Modal -->
    <div class="modal fade" id="bookResourceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nouvelle réservation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="book_resource.php">
                    <div class="modal-body">
                        <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                        
                        <div class="mb-3">
                            <label for="resource_id" class="form-label">Ressource *</label>
                            <select class="form-select" id="resource_id" name="resource_id" required>
                                <option value="">-- Sélectionner une ressource --</option>
                                <?php foreach ($resources as $resource): ?>
                                    <option value="<?php echo $resource['id']; ?>">
                                        <?php echo htmlspecialchars($resource['nom']); ?> (<?php echo $resource['type']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date_debut_booking" class="form-label">Date & Heure de début *</label>
                                <input type="datetime-local" class="form-control" id="date_debut_booking" name="date_debut" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="date_fin_booking" class="form-label">Date & Heure de fin *</label>
                                <input type="datetime-local" class="form-control" id="date_fin_booking" name="date_fin" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>

                        <div id="conflictWarning" class="alert alert-warning d-none">
                            <i class="bi bi-exclamation-triangle"></i> <strong>Conflit détecté!</strong> Cette ressource est déjà réservée pour cette période.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Réserver</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editBookableResource(id) {
            fetch('get_resource.php?id=' + id + '&event_id=<?php echo $eventId; ?>')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('editResourceContent').innerHTML = html;
                    const modal = new bootstrap.Modal(document.getElementById('editResourceModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('editResourceContent').innerHTML = '<p class="text-danger">Erreur lors du chargement.</p>';
                });
        }

        function deleteResource(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette ressource ?')) {
            }
        }

        function viewAvailability(id) {
            alert('Affichage du calendrier de disponibilité pour la ressource ' + id);
        }

        let notificationDropdown;
        
        document.addEventListener('DOMContentLoaded', function() {
            notificationDropdown = new bootstrap.Dropdown(document.querySelector('[data-bs-toggle="dropdown"]'));
            
            document.getElementById('notificationDropdown').addEventListener('show.bs.dropdown', function () {
                loadNotifications();
            });
        });

        function loadNotifications() {
            const notificationList = document.getElementById('notificationList');
            
            notificationList.innerHTML = `
                <div class="text-center p-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>
            `;
            
            fetch('ajax_handler.php?action=get_notifications')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayNotifications(data.notifications);
                        updateNotificationCount(data.unread_count);
                    } else {
                        notificationList.innerHTML = '<div class="text-center p-3 text-muted">Erreur lors du chargement</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                    notificationList.innerHTML = '<div class="text-center p-3 text-muted">Erreur de connexion</div>';
                });
        }

        function displayNotifications(notifications) {
            const notificationList = document.getElementById('notificationList');
            
            if (notifications.length === 0) {
                notificationList.innerHTML = '<div class="text-center p-3 text-muted">Aucune notification</div>';
                return;
            }
            
            let html = '';
            notifications.forEach(notification => {
                const createdDate = new Date(notification.created_at);
                const formattedDate = createdDate.toLocaleDateString('fr-FR') + ' ' + createdDate.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
                
                html += `
                    <li class="notification-item ${notification.is_read ? 'read' : 'unread'}" style="border-bottom: 1px solid #f0f0f0;">
                        <a href="#" class="dropdown-item text-decoration-none" onclick="handleNotificationClick(${notification.id}, '${notification.link || '#'}'); return false;" style="padding: 12px 16px; ${!notification.is_read ? 'background-color: #f8f9fa;' : ''}">
                            <div class="d-flex">
                                <div class="flex-grow-1">
                                    <div class="fw-bold ${!notification.is_read ? 'text-primary' : 'text-muted'}" style="font-size: 14px;">
                                        ${notification.title}
                                    </div>
                                    <div class="text-muted small" style="font-size: 13px;">
                                        ${notification.message}
                                    </div>
                                    <div class="text-muted small" style="font-size: 12px; margin-top: 4px;">
                                        <i class="bi bi-clock me-1"></i>${formattedDate}
                                    </div>
                                </div>
                                ${!notification.is_read ? '<div class="ms-2"><div class="bg-primary rounded-circle" style="width: 8px; height: 8px;"></div></div>' : ''}
                            </div>
                        </a>
                    </li>
                `;
            });
            
            notificationList.innerHTML = html;
        }

        function updateNotificationCount(count) {
            const badge = document.getElementById('notificationCount');
            badge.textContent = count;
            
            if (count > 0) {
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }

        function handleNotificationClick(notificationId, link) {
            fetch('ajax_handler.php?action=mark_notification_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'notification_id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const currentCount = parseInt(document.getElementById('notificationCount').textContent);
                    updateNotificationCount(Math.max(0, currentCount - 1));
                    
                    if (link && link !== '#') {
                        window.location.href = link;
                    }
                }
            })
            .catch(error => console.error('Error marking notification as read:', error));
        }

        function markAllAsRead() {
            fetch('ajax_handler.php?action=mark_all_notifications_read', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationCount(0);
                    loadNotifications(); // Reload to update UI
                }
            })
            .catch(error => console.error('Error marking all notifications as read:', error));
        }

        document.getElementById('date_debut_booking')?.addEventListener('change', checkConflicts);
        document.getElementById('date_fin_booking')?.addEventListener('change', checkConflicts);
        document.getElementById('resource_id')?.addEventListener('change', checkConflicts);

        function checkConflicts() {
            const resourceId = document.getElementById('resource_id').value;
            const dateDebut = document.getElementById('date_debut_booking').value;
            const dateFin = document.getElementById('date_fin_booking').value;
            const warningDiv = document.getElementById('conflictWarning');

            if (!resourceId || !dateDebut || !dateFin) return;

            fetch('check_booking_conflict.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'resource_id=' + resourceId + '&date_debut=' + dateDebut + '&date_fin=' + dateFin
            })
            .then(response => response.json())
            .then(data => {
                if (data.conflict) {
                    warningDiv.classList.remove('d-none');
                } else {
                    warningDiv.classList.add('d-none');
                }
            });
        }
    </script>
</body>
</html>