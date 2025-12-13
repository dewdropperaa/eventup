<?php
/**
 * Event Participants Page - Modernized
 * Displays all participants for an event with modern UI
 * Accessible to event organizers and admins
 */

session_start();
require 'database.php';
require_once 'role_check.php';
require_once 'notifications.php';

// Check if user is logged in
requireLogin();

// Get event ID from URL
$eventId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($eventId <= 0) {
    header('Location: organizer_dashboard.php');
    exit;
}

// Check if user has permission to view participants
if (!isEventOrganizer($_SESSION['user_id'], $eventId)) {
    header('Location: event_details.php?id=' . $eventId);
    exit;
}

// Get database connection
$pdo = getDatabaseConnection();

// Initialize variables
$error = '';
$success = '';

// Get unread notifications count
$unreadCount = 0;
try {
    $unreadNotifications = getUnreadNotifications($_SESSION['user_id']);
    $unreadCount = count($unreadNotifications);
} catch (Exception $e) {
    error_log('Error getting notifications: ' . $e->getMessage());
    $unreadCount = 0;
}

// Fetch event details
try {
    $stmt = $pdo->prepare('SELECT id, titre, lieu, date, nb_max_participants, created_by FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event) {
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    error_log('Error fetching event: ' . $e->getMessage());
    $error = 'Error loading event.';
}

// Determine user roles for navigation
$isEventOwner = isset($_SESSION['user_id']) && $event && $_SESSION['user_id'] == $event['created_by'];
$isEventAdmin = isEventAdmin($_SESSION['user_id'], $eventId);
$isEventOrganizer = isEventOrganizer($_SESSION['user_id'], $eventId);

// Fetch participants
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.nom, u.email, r.date_inscription,
               (SELECT COUNT(*) FROM registrations WHERE user_id = u.id) as total_events
        FROM registrations r
        INNER JOIN users u ON r.user_id = u.id
        WHERE r.event_id = ?
        ORDER BY r.date_inscription DESC
    ");
    $stmt->execute([$eventId]);
    $participants = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching participants: ' . $e->getMessage());
    $participants = [];
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventUp - Gestion des Participants</title>
    
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
.sidebar {
    position: fixed;
    top: 76px;
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 24px 0;
    background: white;
    border-right: 1px solid var(--border-color);
    overflow-y: auto;
}


.sidebar-sticky {
    position: sticky;
    top: 0;
}


.sidebar .nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    color: var(--text-muted);
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 500;
    border-left: 4px solid transparent;
    margin: 2px 0;
}


.sidebar .nav-link i {
    font-size: 18px;
    width: 20px;
    text-align: center;
}


.sidebar .nav-link:hover {
    background: #f0f3f7;
    color: var(--text-dark);
}


.sidebar .nav-link.active {
    background: #fff8f0;
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
   Participant Cards
   ============================ */
.participant-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    transition: all 0.3s ease;
    border: 1px solid var(--border-color);
    height: 100%;
    position: relative;
    overflow: hidden;
}


.participant-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-orange), var(--light-orange));
    opacity: 0;
    transition: opacity 0.3s ease;
}


.participant-card:hover::before {
    opacity: 1;
}


.participant-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.12);
}


.participant-avatar {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
    flex-shrink: 0;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
}


.participant-card:hover .participant-avatar {
    transform: scale(1.1);
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


.yellow-gradient {
    background: linear-gradient(135deg, #FFD700, #FFA500);
}


.participant-name {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary-teal);
    margin-bottom: 4px;
}


.participant-email {
    color: var(--text-muted);
    font-size: 14px;
    margin-bottom: 12px;
    word-break: break-all;
}


.participant-details {
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


.badge-info {
    background: rgba(74, 144, 226, 0.15);
    color: #4A90E2;
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


/* ===========================
   Progress Bar
   ============================ */
.progress {
    height: 12px;
    border-radius: 6px;
    background-color: var(--border-color);
    overflow: hidden;
}


.progress-bar {
    background: linear-gradient(90deg, var(--primary-teal), var(--light-teal));
    border-radius: 6px;
    transition: width 0.6s ease;
    position: relative;
}


.progress-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    animation: shimmer 2s infinite;
}


@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}


/* ===========================
   Statistics Cards
   ============================ */
.stats-card {
    background: white;
    border-radius: 16px;
    padding: 32px 24px;
    text-align: center;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
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
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
}


.stats-icon {
    width: 72px;
    height: 72px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 32px;
    margin: 0 auto 20px;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
}


.stats-number {
    font-size: 36px;
    font-weight: 800;
    color: var(--primary-teal);
    margin-bottom: 8px;
    line-height: 1;
}


.stats-label {
    font-size: 14px;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
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
    
    .participant-avatar {
        width: 48px;
        height: 48px;
        font-size: 22px;
    }
    
    .participant-name {
        font-size: 16px;
    }
    
    .stats-icon {
        width: 56px;
        height: 56px;
        font-size: 24px;
    }
    
    .stats-number {
        font-size: 28px;
    }
}


@media (max-width: 575px) {
    .main-content {
        padding: 16px;
    }
    
    .nav-tabs .nav-link span {
        display: none;
    }
    
    .participant-card .d-flex.gap-2 {
        flex-direction: column;
        gap: 8px !important;
    }
    
    .participant-card .btn-sm {
        width: 100%;
    }


    .btn-primary {
        width: 100%;
        margin-bottom: 8px;
    }
    
    .stats-card {
        padding: 24px 16px;
    }
    
    .stats-icon {
        width: 48px;
        height: 48px;
        font-size: 20px;
        margin-bottom: 16px;
    }
    
    .stats-number {
        font-size: 24px;
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
                    <h1 class="page-title">Gestion des participants</h1>
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
                <ul class="nav nav-tabs mb-4" id="participantTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="participants-tab" data-bs-toggle="tab" data-bs-target="#participants" type="button" role="tab">
                            <i class="bi bi-people me-2"></i>Participants
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="statistics-tab" data-bs-toggle="tab" data-bs-target="#statistics" type="button" role="tab">
                            <i class="bi bi-bar-chart me-2"></i>Statistiques
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="export-tab" data-bs-toggle="tab" data-bs-target="#export" type="button" role="tab">
                            <i class="bi bi-download me-2"></i>Export
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="participantTabContent">
                    <!-- Participants Tab -->
                    <div class="tab-pane fade show active" id="participants" role="tabpanel">
                        <div class="mb-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h5 class="mb-1">Liste des participants</h5>
                                    <p class="text-muted mb-0">
                                        <span class="badge bg-info"><?php echo count($participants); ?></span> participants inscrits
                                        sur <span class="badge bg-secondary"><?php echo htmlspecialchars($event['nb_max_participants']); ?></span> places disponibles
                                    </p>
                                </div>
                                <div>
                                    <button class="btn btn-outline-primary btn-sm" onclick="exportParticipants()">
                                        <i class="bi bi-download me-1"></i>Exporter
                                    </button>
                                </div>
                            </div>
                        </div>

                        <?php if (empty($participants)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-people display-1 text-muted"></i>
                                <h4 class="text-muted mt-3">Aucun participant</h4>
                                <p class="text-muted">Personne ne s'est encore inscrit à cet événement.</p>
                            </div>
                        <?php else: ?>
                            <!-- Participants Grid -->
                            <div class="row g-4 mb-4">
                                <?php foreach ($participants as $index => $participant): 
                                    // Determine avatar color based on index
                                    $gradientClass = 'orange-gradient';
                                    if ($index % 3 === 1) $gradientClass = 'teal-gradient';
                                    if ($index % 3 === 2) $gradientClass = 'blue-gradient';
                                ?>
                                    <div class="col-xl-4 col-md-6">
                                        <div class="participant-card">
                                            <div class="d-flex align-items-start mb-3">
                                                <div class="participant-avatar <?php echo $gradientClass; ?> me-3">
                                                    <?php echo strtoupper(substr($participant['nom'], 0, 1)); ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h5 class="participant-name"><?php echo htmlspecialchars($participant['nom']); ?></h5>
                                                    <p class="participant-email">
                                                        <a href="mailto:<?php echo htmlspecialchars($participant['email']); ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($participant['email']); ?>
                                                        </a>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="participant-details mb-3">
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar-check me-1"></i>Inscrit le <?php echo date('d/m/Y', strtotime($participant['date_inscription'])); ?><br>
                                                    <i class="bi bi-trophy me-1"></i><?php echo (int)$participant['total_events']; ?> événement(s) suivi(s)
                                                </small>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-outline-primary flex-grow-1" onclick="viewParticipantDetails(<?php echo $participant['id']; ?>)">
                                                    <i class="bi bi-eye"></i> Détails
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary" onclick="sendEmailToParticipant('<?php echo htmlspecialchars($participant['email']); ?>')">
                                                    <i class="bi bi-envelope"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Table View -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Vue tableau</h5>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Nom</th>
                                                <th>Email</th>
                                                <th class="text-center">Événements</th>
                                                <th class="text-end">Date d'inscription</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($participants as $participant): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($participant['nom']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <a href="mailto:<?php echo htmlspecialchars($participant['email']); ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($participant['email']); ?>
                                                        </a>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge badge-info">
                                                            <?php echo (int)$participant['total_events']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end">
                                                        <small class="text-muted">
                                                            <?php echo date('M d, Y', strtotime($participant['date_inscription'])); ?>
                                                        </small>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-outline-primary" onclick="viewParticipantDetails(<?php echo $participant['id']; ?>)">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-primary" onclick="sendEmailToParticipant('<?php echo htmlspecialchars($participant['email']); ?>')">
                                                                <i class="bi bi-envelope"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Statistics Tab -->
                    <div class="tab-pane fade" id="statistics" role="tabpanel">
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <div class="stats-icon orange-gradient">
                                        <i class="bi bi-people"></i>
                                    </div>
                                    <div class="stats-number"><?php echo count($participants); ?></div>
                                    <div class="stats-label">Total Participants</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <div class="stats-icon teal-gradient">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                    <div class="stats-number"><?php echo (int)$event['nb_max_participants'] - count($participants); ?></div>
                                    <div class="stats-label">Places disponibles</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <div class="stats-icon blue-gradient">
                                        <i class="bi bi-percent"></i>
                                    </div>
                                    <div class="stats-number">
                                        <?php 
                                        $fillRate = round((count($participants) / (int)$event['nb_max_participants']) * 100);
                                        echo $fillRate . '%';
                                        ?>
                                    </div>
                                    <div class="stats-label">Taux de remplissage</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <div class="stats-icon yellow-gradient">
                                        <i class="bi bi-graph-up"></i>
                                    </div>
                                    <div class="stats-number">
                                        <?php 
                                        $avgEvents = count($participants) > 0 ? round(array_sum(array_column($participants, 'total_events')) / count($participants), 1) : 0;
                                        echo $avgEvents;
                                        ?>
                                    </div>
                                    <div class="stats-label">Moyenne événements</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Progression de l'inscription</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Participants inscrits</span>
                                                <strong><?php echo count($participants); ?>/<?php echo htmlspecialchars($event['nb_max_participants']); ?></strong>
                                            </div>
                                            <div class="progress" role="progressbar" 
                                                 aria-valuenow="<?php echo count($participants); ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="<?php echo (int)$event['nb_max_participants']; ?>">
                                                <div class="progress-bar" 
                                                     style="width: <?php echo $fillRate; ?>%">
                                                </div>
                                            </div>
                                        </div>
                                        <p class="mb-0 text-muted small">
                                            <strong><?php echo (int)$event['nb_max_participants'] - count($participants); ?></strong> places restantes
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Informations sur l'événement</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-2">
                                            <strong>Date & Heure:</strong><br>
                                            <small class="text-muted"><?php echo date('F j, Y \a\t g:i A', strtotime($event['date'])); ?></small>
                                        </p>
                                        <p class="mb-2">
                                            <strong>Lieu:</strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($event['lieu']); ?></small>
                                        </p>
                                        <p class="mb-0">
                                            <strong>Capacité maximale:</strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($event['nb_max_participants']); ?> participants</small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Export Tab -->
                    <div class="tab-pane fade" id="export" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Exporter les participants</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted mb-4">Téléchargez la liste des participants sous différents formats.</p>
                                        
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-primary" onclick="exportAsCSV()">
                                                <i class="bi bi-file-earmark-csv me-2"></i>Exporter en CSV
                                            </button>
                                            <button class="btn btn-outline-primary" onclick="exportAsExcel()">
                                                <i class="bi bi-file-earmark-excel me-2"></i>Exporter en Excel
                                            </button>
                                            <button class="btn btn-outline-primary" onclick="exportAsPDF()">
                                                <i class="bi bi-file-earmark-pdf me-2"></i>Exporter en PDF
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Envoyer un email</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted mb-4">Envoyer un email à tous les participants de l'événement.</p>
                                        
                                        <form id="bulkEmailForm">
                                            <div class="mb-3">
                                                <label for="emailSubject" class="form-label">Sujet</label>
                                                <input type="text" class="form-control" id="emailSubject" placeholder="Entrez le sujet de l'email">
                                            </div>
                                            <div class="mb-3">
                                                <label for="emailMessage" class="form-label">Message</label>
                                                <textarea class="form-control" id="emailMessage" rows="4" placeholder="Entrez votre message..."></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="bi bi-send me-2"></i>Envoyer à tous les participants
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Notification System
        let notificationDropdown;
        
        document.addEventListener('DOMContentLoaded', function() {
            notificationDropdown = new bootstrap.Dropdown(document.querySelector('[data-bs-toggle="dropdown"]'));
            
            // Load notifications when dropdown is shown
            document.getElementById('notificationDropdown').addEventListener('show.bs.dropdown', function () {
                loadNotifications();
            });
        });

        function loadNotifications() {
            const notificationList = document.getElementById('notificationList');
            
            // Show loading spinner
            notificationList.innerHTML = `
                <div class="text-center p-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>
            `;
            
            // Fetch notifications via AJAX
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
            // Mark notification as read
            fetch('ajax_handler.php?action=mark_notification_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'notification_id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update notification count
                    const currentCount = parseInt(document.getElementById('notificationCount').textContent);
                    updateNotificationCount(Math.max(0, currentCount - 1));
                    
                    // Navigate to link if provided
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

        // Participant Management Functions
        function viewParticipantDetails(participantId) {
            // In a real implementation, this would open a modal with participant details
            alert('Affichage des détails du participant ID: ' + participantId);
        }

        function sendEmailToParticipant(email) {
            window.location.href = 'mailto:' + email;
        }

        function exportParticipants() {
            // Switch to export tab
            const exportTab = new bootstrap.Tab(document.getElementById('export-tab'));
            exportTab.show();
        }

        function exportAsCSV() {
            const eventId = <?php echo $eventId; ?>;
            window.location.href = 'export_participants.php?event_id=' + eventId + '&format=csv';
        }

        function exportAsExcel() {
            const eventId = <?php echo $eventId; ?>;
            window.location.href = 'export_participants.php?event_id=' + eventId + '&format=excel';
        }

        function exportAsPDF() {
            const eventId = <?php echo $eventId; ?>;
            window.location.href = 'export_participants.php?event_id=' + eventId + '&format=pdf';
        }

        // Bulk email form submission
        document.getElementById('bulkEmailForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const subject = document.getElementById('emailSubject').value;
            const message = document.getElementById('emailMessage').value;
            const eventId = <?php echo $eventId; ?>;
            
            if (!subject || !message) {
                alert('Veuillez remplir tous les champs');
                return;
            }
            
            // Send bulk email via AJAX
            fetch('send_bulk_email.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'event_id=' + eventId + '&subject=' + encodeURIComponent(subject) + '&message=' + encodeURIComponent(message)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Email envoyé avec succès à ' + data.count + ' participants');
                    document.getElementById('bulkEmailForm').reset();
                } else {
                    alert('Erreur lors de l\'envoi: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error sending bulk email:', error);
                alert('Erreur de connexion');
            });
        });
    </script>
</body>
</html>
