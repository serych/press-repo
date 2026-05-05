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

$eventId = max(1, (int)($_GET['event_id'] ?? 0));
$afterId = max(0, (int)($_GET['after_id'] ?? 0));

if (!$eventId || !chat_event_exists($eventId)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Event nenalezen.']);
    exit;
}

$items = chat_messages_list($eventId, 50, $afterId);
$lastId = 0;

foreach ($items as &$item) {
    $fullName = trim(((string)$item['jmeno']) . ' ' . ((string)$item['prijmeni']));
    $item['author_name'] = $fullName !== '' ? $fullName : (string)$item['user'];
    $item['is_mine'] = (int)$item['user_id'] === (int)(current_user()['id'] ?? 0);
    $lastId = max($lastId, (int)$item['id']);
}
unset($item);

echo json_encode([
    'ok' => true,
    'items' => $items,
    'last_id' => $lastId,
], JSON_UNESCAPED_UNICODE);
