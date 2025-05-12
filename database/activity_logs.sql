-- Activity Logs Table
CREATE TABLE IF NOT EXISTS activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action_type ENUM('login', 'logout', 'register', 'upload', 'edit', 'delete', 'purchase', 'like', 'comment', 'admin_action') NOT NULL,
    action_description TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Create index for faster queries
CREATE INDEX idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_action_type ON activity_logs(action_type);
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);

-- Sample data for testing
INSERT INTO activity_logs (user_id, action_type, action_description, ip_address, user_agent) VALUES
(1, 'login', 'Admin user logged in', '127.0.0.1', 'Mozilla/5.0'),
(1, 'admin_action', 'Viewed admin dashboard', '127.0.0.1', 'Mozilla/5.0'),
(1, 'admin_action', 'Updated user role for user ID 2', '127.0.0.1', 'Mozilla/5.0'),
(2, 'login', 'Artist user logged in', '127.0.0.1', 'Mozilla/5.0'),
(2, 'upload', 'Uploaded new artwork: Sample Artwork', '127.0.0.1', 'Mozilla/5.0'),
(3, 'register', 'New user registered', '127.0.0.1', 'Mozilla/5.0'),
(3, 'login', 'User logged in', '127.0.0.1', 'Mozilla/5.0'),
(3, 'like', 'Liked artwork ID 1', '127.0.0.1', 'Mozilla/5.0'),
(3, 'comment', 'Commented on artwork ID 1', '127.0.0.1', 'Mozilla/5.0'),
(3, 'purchase', 'Purchased artwork ID 1', '127.0.0.1', 'Mozilla/5.0');