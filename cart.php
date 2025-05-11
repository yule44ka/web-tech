<?php
// Set page title
$pageTitle = 'Shopping Cart';

// Include database connection
require_once 'includes/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: login.php?redirect=cart.php');
    exit;
}

$userId = $_SESSION['user_id'];
$cartItems = [];
$cartTotal = 0;
$message = '';
$messageType = '';

// Handle remove item action
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $cartItemId = (int)$_GET['remove'];
    
    try {
        // Check if cart item belongs to user
        $stmt = $pdo->prepare("
            SELECT ci.* FROM cart_items ci
            JOIN cart c ON ci.cart_id = c.cart_id
            WHERE ci.cart_item_id = ? AND c.user_id = ?
        ");
        $stmt->execute([$cartItemId, $userId]);
        $cartItem = $stmt->fetch();
        
        if ($cartItem) {
            // Remove item from cart
            $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_item_id = ?");
            $stmt->execute([$cartItemId]);
            
            $message = 'Item removed from cart.';
            $messageType = 'success';
        }
    } catch (PDOException $e) {
        error_log('Error removing cart item: ' . $e->getMessage());
        $message = 'An error occurred. Please try again.';
        $messageType = 'danger';
    }
}

// Handle update quantity action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update quantities
        foreach ($_POST['quantity'] as $cartItemId => $quantity) {
            $cartItemId = (int)$cartItemId;
            $quantity = (int)$quantity;
            
            if ($quantity <= 0) {
                // Remove item if quantity is 0 or negative
                $stmt = $pdo->prepare("
                    DELETE FROM cart_items 
                    WHERE cart_item_id = ? AND cart_id IN (SELECT cart_id FROM cart WHERE user_id = ?)
                ");
                $stmt->execute([$cartItemId, $userId]);
            } else {
                // Update quantity
                $stmt = $pdo->prepare("
                    UPDATE cart_items 
                    SET quantity = ? 
                    WHERE cart_item_id = ? AND cart_id IN (SELECT cart_id FROM cart WHERE user_id = ?)
                ");
                $stmt->execute([$quantity, $cartItemId, $userId]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        $message = 'Cart updated successfully.';
        $messageType = 'success';
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        
        error_log('Error updating cart: ' . $e->getMessage());
        $message = 'An error occurred. Please try again.';
        $messageType = 'danger';
    }
}

// Get cart items
try {
    $stmt = $pdo->prepare("
        SELECT ci.*, a.title, a.image_path, a.price, u.username as artist_name, u.user_id as artist_id
        FROM cart_items ci
        JOIN cart c ON ci.cart_id = c.cart_id
        JOIN artworks a ON ci.artwork_id = a.artwork_id
        JOIN users u ON a.artist_id = u.user_id
        WHERE c.user_id = ?
        ORDER BY ci.created_at DESC
    ");
    $stmt->execute([$userId]);
    $cartItems = $stmt->fetchAll();
    
    // Calculate cart total
    foreach ($cartItems as $item) {
        $cartTotal += $item['price'] * $item['quantity'];
    }
} catch (PDOException $e) {
    error_log('Error fetching cart items: ' . $e->getMessage());
    $message = 'An error occurred. Please try again.';
    $messageType = 'danger';
}

// Include header
include 'includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4">Shopping Cart</h1>
    
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>" role="alert">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <?php if (empty($cartItems)): ?>
    <div class="alert alert-info">
        <p>Your cart is empty.</p>
        <a href="search.php" class="btn btn-primary mt-2">Browse Artworks</a>
    </div>
    <?php else: ?>
    <form method="POST" action="cart.php">
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Artwork</th>
                                <th>Artist</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cartItems as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="img-thumbnail me-3" style="width: 80px; height: 80px; object-fit: cover;">
                                        <div>
                                            <a href="artwork.php?id=<?php echo $item['artwork_id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($item['title']); ?>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="artist.php?id=<?php echo $item['artist_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($item['artist_name']); ?>
                                    </a>
                                </td>
                                <td>$<?php echo number_format($item['price'], 2); ?></td>
                                <td>
                                    <input type="number" name="quantity[<?php echo $item['cart_item_id']; ?>]" value="<?php echo $item['quantity']; ?>" min="1" max="10" class="form-control form-control-sm cart-quantity" style="width: 70px;" data-cart-item-id="<?php echo $item['cart_item_id']; ?>">
                                </td>
                                <td id="subtotal-<?php echo $item['cart_item_id']; ?>">
                                    $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                </td>
                                <td>
                                    <a href="cart.php?remove=<?php echo $item['cart_item_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to remove this item?');">
                                        <i class="fas fa-trash-alt"></i> Remove
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <button type="submit" name="update_cart" class="btn btn-outline-primary">
                        <i class="fas fa-sync-alt me-2"></i>Update Cart
                    </button>
                    <div class="text-end">
                        <h5 class="mb-0">Total: <span id="cart-total">$<?php echo number_format($cartTotal, 2); ?></span></h5>
                    </div>
                </div>
            </div>
        </div>
    </form>
    
    <div class="d-flex justify-content-between mt-4">
        <a href="search.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Continue Shopping
        </a>
        <a href="checkout.php" class="btn btn-primary">
            Proceed to Checkout <i class="fas fa-arrow-right ms-2"></i>
        </a>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>