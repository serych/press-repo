<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/gallery_access.php';

$token = gallery_access_public_url_token();
$access = gallery_access_find_by_token($token);
$unavailableReason = gallery_access_unavailable_reason($access);
$error = '';

if ($access && $unavailableReason === null) {
    if (empty($access['pin_hash'])) {
        gallery_access_start_public_session($access);
        redirect('/galerie.php');
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pin = trim((string)($_POST['pin'] ?? ''));
        if ($pin !== '' && password_verify($pin, (string)$access['pin_hash'])) {
            gallery_access_start_public_session($access);
            redirect('/galerie.php');
        }
        $error = 'PIN není správný.';
    }
}

$title = $access ? (string)$access['event_title'] : 'Galerie';
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> | Press centrum</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="public-gallery-page">
<header class="site-header">
    <div class="wrap">
        <div class="brand">
            <a href="/" class="brand-link">
                <img src="/assets/logo-cs.svg" alt="PRESS centrum Člověk a Víra" class="brand-logo">
                <span class="brand-text">PRESS centrum ČaV</span>
            </a>
        </div>
    </div>
</header>

<main class="wrap public-gallery-wrap">
    <section class="panel">
        <div class="published-page-head">
            <h1>Galerie<?= $access && $title !== '' ? ' - ' . h($title) : '' ?></h1>
        </div>

        <?php if ($unavailableReason !== null): ?>
            <div class="alert-error"><?= h($unavailableReason) ?></div>
        <?php else: ?>
            <div class="login-box public-gallery-login">
                <h2>Vstup do galerie</h2>
                <?php if ($error !== ''): ?>
                    <div class="alert-error"><?= h($error) ?></div>
                <?php endif; ?>
                <form method="post" class="form" autocomplete="off">
                    <label for="pin">PIN / heslo</label>
                    <input type="password" name="pin" id="pin" inputmode="numeric" autocomplete="one-time-code" autofocus>
                    <button type="submit">Otevřít galerii</button>
                </form>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
