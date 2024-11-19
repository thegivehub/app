CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id bigint(20) unsigned NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_password_resets_token ON password_resets(token);
CREATE INDEX idx_password_resets_expires ON password_resets(expires);
