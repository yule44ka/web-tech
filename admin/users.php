<?php
// Set page title
$pageTitle = 'Manage Users';

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
$currentAdminId = $_SESSION['user_id'];

// Handle user deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $userId = (int)$_GET['delete'];
    
    // Prevent admin from deleting themselves
    if ($userId == $currentAdminId) {
        $message = 'You cannot delete your own account.';
        $messageType = 'danger';
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Delete user's artworks (if they're an artist)
                if ($user['role'] == 'artist') {
                    // Get all artworks by this artist
                    $stmt = $pdo->prepare("SELECT * FROM artworks WHERE artist_id = ?");
                    $stmt->execute([$userId]);
                    $artworks = $stmt->fetchAll();
                    
                    foreach ($artworks as $artwork) {
                        // Delete artwork tags
                        $stmt = $pdo->prepare("DELETE FROM artwork_tags WHERE artwork_id = ?");
                        $stmt->execute([$artwork['artwork_id']]);
                        
                        // Delete artwork likes
                        $stmt = $pdo->prepare("DELETE FROM likes WHERE artwork_id = ?");
                        $stmt->execute([$artwork['artwork_id']]);
                        
                        // Delete artwork comments
                        $stmt = $pdo->prepare("DELETE FROM comments WHERE artwork_id = ?");
                        $stmt->execute([$artwork['artwork_id']]);
                        
                        // Delete artwork from cart items
                        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE artwork_id = ?");
                        $stmt->execute([$artwork['artwork_id']]);
                        
                        // Delete from order items
                        $stmt = $pdo->prepare("DELETE FROM order_items WHERE artwork_id = ?");
                        $stmt->execute([$artwork['artwork_id']]);
                        
                        // Delete artwork
                        $stmt = $pdo->prepare("DELETE FROM artworks WHERE artwork_id = ?");
                        $stmt->execute([$artwork['artwork_id']]);
                        
                        // Delete artwork image file
                        if (file_exists('../' . $artwork['image_path'])) {
                            unlink('../' . $artwork['image_path']);
                        }
                    }
                }
                
                // Delete user's likes
                $stmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // Delete user's comments
                $stmt = $pdo->prepare("DELETE FROM comments WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // Delete user's cart
                $stmt = $pdo->prepare("
                    DELETE FROM cart_items 
                    WHERE cart_id IN (SELECT cart_id FROM cart WHERE user_id = ?)
                ");
                $stmt->execute([$userId]);
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // Delete user's orders (if possible, or mark as deleted)
                $stmt = $pdo->prepare("
                    DELETE FROM order_items 
                    WHERE order_id IN (SELECT order_id FROM orders WHERE user_id = ?)
                ");
                $stmt->execute([$userId]);
                $stmt = $pdo->prepare("DELETE FROM orders WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // Delete user's addresses
                $stmt = $pdo->prepare("DELETE FROM user_addresses WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // Finally, delete the user
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // Commit transaction
                $pdo->commit();
                
                $message = 'User deleted successfully.';
                $messageType = 'success';
            } else {
                $message = 'User not found.';
                $messageType = 'danger';
            }
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            error_log('Error deleting user: ' . $e->getMessage());
            $message = 'An error occurred while deleting the user. Please try again.';
            $messageType = 'danger';
        }
    }
}

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $userId = (int)$_POST['user_id'];
    $newRole = $_POST['role'];
    
    // Validate role
    if (!in_array($newRole, ['user', 'artist', 'admin'])) {
        $message = 'Invalid role selected.';
        $messageType = 'danger';
    } 
    // Prevent admin from changing their own role
    elseif ($userId == $currentAdminId) {
        $message = 'You cannot change your own role.';
        $messageType = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE user_id = ?");
            $stmt->execute([$newRole, $userId]);
            
            $message = 'User role updated successfully.';
            $messageType = 'success';
        } catch (PDOException $e) {
            error_log('Error updating user role: ' . $e->getMessage());
            $message = 'An error occurred while updating the user role. Please try again.';
            $messageType = 'danger';
        }
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
    $searchCondition = "WHERE username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?";
    $searchParams = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

// Get total users count for pagination
try {
    if (!empty($searchCondition)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users $searchCondition");
        $stmt->execute($searchParams);
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    }
    $totalUsers = $stmt->fetchColumn();
    $totalPages = ceil($totalUsers / $perPage);
} catch (PDOException $e) {
    error_log('Error counting users: ' . $e->getMessage());
    $totalUsers = 0;
    $totalPages = 1;
}

// Get users with pagination
$users = [];
try {
    $query = "
        SELECT u.*, 
               (SELECT COUNT(*) FROM artworks WHERE artist_id = u.user_id) as artwork_count,
               (SELECT COUNT(*) FROM orders WHERE user_id = u.user_id) as order_count
        FROM users u
        $searchCondition
        ORDER BY u.created_at DESC
        LIMIT $perPage OFFSET $offset
    ";
    
    if (!empty($searchCondition)) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($searchParams);
    } else {
        $stmt = $pdo->query($query);
    }
    
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching users: ' . $e->getMessage());
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Manage Users</h1>
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
            <form action="users.php" method="GET" class="row g-3">
                <div class="col-md-10">
                    <input type="text" class="form-control" id="search" name="search" placeholder="Search by username, email, or name" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Users Table -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">All Users (<?php echo $totalUsers; ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($users)): ?>
            <div class="p-4 text-center">
                <p>No users found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Stats</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-light rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas fa-user text-secondary"></i>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong><br>
                                        <small class="text-muted">
                                            <?php 
                                            if (!empty($user['first_name']) || !empty($user['last_name'])) {
                                                echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
                                            }
                                            ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <form method="POST" action="users.php" class="d-flex">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <select name="role" class="form-select form-select-sm me-2" <?php echo $user['user_id'] == $currentAdminId ? 'disabled' : ''; ?>>
                                        <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>User</option>
                                        <option value="artist" <?php echo $user['role'] == 'artist' ? 'selected' : ''; ?>>Artist</option>
                                        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                    <button type="submit" name="update_role" class="btn btn-sm btn-outline-primary" <?php echo $user['user_id'] == $currentAdminId ? 'disabled' : ''; ?>>
                                        <i class="fas fa-save"></i>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php if ($user['role'] == 'artist'): ?>
                                    <i class="fas fa-palette me-1"></i> <?php echo $user['artwork_count']; ?> artworks<br>
                                    <?php endif; ?>
                                    <i class="fas fa-shopping-cart me-1"></i> <?php echo $user['order_count']; ?> orders
                                </small>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if ($user['user_id'] != $currentAdminId): ?>
                                <a href="users.php?delete=<?php echo $user['user_id']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&page=<?php echo $page; ?>" class="btn btn-sm btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this user? This will delete all their artworks, orders, and other data. This action cannot be undone.');">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                                <?php else: ?>
                                <span class="text-muted">(Current User)</span>
                                <?php endif; ?>
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
    <nav aria-label="User pagination">
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