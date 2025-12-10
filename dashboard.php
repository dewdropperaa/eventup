<?php
session_start();
require_once 'role_check.php';

// Check if user is logged in
requireLogin();

// If user is an organizer, redirect to organizer dashboard
if (isOrganizer($_SESSION['user_id'])) {
    header('Location: organizer_dashboard.php');
    exit;
}

require 'header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
        <p class="text-muted">You are successfully logged in.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Explore Events</h5>
            </div>
            <div class="card-body">
                <p>Browse and register for upcoming events in your area.</p>
                <a href="index.php" class="btn btn-primary">View All Events</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Account Settings</h5>
            </div>
            <div class="card-body">
                <p>Manage your profile and preferences.</p>
                <p class="text-muted mb-0"><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Not available'); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-primary">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Host Your Own Event</h5>
            </div>
            <div class="card-body">
                <p>Create your own event and automatically become its organizer. Youll be able to manage participants, invite other organizers, and assign tasks.</p>
                <a href="create_event.php" class="btn btn-primary">
                    + Create Event
                </a>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
