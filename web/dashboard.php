<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';

require_login();

$user = current_user();

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <h1>Dashboard</h1>
    <p>Vítejte, <?= h($user['jmeno'] . ' ' . $user['prijmeni']) ?>.</p>

    <div class="info-grid">
        <div class="card">
            <h2>Účet</h2>
            <p><strong>Login:</strong> <?= h($user['user']) ?></p>
            <p><strong>Role:</strong> <?= h($user['role_name']) ?></p>
            <p><strong>Kód role:</strong> <?= h($user['role_code']) ?></p>
        </div>

        <div class="card">
            <h2>Oprávnění</h2>
            <?php if (!empty($user['permissions'])): ?>
                <ul>
                    <?php foreach ($user['permissions'] as $perm): ?>
                        <li><?= h($perm) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Uživatel zatím nemá přiřazena žádná oprávnění.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/inc/footer.php'; ?>