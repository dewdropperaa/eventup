<?php

session_start();
header('Content-Type: application/json');

require 'database.php';
require_once 'role_check.php';

$response = [
    'success' => false,
    'messages' => [],
    'message' => 'An error occurred.'
];

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        $response['message'] = 'You must be logged in to fetch messages.';
        echo json_encode($response);
        exit;
    }

    $eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
    $lastId = isset($_GET['last_id']) ? (int) $_GET['last_id'] : 0;

    if ($eventId <= 0) {
        http_response_code(400);
        $response['message'] = 'Invalid event ID.';
        echo json_encode($response);
        exit;
    }

    $pdo = getDatabaseConnection();

    $stmt = $pdo->prepare('SELECT id, created_by FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        http_response_code(404);
        $response['message'] = 'Event not found.';
        echo json_encode($response);
        exit;
    }

    $isEventOwner = ($_SESSION['user_id'] == $event['created_by']);
    $isEventOrganizer = isEventOrganizer($_SESSION['user_id'], $eventId);

    if (!$isEventOwner && !$isEventOrganizer) {
        http_response_code(403);
        $response['message'] = 'You do not have permission to access messages in this event.';
        echo json_encode($response);
        exit;
    }

    if ($lastId > 0) {
        $stmt = $pdo->prepare('
            SELECT 
                em.id,
                em.event_id,
                em.user_id,
                em.message_text,
                em.created_at,
                u.nom as sender_name,
                CASE WHEN em.user_id = ? THEN 1 ELSE 0 END as is_current_user
            FROM event_messages em
            JOIN users u ON em.user_id = u.id
            WHERE em.event_id = ? AND em.id > ?
            ORDER BY em.created_at ASC
            LIMIT 100
        ');
        $stmt->execute([$_SESSION['user_id'], $eventId, $lastId]);
    } else {
        $stmt = $pdo->prepare('
            SELECT 
                em.id,
                em.event_id,
                em.user_id,
                em.message_text,
                em.created_at,
                u.nom as sender_name,
                CASE WHEN em.user_id = ? THEN 1 ELSE 0 END as is_current_user
            FROM event_messages em
            JOIN users u ON em.user_id = u.id
            WHERE em.event_id = ?
            ORDER BY em.created_at ASC
            LIMIT 100
        ');
        $stmt->execute([$_SESSION['user_id'], $eventId]);
    }

    $messages = $stmt->fetchAll();

    foreach ($messages as &$msg) {
        $msg['is_current_user'] = (bool) $msg['is_current_user'];
    }

    $response['success'] = true;
    $response['messages'] = $messages;
    $response['message'] = 'Messages fetched successfully.';
    http_response_code(200);

} catch (PDOException $e) {
    error_log("Database error in fetch_messages.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Database error occurred.';
} catch (Exception $e) {
    error_log("Error in fetch_messages.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'An unexpected error occurred.';
}

echo json_encode($response);
exit;
?>
