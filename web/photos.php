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
$sort = (string)($_GET['sort'] ?? 'uploaded');
if (!in_array($sort, ['uploaded', 'captured'], true)) {
    $sort = 'uploaded';
}
$reverseSort = (string)($_GET['reverse'] ?? '') === '1';
$currentEvent = photos_get_current_event();
$currentEventId = !empty($currentEvent['id']) ? (int)$currentEvent['id'] : 0;

$downloadJobId = max(0, (int)($_GET['download_job'] ?? 0));
$downloadTotal = max(0, (int)($_GET['download_total'] ?? 0));

$filters = [
    'event_id' => $currentEventId,
    'ftp_user' => $ftpUser,
    'status'   => $status,
];

$totalFiltered = photos_count($filters);
$totalAll = photos_count(['event_id' => $currentEventId]);
$photos = photos_list($filters, null, 0, $sort, $reverseSort);
$photographers = photos_get_photographers(['event_id' => $currentEventId]);
$photoContextQuery = array_filter([
    'ftp_user' => $ftpUser,
    'status' => $status,
    'sort' => $sort !== 'uploaded' ? $sort : '',
    'reverse' => $reverseSort ? '1' : '',
], static fn(string $value): bool => $value !== '');

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
    <h1>Fotografie download RAW, upload hotových</h1>

    <?php if (!empty($currentEvent)): ?>
        <p class="table-subtext">
            Aktuální event:
            <strong><?= h((string)$currentEvent['title']) ?></strong>
            <?php if (!empty($currentEvent['is_temporary'])): ?>
                <span class="badge badge-info">temporary</span>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <div class="photos-layout">
        <div class="photos-main">
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
                    <?php foreach (['ready', 'locked', 'downloaded', 'error'] as $s): ?>
                        <option value="<?= h($s) ?>" <?= $status === $s ? 'selected' : '' ?>>
                            <?= h(photos_status_label($s)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="sort">
                    <option value="uploaded" <?= $sort === 'uploaded' ? 'selected' : '' ?>>řadit podle uploadu</option>
                    <option value="captured" <?= $sort === 'captured' ? 'selected' : '' ?>>řadit podle pořízení</option>
                </select>

                <label class="filter-checkbox">
                    <input type="checkbox" name="reverse" value="1" <?= $reverseSort ? 'checked' : '' ?>>
                    <span>reverzně (nejnovější dole)</span>
                </label>

                <button type="submit">Filtrovat</button>
            </form>

            <p class="photo-count-summary" id="photo-count-summary">
                <?php if ($totalFiltered === $totalAll): ?>
                    Celkem fotografií: <strong><?= (int)$totalAll ?></strong>
                <?php else: ?>
                    Zobrazeno: <strong><?= (int)$totalFiltered ?></strong> z celkových <strong><?= (int)$totalAll ?></strong>
                <?php endif; ?>
            </p>

            <?php if (!empty($photos) && has_permission('photos.download')): ?>
                <div class="bulk-toolbar">
                    <form id="bulk-download-form" method="POST" action="/bulk-download-create.php" style="display:inline;">
                        <button type="submit">Hromadné stažení zamčených</button>
                    </form>

                    <span id="bulk-status-text">
                        <?php if ($downloadJobId > 0 && $downloadTotal > 0): ?>
                            Připraven download <?= $downloadTotal ?> fotografií.
                        <?php else: ?>
                            Moje zamčené v přehledu: <?= $lockedMineCount ?>
                        <?php endif; ?>
                    </span>

                    <span class="ingest-status ingest-status-inline" id="ingest-status" style="display:none">
                        <span class="ingest-pill ingest-uploading">
                            U:<strong id="uploading-count">0</strong>
                            <span class="ingest-dots" id="uploading-dots"><span>.</span><span>.</span><span>.</span></span>
                        </span>

                        <span class="ingest-pill ingest-processing">
                            P:<strong id="processing-count">0</strong>
                            <span class="status-spinner"></span>
                        </span>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (empty($photos)): ?>
                <p>Žádné fotografie.</p>
            <?php else: ?>
                <div class="photo-grid" id="photo-grid">
                    <?php foreach ($photos as $p): ?>
                        <?php
                        $cardClass = 'photo-card';
                        $isEventPhotographerAllowed = photos_is_event_photographer_allowed($p);
                        $isBlocked = photos_is_blocked($p);
                        $statusInfo = photos_display_status($p, $currentUserId);

                        if (($p['status'] ?? '') === 'locked' && !empty($p['locked_by_user_id'])) {
                            if ((int)$p['locked_by_user_id'] === $currentUserId) {
                                $cardClass .= ' selected';
                            } else {
                                $cardClass .= ' locked';
                            }
                        }

                        if (!empty($p['exif_problem'])) {
                            $cardClass .= ' exif-problem';
                        }

                        if (!$isEventPhotographerAllowed) {
                            $cardClass .= ' unassigned-event-photo';
                        }
                        if ($isBlocked) {
                            $cardClass .= ' blocked-photo';
                        }
                        ?>

                        <div class="<?= h($cardClass) ?>">
                            <a href="/photo.php?<?= h(http_build_query(['id' => (int)$p['id']] + $photoContextQuery)) ?>" class="photo-card-link">
                                <div class="thumb">
                                    <?php if (!empty($p['preview_filepath'])): ?>
                                        <img src="/preview.php?id=<?= (int)$p['id'] ?>&size=small" loading="lazy">
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
                                        <?php if (
                                            $statusInfo['class'] === 'status-selected'
                                            && !empty($p['locked_by_user_id'])
                                        ): ?>
                                                <a href="/select.php?id=<?= (int)$p['id'] ?>&action=unlock"
                                                   class="status status-selected status-clickable"
                                                   onclick="event.stopPropagation();">
                                                    <?= h($statusInfo['text']) ?>
                                                </a>
                                        <?php elseif (
                                            $statusInfo['class'] === 'status-ready'
                                            && has_permission('photos.select')
                                        ): ?>
                                                <a href="/select.php?id=<?= (int)$p['id'] ?>&action=lock"
                                                   class="status status-ready status-clickable"
                                                   onclick="event.stopPropagation();">
                                                    <?= h($statusInfo['text']) ?>
                                                </a>
                                        <?php else: ?>
                                            <div class="status-line">
                                                <div class="status <?= h($statusInfo['class']) ?>">
                                                    <?= h($statusInfo['text']) ?>
                                                </div>

                                                <?php if ($statusInfo['note'] !== ''): ?>
                                                    <div class="lock-owner">
                                                        (<?= h($statusInfo['note']) ?>)
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                    </div>

                                    <?php if (!empty($p['exif_problem'])): ?>
                                        <div class="photo-warning">
                                            problém v EXIFu
                                            <?php if (!empty($p['exif_problem_note'])): ?>
                                                – <?= h((string)$p['exif_problem_note']) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!$isEventPhotographerAllowed): ?>
                                        <div class="photo-warning photo-warning-unassigned">
                                            fotograf není přiřazen k eventu
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($isBlocked): ?>
                                        <div class="photo-warning">
                                            fotka je vyřazená z výběru
                                        </div>
                                    <?php endif; ?>

                                    <div class="time">
                                        <?= h((string)$p['uploaded_at']) ?>
                                    </div>

                                    <?php if (!empty($p['first_published_at']) && !empty($p['captured_at'])): ?>
                                        <div class="published-time">
                                            Čas workflow:
                                            <?= h(photos_format_duration_between((string)$p['captured_at'], (string)$p['first_published_at'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

        <?php if ($currentEventId > 0): ?>
            <aside
                class="chat-sidebar is-collapsed"
                id="chat-sidebar"
                data-chat-event-id="<?= (int)$currentEventId ?>"
            >
                <div class="side-panel-toggle-row">
                    <button type="button" class="side-panel-toggle" id="chat-sidebar-toggle" aria-label="Sbalit nebo rozbalit pravý panel" aria-expanded="false">
                        <span class="side-panel-toggle-icon" aria-hidden="true">‹</span>
                    </button>
                </div>
                <?php if (has_permission('published_photos.upload')): ?>
                    <form class="mini-publish" id="mini-publish-form" enctype="multipart/form-data">
                        <div class="mini-publish-head">
                            <strong>Publikace fotek</strong>
                            <a href="/published-upload.php">plná stránka</a>
                        </div>

                        <label class="mini-publish-dropzone" id="mini-publish-dropzone" for="mini-publish-files">
                            <span>Sem přetáhněte JPG</span>
                            <small id="mini-publish-summary">nebo klikněte</small>
                        </label>

                        <input class="mini-publish-input" type="file" name="photos[]" id="mini-publish-files" accept=".jpg,.jpeg,image/jpeg" multiple>

                        <div class="mini-publish-progress" id="mini-publish-progress" hidden>
                            <div class="mini-publish-progress-meta">
                                <span id="mini-publish-label">Nahrávám...</span>
                                <strong>
                                    <span id="mini-publish-count">0/0</span>
                                    <span id="mini-publish-percent">0 %</span>
                                </strong>
                            </div>
                            <div class="mini-publish-track">
                                <div class="mini-publish-bar" id="mini-publish-bar"></div>
                            </div>
                        </div>

                        <button type="submit">Publikovat</button>
                        <div class="mini-publish-result" id="mini-publish-result"></div>
                    </form>
                <?php endif; ?>

                <div class="chat-panel">
                    <div class="side-panel-collapsed-icons" aria-hidden="true">
                        <?php if (has_permission('published_photos.upload')): ?>
                            <span class="side-panel-collapsed-icon">📤</span>
                        <?php endif; ?>
                        <span class="side-panel-collapsed-icon">💬</span>
                    </div>

                    <div class="chat-sidebar-head">
                        <strong>Chat</strong>
                    </div>

                    <div class="event-chat-messages" id="event-chat-messages"></div>

                    <form class="event-chat-form" id="event-chat-form">
                        <textarea id="event-chat-input" placeholder="Napiš zprávu..." maxlength="2000"></textarea>
                        <button type="submit">Odeslat</button>
                    </form>
                </div>
            </aside>
        <?php endif; ?>
    </div>
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

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function renderPhotoCard(item, currentUserId, canSelect) {
    let cardClass = 'photo-card';
    const isEventPhotographerAllowed = item.event_photographer_allowed !== false;
    const isBlocked = item.is_blocked === true;

    if (item.status === 'locked' && item.locked_by_user_id) {
        if (Number(item.locked_by_user_id) === Number(currentUserId)) {
            cardClass += ' selected';
        } else {
            cardClass += ' locked';
        }
    }

    if (item.exif_problem) {
        cardClass += ' exif-problem';
    }

    if (!isEventPhotographerAllowed) {
        cardClass += ' unassigned-event-photo';
    }
    if (isBlocked) {
        cardClass += ' blocked-photo';
    }

    let thumbHtml = '';
    if (item.preview_exists) {
        thumbHtml = '<img src="/preview.php?id=' + item.id + '&size=small" loading="lazy">';
    } else {
        thumbHtml = '<div class="no-preview">bez náhledu</div>';
    }

    let statusHtml = '';

    if (isBlocked) {
        statusHtml = '<div class="status status-blocked">zablokováno</div>';
    } else if (!isEventPhotographerAllowed) {
        statusHtml = '<div class="status status-unassigned">mimo event</div>';
    } else if (Number(item.published_count || 0) > 0) {
        statusHtml = '<div class="status status-published">publikováno</div>';
    } else if (item.status === 'downloaded') {
        statusHtml = '<div class="status status-downloaded">staženo</div>';
    } else if (item.status === 'processing') {
        statusHtml = '<div class="status status-processing">zpracování</div>';
    } else if (item.status === 'uploaded') {
        statusHtml = '<div class="status status-uploaded">nahráno</div>';
    } else if (item.status === 'error') {
        statusHtml = '<div class="status status-error">chyba</div>';
    } else if (item.locked_by_user_id) {
        if (Number(item.locked_by_user_id) === Number(currentUserId)) {
            statusHtml =
                '<a href="/select.php?id=' + item.id + '&action=unlock" class="status status-selected status-clickable" onclick="event.stopPropagation();">ke stažení</a>';
        } else {
            const lockedByName = ((item.locked_jmeno || '') + ' ' + (item.locked_prijmeni || '')).trim();
            let ownerHtml = '';

            if (lockedByName !== '') {
                ownerHtml = '<div class="lock-owner">(' + escapeHtml(lockedByName) + ')</div>';
            } else if (item.locked_by_user) {
                ownerHtml = '<div class="lock-owner">(' + escapeHtml(item.locked_by_user) + ')</div>';
            }

            statusHtml =
                '<div class="status-line">' +
                    '<div class="status status-locked">zamknuto</div>' +
                    ownerHtml +
                '</div>';
        }
    } else {
        if (canSelect) {
            statusHtml =
                '<a href="/select.php?id=' + item.id + '&action=lock" class="status status-ready status-clickable" onclick="event.stopPropagation();">připraveno</a>';
        } else {
            statusHtml = '<div class="status status-ready">připraveno</div>';
        }
    }

    let warningHtml = '';
    if (item.exif_problem) {
        warningHtml = '<div class="photo-warning">problém v EXIFu' +
            (item.exif_problem_note ? ' – ' + escapeHtml(item.exif_problem_note) : '') +
            '</div>';
    }

    if (!isEventPhotographerAllowed) {
        warningHtml += '<div class="photo-warning photo-warning-unassigned">fotograf není přiřazen k eventu</div>';
    }

    if (isBlocked) {
        warningHtml += '<div class="photo-warning">fotka je vyřazená z výběru</div>';
    }

    let publishedTimeHtml = '';
    if (item.published_duration_label) {
        publishedTimeHtml =
            '<div class="published-time">Čas workflow: ' +
            escapeHtml(item.published_duration_label) +
            '</div>';
    }

    const detailUrl = new URL('/photo.php', window.location.origin);
    const currentParams = new URL(window.location.href).searchParams;
    detailUrl.searchParams.set('id', item.id);

    ['ftp_user', 'status', 'sort', 'reverse'].forEach(function (key) {
        if (currentParams.get(key)) {
            detailUrl.searchParams.set(key, currentParams.get(key));
        }
    });

    return '' +
        '<div class="' + cardClass + '">' +
            '<a href="' + detailUrl.pathname + detailUrl.search + '" class="photo-card-link">' +
                '<div class="thumb">' +
                    thumbHtml +
                '</div>' +
                '<div class="meta">' +
                    '<div class="file">' + escapeHtml(item.filename) + '</div>' +
                    '<div class="author">' + escapeHtml(item.ftp_user) + '</div>' +
                    '<div class="status-wrapper">' + statusHtml + '</div>' +
                    warningHtml +
                    '<div class="time">' + escapeHtml(item.uploaded_at) + '</div>' +
                    publishedTimeHtml +
                '</div>' +
            '</a>' +
        '</div>';
}

document.addEventListener('DOMContentLoaded', function () {
    const jobId = <?= $downloadJobId ?>;
    const total = <?= $downloadTotal ?>;
    const canSelect = <?= has_permission('photos.select') ? 'true' : 'false' ?>;
    const currentUserId = <?= (int)$currentUserId ?>;
    const photoGrid = document.getElementById('photo-grid');
    const statusBox = document.getElementById('bulk-status-text');
    const ingestStatus = document.getElementById('ingest-status');
    const uploadingCountEl = document.getElementById('uploading-count');
    const processingCountEl = document.getElementById('processing-count');
    const uploadingDotsEl = document.getElementById('uploading-dots');
    const photoCountSummary = document.getElementById('photo-count-summary');
    const miniPublishForm = document.getElementById('mini-publish-form');
    const miniPublishFiles = document.getElementById('mini-publish-files');
    const miniPublishDropzone = document.getElementById('mini-publish-dropzone');
    const miniPublishSummary = document.getElementById('mini-publish-summary');
    const miniPublishProgress = document.getElementById('mini-publish-progress');
    const miniPublishLabel = document.getElementById('mini-publish-label');
    const miniPublishCount = document.getElementById('mini-publish-count');
    const miniPublishPercent = document.getElementById('mini-publish-percent');
    const miniPublishBar = document.getElementById('mini-publish-bar');
    const miniPublishResult = document.getElementById('mini-publish-result');

    ['click', 'keydown', 'touchstart'].forEach(function (eventName) {
        document.addEventListener(eventName, unlockAudio, { once: true });
    });

    let lastSignature = '';
    let knownIds = new Set();
    let audioUnlocked = false;
    let audioContext = null;

    function unlockAudio() {
        if (audioUnlocked) {
            return;
        }

        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) {
                return;
            }

            audioContext = new Ctx();

            if (audioContext.state === 'suspended') {
                audioContext.resume();
            }

            audioUnlocked = true;
        } catch (e) {
            // ticho
        }
    }

    function beepNewPhoto() {
        if (!audioUnlocked || !audioContext) {
            return;
        }

        try {
            const now = audioContext.currentTime;

            const osc1 = audioContext.createOscillator();
            const osc2 = audioContext.createOscillator();
            const gain = audioContext.createGain();

            osc1.type = 'sine';
            osc1.frequency.value = 880;

            osc2.type = 'sine';
            osc2.frequency.value = 1320;

            gain.gain.setValueAtTime(0.0001, now);
            gain.gain.exponentialRampToValueAtTime(0.18, now + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.45);

            osc1.connect(gain);
            osc2.connect(gain);
            gain.connect(audioContext.destination);

            osc1.start(now);
            osc2.start(now);

            osc1.stop(now + 0.45);
            osc2.stop(now + 0.45);

        } catch (e) {
            // ticho
        }
    }

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

    function updateIngestStatus(data) {
        const uploading = Number(data.uploading_count || 0);
        const processing = Number(data.processing_count || 0);

        if (uploadingCountEl) {
            uploadingCountEl.textContent = String(uploading);
        }

        if (processingCountEl) {
            processingCountEl.textContent = String(processing);
        }

        if (uploadingDotsEl) {
            uploadingDotsEl.style.visibility = uploading > 0 ? 'visible' : 'hidden';
        }

        if (ingestStatus) {
            ingestStatus.style.display = (uploading > 0 || processing > 0) ? 'inline-flex' : 'none';
        }
    }

    function miniPublishFileCounter(percent) {
        const total = miniPublishFiles && miniPublishFiles.files ? miniPublishFiles.files.length : 0;
        if (total === 0) {
            return '0/0';
        }

        const current = Math.max(1, Math.min(total, Math.ceil((percent / 100) * total)));
        return current + '/' + total;
    }

    function setMiniPublishProgress(percent, label) {
        if (!miniPublishProgress || !miniPublishBar || !miniPublishPercent || !miniPublishCount || !miniPublishLabel) {
            return;
        }

        const clean = Math.max(0, Math.min(100, Math.round(percent)));
        miniPublishProgress.hidden = false;
        miniPublishBar.style.width = clean + '%';
        miniPublishPercent.textContent = clean + ' %';
        miniPublishCount.textContent = miniPublishFileCounter(clean);
        miniPublishLabel.textContent = label;
    }

    function updateMiniPublishSummary() {
        if (!miniPublishFiles || !miniPublishSummary) {
            return;
        }

        const count = miniPublishFiles.files ? miniPublishFiles.files.length : 0;
        if (count === 0) {
            miniPublishSummary.textContent = 'nebo klikněte';
        } else if (count === 1) {
            miniPublishSummary.textContent = miniPublishFiles.files[0].name;
        } else {
            miniPublishSummary.textContent = count + ' souborů vybráno';
        }
    }

    function renderMiniPublishResult(data) {
        if (!miniPublishResult) {
            return;
        }

        let html = '';

        if (Array.isArray(data.errors) && data.errors.length > 0) {
            data.errors.forEach(function (error) {
                html += '<div class="mini-publish-message mini-publish-error">' + escapeHtml(error) + '</div>';
            });
        }

        if (Array.isArray(data.uploaded) && data.uploaded.length > 0) {
            data.uploaded.forEach(function (item) {
                html += '<div class="mini-publish-message ' + (item.paired ? 'mini-publish-ok' : 'mini-publish-warning') + '">';
                html += escapeHtml(item.filename);
                html += item.paired ? ' spárováno' : ' bez spárování';
                html += '</div>';
            });
        }

        miniPublishResult.innerHTML = html;
    }

    if (miniPublishForm && miniPublishFiles && miniPublishDropzone) {
        miniPublishFiles.addEventListener('change', updateMiniPublishSummary);

        ['dragenter', 'dragover'].forEach(function (eventName) {
            miniPublishDropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                miniPublishDropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach(function (eventName) {
            miniPublishDropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                miniPublishDropzone.classList.remove('is-dragover');
            });
        });

        miniPublishDropzone.addEventListener('drop', function (event) {
            if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length > 0) {
                miniPublishFiles.files = event.dataTransfer.files;
                updateMiniPublishSummary();
            }
        });

        miniPublishForm.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!miniPublishFiles.files || miniPublishFiles.files.length === 0) {
                if (miniPublishResult) {
                    miniPublishResult.innerHTML = '<div class="mini-publish-message mini-publish-error">Vyber JPG soubory.</div>';
                }
                return;
            }

            const xhr = new XMLHttpRequest();
            const formData = new FormData(miniPublishForm);
            const submitButton = miniPublishForm.querySelector('button[type="submit"]');

            if (miniPublishResult) {
                miniPublishResult.innerHTML = '';
            }
            setMiniPublishProgress(0, 'Připravuji...');

            if (submitButton) {
                submitButton.disabled = true;
            }

            xhr.upload.addEventListener('progress', function (event) {
                if (event.lengthComputable) {
                    setMiniPublishProgress((event.loaded / event.total) * 100, 'Nahrávám...');
                } else {
                    setMiniPublishProgress(0, 'Nahrávám...');
                }
            });

            xhr.addEventListener('load', function () {
                if (submitButton) {
                    submitButton.disabled = false;
                }

                setMiniPublishProgress(100, 'Zpracovávám...');

                try {
                    const data = JSON.parse(xhr.responseText || '{}');
                    renderMiniPublishResult(data);
                    if (miniPublishLabel) {
                        miniPublishLabel.textContent = data.ok ? 'Hotovo' : 'Dokončeno s chybou';
                    }
                    refreshPhotoFeed();
                } catch (e) {
                    if (miniPublishResult) {
                        miniPublishResult.innerHTML = '<div class="mini-publish-message mini-publish-error">Server vrátil nečitelnou odpověď.</div>';
                    }
                    if (miniPublishLabel) {
                        miniPublishLabel.textContent = 'Chyba';
                    }
                }
            });

            xhr.addEventListener('error', function () {
                if (submitButton) {
                    submitButton.disabled = false;
                }
                if (miniPublishResult) {
                    miniPublishResult.innerHTML = '<div class="mini-publish-message mini-publish-error">Upload se nepodařilo dokončit.</div>';
                }
                if (miniPublishLabel) {
                    miniPublishLabel.textContent = 'Chyba';
                }
            });

            xhr.open('POST', '/published-upload.php');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(formData);
        });
    }

    async function refreshPhotoFeed() {
        const url = new URL('/api/photos-feed.php', window.location.origin);
        const current = new URL(window.location.href);

        if (current.searchParams.get('ftp_user')) {
            url.searchParams.set('ftp_user', current.searchParams.get('ftp_user'));
        }
        if (current.searchParams.get('status')) {
            url.searchParams.set('status', current.searchParams.get('status'));
        }
        if (current.searchParams.get('sort')) {
            url.searchParams.set('sort', current.searchParams.get('sort'));
        }
        if (current.searchParams.get('reverse')) {
            url.searchParams.set('reverse', current.searchParams.get('reverse'));
        }

        try {
            const response = await fetch(url.toString(), { cache: 'no-store' });
            if (!response.ok) {
                return;
            }

            const data = await response.json();
            if (!data || !Array.isArray(data.items)) {
                return;
            }

            const newIds = new Set(data.items.map(function (item) {
                return Number(item.id);
            }));

            if (knownIds.size > 0) {
                let hasNewPhoto = false;

                newIds.forEach(function (id) {
                    if (!knownIds.has(id)) {
                        hasNewPhoto = true;
                    }
                });

                if (hasNewPhoto) {
                    beepNewPhoto();
                }
            }

            knownIds = newIds;

            updateIngestStatus(data);
            updatePhotoCountSummary(data);

            const signature = JSON.stringify(data.items.map(function (item) {
                return [
                    item.id,
                    item.status,
                    item.preview_exists ? 1 : 0,
                    item.locked_by_user_id || 0,
                    item.exif_problem ? 1 : 0,
                    item.exif_problem_note || '',
                    item.published_duration_label || '',
                    item.is_blocked ? 1 : 0
                ];
            }));

            if (signature === lastSignature) {
                return;
            }

            lastSignature = signature;

            if (photoGrid) {
                photoGrid.innerHTML = data.items.map(function (item) {
                    return renderPhotoCard(item, currentUserId, canSelect);
                }).join('');
            }

            if (statusBox && !current.searchParams.get('download_job')) {
                statusBox.textContent = 'Moje zamčené v přehledu: ' + Number(data.locked_mine_count || 0);
            }
        } catch (e) {
            // ticho, zkusíme příště
        }
    }

    function updatePhotoCountSummary(data) {
        if (!photoCountSummary) {
            return;
        }

        const total = Number(data.total || 0);
        const totalAll = Number(data.total_all || total);

        if (total === totalAll) {
            photoCountSummary.innerHTML = 'Celkem fotografií: <strong>' + totalAll + '</strong>';
        } else {
            photoCountSummary.innerHTML = 'Zobrazeno: <strong>' + total + '</strong> z celkových <strong>' + totalAll + '</strong>';
        }
    }

    const AUTO_REFRESH_MS = 15000;
    let autoRefreshTimer = null;

    function startAutoRefresh() {
        if (autoRefreshTimer !== null) {
            return;
        }

        autoRefreshTimer = window.setInterval(function () {
            const current = new URL(window.location.href);

            if (current.searchParams.get('download_job')) {
                return;
            }

            if (document.visibilityState !== 'visible') {
                return;
            }

            refreshPhotoFeed();
        }, AUTO_REFRESH_MS);
    }

    function stopAutoRefresh() {
        if (autoRefreshTimer !== null) {
            clearInterval(autoRefreshTimer);
            autoRefreshTimer = null;
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            refreshPhotoFeed();
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    });

    if (document.visibilityState === 'visible') {
        refreshPhotoFeed();
        startAutoRefresh();
    }

    const sidebar = document.getElementById('chat-sidebar');
    const toggle = document.getElementById('chat-sidebar-toggle');

    if (sidebar && toggle) {
        const storageKey = 'press_chat_sidebar_collapsed';
        const saved = localStorage.getItem(storageKey);

        function syncSidebarToggle() {
            const collapsed = sidebar.classList.contains('is-collapsed');
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.setAttribute('title', collapsed ? 'Rozbalit pravý panel' : 'Sbalit pravý panel');
        }

        if (saved === '0') {
            sidebar.classList.remove('is-collapsed');
        } else {
            sidebar.classList.add('is-collapsed');
        }

        syncSidebarToggle();

        toggle.addEventListener('click', function () {
            const collapsed = sidebar.classList.toggle('is-collapsed');
            localStorage.setItem(storageKey, collapsed ? '1' : '0');
            syncSidebarToggle();
        });
    }
});
</script>

<?php
$chatJsFile = __DIR__ . '/assets/chat.js';
$chatJsVersion = is_file($chatJsFile) ? (string)filemtime($chatJsFile) : '1';
?>
<?php if ($currentEventId > 0): ?>
<script src="/assets/chat.js?v=<?= h($chatJsVersion) ?>"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
