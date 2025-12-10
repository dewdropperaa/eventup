<?php
session_start();

require 'database.php';
require_once 'role_check.php';

// Check if user is logged in
requireLogin();

// Get resource ID from GET
$resourceId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

if ($resourceId <= 0 || $eventId <= 0) {
    echo '<p class="text-danger">Invalid parameters.</p>';
    exit;
}

// Check if user is admin or organizer for this event
if (!isEventOrganizer($_SESSION['user_id'], $eventId)) {
    echo '<p class="text-danger">Access denied.</p>';
    exit;
}

$pdo = getDatabaseConnection();

try {
    $stmt = $pdo->prepare('
        SELECT id, nom, type, quantite_totale, description, 
               date_disponibilite_debut, date_disponibilite_fin, 
               image_path, statut
        FROM event_resources 
        WHERE id = ? AND event_id = ?
    ');
    $stmt->execute([$resourceId, $eventId]);
    $resource = $stmt->fetch();

    if (!$resource) {
        echo '<p class="text-danger">Resource not found.</p>';
        exit;
    }
} catch (Exception $e) {
    error_log('Error fetching resource: ' . $e->getMessage());
    echo '<p class="text-danger">Error loading resource.</p>';
    exit;
}
?>

<input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
<input type="hidden" name="resource_id" value="<?php echo $resourceId; ?>">

<div class="mb-3">
    <label for="edit_nom" class="form-label">Nom de la ressource *</label>
    <input type="text" class="form-control" id="edit_nom" name="nom" value="<?php echo htmlspecialchars($resource['nom']); ?>" required>
</div>

<div class="mb-3">
    <label for="edit_type" class="form-label">Type *</label>
    <select class="form-select" id="edit_type" name="type" required>
        <option value="Salle" <?php echo $resource['type'] === 'Salle' ? 'selected' : ''; ?>>Salle</option>
        <option value="Matériel" <?php echo $resource['type'] === 'Matériel' ? 'selected' : ''; ?>>Matériel</option>
        <option value="Autre" <?php echo $resource['type'] === 'Autre' ? 'selected' : ''; ?>>Autre</option>
    </select>
</div>

<div class="mb-3">
    <label for="edit_quantite_totale" class="form-label">Quantité totale *</label>
    <input type="number" class="form-control" id="edit_quantite_totale" name="quantite_totale" min="1" value="<?php echo (int) $resource['quantite_totale']; ?>" required>
</div>

<div class="mb-3">
    <label for="edit_statut" class="form-label">Statut *</label>
    <select class="form-select" id="edit_statut" name="statut" required>
        <option value="Disponible" <?php echo $resource['statut'] === 'Disponible' ? 'selected' : ''; ?>>Disponible</option>
        <option value="Indisponible" <?php echo $resource['statut'] === 'Indisponible' ? 'selected' : ''; ?>>Indisponible</option>
        <option value="En maintenance" <?php echo $resource['statut'] === 'En maintenance' ? 'selected' : ''; ?>>En maintenance</option>
    </select>
</div>

<div class="mb-3">
    <label for="edit_description" class="form-label">Description</label>
    <textarea class="form-control" id="edit_description" name="description" rows="3"><?php echo htmlspecialchars($resource['description'] ?? ''); ?></textarea>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="edit_date_debut" class="form-label">Disponible à partir du</label>
            <input type="date" class="form-control" id="edit_date_debut" name="date_debut" value="<?php echo $resource['date_disponibilite_debut'] ?? ''; ?>">
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label for="edit_date_fin" class="form-label">Disponible jusqu'au</label>
            <input type="date" class="form-control" id="edit_date_fin" name="date_fin" value="<?php echo $resource['date_disponibilite_fin'] ?? ''; ?>">
        </div>
    </div>
</div>

<div class="mb-3">
    <label for="edit_image" class="form-label">Image (optionnel)</label>
    <?php if ($resource['image_path']): ?>
        <div class="mb-2">
            <img src="<?php echo htmlspecialchars($resource['image_path']); ?>" alt="Resource image" style="max-width: 150px; max-height: 150px;">
        </div>
    <?php endif; ?>
    <input type="file" class="form-control" id="edit_image" name="image" accept="image/*">
    <small class="text-muted">Max 2MB. Formats: JPG, PNG, GIF</small>
</div>
