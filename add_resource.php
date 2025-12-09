<?php
session_start();

require 'database.php';
require_once 'role_check.php';

// Check if user is logged in
requireLogin();

// Get event ID from POST
$eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

if ($eventId <= 0) {
    header('Location: index.php');
    exit;
}

// Check if user is admin or organizer for this event
if (!isEventOrganizer($_SESSION['user_id'], $eventId)) {
    header('Location: event_details.php?id=' . $eventId);
    exit;
}

$error = '';
$pdo = getDatabaseConnection();

// Validate input
$nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
$type = isset($_POST['type']) ? trim($_POST['type']) : '';
$quantite_totale = isset($_POST['quantite_totale']) ? (int) $_POST['quantite_totale'] : 0;
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$date_debut = isset($_POST['date_debut']) ? trim($_POST['date_debut']) : null;
$date_fin = isset($_POST['date_fin']) ? trim($_POST['date_fin']) : null;

// Validate required fields
if (empty($nom)) {
    $error = 'Resource name is required.';
} elseif (empty($type) || !in_array($type, ['Salle', 'Matériel', 'Autre'])) {
    $error = 'Invalid resource type.';
} elseif ($quantite_totale < 1) {
    $error = 'Quantity must be at least 1.';
}

// Handle image upload
$imagePath = null;
if (!$error && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/resources/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = $_FILES['image']['name'];
    $fileSize = $_FILES['image']['size'];
    $fileTmp = $_FILES['image']['tmp_name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Validate file
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($fileExt, $allowedExts)) {
        $error = 'Invalid image format. Allowed: JPG, PNG, GIF.';
    } elseif ($fileSize > 2 * 1024 * 1024) { // 2MB
        $error = 'Image file is too large. Max 2MB.';
    }

    if (!$error) {
        $newFileName = 'resource_' . time() . '_' . uniqid() . '.' . $fileExt;
        $uploadPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmp, $uploadPath)) {
            $imagePath = $uploadPath;
        } else {
            $error = 'Failed to upload image.';
        }
    }
}

// Insert resource into database
if (!$error) {
    try {
        $stmt = $pdo->prepare('
            INSERT INTO event_resources 
            (event_id, nom, type, quantite_totale, description, date_disponibilite_debut, date_disponibilite_fin, image_path, statut)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, "Disponible")
        ');
        $stmt->execute([
            $eventId,
            $nom,
            $type,
            $quantite_totale,
            $description ?: null,
            $date_debut ?: null,
            $date_fin ?: null,
            $imagePath
        ]);

        // Redirect back to resources page with success message
        header('Location: resources.php?event_id=' . $eventId . '&success=1');
        exit;
    } catch (PDOException $e) {
        error_log('Error adding resource: ' . $e->getMessage());
        $error = 'Error adding resource. Please try again.';
    }
}

// If there's an error, redirect back with error message
if ($error) {
    header('Location: resources.php?event_id=' . $eventId . '&error=' . urlencode($error));
    exit;
}
?>
