<?php
session_start();

require 'database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['conflict' => false, 'error' => 'Not logged in']);
    exit;
}

$resourceId = isset($_POST['resource_id']) ? (int) $_POST['resource_id'] : 0;
$dateDebut = isset($_POST['date_debut']) ? trim($_POST['date_debut']) : '';
$dateFin = isset($_POST['date_fin']) ? trim($_POST['date_fin']) : '';

if ($resourceId <= 0 || empty($dateDebut) || empty($dateFin)) {
    echo json_encode(['conflict' => false]);
    exit;
}

$dateDebut = str_replace('T', ' ', $dateDebut) . ':00';
$dateFin = str_replace('T', ' ', $dateFin) . ':00';

$startTime = strtotime($dateDebut);
$endTime = strtotime($dateFin);

if ($startTime === false || $endTime === false || $startTime >= $endTime) {
    echo json_encode(['conflict' => false]);
    exit;
}

$pdo = getDatabaseConnection();

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

    echo json_encode(['conflict' => $result['count'] > 0]);
} catch (Exception $e) {
    error_log('Error checking conflicts: ' . $e->getMessage());
    echo json_encode(['conflict' => false, 'error' => 'Error checking availability']);
}
?>
