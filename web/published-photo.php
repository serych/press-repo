<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/photos.php';
require_once __DIR__ . '/inc/gallery_access.php';
require_once __DIR__ . '/inc/published_photos.php';

$galleryAccess = gallery_access_require_login_or_public_access();

$id = (int)($_GET['id'] ?? 0);
$photo = $id > 0 ? published_photos_get_ready($id) : null;

if (
    !$photo
    || ($galleryAccess && !gallery_access_public_access_allows_photo($galleryAccess, $photo))
    || empty($photo['filepath'])
    || !is_file((string)$photo['filepath'])
) {
    http_response_code(404);
    exit('Fotografie nebyla nalezena.');
}

$neighbors = published_photos_neighbor_ids($photo);
$authorLabel = published_photos_author_label_for_photo($photo);
$downloadedInSession = published_photos_was_downloaded_in_session((int)$photo['id']);
$workflowTime = (!empty($photo['captured_at']) && !empty($photo['published_at']))
    ? photos_format_duration_between((string)$photo['captured_at'], (string)$photo['published_at'])
    : '—';

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <div class="published-detail-head">
        <a href="/galerie.php" class="back-link">← Zpět do galerie</a>
        <a href="/published-download.php?id=<?= (int)$photo['id'] ?>" class="button">Stáhnout</a>
    </div>

    <div class="published-detail-layout">
        <div class="published-detail-preview">
            <?php if (!empty($neighbors['prev'])): ?>
                <a class="published-detail-arrow published-detail-arrow-prev" href="/published-photo.php?id=<?= (int)$neighbors['prev'] ?>" aria-label="Předchozí fotografie">‹</a>
            <?php endif; ?>

            <img src="/published-preview.php?id=<?= (int)$photo['id'] ?>" alt="<?= h((string)$photo['filename']) ?>">

            <?php if (!empty($neighbors['next'])): ?>
                <a class="published-detail-arrow published-detail-arrow-next" href="/published-photo.php?id=<?= (int)$neighbors['next'] ?>" aria-label="Následující fotografie">›</a>
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
                    <td><?= !empty($photo['captured_at']) ? h((string)$photo['captured_at']) : '—' ?></td>
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

<?php require_once __DIR__ . '/inc/footer.php'; ?>
