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

$downloadJobId = max(0, (int)($_GET['download_job'] ?? 0));
$downloadTotal = max(0, (int)($_GET['download_total'] ?? 0));

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

$user = current_user();
$currentUserId = (int)$user['id'];

$lockedMineCount = 0;
foreach ($photos as $p) {
    if (
        ($p['status'] ?? '') === 'locked'
        && (int)($p['locked_by_user_id'] ?? 0) === $currentUserId
    ) {
        $lockedMineCount++;
    }
}

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

<?php if (!empty($photos) && has_permission('photos.download')): ?>

<div class="bulk-toolbar">
    <form id="bulk-download-form" method="POST" action="/bulk-download-create.php" style="display:inline;">
        <button type="submit">Hromadné stažení zamčených</button>
    </form>

    <span id="bulk-status-text">
        <?php if ($downloadJobId > 0 && $downloadTotal > 0): ?>
            Připraven download <?= $downloadTotal ?> fotografií.
        <?php else: ?>
            Moje zamčené na této stránce: <?= $lockedMineCount ?>
        <?php endif; ?>
    </span>
</div>

<?php endif; ?>

<?php if (empty($photos)): ?>
<p>Žádné fotografie.</p>
<?php else: ?>

<div class="photo-grid">

<?php foreach ($photos as $p): ?>

<?php
$cardClass = 'photo-card';

if (($p['status'] ?? '') === 'locked' && !empty($p['locked_by_user_id'])) {
    if ((int)$p['locked_by_user_id'] === $currentUserId) {
        $cardClass .= ' selected';
    } else {
        $cardClass .= ' locked';
    }
}
?>

<div class="<?= h($cardClass) ?>">

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

<?php if ($p['status'] === 'downloaded'): ?>

<div class="status status-downloaded">
downloaded
</div>

<?php elseif ($p['locked_by_user_id']): ?>

<?php if ($p['locked_by_user_id'] == $currentUserId): ?>

<a href="/select.php?id=<?= (int)$p['id'] ?>&action=unlock"
class="status status-selected status-clickable"
onclick="event.stopPropagation();">
ke stažení
</a>

<?php else: ?>

<div class="status-line">
    <div class="status status-locked">
        zamknuto
    </div>

    <?php
    $lockedByName = trim(
        ((string)($p['locked_jmeno'] ?? '')) . ' ' .
        ((string)($p['locked_prijmeni'] ?? ''))
    );
    ?>

    <?php if ($lockedByName !== ''): ?>
        <div class="lock-owner">
            (<?= h($lockedByName) ?>)
        </div>
    <?php elseif (!empty($p['locked_by_user'])): ?>
        <div class="lock-owner">
            (<?= h((string)$p['locked_by_user']) ?>)
        </div>
    <?php endif; ?>
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

<script>
async function runBulkDownload(jobId, total) {
    const statusBox = document.getElementById('bulk-status-text');

    async function fetchStatus() {
        const response = await fetch('/api/download-job-status.php?job=' + jobId, {
            cache: 'no-store'
        });
        return await response.json();
    }

    async function step() {
        const status = await fetchStatus();
        const downloaded = Number(status.downloaded || 0);
        const current = status.next_item ? downloaded + 1 : downloaded;

        if (statusBox) {
            statusBox.textContent = 'Probíhá download ' + current + ' / ' + total;
        }

        if (!status.next_item) {
            if (statusBox) {
                statusBox.textContent = 'Download dokončen. Obnovuji galerii…';
            }

            const url = new URL(window.location.href);
            url.searchParams.delete('download_job');
            url.searchParams.delete('download_total');

            setTimeout(() => {
                window.location.href = url.toString();
            }, 800);

            return;
        }

        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = '/download-item.php?job=' + jobId + '&item=' + status.next_item;
        document.body.appendChild(iframe);

        setTimeout(step, 1500);
    }

    step();
}

document.addEventListener('DOMContentLoaded', function () {
    const jobId = <?= $downloadJobId ?>;
    const total = <?= $downloadTotal ?>;

    if (jobId > 0 && total > 0) {
        const ok = window.confirm('Bude se stahovat ' + total + ' fotografií. Pokračovat?');

        if (ok) {
            runBulkDownload(jobId, total);
        } else {
            const url = new URL(window.location.href);
            url.searchParams.delete('download_job');
            url.searchParams.delete('download_total');
            window.location.href = url.toString();
        }
    }
});
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>