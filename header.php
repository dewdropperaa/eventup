<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventUp</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">EventUp</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php 
                    $is_organizer = false;
                    $unread_notifications = [];
                    if (isset($_SESSION['user_id'])) {
                        // Check if user is an admin/organizer
                        require_once 'role_check.php';
                        require_once 'notifications.php';
                        $is_organizer = isOrganizer($_SESSION['user_id']);
                        $unread_notifications = getUnreadNotifications($_SESSION['user_id'], 5);
                    }
                ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Browse Events</a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-bell"></i>
                                <?php if (!empty($unread_notifications)): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo count($unread_notifications); ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" style="min-width: 350px;" id="notification-menu">
                                <li class="dropdown-header">Notifications</li>
                                <li><div class="px-3 py-5 text-center text-muted">Loading...</div></li>
                            </ul>
                        </li>
                        <?php if ($is_organizer): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="organizer_dashboard.php">My Events</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="create_event.php">+ Create Event</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="my_tasks.php">My Tasks</a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="signup.php">Sign Up</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
