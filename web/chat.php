<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/photos.php';

require_login();

$eventId = max(0, (int)($_GET['event_id'] ?? 0));
$showHistory = !empty($_GET['history']) ? 1 : 0;
$currentEvent = null;

if ($eventId > 0) {
    $currentEvent = photos_get_current_event();

    if (!$currentEvent || (int)($currentEvent['id'] ?? 0) !== $eventId) {
        $currentEvent = [
            'id' => $eventId,
            'title' => 'Chat eventu #' . $eventId,
        ];
    }
} else {
    $currentEvent = photos_get_current_event();
    $eventId = !empty($currentEvent['id']) ? (int)$currentEvent['id'] : 0;
}

$chatJsFile = __DIR__ . '/assets/chat.js';
$chatJsVersion = is_file($chatJsFile) ? (string)filemtime($chatJsFile) : '1';

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <div class="page-head">
        <h1>Chat</h1>
    </div>

    <?php if ($eventId <= 0): ?>
        <div class="card">
            <p>Momentálně není aktivní žádný event pro chat.</p>
        </div>
    <?php else: ?>
        <div
            class="card event-chat-page"
            data-chat-event-id="<?= (int)$eventId ?>"
            data-chat-history="<?= $showHistory ? '1' : '0' ?>"
            id="event-chat-root"
        >
            <div class="event-chat-head">
                <strong><?= h((string)($currentEvent['title'] ?? ('Event #' . $eventId))) ?></strong>
            </div>

            <div class="event-chat-toolbar mobile-only">
                <?php if ($showHistory): ?>
                    <a
                        href="/chat.php?event_id=<?= (int)$eventId ?>"
                        class="button button-secondary event-chat-history-link"
                    >
                        Sbalit historii
                    </a>
                <?php else: ?>
                    <a
                        href="/chat.php?event_id=<?= (int)$eventId ?>&history=1"
                        class="button button-secondary event-chat-history-link"
                    >
                        Historie chatu
                    </a>
                <?php endif; ?>
            </div>

            <div class="event-chat-messages" id="event-chat-messages"></div>

            <form class="event-chat-form" id="event-chat-form">
                <textarea id="event-chat-input" placeholder="Napiš zprávu..." maxlength="2000"></textarea>
                <button type="submit">Odeslat</button>
            </form>
        </div>

        <script src="/assets/chat.js?v=<?= h($chatJsVersion) ?>"></script>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const isMobile = window.matchMedia('(max-width: 900px)').matches;
            const root = document.getElementById('event-chat-root');
            const form = document.getElementById('event-chat-form');

            if (!isMobile || !root || !form) {
                return;
            }

            setTimeout(function () {
                form.scrollIntoView({
                    behavior: 'smooth',
                    block: 'end'
                });
            }, 250);
        });
        </script>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>