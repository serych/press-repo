<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/users.php';

require_login();

if (!has_permission('users.manage') && !has_permission('photos.select')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$canManageEvents = has_permission('users.manage');
$events = events_list();

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <div class="page-head">
        <h1>Eventy</h1>

        <?php if ($canManageEvents): ?>
            <a href="/event-create.php" class="button">Nový event</a>
        <?php endif; ?>
    </div>

    <?php if (empty($events)): ?>
        <div class="card">
            <p>Zatím nejsou založené žádné eventy.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Název</th>
                        <th>Stav</th>
                        <th>Vedoucí</th>
                        <th>Začátek</th>
                        <th>Fotografové</th>
                        <th>Fotoeditoři</th>
                        <th>Upload</th>
                        <th>Staženo</th>
                        <?php if ($canManageEvents): ?>
                            <th>Akce</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <?php
                        $participantCounts = events_stats_counts_of_participants((int)$event['id']);
                        $summary = events_stats_summary((int)$event['id']);

                        $leaderName = trim(
                            ((string)($event['leader_jmeno'] ?? '')) . ' ' .
                            ((string)($event['leader_prijmeni'] ?? ''))
                        );

                        $statusLabel = match ((string)$event['status']) {
                            'active'   => 'Aktivní',
                            'planned'  => 'Plánovaný',
                            'finished' => 'Ukončený',
                            default    => (string)$event['status'],
                        };

                        $statusClass = match ((string)$event['status']) {
                            'active'   => 'badge-success',
                            'planned'  => 'badge-warning',
                            'finished' => 'badge-muted',
                            default    => 'badge-muted',
                        };
                        ?>
                        <tr>
                            <td>
                                <div class="event-title-cell">
                                    <div class="event-title-main">
                                        <?= h((string)$event['title']) ?>
                                    </div>

                                    <div class="event-title-sub">
                                        <?= h((string)$event['slug']) ?>
                                    </div>

                                    <?php if (!empty($event['is_temporary'])): ?>
                                        <div class="event-flags">
                                            <span class="badge badge-info">temporary</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <span class="badge <?= h($statusClass) ?>">
                                    <?= h($statusLabel) ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($leaderName !== ''): ?>
                                    <div><?= h($leaderName) ?></div>
                                    <?php if (!empty($event['leader_mobile'])): ?>
                                        <div class="table-subtext"><?= h(users_format_mobile((string)$event['leader_mobile'])) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="table-subtext">—</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($event['starts_at'])): ?>
                                    <?= h((string)$event['starts_at']) ?>
                                <?php else: ?>
                                    <span class="table-subtext">—</span>
                                <?php endif; ?>
                            </td>

                            <td><?= (int)$participantCounts['photographers_count'] ?></td>
                            <td><?= (int)$participantCounts['editors_count'] ?></td>
                            <td><?= (int)$summary['uploaded_total'] ?></td>
                            <td><?= (int)$summary['downloaded_total'] ?></td>

                            <?php if ($canManageEvents): ?>
                                <td>
                                    <div class="table-actions">
                                        <a class="table-action" href="/event-edit.php?id=<?= (int)$event['id'] ?>">Upravit</a>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
