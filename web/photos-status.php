<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/db.php';

require_login();

if (!has_permission('photos.view')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$user = current_user();
$userId = (int)($user['id'] ?? 0);

if ($userId <= 0) {
    redirect('/login.php');
}

$pdo = db();

/*
 * roli bereme z DB, ne ze session/current_user(),
 * aby to fungovalo spolehlivě i když current_user neobsahuje role_code
 */
$stmt = $pdo->prepare("
    SELECT
        u.ftp_user,
        r.code AS role_code
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userRow) {
    http_response_code(403);
    exit('Uživatel nebyl nalezen.');
}

$ftpUser = trim((string)($userRow['ftp_user'] ?? ''));

if ($ftpUser === '') {
    http_response_code(400);
    exit('U uživatele chybí FTP účet.');
}

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.filename,
        p.preview_filepath,
        p.status,
        p.uploaded_at,
        p.locked_by_user_id,
        lu.user AS locked_by_user,
        lu.jmeno AS locked_jmeno,
        lu.prijmeni AS locked_prijmeni
    FROM photos p
    LEFT JOIN users lu ON lu.id = p.locked_by_user_id
    WHERE p.ftp_user = ?
      AND p.status <> 'deleted'
    ORDER BY p.uploaded_at DESC, p.id DESC
    LIMIT 200
");
$stmt->execute([$ftpUser]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel panel-status">
    <div class="status-head">
        <h1>Stav mých fotografií</h1>
        <div class="status-subhead">
            Fotograf: <?= h($ftpUser) ?>
        </div>
    </div>

    <?php if (empty($photos)): ?>
        <p>Nemáte zatím žádné fotografie.</p>
    <?php else: ?>

        <div class="status-photo-list">

            <?php foreach ($photos as $photo): ?>

                <?php
                $lockedByName = trim(
                    ((string)($photo['locked_jmeno'] ?? '')) . ' ' .
                    ((string)($photo['locked_prijmeni'] ?? ''))
                );

                $statusText = 'připraveno';
                $statusClass = 'status-ready';
                $statusNote = '';

                switch ((string)$photo['status']) {
                    case 'downloaded':
                        $statusText = 'staženo';
                        $statusClass = 'status-downloaded';
                        break;

                    case 'locked':
                        $statusText = 'zamknuto';
                        $statusClass = 'status-locked';

                        if ($lockedByName !== '') {
                            $statusNote = $lockedByName;
                        } elseif (!empty($photo['locked_by_user'])) {
                            $statusNote = (string)$photo['locked_by_user'];
                        }
                        break;

                    case 'processing':
                        $statusText = 'zpracování';
                        $statusClass = 'status-processing';
                        break;

                    case 'uploaded':
                        $statusText = 'nahráno';
                        $statusClass = 'status-uploaded';
                        break;

                    case 'error':
                        $statusText = 'chyba';
                        $statusClass = 'status-error';
                        break;
                }
                ?>

                <div class="status-photo-card">
                    <div class="status-photo-thumb">
                        <?php if (!empty($photo['preview_filepath'])): ?>
                            <img src="/preview.php?id=<?= (int)$photo['id'] ?>" alt="<?= h((string)$photo['filename']) ?>">
                        <?php else: ?>
                            <div class="status-no-preview">bez náhledu</div>
                        <?php endif; ?>
                    </div>

                    <div class="status-photo-meta">
                        <div class="status-photo-file">
                            <?= h((string)$photo['filename']) ?>
                        </div>

                        <div class="status-photo-state">
                            <span class="status <?= h($statusClass) ?>">
                                <?= h($statusText) ?>
                            </span>

                            <?php if ($statusNote !== ''): ?>
                                <span class="lock-owner">
                                    (<?= h($statusNote) ?>)
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="status-photo-time">
                            <?= h((string)$photo['uploaded_at']) ?>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>