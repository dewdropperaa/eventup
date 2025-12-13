<?php
session_start();

require 'database.php';
require_once 'role_check.php';

$pdo = getDatabaseConnection();
$user_id = $_SESSION['user_id'] ?? null;

// Use app header for logged-in users
if ($user_id) {
    $useAppHeader = true;
    $activeNav = 'browse';
    $pageTitle = 'EventUp - Parcourir les Événements';
}

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

$additionalHeadContent = '<link href="assets/css/modern.css" rel="stylesheet">
<style>
.section-title {
    font-size: 28px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.subsection-title {
    font-size: 20px;
    font-weight: 600;
    color: #34495e;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.alert-sm {
    font-size: 13px;
    padding: 8px 12px;
}
</style>';
require 'header.php';

// Get user's organizer and admin events if logged in
$organizerEvents = [];
$adminEvents = [];
if ($user_id) {
    try {
        // Get events where user is organizer
        $stmt = $pdo->prepare("
            SELECT e.id, e.titre, e.description, e.date, e.lieu, e.nb_max_participants, e.category,
                   COUNT(DISTINCT r.id) as registered_count,
                   (e.nb_max_participants - COUNT(DISTINCT r.id)) as places_left
            FROM events e
            INNER JOIN event_roles er ON e.id = er.event_id
            LEFT JOIN registrations r ON e.id = r.event_id
            WHERE er.user_id = :user_id AND er.role = 'organizer'
            GROUP BY e.id
            ORDER BY e.date DESC
        ");
        $stmt->execute([':user_id' => $user_id]);
        $organizerEvents = $stmt->fetchAll();
        
        // Get events where user is admin
        $stmt = $pdo->prepare("
            SELECT e.id, e.titre, e.description, e.date, e.lieu, e.nb_max_participants, e.category,
                   COUNT(DISTINCT r.id) as registered_count,
                   (e.nb_max_participants - COUNT(DISTINCT r.id)) as places_left
            FROM events e
            INNER JOIN event_roles er ON e.id = er.event_id
            LEFT JOIN registrations r ON e.id = r.event_id
            WHERE er.user_id = :user_id AND er.role = 'admin'
            GROUP BY e.id
            ORDER BY e.date DESC
        ");
        $stmt->execute([':user_id' => $user_id]);
        $adminEvents = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Error fetching user events: ' . $e->getMessage());
    }
}
?>

<div class="container my-5">
    <?php if ($user_id && (!empty($organizerEvents) || !empty($adminEvents))): ?>
        <!-- My Events Section for Organizers/Admins -->
        <div class="row mb-5">
            <div class="col-12">
                <h2 class="section-title mb-4">
                    <i class="bi bi-star text-primary"></i>
                    Mes Événements
                </h2>
                
                <?php if (!empty($adminEvents)): ?>
                    <div class="mb-4">
                        <h3 class="subsection-title">
                            <i class="bi bi-shield-check text-danger"></i>
                            Mes Événements (Admin)
                            <span class="badge bg-danger ms-2"><?php echo count($adminEvents); ?></span>
                        </h3>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php foreach ($adminEvents as $event): ?>
                                <?php include 'event_card_template.php'; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($organizerEvents)): ?>
                    <div class="mb-4">
                        <h3 class="subsection-title">
                            <i class="bi bi-people text-success"></i>
                            Mes Événements (Organizer)
                            <span class="badge bg-success ms-2"><?php echo count($organizerEvents); ?></span>
                        </h3>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php foreach ($organizerEvents as $event): ?>
                                <?php include 'event_card_template.php'; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <hr class="my-5">
        
    <?php endif; ?>
    
    <!-- Browse All Events Section -->
    <div class="row mb-4">
        <div class="col-12 text-center">
            <h1 class="page-title">
                <?php echo $user_id ? 'Parcourir Tous les Événements' : 'Événements à Venir'; ?>
            </h1>
            <p class="text-muted">Découvrez et inscrivez-vous aux événements</p>
        </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="filter-card mb-4">
        <form method="GET" action="index.php" class="row g-3 align-items-end">
            <div class="col-lg-12 mb-3">
                <label for="live-search-input" class="form-label fw-bold">Rechercher</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" id="live-search-input" class="form-control" placeholder="Tapez pour rechercher des événements à venir..." value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                </div>
            </div>

            <div class="col-md-3">
                <label class="form-label">Catégorie</label>
                <select class="form-select" name="category">
                    <option value="">Toutes les Catégories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Lieu</label>
                <select class="form-select" name="location">
                    <option value="">Tous les Lieux</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $location === $loc ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Période</label>
                <select class="form-select" name="date_filter">
                    <option value="">Toutes les Dates</option>
                    <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Aujourd'hui</option>
                    <option value="this_week" <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>>Cette Semaine</option>
                    <option value="this_month" <?php echo $date_filter === 'this_month' ? 'selected' : ''; ?>>Ce Mois</option>
                    <option value="next_month" <?php echo $date_filter === 'next_month' ? 'selected' : ''; ?>>Mois Prochain</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Trier par</label>
                <select class="form-select" name="sort">
                    <option value="nearest_date" <?php echo $sort === 'nearest_date' ? 'selected' : ''; ?>>Date la Plus Proche</option>
                    <option value="most_popular" <?php echo $sort === 'most_popular' ? 'selected' : ''; ?>>Plus Populaire</option>
                    <option value="alphabetical" <?php echo $sort === 'alphabetical' ? 'selected' : ''; ?>>Alphabétique</option>
                    <option value="recently_added" <?php echo $sort === 'recently_added' ? 'selected' : ''; ?>>Récemment Ajouté</option>
                </select>
            </div>

            <div class="col-md-2 d-flex">
                <button type="submit" class="btn btn-primary w-100 me-2"><i class="bi bi-funnel"></i> Appliquer</button>
                <a href="index.php" class="btn btn-secondary w-100"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>

    <div id="live-search-results" class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 d-none"></div>

    <div id="original-event-list">
        <!-- Results Section -->
        <?php if (empty($events)): ?>
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle"></i>
                <strong>Aucun événement trouvé.</strong> Essayez d'ajuster vos filtres de recherche ou revenez plus tard pour de nouveaux événements.
            </div>
        <?php else: ?>
            <div class="row mb-3">
                <div class="col-md-12">
                    <p class="text-muted">
                        <strong><?php echo count($events); ?></strong> événement<?php echo count($events) !== 1 ? 's' : ''; ?> trouvé<?php echo count($events) !== 1 ? 's' : ''; ?>
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
                        <div class="card h-100 shadow-sm position-relative">
                            <!-- Category Badge -->
                            <div class="position-absolute top-0 end-0 p-2">
                                <span class="badge bg-primary text-white">
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
                                        <strong>Lieu:</strong>
                                        <?php echo htmlspecialchars($event['lieu']); ?>
                                    </p>
                                </div>

                                <!-- Registration Status and Places Left -->
                                <div class="mb-3 p-2 bg-light rounded">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-muted small">
                                            <i class="bi bi-people"></i>
                                            <strong><?php echo $registeredCount; ?>/<?php echo $maxParticipants; ?></strong> inscrits
                                        </span>
                                        <?php if ($isRegistered): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle"></i> Inscrit
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
                                                <?php echo $placesLeft; ?> restant<?php echo $placesLeft !== 1 ? 's' : ''; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status Badge -->
                                <?php if ($isFull): ?>
                                    <div class="alert alert-danger alert-sm py-2 mb-3" role="alert">
                                        <i class="bi bi-exclamation-circle"></i> L'événement est complet
                                    </div>
                                <?php elseif ($placesLeft <= 3): ?>
                                    <div class="alert alert-warning alert-sm py-2 mb-3" role="alert">
                                        <i class="bi bi-exclamation-triangle"></i> Seulement <?php echo $placesLeft; ?> place<?php echo $placesLeft !== 1 ? 's' : ''; ?> restante<?php echo $placesLeft !== 1 ? 's' : ''; ?>!
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Card Footer with Action -->
                            <div class="card-footer bg-white border-top">
                                <a href="event_details.php?id=<?php echo (int) $event['id']; ?>" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-eye"></i> Voir les Détails
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
</div>

<?php require 'footer.php'; ?>
