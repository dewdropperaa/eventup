<?php
/**
 * Add Income Handler
 * Handles adding new incomes to an event
 */

session_start();
require_once 'database.php';
require_once 'role_check.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$source = isset($_POST['source']) ? trim($_POST['source']) : '';
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$date = isset($_POST['date']) ? trim($_POST['date']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// Validate inputs
if ($event_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
    exit();
}

if (empty($source) || empty($title) || $amount <= 0 || empty($date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

// Check permission: user must be event owner or have can_edit_budget permission
if (!canDo($event_id, $_SESSION['user_id'], 'can_edit_budget')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

try {
    $pdo = getDatabaseConnection();
    
    // Insert income
    $stmt = $pdo->prepare('
        INSERT INTO event_incomes (event_id, source, title, amount, date, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    
    $stmt->execute([
        $event_id,
        $source,
        $title,
        $amount,
        $date,
        $notes
    ]);
    
    $income_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Income added successfully',
        'income_id' => $income_id
    ]);
    
} catch (PDOException $e) {
    error_log('Error adding income: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
