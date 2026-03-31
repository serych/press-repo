#!/bin/bash
set -u

WATCHER="/usr/local/bin/press-watcher.sh"
FTP_ROOT="/var/www/press/ftp"
LOG_FILE="/var/log/press-backfill.log"

touch "$LOG_FILE"

log() {
    echo "$(date '+%F %T') $*" >> "$LOG_FILE"
}

if [ ! -f "$WATCHER" ]; then
    log "ERROR: watcher nenalezen: $WATCHER"
    exit 1
fi

# načteme funkce z watcheru
source "$WATCHER"

log "Backfill start"

find "$FTP_ROOT" -type f | while read -r file; do

    # ignoruj preview a temp
    filename="$(basename "$file")"
    lower="$(echo "$filename" | tr '[:upper:]' '[:lower:]')"

    case "$lower" in
        *.thumb.jpg|*.thumb.jpeg|*.tmp|*.part|*.swp|*.swx|.ds_store)
            log "IGN skip thumb/temp: $file"
            continue
            ;;
    esac

    # jen podporované
    if ! is_supported_file "$file"; then
        continue
    fi

    log "BACKFILL: $file"
    process_file "$file"

done

log "Backfill done"