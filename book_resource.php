<?php
session_start();

require 'database.php';
require_once 'role_check.php';

requireLogin();

$eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
$resourceId = isset($_POST['resource_id']) ? (int) $_POST['resource_id'] : 0;
$dateDebut = isset($_POST['date_debut']) ? trim($_POST['date_debut']) : '';
$dateFin = isset($_POST['date_fin']) ? trim($_POST['date_fin']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

if ($eventId <= 0 || $resourceId <= 0) {
    header('Location: index.php');
    exit;
}

if (!isEventOrganizer($_SESSION['user_id'], $eventId)) {
    header('Location: event_details.php?id=' . $eventId);
    exit;
}

$error = '';
$pdo = getDatabaseConnection();

if (empty($dateDebut) || empty($dateFin)) {
    $error = 'Les dates de début et de fin sont requises.';
} else {
    $dateDebut = str_replace('T', ' ', $dateDebut) . ':00';
    $dateFin = str_replace('T', ' ', $dateFin) . ':00';

    $startTime = strtotime($dateDebut);
    $endTime = strtotime($dateFin);

    if ($startTime === false || $endTime === false) {
        $error = 'Format de date invalide.';
    } elseif ($startTime >= $endTime) {
        $error = 'La date de fin doit être après la date de début.';
    }
}

// Check if resource exists and belongs to this event
if (!$error) {
    try {
        $stmt = $pdo->prepare('SELECT id, statut FROM event_resources WHERE id = ? AND event_id = ?');
        $stmt->execute([$resourceId, $eventId]);
        $resource = $stmt->fetch();

        if (!$resource) {
            $error = 'Ressource non trouvée.';
        } elseif ($resource['statut'] !== 'Disponible') {
            $error = 'Cette ressource n\'est pas disponible.';
        }
    } catch (PDOException $e) {
        error_log('Error checking resource: ' . $e->getMessage());
        $error = 'Erreur lors de la vérification de la ressource.';
    }
}

// Check for booking conflicts
if (!$error) {
    try {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as count 
            FROM resource_bookings 
            WHERE resource_id = ? 
            AND statut IN ("Confirmée", "En attente")
            AND (
                (date_debut < ? AND date_fin > ?)
                OR (date_debut < ? AND date_fin > ?)
                OR (date_debut >= ? AND date_fin <= ?)
            )
        ');
        $stmt->execute([
            $resourceId,
            $dateFin, $dateDebut,
            $dateFin, $dateDebut,
            $dateDebut, $dateFin
        ]);
        $result = $stmt->fetch();

        if ($result['count'] > 0) {
            $error = 'Cette ressource est déjà réservée pour cette période.';
        }
    } catch (PDOException $e) {
        error_log('Error checking conflicts: ' . $e->getMessage());
        $error = 'Erreur lors de la vérification de la disponibilité.';
    }
}

// Insert booking into database
if (!$error) {
    try {
        $stmt = $pdo->prepare('
            INSERT INTO resource_bookings 
            (resource_id, user_id, event_id, date_debut, date_fin, statut, notes)
            VALUES (?, ?, ?, ?, ?, "Confirmée", ?)
        ');
        $stmt->execute([
            $resourceId,
            $_SESSION['user_id'],
            $eventId,
            $dateDebut,
            $dateFin,
            $notes ?: null
        ]);

        // Redirect back to resources page with success message
        header('Location: resources.php?event_id=' . $eventId . '&success=1');
        exit;
    } catch (PDOException $e) {
        error_log('Error creating booking: ' . $e->getMessage());
        $error = 'Erreur lors de la création de la réservation. Veuillez réessayer.';
    }
}

// If there's an error, redirect back with error message
if ($error) {
    header('Location: resources.php?event_id=' . $eventId . '&error=' . urlencode($error));
    exit;
}
?>
