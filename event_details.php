<?php
session_start();

require 'database.php';
require_once 'notifications.php';
require_once 'role_check.php';

$event = null;
$organizers = [];
$registrationCount = 0;
$isRegistered = false;
$isEventAdmin = false;
$isEventOrganizer = false;
$isEventOwner = false;
$error = '';
$success = '';

$eventId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($eventId <= 0) {
    $error = 'ID d\'événement invalide.';
} else {
    try {
        $pdo = getDatabaseConnection();
        
        $stmt = $pdo->prepare('SELECT id, titre, description, date, lieu, nb_max_participants, created_by FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();
        
        if (!$event) {
            $error = 'Événement non trouvé.';
        } else {
            $stmt = $pdo->prepare('
                SELECT u.id, u.nom, u.email, er.role 
                FROM event_roles er 
                JOIN users u ON er.user_id = u.id 
                WHERE er.event_id = ? 
                ORDER BY er.role DESC, u.nom ASC
            ');
            $stmt->execute([$eventId]);
            $organizers = $stmt->fetchAll();
            
            $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM registrations WHERE event_id = ?');
            $stmt->execute([$eventId]);
            $result = $stmt->fetch();
            $registrationCount = (int) $result['count'];
            
            if (isset($_SESSION['user_id'])) {
                $stmt = $pdo->prepare('SELECT id FROM registrations WHERE user_id = ? AND event_id = ?');
                $stmt->execute([$_SESSION['user_id'], $eventId]);
                $isRegistered = (bool) $stmt->fetch();
                
                $isEventAdmin = isEventAdmin($_SESSION['user_id'], $eventId);
                $isEventOrganizer = isEventOrganizer($_SESSION['user_id'], $eventId);

                if ($event && isset($event['created_by'])) {
                    $isEventOwner = ($_SESSION['user_id'] == $event['created_by']);
                }
            }
            
            $showLimitedInfo = false;
            if (!isset($_SESSION['user_id'])) {
                $showLimitedInfo = true;
            } elseif (!$isEventAdmin && !$isEventOrganizer && !$isEventOwner) {
                $showLimitedInfo = true;
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attend'])) {
                if (!isset($_SESSION['user_id'])) {
                    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
                    header('Location: login.php');
                    exit;
                }
                
                if ($registrationCount >= $event['nb_max_participants']) {
                    $error = 'Cet événement est complet.';
                } elseif ($isRegistered) {
                    $error = 'Vous êtes déjà inscrit à cet événement.';
                } else {
                    try {
                        $stmt = $pdo->prepare('INSERT INTO registrations (user_id, event_id, date_inscription) VALUES (?, ?, NOW())');
                        $stmt->execute([$_SESSION['user_id'], $eventId]);
                        $success = 'Vous vous êtes inscrit avec succès à cet événement !';
                        $isRegistered = true;
                        
                        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM registrations WHERE event_id = ?');
                        $stmt->execute([$eventId]);
                        $result = $stmt->fetch();
                        $registrationCount = (int) $result['count'];

                        try {
                            $userName = '';
                            $userStmt = $pdo->prepare('SELECT nom FROM users WHERE id = ?');
                            $userStmt->execute([$_SESSION['user_id']]);
                            $userRow = $userStmt->fetch();
                            if ($userRow) {
                                $userName = $userRow['nom'];
                            }

                            $rolesStmt = $pdo->prepare('SELECT user_id FROM event_roles WHERE event_id = ? AND role IN ("admin", "organizer")');
                            $rolesStmt->execute([$eventId]);
                            $roleUsers = $rolesStmt->fetchAll();
                            foreach ($roleUsers as $ru) {
                                $msg = ($userName ? $userName : 'Un participant') . ' s\'est inscrit à l\'événement "' . $event['titre'] . '".';
                                createNotification($ru['user_id'], 'Nouveau participant', $msg, $eventId);
                            }
                        } catch (Exception $inner) {
                            error_log('Notification error on registration: ' . $inner->getMessage());
                        }
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                            $error = 'Vous êtes déjà inscrit à cet événement.';
                            $isRegistered = true;
                        } else {
                            $error = 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.';
                        }
                    }
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching event details: " . $e->getMessage());
        $error = 'Une erreur est survenue lors de la récupération des détails de l\'événement.';
    }
}

$unreadCount = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $unreadNotifications = getUnreadNotifications($_SESSION['user_id']);
        $unreadCount = count($unreadNotifications);
    } catch (Exception $e) {
        error_log('Error getting notifications: ' . $e->getMessage());
        $unreadCount = 0;
    }
}

$pageTitle = 'EventUp - ' . ($event ? $event['titre'] : 'Détails de l\'événement');
$useAppHeader = true;
$activeNav = isset($_SESSION['user_id']) ? 'browse' : '';
$appContentClass = 'container-fluid event-details-wrapper';
$mainColumnClass = (!$error && $event) ? 'col-lg-9 px-md-4 main-content' : 'col-12 px-md-4 main-content';
$additionalHeadContent = <<<HTML
<style>
    :root {
        --primary-orange: #D94A00;
        --primary-teal: #1B5E52;
        --light-orange: #ff6b2c;
        --bg-light: #f5f7fa;
        --border-color: #e1e8ed;
        --text-dark: #2c3e50;
        --text-muted: #657786;
    }

    .event-details-wrapper {
        padding: 96px 24px 64px;
        background: var(--bg-light);
        min-height: 100vh;
        color: var(--text-dark);
    }

    .main-content {
        padding-top: 16px;
        padding-bottom: 48px;
    }

    .page-title {
        font-size: clamp(1.8rem, 2.4vw, 2.5rem);
        font-weight: 700;
        color: var(--primary-teal);
    }

    .event-info-grid {
        display: grid;
        gap: 24px;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        margin-bottom: 24px;
    }

    .info-item {
        display: flex;
        gap: 16px;
        padding: 20px;
        background: #fff;
        border: 1px solid var(--border-color);
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }

    .info-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        background: rgba(217, 74, 0, 0.1);
        color: var(--primary-orange);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .info-content h6 {
        font-weight: 600;
        margin-bottom: 4px;
        color: var(--text-dark);
    }

    .info-content p {
        margin: 0;
        color: var(--text-muted);
    }

    .description-section {
        background: #fff;
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
    }

    .description-section h5 {
        font-weight: 700;
        color: var(--primary-teal);
        margin-bottom: 12px;
    }

    .stats-card {
        border-radius: 16px;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
    }

    .admin-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 1rem;
    }

    .role-admin {
        background: rgba(217, 74, 0, 0.15);
        color: var(--primary-orange);
    }

    .role-organizer {
        background: rgba(27, 94, 82, 0.12);
        color: var(--primary-teal);
    }

    @media (max-width: 991px) {
        .event-details-wrapper {
            padding: 88px 16px 48px;
        }
    }
</style>
HTML;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
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
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 11px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
}


.admin-profile-btn {
    display: flex;
    align-items: center;
    background: var(--bg-light);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 8px 12px;
    font-weight: 500;
    color: var(--text-dark);
    transition: all 0.3s ease;
}


.admin-profile-btn:hover {
    background: white;
    border-color: var(--primary-orange);
    color: var(--primary-orange);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}


.admin-avatar {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 14px;
}

        <?php echo $additionalHeadContent; ?>
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
                <?php if (isset($_SESSION['user_id'])): ?>
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
                <?php else: ?>
                <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Connexion
                </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

<?php

if (($error && !$event) || !$event): ?>
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($error ?: 'Événement non trouvé.'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-house me-2"></i>Back to Dashboard
                </a>
                <a href="index.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left me-2"></i>Retour aux événements
                </a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row g-0">
        <?php include 'event_nav.php'; ?>
        <main class="<?php echo $mainColumnClass; ?>">
            <?php if ($error): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="page-title mb-0"><?php echo htmlspecialchars($event['titre']); ?></h1>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-house me-2"></i>Retour au tableau de bord
                </a>
            </div>

            <?php if ($showLimitedInfo): ?>
                <!-- Limited view for non-organizers and non-logged-in users -->
                <div class="event-info-grid">
                    <div class="info-item">
                        <div class="info-icon"><i class="bi bi-calendar-event"></i></div>
                        <div class="info-content">
                            <h6>Date &amp; Heure</h6>
                            <p><?php echo htmlspecialchars((new DateTime($event['date']))->format('d/m/Y H:i')); ?></p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="bi bi-people"></i></div>
                        <div class="info-content">
                            <h6>Places restantes</h6>
                            <p><?php echo ((int) $event['nb_max_participants'] - $registrationCount); ?> places restantes</p>
                        </div>
                    </div>
                </div>

                <div class="description-section">
                    <h5><i class="bi bi-file-text me-2"></i>Description</h5>
                    <p><?php echo nl2br(htmlspecialchars($event['description'] ?? 'Aucune description disponible.')); ?></p>
                </div>
            <?php else: ?>
                <!-- Full view for organizers and admins -->
                <div class="event-info-grid">
                    <div class="info-item">
                        <div class="info-icon"><i class="bi bi-calendar-event"></i></div>
                        <div class="info-content">
                            <h6>Date &amp; Heure</h6>
                            <p><?php echo htmlspecialchars((new DateTime($event['date']))->format('d/m/Y H:i')); ?></p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="bi bi-geo-alt"></i></div>
                        <div class="info-content">
                            <h6>Lieu</h6>
                            <p><?php echo htmlspecialchars($event['lieu']); ?></p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="bi bi-people"></i></div>
                        <div class="info-content">
                            <h6>Capacité</h6>
                            <p><?php echo $registrationCount; ?> / <?php echo (int) $event['nb_max_participants']; ?> inscrits</p>
                        </div>
                    </div>
                </div>

                <div class="description-section">
                    <h5><i class="bi bi-file-text me-2"></i>Description</h5>
                    <p><?php echo nl2br(htmlspecialchars($event['description'] ?? 'Aucune description disponible.')); ?></p>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Progression des inscriptions</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="fw-semibold text-dark">Utilisation de la capacité</span>
                                <span class="text-muted"><?php echo (int) round(($registrationCount / (int) $event['nb_max_participants']) * 100); ?>%</span>
                            </div>
                            <div class="progress" style="height: 14px;">
                                <div class="progress-bar bg-gradient" style="width: <?php echo ($registrationCount / (int) $event['nb_max_participants']) * 100; ?>%"></div>
                            </div>
                        </div>
                        <?php if ($registrationCount >= $event['nb_max_participants']): ?>
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i><strong>L'événement est complet</strong>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">
                                <i class="bi bi-info-circle me-2"></i><?php echo ((int) $event['nb_max_participants'] - $registrationCount); ?> places restantes
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($organizers)): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-person-check me-2"></i>Organisateurs</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($organizers as $organizer): ?>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center p-3 border rounded-4">
                                            <div class="admin-avatar me-3"><?php echo strtoupper(substr($organizer['nom'], 0, 1)); ?></div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($organizer['nom']); ?></h6>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($organizer['email']); ?></small>
                                            </div>
                                            <span class="badge <?php echo $organizer['role'] === 'admin' ? 'role-admin' : 'role-organizer'; ?>">
                                                <?php echo htmlspecialchars(ucfirst($organizer['role'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (($isEventOwner || $isEventOrganizer) && !$showLimitedInfo): ?>
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-kanban me-2"></i>Gestion de l'événement</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <?php if ($isEventOrganizer): ?>
                                <div class="col-sm-6">
                                    <a href="communication_hub.php?event_id=<?php echo $eventId; ?>" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-chat-dots me-2"></i>Centre de Communication
                                    </a>
                                </div>
                                <div class="col-sm-6">
                                    <a href="resources.php?event_id=<?php echo $eventId; ?>" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-tools me-2"></i>Gestion des Ressources
                                    </a>
                                </div>
                                <div class="col-sm-6">
                                    <a href="budget.php?event_id=<?php echo $eventId; ?>" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-wallet2 me-2"></i>Gestion du Budget
                                    </a>
                                </div>
                                <div class="col-sm-6">
                                    <a href="event_participants.php?id=<?php echo $eventId; ?>" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-person-check me-2"></i>Gestion des Participants
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if ($isEventAdmin): ?>
                                <div class="col-sm-6">
                                    <a href="organizers.php?event_id=<?php echo $eventId; ?>" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-people me-2"></i>Gestion des Organisateurs
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if ($isEventOwner): ?>
                                <div class="col-sm-6">
                                    <a href="event_permissions.php?event_id=<?php echo $eventId; ?>" class="btn btn-outline-primary w-100">
                                        <i class="bi bi-shield-lock me-2"></i>Gestion des Permissions
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="card">
                <div class="card-body">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <p class="text-muted mb-3"><i class="bi bi-info-circle me-2"></i>Connectez-vous pour participer à cet événement.</p>
                        <a href="login.php" class="btn btn-primary w-100">Se connecter pour participer</a>
                    <?php elseif ($isRegistered): ?>
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-check-circle me-2"></i><strong>Vous êtes inscrit</strong>
                        </div>
                    <?php elseif ($registrationCount >= $event['nb_max_participants']): ?>
                        <button class="btn btn-secondary w-100" disabled>
                            <i class="bi bi-lock me-2"></i>Événement complet
                        </button>
                    <?php else: ?>
                        <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#attendModal">
                            <i class="bi bi-check-circle me-2"></i>Participer à l'événement
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <?php if ($event): ?>
        <div class="modal fade" id="attendModal" tabindex="-1" aria-labelledby="attendModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="attendModalLabel">Confirmer l'inscription</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Êtes-vous sûr de vouloir vous inscrire à cet événement ?</p>
                        <div class="p-3 bg-light rounded-3">
                            <h6 class="mb-2"><?php echo htmlspecialchars($event['titre']); ?></h6>
                            <small class="text-muted d-block">
                                <i class="bi bi-calendar me-2"></i><?php echo htmlspecialchars((new DateTime($event['date']))->format('d/m/Y H:i')); ?>
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="attend" value="1" class="btn btn-primary">Confirmer l'inscription</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php require 'footer.php'; ?>
