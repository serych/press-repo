<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/users.php';

require_login();

if (!has_permission('users.manage')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$roles = users_roles_list();

$values = [
    'jmeno'        => '',
    'prijmeni'     => '',
    'user'         => '',
    'password'     => '',
    'ftp_user'     => '',
    'ftp_password' => '',
    'homedir'      => '',
    'role_id'      => '',
    'mobile'       => '',
    'exif_author'  => '',
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
    } elseif (users_login_exists($values['user'])) {
        $errors[] = 'Tento login už existuje.';
    }

    if ($values['password'] === '') {
        $errors[] = 'Vyplň webové heslo.';
    }

    if ($values['ftp_user'] === '') {
        $errors[] = 'Vyplň FTP login.';
    } elseif (preg_match('/\s/', $values['ftp_user'])) {
        $errors[] = 'FTP login nesmí obsahovat mezery.';
    } elseif (users_ftp_login_exists($values['ftp_user'])) {
        $errors[] = 'Tento FTP login už existuje.';
    }

    if ($values['ftp_password'] === '') {
        $errors[] = 'Vyplň FTP heslo.';
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

    if (!$errors) {
        users_create([
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

        header('Location: /users.php');
        exit;
    }
}

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <div class="page-head">
        <h1>Nový uživatel</h1>
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
        <form method="post" class="form user-form" autocomplete="off" id="user-create-form">
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
                    <input
                        type="text"
                        name="user"
                        id="user"
                        value="<?= h($values['user']) ?>"
                        required
                        autocomplete="off"
                        autocapitalize="off"
                        spellcheck="false"
                        data-lpignore="true"
                        data-1p-ignore
                    >
                    <div class="field-feedback" id="user-login-feedback" aria-live="polite"></div>
                </div>

                <div>
                    <label for="password">Webové heslo</label>
                    <div class="password-wrap">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            value=""
                            required
                            autocomplete="new-password"
                            data-lpignore="true"
                            data-1p-ignore
                        >
                        <button type="button" class="password-toggle" data-target="password" aria-label="Zobrazit nebo skrýt heslo">👁</button>
                    </div>
                </div>

                <div>
                    <label for="ftp_user">FTP login</label>
                    <input type="text" name="ftp_user" id="ftp_user" value="<?= h($values['ftp_user']) ?>" required>
                </div>

                <div>
                    <label for="ftp_password">FTP heslo</label>
                    <div class="password-wrap">
                        <input
                            type="password"
                            name="ftp_password"
                            id="ftp_password"
                            value=""
                            required
                            autocomplete="new-password"
                            data-lpignore="true"
                            data-1p-ignore
                        >
                        <button type="button" class="password-toggle" data-target="ftp_password" aria-label="Zobrazit nebo skrýt heslo">👁</button>
                    </div>
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
                <button type="submit">Vytvořit uživatele</button>
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
    const mobileInput = document.getElementById('mobile');
    const loginFeedback = document.getElementById('user-login-feedback');
    let loginCheckTimer = null;
    let loginCheckController = null;

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

    function setLoginState(state, message) {
        loginInput.classList.remove('field-valid', 'field-invalid', 'field-checking');
        if (state) {
            loginInput.classList.add(state);
        }
        if (loginFeedback) {
            loginFeedback.textContent = message || '';
            loginFeedback.className = 'field-feedback';
            if (state === 'field-invalid') {
                loginFeedback.classList.add('field-feedback-error');
            } else if (state === 'field-valid') {
                loginFeedback.classList.add('field-feedback-ok');
            } else if (state === 'field-checking') {
                loginFeedback.classList.add('field-feedback-muted');
            }
        }
    }

    function checkLoginAvailability() {
        const login = loginInput.value.trim();

        if (login === '') {
            setLoginState('', '');
            return;
        }

        if (/\s/.test(login)) {
            setLoginState('field-invalid', 'Login nesmí obsahovat mezery.');
            return;
        }

        if (loginCheckController) {
            loginCheckController.abort();
        }

        loginCheckController = new AbortController();
        setLoginState('field-checking', 'Kontroluji login...');

        fetch('/api/user-login-check.php?login=' + encodeURIComponent(login), {
            cache: 'no-store',
            signal: loginCheckController.signal
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (data) {
                if (loginInput.value.trim() !== login) {
                    return;
                }

                if (data.exists) {
                    setLoginState('field-invalid', 'Tento login už existuje.');
                } else {
                    setLoginState('field-valid', 'Login je volný.');
                }
            })
            .catch(function (error) {
                if (error.name === 'AbortError') {
                    return;
                }
                setLoginState('', '');
            });
    }

    function scheduleLoginCheck() {
        window.clearTimeout(loginCheckTimer);
        loginCheckTimer = window.setTimeout(checkLoginAvailability, 250);
    }

    function formatMobile(value) {
        const trimmed = value.trim();
        if (trimmed === '') {
            return '';
        }

        const hasPlus = trimmed.startsWith('+');
        const digits = trimmed.replace(/\D/g, '');

        if (digits === '') {
            return hasPlus ? '+' : '';
        }

        if (hasPlus) {
            const prefixLength = digits.length > 9 ? digits.length - 9 : Math.min(3, digits.length);
            const prefix = digits.slice(0, prefixLength);
            const rest = digits.slice(prefixLength);
            const groups = rest.match(/.{1,3}/g) || [];
            return '+' + prefix + (groups.length ? ' ' + groups.join(' ') : '');
        }

        return (digits.match(/.{1,3}/g) || []).join(' ');
    }

    function syncMobileFormat() {
        if (!mobileInput) {
            return;
        }

        mobileInput.value = formatMobile(mobileInput.value);
    }

    function syncFtpPassword() {
        if (ftpPasswordInput.value === '' || ftpPasswordInput.dataset.autofill === '1') {
            ftpPasswordInput.value = passwordInput.value;
            ftpPasswordInput.dataset.autofill = '1';
        }
    }

    loginInput.addEventListener('input', function () {
        syncDefaults();
        scheduleLoginCheck();
    });
    passwordInput.addEventListener('input', syncFtpPassword);
    loginInput.addEventListener('blur', checkLoginAvailability);

    if (mobileInput) {
        mobileInput.addEventListener('input', syncMobileFormat);
        mobileInput.addEventListener('blur', syncMobileFormat);
        syncMobileFormat();
    }

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

    syncDefaults();
    syncFtpPassword();
});
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
