USE press;

ALTER TABLE photos
  ADD COLUMN IF NOT EXISTS uploaded_by_role ENUM('author', 'runner') NOT NULL DEFAULT 'author' AFTER event_photographer_allowed;

UPDATE photos p
INNER JOIN event_users eu
    ON eu.event_id = p.event_id
   AND eu.user_id = p.user_id
   AND eu.role_in_event = 'photographer'
   AND eu.runner = 1
SET p.uploaded_by_role = 'runner'
WHERE p.uploaded_by_role = 'author';
