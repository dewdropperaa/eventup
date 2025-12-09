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

require 'header.php';
?>

<div class="row mb-4">
    <div class="col">
        <h1 class="mb-0">
            <i class="bi bi-tools"></i> Gestion des Ressources
        </h1>
        <p class="text-muted mt-2"><?php echo htmlspecialchars($event['titre']); ?></p>
    </div>
    <div class="col-auto">
        <a href="event_details.php?id=<?php echo $eventId; ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Event
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-4" id="resourceTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="resources-tab" data-bs-toggle="tab" data-bs-target="#resources" type="button" role="tab">
            <i class="bi bi-box"></i> Ressources
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="bookings-tab" data-bs-toggle="tab" data-bs-target="#bookings" type="button" role="tab">
            <i class="bi bi-calendar-check"></i> Réservations
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="calendar-tab" data-bs-toggle="tab" data-bs-target="#calendar" type="button" role="tab">
            <i class="bi bi-calendar"></i> Calendrier
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
        <div class="row mb-4">
            <div class="col">
                <h3>Ressources disponibles</h3>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addResourceModal">
                    <i class="bi bi-plus-circle"></i> Ajouter une ressource
                </button>
            </div>
        </div>

        <?php if (empty($resources)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Aucune ressource créée. Cliquez sur "Ajouter une ressource" pour commencer.
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php foreach ($resources as $resource): ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm">
                            <?php if ($resource['image_path']): ?>
                                <img src="<?php echo htmlspecialchars($resource['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($resource['nom']); ?>" style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                    <i class="bi bi-box" style="font-size: 3rem; color: #ccc;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($resource['nom']); ?></h5>
                                <p class="card-text text-muted small">
                                    <strong>Type:</strong> <?php echo htmlspecialchars($resource['type']); ?><br>
                                    <strong>Quantité:</strong> <?php echo (int) $resource['quantite_totale']; ?>
                                </p>
                                <?php if ($resource['description']): ?>
                                    <p class="card-text small"><?php echo htmlspecialchars(substr($resource['description'], 0, 100)); ?>...</p>
                                <?php endif; ?>
                                <div class="mb-3">
                                    <span class="badge bg-<?php 
                                        echo $resource['statut'] === 'Disponible' ? 'success' : 
                                             ($resource['statut'] === 'En maintenance' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo htmlspecialchars($resource['statut']); ?>
                                    </span>
                                </div>
                                <?php if ($resource['date_disponibilite_debut']): ?>
                                    <p class="small text-muted mb-3">
                                        <i class="bi bi-calendar"></i> 
                                        <?php echo date('d/m/Y', strtotime($resource['date_disponibilite_debut'])); ?> - 
                                        <?php echo date('d/m/Y', strtotime($resource['date_disponibilite_fin'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-white border-top">
                                <div class="btn-group w-100" role="group">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editResourceModal" 
                                            onclick="loadResourceEdit(<?php echo $resource['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" style="display: inline; width: 50%;" onsubmit="return confirm('Delete this resource?');">
                                        <input type="hidden" name="action" value="delete_resource">
                                        <input type="hidden" name="resource_id" value="<?php echo $resource['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bookings Tab -->
    <div class="tab-pane fade" id="bookings" role="tabpanel">
        <div class="row mb-4">
            <div class="col">
                <h3>Réservations de ressources</h3>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookResourceModal">
                    <i class="bi bi-plus-circle"></i> Nouvelle réservation
                </button>
            </div>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Aucune réservation. Cliquez sur "Nouvelle réservation" pour en créer une.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Ressource</th>
                            <th>Réservé par</th>
                            <th>Début</th>
                            <th>Fin</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($booking['resource_nom']); ?></strong></td>
                                <td><?php echo htmlspecialchars($booking['user_nom']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($booking['date_debut'])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($booking['date_fin'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $booking['statut'] === 'Confirmée' ? 'success' : 
                                             ($booking['statut'] === 'En attente' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo htmlspecialchars($booking['statut']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($booking['statut'] !== 'Annulée'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Cancel this booking?');">
                                            <input type="hidden" name="action" value="cancel_booking">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-x-circle"></i> Cancel
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

    <!-- Calendar Tab -->
    <div class="tab-pane fade" id="calendar" role="tabpanel">
        <h3 class="mb-4">Calendrier des disponibilités</h3>
        <div id="calendar" style="height: 600px; background: #f8f9fa; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
            <p class="text-muted">Calendar will load here (FullCalendar integration)</p>
        </div>
    </div>

    <!-- Statistics Tab -->
    <div class="tab-pane fade" id="stats" role="tabpanel">
        <h3 class="mb-4">Statistiques des ressources</h3>
        <div class="row">
            <div class="col-md-3">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Total Ressources</h6>
                        <h2 class="text-primary"><?php echo count($resources); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Réservations</h6>
                        <h2 class="text-info"><?php echo count($bookings); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Disponibles</h6>
                        <h2 class="text-success">
                            <?php 
                            $available = count(array_filter($resources, fn($r) => $r['statut'] === 'Disponible'));
                            echo $available;
                            ?>
                        </h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Taux d'utilisation</h6>
                        <h2 class="text-warning">
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
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Ressources les plus réservées</h5>
                    </div>
                    <div class="card-body">
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
                            <ul class="list-group list-group-flush">
                                <?php foreach (array_slice($resourceBookingCounts, 0, 5) as $resourceId => $data): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo htmlspecialchars($data['name']); ?>
                                        <span class="badge bg-primary rounded-pill"><?php echo $data['count']; ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Statut des ressources</h5>
                    </div>
                    <div class="card-body">
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
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-check-circle text-success"></i> Disponible</span>
                                <span class="badge bg-success rounded-pill"><?php echo $statusCounts['Disponible']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-x-circle text-danger"></i> Indisponible</span>
                                <span class="badge bg-danger rounded-pill"><?php echo $statusCounts['Indisponible']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-exclamation-circle text-warning"></i> En maintenance</span>
                                <span class="badge bg-warning rounded-pill"><?php echo $statusCounts['En maintenance']; ?></span>
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
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une ressource</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="add_resource.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                    
                    <div class="mb-3">
                        <label for="nom" class="form-label">Nom de la ressource *</label>
                        <input type="text" class="form-control" id="nom" name="nom" required>
                    </div>

                    <div class="mb-3">
                        <label for="type" class="form-label">Type *</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="Salle">Salle</option>
                            <option value="Matériel">Matériel</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="quantite_totale" class="form-label">Quantité totale *</label>
                        <input type="number" class="form-control" id="quantite_totale" name="quantite_totale" min="1" value="1" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_debut" class="form-label">Disponible à partir du</label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_fin" class="form-label">Disponible jusqu'au</label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="image" class="form-label">Image (optionnel)</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        <small class="text-muted">Max 2MB. Formats: JPG, PNG, GIF</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Resource Modal -->
<div class="modal fade" id="editResourceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier la ressource</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="edit_resource.php" enctype="multipart/form-data">
                <div class="modal-body" id="editResourceContent">
                    <p class="text-muted">Chargement...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Book Resource Modal -->
<div class="modal fade" id="bookResourceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouvelle réservation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="book_resource.php">
                <div class="modal-body">
                    <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
                    
                    <div class="mb-3">
                        <label for="resource_id" class="form-label">Ressource *</label>
                        <select class="form-select" id="resource_id" name="resource_id" required>
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
                                <label for="date_debut_booking" class="form-label">Date & Heure de début *</label>
                                <input type="datetime-local" class="form-control" id="date_debut_booking" name="date_debut" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_fin_booking" class="form-label">Date & Heure de fin *</label>
                                <input type="datetime-local" class="form-control" id="date_fin_booking" name="date_fin" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>

                    <div id="conflictWarning" class="alert alert-warning d-none">
                        <i class="bi bi-exclamation-triangle"></i> <strong>Conflit détecté!</strong> Cette ressource est déjà réservée pour cette période.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Réserver</button>
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
