<?php
/**
 * Organizers Management Page
 * Allows event admins to manage organizers for their event
 */

session_start();

require 'database.php';
require_once 'role_check.php';
require_once 'notifications.php';

// Check if user is logged in
requireLogin();

// Get event ID from URL
$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

if ($eventId <= 0) {
    header('Location: index.php');
    exit;
}

// Check if user has permission to manage organizers (must be event admin)
if (!isEventAdmin($_SESSION['user_id'], $eventId)) {
    header('Location: event_details.php?id=' . $eventId);
    exit;
}

// Get database connection
$pdo = getDatabaseConnection();

// Initialize variables
$error = '';
$success = '';
$event = null;
$organizers = [];
$invitationsPending = [];

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
    $stmt = $pdo->prepare('SELECT id, titre, created_by FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event) {
        header('Location: index.php');
        exit;
    }
    
    // Determine user roles for navigation
    $isEventOwner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $event['created_by'];
    $isEventAdmin = isEventAdmin($_SESSION['user_id'], $eventId);
    $isEventOrganizer = isEventOrganizer($_SESSION['user_id'], $eventId);
} catch (Exception $e) {
    error_log('Error fetching event: ' . $e->getMessage());
    $error = 'Error loading event.';
}

// Fetch organizers
try {
    $stmt = $pdo->prepare('
        SELECT u.id, u.nom, u.email, er.role, eo.created_at
        FROM event_roles er
        JOIN users u ON er.user_id = u.id
        LEFT JOIN event_organizers eo ON er.user_id = eo.user_id AND er.event_id = eo.event_id
        WHERE er.event_id = ? AND er.role IN ("admin", "organizer")
        ORDER BY er.role DESC, u.nom ASC
    ');
    $stmt->execute([$eventId]);
    $organizers = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching organizers: ' . $e->getMessage());
}

// Fetch pending invitations
try {
    $stmt = $pdo->prepare('
        SELECT id, email, created_at, token_expiry
        FROM event_invitations
        WHERE event_id = ? AND used = FALSE AND token_expiry > NOW()
        ORDER BY created_at DESC
    ');
    $stmt->execute([$eventId]);
    $invitationsPending = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching pending invitations: ' . $e->getMessage());
}

// Handle organizer removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_organizer') {
    $organizerId = isset($_POST['organizer_id']) ? (int) $_POST['organizer_id'] : 0;
    
    if ($organizerId > 0 && $organizerId !== $event['created_by']) {
        try {
            // Check if organizer belongs to this event
            $stmt = $pdo->prepare('
                SELECT id FROM event_roles 
                WHERE user_id = ? AND event_id = ? AND role != "admin"
            ');
            $stmt->execute([$organizerId, $eventId]);
            
            if ($stmt->fetch()) {
                // Remove from event_roles
                $stmt = $pdo->prepare('
                    DELETE FROM event_roles 
                    WHERE user_id = ? AND event_id = ? AND role = "organizer"
                ');
                $stmt->execute([$organizerId, $eventId]);
                
                // Remove from event_organizers
                $stmt = $pdo->prepare('
                    DELETE FROM event_organizers 
                    WHERE user_id = ? AND event_id = ?
                ');
                $stmt->execute([$organizerId, $eventId]);
                
                // Remove from event_permissions
                $stmt = $pdo->prepare('
                    DELETE FROM event_permissions 
                    WHERE user_id = ? AND event_id = ?
                ');
                $stmt->execute([$organizerId, $eventId]);
                
                $success = 'Organizer removed successfully.';
                
                // Refresh organizers list
                $stmt = $pdo->prepare('
                    SELECT u.id, u.nom, u.email, er.role, eo.created_at
                    FROM event_roles er
                    JOIN users u ON er.user_id = u.id
                    LEFT JOIN event_organizers eo ON er.user_id = eo.user_id AND er.event_id = eo.event_id
                    WHERE er.event_id = ? AND er.role IN ("admin", "organizer")
                    ORDER BY er.role DESC, u.nom ASC
                ');
                $stmt->execute([$eventId]);
                $organizers = $stmt->fetchAll();
            } else {
                $error = 'Cannot remove the event owner.';
            }
        } catch (PDOException $e) {
            error_log('Error removing organizer: ' . $e->getMessage());
            $error = 'Error removing organizer. Please try again.';
        }
    } else {
        $error = 'Cannot remove the event owner.';
    }
}

// Handle invitation cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_invitation') {
    $invitationId = isset($_POST['invitation_id']) ? (int) $_POST['invitation_id'] : 0;
    
    if ($invitationId > 0) {
        try {
            $stmt = $pdo->prepare('
                DELETE FROM event_invitations 
                WHERE id = ? AND event_id = ?
            ');
            $stmt->execute([$invitationId, $eventId]);
            
            $success = 'Invitation cancelled successfully.';
            
            // Refresh pending invitations
            $stmt = $pdo->prepare('
                SELECT id, email, created_at, token_expiry
                FROM event_invitations
                WHERE event_id = ? AND used = FALSE AND token_expiry > NOW()
                ORDER BY created_at DESC
            ');
            $stmt->execute([$eventId]);
            $invitationsPending = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Error cancelling invitation: ' . $e->getMessage());
            $error = 'Error cancelling invitation. Please try again.';
        }
    }
}

// Handle new organizer invitation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'invite_organizer') {
    $emails = isset($_POST['emails']) ? trim($_POST['emails']) : '';
    
    if (empty($emails)) {
        $error = 'Please enter at least one email address.';
    } else {
        // Parse emails (comma or newline separated)
        $email_list = preg_split('/[\s,]+/', $emails, -1, PREG_SPLIT_NO_EMPTY);
        $email_list = array_map('trim', $email_list);
        $email_list = array_unique($email_list);
        
        $invited_count = 0;
        $errors = [];
        
        try {
            $pdo->beginTransaction();
            
            foreach ($email_list as $email) {
                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Invalid email format: " . htmlspecialchars($email);
                    continue;
                }
                
                // Check if user exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // User exists - check if already has a role in this event
                    $stmt = $pdo->prepare("
                        SELECT id FROM event_roles 
                        WHERE user_id = :user_id AND event_id = :event_id
                    ");
                    $stmt->execute([
                        ':user_id' => $user['id'],
                        ':event_id' => $eventId
                    ]);
                    
                    if ($stmt->fetch()) {
                        $errors[] = htmlspecialchars($email) . " already has a role for this event.";
                    } else {
                        // Add user as organizer
                        $stmt = $pdo->prepare("
                            INSERT INTO event_roles (user_id, event_id, role)
                            VALUES (:user_id, :event_id, 'organizer')
                        ");
                        $stmt->execute([
                            ':user_id' => $user['id'],
                            ':event_id' => $eventId
                        ]);
                        
                        // Also add to event_organizers table for permission management
                        $stmt = $pdo->prepare("
                            INSERT INTO event_organizers (event_id, user_id, role)
                            VALUES (:event_id, :user_id, 'organizer')
                            ON DUPLICATE KEY UPDATE role = 'organizer'
                        ");
                        $stmt->execute([
                            ':event_id' => $eventId,
                            ':user_id' => $user['id']
                        ]);
                        
                        $invited_count++;

                        // Send in-app notification to existing user
                        $msg = "You have been added as an organizer for the event '" . $event['titre'] . "'.";
                        createNotification($user['id'], 'Organizer invitation', $msg, $eventId);
                    }
                } else {
                    // User doesn't exist - send invitation email
                    $invitation_token = bin2hex(random_bytes(32));
                    $token_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
                    
                    // Store invitation in database
                    $stmt = $pdo->prepare("
                        INSERT INTO event_invitations (email, event_id, token, token_expiry, created_at)
                        VALUES (:email, :event_id, :token, :token_expiry, NOW())
                    ");
                    $stmt->execute([
                        ':email' => $email,
                        ':event_id' => $eventId,
                        ':token' => $invitation_token,
                        ':token_expiry' => $token_expiry
                    ]);
                    
                    $invited_count++;
                }
            }
            
            $pdo->commit();
            
            if ($invited_count > 0) {
                $success = $invited_count . " invitations sent successfully!";
            }
            
            if (!empty($errors)) {
                $error = implode('<br>', $errors);
            }
            
            // Refresh organizers and invitations
            $stmt = $pdo->prepare('
                SELECT u.id, u.nom, u.email, er.role, eo.created_at
                FROM event_roles er
                JOIN users u ON er.user_id = u.id
                LEFT JOIN event_organizers eo ON er.user_id = eo.user_id AND er.event_id = eo.event_id
                WHERE er.event_id = ? AND er.role IN ("admin", "organizer")
                ORDER BY er.role DESC, u.nom ASC
            ');
            $stmt->execute([$eventId]);
            $organizers = $stmt->fetchAll();
            
            $stmt = $pdo->prepare('
                SELECT id, email, created_at, token_expiry
                FROM event_invitations
                WHERE event_id = ? AND used = FALSE AND token_expiry > NOW()
                ORDER BY created_at DESC
            ');
            $stmt->execute([$eventId]);
            $invitationsPending = $stmt->fetchAll();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Invitation error: " . $e->getMessage());
            $error = 'An error occurred while sending invitations.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizers Management - EventUp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            color: #1B5E52;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .breadcrumb-custom {
            background-color: transparent;
            padding: 0;
            margin-bottom: 20px;
        }
        
        .breadcrumb-custom a {
            color: #D94A00;
            text-decoration: none;
        }
        
        .breadcrumb-custom a:hover {
            text-decoration: underline;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #e1e8ed;
            margin-bottom: 30px;
        }
        
        .nav-tabs .nav-link {
            color: #657786;
            border: none;
            border-bottom: 3px solid transparent;
            font-weight: 600;
            padding: 12px 20px;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover {
            color: #D94A00;
            border-bottom-color: #D94A00;
        }
        
        .nav-tabs .nav-link.active {
            color: #D94A00;
            border-bottom-color: #D94A00;
            background-color: transparent;
        }
        
        .card {
            border: 1px solid #e1e8ed;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e1e8ed;
            padding: 20px;
            border-radius: 12px 12px 0 0;
        }
        
        .card-header h5 {
            color: #1B5E52;
            font-weight: 700;
            margin: 0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #D94A00, #ff6b2c);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #ff6b2c, #D94A00);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(217, 74, 0, 0.3);
        }
        
        .btn-outline-primary {
            color: #D94A00;
            border-color: #D94A00;
            border-radius: 8px;
        }
        
        .btn-outline-primary:hover {
            background-color: #D94A00;
            border-color: #D94A00;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .organizer-card {
            background: white;
            border: 1px solid #e1e8ed;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .organizer-card:hover {
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }
        
        .organizer-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        
        .organizer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #D94A00, #ff6b2c);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
        }
        
        .organizer-details h6 {
            margin: 0;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .organizer-details p {
            margin: 0;
            color: #657786;
            font-size: 13px;
        }
        
        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 4px;
        }
        
        .role-admin {
            background-color: rgba(217, 74, 0, 0.15);
            color: #D94A00;
        }
        
        .role-organizer {
            background-color: rgba(27, 94, 82, 0.15);
            color: #1B5E52;
        }
        
        .invitation-card {
            background: white;
            border: 1px solid #e1e8ed;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .invitation-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #fff3e0;
            color: #FFD700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-right: 15px;
        }
        
        .invitation-details {
            flex: 1;
        }
        
        .invitation-details p {
            margin: 0;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .invitation-details small {
            color: #657786;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e1e8ed;
            padding: 10px 14px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #D94A00;
            box-shadow: 0 0 0 3px rgba(217, 74, 0, 0.1);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #657786;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #e1e8ed;
            margin-bottom: 15px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: white;
            border: 1px solid #e1e8ed;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #D94A00;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #657786;
            font-size: 14px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include 'event_header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include 'event_nav.php'; ?>
            
            <div class="col-lg-9">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="breadcrumb-custom">
                        <a href="index.php"><i class="bi bi-house"></i> Home</a>
                        <span> / </span>
                        <a href="event_details.php?id=<?php echo $eventId; ?>">Event Details</a>
                        <span> / </span>
                        <span>Organizers Management</span>
                    </div>
                    <h1><i class="bi bi-people"></i> Organizers Management</h1>
                    <p class="text-muted">Manage organizers for: <strong><?php echo htmlspecialchars($event['titre']); ?></strong></p>
                </div>
        
        <!-- Alerts -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-number"><?php echo count($organizers); ?></div>
                <div class="stat-label">Total Organizers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo count(array_filter($organizers, fn($o) => $o['role'] === 'admin')); ?></div>
                <div class="stat-label">Administrators</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo count(array_filter($organizers, fn($o) => $o['role'] === 'organizer')); ?></div>
                <div class="stat-label">Organizers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo count($invitationsPending); ?></div>
                <div class="stat-label">Pending Invitations</div>
            </div>
        </div>
        
        <!-- Tabs -->
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="organizers-tab" data-bs-toggle="tab" data-bs-target="#organizers-content" type="button" role="tab">
                    <i class="bi bi-people me-2"></i>Organizers
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="invitations-tab" data-bs-toggle="tab" data-bs-target="#invitations-content" type="button" role="tab">
                    <i class="bi bi-envelope me-2"></i>Pending Invitations
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="invite-tab" data-bs-toggle="tab" data-bs-target="#invite-content" type="button" role="tab">
                    <i class="bi bi-person-plus me-2"></i>Invite New
                </button>
            </li>
        </ul>
        
        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Organizers Tab -->
            <div class="tab-pane fade show active" id="organizers-content" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5>Current Organizers</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($organizers)): ?>
                            <div class="empty-state">
                                <i class="bi bi-people"></i>
                                <p>No organizers yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($organizers as $organizer): ?>
                                <div class="organizer-card">
                                    <div class="organizer-info">
                                        <div class="organizer-avatar">
                                            <?php echo strtoupper(substr($organizer['nom'], 0, 1)); ?>
                                        </div>
                                        <div class="organizer-details">
                                            <h6><?php echo htmlspecialchars($organizer['nom']); ?></h6>
                                            <p><?php echo htmlspecialchars($organizer['email']); ?></p>
                                            <span class="role-badge <?php echo 'role-' . strtolower($organizer['role']); ?>">
                                                <?php echo ucfirst($organizer['role']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($organizer['id'] !== $event['created_by']): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this organizer?');">
                                                <input type="hidden" name="action" value="remove_organizer">
                                                <input type="hidden" name="organizer_id" value="<?php echo $organizer['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i> Remove
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Event Owner</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Pending Invitations Tab -->
            <div class="tab-pane fade" id="invitations-content" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5>Pending Invitations</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($invitationsPending)): ?>
                            <div class="empty-state">
                                <i class="bi bi-envelope"></i>
                                <p>No pending invitations</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($invitationsPending as $invitation): ?>
                                <div class="invitation-card">
                                    <div style="display: flex; align-items: center; flex: 1;">
                                        <div class="invitation-icon">
                                            <i class="bi bi-envelope"></i>
                                        </div>
                                        <div class="invitation-details">
                                            <p><?php echo htmlspecialchars($invitation['email']); ?></p>
                                            <small>
                                                Sent: <?php echo date('M d, Y H:i', strtotime($invitation['created_at'])); ?>
                                                | Expires: <?php echo date('M d, Y', strtotime($invitation['token_expiry'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Cancel this invitation?');">
                                        <input type="hidden" name="action" value="cancel_invitation">
                                        <input type="hidden" name="invitation_id" value="<?php echo $invitation['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x"></i> Cancel
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Invite New Tab -->
            <div class="tab-pane fade" id="invite-content" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5>Invite New Organizers</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="invite_organizer">
                            
                            <div class="mb-3">
                                <label for="emails" class="form-label">Email Addresses</label>
                                <textarea 
                                    class="form-control" 
                                    id="emails" 
                                    name="emails" 
                                    rows="6" 
                                    placeholder="Enter email addresses separated by commas or new lines&#10;Example:&#10;john@example.com&#10;jane@example.com&#10;bob@example.com"
                                    required
                                ></textarea>
                                <small class="form-text text-muted">
                                    Enter one or more email addresses separated by commas or new lines. 
                                    Existing users will be added immediately, new users will receive an invitation.
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-2"></i>Send Invitations
                            </button>
                            <a href="event_details.php?id=<?php echo $eventId; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Event
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
