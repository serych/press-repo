<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/users.php';

$currentEvent = events_get_current_dashboard_event();

$summary = null;
$participantCounts = null;

if ($currentEvent && !empty($currentEvent['is_public'])) {
    $eventId = (int)$currentEvent['id'];
    $summary = events_stats_summary($eventId);
    $participantCounts = events_stats_counts_of_participants($eventId);
} else {
    $currentEvent = null;
}

?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Probíhající událost – <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="wrap header-row">
        <div class="brand">
            <a href="/ongoing-event.php" class="brand-link">
                <img src="/assets/logo-cs.svg" alt="PRESS centrum Člověk a Víra" class="brand-logo">
                <span class="brand-text">PRESScentrum ČaV</span>
            </a>
        </div>

        <nav class="top-nav top-nav-public">
            <a href="/login.php">Login do PRESS centra</a>
        </nav>
    </div>
</header>

<main class="wrap">
<section class="panel">
    <div class="page-head">
        <h1>Probíhající událost</h1>
    </div>

    <?php if (!$currentEvent): ?>
        <div class="card">
            <p>Momentálně není k dispozici žádná veřejně zobrazená aktivní událost.</p>
        </div>
    <?php else: ?>
        <?php
        $leaderName = trim(
            ((string)($currentEvent['leader_jmeno'] ?? '')) . ' ' .
            ((string)($currentEvent['leader_prijmeni'] ?? ''))
        );

        $statusLabel = match ((string)$currentEvent['status']) {
            'active'   => 'Aktivní',
            'planned'  => 'Plánovaný',
            'finished' => 'Ukončený',
            default    => (string)$currentEvent['status'],
        };

        $statusClass = match ((string)$currentEvent['status']) {
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
                        <h2 class="dashboard-event-title"><?= h((string)$currentEvent['title']) ?></h2>
                        <?php if (!empty($currentEvent['description'])): ?>
                            <div class="dashboard-event-description">
                                <?= nl2br(h((string)$currentEvent['description'])) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($currentEvent['is_temporary'])): ?>
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
                        <?php if (!empty($currentEvent['leader_mobile'])): ?>
                            <div class="dashboard-subvalue">
                                <?= h(users_format_mobile((string)$currentEvent['leader_mobile'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div class="dashboard-label">Začátek</div>
                        <div class="dashboard-value">
                            <?= !empty($currentEvent['starts_at']) ? h((string)$currentEvent['starts_at']) : '—' ?>
                        </div>
                    </div>

                    <div>
                        <div class="dashboard-label">Konec</div>
                        <div class="dashboard-value">
                            <?= !empty($currentEvent['ends_at']) ? h((string)$currentEvent['ends_at']) : '—' ?>
                        </div>
                    </div>

                    <div>
                        <div class="dashboard-label">Veřejný dashboard</div>
                        <div class="dashboard-value">
                            <?= !empty($currentEvent['is_public']) ? 'Ano' : 'Ne' ?>
                        </div>
                    </div>
                </div>

                <div class="dashboard-links">
                    <div>
                        <div class="dashboard-label">Galerie Člověk a Víra</div>
                        <div class="dashboard-value">
                            <?php if (!empty($currentEvent['cav_gallery_url'])): ?>
                                <a href="<?= h((string)$currentEvent['cav_gallery_url']) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= h((string)$currentEvent['cav_gallery_url']) ?>
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <div class="dashboard-label">Cloudový disk</div>
                        <div class="dashboard-value">
                            <?php if (!empty($currentEvent['cloud_url'])): ?>
                                <a href="<?= h((string)$currentEvent['cloud_url']) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= h((string)$currentEvent['cloud_url']) ?>
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
</main>
</body>
</html>
