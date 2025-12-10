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
    
    if (!$data) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    if (empty($data['name'])) {
        throw new Exception('Role name is required');
    }
    
    $name = trim($data['name']);
    $description = trim($data['description'] ?? '');
    $color_class = $data['color_class'] ?? 'orange-gradient';
    $icon_class = $data['icon_class'] ?? 'bi-shield-fill-check';
    
    // Validate color and icon classes
    $valid_colors = ['orange-gradient', 'teal-gradient', 'blue-gradient', 'yellow-gradient', 'bg-secondary-gradient'];
    $valid_icons = ['bi-shield-fill-check', 'bi-person-gear', 'bi-calendar-event', 'bi-building', 'bi-person'];
    
    if (!in_array($color_class, $valid_colors)) {
        $color_class = 'orange-gradient';
    }
    
    if (!in_array($icon_class, $valid_icons)) {
        $icon_class = 'bi-shield-fill-check';
    }
    
    // Check if role name already exists
    $stmt = getDatabaseConnection()->prepare("SELECT id FROM roles WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        throw new Exception('Role name already exists');
    }
    
    // Insert new role
    $stmt = getDatabaseConnection()->prepare("
        INSERT INTO roles (name, description, color_class, icon_class, created_at, updated_at) 
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");
    $result = $stmt->execute([$name, $description, $color_class, $icon_class]);
    
    if (!$result) {
        throw new Exception('Failed to create role');
    }
    
    $role_id = getDatabaseConnection()->lastInsertId();
    
    // Create default permissions for the role
    $default_permissions = [
        'events_create' => false,
        'events_edit' => false,
        'events_delete' => false,
        'events_view' => false,
        'events_publish' => false,
        'users_create' => false,
        'users_edit' => false,
        'users_delete' => false,
        'users_view' => false,
        'resources_manage' => false,
        'resources_reserve' => false,
        'resources_approve' => false,
        'reports_view' => false,
        'reports_export' => false,
        'reports_create' => false,
        'settings_view' => false,
        'settings_modify' => false,
        'settings_roles' => false,
        'scope' => 'event'
    ];
    
    foreach ($default_permissions as $permission => $granted) {
        $stmt = getDatabaseConnection()->prepare("
            INSERT INTO role_permissions (role_id, permission_name, granted, created_at, updated_at) 
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$role_id, $permission, $granted]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Role created successfully',
        'role_id' => $role_id
    ]);
    
} catch (Exception $e) {
    error_log("Create role error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
