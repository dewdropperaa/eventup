<?php
/**
 * Create Event Page
 * Allows logged-in users to create new events
 */

session_start();
require_once 'role_check.php';

// Check if user is logged in
requireLogin();

$pdo = getDatabaseConnection();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
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
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert event
            $stmt = $pdo->prepare("
                INSERT INTO events (titre, description, date, lieu, nb_max_participants, category)
                VALUES (:titre, :description, :date, :lieu, :nb_max_participants, :category)
            ");
            
            $stmt->execute([
                ':titre' => $titre,
                ':description' => $description,
                ':date' => $date,
                ':lieu' => $lieu,
                ':nb_max_participants' => (int)$nb_max_participants,
                ':category' => $category
            ]);
            
            // Get the ID of the newly created event
            $event_id = $pdo->lastInsertId();
            
            // Add creator as admin in event_roles
            $stmt = $pdo->prepare("
                INSERT INTO event_roles (user_id, event_id, role)
                VALUES (:user_id, :event_id, 'admin')
            ");
            
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':event_id' => $event_id
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            $success = 'Événement créé avec succès!';
            
            // Redirect after 2 seconds
            header("Refresh: 2; url=event_details.php?id=" . $event_id);
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            error_log("Event creation error: " . $e->getMessage());
            $error = 'Une erreur est survenue lors de la création de l\'événement. Veuillez réessayer.';
        }
    }
}

include 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">Créer un nouvel événement</h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="create_event.php" id="create-event-form" novalidate>
                    <div class="mb-3">
                        <label for="titre" class="form-label">Titre de l'événement <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="titre" 
                            name="titre" 
                            placeholder="Ex: Conférence Tech 2025"
                            value="<?php echo htmlspecialchars($_POST['titre'] ?? ''); ?>"
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
                        ><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <div class="invalid-feedback">La description est requise.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date" class="form-label">Date et heure <span class="text-danger">*</span></label>
                        <input 
                            type="datetime-local" 
                            class="form-control" 
                            id="date" 
                            name="date"
                            value="<?php echo htmlspecialchars($_POST['date'] ?? ''); ?>"
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
                            value="<?php echo htmlspecialchars($_POST['lieu'] ?? ''); ?>"
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
                            <option value="General" <?php echo ($_POST['category'] ?? 'General') === 'General' ? 'selected' : ''; ?>>General</option>
                            <option value="Technology" <?php echo ($_POST['category'] ?? '') === 'Technology' ? 'selected' : ''; ?>>Technology</option>
                            <option value="Business" <?php echo ($_POST['category'] ?? '') === 'Business' ? 'selected' : ''; ?>>Business</option>
                            <option value="Education" <?php echo ($_POST['category'] ?? '') === 'Education' ? 'selected' : ''; ?>>Education</option>
                            <option value="Sports" <?php echo ($_POST['category'] ?? '') === 'Sports' ? 'selected' : ''; ?>>Sports</option>
                            <option value="Arts & Culture" <?php echo ($_POST['category'] ?? '') === 'Arts & Culture' ? 'selected' : ''; ?>>Arts & Culture</option>
                            <option value="Health & Wellness" <?php echo ($_POST['category'] ?? '') === 'Health & Wellness' ? 'selected' : ''; ?>>Health & Wellness</option>
                            <option value="Networking" <?php echo ($_POST['category'] ?? '') === 'Networking' ? 'selected' : ''; ?>>Networking</option>
                            <option value="Other" <?php echo ($_POST['category'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
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
                            value="<?php echo htmlspecialchars($_POST['nb_max_participants'] ?? ''); ?>"
                            required
                        >
                        <div class="invalid-feedback">Le nombre de participants doit être un nombre positif.</div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Créer l'événement
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
