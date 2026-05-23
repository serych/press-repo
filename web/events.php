<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/pfsense.php';
require_once __DIR__ . '/inc/users.php';

require_login();

if (!has_permission('users.manage') && !has_permission('photos.select')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$canManageEvents = has_permission('users.manage');
$flashMessage = '';
$flashType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'pfsense_ftp_toggle') {
    if (!$canManageEvents) {
        http_response_code(403);
        exit('Přístup odepřen.');
    }

    $targetState = (string)($_POST['target_state'] ?? '');

    try {
        if ($targetState === 'enabled') {
            pfsense_set_ftp_enabled(true);
            $flashMessage = 'FTP přístup byl zapnut.';
            $flashType = 'success';
        } elseif ($targetState === 'disabled') {
            pfsense_set_ftp_enabled(false);
            $flashMessage = 'FTP přístup byl vypnut.';
            $flashType = 'success';
        } else {
            throw new RuntimeException('Neplatný požadovaný stav FTP přístupu.');
        }
    } catch (Throwable $e) {
        $flashMessage = 'FTP přístup se nepodařilo přepnout: ' . $e->getMessage();
        $flashType = 'error';
    }
}

$pfsenseFtpStatus = $canManageEvents ? pfsense_ftp_status() : null;
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

    <?php if ($flashMessage !== ''): ?>
        <div class="<?= $flashType === 'error' ? 'alert-error' : 'alert-success' ?>">
            <?= h($flashMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($canManageEvents): ?>
        <?php
        $ftpState = (string)($pfsenseFtpStatus['state'] ?? 'unconfigured');
        $ftpMessage = (string)($pfsenseFtpStatus['message'] ?? 'pfSense API nenakonfigurováno');
        $ftpBadgeClass = match ($ftpState) {
            'enabled' => 'badge-success',
            'disabled' => 'badge-danger',
            'mixed', 'missing', 'error' => 'badge-warning',
            default => 'badge-muted',
        };
        $canToggleFtp = in_array($ftpState, ['enabled', 'disabled'], true);
        ?>
        <div class="card firewall-switch-card">
            <div>
                <h2>Vypínač press centra</h2>
                <div class="table-subtext">FTP přístup přes pfSense firewall</div>
            </div>

            <div class="firewall-switch-state">
                <span class="badge <?= h($ftpBadgeClass) ?>"><?= h($ftpMessage) ?></span>
                <form method="post" class="js-confirm-form firewall-switch-form"
                      data-confirm-title="<?= $ftpState === 'enabled' ? 'Vypnout FTP přístup?' : 'Zapnout FTP přístup?' ?>"
                      data-confirm-message="<?= $ftpState === 'enabled'
                          ? 'Tímto se na pfSense vypnou FTP firewall/NAT pravidla pro press centrum.'
                          : 'Tímto se na pfSense zapnou FTP firewall/NAT pravidla pro press centrum.' ?>"
                      data-confirm-submit="<?= $ftpState === 'enabled' ? 'Ano, vypnout FTP' : 'Ano, zapnout FTP' ?>">
                    <input type="hidden" name="action" value="pfsense_ftp_toggle">
                    <input type="hidden" name="target_state" value="<?= $ftpState === 'enabled' ? 'disabled' : 'enabled' ?>">
                    <button type="submit" class="button <?= $ftpState === 'enabled' ? 'btn-danger' : '' ?>" <?= $canToggleFtp ? '' : 'disabled' ?>>
                        <?= $ftpState === 'enabled' ? 'Vypnout FTP' : 'Zapnout FTP' ?>
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

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
                        <th>Vystaveno</th>
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
                            <td><?= (int)$summary['published_total'] ?></td>

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

<div class="confirm-modal" id="confirm-modal" hidden>
    <div class="confirm-modal-backdrop" data-confirm-close></div>
    <div class="confirm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
        <h3 id="confirm-modal-title">Potvrzení</h3>
        <div class="confirm-modal-message" id="confirm-modal-message"></div>
        <div class="confirm-modal-actions">
            <button type="button" class="button button-muted" id="confirm-cancel-btn">Zrušit</button>
            <button type="button" class="btn-danger" id="confirm-submit-btn">Pokračovat</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const confirmModal = document.getElementById('confirm-modal');
    const confirmTitle = document.getElementById('confirm-modal-title');
    const confirmMessage = document.getElementById('confirm-modal-message');
    const confirmCancelBtn = document.getElementById('confirm-cancel-btn');
    const confirmSubmitBtn = document.getElementById('confirm-submit-btn');
    let pendingForm = null;

    if (!confirmModal || !confirmTitle || !confirmMessage || !confirmCancelBtn || !confirmSubmitBtn) {
        return;
    }

    function closeConfirmDialog(confirmed) {
        confirmModal.hidden = true;
        document.body.classList.remove('modal-open');

        if (confirmed && pendingForm) {
            const form = pendingForm;
            pendingForm = null;
            form.submit();
            return;
        }

        pendingForm = null;
    }

    document.querySelectorAll('.js-confirm-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            pendingForm = form;
            confirmTitle.textContent = form.dataset.confirmTitle || 'Potvrzení';
            confirmMessage.textContent = form.dataset.confirmMessage || '';
            confirmSubmitBtn.textContent = form.dataset.confirmSubmit || 'Pokračovat';
            confirmModal.hidden = false;
            document.body.classList.add('modal-open');
            confirmSubmitBtn.focus();
        });
    });

    confirmCancelBtn.addEventListener('click', function () {
        closeConfirmDialog(false);
    });

    confirmSubmitBtn.addEventListener('click', function () {
        closeConfirmDialog(true);
    });

    confirmModal.querySelectorAll('[data-confirm-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            closeConfirmDialog(false);
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !confirmModal.hidden) {
            closeConfirmDialog(false);
        }
    });
});
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
