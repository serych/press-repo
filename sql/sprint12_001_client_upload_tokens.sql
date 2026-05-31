USE press;

CREATE TABLE IF NOT EXISTS client_upload_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    scope ENUM('published_upload') NOT NULL DEFAULT 'published_upload',
    token_prefix CHAR(16) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    is_revoked TINYINT(1) NOT NULL DEFAULT 0,
    expires_at DATETIME NULL,
    last_used_at DATETIME NULL,
    last_used_ip VARBINARY(16) NULL,
    last_used_user_agent VARCHAR(500) NULL,
    created_by_user_id INT UNSIGNED NULL,
    revoked_by_user_id INT UNSIGNED NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_client_upload_tokens_hash (token_hash),
    INDEX idx_client_upload_tokens_user_id (user_id),
    INDEX idx_client_upload_tokens_scope (scope),
    INDEX idx_client_upload_tokens_prefix (token_prefix),
    INDEX idx_client_upload_tokens_revoked (is_revoked),
    INDEX idx_client_upload_tokens_expires_at (expires_at),
    INDEX idx_client_upload_tokens_last_used_at (last_used_at),
    INDEX idx_client_upload_tokens_created_by_user_id (created_by_user_id),
    INDEX idx_client_upload_tokens_revoked_by_user_id (revoked_by_user_id),
    CONSTRAINT fk_client_upload_tokens_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_client_upload_tokens_created_by_user
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_client_upload_tokens_revoked_by_user
        FOREIGN KEY (revoked_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT chk_client_upload_tokens_prefix
        CHECK (CHAR_LENGTH(token_prefix) >= 6),
    CONSTRAINT chk_client_upload_tokens_revoked_at
        CHECK ((is_revoked = 0 AND revoked_at IS NULL) OR is_revoked = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO permissions (code, name, description)
VALUES
    ('client_upload_tokens.manage', 'Spravovat klientské upload tokeny', 'Může vytvářet a rušit tokeny pro klientský upload hotových fotografií.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1
FROM roles r
JOIN permissions p
WHERE r.code = 'admin'
  AND p.code = 'client_upload_tokens.manage';
