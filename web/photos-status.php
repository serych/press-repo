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

$stmt = $pdo->prepare("
    SELECT
        u.ftp_user
    FROM users u
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

$scope = (string)($_GET['scope'] ?? 'mine');
if (!in_array($scope, ['mine', 'all'], true)) {
    $scope = 'mine';
}

$stmt = $pdo->query("
    SELECT id
    FROM events
    WHERE status = 'active'
    ORDER BY is_temporary ASC, id DESC
    LIMIT 1
");
$activeEventId = $stmt->fetchColumn();
$activeEventId = $activeEventId !== false ? (int)$activeEventId : 0;

$sql = "
    SELECT
        p.id,
        p.filename,
        p.preview_filepath,
        p.status,
        p.uploaded_at,
        p.locked_by_user_id,
        p.exif_problem,
        p.event_photographer_allowed,
        lu.user AS locked_by_user,
        lu.jmeno AS locked_jmeno,
        lu.prijmeni AS locked_prijmeni,
        p.ftp_user
    FROM photos p
    LEFT JOIN users lu ON lu.id = p.locked_by_user_id
    WHERE p.status <> 'deleted'
";

$params = [];

if ($scope === 'mine') {
    $sql .= " AND p.ftp_user = ?";
    $params[] = $ftpUser;
}

if ($activeEventId > 0) {
    $sql .= " AND p.event_id = ?";
    $params[] = $activeEventId;
}

$sql .= "
    ORDER BY p.uploaded_at DESC, p.id DESC
    LIMIT 200
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel panel-status">
    <div class="status-head">
        <h1>Stav fotek</h1>

        <div class="status-subhead">
            <?php if ($scope === 'mine'): ?>
                Fotograf: <?= h($ftpUser) ?>
            <?php else: ?>
                Zobrazení: všechny fotografie
            <?php endif; ?>
        </div>

        <div class="filters status-scope-switch">
            <a href="?scope=mine" class="button <?= $scope === 'mine' ? '' : 'button-muted' ?>">Moje</a>
            <a href="?scope=all" class="button <?= $scope === 'all' ? '' : 'button-muted' ?>">Všechny</a>
        </div>

        <div class="ingest-status" id="ingest-status" style="display:none">
            <span class="ingest-pill ingest-uploading">
                U:<strong id="uploading-count">0</strong>
                <span class="ingest-dots" id="uploading-dots"><span>.</span><span>.</span><span>.</span></span>
            </span>

            <span class="ingest-pill ingest-processing">
                P:<strong id="processing-count">0</strong>
                <span class="status-spinner"></span>
            </span>
        </div>
    </div>

    <?php if (empty($photos)): ?>
        <p id="status-empty">Nejsou zde zatím žádné fotografie.</p>
        <div class="status-photo-list" id="status-photo-list" style="display:none;"></div>
    <?php else: ?>

        <p id="status-empty" style="display:none;">Nejsou zde zatím žádné fotografie.</p>

        <div class="status-photo-list" id="status-photo-list">

            <?php foreach ($photos as $photo): ?>

                <?php
                $lockedByName = trim(
                    ((string)($photo['locked_jmeno'] ?? '')) . ' ' .
                    ((string)($photo['locked_prijmeni'] ?? ''))
                );

                $statusText = 'připraveno';
                $statusClass = 'status-ready';
                $statusNote = '';

                if ((int)($photo['event_photographer_allowed'] ?? 1) !== 1) {
                    $statusText = 'mimo event';
                    $statusClass = 'status-unassigned';
                    $statusNote = 'fotograf není přiřazen';
                } else {
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
                }

                $cardClass = 'status-photo-card';
                if (!empty($photo['exif_problem'])) {
                    $cardClass .= ' exif-problem';
                }
                if ((int)($photo['event_photographer_allowed'] ?? 1) !== 1) {
                    $cardClass .= ' unassigned-event-photo';
                }
                ?>

                <div class="<?= h($cardClass) ?>" data-photo-id="<?= (int)$photo['id'] ?>">
                    <div class="status-photo-thumb">
                        <?php if (!empty($photo['preview_filepath'])): ?>
                            <img src="/preview.php?id=<?= (int)$photo['id'] ?>" alt="<?= h((string)$photo['filename']) ?>">
                        <?php else: ?>
                            <div class="status-no-preview">bez náhledu</div>
                        <?php endif; ?>
                    </div>

                    <div class="status-photo-meta">
                        <div class="status-photo-file" data-role="filename">
                            <?= h((string)$photo['filename']) ?>
                        </div>

                        <?php if ($scope === 'all'): ?>
                            <div class="status-photo-author" data-role="ftp-user">
                                <?= h((string)$photo['ftp_user']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="status-photo-state">
                            <span class="status <?= h($statusClass) ?>" data-role="status-badge">
                                <?php if ($statusClass === 'status-processing'): ?>
                                    <span class="status-spinner"></span>
                                <?php endif; ?>
                                <?= h($statusText) ?>
                            </span>

                            <span class="lock-owner" data-role="status-note" <?= $statusNote === '' ? 'style="display:none;"' : '' ?>>
                                <?php if ($statusNote !== ''): ?>
                                    (<?= h($statusNote) ?>)
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="status-photo-time" data-role="uploaded-at">
                            <?= h((string)$photo['uploaded_at']) ?>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const POLL_INTERVAL_MS = 30000;
    let timer = null;

    const list = document.getElementById('status-photo-list');
    const empty = document.getElementById('status-empty');
    const uploadingCountEl = document.getElementById('uploading-count');
    const processingCountEl = document.getElementById('processing-count');
    const uploadingDotsEl = document.getElementById('uploading-dots');
    const scope = <?= json_encode($scope, JSON_UNESCAPED_UNICODE) ?>;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function updateIngestStatus(data) {
        const uploading = Number(data.uploading_count || 0);
        const processing = Number(data.processing_count || 0);

        const container = document.getElementById('ingest-status');

        if (uploadingCountEl) {
            uploadingCountEl.textContent = String(uploading);
        }

        if (processingCountEl) {
            processingCountEl.textContent = String(processing);
        }

        if (uploadingDotsEl) {
            uploadingDotsEl.style.visibility = uploading > 0 ? 'visible' : 'hidden';
        }

        if (container) {
            if (uploading > 0 || processing > 0) {
                container.style.display = 'flex';
            } else {
                container.style.display = 'none';
            }
        }
    }

    function renderPhotoCard(item) {
        const previewHtml = item.preview_url
            ? '<img src="' + escapeHtml(item.preview_url) + '" alt="' + escapeHtml(item.filename) + '">'
            : '<div class="status-no-preview">bez náhledu</div>';

        const noteHtml = item.status_note
            ? '<span class="lock-owner" data-role="status-note">(' + escapeHtml(item.status_note) + ')</span>'
            : '<span class="lock-owner" data-role="status-note" style="display:none;"></span>';

        const spinnerHtml = item.status_class === 'status-processing'
            ? '<span class="status-spinner"></span>'
            : '';

        const authorHtml = scope === 'all'
            ? '<div class="status-photo-author" data-role="ftp-user">' + escapeHtml(item.ftp_user || '') + '</div>'
            : '';

        let cardClass = 'status-photo-card' + (item.exif_problem ? ' exif-problem' : '');
        if (item.event_photographer_allowed === false) {
            cardClass += ' unassigned-event-photo';
        }

        return '' +
            '<div class="' + cardClass + '" data-photo-id="' + item.id + '">' +
                '<div class="status-photo-thumb">' +
                    previewHtml +
                '</div>' +
                '<div class="status-photo-meta">' +
                    '<div class="status-photo-file" data-role="filename">' + escapeHtml(item.filename) + '</div>' +
                    authorHtml +
                    '<div class="status-photo-state">' +
                        '<span class="status ' + escapeHtml(item.status_class) + '" data-role="status-badge">' + spinnerHtml + escapeHtml(item.status_text) + '</span>' +
                        noteHtml +
                    '</div>' +
                    '<div class="status-photo-time" data-role="uploaded-at">' + escapeHtml(item.uploaded_at) + '</div>' +
                '</div>' +
            '</div>';
    }

    function ensureListVisibility(hasItems) {
        if (!list || !empty) {
            return;
        }

        if (hasItems) {
            list.style.display = '';
            empty.style.display = 'none';
        } else {
            list.style.display = 'none';
            empty.style.display = '';
        }
    }

    function upsertItem(item) {
        let card = document.querySelector('[data-photo-id="' + item.id + '"]');

        if (!card) {
            if (list) {
                list.insertAdjacentHTML('afterbegin', renderPhotoCard(item));
                ensureListVisibility(true);
            }
            return;
        }

        const badge = card.querySelector('[data-role="status-badge"]');
        const note = card.querySelector('[data-role="status-note"]');
        const time = card.querySelector('[data-role="uploaded-at"]');
        const file = card.querySelector('[data-role="filename"]');
        const ftpUser = card.querySelector('[data-role="ftp-user"]');

        card.classList.toggle('exif-problem', !!item.exif_problem);
        card.classList.toggle('unassigned-event-photo', item.event_photographer_allowed === false);

        if (badge) {
            const spinnerHtml = item.status_class === 'status-processing'
                ? '<span class="status-spinner"></span>'
                : '';

            badge.className = 'status ' + item.status_class;
            badge.innerHTML = spinnerHtml + escapeHtml(item.status_text);
        }

        if (note) {
            if (item.status_note && item.status_note !== '') {
                note.textContent = '(' + item.status_note + ')';
                note.style.display = '';
            } else {
                note.textContent = '';
                note.style.display = 'none';
            }
        }

        if (time) {
            time.textContent = item.uploaded_at;
        }

        if (file) {
            file.textContent = item.filename;
        }

        if (ftpUser && scope === 'all') {
            ftpUser.textContent = item.ftp_user || '';
        }
    }

    async function refreshStatuses() {
        if (document.visibilityState !== 'visible') {
            return;
        }

        try {
            const response = await fetch('/api/photos-status.php?scope=' + encodeURIComponent(scope), {
                cache: 'no-store'
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            if (!data || !Array.isArray(data.items)) {
                return;
            }

            updateIngestStatus(data);

            if (data.items.length === 0) {
                if (list) {
                    list.innerHTML = '';
                }
                ensureListVisibility(false);
                return;
            }

            data.items.forEach(function (item) {
                upsertItem(item);
            });

            ensureListVisibility(true);
        } catch (e) {
            // ticho, zkusíme příště
        }
    }

    function startPolling() {
        if (timer !== null) {
            return;
        }

        timer = window.setInterval(refreshStatuses, POLL_INTERVAL_MS);
    }

    function stopPolling() {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            refreshStatuses();
            startPolling();
        } else {
            stopPolling();
        }
    });

    if (document.visibilityState === 'visible') {
        refreshStatuses();
        startPolling();
    }
});
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
