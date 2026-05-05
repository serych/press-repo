<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/photos.php';

require_login();

if (!has_permission('photos.view')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$id = (int)($_GET['id'] ?? 0);
$ftpUser = isset($_GET['ftp_user']) ? trim((string)$_GET['ftp_user']) : '';
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$sort = (string)($_GET['sort'] ?? 'uploaded');
if (!in_array($sort, ['uploaded', 'captured'], true)) {
    $sort = 'uploaded';
}

if ($id <= 0) {
    http_response_code(404);
    exit;
}

$photo = photos_get_by_id($id);

if (!$photo) {
    http_response_code(404);
    exit;
}

$currentUser = current_user();
$currentUserId = (int)$currentUser['id'];
$isEventPhotographerAllowed = photos_is_event_photographer_allowed($photo);
$isBlocked = photos_is_blocked($photo);
$publishedPhotos = photos_get_published_for_source((int)$photo['id']);
$firstPublishedPhoto = $publishedPhotos[0] ?? null;
$currentEvent = photos_get_current_event();
$currentEventId = !empty($currentEvent['id']) ? (int)$currentEvent['id'] : (int)($photo['event_id'] ?? 0);

$contextFilters = [
    'event_id' => $currentEventId,
    'ftp_user' => $ftpUser,
    'status' => $status,
];
$contextPhotos = photos_list($contextFilters, null, 0, $sort);
$prevPhotoId = null;
$nextPhotoId = null;

foreach ($contextPhotos as $index => $contextPhoto) {
    if ((int)$contextPhoto['id'] !== (int)$photo['id']) {
        continue;
    }

    if (isset($contextPhotos[$index - 1])) {
        $prevPhotoId = (int)$contextPhotos[$index - 1]['id'];
    }
    if (isset($contextPhotos[$index + 1])) {
        $nextPhotoId = (int)$contextPhotos[$index + 1]['id'];
    }

    break;
}

$contextQuery = array_filter([
    'ftp_user' => $ftpUser,
    'status' => $status,
    'sort' => $sort !== 'uploaded' ? $sort : '',
], static fn(string $value): bool => $value !== '');

$photosBackUrl = '/photos.php' . ($contextQuery ? '?' . http_build_query($contextQuery) : '');
$photoDetailUrl = static function (int $photoId) use ($contextQuery): string {
    return '/photo.php?' . http_build_query(['id' => $photoId] + $contextQuery);
};

$downloadedByName = trim(
    ((string)($photo['downloaded_jmeno'] ?? '')) . ' ' .
    ((string)($photo['downloaded_prijmeni'] ?? ''))
);

if ($downloadedByName === '') {
    $downloadedByName = (string)($photo['downloaded_by_user'] ?? '');
}

$blockedByName = trim(
    ((string)($photo['blocked_jmeno'] ?? '')) . ' ' .
    ((string)($photo['blocked_prijmeni'] ?? ''))
);

if ($blockedByName === '') {
    $blockedByName = (string)($photo['blocked_by_user'] ?? '');
}

require_once __DIR__ . '/inc/header.php';
?>

<section class="photo-detail">

<div class="photo-detail-top">
<h1>Detail fotografie</h1>
<div class="photo-detail-nav">
    <a href="<?= h($photosBackUrl) ?>" class="back-link">← zpět na přehled</a>
</div>
</div>

<?php if (!empty($photo['exif_problem'])): ?>
<div class="alert-error">
    Tato fotografie má problém s EXIFem.
    <?php if (!empty($photo['exif_problem_note'])): ?>
        <?= h((string)$photo['exif_problem_note']) ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!$isEventPhotographerAllowed): ?>
<div class="alert-error">
    Tato fotografie je od uživatele, který není přiřazený jako fotograf aktuálního eventu.
</div>
<?php endif; ?>

<div class="photo-detail-grid">

<div class="photo-preview-card<?= !empty($photo['exif_problem']) ? ' detail-exif-problem' : '' ?><?= !$isEventPhotographerAllowed ? ' detail-unassigned-event-photo' : '' ?>">

<?php if (!empty($photo['preview_filepath'])): ?>
<div class="photo-detail-preview-nav">
    <?php if ($prevPhotoId !== null): ?>
        <a href="<?= h($photoDetailUrl($prevPhotoId)) ?>" class="photo-detail-side-arrow" aria-label="Předchozí fotografie">‹</a>
    <?php else: ?>
        <span class="photo-detail-side-arrow is-disabled" aria-hidden="true">‹</span>
    <?php endif; ?>

    <a href="/preview.php?id=<?= (int)$photo['id'] ?>" target="_blank" rel="noopener noreferrer" class="photo-detail-image-link">
        <img src="/preview.php?id=<?= (int)$photo['id'] ?>" class="photo-detail-image">
    </a>

    <?php if ($nextPhotoId !== null): ?>
        <a href="<?= h($photoDetailUrl($nextPhotoId)) ?>" class="photo-detail-side-arrow" aria-label="Následující fotografie">›</a>
    <?php else: ?>
        <span class="photo-detail-side-arrow is-disabled" aria-hidden="true">›</span>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="no-preview large">bez náhledu</div>
<?php endif; ?>

</div>

<div class="photo-info-card">

<h2>Metadata</h2>

<table class="detail-table">

<tr>
<th>Soubor</th>
<td><?= h((string)$photo['filename']) ?></td>
</tr>

<tr>
<th>Fotograf</th>
<td><?= h((string)$photo['ftp_user']) ?></td>
</tr>

<tr>
<th>Stav</th>
<td>

<?php if ($isBlocked): ?>
<span class="status-line">
    <span class="status status-blocked">zablokováno</span>
    <?php if ($blockedByName !== ''): ?>
        <span class="lock-owner">(<?= h($blockedByName) ?>)</span>
    <?php endif; ?>
</span>

<?php elseif (!$isEventPhotographerAllowed): ?>
<span class="status status-unassigned">mimo event</span>

<?php elseif ($photo['status'] === 'downloaded'): ?>
<span class="status status-downloaded">downloaded</span>

<?php elseif (!empty($photo['locked_by_user_id'])): ?>

<?php if ((int)$photo['locked_by_user_id'] === $currentUserId): ?>
<span class="status status-selected">ke stažení</span>
<?php else: ?>

<?php
$lockedByName = trim(
    ((string)($photo['locked_jmeno'] ?? '')) . ' ' .
    ((string)($photo['locked_prijmeni'] ?? ''))
);
?>

<span class="status-line">
    <span class="status status-locked">zamknuto</span>

    <?php if ($lockedByName !== ''): ?>
        <span class="lock-owner">(<?= h($lockedByName) ?>)</span>
    <?php elseif (!empty($photo['locked_by_user'])): ?>
        <span class="lock-owner">(<?= h((string)$photo['locked_by_user']) ?>)</span>
    <?php endif; ?>
</span>

<?php endif; ?>

<?php else: ?>

<span class="status status-ready">ready</span>

<?php endif; ?>

</td>
</tr>

<tr>
<th>Vyfoceno</th>
<td><?= !empty($photo['captured_at']) ? h((string)$photo['captured_at']) : '—' ?></td>
</tr>

<tr>
<th>Nahráno</th>
<td><?= h((string)$photo['uploaded_at']) ?></td>
</tr>

<tr>
<th>Staženo</th>
<td>
    <?= !empty($photo['downloaded_at']) ? h((string)$photo['downloaded_at']) : '—' ?>
    <?php if ($downloadedByName !== ''): ?>
        <span class="detail-muted">(<?= h($downloadedByName) ?>)</span>
    <?php endif; ?>
</td>
</tr>

<tr>
<th>Publikováno</th>
<td><?= !empty($firstPublishedPhoto['published_at']) ? h((string)$firstPublishedPhoto['published_at']) : '—' ?></td>
</tr>

<tr>
<th>Velikost</th>
<td><?= number_format((int)$photo['filesize'], 0, ' ', ' ') ?> B</td>
</tr>

<tr>
<th>EXIF author</th>
<td class="<?= !empty($photo['exif_problem']) ? 'detail-problem-value' : '' ?>">
    <?= h((string)($photo['exif_author'] ?? '—')) ?>
</td>
</tr>

<tr>
<th>EXIF copyright</th>
<td class="<?= !empty($photo['exif_problem']) ? 'detail-problem-value' : '' ?>">
    <?= h((string)($photo['exif_copyright'] ?? '—')) ?>
</td>
</tr>

<?php if (!empty($photo['exif_problem'])): ?>
<tr>
<th>EXIF problém</th>
<td class="detail-problem-value">
    <?= h((string)($photo['exif_problem_note'] ?? 'Ano')) ?>
</td>
</tr>
<?php endif; ?>

<tr>
<th>Originál</th>
<td class="path-cell"><?= h((string)$photo['filepath']) ?></td>
</tr>

</table>

<h2>Časy workflow</h2>

<table class="detail-table timing-table">
<tr>
<th>Vyfocení -> Nahrátí</th>
<td><?= h(photos_format_duration_between($photo['captured_at'] ?? null, $photo['uploaded_at'] ?? null)) ?></td>
</tr>

<tr>
<th>Nahrátí -> Stažení</th>
<td><?= h(photos_format_duration_between($photo['uploaded_at'] ?? null, $photo['downloaded_at'] ?? null)) ?></td>
</tr>

<tr>
<th>Stažení -> Publikace</th>
<td><?= h(photos_format_duration_between($photo['downloaded_at'] ?? null, $firstPublishedPhoto['published_at'] ?? null)) ?></td>
</tr>

<tr>
<th>Workflow celkem</th>
<td><?= h(photos_format_duration_between($photo['captured_at'] ?? null, $firstPublishedPhoto['published_at'] ?? null)) ?></td>
</tr>
</table>

<?php if ($publishedPhotos): ?>
<h2>Publikované fotky</h2>

<table class="detail-table published-detail-table">
<?php foreach ($publishedPhotos as $publishedPhoto): ?>
<?php
$uploadedByName = trim(
    ((string)($publishedPhoto['uploaded_jmeno'] ?? '')) . ' ' .
    ((string)($publishedPhoto['uploaded_prijmeni'] ?? ''))
);

if ($uploadedByName === '') {
    $uploadedByName = (string)($publishedPhoto['uploaded_by_user'] ?? '');
}
?>
<tr>
<th><?= h((string)$publishedPhoto['filename']) ?></th>
<td>
    <div>Publikováno: <?= h((string)$publishedPhoto['published_at']) ?></div>
    <div>Workflow celkem: <?= h(photos_format_duration_between($photo['captured_at'] ?? null, $publishedPhoto['published_at'] ?? null)) ?></div>
    <?php if ($uploadedByName !== ''): ?>
        <div>Publikoval: <?= h($uploadedByName) ?></div>
    <?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<?php if (has_permission('photos.download')): ?>

<div class="photo-actions">

<?php if ($isBlocked): ?>

<div class="btn btn-disabled">
Fotografie je zablokovaná
</div>

<?php elseif (!$isEventPhotographerAllowed): ?>

<div class="btn btn-disabled">
Fotografie není aktivní pro tento event
</div>

<?php elseif ((int)($photo['locked_by_user_id'] ?? 0) === $currentUserId): ?>

<a href="/download.php?id=<?= (int)$photo['id'] ?>"
class="btn btn-download">
Stáhnout originál
</a>

<?php else: ?>

<div class="btn btn-disabled">
Nejprve zamkněte fotografii
</div>

<?php endif; ?>

</div>

<?php endif; ?>

<?php if (has_permission('photos.select')): ?>

<div class="photo-actions">

<?php if ($isBlocked): ?>

<a href="/select.php?id=<?= (int)$photo['id'] ?>&action=unblock"
class="btn btn-primary">
Odblokovat
</a>

<?php elseif (!$isEventPhotographerAllowed): ?>

<div class="btn btn-disabled">
Fotografa je potřeba nejdřív přiřadit k eventu
</div>

<?php elseif (!empty($photo['locked_by_user_id'])): ?>

<?php if ((int)$photo['locked_by_user_id'] === $currentUserId): ?>

<a href="/select.php?id=<?= (int)$photo['id'] ?>&action=unlock"
class="btn btn-secondary">
Odemknout
</a>

<?php else: ?>

<div class="btn btn-disabled">
Zamčeno jiným fotoeditorem
</div>

<?php endif; ?>

<?php else: ?>

<a href="/select.php?id=<?= (int)$photo['id'] ?>&action=lock"
class="btn btn-primary">
Vybrat / zamknout
</a>

<?php endif; ?>

<?php if (!$isBlocked && $isEventPhotographerAllowed): ?>
<a href="/select.php?id=<?= (int)$photo['id'] ?>&action=block"
class="btn btn-secondary">
Zablokovat
</a>
<?php endif; ?>

</div>

<?php endif; ?>

</div>

</div>

</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
