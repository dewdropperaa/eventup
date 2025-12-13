<?php
session_start();
require_once 'database.php';

$errors = [];
$firstName = '';
$lastName = '';
$email = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $agreeTerms = isset($_POST['agreeTerms']);

    // Validate first name
    if (empty($firstName)) {
        $errors['firstName'] = 'Le prénom est requis.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $firstName)) {
        $errors['firstName'] = 'Le prénom ne peut contenir que des lettres et des espaces.';
    } elseif (strlen($firstName) < 2 || strlen($firstName) > 50) {
        $errors['firstName'] = 'Le prénom doit contenir entre 2 et 50 caractères.';
    }

    // Validate last name
    if (empty($lastName)) {
        $errors['lastName'] = 'Le nom de famille est requis.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $lastName)) {
        $errors['lastName'] = 'Le nom de famille ne peut contenir que des lettres et des espaces.';
    } elseif (strlen($lastName) < 2 || strlen($lastName) > 50) {
        $errors['lastName'] = 'Le nom de famille doit contenir entre 2 et 50 caractères.';
    }

    // Validate email
    if (empty($email)) {
        $errors['email'] = 'L\'email est requis.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format d\'email invalide.';
    } elseif (strlen($email) > 100) {
        $errors['email'] = 'L\'adresse email est trop longue.';
    }

    // Validate password
    if (empty($password)) {
        $errors['password'] = 'Le mot de passe est requis.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
        $errors['password'] = 'Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre.';
    }

    // Validate password confirmation
    if (empty($confirmPassword)) {
        $errors['confirmPassword'] = 'Veuillez confirmer votre mot de passe.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirmPassword'] = 'Les mots de passe ne correspondent pas.';
    }

    // Validate terms agreement
    if (!$agreeTerms) {
        $errors['agreeTerms'] = 'Vous devez accepter les Conditions d\'Utilisation et la Politique de Confidentialité.';
    }

    // If no validation errors, proceed with database operations
    if (empty($errors)) {
        try {
            $pdo = getDatabaseConnection();

            // Check if email already exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $errors['email'] = 'Email déjà enregistré.';
            } else {
                // Combine first and last name
                $fullName = trim($firstName . ' ' . $lastName);
                
                // Hash password and insert user
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('INSERT INTO users (nom, email, mot_de_passe, role, created_at) VALUES (?, ?, ?, ?, NOW())');
                $stmt->execute([$fullName, $email, $hashed_password, 'user']);
                
                // Get the new user ID
                $userId = $pdo->lastInsertId();
                
                // Set session for automatic login
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_name'] = $fullName;
                $_SESSION['user_email'] = $email;
                
                // Redirect to dashboard with success message
                $_SESSION['success_message'] = 'Inscription réussie ! Bienvenue sur EventUp !';
                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Registration failed: ' . $e->getMessage());
            $errors['db'] = 'Une erreur s\'est produite lors de l\'inscription. Veuillez réessayer.';
        }
    }
}

// Handle AJAX requests for real-time validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    $fieldErrors = [];
    
    switch ($field) {
        case 'email':
            if (empty($value)) {
                $fieldErrors['email'] = 'L\'email est requis.';
            } elseif (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $fieldErrors['email'] = 'Format d\'email invalide.';
            } else {
                try {
                    $pdo = getDatabaseConnection();
                    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                    $stmt->execute([$value]);
                    if ($stmt->fetch()) {
                        $fieldErrors['email'] = 'Email déjà enregistré.';
                    }
                } catch (PDOException $e) {
                    $fieldErrors['email'] = 'Impossible de valider l\'email.';
                }
            }
            break;
            
        case 'password':
            if (empty($value)) {
                $fieldErrors['password'] = 'Le mot de passe est requis.';
            } elseif (strlen($value) < 8) {
                $fieldErrors['password'] = 'Le mot de passe doit contenir au moins 8 caractères.';
            } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $value)) {
                $fieldErrors['password'] = 'Le mot de passe doit contenir une majuscule, une minuscule et un chiffre.';
            }
            break;
    }
    
    echo json_encode(['success' => empty($fieldErrors), 'errors' => $fieldErrors]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - EventUp</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="signup.css">
</head>
<body>
    <!-- Header -->
    <header class="signup-header">
        <nav class="container-fluid">
            <div class="row align-items-center">
                <div class="col-auto">
                    <div class="logo">
                        <a href="index.php">
                            <img src="assets/EventUp_logo.png" alt="EventUp Logo" class="logo-img">
                        </a>
                    </div>
                </div>
                <div class="col text-end">
                    <span class="text-muted me-2">Vous avez déjà un compte ?</span>
                    <a href="login.php" class="btn-login">Se connecter</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Sign Up Section -->
    <section class="signup-section">
        <div class="container">
            <div class="row justify-content-center align-items-center min-vh-100">
                <div class="col-lg-10">
                    <div class="signup-container">
                        <div class="row g-0">
                            <!-- Left Side - Info Panel -->
                            <div class="col-lg-5 d-none d-lg-block">
                                <div class="signup-info-panel">
                                    <div class="info-content">
                                        <div class="info-icon">
                                            <i class="bi bi-rocket-takeoff"></i>
                                        </div>
                                        <h2>Commencez à Gérer vos Événements Aujourd'hui</h2>
                                        <p>Rejoignez des milliers d'organisations qui optimisent leurs événements internes avec EventUp.</p>
                                        
                                        <div class="benefits-list">
                                            <div class="benefit-item">
                                                <div class="benefit-icon">
                                                    <i class="bi bi-check-lg"></i>
                                                </div>
                                                <span>Un tableau de bord contrôle tous vos événements</span>
                                            </div>
                                            <div class="benefit-item">
                                                <div class="benefit-icon">
                                                    <i class="bi bi-check-lg"></i>
                                                </div>
                                                <span>Événements et participants illimités</span>
                                            </div>
                                            <div class="benefit-item">
                                                <div class="benefit-icon">
                                                    <i class="bi bi-check-lg"></i>
                                                </div>
                                                <span>Support client 24/7</span>
                                            </div>
                                            <div class="benefit-item">
                                                <div class="benefit-icon">
                                                    <i class="bi bi-check-lg"></i>
                                                </div>
                                                <span>Analyses et aperçus avancés</span>
                                            </div>
                                            <div class="benefit-item">
                                                <div class="benefit-icon">
                                                    <i class="bi bi-check-lg"></i>
                                                </div>
                                                <span>Collaboration d'équipe facile</span>
                                            </div>
                                        </div>

                                        <div class="trust-badge">
                                            <div class="trust-stats">
                                                <div class="stat-item">
                                                    <strong>10 000+</strong>
                                                    <span>Organisations</span>
                                                </div>
                                                <div class="stat-divider"></div>
                                                <div class="stat-item">
                                                    <strong>500K+</strong>
                                                    <span>Événements</span>
                                                </div>
                                                <div class="stat-divider"></div>
                                                <div class="stat-item">
                                                    <strong>98%</strong>
                                                    <span>Satisfaction</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Side - Sign Up Form -->
                            <div class="col-lg-7">
                                <div class="signup-form-wrapper">
                                    <div class="signup-header-content">
                                        <h1>Créez Votre Compte</h1>
                                        <p>Commencez avec EventUp en quelques minutes</p>
                                    </div>

                                    <?php if (isset($errors['db'])): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <i class="bi bi-exclamation-circle"></i>
                                            <?php echo htmlspecialchars($errors['db']); ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Divider -->
                                    <div class="divider">
                                        <span>Ou inscrivez-vous avec email</span>
                                    </div>

                                    <form class="signup-form" id="signupForm" method="post" novalidate>
                                        <!-- Name Fields -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="firstName" class="form-label">
                                                        <i class="bi bi-person"></i> Prénom
                                                    </label>
                                                    <input 
                                                        type="text" 
                                                        class="form-control <?php echo isset($errors['firstName']) ? 'is-invalid' : ''; ?>" 
                                                        id="firstName" 
                                                        name="firstName"
                                                        value="<?php echo htmlspecialchars($firstName); ?>"
                                                        placeholder="Jean"
                                                        required
                                                    >
                                                    <?php if (isset($errors['firstName'])): ?>
                                                        <div class="invalid-feedback d-block">
                                                            <?php echo htmlspecialchars($errors['firstName']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="lastName" class="form-label">
                                                        <i class="bi bi-person"></i> Nom
                                                    </label>
                                                    <input 
                                                        type="text" 
                                                        class="form-control <?php echo isset($errors['lastName']) ? 'is-invalid' : ''; ?>" 
                                                        id="lastName" 
                                                        name="lastName"
                                                        value="<?php echo htmlspecialchars($lastName); ?>"
                                                        placeholder="Dupont"
                                                        required
                                                    >
                                                    <?php if (isset($errors['lastName'])): ?>
                                                        <div class="invalid-feedback d-block">
                                                            <?php echo htmlspecialchars($errors['lastName']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Email Input -->
                                        <div class="form-group">
                                            <label for="email" class="form-label">
                                                <i class="bi bi-envelope"></i> Email Professionnel
                                            </label>
                                            <input 
                                                type="email" 
                                                class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                                id="email" 
                                                name="email"
                                                value="<?php echo htmlspecialchars($email); ?>"
                                                placeholder="vous@entreprise.com"
                                                required
                                            >
                                            <?php if (isset($errors['email'])): ?>
                                                <div class="invalid-feedback d-block">
                                                    <?php echo htmlspecialchars($errors['email']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Password Input -->
                                        <div class="form-group">
                                            <label for="password" class="form-label">
                                                <i class="bi bi-lock"></i> Mot de passe
                                            </label>
                                            <div class="password-input-wrapper">
                                                <input 
                                                    type="password" 
                                                    class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                                    id="password" 
                                                    name="password"
                                                    placeholder="Créez un mot de passe fort"
                                                    required
                                                >
                                                <button type="button" class="password-toggle" id="togglePassword">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                            <?php if (isset($errors['password'])): ?>
                                                <div class="invalid-feedback d-block">
                                                    <?php echo htmlspecialchars($errors['password']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="password-strength" id="passwordStrength">
                                                <div class="strength-bar">
                                                    <div class="strength-progress"></div>
                                                </div>
                                                <span class="strength-text">Force du mot de passe : <strong>-</strong></span>
                                            </div>
                                        </div>

                                        <!-- Confirm Password -->
                                        <div class="form-group">
                                            <label for="confirmPassword" class="form-label">
                                                <i class="bi bi-lock-fill"></i> Confirmer le mot de passe
                                            </label>
                                            <div class="password-input-wrapper">
                                                <input 
                                                    type="password" 
                                                    class="form-control <?php echo isset($errors['confirmPassword']) ? 'is-invalid' : ''; ?>" 
                                                    id="confirmPassword" 
                                                    name="confirmPassword"
                                                    placeholder="Réentrez votre mot de passe"
                                                    required
                                                >
                                                <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                            <?php if (isset($errors['confirmPassword'])): ?>
                                                <div class="invalid-feedback d-block">
                                                    <?php echo htmlspecialchars($errors['confirmPassword']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Terms and Privacy -->
                                        <div class="form-check-wrapper">
                                            <div class="form-check">
                                                <input class="form-check-input <?php echo isset($errors['agreeTerms']) ? 'is-invalid' : ''; ?>" type="checkbox" id="agreeTerms" name="agreeTerms" required>
                                                <label class="form-check-label" for="agreeTerms">
                                                    J'accepte les <a href="#">Conditions d'Utilisation</a> et la <a href="#">Politique de Confidentialité</a>
                                                </label>
                                                <?php if (isset($errors['agreeTerms'])): ?>
                                                    <div class="invalid-feedback d-block">
                                                        <?php echo htmlspecialchars($errors['agreeTerms']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Submit Button -->
                                        <button type="submit" class="btn-signup-submit">
                                            <span id="submitText">Créer un Compte</span>
                                            <i class="bi bi-arrow-right" id="submitIcon"></i>
                                        </button>
                                    </form>

                                    <!-- Login Link -->
                                    <div class="login-link">
                                        <p>Vous avez déjà un compte ? <a href="login.php">Connectez-vous ici</a></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Password Toggle for Password field
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

        // Password Toggle for Confirm Password field
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const icon = this.querySelector('i');
            
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                confirmPasswordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });

        // Password Strength Checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.querySelector('.strength-progress');
            const strengthText = document.querySelector('.strength-text strong');
            
            let strength = 0;
            let label = '';
            let color = '';
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[$@#&!]+/)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    label = 'Faible';
                    color = '#dc3545';
                    break;
                case 2:
                case 3:
                    label = 'Moyen';
                    color = '#ffc107';
                    break;
                case 4:
                    label = 'Bon';
                    color = '#17a2b8';
                    break;
                case 5:
                    label = 'Fort';
                    color = '#28a745';
                    break;
            }
            
            strengthBar.style.width = (strength * 20) + '%';
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = password.length > 0 ? label : '-';
            strengthText.style.color = color;
        });

        // Real-time email validation
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            if (email && email.includes('@')) {
                validateField('email', email);
            }
        });

        // Form Submission
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const agreeTerms = document.getElementById('agreeTerms').checked;
            
            // Client-side validation
            if (password !== confirmPassword) {
                alert('Les mots de passe ne correspondent pas !');
                return;
            }
            
            if (!agreeTerms) {
                alert('Vous devez accepter les Conditions d\'Utilisation et la Politique de Confidentialité.');
                return;
            }
            
            // Show loading state
            const submitBtn = document.querySelector('.btn-signup-submit');
            const submitText = document.getElementById('submitText');
            const submitIcon = document.getElementById('submitIcon');
            
            submitBtn.disabled = true;
            submitText.textContent = 'Création du compte...';
            submitIcon.className = 'bi bi-arrow-repeat';
            submitIcon.style.animation = 'spin 1s linear infinite';
            
            // Submit form
            this.submit();
        });

        // AJAX field validation
        function validateField(field, value) {
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('field', field);
            formData.append('value', value);
            
            fetch('signup.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success && data.errors[field]) {
                    // Show error for the field
                    const input = document.getElementById(field);
                    input.classList.add('is-invalid');
                    
                    // Remove existing error feedback
                    const existingFeedback = input.parentNode.querySelector('.invalid-feedback');
                    if (existingFeedback) {
                        existingFeedback.remove();
                    }
                    
                    // Add new error feedback
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback d-block';
                    feedback.textContent = data.errors[field];
                    input.parentNode.appendChild(feedback);
                } else {
                    // Remove error if validation passes
                    const input = document.getElementById(field);
                    input.classList.remove('is-invalid');
                    const feedback = input.parentNode.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.remove();
                    }
                }
            })
            .catch(error => {
                console.error('Validation error:', error);
            });
        }

        // Add CSS animation for loading spinner
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
