<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/users.php';

require_login();

$dashboardEvent = events_get_current_dashboard_event();

$summary = null;
$participantCounts = null;
$photographers = [];
$editors = [];
$serverNow = microtime(true);
$eventTimezone = events_default_timezone();
$serverMsToday = 0;

if ($dashboardEvent) {
    $eventId = (int)$dashboardEvent['id'];
    $eventTimezone = events_normalize_timezone((string)($dashboardEvent['timezone'] ?? ''));
    $eventNow = (new DateTimeImmutable('@' . (string)(int)$serverNow))
        ->setTimezone(new DateTimeZone($eventTimezone));
    $serverMsToday = (
        (int)$eventNow->format('G') * 3600
        + (int)$eventNow->format('i') * 60
        + (int)$eventNow->format('s')
    ) * 1000 + (int)(($serverNow - floor($serverNow)) * 1000);

    $summary = events_stats_summary($eventId);
    $participantCounts = events_stats_counts_of_participants($eventId);
    $photographers = events_stats_photographers($eventId);
    $editors = events_stats_editors($eventId);
}

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <?php if (!$dashboardEvent): ?>
        <div class="card">
            <p>Momentálně není k dispozici žádný aktivní event.</p>
        </div>
    <?php else: ?>
        <?php
        $leaderName = trim(
            ((string)($dashboardEvent['leader_jmeno'] ?? '')) . ' ' .
            ((string)($dashboardEvent['leader_prijmeni'] ?? ''))
        );

        $statusLabel = match ((string)$dashboardEvent['status']) {
            'active'   => 'Aktivní',
            'planned'  => 'Plánovaný',
            'finished' => 'Ukončený',
            default    => (string)$dashboardEvent['status'],
        };

        $statusClass = match ((string)$dashboardEvent['status']) {
            'active'   => 'badge-success',
            'planned'  => 'badge-warning',
            'finished' => 'badge-muted',
            default    => 'badge-muted',
        };
        ?>

        <div class="dashboard-grid">
            <div class="card dashboard-card dashboard-card-main">
                <div class="dashboard-event-head">
                    <div>
                        <h1 class="dashboard-event-title"><?= h((string)$dashboardEvent['title']) ?> - dashboard</h1>
                        <div class="dashboard-event-sub">
                            <?= h((string)$dashboardEvent['slug']) ?>
                            <?php if (!empty($dashboardEvent['is_temporary'])): ?>
                                <span class="badge badge-info">temporary</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="dashboard-event-side">
                        <span class="badge <?= h($statusClass) ?>">
                            <?= h($statusLabel) ?>
                        </span>
                        <div class="dashboard-clock" id="dashboard-clock" data-server-ms="<?= $serverMsToday ?>">--:--:--</div>
                        <div class="dashboard-clock-zone"><?= h($eventTimezone) ?></div>
                        <label class="dashboard-beep-toggle">
                            <input type="checkbox" id="dashboard-beep-toggle">
                            <span>Pípat</span>
                        </label>
                    </div>
                </div>

                <div class="dashboard-meta-grid">
                    <div>
                        <div class="dashboard-label">Vedoucí eventu</div>
                        <div class="dashboard-value">
                            <?= h($leaderName !== '' ? $leaderName : '—') ?>
                        </div>
                        <?php if (!empty($dashboardEvent['leader_mobile'])): ?>
                            <div class="dashboard-subvalue">
                                <?= h(users_format_mobile((string)$dashboardEvent['leader_mobile'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div class="dashboard-label">Začátek</div>
                        <div class="dashboard-value">
                            <?= !empty($dashboardEvent['starts_at']) ? h((string)$dashboardEvent['starts_at']) : '—' ?>
                        </div>
                    </div>

                    <div>
                        <div class="dashboard-label">Konec</div>
                        <div class="dashboard-value">
                            <?= !empty($dashboardEvent['ends_at']) ? h((string)$dashboardEvent['ends_at']) : '—' ?>
                        </div>
                    </div>

                    <div>
                        <div class="dashboard-label">Veřejný dashboard</div>
                        <div class="dashboard-value">
                            <?= !empty($dashboardEvent['is_public']) ? 'Ano' : 'Ne' ?>
                        </div>
                    </div>
                </div>

                <div class="dashboard-links">
                    <div>
                        <div class="dashboard-label">Galerie Člověk a Víra</div>
                        <div class="dashboard-value">
                            <?php if (!empty($dashboardEvent['cav_gallery_url'])): ?>
                                <a href="<?= h((string)$dashboardEvent['cav_gallery_url']) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= h((string)$dashboardEvent['cav_gallery_url']) ?>
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>

            <div class="card dashboard-card dashboard-card-people">
                <h2>Fotografové</h2>

                <?php if (empty($photographers)): ?>
                    <p>Pro tento event nejsou přiřazeni žádní fotografové.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="list-table dashboard-table dashboard-table-photographers">
                            <colgroup>
                                <col class="col-name">
                                <col class="col-mobile">
                                <col class="col-mini">
                                <col class="col-mini">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Jméno</th>
                                    <th>Mobil</th>
                                    <th>Nahráno</th>
                                    <th>Publikováno</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($photographers as $p): ?>
                                    <?php $fullName = trim((string)$p['jmeno'] . ' ' . (string)$p['prijmeni']); ?>
                                    <tr>
                                        <td>
                                            <div class="dashboard-name-cell">
                                                <span class="dashboard-name-text">
                                                    <?= h($fullName !== '' ? $fullName : (string)$p['user']) ?>
                                                </span>
                                                <?php if (!empty($p['runner'])): ?>
                                                    <span class="badge badge-runner">runner</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?= !empty($p['mobile']) ? h(users_format_mobile((string)$p['mobile'])) : '—' ?>
                                        </td>
                                        <td class="dashboard-num-cell">
                                            <?= (int)($p['uploaded_count'] ?? 0) ?>
                                        </td>
                                        <td class="dashboard-num-cell">
                                            <?= (int)($p['published_count'] ?? 0) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card dashboard-card dashboard-card-summary">
                <h2>Souhrn</h2>

                <div class="dashboard-stats-grid">
                    <div class="stat-box">
                        <div class="stat-value"><?= (int)($participantCounts['photographers_count'] ?? 0) ?></div>
                        <div class="stat-label">Fotografové</div>
                    </div>

                    <div class="stat-box">
                        <div class="stat-value"><?= (int)($participantCounts['editors_count'] ?? 0) ?></div>
                        <div class="stat-label">Fotoeditoři</div>
                    </div>

                    <div class="stat-box">
                        <div class="stat-value"><?= (int)($summary['uploaded_total'] ?? 0) ?></div>
                        <div class="stat-label">Upload od fotografů</div>
                    </div>

                    <div class="stat-box">
                        <div class="stat-value"><?= (int)($summary['published_total'] ?? 0) ?></div>
                        <div class="stat-label">Publikováno</div>
                    </div>

                    <div class="stat-box">
                        <div class="stat-value"><?= h(events_format_duration($summary['workflow_min'] ?? null)) ?></div>
                        <div class="stat-label">Workflow min.</div>
                    </div>

                    <div class="stat-box">
                        <div class="stat-value"><?= h(events_format_duration($summary['workflow_median'] ?? null)) ?></div>
                        <div class="stat-label">Workflow medián</div>
                    </div>

                    <div class="stat-box">
                        <div class="stat-value"><?= h(events_format_duration($summary['workflow_max'] ?? null)) ?></div>
                        <div class="stat-label">Workflow max.</div>
                    </div>
                </div>
            </div>

            <div class="card dashboard-card dashboard-card-people">
                <h2>Fotoeditoři</h2>

                <?php if (empty($editors)): ?>
                    <p>Pro tento event nejsou přiřazeni žádní fotoeditoři.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="list-table dashboard-table dashboard-table-editors">
                            <colgroup>
                                <col class="col-name">
                                <col class="col-mobile">
                                <col class="col-mini">
                                <col class="col-mini">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Jméno</th>
                                    <th>Mobil</th>
                                    <th>Staženo</th>
                                    <th>Publikováno</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($editors as $e): ?>
                                    <tr>
                                        <td>
                                            <?= h(trim((string)$e['jmeno'] . ' ' . (string)$e['prijmeni'])) ?>
                                        </td>
                                        <td>
                                            <?= !empty($e['mobile']) ? h(users_format_mobile((string)$e['mobile'])) : '—' ?>
                                        </td>
                                        <td class="dashboard-num-cell">
                                            <?= (int)($e['downloaded_count'] ?? 0) ?>
                                        </td>
                                        <td class="dashboard-num-cell">
                                            <?= (int)($e['published_count'] ?? 0) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const clock = document.getElementById('dashboard-clock');
    if (!clock) {
        return;
    }

    const beepToggle = document.getElementById('dashboard-beep-toggle');
    const serverMs = Number(clock.dataset.serverMs || 0);
    const navigationEntry = performance.getEntriesByType('navigation')[0];
    const startedAt = navigationEntry && navigationEntry.responseStart ? navigationEntry.responseStart : 0;
    let audioContext = null;
    let lastRenderedSecond = null;
    let lastBeepKey = '';
    let reloadTimer = null;

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function isBeepEnabled() {
        return Boolean(beepToggle && beepToggle.checked);
    }

    function scheduleReload() {
        if (reloadTimer) {
            window.clearTimeout(reloadTimer);
            reloadTimer = null;
        }

        if (!isBeepEnabled()) {
            reloadTimer = window.setTimeout(function () {
                window.location.reload();
            }, 60000);
        }
    }

    function ensureAudioContext() {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) {
            return null;
        }

        if (!audioContext) {
            audioContext = new AudioContextClass();
        }

        if (audioContext.state === 'suspended') {
            audioContext.resume();
        }

        return audioContext;
    }

    function startBeep(context, durationMs) {
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        const now = context.currentTime;
        const duration = durationMs / 1000;

        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(1000, now);
        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(0.22, now + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + duration);

        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.start(now);
        oscillator.stop(now + duration + 0.02);
    }

    function playBeep(durationMs) {
        const context = ensureAudioContext();
        if (!context) {
            return;
        }

        if (context.state === 'suspended') {
            context.resume().then(function () {
                startBeep(context, durationMs);
            });
            return;
        }

        startBeep(context, durationMs);
    }

    function maybeBeep(secondsToday) {
        if (!isBeepEnabled()) {
            return;
        }

        const secondsFromQuarter = secondsToday % 15;
        const isQuarterSecond = secondsFromQuarter === 0;
        const secondsToNextQuarter = isQuarterSecond ? 0 : 15 - secondsFromQuarter;
        if (secondsToNextQuarter > 4) {
            return;
        }

        const key = String(secondsToday);
        if (key === lastBeepKey) {
            return;
        }

        lastBeepKey = key;
        playBeep(isQuarterSecond ? 500 : 250);
    }

    function updateClock() {
        const elapsedMs = performance.now() - startedAt;
        const msToday = (serverMs + elapsedMs) % 86400000;
        const secondsToday = Math.floor(msToday / 1000);
        if (secondsToday === lastRenderedSecond) {
            return;
        }

        lastRenderedSecond = secondsToday;
        const hours = Math.floor(secondsToday / 3600);
        const minutes = Math.floor((secondsToday % 3600) / 60);
        const seconds = secondsToday % 60;
        clock.textContent = pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);
        maybeBeep(secondsToday);
    }

    if (beepToggle) {
        beepToggle.addEventListener('change', function () {
            if (beepToggle.checked) {
                if (!window.confirm('Opravdu zapnout pípání hodin?')) {
                    beepToggle.checked = false;
                    scheduleReload();
                    return;
                }

                playBeep(250);
            }

            scheduleReload();
        });

        window.addEventListener('pagehide', function () {
            beepToggle.checked = false;
        });
    }

    updateClock();
    window.setInterval(updateClock, 250);
    scheduleReload();
});
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
