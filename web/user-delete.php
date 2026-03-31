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

$currentUser = current_user();
$currentUserId = (int)$currentUser['id'];
$currentIsSuperadmin = (($currentUser['role_code'] ?? '') === 'superadmin');

$id = max(1, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
$targetUser = users_get($id);

if (!$targetUser) {
    http_response_code(404);
    exit('Uživatel nebyl nalezen.');
}

$targetIsSuperadmin = (($targetUser['role_code'] ?? '') === 'superadmin');
$errors = [];

if ((int)$targetUser['id'] === $currentUserId) {
    $errors[] = 'Nelze smazat vlastní účet.';
}

if ($targetIsSuperadmin && !$currentIsSuperadmin) {
    $errors[] = 'Tento účet nemůžeš smazat.';
}

if ($targetIsSuperadmin && users_count_superadmins() <= 1) {
    $errors[] = 'Nelze smazat posledního superadmina.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {
    users_delete((int)$targetUser['id']);
    header('Location: /users.php');
    exit;
}

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <div class="page-head">
        <h1>Smazat uživatele</h1>
        <a href="/users.php" class="button">Zpět na uživatele</a>
    </div>

    <?php if ($errors): ?>
        <div class="alert-error">
            <?php foreach ($errors as $error): ?>
                <div><?= h($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card">
            <p>Opravdu chceš smazat tohoto uživatele?</p>

            <table class="detail-table">
                <tr>
                    <th>Jméno</th>
                    <td><?= h(trim((string)$targetUser['jmeno'] . ' ' . (string)$targetUser['prijmeni'])) ?></td>
                </tr>
                <tr>
                    <th>Login</th>
                    <td><?= h((string)$targetUser['user']) ?></td>
                </tr>
                <tr>
                    <th>FTP login</th>
                    <td><?= h((string)$targetUser['ftp_user']) ?></td>
                </tr>
                <tr>
                    <th>Role</th>
                    <td><?= h((string)$targetUser['role_name']) ?></td>
                </tr>
                <tr>
                    <th>Home directory</th>
                    <td class="path-cell"><?= h((string)$targetUser['homedir']) ?></td>
                </tr>
            </table>

            <form method="post" class="form-actions" style="margin-top:20px;">
                <input type="hidden" name="id" value="<?= (int)$targetUser['id'] ?>">
                <button type="submit" class="btn btn-danger">Ano, smazat uživatele</button>
                <a href="/users.php" class="btn btn-secondary">Zrušit</a>
            </form>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>