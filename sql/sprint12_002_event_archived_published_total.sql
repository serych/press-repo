USE press;

ALTER TABLE events
  ADD COLUMN IF NOT EXISTS archived_published_total INT UNSIGNED NOT NULL DEFAULT 0
  AFTER archived_downloaded_total;

UPDATE events e
LEFT JOIN (
    SELECT
        event_id,
        COUNT(*) AS published_total
    FROM published_photos
    WHERE status = 'ready'
    GROUP BY event_id
) pp ON pp.event_id = e.id
SET e.archived_published_total = GREATEST(
    e.archived_published_total,
    COALESCE(pp.published_total, 0)
);
