<?php
require_once 'database.php';

function createNotification($user_id, $type, $message, $event_id = null) {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, event_id, type, message) VALUES (:user_id, :event_id, :type, :message)");
        $result = $stmt->execute([
            ':user_id' => $user_id,
            ':event_id' => $event_id,
            ':type' => $type,
            ':message' => $message
        ]);
        if ($result) {
            error_log("Notification created for user $user_id: $type");
        }
        return $result;
    } catch (PDOException $e) {
        error_log('createNotification error: ' . $e->getMessage());
        return false;
    }
}

function getUnreadNotifications($user_id, $limit = 5) {
    try {
        $pdo = getDatabaseConnection();
        $limit = max(1, (int)$limit);
        $sql = "SELECT id, event_id, type, message, created_at
                FROM notifications
                WHERE user_id = :user_id AND is_read = 0
                ORDER BY created_at DESC
                LIMIT $limit";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('getUnreadNotifications error: ' . $e->getMessage());
        return [];
    }
}

function markAllNotificationsRead($user_id) {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute([':user_id' => $user_id]);
    } catch (PDOException $e) {
        error_log('markAllNotificationsRead error: ' . $e->getMessage());
    }
}
