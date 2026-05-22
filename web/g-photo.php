<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/photos.php';
require_once __DIR__ . '/inc/gallery_access.php';
require_once __DIR__ . '/inc/published_photos.php';

$token = gallery_access_public_url_token();
$access = gallery_access_find_by_token($token);
if (!$access || !gallery_access_is_public_session_allowed($access)) {
    header('Location: /g/' . rawurlencode($token));
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$photo = $id > 0 ? published_photos_get_ready($id) : null;

if (!$photo || (int)$photo['event_id'] !== (int)$access['event_id'] || empty($photo['filepath']) || !is_file((string)$photo['filepath'])) {
    http_response_code(404);
    exit('Fotografie nebyla nalezena.');
}

$neighbors = published_photos_neighbor_ids($photo);
$authorLabel = published_photos_author_label_for_photo($photo);
$downloadedInSession = published_photos_was_downloaded_in_session((int)$photo['id']);
$workflowTime = (!empty($photo['captured_at']) && !empty($photo['published_at']))
    ? photos_format_duration_between((string)$photo['captured_at'], (string)$photo['published_at'])
    : '-';
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h((string)$photo['filename']) ?> | Press centrum</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="public-gallery-page">
<header class="site-header">
    <div class="wrap">
        <div class="brand">Press centrum</div>
    </div>
</header>

<main class="wrap public-gallery-wrap">
    <section class="panel">
        <div class="published-detail-head">
            <a href="/g/<?= h($token) ?>" class="back-link">Zpět do galerie</a>
            <a href="/g-download.php?token=<?= h($token) ?>&amp;id=<?= (int)$photo['id'] ?>" class="button">Stáhnout</a>
        </div>

        <div class="published-detail-layout">
            <div class="published-detail-preview">
                <?php if (!empty($neighbors['prev'])): ?>
                    <a class="published-detail-arrow published-detail-arrow-prev" href="/g-photo.php?token=<?= h($token) ?>&amp;id=<?= (int)$neighbors['prev'] ?>" aria-label="Předchozí fotografie">&lsaquo;</a>
                <?php endif; ?>

                <img src="/g-preview.php?token=<?= h($token) ?>&amp;id=<?= (int)$photo['id'] ?>" alt="<?= h((string)$photo['filename']) ?>">

                <?php if (!empty($neighbors['next'])): ?>
                    <a class="published-detail-arrow published-detail-arrow-next" href="/g-photo.php?token=<?= h($token) ?>&amp;id=<?= (int)$neighbors['next'] ?>" aria-label="Následující fotografie">&rsaquo;</a>
                <?php endif; ?>
            </div>

            <aside class="card published-detail-info">
                <h1><?= h((string)$photo['filename']) ?></h1>

                <table class="detail-table published-detail-table">
                    <tr>
                        <th>Autor</th>
                        <td><?= h($authorLabel) ?></td>
                    </tr>
                    <tr>
                        <th>Vyfoceno</th>
                        <td><?= !empty($photo['captured_at']) ? h((string)$photo['captured_at']) : '-' ?></td>
                    </tr>
                    <tr>
                        <th>Doba úprav</th>
                        <td><?= h($workflowTime) ?></td>
                    </tr>
                    <tr>
                        <th>Stažení</th>
                        <td>
                            <?= (int)($photo['download_count'] ?? 0) ?>
                            <?php if ($downloadedInSession): ?>
                                <span class="status status-downloaded">staženo v této relaci</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </aside>
        </div>
    </section>
</main>
</body>
</html>
