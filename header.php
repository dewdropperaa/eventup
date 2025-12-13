<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'notifications.php';
require_once 'role_check.php';

$useAppHeader = $useAppHeader ?? false;
$activeNav = $activeNav ?? '';
$appContentClass = $appContentClass ?? 'container mt-4';
$pageTitle = $pageTitle ?? 'EventUp';
$additionalHeadContent = $additionalHeadContent ?? '';

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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/header.css" rel="stylesheet">
    <?php echo $additionalHeadContent; ?>
</head>
<body class="<?php echo $useAppHeader ? 'app-body' : ''; ?>">
<?php if ($useAppHeader): ?>
    <?php include __DIR__ . '/app_header_nav.php'; ?>
<?php else: ?>
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center me-4" href="index.php">
                <div class="logo-icon me-2">
                    <img src="assets/EventUp_logo.png" alt="EventUp Logo" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <span class="brand-text">EventUp</span>
            </a>
            
            <div class="d-flex align-items-center">
                <a href="register.php" class="btn btn-outline-primary me-2">S'inscrire</a>
                <a href="login.php" class="btn btn-primary">Se connecter</a>
            </div>
        </div>
    </nav>
<?php endif; ?>
<?php if (isset($_SESSION['user_id'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggle = document.getElementById('notificationsDropdown');
            const dropdownMenu = document.getElementById('notificationDropdown');
            const notificationList = document.getElementById('notificationList');
            const notificationBadge = document.getElementById('notificationCount');

            if (!dropdownToggle || !dropdownMenu || !notificationList || !notificationBadge) {
                return;
            }

            const dropdownContainer = dropdownToggle.closest('.dropdown');

            function setLoading() {
                notificationList.innerHTML = '<div class="text-center p-3"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            }

            function formatDateTime(value) {
                try {
                    const date = new Date((value || '').replace(' ', 'T'));
                    if (Number.isNaN(date.getTime())) {
                        return value;
                    }
                    return date.toLocaleString();
                } catch (error) {
                    return value;
                }
            }

            function updateBadge(count) {
                notificationBadge.textContent = count;
                notificationBadge.style.display = count > 0 ? 'flex' : 'none';
            }

            function renderNotifications(notifications) {
                if (!Array.isArray(notifications) || notifications.length === 0) {
                    notificationList.innerHTML = '<div class="text-center p-3 text-muted small">No new notifications</div>';
                    updateBadge(0);
                    return;
                }

                const items = notifications.map((notification) => {
                    const type = notification.type || 'Notification';
                    const message = notification.message || '';
                    const createdAt = formatDateTime(notification.created_at || '');

                    return `
                        <div class="px-3 py-2 border-bottom small">
                            <div class="fw-semibold text-truncate">${type}</div>
                            <div class="text-muted text-wrap">${message}</div>
                            <div class="text-muted" style="font-size: 0.75rem;">${createdAt}</div>
                        </div>
                    `;
                }).join('');

                notificationList.innerHTML = items;
                updateBadge(notifications.length);
            }

            window.loadNotifications = function loadNotifications() {
                setLoading();

                fetch('ajax_handler.php?action=get_notifications', {
                    credentials: 'same-origin'
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (!data || data.success !== true) {
                            notificationList.innerHTML = '<div class="text-center p-3 text-muted small">Unable to load notifications.</div>';
                            updateBadge(0);
                            return;
                        }

                        renderNotifications(data.notifications);
                    })
                    .catch(() => {
                        notificationList.innerHTML = '<div class="text-center p-3 text-muted small">Unable to load notifications.</div>';
                        updateBadge(0);
                    });
            };

            window.markAllAsRead = function markAllAsRead() {
                fetch('ajax_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ action: 'mark_all_notifications_read' }),
                    credentials: 'same-origin'
                })
                    .then((response) => response.json())
                    .then(() => loadNotifications())
                    .catch(() => loadNotifications());
            };

            if (dropdownContainer) {
                dropdownContainer.addEventListener('show.bs.dropdown', loadNotifications);
            } else {
                dropdownToggle.addEventListener('click', loadNotifications);
            }
        });
    </script>
<?php endif; ?>
<div class="<?php echo htmlspecialchars($appContentClass, ENT_QUOTES, 'UTF-8'); ?>">
