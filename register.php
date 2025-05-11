<?php
// Set page title
$pageTitle = 'Register';

// Include database connection
require_once 'includes/db_connect.php';

// Initialize variables
$username = '';
$email = '';
$firstName = '';
$lastName = '';
$role = '';
$errors = [];
$success = false;

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $role = $_POST['role'] ?? 'user';
    
    // Validate username
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    } elseif (strlen($username) < 3) {
        $errors['username'] = 'Username must be at least 3 characters';
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $errors['username'] = 'Username already exists';
        }
    }
    
    // Validate email
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = 'Email already exists';
        }
    }
    
    // Validate password
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters';
    }
    
    // Validate confirm password
    if (empty($confirmPassword)) {
        $errors['confirmPassword'] = 'Please confirm your password';
    } elseif ($password !== $confirmPassword) {
        $errors['confirmPassword'] = 'Passwords do not match';
    }
    
    // Validate role
    if (!in_array($role, ['user', 'artist'])) {
        $errors['role'] = 'Invalid role selected';
    }
    
    // If no errors, create user
    if (empty($errors)) {
        try {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user into database
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, role)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $email, $hashedPassword, $firstName, $lastName, $role]);
            
            // Create cart for user
            $userId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO cart (user_id) VALUES (?)");
            $stmt->execute([$userId]);
            
            // Set success message
            $success = true;
            
            // Clear form data
            $username = '';
            $email = '';
            $firstName = '';
            $lastName = '';
            $role = '';
        } catch (PDOException $e) {
            $errors['general'] = 'Registration failed: ' . $e->getMessage();
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow">
            <div class="card-body p-5">
                <h1 class="card-title text-center mb-4">Create an Account</h1>
                
                <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <h4 class="alert-heading">Registration Successful!</h4>
                    <p>Your account has been created successfully. You can now <a href="login.php" class="alert-link">login</a> with your credentials.</p>
                </div>
                <?php else: ?>
                
                <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($errors['general']); ?>
                </div>
                <?php endif; ?>
                
                <form id="registerForm" method="POST" action="register.php" novalidate>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                        <?php if (isset($errors['username'])): ?>
                        <div class="invalid-feedback">
                            <?php echo htmlspecialchars($errors['username']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        <?php if (isset($errors['email'])): ?>
                        <div class="invalid-feedback">
                            <?php echo htmlspecialchars($errors['email']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($firstName); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo htmlspecialchars($lastName); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                        <?php if (isset($errors['password'])): ?>
                        <div class="invalid-feedback">
                            <?php echo htmlspecialchars($errors['password']); ?>
                        </div>
                        <?php endif; ?>
                        <div class="form-text">Password must be at least 6 characters long.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirmPassword" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?php echo isset($errors['confirmPassword']) ? 'is-invalid' : ''; ?>" id="confirmPassword" name="confirmPassword" required>
                        <?php if (isset($errors['confirmPassword'])): ?>
                        <div class="invalid-feedback">
                            <?php echo htmlspecialchars($errors['confirmPassword']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">I am registering as a <span class="text-danger">*</span></label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="role" id="roleUser" value="user" <?php echo ($role === 'user' || $role === '') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="roleUser">
                                Collector (I want to browse and purchase artworks)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="role" id="roleArtist" value="artist" <?php echo $role === 'artist' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="roleArtist">
                                Artist (I want to sell my artworks)
                            </label>
                        </div>
                        <?php if (isset($errors['role'])): ?>
                        <div class="text-danger small">
                            <?php echo htmlspecialchars($errors['role']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Create Account</button>
                    </div>
                </form>
                
                <div class="mt-4 text-center">
                    <p>Already have an account? <a href="login.php">Login</a></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>