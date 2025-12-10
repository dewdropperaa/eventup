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
    
    // Prevent deletion of admin role
    if ($role['name'] === 'admin' || $role['name'] === 'Administrateur') {
        throw new Exception('Cannot delete admin role');
    }
    
    // Check if role is assigned to any users
    $stmt = getDatabaseConnection()->prepare("SELECT COUNT(*) as user_count FROM user_organizations WHERE role_id = ?");
    $stmt->execute([$role_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['user_count'] > 0) {
        throw new Exception('Cannot delete role that is assigned to users. Please reassign users first.');
    }
    
    // Begin transaction
    getDatabaseConnection()->beginTransaction();
    
    try {
        // Delete role permissions
        $stmt = getDatabaseConnection()->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt->execute([$role_id]);
        
        // Delete role
        $stmt = getDatabaseConnection()->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->execute([$role_id]);
        
        // Commit transaction
        getDatabaseConnection()->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction
        getDatabaseConnection()->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Delete role error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
