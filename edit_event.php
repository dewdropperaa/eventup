<?php
/**
 * Edit Event Page
 * Allows event admins/organizers to edit event details
 * Only admins can delete the event
 */

session_start();
require_once 'role_check.php';

// Check if user is logged in
requireLogin();

require_once 'database.php';

$pdo = getDatabaseConnection();
$error = '';
$success = '';
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$event = null;
$user_role = null;

// Verify event exists and user has permission
if ($event_id <= 0) {
    $error = 'ID d\'événement invalide.';
} else {
    try {
        // Get event and user's role
        $stmt = $pdo->prepare("
            SELECT e.*, er.role
            FROM events e
            LEFT JOIN event_roles er ON e.id = er.event_id AND er.user_id = :user_id
            WHERE e.id = :event_id
        ");
        
        $stmt->execute([
            ':event_id' => $event_id,
            ':user_id' => $_SESSION['user_id']
        ]);
        
        $event = $stmt->fetch();
        
        if (!$event) {
            $error = 'Événement non trouvé.';
        } elseif (!$event['role'] || $event['role'] !== 'admin') {
            $error = 'Vous n\'avez pas la permission de modifier cet événement.';
        } else {
            $user_role = $event['role'];
        }
    } catch (PDOException $e) {
        error_log("Event verification error: " . $e->getMessage());
        $error = 'Une erreur est survenue lors de la vérification de l\'événement.';
    }
}

// Handle delete request (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && !$error) {
    if ($user_role !== 'admin') {
        $error = 'Seul l\'administrateur de l\'événement peut supprimer l\'événement.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Delete event (cascading deletes will handle related records)
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = :event_id");
            $stmt->execute([':event_id' => $event_id]);
            
            $pdo->commit();
            
            $_SESSION['success_message'] = 'Événement supprimé avec succès!';
            header("Location: dashboard.php");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Event deletion error: " . $e->getMessage());
            $error = 'Une erreur est survenue lors de la suppression de l\'événement.';
        }
    }
}

// Handle edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action']) && !$error) {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $lieu = trim($_POST['lieu'] ?? '');
    $nb_max_participants = trim($_POST['nb_max_participants'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    
    // Validation
    if (empty($titre)) {
        $error = 'Le titre est requis.';
    } elseif (empty($description)) {
        $error = 'La description est requise.';
    } elseif (empty($date)) {
        $error = 'La date est requise.';
    } elseif (empty($lieu)) {
        $error = 'Le lieu est requis.';
    } elseif (empty($nb_max_participants)) {
        $error = 'Le nombre maximum de participants est requis.';
    } elseif (!is_numeric($nb_max_participants) || $nb_max_participants <= 0) {
        $error = 'Le nombre maximum de participants doit être un nombre positif.';
    } else {
        try {
            // Update event
            $stmt = $pdo->prepare("
                UPDATE events 
                SET titre = :titre, 
                    description = :description, 
                    date = :date, 
                    lieu = :lieu, 
                    nb_max_participants = :nb_max_participants,
                    category = :category
                WHERE id = :event_id
            ");
            
            $stmt->execute([
                ':titre' => $titre,
                ':description' => $description,
                ':date' => $date,
                ':lieu' => $lieu,
                ':nb_max_participants' => (int)$nb_max_participants,
                ':category' => $category,
                ':event_id' => $event_id
            ]);
            
            $success = 'Événement mis à jour avec succès!';
            
            // Refresh event data
            $stmt = $pdo->prepare("
                SELECT e.*, er.role
                FROM events e
                LEFT JOIN event_roles er ON e.id = er.event_id AND er.user_id = :user_id
                WHERE e.id = :event_id
            ");
            
            $stmt->execute([
                ':event_id' => $event_id,
                ':user_id' => $_SESSION['user_id']
            ]);
            
            $event = $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Event update error: " . $e->getMessage());
            $error = 'Une erreur est survenue lors de la mise à jour de l\'événement.';
        }
    }
}

include 'header.php';
?>

<?php if ($error && !$event): ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i>
                <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">Retour au tableau de bord</a>
        </div>
    </div>
<?php elseif ($event): ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Modifier l'événement</h3>
                    <small>Rôle: <span class="badge bg-light text-dark"><?php echo $user_role === 'admin' ? 'Administrateur' : 'Organisateur'; ?></span></small>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-circle"></i>
                            <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle"></i>
                            <strong>Success!</strong> <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="edit_event.php?id=<?php echo $event_id; ?>" novalidate>
                        <div class="mb-3">
                            <label for="titre" class="form-label">Titre de l'événement <span class="text-danger">*</span></label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="titre" 
                                name="titre" 
                                placeholder="Ex: Conférence Tech 2025"
                                value="<?php echo htmlspecialchars($event['titre']); ?>"
                                required
                            >
                            <div class="invalid-feedback">Le titre est requis.</div>
                            <small class="form-text text-muted">Maximum 150 caractères</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea 
                                class="form-control" 
                                id="description" 
                                name="description" 
                                rows="4"
                                placeholder="Décrivez votre événement..."
                                required
                            ><?php echo htmlspecialchars($event['description']); ?></textarea>
                            <div class="invalid-feedback">La description est requise.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="date" class="form-label">Date et heure <span class="text-danger">*</span></label>
                            <input 
                                type="datetime-local" 
                                class="form-control" 
                                id="date" 
                                name="date"
                                value="<?php echo htmlspecialchars(str_replace(' ', 'T', $event['date'])); ?>"
                                required
                            >
                            <div class="invalid-feedback">La date et l'heure sont requises.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="lieu" class="form-label">Lieu <span class="text-danger">*</span></label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="lieu" 
                                name="lieu" 
                                placeholder="Ex: Paris Convention Center"
                                value="<?php echo htmlspecialchars($event['lieu']); ?>"
                                required
                            >
                            <div class="invalid-feedback">Le lieu est requis.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category" class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <select 
                                class="form-select" 
                                id="category" 
                                name="category"
                                required
                            >
                                <option value="General" <?php echo ($event['category'] ?? 'General') === 'General' ? 'selected' : ''; ?>>General</option>
                                <option value="Technology" <?php echo ($event['category'] ?? '') === 'Technology' ? 'selected' : ''; ?>>Technology</option>
                                <option value="Business" <?php echo ($event['category'] ?? '') === 'Business' ? 'selected' : ''; ?>>Business</option>
                                <option value="Education" <?php echo ($event['category'] ?? '') === 'Education' ? 'selected' : ''; ?>>Education</option>
                                <option value="Sports" <?php echo ($event['category'] ?? '') === 'Sports' ? 'selected' : ''; ?>>Sports</option>
                                <option value="Arts & Culture" <?php echo ($event['category'] ?? '') === 'Arts & Culture' ? 'selected' : ''; ?>>Arts & Culture</option>
                                <option value="Health & Wellness" <?php echo ($event['category'] ?? '') === 'Health & Wellness' ? 'selected' : ''; ?>>Health & Wellness</option>
                                <option value="Networking" <?php echo ($event['category'] ?? '') === 'Networking' ? 'selected' : ''; ?>>Networking</option>
                                <option value="Other" <?php echo ($event['category'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                            <div class="invalid-feedback">La catégorie est requise.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="nb_max_participants" class="form-label">Nombre maximum de participants <span class="text-danger">*</span></label>
                            <input 
                                type="number" 
                                class="form-control" 
                                id="nb_max_participants" 
                                name="nb_max_participants" 
                                placeholder="Ex: 100"
                                min="1"
                                value="<?php echo htmlspecialchars($event['nb_max_participants']); ?>"
                                required
                            >
                            <div class="invalid-feedback">Le nombre de participants doit être un nombre positif.</div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                Enregistrer les modifications
                            </button>
                            <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Delete Event Section (Admin Only) -->
            <?php if ($user_role === 'admin'): ?>
                <div class="card shadow-sm mt-4 border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">Zone de danger</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            <strong>Supprimer cet événement</strong><br>
                            Cette action est irréversible. Tous les rôles d'événement, les inscriptions et les invitations associées seront supprimés.
                        </p>
                        
                        <button 
                            type="button" 
                            class="btn btn-danger" 
                            data-bs-toggle="modal" 
                            data-bs-target="#deleteConfirmModal"
                        >
                            Supprimer l'événement
                        </button>
                    </div>
                </div>
                
                <!-- Delete Confirmation Modal -->
                <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title" id="deleteConfirmLabel">Confirmer la suppression</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Êtes-vous sûr de vouloir supprimer l'événement <strong><?php echo htmlspecialchars($event['titre']); ?></strong>?</p>
                                <p class="text-danger"><strong>Cette action est irréversible!</strong></p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <form method="POST" action="edit_event.php?id=<?php echo $event_id; ?>" style="display: inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-danger">Supprimer définitivement</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
