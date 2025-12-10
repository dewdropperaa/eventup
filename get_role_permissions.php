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
    
    // Get role permissions
    $stmt = getDatabaseConnection()->prepare("
        SELECT permission_name, granted 
        FROM role_permissions 
        WHERE role_id = ?
    ");
    $stmt->execute([$role_id]);
    $permissions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to associative array
    $permissions = [];
    $scope = 'event'; // default scope
    
    foreach ($permissions_data as $perm) {
        if ($perm['permission_name'] === 'scope') {
            $scope = $perm['granted'];
        } else {
            $permissions[$perm['permission_name']] = (bool)$perm['granted'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'scope' => $scope,
        'permissions' => $permissions
    ]);
    
} catch (Exception $e) {
    error_log("Get role permissions error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
