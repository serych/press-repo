<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$user = current_user();
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="wrap header-row">
        <div class="brand">
            <a href="/" class="brand-link">
                <img src="/assets/logo-cs.svg" alt="PRESS centrum Člověk a Víra" class="brand-logo">
                <span class="brand-text">PRESScentrum ČaV</span>
            </a>
        </div>

        <?php if ($user): ?>
            <button class="nav-toggle" type="button" aria-label="Otevřít menu" aria-expanded="false" aria-controls="top-nav">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="top-nav" id="top-nav">
                <a href="/dashboard.php">Dashboard</a>
                <a href="/photos.php">Fotografie</a>
                <a href="/photos-status.php">Moje fotky</a>

                <?php if (has_permission('users.manage')): ?>
                    <a href="/users.php">Uživatelé</a>
                <?php endif; ?>

                <a href="/logout.php">Odhlásit</a>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="wrap">

<?php if ($user): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.querySelector('.nav-toggle');
    const nav = document.getElementById('top-nav');

    if (!btn || !nav) {
        return;
    }

    btn.addEventListener('click', function () {
        const isOpen = nav.classList.toggle('is-open');
        btn.classList.toggle('is-open', isOpen);
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
});
</script>
<?php endif; ?>