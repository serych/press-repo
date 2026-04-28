<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/photos.php';
require_once __DIR__ . '/inc/published_photos.php';

require_login();

if (!has_permission('published_photos.upload')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$event = photos_get_current_event();
$user = current_user();
$errors = [];
$uploaded = [];

function upload_ini_bytes(string $value): int
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

function upload_format_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / 1024 / 1024, 0, ',', ' ') . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', ' ') . ' kB';
    }

    return $bytes . ' B';
}

$uploadMaxBytes = upload_ini_bytes((string)ini_get('upload_max_filesize'));
$postMaxBytes = upload_ini_bytes((string)ini_get('post_max_size'));
$effectiveMaxBytes = min(
    $uploadMaxBytes > 0 ? $uploadMaxBytes : PHP_INT_MAX,
    $postMaxBytes > 0 ? $postMaxBytes : PHP_INT_MAX
);

if (!$event) {
    $errors[] = 'Není vybraný aktivní event.';
}

if (is_post() && $event) {
    $files = $_FILES['photos'] ?? null;
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);

    if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
        $errors[] = 'Upload je větší než serverový limit post_max_size '
            . upload_format_bytes($postMaxBytes)
            . '. Aktuální požadavek má přibližně '
            . upload_format_bytes($contentLength)
            . '.';
    } elseif (!is_array($files) || empty($files['name'])) {
        $errors[] = 'Vyber alespoň jeden JPG soubor.';
    } else {
        $count = is_array($files['name']) ? count($files['name']) : 0;

        for ($i = 0; $i < $count; $i++) {
            $file = [
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];

            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
                && (string)($file['name'] ?? '') === ''
            ) {
                continue;
            }

            try {
                $uploaded[] = published_photos_store_upload($event, $user, $file);
            } catch (Throwable $e) {
                $errors[] = (string)$file['name'] . ': ' . $e->getMessage();
            }
        }

        if (!$uploaded && !$errors) {
            $errors[] = 'Nebyl nahrán žádný soubor.';
        }
    }
}

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <div class="page-head">
        <h1>Nahrát hotové fotografie</h1>
        <a href="/photos.php" class="button">Zpět na fotografie</a>
    </div>

    <?php if ($event): ?>
        <p class="table-subtext">
            Event:
            <strong><?= h((string)$event['title']) ?></strong>
            <span class="badge badge-info"><?= h((string)$event['slug']) ?></span>
            <?php if ($effectiveMaxBytes !== PHP_INT_MAX): ?>
                <span class="badge badge-info">limit <?= h(upload_format_bytes($effectiveMaxBytes)) ?> / soubor</span>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert-error">
            <?php foreach ($errors as $error): ?>
                <div><?= h($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($uploaded): ?>
        <div class="alert-success">
            <?php foreach ($uploaded as $item): ?>
                <div>
                    <?= h((string)$item['filename']) ?>
                    <?php if (!empty($item['source_photo'])): ?>
                        spárováno s <?= h((string)$item['source_photo']['filename']) ?>
                    <?php else: ?>
                        uloženo bez automatického spárování
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="post" enctype="multipart/form-data" class="form published-upload-form">
            <label for="photos">JPG soubory</label>
            <input type="file" name="photos[]" id="photos" accept=".jpg,.jpeg,image/jpeg" multiple required>
            <?php if ($effectiveMaxBytes !== PHP_INT_MAX): ?>
                <p class="table-subtext">Aktuální serverový limit je <?= h(upload_format_bytes($effectiveMaxBytes)) ?> na jeden soubor.</p>
            <?php endif; ?>

            <button type="submit">Nahrát hotové fotografie</button>
        </form>
    </div>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
