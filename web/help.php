<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/help.php';

require_login();

$user = current_user();
if (!help_can_view($user)) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$canManageHelp = help_can_manage($user);
$errors = [];
$flashMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManageHelp) {
        http_response_code(403);
        exit('Přístup odepřen.');
    }

    $action = (string)($_POST['action'] ?? '');
    $id = max(0, (int)($_POST['id'] ?? 0));

    try {
        if ($action === 'create') {
            help_create_document(
                (string)($_POST['title'] ?? ''),
                $_FILES['pdf'] ?? [],
                (int)($user['id'] ?? 0)
            );
            $flashMessage = 'Nápověda byla přidána.';
        } elseif ($action === 'rename') {
            help_update_title($id, (string)($_POST['title'] ?? ''));
            $flashMessage = 'Název byl uložen.';
        } elseif ($action === 'replace') {
            help_replace_document_file($id, $_FILES['pdf'] ?? [], (int)($user['id'] ?? 0));
            $flashMessage = 'PDF bylo nahrazeno.';
        } elseif ($action === 'delete') {
            help_delete_document($id);
            $flashMessage = 'Nápověda byla smazána.';
        } elseif ($action === 'move_up' || $action === 'move_down') {
            help_move_document($id, $action === 'move_up' ? 'up' : 'down');
            $flashMessage = 'Pořadí bylo změněno.';
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$documents = help_documents_list();

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <div class="page-head">
        <h1>Nápověda</h1>
    </div>

    <?php if ($flashMessage !== ''): ?>
        <div class="alert-success"><?= h($flashMessage) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert-error">
            <?php foreach ($errors as $error): ?>
                <div><?= h($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($canManageHelp): ?>
        <div class="card help-admin-card">
            <h2>Přidat PDF nápovědu</h2>
            <form method="post" enctype="multipart/form-data" class="form help-create-form">
                <input type="hidden" name="action" value="create">
                <div class="form-grid">
                    <div>
                        <label for="title">Název</label>
                        <input type="text" name="title" id="title" placeholder="Např. Nastavení foťáku">
                    </div>
                    <div>
                        <label for="pdf">PDF soubor</label>
                        <input type="file" name="pdf" id="pdf" accept="application/pdf,.pdf" required>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit">Přidat nápovědu</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="card">
        <?php if (!$documents): ?>
            <p>Zatím zde nejsou žádné dokumenty nápovědy.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="list-table help-table">
                    <thead>
                        <tr>
                            <th>Název</th>
                            <th>Velikost</th>
                            <th>Aktualizováno</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $index => $document): ?>
                            <?php
                            $id = (int)$document['id'];
                            $uploadedByName = trim(
                                ((string)($document['uploaded_jmeno'] ?? '')) . ' ' .
                                ((string)($document['uploaded_prijmeni'] ?? ''))
                            );
                            if ($uploadedByName === '') {
                                $uploadedByName = (string)($document['uploaded_by_user'] ?? '');
                            }
                            ?>
                            <tr>
                                <td>
                                    <div class="help-title-cell">
                                        <strong><?= h((string)$document['title']) ?></strong>
                                        <?php if ($uploadedByName !== ''): ?>
                                            <small>Nahrál: <?= h($uploadedByName) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= h(help_format_filesize(isset($document['filesize']) ? (int)$document['filesize'] : null)) ?></td>
                                <td><?= h((string)$document['updated_at']) ?></td>
                                <td>
                                    <div class="help-actions">
                                        <a class="button button-small" href="/help-download.php?id=<?= $id ?>">Stáhnout</a>

                                        <?php if ($canManageHelp): ?>
                                            <form method="post" class="help-inline-form">
                                                <input type="hidden" name="id" value="<?= $id ?>">
                                                <input type="hidden" name="action" value="move_up">
                                                <button type="submit" class="button button-muted button-small" <?= $index === 0 ? 'disabled' : '' ?>>Nahoru</button>
                                            </form>

                                            <form method="post" class="help-inline-form">
                                                <input type="hidden" name="id" value="<?= $id ?>">
                                                <input type="hidden" name="action" value="move_down">
                                                <button type="submit" class="button button-muted button-small" <?= $index === count($documents) - 1 ? 'disabled' : '' ?>>Dolů</button>
                                            </form>

                                            <form method="post" class="help-rename-form">
                                                <input type="hidden" name="id" value="<?= $id ?>">
                                                <input type="hidden" name="action" value="rename">
                                                <input type="text" name="title" value="<?= h((string)$document['title']) ?>" aria-label="Název dokumentu">
                                                <button type="submit" class="button button-muted button-small">Uložit název</button>
                                            </form>

                                            <form method="post" enctype="multipart/form-data" class="help-replace-form">
                                                <input type="hidden" name="id" value="<?= $id ?>">
                                                <input type="hidden" name="action" value="replace">
                                                <input type="file" name="pdf" accept="application/pdf,.pdf" required aria-label="Nahradit PDF">
                                                <button type="submit" class="button button-muted button-small">Nahradit</button>
                                            </form>

                                            <form method="post" class="help-inline-form" onsubmit="return window.confirm('Opravdu smazat tuto nápovědu?');">
                                                <input type="hidden" name="id" value="<?= $id ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn-danger button-small">Smazat</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
