<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/chat.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

if (!can_access_chat()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Přístup odepřen.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$user = current_user();
$userId = (int)($user['id'] ?? 0);
$eventId = max(0, (int)($_POST['event_id'] ?? 0));
$lastId = max(0, (int)($_POST['last_id'] ?? 0));

if ($eventId > 0 && $lastId > 0) {
    chat_mark_read($eventId, $userId, $lastId);
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
