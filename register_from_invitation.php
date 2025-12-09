<?php
/**
 * Register from Invitation Page
 * Allows invited users to register and join an event as organizer
 */

session_start();
require_once 'database.php';

// If user is already logged in, redirect to event details
if (isset($_SESSION['user_id'])) {
    $token = isset($_GET['token']) ? trim($_GET['token']) : '';
    if ($token) {
        // Try to process the invitation
        $pdo = getDatabaseConnection();
        
        try {
            $stmt = $pdo->prepare("
                SELECT event_id, email FROM event_invitations 
                WHERE token = :token AND token_expiry > NOW() AND used = FALSE
            ");
            $stmt->execute([':token' => $token]);
            $invitation = $stmt->fetch();
            
            if ($invitation) {
                // Check if logged-in user email matches invitation email
                $stmt = $pdo->prepare("SELECT email FROM users WHERE id = :user_id");
                $stmt->execute([':user_id' => $_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                if ($user && $user['email'] === $invitation['email']) {
                    // Add user as organizer
                    $stmt = $pdo->prepare("
                        INSERT INTO event_roles (user_id, event_id, role)
                        VALUES (:user_id, :event_id, 'organizer')
                    ");
                    $stmt->execute([
                        ':user_id' => $_SESSION['user_id'],
                        ':event_id' => $invitation['event_id']
                    ]);
                    
                    // Also add to event_organizers table for permission management
                    $stmt = $pdo->prepare("
                        INSERT INTO event_organizers (event_id, user_id, role)
                        VALUES (:event_id, :user_id, 'organizer')
                        ON DUPLICATE KEY UPDATE role = 'organizer'
                    ");
                    $stmt->execute([
                        ':event_id' => $invitation['event_id'],
                        ':user_id' => $_SESSION['user_id']
                    ]);
                    
                    // Mark invitation as used
                    $stmt = $pdo->prepare("UPDATE event_invitations SET used = TRUE WHERE token = :token");
                    $stmt->execute([':token' => $token]);
                    
                    $_SESSION['success_message'] = 'Vous avez rejoint l\'événement en tant qu\'organisateur!';
                    header("Location: event_details.php?id=" . $invitation['event_id']);
                    exit();
                } else {
                    $_SESSION['login_error'] = 'Votre email ne correspond pas à l\'invitation.';
                    header("Location: login.php");
                    exit();
                }
            } else {
                $_SESSION['login_error'] = 'Invitation invalide ou expirée.';
                header("Location: login.php");
                exit();
            }
        } catch (PDOException $e) {
            error_log("Invitation processing error: " . $e->getMessage());
            $_SESSION['login_error'] = 'Une erreur est survenue lors du traitement de l\'invitation.';
            header("Location: login.php");
            exit();
        }
    }
    
    header("Location: dashboard.php");
    exit();
}

$pdo = getDatabaseConnection();
$error = '';
$success = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$invitation = null;

// Verify invitation token
if (empty($token)) {
    $error = 'Token d\'invitation invalide.';
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT ei.email, ei.event_id, e.titre 
            FROM event_invitations ei
            JOIN events e ON ei.event_id = e.id
            WHERE ei.token = :token AND ei.token_expiry > NOW() AND ei.used = FALSE
        ");
        $stmt->execute([':token' => $token]);
        $invitation = $stmt->fetch();
        
        if (!$invitation) {
            $error = 'Invitation invalide ou expirée.';
        }
    } catch (PDOException $e) {
        error_log("Invitation verification error: " . $e->getMessage());
        $error = 'Une erreur est survenue lors de la vérification de l\'invitation.';
    }
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invitation) {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    // Validation
    if (empty($nom)) {
        $error = 'Le nom est requis.';
    } elseif (empty($email)) {
        $error = 'L\'email est requis.';
    } elseif ($email !== $invitation['email']) {
        $error = 'L\'email doit correspondre à l\'invitation.';
    } elseif (empty($password)) {
        $error = 'Le mot de passe est requis.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password !== $password_confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            
            if ($stmt->fetch()) {
                $error = 'Cet email est déjà utilisé.';
            } else {
                $pdo->beginTransaction();
                
                // Create new user
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (nom, email, mot_de_passe)
                    VALUES (:nom, :email, :mot_de_passe)
                ");
                $stmt->execute([
                    ':nom' => $nom,
                    ':email' => $email,
                    ':mot_de_passe' => $hashed_password
                ]);
                
                $user_id = $pdo->lastInsertId();
                
                // Add user as organizer to the event
                $stmt = $pdo->prepare("
                    INSERT INTO event_roles (user_id, event_id, role)
                    VALUES (:user_id, :event_id, 'organizer')
                ");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':event_id' => $invitation['event_id']
                ]);
                
                // Also add to event_organizers table for permission management
                $stmt = $pdo->prepare("
                    INSERT INTO event_organizers (event_id, user_id, role)
                    VALUES (:event_id, :user_id, 'organizer')
                    ON DUPLICATE KEY UPDATE role = 'organizer'
                ");
                $stmt->execute([
                    ':event_id' => $invitation['event_id'],
                    ':user_id' => $user_id
                ]);
                
                // Mark invitation as used
                $stmt = $pdo->prepare("UPDATE event_invitations SET used = TRUE WHERE token = :token");
                $stmt->execute([':token' => $token]);
                
                $pdo->commit();
                
                // Log the user in
                $_SESSION['user_id'] = $user_id;
                $_SESSION['success_message'] = 'Inscription réussie! Vous avez rejoint l\'événement en tant qu\'organisateur.';
                
                header("Location: event_details.php?id=" . $invitation['event_id']);
                exit();
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Registration error: " . $e->getMessage());
            $error = 'Une erreur est survenue lors de l\'inscription.';
        }
    }
}

include 'header.php';
?>

<?php if ($error && !$invitation): ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <a href="index.php" class="btn btn-secondary">Retour à l'accueil</a>
        </div>
    </div>
<?php elseif ($invitation): ?>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h3 class="mb-0">Créer un compte</h3>
                    <small>Événement: <?php echo htmlspecialchars($invitation['titre']); ?></small>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <p class="text-muted mb-4">
                        Vous avez été invité à rejoindre l'événement <strong><?php echo htmlspecialchars($invitation['titre']); ?></strong> en tant qu'organisateur. 
                        Créez un compte pour accepter l'invitation.
                    </p>
                    
                    <form method="POST" action="register_from_invitation.php?token=<?php echo htmlspecialchars($token); ?>" novalidate>
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom complet <span class="text-danger">*</span></label>
                            <input 
                                type="text" 
                                class="form-control" 
                                id="nom" 
                                name="nom"
                                value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>"
                                required
                            >
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input 
                                type="email" 
                                class="form-control" 
                                id="email" 
                                name="email"
                                value="<?php echo htmlspecialchars($invitation['email']); ?>"
                                readonly
                            >
                            <small class="form-text text-muted">Cet email a été défini par l'invitation.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                            <input 
                                type="password" 
                                class="form-control" 
                                id="password" 
                                name="password"
                                placeholder="Au moins 6 caractères"
                                required
                            >
                        </div>
                        
                        <div class="mb-3">
                            <label for="password_confirm" class="form-label">Confirmer le mot de passe <span class="text-danger">*</span></label>
                            <input 
                                type="password" 
                                class="form-control" 
                                id="password_confirm" 
                                name="password_confirm"
                                placeholder="Confirmez votre mot de passe"
                                required
                            >
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100">
                            Créer un compte et rejoindre l'événement
                        </button>
                    </form>
                    
                    <hr>
                    
                    <p class="text-center text-muted small">
                        Vous avez déjà un compte? <a href="login.php">Connectez-vous</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
