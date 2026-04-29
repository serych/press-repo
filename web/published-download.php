<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/published_photos.php';

require_login();

$id = (int)($_GET['id'] ?? 0);
$photo = $id > 0 ? published_photos_get_ready($id) : null;

if (!$photo || empty($photo['filepath']) || !is_file((string)$photo['filepath'])) {
    http_response_code(404);
    exit;
}

$filepath = (string)$photo['filepath'];
$filename = basename((string)$photo['filename']);

published_photos_mark_downloaded($id);

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
