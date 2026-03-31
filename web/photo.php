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

require_once __DIR__ . '/inc/header.php';
?>

<section class="photo-detail">

<div class="photo-detail-top">
<h1>Detail fotografie</h1>
<p><a href="/photos.php" class="back-link">← zpět</a></p>
</div>

<div class="photo-detail-grid">

<div class="photo-preview-card">

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

<?php if ($photo['status'] === 'downloaded'): ?>
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
<th>Originál</th>
<td class="path-cell"><?= h((string)$photo['filepath']) ?></td>
</tr>

</table>

<?php if (has_permission('photos.download')): ?>

<div class="photo-actions">

<?php if ((int)($photo['locked_by_user_id'] ?? 0) === $currentUserId): ?>

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

<?php if (!empty($photo['locked_by_user_id'])): ?>

<?php if ((int)$photo['locked_by_user_id'] === $currentUserId): ?>

<a href="/select.php?id=<?= (int)$photo['id'] ?>&action=unlock"
class="btn btn-secondary">
Odemknout
</a>

<?php else: ?>

<div class="btn btn-disabled">
Zamčeno jiným redaktorem
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