<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

require_login();

if (!has_permission('photos.download')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(404);
    exit;
}

$sql = "
SELECT
    id,
    filename,
    filepath
FROM photos
WHERE id = :id
LIMIT 1
";

$stmt = db()->prepare($sql);
$stmt->execute(['id' => $id]);
$photo = $stmt->fetch();

if (!$photo) {
    http_response_code(404);
    exit;
}

$file = $photo['filepath'];

if (!is_file($file)) {
    http_response_code(404);
    exit;
}

/* log download */

$user = current_user();

$sql = "
INSERT INTO photo_log
(photo_id, user_id, action, created_at)
VALUES (:photo_id, :user_id, 'downloaded', NOW())
";

db()->prepare($sql)->execute([
    'photo_id' => $id,
    'user_id' => $user['id']
]);

/* download */

$filename = $photo['filename'];
$filesize = filesize($file);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: no-cache');

readfile($file);
exit;