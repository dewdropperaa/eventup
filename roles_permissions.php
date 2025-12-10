<?php
session_start();
require_once 'auth.php';
require_once 'database.php';
require_once 'role_check.php';

// Check if user is logged in and is an admin
requireLogin();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Get current user info
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch roles from database
$roles = [];
try {
    $stmt = getDatabaseConnection()->prepare("
        SELECT r.*, 
               COUNT(uo.user_id) as user_count 
        FROM roles r 
        LEFT JOIN user_organizations uo ON r.id = uo.role_id 
        GROUP BY r.id 
        ORDER BY r.name
    ");
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching roles: " . $e->getMessage());
}

// Fetch permissions for current role
$current_role_permissions = [];
if (isset($_GET['role_id']) && !empty($_GET['role_id'])) {
    try {
        $stmt = getDatabaseConnection()->prepare("
            SELECT permission_name, granted 
            FROM role_permissions 
            WHERE role_id = ?
        ");
        $stmt->execute([$_GET['role_id']]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($permissions as $perm) {
            $current_role_permissions[$perm['permission_name']] = $perm['granted'];
        }
    } catch (Exception $e) {
        error_log("Error fetching role permissions: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rôles & Permissions - EventUp Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="roles_permissions.css">
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                <div class="logo-icon me-2">
                    <img src="EventUp_logo.png" alt="EventUp Logo" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <span class="brand-text">EventUp Admin</span>
            </a>
            
            <div class="d-flex align-items-center ms-auto">
                <div class="search-box me-3 d-none d-lg-block">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control" placeholder="Rechercher...">
                </div>
                
                <div class="notification-btn me-3 position-relative">
                    <i class="bi bi-bell"></i>
                    <span class="notification-badge">3</span>
                </div>
                
                <div class="dropdown">
                    <button class="btn admin-profile-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <div class="admin-avatar me-2"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                        <span class="d-none d-md-inline"><?php echo htmlspecialchars($username); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profil</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Paramètres</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar">
                <div class="sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-grid"></i>
                                <span>Tableau de bord</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-people"></i>
                                <span>Utilisateurs</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-grid-3x3"></i>
                                <span>Catégories</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="resources.php">
                                <i class="bi bi-box"></i>
                                <span>Ressources</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-file-text"></i>
                                <span>Modèles</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="roles_permissions.php">
                                <i class="bi bi-shield-lock"></i>
                                <span>Rôles & Permissions</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-bar-chart"></i>
                                <span>Rapports</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-gear"></i>
                                <span>Paramètres</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="page-title">Rôles & Permissions</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roleModal">
                        <i class="bi bi-plus-circle me-2"></i>Créer un rôle
                    </button>
                </div>

                <div class="row g-4">
                    <!-- Roles List Section -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Liste des rôles</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="role-list">
                                    <?php foreach ($roles as $role): ?>
                                        <div class="role-item <?php echo (isset($_GET['role_id']) && $_GET['role_id'] == $role['id']) ? 'active' : ''; ?>" 
                                             onclick="selectRole(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars($role['name']); ?>')">
                                            <div class="role-icon-wrapper <?php echo htmlspecialchars($role['color_class'] ?? 'orange-gradient'); ?>">
                                                <i class="bi <?php echo htmlspecialchars($role['icon_class'] ?? 'bi-shield-fill-check'); ?>"></i>
                                            </div>
                                            <div class="role-info">
                                                <h6 class="role-name"><?php echo htmlspecialchars($role['name']); ?></h6>
                                                <small class="role-description"><?php echo htmlspecialchars($role['description'] ?? 'Rôle système'); ?></small>
                                            </div>
                                            <span class="badge-users"><?php echo $role['user_count']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Permissions Section -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1" id="selectedRoleName">
                                            <?php echo isset($_GET['role_id']) ? htmlspecialchars($roles[array_search($_GET['role_id'], array_column($roles, 'id'))]['name'] ?? 'Sélectionner un rôle') : 'Sélectionner un rôle'; ?>
                                        </h5>
                                        <small class="text-muted">Configurer les permissions et la portée</small>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-outline-primary me-2" onclick="editRole()" id="editRoleBtn" style="display: none;">
                                            <i class="bi bi-pencil"></i> Modifier
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteRole()" id="deleteRoleBtn" style="display: none;">
                                            <i class="bi bi-trash"></i> Supprimer
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body" id="permissionsContent" style="display: none;">
                                <!-- Scope Configuration -->
                                <div class="scope-section mb-4">
                                    <h6 class="section-title">
                                        <i class="bi bi-globe me-2"></i>Portée des permissions
                                    </h6>
                                    <div class="scope-options">
                                        <label class="scope-option">
                                            <input type="radio" name="scope" value="global" <?php echo (!isset($_GET['role_id']) || empty($current_role_permissions)) ? 'checked' : ''; ?>>
                                            <div class="scope-content">
                                                <i class="bi bi-globe2"></i>
                                                <div>
                                                    <strong>Globale</strong>
                                                    <small>Accès à toute l'organisation</small>
                                                </div>
                                            </div>
                                        </label>

                                        <label class="scope-option">
                                            <input type="radio" name="scope" value="department" <?php echo (isset($current_role_permissions['scope']) && $current_role_permissions['scope'] == 'department') ? 'checked' : ''; ?>>
                                            <div class="scope-content">
                                                <i class="bi bi-building"></i>
                                                <div>
                                                    <strong>Départementale</strong>
                                                    <small>Limité à un département</small>
                                                </div>
                                            </div>
                                        </label>

                                        <label class="scope-option">
                                            <input type="radio" name="scope" value="event" <?php echo (isset($current_role_permissions['scope']) && $current_role_permissions['scope'] == 'event') ? 'checked' : ''; ?>>
                                            <div class="scope-content">
                                                <i class="bi bi-calendar-event"></i>
                                                <div>
                                                    <strong>Par événement</strong>
                                                    <small>Uniquement les événements assignés</small>
                                                </div>
                                            </div>
                                        </label>
                                    </div>

                                    <div id="departmentSelector" class="mt-3" style="display: none;">
                                        <label class="form-label">Sélectionner le département</label>
                                        <select class="form-select">
                                            <option>Ressources Humaines</option>
                                            <option>Marketing</option>
                                            <option>Finance</option>
                                            <option>IT</option>
                                            <option>Opérations</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Permissions Categories -->
                                <div class="permissions-section">
                                    <h6 class="section-title">
                                        <i class="bi bi-key me-2"></i>Permissions détaillées
                                    </h6>

                                    <!-- Events Permissions -->
                                    <div class="permission-category mb-3">
                                        <div class="category-header">
                                            <input class="form-check-input category-check" type="checkbox" id="eventsAll" onchange="toggleCategory('events')">
                                            <label class="category-label" for="eventsAll">
                                                <i class="bi bi-calendar-event"></i>
                                                <span>Événements</span>
                                            </label>
                                        </div>
                                        <div class="permission-items">
                                            <div class="permission-item">
                                                <input class="form-check-input events-permission" type="checkbox" id="eventsCreate" <?php echo (isset($current_role_permissions['events_create']) && $current_role_permissions['events_create']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="eventsCreate">Créer des événements</label>
                                            </div>
                                            <div class="permission-item">
                                                <input class="form-check-input events-permission" type="checkbox" id="eventsEdit" <?php echo (isset($current_role_permissions['events_edit']) && $current_role_permissions['events_edit']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="eventsEdit">Modifier des événements</label>
                                            </div>
                                            <div class="permission-item">
                                                <input class="form-check-input events-permission" type="checkbox" id="eventsDelete" <?php echo (isset($current_role_permissions['events_delete']) && $current_role_permissions['events_delete']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="eventsDelete">Supprimer des événements</label>
                                            </div>
                                            <div class="permission-item">
                                                <input class="form-check-input events-permission" type="checkbox" id="eventsView" <?php echo (isset($current_role_permissions['events_view']) && $current_role_permissions['events_view']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="eventsView">Voir tous les événements</label>
                                            </div>
                                            <div class="permission-item">
                                                <input class="form-check-input events-permission" type="checkbox" id="eventsPublish" <?php echo (isset($current_role_permissions['events_publish']) && $current_role_permissions['events_publish']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="eventsPublish">Publier des événements</label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Users Permissions -->
                                    <div class="permission-category mb-3">
                                        <div class="category-header">
                                            <input class="form-check-input category-check" type="checkbox" id="usersAll" onchange="toggleCategory('users')">
                                            <label class="category-label" for="usersAll">
                                                <i class="bi bi-people"></i>
                                                <span>Utilisateurs</span>
                                            </label>
                                        </div>
                                        <div class="permission-items">
                                            <div class="permission-item">
                                                <input class="form-check-input users-permission" type="checkbox" id="usersCreate" <?php echo (isset($current_role_permissions['users_create']) && $current_role_permissions['users_create']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="usersCreate">Créer des utilisateurs</label>
                                            </div>
                                            <div class="permission-item">
                                                <input class="form-check-input users-permission" type="checkbox" id="usersEdit" <?php echo (isset($current_role_permissions['users_edit']) && $current_role_permissions['users_edit']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="usersEdit">Modifier des utilisateurs</label>
                                            </div>
                                            <div class="permission-item">
                                                <input class="form-check-input users-permission" type="checkbox" id="usersDelete" <?php echo (isset($current_role_permissions['users_delete']) && $current_role_permissions['users_delete']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="usersDelete">Supprimer des utilisateurs</label>
                                            </div>
                                            <div class="permission-item">
                                                <input class="form-check-input users-permission" type="checkbox" id="usersView" <?php echo (isset($current_role_permissions['users_view']) && $current_role_permissions['users_view']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="usersView">Voir tous les utilisateurs</label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Resources Permissions -->
                                    <div class="permission-category mb-3">
                                        <div class="category-header">
                                            <input class="form-check-input category-check" type="checkbox" id="resourcesAll" onchange="toggleCategory('resources')">
                                            <label class="category-label" for="resourcesAll">
                                                <i class="bi bi-box"></i>
                                                <span>Ressources</span>
                                            </label>
                                        </div>
                                        <div class="permission-items">
                                            <div class="permission-item">
                                                <input class="form-check-input resources-permission" type="checkbox" id="resourcesManage" <?php echo (isset($current_role_permissions['resources_manage']) && $current_role_permissions['resources_manage']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="resourcesManage">Gérer les ressources</label>
                                            </div>
                                            <div class="permission-item">
                                                <input class="form-check-input resources-permission" type="checkbox" id="resourcesReserve" <?php echo (isset($current_role_permissions['resources_reserve']) && $current_role_permissions['resources_reserve']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="resourcesReserve">Réserver des ressources</label>
                                            </div>
                                            <div class="permission-item">
                                                <input class="form-check-input resources-permission" type="checkbox" id="resourcesApprove" <?php echo (isset($current_role_permissions['resources_approve']) && $current_role_permissions['resources_approve']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="resourcesApprove">Approuver les réservations</label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Reports Permissions -->
                                    <div class="permission-category mb-3">
                                        <div class="category-header">
                                            <input class="form-check-input category-check" type="checkbox" id="reportsAll" onchange="toggleCategory('reports')">
                                            <label class="category-label" for="reportsAll">
                                                <i class="bi bi-bar-chart"></i>
                                                <span>Rapports</span>
                                            </label>
                                        </div>
                                        <div class="permission-items">
                                            <div class="permission-item">
                                                <input class="form-check-input reports-permission" type="checkbox" id="reportsView" <?php echo (isset($current_role_permissions['reports_view']) && $current_role_permissions['reports_view']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="reportsView">Voir les rapports</label>
                                            </div>
                                            <div class="permission-item">
                                                <input class="form-check-input reports-permission" type="checkbox" id="reportsExport" <?php echo (isset($current_role_permissions['reports_export']) && $current_role_permissions['reports_export']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="reportsExport">Exporter les rapports</label>
                                            </div>
                                            <div class="permission-item">
                                                <input class="form-check-input reports-permission" type="checkbox" id="reportsCreate" <?php echo (isset($current_role_permissions['reports_create']) && $current_role_permissions['reports_create']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="reportsCreate">Créer des rapports personnalisés</label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Settings Permissions -->
                                    <div class="permission-category mb-3">
                                        <div class="category-header">
                                            <input class="form-check-input category-check" type="checkbox" id="settingsAll" onchange="toggleCategory('settings')">
                                            <label class="category-label" for="settingsAll">
                                                <i class="bi bi-gear"></i>
                                                <span>Paramètres</span>
                                            </label>
                                        </div>
                                        <div class="permission-items">
                                            <div class="permission-item">
                                                <input class="form-check-input settings-permission" type="checkbox" id="settingsView" <?php echo (isset($current_role_permissions['settings_view']) && $current_role_permissions['settings_view']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="settingsView">Voir les paramètres</label>
                                            </div>
                                            <div class="permission-item">
                                                <input class="form-check-input settings-permission" type="checkbox" id="settingsModify" <?php echo (isset($current_role_permissions['settings_modify']) && $current_role_permissions['settings_modify']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="settingsModify">Modifier les paramètres</label>
                                            </div>
                                            <div class="permission-item">
                                                <input class="form-check-input settings-permission" type="checkbox" id="settingsRoles" <?php echo (isset($current_role_permissions['settings_roles']) && $current_role_permissions['settings_roles']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="settingsRoles">Gérer les rôles et permissions</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button class="btn btn-primary" onclick="savePermissions()">
                                        <i class="bi bi-check-circle me-2"></i>Enregistrer les modifications
                                    </button>
                                    <button class="btn btn-outline-primary ms-2" onclick="resetPermissions()">
                                        Réinitialiser
                                    </button>
                                </div>
                            </div>
                            
                            <div class="card-body text-center text-muted" id="noRoleSelected">
                                <i class="bi bi-shield-lock" style="font-size: 48px; opacity: 0.3;"></i>
                                <p class="mt-3">Sélectionnez un rôle pour configurer ses permissions</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Role Modal -->
    <div class="modal fade" id="roleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Créer un nouveau rôle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="roleForm">
                        <div class="mb-3">
                            <label for="roleName" class="form-label">Nom du rôle</label>
                            <input type="text" class="form-control" id="roleName" required>
                        </div>
                        <div class="mb-3">
                            <label for="roleDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="roleDescription" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="roleColor" class="form-label">Couleur de l'icône</label>
                            <select class="form-select" id="roleColor">
                                <option value="orange-gradient">Orange</option>
                                <option value="teal-gradient">Vert</option>
                                <option value="blue-gradient">Bleu</option>
                                <option value="yellow-gradient">Jaune</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="roleIcon" class="form-label">Icône</label>
                            <select class="form-select" id="roleIcon">
                                <option value="bi-shield-fill-check">Bouclier</option>
                                <option value="bi-person-gear">Personnage + Engrenage</option>
                                <option value="bi-calendar-event">Calendrier</option>
                                <option value="bi-building">Bâtiment</option>
                                <option value="bi-person">Personne</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" onclick="createRole()">Créer le rôle</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentRoleId = null;

        // Scope selector logic
        document.querySelectorAll('input[name="scope"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const departmentSelector = document.getElementById('departmentSelector');
                if (this.value === 'department') {
                    departmentSelector.style.display = 'block';
                } else {
                    departmentSelector.style.display = 'none';
                }
            });
        });

        function selectRole(id, name) {
            currentRoleId = id;
            
            // Update active state
            document.querySelectorAll('.role-item').forEach(item => {
                item.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Update UI
            document.getElementById('selectedRoleName').textContent = name;
            document.getElementById('permissionsContent').style.display = 'block';
            document.getElementById('noRoleSelected').style.display = 'none';
            document.getElementById('editRoleBtn').style.display = 'inline-block';
            document.getElementById('deleteRoleBtn').style.display = 'inline-block';
            
            // Load role permissions via AJAX
            loadRolePermissions(id);
        }

        function loadRolePermissions(roleId) {
            fetch(`get_role_permissions.php?role_id=${roleId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update scope
                        if (data.scope) {
                            document.querySelector(`input[name="scope"][value="${data.scope}"]`).checked = true;
                        }
                        
                        // Update permissions
                        Object.keys(data.permissions).forEach(perm => {
                            const checkbox = document.getElementById(perm);
                            if (checkbox) {
                                checkbox.checked = data.permissions[perm];
                            }
                        });
                        
                        // Update category checkboxes
                        ['events', 'users', 'resources', 'reports', 'settings'].forEach(category => {
                            updateCategoryCheckbox(category);
                        });
                    }
                })
                .catch(error => console.error('Error loading permissions:', error));
        }

        function toggleCategory(category) {
            const categoryCheck = document.getElementById(category + 'All');
            const permissions = document.querySelectorAll('.' + category + '-permission');
            
            permissions.forEach(permission => {
                permission.checked = categoryCheck.checked;
            });
        }

        function updateCategoryCheckbox(category) {
            const categoryCheck = document.getElementById(category + 'All');
            const permissions = document.querySelectorAll('.' + category + '-permission');
            const allChecked = Array.from(permissions).every(p => p.checked);
            if (categoryCheck) categoryCheck.checked = allChecked;
        }

        // Update category checkbox when individual permissions change
        document.querySelectorAll('.permission-items input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const category = this.className.split('-')[0].replace('form-check-input ', '');
                updateCategoryCheckbox(category);
            });
        });

        function editRole() {
            if (!currentRoleId) return;
            
            // Load role data for editing
            fetch(`get_role.php?role_id=${currentRoleId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('roleName').value = data.role.name;
                        document.getElementById('roleDescription').value = data.role.description || '';
                        document.getElementById('roleColor').value = data.role.color_class || 'orange-gradient';
                        document.getElementById('roleIcon').value = data.role.icon_class || 'bi-shield-fill-check';
                        
                        document.querySelector('#roleModal .modal-title').textContent = 'Modifier le rôle';
                        const modal = new bootstrap.Modal(document.getElementById('roleModal'));
                        modal.show();
                    }
                })
                .catch(error => console.error('Error loading role:', error));
        }

        function deleteRole() {
            if (!currentRoleId) return;
            
            if (confirm('Êtes-vous sûr de vouloir supprimer ce rôle ?')) {
                fetch('delete_role.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ role_id: currentRoleId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Rôle supprimé avec succès');
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error deleting role:', error);
                    alert('Erreur lors de la suppression du rôle');
                });
            }
        }

        function createRole() {
            const formData = {
                name: document.getElementById('roleName').value,
                description: document.getElementById('roleDescription').value,
                color_class: document.getElementById('roleColor').value,
                icon_class: document.getElementById('roleIcon').value
            };
            
            fetch('create_role.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Rôle créé avec succès');
                    const modal = bootstrap.Modal.getInstance(document.getElementById('roleModal'));
                    modal.hide();
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error creating role:', error);
                alert('Erreur lors de la création du rôle');
            });
        }

        function savePermissions() {
            if (!currentRoleId) return;
            
            // Collect all permissions
            const permissions = {};
            document.querySelectorAll('.permission-items input[type="checkbox"]').forEach(checkbox => {
                permissions[checkbox.id] = checkbox.checked;
            });
            
            // Add scope
            const scope = document.querySelector('input[name="scope"]:checked').value;
            
            const data = {
                role_id: currentRoleId,
                scope: scope,
                permissions: permissions
            };
            
            fetch('save_role_permissions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Permissions enregistrées avec succès');
                } else {
                    alert('Erreur: ' + result.message);
                }
            })
            .catch(error => {
                console.error('Error saving permissions:', error);
                alert('Erreur lors de l\'enregistrement des permissions');
            });
        }

        function resetPermissions() {
            if (confirm('Réinitialiser toutes les permissions ?')) {
                location.reload();
            }
        }

        // Initialize category checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            ['events', 'users', 'resources', 'reports', 'settings'].forEach(category => {
                updateCategoryCheckbox(category);
            });
        });
    </script>
</body>
</html>
