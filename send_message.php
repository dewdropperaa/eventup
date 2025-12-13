<?php

session_start();
header('Content-Type: application/json');

require 'database.php';
require_once 'role_check.php';

$response = [
    'success' => false,
    'message' => 'Une erreur s\'est produite.'
];

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        $response['message'] = 'Vous devez être connecté pour envoyer des messages.';
        echo json_encode($response);
        exit;
    }

    $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
    $messageText = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';

    if ($eventId <= 0) {
        http_response_code(400);
        $response['message'] = 'ID événement invalide.';
        echo json_encode($response);
        exit;
    }

    if (empty($messageText)) {
        http_response_code(400);
        $response['message'] = 'Le message ne peut pas être vide.';
        echo json_encode($response);
        exit;
    }

    if (strlen($messageText) > 5000) {
        http_response_code(400);
        $response['message'] = 'Le message est trop long (maximum 5000 caractères).';
        echo json_encode($response);
        exit;
    }

    $pdo = getDatabaseConnection();

    $stmt = $pdo->prepare('SELECT id, created_by FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        http_response_code(404);
        $response['message'] = 'Événement non trouvé.';
        echo json_encode($response);
        exit;
    }

    $isEventOwner = ($_SESSION['user_id'] == $event['created_by']);
    $isEventOrganizer = isEventOrganizer($_SESSION['user_id'], $eventId);

    if (!$isEventOwner && !$isEventOrganizer) {
        http_response_code(403);
        $response['message'] = 'Vous n\'avez pas la permission d\'envoyer des messages dans cet événement.';
        echo json_encode($response);
        exit;
    }

    $stmt = $pdo->prepare('
        INSERT INTO event_messages (event_id, user_id, message_text, created_at)
        VALUES (?, ?, ?, NOW())
    ');
    $stmt->execute([$eventId, $_SESSION['user_id'], $messageText]);

    $response['success'] = true;
    $response['message'] = 'Message envoyé avec succès.';
    http_response_code(200);

} catch (PDOException $e) {
    error_log("Database error in send_message.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Erreur de base de données.';
} catch (Exception $e) {
    error_log("Error in send_message.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Une erreur inattendue s\'est produite.';
}

echo json_encode($response);
exit;
?>
