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

header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=300');

$filepath = (string)$photo['filepath'];
$image = @imagecreatefromjpeg($filepath);
if (!$image) {
    readfile($filepath);
    exit;
}

$width = imagesx($image);
$height = imagesy($image);
$maxWidth = 900;
$maxHeight = 650;
$scale = min($maxWidth / max(1, $width), $maxHeight / max(1, $height), 1);

if ($scale >= 1) {
    imagejpeg($image, null, 85);
    imagedestroy($image);
    exit;
}

$targetWidth = max(1, (int)round($width * $scale));
$targetHeight = max(1, (int)round($height * $scale));
$preview = imagecreatetruecolor($targetWidth, $targetHeight);

imagecopyresampled($preview, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
imagejpeg($preview, null, 82);

imagedestroy($preview);
imagedestroy($image);
