<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function client_upload_tokens_can_manage(): bool
{
    return has_permission('client_upload_tokens.manage') || has_permission('users.manage');
}

function client_upload_tokens_generate_plain_token(): string
{
    return 'pcup_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function client_upload_tokens_hash(string $token): string
{
    return hash('sha256', $token);
}

function client_upload_tokens_list(): array
{
    $currentUser = current_user();
    $currentRoleCode = (string)($currentUser['role_code'] ?? '');
    $where = '';

    if ($currentRoleCode !== 'superadmin') {
        $where = "WHERE owner_role.code <> 'superadmin'";
    }

    $stmt = db()->query("
        SELECT
            cut.*,
            owner.user AS owner_login,
            owner.jmeno AS owner_jmeno,
            owner.prijmeni AS owner_prijmeni,
            owner_role.code AS owner_role_code,
            owner_role.name AS owner_role_name,
            creator.user AS created_by_login,
            creator.jmeno AS created_by_jmeno,
            creator.prijmeni AS created_by_prijmeni,
            revoker.user AS revoked_by_login,
            revoker.jmeno AS revoked_by_jmeno,
            revoker.prijmeni AS revoked_by_prijmeni
        FROM client_upload_tokens cut
        INNER JOIN users owner ON owner.id = cut.user_id
        INNER JOIN roles owner_role ON owner_role.id = owner.role_id
        LEFT JOIN users creator ON creator.id = cut.created_by_user_id
        LEFT JOIN users revoker ON revoker.id = cut.revoked_by_user_id
        $where
        ORDER BY cut.is_revoked ASC, cut.created_at DESC, cut.id DESC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function client_upload_tokens_eligible_users(): array
{
    $currentUser = current_user();
    $currentRoleCode = (string)($currentUser['role_code'] ?? '');
    $where = "WHERE u.is_active = 1";

    if ($currentRoleCode !== 'superadmin') {
        $where .= " AND r.code <> 'superadmin'";
    }

    $stmt = db()->query("
        SELECT DISTINCT
            u.id,
            u.user,
            u.jmeno,
            u.prijmeni,
            r.code AS role_code,
            r.name AS role_name
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        INNER JOIN role_permissions rp ON rp.role_id = r.id AND rp.allowed = 1
        INNER JOIN permissions p ON p.id = rp.permission_id
        $where
          AND p.code = 'published_photos.upload'
        ORDER BY r.priority ASC, u.prijmeni ASC, u.jmeno ASC, u.user ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function client_upload_tokens_user_can_receive(int $userId): bool
{
    foreach (client_upload_tokens_eligible_users() as $user) {
        if ((int)$user['id'] === $userId) {
            return true;
        }
    }

    return false;
}

function client_upload_tokens_create(int $userId, string $name, ?string $expiresAt, int $createdByUserId): array
{
    $name = trim($name);
    if ($name === '') {
        throw new InvalidArgumentException('Vyplň název tokenu.');
    }

    if (strlen($name) > 150) {
        throw new InvalidArgumentException('Název tokenu je příliš dlouhý.');
    }

    if (!client_upload_tokens_user_can_receive($userId)) {
        throw new InvalidArgumentException('Vybraný uživatel nemůže dostat token pro upload hotových fotografií.');
    }

    $expiresAt = trim((string)$expiresAt);
    if ($expiresAt === '') {
        $expiresAt = null;
    } else {
        $timestamp = strtotime($expiresAt);
        if ($timestamp === false) {
            throw new InvalidArgumentException('Neplatné datum expirace tokenu.');
        }
        $expiresAt = date('Y-m-d H:i:s', $timestamp);
    }

    $token = client_upload_tokens_generate_plain_token();
    $tokenHash = client_upload_tokens_hash($token);
    $tokenPrefix = substr($token, 0, 16);

    $stmt = db()->prepare("
        INSERT INTO client_upload_tokens (
            user_id,
            name,
            scope,
            token_prefix,
            token_hash,
            expires_at,
            created_by_user_id
        ) VALUES (
            :user_id,
            :name,
            'published_upload',
            :token_prefix,
            :token_hash,
            :expires_at,
            :created_by_user_id
        )
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':name' => $name,
        ':token_prefix' => $tokenPrefix,
        ':token_hash' => $tokenHash,
        ':expires_at' => $expiresAt,
        ':created_by_user_id' => $createdByUserId,
    ]);

    return [
        'id' => (int)db()->lastInsertId(),
        'token' => $token,
    ];
}

function client_upload_tokens_revoke(int $id, int $revokedByUserId): void
{
    $currentUser = current_user();
    $currentRoleCode = (string)($currentUser['role_code'] ?? '');
    $where = '';

    if ($currentRoleCode !== 'superadmin') {
        $where = "AND owner_role.code <> 'superadmin'";
    }

    $stmt = db()->prepare("
        UPDATE client_upload_tokens cut
        INNER JOIN users owner ON owner.id = cut.user_id
        INNER JOIN roles owner_role ON owner_role.id = owner.role_id
        SET
            cut.is_revoked = 1,
            cut.revoked_by_user_id = :revoked_by_user_id,
            cut.revoked_at = NOW()
        WHERE cut.id = :id
          AND cut.is_revoked = 0
          $where
    ");
    $stmt->execute([
        ':id' => $id,
        ':revoked_by_user_id' => $revokedByUserId,
    ]);
}

function client_upload_tokens_user_label(array $row, string $prefix): string
{
    $name = trim(
        (string)($row[$prefix . '_jmeno'] ?? '') . ' ' .
        (string)($row[$prefix . '_prijmeni'] ?? '')
    );

    $login = (string)($row[$prefix . '_login'] ?? '');
    if ($name !== '' && $login !== '') {
        return $name . ' (' . $login . ')';
    }

    return $name !== '' ? $name : $login;
}
