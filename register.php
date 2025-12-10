<?php
session_start();
require_once 'database.php';

$errors = [];
$nom = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate name
    if (empty($nom)) {
        $errors['nom'] = 'Name is required.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $nom)) {
        $errors['nom'] = 'Name can only contain letters and spaces.';
    }

    // Validate email
    if (empty($email)) {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format.';
    }

    // Validate password
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    }

    // If no validation errors, proceed with database operations
    if (empty($errors)) {
        try {
            $pdo = getDatabaseConnection();

            // Check if email already exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $errors['email'] = 'Email already registered.';
            } else {
                // Hash password and insert user
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('INSERT INTO users (nom, email, mot_de_passe) VALUES (?, ?, ?)');
                $stmt->execute([$nom, $email, $hashed_password]);
                
                // Redirect to login page with a success message
                $_SESSION['success_message'] = 'Registration successful! You can now log in.';
                header('Location: login.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Registration failed: ' . $e->getMessage());
            $errors['db'] = 'An error occurred during registration. Please try again.';
        }
    }
}

require_once 'header.php';
?>

<!-- Notice for new signup page -->
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="bi bi-info-circle"></i>
    <strong>New Professional Sign Up Available!</strong> We've launched a new, modern signup experience with enhanced features and better security.
    <a href="signup.php" class="alert-link">Try our new Sign Up page</a> for the best experience.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="card-title text-center mb-4">Create an Account</h1>

                <?php if (isset($errors['db'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle"></i>
                        <?php echo htmlspecialchars($errors['db']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="post" id="register-form" novalidate>
                    <div class="mb-3">
                        <label for="nom" class="form-label">Full Name</label>
                        <input type="text" class="form-control <?php echo isset($errors['nom']) ? 'is-invalid' : ''; ?>" id="nom" name="nom" value="<?php echo htmlspecialchars($nom); ?>" required>
                        <div class="invalid-feedback"><?php echo isset($errors['nom']) ? htmlspecialchars($errors['nom']) : 'Please enter your full name.'; ?></div>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        <div class="invalid-feedback"><?php echo isset($errors['email']) ? htmlspecialchars($errors['email']) : 'Please enter a valid email address.'; ?></div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                        <div class="invalid-feedback"><?php echo isset($errors['password']) ? htmlspecialchars($errors['password']) : 'Password must be at least 8 characters long.'; ?></div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Register</button>
                    </div>
                </form>

                <div class="text-center mt-3">
                    <p>Already have an account? <a href="login.php">Log in here</a>.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
