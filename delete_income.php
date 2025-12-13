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

$income_id = isset($_POST['income_id']) ? (int)$_POST['income_id'] : 0;

if ($income_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid income ID']);
    exit();
}

try {
    $pdo = getDatabaseConnection();
    
    $stmt = $pdo->prepare('SELECT event_id FROM event_incomes WHERE id = ?');
    $stmt->execute([$income_id]);
    $income = $stmt->fetch();
    
    if (!$income) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Income not found']);
        exit();
    }
    
    $event_id = $income['event_id'];
    
    if (!canDo($event_id, $_SESSION['user_id'], 'can_edit_budget')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit();
    }
    
    $stmt = $pdo->prepare('DELETE FROM event_incomes WHERE id = ?');
    $stmt->execute([$income_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Income deleted successfully'
    ]);
    
} catch (PDOException $e) {
    error_log('Error deleting income: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
