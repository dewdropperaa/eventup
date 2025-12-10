<?php
/**
 * Event Navigation Sidebar Component
 * Displays navigation menu for event management pages
 * 
 * Required variables:
 * - $eventId: The current event ID
 * - $isEventOwner: Boolean indicating if user is event owner
 * - $isEventAdmin: Boolean indicating if user is event admin
 * - $isEventOrganizer: Boolean indicating if user is event organizer
 */

// Determine current page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
.event-nav-sidebar {
    background: #f8f9fa;
    border-right: 1px solid #e1e8ed;
    padding: 0;
    position: sticky;
    top: 76px;
}

.event-nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    color: #657786;
    text-decoration: none;
    border-left: 4px solid transparent;
    transition: all 0.3s ease;
    font-size: 15px;
    font-weight: 500;
}

.event-nav-item:hover {
    background-color: #f0f3f7;
    color: #2c3e50;
}

.event-nav-item.active {
    background-color: #fff8f0;
    color: #D94A00;
    border-left-color: #D94A00;
}

.event-nav-item i {
    font-size: 18px;
    width: 24px;
    text-align: center;
    flex-shrink: 0;
}

.event-nav-item.active i {
    color: #D94A00;
}
</style>

<div class="col-lg-3 event-nav-sidebar">
    <!-- Dashboard Link -->
    <a href="event_details.php?id=<?php echo $eventId; ?>" 
       class="event-nav-item <?php echo $current_page === 'event_details.php' ? 'active' : ''; ?>">
        <i class="bi bi-grid"></i>
        <span>Tableau de bord</span>
    </a>

    <!-- Communication Hub (Organizers only) -->
    <?php if ($isEventOrganizer || $isEventOwner): ?>
    <a href="communication_hub.php?event_id=<?php echo $eventId; ?>" 
       class="event-nav-item <?php echo $current_page === 'communication_hub.php' ? 'active' : ''; ?>">
        <i class="bi bi-chat-dots"></i>
        <span>Communication</span>
    </a>
    <?php endif; ?>

    <!-- Resources (Organizers only) -->
    <?php if ($isEventOrganizer || $isEventOwner): ?>
    <a href="resources.php?event_id=<?php echo $eventId; ?>" 
       class="event-nav-item <?php echo $current_page === 'resources.php' ? 'active' : ''; ?>">
        <i class="bi bi-box"></i>
        <span>Ressources</span>
    </a>
    <?php endif; ?>

    <!-- Budget (Organizers only) -->
    <?php if ($isEventOrganizer || $isEventOwner): ?>
    <a href="budget.php?event_id=<?php echo $eventId; ?>" 
       class="event-nav-item <?php echo $current_page === 'budget.php' ? 'active' : ''; ?>">
        <i class="bi bi-wallet2"></i>
        <span>Budget</span>
    </a>
    <?php endif; ?>

    <!-- Organizers (Admin only) -->
    <?php if ($isEventAdmin || $isEventOwner): ?>
    <a href="organizers.php?event_id=<?php echo $eventId; ?>" 
       class="event-nav-item <?php echo $current_page === 'organizers.php' ? 'active' : ''; ?>">
        <i class="bi bi-people"></i>
        <span>Organisateurs</span>
    </a>
    <?php endif; ?>

    <!-- Permissions (Owner only) -->
    <?php if ($isEventOwner): ?>
    <a href="event_permissions.php?event_id=<?php echo $eventId; ?>" 
       class="event-nav-item <?php echo $current_page === 'event_permissions.php' ? 'active' : ''; ?>">
        <i class="bi bi-shield-lock"></i>
        <span>Permissions</span>
    </a>
    <?php endif; ?>
</div>
