-- Sprint 9 - odstraneni odkazu na cloudovy disk z eventu
-- Spoustet nad databazi press.

ALTER TABLE events
  DROP COLUMN IF EXISTS cloud_url;
