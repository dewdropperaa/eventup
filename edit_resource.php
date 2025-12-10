<?php
session_start();

require 'database.php';
require_once 'role_check.php';

// Check if user is logged in
requireLogin();

// Get resource ID from POST
$resourceId = isset($_POST['resource_id']) ? (int) $_POST['resource_id'] : 0;
$eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

if ($resourceId <= 0 || $eventId <= 0) {
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
$statut = isset($_POST['statut']) ? trim($_POST['statut']) : 'Disponible';

// Validate required fields
if (empty($nom)) {
    $error = 'Resource name is required.';
} elseif (empty($type) || !in_array($type, ['Salle', 'Matériel', 'Autre'])) {
    $error = 'Invalid resource type.';
} elseif ($quantite_totale < 1) {
    $error = 'Quantity must be at least 1.';
} elseif (!in_array($statut, ['Disponible', 'Indisponible', 'En maintenance'])) {
    $error = 'Invalid status.';
}

// Get current resource to check for image
$currentResource = null;
if (!$error) {
    try {
        $stmt = $pdo->prepare('SELECT image_path FROM event_resources WHERE id = ? AND event_id = ?');
        $stmt->execute([$resourceId, $eventId]);
        $currentResource = $stmt->fetch();
        if (!$currentResource) {
            $error = 'Resource not found.';
        }
    } catch (PDOException $e) {
        error_log('Error fetching resource: ' . $e->getMessage());
        $error = 'Error loading resource.';
    }
}

// Handle image upload
$imagePath = $currentResource['image_path'] ?? null;
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
        // Delete old image if exists
        if ($imagePath && file_exists($imagePath)) {
            unlink($imagePath);
        }

        $newFileName = 'resource_' . time() . '_' . uniqid() . '.' . $fileExt;
        $uploadPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmp, $uploadPath)) {
            $imagePath = $uploadPath;
        } else {
            $error = 'Failed to upload image.';
        }
    }
}

// Update resource in database
if (!$error) {
    try {
        $stmt = $pdo->prepare('
            UPDATE event_resources 
            SET nom = ?, type = ?, quantite_totale = ?, description = ?, 
                date_disponibilite_debut = ?, date_disponibilite_fin = ?, 
                image_path = ?, statut = ?
            WHERE id = ? AND event_id = ?
        ');
        $stmt->execute([
            $nom,
            $type,
            $quantite_totale,
            $description ?: null,
            $date_debut ?: null,
            $date_fin ?: null,
            $imagePath,
            $statut,
            $resourceId,
            $eventId
        ]);

        // Redirect back to resources page with success message
        header('Location: resources.php?event_id=' . $eventId . '&success=1');
        exit;
    } catch (PDOException $e) {
        error_log('Error updating resource: ' . $e->getMessage());
        $error = 'Error updating resource. Please try again.';
    }
}

// If there's an error, redirect back with error message
if ($error) {
    header('Location: resources.php?event_id=' . $eventId . '&error=' . urlencode($error));
    exit;
}
?>
