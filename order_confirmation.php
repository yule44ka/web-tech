<?php
// Set page title
$pageTitle = 'Order Confirmation';

// Include database connection
require_once 'includes/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: login.php');
    exit;
}

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirect to home page if no valid order ID
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['user_id'];
$orderId = (int)$_GET['id'];

// Get order details
$order = null;
$orderItems = [];
try {
    // Check if order belongs to user
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE order_id = ? AND user_id = ?
    ");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        // Order not found or doesn't belong to user, redirect to home page
        header('Location: index.php');
        exit;
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, a.title, a.image_path, u.username as artist_name
        FROM order_items oi
        JOIN artworks a ON oi.artwork_id = a.artwork_id
        JOIN users u ON a.artist_id = u.user_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $orderItems = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Error fetching order: ' . $e->getMessage());
    // Redirect to home page on error
    header('Location: index.php');
    exit;
}

// Include header
include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-success mb-4">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="fas fa-check-circle me-2"></i>Order Confirmed</h4>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        <h2 class="mt-3">Thank You for Your Order!</h2>
                        <p class="lead">Your order has been placed successfully.</p>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Order Number:</strong> #<?php echo $orderId; ?></p>
                            <p><strong>Order Date:</strong> <?php echo date('F j, Y g:i a', strtotime($order['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p><strong>Order Status:</strong> <span class="badge bg-warning"><?php echo ucfirst($order['status']); ?></span></p>
                            <p><strong>Total Amount:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h5 class="mb-3">Order Items</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="table-light">
                                <tr>
                                    <th>Artwork</th>
                                    <th>Artist</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="img-thumbnail me-2" style="width: 50px; height: 50px; object-fit: cover;">
                                            <div>
                                                <a href="artwork.php?id=<?php echo $item['artwork_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($item['title']); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['artist_name']); ?></td>
                                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td class="text-end">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                    <td class="text-end"><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <hr>
                    
                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle me-2"></i>What's Next?</h5>
                        <p class="mb-0">Your order is now being processed. You will receive an email confirmation shortly. You can track the status of your order in your <a href="orders.php" class="alert-link">order history</a>.</p>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between">
                        <a href="orders.php" class="btn btn-outline-primary">
                            <i class="fas fa-list me-2"></i>View All Orders
                        </a>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i>Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Additional Information -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Digital Delivery Information</h5>
                </div>
                <div class="card-body">
                    <p>As ArtLoop is a digital art platform, your purchased artworks will be available for download in your account. Here's what you need to know:</p>
                    
                    <ul>
                        <li>Digital artworks will be available in your <a href="profile.php">profile</a> under "My Purchases".</li>
                        <li>You will receive download links via email within 24 hours.</li>
                        <li>All artworks come with a digital certificate of authenticity.</li>
                        <li>If you have any issues accessing your purchases, please <a href="contact.php">contact our support team</a>.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>