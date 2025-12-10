<?php
/**
 * Role-Based Access Control Helper
 * Provides functions to check user roles and enforce access control
 */

require_once 'database.php';

/**
 * Check if user is logged in
 * Redirects to login if not
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Check if user is an organizer or admin for any event
 * Returns true if user has at least one organizer/admin role
 */
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

/**
 * Check if user is an admin for a specific event
 * Returns true if user is admin of the event
 */
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

/**
 * Check if user has organizer or admin role for a specific event
 * Returns true if user is organizer or admin of the event
 */
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

/**
 * Get user's role for a specific event
 * Returns 'admin', 'organizer', or null
 */
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

/**
 * Require organizer/admin role for a specific event
 * Redirects to organizer dashboard if user doesn't have permission
 */
function requireEventOrganizer($user_id, $event_id) {
    if (!isEventOrganizer($user_id, $event_id)) {
        header('Location: organizer_dashboard.php');
        exit();
    }
}

/**
 * Require admin role for a specific event
 * Redirects to organizer dashboard if user is not admin
 */
function requireEventAdmin($user_id, $event_id) {
    if (!isEventAdmin($user_id, $event_id)) {
        header('Location: organizer_dashboard.php');
        exit();
    }
}

/**
 * Require organizer/admin role for any event
 * Redirects to dashboard if user is not an organizer
 */
function requireOrganizer() {
    requireLogin();
    if (!isOrganizer($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit();
    }
}

/**
 * Check if a user has a specific permission for an event.
 *
 * @param int $event_id The ID of the event.
 * @param int $user_id The ID of the user.
 * @param string $permission_name The name of the permission to check.
 * @return bool True if the user has the permission, false otherwise.
 */
function canDo($event_id, $user_id, $permission_name) {
    try {
        $pdo = getDatabaseConnection();

        // First, check if the user is the event owner (created_by).
        $stmt = $pdo->prepare('SELECT created_by FROM events WHERE id = ?');
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();

        if ($event && $event['created_by'] == $user_id) {
            return true; // Event owner has all permissions.
        }

        // If not the owner, check the event_permissions table.
        $stmt = $pdo->prepare(
            'SELECT is_allowed FROM event_permissions WHERE event_id = ? AND user_id = ? AND permission_name = ?'
        );
        $stmt->execute([$event_id, $user_id, $permission_name]);
        $permission = $stmt->fetch();

        if ($permission) {
            return (bool)$permission['is_allowed'];
        }

        // Default to not allowed if no specific permission is set.
        return false;

    } catch (PDOException $e) {
        error_log('Error in canDo() function: ' . $e->getMessage());
        return false; // Fail safely
    }
}

/**
 * Check if a user has a specific permission based on their role.
 * This function checks the role-based permissions system.
 *
 * @param int $user_id The ID of the user.
 * @param string $permission_name The name of the permission to check.
 * @param string $context The context (global, department, event).
 * @return bool True if the user has the permission, false otherwise.
 */
function hasRolePermission($user_id, $permission_name, $context = 'event') {
    try {
        $pdo = getDatabaseConnection();

        // Get user's role from user_organizations table
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
            return false; // No role assigned
        }

        // Check if permission is granted
        if ($result['granted'] === null) {
            return false; // Permission not set for this role
        }

        // Check scope permission
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
        return false; // Fail safely
    }
}

/**
 * Get user's role name
 *
 * @param int $user_id The ID of the user.
 * @return string|null The role name or null if no role.
 */
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

/**
 * Check if user is admin (system-wide)
 *
 * @param int $user_id The ID of the user.
 * @return bool True if user is admin, false otherwise.
 */
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

/**
 * Enhanced canDo function that combines event permissions and role permissions
 *
 * @param int $event_id The ID of the event.
 * @param int $user_id The ID of the user.
 * @param string $permission_name The name of the permission to check.
 * @return bool True if the user has the permission, false otherwise.
 */
function canDoEnhanced($event_id, $user_id, $permission_name) {
    // First check event-specific permissions (existing logic)
    if (canDo($event_id, $user_id, $permission_name)) {
        return true;
    }
    
    // Then check role-based permissions
    return hasRolePermission($user_id, $permission_name, 'event');
}

/**
 * Get all permissions for a user's role
 *
 * @param int $user_id The ID of the user.
 * @return array Array of permissions with granted status.
 */
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
