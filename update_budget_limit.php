<?php

session_start();
require_once 'database.php';
require_once 'role_check.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$budget_limit = isset($_POST['budget_limit']) ? (float)$_POST['budget_limit'] : 0;
$currency = isset($_POST['currency']) ? trim($_POST['currency']) : 'MAD';

if ($event_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
    exit();
}

if ($budget_limit < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Budget limit cannot be negative']);
    exit();
}

if (!canDo($event_id, $_SESSION['user_id'], 'can_edit_budget')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

try {
    $pdo = getDatabaseConnection();
    
    $stmt = $pdo->prepare('SELECT id FROM event_budget_settings WHERE event_id = ?');
    $stmt->execute([$event_id]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing budget settings
        $stmt = $pdo->prepare('
            UPDATE event_budget_settings 
            SET budget_limit = ?, currency = ?
            WHERE event_id = ?
        ');
        $stmt->execute([$budget_limit, $currency, $event_id]);
    } else {
        // Insert new budget settings
        $stmt = $pdo->prepare('
            INSERT INTO event_budget_settings (event_id, budget_limit, currency)
            VALUES (?, ?, ?)
        ');
        $stmt->execute([$event_id, $budget_limit, $currency]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Budget limit updated successfully'
    ]);
    
} catch (PDOException $e) {
    error_log('Error updating budget limit: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
