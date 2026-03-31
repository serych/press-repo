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

$ftpUser = isset($_GET['ftp_user']) ? trim((string)$_GET['ftp_user']) : '';
$status  = isset($_GET['status']) ? trim((string)$_GET['status']) : '';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;
$offset = ($page - 1) * $perPage;

$filters = [
    'ftp_user' => $ftpUser,
    'status'   => $status,
];

$total = photos_count($filters);
$photos = photos_list($filters, $perPage, $offset);
$photographers = photos_get_photographers();

$totalPages = max(1, (int)ceil($total / $perPage));

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
<h1>Fotografie</h1>

<form method="get" class="filters">

<select name="ftp_user">
<option value="">-- fotograf --</option>
<?php foreach ($photographers as $p): ?>
<option value="<?= h((string)$p['ftp_user']) ?>"
<?= $ftpUser === (string)$p['ftp_user'] ? 'selected' : '' ?>>
<?= h((string)$p['ftp_user']) ?>
</option>
<?php endforeach; ?>
</select>

<select name="status">
<option value="">-- stav --</option>
<?php foreach (['ready','locked','downloaded','error'] as $s): ?>
<option value="<?= h($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
<?= h($s) ?>
</option>
<?php endforeach; ?>
</select>

<button type="submit">Filtrovat</button>

</form>

<?php if (empty($photos)): ?>
<p>Žádné fotografie.</p>
<?php else: ?>

<div class="photo-grid">

<?php foreach ($photos as $p): ?>

<div class="photo-card <?= $p['locked_by_user_id'] ? 'selected' : '' ?>">

<a href="/photo.php?id=<?= (int)$p['id'] ?>" class="photo-card-link">

<div class="thumb">
<?php if (!empty($p['preview_filepath'])): ?>
<img src="/preview.php?id=<?= (int)$p['id'] ?>">
<?php else: ?>
<div class="no-preview">bez náhledu</div>
<?php endif; ?>
</div>

<div class="meta">

<div class="file">
<?= h((string)$p['filename']) ?>
</div>

<div class="author">
<?= h((string)$p['ftp_user']) ?>
</div>

<div class="status-wrapper">

<?php if ($p['locked_by_user_id']): ?>

<?php if ($p['locked_by_user_id'] == current_user()['id']): ?>

<a href="/select.php?id=<?= (int)$p['id'] ?>&action=unlock"
class="status status-selected status-clickable"
onclick="event.stopPropagation();">
locked
</a>

<?php else: ?>

<div class="status status-locked">
locked
</div>

<?php endif; ?>

<?php else: ?>

<?php if (has_permission('photos.select')): ?>

<a href="/select.php?id=<?= (int)$p['id'] ?>&action=lock"
class="status status-ready status-clickable"
onclick="event.stopPropagation();">
ready
</a>

<?php else: ?>

<div class="status status-ready">
ready
</div>

<?php endif; ?>

<?php endif; ?>

</div>

<div class="time">
<?= h((string)$p['uploaded_at']) ?>
</div>

</div>
</a>

</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

<?php if ($totalPages > 1): ?>

<div class="pagination">

<?php for ($i = 1; $i <= $totalPages; $i++): ?>

<a class="<?= $i === $page ? 'active' : '' ?>"
href="?page=<?= $i ?>&ftp_user=<?= urlencode($ftpUser) ?>&status=<?= urlencode($status) ?>">
<?= $i ?>
</a>

<?php endfor; ?>

</div>

<?php endif; ?>

</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>