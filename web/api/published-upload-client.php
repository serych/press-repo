<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/client_upload_tokens.php';
require_once __DIR__ . '/../inc/published_upload.php';

function published_upload_client_json(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    published_upload_client_json([
        'ok' => false,
        'error' => 'Endpoint podporuje pouze POST.',
    ], 405);
}

$auth = client_upload_tokens_authenticate_bearer('published_upload');
if (($auth['ok'] ?? false) !== true) {
    published_upload_client_json([
        'ok' => false,
        'error' => (string)($auth['error'] ?? 'Autentizace selhala.'),
    ], (int)($auth['status'] ?? 401));
}

$event = published_photos_current_event();
if (!$event) {
    published_upload_client_json([
        'ok' => false,
        'error' => 'Není vybraný aktivní event.',
    ], 409);
}

$files = $_FILES['photo'] ?? $_FILES['photos'] ?? null;
$result = published_upload_handle_request(
    $event,
    $auth['user'] ?? [],
    is_array($files) ? $files : null,
    (int)($_SERVER['CONTENT_LENGTH'] ?? 0)
);

$uploaded = array_map(
    static fn(array $item): array => published_upload_json_item($item),
    $result['uploaded']
);

published_upload_client_json([
    'ok' => (bool)$result['ok'],
    'event' => [
        'id' => (int)$event['id'],
        'title' => (string)$event['title'],
        'slug' => (string)($event['slug'] ?? ''),
        'status' => (string)$event['status'],
    ],
    'errors' => $result['errors'],
    'uploaded' => $uploaded,
], (bool)$result['ok'] ? 200 : 400);
