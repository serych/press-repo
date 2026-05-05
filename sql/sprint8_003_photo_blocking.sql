USE press;

ALTER TABLE photos
  ADD COLUMN IF NOT EXISTS is_blocked TINYINT(1) NOT NULL DEFAULT 0 AFTER event_photographer_allowed,
  ADD COLUMN IF NOT EXISTS blocked_by_user_id INT UNSIGNED NULL AFTER is_blocked,
  ADD COLUMN IF NOT EXISTS blocked_at DATETIME NULL AFTER blocked_by_user_id;

CREATE INDEX IF NOT EXISTS idx_photos_is_blocked ON photos (is_blocked);
CREATE INDEX IF NOT EXISTS idx_photos_blocked_by_user_id ON photos (blocked_by_user_id);

SET @fk_photos_blocked_by_user_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'photos'
      AND COLUMN_NAME = 'blocked_by_user_id'
      AND REFERENCED_TABLE_NAME = 'users'
);

SET @fk_photos_blocked_by_user_sql = IF(
    @fk_photos_blocked_by_user_exists > 0,
    'SELECT 1',
    'ALTER TABLE photos ADD CONSTRAINT fk_photos_blocked_by_user FOREIGN KEY (blocked_by_user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE'
);

PREPARE fk_photos_blocked_by_user_stmt FROM @fk_photos_blocked_by_user_sql;
EXECUTE fk_photos_blocked_by_user_stmt;
DEALLOCATE PREPARE fk_photos_blocked_by_user_stmt;
