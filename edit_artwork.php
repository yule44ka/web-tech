<?php
// Set page title
$pageTitle = 'Edit Artwork';

// Include database connection
require_once 'includes/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an artist or admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'artist' && $_SESSION['role'] != 'admin')) {
    // Redirect to login page
    header('Location: login.php?redirect=edit_artwork.php');
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';
$formData = [
    'title' => '',
    'description' => '',
    'price' => '',
    'category_id' => '',
    'tags' => ''
];

// Check if artwork ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: artist/dashboard.php');
    exit;
}

$artworkId = (int)$_GET['id'];

// Get artwork data
try {
    $stmt = $pdo->prepare("
        SELECT a.*, GROUP_CONCAT(t.name SEPARATOR ', ') as tags
        FROM artworks a
        LEFT JOIN artwork_tags at ON a.artwork_id = at.artwork_id
        LEFT JOIN tags t ON at.tag_id = t.tag_id
        WHERE a.artwork_id = ?
        GROUP BY a.artwork_id
    ");
    $stmt->execute([$artworkId]);
    $artwork = $stmt->fetch();
    
    // Check if artwork exists and belongs to the user
    if (!$artwork) {
        header('Location: artist/dashboard.php');
        exit;
    }
    
    // Check if user is the artist or admin
    if ($artwork['artist_id'] != $userId && $_SESSION['role'] != 'admin') {
        header('Location: artist/dashboard.php');
        exit;
    }
    
    // Populate form data
    $formData = [
        'title' => $artwork['title'],
        'description' => $artwork['description'],
        'price' => $artwork['price'],
        'category_id' => $artwork['category_id'],
        'tags' => $artwork['tags'] ?? ''
    ];
} catch (PDOException $e) {
    error_log('Error fetching artwork: ' . $e->getMessage());
    header('Location: artist/dashboard.php');
    exit;
}

// Get categories for dropdown
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error fetching categories: ' . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $errors = [];
    
    // Get form data
    $formData['title'] = trim($_POST['title'] ?? '');
    $formData['description'] = trim($_POST['description'] ?? '');
    $formData['price'] = trim($_POST['price'] ?? '');
    $formData['category_id'] = (int)($_POST['category_id'] ?? 0);
    $formData['tags'] = trim($_POST['tags'] ?? '');
    
    // Validate title
    if (empty($formData['title'])) {
        $errors['title'] = 'Title is required';
    } elseif (strlen($formData['title']) > 100) {
        $errors['title'] = 'Title must be less than 100 characters';
    }
    
    // Validate description
    if (empty($formData['description'])) {
        $errors['description'] = 'Description is required';
    }
    
    // Validate price
    if (empty($formData['price'])) {
        $errors['price'] = 'Price is required';
    } elseif (!is_numeric($formData['price']) || $formData['price'] <= 0) {
        $errors['price'] = 'Price must be a positive number';
    }
    
    // Validate category
    if ($formData['category_id'] <= 0) {
        $errors['category_id'] = 'Please select a category';
    } else {
        // Check if category exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE category_id = ?");
        $stmt->execute([$formData['category_id']]);
        if ($stmt->fetchColumn() == 0) {
            $errors['category_id'] = 'Invalid category selected';
        }
    }
    
    // If no errors, process update
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Check if a new image was uploaded
            $updateImage = false;
            $filePath = $artwork['image_path'];
            
            if (isset($_FILES['artwork_image']) && $_FILES['artwork_image']['error'] != UPLOAD_ERR_NO_FILE) {
                // Validate image
                if ($_FILES['artwork_image']['error'] != UPLOAD_ERR_OK) {
                    throw new Exception('Error uploading image: ' . $_FILES['artwork_image']['error']);
                }
                
                // Check file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $fileType = $_FILES['artwork_image']['type'];
                
                if (!in_array($fileType, $allowedTypes)) {
                    throw new Exception('Only JPEG, PNG, and GIF images are allowed');
                }
                
                // Check file size (max 5MB)
                $maxSize = 5 * 1024 * 1024; // 5MB in bytes
                if ($_FILES['artwork_image']['size'] > $maxSize) {
                    throw new Exception('Image size must be less than 5MB');
                }
                
                // Upload image
                $uploadDir = 'uploads/';
                
                // Create uploads directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generate unique filename
                $fileExtension = pathinfo($_FILES['artwork_image']['name'], PATHINFO_EXTENSION);
                $fileName = uniqid('artwork_') . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;
                
                // Move uploaded file
                if (!move_uploaded_file($_FILES['artwork_image']['tmp_name'], $filePath)) {
                    throw new Exception('Failed to move uploaded file');
                }
                
                $updateImage = true;
            }
            
            // Update artwork in database
            if ($updateImage) {
                $stmt = $pdo->prepare("
                    UPDATE artworks 
                    SET title = ?, description = ?, image_path = ?, price = ?, category_id = ?, updated_at = NOW()
                    WHERE artwork_id = ?
                ");
                $stmt->execute([
                    $formData['title'],
                    $formData['description'],
                    $filePath,
                    $formData['price'],
                    $formData['category_id'],
                    $artworkId
                ]);
                
                // Delete old image file
                if (file_exists($artwork['image_path'])) {
                    unlink($artwork['image_path']);
                }
            } else {
                $stmt = $pdo->prepare("
                    UPDATE artworks 
                    SET title = ?, description = ?, price = ?, category_id = ?, updated_at = NOW()
                    WHERE artwork_id = ?
                ");
                $stmt->execute([
                    $formData['title'],
                    $formData['description'],
                    $formData['price'],
                    $formData['category_id'],
                    $artworkId
                ]);
            }
            
            // Process tags
            // First, remove all existing tags for this artwork
            $stmt = $pdo->prepare("DELETE FROM artwork_tags WHERE artwork_id = ?");
            $stmt->execute([$artworkId]);
            
            // Then add the new tags
            if (!empty($formData['tags'])) {
                $tags = explode(',', $formData['tags']);
                
                foreach ($tags as $tagName) {
                    $tagName = trim($tagName);
                    if (empty($tagName)) continue;
                    
                    // Check if tag exists
                    $stmt = $pdo->prepare("SELECT tag_id FROM tags WHERE name = ?");
                    $stmt->execute([$tagName]);
                    $tag = $stmt->fetch();
                    
                    if ($tag) {
                        $tagId = $tag['tag_id'];
                    } else {
                        // Create new tag
                        $stmt = $pdo->prepare("INSERT INTO tags (name) VALUES (?)");
                        $stmt->execute([$tagName]);
                        $tagId = $pdo->lastInsertId();
                    }
                    
                    // Associate tag with artwork
                    $stmt = $pdo->prepare("INSERT INTO artwork_tags (artwork_id, tag_id) VALUES (?, ?)");
                    $stmt->execute([$artworkId, $tagId]);
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Set success message
            $message = 'Artwork updated successfully!';
            $messageType = 'success';
            
            // Redirect to artwork page
            header('Location: artwork.php?id=' . $artworkId);
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            
            // Delete uploaded file if it exists and is new
            if (isset($updateImage) && $updateImage && isset($filePath) && file_exists($filePath)) {
                unlink($filePath);
            }
            
            error_log('Error updating artwork: ' . $e->getMessage());
            $message = 'An error occurred while updating your artwork. Please try again.';
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
    <h1 class="mb-4">Edit Artwork</h1>
    
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?>" role="alert">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="edit_artwork.php?id=<?php echo $artworkId; ?>" enctype="multipart/form-data" id="editForm">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" id="title" name="title" value="<?php echo htmlspecialchars($formData['title']); ?>" required>
                            <?php if (isset($errors['title'])): ?>
                            <div class="invalid-feedback">
                                <?php echo $errors['title']; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" id="description" name="description" rows="5" required><?php echo htmlspecialchars($formData['description']); ?></textarea>
                            <?php if (isset($errors['description'])): ?>
                            <div class="invalid-feedback">
                                <?php echo $errors['description']; ?>
                            </div>
                            <?php endif; ?>
                            <div class="form-text">Describe your artwork, including its inspiration, techniques used, and any other relevant details.</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="price" class="form-label">Price ($) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" class="form-control <?php echo isset($errors['price']) ? 'is-invalid' : ''; ?>" id="price" name="price" value="<?php echo htmlspecialchars($formData['price']); ?>" required>
                                <?php if (isset($errors['price'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['price']; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select <?php echo isset($errors['category_id']) ? 'is-invalid' : ''; ?>" id="category" name="category_id" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>" <?php echo $formData['category_id'] == $category['category_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['category_id'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['category_id']; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="tags" class="form-label">Tags</label>
                            <input type="text" class="form-control" id="tags" name="tags" placeholder="Enter tags separated by commas" value="<?php echo htmlspecialchars($formData['tags']); ?>">
                            <div class="form-text">Optional. Add tags to help users find your artwork (e.g., abstract, landscape, portrait).</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Current Image</label>
                            <div class="text-center mb-3">
                                <img src="<?php echo htmlspecialchars($artwork['image_path']); ?>" alt="<?php echo htmlspecialchars($artwork['title']); ?>" class="img-fluid img-thumbnail" style="max-height: 300px;">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="artworkImage" class="form-label">New Artwork Image (Optional)</label>
                            <input type="file" class="form-control <?php echo isset($errors['artwork_image']) ? 'is-invalid' : ''; ?>" id="artworkImage" name="artwork_image" accept="image/jpeg, image/png, image/gif">
                            <?php if (isset($errors['artwork_image'])): ?>
                            <div class="invalid-feedback">
                                <?php echo $errors['artwork_image']; ?>
                            </div>
                            <?php endif; ?>
                            <div class="form-text">Upload a new image only if you want to replace the current one. Maximum file size: 5MB. Supported formats: JPEG, PNG, GIF.</div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="text-center">
                                <img id="imagePreview" src="#" alt="New Image Preview" class="img-fluid img-thumbnail" style="max-height: 300px; display: none;">
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Update Artwork</button>
                            <a href="artwork.php?id=<?php echo $artworkId; ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Editing Guidelines</h5>
                </div>
                <div class="card-body">
                    <p>Please follow these guidelines when editing your artwork:</p>
                    
                    <ul>
                        <li>You must own the rights to the artwork you're uploading.</li>
                        <li>Artwork must be original and created by you.</li>
                        <li>Provide a clear, high-quality image of your artwork.</li>
                        <li>Write a detailed description to help potential buyers understand your work.</li>
                        <li>Set a fair price that reflects the value of your work.</li>
                        <li>Use relevant tags to make your artwork more discoverable.</li>
                    </ul>
                    
                    <p class="mb-0 text-muted small">By uploading, you agree to our <a href="#">Terms of Service</a> and <a href="#">Artist Agreement</a>.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image preview
    const artworkImage = document.getElementById('artworkImage');
    const imagePreview = document.getElementById('imagePreview');
    
    artworkImage.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
            }
            
            reader.readAsDataURL(this.files[0]);
        }
    });
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?>