<?php
/**
 * Event Participants Page
 * Displays all participants for an event in a detailed table view
 * Accessible to event organizers and admins
 */

session_start();
require_once 'role_check.php';

// Check if user is logged in
requireLogin();

require_once 'database.php';

$pdo = getDatabaseConnection();
$user_id = $_SESSION['user_id'];
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$event = null;
$participants = [];
$error = '';

// Validate event_id
if (!$event_id) {
    header('Location: organizer_dashboard.php');
    exit();
}

// Fetch event details
try {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = :event_id");
    $stmt->execute([':event_id' => $event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        $error = 'Event not found.';
    } else {
        // Check if user is organizer/admin of this event
        requireEventOrganizer($user_id, $event_id);
    }
} catch (PDOException $e) {
    error_log("Error fetching event: " . $e->getMessage());
    $error = 'An error occurred while loading the event.';
}

// Fetch all participants
if (!$error) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, u.nom, u.email, r.date_inscription,
                   (SELECT COUNT(*) FROM registrations WHERE user_id = u.id) as total_events
            FROM registrations r
            INNER JOIN users u ON r.user_id = u.id
            WHERE r.event_id = :event_id
            ORDER BY r.date_inscription DESC
        ");
        
        $stmt->execute([':event_id' => $event_id]);
        $participants = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching participants: " . $e->getMessage());
        $error = 'An error occurred while loading participants.';
    }
}

include 'header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2>Event Participants</h2>
        <p class="text-muted">
            <strong><?php echo htmlspecialchars($event['titre'] ?? 'Event'); ?></strong>
        </p>
    </div>
    <div class="col-md-4 text-end">
        <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-secondary">← Back to Event</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle"></i>
        <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($event && !$error): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="mb-0">
                        <i class="bi bi-people"></i> Participants
                    </h5>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-light text-dark">
                        <?php echo count($participants); ?>/<?php echo htmlspecialchars($event['nb_max_participants']); ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($participants)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.5;"></i>
                    <p class="mt-3 mb-0">No participants registered yet</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 30%;">Name</th>
                                <th style="width: 35%;">Email</th>
                                <th class="text-center" style="width: 15%;">Events Attended</th>
                                <th class="text-end" style="width: 20%;">Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participants as $participant): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($participant['nom']); ?></strong>
                                    </td>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($participant['email']); ?>">
                                            <?php echo htmlspecialchars($participant['email']); ?>
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info">
                                            <?php echo (int)$participant['total_events']; ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($participant['date_inscription'])); ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-light">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <small class="text-muted">
                                Showing <strong><?php echo count($participants); ?></strong> of <strong><?php echo htmlspecialchars($event['nb_max_participants']); ?></strong> available spots
                            </small>
                        </div>
                        <div class="col-md-6 text-end">
                            <small class="text-muted">
                                Capacity: <strong><?php echo round((count($participants) / (int)$event['nb_max_participants']) * 100); ?>%</strong>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Event Summary Card -->
    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">Event Information</h6>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong>Date & Time:</strong><br>
                        <?php echo date('F j, Y \a\t g:i A', strtotime($event['date'])); ?>
                    </p>
                    <p class="mb-2">
                        <strong>Location:</strong><br>
                        <?php echo htmlspecialchars($event['lieu']); ?>
                    </p>
                    <p class="mb-0">
                        <strong>Max Capacity:</strong><br>
                        <?php echo htmlspecialchars($event['nb_max_participants']); ?> participants
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">Attendance Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Registered Participants</span>
                            <strong><?php echo count($participants); ?>/<?php echo htmlspecialchars($event['nb_max_participants']); ?></strong>
                        </div>
                        <div class="progress" role="progressbar" 
                             aria-valuenow="<?php echo count($participants); ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="<?php echo (int)$event['nb_max_participants']; ?>">
                            <div class="progress-bar" 
                                 style="width: <?php echo round((count($participants) / (int)$event['nb_max_participants']) * 100); ?>%">
                            </div>
                        </div>
                    </div>
                    <p class="mb-0 text-muted small">
                        <strong><?php echo ((int)$event['nb_max_participants'] - count($participants)); ?></strong> spots remaining
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require 'footer.php'; ?>
