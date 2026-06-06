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
$eventTimezoneName = events_normalize_timezone((string)($event['timezone'] ?? ''));
$eventTimezone = new DateTimeZone($eventTimezoneName);
$photos = $eventId > 0 ? published_photos_list_ready($eventId) : [];
$timelinePhotos = array_values(array_filter($photos, static fn(array $photo): bool => !empty($photo['captured_at'])));
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

        <div class="published-gallery-shell">
            <?php if (count($timelinePhotos) >= 2): ?>
                <div class="published-timeline" id="published-timeline" aria-hidden="true">
                    <div class="published-timeline-track" id="published-timeline-track">
                        <div class="published-timeline-ticks" id="published-timeline-ticks"></div>
                        <button type="button" class="published-timeline-thumb" id="published-timeline-thumb" tabindex="-1"></button>
                    </div>
                </div>
            <?php endif; ?>

        <div class="published-grid" id="published-grid">
            <?php foreach ($photos as $photo): ?>
                <?php
                $id = (int)$photo['id'];
                $downloadedInSession = published_photos_was_downloaded_in_session($id);
                $capturedTimelineTs = null;
                if (!empty($photo['captured_at'])) {
                    $capturedDate = new DateTimeImmutable((string)$photo['captured_at'], $eventTimezone);
                    $capturedTimelineTs = gmmktime(
                        (int)$capturedDate->format('H'),
                        (int)$capturedDate->format('i'),
                        (int)$capturedDate->format('s'),
                        (int)$capturedDate->format('m'),
                        (int)$capturedDate->format('d'),
                        (int)$capturedDate->format('Y')
                    );
                }
                ?>
                <article
                    class="published-card<?= $downloadedInSession ? ' is-downloaded-session' : '' ?>"
                    data-captured-ts="<?= $capturedTimelineTs !== false && $capturedTimelineTs !== null ? (int)$capturedTimelineTs : '' ?>"
                >
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
        </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = Array.from(document.querySelectorAll('.published-checkbox'));
    const selectAll = document.getElementById('published-select-all');
    const selectedButton = document.getElementById('published-download-selected');
    const status = document.getElementById('published-download-status');
    const grid = document.getElementById('published-grid');
    const timeline = document.getElementById('published-timeline');
    const timelineTrack = document.getElementById('published-timeline-track');
    const timelineTicks = document.getElementById('published-timeline-ticks');
    const timelineThumb = document.getElementById('published-timeline-thumb');

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

    function pad2(value) {
        return String(value).padStart(2, '0');
    }

    function formatTickLabel(timestamp) {
        const date = new Date(timestamp * 1000);
        return pad2(date.getUTCHours()) + ':' + pad2(date.getUTCMinutes());
    }

    function formatTickHour(timestamp) {
        const date = new Date(timestamp * 1000);
        return pad2(date.getUTCHours());
    }

    function buildTimeline() {
        if (!grid || !timeline || !timelineTrack || !timelineTicks || !timelineThumb) {
            return;
        }

        const timestamps = Array.from(grid.querySelectorAll('[data-captured-ts]'))
            .map(function (card) { return Number(card.dataset.capturedTs || 0); })
            .filter(function (value) { return value > 0; });

        if (timestamps.length < 2) {
            timeline.style.display = 'none';
            return;
        }

        const cards = Array.from(grid.querySelectorAll('[data-captured-ts]'))
            .map(function (card) {
                return {
                    element: card,
                    ts: Number(card.dataset.capturedTs || 0)
                };
            })
            .filter(function (item) { return item.ts > 0; });
        const minTs = Math.min.apply(null, timestamps);
        const maxTs = Math.max.apply(null, timestamps);
        const range = Math.max(1, maxTs - minTs);
        const quarter = 15 * 60;
        const firstTick = Math.ceil(minTs / quarter) * quarter;
        let html = '';

        for (let ts = firstTick; ts <= maxTs; ts += quarter) {
            const ratio = (maxTs - ts) / range;
            const top = Math.max(0, Math.min(100, ratio * 100));
            const date = new Date(ts * 1000);
            const isHour = date.getUTCMinutes() === 0;
            html += '<div class="published-timeline-tick ' + (isHour ? 'is-hour' : 'is-quarter') + '" style="top:' + top + '%">';
            html += '<span></span>';
            if (isHour) {
                html += '<strong data-hour="' + formatTickHour(ts) + '">' + formatTickLabel(ts) + '</strong>';
            }
            html += '</div>';
        }

        timelineTicks.innerHTML = html;

        function nearestCardByTime(targetTs) {
            let best = cards[0];
            let bestDistance = Math.abs(cards[0].ts - targetTs);

            cards.forEach(function (item) {
                const distance = Math.abs(item.ts - targetTs);
                if (distance < bestDistance) {
                    best = item;
                    bestDistance = distance;
                }
            });

            return best;
        }

        function updateThumb() {
            const viewportAnchor = window.scrollY + 140;
            let current = cards[cards.length - 1];

            for (let i = 0; i < cards.length; i++) {
                const top = window.scrollY + cards[i].element.getBoundingClientRect().top;
                if (top >= viewportAnchor) {
                    current = cards[i];
                    break;
                }
            }

            const ratio = Math.max(0, Math.min(1, (maxTs - current.ts) / range));
            timelineThumb.style.top = (ratio * 100) + '%';
        }

        function scrollToTrackPosition(clientY) {
            const rect = timelineTrack.getBoundingClientRect();
            const ratio = Math.max(0, Math.min(1, (clientY - rect.top) / Math.max(1, rect.height)));
            const targetTs = maxTs - ratio * range;
            const target = nearestCardByTime(targetTs);
            window.scrollTo({
                top: Math.max(0, window.scrollY + target.element.getBoundingClientRect().top - 120),
                behavior: 'auto'
            });
        }

        let dragging = false;

        timelineTrack.addEventListener('pointerdown', function (event) {
            dragging = true;
            timelineTrack.setPointerCapture(event.pointerId);
            scrollToTrackPosition(event.clientY);
        });

        timelineTrack.addEventListener('pointermove', function (event) {
            if (!dragging) {
                return;
            }
            scrollToTrackPosition(event.clientY);
        });

        timelineTrack.addEventListener('pointerup', function () {
            dragging = false;
        });

        timelineTrack.addEventListener('pointercancel', function () {
            dragging = false;
        });

        timelineTrack.addEventListener('click', function (event) {
            scrollToTrackPosition(event.clientY);
        });

        window.addEventListener('scroll', updateThumb, { passive: true });
        window.addEventListener('resize', updateThumb);
        updateThumb();
    }

    buildTimeline();
});
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
