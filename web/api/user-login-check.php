<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/users.php';

require_login();

if (!has_permission('users.manage')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$login = trim((string)($_GET['login'] ?? ''));
$valid = $login !== '' && !preg_match('/\s/', $login);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'valid' => $valid,
    'exists' => $valid ? users_login_exists($login) : false,
], JSON_UNESCAPED_UNICODE);
