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
    // Get JSON input
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    if (!$data || empty($data['role_id'])) {
        throw new Exception('Role ID is required');
    }
    
    $role_id = intval($data['role_id']);
    
    // Check if role exists
    $stmt = getDatabaseConnection()->prepare("SELECT name FROM roles WHERE id = ?");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$role) {
        throw new Exception('Role not found');
    }
    
    // Begin transaction
    getDatabaseConnection()->beginTransaction();
    
    try {
        // Update scope
        $stmt = getDatabaseConnection()->prepare("
            INSERT INTO role_permissions (role_id, permission_name, granted, created_at, updated_at) 
            VALUES (?, 'scope', ?, NOW(), NOW()) 
            ON DUPLICATE KEY UPDATE granted = ?, updated_at = NOW()
        ");
        $stmt->execute([$role_id, $data['scope'], $data['scope']]);
        
        // Update permissions
        foreach ($data['permissions'] as $permission_name => $granted) {
            $stmt = getDatabaseConnection()->prepare("
                INSERT INTO role_permissions (role_id, permission_name, granted, created_at, updated_at) 
                VALUES (?, ?, ?, NOW(), NOW()) 
                ON DUPLICATE KEY UPDATE granted = ?, updated_at = NOW()
            ");
            $stmt->execute([$role_id, $permission_name, $granted, $granted]);
        }
        
        // Commit transaction
        getDatabaseConnection()->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Permissions saved successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction
        getDatabaseConnection()->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Save role permissions error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
