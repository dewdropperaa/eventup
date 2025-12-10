<?php
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

// Check if user has permission to manage resources
if (!canDo($eventId, $_SESSION['user_id'], 'can_manage_resources')) {
    header('Location: event_details.php?id=' . $eventId);
    exit;
}

$pdo = getDatabaseConnection();
$error = '';
$success = '';
$resources = [];
$bookings = [];
$event = null;

// Fetch event details
try {
    $stmt = $pdo->prepare('SELECT id, titre FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event) {
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    error_log('Error fetching event: ' . $e->getMessage());
    $error = 'Error loading event.';
}

// Fetch resources
try {
    $stmt = $pdo->prepare('
        SELECT id, nom, type, quantite_totale, description, 
               date_disponibilite_debut, date_disponibilite_fin, 
               image_path, statut, created_at
        FROM event_resources 
        WHERE event_id = ? 
        ORDER BY created_at DESC
    ');
    $stmt->execute([$eventId]);
    $resources = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching resources: ' . $e->getMessage());
}

// Fetch bookings
try {
    $stmt = $pdo->prepare('
        SELECT rb.id, rb.resource_id, rb.date_debut, rb.date_fin, 
               rb.statut, rb.notes, u.nom as user_nom, er.nom as resource_nom
        FROM resource_bookings rb
        JOIN users u ON rb.user_id = u.id
        JOIN event_resources er ON rb.resource_id = er.id
        WHERE rb.event_id = ? 
        ORDER BY rb.date_debut DESC
    ');
    $stmt->execute([$eventId]);
    $bookings = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching bookings: ' . $e->getMessage());
}

// Handle resource deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_resource') {
    $resourceId = isset($_POST['resource_id']) ? (int) $_POST['resource_id'] : 0;
    
    if ($resourceId > 0) {
        try {
            // Check if resource belongs to this event
            $stmt = $pdo->prepare('SELECT id FROM event_resources WHERE id = ? AND event_id = ?');
            $stmt->execute([$resourceId, $eventId]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare('DELETE FROM event_resources WHERE id = ?');
                $stmt->execute([$resourceId]);
                $success = 'Resource deleted successfully.';
                
                // Refresh resources
                $stmt = $pdo->prepare('
                    SELECT id, nom, type, quantite_totale, description, 
                           date_disponibilite_debut, date_disponibilite_fin, 
                           image_path, statut, created_at
                    FROM event_resources 
                    WHERE event_id = ? 
                    ORDER BY created_at DESC
                ');
                $stmt->execute([$eventId]);
                $resources = $stmt->fetchAll();
            }
        } catch (Exception $e) {
            error_log('Error deleting resource: ' . $e->getMessage());
            $error = 'Error deleting resource.';
        }
    }
}

// Handle booking cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
    $bookingId = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
    
    if ($bookingId > 0) {
        try {
            $stmt = $pdo->prepare('UPDATE resource_bookings SET statut = "Annulée" WHERE id = ? AND event_id = ?');
            $stmt->execute([$bookingId, $eventId]);
            $success = 'Booking cancelled successfully.';
            
            // Refresh bookings
            $stmt = $pdo->prepare('
                SELECT rb.id, rb.resource_id, rb.date_debut, rb.date_fin, 
                       rb.statut, rb.notes, u.nom as user_nom, er.nom as resource_nom
                FROM resource_bookings rb
                JOIN users u ON rb.user_id = u.id
                JOIN event_resources er ON rb.resource_id = er.id
                WHERE rb.event_id = ? 
                ORDER BY rb.date_debut DESC
            ');
            $stmt->execute([$eventId]);
            $bookings = $stmt->fetchAll();
        } catch (Exception $e) {
            error_log('Error cancelling booking: ' . $e->getMessage());
            $error = 'Error cancelling booking.';
        }
    }
}

?>

<style>
:root {
    --primary-orange: #d95400;
    --dark-green: #084838;
    --light-green: #0a6b51;
    --yellow: #ffe500;
    --bg-light: #f8f9fa;
    --text-dark: #212529;
    --text-muted: #6c757d;
}

.resources-header {
    margin-bottom: 3rem;
    padding-bottom: 2rem;
    border-bottom: 2px solid #e9ecef;
}

.resources-header h1 {
    font-size: 2.5rem;
    font-weight: 900;
    color: var(--dark-green);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.resources-header p {
    color: var(--text-muted);
    font-size: 1.05rem;
    margin: 0;
}

.resources-tabs {
    border-bottom: 2px solid #e9ecef;
    margin-bottom: 2rem;
}

.resources-tabs .nav-link {
    color: var(--text-muted);
    border: none;
    padding: 1rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s;
    position: relative;
}

.resources-tabs .nav-link:hover {
    color: var(--primary-orange);
}

.resources-tabs .nav-link.active {
    color: var(--primary-orange);
    background: none;
    border-bottom: 3px solid var(--primary-orange);
    padding-bottom: calc(1rem - 3px);
}

.resources-tabs .nav-link i {
    margin-right: 0.5rem;
}

.btn-add-resource {
    background: var(--primary-orange);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-add-resource:hover {
    background: #c94700;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(217, 84, 0, 0.3);
    color: white;
}

.resource-card {
    border: none;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    height: 100%;
    display: flex;
    flex-direction: column;
}

.resource-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.resource-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    margin-bottom: 1rem;
}

.resource-card-body {
    flex-grow: 1;
}

.resource-card-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 0.5rem;
}

.resource-card-subtitle {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
}

.resource-status-badge {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.status-disponible {
    background: #d4edda;
    color: #155724;
}

.status-maintenance {
    background: #fff3cd;
    color: #856404;
}

.status-indisponible {
    background: #f8d7da;
    color: #721c24;
}

.resource-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e9ecef;
}

.btn-resource-action {
    flex: 1;
    padding: 0.6rem;
    border-radius: 8px;
    border: 1.5px solid #e9ecef;
    background: white;
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
}

.btn-resource-action:hover {
    border-color: var(--primary-orange);
    color: var(--primary-orange);
}

.btn-resource-action.delete:hover {
    border-color: #dc3545;
    color: #dc3545;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark-green);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: var(--bg-light);
    border-radius: 12px;
}

.empty-state i {
    font-size: 3rem;
    color: var(--text-muted);
    margin-bottom: 1rem;
}

.empty-state p {
    color: var(--text-muted);
    margin: 0;
}
</style>

<div class="resources-header">
    <h1>
        <i class="bi bi-box-seam" style="color: var(--primary-orange);"></i>
        Gestion des ressources
    </h1>
    <p><?php echo htmlspecialchars($event['titre']); ?></p>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius: 10px; border: none; background: #fef2f2; color: #dc2626; border-left: 4px solid #dc2626;">
        <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius: 10px; border: none; background: #f0fdf4; color: #16a34a; border-left: 4px solid #16a34a;">
        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Tabs Navigation -->
<ul class="nav resources-tabs" id="resourceTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="resources-tab" data-bs-toggle="tab" data-bs-target="#resources" type="button" role="tab">
            <i class="bi bi-box"></i> Ressources réservables
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="bookings-tab" data-bs-toggle="tab" data-bs-target="#bookings" type="button" role="tab">
            <i class="bi bi-calendar-check"></i> Réservations
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab">
            <i class="bi bi-bar-chart"></i> Statistiques
        </button>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content" id="resourceTabContent">
    <!-- Resources Tab -->
    <div class="tab-pane fade show active" id="resources" role="tabpanel">
        <div class="row mb-4 align-items-center">
            <div class="col">
                <h3 class="section-title">
                    <i class="bi bi-box-seam" style="color: var(--primary-orange);"></i>
                    Ressources réservables
                </h3>
            </div>
            <div class="col-auto">
                <button class="btn-add-resource" data-bs-toggle="modal" data-bs-target="#addResourceModal">
                    <i class="bi bi-plus-circle"></i> Ajouter une ressource
                </button>
            </div>
        </div>

        <?php if (empty($resources)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>Aucune ressource créée. Cliquez sur "Ajouter une ressource" pour commencer.</p>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
                <?php foreach ($resources as $resource): 
                    // Determine icon and color based on type
                    $iconClass = 'bi-box';
                    $bgColor = 'var(--primary-orange)';
                    
                    if (strpos($resource['type'], 'Salle') !== false) {
                        $iconClass = 'bi-door-closed';
                        $bgColor = '#084838';
                    } elseif (strpos($resource['type'], 'Matériel') !== false) {
                        $iconClass = 'bi-tools';
                        $bgColor = '#0a6b51';
                    } elseif (strpos($resource['type'], 'Véhicule') !== false) {
                        $iconClass = 'bi-car-front';
                        $bgColor = '#4a90e2';
                    }
                ?>
                    <div class="col">
                        <div class="resource-card">
                            <!-- Icon Header -->
                            <div style="padding: 1.5rem 1.5rem 0; background: white;">
                                <div class="resource-icon" style="background: <?php echo $bgColor; ?>; color: white;">
                                    <i class="bi <?php echo $iconClass; ?>"></i>
                                </div>
                            </div>
                            
                            <!-- Card Content -->
                            <div class="resource-card-body" style="padding: 0 1.5rem;">
                                <h5 class="resource-card-title"><?php echo htmlspecialchars($resource['nom']); ?></h5>
                                <p class="resource-card-subtitle">
                                    <?php echo htmlspecialchars($resource['type']); ?>
                                </p>
                                
                                <!-- Status Badge -->
                                <div class="resource-status-badge status-<?php 
                                    echo strtolower(str_replace(' ', '-', $resource['statut'])); 
                                ?>">
                                    <?php echo htmlspecialchars($resource['statut']); ?>
                                </div>
                                
                                <!-- Details -->
                                <div style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem;">
                                    <?php if ($resource['quantite_totale']): ?>
                                        <p style="margin: 0.3rem 0;">
                                            <i class="bi bi-box" style="color: var(--primary-orange);"></i>
                                            Quantité: <?php echo (int) $resource['quantite_totale']; ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($resource['description']): ?>
                                        <p style="margin: 0.3rem 0;">
                                            <?php echo htmlspecialchars(substr($resource['description'], 0, 80)); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($resource['date_disponibilite_debut']): ?>
                                        <p style="margin: 0.3rem 0;">
                                            <i class="bi bi-calendar"></i>
                                            <?php echo date('d/m/Y', strtotime($resource['date_disponibilite_debut'])); ?> - 
                                            <?php echo date('d/m/Y', strtotime($resource['date_disponibilite_fin'])); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="resource-actions" style="padding: 1rem 1.5rem 1.5rem;">
                                <button class="btn-resource-action" data-bs-toggle="modal" data-bs-target="#editResourceModal" 
                                        onclick="loadResourceEdit(<?php echo $resource['id']; ?>)" title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" style="display: contents;" onsubmit="return confirm('Supprimer cette ressource?');">
                                    <input type="hidden" name="action" value="delete_resource">
                                    <input type="hidden" name="resource_id" value="<?php echo $resource['id']; ?>">
                                    <button type="submit" class="btn-resource-action delete" title="Supprimer">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bookings Tab -->
    <div class="tab-pane fade" id="bookings" role="tabpanel">
        <div class="row mb-4 align-items-center">
            <div class="col">
                <h3 class="section-title">
                    <i class="bi bi-calendar-check" style="color: var(--primary-orange);"></i>
                    Réservations de ressources
                </h3>
            </div>
            <div class="col-auto">
                <button class="btn-add-resource" data-bs-toggle="modal" data-bs-target="#bookResourceModal">
                    <i class="bi bi-plus-circle"></i> Nouvelle réservation
                </button>
            </div>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <p>Aucune réservation. Cliquez sur "Nouvelle réservation" pour en créer une.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover" style="border-collapse: collapse;">
                    <thead style="background: var(--bg-light); border-bottom: 2px solid #e9ecef;">
                        <tr>
                            <th style="color: var(--text-dark); font-weight: 700; padding: 1rem;">Ressource</th>
                            <th style="color: var(--text-dark); font-weight: 700; padding: 1rem;">Réservé par</th>
                            <th style="color: var(--text-dark); font-weight: 700; padding: 1rem;">Début</th>
                            <th style="color: var(--text-dark); font-weight: 700; padding: 1rem;">Fin</th>
                            <th style="color: var(--text-dark); font-weight: 700; padding: 1rem;">Statut</th>
                            <th style="color: var(--text-dark); font-weight: 700; padding: 1rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr style="border-bottom: 1px solid #e9ecef;">
                                <td style="padding: 1rem;"><strong><?php echo htmlspecialchars($booking['resource_nom']); ?></strong></td>
                                <td style="padding: 1rem;"><?php echo htmlspecialchars($booking['user_nom']); ?></td>
                                <td style="padding: 1rem;"><?php echo date('d/m/Y H:i', strtotime($booking['date_debut'])); ?></td>
                                <td style="padding: 1rem;"><?php echo date('d/m/Y H:i', strtotime($booking['date_fin'])); ?></td>
                                <td style="padding: 1rem;">
                                    <span class="badge" style="padding: 0.5rem 0.75rem; font-weight: 600; <?php 
                                        echo $booking['statut'] === 'Confirmée' ? 'background: #d4edda; color: #155724;' : 
                                             ($booking['statut'] === 'En attente' ? 'background: #fff3cd; color: #856404;' : 'background: #f8d7da; color: #721c24;'); 
                                    ?>">
                                        <?php echo htmlspecialchars($booking['statut']); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php if ($booking['statut'] !== 'Annulée'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Annuler cette réservation?');">
                                            <input type="hidden" name="action" value="cancel_booking">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <button type="submit" class="btn-resource-action delete" style="width: auto; padding: 0.5rem 0.75rem;">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Statistics Tab -->
    <div class="tab-pane fade" id="stats" role="tabpanel">
        <h3 class="section-title">
            <i class="bi bi-bar-chart" style="color: var(--primary-orange);"></i>
            Statistiques des ressources
        </h3>
        
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card" style="border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); text-align: center;">
                    <div class="card-body" style="padding: 2rem 1.5rem;">
                        <div style="width: 50px; height: 50px; background: var(--primary-orange); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 1.5rem;">
                            <i class="bi bi-box"></i>
                        </div>
                        <h6 style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.5rem;">Total Ressources</h6>
                        <h2 style="color: var(--dark-green); font-weight: 900; margin: 0;"><?php echo count($resources); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card" style="border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); text-align: center;">
                    <div class="card-body" style="padding: 2rem 1.5rem;">
                        <div style="width: 50px; height: 50px; background: #0a6b51; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 1.5rem;">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <h6 style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.5rem;">Réservations</h6>
                        <h2 style="color: var(--dark-green); font-weight: 900; margin: 0;"><?php echo count($bookings); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card" style="border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); text-align: center;">
                    <div class="card-body" style="padding: 2rem 1.5rem;">
                        <div style="width: 50px; height: 50px; background: #28a745; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 1.5rem;">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <h6 style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.5rem;">Disponibles</h6>
                        <h2 style="color: var(--dark-green); font-weight: 900; margin: 0;">
                            <?php 
                            $available = count(array_filter($resources, fn($r) => $r['statut'] === 'Disponible'));
                            echo $available;
                            ?>
                        </h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card" style="border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); text-align: center;">
                    <div class="card-body" style="padding: 2rem 1.5rem;">
                        <div style="width: 50px; height: 50px; background: var(--yellow); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: var(--dark-green); font-size: 1.5rem;">
                            <i class="bi bi-percent"></i>
                        </div>
                        <h6 style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.5rem;">Taux d'utilisation</h6>
                        <h2 style="color: var(--dark-green); font-weight: 900; margin: 0;">
                            <?php 
                            $usage = count($resources) > 0 ? round((count($bookings) / count($resources)) * 100) : 0;
                            echo $usage . '%';
                            ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card" style="border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <div class="card-header" style="background: var(--bg-light); border: none; border-bottom: 2px solid #e9ecef; padding: 1.5rem;">
                        <h5 style="margin: 0; color: var(--dark-green); font-weight: 700;">Ressources les plus réservées</h5>
                    </div>
                    <div class="card-body" style="padding: 1.5rem;">
                        <?php 
                        $resourceBookingCounts = [];
                        foreach ($bookings as $booking) {
                            $resourceId = $booking['resource_id'];
                            if (!isset($resourceBookingCounts[$resourceId])) {
                                $resourceBookingCounts[$resourceId] = ['name' => $booking['resource_nom'], 'count' => 0];
                            }
                            $resourceBookingCounts[$resourceId]['count']++;
                        }
                        arsort($resourceBookingCounts);
                        ?>
                        <?php if (empty($resourceBookingCounts)): ?>
                            <p class="text-muted">Aucune réservation</p>
                        <?php else: ?>
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <?php foreach (array_slice($resourceBookingCounts, 0, 5) as $resourceId => $data): ?>
                                    <li style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #e9ecef;">
                                        <span style="color: var(--text-dark); font-weight: 500;"><?php echo htmlspecialchars($data['name']); ?></span>
                                        <span style="background: var(--primary-orange); color: white; padding: 0.4rem 0.8rem; border-radius: 20px; font-weight: 600; font-size: 0.85rem;"><?php echo $data['count']; ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card" style="border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <div class="card-header" style="background: var(--bg-light); border: none; border-bottom: 2px solid #e9ecef; padding: 1.5rem;">
                        <h5 style="margin: 0; color: var(--dark-green); font-weight: 700;">Statut des ressources</h5>
                    </div>
                    <div class="card-body" style="padding: 1.5rem;">
                        <?php 
                        $statusCounts = [
                            'Disponible' => 0,
                            'Indisponible' => 0,
                            'En maintenance' => 0
                        ];
                        foreach ($resources as $resource) {
                            $statusCounts[$resource['statut']]++;
                        }
                        ?>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <li style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #e9ecef;">
                                <span style="color: var(--text-dark);"><i class="bi bi-check-circle" style="color: #28a745; margin-right: 0.5rem;"></i> Disponible</span>
                                <span style="background: #d4edda; color: #155724; padding: 0.4rem 0.8rem; border-radius: 20px; font-weight: 600; font-size: 0.85rem;"><?php echo $statusCounts['Disponible']; ?></span>
                            </li>
                            <li style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #e9ecef;">
                                <span style="color: var(--text-dark);"><i class="bi bi-x-circle" style="color: #dc3545; margin-right: 0.5rem;"></i> Indisponible</span>
                                <span style="background: #f8d7da; color: #721c24; padding: 0.4rem 0.8rem; border-radius: 20px; font-weight: 600; font-size: 0.85rem;"><?php echo $statusCounts['Indisponible']; ?></span>
                            </li>
                            <li style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0;">
                                <span style="color: var(--text-dark);"><i class="bi bi-exclamation-circle" style="color: #ffc107; margin-right: 0.5rem;"></i> En maintenance</span>
                                <span style="background: #fff3cd; color: #856404; padding: 0.4rem 0.8rem; border-radius: 20px; font-weight: 600; font-size: 0.85rem;"><?php echo $statusCounts['En maintenance']; ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Resource Modal -->
<div class="modal fade" id="addResourceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border: none; border-radius: 12px;">
            <div class="modal-header" style="border-bottom: 2px solid #e9ecef; padding: 1.5rem;">
                <h5 class="modal-title" style="color: var(--dark-green); font-weight: 700;">Ajouter une ressource</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="add_resource.php" enctype="multipart/form-data">
                <div class="modal-body" style="padding: 2rem;">
                    <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                    
                    <div class="mb-3">
                        <label for="nom" class="form-label" style="color: var(--text-dark); font-weight: 600;">Nom de la ressource *</label>
                        <input type="text" class="form-control" id="nom" name="nom" required style="border: 2px solid #e9ecef; border-radius: 8px; padding: 0.75rem;">
                    </div>

                    <div class="mb-3">
                        <label for="type" class="form-label" style="color: var(--text-dark); font-weight: 600;">Type *</label>
                        <select class="form-select" id="type" name="type" required style="border: 2px solid #e9ecef; border-radius: 8px; padding: 0.75rem;">
                            <option value="">-- Sélectionner --</option>
                            <option value="Salle">Salle</option>
                            <option value="Matériel">Matériel</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="quantite_totale" class="form-label" style="color: var(--text-dark); font-weight: 600;">Quantité totale *</label>
                        <input type="number" class="form-control" id="quantite_totale" name="quantite_totale" min="1" value="1" required style="border: 2px solid #e9ecef; border-radius: 8px; padding: 0.75rem;">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label" style="color: var(--text-dark); font-weight: 600;">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" style="border: 2px solid #e9ecef; border-radius: 8px; padding: 0.75rem;"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_debut" class="form-label" style="color: var(--text-dark); font-weight: 600;">Disponible à partir du</label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut" style="border: 2px solid #e9ecef; border-radius: 8px; padding: 0.75rem;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_fin" class="form-label" style="color: var(--text-dark); font-weight: 600;">Disponible jusqu'au</label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin" style="border: 2px solid #e9ecef; border-radius: 8px; padding: 0.75rem;">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="image" class="form-label" style="color: var(--text-dark); font-weight: 600;">Image (optionnel)</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*" style="border: 2px solid #e9ecef; border-radius: 8px; padding: 0.75rem;">
                        <small class="text-muted">Max 2MB. Formats: JPG, PNG, GIF</small>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 2px solid #e9ecef; padding: 1.5rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background: #e9ecef; color: var(--text-dark); border: none; border-radius: 8px; padding: 0.6rem 1.5rem; font-weight: 600;">Annuler</button>
                    <button type="submit" class="btn-add-resource" style="border: none;">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Resource Modal -->
<div class="modal fade" id="editResourceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border: none; border-radius: 12px;">
            <div class="modal-header" style="border-bottom: 2px solid #e9ecef; padding: 1.5rem;">
                <h5 class="modal-title" style="color: var(--dark-green); font-weight: 700;">Modifier la ressource</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="edit_resource.php" enctype="multipart/form-data">
                <div class="modal-body" id="editResourceContent" style="padding: 2rem;">
                    <p class="text-muted">Chargement...</p>
                </div>
                <div class="modal-footer" style="border-top: 2px solid #e9ecef; padding: 1.5rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background: #e9ecef; color: var(--text-dark); border: none; border-radius: 8px; padding: 0.6rem 1.5rem; font-weight: 600;">Annuler</button>
                    <button type="submit" class="btn-add-resource" style="border: none;">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Book Resource Modal -->
<div class="modal fade" id="bookResourceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border: none; border-radius: 12px;">
            <div class="modal-header" style="border-bottom: 2px solid #e9ecef; padding: 1.5rem;">
                <h5 class="modal-title" style="color: var(--dark-green); font-weight: 700;">Nouvelle réservation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="book_resource.php">
                <div class="modal-body" style="padding: 2rem;">
                    <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                    
                    <div class="mb-3">
                        <label for="resource_id" class="form-label" style="color: var(--text-dark); font-weight: 600;">Ressource *</label>
                        <select class="form-select" id="resource_id" name="resource_id" required style="border: 2px solid #e9ecef; border-radius: 8px; padding: 0.75rem;">
                            <option value="">-- Sélectionner une ressource --</option>
                            <?php foreach ($resources as $resource): ?>
                                <option value="<?php echo $resource['id']; ?>">
                                    <?php echo htmlspecialchars($resource['nom']); ?> (<?php echo $resource['type']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_debut_booking" class="form-label" style="color: var(--text-dark); font-weight: 600;">Date & Heure de début *</label>
                                <input type="datetime-local" class="form-control" id="date_debut_booking" name="date_debut" required style="border: 2px solid #e9ecef; border-radius: 8px; padding: 0.75rem;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_fin_booking" class="form-label" style="color: var(--text-dark); font-weight: 600;">Date & Heure de fin *</label>
                                <input type="datetime-local" class="form-control" id="date_fin_booking" name="date_fin" required style="border: 2px solid #e9ecef; border-radius: 8px; padding: 0.75rem;">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label" style="color: var(--text-dark); font-weight: 600;">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" style="border: 2px solid #e9ecef; border-radius: 8px; padding: 0.75rem;"></textarea>
                    </div>

                    <div id="conflictWarning" class="alert alert-warning d-none" style="border-radius: 10px; border: none; background: #fff3cd; color: #856404; border-left: 4px solid #ffc107;">
                        <i class="bi bi-exclamation-triangle"></i> <strong>Conflit détecté!</strong> Cette ressource est déjà réservée pour cette période.
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 2px solid #e9ecef; padding: 1.5rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background: #e9ecef; color: var(--text-dark); border: none; border-radius: 8px; padding: 0.6rem 1.5rem; font-weight: 600;">Annuler</button>
                    <button type="submit" class="btn-add-resource" style="border: none;">Réserver</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function loadResourceEdit(resourceId) {
    fetch('get_resource.php?id=' + resourceId + '&event_id=<?php echo $eventId; ?>')
        .then(response => response.text())
        .then(html => {
            document.getElementById('editResourceContent').innerHTML = html;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('editResourceContent').innerHTML = '<p class="text-danger">Erreur lors du chargement.</p>';
        });
}

// Check for booking conflicts
document.getElementById('date_debut_booking')?.addEventListener('change', checkConflicts);
document.getElementById('date_fin_booking')?.addEventListener('change', checkConflicts);
document.getElementById('resource_id')?.addEventListener('change', checkConflicts);

function checkConflicts() {
    const resourceId = document.getElementById('resource_id').value;
    const dateDebut = document.getElementById('date_debut_booking').value;
    const dateFin = document.getElementById('date_fin_booking').value;
    const warningDiv = document.getElementById('conflictWarning');

    if (!resourceId || !dateDebut || !dateFin) return;

    fetch('check_booking_conflict.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'resource_id=' + resourceId + '&date_debut=' + dateDebut + '&date_fin=' + dateFin
    })
    .then(response => response.json())
    .then(data => {
        if (data.conflict) {
            warningDiv.classList.remove('d-none');
        } else {
            warningDiv.classList.add('d-none');
        }
    });
}
</script>

<?php require 'footer.php'; ?>
