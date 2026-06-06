<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/photos.php';
require_once __DIR__ . '/../inc/published_upload.php';

require_login();

function published_upload_api_json(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!has_permission('published_photos.upload')) {
    published_upload_api_json([
        'ok' => false,
        'errors' => ['Přístup odepřen.'],
        'uploaded' => [],
    ], 403);
}

if (!is_post()) {
    published_upload_api_json([
        'ok' => false,
        'errors' => ['Endpoint podporuje pouze POST.'],
        'uploaded' => [],
    ], 405);
}

$event = photos_get_current_event();
if (!$event) {
    published_upload_api_json([
        'ok' => false,
        'errors' => ['Není vybraný aktivní event.'],
        'uploaded' => [],
    ], 409);
}

$result = published_upload_handle_request(
    $event,
    current_user() ?? [],
    $_FILES['photos'] ?? null,
    (int)($_SERVER['CONTENT_LENGTH'] ?? 0)
);

published_upload_api_json([
    'ok' => (bool)$result['ok'],
    'errors' => $result['errors'],
    'uploaded' => array_map(
        static fn(array $item): array => published_upload_json_item($item),
        $result['uploaded']
    ),
], (bool)$result['ok'] ? 200 : 400);
