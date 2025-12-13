<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - EventUp</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <!-- Header -->
    <header class="login-header">
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
                    <span class="text-muted me-2">Vous n'avez pas de compte ?</span>
                    <a href="register.php" class="btn-signup">S'inscrire</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Login Section -->
    <section class="login-section">
        <div class="container">
            <div class="row justify-content-center align-items-center min-vh-100">
                <div class="col-lg-10">
                    <div class="login-container">
                        <div class="row g-0">
                            <!-- Left Side - Login Form -->
                            <div class="col-lg-6">
                                <div class="login-form-wrapper">
                                    <div class="login-header-content">
                                        <h1>Bon retour !</h1>
                                        <p>Connectez-vous pour gérer vos événements et tout garder organisé</p>
                                    </div>

                                    <?php if ($error): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <i class="bi bi-exclamation-circle"></i>
                                            <?php echo htmlspecialchars($error); ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($success_message): ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <i class="bi bi-check-circle"></i>
                                            <?php echo htmlspecialchars($success_message); ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php endif; ?>

                                    <form class="login-form" action="auth.php" method="post" id="loginForm">
                                        <!-- Email Input -->
                                        <div class="form-group">
                                            <label for="email" class="form-label">
                                                <i class="bi bi-envelope"></i> Adresse Email
                                            </label>
                                            <input 
                                                type="email" 
                                                class="form-control" 
                                                id="email" 
                                                name="email"
                                                placeholder="you@company.com"
                                                required
                                            >
                                        </div>

                                        <!-- Password Input -->
                                        <div class="form-group">
                                            <label for="password" class="form-label">
                                                <i class="bi bi-lock"></i> Mot de passe
                                            </label>
                                            <div class="password-input-wrapper">
                                                <input 
                                                    type="password" 
                                                    class="form-control" 
                                                    id="password" 
                                                    name="password"
                                                    placeholder="Entrez votre mot de passe"
                                                    required
                                                >
                                                <button type="button" class="password-toggle" id="togglePassword">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Submit Button -->
                                        <button type="submit" class="btn-login-submit">
                                            Se connecter
                                            <i class="bi bi-arrow-right"></i>
                                        </button>
                                    </form>

                                    <!-- Sign Up Link -->
                                    <div class="signup-link">
                                        <p>Vous n'avez pas de compte ? <a href="register.php">Créez-en un maintenant</a></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Side - Info Panel -->
                            <div class="col-lg-6 d-none d-lg-block">
                                <div class="login-info-panel">
                                    <div class="info-content">
                                        <div class="info-icon">
                                            <i class="bi bi-calendar-check"></i>
                                        </div>
                                        <h2>Gérez les Événements Facilement</h2>
                                        <p>Rejoignez des milliers d'organisations utilisant EventUp pour rationaliser leur gestion d'événements internes.</p>
                                        
                                        <div class="features-list">
                                            <div class="feature-item">
                                                <div class="feature-icon">
                                                    <i class="bi bi-check-circle-fill"></i>
                                                </div>
                                                <div class="feature-text">
                                                    <h4>Création Rapide d'Événements</h4>
                                                    <p>Créez et gérez des événements en quelques secondes</p>
                                                </div>
                                            </div>
                                            <div class="feature-item">
                                                <div class="feature-icon">
                                                    <i class="bi bi-check-circle-fill"></i>
                                                </div>
                                                <div class="feature-text">
                                                    <h4>Suivi en Temps Réel</h4>
                                                    <p>Surveillez les RSVPs et la participation en direct</p>
                                                </div>
                                            </div>
                                            <div class="feature-item">
                                                <div class="feature-icon">
                                                    <i class="bi bi-check-circle-fill"></i>
                                                </div>
                                                <div class="feature-text">
                                                    <h4>Collaboration d'Équipe</h4>
                                                    <p>Travaillez en harmonie avec votre équipe</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="testimonial">
                                            <div class="quote-icon">
                                                <i class="bi bi-quote"></i>
                                            </div>
                                            <p>"EventUp a transformé notre façon de gérer les événements d'entreprise. C'est intuitif, puissant et nous fait gagner des heures chaque semaine."</p>
                                            <div class="testimonial-author">
                                                <strong>Sarah Martin</strong>
                                                <span>Responsable RH, TechCorp</span>
                                            </div>
                                        </div>
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
        // Password Toggle
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

        // Form Validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            
            // Basic validation
            if (!email.value || !password.value) {
                e.preventDefault();
                // Create error alert if not present
                if (!document.querySelector('.alert-danger')) {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="bi bi-exclamation-circle"></i>
                        Veuillez remplir tous les champs requis.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    const form = document.querySelector('.login-form');
                    form.parentNode.insertBefore(alertDiv, form);
                }
                return false;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email.value)) {
                e.preventDefault();
                if (!document.querySelector('.alert-danger')) {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="bi bi-exclamation-circle"></i>
                        Veuillez entrer une adresse email valide.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    const form = document.querySelector('.login-form');
                    form.parentNode.insertBefore(alertDiv, form);
                }
                return false;
            }
        });
    </script>
</body>
</html>
