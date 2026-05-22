<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/gallery_access.php';
require_once __DIR__ . '/inc/published_photos.php';

$galleryAccess = gallery_access_require_login_or_public_access();

$id = (int)($_GET['id'] ?? 0);
$size = (string)($_GET['size'] ?? 'detail');
$photo = $id > 0 ? published_photos_get_ready($id) : null;

if (
    !$photo
    || ($galleryAccess && !gallery_access_public_access_allows_photo($galleryAccess, $photo))
    || empty($photo['filepath'])
    || !is_file((string)$photo['filepath'])
) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=300');

$previewPath = published_photos_preview_for_photo($photo, $size === 'small' ? 'small' : 'detail');

readfile($previewPath);
exit;
