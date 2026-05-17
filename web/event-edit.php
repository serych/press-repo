<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/users.php';
require_once __DIR__ . '/inc/chat.php';
require_once __DIR__ . '/inc/db.php';

require_login();

if (!has_permission('users.manage')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

function event_get_ftp_homedirs_for_cleanup(int $eventId): array
{
    $stmt = db()->prepare("
        SELECT DISTINCT
            u.homedir
        FROM photos p
        INNER JOIN users u ON u.ftp_user = p.ftp_user
        WHERE p.event_id = :event_id
          AND u.homedir IS NOT NULL
          AND u.homedir <> ''
        ORDER BY u.homedir ASC
    ");
    $stmt->execute([
        'event_id' => $eventId,
    ]);

    $dirs = [];
    foreach ($stmt->fetchAll() as $row) {
        $dir = trim((string)($row['homedir'] ?? ''));
        if ($dir !== '') {
            $dirs[] = $dir;
        }
    }

    return array_values(array_unique($dirs));
}

function event_restore_ftp_homedirs(array $dirs): array
{
    $restored = [];
    $failed = [];

    foreach ($dirs as $dir) {
        $dir = trim((string)$dir);
        if ($dir === '') {
            continue;
        }

        if (is_dir($dir)) {
            continue;
        }

        if (@mkdir($dir, 0775, true) || is_dir($dir)) {
            $restored[] = $dir;
        } else {
            $failed[] = $dir;
        }
    }

    return [
        'restored' => $restored,
        'failed' => $failed,
    ];
}

$id = max(1, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
$event = events_get($id);

if (!$event) {
    http_response_code(404);
    exit('Event nebyl nalezen.');
}

$allUsers = events_users_for_picker();
$editorUsers = events_users_for_picker('editor');
$timezones = events_timezone_identifiers();
$defaultTimezone = events_default_timezone();

$flashMessage = '';
$flashType = 'info';

if (!empty($_GET['chat_deleted'])) {
    $flashMessage = 'Chat eventu byl smazán.';
    $flashType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'delete_chat') {
    if (!has_permission('users.manage') && !has_permission('photos.select')) {
        http_response_code(403);
        exit('Přístup odepřen.');
    }

    chat_delete_all_for_event($id);

    header('Location: /event-edit.php?id=' . $id . '&chat_deleted=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup_action'])) {
    $cleanupAction = trim((string)($_POST['cleanup_action'] ?? ''));

    try {
        if ($cleanupAction === 'cleanup_test_data') {
            $ftpHomedirs = event_get_ftp_homedirs_for_cleanup($id);

            $result = events_cleanup_test_data($id);
            $restoreResult = event_restore_ftp_homedirs($ftpHomedirs);

            $flashMessage = 'Testovací data byla smazána. Fotky: ' . (int)$result['deleted_photos']
                . ', soubory: ' . (int)$result['deleted_files']
                . ', náhledy: ' . (int)$result['deleted_previews'] . '.';

            if (!empty($restoreResult['restored'])) {
                $flashMessage .= ' FTP adresáře obnoveny: ' . count($restoreResult['restored']) . '.';
            }

            if (!empty($restoreResult['failed'])) {
                $flashMessage .= ' Nepodařilo se obnovit některé FTP adresáře (' . count($restoreResult['failed']) . ').';
                $flashType = 'error';
            } else {
                $flashType = 'success';
            }
        } elseif ($cleanupAction === 'archive_event') {
            $result = events_archive($id);
            $flashMessage = 'Event byl archivován. Uložený souhrn: nahráno '
                . (int)$result['archived_uploaded_total'] . ', staženo '
                . (int)$result['archived_downloaded_total'] . '. Smazané fotky: '
                . (int)$result['deleted_photos'] . '.';
            $flashType = 'success';
        } elseif ($cleanupAction === 'cleanup_published_gallery') {
            $result = events_cleanup_published_gallery($id);
            $flashMessage = 'Hotová galerie byla smazána. Publikované fotky: '
                . (int)$result['deleted_published_photos']
                . ', soubory: ' . (int)$result['deleted_files']
                . ', náhledy: ' . (int)$result['deleted_previews'] . '.';
            $flashType = 'success';
        }

        $event = events_get($id);
    } catch (Throwable $e) {
        $flashMessage = 'Operaci se nepodařilo dokončit: ' . $e->getMessage();
        $flashType = 'error';
    }
}

$values = [
    'title'           => (string)$event['title'],
    'slug'            => (string)$event['slug'],
    'description'     => (string)($event['description'] ?? ''),
    'starts_at'       => !empty($event['starts_at']) ? date('Y-m-d\TH:i', strtotime((string)$event['starts_at'])) : '',
    'ends_at'         => !empty($event['ends_at']) ? date('Y-m-d\TH:i', strtotime((string)$event['ends_at'])) : '',
    'timezone'        => events_normalize_timezone((string)($event['timezone'] ?? '')),
    'cav_gallery_url' => (string)($event['cav_gallery_url'] ?? ''),
    'gps_coordinates' => events_gps_coordinates_input($event),
    'gps_altitude'    => events_gps_altitude_input($event),
    'leader_user_id'  => !empty($event['leader_user_id']) ? (string)$event['leader_user_id'] : '',
    'status'          => (string)$event['status'],
    'is_public'       => !empty($event['is_public']) ? '1' : '0',
    'is_temporary'    => !empty($event['is_temporary']) ? '1' : '0',
];

$selectedPhotographers = events_participants_get_ids_by_role($id, 'photographer');
$selectedEditors = events_participants_get_ids_by_role($id, 'editor');
$selectedRunnerUserIds = events_participants_get_runner_user_ids($id);

$errors = [];
$otherActiveEvent = events_get_other_active($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['cleanup_action']) && (string)($_POST['action'] ?? '') !== 'delete_chat') {
    $values['title']           = trim((string)($_POST['title'] ?? ''));
    $values['slug']            = trim((string)($_POST['slug'] ?? ''));
    $values['description']     = trim((string)($_POST['description'] ?? ''));
    $values['starts_at']       = trim((string)($_POST['starts_at'] ?? ''));
    $values['ends_at']         = trim((string)($_POST['ends_at'] ?? ''));
    $values['timezone']        = !empty($_POST['use_custom_timezone'])
        ? events_normalize_timezone((string)($_POST['timezone'] ?? ''))
        : $defaultTimezone;
    $values['cav_gallery_url'] = trim((string)($_POST['cav_gallery_url'] ?? ''));
    $values['gps_coordinates'] = trim((string)($_POST['gps_coordinates'] ?? ''));
    $values['gps_altitude']    = trim((string)($_POST['gps_altitude'] ?? ''));
    $values['leader_user_id']  = trim((string)($_POST['leader_user_id'] ?? ''));
    $values['status']          = trim((string)($_POST['status'] ?? 'planned'));
    $values['is_public']       = !empty($_POST['is_public']) ? '1' : '0';
    $values['is_temporary']    = !empty($event['is_temporary']) ? '1' : '0';

    $selectedPhotographers = array_values(array_unique(array_map('intval', $_POST['photographers'] ?? [])));
    $selectedEditors = array_values(array_unique(array_map('intval', $_POST['editors'] ?? [])));
    $selectedRunnerUserIds = array_values(array_unique(array_map('intval', $_POST['runners'] ?? [])));

    if ($values['title'] === '') {
        $errors[] = 'Vyplň název eventu.';
    }

    if ($values['slug'] === '') {
        $values['slug'] = events_slugify($values['title']);
    } else {
        $values['slug'] = events_slugify($values['slug']);
    }

    if ($values['slug'] === '') {
        $errors[] = 'Vyplň slug eventu.';
    } elseif (events_slug_exists($values['slug'], $id)) {
        $errors[] = 'Tento slug už existuje.';
    }

    if (!in_array($values['status'], ['planned', 'active', 'finished'], true)) {
        $errors[] = 'Neplatný stav eventu.';
    }

    $gps = events_gps_parse_coordinates($values['gps_coordinates']);
    if ($gps === null) {
        $errors[] = 'GPS souřadnice zadej ve formátu například 49.1896308N, 16.5751786E.';
    }

    $gpsAltitude = events_gps_parse_altitude($values['gps_altitude']);
    if ($gpsAltitude === null) {
        $errors[] = 'Nadmořskou výšku zadej jako číslo v metrech.';
    }

    if ((string)$event['status'] === 'finished' && $values['status'] !== 'finished') {
        if (empty($_POST['confirmed_status_change'])) {
            $errors[] = 'Měníš stav už ukončeného eventu. Potvrď tuto nestandardní operaci.';
        }
    }

    $otherActiveEvent = events_get_other_active($id);
    if ($values['status'] === 'active' && $otherActiveEvent && empty($_POST['deactivate_other_active'])) {
        $errors[] = 'V současnosti je aktivní event ' . (string)$otherActiveEvent['title'] . '. Potvrď jeho deaktivaci.';
    }

    if ($values['starts_at'] !== '' && strtotime($values['starts_at']) === false) {
        $errors[] = 'Neplatný datum/čas začátku.';
    }

    if ($values['ends_at'] !== '' && strtotime($values['ends_at']) === false) {
        $errors[] = 'Neplatný datum/čas konce.';
    }

    if (!events_timezone_is_valid($values['timezone'])) {
        $errors[] = 'Neplatné časové pásmo eventu.';
    }

    if ($values['starts_at'] !== '' && $values['ends_at'] !== '') {
        if (strtotime($values['ends_at']) < strtotime($values['starts_at'])) {
            $errors[] = 'Konec eventu nesmí být dříve než začátek.';
        }
    }

    $leaderUserId = (int)$values['leader_user_id'];
    if ($leaderUserId < 0) {
        $errors[] = 'Neplatný vedoucí eventu.';
    }

    $invalidRunnerIds = array_values(array_filter(
        $selectedRunnerUserIds,
        static fn(int $userId): bool => !in_array($userId, $selectedPhotographers, true)
    ));

    if ($invalidRunnerIds !== []) {
        $errors[] = 'Runner musí být vybraný i mezi fotografy.';
    }

    $allowedEditorIds = events_filter_editor_ids($selectedEditors);
    
    $invalidEditorIds = array_values(array_diff($selectedEditors, $allowedEditorIds));
    
    if ($invalidEditorIds !== []) {
        $errors[] = 'Fotoeditorem se nesmí stát fotograf.';
    }

    if (!$errors) {
        if ($values['status'] === 'active' && !empty($_POST['deactivate_other_active'])) {
            events_deactivate_other_active($id);
        }

        events_update($id, [
            'title'           => $values['title'],
            'slug'            => $values['slug'],
            'description'     => $values['description'],
            'starts_at'       => $values['starts_at'],
            'ends_at'         => $values['ends_at'],
            'timezone'        => $values['timezone'],
            'cav_gallery_url' => $values['cav_gallery_url'],
            'gps_latitude'    => $gps['gps_latitude'],
            'gps_latitude_ref' => $gps['gps_latitude_ref'],
            'gps_longitude'   => $gps['gps_longitude'],
            'gps_longitude_ref' => $gps['gps_longitude_ref'],
            'gps_altitude'    => $gpsAltitude['gps_altitude'],
            'gps_altitude_ref' => $gpsAltitude['gps_altitude_ref'],
            'leader_user_id'  => $leaderUserId > 0 ? $leaderUserId : null,
            'status'          => $values['status'],
            'is_public'       => $values['is_public'] === '1' ? 1 : 0,
            'is_temporary'    => !empty($event['is_temporary']) ? 1 : 0,
        ]);

        events_participants_save(
            $id,
            $selectedPhotographers,
            $selectedEditors,
            $selectedRunnerUserIds
        );

        header('Location: /event-edit.php?id=' . $id);
        exit;
    }
}

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <div class="page-head">
        <h1>Upravit event</h1>
        <a href="/events.php" class="button">Zpět na eventy</a>
    </div>

    <?php if ($flashMessage !== ''): ?>
        <div class="<?= $flashType === 'error' ? 'alert-error' : 'alert-success' ?>">
            <?= h($flashMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert-error">
            <?php foreach ($errors as $error): ?>
                <div><?= h($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php $summary = events_stats_summary($id); ?>

    <div class="card" style="margin-bottom: 16px;">
        <h2 style="margin-top: 0;">Úklid press centra</h2>
        <div class="table-subtext" style="margin-bottom: 12px;">
            Aktuální souhrn eventu: nahráno <?= (int)$summary['uploaded_total'] ?>, staženo <?= (int)$summary['downloaded_total'] ?>.
            <?php if (!empty($event['archived_at'])): ?>
                Archivováno dne <?= h((string)$event['archived_at']) ?>.
            <?php endif; ?>
        </div>

        <div class="form-actions" style="margin-bottom: 12px;">
            <a class="button" href="/event-report-download.php?id=<?= (int)$id ?>&format=xls">
                Stáhnout přehled fotek Excel
            </a>
            <a class="button button-muted" href="/event-report-download.php?id=<?= (int)$id ?>&format=csv">
                Stáhnout přehled fotek CSV
            </a>
        </div>

        <div class="form-actions">
            <form method="post" class="js-confirm-form"
                  data-confirm-title="Vyčistit testovací data?"
                  data-confirm-message="Budou odstraněny všechny nahrané testovací fotky, náhledy i databázové záznamy, které k nim patří."
                  data-confirm-submit="Ano, smazat testovací data">
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <input type="hidden" name="cleanup_action" value="cleanup_test_data">
                <button type="submit" class="btn-danger">Vyčistit testovací data</button>
            </form>

            <form method="post" class="js-confirm-form"
                  data-confirm-title="Archivovat event?"
                  data-confirm-message="Uloží se finální souhrn, smažou se pracovní fotky a náhledy a event bude přepnut do stavu Ukončený. Hotová galerie zůstane zachovaná."
                  data-confirm-submit="Ano, archivovat event">
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <input type="hidden" name="cleanup_action" value="archive_event">
                <button type="submit" class="btn-danger">Archivovat po eventu</button>
            </form>

            <form method="post" class="js-confirm-form"
                  data-confirm-title="Smazat hotovou galerii?"
                  data-confirm-message="Budou nenávratně odstraněny publikované JPG soubory, jejich náhledy a záznamy galerie pro tento event. Pracovní RAW/editor část zůstane beze změny."
                  data-confirm-submit="Ano, smazat hotovou galerii">
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <input type="hidden" name="cleanup_action" value="cleanup_published_gallery">
                <button type="submit" class="btn-danger">Smazání hotové galerie</button>
            </form>

            <form method="post" class="js-confirm-form"
                  data-confirm-title="Smazat chat?"
                  data-confirm-message="Opravdu nenávratně smazat chat?"
                  data-confirm-submit="Ano, smazat chat">
                <input type="hidden" name="id" value="<?= (int)$id ?>">
                <input type="hidden" name="action" value="delete_chat">
                <button type="submit" class="btn-danger">Smazat chat</button>
            </form>
        </div>
    </div>

    <div class="card">
        <form method="post" class="form event-form" autocomplete="off" id="event-edit-form">
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <input type="hidden" name="confirmed_status_change" id="confirmed_status_change" value="0">
            <input type="hidden" name="deactivate_other_active" id="deactivate_other_active" value="0">

            <div class="form-grid">
                <div>
                    <label for="title">Název eventu</label>
                    <input type="text" name="title" id="title" value="<?= h($values['title']) ?>" required>
                </div>

                <div>
                    <label for="slug">Slug</label>
                    <input type="text" name="slug" id="slug" value="<?= h($values['slug']) ?>" required>
                </div>

                <div>
                    <label for="starts_at">Začátek</label>
                    <input type="datetime-local" name="starts_at" id="starts_at" value="<?= h($values['starts_at']) ?>">
                </div>

                <div>
                    <label for="ends_at">Konec</label>
                    <input type="datetime-local" name="ends_at" id="ends_at" value="<?= h($values['ends_at']) ?>">
                </div>

                <div class="form-grid-span-2 event-timezone-field">
                    <label class="checkbox-line">
                        <input
                            type="checkbox"
                            name="use_custom_timezone"
                            value="1"
                            id="use_custom_timezone"
                            <?= $values['timezone'] !== $defaultTimezone ? 'checked' : '' ?>
                        >
                        <span>Jiné časové pásmo</span>
                    </label>

                    <select name="timezone" id="timezone" <?= $values['timezone'] === $defaultTimezone ? 'disabled' : '' ?>>
                        <?php foreach ($timezones as $timezone): ?>
                            <option value="<?= h($timezone) ?>" <?= $values['timezone'] === $timezone ? 'selected' : '' ?>>
                                <?= h($timezone) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="leader_user_id">Vedoucí eventu</label>
                    <select name="leader_user_id" id="leader_user_id">
                        <option value="">-- bez vedoucího --</option>
                        <?php foreach ($allUsers as $u): ?>
                            <?php $fullName = trim((string)$u['jmeno'] . ' ' . (string)$u['prijmeni']); ?>
                            <option value="<?= (int)$u['id'] ?>" <?= (string)$u['id'] === $values['leader_user_id'] ? 'selected' : '' ?>>
                                <?= h($fullName !== '' ? $fullName : (string)$u['user']) ?>
                                <?php if (!empty($u['mobile'])): ?>
                                    (<?= h(users_format_mobile((string)$u['mobile'])) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="status">Stav</label>
                    <select name="status" id="status" required data-original-status="<?= h((string)$event['status']) ?>">
                        <option value="planned" <?= $values['status'] === 'planned' ? 'selected' : '' ?>>Plánovaný</option>
                        <option value="active" <?= $values['status'] === 'active' ? 'selected' : '' ?>>Aktivní</option>
                        <option value="finished" <?= $values['status'] === 'finished' ? 'selected' : '' ?>>Ukončený</option>
                    </select>
                </div>

                <div>
                    <label for="cav_gallery_url">URL galerie Člověk a Víra</label>
                    <input type="url" name="cav_gallery_url" id="cav_gallery_url" value="<?= h($values['cav_gallery_url']) ?>">
                </div>

                <div>
                    <label for="gps_coordinates">GPS souřadnice</label>
                    <input type="text" name="gps_coordinates" id="gps_coordinates" value="<?= h($values['gps_coordinates']) ?>" placeholder="49.1896308N, 16.5751786E">
                </div>

                <div>
                    <label for="gps_altitude">Nadmořská výška GPS (m)</label>
                    <input type="text" name="gps_altitude" id="gps_altitude" value="<?= h($values['gps_altitude']) ?>" placeholder="250">
                </div>

                <div class="form-grid-span-2">
                    <label for="description">Popis / poznámka</label>
                    <textarea name="description" id="description" rows="3"><?= h($values['description']) ?></textarea>
                </div>

                <div class="form-grid-span-2">
                    <label class="checkbox-line">
                        <input type="checkbox" name="is_public" value="1" <?= $values['is_public'] === '1' ? 'checked' : '' ?>>
                        <span>Zobrazit event i na veřejném dashboardu</span>
                    </label>
                </div>

                <?php if (!empty($event['is_temporary'])): ?>
                    <div class="form-grid-span-2">
                        <div class="alert-info">
                            Tento event je označen jako temporary. Typ eventu nelze měnit.
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php
            $pickerAllUsersJson = json_encode($allUsers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $pickerEditorUsersJson = json_encode($editorUsers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $selectedPhotographersJson = json_encode($selectedPhotographers);
            $selectedEditorsJson = json_encode($selectedEditors);
            $selectedRunnerUserIdsJson = json_encode($selectedRunnerUserIds);
            ?>

            <div class="participant-pickers participant-pickers-enhanced">
                <div class="card participant-card participant-card-full">
                    <h2>Fotoeditoři</h2>
                    <div
                        id="editor-picker"
                        class="participant-picker participant-picker-table"
                        data-users='<?= h((string)$pickerEditorUsersJson) ?>'
                        data-selected='<?= h((string)$selectedEditorsJson) ?>'
                        data-hidden-name="editors[]"
                        data-placeholder="Začni psát příjmení, jméno nebo login…"
                        data-empty-text="Zatím není vybraný žádný fotoeditor."
                        data-mode="editor"
                        data-add-label="Přidání:"
                    ></div>
                </div>

                <div class="card participant-card participant-card-full">
                    <h2>Fotografové / runneři</h2>
                    <div
                        id="photographer-picker"
                        class="participant-picker participant-picker-table"
                        data-users='<?= h((string)$pickerAllUsersJson) ?>'
                        data-selected='<?= h((string)$selectedPhotographersJson) ?>'
                        data-runners='<?= h((string)$selectedRunnerUserIdsJson) ?>'
                        data-hidden-name="photographers[]"
                        data-runner-name="runners[]"
                        data-placeholder="Začni psát příjmení, jméno nebo login…"
                        data-empty-text="Zatím není vybraný žádný fotograf."
                        data-mode="photographer"
                        data-add-label="Přidání:"
                    ></div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit">Uložit event</button>
            </div>
        </form>
    </div>
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
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    const statusSelect = document.getElementById('status');
    const form = document.getElementById('event-edit-form');
    const confirmedStatusChange = document.getElementById('confirmed_status_change');
    const deactivateOtherActive = document.getElementById('deactivate_other_active');
    const customTimezoneToggle = document.getElementById('use_custom_timezone');
    const timezoneSelect = document.getElementById('timezone');
    const otherActiveEventTitle = <?= json_encode($otherActiveEvent ? (string)$otherActiveEvent['title'] : '', JSON_UNESCAPED_UNICODE) ?>;

    const confirmModal = document.getElementById('confirm-modal');
    const confirmTitle = document.getElementById('confirm-modal-title');
    const confirmMessage = document.getElementById('confirm-modal-message');
    const confirmCancelBtn = document.getElementById('confirm-cancel-btn');
    const confirmSubmitBtn = document.getElementById('confirm-submit-btn');

    let confirmResolve = null;

    function slugify(value) {
        return value
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function normalizeText(value) {
        return (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function showConfirmDialog(title, message, submitLabel) {
        return new Promise(function (resolve) {
            confirmResolve = resolve;
            confirmTitle.textContent = title || 'Potvrzení';
            confirmMessage.textContent = message || '';
            confirmSubmitBtn.textContent = submitLabel || 'Pokračovat';
            confirmModal.hidden = false;
            document.body.classList.add('modal-open');
            confirmSubmitBtn.focus();
        });
    }

    function closeConfirmDialog(result) {
        confirmModal.hidden = true;
        document.body.classList.remove('modal-open');
        if (confirmResolve) {
            confirmResolve(result);
            confirmResolve = null;
        }
    }

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

    titleInput.addEventListener('input', function () {
        if (slugInput.dataset.autofill === '0') {
            return;
        }

        slugInput.value = slugify(titleInput.value);
        slugInput.dataset.autofill = '1';
    });

    slugInput.addEventListener('input', function () {
        slugInput.dataset.autofill = slugInput.value.trim() === '' ? '1' : '0';
    });

    if (slugInput.value.trim() === '') {
        slugInput.dataset.autofill = '1';
    } else {
        slugInput.dataset.autofill = '0';
    }

    function syncTimezoneSelect() {
        if (!customTimezoneToggle || !timezoneSelect) {
            return;
        }

        timezoneSelect.disabled = !customTimezoneToggle.checked;
    }

    if (customTimezoneToggle) {
        customTimezoneToggle.addEventListener('change', syncTimezoneSelect);
        syncTimezoneSelect();
    }

    document.querySelectorAll('.js-confirm-form').forEach(function (confirmForm) {
        confirmForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            const ok = await showConfirmDialog(
                confirmForm.dataset.confirmTitle || 'Potvrzení',
                confirmForm.dataset.confirmMessage || '',
                confirmForm.dataset.confirmSubmit || 'Pokračovat'
            );

            if (ok) {
                confirmForm.submit();
            }
        });
    });

    form.addEventListener('submit', async function (event) {
        const originalStatus = statusSelect.dataset.originalStatus || '';
        const newStatus = statusSelect.value || '';

        if (newStatus === 'active' && otherActiveEventTitle !== '' && deactivateOtherActive.value !== '1') {
            event.preventDefault();

            const ok = await showConfirmDialog(
                'Deaktivovat současný aktivní event?',
                'V současnosti je aktivní event ' + otherActiveEventTitle + '. Mám ho deaktivovat?',
                'Ano, deaktivovat'
            );

            if (!ok) {
                return;
            }

            deactivateOtherActive.value = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
            return;
        }

        if (originalStatus === 'finished' && newStatus !== 'finished') {
            event.preventDefault();

            const ok = await showConfirmDialog(
                'Změnit stav ukončeného eventu?',
                'Tento event je už ukončený. Opravdu chceš změnit jeho stav na jiný? To není standardní situace.',
                'Ano, změnit stav'
            );

            if (!ok) {
                return;
            }

            confirmedStatusChange.value = '1';
            form.submit();
            return;
        }

        confirmedStatusChange.value = '0';
    });

    function buildLabel(user) {
        const fullName = [user.prijmeni, user.jmeno].filter(Boolean).join(' ').trim()
            || [user.jmeno, user.prijmeni].filter(Boolean).join(' ').trim()
            || user.user;

        const roleText = user.role_name ? `(${user.role_name})` : '';
        const phoneText = user.mobile || '';

        return { fullName, roleText, phoneText };
    }

    function initParticipantPicker(root) {
        const users = JSON.parse(root.dataset.users || '[]');
        const selected = new Set(JSON.parse(root.dataset.selected || '[]').map(Number));
        const runners = new Set(JSON.parse(root.dataset.runners || '[]').map(Number));
        const hiddenName = root.dataset.hiddenName;
        const runnerName = root.dataset.runnerName || '';
        const placeholder = root.dataset.placeholder || 'Hledat…';
        const emptyText = root.dataset.emptyText || 'Nic nevybráno.';
        const mode = root.dataset.mode || 'default';
        const addLabel = root.dataset.addLabel || 'Přidání:';

        const userMap = new Map(users.map(user => [Number(user.id), user]));

        root.innerHTML = `
            <div class="participant-picker-add-row">
                <label class="participant-add-label">${escapeHtml(addLabel)}</label>
                <div class="participant-picker-search">
                    <input type="text" class="participant-search-input" placeholder="${escapeHtml(placeholder)}" autocomplete="off">
                    <div class="participant-suggest-list" hidden></div>
                </div>
            </div>
            <div class="participant-table-wrap">
                <table class="participant-table">
                    <thead>
                        <tr>
                            <th>Jméno</th>
                            <th>Telefon</th>
                            ${mode === 'photographer' ? '<th class="participant-col-runner">Runner</th>' : ''}
                            <th class="participant-col-action">Akce</th>
                        </tr>
                    </thead>
                    <tbody class="participant-selected-body"></tbody>
                </table>
                <div class="participant-empty" hidden></div>
            </div>
        `;

        const input = root.querySelector('.participant-search-input');
        const suggestList = root.querySelector('.participant-suggest-list');
        const selectedBody = root.querySelector('.participant-selected-body');
        const emptyBox = root.querySelector('.participant-empty');

        function createHidden(name, value) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = name;
            hidden.value = String(value);
            return hidden;
        }

        function renderSelected() {
            selectedBody.innerHTML = '';

            const ids = Array.from(selected).sort((a, b) => {
                const ua = userMap.get(a);
                const ub = userMap.get(b);
                const sa = normalizeText((ua?.prijmeni || '') + ' ' + (ua?.jmeno || '') + ' ' + (ua?.user || ''));
                const sb = normalizeText((ub?.prijmeni || '') + ' ' + (ub?.jmeno || '') + ' ' + (ub?.user || ''));
                return sa.localeCompare(sb, 'cs');
            });

            if (!ids.length) {
                emptyBox.hidden = false;
                emptyBox.textContent = emptyText;
                return;
            }

            emptyBox.hidden = true;
            emptyBox.textContent = '';

            ids.forEach(function (id, index) {
                const user = userMap.get(id);
                if (!user) {
                    return;
                }

                const label = buildLabel(user);

                const row = document.createElement('tr');
                row.className = 'participant-table-row';
                row.dataset.index = String(index % 2);

                const nameCell = document.createElement('td');
                nameCell.className = 'participant-name-cell';

                const nameWrap = document.createElement('div');
                nameWrap.className = 'participant-name-wrap';

                const strong = document.createElement('strong');
                strong.textContent = label.fullName;

                const small = document.createElement('small');
                small.textContent = label.roleText;

                nameWrap.appendChild(strong);
                if (label.roleText !== '') {
                    nameWrap.appendChild(small);
                }

                nameCell.appendChild(nameWrap);
                nameCell.appendChild(createHidden(hiddenName, id));

                const phoneCell = document.createElement('td');
                phoneCell.className = 'participant-phone-cell';
                phoneCell.textContent = label.phoneText || '—';

                row.appendChild(nameCell);
                row.appendChild(phoneCell);

                if (mode === 'photographer') {
                    const runnerCell = document.createElement('td');
                    runnerCell.className = 'participant-runner-cell';

                    const runnerCheckbox = document.createElement('input');
                    runnerCheckbox.type = 'checkbox';
                    runnerCheckbox.checked = runners.has(id);
                    runnerCheckbox.addEventListener('change', function () {
                        if (runnerCheckbox.checked) {
                            runners.add(id);
                        } else {
                            runners.delete(id);
                        }
                        renderSelected();
                    });

                    runnerCell.appendChild(runnerCheckbox);

                    if (runners.has(id)) {
                        runnerCell.appendChild(createHidden(runnerName, id));
                    }

                    row.appendChild(runnerCell);
                }

                const actionCell = document.createElement('td');
                actionCell.className = 'participant-action-cell';

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'participant-remove-btn participant-remove-btn-small';
                removeBtn.textContent = 'Odebrat';
                removeBtn.addEventListener('click', function () {
                    selected.delete(id);
                    runners.delete(id);
                    renderSelected();
                    renderSuggestions();
                    input.focus();
                });

                actionCell.appendChild(removeBtn);
                row.appendChild(actionCell);

                selectedBody.appendChild(row);
            });
        }

        function renderSuggestions() {
            const term = normalizeText(input.value.trim());

            const results = users.filter(function (user) {
                if (selected.has(Number(user.id))) {
                    return false;
                }

                if (term === '') {
                    return false;
                }

                const haystack = normalizeText([
                    user.prijmeni,
                    user.jmeno,
                    user.user,
                    user.mobile,
                    user.role_name
                ].filter(Boolean).join(' '));

                return haystack.includes(term);
            }).slice(0, 8);

            suggestList.innerHTML = '';

            if (!results.length) {
                suggestList.hidden = true;
                return;
            }

            results.forEach(function (user) {
                const label = buildLabel(user);
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'participant-suggest-item';
                button.innerHTML = `
                    <strong>${escapeHtml(label.fullName)}</strong>
                    <small>${escapeHtml([label.roleText, label.phoneText].filter(Boolean).join(' · '))}</small>
                `;
                button.addEventListener('click', function () {
                    selected.add(Number(user.id));
                    input.value = '';
                    renderSelected();
                    renderSuggestions();
                    input.focus();
                });

                suggestList.appendChild(button);
            });

            suggestList.hidden = false;
        }

        input.addEventListener('input', renderSuggestions);
        input.addEventListener('focus', renderSuggestions);

        document.addEventListener('click', function (event) {
            if (!root.contains(event.target)) {
                suggestList.hidden = true;
            }
        });

        renderSelected();
    }

    document.querySelectorAll('.participant-picker').forEach(initParticipantPicker);
});
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
