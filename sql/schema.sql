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
    event_photographer_allowed TINYINT(1) NOT NULL DEFAULT 1,
    filesize BIGINT UNSIGNED NULL,
    filetype VARCHAR(20) NULL,
    width INT NULL,
    height INT NULL,
    status ENUM(
        'uploaded',
        'processing',
        'ready',
        'selected',
        'deleted',
        'error'
    ) NOT NULL DEFAULT 'uploaded',
    selected TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    selected_at DATETIME NULL,
    checksum VARCHAR(64) NULL,
    INDEX idx_photos_ftp_user (ftp_user),
    INDEX idx_photos_status (status),
    INDEX idx_photos_selected (selected),
    INDEX idx_photos_uploaded_at (uploaded_at),
    CONSTRAINT fk_photos_user
        FOREIGN KEY (user_id) REFERENCES users(id)
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
    ('press_operator', 'Redaktor', 'Práce s fotografiemi, bez user managementu.', 1, 20),
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
