-- Sprint 9 - cache autora publikovane fotografie pro rychle zobrazeni galerie
-- Spoustet nad databazi press.

ALTER TABLE published_photos
  ADD COLUMN IF NOT EXISTS author_label VARCHAR(255) NULL AFTER checksum;
