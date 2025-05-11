<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$isArtist = $isLoggedIn && ($_SESSION['role'] == 'artist' || $_SESSION['role'] == 'admin');
$isAdmin = $isLoggedIn && $_SESSION['role'] == 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ArtLoop' : 'ArtLoop - Online Digital Art Gallery'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/css/style.css">
    
    <?php if (isset($extraCSS)) echo $extraCSS; ?>
</head>
<body>
    <!-- Header -->
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="/">
                    <i class="fas fa-palette me-2"></i>ArtLoop
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="/">Home</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="categoriesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Categories
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="categoriesDropdown">
                                <?php
                                // Include database connection
                                require_once 'db_connect.php';
                                $pdo = getDBConnection();
                                
                                // Get categories from database
                                $stmt = $pdo->query("SELECT category_id, name FROM categories ORDER BY name");
                                while ($category = $stmt->fetch()) {
                                    echo '<li><a class="dropdown-item" href="/category.php?id=' . $category['category_id'] . '">' . htmlspecialchars($category['name']) . '</a></li>';
                                }
                                ?>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/search.php">Search</a>
                        </li>
                        <?php if ($isArtist): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/artist/dashboard.php">Artist Dashboard</a>
                        </li>
                        <?php endif; ?>
                        <?php if ($isAdmin): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/dashboard.php">Admin Panel</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                    
                    <!-- Search form -->
                    <form class="d-flex me-2" action="/search.php" method="GET">
                        <input class="form-control me-2" type="search" name="q" placeholder="Search artworks..." aria-label="Search">
                        <button class="btn btn-outline-light" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                    
                    <ul class="navbar-nav">
                        <?php if ($isLoggedIn): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/cart.php">
                                <i class="fas fa-shopping-cart"></i>
                                <span id="cart-count" class="badge bg-primary">
                                    <?php
                                    // Get cart count
                                    $userId = $_SESSION['user_id'];
                                    $stmt = $pdo->prepare("
                                        SELECT COUNT(*) as count FROM cart_items ci
                                        JOIN cart c ON ci.cart_id = c.cart_id
                                        WHERE c.user_id = ?
                                    ");
                                    $stmt->execute([$userId]);
                                    $cartCount = $stmt->fetch()['count'];
                                    echo $cartCount;
                                    ?>
                                </span>
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="/profile.php">My Profile</a></li>
                                <li><a class="dropdown-item" href="/orders.php">My Orders</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/logout.php">Logout</a></li>
                            </ul>
                        </li>
                        <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/register.php">Register</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    
    <!-- Main Content Container -->
    <main class="container py-4">