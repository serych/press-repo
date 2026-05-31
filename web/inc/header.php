<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/photos.php';
require_once __DIR__ . '/gallery_access.php';
require_once __DIR__ . '/pfsense.php';

$user = current_user();
$publicGalleryAccess = gallery_access_current_public_access();
$currentEvent = null;
$currentEventId = 0;
$roleCode = '';
$dashboardUrl = '/dashboard.php';
$showPhotographerOverview = false;
$showPhotoEditing = false;
$showFtpReplacement = false;
$showAdminItems = false;
$showHelp = false;
$showChat = false;
$displayName = '';
$currentPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
$pressCenterFtpDisabled = false;

if ($user) {
    $currentEvent = photos_get_current_event();
    $currentEventId = !empty($currentEvent['id']) ? (int)$currentEvent['id'] : 0;
    $roleCode = (string)($user['role_code'] ?? '');
    $dashboardUrl = $roleCode === 'journalist' ? '/info.php' : '/dashboard.php';
    $showPhotographerOverview = $roleCode !== 'journalist';
    $showPhotoEditing = in_array($roleCode, ['press_operator', 'admin', 'superadmin'], true);
    $showFtpReplacement = $roleCode !== 'journalist';
    $showAdminItems = in_array($roleCode, ['admin', 'superadmin'], true);
    $showHelp = $roleCode !== 'journalist';
    $showChat = can_access_chat($user);
    $displayName = trim((string)($user['jmeno'] ?? '') . ' ' . (string)($user['prijmeni'] ?? ''));

    if ($displayName === '') {
        $displayName = (string)($user['user'] ?? '');
    }
} elseif ($publicGalleryAccess) {
    $currentEvent = events_get((int)$publicGalleryAccess['event_id']);
    $currentEventId = !empty($currentEvent['id']) ? (int)$currentEvent['id'] : 0;
    $roleCode = 'journalist';
    $dashboardUrl = '/info.php';
    $displayName = 'Žurnalista';
}

if ($currentPath === '') {
    $currentPath = (string)($_SERVER['SCRIPT_NAME'] ?? '');
}

if ($user || $publicGalleryAccess) {
    if (isset($pfsenseFtpStatus) && is_array($pfsenseFtpStatus)) {
        $pressCenterFtpDisabled = (string)($pfsenseFtpStatus['state'] ?? '') === 'disabled';
    } elseif (pfsense_is_configured()) {
        $pressCenterFtpDisabled = (string)(pfsense_ftp_status()['state'] ?? '') === 'disabled';
    }
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
<header class="site-header<?= $pressCenterFtpDisabled ? ' is-ftp-disabled' : '' ?>">
    <div class="wrap header-row">
        <div class="brand">
            <a href="/" class="brand-link">
                <img src="/assets/logo-cs.svg" alt="PRESS centrum Člověk a Víra" class="brand-logo">
                <span class="brand-text"><?= $pressCenterFtpDisabled ? 'PRESS centrum vypnuto' : 'PRESS centrum ČaV' ?></span>
            </a>

            <?php if ($user && $showChat): ?>
                <a href="<?= h($chatUrl) ?>" class="header-chat-indicator" aria-label="Otevřít chat">
                    <span class="header-chat-icon" aria-hidden="true">💬</span>
                    <span
                        class="header-chat-badge"
                        id="chat-unread-badge"
                        data-chat-event-id="<?= (int)$currentEventId ?>"
                        hidden
                    >0</span>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($user || $publicGalleryAccess): ?>
            <button class="nav-toggle" type="button" aria-label="Otevřít menu" aria-expanded="false" aria-controls="top-nav">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="top-nav" id="top-nav">
                <a href="<?= h($dashboardUrl) ?>" class="<?= $currentPath === $dashboardUrl ? 'is-active' : '' ?>">Dashboard</a>
                <a href="/galerie.php" class="<?= in_array($currentPath, ['/galerie.php', '/published-photo.php'], true) ? 'is-active' : '' ?>">Galerie</a>

                <?php if ($showPhotographerOverview): ?>
                    <a href="/photos-status.php" class="<?= $currentPath === '/photos-status.php' ? 'is-active' : '' ?>">Fotograf přehled</a>
                <?php endif; ?>

                <?php if ($showFtpReplacement): ?>
                    <a href="/ftp.php" class="<?= $currentPath === '/ftp.php' ? 'is-active' : '' ?>">NeFTP upload</a>
                <?php endif; ?>

                <?php if ($showPhotoEditing): ?>
                    <a href="/photos.php" class="<?= in_array($currentPath, ['/photos.php', '/photo.php'], true) ? 'is-active' : '' ?>">Foto editace</a>
                <?php endif; ?>

                <?php if ($showAdminItems): ?>
                    <a href="/users.php" class="<?= in_array($currentPath, ['/users.php', '/user-create.php', '/user-edit.php'], true) ? 'is-active' : '' ?>">Uživatelé</a>
                    <a href="/events.php" class="<?= in_array($currentPath, ['/events.php', '/event-create.php', '/event-edit.php'], true) ? 'is-active' : '' ?>">Eventy</a>
                <?php endif; ?>

                <?php if ($showHelp): ?>
                    <a href="/help.php" class="<?= in_array($currentPath, ['/help.php', '/help-download.php'], true) ? 'is-active' : '' ?>">Nápověda</a>
                <?php endif; ?>

                <?php if ($user): ?>
                    <a href="/logout.php" class="nav-logout">
                        Odhlásit
                        <?php if ($displayName !== ''): ?>
                            <span class="nav-logout-tooltip" role="tooltip"><?= h($displayName) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="wrap">

<?php if ($user || $publicGalleryAccess): ?>
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

    if (!chatBadge) {
        return;
    }

    async function refreshChatBadge() {
        const eventId = Number(chatBadge.dataset.chatEventId || 0);

        if (eventId <= 0) {
            chatBadge.textContent = '0';
            chatBadge.hidden = true;
            return;
        }

        if (document.visibilityState !== 'visible') {
            return;
        }

        try {
            const url = new URL('/api/chat-unread-count.php', window.location.origin);
            url.searchParams.set('event_id', String(eventId));

            const response = await fetch(url.toString(), {
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
