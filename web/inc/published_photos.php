<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/events.php';

if (!defined('PUBLISHED_PHOTOS_ROOT')) {
    define('PUBLISHED_PHOTOS_ROOT', '/var/www/press/published');
}

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

function published_photos_lower(string $value): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

function published_photos_find_source_photo(int $eventId, string $publishedFilename): ?array
{
    $publishedBase = published_photos_lower(pathinfo($publishedFilename, PATHINFO_FILENAME));

    $stmt = db()->prepare("
        SELECT
            id,
            filename,
            uploaded_at,
            downloaded_at,
            captured_at
        FROM photos
        WHERE event_id = ?
          AND event_photographer_allowed = 1
          AND status <> 'deleted'
        ORDER BY LENGTH(filename) DESC, id DESC
    ");
    $stmt->execute([$eventId]);

    $matches = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $photo) {
        $sourceBase = published_photos_lower(pathinfo((string)$photo['filename'], PATHINFO_FILENAME));
        if ($sourceBase !== '' && str_contains($publishedBase, $sourceBase)) {
            $matches[] = $photo;
        }
    }

    if (count($matches) !== 1) {
        return null;
    }

    return $matches[0];
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
            filesize,
            filetype,
            width,
            height,
            checksum,
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
            :filesize,
            :filetype,
            :width,
            :height,
            :checksum,
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
        ':filesize' => filesize($filepath) ?: null,
        ':filetype' => 'jpg',
        ':width' => isset($imageInfo[0]) ? (int)$imageInfo[0] : null,
        ':height' => isset($imageInfo[1]) ? (int)$imageInfo[1] : null,
        ':checksum' => $checksum,
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
