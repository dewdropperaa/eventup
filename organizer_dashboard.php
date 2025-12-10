<?php
/**
 * Organizer Dashboard Page
 * Displays events where the user is admin or organizer
 * Shows event info, participants, and assigned organizers
 */

session_start();
require_once 'role_check.php';

// Check if user is logged in
requireLogin();

// Check if user is an organizer
requireOrganizer();

require_once 'database.php';

$pdo = getDatabaseConnection();
$user_id = $_SESSION['user_id'];

try {
    // Fetch all events where user is admin or organizer
    $stmt = $pdo->prepare("
        SELECT DISTINCT e.id, e.titre, e.description, e.date, e.lieu, e.nb_max_participants,
               er.role
        FROM events e
        INNER JOIN event_roles er ON e.id = er.event_id
        WHERE er.user_id = :user_id AND er.role IN ('admin', 'organizer')
        ORDER BY e.date DESC
    ");
    
    $stmt->execute([':user_id' => $user_id]);
    $events = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching organizer events: " . $e->getMessage());
    $events = [];
}

include 'header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i>
        <strong>Success!</strong> <?php echo htmlspecialchars($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2>Organizer Dashboard</h2>
        <p class="text-muted">Manage events where you are an admin or organizer</p>
    </div>
    <div class="col-md-4 text-end">
        <a href="create_event.php" class="btn btn-primary">+ Create New Event</a>
    </div>
</div>

<?php if (empty($events)): ?>
    <div class="alert alert-info" role="alert">
        <strong>No events yet!</strong> You are not assigned as an admin or organizer to any events.
        <a href="create_event.php" class="alert-link">Create your first event</a>.
    </div>
<?php else: ?>
    <?php foreach ($events as $event): ?>
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-0"><?php echo htmlspecialchars($event['titre']); ?></h5>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-light text-dark">
                            <?php echo ucfirst($event['role']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Event Info Section -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Event Details</h6>
                        <p>
                            <strong>Description:</strong><br>
                            <?php echo htmlspecialchars($event['description']); ?>
                        </p>
                        <p>
                            <strong>Date & Time:</strong><br>
                            <?php echo date('F j, Y \a\t g:i A', strtotime($event['date'])); ?>
                        </p>
                        <p>
                            <strong>Location:</strong><br>
                            <?php echo htmlspecialchars($event['lieu']); ?>
                        </p>
                        <p>
                            <strong>Max Participants:</strong><br>
                            <?php echo htmlspecialchars($event['nb_max_participants']); ?>
                        </p>
                    </div>
                    
                    <!-- Participants Section -->
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-people"></i> Participants
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <?php
                                try {
                                    $stmt = $pdo->prepare("
                                        SELECT u.id, u.nom, u.email, r.date_inscription
                                        FROM registrations r
                                        INNER JOIN users u ON r.user_id = u.id
                                        WHERE r.event_id = :event_id
                                        ORDER BY r.date_inscription ASC
                                    ");
                                    
                                    $stmt->execute([':event_id' => $event['id']]);
                                    $participants = $stmt->fetchAll();
                                    
                                    if (empty($participants)):
                                ?>
                                        <div class="p-3 text-center text-muted">
                                            <p class="mb-0">No participants yet</p>
                                        </div>
                                <?php
                                    else:
                                ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-sm mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Email</th>
                                                        <th class="text-end">Registered</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($participants as $participant): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($participant['nom']); ?></strong>
                                                            </td>
                                                            <td>
                                                                <small class="text-muted"><?php echo htmlspecialchars($participant['email']); ?></small>
                                                            </td>
                                                            <td class="text-end">
                                                                <small class="text-muted"><?php echo date('M d, Y', strtotime($participant['date_inscription'])); ?></small>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="card-footer bg-light">
                                            <small class="text-muted">
                                                <strong><?php echo count($participants); ?>/<?php echo htmlspecialchars($event['nb_max_participants']); ?></strong> participants registered
                                            </small>
                                        </div>
                                <?php
                                    endif;
                                } catch (PDOException $e) {
                                    error_log("Error fetching participants: " . $e->getMessage());
                                    echo '<div class="p-3"><p class="text-danger mb-0">Error loading participants</p></div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Organizers Section -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0">
                                    <i class="bi bi-person-check"></i> Assigned Organizers
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <?php
                                try {
                                    $stmt = $pdo->prepare("
                                        SELECT u.id, u.nom, u.email, er.role
                                        FROM event_roles er
                                        INNER JOIN users u ON er.user_id = u.id
                                        WHERE er.event_id = :event_id AND er.role IN ('admin', 'organizer')
                                        ORDER BY er.role DESC, u.nom ASC
                                    ");
                                    
                                    $stmt->execute([':event_id' => $event['id']]);
                                    $organizers = $stmt->fetchAll();
                                    
                                    if (empty($organizers)):
                                ?>
                                        <div class="p-3 text-center text-muted">
                                            <p class="mb-0">No organizers assigned</p>
                                        </div>
                                <?php
                                    else:
                                ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-sm mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Email</th>
                                                        <th class="text-center">Role</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($organizers as $organizer): ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($organizer['nom']); ?></strong>
                                                            </td>
                                                            <td>
                                                                <small class="text-muted"><?php echo htmlspecialchars($organizer['email']); ?></small>
                                                            </td>
                                                            <td class="text-center">
                                                                <span class="badge bg-<?php echo $organizer['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                                                    <?php echo ucfirst($organizer['role']); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="card-footer bg-light">
                                            <small class="text-muted">
                                                <strong><?php echo count($organizers); ?></strong> organizer<?php echo count($organizers) !== 1 ? 's' : ''; ?> assigned
                                            </small>
                                        </div>
                                <?php
                                    endif;
                                } catch (PDOException $e) {
                                    error_log("Error fetching organizers: " . $e->getMessage());
                                    echo '<div class="p-3"><p class="text-danger mb-0">Error loading organizers</p></div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card Footer with Actions -->
            <div class="card-footer bg-light">
                <div class="d-flex gap-2 flex-wrap">
                    <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-eye"></i> View Details
                    </a>
                    <a href="event_participants.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-people"></i> Participants
                    </a>
                    <?php if ($event['role'] === 'admin'): ?>
                        <a href="edit_event.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <a href="invite_organizers.php?event_id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-person-plus"></i> Invite
                        </a>
                        <a href="manage_tasks.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-list-check"></i> Tasks
                        </a>
                        <button 
                            type="button" 
                            class="btn btn-sm btn-outline-danger" 
                            data-bs-toggle="modal" 
                            data-bs-target="#deleteEventModal"
                            data-event-id="<?php echo $event['id']; ?>"
                            data-event-title="<?php echo htmlspecialchars($event['titre']); ?>"
                        >
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Delete Event Confirmation Modal -->
<div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteEventLabel">Confirm Event Deletion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the event <strong id="eventTitleDisplay"></strong>?</p>
                <p class="text-danger"><strong>This action cannot be undone! All participants, organizers, and tasks will be deleted.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="edit_event.php" style="display: inline;" id="deleteEventForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="event_id" id="deleteEventId" value="">
                    <button type="submit" class="btn btn-danger">Delete Permanently</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete Event Modal Handler
    const deleteEventModal = document.getElementById('deleteEventModal');
    if (deleteEventModal) {
        deleteEventModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const eventId = button.getAttribute('data-event-id');
            const eventTitle = button.getAttribute('data-event-title');
            
            document.getElementById('eventTitleDisplay').textContent = eventTitle;
            document.getElementById('deleteEventId').value = eventId;
            
            // Update form action with event ID
            const form = document.getElementById('deleteEventForm');
            form.action = 'edit_event.php?id=' + eventId;
        });
    }
});
</script>

<?php include 'footer.php'; ?>
