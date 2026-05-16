-- Sprint 9 - GPS souradnice eventu pro zapis do EXIFu fotografii
-- Spoustet nad databazi press.

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS gps_latitude VARCHAR(64) NULL AFTER cav_gallery_url,
  ADD COLUMN IF NOT EXISTS gps_latitude_ref CHAR(1) NULL AFTER gps_latitude,
  ADD COLUMN IF NOT EXISTS gps_longitude VARCHAR(64) NULL AFTER gps_latitude_ref,
  ADD COLUMN IF NOT EXISTS gps_longitude_ref CHAR(1) NULL AFTER gps_longitude;
