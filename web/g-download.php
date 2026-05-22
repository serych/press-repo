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
header('Location: /published-download.php?id=' . $id);
exit;

$photo = $id > 0 ? published_photos_get_ready($id) : null;

if (!$photo || (int)$photo['event_id'] !== (int)$access['event_id'] || empty($photo['filepath']) || !is_file((string)$photo['filepath'])) {
    http_response_code(404);
    exit;
}

$filepath = (string)$photo['filepath'];
$filename = basename((string)$photo['filename']);

published_photos_mark_downloaded($id);
gallery_access_record_download($access, $id);

header('Content-Description: File Transfer');
header('Content-Type: image/jpeg');
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Content-Length: ' . (string)filesize($filepath));
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($filepath);
exit;
