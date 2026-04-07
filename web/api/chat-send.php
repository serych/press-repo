<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/chat.php';

require_login();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$eventId = max(1, (int)($_POST['event_id'] ?? 0));
$message = trim((string)($_POST['message'] ?? ''));
$userId = (int)(current_user()['id'] ?? 0);

if (!$eventId || !chat_event_exists($eventId)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Event nenalezen.']);
    exit;
}

if ($message === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Zpráva je prázdná.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$messageId = chat_message_create($eventId, $userId, $message);
chat_mark_read($eventId, $userId, $messageId);

echo json_encode([
    'ok' => true,
    'message_id' => $messageId,
], JSON_UNESCAPED_UNICODE);