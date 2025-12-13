<?php
session_start();
require_once 'role_check.php';
require_once 'notifications.php';

requireLogin();

if (isOrganizer($_SESSION['user_id'])) {
    header('Location: organizer_dashboard.php');
    exit;
}

$pdo = getDatabaseConnection();

$stats = [];
try {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as registered_events 
        FROM event_participants ep 
        JOIN events e ON ep.event_id = e.id 
        WHERE ep.user_id = ? AND e.date >= CURDATE()
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $stats['registered_events'] = $stmt->fetch()['registered_events'];
    
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as upcoming_events 
        FROM events 
        WHERE date >= CURDATE() AND status = "published"
    ');
    $stmt->execute();
    $stats['upcoming_events'] = $stmt->fetch()['upcoming_events'];
    
    $stmt = $pdo->prepare('SELECT COUNT(*) as total_events FROM events WHERE status = "published"');
    $stmt->execute();
    $stats['total_events'] = $stmt->fetch()['total_events'];
    
    $stmt = $pdo->prepare('
        SELECT e.titre, e.date, e.id, ep.registration_date
        FROM event_participants ep
        JOIN events e ON ep.event_id = e.id
        WHERE ep.user_id = ?
        ORDER BY ep.registration_date DESC
        LIMIT 5
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $recent_registrations = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log('Error fetching dashboard stats: ' . $e->getMessage());
    $stats = ['registered_events' => 0, 'upcoming_events' => 0, 'total_events' => 0];
    $recent_registrations = [];
}

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
    <title>EventUp - Tableau de bord</title>
    
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
   Action Cards
   ============================ */
.action-card {
    background: white;
    border-radius: 16px;
    padding: 32px;
    transition: all 0.3s ease;
    border: 1px solid var(--border-color);
    height: 100%;
    display: flex;
    flex-direction: column;
}

.action-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
}

.action-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    margin-bottom: 20px;
}

.action-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--primary-teal);
    margin-bottom: 12px;
}

.action-description {
    color: var(--text-muted);
    margin-bottom: 20px;
    flex-grow: 1;
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

.btn-lg {
    padding: 16px 32px;
    font-size: 16px;
}

/* ===========================
   Recent Activity
   ============================ */
.activity-item {
    padding: 16px;
    border-left: 3px solid transparent;
    transition: all 0.3s ease;
    border-radius: 0 8px 8px 0;
}

.activity-item:hover {
    background: var(--bg-light);
    border-left-color: var(--primary-orange);
}

.activity-title {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 4px;
}

.activity-date {
    color: var(--text-muted);
    font-size: 13px;
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
    
    .action-card {
        padding: 24px;
    }
    
    .btn-lg {
        padding: 12px 24px;
        font-size: 14px;
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
    
    .action-card {
        padding: 20px;
        margin-bottom: 16px;
    }
}
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
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
            <div class="col-12">
                <h1 class="page-title">Bienvenue, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
                <p class="page-subtitle">Vous êtes connecté avec succès. Voici votre tableau de bord personnel.</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon orange-gradient">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <div class="stats-number"><?php echo (int) $stats['registered_events']; ?></div>
                    <div class="stats-label">Événements Inscrits</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon teal-gradient">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div class="stats-number"><?php echo (int) $stats['upcoming_events']; ?></div>
                    <div class="stats-label">Événements à Venir</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon blue-gradient">
                        <i class="bi bi-calendar3"></i>
                    </div>
                    <div class="stats-number"><?php echo (int) $stats['total_events']; ?></div>
                    <div class="stats-label">Total Événements</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-icon green-gradient">
                        <i class="bi bi-envelope"></i>
                    </div>
                    <div class="stats-number"><?php echo $unreadCount; ?></div>
                    <div class="stats-label">Notifications</div>
                </div>
            </div>
        </div>

        <!-- Action Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="action-card">
                    <div class="action-icon orange-gradient">
                        <i class="bi bi-search"></i>
                    </div>
                    <h3 class="action-title">Explorer les Événements</h3>
                    <p class="action-description">Découvrez et participez aux événements à venir dans votre région. Trouvez des activités qui correspondent à vos intérêts.</p>
                    <a href="index.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-calendar-week me-2"></i>Voir tous les événements
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="action-card">
                    <div class="action-icon teal-gradient">
                        <i class="bi bi-plus-circle"></i>
                    </div>
                    <h3 class="action-title">Créer Votre Événement</h3>
                    <p class="action-description">Organisez votre propre événement et devenez automatiquement son organisateur. Gérez les participants et les tâches.</p>
                    <a href="create_event.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-plus-lg me-2"></i>Créer un événement
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activity & Account Info -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Activité Récente</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_registrations)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-x display-4 text-muted"></i>
                                <p class="text-muted mt-3">Aucune inscription récente</p>
                                <a href="index.php" class="btn btn-outline-primary">Explorer les événements</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_registrations as $registration): ?>
                                <div class="activity-item">
                                    <div class="activity-title">
                                        <i class="bi bi-calendar-check me-2 text-primary"></i>
                                        <?php echo htmlspecialchars($registration['titre']); ?>
                                    </div>
                                    <div class="activity-date">
                                        Inscrit le <?php echo date('d/m/Y', strtotime($registration['registration_date'])); ?>
                                        <?php if ($registration['date'] >= date('Y-m-d')): ?>
                                            <span class="badge bg-success ms-2">À venir</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>Informations du Compte</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-muted">Nom</label>
                            <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_nom'] ?? 'Non disponible'); ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Email</label>
                            <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Non disponible'); ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Type de compte</label>
                            <div class="badge bg-primary">Participant</div>
                        </div>
                        <hr>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary">
                                <i class="bi bi-gear me-2"></i>Paramètres du profil
                            </button>
                            <a href="logout.php" class="btn btn-outline-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>Déconnexion
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Notification System -->
    <script>
        document.querySelector('[data-bs-toggle="dropdown"]').addEventListener('click', function() {
            loadNotifications();
        });

        function loadNotifications() {
            const notificationList = document.getElementById('notificationList');
            
            fetch('ajax_handler.php?action=get_notifications', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayNotifications(data.notifications);
                    updateNotificationCount(data.unread_count);
                } else {
                    notificationList.innerHTML = '<div class="text-center p-3 text-muted">Erreur de chargement</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                notificationList.innerHTML = '<div class="text-center p-3 text-muted">Erreur de chargement</div>';
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
                html += `
                    <li class="dropdown-item notification-item ${notification.is_read ? 'read' : 'unread'}" data-id="${notification.id}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="fw-bold ${notification.is_read ? 'text-muted' : 'text-dark'}">${notification.title}</div>
                                <div class="small text-muted">${notification.message}</div>
                                <div class="small text-muted mt-1">${notification.created_at_formatted}</div>
                            </div>
                            ${!notification.is_read ? '<button class="btn btn-sm btn-outline-secondary mark-read-btn" onclick="markAsRead(' + notification.id + ')"><i class="bi bi-check"></i></button>' : ''}
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                `;
            });
            
            notificationList.innerHTML = html;
        }

        function updateNotificationCount(count) {
            const badge = document.getElementById('notificationCount');
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }

        function markAsRead(notificationId) {
            fetch('ajax_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_notification_read',
                    notification_id: notificationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications(); // Reload notifications
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function markAllAsRead() {
            fetch('ajax_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_all_notifications_read'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications(); // Reload notifications
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>
