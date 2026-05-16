-- Sprint 9 - nadmorska vyska eventu pro zapis do EXIF GPSAltitude
-- Spoustet nad databazi press.

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS gps_altitude VARCHAR(32) NULL AFTER gps_longitude_ref,
  ADD COLUMN IF NOT EXISTS gps_altitude_ref CHAR(1) NULL AFTER gps_altitude;
