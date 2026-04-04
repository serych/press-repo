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

normalize_spaces() {
    printf '%s' "$1" | tr '\r\n\t' '   ' | sed -E 's/[[:space:]]+/ /g; s/^ +//; s/ +$//'
}

normalize_author() {
    local value="$1"

    value="$(normalize_spaces "$value")"

    if command -v iconv >/dev/null 2>&1; then
        value="$(printf '%s' "$value" | iconv -f UTF-8 -t ASCII//TRANSLIT 2>/dev/null || printf '%s' "$value")"
    fi

    value="$(printf '%s' "$value" \
        | tr '[:upper:]' '[:lower:]' \
        | sed -E 's/[\"'"'"'`´.,;:_+=~!?|(){}\[\]<>\/\\-]+/ /g; s/[[:space:]]+/ /g; s/^ +//; s/ +$//')"

    printf '%s\n' "$value"
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
        SELECT
            CASE
                WHEN TRIM(CONCAT(COALESCE(jmeno,''), ' ', COALESCE(prijmeni,''))) <> '' THEN TRIM(CONCAT(COALESCE(jmeno,''), ' ', COALESCE(prijmeni,'')))
                ELSE ftp_user
            END
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

get_active_event_id() {
    mysql --batch --skip-column-names -e "
        SELECT id
        FROM events
        WHERE status = 'active'
          AND is_temporary = 0
        ORDER BY id DESC
        LIMIT 1;
    " 2>/dev/null
}

get_temporary_event_id() {
    mysql --batch --skip-column-names -e "
        SELECT id
        FROM events
        WHERE status = 'active'
          AND is_temporary = 1
        ORDER BY id DESC
        LIMIT 1;
    " 2>/dev/null
}

get_current_event_id() {
    local id

    id="$(get_active_event_id)"

    if [ -n "$id" ]; then
        echo "$id"
        return
    fi

    id="$(get_temporary_event_id)"

    if [ -n "$id" ]; then
        echo "$id"
        return
    fi

    echo ""
}

is_runner_for_event() {
    local ftp_user="$1"
    local event_id="$2"
    local result

    if [ -z "$event_id" ] || [ "$event_id" = "NULL" ]; then
        return 1
    fi

    result="$(mysql --batch --skip-column-names -e "
        SELECT 1
        FROM event_users eu
        INNER JOIN users u ON u.id = eu.user_id
        WHERE eu.event_id = ${event_id}
          AND eu.role_in_event = 'photographer'
          AND eu.runner = 1
          AND u.ftp_user = '$(sql_escape "$ftp_user")'
        LIMIT 1;
    " 2>/dev/null)"

    [ "$result" = "1" ]
}

read_exif_author() {
    local file="$1"
    local value=""

    local tags=(
        "Artist"
        "Author"
        "Creator"
        "XPAuthor"
        "IPTC:By-line"
        "XMP-dc:Creator"
    )

    local tag
    for tag in "${tags[@]}"; do
        value="$(exiftool -s3 "-$tag" "$file" 2>> "$LOG_FILE" | head -n 1)"
        value="$(normalize_spaces "$value")"
        if [ -n "$value" ]; then
            echo "$value"
            return
        fi
    done

    echo ""
}

find_matching_photographer_for_event_by_exif_author() {
    local event_id="$1"
    local exif_author_raw="$2"
    local normalized_needle
    normalized_needle="$(normalize_author "$exif_author_raw")"

    if [ -z "$event_id" ] || [ "$event_id" = "NULL" ] || [ -z "$normalized_needle" ]; then
        return 1
    fi

    local rows
    rows="$(mysql --batch --raw --skip-column-names -e "
        SELECT
            u.id,
            u.ftp_user,
            TRIM(CONCAT(COALESCE(u.jmeno,''), ' ', COALESCE(u.prijmeni,''))) AS author_name,
            COALESCE(u.exif_author, '')
        FROM event_users eu
        INNER JOIN users u ON u.id = eu.user_id
        WHERE eu.event_id = ${event_id}
          AND eu.role_in_event = 'photographer'
        ORDER BY eu.runner ASC, u.id ASC;
    " 2>/dev/null)"

    [ -z "$rows" ] && return 1

    local line
    while IFS=$'\t' read -r user_id candidate_ftp_user author_name candidate_exif_author; do
        [ -z "$user_id" ] && continue

        candidate_exif_author="$(normalize_spaces "$candidate_exif_author")"
        [ -z "$candidate_exif_author" ] && continue

        local normalized_candidate
        normalized_candidate="$(normalize_author "$candidate_exif_author")"

        if [ -n "$normalized_candidate" ] && [ "$normalized_candidate" = "$normalized_needle" ]; then
            printf '%s\t%s\t%s\n' "$user_id" "$candidate_ftp_user" "$author_name"
            return 0
        fi
    done <<< "$rows"

    return 1
}

ensure_dir_exists() {
    local dir="$1"

    if [ ! -d "$dir" ]; then
        mkdir -p "$dir" || return 1
    fi

    return 0
}

get_unique_destination_path() {
    local target_dir="$1"
    local filename="$2"

    local base="${filename%.*}"
    local ext=""
    if [ "$base" != "$filename" ]; then
        ext=".${filename##*.}"
    else
        base="$filename"
    fi

    local candidate="$target_dir/$filename"
    if [ ! -e "$candidate" ]; then
        echo "$candidate"
        return
    fi

    local i=1
    while true; do
        candidate="$target_dir/${base}_$i${ext}"
        if [ ! -e "$candidate" ]; then
            echo "$candidate"
            return
        fi
        i=$((i + 1))
    done
}

move_file_to_ftp_user_dir() {
    local src="$1"
    local target_ftp_user="$2"

    local filename
    filename="$(basename "$src")"

    local target_dir="$FTP_ROOT/$target_ftp_user"
    ensure_dir_exists "$target_dir" || return 1

    local dst
    dst="$(get_unique_destination_path "$target_dir" "$filename")"

    if [ "$src" = "$dst" ]; then
        echo "$src"
        return 0
    fi

    if mv -f "$src" "$dst"; then
        log "Soubor přesunut: $src -> $dst"
        echo "$dst"
        return 0
    fi

    log "ERROR: Nepodařilo se přesunout soubor: $src -> $dst"
    return 1
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
    local event_id="$8"

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
            event_id,
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
            ${event_id:-NULL},
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

update_photo_exif_problem() {
    local photo_id="$1"
    local exif_problem="$2"
    local note="$3"

    mysql -e "
        UPDATE photos
        SET
            exif_problem = ${exif_problem},
            exif_problem_note = $( [ -n "$note" ] && printf "'%s'" "$(sql_escape "$note")" || printf "NULL" )
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

    local filesize
    filesize="$(stat -c%s "$file" 2>/dev/null || echo NULL)"

    local checksum
    checksum="$(sha256sum "$file" 2>/dev/null | awk '{print $1}')"

    local event_id
    event_id="$(get_current_event_id)"
    [ -z "$event_id" ] && event_id="NULL"

    local user_id
    user_id="$(get_user_id "$ftp_user")"
    [ -z "$user_id" ] && user_id="NULL"

    local author_name
    author_name="$(get_user_author_name "$ftp_user")"

    local skip_metadata_write=0
    local exif_problem=0
    local exif_problem_note=""

    if [ "$event_id" != "NULL" ] && is_runner_for_event "$ftp_user" "$event_id"; then
        local runner_exif_author_raw
        runner_exif_author_raw="$(read_exif_author "$file")"

        if [ -z "$runner_exif_author_raw" ]; then
            exif_problem=1
            exif_problem_note="Runner upload: chybí EXIF author"
            skip_metadata_write=1
            log "Runner bez EXIF author: $file"
        else
            local match_row
            match_row="$(find_matching_photographer_for_event_by_exif_author "$event_id" "$runner_exif_author_raw" || true)"

            if [ -n "$match_row" ]; then
                local matched_user_id
                local matched_ftp_user
                local matched_author_name

                IFS=$'\t' read -r matched_user_id matched_ftp_user matched_author_name <<< "$match_row"

                if [ -n "$matched_ftp_user" ]; then
                    local original_file="$file"
                    local original_ftp_user="$ftp_user"

                    local moved_file
                    moved_file="$(move_file_to_ftp_user_dir "$file" "$matched_ftp_user")"
                    if [ $? -ne 0 ] || [ -z "$moved_file" ] || [ ! -f "$moved_file" ]; then
                        log "ERROR: Runner match nalezen, ale přesun selhal: $file -> $matched_ftp_user"
                        return 1
                    fi

                    file="$moved_file"
                    filename="$(basename "$file")"
                    ftp_user="$matched_ftp_user"
                    user_id="$matched_user_id"
                    author_name="$matched_author_name"

                    rel_path="${file#$FTP_ROOT/}"

                    log "Runner match: EXIF author '$runner_exif_author_raw' přiřazen k ftp_user '$ftp_user' (původně '$original_ftp_user')"
                else
                    exif_problem=1
                    exif_problem_note="Runner upload: EXIF author '$runner_exif_author_raw' neodpovídá žádnému fotografovi eventu"
                    skip_metadata_write=1
                    log "Runner bez shody: $file | EXIF author='$runner_exif_author_raw'"
                fi
            else
                exif_problem=1
                exif_problem_note="Runner upload: EXIF author '$runner_exif_author_raw' neodpovídá žádnému fotografovi eventu"
                skip_metadata_write=1
                log "Runner bez shody: $file | EXIF author='$runner_exif_author_raw'"
            fi
        fi
    fi

    local existing_id
    existing_id="$(get_existing_photo_id "$file")"

    local photo_id
    local is_new_file=0

    if [ -n "$existing_id" ]; then
        photo_id="$existing_id"
    else
        insert_photo_row "$filename" "$file" "$ftp_user" "$user_id" "$filesize" "$filetype" "$checksum" "$event_id"
        photo_id="$(mysql --batch --skip-column-names -e "SELECT id FROM photos WHERE filepath = '$(sql_escape "$file")' ORDER BY id DESC LIMIT 1;" 2>/dev/null)"

        if [ -z "$photo_id" ]; then
            log "Nepodařilo se vložit DB záznam: $file"
            return 1
        fi

        insert_photo_log "$photo_id" "$user_id" "upload"
        is_new_file=1
    fi

    update_photo_exif_problem "$photo_id" "$exif_problem" "$exif_problem_note"
    update_photo_processing "$photo_id"

    # Zápis metadat jen při prvním zpracování nového souboru
    if [ "$is_new_file" -eq 1 ] && [ "$skip_metadata_write" -ne 1 ]; then
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