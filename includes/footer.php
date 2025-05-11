    </main>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5><i class="fas fa-palette me-2"></i>ArtLoop</h5>
                    <p class="text-muted">Discover and collect unique digital artworks from talented artists around the world.</p>
                    <div class="social-icons">
                        <a href="#" class="text-white me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-pinterest"></i></a>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <h5>Explore</h5>
                    <ul class="list-unstyled">
                        <li><a href="/" class="text-muted">Home</a></li>
                        <li><a href="/search.php" class="text-muted">Search</a></li>
                        <li><a href="/category.php" class="text-muted">Categories</a></li>
                        <li><a href="/artists.php" class="text-muted">Artists</a></li>
                    </ul>
                </div>
                <div class="col-md-2 mb-3">
                    <h5>Account</h5>
                    <ul class="list-unstyled">
                        <?php if ($isLoggedIn): ?>
                        <li><a href="/profile.php" class="text-muted">My Profile</a></li>
                        <li><a href="/orders.php" class="text-muted">My Orders</a></li>
                        <li><a href="/logout.php" class="text-muted">Logout</a></li>
                        <?php else: ?>
                        <li><a href="/login.php" class="text-muted">Login</a></li>
                        <li><a href="/register.php" class="text-muted">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Newsletter</h5>
                    <p class="text-muted">Subscribe to our newsletter for updates on new artworks and features.</p>
                    <form class="d-flex">
                        <input type="email" class="form-control me-2" placeholder="Your email">
                        <button type="submit" class="btn btn-primary">Subscribe</button>
                    </form>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> ArtLoop. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <ul class="list-inline mb-0">
                        <li class="list-inline-item"><a href="/terms.php" class="text-muted">Terms of Service</a></li>
                        <li class="list-inline-item"><a href="/privacy.php" class="text-muted">Privacy Policy</a></li>
                        <li class="list-inline-item"><a href="/contact.php" class="text-muted">Contact Us</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="/js/main.js"></script>
    
    <?php if (isset($extraJS)) echo $extraJS; ?>
</body>
</html>