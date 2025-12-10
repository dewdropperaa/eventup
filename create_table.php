<?php
require_once 'database.php';

echo "<h2>Creating Notifications Table</h2>";

try {
    $pdo = getDatabaseConnection();
    
    // Create the notifications table
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
      id int(11) AUTO_INCREMENT PRIMARY KEY,
      user_id int(11) NOT NULL,
      event_id int(11) NULL,
      type varchar(50) NOT NULL,
      message text NOT NULL,
      is_read tinyint(1) NOT NULL DEFAULT 0,
      created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id)
          ON UPDATE CASCADE
          ON DELETE CASCADE,
      CONSTRAINT fk_notifications_event
        FOREIGN KEY (event_id) REFERENCES events(id)
          ON UPDATE CASCADE
          ON DELETE SET NULL,
      INDEX idx_notifications_user (user_id, is_read),
      INDEX idx_notifications_event (event_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "<p style='color: green;'>✓ Notifications table created successfully!</p>";
    
    // Verify table exists
    $stmt = $pdo->query("DESCRIBE notifications");
    $columns = $stmt->fetchAll();
    echo "<p>Table has " . count($columns) . " columns</p>";
    
    // Test creating a notification
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, 'Test Notification', 'Notifications table is now working!']);
        echo "<p style='color: green;'>✓ Test notification created for user $user_id</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='test_db_direct.php'>Test the database again</a></p>";
echo "<p><a href='index.php'>Go to main page</a></p>";
?>
