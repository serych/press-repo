<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/chat.php';

require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!can_access_chat()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Přístup odepřen.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
$userId = (int)($user['id'] ?? 0);
$eventId = max(0, (int)($_GET['event_id'] ?? 0));

if ($eventId > 0) {
    if (!chat_event_exists($eventId)) {
        echo json_encode([
            'ok' => true,
            'total' => 0,
            'events' => [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $count = chat_unread_count_for_event($eventId, $userId);

    echo json_encode([
        'ok' => true,
        'total' => $count,
        'events' => $count > 0 ? [[
            'event_id' => $eventId,
            'unread_count' => $count,
        ]] : [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(
    ['ok' => true] + chat_unread_summary_for_user($userId),
    JSON_UNESCAPED_UNICODE
);
