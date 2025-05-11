<?php
// Set page title (will be updated with artwork title)
$pageTitle = 'Artwork Details';

// Include database connection
require_once 'includes/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if artwork ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirect to home page if no valid artwork ID
    header('Location: index.php');
    exit;
}

$artworkId = (int)$_GET['id'];
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Get artwork details
$artwork = null;
$isLiked = false;
try {
    // Increment view count
    $stmt = $pdo->prepare("UPDATE artworks SET views = views + 1 WHERE artwork_id = ?");
    $stmt->execute([$artworkId]);
    
    // Get artwork details with artist info and category
    $stmt = $pdo->prepare("
        SELECT a.*, u.user_id as artist_id, u.username as artist_name, u.bio as artist_bio, 
               u.profile_image as artist_image, c.name as category_name, c.category_id
        FROM artworks a
        JOIN users u ON a.artist_id = u.user_id
        JOIN categories c ON a.category_id = c.category_id
        WHERE a.artwork_id = ?
    ");
    $stmt->execute([$artworkId]);
    $artwork = $stmt->fetch();
    
    if (!$artwork) {
        // Artwork not found, redirect to home page
        header('Location: index.php');
        exit;
    }
    
    // Update page title with artwork title
    $pageTitle = htmlspecialchars($artwork['title']) . ' - Artwork';
    
    // Check if user has liked this artwork
    if ($userId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE user_id = ? AND artwork_id = ?");
        $stmt->execute([$userId, $artworkId]);
        $isLiked = $stmt->fetchColumn() > 0;
    }
    
    // Get similar artworks (same category, different artwork)
    $stmt = $pdo->prepare("
        SELECT a.*, u.username as artist_name
        FROM artworks a
        JOIN users u ON a.artist_id = u.user_id
        WHERE a.category_id = ? AND a.artwork_id != ?
        ORDER BY a.likes DESC
        LIMIT 4
    ");
    $stmt->execute([$artwork['category_id'], $artworkId]);
    $similarArtworks = $stmt->fetchAll();
    
    // Get comments for this artwork
    $stmt = $pdo->prepare("
        SELECT c.*, u.username, u.profile_image
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.artwork_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$artworkId]);
    $comments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Error fetching artwork: ' . $e->getMessage());
    // Redirect to home page on error
    header('Location: index.php');
    exit;
}

// Handle comment submission
$commentError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment']) && $userId) {
    $comment = trim($_POST['comment']);
    
    if (empty($comment)) {
        $commentError = 'Comment cannot be empty';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO comments (artwork_id, user_id, content)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$artworkId, $userId, $comment]);
            
            // Redirect to prevent form resubmission
            header('Location: artwork.php?id=' . $artworkId . '#comments');
            exit;
        } catch (PDOException $e) {
            error_log('Error adding comment: ' . $e->getMessage());
            $commentError = 'Failed to add comment. Please try again.';
        }
    }
}

// Include header
include 'includes/header.php';

// Add extra JavaScript for this page
$extraJS = '<script src="/js/artwork.js"></script>';
?>

<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="category.php?id=<?php echo $artwork['category_id']; ?>"><?php echo htmlspecialchars($artwork['category_name']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($artwork['title']); ?></li>
        </ol>
    </nav>
    
    <div class="row">
        <!-- Artwork Image -->
        <div class="col-md-6 mb-4">
            <img src="<?php echo htmlspecialchars($artwork['image_path']); ?>" class="img-fluid artwork-image" alt="<?php echo htmlspecialchars($artwork['title']); ?>">
        </div>
        
        <!-- Artwork Details -->
        <div class="col-md-6 mb-4">
            <div class="artwork-info">
                <h1><?php echo htmlspecialchars($artwork['title']); ?></h1>
                
                <!-- Artist Info -->
                <div class="artist-info">
                    <?php if (!empty($artwork['artist_image'])): ?>
                    <img src="<?php echo htmlspecialchars($artwork['artist_image']); ?>" class="artist-avatar" alt="<?php echo htmlspecialchars($artwork['artist_name']); ?>">
                    <?php else: ?>
                    <div class="artist-avatar bg-secondary d-flex align-items-center justify-content-center text-white">
                        <i class="fas fa-user"></i>
                    </div>
                    <?php endif; ?>
                    <div>
                        <p class="artist-name">by <a href="artist.php?id=<?php echo $artwork['artist_id']; ?>"><?php echo htmlspecialchars($artwork['artist_name']); ?></a></p>
                        <p class="text-muted small mb-0">
                            <i class="far fa-eye me-1"></i> <?php echo $artwork['views']; ?> views
                            <i class="far fa-heart ms-3 me-1"></i> <?php echo $artwork['likes']; ?> likes
                        </p>
                    </div>
                </div>
                
                <!-- Price and Buy Button -->
                <div class="d-flex align-items-center mb-3">
                    <p class="artwork-price mb-0">$<?php echo number_format($artwork['price'], 2); ?></p>
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $artwork['artist_id']): ?>
                    <button class="btn btn-primary ms-3 add-to-cart" data-artwork-id="<?php echo $artwork['artwork_id']; ?>">
                        <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                    </button>
                    <?php endif; ?>
                </div>
                
                <!-- Like Button -->
                <?php if (isset($_SESSION['user_id'])): ?>
                <button class="btn btn-outline-danger mb-3 like-artwork <?php echo $isLiked ? 'liked' : ''; ?>" data-artwork-id="<?php echo $artwork['artwork_id']; ?>">
                    <i class="<?php echo $isLiked ? 'fas' : 'far'; ?> fa-heart me-1"></i>
                    <span class="like-text"><?php echo $isLiked ? 'Liked' : 'Like'; ?></span>
                    <span class="like-count"><?php echo $artwork['likes']; ?></span>
                </button>
                <?php endif; ?>
                
                <!-- Category -->
                <p class="mb-3">
                    <span class="text-muted">Category:</span>
                    <a href="category.php?id=<?php echo $artwork['category_id']; ?>" class="category-pill">
                        <?php echo htmlspecialchars($artwork['category_name']); ?>
                    </a>
                </p>
                
                <!-- Description -->
                <h5>Description</h5>
                <div class="artwork-description">
                    <?php echo nl2br(htmlspecialchars($artwork['description'])); ?>
                </div>
                
                <!-- Date -->
                <p class="text-muted small">
                    <i class="far fa-calendar-alt me-1"></i> Posted on <?php echo date('F j, Y', strtotime($artwork['created_at'])); ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Comments Section -->
    <div class="row mt-4" id="comments">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white">
                    <h3 class="mb-0">Comments (<?php echo count($comments); ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Comment Form -->
                    <form method="POST" action="artwork.php?id=<?php echo $artworkId; ?>#comments">
                        <div class="mb-3">
                            <label for="comment" class="form-label">Add a comment</label>
                            <textarea class="form-control <?php echo !empty($commentError) ? 'is-invalid' : ''; ?>" id="comment" name="comment" rows="3" required></textarea>
                            <?php if (!empty($commentError)): ?>
                            <div class="invalid-feedback">
                                <?php echo $commentError; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-primary">Post Comment</button>
                    </form>
                    <hr>
                    <?php else: ?>
                    <div class="alert alert-info">
                        Please <a href="login.php">login</a> to leave a comment.
                    </div>
                    <?php endif; ?>
                    
                    <!-- Comments List -->
                    <?php if (empty($comments)): ?>
                    <p class="text-muted">No comments yet. Be the first to comment!</p>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                        <div class="comment">
                            <div class="comment-header">
                                <?php if (!empty($comment['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($comment['profile_image']); ?>" class="comment-avatar" alt="<?php echo htmlspecialchars($comment['username']); ?>">
                                <?php else: ?>
                                <div class="comment-avatar bg-secondary d-flex align-items-center justify-content-center text-white">
                                    <i class="fas fa-user"></i>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <h6 class="comment-author"><?php echo htmlspecialchars($comment['username']); ?></h6>
                                    <p class="comment-date"><?php echo date('F j, Y g:i a', strtotime($comment['created_at'])); ?></p>
                                </div>
                            </div>
                            <div class="comment-content">
                                <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Similar Artworks Section -->
    <?php if (!empty($similarArtworks)): ?>
    <div class="row mt-5">
        <div class="col-12">
            <h3 class="mb-4">Similar Artworks</h3>
            <div class="row">
                <?php foreach ($similarArtworks as $similar): ?>
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="card h-100">
                        <img src="<?php echo htmlspecialchars($similar['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($similar['title']); ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($similar['title']); ?></h5>
                            <p class="card-text text-muted">by <a href="artist.php?id=<?php echo $similar['artist_id']; ?>"><?php echo htmlspecialchars($similar['artist_name']); ?></a></p>
                            <p class="card-text artwork-price">$<?php echo number_format($similar['price'], 2); ?></p>
                            <a href="artwork.php?id=<?php echo $similar['artwork_id']; ?>" class="btn btn-outline-primary">View Details</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>