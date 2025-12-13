<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/role_check.php';

$isLoggedIn = isset($_SESSION['user_id']);
$activeNav = $activeNav ?? '';
$unreadCount = isset($unreadCount) ? (int) $unreadCount : 0;
$userName = $userName ?? ($isLoggedIn ? ($_SESSION['user_nom'] ?? 'Utilisateur') : '');
$userInitial = $userInitial ?? ($isLoggedIn ? strtoupper(substr($userName, 0, 1)) : '');
$isOrganizerUser = $isOrganizerUser ?? ($isLoggedIn && function_exists('isOrganizer') ? isOrganizer($_SESSION['user_id']) : false);

$brandHref = 'index.php';
if ($isLoggedIn) {
    $brandHref = $isOrganizerUser ? 'organizer_dashboard.php' : 'dashboard.php';
}

$navLinks = [
    [
        'key' => 'browse',
        'label' => 'Parcourir les Événements',
        'href' => 'index.php',
        'visible' => true
    ],
    [
        'key' => 'dashboard',
        'label' => 'Tableau de bord',
        'href' => 'dashboard.php',
        'visible' => $isLoggedIn
    ],
    [
        'key' => 'my_events',
        'label' => 'Mes Événements',
        'href' => 'organizer_dashboard.php',
        'visible' => $isOrganizerUser
    ],
    [
        'key' => 'create_event',
        'label' => '+ Créer un Événement',
        'href' => 'create_event.php',
        'visible' => $isOrganizerUser
    ],
    [
        'key' => 'my_tasks',
        'label' => 'Mes Tâches',
        'href' => 'my_tasks.php',
        'visible' => $isOrganizerUser
    ],
];
?>

<nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm app-navbar" role="navigation">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo htmlspecialchars($brandHref); ?>">
            <div class="logo-icon me-2">
                <img src="assets/EventUp_logo.png" alt="EventUp Logo" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <span class="brand-text">EventUp</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#appNavbar" aria-controls="appNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="appNavbar">
            <ul class="navbar-nav mx-auto align-items-lg-center">
                <?php foreach ($navLinks as $link): ?>
                    <?php if ($link['visible']): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $activeNav === $link['key'] ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($link['href']); ?>">
                                <?php echo htmlspecialchars($link['label']); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>

            <div class="d-flex align-items-center gap-3">
                <?php if ($isLoggedIn): ?>
                    <div class="dropdown">
                        <button class="notification-btn position-relative" id="notificationsDropdown" data-bs-toggle="dropdown" aria-expanded="false" type="button">
                            <i class="bi bi-bell"></i>
                            <span class="notification-badge" id="notificationCount" style="<?php echo $unreadCount > 0 ? '' : 'display:none;'; ?>">
                                <?php echo $unreadCount; ?>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end p-0" aria-labelledby="notificationsDropdown" id="notificationDropdown" style="width: 330px; max-height: 420px; overflow-y: auto;">
                            <li class="dropdown-header d-flex justify-content-between align-items-center px-3 py-2">
                                <span class="fw-semibold">Notifications</span>
                                <button class="btn btn-link p-0 small" type="button" onclick="markAllAsRead()">Tout marquer comme lu</button>
                            </li>
                            <li><hr class="dropdown-divider my-0"></li>
                            <li id="notificationList">
                                <div class="text-center p-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider my-0"></li>
                            <li class="px-3 py-2 text-center">
                                <a href="notifications_list.php" class="small text-decoration-none">Voir toutes les notifications</a>
                            </li>
                        </ul>
                    </div>

                    <div class="dropdown">
                        <button class="btn admin-profile-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="admin-avatar me-2"><?php echo htmlspecialchars($userInitial ?: 'A'); ?></div>
                            <span class="d-none d-md-inline fw-semibold text-capitalize"><?php echo htmlspecialchars($userName); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li>
                                <a class="dropdown-item" href="dashboard.php">
                                    <i class="bi bi-house me-2"></i>Tableau de bord
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#">
                                    <i class="bi bi-person me-2"></i>Profil
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#">
                                    <i class="bi bi-gear me-2"></i>Paramètres
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Déconnexion
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-2">
                        <a href="login.php" class="btn btn-outline-primary">Se connecter</a>
                        <a href="register.php" class="btn btn-primary">S'inscrire</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
