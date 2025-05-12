<?php
// Set page title
$pageTitle = 'Activity Logs';

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

// Include activity logger
require_once '../includes/activity_logger.php';

// Log this admin action
$adminId = $_SESSION['user_id'];
$adminUsername = $_SESSION['username'];
logAdminAction($adminId, $adminUsername, "viewed activity logs", $pdo);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filtering
$actionType = isset($_GET['action_type']) ? $_GET['action_type'] : '';
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($actionType)) {
    $conditions[] = "action_type = ?";
    $params[] = $actionType;
}

if ($userId > 0) {
    $conditions[] = "user_id = ?";
    $params[] = $userId;
}

if (!empty($dateFrom)) {
    $conditions[] = "created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $conditions[] = "created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

if (!empty($search)) {
    $conditions[] = "action_description LIKE ?";
    $params[] = "%$search%";
}

$whereClause = '';
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

// Get total logs count for pagination
try {
    $countQuery = "SELECT COUNT(*) FROM activity_logs $whereClause";
    
    if (!empty($params)) {
        $stmt = $pdo->prepare($countQuery);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->query($countQuery);
    }
    
    $totalLogs = $stmt->fetchColumn();
    $totalPages = ceil($totalLogs / $perPage);
} catch (PDOException $e) {
    error_log('Error counting activity logs: ' . $e->getMessage());
    $totalLogs = 0;
    $totalPages = 1;
}

// Get logs with pagination
$logs = [];
try {
    $query = "
        SELECT l.*, u.username 
        FROM activity_logs l
        LEFT JOIN users u ON l.user_id = u.user_id
        $whereClause
        ORDER BY l.created_at DESC
        LIMIT $perPage OFFSET $offset
    ";
    
    if (!empty($params)) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->query($query);
    }
    
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching activity logs: ' . $e->getMessage());
}

// Get action types for filter dropdown
$actionTypes = [];
try {
    $stmt = $pdo->query("
        SELECT DISTINCT action_type 
        FROM activity_logs 
        ORDER BY action_type
    ");
    $actionTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('Error fetching action types: ' . $e->getMessage());
}

// Get users for filter dropdown
$users = [];
try {
    $stmt = $pdo->query("
        SELECT DISTINCT u.user_id, u.username
        FROM users u
        JOIN activity_logs l ON u.user_id = l.user_id
        ORDER BY u.username
    ");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching users for filter: ' . $e->getMessage());
}

// Include header
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Activity Logs</h1>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Filter Logs</h5>
        </div>
        <div class="card-body">
            <form action="activity_logs.php" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="action_type" class="form-label">Action Type</label>
                    <select class="form-select" id="action_type" name="action_type">
                        <option value="">All Actions</option>
                        <?php foreach ($actionTypes as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo $actionType == $type ? 'selected' : ''; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="user_id" class="form-label">User</label>
                    <select class="form-select" id="user_id" name="user_id">
                        <option value="0">All Users</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>" <?php echo $userId == $user['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="activity_logs.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Logs Table -->
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Activity Logs</h5>
            <span class="badge bg-secondary"><?php echo number_format($totalLogs); ?> logs found</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
            <div class="p-4 text-center">
                <p>No activity logs found.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Date & Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo $log['log_id']; ?></td>
                            <td><?php echo date('M j, Y g:i:s a', strtotime($log['created_at'])); ?></td>
                            <td>
                                <?php if ($log['user_id']): ?>
                                <a href="users.php?search=<?php echo urlencode($log['username']); ?>">
                                    <?php echo htmlspecialchars($log['username']); ?>
                                </a>
                                <?php else: ?>
                                <span class="text-muted">Guest</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo getBadgeClass($log['action_type']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $log['action_type'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($log['action_description']); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
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
    <nav aria-label="Activity logs pagination">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&action_type=<?php echo urlencode($actionType); ?>&user_id=<?php echo $userId; ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            
            <?php 
            // Show limited page numbers with ellipsis
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            if ($startPage > 1) {
                echo '<li class="page-item"><a class="page-link" href="?page=1&action_type=' . urlencode($actionType) . '&user_id=' . $userId . '&date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo) . '&search=' . urlencode($search) . '">1</a></li>';
                if ($startPage > 2) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }
            
            for ($i = $startPage; $i <= $endPage; $i++): 
            ?>
            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&action_type=<?php echo urlencode($actionType); ?>&user_id=<?php echo $userId; ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&search=<?php echo urlencode($search); ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; 
            
            if ($endPage < $totalPages) {
                if ($endPage < $totalPages - 1) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&action_type=' . urlencode($actionType) . '&user_id=' . $userId . '&date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo) . '&search=' . urlencode($search) . '">' . $totalPages . '</a></li>';
            }
            ?>
            
            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&action_type=<?php echo urlencode($actionType); ?>&user_id=<?php echo $userId; ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php
/**
 * Get the appropriate Bootstrap badge class for an action type
 * 
 * @param string $actionType The action type
 * @return string The Bootstrap badge class
 */
function getBadgeClass($actionType) {
    switch ($actionType) {
        case 'login':
        case 'logout':
            return 'bg-secondary';
        case 'register':
            return 'bg-primary';
        case 'upload':
        case 'edit':
            return 'bg-info';
        case 'delete':
            return 'bg-danger';
        case 'purchase':
            return 'bg-success';
        case 'like':
        case 'comment':
            return 'bg-warning';
        case 'admin_action':
            return 'bg-dark';
        default:
            return 'bg-secondary';
    }
}

// Include footer
include '../includes/footer.php';
?>