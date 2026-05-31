<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/users.php';
require_once __DIR__ . '/inc/client_upload_tokens.php';

require_login();

if (!has_permission('users.manage')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$currentUser = current_user();
$currentUserId = (int)$currentUser['id'];

$id = max(1, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
$targetUser = users_get($id);

if (!$targetUser) {
    http_response_code(404);
    exit('Uživatel nebyl nalezen.');
}

$currentIsSuperadmin = (($currentUser['role_code'] ?? '') === 'superadmin');
$targetIsSuperadmin = (($targetUser['role_code'] ?? '') === 'superadmin');

if ($targetIsSuperadmin && !$currentIsSuperadmin) {
    http_response_code(403);
    exit('Tento účet nemůžeš upravovat.');
}

$roles = users_roles_list();

$values = [
    'jmeno'        => (string)$targetUser['jmeno'],
    'prijmeni'     => (string)$targetUser['prijmeni'],
    'user'         => (string)$targetUser['user'],
    'password'     => '',
    'ftp_user'     => (string)$targetUser['ftp_user'],
    'ftp_password' => '',
    'homedir'      => (string)$targetUser['homedir'],
    'role_id'      => (string)$targetUser['role_id'],
    'mobile'       => (string)($targetUser['mobile'] ?? ''),
    'exif_author'  => (string)($targetUser['exif_author'] ?? ''),
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values['jmeno']        = trim((string)($_POST['jmeno'] ?? ''));
    $values['prijmeni']     = trim((string)($_POST['prijmeni'] ?? ''));
    $values['user']         = trim((string)($_POST['user'] ?? ''));
    $values['password']     = (string)($_POST['password'] ?? '');
    $values['ftp_user']     = trim((string)($_POST['ftp_user'] ?? ''));
    $values['ftp_password'] = (string)($_POST['ftp_password'] ?? '');
    $values['homedir']      = trim((string)($_POST['homedir'] ?? ''));
    $values['role_id']      = (string)($_POST['role_id'] ?? '');
    $values['mobile']       = trim((string)($_POST['mobile'] ?? ''));
    $values['exif_author']  = trim((string)($_POST['exif_author'] ?? ''));

    if ($values['jmeno'] === '') {
        $errors[] = 'Vyplň jméno.';
    }

    if ($values['prijmeni'] === '') {
        $errors[] = 'Vyplň příjmení.';
    }

    if ($values['user'] === '') {
        $errors[] = 'Vyplň login.';
    } elseif (preg_match('/\s/', $values['user'])) {
        $errors[] = 'Login nesmí obsahovat mezery.';
    } elseif (users_login_exists($values['user'], $id)) {
        $errors[] = 'Tento login už existuje.';
    }

    if ($values['ftp_user'] === '') {
        $errors[] = 'Vyplň FTP login.';
    } elseif (preg_match('/\s/', $values['ftp_user'])) {
        $errors[] = 'FTP login nesmí obsahovat mezery.';
    } elseif (users_ftp_login_exists($values['ftp_user'], $id)) {
        $errors[] = 'Tento FTP login už existuje.';
    }

    if ($values['homedir'] === '') {
        $errors[] = 'Vyplň home directory.';
    }

    $roleId = (int)$values['role_id'];
    if ($roleId <= 0) {
        $errors[] = 'Vyber roli.';
    } elseif (!users_role_allowed_for_current_user($roleId)) {
        $errors[] = 'Tuto roli nemůžeš přiřadit.';
    }

    $selectedRoleCode = '';
    foreach ($roles as $role) {
        if ((int)$role['id'] === $roleId) {
            $selectedRoleCode = (string)$role['code'];
            break;
        }
    }

    if ($targetIsSuperadmin && $selectedRoleCode !== 'superadmin') {
        if (users_count_superadmins() <= 1) {
            $errors[] = 'Nelze změnit roli posledního superadmina.';
        }
    }

    if (!$errors) {
        users_update($id, [
            'jmeno'        => $values['jmeno'],
            'prijmeni'     => $values['prijmeni'],
            'user'         => $values['user'],
            'password'     => $values['password'],
            'ftp_user'     => $values['ftp_user'],
            'ftp_password' => $values['ftp_password'],
            'homedir'      => $values['homedir'],
            'role_id'      => $roleId,
            'mobile'       => $values['mobile'],
            'exif_author'  => $values['exif_author'],
        ]);

        if (!client_upload_tokens_role_can_upload_published($roleId)) {
            client_upload_tokens_revoke_all_for_user($id, $currentUserId);
        }

        header('Location: /users.php');
        exit;
    }
}

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <div class="page-head">
        <h1>Upravit uživatele</h1>
        <a href="/users.php" class="button">Zpět na uživatele</a>
    </div>

    <?php if ($errors): ?>
        <div class="alert-error">
            <?php foreach ($errors as $error): ?>
                <div><?= h($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="post" class="form user-form" autocomplete="off">
            <input type="hidden" name="id" value="<?= (int)$id ?>">

            <div class="form-grid">
                <div>
                    <label for="jmeno">Jméno</label>
                    <input type="text" name="jmeno" id="jmeno" value="<?= h($values['jmeno']) ?>" required>
                </div>

                <div>
                    <label for="prijmeni">Příjmení</label>
                    <input type="text" name="prijmeni" id="prijmeni" value="<?= h($values['prijmeni']) ?>" required>
                </div>

                <div>
                    <label for="user">Login</label>
                    <input type="text" name="user" id="user" value="<?= h($values['user']) ?>" required>
                </div>

                <div>
                    <label for="password">Nové webové heslo</label>
                    <div class="password-wrap">
                        <input type="password" name="password" id="password" value="<?= h($values['password']) ?>">
                        <button type="button" class="password-toggle" data-target="password" aria-label="Zobrazit nebo skrýt heslo">👁</button>
                    </div>
                    <small class="form-help">Nech prázdné, pokud nechceš měnit.</small>
                </div>

                <div>
                    <label for="ftp_user">FTP login</label>
                    <input type="text" name="ftp_user" id="ftp_user" value="<?= h($values['ftp_user']) ?>" required>
                </div>

                <div>
                    <label for="ftp_password">Nové FTP heslo</label>
                    <div class="password-wrap">
                        <input type="password" name="ftp_password" id="ftp_password" value="<?= h($values['ftp_password']) ?>">
                        <button type="button" class="password-toggle" data-target="ftp_password" aria-label="Zobrazit nebo skrýt heslo">👁</button>
                    </div>
                    <small class="form-help">Nech prázdné, pokud nechceš měnit.</small>
                </div>

                <div>
                    <label for="homedir">Home directory</label>
                    <input type="text" name="homedir" id="homedir" value="<?= h($values['homedir']) ?>" required>
                </div>

                <div>
                    <label for="role_id">Role</label>
                    <select name="role_id" id="role_id" required>
                        <option value="">-- vyber roli --</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int)$role['id'] ?>"
                                <?= (string)$role['id'] === $values['role_id'] ? 'selected' : '' ?>>
                                <?= h((string)$role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid-span-2">
                    <label for="mobile">Mobilní číslo</label>
                    <input
                        type="text"
                        name="mobile"
                        id="mobile"
                        value="<?= h(users_format_mobile($values['mobile'])) ?>"
                        placeholder="+420 123 456 789"
                        inputmode="tel"
                        autocomplete="tel"
                    >
                </div>

                <div class="form-grid-span-2">
                    <label for="exif_author">
                        EXIF - pole author
                        <span class="help-tip" title="Vyplňte autora přesně v tom tvaru, jak ho mají nastavené fotky ve vašem foťáku.">?</span>
                    </label>
                    <input
                        type="text"
                        name="exif_author"
                        id="exif_author"
                        value="<?= h($values['exif_author']) ?>"
                    >
                </div>
            </div>

            <div class="form-actions">
                <button type="submit">Uložit změny</button>
            </div>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const loginInput = document.getElementById('user');
    const passwordInput = document.getElementById('password');
    const ftpLoginInput = document.getElementById('ftp_user');
    const ftpPasswordInput = document.getElementById('ftp_password');
    const homedirInput = document.getElementById('homedir');

    function slugify(value) {
        return value.trim().replace(/\s+/g, '');
    }

    function syncDefaults() {
        const login = slugify(loginInput.value);

        if (ftpLoginInput.value.trim() === '' || ftpLoginInput.dataset.autofill === '1') {
            ftpLoginInput.value = login;
            ftpLoginInput.dataset.autofill = '1';
        }

        if (homedirInput.value.trim() === '' || homedirInput.dataset.autofill === '1') {
            homedirInput.value = login ? '/var/www/press/ftp/' + login : '';
            homedirInput.dataset.autofill = '1';
        }
    }

    function syncFtpPassword() {
        if (ftpPasswordInput.value === '' || ftpPasswordInput.dataset.autofill === '1') {
            ftpPasswordInput.value = passwordInput.value;
            ftpPasswordInput.dataset.autofill = '1';
        }
    }

    loginInput.addEventListener('input', syncDefaults);
    passwordInput.addEventListener('input', syncFtpPassword);

    ftpLoginInput.addEventListener('input', function () {
        ftpLoginInput.dataset.autofill = '0';
    });

    homedirInput.addEventListener('input', function () {
        homedirInput.dataset.autofill = '0';
    });

    ftpPasswordInput.addEventListener('input', function () {
        ftpPasswordInput.dataset.autofill = '0';
    });

    document.querySelectorAll('.password-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetId = btn.dataset.target;
            const input = document.getElementById(targetId);

            if (!input) {
                return;
            }

            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🙈';
            } else {
                input.type = 'password';
                btn.textContent = '👁';
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
