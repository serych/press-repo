<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/users.php';

require_login();

if (!has_permission('users.manage')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$users = events_users_for_picker();

$values = [
    'title'           => '',
    'slug'            => '',
    'description'     => '',
    'starts_at'       => '',
    'ends_at'         => '',
    'cav_gallery_url' => '',
    'cloud_url'       => '',
    'leader_user_id'  => '',
    'status'          => 'planned',
    'is_public'       => '0',
];

$selectedPhotographers = [];
$selectedEditors = [];
$selectedRunnerUserId = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['title']           = trim((string)($_POST['title'] ?? ''));
    $values['slug']            = trim((string)($_POST['slug'] ?? ''));
    $values['description']     = trim((string)($_POST['description'] ?? ''));
    $values['starts_at']       = trim((string)($_POST['starts_at'] ?? ''));
    $values['ends_at']         = trim((string)($_POST['ends_at'] ?? ''));
    $values['cav_gallery_url'] = trim((string)($_POST['cav_gallery_url'] ?? ''));
    $values['cloud_url']       = trim((string)($_POST['cloud_url'] ?? ''));
    $values['leader_user_id']  = trim((string)($_POST['leader_user_id'] ?? ''));
    $values['status']          = trim((string)($_POST['status'] ?? 'planned'));
    $values['is_public']       = !empty($_POST['is_public']) ? '1' : '0';

    $selectedPhotographers = array_values(array_unique(array_map('intval', $_POST['photographers'] ?? [])));
    $selectedEditors = array_values(array_unique(array_map('intval', $_POST['editors'] ?? [])));
    $selectedRunnerUserId = max(0, (int)($_POST['runner_user_id'] ?? 0));

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
    } elseif (events_slug_exists($values['slug'])) {
        $errors[] = 'Tento slug už existuje.';
    }

    if (!in_array($values['status'], ['planned', 'active', 'finished'], true)) {
        $errors[] = 'Neplatný stav eventu.';
    }

    if ($values['status'] === 'active' && events_other_active_regular_exists()) {
        $errors[] = 'Už existuje jiný aktivní běžný event. Nejprve ho ukonči nebo přepni.';
    }

    if ($values['starts_at'] !== '' && strtotime($values['starts_at']) === false) {
        $errors[] = 'Neplatný datum/čas začátku.';
    }

    if ($values['ends_at'] !== '' && strtotime($values['ends_at']) === false) {
        $errors[] = 'Neplatný datum/čas konce.';
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

    if ($selectedRunnerUserId > 0 && !in_array($selectedRunnerUserId, $selectedPhotographers, true)) {
        $errors[] = 'Runner musí být vybraný i mezi fotografy.';
    }

    if (!$errors) {
        $eventId = events_create([
            'title'           => $values['title'],
            'slug'            => $values['slug'],
            'description'     => $values['description'],
            'starts_at'       => $values['starts_at'],
            'ends_at'         => $values['ends_at'],
            'cav_gallery_url' => $values['cav_gallery_url'],
            'cloud_url'       => $values['cloud_url'],
            'leader_user_id'  => $leaderUserId > 0 ? $leaderUserId : null,
            'status'          => $values['status'],
            'is_public'       => $values['is_public'] === '1' ? 1 : 0,
            'is_temporary'    => 0,
            'created_by'      => (int)(current_user()['id'] ?? 0),
        ]);

        events_participants_save(
            $eventId,
            $selectedPhotographers,
            $selectedEditors,
            $selectedRunnerUserId > 0 ? $selectedRunnerUserId : null
        );

        header('Location: /events.php');
        exit;
    }
}

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <div class="page-head">
        <h1>Nový event</h1>
        <a href="/events.php" class="button">Zpět na eventy</a>
    </div>

    <?php if ($errors): ?>
        <div class="alert-error">
            <?php foreach ($errors as $error): ?>
                <div><?= h($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="post" class="form event-form" autocomplete="off">
            <div class="form-grid">
                <div>
                    <label for="title">Název eventu</label>
                    <input type="text" name="title" id="title" value="<?= h($values['title']) ?>" required>
                </div>

                <div>
                    <label for="slug">Slug</label>
                    <input type="text" name="slug" id="slug" value="<?= h($values['slug']) ?>">
                </div>

                <div>
                    <label for="starts_at">Začátek</label>
                    <input type="datetime-local" name="starts_at" id="starts_at" value="<?= h($values['starts_at']) ?>">
                </div>

                <div>
                    <label for="ends_at">Konec</label>
                    <input type="datetime-local" name="ends_at" id="ends_at" value="<?= h($values['ends_at']) ?>">
                </div>

                <div>
                    <label for="leader_user_id">Vedoucí eventu</label>
                    <select name="leader_user_id" id="leader_user_id">
                        <option value="">-- bez vedoucího --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= (string)$u['id'] === $values['leader_user_id'] ? 'selected' : '' ?>>
                                <?= h(trim((string)$u['jmeno'] . ' ' . (string)$u['prijmeni'])) ?>
                                <?php if (!empty($u['mobile'])): ?>
                                    (<?= h(users_format_mobile((string)$u['mobile'])) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="status">Stav</label>
                    <select name="status" id="status" required>
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
                    <label for="cloud_url">URL cloudového disku</label>
                    <input type="url" name="cloud_url" id="cloud_url" value="<?= h($values['cloud_url']) ?>">
                </div>

                <div class="form-grid-span-2">
                    <label for="description">Popis / poznámka</label>
                    <textarea name="description" id="description" rows="4"><?= h($values['description']) ?></textarea>
                </div>

                <div class="form-grid-span-2">
                    <label class="checkbox-line">
                        <input type="checkbox" name="is_public" value="1" <?= $values['is_public'] === '1' ? 'checked' : '' ?>>
                        <span>Zobrazit event i na veřejném dashboardu</span>
                    </label>
                </div>
            </div>

            <div class="participant-pickers">
                <div class="card participant-card">
                    <h2>Fotografové</h2>

                    <div class="participant-list">
                        <?php foreach ($users as $u): ?>
                            <?php
                            $fullName = trim((string)$u['jmeno'] . ' ' . (string)$u['prijmeni']);
                            $userId = (int)$u['id'];
                            $checked = in_array($userId, $selectedPhotographers, true);
                            $isRunner = $selectedRunnerUserId === $userId;
                            ?>
                            <label class="participant-item participant-item-runner">
                                <span class="participant-main">
                                    <input type="checkbox" name="photographers[]" value="<?= $userId ?>" <?= $checked ? 'checked' : '' ?>>
                                    <span>
                                        <strong><?= h($fullName !== '' ? $fullName : (string)$u['user']) ?></strong>
                                        <small>
                                            <?= h((string)$u['role_name']) ?>
                                            <?php if (!empty($u['mobile'])): ?> · <?= h(users_format_mobile((string)$u['mobile'])) ?><?php endif; ?>
                                        </small>
                                    </span>
                                </span>

                                <span class="participant-runner-pick">
                                    <input type="radio" name="runner_user_id" value="<?= $userId ?>" <?= $isRunner ? 'checked' : '' ?>>
                                    <span>runner</span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card participant-card">
                    <h2>Redaktoři</h2>

                    <div class="participant-list">
                        <?php foreach ($users as $u): ?>
                            <?php
                            $fullName = trim((string)$u['jmeno'] . ' ' . (string)$u['prijmeni']);
                            $checked = in_array((int)$u['id'], $selectedEditors, true);
                            ?>
                            <label class="participant-item">
                                <input type="checkbox" name="editors[]" value="<?= (int)$u['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                                <span>
                                    <strong><?= h($fullName !== '' ? $fullName : (string)$u['user']) ?></strong>
                                    <small>
                                        <?= h((string)$u['role_name']) ?>
                                        <?php if (!empty($u['mobile'])): ?> · <?= h(users_format_mobile((string)$u['mobile'])) ?><?php endif; ?>
                                    </small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit">Vytvořit event</button>
            </div>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');

    function slugify(value) {
        return value
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

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

    document.querySelectorAll('.participant-item-runner').forEach(function (item) {
        const photographerCheckbox = item.querySelector('input[type="checkbox"][name="photographers[]"]');
        const runnerRadio = item.querySelector('input[type="radio"][name="runner_user_id"]');

        if (!photographerCheckbox || !runnerRadio) {
            return;
        }

        photographerCheckbox.addEventListener('change', function () {
            if (!photographerCheckbox.checked && runnerRadio.checked) {
                runnerRadio.checked = false;
            }
        });

        runnerRadio.addEventListener('change', function () {
            if (runnerRadio.checked) {
                photographerCheckbox.checked = true;
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>