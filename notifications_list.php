<?php
/**
 * Dedicated Notifications Page
 * Shows all notifications for the current user with pagination and individual mark-as-read
 */

session_start();
require_once 'role_check.php';
requireLogin();
require_once 'database.php';
require_once 'notifications.php';

$pdo = getDatabaseConnection();
$user_id = $_SESSION['user_id'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Handle AJAX mark-as-read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    $notif_id = (int)($_POST['notif_id'] ?? 0);
    if ($notif_id) {
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
            $stmt->execute([':id' => $notif_id, ':user_id' => $user_id]);
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            error_log('mark notification read error: ' . $e->getMessage());
            echo json_encode(['success' => false]);
            exit;
        }
    }
    echo json_encode(['success' => false]);
    exit;
}

// Fetch total count for pagination
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $total = (int)$stmt->fetch()['total'];
    $total_pages = ceil($total / $per_page);
} catch (PDOException $e) {
    error_log('notification count error: ' . $e->getMessage());
    $total = 0;
    $total_pages = 0;
}

// Fetch notifications for current page
$notifications = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, event_id, type, message, is_read, created_at
        FROM notifications
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('fetch notifications error: ' . $e->getMessage());
    $notifications = [];
}

include 'header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h2>Notifications</h2>
        <p class="text-muted">All your notifications</p>
    </div>
    <div class="col-md-4 text-end">
        <a href="notifications_mark_read.php" class="btn btn-sm btn-outline-secondary">Mark all as read</a>
    </div>
</div>

<?php if (empty($notifications)): ?>
    <div class="alert alert-info" role="alert">
        <strong>No notifications yet!</strong> You'll see your notifications here.
    </div>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($notifications as $notif): ?>
            <div class="list-group-item <?php echo $notif['is_read'] ? 'bg-light' : ''; ?>" id="notif-<?php echo $notif['id']; ?>">
                <div class="row align-items-start">
                    <div class="col-md-10">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1 <?php echo $notif['is_read'] ? 'text-muted' : 'fw-bold'; ?>">
                                    <?php echo htmlspecialchars($notif['type']); ?>
                                </h6>
                                <p class="mb-1 <?php echo $notif['is_read'] ? 'text-muted' : ''; ?>">
                                    <?php echo htmlspecialchars($notif['message']); ?>
                                </p>
                                <small class="text-muted">
                                    <?php echo date('M j, Y \a\t g:i A', strtotime($notif['created_at'])); ?>
                                    <?php if ($notif['event_id']): ?>
                                        <span class="badge bg-secondary ms-2">Event ID <?php echo $notif['event_id']; ?></span>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 text-end">
                        <?php if (!$notif['is_read']): ?>
                            <button class="btn btn-sm btn-outline-primary mark-read-btn" data-notif-id="<?php echo $notif['id']; ?>">
                                <i class="bi bi-check"></i> Mark as read
                            </button>
                        <?php else: ?>
                            <span class="badge bg-success">Read</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Notifications pagination" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle mark-as-read buttons
    const markReadButtons = document.querySelectorAll('.mark-read-btn');
    markReadButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const notifId = this.getAttribute('data-notif-id');
            const notifItem = document.getElementById('notif-' + notifId);
            
            fetch('notifications_list.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_read&notif_id=' + encodeURIComponent(notifId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI to show as read
                    notifItem.classList.add('bg-light');
                    const title = notifItem.querySelector('h6');
                    const message = notifItem.querySelector('p');
                    title.classList.remove('fw-bold');
                    title.classList.add('text-muted');
                    message.classList.add('text-muted');
                    
                    // Replace button with "Read" badge
                    const btnContainer = this.parentElement;
                    btnContainer.innerHTML = '<span class="badge bg-success">Read</span>';
                } else {
                    alert('Failed to mark notification as read.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while marking notification as read.');
            });
        });
    });
});
</script>

<?php include 'footer.php'; ?>
