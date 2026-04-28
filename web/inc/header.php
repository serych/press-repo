<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/photos.php';

$user = current_user();
$currentEvent = null;
$currentEventId = 0;

if ($user) {
    $currentEvent = photos_get_current_event();
    $currentEventId = !empty($currentEvent['id']) ? (int)$currentEvent['id'] : 0;
}

$chatUrl = '/chat.php';
if ($currentEventId > 0) {
    $chatUrl = '/chat.php?event_id=' . $currentEventId;
}

$styleFile = __DIR__ . '/../assets/style.css';
$styleVersion = is_file($styleFile) ? (string)filemtime($styleFile) : '1';
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/style.css?v=<?= h($styleVersion) ?>">
</head>
<body>
<header class="site-header">
    <div class="wrap header-row">
        <div class="brand">
            <a href="/" class="brand-link">
                <img src="/assets/logo-cs.svg" alt="PRESS centrum Člověk a Víra" class="brand-logo">
                <span class="brand-text">PRESScentrum ČaV</span>
            </a>

            <?php if ($user): ?>
                <a href="<?= h($chatUrl) ?>" class="header-chat-indicator" aria-label="Otevřít chat">
                    <span class="header-chat-icon" aria-hidden="true">💬</span>
                    <span class="header-chat-badge" id="chat-unread-badge" hidden>0</span>
                </a>
            <?php endif; ?>
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
                <?php if (has_permission('published_photos.upload')): ?>
                    <a href="/published-upload.php">Hotové fotky</a>
                <?php endif; ?>
                <a href="/photos-status.php">Foto přehled</a>
                
                <?php if (has_permission('users.manage')): ?>
                    <a href="/users.php">Uživatelé</a>
                <?php endif; ?>

                <?php if (has_permission('users.manage') || has_permission('photos.select')): ?>
                    <a href="/events.php">Eventy</a>
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
    const chatBadge = document.getElementById('chat-unread-badge');

    if (btn && nav) {
        btn.addEventListener('click', function () {
            const isOpen = nav.classList.toggle('is-open');
            btn.classList.toggle('is-open', isOpen);
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    async function refreshChatBadge() {
        if (document.visibilityState !== 'visible') {
            return;
        }

        try {
            const response = await fetch('/api/chat-unread-count.php', {
                cache: 'no-store'
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            const total = Number(data.total || 0);

            if (chatBadge) {
                chatBadge.textContent = String(total);
                chatBadge.hidden = total <= 0;
            }
        } catch (e) {
            // ticho
        }
    }

    window.refreshChatBadge = refreshChatBadge;

    refreshChatBadge();

    window.setInterval(function () {
        refreshChatBadge();
    }, 10000);

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            refreshChatBadge();
        }
    });
});
</script>
<?php endif; ?>
