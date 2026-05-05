<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';

require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(404);
    exit;
}

$sql = "
    SELECT preview_filepath
    FROM photos
    WHERE id = :id
    LIMIT 1
";

$stmt = db()->prepare($sql);
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();

if (!$row || !$row['preview_filepath']) {
    http_response_code(404);
    exit;
}

$file = $row['preview_filepath'];
$size = (string)($_GET['size'] ?? 'detail');

if ($size === 'small') {
    $smallFile = preview_small_filepath((string)$file);
    if (is_file($smallFile)) {
        $file = $smallFile;
    }
}

if (!is_file($file)) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=60');

readfile($file);
exit;

function preview_small_filepath(string $detailPath): string
{
    $dir = dirname($detailPath);
    $filename = basename($detailPath);
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $base = pathinfo($filename, PATHINFO_FILENAME);

    if ($ext === '') {
        $ext = 'jpg';
    }

    return $dir . '/' . $base . '-small.' . $ext;
}
