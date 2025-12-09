<?php
/**
 * Complete flow test - simulates real user interaction
 */

session_start();
require_once 'database.php';
require_once 'notifications.php';
require_once 'role_check.php';

$pdo = getDatabaseConnection();

?>
<!DOCTYPE html>
<html>
<head>
    <title>EventUp - Complete Flow Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1>EventUp - Complete Flow Test</h1>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5>Step 1: Database Status</h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        // Check tables exist
                        $tables = ['users', 'events', 'registrations', 'tasks', 'notifications', 'event_roles'];
                        foreach ($tables as $table) {
                            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                            $exists = $stmt->rowCount() > 0;
                            echo ($exists ? '✓' : '✗') . " Table: $table<br>";
                        }
                    } catch (Exception $e) {
                        echo "ERROR: " . $e->getMessage();
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5>Step 2: Test User Data</h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
                        $result = $stmt->fetch();
                        echo "Total users: " . $result['count'] . "<br>";
                        
                        $stmt = $pdo->query("SELECT id, nom, email FROM users LIMIT 3");
                        $users = $stmt->fetchAll();
                        foreach ($users as $user) {
                            echo "- " . $user['nom'] . " (" . $user['email'] . ")<br>";
                        }
                    } catch (Exception $e) {
                        echo "ERROR: " . $e->getMessage();
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5>Step 3: Create Test Notification</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label>Select User:</label>
                            <select name="user_id" class="form-control" required>
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
                        <button type="submit" name="action" value="create_notif" class="btn btn-success">Create Test Notification</button>
                    </form>
                    
                    <?php
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_notif') {
                        $user_id = (int)$_POST['user_id'];
                        $result = createNotification($user_id, 'Test Notification', 'This is a test notification created at ' . date('Y-m-d H:i:s'), null);
                        if ($result) {
                            echo "<div class='alert alert-success mt-3'>✓ Notification created successfully</div>";
                        } else {
                            echo "<div class='alert alert-danger mt-3'>✗ Failed to create notification</div>";
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5>Step 4: View Notifications</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label>Select User:</label>
                            <select name="user_id_view" class="form-control" required>
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
                        <button type="submit" name="action" value="view_notif" class="btn btn-info">View Notifications</button>
                    </form>
                    
                    <?php
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'view_notif') {
                        $user_id = (int)$_POST['user_id_view'];
                        $notifs = getUnreadNotifications($user_id, 10);
                        echo "<div class='mt-3'>";
                        echo "Unread notifications: " . count($notifs) . "<br>";
                        if (count($notifs) > 0) {
                            echo "<ul class='list-group mt-2'>";
                            foreach ($notifs as $notif) {
                                echo "<li class='list-group-item'>";
                                echo "<strong>" . htmlspecialchars($notif['type']) . "</strong><br>";
                                echo htmlspecialchars($notif['message']) . "<br>";
                                echo "<small class='text-muted'>" . $notif['created_at'] . "</small>";
                                echo "</li>";
                            }
                            echo "</ul>";
                        }
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5>Step 5: Test AJAX Endpoint</h5>
                </div>
                <div class="card-body">
                    <p>Testing: <code>ajax_handler.php?action=get_notifications</code></p>
                    <button class="btn btn-warning" onclick="testAjaxEndpoint()">Test AJAX Endpoint</button>
                    <div id="ajax-result" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5>Step 6: Check My Tasks</h5>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tasks");
                        $result = $stmt->fetch();
                        echo "Total tasks in database: " . $result['count'] . "<br>";
                        
                        $stmt = $pdo->query("SELECT t.id, t.task_name, u.nom as organizer, e.titre as event FROM tasks t JOIN users u ON t.organizer_id = u.id JOIN events e ON t.event_id = e.id LIMIT 5");
                        $tasks = $stmt->fetchAll();
                        
                        if (count($tasks) > 0) {
                            echo "<table class='table table-sm mt-3'>";
                            echo "<thead><tr><th>Task</th><th>Organizer</th><th>Event</th></tr></thead>";
                            echo "<tbody>";
                            foreach ($tasks as $task) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($task['task_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($task['organizer']) . "</td>";
                                echo "<td>" . htmlspecialchars($task['event']) . "</td>";
                                echo "</tr>";
                            }
                            echo "</tbody>";
                            echo "</table>";
                        } else {
                            echo "No tasks found";
                        }
                    } catch (Exception $e) {
                        echo "ERROR: " . $e->getMessage();
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testAjaxEndpoint() {
    const resultDiv = document.getElementById('ajax-result');
    resultDiv.innerHTML = '<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>';
    
    fetch('ajax_handler.php?action=get_notifications', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
        },
    })
    .then(response => response.json())
    .then(data => {
        resultDiv.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error.message + '</div>';
    });
}
</script>

</body>
</html>
