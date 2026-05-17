USE press;

CREATE TABLE IF NOT EXISTS help_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL,
    filesize BIGINT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    uploaded_by_user_id INT UNSIGNED NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_help_documents_sort_order (sort_order),
    INDEX idx_help_documents_uploaded_by_user_id (uploaded_by_user_id),
    CONSTRAINT fk_help_documents_uploaded_by_user
        FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;
