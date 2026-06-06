<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/events.php';

if (!defined('PUBLISHED_PHOTOS_ROOT')) {
    define('PUBLISHED_PHOTOS_ROOT', '/var/www/press/published');
}

const PUBLISHED_DETAIL_PREVIEW_SIZE = '2000x2000>';
const PUBLISHED_OVERVIEW_PREVIEW_SIZE = '420x420>';

function published_photos_event_storage_dir(array $event): string
{
    $slug = trim((string)($event['slug'] ?? ''));
    if ($slug === '') {
        $slug = 'event-' . (int)($event['id'] ?? 0);
    }

    return rtrim(PUBLISHED_PHOTOS_ROOT, '/') . '/' . $slug;
}

function published_photos_prepare_event_storage(array $event): string
{
    $dir = published_photos_event_storage_dir($event);

    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        throw new RuntimeException('Nepodařilo se vytvořit adresář pro hotové fotografie.');
    }

    if (!is_writable($dir)) {
        throw new RuntimeException('Adresář pro hotové fotografie není zapisovatelný.');
    }

    return $dir;
}

function published_photos_is_jpeg_upload(array $file): bool
{
    $tmpName = (string)($file['tmp_name'] ?? '');
    $name = (string)($file['name'] ?? '');

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return false;
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg'], true)) {
        return false;
    }

    $info = @getimagesize($tmpName);
    return is_array($info) && (int)($info[2] ?? 0) === IMAGETYPE_JPEG;
}

function published_photos_upload_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE => 'Soubor je větší než povolený limit uploadu.',
        UPLOAD_ERR_PARTIAL => 'Soubor byl nahrán jen částečně.',
        UPLOAD_ERR_NO_FILE => 'Nebyl vybrán žádný soubor.',
        UPLOAD_ERR_NO_TMP_DIR => 'Na serveru chybí dočasný adresář pro upload.',
        UPLOAD_ERR_CANT_WRITE => 'Server nedokázal soubor zapsat na disk.',
        UPLOAD_ERR_EXTENSION => 'Upload zastavilo PHP rozšíření.',
        default => 'Soubor se nepodařilo nahrát.',
    };
}

function published_photos_safe_filename(string $filename): string
{
    $filename = basename($filename);
    $filename = preg_replace('~[^\pL\pN._ -]+~u', '_', $filename) ?? $filename;
    $filename = preg_replace('~[[:space:]]+~', '_', $filename) ?? $filename;
    $filename = trim($filename, " ._\t\n\r\0\x0B");

    if ($filename === '') {
        $filename = 'photo.jpg';
    }

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $base = pathinfo($filename, PATHINFO_FILENAME);

    if (!in_array($ext, ['jpg', 'jpeg'], true)) {
        $ext = 'jpg';
    }

    if ($base === '') {
        $base = 'photo';
    }

    return $base . '.' . $ext;
}

function published_photos_unique_path(string $dir, string $filename): array
{
    $filename = published_photos_safe_filename($filename);
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $base = pathinfo($filename, PATHINFO_FILENAME);
    $candidate = $filename;
    $i = 2;

    while (is_file($dir . '/' . $candidate)) {
        $candidate = $base . '-' . $i . '.' . $ext;
        $i++;
    }

    return [$candidate, $dir . '/' . $candidate];
}

function published_photos_preview_path(string $filepath, string $size = 'detail'): string
{
    $dir = dirname($filepath);
    $filename = basename($filepath);
    $base = pathinfo($filename, PATHINFO_FILENAME);

    return $dir . '/' . $base . ($size === 'small' ? '-small' : '-preview') . '.jpg';
}

function published_photos_preview_filename(string $filename, string $size = 'detail'): string
{
    $base = pathinfo($filename, PATHINFO_FILENAME);
    return $base . ($size === 'small' ? '-small' : '-preview') . '.jpg';
}

function published_photos_generate_preview(string $sourcePath, string $targetPath, string $geometry, int $quality): bool
{
    if (!is_file($sourcePath)) {
        return false;
    }

    $dir = dirname($targetPath);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        return false;
    }

    $cmd = implode(' ', [
        'convert',
        escapeshellarg($sourcePath),
        '-auto-orient',
        '-resize',
        escapeshellarg($geometry),
        '-strip',
        '-interlace',
        'Plane',
        '-quality',
        (string)$quality,
        escapeshellarg($targetPath),
        '2>&1',
    ]);

    exec($cmd, $output, $code);
    if ($code !== 0 || !is_file($targetPath)) {
        return false;
    }

    @chmod($targetPath, 0664);
    return true;
}

function published_photos_generate_previews(string $sourcePath): array
{
    $detailPath = published_photos_preview_path($sourcePath, 'detail');
    $smallPath = published_photos_preview_path($sourcePath, 'small');

    if (!published_photos_generate_preview($sourcePath, $detailPath, PUBLISHED_DETAIL_PREVIEW_SIZE, 82)) {
        throw new RuntimeException('Nepodařilo se vytvořit náhled hotové fotografie.');
    }

    if (!published_photos_generate_preview($detailPath, $smallPath, PUBLISHED_OVERVIEW_PREVIEW_SIZE, 72)) {
        @unlink($detailPath);
        throw new RuntimeException('Nepodařilo se vytvořit malý náhled hotové fotografie.');
    }

    return [
        'detail' => $detailPath,
        'small' => $smallPath,
    ];
}

function published_photos_preview_for_photo(array $photo, string $size = 'detail'): string
{
    $filepath = (string)($photo['filepath'] ?? '');
    $previewPath = (string)($photo['preview_filepath'] ?? '');

    if ($size === 'small' && $previewPath !== '') {
        $smallPath = published_photos_preview_path($filepath, 'small');
        if (is_file($smallPath)) {
            return $smallPath;
        }
    }

    if ($previewPath !== '' && is_file($previewPath)) {
        return $previewPath;
    }

    return $filepath;
}

function published_photos_lower(string $value): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function published_photos_pairing_base(string $filename): string
{
    $base = published_photos_lower(pathinfo($filename, PATHINFO_FILENAME));
    $base = preg_replace('~\s+\(\d+\)$~u', '', $base) ?? $base;
    if (preg_match('~^(.+)[-_]\d+$~u', $base, $matches) && preg_match('~\d{3,}$~', $matches[1])) {
        $base = $matches[1];
    }

    return $base;
}

function published_photos_source_match_score(array $photo): array
{
    $status = (string)($photo['status'] ?? '');
    $statusScore = match ($status) {
        'downloaded' => 5,
        'locked' => 4,
        'ready' => 3,
        'selected' => 2,
        'uploaded', 'processing' => 1,
        default => 0,
    };

    return [
        $statusScore,
        (int)($photo['filesize'] ?? 0),
        strtotime((string)($photo['downloaded_at'] ?? '')) ?: 0,
        strtotime((string)($photo['uploaded_at'] ?? '')) ?: 0,
        (int)($photo['id'] ?? 0),
    ];
}

function published_photos_best_source_match(array $matches): ?array
{
    if (!$matches) {
        return null;
    }

    usort($matches, static function (array $a, array $b): int {
        return published_photos_source_match_score($b) <=> published_photos_source_match_score($a);
    });

    return $matches[0];
}

function published_photos_find_source_photo(int $eventId, string $publishedFilename): ?array
{
    $publishedRawBase = published_photos_lower(pathinfo($publishedFilename, PATHINFO_FILENAME));
    $publishedBase = published_photos_pairing_base($publishedFilename);

    $stmt = db()->prepare("
        SELECT
            id,
            filename,
            status,
            filesize,
            uploaded_at,
            downloaded_at,
            captured_at
        FROM photos
        WHERE event_id = ?
          AND event_photographer_allowed = 1
          AND status NOT IN ('deleted', 'error')
        ORDER BY LENGTH(filename) DESC, id DESC
    ");
    $stmt->execute([$eventId]);

    $exactMatchesByBase = [];
    $matchesByBase = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $photo) {
        $sourceRawBase = published_photos_lower(pathinfo((string)$photo['filename'], PATHINFO_FILENAME));
        if ($sourceRawBase !== '' && str_contains($publishedRawBase, $sourceRawBase)) {
            $exactMatchesByBase[$sourceRawBase][] = $photo;
            continue;
        }

        $sourceBase = published_photos_pairing_base((string)$photo['filename']);
        if ($sourceBase !== '' && str_contains($publishedBase, $sourceBase)) {
            $matchesByBase[$sourceBase][] = $photo;
        }
    }

    if ($exactMatchesByBase) {
        uksort($exactMatchesByBase, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        $bestExactBase = array_key_first($exactMatchesByBase);
        if ($bestExactBase !== null) {
            return published_photos_best_source_match($exactMatchesByBase[$bestExactBase]);
        }
    }

    if (!$matchesByBase) {
        return null;
    }

    uksort($matchesByBase, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

    $bestBase = array_key_first($matchesByBase);
    if ($bestBase === null) {
        return null;
    }

    return published_photos_best_source_match($matchesByBase[$bestBase]);
}

function published_photos_store_upload(array $event, array $user, array $file): array
{
    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        throw new RuntimeException(published_photos_upload_error_message($errorCode));
    }

    if (!published_photos_is_jpeg_upload($file)) {
        throw new RuntimeException('Nahrát lze pouze platný JPG soubor.');
    }

    $dir = published_photos_prepare_event_storage($event);
    [$filename, $filepath] = published_photos_unique_path($dir, (string)$file['name']);

    if (!move_uploaded_file((string)$file['tmp_name'], $filepath)) {
        throw new RuntimeException('Soubor se nepodařilo uložit.');
    }

    @chmod($filepath, 0664);

    try {
        $previewPaths = published_photos_generate_previews($filepath);
    } catch (Throwable $e) {
        @unlink($filepath);
        throw $e;
    }

    $imageInfo = @getimagesize($filepath) ?: [];
    $sourcePhoto = published_photos_find_source_photo((int)$event['id'], $filename);
    $checksum = hash_file('sha256', $filepath) ?: null;

    $stmt = db()->prepare("
        INSERT INTO published_photos (
            event_id,
            source_photo_id,
            uploaded_by_user_id,
            filename,
            filepath,
            preview_filename,
            preview_filepath,
            filesize,
            filetype,
            width,
            height,
            checksum,
            author_label,
            captured_at,
            source_uploaded_at,
            editor_downloaded_at,
            published_at,
            status
        ) VALUES (
            :event_id,
            :source_photo_id,
            :uploaded_by_user_id,
            :filename,
            :filepath,
            :preview_filename,
            :preview_filepath,
            :filesize,
            :filetype,
            :width,
            :height,
            :checksum,
            :author_label,
            :captured_at,
            :source_uploaded_at,
            :editor_downloaded_at,
            NOW(),
            'ready'
        )
    ");

    $stmt->execute([
        ':event_id' => (int)$event['id'],
        ':source_photo_id' => $sourcePhoto ? (int)$sourcePhoto['id'] : null,
        ':uploaded_by_user_id' => (int)$user['id'],
        ':filename' => $filename,
        ':filepath' => $filepath,
        ':preview_filename' => published_photos_preview_filename($filename, 'detail'),
        ':preview_filepath' => $previewPaths['detail'],
        ':filesize' => filesize($filepath) ?: null,
        ':filetype' => 'jpg',
        ':width' => isset($imageInfo[0]) ? (int)$imageInfo[0] : null,
        ':height' => isset($imageInfo[1]) ? (int)$imageInfo[1] : null,
        ':checksum' => $checksum,
        ':author_label' => published_photos_author_label($filepath),
        ':captured_at' => $sourcePhoto ? ($sourcePhoto['captured_at'] ?? null) : null,
        ':source_uploaded_at' => $sourcePhoto ? ($sourcePhoto['uploaded_at'] ?? null) : null,
        ':editor_downloaded_at' => $sourcePhoto ? ($sourcePhoto['downloaded_at'] ?? null) : null,
    ]);

    $publishedPhotoId = (int)db()->lastInsertId();

    $log = db()->prepare("
        INSERT INTO published_photo_log (published_photo_id, user_id, action, ip, created_at)
        VALUES (?, ?, 'uploaded', ?, NOW())
    ");
    $log->execute([
        $publishedPhotoId,
        (int)$user['id'],
        inet_pton($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
    ]);

    return [
        'id' => $publishedPhotoId,
        'filename' => $filename,
        'source_photo' => $sourcePhoto,
    ];
}

function published_photos_current_event(): ?array
{
    $sql = "
        SELECT *
        FROM events
        WHERE status = 'active'
        ORDER BY is_temporary ASC, id DESC
        LIMIT 1
    ";

    $row = db()->query($sql)->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function published_photos_list_ready(int $eventId): array
{
    $stmt = db()->prepare("
        SELECT
            pp.*,
            sp.filename AS source_filename,
            sp.ftp_user AS source_ftp_user
        FROM published_photos pp
        LEFT JOIN photos sp ON sp.id = pp.source_photo_id
        WHERE pp.event_id = ?
          AND pp.status = 'ready'
        ORDER BY
          pp.captured_at IS NULL,
          pp.captured_at DESC,
          pp.published_at DESC,
          pp.id DESC
    ");
    $stmt->execute([$eventId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function published_photos_in_editor_work_count(int $eventId): int
{
    $stmt = db()->prepare("
        SELECT COUNT(*)
        FROM photos p
        LEFT JOIN (
            SELECT source_photo_id, COUNT(*) AS published_count
            FROM published_photos
            WHERE source_photo_id IS NOT NULL
              AND status = 'ready'
            GROUP BY source_photo_id
        ) pp ON pp.source_photo_id = p.id
        WHERE p.event_id = ?
          AND p.downloaded_at IS NOT NULL
          AND p.downloaded_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
          AND p.status <> 'deleted'
          AND COALESCE(pp.published_count, 0) = 0
    ");
    $stmt->execute([$eventId]);

    return (int)$stmt->fetchColumn();
}

function published_photos_get_ready(int $id): ?array
{
    $stmt = db()->prepare("
        SELECT *
        FROM published_photos
        WHERE id = ?
          AND status = 'ready'
        LIMIT 1
    ");
    $stmt->execute([$id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function published_photos_neighbor_ids(array $photo): array
{
    $eventId = (int)($photo['event_id'] ?? 0);
    $photoId = (int)($photo['id'] ?? 0);

    if ($eventId <= 0 || $photoId <= 0) {
        return ['prev' => null, 'next' => null];
    }

    $photos = published_photos_list_ready($eventId);
    $prev = null;
    $next = null;

    foreach ($photos as $index => $item) {
        if ((int)$item['id'] !== $photoId) {
            continue;
        }

        if (isset($photos[$index - 1])) {
            $prev = (int)$photos[$index - 1]['id'];
        }
        if (isset($photos[$index + 1])) {
            $next = (int)$photos[$index + 1]['id'];
        }

        break;
    }

    return ['prev' => $prev, 'next' => $next];
}

function published_photos_author_label(string $filepath): string
{
    if (!is_file($filepath)) {
        return 'Člověk a Víra';
    }

    $escaped = escapeshellarg($filepath);
    $cmd = "exiftool -s3 -Artist -Author -Creator -XMP-dc:Creator -IPTC:By-line $escaped 2>/dev/null";
    $output = shell_exec($cmd);

    $author = '';
    if (is_string($output) && trim($output) !== '') {
        foreach (preg_split('~\R~u', trim($output)) ?: [] as $line) {
            $line = trim((string)$line);
            if ($line !== '') {
                $author = $line;
                break;
            }
        }
    }

    if ($author === '') {
        return 'Člověk a Víra';
    }

    return $author . ' / Člověk a Víra';
}

function published_photos_author_label_for_photo(array $photo): string
{
    $authorLabel = trim((string)($photo['author_label'] ?? ''));
    if ($authorLabel !== '') {
        return $authorLabel;
    }

    return published_photos_author_label((string)($photo['filepath'] ?? ''));
}

function published_photos_mark_downloaded(int $id): void
{
    db()->prepare("
        UPDATE published_photos
        SET download_count = download_count + 1
        WHERE id = ?
          AND status = 'ready'
    ")->execute([$id]);

    db()->prepare("
        INSERT INTO published_photo_log (published_photo_id, user_id, action, ip, created_at)
        VALUES (?, NULL, 'downloaded', ?, NOW())
    ")->execute([
        $id,
        inet_pton($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
    ]);

    if (!isset($_SESSION['published_downloaded']) || !is_array($_SESSION['published_downloaded'])) {
        $_SESSION['published_downloaded'] = [];
    }

    $_SESSION['published_downloaded'][(string)$id] = time();
}

function published_photos_was_downloaded_in_session(int $id): bool
{
    return !empty($_SESSION['published_downloaded'])
        && is_array($_SESSION['published_downloaded'])
        && isset($_SESSION['published_downloaded'][(string)$id]);
}
