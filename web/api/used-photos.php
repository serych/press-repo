<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/photos.php';

require_login();

if (!has_permission('photos.view')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$user = current_user();
$userId = (int)($user['id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    exit('Neplatný uživatel.');
}

$pdo = db();

$stmt = $pdo->prepare("
    SELECT u.ftp_user
    FROM users u
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userRow) {
    http_response_code(404);
    exit('Uživatel nebyl nalezen.');
}

$ftpUser = trim((string)($userRow['ftp_user'] ?? ''));
if ($ftpUser === '') {
    http_response_code(400);
    exit('U uživatele chybí FTP účet.');
}

$stmt = $pdo->query("
    SELECT id
    FROM events
    WHERE status = 'active'
    ORDER BY is_temporary ASC, id DESC
    LIMIT 1
");
$activeEventId = $stmt->fetchColumn();
$activeEventId = $activeEventId !== false ? (int)$activeEventId : 0;

$items = photos_used_original_basenames_for_photographer($activeEventId, $ftpUser);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo json_encode([
    'ok' => true,
    'active_event_id' => $activeEventId > 0 ? $activeEventId : null,
    'ftp_user' => $ftpUser,
    'count' => count($items),
    'items' => $items,
    'list' => implode(', ', $items),
], JSON_UNESCAPED_UNICODE);
