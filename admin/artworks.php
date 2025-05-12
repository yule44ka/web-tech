<?php
// Set page title
$pageTitle = 'Manage Artworks';

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

$message = '';
$messageType = '';

// Handle artwork deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $artworkId = (int)$_GET['delete'];
    
    try {
        // Get artwork details first (for image path)
        $stmt = $pdo->prepare("SELECT * FROM artworks WHERE artwork_id = ?");
        $stmt->execute([$artworkId]);
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
            
            // Delete from order items (if possible, or mark as deleted)
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE artwork_id = ?");
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
            $message = 'Artwork not found.';
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

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = '';
$searchParams = [];

if (!empty($search)) {
    $searchCondition = "WHERE a.title LIKE ? OR u.username LIKE ?";
    $searchParams = ["%$search%", "%$search%"];
}

// Get total artworks count for pagination
try {
    if (!empty($searchCondition)) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM artworks a
            JOIN users u ON a.artist_id = u.user_id
            $searchCondition
        ");
        $stmt->execute($searchParams);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) FROM artworks");
    }
    $totalArtworks = $stmt->fetchColumn();
    $totalPages = ceil($totalArtworks / $perPage);
} catch (PDOException $e) {
    error_log('Error counting artworks: ' . $e->getMessage());
    $totalArtworks = 0;
    $totalPages = 1;
}

// Get artworks with pagination
$artworks = [];
try {
    $query = "
        SELECT a.*, c.name as category_name, u.username as artist_name,
               (SELECT COUNT(*) FROM likes WHERE artwork_id = a.artwork_id) as like_count,
               (SELECT COUNT(*) FROM comments WHERE artwork_id = a.artwork_id) as comment_count,
               (SELECT COUNT(*) FROM order_items WHERE artwork_id = a.artwork_id) as sale_count
        FROM artworks a
        JOIN categories c ON a.category_id = c.category_id
        JOIN users u ON a.artist_id = u.user_id
        $searchCondition
        ORDER BY a.created_at DESC
        LIMIT $perPage OFFSET $offset
    ";
    
    if (!empty($searchCondition)) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($searchParams);
    } else {
        $stmt = $pdo->query($query);
    }
    
    $artworks = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching artworks: ' . $e->getMessage());
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Manage Artworks</h1>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
    
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>" role="alert">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="artworks.php" method="GET" class="row g-3">
                <div class="col-md-10">
                    <input type="text" class="form-control" id="search" name="search" placeholder="Search by artwork title or artist name" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Artworks Table -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">All Artworks (<?php echo $totalArtworks; ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($artworks)): ?>
            <div class="p-4 text-center">
                <p>No artworks found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Artwork</th>
                            <th>Artist</th>
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
                            <td><?php echo htmlspecialchars($artwork['artist_name']); ?></td>
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
                                <a href="artworks.php?delete=<?php echo $artwork['artwork_id']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this artwork? This action cannot be undone.');">
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
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Artwork pagination">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; ?>
            
            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>