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

$user = current_user();
$users = users_list();

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <div class="page-head">
        <h1>Uživatelé</h1>
        <div class="table-actions">
            <a href="/client-upload-tokens.php" class="button button-muted">Upload tokeny</a>
            <a href="/user-create.php" class="button">Nový uživatel</a>
        </div>
    </div>

    <?php if (empty($users)): ?>
        <p>Žádní uživatelé.</p>
    <?php else: ?>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Jméno</th>
                    <th>Login</th>
                    <th>Role</th>
                    <th>Poslední přihlášení</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>

            <?php foreach ($users as $u): ?>
                <?php
                $isSelf = (int)$u['id'] === (int)$user['id'];
                $isTargetSuperadmin = (($u['role_code'] ?? '') === 'superadmin');
                $currentIsSuperadmin = (($user['role_code'] ?? '') === 'superadmin');
                ?>
                <tr>
                    <td>
                        <div class="user-name">
                            <?= h(trim((string)$u['jmeno'] . ' ' . (string)$u['prijmeni'])) ?>
                        </div>
                    </td>
                    <td>
                        <code class="inline-code"><?= h((string)$u['user']) ?></code>
                    </td>
                    <td>
                        <span class="role-badge role-<?= h((string)$u['role_code']) ?>">
                            <?= h((string)$u['role_name']) ?>
                        </span>
                    </td>
                    <td>
                        <?= !empty($u['last_login_at']) ? h((string)$u['last_login_at']) : '—' ?>
                    </td>
                    <td class="actions-cell">
                        <a class="table-action" href="/user-edit.php?id=<?= (int)$u['id'] ?>">Upravit</a>

                        <?php if (!$isSelf && ($currentIsSuperadmin || !$isTargetSuperadmin)): ?>
                        <a class="table-action table-action-danger"
                           href="/user-delete.php?id=<?= (int)$u['id'] ?>">
                            Smazat
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            </tbody>
        </table>
    </div>

    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
