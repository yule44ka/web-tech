<?php
// Set page title
$pageTitle = 'My Orders';

// Include database connection
require_once 'includes/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: login.php?redirect=orders.php');
    exit;
}

$userId = $_SESSION['user_id'];
$orders = [];
$message = '';
$messageType = '';

// Get user's orders
try {
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll();
    
    // Get order items for each order
    foreach ($orders as &$order) {
        $stmt = $pdo->prepare("
            SELECT oi.*, a.title, a.image_path, u.username as artist_name
            FROM order_items oi
            JOIN artworks a ON oi.artwork_id = a.artwork_id
            JOIN users u ON a.artist_id = u.user_id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order['order_id']]);
        $order['items'] = $stmt->fetchAll();
        
        // Count total items
        $order['total_items'] = array_sum(array_column($order['items'], 'quantity'));
    }
    
} catch (PDOException $e) {
    error_log('Error fetching orders: ' . $e->getMessage());
    $message = 'An error occurred while retrieving your orders. Please try again.';
    $messageType = 'danger';
}

// Include header
include 'includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4">My Orders</h1>
    
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>" role="alert">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <?php if (empty($orders)): ?>
    <div class="alert alert-info">
        <p>You haven't placed any orders yet.</p>
        <a href="search.php" class="btn btn-primary mt-2">Browse Artworks</a>
    </div>
    <?php else: ?>
    
    <!-- Orders List -->
    <div class="row">
        <div class="col-12">
            <?php foreach ($orders as $order): ?>
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">Order #<?php echo $order['order_id']; ?></h5>
                            <p class="text-muted mb-0 small">Placed on <?php echo date('F j, Y g:i a', strtotime($order['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <span class="badge bg-<?php 
                                echo $order['status'] === 'completed' ? 'success' : 
                                    ($order['status'] === 'cancelled' ? 'danger' : 'warning'); 
                            ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                            <span class="ms-2">Total: <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h6><?php echo $order['total_items']; ?> item(s) in this order</h6>
                            <div class="row">
                                <?php foreach (array_slice($order['items'], 0, 4) as $item): ?>
                                <div class="col-6 col-md-3 mb-3">
                                    <div class="text-center">
                                        <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="img-thumbnail mb-2" style="width: 80px; height: 80px; object-fit: cover;">
                                        <div class="small text-truncate" title="<?php echo htmlspecialchars($item['title']); ?>">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php if (count($order['items']) > 4): ?>
                                <div class="col-6 col-md-3 mb-3">
                                    <div class="d-flex align-items-center justify-content-center h-100">
                                        <span class="text-muted">+<?php echo count($order['items']) - 4; ?> more</span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <a href="order_confirmation.php?id=<?php echo $order['order_id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-eye me-2"></i>View Order Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Continue Shopping Button -->
    <div class="text-center mt-4">
        <a href="index.php" class="btn btn-primary">
            <i class="fas fa-home me-2"></i>Continue Shopping
        </a>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>