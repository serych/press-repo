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
    filepath,
    locked_by_user_id,
    event_photographer_allowed
FROM photos
WHERE id = :id
LIMIT 1
";

$stmt = db()->prepare($sql);
$stmt->execute(['id'=>$id]);
$photo = $stmt->fetch();
$user = current_user();

if (!$photo) {
    http_response_code(404);
    exit;
}

if ((int)($photo['event_photographer_allowed'] ?? 1) !== 1) {
    http_response_code(403);
    exit('Fotografie není aktivní pro tento event.');
}

/* musí být locked a náležet uživateli */

if (!$photo['locked_by_user_id']
    || $photo['locked_by_user_id'] != $user['id']) {

    http_response_code(403);
    exit('Fotografie není zamčena pro tohoto uživatele.');
}

$file = $photo['filepath'];

if (!is_file($file)) {
    http_response_code(404);
    exit;
}

if (str_ends_with($photo['filename'], '.thumb.jpg')) {
    http_response_code(404);
    exit;
}

/* update status */

$user = current_user();

$sql = "
UPDATE photos
SET
    status = 'downloaded',
    downloaded = 1,
    downloaded_at = NOW(),
    locked_by_user_id = NULL,
    locked_at = NULL
WHERE id = :id
";

db()->prepare($sql)->execute(['id'=>$id]);

/* log */

$sql = "
INSERT INTO photo_log
(photo_id,user_id,action,created_at)
VALUES (:pid,:uid,'downloaded',NOW())
";

db()->prepare($sql)->execute([
    'pid'=>$id,
    'uid'=>$user['id']
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
