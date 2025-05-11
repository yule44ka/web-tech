<?php
// Set page title (will be updated with category name)
$pageTitle = 'Category';

// Include database connection
require_once 'includes/db_connect.php';

// Check if category ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirect to search page if no valid category ID
    header('Location: search.php');
    exit;
}

$categoryId = (int)$_GET['id'];

// Get category details
$category = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();

    if (!$category) {
        // Category not found, redirect to search page
        header('Location: search.php');
        exit;
    }

    // Update page title with category name
    $pageTitle = htmlspecialchars($category['name'] ?? '') . ' - Category';
} catch (PDOException $e) {
    error_log('Error fetching category: ' . $e->getMessage());
    // Redirect to search page on error
    header('Location: search.php');
    exit;
}

// Initialize variables for pagination
$resultsPerPage = 12;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$sort = isset($_GET['sort']) && in_array($_GET['sort'], ['newest', 'oldest', 'price_low', 'price_high', 'popular']) ? $_GET['sort'] : 'newest';

// Get total artworks count for this category
$totalResults = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM artworks WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $totalResults = $stmt->fetch()['total'];
    $totalPages = ceil($totalResults / $resultsPerPage);
} catch (PDOException $e) {
    error_log('Error counting category artworks: ' . $e->getMessage());
}

// Build SQL query with sorting
$sql = "
    SELECT a.*, u.username as artist_name
    FROM artworks a
    JOIN users u ON a.artist_id = u.user_id
    WHERE a.category_id = ?
";

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

// Add pagination
$offset = ($currentPage - 1) * $resultsPerPage;
$sql .= " LIMIT $resultsPerPage OFFSET $offset";

// Get artworks for this category
$artworks = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$categoryId]);
    $artworks = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching category artworks: ' . $e->getMessage());
}

// Include header
include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><?php echo htmlspecialchars($category['name'] ?? ''); ?></h1>
            <?php if (isset($category['description']) && !empty($category['description'])): ?>
            <p class="lead"><?php echo htmlspecialchars($category['description']); ?></p>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-md-end">
            <div class="d-flex justify-content-md-end align-items-center">
                <label for="sortFilter" class="me-2">Sort By:</label>
                <select class="form-select w-auto" id="sortFilter" onchange="window.location.href='?id=<?php echo $categoryId; ?>&sort='+this.value">
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Results Count -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <p class="mb-0">
            <?php if ($totalResults > 0): ?>
                Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $resultsPerPage, $totalResults); ?> of <?php echo $totalResults; ?> artworks
            <?php else: ?>
                No artworks found in this category
            <?php endif; ?>
        </p>
    </div>

    <!-- Results Grid -->
    <div class="row">
        <?php if (empty($artworks)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                No artworks available in this category yet. 
                <?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] === 'artist' || $_SESSION['role'] === 'admin')): ?>
                <a href="upload.php?category=<?php echo $categoryId; ?>" class="alert-link">Be the first to upload!</a>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
            <?php foreach ($artworks as $artwork): ?>
            <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                <div class="card h-100">
                    <img src="<?php echo htmlspecialchars($artwork['image_path'] ?? ''); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($artwork['title'] ?? ''); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($artwork['title'] ?? ''); ?></h5>
                        <p class="card-text text-muted">by <a href="artist.php?id=<?php echo $artwork['artist_id'] ?? 0; ?>"><?php echo htmlspecialchars($artwork['artist_name'] ?? ''); ?></a></p>
                        <p class="card-text artwork-price">$<?php echo number_format($artwork['price'] ?? 0, 2); ?></p>
                        <a href="artwork.php?id=<?php echo $artwork['artwork_id'] ?? 0; ?>" class="btn btn-outline-primary">View Details</a>
                    </div>
                    <div class="card-footer bg-white">
                        <small class="text-muted">
                            <i class="far fa-eye me-1"></i> <?php echo $artwork['views'] ?? 0; ?> views
                            <i class="far fa-heart ms-3 me-1"></i> <?php echo $artwork['likes'] ?? 0; ?> likes
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Category pages" class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo '?id=' . $categoryId . '&sort=' . $sort . '&page=' . ($currentPage - 1); ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>

            <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
            <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                <a class="page-link" href="<?php echo '?id=' . $categoryId . '&sort=' . $sort . '&page=' . $i; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; ?>

            <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo '?id=' . $categoryId . '&sort=' . $sort . '&page=' . ($currentPage + 1); ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

    <!-- Related Categories -->
    <div class="mt-5">
        <h3>Explore Other Categories</h3>
        <div class="row mt-3">
            <?php
            try {
                $stmt = $pdo->prepare("
                    SELECT c.*, COUNT(a.artwork_id) as artwork_count
                    FROM categories c
                    LEFT JOIN artworks a ON c.category_id = a.category_id
                    WHERE c.category_id != ?
                    GROUP BY c.category_id
                    ORDER BY artwork_count DESC
                    LIMIT 4
                ");
                $stmt->execute([$categoryId]);
                $relatedCategories = $stmt->fetchAll();

                foreach ($relatedCategories as $relatedCategory):
            ?>
            <div class="col-md-6 col-lg-3 mb-3">
                <a href="category.php?id=<?php echo $relatedCategory['category_id'] ?? 0; ?>" class="text-decoration-none">
                    <div class="card h-100 text-center">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($relatedCategory['name'] ?? ''); ?></h5>
                            <p class="card-text text-muted"><?php echo $relatedCategory['artwork_count'] ?? 0; ?> artworks</p>
                        </div>
                    </div>
                </a>
            </div>
            <?php
                endforeach;
            } catch (PDOException $e) {
                error_log('Error fetching related categories: ' . $e->getMessage());
            }
            ?>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>
