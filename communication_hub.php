<?php
session_start();

require 'database.php';
require_once 'role_check.php';

// Require login
requireLogin();

$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
$event = null;
$error = '';
$canAccess = false;

if ($eventId <= 0) {
    $error = 'Invalid event ID.';
} else {
    try {
        $pdo = getDatabaseConnection();
        
        // Fetch event details
        $stmt = $pdo->prepare('SELECT id, titre, created_by FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();
        
        if (!$event) {
            $error = 'Event not found.';
        } else {
            // Check if user is organizer or event owner
            $isEventOwner = ($_SESSION['user_id'] == $event['created_by']);
            $isEventOrganizer = isEventOrganizer($_SESSION['user_id'], $eventId);
            $isEventAdmin = isEventAdmin($_SESSION['user_id'], $eventId);
            
            if ($isEventOwner || $isEventOrganizer) {
                $canAccess = true;
            } else {
                $error = 'You do not have permission to access this communication hub.';
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching event: " . $e->getMessage());
        $error = 'An error occurred while fetching event details.';
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventUp - Communication Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            padding-top: 76px;
            background-color: #f5f7fa;
        }
    </style>
</head>
<body>
    <?php include 'event_header.php'; ?>

<?php if ($canAccess && $event): ?>
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include 'event_nav.php'; ?>
            
            <div class="col-lg-9">
            <!-- Header Card -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0"><i class="bi bi-chat-dots"></i> Communication Hub</h5>
                            <small class="text-light"><?php echo htmlspecialchars($event['titre']); ?></small>
                        </div>
                        <a href="event_details.php?id=<?php echo $eventId; ?>" class="btn btn-light btn-sm">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
            </div>

            <!-- Chat Container -->
            <div class="card shadow-sm" style="height: 600px; display: flex; flex-direction: column;">
                <!-- Messages Area -->
                <div class="card-body" id="messages-container" style="overflow-y: auto; flex: 1; background-color: #f8f9fa;">
                    <div class="text-center text-muted py-5">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <p>Loading messages...</p>
                    </div>
                </div>

                <!-- Message Input Area -->
                <div class="card-footer border-top bg-white">
                    <form id="message-form" class="d-flex gap-2">
                        <input 
                            type="text" 
                            id="message-input" 
                            class="form-control" 
                            placeholder="Type your message..." 
                            autocomplete="off"
                            required
                        >
                        <button type="submit" class="btn btn-primary" id="send-btn">
                            <i class="bi bi-send"></i> Send
                        </button>
                    </form>
                </div>
            </div>

            <!-- Info Card -->
            <div class="card mt-4 shadow-sm">
                <div class="card-body">
                    <p class="mb-0 text-muted">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> This is a private communication channel for event organizers only. Messages are stored and visible to all organizers of this event.
                    </p>
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

        // Load messages on page load
        loadMessages();

        // Set up auto-refresh every 2 seconds
        setInterval(loadMessages, 2000);

        // Handle form submission
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const messageText = messageInput.value.trim();
            if (!messageText) return;

            sendBtn.disabled = true;
            sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sending...';

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
                    alert('Failed to send message: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error sending message:', error);
                alert('An error occurred while sending the message.');
            })
            .finally(() => {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="bi bi-send"></i> Send';
                messageInput.focus();
            });
        });

        // Handle Enter key to send message
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
                    
                    // If this is the first load and we have messages
                    if (lastMessageId === 0 && messages.length > 0) {
                        messagesContainer.innerHTML = '';
                        messages.forEach(msg => {
                            appendMessage(msg);
                        });
                        lastMessageId = messages[messages.length - 1].id;
                        scrollToBottom();
                    } else if (messages.length > 0) {
                        // Append only new messages
                        messages.forEach(msg => {
                            appendMessage(msg, true); // true = highlight new message
                        });
                        lastMessageId = messages[messages.length - 1].id;
                        scrollToBottom();
                    }
                } else {
                    if (lastMessageId === 0) {
                        messagesContainer.innerHTML = '<div class="text-center text-danger py-5"><p>Failed to load messages.</p></div>';
                    }
                }
            })
            .catch(error => {
                console.error('Error loading messages:', error);
                if (lastMessageId === 0) {
                    messagesContainer.innerHTML = '<div class="text-center text-danger py-5"><p>Error loading messages.</p></div>';
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
            const bgClass = isCurrentUser ? 'bg-primary text-white' : 'bg-white border';
            const alignClass = isCurrentUser ? 'ms-auto' : '';
            
            const messageTime = formatTime(message.created_at);
            
            messageEl.innerHTML = `
                <div class="d-flex ${isCurrentUser ? 'justify-content-end' : 'justify-content-start'}">
                    <div class="card ${bgClass} ${alignClass}" style="max-width: 70%; word-wrap: break-word;">
                        <div class="card-body py-2 px-3">
                            <div class="fw-bold small mb-1">${escapeHTML(message.sender_name)}</div>
                            <div class="mb-2">${escapeHTML(message.message_text)}</div>
                            <div class="small ${isCurrentUser ? 'text-light' : 'text-muted'}" style="font-size: 0.75rem;">
                                ${messageTime}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            messagesContainer.appendChild(messageEl);
            
            // Highlight new message briefly
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
        <?php echo htmlspecialchars($error ?: 'Unable to access communication hub.'); ?>
    </div>
    <a href="index.php" class="btn btn-primary">Back to Events</a>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
