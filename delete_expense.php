<?php
/**
 * Delete Expense Handler
 * Handles deleting expenses from an event
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
$expense_id = isset($_POST['expense_id']) ? (int)$_POST['expense_id'] : 0;

if ($expense_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid expense ID']);
    exit();
}

try {
    $pdo = getDatabaseConnection();
    
    // Get expense to verify event_id and check permission
    $stmt = $pdo->prepare('SELECT event_id FROM event_expenses WHERE id = ?');
    $stmt->execute([$expense_id]);
    $expense = $stmt->fetch();
    
    if (!$expense) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Expense not found']);
        exit();
    }
    
    $event_id = $expense['event_id'];
    
    // Check permission
    if (!canDo($event_id, $_SESSION['user_id'], 'can_edit_budget')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit();
    }
    
    // Delete expense
    $stmt = $pdo->prepare('DELETE FROM event_expenses WHERE id = ?');
    $stmt->execute([$expense_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Expense deleted successfully'
    ]);
    
} catch (PDOException $e) {
    error_log('Error deleting expense: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
