<?php
session_start();

require 'database.php';
require_once 'role_check.php';
require_once 'notifications.php';

requireLogin();

$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
$event = null;
$error = '';
$canAccess = false;

if ($eventId <= 0) {
    $error = 'ID événement invalide.';
} else {
    try {
        $pdo = getDatabaseConnection();
        
        $stmt = $pdo->prepare('SELECT id, titre, created_by FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();
        
        if (!$event) {
            $error = 'Événement non trouvé.';
        } else {
            if ($event['created_by'] == $_SESSION['user_id'] || isEventOrganizer($_SESSION['user_id'], $eventId) || canDo($eventId, $_SESSION['user_id'], 'can_manage_resources')) {
                $canAccess = true;
            } else {
                $error = 'Vous n\'avez pas la permission d\'accéder à ce centre de communication.';
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching event: " . $e->getMessage());
        $error = 'Une erreur s\'est produite lors de la récupération des détails de l\'événement.';
    }
}

$unreadCount = 0;
try {
    $unreadNotifications = getUnreadNotifications($_SESSION['user_id']);
    $unreadCount = count($unreadNotifications);
} catch (Exception $e) {
    error_log('Error getting notifications: ' . $e->getMessage());
    $unreadCount = 0;
}

$isEventOwner = isset($_SESSION['user_id']) && $event && $_SESSION['user_id'] == $event['created_by'];
$isEventAdmin = isEventAdmin($_SESSION['user_id'], $eventId);
$isEventOrganizer = isEventOrganizer($_SESSION['user_id'], $eventId);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventUp - Centre de Communication</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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

        .card-header {
            background: linear-gradient(135deg, var(--primary-orange), var(--light-orange)) !important;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--light-orange), var(--primary-orange));
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(217, 74, 0, 0.3);
        }
        
        .spinner-border {
            color: var(--primary-orange) !important;
        }
        
        .message-bubble {
            border-radius: 18px;
            padding: 12px 16px;
            margin: 8px 0;
            max-width: 70%;
            word-wrap: break-word;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .message-bubble:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .current-user-message {
            background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
            color: white;
            margin-left: auto;
        }
        
        .other-user-message {
            background: white;
            border: 1px solid #e9ecef;
            margin-right: auto;
        }
        
        .form-control:focus {
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(217, 74, 0, 0.1);
        }
        
        .chat-container {
            background: linear-gradient(to bottom, #f8f9fa, #ffffff);
            border-radius: 12px;
            overflow: hidden;
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

<?php if ($canAccess && $event): ?>
    <div class="container-fluid">
        <div class="row">
            <?php include 'event_nav.php'; ?>
            
            <div class="col-lg-9 px-md-4 main-content">
            <!-- Header Card -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0"><i class="bi bi-chat-dots-fill me-2"></i>Centre de Communication</h5>
                            <small class="text-light opacity-90"><?php echo htmlspecialchars($event['titre']); ?></small>
                        </div>
                        <a href="event_details.php?id=<?php echo $eventId; ?>" class="btn btn-light btn-sm">
                            <i class="bi bi-arrow-left"></i> Retour
                        </a>
                    </div>
                </div>
            </div>

            <!-- Chat Container -->
            <div class="card shadow-sm chat-container" style="height: 600px; display: flex; flex-direction: column;">
                <!-- Messages Area -->
                <div class="card-body" id="messages-container" style="overflow-y: auto; flex: 1; background: linear-gradient(to bottom, #f8f9fa, #ffffff);">
                    <div class="text-center text-muted py-5">
                        <div class="spinner-border mb-3" role="status"></div>
                        <p class="mb-0"><i class="bi bi-chat-dots me-2"></i>Chargement des messages...</p>
                    </div>
                </div>

                <!-- Message Input Area -->
                <div class="card-footer border-top bg-white">
                    <form id="message-form" class="d-flex gap-2">
                        <input 
                            type="text" 
                            id="message-input" 
                            class="form-control" 
                            placeholder="Tapez votre message..." 
                            autocomplete="off"
                            required
                        >
                        <button type="submit" class="btn btn-primary" id="send-btn">
                            <i class="bi bi-send-fill me-2"></i>Envoyer
                        </button>
                    </form>
                </div>
            </div>

            <!-- Info Card -->
            <div class="card mt-4 shadow-sm">
                <div class="card-body">
                    <p class="mb-0"><i class="bi bi-info-circle me-2"></i><strong>Note :</strong> Ceci est un canal de communication privé pour les organisateurs d'événements uniquement. Les messages sont stockés et visibles par tous les organisateurs de cet événement.</p>
                </div>
            </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const eventId = <?php echo $eventId; ?>;
        const messagesContainer = document.getElementById('messages-container');
        const messageForm = document.getElementById('message-form');
        const messageInput = document.getElementById('message-input');
        const sendBtn = document.getElementById('send-btn');
        let lastMessageId = 0;
        let isLoadingMessages = false;

        loadMessages();

        setInterval(loadMessages, 2000);

        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const messageText = messageInput.value.trim();
            if (!messageText) return;

            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Envoi en cours...';

            fetch('send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'event_id=' + encodeURIComponent(eventId) + '&message_text=' + encodeURIComponent(messageText)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageInput.value = '';
                    lastMessageId = 0; // Reset to load all messages
                    loadMessages();
                } else {
                    alert('Échec de l\'envoi du message : ' + (data.message || 'Erreur inconnue'));
                }
            })
            .catch(error => {
                console.error('Error sending message:', error);
                alert('Une erreur s\'est produite lors de l\'envoi du message.');
            })
            .finally(() => {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="bi bi-send-fill me-2"></i>Envoyer';
                messageInput.focus();
            });
        });

        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                messageForm.dispatchEvent(new Event('submit'));
            }
        });

        function loadMessages() {
            if (isLoadingMessages) return;
            isLoadingMessages = true;

            fetch('fetch_messages.php?event_id=' + encodeURIComponent(eventId) + '&last_id=' + lastMessageId, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const messages = data.messages;
                    
                    if (lastMessageId === 0 && messages.length > 0) {
                        messagesContainer.innerHTML = '';
                        messages.forEach(msg => {
                            appendMessage(msg);
                        });
                        lastMessageId = messages[messages.length - 1].id;
                        scrollToBottom();
                    } else if (messages.length > 0) {
                        messages.forEach(msg => {
                            appendMessage(msg, true); // true = highlight new message
                        });
                        lastMessageId = messages[messages.length - 1].id;
                        scrollToBottom();
                    }
                } else {
                    if (lastMessageId === 0) {
                        messagesContainer.innerHTML = '<div class="text-center text-danger py-5"><p>Échec du chargement des messages.</p></div>';
                    }
                }
            })
            .catch(error => {
                console.error('Error loading messages:', error);
                if (lastMessageId === 0) {
                    messagesContainer.innerHTML = '<div class="text-center text-danger py-5"><p>Erreur lors du chargement des messages.</p></div>';
                }
            })
            .finally(() => {
                isLoadingMessages = false;
            });
        }

        function appendMessage(message, isNew = false) {
            const messageEl = document.createElement('div');
            messageEl.className = 'mb-3 message-item' + (isNew ? ' new-message' : '');
            
            const isCurrentUser = message.is_current_user;
            const messageClass = isCurrentUser ? 'current-user-message' : 'other-user-message';
            const alignClass = isCurrentUser ? 'justify-content-end' : 'justify-content-start';
            
            const messageTime = formatTime(message.created_at);
            
            messageEl.innerHTML = `
                <div class="d-flex ${alignClass}">
                    <div class="message-bubble ${messageClass}">
                        <div class="d-flex align-items-center mb-2">
                            <div class="fw-bold small me-2">${escapeHTML(message.sender_name)}</div>
                            ${isCurrentUser ? '<i class="bi bi-check2-all text-white opacity-75"></i>' : '<i class="bi bi-person-circle text-muted opacity-50"></i>'}
                        </div>
                        <div class="mb-2">${escapeHTML(message.message_text)}</div>
                        <div class="small ${isCurrentUser ? 'text-white opacity-75' : 'text-muted'}" style="font-size: 0.75rem;">
                            <i class="bi bi-clock me-1"></i>${messageTime}
                        </div>
                    </div>
                </div>
            `;
            
            messagesContainer.appendChild(messageEl);
            
            if (isNew) {
                messageEl.style.animation = 'fadeIn 0.3s ease-in';
                setTimeout(() => {
                    messageEl.classList.remove('new-message');
                }, 1000);
            }
        }

        function scrollToBottom() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function formatTime(dateString) {
            const date = new Date(dateString);
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${hours}:${minutes}, ${day}/${month}/${year}`;
        }

        function escapeHTML(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    });
    </script>

    <style>
    @keyframes fadeIn {
        from {
            opacity: 0.5;
            background-color: #fff3cd !important;
        }
        to {
            opacity: 1;
        }
    }

    #messages-container {
        display: flex;
        flex-direction: column;
    }

    .message-item {
        animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    </style>
</head>
<body>

<?php else: ?>
    <div class="alert alert-danger">
        <?php echo htmlspecialchars($error ?: 'Impossible d\'accéder au centre de communication.'); ?>
    </div>
    <a href="index.php" class="btn btn-primary"><i class="bi bi-house me-2"></i>Retour aux événements</a>
<?php endif; ?>

<?php require_once 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>