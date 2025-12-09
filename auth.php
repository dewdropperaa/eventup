<?php
session_start();
require_once 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = 'Please fill in all fields.';
        header('Location: login.php');
        exit;
    }

    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare('SELECT id, nom, email, mot_de_passe FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nom'];
            $_SESSION['user_email'] = $user['email'];
            header('Location: dashboard.php');
            exit;
        } else {
            $_SESSION['login_error'] = 'Invalid email or password.';
            header('Location: login.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log('Login failed: ' . $e->getMessage());
        $_SESSION['login_error'] = 'An error occurred. Please try again.';
        header('Location: login.php');
        exit;
    }
} else {
    header('Location: login.php');
    exit;
}
?>
