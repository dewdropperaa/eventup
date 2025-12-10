<?php
session_start();
require 'database.php';
require 'role_check.php';

// Check if user is logged in
requireLogin();

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$error = '';
$event = null;
$organizers = [];
$permissions = [];

if ($eventId <= 0) {
    $error = 'Invalid event ID.';
} else {
    $pdo = getDatabaseConnection();
    
    // Fetch event details and check if current user is the owner
    $stmt = $pdo->prepare('SELECT titre, created_by FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();

    if (!$event || $event['created_by'] != $_SESSION['user_id']) {
        // If the user is not the owner, check if they are a co-owner with permission to manage permissions
        // This part can be enhanced later if co-owner roles are fleshed out
        $error = 'Unauthorized access. You are not the owner of this event.';
        $event = null; // Don't display the page content
    } else {
        // Set role variables for navigation
        $isEventOwner = true;
        $isEventAdmin = isEventAdmin($_SESSION['user_id'], $eventId);
        $isEventOrganizer = isEventOrganizer($_SESSION['user_id'], $eventId);
        // Fetch organizers for this event from the new event_organizers table
        $stmt = $pdo->prepare('SELECT u.id, u.nom, u.email, eo.role FROM event_organizers eo JOIN users u ON eo.user_id = u.id WHERE eo.event_id = ? ORDER BY u.nom');
        $stmt->execute([$eventId]);
        $organizers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all existing permissions for this event
        $stmt = $pdo->prepare('SELECT user_id, permission_name, is_allowed FROM event_permissions WHERE event_id = ?');
        $stmt->execute([$eventId]);
        $permsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Organize permissions by user_id for easier lookup
        foreach ($permsData as $p) {
            $permissions[$p['user_id']][$p['permission_name']] = $p['is_allowed'];
        }
    }
}

// Define the list of manageable permissions
$permission_list = [
    'can_edit_budget' => 'Edit Budget',
    'can_manage_resources' => 'Manage Resources',
    'can_invite_organizers' => 'Invite Organizers',
    'can_publish_updates' => 'Publish Updates'
];

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventUp - Gestion des Permissions</title>
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

<div class="container-fluid mt-4">
    <div class="row">
        <?php if (!$error): ?>
            <?php include 'event_nav.php'; ?>
        <?php endif; ?>
        
        <div class="col-lg-9">
            <?php if ($error): ?>
                <div class="alert alert-danger"><h4><i class="bi bi-exclamation-triangle-fill"></i> Access Denied</h4><p><?php echo htmlspecialchars($error); ?></p></div>
            <?php elseif ($event): ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="mb-0">Permissions for "<?php echo htmlspecialchars($event['titre']); ?>"</h1>
                    <a href="event_details.php?id=<?php echo $eventId; ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Event</a>
                </div>

        <?php if (empty($organizers)): ?>
            <div class="alert alert-info text-center">
                <p class="mb-0">There are no organizers assigned to this event yet. <br>You can invite organizers from the event management page.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($organizers as $organizer): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-header d-flex align-items-center">
                                <img src="https://i.pravatar.cc/50?u=<?php echo $organizer['email']; ?>" alt="Avatar" class="rounded-circle me-3" width="50" height="50">
                                <div>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($organizer['nom']); ?></h5>
                                    <small class="text-muted"><?php echo htmlspecialchars($organizer['email']); ?></small>
                                </div>
                                <span class="badge bg-primary ms-auto"><?php echo htmlspecialchars(ucfirst($organizer['role'])); ?></span>
                            </div>
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">Permissions</h6>
                                <?php foreach ($permission_list as $perm_key => $perm_label): ?>
                                    <?php 
                                        $is_allowed = $permissions[$organizer['id']][$perm_key] ?? 0;
                                    ?>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input permission-switch" type="checkbox" role="switch" 
                                               id="perm-<?php echo $organizer['id']; ?>-<?php echo $perm_key; ?>" 
                                               data-event-id="<?php echo $eventId; ?>" 
                                               data-user-id="<?php echo $organizer['id']; ?>" 
                                               data-permission-name="<?php echo $perm_key; ?>" 
                                               <?php echo $is_allowed ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="perm-<?php echo $organizer['id']; ?>-<?php echo $perm_key; ?>">
                                            <?php echo htmlspecialchars($perm_label); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-warning">Event not found or access denied.</div>
        <?php endif; ?>
        </div>
    </div>
</div>

<!-- Toast container for notifications -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
  <div id="permissionToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <strong class="me-auto">Notification</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body">
      Permission updated successfully.
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
