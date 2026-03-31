<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/auth.php';

start_session_if_needed();

if (is_logged_in()) {
    redirect(default_homepage_for_user(current_user()));
}

$error = '';

if (is_post()) {
    $username = post('username');
    $password = post('password');

    if ($username === '' || $password === '') {
        $error = 'Vyplňte uživatelské jméno i heslo.';
    } elseif (!login_user($username, $password)) {
        $error = 'Neplatné přihlašovací údaje.';
    } else {
        redirect(default_homepage_for_user(current_user()));
    }
}

require_once __DIR__ . '/inc/header.php';
?>

<section class="login-box">
    <h1>Přihlášení</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login.php" class="form">
        <label for="username">Uživatel</label>
        <input type="text" name="username" id="username" required autofocus>

        <label for="password">Heslo</label>
        <input type="password" name="password" id="password" required>

        <button type="submit">Přihlásit</button>
    </form>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>