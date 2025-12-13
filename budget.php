<?php
session_start();

require 'database.php';
require_once 'role_check.php';
require_once 'notifications.php';

requireLogin();

$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

if ($eventId <= 0) {
    header('Location: index.php');
    exit;
}

$event = null;
try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare('SELECT id, titre, created_by FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event || ($event['created_by'] != $_SESSION['user_id'] && !canDo($eventId, $_SESSION['user_id'], 'can_edit_budget'))) {
        header('Location: event_details.php?id=' . $eventId);
        exit;
    }
} catch (Exception $e) {
    error_log('Error checking event owner: ' . $e->getMessage());
    header('Location: event_details.php?id=' . $eventId);
    exit;
}

$pdo = getDatabaseConnection();

$error = '';
$success = '';

$unreadCount = 0;
try {
    $unreadNotifications = getUnreadNotifications($_SESSION['user_id']);
    $unreadCount = count($unreadNotifications);
} catch (Exception $e) {
    error_log('Error getting notifications: ' . $e->getMessage());
    $unreadCount = 0;
}

try {
    $stmt = $pdo->prepare('SELECT id, titre, date, created_by FROM events WHERE id = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event) {
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    error_log('Error fetching event: ' . $e->getMessage());
    $error = 'Error loading event.';
}

$isEventOwner = isset($_SESSION['user_id']) && $event && $_SESSION['user_id'] == $event['created_by'];
$isEventAdmin = isEventAdmin($_SESSION['user_id'], $eventId);
$isEventOrganizer = isEventOrganizer($_SESSION['user_id'], $eventId);

try {
    $stmt = $pdo->prepare('SELECT budget_limit, currency FROM event_budget_settings WHERE event_id = ?');
    $stmt->execute([$eventId]);
    $budget_settings = $stmt->fetch();
    
    if (!$budget_settings) {
        $budget_settings = ['budget_limit' => 0, 'currency' => 'MAD'];
    }
    
    $stmt = $pdo->prepare('
        SELECT id, category, title, amount, date, notes 
        FROM event_expenses 
        WHERE event_id = ? 
        ORDER BY date DESC
    ');
    $stmt->execute([$eventId]);
    $expenses = $stmt->fetchAll();
    
    $stmt = $pdo->prepare('
        SELECT id, source, title, amount, date, notes 
        FROM event_incomes 
        WHERE event_id = ? 
        ORDER BY date DESC
    ');
    $stmt->execute([$eventId]);
    $incomes = $stmt->fetchAll();
    
    $total_expenses = array_sum(array_column($expenses, 'amount'));
    $total_incomes = array_sum(array_column($incomes, 'amount'));
    $balance = $total_incomes - $total_expenses;
    $budget_limit = $budget_settings['budget_limit'];
    $currency = $budget_settings['currency'];
    
    $category_breakdown = [];
    foreach ($expenses as $expense) {
        if (!isset($category_breakdown[$expense['category']])) {
            $category_breakdown[$expense['category']] = 0;
        }
        $category_breakdown[$expense['category']] += $expense['amount'];
    }
    
} catch (PDOException $e) {
    error_log('Error loading budget: ' . $e->getMessage());
    die('Error loading budget data');
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventUp - Gestion du Budget</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <style>
        /* ===========================
   Custom CSS Variables
   ============================ */
:root {
    --primary-orange: #D94A00;
    --primary-teal: #1B5E52;
    --light-orange: #ff6b2c;
    --light-teal: #267061;
    --warning-yellow: #FFD700;
    --info-blue: #4A90E2;
    --success-green: #2ed573;
    --text-dark: #2c3e50;
    --text-muted: #657786;
    --bg-light: #f5f7fa;
    --border-color: #e1e8ed;
}


/* ===========================
   Base Styles
   ============================ */
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background-color: var(--bg-light);
    color: var(--text-dark);
    padding-top: 76px;
}


/* ===========================
   Navbar Styles
   ============================ */
.navbar {
    height: 76px;
    backdrop-filter: blur(10px);
    background-color: rgba(255, 255, 255, 0.98) !important;
    border-bottom: 1px solid var(--border-color);
}


.logo-icon {
    width: 92px;
    height: 92px;
    background: transparent;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 22px;
}


.brand-text {
    font-weight: 700;
    font-size: 20px;
    color: var(--primary-teal);
    letter-spacing: -0.5px;
}




.notification-btn {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-light);
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 20px;
    color: var(--text-dark);
}


.notification-btn:hover {
    background: var(--primary-orange);
    color: white;
    transform: scale(1.05);
}


.notification-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: var(--primary-orange);
    color: white;
    font-size: 11px;
    font-weight: 700;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
}


.admin-profile-btn {
    display: flex;
    align-items: center;
    background: transparent;
    border: none;
    padding: 6px 12px;
    border-radius: 10px;
    transition: all 0.3s ease;
}


.admin-profile-btn:hover {
    background: var(--bg-light);
}


.admin-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
}


/* ===========================
   Sidebar Styles
   ============================ */
.sidebar {
    position: fixed;
    top: 76px;
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 24px 0;
    background: white;
    border-right: 1px solid var(--border-color);
    overflow-y: auto;
}


.sidebar-sticky {
    position: sticky;
    top: 0;
}


.sidebar .nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 24px;
    color: var(--text-muted);
    text-decoration: none;
    transition: all 0.3s ease;
    font-weight: 500;
    border-left: 3px solid transparent;
    margin: 4px 0;
}


.sidebar .nav-link i {
    font-size: 20px;
    width: 24px;
}


.sidebar .nav-link:hover {
    background: linear-gradient(90deg, rgba(217, 74, 0, 0.05), transparent);
    color: var(--primary-orange);
}


.sidebar .nav-link.active {
    background: linear-gradient(90deg, rgba(217, 74, 0, 0.1), transparent);
    color: var(--primary-orange);
    border-left-color: var(--primary-orange);
}


/* ===========================
   Main Content
   ============================ */
.main-content {
    padding-top: 32px;
    padding-bottom: 48px;
}


.page-title {
    font-size: 32px;
    font-weight: 700;
    color: var(--primary-teal);
    margin-bottom: 0;
}


/* ===========================
   Tabs
   ============================ */
.nav-tabs {
    border-bottom: 2px solid var(--border-color);
    margin-bottom: 32px;
}


.nav-tabs .nav-link {
    color: var(--text-muted);
    border: none;
    border-bottom: 3px solid transparent;
    padding: 12px 24px;
    font-weight: 600;
    transition: all 0.3s ease;
    background: transparent;
}


.nav-tabs .nav-link:hover {
    color: var(--primary-orange);
    border-color: transparent;
    background: transparent;
}


.nav-tabs .nav-link.active {
    color: var(--primary-orange);
    border-color: transparent transparent var(--primary-orange) transparent;
    background-color: transparent;
}


.nav-tabs .nav-link i {
    font-size: 18px;
}


/* ===========================
   Buttons
   ============================ */
.btn-primary {
    background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}


.btn-primary:hover {
    background: linear-gradient(135deg, var(--light-orange), var(--primary-orange));
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(217, 74, 0, 0.3);
}


.btn-success {
    background: linear-gradient(135deg, var(--success-green), #26d0ce);
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}


.btn-success:hover {
    background: linear-gradient(135deg, #26d0ce, var(--success-green));
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(46, 213, 115, 0.3);
}


.btn-danger {
    background: linear-gradient(135deg, #dc3545, #c82333);
    border: none;
    padding: 8px 16px;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
}


.btn-danger:hover {
    background: linear-gradient(135deg, #c82333, #dc3545);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(220, 53, 69, 0.3);
}


.btn-outline-primary {
    color: var(--primary-orange);
    border-color: var(--primary-orange);
    border-radius: 10px;
    padding: 8px 16px;
    font-weight: 600;
    transition: all 0.3s ease;
}


.btn-outline-primary:hover {
    background: var(--primary-orange);
    border-color: var(--primary-orange);
    color: white;
}


.btn-sm {
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
}


/* ===========================
   Budget Cards
   ============================ */
.budget-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    transition: all 0.3s ease;
    border: 1px solid var(--border-color);
    height: 100%;
    position: relative;
    overflow: hidden;
}


.budget-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
}


.budget-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-orange), var(--light-orange));
}


.budget-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 26px;
    margin-bottom: 16px;
}


.income-gradient {
    background: linear-gradient(135deg, var(--success-green), #26d0ce);
}


.expense-gradient {
    background: linear-gradient(135deg, #dc3545, #c82333);
}


.balance-gradient {
    background: linear-gradient(135deg, var(--primary-teal), var(--light-teal));
}


.budget-gradient {
    background: linear-gradient(135deg, var(--primary-orange), var(--light-orange));
}


.budget-title {
    font-size: 14px;
    color: var(--text-muted);
    margin-bottom: 8px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}


.budget-amount {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 8px;
}


.budget-subtitle {
    font-size: 12px;
    color: var(--text-muted);
}


/* ===========================
   Badges
   ============================ */
.badge {
    padding: 6px 14px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 12px;
}


.badge-success {
    background: rgba(46, 213, 115, 0.15);
    color: #2ed573;
}


.badge-warning {
    background: rgba(255, 215, 0, 0.2);
    color: #d4a000;
}


.badge-danger {
    background: rgba(220, 53, 69, 0.15);
    color: #dc3545;
}


.badge-info {
    background: rgba(74, 144, 226, 0.15);
    color: var(--info-blue);
}


.badge-secondary {
    background: rgba(108, 117, 125, 0.15);
    color: #6c757d;
}


/* ===========================
   Cards
   ============================ */
.card {
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    transition: all 0.3s ease;
}


.card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}


.card-header {
    background: white;
    border-bottom: 1px solid var(--border-color);
    padding: 20px 24px;
    border-radius: 16px 16px 0 0 !important;
}


.card-header h5 {
    font-weight: 700;
    color: var(--primary-teal);
}


/* ===========================
   Table Styles
   ============================ */
.table {
    margin-bottom: 0;
}


.table thead th {
    background: var(--bg-light);
    color: var(--primary-teal);
    font-weight: 700;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border-color);
    padding: 16px;
}


.table tbody td {
    padding: 16px;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-color);
}


.table tbody tr {
    transition: all 0.3s ease;
}


.table tbody tr:hover {
    background: var(--bg-light);
    transform: scale(1.01);
}


.table tbody td i.fs-5 {
    font-size: 24px;
}


.text-danger {
    color: #dc3545 !important;
}


.text-primary {
    color: #0d6efd !important;
}


.text-success {
    color: #198754 !important;
}


.text-info {
    color: #0dcaf0 !important;
}


/* ===========================
   Modal
   ============================ */
.modal-content {
    border: none;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
}


.modal-header {
    background: var(--bg-light);
    border-bottom: 1px solid var(--border-color);
    border-radius: 16px 16px 0 0;
    padding: 20px 24px;
}


.modal-title {
    color: var(--primary-teal);
    font-weight: 700;
    font-size: 20px;
}


.modal-body {
    padding: 24px;
}


.modal-footer {
    border-top: 1px solid var(--border-color);
    padding: 16px 24px;
}


/* ===========================
   Forms
   ============================ */
.form-label {
    font-weight: 600;
    color: var(--text-dark);
    margin-bottom: 8px;
    font-size: 14px;
}


.form-control,
.form-select {
    border-radius: 10px;
    border: 1px solid var(--border-color);
    padding: 10px 14px;
    transition: all 0.3s ease;
    font-size: 14px;
}


.form-control:focus,
.form-select:focus {
    border-color: var(--primary-orange);
    box-shadow: 0 0 0 3px rgba(217, 74, 0, 0.1);
    outline: none;
}


/* ===========================
   Responsive Design
   ============================ */
@media (max-width: 991px) {
    .sidebar {
        width: 70px;
    }
    
    .sidebar .nav-link span {
        display: none;
    }
    
    .sidebar .nav-link {
        justify-content: center;
        padding: 12px 8px;
    }
}


@media (max-width: 767px) {
    body {
        padding-top: 64px;
    }
    
    .navbar {
        height: 64px;
    }
    
    .sidebar {
        top: 64px;
        width: 60px;
    }
    
        
    .page-title {
        font-size: 24px;
    }


    .nav-tabs .nav-link {
        padding: 10px 16px;
        font-size: 14px;
    }
    
    .budget-icon {
        width: 48px;
        height: 48px;
        font-size: 22px;
    }
    
    .budget-amount {
        font-size: 24px;
    }
}


@media (max-width: 575px) {
    .main-content {
        padding: 16px;
    }
    
    .nav-tabs .nav-link span {
        display: none;
    }
    
    .budget-card .d-flex.gap-2 {
        flex-direction: column;
        gap: 8px !important;
    }
    
    .budget-card .btn-sm {
        width: 100%;
    }


    .btn-primary {
        width: 100%;
        margin-bottom: 8px;
    }
}
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center" href="event_details.php?id=<?php echo $eventId; ?>">
                <div class="logo-icon me-2">
                    <img src="assets/EventUp_logo.png" alt="EventUp Logo" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <span class="brand-text">EventUp</span>
            </a>
            
            <div class="d-flex align-items-center ms-auto">
                <div class="dropdown me-3">
                    <div class="notification-btn position-relative" data-bs-toggle="dropdown" style="cursor: pointer;">
                        <i class="bi bi-bell"></i>
                        <span class="notification-badge" id="notificationCount"><?php echo $unreadCount; ?></span>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end" id="notificationDropdown" style="width: 350px; max-height: 400px; overflow-y: auto;">
                        <li class="dropdown-header d-flex justify-content-between align-items-center">
                            <span>Notifications</span>
                            <a href="#" class="text-decoration-none" onclick="markAllAsRead()">Tout marquer comme lu</a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li id="notificationList">
                            <div class="text-center p-3">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
                
                <div class="dropdown">
                    <button class="btn admin-profile-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <div class="admin-avatar me-2"><?php echo strtoupper(substr($_SESSION['user_nom'] ?? 'A', 0, 1)); ?></div>
                        <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['user_nom'] ?? 'Admin'); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-house me-2"></i>Tableau de bord</a></li>
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
            <?php include 'event_nav.php'; ?>

            <!-- Main Content -->
            <main class="col-lg-9 px-md-4 main-content">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="page-title">Gestion du budget</h1>
                    <div>
                        <small class="text-muted me-2">Événement:</small>
                        <strong><?php echo htmlspecialchars($event['titre']); ?></strong>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="budget-card">
                            <div class="budget-icon income-gradient">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                            <div class="budget-title">Revenus totaux</div>
                            <div class="budget-amount text-success"><?php echo number_format($total_incomes, 2); ?> <?php echo $currency; ?></div>
                            <div class="budget-subtitle">
                                <?php if ($total_incomes > 0): ?>
                                    <i class="bi bi-arrow-up"></i> Positif
                                <?php else: ?>
                                    <i class="bi bi-dash"></i> Aucun revenu
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="budget-card">
                            <div class="budget-icon expense-gradient">
                                <i class="bi bi-credit-card"></i>
                            </div>
                            <div class="budget-title">Dépenses totales</div>
                            <div class="budget-amount text-danger"><?php echo number_format($total_expenses, 2); ?> <?php echo $currency; ?></div>
                            <div class="budget-subtitle">
                                <?php if ($total_expenses > 0): ?>
                                    <i class="bi bi-arrow-down"></i> Sorties
                                <?php else: ?>
                                    <i class="bi bi-dash"></i> Aucune dépense
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="budget-card">
                            <div class="budget-icon balance-gradient">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <div class="budget-title">Solde</div>
                            <div class="budget-amount <?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo number_format($balance, 2); ?> <?php echo $currency; ?>
                            </div>
                            <div class="budget-subtitle">
                                <?php if ($balance >= 0): ?>
                                    <i class="bi bi-check-circle"></i> Équilibré
                                <?php else: ?>
                                    <i class="bi bi-exclamation-triangle"></i> Déficit
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="budget-card">
                            <div class="budget-icon budget-gradient">
                                <i class="bi bi-piggy-bank"></i>
                            </div>
                            <div class="budget-title">Budget limite</div>
                            <div class="budget-amount"><?php echo number_format($budget_limit, 2); ?> <?php echo $currency; ?></div>
                            <div class="budget-subtitle">
                                <?php 
                                if ($budget_limit > 0) {
                                    $percentage = round(($total_expenses / $budget_limit) * 100);
                                    echo '<i class="bi bi-graph-up"></i> ' . $percentage . '% utilisé';
                                } else {
                                    echo '<i class="bi bi-dash"></i> Non défini';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PDF Export Button -->
                <div class="d-flex justify-content-end mb-4">
                    <a href="generate_budget_pdf.php?event_id=<?php echo $eventId; ?>" class="btn btn-danger" target="_blank">
                        <i class="bi bi-file-pdf"></i> Exporter PDF
                    </a>
                </div>

                <!-- Alerts -->
                <?php if ($budget_limit > 0): ?>
                    <?php if ($total_expenses > $budget_limit): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <div class="d-flex align-items-center">
                                <div class="budget-icon expense-gradient me-3" style="width: 40px; height: 40px; font-size: 20px; margin-bottom: 0;">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <strong class="text-danger">Attention :</strong> Votre événement dépasse le budget prévu !
                                    <div class="mt-1">Dépassement de <?php echo number_format($total_expenses - $budget_limit, 2); ?> <?php echo $currency; ?></div>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php elseif ($total_expenses >= ($budget_limit * 0.8)): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <div class="d-flex align-items-center">
                                <div class="budget-icon" style="background: linear-gradient(135deg, var(--warning-yellow), #FFA500); margin-bottom: 0; me-3; width: 40px; height: 40px; font-size: 20px;">
                                    <i class="bi bi-exclamation-circle"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <strong class="text-warning">Attention :</strong> Vous avez atteint <?php echo round(($total_expenses / $budget_limit) * 100); ?>% du budget.
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Budget Limit Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Paramètres du budget</h5>
                    </div>
                    <div class="card-body">
                        <form id="budgetLimitForm" class="row g-3">
                            <div class="col-md-6">
                                <label for="budgetLimit" class="form-label">Limite du budget</label>
                                <input type="number" class="form-control" id="budgetLimit" name="budget_limit" 
                                       value="<?php echo $budget_limit; ?>" step="0.01" min="0">
                            </div>
                            <div class="col-md-6">
                                <label for="currency" class="form-label">Devise</label>
                                <select class="form-select" id="currency" name="currency">
                                    <option value="MAD" <?php echo $currency === 'MAD' ? 'selected' : ''; ?>>MAD (Dirham marocain)</option>
                                    <option value="USD" <?php echo $currency === 'USD' ? 'selected' : ''; ?>>USD (Dollar américain)</option>
                                    <option value="EUR" <?php echo $currency === 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                                    <option value="GBP" <?php echo $currency === 'GBP' ? 'selected' : ''; ?>>GBP (Livre sterling)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Enregistrer la limite du budget
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabs for Expenses and Incomes -->
                <ul class="nav nav-tabs mb-4" id="budgetTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="expenses-tab" data-bs-toggle="tab" data-bs-target="#expenses" 
                                type="button" role="tab" aria-controls="expenses" aria-selected="true">
                            <i class="bi bi-cash-coin me-2"></i>Dépenses
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="incomes-tab" data-bs-toggle="tab" data-bs-target="#incomes" 
                                type="button" role="tab" aria-controls="incomes" aria-selected="false">
                            <i class="bi bi-money-bill me-2"></i>Revenus
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="charts-tab" data-bs-toggle="tab" data-bs-target="#charts" 
                                type="button" role="tab" aria-controls="charts" aria-selected="false">
                            <i class="bi bi-bar-chart me-2"></i>Graphiques
                        </button>
                    </li>
                </ul>

<div class="tab-content" id="budgetTabContent">
    <!-- Expenses Tab -->
    <div class="tab-pane fade show active" id="expenses" role="tabpanel" aria-labelledby="expenses-tab">
        <div class="mb-4">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="bi bi-plus-circle me-2"></i>Ajouter une dépense
            </button>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Catégorie</th>
                        <th>Titre</th>
                        <th>Montant</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="expensesTableBody">
                    <?php foreach ($expenses as $expense): ?>
                        <tr data-expense-id="<?php echo $expense['id']; ?>">
                            <td><?php echo htmlspecialchars($expense['date']); ?></td>
                            <td>
                                <span class="badge badge-secondary"><?php echo htmlspecialchars($expense['category']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($expense['title']); ?></td>
                            <td class="fw-bold text-danger"><?php echo number_format($expense['amount'], 2); ?> <?php echo $currency; ?></td>
                            <td><?php echo htmlspecialchars($expense['notes'] ?? ''); ?></td>
                            <td>
                                <button class="btn btn-sm btn-danger delete-expense" data-expense-id="<?php echo $expense['id']; ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($expenses)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <h4 class="text-muted mt-3">Aucune dépense</h4>
                    <p class="text-muted">Cliquez sur "Ajouter une dépense" pour commencer.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Incomes Tab -->
    <div class="tab-pane fade" id="incomes" role="tabpanel" aria-labelledby="incomes-tab">
        <div class="mb-4">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addIncomeModal">
                <i class="bi bi-plus-circle me-2"></i>Ajouter un revenu
            </button>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Source</th>
                        <th>Titre</th>
                        <th>Montant</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="incomesTableBody">
                    <?php foreach ($incomes as $income): ?>
                        <tr data-income-id="<?php echo $income['id']; ?>">
                            <td><?php echo htmlspecialchars($income['date']); ?></td>
                            <td>
                                <span class="badge badge-info"><?php echo htmlspecialchars($income['source']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($income['title']); ?></td>
                            <td class="fw-bold text-success"><?php echo number_format($income['amount'], 2); ?> <?php echo $currency; ?></td>
                            <td><?php echo htmlspecialchars($income['notes'] ?? ''); ?></td>
                            <td>
                                <button class="btn btn-sm btn-danger delete-income" data-income-id="<?php echo $income['id']; ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($incomes)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <h4 class="text-muted mt-3">Aucun revenu</h4>
                    <p class="text-muted">Cliquez sur "Ajouter un revenu" pour commencer.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Charts Tab -->
    <div class="tab-pane fade" id="charts" role="tabpanel" aria-labelledby="charts-tab">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Répartition par catégorie</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Budget vs Dépenses réelles</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="budgetChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addExpenseModalLabel">Ajouter une dépense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addExpenseForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="expenseCategory" class="form-label">Catégorie</label>
                        <select class="form-select" id="expenseCategory" name="category" required>
                            <option value="">Sélectionner une catégorie</option>
                            <option value="Logistique">Logistique</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Restauration">Restauration</option>
                            <option value="Matériel">Matériel</option>
                            <option value="Transport">Transport</option>
                            <option value="Autres">Autres</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="expenseTitle" class="form-label">Titre</label>
                        <input type="text" class="form-control" id="expenseTitle" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="expenseAmount" class="form-label">Montant (<?php echo $currency; ?>)</label>
                        <input type="number" class="form-control" id="expenseAmount" name="amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="expenseDate" class="form-label">Date</label>
                        <input type="date" class="form-control" id="expenseDate" name="date" required>
                    </div>
                    <div class="mb-3">
                        <label for="expenseNotes" class="form-label">Notes</label>
                        <textarea class="form-control" id="expenseNotes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer la dépense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Income Modal -->
<div class="modal fade" id="addIncomeModal" tabindex="-1" aria-labelledby="addIncomeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addIncomeModalLabel">Ajouter un revenu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addIncomeForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="incomeSource" class="form-label">Source</label>
                        <input type="text" class="form-control" id="incomeSource" name="source" placeholder="ex: Ventes de billets, Parrainage" required>
                    </div>
                    <div class="mb-3">
                        <label for="incomeTitle" class="form-label">Titre</label>
                        <input type="text" class="form-control" id="incomeTitle" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="incomeAmount" class="form-label">Montant (<?php echo $currency; ?>)</label>
                        <input type="number" class="form-control" id="incomeAmount" name="amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="incomeDate" class="form-label">Date</label>
                        <input type="date" class="form-control" id="incomeDate" name="date" required>
                    </div>
                    <div class="mb-3">
                        <label for="incomeNotes" class="form-label">Notes</label>
                        <textarea class="form-control" id="incomeNotes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer le revenu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
const eventId = <?php echo $eventId; ?>;
const currency = '<?php echo $currency; ?>';
const budgetLimit = <?php echo $budget_limit; ?>;
const totalExpenses = <?php echo $total_expenses; ?>;
const totalIncomes = <?php echo $total_incomes; ?>;

const categoryBreakdown = <?php echo json_encode($category_breakdown); ?>;

document.getElementById('charts-tab').addEventListener('click', function() {
    setTimeout(initializeCharts, 100);
});

function initializeCharts() {
    const categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx && !categoryCtx.chart) {
        const labels = Object.keys(categoryBreakdown);
        const data = Object.values(categoryBreakdown);
        const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];
        
        categoryCtx.chart = new Chart(categoryCtx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors.slice(0, labels.length),
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + ' ' + currency;
                            }
                        }
                    }
                }
            }
        });
    }
    
    const budgetCtx = document.getElementById('budgetChart');
    if (budgetCtx && !budgetCtx.chart) {
        budgetCtx.chart = new Chart(budgetCtx, {
            type: 'bar',
            data: {
                labels: ['Budget', 'Actual Expenses'],
                datasets: [{
                    label: 'Amount (' + currency + ')',
                    data: [budgetLimit, totalExpenses],
                    backgroundColor: [
                        '#36A2EB',
                        totalExpenses > budgetLimit ? '#FF6384' : '#4BC0C0'
                    ],
                    borderColor: [
                        '#36A2EB',
                        totalExpenses > budgetLimit ? '#FF6384' : '#4BC0C0'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.x + ' ' + currency;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

document.getElementById('addExpenseForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('event_id', eventId);
    
    fetch('add_expense.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Succès', 'Dépense ajoutée avec succès');
            document.getElementById('addExpenseForm').reset();
            const modal = bootstrap.Modal.getInstance(document.getElementById('addExpenseModal'));
            modal.hide();
            location.reload();
        } else {
            showToast('Erreur', data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Erreur', 'Échec de l\'ajout de la dépense', 'danger');
    });
});

document.getElementById('addIncomeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('event_id', eventId);
    
    fetch('add_income.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Succès', 'Revenu ajouté avec succès');
            document.getElementById('addIncomeForm').reset();
            const modal = bootstrap.Modal.getInstance(document.getElementById('addIncomeModal'));
            modal.hide();
            location.reload();
        } else {
            showToast('Erreur', data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Erreur', 'Échec de l\'ajout du revenu', 'danger');
    });
});

document.querySelectorAll('.delete-expense').forEach(button => {
    button.addEventListener('click', function() {
        if (confirm('Êtes-vous sûr de vouloir supprimer cette dépense ?')) {
            const expenseId = this.dataset.expenseId;
            
            fetch('delete_expense.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'expense_id=' + expenseId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Succès', 'Dépense supprimée avec succès');
                    document.querySelector(`[data-expense-id="${expenseId}"]`).remove();
                    location.reload();
                } else {
                    showToast('Erreur', data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur', 'Échec de la suppression de la dépense', 'danger');
            });
        }
    });
});

document.querySelectorAll('.delete-income').forEach(button => {
    button.addEventListener('click', function() {
        if (confirm('Êtes-vous sûr de vouloir supprimer ce revenu ?')) {
            const incomeId = this.dataset.incomeId;
            
            fetch('delete_income.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'income_id=' + incomeId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Succès', 'Revenu supprimé avec succès');
                    document.querySelector(`[data-income-id="${incomeId}"]`).remove();
                    location.reload();
                } else {
                    showToast('Erreur', data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur', 'Échec de la suppression du revenu', 'danger');
            });
        }
    });
});

document.getElementById('budgetLimitForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('event_id', eventId);
    
    fetch('update_budget_limit.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Succès', 'Limite du budget mise à jour avec succès');
            location.reload();
        } else {
            showToast('Erreur', data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Erreur', 'Échec de la mise à jour de la limite du budget', 'danger');
    });
});

function showToast(title, message, type = 'success') {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type === 'danger' ? 'danger' : 'success'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}:</strong> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    const toastContainer = document.createElement('div');
    toastContainer.className = 'position-fixed bottom-0 end-0 p-3';
    toastContainer.innerHTML = toastHtml;
    document.body.appendChild(toastContainer);
    
    const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'));
    toast.show();
    
    setTimeout(() => toastContainer.remove(), 5000);
}
</script>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
