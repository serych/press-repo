<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function users_can_manage(): bool
{
    return has_permission('users.manage');
}

function users_is_superadmin(array $user): bool
{
    return (($user['role_code'] ?? '') === 'superadmin');
}

function users_is_admin(array $user): bool
{
    return in_array(($user['role_code'] ?? ''), ['admin', 'superadmin'], true);
}

function users_list(): array
{
    $pdo = db();
    $currentUser = current_user();
    $currentRoleCode = (string)($currentUser['role_code'] ?? '');

    if ($currentRoleCode === 'superadmin') {
        $stmt = $pdo->query("
            SELECT
                u.id,
                u.jmeno,
                u.prijmeni,
                u.user,
                u.ftp_user,
                u.homedir,
                u.exif_author,
                u.is_active,
                u.last_login_at,
                r.id   AS role_id,
                r.code AS role_code,
                r.name AS role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            ORDER BY r.priority ASC, u.prijmeni ASC, u.jmeno ASC, u.user ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.jmeno,
            u.prijmeni,
            u.user,
            u.ftp_user,
            u.homedir,
            u.exif_author,
            u.is_active,
            u.last_login_at,
            r.id   AS role_id,
            r.code AS role_code,
            r.name AS role_name
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE r.code <> 'superadmin'
        ORDER BY r.priority ASC, u.prijmeni ASC, u.jmeno ASC, u.user ASC
    ");
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function users_get(int $id): ?array
{
    $pdo = db();
    $currentUser = current_user();
    $currentRoleCode = (string)($currentUser['role_code'] ?? '');

    if ($currentRoleCode === 'superadmin') {
        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.jmeno,
                u.prijmeni,
                u.mobile,
                u.exif_author,
                u.user,
                u.pass_hash,
                u.ftp_user,
                u.ftp_pass_hash,
                u.homedir,
                u.role_id,
                u.is_active,
                u.last_login_at,
                r.code AS role_code,
                r.name AS role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.jmeno,
                u.prijmeni,
                u.mobile,
                u.exif_author,
                u.user,
                u.pass_hash,
                u.ftp_user,
                u.ftp_pass_hash,
                u.homedir,
                u.role_id,
                u.is_active,
                u.last_login_at,
                r.code AS role_code,
                r.name AS role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.id = ?
              AND r.code <> 'superadmin'
            LIMIT 1
        ");
        $stmt->execute([$id]);
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function users_roles_list(): array
{
    $pdo = db();
    $currentUser = current_user();
    $currentRoleCode = (string)($currentUser['role_code'] ?? '');

    if ($currentRoleCode === 'superadmin') {
        $stmt = $pdo->query("
            SELECT id, code, name, priority
            FROM roles
            ORDER BY priority ASC, name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt = $pdo->prepare("
        SELECT id, code, name, priority
        FROM roles
        WHERE code <> 'superadmin'
        ORDER BY priority ASC, name ASC
    ");
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function users_count_superadmins(): int
{
    $pdo = db();
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE r.code = 'superadmin'
    ");
    return (int)$stmt->fetchColumn();
}

function users_login_exists(string $login, ?int $excludeUserId = null): bool
{
    $pdo = db();

    if ($excludeUserId) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE user = ?
              AND id <> ?
        ");
        $stmt->execute([$login, $excludeUserId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE user = ?
        ");
        $stmt->execute([$login]);
    }

    return (int)$stmt->fetchColumn() > 0;
}

function users_ftp_login_exists(string $ftpLogin, ?int $excludeUserId = null): bool
{
    $pdo = db();

    if ($excludeUserId) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE ftp_user = ?
              AND id <> ?
        ");
        $stmt->execute([$ftpLogin, $excludeUserId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM users
            WHERE ftp_user = ?
        ");
        $stmt->execute([$ftpLogin]);
    }

    return (int)$stmt->fetchColumn() > 0;
}

function users_role_allowed_for_current_user(int $roleId): bool
{
    foreach (users_roles_list() as $role) {
        if ((int)$role['id'] === $roleId) {
            return true;
        }
    }
    return false;
}

function users_create(array $data): int
{
    $pdo = db();

    $stmt = $pdo->prepare("
        INSERT INTO users
        (
            jmeno,
            prijmeni,
            mobile,
            exif_author,
            user,
            pass_hash,
            ftp_user,
            ftp_pass_hash,
            homedir,
            role_id,
            is_active
        )
        VALUES
        (
            :jmeno,
            :prijmeni,
            :mobile,
            :exif_author,
            :user,
            :pass_hash,
            :ftp_user,
            :ftp_pass_hash,
            :homedir,
            :role_id,
            1
        )
    ");

    $stmt->execute([
        ':jmeno'         => $data['jmeno'],
        ':prijmeni'      => $data['prijmeni'],
        ':mobile'        => users_normalize_mobile($data['mobile'] ?? ''),
        ':exif_author'   => users_normalize_exif_author($data['exif_author'] ?? ''),
        ':user'          => $data['user'],
        ':pass_hash'     => password_hash($data['password'], PASSWORD_BCRYPT),
        ':ftp_user'      => $data['ftp_user'],
        ':ftp_pass_hash' => password_hash($data['ftp_password'], PASSWORD_BCRYPT),
        ':homedir'       => $data['homedir'],
        ':role_id'       => $data['role_id'],
    ]);

    users_ensure_ftp_directory($data['homedir']);
    return (int)$pdo->lastInsertId();
}

function users_update(int $id, array $data): void
{
    $pdo = db();

    $fields = [
        'jmeno = :jmeno',
        'prijmeni = :prijmeni',
        'mobile = :mobile',
        'exif_author = :exif_author',
        'user = :user',
        'ftp_user = :ftp_user',
        'homedir = :homedir',
        'role_id = :role_id',
    ];

    $params = [
        ':id'          => $id,
        ':jmeno'       => $data['jmeno'],
        ':prijmeni'    => $data['prijmeni'],
        ':mobile'      => users_normalize_mobile($data['mobile'] ?? ''),
        ':exif_author' => users_normalize_exif_author($data['exif_author'] ?? ''),
        ':user'        => $data['user'],
        ':ftp_user'    => $data['ftp_user'],
        ':homedir'     => $data['homedir'],
        ':role_id'     => $data['role_id'],
    ];

    if (!empty($data['password'])) {
        $fields[] = 'pass_hash = :pass_hash';
        $params[':pass_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
    }

    if (!empty($data['ftp_password'])) {
        $fields[] = 'ftp_pass_hash = :ftp_pass_hash';
        $params[':ftp_pass_hash'] = password_hash($data['ftp_password'], PASSWORD_BCRYPT);
    }

    $sql = "
        UPDATE users
        SET " . implode(",\n            ", $fields) . "
        WHERE id = :id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    users_ensure_ftp_directory($data['homedir']);
}

function users_delete(int $id): void
{
    $pdo = db();

    $stmt = $pdo->prepare("
        DELETE FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
}

function users_ensure_ftp_directory(string $homedir): void
{
    $homedir = trim($homedir);

    if ($homedir === '') {
        throw new RuntimeException('Chybí home directory.');
    }

    $base = '/var/www/press/ftp/';
    if (strncmp($homedir, $base, strlen($base)) !== 0) {
        throw new RuntimeException('Home directory je mimo povolený FTP koøen.');
    }

    if (!is_dir($homedir)) {
        if (!mkdir($homedir, 0755, true) && !is_dir($homedir)) {
            throw new RuntimeException('Nepodaøilo se vytvoøit FTP adresáø.');
        }
    }

    @chmod($homedir, 0755);

    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        @chown($homedir, 'www-data');
        @chgrp($homedir, 'www-data');
    }
}

function users_normalize_mobile(?string $value): ?string
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    $value = preg_replace('~(?!^\+)[^0-9]|[^\+0-9]~', '', $value);

    if (preg_match('~^\d{9}$~', $value)) {
        $value = '+420' . $value;
    }

    if (preg_match('~^420\d{9}$~', $value)) {
        $value = '+' . $value;
    }

    return $value;
}

function users_format_mobile(?string $value): string
{
    $value = trim((string)$value);

    if ($value === '') {
        return '';
    }

    $normalized = users_normalize_mobile($value);
    if ($normalized === null) {
        return '';
    }

    if (preg_match('~^\+420(\d{3})(\d{3})(\d{3})$~', $normalized, $m)) {
        return '+420 ' . $m[1] . ' ' . $m[2] . ' ' . $m[3];
    }

    return $normalized;
}

function users_normalize_exif_author(?string $value): ?string
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}