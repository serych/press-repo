-- Sprint 8 - databazovy zaklad vystupniho uloziste hotovych fotografii
-- Spoustet nad databazi press.

ALTER TABLE photos
  ADD COLUMN IF NOT EXISTS captured_at DATETIME NULL AFTER event_photographer_allowed,
  ADD COLUMN IF NOT EXISTS downloaded_by_user_id INT UNSIGNED NULL AFTER downloaded_at;

CREATE INDEX IF NOT EXISTS idx_photos_captured_at ON photos (captured_at);
CREATE INDEX IF NOT EXISTS idx_photos_event_captured_at ON photos (event_id, captured_at);
CREATE INDEX IF NOT EXISTS idx_photos_downloaded_by_user_id ON photos (downloaded_by_user_id);

SET @fk_photos_downloaded_by_user_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'photos'
      AND COLUMN_NAME = 'downloaded_by_user_id'
      AND REFERENCED_TABLE_NAME = 'users'
);

SET @fk_photos_downloaded_by_user_sql = IF(
    @fk_photos_downloaded_by_user_exists > 0,
    'SELECT 1',
    'ALTER TABLE photos ADD CONSTRAINT fk_photos_downloaded_by_user FOREIGN KEY (downloaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE'
);

PREPARE fk_photos_downloaded_by_user_stmt FROM @fk_photos_downloaded_by_user_sql;
EXECUTE fk_photos_downloaded_by_user_stmt;
DEALLOCATE PREPARE fk_photos_downloaded_by_user_stmt;

CREATE TABLE IF NOT EXISTS published_photos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    source_photo_id BIGINT UNSIGNED NULL,
    uploaded_by_user_id INT UNSIGNED NULL,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL,
    preview_filename VARCHAR(255) NULL,
    preview_filepath VARCHAR(500) NULL,
    filesize BIGINT UNSIGNED NULL,
    filetype VARCHAR(20) NULL,
    width INT NULL,
    height INT NULL,
    checksum VARCHAR(64) NULL,
    captured_at DATETIME NULL,
    source_uploaded_at DATETIME NULL,
    editor_downloaded_at DATETIME NULL,
    published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('ready', 'hidden', 'deleted') NOT NULL DEFAULT 'ready',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_photos_event_id (event_id),
    INDEX idx_published_photos_source_photo_id (source_photo_id),
    INDEX idx_published_photos_uploaded_by_user_id (uploaded_by_user_id),
    INDEX idx_published_photos_status (status),
    INDEX idx_published_photos_published_at (published_at),
    INDEX idx_published_photos_captured_at (captured_at),
    INDEX idx_published_photos_event_published_at (event_id, published_at),
    CONSTRAINT fk_published_photos_source_photo
        FOREIGN KEY (source_photo_id) REFERENCES photos(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_published_photos_uploaded_by_user
        FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS published_photo_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    published_photo_id BIGINT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    action ENUM('uploaded', 'downloaded', 'hidden', 'restored', 'deleted', 'paired', 'unpaired') NOT NULL,
    ip VARBINARY(16) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_published_photo_log_photo_id (published_photo_id),
    INDEX idx_published_photo_log_user_id (user_id),
    INDEX idx_published_photo_log_action (action),
    INDEX idx_published_photo_log_created_at (created_at),
    CONSTRAINT fk_published_photo_log_photo
        FOREIGN KEY (published_photo_id) REFERENCES published_photos(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_published_photo_log_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

INSERT INTO permissions (code, name, description)
VALUES
    ('published_photos.view', 'Zobrazit hotové fotografie', 'Může prohlížet výstupní úložiště hotových fotografií.'),
    ('published_photos.upload', 'Nahrávat hotové fotografie', 'Může nahrávat upravené fotografie do výstupního úložiště.'),
    ('published_photos.download', 'Stahovat hotové fotografie', 'Může stahovat hotové fotografie z výstupního úložiště.'),
    ('published_photos.manage', 'Spravovat hotové fotografie', 'Může skrývat, mazat a ručně párovat hotové fotografie.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description);

INSERT INTO role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1
FROM roles r
JOIN permissions p
WHERE r.code IN ('superadmin', 'admin')
  AND p.code IN ('published_photos.view', 'published_photos.upload', 'published_photos.download', 'published_photos.manage')
ON DUPLICATE KEY UPDATE allowed = VALUES(allowed);

INSERT INTO role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1
FROM roles r
JOIN permissions p
WHERE r.code = 'press_operator'
  AND p.code IN ('published_photos.view', 'published_photos.upload')
ON DUPLICATE KEY UPDATE allowed = VALUES(allowed);

INSERT INTO role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1
FROM roles r
JOIN permissions p
WHERE r.code = 'journalist'
  AND p.code IN ('published_photos.view', 'published_photos.download', 'dashboard.view')
ON DUPLICATE KEY UPDATE allowed = VALUES(allowed);
