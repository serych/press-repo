<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/photos.php';

require_login();

$ftpUser = $_GET['ftp_user'] ?? '';
$status  = $_GET['status'] ?? '';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;
$offset = ($page - 1) * $perPage;

$filters = [
    'ftp_user' => $ftpUser,
    'status' => $status
];

$total = photos_count($filters);
$photos = photos_list($filters, $perPage, $offset);
$photographers = photos_get_photographers();

$totalPages = max(1, (int)ceil($total / $perPage));

require_once __DIR__ . '/inc/header.php';
?>

<h1>Fotografie</h1>

<form method="get" class="filters">

<select name="ftp_user">
<option value="">-- fotograf --</option>
<?php foreach ($photographers as $p): ?>
<option value="<?= h($p['ftp_user']) ?>"
<?= $ftpUser === $p['ftp_user'] ? 'selected' : '' ?>>
<?= h($p['ftp_user']) ?>
</option>
<?php endforeach; ?>
</select>

<select name="status">
<option value="">-- stav --</option>
<?php foreach (['uploaded','processing','ready','error','selected'] as $s): ?>
<option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>>
<?= $s ?>
</option>
<?php endforeach; ?>
</select>

<button type="submit">Filtrovat</button>

</form>

<div class="photo-grid">

<?php foreach ($photos as $p): ?>

<div class="photo-card">

<div class="thumb">
<?php if ($p['preview_filepath']): ?>
<img src="/preview.php?id=<?= (int)$p['id'] ?>">
<?php else: ?>
<div class="no-preview">bez náhledu</div>
<?php endif; ?>
</div>

<div class="meta">

<div class="file"><?= h($p['filename']) ?></div>

<div class="author">
<?= h($p['ftp_user']) ?>
</div>

<div class="status status-<?= h($p['status']) ?>">
<?= h($p['status']) ?>
</div>

<div class="time">
<?= h($p['uploaded_at']) ?>
</div>

</div>

</div>

<?php endforeach; ?>

</div>

<?php if ($totalPages > 1): ?>

<div class="pagination">

<?php for ($i=1; $i<=$totalPages; $i++): ?>

<a class="<?= $i==$page?'active':'' ?>"
href="?page=<?= $i ?>&ftp_user=<?= h($ftpUser) ?>&status=<?= h($status) ?>">
<?= $i ?>
</a>

<?php endfor; ?>

</div>

<?php endif; ?>

<?php require_once __DIR__ . '/inc/footer.php'; ?>