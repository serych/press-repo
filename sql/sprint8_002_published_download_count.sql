-- Sprint 8 - pocitadlo stazeni publikovanych fotografii
-- Spoustet nad databazi press.

ALTER TABLE published_photos
  ADD COLUMN IF NOT EXISTS download_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER published_at;

CREATE INDEX IF NOT EXISTS idx_published_photos_download_count ON published_photos (download_count);
