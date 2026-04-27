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

require_once __DIR__ . '/inc/header.php';
?>

<section class="photo-detail">

<div class="photo-detail-top">
<h1>Detail fotografie</h1>
<p><a href="/photos.php" class="back-link">← zpět</a></p>
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
<img src="/preview.php?id=<?= (int)$photo['id'] ?>" class="photo-detail-image">
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

<?php if (!$isEventPhotographerAllowed): ?>
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
<th>Nahráno</th>
<td><?= h((string)$photo['uploaded_at']) ?></td>
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

<?php if (has_permission('photos.download')): ?>

<div class="photo-actions">

<?php if (!$isEventPhotographerAllowed): ?>

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

<?php if (!$isEventPhotographerAllowed): ?>

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

</div>

<?php endif; ?>

</div>

</div>

</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
