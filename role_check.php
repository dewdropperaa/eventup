<?php

require_once 'database.php';

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function isOrganizer($user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    if (!$user_id) {
        return false;
    }
    
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM event_roles 
            WHERE user_id = ? AND role IN ('admin', 'organizer')
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking organizer status: " . $e->getMessage());
        return false;
    }
}

function isEventAdmin($user_id, $event_id) {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM event_roles 
            WHERE user_id = ? AND event_id = ? AND role = 'admin'
        ");
        $stmt->execute([$user_id, $event_id]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking event admin status: " . $e->getMessage());
        return false;
    }
}

function isEventOrganizer($user_id, $event_id) {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM event_roles 
            WHERE user_id = ? AND event_id = ? AND role IN ('admin', 'organizer')
        ");
        $stmt->execute([$user_id, $event_id]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking event organizer status: " . $e->getMessage());
        return false;
    }
}

function getUserEventRole($user_id, $event_id) {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("
            SELECT role 
            FROM event_roles 
            WHERE user_id = ? AND event_id = ?
        ");
        $stmt->execute([$user_id, $event_id]);
        $result = $stmt->fetch();
        return $result ? $result['role'] : null;
    } catch (PDOException $e) {
        error_log("Error getting user event role: " . $e->getMessage());
        return null;
    }
}

function requireEventOrganizer($user_id, $event_id) {
    if (!isEventOrganizer($user_id, $event_id)) {
        header('Location: organizer_dashboard.php');
        exit();
    }
}

function requireEventAdmin($user_id, $event_id) {
    if (!isEventAdmin($user_id, $event_id)) {
        header('Location: organizer_dashboard.php');
        exit();
    }
}

function requireOrganizer() {
    requireLogin();
    if (!isOrganizer($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit();
    }
}

function canDo($event_id, $user_id, $permission_name) {
    try {
        $pdo = getDatabaseConnection();

        $stmt = $pdo->prepare('SELECT created_by FROM events WHERE id = ?');
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();

        if ($event && $event['created_by'] == $user_id) {
            return true;
        }

        $stmt = $pdo->prepare(
            'SELECT is_allowed FROM event_permissions WHERE event_id = ? AND user_id = ? AND permission_name = ?'
        );
        $stmt->execute([$event_id, $user_id, $permission_name]);
        $permission = $stmt->fetch();

        if ($permission) {
            return (bool)$permission['is_allowed'];
        }

        return false;

    } catch (PDOException $e) {
        error_log('Error in canDo() function: ' . $e->getMessage());
        return false;
    }
}

function hasRolePermission($user_id, $permission_name, $context = 'event') {
    try {
        $pdo = getDatabaseConnection();

        $stmt = $pdo->prepare("
            SELECT uo.role_id, r.name as role_name, rp.granted, rp.permission_name
            FROM user_organizations uo
            LEFT JOIN roles r ON uo.role_id = r.id
            LEFT JOIN role_permissions rp ON r.id = rp.role_id AND rp.permission_name = ?
            WHERE uo.user_id = ?
        ");
        $stmt->execute([$permission_name, $user_id]);
        $result = $stmt->fetch();

        if (!$result || !$result['role_id']) {
            return false;
        }

        if ($result['granted'] === null) {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT granted 
            FROM role_permissions 
            WHERE role_id = ? AND permission_name = 'scope'
        ");
        $stmt->execute([$result['role_id']]);
        $scope_result = $stmt->fetch();

        if ($scope_result) {
            $scope = $scope_result['granted'];
            
            // Check if the scope allows this permission
            if ($scope === 'global') {
                return (bool)$result['granted'];
            } elseif ($scope === 'department' && $context === 'department') {
                return (bool)$result['granted'];
            } elseif ($scope === 'event' && ($context === 'event' || $context === 'department')) {
                return (bool)$result['granted'];
            }
        }

        return (bool)$result['granted'];

    } catch (PDOException $e) {
        error_log('Error in hasRolePermission() function: ' . $e->getMessage());
        return false;
    }
}

function getUserRole($user_id) {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("
            SELECT r.name 
            FROM user_organizations uo
            LEFT JOIN roles r ON uo.role_id = r.id
            WHERE uo.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result ? $result['name'] : null;
    } catch (PDOException $e) {
        error_log('Error in getUserRole() function: ' . $e->getMessage());
        return null;
    }
}

function isSystemAdmin($user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    if (!$user_id) {
        return false;
    }
    
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("
            SELECT r.name 
            FROM user_organizations uo
            LEFT JOIN roles r ON uo.role_id = r.id
            WHERE uo.user_id = ? AND r.name IN ('admin', 'Administrateur')
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result !== false;
    } catch (PDOException $e) {
        error_log('Error in isSystemAdmin() function: ' . $e->getMessage());
        return false;
    }
}

function canDoEnhanced($event_id, $user_id, $permission_name) {
    if (canDo($event_id, $user_id, $permission_name)) {
        return true;
    }
    
    return hasRolePermission($user_id, $permission_name, 'event');
}

function getUserRolePermissions($user_id) {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("
            SELECT rp.permission_name, rp.granted 
            FROM user_organizations uo
            LEFT JOIN roles r ON uo.role_id = r.id
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            WHERE uo.user_id = ? AND rp.permission_name != 'scope'
        ");
        $stmt->execute([$user_id]);
        $permissions = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[$row['permission_name']] = (bool)$row['granted'];
        }
        
        return $permissions;
    } catch (PDOException $e) {
        error_log('Error in getUserRolePermissions() function: ' . $e->getMessage());
        return [];
    }
}

?>
