<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/gallery_access.php';
require_once __DIR__ . '/inc/published_photos.php';

$galleryAccess = gallery_access_require_login_or_public_access();

$event = $galleryAccess ? events_get((int)$galleryAccess['event_id']) : published_photos_current_event();
$eventId = !empty($event['id']) ? (int)$event['id'] : 0;
$photos = $eventId > 0 ? published_photos_list_ready($eventId) : [];
$publishedPhotoCount = count($photos);
$publishedPhotoCountLabel = $publishedPhotoCount === 1
    ? 'fotografie'
    : ($publishedPhotoCount >= 2 && $publishedPhotoCount <= 4 ? 'fotografie' : 'fotografií');
$inEditorWorkCount = $eventId > 0 ? published_photos_in_editor_work_count($eventId) : 0;
$eventStatusLabel = null;
$eventStatusClass = 'badge-muted';

if ($event) {
    $eventStatusLabel = match ((string)$event['status']) {
        'active' => 'Aktivní',
        'planned' => 'Plánovaný',
        'finished' => 'Ukončený',
        default => (string)$event['status'],
    };
    $eventStatusClass = match ((string)$event['status']) {
        'active' => 'badge-success',
        'planned' => 'badge-warning',
        default => 'badge-muted',
    };
}

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <div class="published-page-head">
        <div class="published-event-title">
            <h1>Galerie<?= $event ? ' - ' . h((string)$event['title']) : '' ?></h1>
            <?php if ($eventStatusLabel !== null): ?>
                <span class="badge <?= h($eventStatusClass) ?>"><?= h($eventStatusLabel) ?></span>
            <?php endif; ?>
            <span class="badge badge-info"><?= (int)$publishedPhotoCount ?> <?= h($publishedPhotoCountLabel) ?></span>
        </div>

        <?php if ($event && !empty($event['description'])): ?>
            <div class="published-event-description">
                <?= nl2br(h((string)$event['description'])) ?>
            </div>
        <?php endif; ?>

        <p class="published-license-note">
            Při použití fotografie uveďte jméno autora/Člověk a Víra, tak jak je uvedeno u každé fotografie.
            <a href="https://www.clovekavira.cz/licencni-podminky" target="_blank" rel="noopener noreferrer">Licenční podmínky</a>
        </p>

        <?php if ($event && $inEditorWorkCount > 0): ?>
            <p class="published-work-note">
                Fotoeditoři právě pracují na <strong><?= (int)$inEditorWorkCount ?></strong>
                <?= $inEditorWorkCount === 1 ? 'fotografii' : 'fotografiích' ?>.
            </p>
        <?php endif; ?>
    </div>

    <?php if (!$event): ?>
        <p>Žádný aktivní event.</p>
    <?php elseif (!$photos): ?>
        <p>Zatím nejsou publikované žádné fotografie.</p>
    <?php else: ?>
        <div class="published-toolbar">
            <label class="checkbox-line">
                <input type="checkbox" id="published-select-all">
                <span>Vybrat vše</span>
            </label>
            <button type="button" class="button" id="published-download-selected">Stáhnout vybrané</button>
            <span class="table-subtext" id="published-download-status"></span>
        </div>

        <div class="published-grid">
            <?php foreach ($photos as $photo): ?>
                <?php
                $id = (int)$photo['id'];
                $downloadedInSession = published_photos_was_downloaded_in_session($id);
                ?>
                <article class="published-card<?= $downloadedInSession ? ' is-downloaded-session' : '' ?>">
                    <label class="published-select">
                        <input type="checkbox" class="published-checkbox" value="<?= $id ?>">
                    </label>

                    <a href="/published-photo.php?id=<?= $id ?>" class="published-thumb-link">
                        <img src="/published-preview.php?id=<?= $id ?>&size=small" alt="<?= h((string)$photo['filename']) ?>" loading="lazy">
                    </a>

                    <div class="published-meta">
                        <div class="file"><?= h((string)$photo['filename']) ?></div>
                        <div class="author"><?= h(published_photos_author_label_for_photo($photo)) ?></div>
                        <div class="time">
                            Vyfoceno:
                            <?= !empty($photo['captured_at']) ? h((string)$photo['captured_at']) : '—' ?>
                        </div>
                        <div class="time">
                            Stažení celkem: <?= (int)($photo['download_count'] ?? 0) ?>
                            <?php if ($downloadedInSession): ?>
                                <span class="status status-downloaded">staženo v této relaci</span>
                            <?php endif; ?>
                        </div>
                        <a href="/published-download.php?id=<?= $id ?>" class="button published-download-button">Stáhnout</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = Array.from(document.querySelectorAll('.published-checkbox'));
    const selectAll = document.getElementById('published-select-all');
    const selectedButton = document.getElementById('published-download-selected');
    const status = document.getElementById('published-download-status');

    function selectedIds() {
        return checkboxes
            .filter(function (checkbox) { return checkbox.checked; })
            .map(function (checkbox) { return checkbox.value; });
    }

    function downloadIds(ids) {
        if (!ids.length) {
            if (status) {
                status.textContent = 'Vyber alespoň jednu fotku.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Spouštím stažení ' + ids.length + ' fotek...';
        }

        ids.forEach(function (id, index) {
            window.setTimeout(function () {
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = '/published-download.php?id=' + encodeURIComponent(id);
                document.body.appendChild(iframe);
            }, index * 450);
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });
    }

    if (selectedButton) {
        selectedButton.addEventListener('click', function () {
            downloadIds(selectedIds());
        });
    }
});
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
