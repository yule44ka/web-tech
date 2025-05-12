<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../includes/db_connect.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to add items to your cart'
    ]);
    exit;
}

// Check if artwork ID is provided
if (!isset($_POST['artwork_id']) || !is_numeric($_POST['artwork_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid artwork ID'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];
$artworkId = (int)$_POST['artwork_id'];

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Check if artwork exists
    $stmt = $pdo->prepare("SELECT * FROM artworks WHERE artwork_id = ?");
    $stmt->execute([$artworkId]);
    $artwork = $stmt->fetch();

    if (!$artwork) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Artwork not found'
        ]);
        exit;
    }

    // Allow creators to add their own artwork to cart
    // Previous restriction removed to allow creators to add their own artwork

    // Get user's cart
    $stmt = $pdo->prepare("SELECT * FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);
    $cart = $stmt->fetch();

    // If user doesn't have a cart, create one
    if (!$cart) {
        $stmt = $pdo->prepare("INSERT INTO cart (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        $cartId = $pdo->lastInsertId();
    } else {
        $cartId = $cart['cart_id'];
    }

    // Check if artwork is already in cart
    $stmt = $pdo->prepare("
        SELECT * FROM cart_items 
        WHERE cart_id = ? AND artwork_id = ?
    ");
    $stmt->execute([$cartId, $artworkId]);
    $cartItem = $stmt->fetch();

    if ($cartItem) {
        // Artwork is already in cart
        $inCart = true;
    } else {
        // Add artwork to cart
        $stmt = $pdo->prepare("
            INSERT INTO cart_items (cart_id, artwork_id, quantity)
            VALUES (?, ?, 1)
        ");
        $stmt->execute([$cartId, $artworkId]);
        $inCart = true;
    }

    // Get cart count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM cart_items
        WHERE cart_id = ?
    ");
    $stmt->execute([$cartId]);
    $cartCount = $stmt->fetch()['count'];

    // Commit transaction
    $pdo->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'inCart' => $inCart,
        'cartCount' => $cartCount,
        'message' => 'Artwork added to cart'
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();

    // Log error
    error_log('Error adding to cart: ' . $e->getMessage());

    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>
