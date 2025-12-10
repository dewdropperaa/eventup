<?php
session_start();
require_once 'auth.php';
require_once 'database.php';
require_once 'role_check.php';

// Check if user is logged in and is an admin
requireLogin();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

header('Content-Type: application/json');

try {
    if (!isset($_GET['role_id']) || empty($_GET['role_id'])) {
        throw new Exception('Role ID is required');
    }
    
    $role_id = intval($_GET['role_id']);
    
    // Get role details
    $stmt = getDatabaseConnection()->prepare("
        SELECT id, name, description, color_class, icon_class, created_at, updated_at 
        FROM roles 
        WHERE id = ?
    ");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        throw new Exception('Role not found');
    }
    
    echo json_encode([
        'success' => true,
        'role' => $role
    ]);
    
} catch (Exception $e) {
    error_log("Get role error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
