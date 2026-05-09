<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/users.php';

require_login();

$infoEvent = events_get_current_dashboard_event();

$summary = null;
$participantCounts = null;

if ($infoEvent && !empty($infoEvent['is_public'])) {
    $eventId = (int)$infoEvent['id'];
    $summary = events_stats_summary($eventId);
    $participantCounts = events_stats_counts_of_participants($eventId);
} else {
    $infoEvent = null;
}

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <?php if (!$infoEvent): ?>
        <div class="card">
            <p>Momentálně není k dispozici žádná veřejně zobrazená aktivní událost.</p>
        </div>
    <?php else: ?>
        <?php
        $leaderName = trim(
            ((string)($infoEvent['leader_jmeno'] ?? '')) . ' ' .
            ((string)($infoEvent['leader_prijmeni'] ?? ''))
        );

        $statusLabel = match ((string)$infoEvent['status']) {
            'active'   => 'Aktivní',
            'planned'  => 'Plánovaný',
            'finished' => 'Ukončený',
            default    => (string)$infoEvent['status'],
        };

        $statusClass = match ((string)$infoEvent['status']) {
            'active'   => 'badge-success',
            'planned'  => 'badge-warning',
            'finished' => 'badge-muted',
            default    => 'badge-muted',
        };
        ?>

        <div class="dashboard-grid dashboard-grid-public">
            <div class="card dashboard-card dashboard-card-main">
                <div class="dashboard-event-head">
                    <div>
                        <h2 class="dashboard-event-title"><?= h((string)$infoEvent['title']) ?></h2>
                        <?php if (!empty($infoEvent['description'])): ?>
                            <div class="dashboard-event-description">
                                <?= nl2br(h((string)$infoEvent['description'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($infoEvent['is_temporary'])): ?>
                            <div class="dashboard-event-sub">
                                <span class="badge badge-info">temporary</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <span class="badge <?= h($statusClass) ?>">
                            <?= h($statusLabel) ?>
                        </span>
                    </div>
                </div>

                <div class="dashboard-meta-grid">
                    <div>
                        <div class="dashboard-label">Vedoucí eventu</div>
                        <div class="dashboard-value">
                            <?= h($leaderName !== '' ? $leaderName : '—') ?>
                        </div>
                        <?php if (!empty($infoEvent['leader_mobile'])): ?>
                            <div class="dashboard-subvalue">
                                <?= h(users_format_mobile((string)$infoEvent['leader_mobile'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div class="dashboard-label">Začátek</div>
                        <div class="dashboard-value">
                            <?= !empty($infoEvent['starts_at']) ? h((string)$infoEvent['starts_at']) : '—' ?>
                        </div>
                    </div>

                    <div>
                        <div class="dashboard-label">Konec</div>
                        <div class="dashboard-value">
                            <?= !empty($infoEvent['ends_at']) ? h((string)$infoEvent['ends_at']) : '—' ?>
                        </div>
                    </div>
                </div>

                <div class="dashboard-links">
                    <div>
                        <div class="dashboard-label">Galerie Člověk a Víra</div>
                        <div class="dashboard-value">
                            <?php if (!empty($infoEvent['cav_gallery_url'])): ?>
                                <a href="<?= h((string)$infoEvent['cav_gallery_url']) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= h((string)$infoEvent['cav_gallery_url']) ?>
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card dashboard-card dashboard-card-summary dashboard-card-summary-public">
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
                        <div class="stat-label">Upload celkem</div>
                    </div>

                    <div class="stat-box">
                        <div class="stat-value"><?= (int)($summary['downloaded_total'] ?? 0) ?></div>
                        <div class="stat-label">Použito celkem</div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/inc/footer.php'; ?>
