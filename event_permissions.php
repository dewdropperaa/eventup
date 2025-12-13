<?php
session_start();
require 'database.php';
require 'role_check.php';

requireLogin();

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$error = '';
$event = null;
$organizers = [];
$permissions = [];

if ($eventId <= 0) {
    $error = 'ID événement invalide.';
} else {
    $pdo = getDatabaseConnection();
    
    $stmt = $pdo->prepare('SELECT titre, created_by FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();

    if (!$event || $event['created_by'] != $_SESSION['user_id']) {
        $error = 'Accès non autorisé. Vous n\'êtes pas le propriétaire de cet événement.';
        $event = null; // Don't display the page content
    } else {
        $isEventOwner = true;
        $isEventAdmin = isEventAdmin($_SESSION['user_id'], $eventId);
        $isEventOrganizer = isEventOrganizer($_SESSION['user_id'], $eventId);
        $stmt = $pdo->prepare('SELECT u.id, u.nom, u.email, eo.role FROM event_organizers eo JOIN users u ON eo.user_id = u.id WHERE eo.event_id = ? ORDER BY u.nom');
        $stmt->execute([$eventId]);
        $organizers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare('SELECT user_id, permission_name, is_allowed FROM event_permissions WHERE event_id = ?');
        $stmt->execute([$eventId]);
        $permsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($permsData as $p) {
            $permissions[$p['user_id']][$p['permission_name']] = $p['is_allowed'];
        }
    }
}

$permission_list = [
    'can_edit_budget' => 'Modifier le budget',
    'can_manage_resources' => 'Gérer les ressources',
    'can_invite_organizers' => 'Inviter des organisateurs',
    'can_publish_updates' => 'Publier des mises à jour'
];

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventUp - Gestion des Permissions</title>
    
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


.btn-outline-secondary {
    color: var(--text-muted);
    border-color: var(--border-color);
    border-radius: 10px;
    padding: 8px 16px;
    font-weight: 600;
    transition: all 0.3s ease;
}


.btn-outline-secondary:hover {
    background: var(--bg-light);
    border-color: var(--border-color);
    color: var(--text-dark);
}


/* ===========================
   Permission Cards
   ============================ */
.permission-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    transition: all 0.3s ease;
    border: 1px solid var(--border-color);
    height: 100%;
}


.permission-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
}


.permission-avatar {
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


.permission-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary-teal);
    margin-bottom: 8px;
}


.permission-email {
    color: var(--text-muted);
    font-size: 14px;
    margin-bottom: 12px;
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


.role-badge {
    background: rgba(27, 94, 82, 0.15);
    color: var(--primary-teal);
}


.role-admin {
    background: rgba(217, 74, 0, 0.15);
    color: var(--primary-orange);
}


.role-organizer {
    background: rgba(27, 94, 82, 0.15);
    color: var(--primary-teal);
}


/* ===========================
   Form Switch
   ============================ */
.form-check-input {
    width: 48px;
    height: 24px;
    border-radius: 12px;
    border: 2px solid var(--border-color);
    background-color: var(--bg-light);
    transition: all 0.3s ease;
}


.form-check-input:checked {
    background-color: var(--primary-orange);
    border-color: var(--primary-orange);
}


.form-check-input:focus {
    border-color: var(--primary-orange);
    box-shadow: 0 0 0 3px rgba(217, 74, 0, 0.1);
    outline: none;
}


.form-check-label {
    color: var(--text-dark);
    font-weight: 500;
    font-size: 14px;
    margin-left: 8px;
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
   Alerts
   ============================ */
.alert {
    border: none;
    border-radius: 12px;
    padding: 16px 20px;
    font-weight: 500;
}


.alert-danger {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}


.alert-info {
    background: rgba(74, 144, 226, 0.1);
    color: var(--info-blue);
}


.alert-warning {
    background: rgba(255, 215, 0, 0.1);
    color: #d4a000;
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


    .permission-avatar {
        width: 48px;
        height: 48px;
        font-size: 22px;
    }
    
    .permission-title {
        font-size: 16px;
    }
}


@media (max-width: 575px) {
    .main-content {
        padding: 16px;
    }


    .btn-outline-secondary {
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
                        <span class="notification-badge" id="notificationCount">0</span>
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
                                    <span class="visually-hidden">Chargement en cours...</span>
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
                        <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Mon profil</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Paramètres</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Se déconnecter</a></li>
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
                    <h1 class="page-title">Gestion des permissions</h1>
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

                <?php if (empty($organizers)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-people display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">Aucun organisateur</h4>
                        <p class="text-muted">Il n'y a aucun organisateur assigné à cet événement. <br>Vous pouvez inviter des organisateurs depuis la page de gestion de l'événement.</p>
                        <a href="event_details.php?id=<?php echo $eventId; ?>" class="btn btn-outline-primary mt-3">
                            <i class="bi bi-arrow-left me-2"></i>Retour à l'événement
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Organizers Grid -->
                    <div class="row g-4">
                        <?php foreach ($organizers as $organizer): ?>
                            <div class="col-xl-4 col-md-6">
                                <div class="permission-card">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="permission-avatar teal-gradient">
                                            <i class="bi bi-person-fill"></i>
                                        </div>
                                        <span class="badge role-<?php echo strtolower($organizer['role']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($organizer['role'])); ?>
                                        </span>
                                    </div>
                                    <h5 class="permission-title"><?php echo htmlspecialchars($organizer['nom']); ?></h5>
                                    <p class="permission-email">
                                        <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($organizer['email']); ?>
                                    </p>
                                    
                                    <hr class="my-3" style="border-color: var(--border-color);">
                                    
                                    <h6 class="mb-3" style="color: var(--text-dark); font-weight: 600;">
                                        <i class="bi bi-shield-check me-2"></i>Permissions
                                    </h6>
                                    
                                    <?php foreach ($permission_list as $perm_key => $perm_label): ?>
                                        <?php 
                                            $is_allowed = $permissions[$organizer['id']][$perm_key] ?? 0;
                                            $iconClass = 'bi-pencil-square';
                                            if ($perm_key === 'can_manage_resources') $iconClass = 'bi-box';
                                            elseif ($perm_key === 'can_invite_organizers') $iconClass = 'bi-person-plus';
                                            elseif ($perm_key === 'can_publish_updates') $iconClass = 'bi-broadcast';
                                            elseif ($perm_key === 'can_edit_budget') $iconClass = 'bi-wallet2';
                                        ?>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input permission-switch" type="checkbox" role="switch" 
                                                   id="perm-<?php echo $organizer['id']; ?>-<?php echo $perm_key; ?>" 
                                                   data-event-id="<?php echo $eventId; ?>" 
                                                   data-user-id="<?php echo $organizer['id']; ?>" 
                                                   data-permission-name="<?php echo $perm_key; ?>" 
                                                   <?php echo $is_allowed ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="perm-<?php echo $organizer['id']; ?>-<?php echo $perm_key; ?>">
                                                <i class="bi <?php echo $iconClass; ?> me-2"></i>
                                                <?php echo htmlspecialchars($perm_label); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4">
                        <a href="event_details.php?id=<?php echo $eventId; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Retour à l'événement
                        </a>
                    </div>
                <?php endif; ?>
        </main>
    </div>
</div>

<!-- Toast container for notifications -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
  <div id="permissionToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <strong class="me-auto">Notification système</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body">
      Permission mise à jour avec succès.
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const permissionSwitches = document.querySelectorAll('.permission-switch');
    
    permissionSwitches.forEach(switchElement => {
        switchElement.addEventListener('change', function() {
            const eventId = this.dataset.eventId;
            const userId = this.dataset.userId;
            const permissionName = this.dataset.permissionName;
            const isAllowed = this.checked ? 1 : 0;
            const originalState = this.checked;
            
            this.disabled = true;
            
            fetch('update_event_permission.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    event_id: eventId,
                    user_id: userId,
                    permission_name: permissionName,
                    is_allowed: isAllowed
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast('Permission mise à jour avec succès', 'success');
                } else {
                    this.checked = !originalState;
                    showToast('Erreur lors de la mise à jour de la permission: ' + (data.message || 'Erreur inconnue'), 'error');
                }
            })
            .catch(error => {
                // Revert switch on error
                this.checked = !originalState;
                console.error('Error:', error);
                showToast('Erreur lors de la mise à jour de la permission', 'error');
            })
            .finally(() => {
                this.disabled = false;
            });
        });
    });
});

function showToast(message, type = 'success') {
    const toastElement = document.getElementById('permissionToast');
    const toastBody = toastElement.querySelector('.toast-body');
    
    toastBody.textContent = message;
    
    if (type === 'error') {
        toastElement.classList.add('bg-danger', 'text-white');
    } else {
        toastElement.classList.remove('bg-danger', 'text-white');
    }
    
    const toast = new bootstrap.Toast(toastElement);
    toast.show();
}

function loadNotifications() {
    const notificationList = document.getElementById('notificationList');
    
    fetch('ajax_handler.php?action=get_notifications')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayNotifications(data.notifications);
                updateNotificationCount(data.unread_count);
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
            notificationList.innerHTML = '<li><a class="dropdown-item" href="#">Erreur lors du chargement des notifications</a></li>';
        });
}

function displayNotifications(notifications) {
    const notificationList = document.getElementById('notificationList');
    
    if (notifications.length === 0) {
        notificationList.innerHTML = '<li><a class="dropdown-item text-muted" href="#">Aucune notification</a></li>';
        return;
    }
    
    notificationList.innerHTML = notifications.map(notification => `
        <li>
            <a class="dropdown-item ${notification.read_at ? '' : 'bg-light'}" href="#" onclick="handleNotificationClick(${notification.id}, '${notification.link}')">
                <div class="d-flex">
                    <div class="flex-grow-1">
                        <strong>${notification.title}</strong>
                        <div class="small text-muted">${notification.message}</div>
                        <div class="small text-muted">${formatNotificationTime(notification.created_at)}</div>
                    </div>
                    ${!notification.read_at ? '<div class="ms-2"><span class="badge bg-primary">Nouveau</span></div>' : ''}
                </div>
            </a>
        </li>
    `).join('');
}

function updateNotificationCount(count) {
    const badge = document.getElementById('notificationCount');
    if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    }
}

function formatNotificationTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) return 'À l\'instant';
    if (diff < 3600000) return Math.floor(diff / 60000) + ' min';
    if (diff < 86400000) return Math.floor(diff / 3600000) + ' h';
    return date.toLocaleDateString();
}

function handleNotificationClick(notificationId, link) {
    fetch('ajax_handler.php?action=mark_notification_read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ notification_id: notificationId })
    })
    .then(() => {
        loadNotifications(); // Refresh notifications
        if (link) window.location.href = link;
    });
}

function markAllAsRead() {
    fetch('ajax_handler.php?action=mark_all_notifications_read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(() => {
        loadNotifications(); // Refresh notifications
    });
    return false;
}

document.addEventListener('DOMContentLoaded', function() {
    const notificationDropdown = document.getElementById('notificationDropdown');
    if (notificationDropdown) {
        notificationDropdown.addEventListener('show.bs.dropdown', loadNotifications);
    }
});
</script>
</body>
</html>
