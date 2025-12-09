<?php
session_start();

require 'database.php';

$pdo = getDatabaseConnection();
$user_id = $_SESSION['user_id'] ?? null;

// Get filter and search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$date_filter = isset($_GET['date_filter']) ? trim($_GET['date_filter']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'nearest_date';
$role_filter = isset($_GET['role_filter']) ? trim($_GET['role_filter']) : '';

// Build the base query
$query = "
    SELECT 
        e.id, 
        e.titre, 
        e.description,
        e.date, 
        e.lieu, 
        e.nb_max_participants,
        e.category,
        e.created_at,
        COUNT(DISTINCT r.id) as registered_count,
        (e.nb_max_participants - COUNT(DISTINCT r.id)) as places_left,
        CASE WHEN r.user_id = :current_user THEN 1 ELSE 0 END as is_registered
    FROM events e
    LEFT JOIN registrations r ON e.id = r.event_id
    WHERE e.date >= NOW()
";

$params = [':current_user' => $user_id];

// Apply search filter
if (!empty($search)) {
    $query .= " AND (LOWER(e.titre) LIKE LOWER(:search) OR LOWER(e.description) LIKE LOWER(:search) OR LOWER(e.lieu) LIKE LOWER(:search))";
    $params[':search'] = '%' . $search . '%';
}

// Apply category filter
if (!empty($category)) {
    $query .= " AND e.category = :category";
    $params[':category'] = $category;
}

// Apply location filter
if (!empty($location)) {
    $query .= " AND e.lieu LIKE :location";
    $params[':location'] = '%' . $location . '%';
}

// Apply date filter
if (!empty($date_filter)) {
    switch ($date_filter) {
        case 'today':
            $query .= " AND DATE(e.date) = CURDATE()";
            break;
        case 'this_week':
            $query .= " AND WEEK(e.date) = WEEK(NOW()) AND YEAR(e.date) = YEAR(NOW())";
            break;
        case 'this_month':
            $query .= " AND MONTH(e.date) = MONTH(NOW()) AND YEAR(e.date) = YEAR(NOW())";
            break;
        case 'next_month':
            $query .= " AND MONTH(e.date) = MONTH(DATE_ADD(NOW(), INTERVAL 1 MONTH)) AND YEAR(e.date) = YEAR(DATE_ADD(NOW(), INTERVAL 1 MONTH))";
            break;
    }
}

// Apply role filter (for organizers/admins)
if (!empty($role_filter) && $user_id) {
    // Bind once for role checks
    $params[':role_user_id'] = $user_id;
    switch ($role_filter) {
        case 'admin':
            $query .= " AND EXISTS (SELECT 1 FROM event_roles er WHERE er.event_id = e.id AND er.user_id = :role_user_id AND er.role = 'admin')";
            break;
        case 'organizer':
            $query .= " AND EXISTS (SELECT 1 FROM event_roles er WHERE er.event_id = e.id AND er.user_id = :role_user_id AND er.role IN ('admin', 'organizer'))";
            break;
    }
}

$query .= " GROUP BY e.id";

// Apply sorting
switch ($sort) {
    case 'nearest_date':
        $query .= " ORDER BY e.date ASC";
        break;
    case 'most_popular':
        $query .= " ORDER BY registered_count DESC, e.date ASC";
        break;
    case 'alphabetical':
        $query .= " ORDER BY e.titre ASC";
        break;
    case 'recently_added':
        $query .= " ORDER BY e.created_at DESC";
        break;
    default:
        $query .= " ORDER BY e.date ASC";
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $events = $stmt->fetchAll();
    
    // Get all unique categories for filter dropdown
    $category_stmt = $pdo->query("SELECT DISTINCT category FROM events WHERE date >= NOW() ORDER BY category ASC");
    $categories = $category_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get all unique locations for filter dropdown
    $location_stmt = $pdo->query("SELECT DISTINCT lieu FROM events WHERE date >= NOW() ORDER BY lieu ASC");
    $locations = $location_stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    error_log('Error fetching events: ' . $e->getMessage());
    $events = [];
    $categories = [];
    $locations = [];
}

require 'header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="mb-2">Upcoming Events</h1>
        <p class="text-muted">Discover and register for events</p>
    </div>
</div>

<!-- Live Search Section -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <label for="live-search-input" class="form-label fw-semibold">Search</label>
        <div class="input-group input-group-lg">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" id="live-search-input" class="form-control" placeholder="Type to search upcoming events..." autocomplete="off">
        </div>
    
    </div>
</div>

<div id="live-search-results" class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 d-none"></div>

<div id="original-event-list">
<!-- Search and Filter Section -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="index.php" class="row g-3">
            <!-- Filters Row -->
            <div class="col-md-3">
                <label class="form-label"><strong>Category</strong></label>
                <select class="form-select" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label"><strong>Location</strong></label>
                <select class="form-select" name="location">
                    <option value="">All Locations</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $location === $loc ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label"><strong>Date Range</strong></label>
                <select class="form-select" name="date_filter">
                    <option value="">All Dates</option>
                    <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="this_week" <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                    <option value="this_month" <?php echo $date_filter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                    <option value="next_month" <?php echo $date_filter === 'next_month' ? 'selected' : ''; ?>>Next Month</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label"><strong>Sort By</strong></label>
                <select class="form-select" name="sort">
                    <option value="nearest_date" <?php echo $sort === 'nearest_date' ? 'selected' : ''; ?>>Nearest Date</option>
                    <option value="most_popular" <?php echo $sort === 'most_popular' ? 'selected' : ''; ?>>Most Popular</option>
                    <option value="alphabetical" <?php echo $sort === 'alphabetical' ? 'selected' : ''; ?>>Alphabetical</option>
                    <option value="recently_added" <?php echo $sort === 'recently_added' ? 'selected' : ''; ?>>Recently Added</option>
                </select>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="col-md-12">
                    <label class="form-label"><strong>My Role</strong></label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="role_filter" id="role_all" value="" <?php echo empty($role_filter) ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-secondary" for="role_all">All Events</label>

                        <input type="radio" class="btn-check" name="role_filter" id="role_organizer" value="organizer" <?php echo $role_filter === 'organizer' ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-info" for="role_organizer">My Events (Organizer)</label>

                        <input type="radio" class="btn-check" name="role_filter" id="role_admin" value="admin" <?php echo $role_filter === 'admin' ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-danger" for="role_admin">My Events (Admin)</label>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Apply Filters
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-clockwise"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>
<!-- Results Section -->
<?php if (empty($events)): ?>
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle"></i>
        <strong>No events found.</strong> Try adjusting your search filters or check back later for new events.
    </div>
<?php else: ?>
    <div class="row mb-3">
        <div class="col-md-12">
            <p class="text-muted">
                <strong><?php echo count($events); ?></strong> event<?php echo count($events) !== 1 ? 's' : ''; ?> found
            </p>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($events as $event): ?>
            <?php
                $eventDate = new DateTime($event['date']);
                $formattedDate = $eventDate->format('d/m/Y H:i');
                $placesLeft = max(0, $event['places_left']);
                $registeredCount = $event['registered_count'];
                $maxParticipants = $event['nb_max_participants'];
                $isFull = $placesLeft <= 0;
                $isRegistered = $event['is_registered'] == 1;
            ?>
            <div class="col">
                <div class="card h-100 shadow-sm border-0 position-relative">
                    <!-- Category Badge -->
                    <div class="position-absolute top-0 end-0 p-2">
                        <span class="badge bg-primary">
                            <?php echo htmlspecialchars($event['category']); ?>
                        </span>
                    </div>

                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title pe-5"><?php echo htmlspecialchars($event['titre']); ?></h5>
                        
                        <!-- Description -->
                        <p class="card-text text-muted small mb-3">
                            <?php 
                                $desc = htmlspecialchars($event['description']);
                                echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                            ?>
                        </p>

                        <!-- Event Details -->
                        <div class="mb-3">
                            <p class="card-text mb-2">
                                <i class="bi bi-calendar-event text-primary"></i>
                                <strong>Date:</strong>
                                <?php echo htmlspecialchars($formattedDate); ?>
                            </p>
                            <p class="card-text mb-2">
                                <i class="bi bi-geo-alt text-danger"></i>
                                <strong>Location:</strong>
                                <?php echo htmlspecialchars($event['lieu']); ?>
                            </p>
                        </div>

                        <!-- Registration Status and Places Left -->
                        <div class="mb-3 p-2 bg-light rounded">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">
                                    <i class="bi bi-people"></i>
                                    <strong><?php echo $registeredCount; ?>/<?php echo $maxParticipants; ?></strong> registered
                                </span>
                                <?php if ($isRegistered): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> Registered
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Places Left Progress Bar -->
                            <div class="progress" style="height: 20px;">
                                <div 
                                    class="progress-bar <?php echo $isFull ? 'bg-danger' : 'bg-success'; ?>" 
                                    role="progressbar" 
                                    style="width: <?php echo ($registeredCount / $maxParticipants) * 100; ?>%"
                                    aria-valuenow="<?php echo $registeredCount; ?>" 
                                    aria-valuemin="0" 
                                    aria-valuemax="<?php echo $maxParticipants; ?>"
                                >
                                    <small class="text-white fw-bold">
                                        <?php echo $placesLeft; ?> left
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Status Badge -->
                        <?php if ($isFull): ?>
                            <div class="alert alert-danger alert-sm py-2 mb-3" role="alert">
                                <i class="bi bi-exclamation-circle"></i> Event is full
                            </div>
                        <?php elseif ($placesLeft <= 3): ?>
                            <div class="alert alert-warning alert-sm py-2 mb-3" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> Only <?php echo $placesLeft; ?> place<?php echo $placesLeft !== 1 ? 's' : ''; ?> left!
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Card Footer with Action -->
                    <div class="card-footer bg-white border-top">
                        <a href="event_details.php?id=<?php echo (int) $event['id']; ?>" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-eye"></i> View Details
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>

<div id="no-live-results" class="alert alert-info d-none" role="alert">
    <i class="bi bi-info-circle"></i>
    <strong>No events found.</strong>
</div>

<?php require 'footer.php'; ?>
