<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function start_session_if_needed(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function login_user(string $username, string $password): bool
{
    start_session_if_needed();

    $sql = "
        SELECT
            u.id,
            u.jmeno,
            u.prijmeni,
            u.user,
            u.pass_hash,
            u.is_active,
            r.id AS role_id,
            r.code AS role_code,
            r.name AS role_name
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE u.user = :username
        LIMIT 1
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    if ((int)$user['is_active'] !== 1) {
        return false;
    }

    if (!password_verify($password, (string)$user['pass_hash'])) {
        return false;
    }

    $permissions = get_permissions_for_role((int)$user['role_id']);

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'jmeno' => (string)$user['jmeno'],
        'prijmeni' => (string)$user['prijmeni'],
        'user' => (string)$user['user'],
        'role_id' => (int)$user['role_id'],
        'role_code' => (string)$user['role_code'],
        'role_name' => (string)$user['role_name'],
        'permissions' => $permissions,
    ];

    update_last_login((int)$user['id']);

    return true;
}

function get_permissions_for_role(int $roleId): array
{
    $sql = "
        SELECT p.code
        FROM role_permissions rp
        INNER JOIN permissions p ON p.id = rp.permission_id
        WHERE rp.role_id = :role_id
          AND rp.allowed = 1
    ";

    $stmt = db()->prepare($sql);
    $stmt->execute(['role_id' => $roleId]);

    $permissions = [];
    foreach ($stmt->fetchAll() as $row) {
        $permissions[] = (string)$row['code'];
    }

    return $permissions;
}

function update_last_login(int $userId): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $packedIp = null;

    if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
        $packedIp = inet_pton($ip);
    }

    $sql = "
        UPDATE users
        SET last_login_at = NOW(),
            last_login_ip = :last_login_ip
        WHERE id = :id
    ";

    $stmt = db()->prepare($sql);
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

    if ($packedIp === false || $packedIp === null) {
        $stmt->bindValue(':last_login_ip', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':last_login_ip', $packedIp, PDO::PARAM_LOB);
    }

    $stmt->execute();
}

function logout_user(): void
{
    start_session_if_needed();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool)$params['secure'],
            (bool)$params['httponly']
        );
    }

    session_destroy();
}

function current_user(): ?array
{
    start_session_if_needed();
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('/login.php');
    }
}

function has_permission(string $permissionCode): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    return in_array($permissionCode, $user['permissions'] ?? [], true);
}

function has_role(string $roleCode): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    return ($user['role_code'] ?? '') === $roleCode;
}