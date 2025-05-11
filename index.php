<?php
// Set page title
$pageTitle = 'Home';

// Include database connection
require_once 'includes/db_connect.php';

// Get latest artworks
$latestArtworks = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, u.username as artist_name, c.name as category_name
        FROM artworks a
        JOIN users u ON a.artist_id = u.user_id
        JOIN categories c ON a.category_id = c.category_id
        ORDER BY a.created_at DESC
        LIMIT 6
    ");
    $latestArtworks = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle error
    error_log('Error fetching latest artworks: ' . $e->getMessage());
}

// Get top artworks (most liked)
$topArtworks = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, u.username as artist_name, c.name as category_name, COUNT(l.artwork_id) as like_count
        FROM artworks a
        JOIN users u ON a.artist_id = u.user_id
        JOIN categories c ON a.category_id = c.category_id
        LEFT JOIN likes l ON a.artwork_id = l.artwork_id
        GROUP BY a.artwork_id
        ORDER BY like_count DESC, a.views DESC
        LIMIT 6
    ");
    $topArtworks = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle error
    error_log('Error fetching top artworks: ' . $e->getMessage());
}

// Get categories with artwork count
$categories = [];
try {
    $stmt = $pdo->query("
        SELECT c.*, COUNT(a.artwork_id) as artwork_count
        FROM categories c
        LEFT JOIN artworks a ON c.category_id = a.category_id
        GROUP BY c.category_id
        ORDER BY artwork_count DESC
        LIMIT 8
    ");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    // Handle error
    error_log('Error fetching categories: ' . $e->getMessage());
}

// Include header
include 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero text-center">
    <div class="container">
        <h1>Discover & Collect Digital Art</h1>
        <p class="lead">ArtLoop is the premier platform for digital artists to showcase and sell their unique creations.</p>
        <div class="mt-4">
            <a href="search.php" class="btn btn-light btn-lg me-2">Explore Artworks</a>
            <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="register.php" class="btn btn-outline-light btn-lg">Join as Artist</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Latest Artworks Section -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Latest Artworks</h2>
            <a href="search.php?sort=newest" class="btn btn-outline-primary">View All</a>
        </div>
        
        <div class="row">
            <?php if (empty($latestArtworks)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No artworks available yet. Be the first to upload your artwork!
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($latestArtworks as $artwork): ?>
                <div class="col-md-6 col-lg-4">
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
    </div>
</section>

<!-- Top Artworks Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Top Artworks of the Week</h2>
            <a href="search.php?sort=popular" class="btn btn-outline-primary">View All</a>
        </div>
        
        <div class="row">
            <?php if (empty($topArtworks)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No artworks available yet. Be the first to upload your artwork!
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($topArtworks as $artwork): ?>
                <div class="col-md-6 col-lg-4">
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
    </div>
</section>

<!-- Categories Section -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Browse by Category</h2>
            <a href="categories.php" class="btn btn-outline-primary">View All</a>
        </div>
        
        <div class="row">
            <?php if (empty($categories)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No categories available yet.
                </div>
            </div>
            <?php else: ?>
                <?php foreach ($categories as $category): ?>
                <div class="col-md-6 col-lg-3 mb-4">
                    <a href="category.php?id=<?php echo $category['category_id']; ?>" class="text-decoration-none">
                        <div class="card h-100 text-center">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                                <p class="card-text text-muted"><?php echo $category['artwork_count']; ?> artworks</p>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Call to Action Section -->
<section class="py-5 bg-primary text-white text-center">
    <div class="container">
        <h2>Ready to Showcase Your Art?</h2>
        <p class="lead mb-4">Join thousands of artists who are selling their unique digital creations on ArtLoop.</p>
        <?php if (!isset($_SESSION['user_id'])): ?>
        <a href="register.php" class="btn btn-light btn-lg">Create an Account</a>
        <?php else: ?>
        <a href="upload.php" class="btn btn-light btn-lg">Upload Artwork</a>
        <?php endif; ?>
    </div>
</section>

<?php
// Include footer
include 'includes/footer.php';
?>