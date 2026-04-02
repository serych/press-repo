#!/bin/bash
set -u

FTP_ROOT="/var/www/press/ftp"
PREVIEW_ROOT="/var/www/press/previews"
LOG_FILE="/var/log/press-watcher.log"

mkdir -p "$PREVIEW_ROOT"
touch "$LOG_FILE"

log() {
    echo "$(date '+%F %T') $*" >> "$LOG_FILE"
}

# Kontrola potřebných nástrojů
if command -v convert >/dev/null 2>&1; then
    IM_CONVERT="convert"
else
    log "ERROR: Nenalezen příkaz 'convert' z ImageMagick"
    exit 1
fi

if ! command -v identify >/dev/null 2>&1; then
    log "ERROR: Nenalezen příkaz 'identify'"
    exit 1
fi

if ! command -v exiftool >/dev/null 2>&1; then
    log "ERROR: Nenalezen příkaz 'exiftool'"
    exit 1
fi

if ! command -v dcraw >/dev/null 2>&1; then
    log "WARNING: Nenalezen příkaz 'dcraw' - část RAW preview nemusí fungovat"
fi

if command -v rawtherapee-cli >/dev/null 2>&1; then
    log "INFO: rawtherapee-cli nalezen"
else
    log "WARNING: rawtherapee-cli nenalezen - moderní RAW preview fallback může selhávat"
fi

get_extension() {
    local file="$1"
    local ext="${file##*.}"
    echo "$ext" | tr '[:upper:]' '[:lower:]'
}

is_supported_file() {
    local file="$1"
    local ext
    ext="$(get_extension "$file")"

    case "$ext" in
        cr2|cr3|nef|nrw|arw|sr2|srf|raf|rw2|orf|dng|pef|iiq|3fr|jpg|jpeg)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

is_jpeg() {
    local file="$1"
    local ext
    ext="$(get_extension "$file")"

    [[ "$ext" == "jpg" || "$ext" == "jpeg" ]]
}

should_ignore_file() {
    local file="$1"
    local filename
    filename="$(basename "$file")"
    local lower
    lower="$(echo "$filename" | tr '[:upper:]' '[:lower:]')"

    # ignoruj vše mimo FTP root
    case "$file" in
        "$FTP_ROOT"/*) ;;
        *)
            return 0
            ;;
    esac

    # ignoruj cokoliv z previews
    case "$file" in
        "$PREVIEW_ROOT"/*)
            return 0
            ;;
    esac

    # ignoruj dočasné a pomocné soubory
    case "$lower" in
        *.thumb.jpg|*.thumb.jpeg|*.tmp|*.part|*.swp|*.swx|*.ds_store)
            return 0
            ;;
    esac

    # ignoruj skryté soubory
    case "$filename" in
        .*)
            return 0
            ;;
    esac

    return 1
}

wait_until_stable() {
    local file="$1"
    local last_size="-1"
    local same_count=0
    local max_tries=60
    local i=0

    while [ $i -lt $max_tries ]; do
        if [ ! -f "$file" ]; then
            return 1
        fi

        local size
        size=$(stat -c%s "$file" 2>/dev/null || echo -1)

        if [ "$size" -eq "$last_size" ] && [ "$size" -gt 0 ]; then
            same_count=$((same_count + 1))
        else
            same_count=0
            last_size="$size"
        fi

        if [ "$same_count" -ge 2 ]; then
            return 0
        fi

        sleep 1
        i=$((i + 1))
    done

    return 1
}

sql_escape() {
    echo "$1" | sed "s/'/''/g"
}

get_user_id() {
    local ftp_user="$1"
    mysql --batch --skip-column-names -e "
        SELECT id
        FROM users
        WHERE ftp_user = '$(sql_escape "$ftp_user")'
        LIMIT 1;
    " 2>/dev/null
}

get_user_author_name() {
    local ftp_user="$1"
    local result

    result="$(mysql --batch --skip-column-names -e "
        SELECT TRIM(CONCAT(COALESCE(jmeno,''), ' ', COALESCE(prijmeni,'')))
        FROM users
        WHERE ftp_user = '$(sql_escape "$ftp_user")'
        LIMIT 1;
    " 2>/dev/null)"

    if [ -n "$result" ]; then
        echo "$result"
    else
        echo "$ftp_user"
    fi
}

get_existing_photo_id() {
    local filepath="$1"
    mysql --batch --skip-column-names -e "
        SELECT id
        FROM photos
        WHERE filepath = '$(sql_escape "$filepath")'
        LIMIT 1;
    " 2>/dev/null
}

insert_photo_row() {
    local filename="$1"
    local filepath="$2"
    local ftp_user="$3"
    local user_id="$4"
    local filesize="$5"
    local filetype="$6"
    local checksum="$7"

    mysql -e "
        INSERT INTO photos (
            filename,
            filepath,
            ftp_user,
            user_id,
            filesize,
            filetype,
            status,
            checksum,
            uploaded_at
        ) VALUES (
            '$(sql_escape "$filename")',
            '$(sql_escape "$filepath")',
            '$(sql_escape "$ftp_user")',
            ${user_id:-NULL},
            ${filesize:-NULL},
            '$(sql_escape "$filetype")',
            'uploaded',
            '$(sql_escape "$checksum")',
            NOW()
        );
    " 2>/dev/null
}

update_photo_processing() {
    local photo_id="$1"
    mysql -e "
        UPDATE photos
        SET status = 'processing'
        WHERE id = ${photo_id};
    " 2>/dev/null
}

update_photo_ready() {
    local photo_id="$1"
    local preview_filename="$2"
    local preview_filepath="$3"
    local width="$4"
    local height="$5"

    mysql -e "
        UPDATE photos
        SET
            preview_filename = '$(sql_escape "$preview_filename")',
            preview_filepath = '$(sql_escape "$preview_filepath")',
            width = ${width:-NULL},
            height = ${height:-NULL},
            status = 'ready',
            processed_at = NOW()
        WHERE id = ${photo_id};
    " 2>/dev/null
}

update_photo_error() {
    local photo_id="$1"
    mysql -e "
        UPDATE photos
        SET status = 'error',
            processed_at = NOW()
        WHERE id = ${photo_id};
    " 2>/dev/null
}

insert_photo_log() {
    local photo_id="$1"
    local user_id="$2"
    local action="$3"

    mysql -e "
        INSERT INTO photo_log (
            photo_id,
            user_id,
            action,
            ip,
            created_at
        ) VALUES (
            ${photo_id},
            ${user_id:-NULL},
            '$(sql_escape "$action")',
            NULL,
            NOW()
        );
    " 2>/dev/null
}

get_image_dimensions() {
    local file="$1"
    identify -format "%w %h" "$file" 2>/dev/null
}

is_preview_too_dark() {
    local file="$1"

    if [ ! -f "$file" ]; then
        return 0
    fi

    local mean
    mean=$("$IM_CONVERT" "$file" -colorspace Gray -format "%[fx:mean]" info: 2>/dev/null || echo "0")

    # mean je 0.0 až 1.0; pod 0.01 bereme jako prakticky černé
    awk -v m="$mean" 'BEGIN { exit !(m < 0.01) }'
}

write_press_metadata() {
    local file="$1"
    local author="$2"

    if [ -z "$author" ]; then
        return 0
    fi

    if exiftool \
        -q -q \
        -overwrite_original \
        -Artist="$author" \
        -Copyright="© $author" \
        -XMP-dc:Creator="$author" \
        -XMP-dc:Rights="© $author" \
        -IPTC:By-line="$author" \
        -IPTC:CopyrightNotice="© $author" \
        -XMP-xmpRights:Marked=True \
        -IPTC:CopyrightFlag=True \
        -Description="via PressCentrum" \
        -XMP-dc:Description="via PressCentrum" \
        -IPTC:Caption-Abstract="via PressCentrum" \
        "$file" >> "$LOG_FILE" 2>&1; then

        log "Metadata zapsána: $file ($author)"
        return 0
    fi

    log "WARNING: Nepodařilo se zapsat metadata: $file"
    return 1
}

generate_preview_from_jpeg() {
    local src="$1"
    local dst="$2"

    mkdir -p "$(dirname "$dst")"

    "$IM_CONVERT" "$src" \
        -auto-orient \
        -resize "2000x2000>" \
        -strip \
        -interlace Plane \
        -quality 82 \
        "$dst" >> "$LOG_FILE" 2>&1
}

extract_embedded_preview() {
    local src="$1"
    local dst="$2"
    local ext
    ext="$(get_extension "$src")"

    mkdir -p "$(dirname "$dst")"
    rm -f "$dst"

    case "$ext" in
        nef|nrw|cr2|cr3)
            if exiftool -b -JpgFromRaw "$src" > "$dst" 2>> "$LOG_FILE"; then
                if [ -s "$dst" ]; then
                    exiftool -q -q -overwrite_original -TagsFromFile "$src" -Orientation "$dst" >> "$LOG_FILE" 2>&1 || true
                    return 0
                fi
            fi
            ;;
    esac

    if exiftool -b -PreviewImage "$src" > "$dst" 2>> "$LOG_FILE"; then
        if [ -s "$dst" ]; then
            exiftool -q -q -overwrite_original -TagsFromFile "$src" -Orientation "$dst" >> "$LOG_FILE" 2>&1 || true
            return 0
        fi
    fi

    rm -f "$dst"
    return 1
}

finalize_embedded_preview() {
    local src="$1"
    local dst="$2"

    "$IM_CONVERT" "$src" \
        -auto-orient \
        -resize "2000x2000>" \
        -strip \
        -interlace Plane \
        -quality 82 \
        "$dst" >> "$LOG_FILE" 2>&1
}

generate_preview_rawtherapee() {
    local src="$1"
    local dst="$2"
    local tmp
    tmp="$(mktemp /tmp/press_rt_XXXXXX.jpg)"

    rm -f "$tmp"

    if ! command -v rawtherapee-cli >/dev/null 2>&1; then
        return 1
    fi

    if timeout 60 rawtherapee-cli \
        -o "$tmp" \
        -c "$src" \
        -Y >> "$LOG_FILE" 2>&1; then

        if "$IM_CONVERT" "$tmp" \
            -auto-orient \
            -resize "2000x2000>" \
            -strip \
            -interlace Plane \
            -quality 82 \
            "$dst" >> "$LOG_FILE" 2>&1; then

            rm -f "$tmp"

            if is_preview_too_dark "$dst"; then
                log "Preview z rawtherapee je příliš tmavý, fallback: $src"
                rm -f "$dst"
                return 1
            fi

            log "RAW decode via rawtherapee: $src"
            return 0
        fi
    fi

    rm -f "$tmp" "$dst"
    return 1
}

generate_preview_dcraw() {
    local src="$1"
    local dst="$2"
    local tmpbase
    tmpbase="$(mktemp -u /tmp/press_preview_XXXXXX)"

    rm -f "${tmpbase}.ppm"

    if ! command -v dcraw >/dev/null 2>&1; then
        return 1
    fi

    if dcraw -c -w "$src" > "${tmpbase}.ppm" 2>> "$LOG_FILE"; then
        if "$IM_CONVERT" "${tmpbase}.ppm" \
            -auto-orient \
            -resize "2000x2000>" \
            -strip \
            -interlace Plane \
            -quality 82 \
            "$dst" >> "$LOG_FILE" 2>&1; then

            rm -f "${tmpbase}.ppm"

            if is_preview_too_dark "$dst"; then
                log "Preview z dcraw je příliš tmavý, fallback: $src"
                rm -f "$dst"
                return 1
            fi

            log "RAW decode via dcraw: $src"
            return 0
        fi
    fi

    rm -f "${tmpbase}.ppm" "$dst"
    return 1
}

generate_preview_from_raw() {
    local src="$1"
    local dst="$2"
    local ext
    ext="$(get_extension "$src")"

    mkdir -p "$(dirname "$dst")"

    local embedded_tmp
    embedded_tmp="$(mktemp /tmp/press_embedded_XXXXXX.jpg)"

    # 1) Primárně embedded preview / JpgFromRaw
    if extract_embedded_preview "$src" "$embedded_tmp"; then
        if finalize_embedded_preview "$embedded_tmp" "$dst"; then
            rm -f "$embedded_tmp"

            if is_preview_too_dark "$dst"; then
                log "Embedded preview je příliš tmavý, fallback: $src"
                rm -f "$dst"
            else
                log "RAW preview via embedded image: $src"
                return 0
            fi
        fi
    fi

    rm -f "$embedded_tmp"

    # 2) Fallback podle typu RAW
    case "$ext" in
        cr3|cr2)
            generate_preview_rawtherapee "$src" "$dst" && return 0
            generate_preview_dcraw "$src" "$dst" && return 0
            ;;
        nef|nrw)
            generate_preview_dcraw "$src" "$dst" && return 0
            generate_preview_rawtherapee "$src" "$dst" && return 0
            ;;
        *)
            generate_preview_rawtherapee "$src" "$dst" && return 0
            generate_preview_dcraw "$src" "$dst" && return 0
            ;;
    esac

    return 1
}

process_file() {
    local file="$1"

    if [ ! -f "$file" ]; then
        return 0
    fi

    if should_ignore_file "$file"; then
        log "Ignoruji soubor: $file"
        return 0
    fi

    if ! is_supported_file "$file"; then
        return 0
    fi

    if ! wait_until_stable "$file"; then
        log "Soubor se nestabilizoval: $file"
        return 1
    fi

    local rel_path="${file#$FTP_ROOT/}"
    local ftp_user="${rel_path%%/*}"

    if [ -z "$ftp_user" ] || [ "$ftp_user" = "$rel_path" ]; then
        log "Nepodařilo se určit ftp_user z cesty: $file"
        return 1
    fi

    local filename
    filename="$(basename "$file")"

    local filetype="raw"
    if is_jpeg "$file"; then
        filetype="jpeg"
    fi

    local user_id
    user_id="$(get_user_id "$ftp_user")"
    [ -z "$user_id" ] && user_id="NULL"

    local author_name
    author_name="$(get_user_author_name "$ftp_user")"

    local filesize
    filesize="$(stat -c%s "$file" 2>/dev/null || echo NULL)"

    local checksum
    checksum="$(sha256sum "$file" 2>/dev/null | awk '{print $1}')"

    local existing_id
    existing_id="$(get_existing_photo_id "$file")"

local photo_id
local is_new_file=0

if [ -n "$existing_id" ]; then
    photo_id="$existing_id"
else
    insert_photo_row "$filename" "$file" "$ftp_user" "$user_id" "$filesize" "$filetype" "$checksum"
    photo_id="$(mysql --batch --skip-column-names -e "SELECT id FROM photos WHERE filepath = '$(sql_escape "$file")' ORDER BY id DESC LIMIT 1;" 2>/dev/null)"

    if [ -z "$photo_id" ]; then
        log "Nepodařilo se vložit DB záznam: $file"
        return 1
    fi

    insert_photo_log "$photo_id" "$user_id" "upload"
    is_new_file=1
fi

update_photo_processing "$photo_id"

# Zápis metadat jen při prvním zpracování nového souboru
if [ "$is_new_file" -eq 1 ]; then
    write_press_metadata "$file" "$author_name"
fi

    local base_no_ext="${filename%.*}"
    local preview_dir="$PREVIEW_ROOT/$ftp_user"
    local preview_filename="${base_no_ext}.jpg"
    local preview_filepath="$preview_dir/$preview_filename"

    local ok=1
    if is_jpeg "$file"; then
        generate_preview_from_jpeg "$file" "$preview_filepath" || ok=0
    else
        generate_preview_from_raw "$file" "$preview_filepath" || ok=0
    fi

    if [ $ok -ne 1 ] || [ ! -f "$preview_filepath" ]; then
        update_photo_error "$photo_id"
        log "Preview generation FAILED: $file"
        return 1
    fi

    local dims
    dims="$(get_image_dimensions "$preview_filepath")"

    local width=""
    local height=""
    if [ -n "$dims" ]; then
        width="$(echo "$dims" | awk '{print $1}')"
        height="$(echo "$dims" | awk '{print $2}')"
    fi

    update_photo_ready "$photo_id" "$preview_filename" "$preview_filepath" "$width" "$height"
    insert_photo_log "$photo_id" "$user_id" "preview_generated"

    log "Zpracováno OK: $file -> $preview_filepath"
    return 0
}

reprocess_existing() {
    log "Press watcher reprocess start"

    find "$FTP_ROOT" -type f | while read -r file; do
        process_file "$file"
    done
}

run_watcher() {
    log "Press watcher start"

    inotifywait -m -r \
        -e close_write -e moved_to \
        --format '%w%f' \
        "$FTP_ROOT" | while read -r file; do
        process_file "$file"
    done
}

if [[ "${1:-}" == "--reprocess" ]]; then
    reprocess_existing
    exit 0
fi

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    run_watcher
fi