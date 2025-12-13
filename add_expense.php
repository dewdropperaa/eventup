<?php

session_start();
require_once 'database.php';
require_once 'role_check.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
$category = isset($_POST['category']) ? trim($_POST['category']) : '';
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$date = isset($_POST['date']) ? trim($_POST['date']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

if ($event_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID événement invalide']);
    exit();
}

if (empty($category) || empty($title) || $amount <= 0 || empty($date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Champs obligatoires manquants']);
    exit();
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Format de date invalide']);
    exit();
}

if (!canDo($event_id, $_SESSION['user_id'], 'can_edit_budget')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Interdit']);
    exit();
}

try {
    $pdo = getDatabaseConnection();
    
    $stmt = $pdo->prepare('
        INSERT INTO event_expenses (event_id, category, title, amount, date, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    
    $stmt->execute([
        $event_id,
        $category,
        $title,
        $amount,
        $date,
        $notes
    ]);
    
    $expense_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Dépense ajoutée avec succès',
        'expense_id' => $expense_id
    ]);
    
} catch (PDOException $e) {
    error_log('Error adding expense: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
}
?>
