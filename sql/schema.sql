-- Press centrum - databazove schema
-- UTF-8 / MariaDB / InnoDB

CREATE DATABASE IF NOT EXISTS press
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_czech_ci;

USE press;

CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 1,
    priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    allowed TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_role_permissions_permission
        FOREIGN KEY (permission_id) REFERENCES permissions(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jmeno VARCHAR(100) NOT NULL,
    prijmeni VARCHAR(100) NOT NULL,
    user VARCHAR(100) NOT NULL UNIQUE,
    pass_hash VARCHAR(255) NOT NULL,
    ftp_user VARCHAR(100) NOT NULL UNIQUE,
    ftp_pass_hash VARCHAR(255) NOT NULL,
    homedir VARCHAR(255) NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    last_login_ip VARBINARY(16) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT chk_users_homedir
        CHECK (homedir <> '')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS photos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL,
    preview_filename VARCHAR(255) NULL,
    preview_filepath VARCHAR(500) NULL,
    ftp_user VARCHAR(100) NOT NULL,
    user_id INT UNSIGNED NULL,
    event_id INT UNSIGNED NULL,
    event_photographer_allowed TINYINT(1) NOT NULL DEFAULT 1,
    uploaded_by_role ENUM('author', 'runner') NOT NULL DEFAULT 'author',
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    blocked_by_user_id INT UNSIGNED NULL,
    blocked_at DATETIME NULL,
    captured_at DATETIME NULL,
    exif_problem TINYINT(1) NOT NULL DEFAULT 0,
    exif_problem_note VARCHAR(255) NULL,
    locked_by_user_id INT UNSIGNED NULL,
    locked_at DATETIME NULL,
    downloaded TINYINT(1) NOT NULL DEFAULT 0,
    downloaded_at DATETIME NULL,
    downloaded_by_user_id INT UNSIGNED NULL,
    filesize BIGINT UNSIGNED NULL,
    filetype VARCHAR(20) NULL,
    width INT NULL,
    height INT NULL,
    status ENUM(
        'uploaded',
        'processing',
        'ready',
        'selected',
        'locked',
        'downloaded',
        'deleted',
        'error'
    ) NOT NULL DEFAULT 'uploaded',
    selected TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    selected_at DATETIME NULL,
    checksum VARCHAR(64) NULL,
    INDEX idx_photos_event_id (event_id),
    INDEX idx_photos_ftp_user (ftp_user),
    INDEX idx_photos_user_id (user_id),
    INDEX idx_photos_status (status),
    INDEX idx_photos_is_blocked (is_blocked),
    INDEX idx_photos_blocked_by_user_id (blocked_by_user_id),
    INDEX idx_photos_selected (selected),
    INDEX idx_photos_captured_at (captured_at),
    INDEX idx_photos_event_captured_at (event_id, captured_at),
    INDEX idx_photos_uploaded_at (uploaded_at),
    INDEX idx_photos_downloaded_by_user_id (downloaded_by_user_id),
    CONSTRAINT fk_photos_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_photos_downloaded_by_user
        FOREIGN KEY (downloaded_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_photos_blocked_by_user
        FOREIGN KEY (blocked_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

CREATE TABLE IF NOT EXISTS photo_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    photo_id BIGINT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    action ENUM(
        'upload',
        'preview_generated',
        'selected',
        'downloaded',
        'deleted'
    ) NOT NULL,
    ip VARBINARY(16) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_photo_log_photo_id (photo_id),
    INDEX idx_photo_log_action (action),
    INDEX idx_photo_log_created_at (created_at),
    CONSTRAINT fk_photo_log_photo
        FOREIGN KEY (photo_id) REFERENCES photos(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_photo_log_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

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
    author_label VARCHAR(255) NULL,
    captured_at DATETIME NULL,
    source_uploaded_at DATETIME NULL,
    editor_downloaded_at DATETIME NULL,
    published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    download_count INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('ready', 'hidden', 'deleted') NOT NULL DEFAULT 'ready',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_published_photos_event_id (event_id),
    INDEX idx_published_photos_source_photo_id (source_photo_id),
    INDEX idx_published_photos_uploaded_by_user_id (uploaded_by_user_id),
    INDEX idx_published_photos_status (status),
    INDEX idx_published_photos_published_at (published_at),
    INDEX idx_published_photos_download_count (download_count),
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
    action ENUM(
        'uploaded',
        'downloaded',
        'hidden',
        'restored',
        'deleted',
        'paired',
        'unpaired'
    ) NOT NULL,
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

-- View pro vsftpd / PAM autentizaci
CREATE OR REPLACE VIEW v_vsftpd_users AS
SELECT
    ftp_user      AS username,
    ftp_pass_hash AS password
FROM users
WHERE is_active = 1
  AND ftp_user IS NOT NULL
  AND ftp_user <> ''
  AND ftp_pass_hash IS NOT NULL
  AND ftp_pass_hash <> '';

-- Vychozi role
INSERT INTO roles (code, name, description, is_system, priority)
VALUES
    ('superadmin', 'Hlavní admin', 'Veškerá práva včetně práva editovat všechny uživatele.', 1, 1),
    ('admin', 'Admin', 'Veškerá běžná administrátorská práva, ale nesmí upravovat hlavního admina.', 1, 10),
    ('press_operator', 'Fotoeditor', 'Práce s fotografiemi, bez user managementu.', 1, 20),
    ('photographer', 'Fotograf', 'Nahrávání fotografií a pasivní přístup do webového rozhraní.', 1, 30),
    ('journalist', 'Žurnalista', 'Uživatel hotových fotografií, připravený pro budoucí čtecí přístup.', 1, 40)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    is_system = VALUES(is_system),
    priority = VALUES(priority);

-- Vychozi opravneni
INSERT INTO permissions (code, name, description)
VALUES
    ('users.view', 'Zobrazit uživatele', 'Může zobrazit seznam a detail uživatelů.'),
    ('users.create', 'Vytvořit uživatele', 'Může zakládat nové uživatele.'),
    ('users.edit', 'Upravit uživatele', 'Může upravovat existující uživatele.'),
    ('users.delete', 'Smazat uživatele', 'Může mazat uživatele.'),
    ('users.edit_superadmin', 'Upravit hlavního admina', 'Může upravovat účet hlavního admina.'),
    ('roles.view', 'Zobrazit role', 'Může zobrazit role a jejich oprávnění.'),
    ('roles.assign', 'Přiřadit roli', 'Může měnit role uživatelům.'),
    ('photos.view', 'Zobrazit fotografie', 'Může prohlížet fotografie ve webovém rozhraní.'),
    ('photos.download', 'Stahovat fotografie', 'Může stahovat originály fotografií.'),
    ('photos.delete', 'Mazat fotografie', 'Může mazat fotografie ze storage.'),
    ('photos.select', 'Označovat / vybírat fotografie', 'Může vybírat fotografie pro další zpracování.'),
    ('published_photos.view', 'Zobrazit hotové fotografie', 'Může prohlížet výstupní úložiště hotových fotografií.'),
    ('published_photos.upload', 'Nahrávat hotové fotografie', 'Může nahrávat upravené fotografie do výstupního úložiště.'),
    ('published_photos.download', 'Stahovat hotové fotografie', 'Může stahovat hotové fotografie z výstupního úložiště.'),
    ('published_photos.manage', 'Spravovat hotové fotografie', 'Může skrývat, mazat a ručně párovat hotové fotografie.'),
    ('ftp.upload', 'Nahrávat přes FTP', 'Může nahrávat fotografie přes FTP/FTPS.'),
    ('logs.view', 'Zobrazit logy', 'Může prohlížet provozní logy a historii akcí.'),
    ('dashboard.view', 'Přístup do webového rozhraní', 'Může se přihlásit do webové aplikace.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description);

-- Vazba roli na opravneni (idempotentne)
INSERT IGNORE INTO role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1
FROM roles r
JOIN permissions p
WHERE r.code = 'superadmin';

INSERT IGNORE INTO role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1
FROM roles r
JOIN permissions p
WHERE r.code = 'admin'
  AND p.code IN (
    'users.view',
    'users.create',
    'users.edit',
    'users.delete',
    'roles.view',
    'roles.assign',
    'photos.view',
    'photos.download',
    'photos.delete',
    'photos.select',
    'published_photos.view',
    'published_photos.upload',
    'published_photos.download',
    'published_photos.manage',
    'ftp.upload',
    'logs.view',
    'dashboard.view'
  );

INSERT IGNORE INTO role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1
FROM roles r
JOIN permissions p
WHERE r.code = 'press_operator'
  AND p.code IN (
    'photos.view',
    'photos.download',
    'photos.delete',
    'photos.select',
    'published_photos.view',
    'published_photos.upload',
    'logs.view',
    'dashboard.view'
  );

INSERT IGNORE INTO role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1
FROM roles r
JOIN permissions p
WHERE r.code = 'photographer'
  AND p.code IN (
    'photos.view',
    'ftp.upload',
    'dashboard.view'
  );

INSERT IGNORE INTO role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1
FROM roles r
JOIN permissions p
WHERE r.code = 'journalist'
  AND p.code IN (
    'published_photos.view',
    'published_photos.download',
    'dashboard.view'
  );
