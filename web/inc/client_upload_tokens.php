<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function client_upload_tokens_can_manage(): bool
{
    return has_permission('client_upload_tokens.manage') || has_permission('users.manage');
}

function client_upload_tokens_can_manage_own(): bool
{
    return has_permission('published_photos.upload');
}

function client_upload_tokens_generate_plain_token(): string
{
    return 'pcup_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function client_upload_tokens_hash(string $token): string
{
    return hash('sha256', $token);
}

function client_upload_tokens_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '');

    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $name => $value) {
            if (strtolower((string)$name) === 'authorization') {
                $header = (string)$value;
                break;
            }
        }
    }

    if (!preg_match('~^Bearer\s+(.+)$~i', trim($header), $matches)) {
        return '';
    }

    return trim($matches[1]);
}

function client_upload_tokens_authenticate_bearer(string $scope = 'published_upload'): array
{
    $token = client_upload_tokens_bearer_token();
    if ($token === '') {
        return [
            'ok' => false,
            'status' => 401,
            'error' => 'Chybí Authorization: Bearer token.',
        ];
    }

    $tokenHash = client_upload_tokens_hash($token);
    $stmt = db()->prepare("
        SELECT
            cut.*,
            u.id AS user_id,
            u.jmeno,
            u.prijmeni,
            u.user,
            u.is_active,
            r.id AS role_id,
            r.code AS role_code,
            r.name AS role_name
        FROM client_upload_tokens cut
        INNER JOIN users u ON u.id = cut.user_id
        INNER JOIN roles r ON r.id = u.role_id
        WHERE cut.token_hash = ?
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [
            'ok' => false,
            'status' => 401,
            'error' => 'Neplatný token.',
        ];
    }

    if ((string)$row['scope'] !== $scope) {
        return [
            'ok' => false,
            'status' => 403,
            'error' => 'Token nemá potřebný rozsah oprávnění.',
        ];
    }

    if ((int)$row['is_revoked'] === 1) {
        return [
            'ok' => false,
            'status' => 401,
            'error' => 'Token je zneplatněný.',
        ];
    }

    if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) {
        return [
            'ok' => false,
            'status' => 401,
            'error' => 'Token expiroval.',
        ];
    }

    if ((int)$row['is_active'] !== 1) {
        return [
            'ok' => false,
            'status' => 403,
            'error' => 'Uživatel tokenu není aktivní.',
        ];
    }

    $permissions = get_permissions_for_role((int)$row['role_id']);
    if (!in_array('published_photos.upload', $permissions, true)) {
        return [
            'ok' => false,
            'status' => 403,
            'error' => 'Uživatel tokenu nemá oprávnění nahrávat hotové fotografie.',
        ];
    }

    client_upload_tokens_mark_used((int)$row['id']);

    return [
        'ok' => true,
        'status' => 200,
        'token' => $row,
        'user' => [
            'id' => (int)$row['user_id'],
            'jmeno' => (string)$row['jmeno'],
            'prijmeni' => (string)$row['prijmeni'],
            'user' => (string)$row['user'],
            'role_id' => (int)$row['role_id'],
            'role_code' => (string)$row['role_code'],
            'role_name' => (string)$row['role_name'],
            'permissions' => $permissions,
        ],
    ];
}

function client_upload_tokens_mark_used(int $tokenId): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $packedIp = $ip && filter_var($ip, FILTER_VALIDATE_IP) ? inet_pton($ip) : null;
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

    $stmt = db()->prepare("
        UPDATE client_upload_tokens
        SET
            last_used_at = NOW(),
            last_used_ip = :last_used_ip,
            last_used_user_agent = :last_used_user_agent
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->bindValue(':id', $tokenId, PDO::PARAM_INT);
    $stmt->bindValue(':last_used_user_agent', $userAgent);
    if ($packedIp === false || $packedIp === null) {
        $stmt->bindValue(':last_used_ip', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':last_used_ip', $packedIp, PDO::PARAM_LOB);
    }
    $stmt->execute();
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

function client_upload_tokens_list_for_user(int $userId): array
{
    $stmt = db()->prepare("
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
        WHERE cut.user_id = :user_id
        ORDER BY cut.is_revoked ASC, cut.created_at DESC, cut.id DESC
    ");
    $stmt->execute([':user_id' => $userId]);

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

function client_upload_tokens_revoke_own(int $id, int $userId): void
{
    $stmt = db()->prepare("
        UPDATE client_upload_tokens
        SET
            is_revoked = 1,
            revoked_by_user_id = :revoked_by_user_id,
            revoked_at = NOW()
        WHERE id = :id
          AND user_id = :user_id
          AND is_revoked = 0
    ");
    $stmt->execute([
        ':id' => $id,
        ':user_id' => $userId,
        ':revoked_by_user_id' => $userId,
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
