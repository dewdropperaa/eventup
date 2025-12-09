<?php
session_start();
?>

<?php require 'header.php'; ?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6">
        <div class="card shadow-lg">
            <div class="card-body text-center py-5">
                <i class="bi bi-lock-fill" style="font-size: 4rem; color: #dc3545;"></i>
                <h1 class="card-title mt-3">Access Denied</h1>
                <p class="card-text text-muted mb-4">
                    You do not have permission to access this resource. 
                    Only the event owner or authorized organizers can access this page.
                </p>
                <div class="d-grid gap-2">
                    <a href="javascript:history.back()" class="btn btn-primary">Go Back</a>
                    <a href="dashboard.php" class="btn btn-outline-secondary">Go to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
