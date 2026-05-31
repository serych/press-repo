<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/client_upload_tokens.php';

require_login();

$currentUser = current_user();
$currentUserId = (int)($currentUser['id'] ?? 0);
$canManageOwnTokens = client_upload_tokens_can_manage_own();
$errors = [];
$flashMessage = '';
$generatedToken = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!$canManageOwnTokens) {
        $errors[] = 'Nemáš oprávnění spravovat upload tokeny.';
    } elseif ($action === 'create_token') {
        $name = trim((string)($_POST['name'] ?? ''));
        $expiresAt = trim((string)($_POST['expires_at'] ?? ''));

        try {
            $created = client_upload_tokens_create($currentUserId, $name, $expiresAt !== '' ? $expiresAt : null, $currentUserId);
            $generatedToken = (string)$created['token'];
            $flashMessage = 'Token byl vytvořen. Zkopíruj ho teď, později už nebude zobrazen.';
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    } elseif ($action === 'revoke_token') {
        $tokenId = (int)($_POST['token_id'] ?? 0);
        if ($tokenId <= 0) {
            $errors[] = 'Neplatný token.';
        } else {
            client_upload_tokens_revoke_own($tokenId, $currentUserId);
            $flashMessage = 'Token byl zneplatněn.';
        }
    } else {
        $errors[] = 'Neznámá akce.';
    }
}

$tokens = $canManageOwnTokens ? client_upload_tokens_list_for_user($currentUserId) : [];

require_once __DIR__ . '/inc/header.php';
?>

<section class="panel">
    <div class="page-head">
        <h1>Můj účet</h1>
        <a href="/logout.php" class="button button-muted">Odhlásit</a>
    </div>

    <div class="card">
        <h2>Přihlášený uživatel</h2>
        <div class="account-detail-grid">
            <div>
                <strong>Jméno</strong>
                <div><?= h(trim((string)($currentUser['jmeno'] ?? '') . ' ' . (string)($currentUser['prijmeni'] ?? ''))) ?></div>
            </div>
            <div>
                <strong>Login</strong>
                <div><?= h((string)($currentUser['user'] ?? '')) ?></div>
            </div>
            <div>
                <strong>Role</strong>
                <div><?= h((string)($currentUser['role_name'] ?? '')) ?></div>
            </div>
        </div>
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

    <?php if ($generatedToken !== ''): ?>
        <div class="alert-info">
            <strong>Nový Lightroom token:</strong>
            <div class="client-token-copy-row">
                <code class="client-token-value" id="generated-client-token"><?= h($generatedToken) ?></code>
                <button type="button" class="button button-muted" id="copy-client-token">Kopírovat token</button>
            </div>
            <div class="form-help">Ulož si ho teď. V databázi je uložený pouze hash a později už nepůjde znovu zobrazit.</div>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Lightroom upload token</h2>

        <?php if (!$canManageOwnTokens): ?>
            <p class="table-subtext">Tvoje role nemá oprávnění nahrávat hotové fotografie, proto si nemůže vytvořit Lightroom upload token.</p>
        <?php else: ?>
            <p class="table-subtext">
                Token slouží pro Lightroom Classic plugin. Platí pouze pro tvůj účet a umožňuje nahrávat hotové JPG do právě aktivního eventu.
            </p>

            <form method="post" class="form" autocomplete="off">
                <input type="hidden" name="action" value="create_token">

                <div class="form-grid">
                    <div>
                        <label for="name">Název tokenu</label>
                        <input type="text" name="name" id="name" maxlength="150" placeholder="Lightroom notebook" required>
                    </div>

                    <div>
                        <label for="expires_at">Expirace</label>
                        <input type="datetime-local" name="expires_at" id="expires_at">
                        <small class="form-help">Volitelné. Prázdné pole znamená token bez automatické expirace.</small>
                    </div>
                </div>

                <button type="submit">Vytvořit můj token</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($canManageOwnTokens): ?>
        <h2>Moje tokeny</h2>

        <?php if (!$tokens): ?>
            <p>Zatím nemáš vytvořený žádný Lightroom upload token.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Název</th>
                            <th>Prefix</th>
                            <th>Stav</th>
                            <th>Vytvořeno</th>
                            <th>Poslední použití</th>
                            <th>Akce</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tokens as $token): ?>
                            <?php
                            $isRevoked = (int)$token['is_revoked'] === 1;
                            $isExpired = !$isRevoked
                                && !empty($token['expires_at'])
                                && strtotime((string)$token['expires_at']) !== false
                                && strtotime((string)$token['expires_at']) < time();
                            ?>
                            <tr>
                                <td><?= h((string)$token['name']) ?></td>
                                <td><code class="inline-code"><?= h((string)$token['token_prefix']) ?>...</code></td>
                                <td>
                                    <?php if ($isRevoked): ?>
                                        <span class="status status-error">zneplatněný</span>
                                        <?php if (!empty($token['revoked_at'])): ?>
                                            <div class="table-subtext"><?= h((string)$token['revoked_at']) ?></div>
                                        <?php endif; ?>
                                    <?php elseif ($isExpired): ?>
                                        <span class="status status-error">expirovaný</span>
                                    <?php else: ?>
                                        <span class="status status-ready">aktivní</span>
                                        <?php if (!empty($token['expires_at'])): ?>
                                            <div class="table-subtext">do <?= h((string)$token['expires_at']) ?></div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= h((string)$token['created_at']) ?></td>
                                <td><?= !empty($token['last_used_at']) ? h((string)$token['last_used_at']) : '—' ?></td>
                                <td class="actions-cell">
                                    <?php if (!$isRevoked): ?>
                                        <form method="post" onsubmit="return confirm('Opravdu zneplatnit tento token?');">
                                            <input type="hidden" name="action" value="revoke_token">
                                            <input type="hidden" name="token_id" value="<?= (int)$token['id'] ?>">
                                            <button type="submit" class="table-action table-action-danger">Zneplatnit</button>
                                        </form>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const token = document.getElementById('generated-client-token');
    const button = document.getElementById('copy-client-token');

    if (!token || !button) {
        return;
    }

    button.addEventListener('click', async function () {
        const value = token.textContent || '';
        const originalText = button.textContent;

        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(value);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = value;
                textarea.style.position = 'fixed';
                textarea.style.left = '-9999px';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }

            button.textContent = 'Zkopírováno';
            window.setTimeout(function () {
                button.textContent = originalText;
            }, 2000);
        } catch (e) {
            button.textContent = 'Kopírování selhalo';
            window.setTimeout(function () {
                button.textContent = originalText;
            }, 2000);
        }
    });
});
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
