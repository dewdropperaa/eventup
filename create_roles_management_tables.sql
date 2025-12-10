-- ========================================
-- Roles Management System Database Schema
-- ========================================

-- Create roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    color_class VARCHAR(50) DEFAULT 'orange-gradient',
    icon_class VARCHAR(50) DEFAULT 'bi-shield-fill-check',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create role_permissions table
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_name VARCHAR(100) NOT NULL,
    granted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_name),
    INDEX idx_permission_name (permission_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_organizations table (if not exists)
CREATE TABLE IF NOT EXISTS user_organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    organization_id INT DEFAULT 1,
    role_id INT,
    department VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_org (user_id, organization_id),
    INDEX idx_user_id (user_id),
    INDEX idx_role_id (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Insert Default Roles
-- ========================================

-- Insert default roles
INSERT INTO roles (name, description, color_class, icon_class) VALUES
('Administrateur', 'Accès complet au système', 'orange-gradient', 'bi-shield-fill-check'),
('Gestionnaire', 'Gestion des événements', 'teal-gradient', 'bi-person-gear'),
('Organisateur', 'Création d''événements', 'blue-gradient', 'bi-calendar-event'),
('Chef de département', 'Portée départementale', 'yellow-gradient', 'bi-building'),
('Participant', 'Lecture seule', 'bg-secondary-gradient', 'bi-person')
ON DUPLICATE KEY UPDATE 
    description = VALUES(description),
    color_class = VALUES(color_class),
    icon_class = VALUES(icon_class);

-- ========================================
-- Insert Default Permissions for Each Role
-- ========================================

-- Administrateur - Full permissions
INSERT INTO role_permissions (role_id, permission_name, granted) 
SELECT r.id, p.permission_name, p.granted
FROM roles r, 
(
    SELECT 'events_create' as permission_name, 1 as granted UNION ALL
    SELECT 'events_edit', 1 UNION ALL
    SELECT 'events_delete', 1 UNION ALL
    SELECT 'events_view', 1 UNION ALL
    SELECT 'events_publish', 1 UNION ALL
    SELECT 'users_create', 1 UNION ALL
    SELECT 'users_edit', 1 UNION ALL
    SELECT 'users_delete', 1 UNION ALL
    SELECT 'users_view', 1 UNION ALL
    SELECT 'resources_manage', 1 UNION ALL
    SELECT 'resources_reserve', 1 UNION ALL
    SELECT 'resources_approve', 1 UNION ALL
    SELECT 'reports_view', 1 UNION ALL
    SELECT 'reports_export', 1 UNION ALL
    SELECT 'reports_create', 1 UNION ALL
    SELECT 'settings_view', 1 UNION ALL
    SELECT 'settings_modify', 1 UNION ALL
    SELECT 'settings_roles', 1 UNION ALL
    SELECT 'scope', 'global'
) p
WHERE r.name = 'Administrateur'
ON DUPLICATE KEY UPDATE granted = VALUES(granted);

-- Gestionnaire - Most permissions except settings
INSERT INTO role_permissions (role_id, permission_name, granted) 
SELECT r.id, p.permission_name, p.granted
FROM roles r, 
(
    SELECT 'events_create' as permission_name, 1 as granted UNION ALL
    SELECT 'events_edit', 1 UNION ALL
    SELECT 'events_delete', 1 UNION ALL
    SELECT 'events_view', 1 UNION ALL
    SELECT 'events_publish', 1 UNION ALL
    SELECT 'users_create', 0 UNION ALL
    SELECT 'users_edit', 0 UNION ALL
    SELECT 'users_delete', 0 UNION ALL
    SELECT 'users_view', 1 UNION ALL
    SELECT 'resources_manage', 1 UNION ALL
    SELECT 'resources_reserve', 1 UNION ALL
    SELECT 'resources_approve', 1 UNION ALL
    SELECT 'reports_view', 1 UNION ALL
    SELECT 'reports_export', 1 UNION ALL
    SELECT 'reports_create', 1 UNION ALL
    SELECT 'settings_view', 0 UNION ALL
    SELECT 'settings_modify', 0 UNION ALL
    SELECT 'settings_roles', 0 UNION ALL
    SELECT 'scope', 'department'
) p
WHERE r.name = 'Gestionnaire'
ON DUPLICATE KEY UPDATE granted = VALUES(granted);

-- Organisateur - Event management permissions
INSERT INTO role_permissions (role_id, permission_name, granted) 
SELECT r.id, p.permission_name, p.granted
FROM roles r, 
(
    SELECT 'events_create' as permission_name, 1 as granted UNION ALL
    SELECT 'events_edit', 1 UNION ALL
    SELECT 'events_delete', 0 UNION ALL
    SELECT 'events_view', 1 UNION ALL
    SELECT 'events_publish', 1 UNION ALL
    SELECT 'users_create', 0 UNION ALL
    SELECT 'users_edit', 0 UNION ALL
    SELECT 'users_delete', 0 UNION ALL
    SELECT 'users_view', 0 UNION ALL
    SELECT 'resources_manage', 0 UNION ALL
    SELECT 'resources_reserve', 1 UNION ALL
    SELECT 'resources_approve', 0 UNION ALL
    SELECT 'reports_view', 0 UNION ALL
    SELECT 'reports_export', 0 UNION ALL
    SELECT 'reports_create', 0 UNION ALL
    SELECT 'settings_view', 0 UNION ALL
    SELECT 'settings_modify', 0 UNION ALL
    SELECT 'settings_roles', 0 UNION ALL
    SELECT 'scope', 'event'
) p
WHERE r.name = 'Organisateur'
ON DUPLICATE KEY UPDATE granted = VALUES(granted);

-- Chef de département - Department-level permissions
INSERT INTO role_permissions (role_id, permission_name, granted) 
SELECT r.id, p.permission_name, p.granted
FROM roles r, 
(
    SELECT 'events_create' as permission_name, 1 as granted UNION ALL
    SELECT 'events_edit', 1 UNION ALL
    SELECT 'events_delete', 0 UNION ALL
    SELECT 'events_view', 1 UNION ALL
    SELECT 'events_publish', 1 UNION ALL
    SELECT 'users_create', 0 UNION ALL
    SELECT 'users_edit', 0 UNION ALL
    SELECT 'users_delete', 0 UNION ALL
    SELECT 'users_view', 1 UNION ALL
    SELECT 'resources_manage', 1 UNION ALL
    SELECT 'resources_reserve', 1 UNION ALL
    SELECT 'resources_approve', 1 UNION ALL
    SELECT 'reports_view', 1 UNION ALL
    SELECT 'reports_export', 1 UNION ALL
    SELECT 'reports_create', 0 UNION ALL
    SELECT 'settings_view', 0 UNION ALL
    SELECT 'settings_modify', 0 UNION ALL
    SELECT 'settings_roles', 0 UNION ALL
    SELECT 'scope', 'department'
) p
WHERE r.name = 'Chef de département'
ON DUPLICATE KEY UPDATE granted = VALUES(granted);

-- Participant - View-only permissions
INSERT INTO role_permissions (role_id, permission_name, granted) 
SELECT r.id, p.permission_name, p.granted
FROM roles r, 
(
    SELECT 'events_create' as permission_name, 0 as granted UNION ALL
    SELECT 'events_edit', 0 UNION ALL
    SELECT 'events_delete', 0 UNION ALL
    SELECT 'events_view', 1 UNION ALL
    SELECT 'events_publish', 0 UNION ALL
    SELECT 'users_create', 0 UNION ALL
    SELECT 'users_edit', 0 UNION ALL
    SELECT 'users_delete', 0 UNION ALL
    SELECT 'users_view', 0 UNION ALL
    SELECT 'resources_manage', 0 UNION ALL
    SELECT 'resources_reserve', 1 UNION ALL
    SELECT 'resources_approve', 0 UNION ALL
    SELECT 'reports_view', 0 UNION ALL
    SELECT 'reports_export', 0 UNION ALL
    SELECT 'reports_create', 0 UNION ALL
    SELECT 'settings_view', 0 UNION ALL
    SELECT 'settings_modify', 0 UNION ALL
    SELECT 'settings_roles', 0 UNION ALL
    SELECT 'scope', 'event'
) p
WHERE r.name = 'Participant'
ON DUPLICATE KEY UPDATE granted = VALUES(granted);

-- ========================================
-- Create Indexes for Performance
-- ========================================

-- Create composite indexes for common queries
CREATE INDEX idx_role_permissions_lookup ON role_permissions(role_id, permission_name, granted);
CREATE INDEX idx_user_org_role ON user_organizations(user_id, role_id);

-- ========================================
-- Update existing users to have default roles
-- ========================================

-- Assign admin role to existing admin users (if role column exists in users table)
UPDATE user_organizations uo 
SET uo.role_id = (SELECT id FROM roles WHERE name = 'Administrateur' LIMIT 1)
WHERE uo.user_id IN (
    SELECT u.id FROM users u WHERE u.role = 'admin'
) AND uo.role_id IS NULL;

-- Assign organizer role to existing organizer users
UPDATE user_organizations uo 
SET uo.role_id = (SELECT id FROM roles WHERE name = 'Organisateur' LIMIT 1)
WHERE uo.user_id IN (
    SELECT u.id FROM users u WHERE u.role = 'organizer'
) AND uo.role_id IS NULL;

-- Assign participant role to other users
UPDATE user_organizations uo 
SET uo.role_id = (SELECT id FROM roles WHERE name = 'Participant' LIMIT 1)
WHERE uo.role_id IS NULL;

-- ========================================
-- Add triggers for audit trail (optional)
-- ========================================

DELIMITER //

CREATE TRIGGER roles_audit_insert 
AFTER INSERT ON roles
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, action, record_id, old_values, new_values, user_id, timestamp)
    VALUES ('roles', 'INSERT', NEW.id, NULL, JSON_OBJECT(
        'name', NEW.name,
        'description', NEW.description,
        'color_class', NEW.color_class,
        'icon_class', NEW.icon_class
    ), @current_user_id, NOW());
END//

CREATE TRIGGER roles_audit_update 
AFTER UPDATE ON roles
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, action, record_id, old_values, new_values, user_id, timestamp)
    VALUES ('roles', 'UPDATE', NEW.id, JSON_OBJECT(
        'name', OLD.name,
        'description', OLD.description,
        'color_class', OLD.color_class,
        'icon_class', OLD.icon_class
    ), JSON_OBJECT(
        'name', NEW.name,
        'description', NEW.description,
        'color_class', NEW.color_class,
        'icon_class', NEW.icon_class
    ), @current_user_id, NOW());
END//

DELIMITER ;

-- ========================================
-- Summary
-- ========================================

-- This script creates:
-- 1. roles table - stores role definitions
-- 2. role_permissions table - stores granular permissions for each role
-- 3. user_organizations table - links users to roles (if not exists)
-- 4. Default roles with appropriate permissions
-- 5. Performance indexes
-- 6. Audit triggers (optional)

-- The system supports:
-- - Global, departmental, and event-level scopes
-- - Granular permissions for events, users, resources, reports, and settings
-- - Role-based access control
-- - Audit trail for role changes
