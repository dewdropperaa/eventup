<?php
/**
 * Direct AJAX Endpoint Test
 * This simulates what the browser does when fetching notifications
 */

session_start();
require_once 'database.php';
require_once 'notifications.php';

$pdo = getDatabaseConnection();

?>
<!DOCTYPE html>
<html>
<head>
    <title>EventUp - AJAX Endpoint Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1>EventUp - AJAX Endpoint Test</h1>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5>Step 1: Select a User</h5>
                </div>
                <div class="card-body">
                    <form id="userForm">
                        <div class="mb-3">
                            <label>Select User:</label>
                            <select id="userId" class="form-control" required>
                                <option value="">-- Select User --</option>
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT id, nom, email FROM users LIMIT 10");
                                    $users = $stmt->fetchAll();
                                    foreach ($users as $user) {
                                        echo "<option value='" . $user['id'] . "'>" . $user['nom'] . " (" . $user['email'] . ")</option>";
                                    }
                                } catch (Exception $e) {
                                    echo "<option>Error loading users</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="testAjax()">Test AJAX Endpoint</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5>Step 2: Create Test Notification</h5>
                </div>
                <div class="card-body">
                    <form id="notifForm">
                        <div class="mb-3">
                            <label>Select User:</label>
                            <select id="notifUserId" class="form-control" required>
                                <option value="">-- Select User --</option>
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT id, nom FROM users LIMIT 10");
                                    $users = $stmt->fetchAll();
                                    foreach ($users as $user) {
                                        echo "<option value='" . $user['id'] . "'>" . $user['nom'] . "</option>";
                                    }
                                } catch (Exception $e) {
                                    echo "<option>Error loading users</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <button type="button" class="btn btn-success" onclick="createTestNotification()">Create Test Notification</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5>AJAX Response</h5>
                </div>
                <div class="card-body">
                    <div id="response" style="background-color: #f5f5f5; padding: 10px; border-radius: 5px; min-height: 200px; max-height: 500px; overflow-y: auto;">
                        <p class="text-muted">Response will appear here...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5>Debug Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Current Session User ID:</strong> <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not logged in'; ?></p>
                    <p><strong>AJAX Handler Path:</strong> <code>ajax_handler.php</code></p>
                    <p><strong>Test URL:</strong> <code>ajax_handler.php?action=get_notifications</code></p>
                    <p><strong>Method:</strong> GET</p>
                    <hr>
                    <p><strong>Instructions:</strong></p>
                    <ol>
                        <li>If you're not logged in, the AJAX endpoint will return an authentication error</li>
                        <li>Select a user and click "Test AJAX Endpoint" to fetch their notifications</li>
                        <li>Create a test notification for a user first, then fetch it</li>
                        <li>The response should be valid JSON with a "success" field and "notifications" array</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testAjax() {
    const userId = document.getElementById('userId').value;
    const responseDiv = document.getElementById('response');
    
    if (!userId) {
        responseDiv.innerHTML = '<div class="alert alert-warning">Please select a user</div>';
        return;
    }
    
    responseDiv.innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div> Testing AJAX endpoint...';
    
    // First, we need to set the session user to the selected user for testing
    // Since we can't change the session from JavaScript, we'll simulate the AJAX call
    
    fetch('ajax_handler.php?action=get_notifications', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
        },
    })
    .then(response => response.json())
    .then(data => {
        responseDiv.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
        
        if (data.success) {
            responseDiv.innerHTML += '<div class="alert alert-success mt-3">✓ AJAX endpoint is working!</div>';
            responseDiv.innerHTML += '<p>Notifications retrieved: ' + (data.notifications ? data.notifications.length : 0) + '</p>';
        } else {
            responseDiv.innerHTML += '<div class="alert alert-danger mt-3">✗ Error: ' + (data.message || 'Unknown error') + '</div>';
        }
    })
    .catch(error => {
        responseDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error.message + '</div>';
        responseDiv.innerHTML += '<pre>' + error.stack + '</pre>';
    });
}

function createTestNotification() {
    const userId = document.getElementById('notifUserId').value;
    const responseDiv = document.getElementById('response');
    
    if (!userId) {
        responseDiv.innerHTML = '<div class="alert alert-warning">Please select a user</div>';
        return;
    }
    
    responseDiv.innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div> Creating test notification...';
    
    fetch('test_ajax_direct.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=create_test_notif&user_id=' + encodeURIComponent(userId)
    })
    .then(response => response.json())
    .then(data => {
        responseDiv.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
        
        if (data.success) {
            responseDiv.innerHTML += '<div class="alert alert-success mt-3">✓ Test notification created!</div>';
        } else {
            responseDiv.innerHTML += '<div class="alert alert-danger mt-3">✗ Error: ' + (data.message || 'Unknown error') + '</div>';
        }
    })
    .catch(error => {
        responseDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error.message + '</div>';
    });
}
</script>

<?php
// Handle AJAX POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'create_test_notif') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        
        require_once 'notifications.php';
        $result = createNotification($user_id, 'Test Notification', 'This is a test notification created at ' . date('Y-m-d H:i:s'), null);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Test notification created']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create notification']);
        }
        exit;
    }
}
?>

</body>
</html>
