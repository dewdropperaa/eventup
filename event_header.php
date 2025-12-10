<?php
/**
 * Event Management Header Component
 * Displays the top navigation bar with notifications and user profile
 */

// Get unread notifications count if not already set
if (!isset($unreadCount)) {
    $unreadCount = 0;
    try {
        if (isset($_SESSION['user_id'])) {
            require_once 'notifications.php';
            $unreadNotifications = getUnreadNotifications($_SESSION['user_id']);
            $unreadCount = count($unreadNotifications);
        }
    } catch (Exception $e) {
        error_log('Error getting notifications: ' . $e->getMessage());
        $unreadCount = 0;
    }
}
?>

<style>
/* CSS Variables */
:root {
    --primary-orange: #D94A00;
    --primary-teal: #1B5E52;
    --light-orange: #ff6b2c;
    --light-teal: #267061;
    --text-dark: #2c3e50;
    --text-muted: #657786;
    --bg-light: #f5f7fa;
    --border-color: #e1e8ed;
}

/* Navbar Styles */
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
</style>

<!-- Header Navigation -->
<nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="event_details.php?id=<?php echo isset($eventId) ? $eventId : ''; ?>">
            <div class="logo-icon me-2">
                <img src="assets/EventUp_logo.png" alt="EventUp Logo" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <span class="brand-text">EventUp</span>
        </a>
        
        <div class="d-flex align-items-center ms-auto">
            <!-- Notifications Dropdown -->
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
            
            <!-- User Profile Dropdown -->
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
</script>
