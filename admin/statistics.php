<?php
// Set page title
$pageTitle = 'Platform Statistics';

// Include database connection
require_once '../includes/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    // Redirect to login page
    header('Location: ../login.php');
    exit;
}

// Get platform statistics
$stats = [
    'total_users' => 0,
    'total_artists' => 0,
    'total_artworks' => 0,
    'total_sales' => 0,
    'total_revenue' => 0,
    'total_likes' => 0,
    'total_comments' => 0,
    'total_views' => 0
];

try {
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $stats['total_users'] = $stmt->fetchColumn();
    
    // Total artists
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'artist'");
    $stats['total_artists'] = $stmt->fetchColumn();
    
    // Total artworks
    $stmt = $pdo->query("SELECT COUNT(*) FROM artworks");
    $stats['total_artworks'] = $stmt->fetchColumn();
    
    // Total sales and revenue
    $stmt = $pdo->query("
        SELECT COUNT(*) as sales, COALESCE(SUM(oi.price * oi.quantity), 0) as revenue
        FROM order_items oi
    ");
    $salesData = $stmt->fetch();
    $stats['total_sales'] = $salesData['sales'];
    $stats['total_revenue'] = $salesData['revenue'];
    
    // Total likes
    $stmt = $pdo->query("SELECT COUNT(*) FROM likes");
    $stats['total_likes'] = $stmt->fetchColumn();
    
    // Total comments
    $stmt = $pdo->query("SELECT COUNT(*) FROM comments");
    $stats['total_comments'] = $stmt->fetchColumn();
    
    // Total views
    $stmt = $pdo->query("SELECT COALESCE(SUM(views), 0) FROM artworks");
    $stats['total_views'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('Error fetching platform statistics: ' . $e->getMessage());
}

// Get top artists by sales
$topArtistsBySales = [];
try {
    $stmt = $pdo->query("
        SELECT u.user_id, u.username, u.first_name, u.last_name, 
               COUNT(DISTINCT o.order_id) as order_count,
               COUNT(oi.order_item_id) as item_count,
               SUM(oi.price * oi.quantity) as revenue
        FROM users u
        JOIN artworks a ON u.user_id = a.artist_id
        JOIN order_items oi ON a.artwork_id = oi.artwork_id
        JOIN orders o ON oi.order_id = o.order_id
        GROUP BY u.user_id
        ORDER BY revenue DESC
        LIMIT 10
    ");
    $topArtistsBySales = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching top artists by sales: ' . $e->getMessage());
}

// Get top artists by artworks
$topArtistsByArtworks = [];
try {
    $stmt = $pdo->query("
        SELECT u.user_id, u.username, u.first_name, u.last_name, 
               COUNT(a.artwork_id) as artwork_count,
               SUM(a.views) as total_views,
               SUM(a.likes) as total_likes
        FROM users u
        JOIN artworks a ON u.user_id = a.artist_id
        GROUP BY u.user_id
        ORDER BY artwork_count DESC
        LIMIT 10
    ");
    $topArtistsByArtworks = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching top artists by artworks: ' . $e->getMessage());
}

// Get top artworks by views
$topArtworksByViews = [];
try {
    $stmt = $pdo->query("
        SELECT a.artwork_id, a.title, a.image_path, a.views, a.likes, 
               u.username as artist_name, u.user_id as artist_id,
               COUNT(c.comment_id) as comment_count
        FROM artworks a
        JOIN users u ON a.artist_id = u.user_id
        LEFT JOIN comments c ON a.artwork_id = c.artwork_id
        GROUP BY a.artwork_id
        ORDER BY a.views DESC
        LIMIT 10
    ");
    $topArtworksByViews = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching top artworks by views: ' . $e->getMessage());
}

// Get top artworks by sales
$topArtworksBySales = [];
try {
    $stmt = $pdo->query("
        SELECT a.artwork_id, a.title, a.image_path, a.price,
               u.username as artist_name, u.user_id as artist_id,
               COUNT(oi.order_item_id) as sale_count,
               SUM(oi.quantity) as quantity_sold,
               SUM(oi.price * oi.quantity) as revenue
        FROM artworks a
        JOIN users u ON a.artist_id = u.user_id
        JOIN order_items oi ON a.artwork_id = oi.artwork_id
        GROUP BY a.artwork_id
        ORDER BY revenue DESC
        LIMIT 10
    ");
    $topArtworksBySales = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching top artworks by sales: ' . $e->getMessage());
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Platform Statistics</h1>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
    
    <!-- Overview Statistics -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card h-100 bg-primary text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Users</h5>
                    <p class="display-4"><?php echo $stats['total_users']; ?></p>
                    <p class="card-text">
                        Artists: <?php echo $stats['total_artists']; ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100 bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Content</h5>
                    <p class="display-4"><?php echo $stats['total_artworks']; ?></p>
                    <p class="card-text">Total Artworks</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100 bg-info text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Engagement</h5>
                    <p class="display-4"><?php echo number_format($stats['total_views']); ?></p>
                    <p class="card-text">
                        <i class="far fa-heart me-1"></i> <?php echo number_format($stats['total_likes']); ?> Likes<br>
                        <i class="far fa-comment me-1"></i> <?php echo number_format($stats['total_comments']); ?> Comments
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100 bg-warning text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Sales</h5>
                    <p class="display-4"><?php echo number_format($stats['total_sales']); ?></p>
                    <p class="card-text">Revenue: $<?php echo number_format($stats['total_revenue'], 2); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Artists by Sales -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Top Artists by Sales</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($topArtistsBySales)): ?>
            <div class="p-4 text-center">
                <p>No sales data available.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Artist</th>
                            <th>Orders</th>
                            <th>Items Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topArtistsBySales as $index => $artist): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($artist['username']); ?></strong>
                                <?php if (!empty($artist['first_name']) || !empty($artist['last_name'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($artist['first_name'] . ' ' . $artist['last_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $artist['order_count']; ?></td>
                            <td><?php echo $artist['item_count']; ?></td>
                            <td>$<?php echo number_format($artist['revenue'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Top Artists by Artworks -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Top Artists by Artworks</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($topArtistsByArtworks)): ?>
            <div class="p-4 text-center">
                <p>No artwork data available.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Artist</th>
                            <th>Artworks</th>
                            <th>Views</th>
                            <th>Likes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topArtistsByArtworks as $index => $artist): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($artist['username']); ?></strong>
                                <?php if (!empty($artist['first_name']) || !empty($artist['last_name'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($artist['first_name'] . ' ' . $artist['last_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $artist['artwork_count']; ?></td>
                            <td><?php echo number_format($artist['total_views']); ?></td>
                            <td><?php echo number_format($artist['total_likes']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Top Artworks by Views -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Top Artworks by Views</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($topArtworksByViews)): ?>
            <div class="p-4 text-center">
                <p>No artwork view data available.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Artwork</th>
                            <th>Artist</th>
                            <th>Views</th>
                            <th>Likes</th>
                            <th>Comments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topArtworksByViews as $index => $artwork): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="../<?php echo htmlspecialchars($artwork['image_path']); ?>" alt="<?php echo htmlspecialchars($artwork['title']); ?>" class="img-thumbnail me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                    <a href="../artwork.php?id=<?php echo $artwork['artwork_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($artwork['title']); ?>
                                    </a>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($artwork['artist_name']); ?></td>
                            <td><?php echo number_format($artwork['views']); ?></td>
                            <td><?php echo number_format($artwork['likes']); ?></td>
                            <td><?php echo number_format($artwork['comment_count']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Top Artworks by Sales -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Top Artworks by Sales</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($topArtworksBySales)): ?>
            <div class="p-4 text-center">
                <p>No artwork sales data available.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Artwork</th>
                            <th>Artist</th>
                            <th>Price</th>
                            <th>Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topArtworksBySales as $index => $artwork): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="../<?php echo htmlspecialchars($artwork['image_path']); ?>" alt="<?php echo htmlspecialchars($artwork['title']); ?>" class="img-thumbnail me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                    <a href="../artwork.php?id=<?php echo $artwork['artwork_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($artwork['title']); ?>
                                    </a>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($artwork['artist_name']); ?></td>
                            <td>$<?php echo number_format($artwork['price'], 2); ?></td>
                            <td><?php echo number_format($artwork['quantity_sold']); ?></td>
                            <td>$<?php echo number_format($artwork['revenue'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>