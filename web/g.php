<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/gallery_access.php';
require_once __DIR__ . '/inc/published_photos.php';

$token = gallery_access_public_url_token();
$access = gallery_access_find_by_token($token);
$unavailableReason = gallery_access_unavailable_reason($access);
$error = '';

if ($access && $unavailableReason === null) {
    if (empty($access['pin_hash'])) {
        gallery_access_start_public_session($access);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pin = trim((string)($_POST['pin'] ?? ''));
        if ($pin !== '' && password_verify($pin, (string)$access['pin_hash'])) {
            gallery_access_start_public_session($access);
            header('Location: /g/' . rawurlencode($token));
            exit;
        }
        $error = 'PIN není správný.';
    }
}

$isAllowed = $access && gallery_access_is_public_session_allowed($access);
$eventId = $isAllowed ? (int)$access['event_id'] : 0;
$photos = $eventId > 0 ? published_photos_list_ready($eventId) : [];
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
        <div class="brand">Press centrum</div>
    </div>
</header>

<main class="wrap public-gallery-wrap">
    <section class="panel">
        <div class="published-page-head">
            <h1>Galerie<?= $access && $title !== '' ? ' - ' . h($title) : '' ?></h1>

            <?php if ($isAllowed && !empty($access['event_description'])): ?>
                <div class="published-event-description">
                    <?= nl2br(h((string)$access['event_description'])) ?>
                </div>
            <?php endif; ?>

            <?php if ($isAllowed): ?>
                <p class="published-license-note">
                    Při použití fotografie uveďte jméno autora/Člověk a Víra, tak jak je uvedeno u každé fotografie.
                    <a href="https://www.clovekavira.cz/licencni-podminky" target="_blank" rel="noopener noreferrer">Licenční podmínky</a>
                </p>
            <?php endif; ?>
        </div>

        <?php if ($unavailableReason !== null): ?>
            <div class="alert-error"><?= h($unavailableReason) ?></div>
        <?php elseif (!$isAllowed): ?>
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
        <?php elseif (!$photos): ?>
            <p>Zatím nejsou publikované žádné fotografie.</p>
        <?php else: ?>
            <div class="published-toolbar">
                <label class="checkbox-line">
                    <input type="checkbox" id="published-select-all">
                    <span>Vybrat vše</span>
                </label>
                <button type="button" class="button" id="published-download-selected">Stáhnout vybrané</button>
                <span class="table-subtext" id="published-download-status"></span>
            </div>

            <div class="published-grid">
                <?php foreach ($photos as $photo): ?>
                    <?php
                    $photoId = (int)$photo['id'];
                    $downloadedInSession = published_photos_was_downloaded_in_session($photoId);
                    ?>
                    <article class="published-card<?= $downloadedInSession ? ' is-downloaded-session' : '' ?>">
                        <label class="published-select">
                            <input type="checkbox" class="published-checkbox" value="<?= $photoId ?>">
                        </label>

                        <a href="/g-photo.php?token=<?= h($token) ?>&amp;id=<?= $photoId ?>" class="published-thumb-link">
                            <img src="/g-preview.php?token=<?= h($token) ?>&amp;id=<?= $photoId ?>&amp;size=small" alt="<?= h((string)$photo['filename']) ?>" loading="lazy">
                        </a>

                        <div class="published-meta">
                            <div class="file"><?= h((string)$photo['filename']) ?></div>
                            <div class="author"><?= h(published_photos_author_label_for_photo($photo)) ?></div>
                            <div class="time">
                                Vyfoceno:
                                <?= !empty($photo['captured_at']) ? h((string)$photo['captured_at']) : '-' ?>
                            </div>
                            <div class="time">
                                Stažení celkem: <?= (int)($photo['download_count'] ?? 0) ?>
                                <?php if ($downloadedInSession): ?>
                                    <span class="status status-downloaded">staženo v této relaci</span>
                                <?php endif; ?>
                            </div>
                            <a href="/g-download.php?token=<?= h($token) ?>&amp;id=<?= $photoId ?>" class="button published-download-button">Stáhnout</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = Array.from(document.querySelectorAll('.published-checkbox'));
    const selectAll = document.getElementById('published-select-all');
    const selectedButton = document.getElementById('published-download-selected');
    const status = document.getElementById('published-download-status');
    const token = <?= json_encode($token) ?>;

    function selectedIds() {
        return checkboxes
            .filter(function (checkbox) { return checkbox.checked; })
            .map(function (checkbox) { return checkbox.value; });
    }

    function downloadIds(ids) {
        if (!ids.length) {
            if (status) {
                status.textContent = 'Vyber alespoň jednu fotku.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Spouštím stažení ' + ids.length + ' fotek...';
        }

        ids.forEach(function (id, index) {
            window.setTimeout(function () {
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = '/g-download.php?token=' + encodeURIComponent(token) + '&id=' + encodeURIComponent(id);
                document.body.appendChild(iframe);
            }, index * 450);
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });
    }

    if (selectedButton) {
        selectedButton.addEventListener('click', function () {
            downloadIds(selectedIds());
        });
    }
});
</script>
</body>
</html>
