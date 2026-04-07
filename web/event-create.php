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

$allUsers = events_users_for_picker();
$editorUsers = events_users_for_picker('editor');

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
$selectedRunnerUserIds = [];
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

    $invalidRunnerIds = array_values(array_filter(
        $selectedRunnerUserIds,
        static fn(int $userId): bool => !in_array($userId, $selectedPhotographers, true)
    ));

    if ($invalidRunnerIds !== []) {
        $errors[] = 'Runner musí být vybraný i mezi fotografy.';
    }

    if (events_filter_editor_ids($selectedEditors) !== $selectedEditors) {
        $errors[] = 'Redaktorem se nesmí stát fotograf.';
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
            $selectedRunnerUserIds
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
                        <?php foreach ($allUsers as $u): ?>
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

            <?php
            $pickerAllUsersJson = json_encode($allUsers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $pickerEditorUsersJson = json_encode($editorUsers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $selectedPhotographersJson = json_encode($selectedPhotographers);
            $selectedEditorsJson = json_encode($selectedEditors);
            $selectedRunnerUserIdsJson = json_encode($selectedRunnerUserIds);
            ?>

            <div class="participant-pickers participant-pickers-enhanced">
                <div class="card participant-card participant-card-full">
                    <h2>Redaktoři</h2>
                    <div
                        id="editor-picker"
                        class="participant-picker participant-picker-table"
                        data-users='<?= h((string)$pickerEditorUsersJson) ?>'
                        data-selected='<?= h((string)$selectedEditorsJson) ?>'
                        data-hidden-name="editors[]"
                        data-placeholder="Začni psát příjmení, jméno nebo login…"
                        data-empty-text="Zatím není vybraný žádný redaktor."
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

    function normalizeText(value) {
        return (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
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

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

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