<?php
declare(strict_types=1);

require_once __DIR__ . '/published_photos.php';

function published_upload_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float)$value;

    return match ($unit) {
        'g' => (int)($number * 1024 * 1024 * 1024),
        'm' => (int)($number * 1024 * 1024),
        'k' => (int)($number * 1024),
        default => (int)$number,
    };
}

function published_upload_format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / 1024 / 1024, 0, ',', ' ') . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', ' ') . ' kB';
    }

    return $bytes . ' B';
}

function published_upload_effective_max_bytes(): int
{
    $uploadMaxBytes = published_upload_ini_bytes((string)ini_get('upload_max_filesize'));
    $postMaxBytes = published_upload_ini_bytes((string)ini_get('post_max_size'));

    return min(
        $uploadMaxBytes > 0 ? $uploadMaxBytes : PHP_INT_MAX,
        $postMaxBytes > 0 ? $postMaxBytes : PHP_INT_MAX
    );
}

function published_upload_post_max_bytes(): int
{
    return published_upload_ini_bytes((string)ini_get('post_max_size'));
}

function published_upload_normalize_files(?array $files): array
{
    if (!is_array($files) || empty($files['name'])) {
        return [];
    }

    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $items = [];

    foreach ($names as $i => $name) {
        $file = [
            'name' => $name ?? '',
            'type' => is_array($files['type'] ?? null) ? ($files['type'][$i] ?? '') : ($files['type'] ?? ''),
            'tmp_name' => is_array($files['tmp_name'] ?? null) ? ($files['tmp_name'][$i] ?? '') : ($files['tmp_name'] ?? ''),
            'error' => is_array($files['error'] ?? null) ? ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) : ($files['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => is_array($files['size'] ?? null) ? ($files['size'][$i] ?? 0) : ($files['size'] ?? 0),
        ];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
            && (string)($file['name'] ?? '') === ''
        ) {
            continue;
        }

        $items[] = $file;
    }

    return $items;
}

function published_upload_handle_request(array $event, array $user, ?array $files, int $contentLength): array
{
    $errors = [];
    $uploaded = [];
    $postMaxBytes = published_upload_post_max_bytes();

    if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
        $errors[] = 'Upload je větší než serverový limit post_max_size '
            . published_upload_format_bytes($postMaxBytes)
            . '. Aktuální požadavek má přibližně '
            . published_upload_format_bytes($contentLength)
            . '.';

        return [
            'ok' => false,
            'errors' => $errors,
            'uploaded' => [],
        ];
    }

    $normalizedFiles = published_upload_normalize_files($files);
    if (!$normalizedFiles) {
        $errors[] = 'Vyber alespoň jeden JPG soubor.';
    }

    foreach ($normalizedFiles as $file) {
        try {
            $uploaded[] = published_photos_store_upload($event, $user, $file);
        } catch (Throwable $e) {
            $errors[] = (string)$file['name'] . ': ' . $e->getMessage();
        }
    }

    if (!$uploaded && !$errors) {
        $errors[] = 'Nebyl nahrán žádný soubor.';
    }

    return [
        'ok' => $errors === [],
        'errors' => $errors,
        'uploaded' => $uploaded,
    ];
}

function published_upload_json_item(array $item): array
{
    return [
        'id' => (int)$item['id'],
        'filename' => (string)$item['filename'],
        'paired' => !empty($item['source_photo']),
        'source_photo_id' => !empty($item['source_photo'])
            ? (int)$item['source_photo']['id']
            : null,
        'source_filename' => !empty($item['source_photo'])
            ? (string)$item['source_photo']['filename']
            : null,
    ];
}
