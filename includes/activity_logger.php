<?php
/**
 * Activity Logger
 * 
 * Functions for logging user activities in the system
 */

/**
 * Log an activity
 * 
 * @param int|null $userId The user ID or null for guest actions
 * @param string $actionType The type of action (login, logout, register, etc.)
 * @param string $actionDescription Description of the action
 * @param PDO $pdo Database connection
 * @return bool True on success, false on failure
 */
function logActivity($userId, $actionType, $actionDescription, $pdo) {
    try {
        // Get IP address
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Get user agent
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Prepare and execute query
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (user_id, action_type, action_description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$userId, $actionType, $actionDescription, $ipAddress, $userAgent]);
        
        return true;
    } catch (PDOException $e) {
        error_log('Error logging activity: ' . $e->getMessage());
        return false;
    }
}

/**
 * Log a login activity
 * 
 * @param int $userId The user ID
 * @param string $username The username
 * @param PDO $pdo Database connection
 * @return bool True on success, false on failure
 */
function logLogin($userId, $username, $pdo) {
    return logActivity($userId, 'login', "User '$username' logged in", $pdo);
}

/**
 * Log a logout activity
 * 
 * @param int $userId The user ID
 * @param string $username The username
 * @param PDO $pdo Database connection
 * @return bool True on success, false on failure
 */
function logLogout($userId, $username, $pdo) {
    return logActivity($userId, 'logout', "User '$username' logged out", $pdo);
}

/**
 * Log a registration activity
 * 
 * @param int $userId The user ID
 * @param string $username The username
 * @param PDO $pdo Database connection
 * @return bool True on success, false on failure
 */
function logRegistration($userId, $username, $pdo) {
    return logActivity($userId, 'register', "New user '$username' registered", $pdo);
}

/**
 * Log an artwork upload activity
 * 
 * @param int $userId The user ID
 * @param string $username The username
 * @param string $artworkTitle The artwork title
 * @param int $artworkId The artwork ID
 * @param PDO $pdo Database connection
 * @return bool True on success, false on failure
 */
function logArtworkUpload($userId, $username, $artworkTitle, $artworkId, $pdo) {
    return logActivity(
        $userId, 
        'upload', 
        "User '$username' uploaded new artwork '$artworkTitle' (ID: $artworkId)", 
        $pdo
    );
}

/**
 * Log an artwork edit activity
 * 
 * @param int $userId The user ID
 * @param string $username The username
 * @param string $artworkTitle The artwork title
 * @param int $artworkId The artwork ID
 * @param PDO $pdo Database connection
 * @return bool True on success, false on failure
 */
function logArtworkEdit($userId, $username, $artworkTitle, $artworkId, $pdo) {
    return logActivity(
        $userId, 
        'edit', 
        "User '$username' edited artwork '$artworkTitle' (ID: $artworkId)", 
        $pdo
    );
}

/**
 * Log an artwork delete activity
 * 
 * @param int $userId The user ID
 * @param string $username The username
 * @param string $artworkTitle The artwork title
 * @param int $artworkId The artwork ID
 * @param PDO $pdo Database connection
 * @return bool True on success, false on failure
 */
function logArtworkDelete($userId, $username, $artworkTitle, $artworkId, $pdo) {
    return logActivity(
        $userId, 
        'delete', 
        "User '$username' deleted artwork '$artworkTitle' (ID: $artworkId)", 
        $pdo
    );
}

/**
 * Log a purchase activity
 * 
 * @param int $userId The user ID
 * @param string $username The username
 * @param int $orderId The order ID
 * @param float $amount The order amount
 * @param PDO $pdo Database connection
 * @return bool True on success, false on failure
 */
function logPurchase($userId, $username, $orderId, $amount, $pdo) {
    return logActivity(
        $userId, 
        'purchase', 
        "User '$username' made a purchase (Order ID: $orderId, Amount: $" . number_format($amount, 2) . ")", 
        $pdo
    );
}

/**
 * Log a like activity
 * 
 * @param int $userId The user ID
 * @param string $username The username
 * @param int $artworkId The artwork ID
 * @param string $artworkTitle The artwork title
 * @param PDO $pdo Database connection
 * @return bool True on success, false on failure
 */
function logLike($userId, $username, $artworkId, $artworkTitle, $pdo) {
    return logActivity(
        $userId, 
        'like', 
        "User '$username' liked artwork '$artworkTitle' (ID: $artworkId)", 
        $pdo
    );
}

/**
 * Log a comment activity
 * 
 * @param int $userId The user ID
 * @param string $username The username
 * @param int $artworkId The artwork ID
 * @param string $artworkTitle The artwork title
 * @param PDO $pdo Database connection
 * @return bool True on success, false on failure
 */
function logComment($userId, $username, $artworkId, $artworkTitle, $pdo) {
    return logActivity(
        $userId, 
        'comment', 
        "User '$username' commented on artwork '$artworkTitle' (ID: $artworkId)", 
        $pdo
    );
}

/**
 * Log an admin action
 * 
 * @param int $userId The admin user ID
 * @param string $username The admin username
 * @param string $action Description of the admin action
 * @param PDO $pdo Database connection
 * @return bool True on success, false on failure
 */
function logAdminAction($userId, $username, $action, $pdo) {
    return logActivity(
        $userId, 
        'admin_action', 
        "Admin '$username' $action", 
        $pdo
    );
}