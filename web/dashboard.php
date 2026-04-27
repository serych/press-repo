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

if ($dashboardEvent) {
    $eventId = (int)$dashboardEvent['id'];

    $summary = events_stats_summary($eventId);
    $participantCounts = events_stats_counts_of_participants($eventId);
    $photographers = events_stats_photographers($eventId);
    $editors = events_participants_by_role($eventId, 'editor');
}

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <div class="page-head">
        <h1>Dashboard</h1>
    </div>

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
                        <h2 class="dashboard-event-title"><?= h((string)$dashboardEvent['title']) ?></h2>
                        <div class="dashboard-event-sub">
                            <?= h((string)$dashboardEvent['slug']) ?>
                            <?php if (!empty($dashboardEvent['is_temporary'])): ?>
                                <span class="badge badge-info">temporary</span>
                            <?php endif; ?>
                        </div>
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

                    <div>
                        <div class="dashboard-label">Cloudový disk</div>
                        <div class="dashboard-value">
                            <?php if (!empty($dashboardEvent['cloud_url'])): ?>
                                <a href="<?= h((string)$dashboardEvent['cloud_url']) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= h((string)$dashboardEvent['cloud_url']) ?>
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
                                    <th>Upload</th>
                                    <th>Použito</th>
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
                                            <?= (int)($p['downloaded_count'] ?? 0) ?>
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
                        <div class="stat-label">Redaktoři</div>
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

            <div class="card dashboard-card dashboard-card-people">
                <h2>Redaktoři</h2>

                <?php if (empty($editors)): ?>
                    <p>Pro tento event nejsou přiřazeni žádní redaktoři.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="list-table dashboard-table dashboard-table-editors">
                            <colgroup>
                                <col class="col-name">
                                <col class="col-mobile">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Jméno</th>
                                    <th>Mobil</th>
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
setTimeout(function () {
    window.location.reload();
}, 60000);
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>