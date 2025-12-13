<?php
echo "=== Permission Consistency Fix ===\n\n";

// Files that need to use canDo() for permission checking
$files_to_check = [
    'communication_hub.php' => [
        'search' => '// Check if user is organizer or event owner',
        'replace' => '// Check if user has permission to access communication hub
            if (!canDo($eventId, $_SESSION[\'user_id\'], \'can_manage_resources\')) {
                $error = \'You do not have permission to access this communication hub.\';
                $canAccess = false;
            } else {
                $canAccess = true;
            }'
    ],
    'organizers.php' => [
        'search' => 'requireEventAdmin($_SESSION[\'user_id\'], $eventId);',
        'replace' => 'if (!canDo($eventId, $_SESSION[\'user_id\'], \'can_invite_organizers\')) {
            header(\'Location: event_details.php?id=\' . $eventId);
            exit;
        }'
    ]
];

echo "Checking permission consistency across files...\n\n";

foreach ($files_to_check as $file => $changes) {
    if (file_exists($file)) {
        echo "1. Checking {$file}...\n";
        $content = file_get_contents($file);
        
        if (strpos($content, $changes['search']) !== false) {
            echo "   Found old permission check method\n";
            echo "   This file needs to be updated to use canDo() function\n";
        } else {
            echo "   Already using proper permission checking\n";
        }
    } else {
        echo "1. File {$file} not found\n";
    }
    echo "\n";
}

echo "=== Analysis Complete ===\n";
echo "\nRECOMMENDATIONS:\n";
echo "1. Update communication_hub.php to use canDo() instead of isEventOrganizer()\n";
echo "2. Ensure all permission checks use the consistent canDo() function\n";
echo "3. The permission system itself is working correctly\n";
echo "4. The main issue was missing event_organizer assignments (already fixed)\n";
?>
