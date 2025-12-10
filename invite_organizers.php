<?php
/**
 * Invite Organizers Page
 * Allows event admins to invite organizers to their event
 */

session_start();
require_once 'role_check.php';

// Check if user is logged in
requireLogin();

require_once 'database.php';
require_once 'notifications.php';

$pdo = getDatabaseConnection();
$error = '';
$success = '';
$event = null;
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// Verify event exists and user is admin
if ($event_id <= 0) {
    $error = 'ID d\'événement invalide.';
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT e.id, e.titre 
            FROM events e
            JOIN event_roles er ON e.id = er.event_id
            WHERE e.id = :event_id AND er.user_id = :user_id AND er.role = 'admin'
        ");
        
        $stmt->execute([
            ':event_id' => $event_id,
            ':user_id' => $_SESSION['user_id']
        ]);
        
        $event = $stmt->fetch();
        
        if (!$event) {
            $error = 'Vous n\'avez pas la permission d\'accéder à cette page.';
        }
    } catch (PDOException $e) {
        error_log("Event verification error: " . $e->getMessage());
        $error = 'Une erreur est survenue lors de la vérification de l\'événement.';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $emails = isset($_POST['emails']) ? trim($_POST['emails']) : '';
    
    if (empty($emails)) {
        $error = 'Veuillez entrer au moins une adresse email.';
    } else {
        // Parse emails (comma or newline separated)
        $email_list = preg_split('/[\s,]+/', $emails, -1, PREG_SPLIT_NO_EMPTY);
        $email_list = array_map('trim', $email_list);
        $email_list = array_unique($email_list);
        
        $invited_count = 0;
        $errors = [];
        
        try {
            $pdo->beginTransaction();
            
            foreach ($email_list as $email) {
                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Format d'email invalide: " . htmlspecialchars($email);
                    continue;
                }
                
                // Check if user exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // User exists - check if already has a role in this event
                    $stmt = $pdo->prepare("
                        SELECT id FROM event_roles 
                        WHERE user_id = :user_id AND event_id = :event_id
                    ");
                    $stmt->execute([
                        ':user_id' => $user['id'],
                        ':event_id' => $event_id
                    ]);
                    
                    if ($stmt->fetch()) {
                        $errors[] = htmlspecialchars($email) . " a déjà un rôle pour cet événement.";
                    } else {
                        // Add user as organizer
                        $stmt = $pdo->prepare("
                            INSERT INTO event_roles (user_id, event_id, role)
                            VALUES (:user_id, :event_id, 'organizer')
                        ");
                        $stmt->execute([
                            ':user_id' => $user['id'],
                            ':event_id' => $event_id
                        ]);
                        
                        // Also add to event_organizers table for permission management
                        $stmt = $pdo->prepare("
                            INSERT INTO event_organizers (event_id, user_id, role)
                            VALUES (:event_id, :user_id, 'organizer')
                            ON DUPLICATE KEY UPDATE role = 'organizer'
                        ");
                        $stmt->execute([
                            ':event_id' => $event_id,
                            ':user_id' => $user['id']
                        ]);
                        
                        $invited_count++;

                        // Send in-app notification to existing user
                        $msg = "You have been added as an organizer for the event '" . $event['titre'] . "'.";
                        createNotification($user['id'], 'Organizer invitation', $msg, $event_id);
                    }
                } else {
                    // User doesn't exist - send invitation email
                    $invitation_token = bin2hex(random_bytes(32));
                    $token_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
                    
                    // Store invitation in database
                    $stmt = $pdo->prepare("
                        INSERT INTO event_invitations (email, event_id, token, token_expiry, created_at)
                        VALUES (:email, :event_id, :token, :token_expiry, NOW())
                    ");
                    $stmt->execute([
                        ':email' => $email,
                        ':event_id' => $event_id,
                        ':token' => $invitation_token,
                        ':token_expiry' => $token_expiry
                    ]);
                    
                    // Send invitation email
                    $invitation_link = "http://" . $_SERVER['HTTP_HOST'] . "/EventUp/register_from_invitation.php?token=" . $invitation_token;
                    sendInvitationEmail($email, $event['titre'], $invitation_link);
                    $invited_count++;
                }
            }
            
            $pdo->commit();
            
            if ($invited_count > 0) {
                $success = $invited_count . " invitations envoyées avec succès!";
            }
            
            if (!empty($errors)) {
                $error = implode('<br>', $errors);
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Invitation error: " . $e->getMessage());
            $error = 'Une erreur est survenue lors de l\'envoi des invitations.';
        }
    }
}

/**
 * Send invitation email to new user
 * 
 * @param string $email Recipient email
 * @param string $event_title Event title
 * @param string $invitation_link Registration link with token
 */
function sendInvitationEmail($email, $event_title, $invitation_link) {
    $subject = "Invitation à rejoindre l'événement: " . $event_title;
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f8f9fa; }
            .button { display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>EventUp</h1>
            </div>
            <div class='content'>
                <p>Bonjour,</p>
                <p>Vous avez été invité à rejoindre l'événement <strong>" . htmlspecialchars($event_title) . "</strong> en tant qu'organisateur.</p>
                <p>Pour accepter cette invitation, veuillez créer un compte ou vous connecter en utilisant le lien ci-dessous:</p>
                <a href='" . htmlspecialchars($invitation_link) . "' class='button'>Accepter l'invitation</a>
                <p style='margin-top: 20px; font-size: 12px; color: #666;'>Ce lien d'invitation expire dans 7 jours.</p>
            </div>
            <div class='footer'>
                <p>&copy; 2025 EventUp. Tous droits réservés.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: noreply@eventup.local\r\n";
    
    // In production, use a proper mail service like PHPMailer or SendGrid
    // For now, we'll use PHP's mail function
    mail($email, $subject, $message, $headers);
}

include 'header.php';
?>

<?php if ($error && !$event): ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i>
                <strong>Error!</strong> <?php echo $error; ?>
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
                    <h3 class="mb-0">Inviter des organisateurs</h3>
                    <small>Événement: <?php echo htmlspecialchars($event['titre']); ?></small>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-circle"></i>
                            <strong>Error!</strong> <?php echo $error; ?>
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
                    
                    <form method="POST" action="invite_organizers.php?event_id=<?php echo $event_id; ?>" id="inviteForm" novalidate>
                        <div class="mb-3">
                            <label for="emails" class="form-label">Adresses email <span class="text-danger">*</span></label>
                            <textarea 
                                class="form-control" 
                                id="emails" 
                                name="emails" 
                                rows="6"
                                placeholder="Entrez les adresses email séparées par des virgules ou des sauts de ligne&#10;Ex:&#10;organizer1@example.com&#10;organizer2@example.com&#10;organizer3@example.com"
                                required
                            ><?php echo htmlspecialchars($_POST['emails'] ?? ''); ?></textarea>
                            <small class="form-text text-muted">
                                Vous pouvez entrer plusieurs adresses email séparées par des virgules ou des sauts de ligne.
                                <br>
                                • Si l'utilisateur existe déjà, il sera ajouté directement comme organisateur.
                                <br>
                                • Si l'utilisateur n'existe pas, une invitation par email lui sera envoyée.
                            </small>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button 
                                type="button" 
                                class="btn btn-primary" 
                                id="submitInviteBtn"
                                data-bs-toggle="modal" 
                                data-bs-target="#confirmInviteModal"
                            >
                                Envoyer les invitations
                            </button>
                            <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Display current organizers -->
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Organisateurs actuels</h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $pdo->prepare("
                            SELECT u.nom, u.email, er.role
                            FROM event_roles er
                            JOIN users u ON er.user_id = u.id
                            WHERE er.event_id = :event_id AND er.role IN ('admin', 'organizer')
                            ORDER BY er.role DESC, u.nom ASC
                        ");
                        $stmt->execute([':event_id' => $event_id]);
                        $organizers = $stmt->fetchAll();
                        
                        if ($organizers) {
                            echo '<table class="table table-hover">';
                            echo '<thead><tr><th>Nom</th><th>Email</th><th>Rôle</th></tr></thead>';
                            echo '<tbody>';
                            foreach ($organizers as $org) {
                                $role_badge = $org['role'] === 'admin' ? 
                                    '<span class="badge bg-danger">Admin</span>' : 
                                    '<span class="badge bg-info">Organisateur</span>';
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($org['nom']) . '</td>';
                                echo '<td>' . htmlspecialchars($org['email']) . '</td>';
                                echo '<td>' . $role_badge . '</td>';
                                echo '</tr>';
                            }
                            echo '</tbody>';
                            echo '</table>';
                        } else {
                            echo '<p class="text-muted">Aucun organisateur pour le moment.</p>';
                        }
                    } catch (PDOException $e) {
                        error_log("Organizers fetch error: " . $e->getMessage());
                        echo '<p class="text-danger">Erreur lors du chargement des organisateurs.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Confirm Organizer Invitation Modal -->
<div class="modal fade" id="confirmInviteModal" tabindex="-1" aria-labelledby="confirmInviteLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="confirmInviteLabel">Confirm Organizer Invitations</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>You are about to send invitations to the following email addresses:</p>
                <div id="emailPreview" class="alert alert-light border" style="max-height: 200px; overflow-y: auto;">
                    <!-- Email list will be populated here -->
                </div>
                <p class="text-muted small">
                    <strong>Note:</strong> Existing users will be added directly as organizers. New users will receive an invitation email.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmInviteBtn">Send Invitations</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const inviteForm = document.getElementById('inviteForm');
    const submitInviteBtn = document.getElementById('submitInviteBtn');
    const confirmInviteModal = document.getElementById('confirmInviteModal');
    const confirmInviteBtn = document.getElementById('confirmInviteBtn');
    const emailPreview = document.getElementById('emailPreview');

    if (submitInviteBtn && confirmInviteModal) {
        submitInviteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const emailsTextarea = document.getElementById('emails');
            const emails = emailsTextarea.value.trim();
            
            if (!emails) {
                alert('Please enter at least one email address.');
                return;
            }
            
            // Parse and display emails
            const emailList = emails.split(/[\s,\n]+/).filter(email => email.trim().length > 0);
            const uniqueEmails = [...new Set(emailList)];
            
            // Display emails in preview
            emailPreview.innerHTML = '';
            uniqueEmails.forEach(email => {
                const emailItem = document.createElement('div');
                emailItem.className = 'mb-2';
                emailItem.innerHTML = '<i class="bi bi-envelope"></i> ' + htmlEscape(email);
                emailPreview.appendChild(emailItem);
            });
            
            // Show modal
            const modal = new bootstrap.Modal(confirmInviteModal);
            modal.show();
        });

        if (confirmInviteBtn) {
            confirmInviteBtn.addEventListener('click', function() {
                inviteForm.submit();
            });
        }
    }

    // Helper function to escape HTML
    function htmlEscape(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
});
</script>

<?php include 'footer.php'; ?>
