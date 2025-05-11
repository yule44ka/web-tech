<?php
// Set page title
$pageTitle = 'Checkout';

// Include database connection
require_once 'includes/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: login.php?redirect=checkout.php');
    exit;
}

$userId = $_SESSION['user_id'];
$cartItems = [];
$cartTotal = 0;
$message = '';
$messageType = '';

// Get user's addresses
$addresses = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC");
    $stmt->execute([$userId]);
    $addresses = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching addresses: ' . $e->getMessage());
}

// Get cart items
try {
    $stmt = $pdo->prepare("
        SELECT ci.*, a.title, a.image_path, a.price, u.username as artist_name
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
    
    // If cart is empty, redirect to cart page
    if (empty($cartItems)) {
        header('Location: cart.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Error fetching cart items: ' . $e->getMessage());
    $message = 'An error occurred. Please try again.';
    $messageType = 'danger';
}

// Handle checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Validate form data
    $errors = [];
    
    // Get form data
    $addressId = isset($_POST['address_id']) ? (int)$_POST['address_id'] : 0;
    $newAddress = isset($_POST['new_address']) && $_POST['new_address'] === '1';
    
    if ($newAddress) {
        // Validate new address fields
        $addressLine1 = trim($_POST['address_line1'] ?? '');
        $addressLine2 = trim($_POST['address_line2'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $postalCode = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $saveAddress = isset($_POST['save_address']) && $_POST['save_address'] === '1';
        
        if (empty($addressLine1)) {
            $errors['address_line1'] = 'Address line 1 is required';
        }
        
        if (empty($city)) {
            $errors['city'] = 'City is required';
        }
        
        if (empty($postalCode)) {
            $errors['postal_code'] = 'Postal code is required';
        }
        
        if (empty($country)) {
            $errors['country'] = 'Country is required';
        }
    } else {
        // Validate existing address
        if ($addressId <= 0) {
            $errors['address_id'] = 'Please select an address';
        } else {
            // Check if address belongs to user
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE address_id = ? AND user_id = ?");
            $stmt->execute([$addressId, $userId]);
            if ($stmt->fetchColumn() == 0) {
                $errors['address_id'] = 'Invalid address selected';
            }
        }
    }
    
    // If no errors, process order
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // If new address and save_address is checked, save address
            if ($newAddress && $saveAddress) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_addresses (user_id, address_line1, address_line2, city, state, postal_code, country, is_default)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userId,
                    $addressLine1,
                    $addressLine2,
                    $city,
                    $state,
                    $postalCode,
                    $country,
                    empty($addresses) ? 1 : 0 // Make default if no other addresses
                ]);
                
                $addressId = $pdo->lastInsertId();
            }
            
            // Create order
            $stmt = $pdo->prepare("
                INSERT INTO orders (user_id, total_amount, status)
                VALUES (?, ?, 'pending')
            ");
            $stmt->execute([$userId, $cartTotal]);
            $orderId = $pdo->lastInsertId();
            
            // Add order items
            $stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, artwork_id, price, quantity)
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($cartItems as $item) {
                $stmt->execute([
                    $orderId,
                    $item['artwork_id'],
                    $item['price'],
                    $item['quantity']
                ]);
            }
            
            // Clear cart
            $stmt = $pdo->prepare("
                DELETE FROM cart_items 
                WHERE cart_id IN (SELECT cart_id FROM cart WHERE user_id = ?)
            ");
            $stmt->execute([$userId]);
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to order confirmation page
            header('Location: order_confirmation.php?id=' . $orderId);
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            
            error_log('Error processing order: ' . $e->getMessage());
            $message = 'An error occurred while processing your order. Please try again.';
            $messageType = 'danger';
        }
    } else {
        // Display validation errors
        $message = 'Please correct the errors below.';
        $messageType = 'danger';
    }
}

// Include header
include 'includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4">Checkout</h1>
    
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>" role="alert">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Checkout Form -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Shipping Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="checkout.php" id="checkoutForm">
                        <?php if (!empty($addresses)): ?>
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="new_address" id="useExistingAddress" value="0" checked>
                                <label class="form-check-label" for="useExistingAddress">
                                    Use an existing address
                                </label>
                            </div>
                            
                            <div class="mt-3" id="existingAddressSection">
                                <?php foreach ($addresses as $address): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="address_id" id="address<?php echo $address['address_id']; ?>" value="<?php echo $address['address_id']; ?>" <?php echo $address['is_default'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="address<?php echo $address['address_id']; ?>">
                                        <strong><?php echo htmlspecialchars($address['address_line1']); ?></strong>
                                        <?php if (!empty($address['address_line2'])): ?>
                                        <br><?php echo htmlspecialchars($address['address_line2']); ?>
                                        <?php endif; ?>
                                        <br><?php echo htmlspecialchars($address['city']); ?>, <?php echo htmlspecialchars($address['state']); ?> <?php echo htmlspecialchars($address['postal_code']); ?>
                                        <br><?php echo htmlspecialchars($address['country']); ?>
                                        <?php if ($address['is_default']): ?>
                                        <span class="badge bg-primary ms-2">Default</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                                
                                <?php if (isset($errors['address_id'])): ?>
                                <div class="text-danger mt-2">
                                    <?php echo $errors['address_id']; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="radio" name="new_address" id="useNewAddress" value="1">
                                <label class="form-check-label" for="useNewAddress">
                                    Use a new address
                                </label>
                            </div>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="new_address" value="1">
                        <?php endif; ?>
                        
                        <div id="newAddressSection" <?php echo (!empty($addresses) && !isset($errors['address_line1'])) ? 'style="display: none;"' : ''; ?>>
                            <div class="mb-3">
                                <label for="addressLine1" class="form-label">Address Line 1 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['address_line1']) ? 'is-invalid' : ''; ?>" id="addressLine1" name="address_line1" value="<?php echo htmlspecialchars($_POST['address_line1'] ?? ''); ?>">
                                <?php if (isset($errors['address_line1'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['address_line1']; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="addressLine2" class="form-label">Address Line 2</label>
                                <input type="text" class="form-control" id="addressLine2" name="address_line2" value="<?php echo htmlspecialchars($_POST['address_line2'] ?? ''); ?>">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?php echo isset($errors['city']) ? 'is-invalid' : ''; ?>" id="city" name="city" value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                                    <?php if (isset($errors['city'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['city']; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="state" class="form-label">State/Province</label>
                                    <input type="text" class="form-control" id="state" name="state" value="<?php echo htmlspecialchars($_POST['state'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="postalCode" class="form-label">Postal Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?php echo isset($errors['postal_code']) ? 'is-invalid' : ''; ?>" id="postalCode" name="postal_code" value="<?php echo htmlspecialchars($_POST['postal_code'] ?? ''); ?>">
                                    <?php if (isset($errors['postal_code'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['postal_code']; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="country" class="form-label">Country <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control <?php echo isset($errors['country']) ? 'is-invalid' : ''; ?>" id="country" name="country" value="<?php echo htmlspecialchars($_POST['country'] ?? ''); ?>">
                                    <?php if (isset($errors['country'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['country']; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="saveAddress" name="save_address" value="1" checked>
                                <label class="form-check-label" for="saveAddress">
                                    Save this address for future orders
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid mt-4">
                            <button type="submit" name="place_order" class="btn btn-primary btn-lg">Place Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Order Summary -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Items (<?php echo count($cartItems); ?>):</span>
                        <span>$<?php echo number_format($cartTotal, 2); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Shipping:</span>
                        <span>Free</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <strong>Total:</strong>
                        <strong>$<?php echo number_format($cartTotal, 2); ?></strong>
                    </div>
                    
                    <div class="accordion" id="orderItemsAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingItems">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseItems" aria-expanded="false" aria-controls="collapseItems">
                                    View Order Items
                                </button>
                            </h2>
                            <div id="collapseItems" class="accordion-collapse collapse" aria-labelledby="headingItems" data-bs-parent="#orderItemsAccordion">
                                <div class="accordion-body p-0">
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($cartItems as $item): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex">
                                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="img-thumbnail me-2" style="width: 50px; height: 50px; object-fit: cover;">
                                                <div>
                                                    <div><?php echo htmlspecialchars($item['title']); ?></div>
                                                    <div class="text-muted small">
                                                        $<?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?>
                                                    </div>
                                                </div>
                                                <div class="ms-auto">
                                                    $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                                </div>
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle address sections based on radio selection
    const useExistingAddress = document.getElementById('useExistingAddress');
    const useNewAddress = document.getElementById('useNewAddress');
    const existingAddressSection = document.getElementById('existingAddressSection');
    const newAddressSection = document.getElementById('newAddressSection');
    
    if (useExistingAddress && useNewAddress) {
        useExistingAddress.addEventListener('change', function() {
            if (this.checked) {
                existingAddressSection.style.display = 'block';
                newAddressSection.style.display = 'none';
            }
        });
        
        useNewAddress.addEventListener('change', function() {
            if (this.checked) {
                existingAddressSection.style.display = 'none';
                newAddressSection.style.display = 'block';
            }
        });
    }
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?>