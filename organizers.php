<?php

session_start();

require 'database.php';
require_once 'role_check.php';
require_once 'notifications.php';


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
    $error = 'Erreur de chargement de l\'événement.';
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
                
                $success = 'Organisateur supprimé avec succès.';
                
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
                $error = 'Impossible de supprimer le propriétaire de l\'événement.';
            }
        } catch (PDOException $e) {
            error_log('Error removing organizer: ' . $e->getMessage());
            $error = 'Erreur lors de la suppression de l\'organisateur. Veuillez réessayer.';
        }
    } else {
        $error = 'Impossible de supprimer le propriétaire de l\'événement.';
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
            
            $success = 'Invitation annulée avec succès.';
            
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
            $error = 'Erreur lors de l\'annulation de l\'invitation. Veuillez réessayer.';
        }
    }
}

// Handle new organizer invitation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'invite_organizer') {
    $emails = isset($_POST['emails']) ? trim($_POST['emails']) : '';
    
    if (empty($emails)) {
        $error = 'Veuillez saisir au moins une adresse e-mail.';
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
                    $errors[] = "Format d\'e-mail invalide : " . htmlspecialchars($email);
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
                        $errors[] = htmlspecialchars($email) . " a déjà un rôle pour cet événement.";
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
                        $msg = "Vous avez été ajouté comme organisateur pour l\'événement '" . $event['titre'] . "'.";
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
                $success = $invited_count . " invitations envoyées avec succès!";
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
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Invitation error: " . $e->getMessage());
            $error = 'Une erreur est survenue lors de l\'envoi des invitations.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventUp - Gestion des Organisateurs</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <style>
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


body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background-color: var(--bg-light);
    color: var(--text-dark);
    padding-top: 76px;
}


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


.sidebar {
    position: fixed;
    top: 76px;
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 0;
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


.page-header {
    margin-bottom: 32px;
}

.page-header h1 {
    font-size: 32px;
    font-weight: 700;
    color: var(--primary-teal);
    margin-bottom: 8px;
}

.page-header p {
    color: var(--text-muted);
    margin-bottom: 0;
}

.breadcrumb-custom {
    font-size: 14px;
    color: var(--text-muted);
    margin-bottom: 16px;
}

.breadcrumb-custom a {
    color: var(--primary-orange);
    text-decoration: none;
    transition: all 0.3s ease;
}

.breadcrumb-custom a:hover {
    color: var(--light-orange);
}


.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.stat-box {
    background: white;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
    text-align: center;
}

.stat-box:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    color: var(--primary-orange);
    margin-bottom: 8px;
}

.stat-label {
    font-size: 14px;
    color: var(--text-muted);
    font-weight: 600;
}


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
    border-color: var(--text-muted);
    color: var(--text-dark);
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


.badge-secondary {
    background: rgba(108, 117, 125, 0.15);
    color: #6c757d;
}


.role-admin {
    background: rgba(217, 74, 0, 0.15);
    color: var(--primary-orange);
}


.role-organizer {
    background: rgba(27, 94, 82, 0.15);
    color: var(--primary-teal);
}


.role-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 12px;
}


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
    margin-bottom: 0;
}


.card-body {
    padding: 24px;
}


.organizer-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    margin-bottom: 16px;
    transition: all 0.3s ease;
    background: white;
}


.organizer-card:hover {
    background: var(--bg-light);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}


.organizer-info {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1;
}


.organizer-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    flex-shrink: 0;
}


.organizer-details h6 {
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 4px;
}


.organizer-details p {
    color: var(--text-muted);
    font-size: 14px;
    margin-bottom: 8px;
}


.invitation-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    margin-bottom: 16px;
    transition: all 0.3s ease;
    background: white;
}


.invitation-card:hover {
    background: var(--bg-light);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}


.invitation-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--info-blue), #357ABD);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
    margin-right: 16px;
}


.invitation-details p {
    color: var(--text-dark);
    font-weight: 600;
    margin-bottom: 4px;
}


.invitation-details small {
    color: var(--text-muted);
    font-size: 13px;
}


.empty-state {
    text-align: center;
    padding: 48px 24px;
    color: var(--text-muted);
}


.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}


.empty-state p {
    font-size: 16px;
    margin-bottom: 0;
}


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


.form-text {
    color: var(--text-muted);
    font-size: 13px;
}


.alert {
    border: none;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 24px;
}


.alert-success {
    background: rgba(46, 213, 115, 0.1);
    color: #2ed573;
    border-left: 4px solid #2ed573;
}


.alert-danger {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
    border-left: 4px solid #dc3545;
}


.alert-dismissible .btn-close {
    color: currentColor;
    opacity: 0.7;
}


.alert-dismissible .btn-close:hover {
    opacity: 1;
}


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
    
    .organizer-card,
    .invitation-card {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .organizer-card > div:last-child,
    .invitation-card > form {
        width: 100%;
        margin-top: 16px;
    }
    
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 576px) {
    .stats-row {
        grid-template-columns: 1fr;
    }
    
    .page-header h1 {
        font-size: 24px;
    }
}
    </style>
</head>
<body>
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
            
            <main class="col-lg-9 px-md-4 main-content">
                <div class="page-header">
                   
                    <h1><i class="bi bi-people"></i> Gestion des Organisateurs</h1>
                    <p>Gérer les organisateurs pour : <strong><?php echo htmlspecialchars($event['titre']); ?></strong></p>
                </div>
        
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
        
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-number"><?php echo count($organizers); ?></div>
                <div class="stat-label">Total des Organisateurs</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo count(array_filter($organizers, fn($o) => $o['role'] === 'admin')); ?></div>
                <div class="stat-label">Administrateurs</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo count(array_filter($organizers, fn($o) => $o['role'] === 'organizer')); ?></div>
                <div class="stat-label">Organisateurs</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?php echo count($invitationsPending); ?></div>
                <div class="stat-label">Invitations en attente</div>
            </div>
        </div>
        
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="organizers-tab" data-bs-toggle="tab" data-bs-target="#organizers-content" type="button" role="tab">
                    <i class="bi bi-people me-2"></i>Organisateurs
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="invitations-tab" data-bs-toggle="tab" data-bs-target="#invitations-content" type="button" role="tab">
                    <i class="bi bi-envelope me-2"></i>Invitations en attente
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="invite-tab" data-bs-toggle="tab" data-bs-target="#invite-content" type="button" role="tab">
                    <i class="bi bi-person-plus me-2"></i>Inviter de nouveaux
                </button>
            </li>
        </ul>
        
        <div class="tab-content">
            <div class="tab-pane fade show active" id="organizers-content" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5>Organisateurs actuels</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($organizers)): ?>
                            <div class="empty-state">
                                <i class="bi bi-people"></i>
                                <p>Aucun organisateur pour le moment</p>
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
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet organisateur?');">
                                                <input type="hidden" name="action" value="remove_organizer">
                                                <input type="hidden" name="organizer_id" value="<?php echo $organizer['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i> Supprimer
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Propriétaire de l\'événement</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="invitations-content" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5>Invitations en attente</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($invitationsPending)): ?>
                            <div class="empty-state">
                                <i class="bi bi-envelope"></i>
                                <p>Aucune invitation en attente</p>
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
                                                Envoyée : <?php echo date('d M Y H:i', strtotime($invitation['created_at'])); ?>
                                                | Expire : <?php echo date('d M Y', strtotime($invitation['token_expiry'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Annuler cette invitation?');">
                                        <input type="hidden" name="action" value="cancel_invitation">
                                        <input type="hidden" name="invitation_id" value="<?php echo $invitation['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x"></i> Annuler
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="tab-pane fade" id="invite-content" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5>Inviter de nouveaux organisateurs</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="invite_organizer">
                            
                            <div class="mb-3">
                                <label for="emails" class="form-label">Adresses e-mail</label>
                                <textarea 
                                    class="form-control" 
                                    id="emails" 
                                    name="emails" 
                                    rows="6" 
                                    placeholder="Entrez les adresses e-mail séparées par des virgules ou de nouvelles lignes&#10;Exemple :&#10;john@example.com&#10;jane@example.com&#10;bob@example.com"
                                    required
                                ></textarea>
                                <small class="form-text">
                                    Entrez une ou plusieurs adresses e-mail séparées par des virgules ou de nouvelles lignes. 
                                    Les utilisateurs existants seront ajoutés immédiatement, les nouveaux utilisateurs recevront une invitation.
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-2"></i>Envoyer les invitations
                            </button>
                            <a href="event_details.php?id=<?php echo $eventId; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Retour à l\'événement
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
            </main>
        </div>
    </div>
    
    <?php require_once 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
