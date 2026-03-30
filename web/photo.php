<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/photos.php';

require_login();

if (!has_permission('photos.view')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(404);
    exit('Fotografie nebyla nalezena.');
}

$photo = photos_get_by_id($id);

if (!$photo) {
    http_response_code(404);
    exit('Fotografie nebyla nalezena.');
}

require_once __DIR__ . '/inc/header.php';
?>

<section class="photo-detail">
    <div class="photo-detail-top">
        <h1>Detail fotografie</h1>
        <p><a href="/photos.php" class="back-link">← Zpět na přehled fotografií</a></p>
    </div>

    <div class="photo-detail-grid">
        <div class="photo-preview-card">
            <?php if (!empty($photo['preview_filepath'])): ?>
                <img
                    src="/preview.php?id=<?= (int)$photo['id'] ?>"
                    alt="<?= h((string)$photo['filename']) ?>"
                    class="photo-detail-image"
                >
            <?php else: ?>
                <div class="no-preview large">Náhled není k dispozici</div>
            <?php endif; ?>
        </div>

        <div class="photo-info-card">
            <h2>Metadata</h2>

            <table class="detail-table">
                <tr>
                    <th>ID</th>
                    <td><?= (int)$photo['id'] ?></td>
                </tr>
                <tr>
                    <th>Soubor</th>
                    <td><?= h((string)$photo['filename']) ?></td>
                </tr>
                <tr>
                    <th>Typ</th>
                    <td><?= h((string)$photo['filetype']) ?></td>
                </tr>
                <tr>
                    <th>Stav</th>
                    <td>
                        <span class="status status-<?= h((string)$photo['status']) ?>">
                            <?= h((string)$photo['status']) ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Fotograf (FTP)</th>
                    <td><?= h((string)$photo['ftp_user']) ?></td>
                </tr>
                <tr>
                    <th>Uživatel</th>
                    <td>
                        <?php
                        $fullName = trim(((string)($photo['jmeno'] ?? '')) . ' ' . ((string)($photo['prijmeni'] ?? '')));
                        if ($fullName !== '') {
                            echo h($fullName);
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Web login</th>
                    <td><?= !empty($photo['web_user']) ? h((string)$photo['web_user']) : '—' ?></td>
                </tr>
                <tr>
                    <th>Velikost souboru</th>
                    <td>
                        <?php if (!empty($photo['filesize'])): ?>
                            <?= h(number_format((int)$photo['filesize'], 0, ',', ' ')) ?> B
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Rozlišení preview</th>
                    <td>
                        <?php if (!empty($photo['width']) && !empty($photo['height'])): ?>
                            <?= (int)$photo['width'] ?> × <?= (int)$photo['height'] ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Originál</th>
                    <td class="path-cell"><?= h((string)$photo['filepath']) ?></td>
                </tr>
                <tr>
                    <th>Preview</th>
                    <td class="path-cell">
                        <?= !empty($photo['preview_filepath']) ? h((string)$photo['preview_filepath']) : '—' ?>
                    </td>
                </tr>
                <tr>
                    <th>Nahráno</th>
                    <td><?= h((string)$photo['uploaded_at']) ?></td>
                </tr>
                <tr>
                    <th>Zpracováno</th>
                    <td><?= !empty($photo['processed_at']) ? h((string)$photo['processed_at']) : '—' ?></td>
                </tr>
                <tr>
                    <th>Vybráno</th>
                    <td><?= !empty($photo['selected_at']) ? h((string)$photo['selected_at']) : '—' ?></td>
                </tr>
                <tr>
                    <th>Checksum</th>
                    <td class="path-cell"><?= !empty($photo['checksum']) ? h((string)$photo['checksum']) : '—' ?></td>
                </tr>
            </table>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>