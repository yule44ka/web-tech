<?php
// Set page title
$pageTitle = 'Artist Dashboard';

// Include database connection
require_once '../includes/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an artist or admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'artist' && $_SESSION['role'] != 'admin')) {
    // Redirect to login page
    header('Location: ../login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle artwork deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $artworkId = (int)$_GET['delete'];
    
    try {
        // Check if artwork belongs to the user
        $stmt = $pdo->prepare("SELECT * FROM artworks WHERE artwork_id = ? AND artist_id = ?");
        $stmt->execute([$artworkId, $userId]);
        $artwork = $stmt->fetch();
        
        if ($artwork) {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Delete artwork tags
            $stmt = $pdo->prepare("DELETE FROM artwork_tags WHERE artwork_id = ?");
            $stmt->execute([$artworkId]);
            
            // Delete artwork likes
            $stmt = $pdo->prepare("DELETE FROM likes WHERE artwork_id = ?");
            $stmt->execute([$artworkId]);
            
            // Delete artwork comments
            $stmt = $pdo->prepare("DELETE FROM comments WHERE artwork_id = ?");
            $stmt->execute([$artworkId]);
            
            // Delete artwork from cart items
            $stmt = $pdo->prepare("DELETE FROM cart_items WHERE artwork_id = ?");
            $stmt->execute([$artworkId]);
            
            // Delete artwork
            $stmt = $pdo->prepare("DELETE FROM artworks WHERE artwork_id = ?");
            $stmt->execute([$artworkId]);
            
            // Delete artwork image file
            if (file_exists('../' . $artwork['image_path'])) {
                unlink('../' . $artwork['image_path']);
            }
            
            // Commit transaction
            $pdo->commit();
            
            $message = 'Artwork deleted successfully.';
            $messageType = 'success';
        } else {
            $message = 'You do not have permission to delete this artwork.';
            $messageType = 'danger';
        }
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log('Error deleting artwork: ' . $e->getMessage());
        $message = 'An error occurred while deleting the artwork. Please try again.';
        $messageType = 'danger';
    }
}

// Get artist's artworks
$artworks = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, c.name as category_name, 
               (SELECT COUNT(*) FROM likes WHERE artwork_id = a.artwork_id) as like_count,
               (SELECT COUNT(*) FROM comments WHERE artwork_id = a.artwork_id) as comment_count,
               (SELECT COUNT(*) FROM order_items WHERE artwork_id = a.artwork_id) as sale_count
        FROM artworks a
        JOIN categories c ON a.category_id = c.category_id
        WHERE a.artist_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$userId]);
    $artworks = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching artworks: ' . $e->getMessage());
}

// Get artist statistics
$stats = [
    'total_artworks' => 0,
    'total_views' => 0,
    'total_likes' => 0,
    'total_comments' => 0,
    'total_sales' => 0,
    'total_revenue' => 0
];

try {
    // Total artworks
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM artworks WHERE artist_id = ?");
    $stmt->execute([$userId]);
    $stats['total_artworks'] = $stmt->fetchColumn();
    
    // Total views
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(views), 0) FROM artworks WHERE artist_id = ?");
    $stmt->execute([$userId]);
    $stats['total_views'] = $stmt->fetchColumn();
    
    // Total likes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM likes 
        WHERE artwork_id IN (SELECT artwork_id FROM artworks WHERE artist_id = ?)
    ");
    $stmt->execute([$userId]);
    $stats['total_likes'] = $stmt->fetchColumn();
    
    // Total comments
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM comments 
        WHERE artwork_id IN (SELECT artwork_id FROM artworks WHERE artist_id = ?)
    ");
    $stmt->execute([$userId]);
    $stats['total_comments'] = $stmt->fetchColumn();
    
    // Total sales and revenue
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as sales, COALESCE(SUM(oi.price * oi.quantity), 0) as revenue
        FROM order_items oi
        JOIN artworks a ON oi.artwork_id = a.artwork_id
        WHERE a.artist_id = ?
    ");
    $stmt->execute([$userId]);
    $salesData = $stmt->fetch();
    $stats['total_sales'] = $salesData['sales'];
    $stats['total_revenue'] = $salesData['revenue'];
} catch (PDOException $e) {
    error_log('Error fetching artist statistics: ' . $e->getMessage());
}

// Get recent activity (likes, comments, sales)
$recentActivity = [];
try {
    // Recent likes
    $stmt = $pdo->prepare("
        SELECT l.created_at, u.username, a.title, a.artwork_id, 'like' as activity_type
        FROM likes l
        JOIN users u ON l.user_id = u.user_id
        JOIN artworks a ON l.artwork_id = a.artwork_id
        WHERE a.artist_id = ?
        ORDER BY l.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentLikes = $stmt->fetchAll();
    
    // Recent comments
    $stmt = $pdo->prepare("
        SELECT c.created_at, u.username, a.title, a.artwork_id, 'comment' as activity_type, c.content
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        JOIN artworks a ON c.artwork_id = a.artwork_id
        WHERE a.artist_id = ?
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentComments = $stmt->fetchAll();
    
    // Recent sales
    $stmt = $pdo->prepare("
        SELECT o.created_at, a.title, a.artwork_id, 'sale' as activity_type, oi.price, oi.quantity
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        JOIN artworks a ON oi.artwork_id = a.artwork_id
        WHERE a.artist_id = ?
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentSales = $stmt->fetchAll();
    
    // Combine and sort by date
    $recentActivity = array_merge($recentLikes, $recentComments, $recentSales);
    usort($recentActivity, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $recentActivity = array_slice($recentActivity, 0, 10);
} catch (PDOException $e) {
    error_log('Error fetching recent activity: ' . $e->getMessage());
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4">Artist Dashboard</h1>
    
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>" role="alert">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card h-100 bg-primary text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Artworks</h5>
                    <p class="display-4"><?php echo $stats['total_artworks']; ?></p>
                    <p class="card-text">Total artworks uploaded</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card h-100 bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Engagement</h5>
                    <p class="display-4"><?php echo $stats['total_views']; ?></p>
                    <p class="card-text">
                        <i class="far fa-eye me-1"></i> Views
                        <i class="far fa-heart ms-3 me-1"></i> <?php echo $stats['total_likes']; ?> Likes
                        <i class="far fa-comment ms-3 me-1"></i> <?php echo $stats['total_comments']; ?> Comments
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card h-100 bg-info text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Sales</h5>
                    <p class="display-4"><?php echo $stats['total_sales']; ?></p>
                    <p class="card-text">Total Revenue: $<?php echo number_format($stats['total_revenue'], 2); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="mb-4">
        <a href="../upload.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Upload New Artwork
        </a>
        <a href="../profile.php" class="btn btn-outline-secondary ms-2">
            <i class="fas fa-user me-2"></i>Edit Profile
        </a>
    </div>
    
    <!-- Artworks Table -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">My Artworks</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($artworks)): ?>
            <div class="p-4 text-center">
                <p class="mb-3">You haven't uploaded any artworks yet.</p>
                <a href="../upload.php" class="btn btn-primary">Upload Your First Artwork</a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Artwork</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stats</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($artworks as $artwork): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="../<?php echo htmlspecialchars($artwork['image_path']); ?>" alt="<?php echo htmlspecialchars($artwork['title']); ?>" class="img-thumbnail me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                    <div>
                                        <a href="../artwork.php?id=<?php echo $artwork['artwork_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($artwork['title']); ?>
                                        </a>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($artwork['category_name']); ?></td>
                            <td>$<?php echo number_format($artwork['price'], 2); ?></td>
                            <td>
                                <small class="text-muted">
                                    <i class="far fa-eye me-1"></i> <?php echo $artwork['views']; ?> views<br>
                                    <i class="far fa-heart me-1"></i> <?php echo $artwork['like_count']; ?> likes<br>
                                    <i class="far fa-comment me-1"></i> <?php echo $artwork['comment_count']; ?> comments<br>
                                    <i class="fas fa-shopping-cart me-1"></i> <?php echo $artwork['sale_count']; ?> sales
                                </small>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($artwork['created_at'])); ?></td>
                            <td>
                                <a href="../artwork.php?id=<?php echo $artwork['artwork_id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="../edit_artwork.php?id=<?php echo $artwork['artwork_id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="dashboard.php?delete=<?php echo $artwork['artwork_id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this artwork? This action cannot be undone.');">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">Recent Activity</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($recentActivity)): ?>
            <div class="p-4 text-center">
                <p>No recent activity to display.</p>
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($recentActivity as $activity): ?>
                <div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">
                            <?php if ($activity['activity_type'] === 'like'): ?>
                                <i class="fas fa-heart text-danger me-2"></i> <?php echo htmlspecialchars($activity['username']); ?> liked your artwork "<?php echo htmlspecialchars($activity['title']); ?>"
                            <?php elseif ($activity['activity_type'] === 'comment'): ?>
                                <i class="fas fa-comment text-primary me-2"></i> <?php echo htmlspecialchars($activity['username']); ?> commented on your artwork "<?php echo htmlspecialchars($activity['title']); ?>"
                            <?php elseif ($activity['activity_type'] === 'sale'): ?>
                                <i class="fas fa-shopping-cart text-success me-2"></i> Your artwork "<?php echo htmlspecialchars($activity['title']); ?>" was purchased (<?php echo $activity['quantity']; ?> × $<?php echo number_format($activity['price'], 2); ?>)
                            <?php endif; ?>
                        </h6>
                        <small class="text-muted"><?php echo date('M j, Y g:i a', strtotime($activity['created_at'])); ?></small>
                    </div>
                    <?php if ($activity['activity_type'] === 'comment' && isset($activity['content'])): ?>
                    <p class="mb-1 text-muted small">"<?php echo htmlspecialchars(substr($activity['content'], 0, 100)); ?><?php echo strlen($activity['content']) > 100 ? '...' : ''; ?>"</p>
                    <?php endif; ?>
                    <a href="../artwork.php?id=<?php echo $activity['artwork_id']; ?>" class="small">View Artwork</a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>