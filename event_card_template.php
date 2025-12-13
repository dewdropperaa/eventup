<?php
/**
 * Event Card Template
 * This template is included to display event cards in the browse events page
 * $event variable should be available when this template is included
 */

if (!isset($event)) {
    return;
}

$eventDate = new DateTime($event['date']);
$formattedDate = $eventDate->format('d/m/Y H:i');
$placesLeft = max(0, $event['places_left']);
$registeredCount = $event['registered_count'];
$maxParticipants = $event['nb_max_participants'];
$isFull = $placesLeft <= 0;

// Check if current user is registered for this event
$isRegistered = false;
if (isset($_SESSION['user_id'])) {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM registrations WHERE user_id = :user_id AND event_id = :event_id");
        $stmt->execute([':user_id' => $_SESSION['user_id'], ':event_id' => $event['id']]);
        $isRegistered = $stmt->fetch()['count'] > 0;
    } catch (Exception $e) {
        error_log('Error checking registration status: ' . $e->getMessage());
    }
}
?>

<div class="col">
    <div class="card h-100 shadow-sm position-relative">
        <!-- Category Badge -->
        <div class="position-absolute top-0 end-0 p-2">
            <span class="badge bg-primary text-white">
                <?php echo htmlspecialchars($event['category']); ?>
            </span>
        </div>

        <div class="card-body d-flex flex-column">
            <h5 class="card-title pe-5"><?php echo htmlspecialchars($event['titre']); ?></h5>
            
            <!-- Description -->
            <p class="card-text text-muted small mb-3">
                <?php 
                    $desc = htmlspecialchars($event['description']);
                    echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                ?>
            </p>

            <!-- Event Details -->
            <div class="mb-3">
                <p class="card-text mb-2">
                    <i class="bi bi-calendar-event text-primary"></i>
                    <strong>Date:</strong>
                    <?php echo htmlspecialchars($formattedDate); ?>
                </p>
                <p class="card-text mb-2">
                    <i class="bi bi-geo-alt text-danger"></i>
                    <strong>Location:</strong>
                    <?php echo htmlspecialchars($event['lieu']); ?>
                </p>
            </div>

            <!-- Registration Status and Places Left -->
            <div class="mb-3 p-2 bg-light rounded">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">
                        <i class="bi bi-people"></i>
                        <strong><?php echo $registeredCount; ?>/<?php echo $maxParticipants; ?></strong> registered
                    </span>
                    <?php if ($isRegistered): ?>
                        <span class="badge bg-success">
                            <i class="bi bi-check-circle"></i> Registered
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Places Left Progress Bar -->
                <div class="progress" style="height: 20px;">
                    <div 
                        class="progress-bar <?php echo $isFull ? 'bg-danger' : 'bg-success'; ?>" 
                        role="progressbar" 
                        style="width: <?php echo ($registeredCount / $maxParticipants) * 100; ?>%"
                        aria-valuenow="<?php echo $registeredCount; ?>" 
                        aria-valuemin="0" 
                        aria-valuemax="<?php echo $maxParticipants; ?>"
                    >
                        <small class="text-white fw-bold">
                            <?php echo $placesLeft; ?> left
                        </small>
                    </div>
                </div>
            </div>

            <!-- Status Badge -->
            <?php if ($isFull): ?>
                <div class="alert alert-danger alert-sm py-2 mb-3" role="alert">
                    <i class="bi bi-exclamation-circle"></i> Event is full
                </div>
            <?php elseif ($placesLeft <= 3): ?>
                <div class="alert alert-warning alert-sm py-2 mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> Only <?php echo $placesLeft; ?> place<?php echo $placesLeft !== 1 ? 's' : ''; ?> left!
                </div>
            <?php endif; ?>
        </div>

        <!-- Card Footer with Action -->
        <div class="card-footer bg-white border-top">
            <a href="event_details.php?id=<?php echo (int) $event['id']; ?>" class="btn btn-primary btn-sm w-100">
                <i class="bi bi-eye"></i> View Details
            </a>
        </div>
    </div>
</div>
