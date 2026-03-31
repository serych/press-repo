<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

require_login();

if (!has_permission('photos.select')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$id = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? 'lock';

if ($id <= 0) {
    http_response_code(404);
    exit;
}

$user = current_user();

/* načti fotku */

$sql = "
SELECT id, locked_by_user_id
FROM photos
WHERE id = :id
LIMIT 1
";

$stmt = db()->prepare($sql);
$stmt->execute(['id'=>$id]);
$photo = $stmt->fetch();

if (!$photo) {
    http_response_code(404);
    exit;
}

/* LOCK */

if ($action === 'lock') {

    /* někdo jiný už locknul */

    if ($photo['locked_by_user_id']
        && $photo['locked_by_user_id'] != $user['id']) {

        redirect($_SERVER['HTTP_REFERER'] ?? '/photos.php');
    }

    $sql = "
    UPDATE photos
    SET
        status = 'locked',
        locked_by_user_id = :uid,
        locked_at = NOW()
    WHERE id = :id
    ";

    db()->prepare($sql)->execute([
        'id'=>$id,
        'uid'=>$user['id']
    ]);

    $logAction = 'locked';
}

/* UNLOCK */

if ($action === 'unlock') {

    /* odemknout může jen autor */

    if ($photo['locked_by_user_id'] != $user['id']) {
        redirect($_SERVER['HTTP_REFERER'] ?? '/photos.php');
    }

    $sql = "
    UPDATE photos
    SET
        status = 'ready',
        locked_by_user_id = NULL,
        locked_at = NULL
    WHERE id = :id
    ";

    db()->prepare($sql)->execute(['id'=>$id]);

    $logAction = 'unlocked';
}

/* log */

$sql = "
INSERT INTO photo_log
(photo_id,user_id,action,created_at)
VALUES (:pid,:uid,:action,NOW())
";

db()->prepare($sql)->execute([
    'pid'=>$id,
    'uid'=>$user['id'],
    'action'=>$logAction
]);

redirect($_SERVER['HTTP_REFERER'] ?? '/photos.php');