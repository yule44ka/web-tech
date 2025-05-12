<?php
// Set page title
$pageTitle = 'Admin Dashboard';

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

// Get basic statistics
$stats = [
    'total_users' => 0,
    'total_artists' => 0,
    'total_artworks' => 0,
    'total_sales' => 0,
    'total_revenue' => 0
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
} catch (PDOException $e) {
    error_log('Error fetching admin statistics: ' . $e->getMessage());
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <h1 class="mb-4">Admin Dashboard</h1>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card h-100 bg-primary text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Users & Artists</h5>
                    <p class="display-4"><?php echo $stats['total_users']; ?></p>
                    <p class="card-text">
                        Total Users: <?php echo $stats['total_users']; ?><br>
                        Artists: <?php echo $stats['total_artists']; ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card h-100 bg-success text-white">
                <div class="card-body text-center">
                    <h5 class="card-title">Content</h5>
                    <p class="display-4"><?php echo $stats['total_artworks']; ?></p>
                    <p class="card-text">Total Artworks</p>
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
    
    <!-- Admin Navigation -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-palette fa-3x mb-3 text-primary"></i>
                    <h5 class="card-title">Artwork Management</h5>
                    <p class="card-text">View, edit, and delete artworks</p>
                    <a href="artworks.php" class="btn btn-primary">Manage Artworks</a>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-users fa-3x mb-3 text-success"></i>
                    <h5 class="card-title">User Management</h5>
                    <p class="card-text">View, edit, and manage users</p>
                    <a href="users.php" class="btn btn-success">Manage Users</a>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-chart-bar fa-3x mb-3 text-info"></i>
                    <h5 class="card-title">Statistics</h5>
                    <p class="card-text">View platform statistics and top artists</p>
                    <a href="statistics.php" class="btn btn-info">View Statistics</a>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="fas fa-history fa-3x mb-3 text-warning"></i>
                    <h5 class="card-title">Activity Logs</h5>
                    <p class="card-text">View system activity logs</p>
                    <a href="activity_logs.php" class="btn btn-warning">View Logs</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">Recent Activity</h5>
        </div>
        <div class="card-body">
            <p class="text-center">Activity logs will be displayed here once implemented.</p>
        </div>
    </div>
</div>

<?php
// Include footer
include '../includes/footer.php';
?>