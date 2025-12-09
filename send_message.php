<?php
/**
 * Send Message Handler
 * Handles POST requests to send messages to event communication hub
 * Only organizers and event owners can send messages
 */

session_start();
header('Content-Type: application/json');

require 'database.php';
require_once 'role_check.php';

$response = [
    'success' => false,
    'message' => 'An error occurred.'
];

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        $response['message'] = 'You must be logged in to send messages.';
        echo json_encode($response);
        exit;
    }

    // Validate input
    $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;
    $messageText = isset($_POST['message_text']) ? trim($_POST['message_text']) : '';

    if ($eventId <= 0) {
        http_response_code(400);
        $response['message'] = 'Invalid event ID.';
        echo json_encode($response);
        exit;
    }

    if (empty($messageText)) {
        http_response_code(400);
        $response['message'] = 'Message cannot be empty.';
        echo json_encode($response);
        exit;
    }

    if (strlen($messageText) > 5000) {
        http_response_code(400);
        $response['message'] = 'Message is too long (max 5000 characters).';
        echo json_encode($response);
        exit;
    }

    $pdo = getDatabaseConnection();

    // Verify event exists
    $stmt = $pdo->prepare('SELECT id, created_by FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();

    if (!$event) {
        http_response_code(404);
        $response['message'] = 'Event not found.';
        echo json_encode($response);
        exit;
    }

    // Check if user is organizer or event owner
    $isEventOwner = ($_SESSION['user_id'] == $event['created_by']);
    $isEventOrganizer = isEventOrganizer($_SESSION['user_id'], $eventId);

    if (!$isEventOwner && !$isEventOrganizer) {
        http_response_code(403);
        $response['message'] = 'You do not have permission to send messages in this event.';
        echo json_encode($response);
        exit;
    }

    // Insert message into database
    $stmt = $pdo->prepare('
        INSERT INTO event_messages (event_id, user_id, message_text, created_at)
        VALUES (?, ?, ?, NOW())
    ');
    $stmt->execute([$eventId, $_SESSION['user_id'], $messageText]);

    $response['success'] = true;
    $response['message'] = 'Message sent successfully.';
    http_response_code(200);

} catch (PDOException $e) {
    error_log("Database error in send_message.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Database error occurred.';
} catch (Exception $e) {
    error_log("Error in send_message.php: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'An unexpected error occurred.';
}

echo json_encode($response);
exit;
?>
