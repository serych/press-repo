<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/client_upload_tokens.php';
require_once __DIR__ . '/../inc/published_photos.php';

function client_api_json(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    client_api_json([
        'ok' => false,
        'error' => 'Endpoint podporuje pouze GET.',
    ], 405);
}

$auth = client_upload_tokens_authenticate_bearer('published_upload');
if (($auth['ok'] ?? false) !== true) {
    client_api_json([
        'ok' => false,
        'error' => (string)($auth['error'] ?? 'Autentizace selhala.'),
    ], (int)($auth['status'] ?? 401));
}

$event = published_photos_current_event();
if (!$event) {
    client_api_json([
        'ok' => false,
        'error' => 'Není vybraný aktivní event.',
    ], 409);
}

client_api_json([
    'ok' => true,
    'event' => [
        'id' => (int)$event['id'],
        'title' => (string)$event['title'],
        'slug' => (string)($event['slug'] ?? ''),
        'status' => (string)$event['status'],
    ],
]);
