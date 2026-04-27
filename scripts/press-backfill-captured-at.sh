#!/bin/bash
set -u

WATCHER="/usr/local/bin/press-watcher.sh"
LOG_FILE="/var/log/press-backfill-captured-at.log"

touch "$LOG_FILE"

log() {
    echo "$(date '+%F %T') $*" >> "$LOG_FILE"
}

if [ ! -f "$WATCHER" ]; then
    log "ERROR: watcher nenalezen: $WATCHER"
    exit 1
fi

source "$WATCHER"

log "Captured_at backfill start"

mysql --batch --raw --skip-column-names -e "
    SELECT id, filepath
    FROM photos
    WHERE captured_at IS NULL
      AND filepath IS NOT NULL
      AND filepath <> ''
    ORDER BY id ASC;
" 2>> "$LOG_FILE" | while IFS=$'\t' read -r photo_id filepath; do
    if [ -z "$photo_id" ] || [ -z "$filepath" ]; then
        continue
    fi

    if [ ! -f "$filepath" ]; then
        log "MISS photo_id=$photo_id file=$filepath"
        continue
    fi

    captured_at="$(read_exif_captured_at "$filepath")"

    if [ -z "$captured_at" ]; then
        log "NO_EXIF photo_id=$photo_id file=$filepath"
        continue
    fi

    update_photo_captured_at "$photo_id" "$captured_at"
    log "OK photo_id=$photo_id captured_at=$captured_at file=$filepath"
done

log "Captured_at backfill done"
