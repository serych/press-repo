USE press;

CREATE TABLE IF NOT EXISTS event_gallery_access (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    token CHAR(3) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    pin_hash VARCHAR(255) NULL,
    close_days_after_event SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    expires_at DATETIME NULL,
    created_by_user_id INT UNSIGNED NULL,
    updated_by_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_event_gallery_access_event_id (event_id),
    UNIQUE KEY uq_event_gallery_access_token (token),
    INDEX idx_event_gallery_access_enabled (is_enabled),
    INDEX idx_event_gallery_access_expires_at (expires_at),
    INDEX idx_event_gallery_access_created_by_user_id (created_by_user_id),
    INDEX idx_event_gallery_access_updated_by_user_id (updated_by_user_id),
    CONSTRAINT fk_event_gallery_access_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_event_gallery_access_created_by_user
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_event_gallery_access_updated_by_user
        FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT chk_event_gallery_access_token_length
        CHECK (CHAR_LENGTH(token) = 3),
    CONSTRAINT chk_event_gallery_access_close_days
        CHECK (close_days_after_event <= 365)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS journalist_gallery_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_gallery_access_id INT UNSIGNED NULL,
    event_id INT UNSIGNED NOT NULL,
    session_token CHAR(64) NOT NULL,
    ip VARBINARY(16) NULL,
    user_agent VARCHAR(500) NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    first_downloaded_at DATETIME NULL,
    download_count INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_journalist_gallery_sessions_token (session_token),
    INDEX idx_journalist_gallery_sessions_access_id (event_gallery_access_id),
    INDEX idx_journalist_gallery_sessions_event_id (event_id),
    INDEX idx_journalist_gallery_sessions_started_at (started_at),
    INDEX idx_journalist_gallery_sessions_first_downloaded_at (first_downloaded_at),
    INDEX idx_journalist_gallery_sessions_download_count (download_count),
    CONSTRAINT fk_journalist_gallery_sessions_access
        FOREIGN KEY (event_gallery_access_id) REFERENCES event_gallery_access(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_journalist_gallery_sessions_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS journalist_gallery_downloads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journalist_gallery_session_id BIGINT UNSIGNED NULL,
    event_id INT UNSIGNED NOT NULL,
    published_photo_id BIGINT UNSIGNED NULL,
    ip VARBINARY(16) NULL,
    downloaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_journalist_gallery_downloads_session_id (journalist_gallery_session_id),
    INDEX idx_journalist_gallery_downloads_event_id (event_id),
    INDEX idx_journalist_gallery_downloads_photo_id (published_photo_id),
    INDEX idx_journalist_gallery_downloads_downloaded_at (downloaded_at),
    CONSTRAINT fk_journalist_gallery_downloads_session
        FOREIGN KEY (journalist_gallery_session_id) REFERENCES journalist_gallery_sessions(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_journalist_gallery_downloads_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_journalist_gallery_downloads_photo
        FOREIGN KEY (published_photo_id) REFERENCES published_photos(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
