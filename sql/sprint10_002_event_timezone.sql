USE press;

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Prague' AFTER ends_at;

UPDATE events
SET timezone = 'Europe/Prague'
WHERE timezone IS NULL
   OR timezone = '';
