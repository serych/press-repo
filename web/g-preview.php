<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/gallery_access.php';
require_once __DIR__ . '/inc/published_photos.php';

$token = gallery_access_public_url_token();
$access = gallery_access_find_by_token($token);
if (!$access || !gallery_access_is_public_session_allowed($access)) {
    http_response_code(403);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$size = (string)($_GET['size'] ?? 'detail');
$photo = $id > 0 ? published_photos_get_ready($id) : null;

if (!$photo || (int)$photo['event_id'] !== (int)$access['event_id'] || empty($photo['filepath']) || !is_file((string)$photo['filepath'])) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=300');

$previewPath = published_photos_preview_for_photo($photo, $size === 'small' ? 'small' : 'detail');
readfile($previewPath);
exit;
