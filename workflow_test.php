<?php
// workflow_test.php

echo "Starting Workflow Test...\n\n";

require_once 'database.php';
require_once 'role_check.php';

// Set up error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test Functions
function runTest($description, $testFunction) {
    echo "- Running: {$description}... ";
    try {
        $result = $testFunction();
        if ($result) {
            echo "\033[32mPASSED\033[0m\n";
        } else {
            echo "\033[31mFAILED\033[0m\n";
        }
    } catch (Exception $e) {
        echo "\033[31mERROR: " . $e->getMessage() . "\033[0m\n";
    }
}

$testData = [];

function cleanup() {
    global $testData;
    $pdo = getDatabaseConnection();
    echo "\nCleaning up test data...\n";
    if (!empty($testData['users'])) {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id IN (' . implode(',', array_fill(0, count($testData['users']), '?')) . ')');
        $stmt->execute(array_column($testData['users'], 'id'));
        echo "- Deleted " . count($testData['users']) . " test users.\n";
    }
    if (!empty($testData['events'])) {
        $eventIds = array_column($testData['events'], 'id');
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        
        $stmt = $pdo->prepare("DELETE FROM event_roles WHERE event_id IN ($placeholders)");
        $stmt->execute($eventIds);
        echo "- Deleted event roles.\n";

        $stmt = $pdo->prepare("DELETE FROM event_invitations WHERE event_id IN ($placeholders)");
        $stmt->execute($eventIds);
        echo "- Deleted invitations.\n";

        $stmt = $pdo->prepare("DELETE FROM tasks WHERE event_id IN ($placeholders)");
        $stmt->execute($eventIds);
        echo "- Deleted tasks.\n";

        $stmt = $pdo->prepare("DELETE FROM registrations WHERE event_id IN ($placeholders)");
        $stmt->execute($eventIds);
        echo "- Deleted event registrations.\n";

        $stmt = $pdo->prepare("DELETE FROM events WHERE id IN ($placeholders)");
        $stmt->execute($eventIds);
        echo "- Deleted " . count($testData['events']) . " test events.\n";
    }
}

register_shutdown_function('cleanup');

runTest("User Registration", function() {
    global $testData;
    $pdo = getDatabaseConnection();
    $usersToCreate = [
        ['nom' => 'Test Admin', 'email' => 'admin@test.com', 'password' => 'password123'],
        ['nom' => 'Test Organizer', 'email' => 'organizer@test.com', 'password' => 'password123'],
        ['nom' => 'Test User', 'email' => 'user@test.com', 'password' => 'password123'],
    ];

    foreach ($usersToCreate as $userData) {
        // Clean up user if exists
        $stmt = $pdo->prepare('DELETE FROM users WHERE email = ?');
        $stmt->execute([$userData['email']]);

        $hashed_password = password_hash($userData['password'], PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (nom, email, mot_de_passe) VALUES (?, ?, ?)');
        $stmt->execute([$userData['nom'], $userData['email'], $hashed_password]);
        $userId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception("Failed to create user: {$userData['email']}");
        }
        $testData['users'][] = $user;
    }
    return count($testData['users']) === 3;
});

runTest("Event Creation", function() {
    global $testData;
    $pdo = getDatabaseConnection();
    $adminUser = $testData['users'][0]; // Admin is the first user

    $eventData = [
        'titre' => 'Test Event',
        'description' => 'A test event for the workflow.',
        'date' => date('Y-m-d H:i:s', strtotime('+1 week')),
        'lieu' => 'Virtual',
        'nb_max_participants' => 50
    ];

    $stmt = $pdo->prepare('INSERT INTO events (titre, description, date, lieu, nb_max_participants) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(array_values($eventData));
    $eventId = $pdo->lastInsertId();

    if (!$eventId) {
        throw new Exception('Failed to create event.');
    }
    $testData['events'][] = ['id' => $eventId];

    // Assign admin role
    $stmt = $pdo->prepare('INSERT INTO event_roles (user_id, event_id, role) VALUES (?, ?, ?)');
    $stmt->execute([$adminUser['id'], $eventId, 'admin']);

    // Verification
    $role = isEventAdmin($adminUser['id'], $eventId);

    return $role;
});

runTest("Invite Organizer", function() {
    global $testData;
    $pdo = getDatabaseConnection();
    $organizerUser = $testData['users'][1];
    $eventId = $testData['events'][0]['id'];

    $invitation_token = bin2hex(random_bytes(16));
    $testData['invitation_token'] = $invitation_token;

    $stmt = $pdo->prepare('INSERT INTO event_invitations (event_id, email, token, role, token_expiry) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$eventId, $organizerUser['email'], $invitation_token, 'organizer', date('Y-m-d H:i:s', strtotime('+1 day'))]);

    // Verification
    $stmt = $pdo->prepare('SELECT * FROM event_invitations WHERE token = ?');
    $stmt->execute([$invitation_token]);
    $invitation = $stmt->fetch();

    return $invitation && $invitation['email'] === $organizerUser['email'];
});

runTest("Accept Invitation", function() {
    global $testData;
    $pdo = getDatabaseConnection();
    $organizerUser = $testData['users'][1];
    $invitation_token = $testData['invitation_token'];

    // Find invitation
    $stmt = $pdo->prepare('SELECT * FROM event_invitations WHERE token = ?');
    $stmt->execute([$invitation_token]);
    $invitation = $stmt->fetch();

    if (!$invitation) {
        throw new Exception('Invitation not found.');
    }

    // Add event role
    $stmt = $pdo->prepare('INSERT INTO event_roles (user_id, event_id, role) VALUES (?, ?, ?)');
    $stmt->execute([$organizerUser['id'], $invitation['event_id'], $invitation['role']]);

    // Delete invitation
    $stmt = $pdo->prepare('DELETE FROM event_invitations WHERE id = ?');
    $stmt->execute([$invitation['id']]);

    // Verification
    $isOrganizer = isEventOrganizer($organizerUser['id'], $invitation['event_id']);
    $stmt = $pdo->prepare('SELECT * FROM event_invitations WHERE id = ?');
    $stmt->execute([$invitation['id']]);
    $invitationExists = $stmt->fetch();

    return $isOrganizer && !$invitationExists;
});

runTest("Assign Task", function() {
    global $testData;
    $pdo = getDatabaseConnection();
    $organizerUser = $testData['users'][1];
    $eventId = $testData['events'][0]['id'];

    $taskData = [
        'event_id' => $eventId,
        'organizer_id' => $organizerUser['id'],
        'task_name' => 'Prepare presentation',
        'description' => 'Create the slides for the opening keynote.',
        'due_date' => date('Y-m-d H:i:s', strtotime('+3 days'))
    ];

    $stmt = $pdo->prepare('INSERT INTO tasks (event_id, organizer_id, task_name, description, due_date) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute(array_values($taskData));
    $taskId = $pdo->lastInsertId();

    if (!$taskId) {
        throw new Exception('Failed to create task.');
    }
    $testData['tasks'][] = ['id' => $taskId];

    // Verification
    $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    return $task && $task['organizer_id'] === $organizerUser['id'];
});

runTest("Event Attendance", function() {
    global $testData;
    $pdo = getDatabaseConnection();
    $regularUser = $testData['users'][2];
    $eventId = $testData['events'][0]['id'];

    $stmt = $pdo->prepare('INSERT INTO registrations (event_id, user_id) VALUES (?, ?)');
    $stmt->execute([$eventId, $regularUser['id']]);

    // Verification
    $stmt = $pdo->prepare('SELECT * FROM registrations WHERE event_id = ? AND user_id = ?');
    $stmt->execute([$eventId, $regularUser['id']]);
    $registration = $stmt->fetch();

    return (bool)$registration;
});

echo "\nWorkflow Test Completed.\n";
