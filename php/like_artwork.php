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
        'message' => 'You must be logged in to like artworks'
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
    
    // Check if user has already liked this artwork
    $stmt = $pdo->prepare("SELECT * FROM likes WHERE user_id = ? AND artwork_id = ?");
    $stmt->execute([$userId, $artworkId]);
    $like = $stmt->fetch();
    
    if ($like) {
        // User has already liked this artwork, so unlike it
        $stmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND artwork_id = ?");
        $stmt->execute([$userId, $artworkId]);
        
        // Decrement like count in artworks table
        $stmt = $pdo->prepare("UPDATE artworks SET likes = likes - 1 WHERE artwork_id = ?");
        $stmt->execute([$artworkId]);
        
        $liked = false;
    } else {
        // User has not liked this artwork, so like it
        $stmt = $pdo->prepare("INSERT INTO likes (user_id, artwork_id) VALUES (?, ?)");
        $stmt->execute([$userId, $artworkId]);
        
        // Increment like count in artworks table
        $stmt = $pdo->prepare("UPDATE artworks SET likes = likes + 1 WHERE artwork_id = ?");
        $stmt->execute([$artworkId]);
        
        $liked = true;
    }
    
    // Get updated like count
    $stmt = $pdo->prepare("SELECT likes FROM artworks WHERE artwork_id = ?");
    $stmt->execute([$artworkId]);
    $likeCount = $stmt->fetch()['likes'];
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'likeCount' => $likeCount
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    // Log error
    error_log('Error liking artwork: ' . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}
?>