<?php
session_start();

require 'database.php';
require_once 'role_check.php';

// Check if user is logged in
requireLogin();

// Get parameters from POST
$eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
$resourceId = isset($_POST['resource_id']) ? (int) $_POST['resource_id'] : 0;
$dateDebut = isset($_POST['date_debut']) ? trim($_POST['date_debut']) : '';
$dateFin = isset($_POST['date_fin']) ? trim($_POST['date_fin']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

if ($eventId <= 0 || $resourceId <= 0) {
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
if (empty($dateDebut) || empty($dateFin)) {
    $error = 'Start and end dates are required.';
} else {
    // Convert datetime-local format to proper datetime
    $dateDebut = str_replace('T', ' ', $dateDebut) . ':00';
    $dateFin = str_replace('T', ' ', $dateFin) . ':00';

    // Validate dates
    $startTime = strtotime($dateDebut);
    $endTime = strtotime($dateFin);

    if ($startTime === false || $endTime === false) {
        $error = 'Invalid date format.';
    } elseif ($startTime >= $endTime) {
        $error = 'End date must be after start date.';
    }
}

// Check if resource exists and belongs to this event
if (!$error) {
    try {
        $stmt = $pdo->prepare('SELECT id, statut FROM event_resources WHERE id = ? AND event_id = ?');
        $stmt->execute([$resourceId, $eventId]);
        $resource = $stmt->fetch();

        if (!$resource) {
            $error = 'Resource not found.';
        } elseif ($resource['statut'] !== 'Disponible') {
            $error = 'This resource is not available.';
        }
    } catch (PDOException $e) {
        error_log('Error checking resource: ' . $e->getMessage());
        $error = 'Error checking resource.';
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
            $error = 'This resource is already booked for this time period.';
        }
    } catch (PDOException $e) {
        error_log('Error checking conflicts: ' . $e->getMessage());
        $error = 'Error checking availability.';
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
        $error = 'Error creating booking. Please try again.';
    }
}

// If there's an error, redirect back with error message
if ($error) {
    header('Location: resources.php?event_id=' . $eventId . '&error=' . urlencode($error));
    exit;
}
?>
