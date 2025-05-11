<?php
// Set page title
$pageTitle = 'Search Artworks';

// Include database connection
require_once 'includes/db_connect.php';

// Initialize variables
$query = '';
$category = '';
$sort = 'newest';
$minPrice = '';
$maxPrice = '';
$artworks = [];
$totalResults = 0;
$resultsPerPage = 12;
$currentPage = 1;
$totalPages = 1;

// Get search parameters
if (isset($_GET['q'])) {
    $query = trim($_GET['q']);
}
if (isset($_GET['category'])) {
    $category = (int)$_GET['category'];
}
if (isset($_GET['sort']) && in_array($_GET['sort'], ['newest', 'oldest', 'price_low', 'price_high', 'popular'])) {
    $sort = $_GET['sort'];
}
if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
    $minPrice = (float)$_GET['min_price'];
}
if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
    $maxPrice = (float)$_GET['max_price'];
}
if (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) {
    $currentPage = (int)$_GET['page'];
}

// Build SQL query
$sql = "
    SELECT a.*, u.username as artist_name, c.name as category_name
    FROM artworks a
    JOIN users u ON a.artist_id = u.user_id
    JOIN categories c ON a.category_id = c.category_id
    WHERE 1=1
";

$countSql = "
    SELECT COUNT(*) as total
    FROM artworks a
    JOIN users u ON a.artist_id = u.user_id
    JOIN categories c ON a.category_id = c.category_id
    WHERE 1=1
";

$params = [];

// Add search conditions
if (!empty($query)) {
    $sql .= " AND (a.title LIKE ? OR u.username LIKE ? OR c.name LIKE ?)";
    $countSql .= " AND (a.title LIKE ? OR u.username LIKE ? OR c.name LIKE ?)";
    $searchTerm = "%$query%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($category)) {
    $sql .= " AND a.category_id = ?";
    $countSql .= " AND a.category_id = ?";
    $params[] = $category;
}

if (!empty($minPrice)) {
    $sql .= " AND a.price >= ?";
    $countSql .= " AND a.price >= ?";
    $params[] = $minPrice;
}

if (!empty($maxPrice)) {
    $sql .= " AND a.price <= ?";
    $countSql .= " AND a.price <= ?";
    $params[] = $maxPrice;
}

// Add sorting
switch ($sort) {
    case 'newest':
        $sql .= " ORDER BY a.created_at DESC";
        break;
    case 'oldest':
        $sql .= " ORDER BY a.created_at ASC";
        break;
    case 'price_low':
        $sql .= " ORDER BY a.price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY a.price DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY a.likes DESC, a.views DESC";
        break;
    default:
        $sql .= " ORDER BY a.created_at DESC";
}

// Get total results count
try {
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalResults = $stmt->fetch()['total'];
    $totalPages = ceil($totalResults / $resultsPerPage);
} catch (PDOException $e) {
    error_log('Error counting search results: ' . $e->getMessage());
}

// Add pagination
$offset = ($currentPage - 1) * $resultsPerPage;
$sql .= " LIMIT $resultsPerPage OFFSET $offset";

// Execute query
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $artworks = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error searching artworks: ' . $e->getMessage());
}

// Get all categories for filter
$allCategories = [];
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $allCategories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching categories: ' . $e->getMessage());
}

// Include header
include 'includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4">
        <?php if (!empty($query)): ?>
            Search Results for "<?php echo htmlspecialchars($query); ?>"
        <?php else: ?>
            Browse Artworks
        <?php endif; ?>
    </h1>
    
    <!-- Search and Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="search.php" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="searchQuery" class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchQuery" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search by title, artist, or category">
                </div>
                
                <div class="col-md-3">
                    <label for="categoryFilter" class="form-label">Category</label>
                    <select class="form-select" id="categoryFilter" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($allCategories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>" <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="sortFilter" class="form-label">Sort By</label>
                    <select class="form-select" id="sortFilter" name="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Price Range</label>
                    <div class="row g-2">
                        <div class="col">
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="min_price" value="<?php echo htmlspecialchars($minPrice); ?>" placeholder="Min">
                            </div>
                        </div>
                        <div class="col">
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="max_price" value="<?php echo htmlspecialchars($maxPrice); ?>" placeholder="Max">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 text-end">
                    <label class="form-label d-block">&nbsp;</label>
                    <a href="search.php" class="btn btn-outline-secondary">Clear Filters</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Results Count -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <p class="mb-0">
            <?php if ($totalResults > 0): ?>
                Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $resultsPerPage, $totalResults); ?> of <?php echo $totalResults; ?> results
            <?php else: ?>
                No results found
            <?php endif; ?>
        </p>
    </div>
    
    <!-- Results Grid -->
    <div class="row">
        <?php if (empty($artworks)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                No artworks found matching your criteria. Try adjusting your search filters.
            </div>
        </div>
        <?php else: ?>
            <?php foreach ($artworks as $artwork): ?>
            <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                <div class="card h-100">
                    <img src="<?php echo htmlspecialchars($artwork['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($artwork['title']); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($artwork['title']); ?></h5>
                        <p class="card-text text-muted">by <a href="artist.php?id=<?php echo $artwork['artist_id']; ?>"><?php echo htmlspecialchars($artwork['artist_name']); ?></a></p>
                        <p class="card-text artwork-price">$<?php echo number_format($artwork['price'], 2); ?></p>
                        <a href="artwork.php?id=<?php echo $artwork['artwork_id']; ?>" class="btn btn-outline-primary">View Details</a>
                    </div>
                    <div class="card-footer bg-white">
                        <small class="text-muted">
                            <i class="far fa-eye me-1"></i> <?php echo $artwork['views']; ?> views
                            <i class="far fa-heart ms-3 me-1"></i> <?php echo $artwork['likes']; ?> likes
                            <span class="float-end">
                                <a href="category.php?id=<?php echo $artwork['category_id']; ?>" class="category-pill"><?php echo htmlspecialchars($artwork['category_name']); ?></a>
                            </span>
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Search results pages" class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo '?' . http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            
            <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
            <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                <a class="page-link" href="<?php echo '?' . http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; ?>
            
            <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo '?' . http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>