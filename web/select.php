<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

require_login();

function select_wants_json(): bool
{
    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));

    return $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json');
}

function select_finish(bool $ok = true, string $message = ''): never
{
    if (select_wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo json_encode([
            'ok' => $ok,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    redirect($_SERVER['HTTP_REFERER'] ?? '/photos.php');
}

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
SELECT id, status, locked_by_user_id, event_photographer_allowed, is_blocked
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

if ((int)($photo['event_photographer_allowed'] ?? 1) !== 1) {
    http_response_code(403);
    exit('Fotografie není od fotografa přiřazeného k eventu.');
}

if ($action === 'block') {
    $sql = "
    UPDATE photos
    SET
        is_blocked = 1,
        blocked_by_user_id = :uid,
        blocked_at = NOW(),
        status = IF(status = 'locked', 'ready', status),
        locked_by_user_id = NULL,
        locked_at = NULL
    WHERE id = :id
    ";

    db()->prepare($sql)->execute([
        'id' => $id,
        'uid' => $user['id'],
    ]);

    select_finish();
}

if ($action === 'unblock') {
    $sql = "
    UPDATE photos
    SET
        is_blocked = 0,
        blocked_by_user_id = NULL,
        blocked_at = NULL
    WHERE id = :id
    ";

    db()->prepare($sql)->execute(['id' => $id]);

    select_finish();
}

if (!empty($photo['is_blocked'])) {
    http_response_code(403);
    exit('Fotografie je zablokovaná.');
}

/* LOCK */

if ($action === 'lock') {

    /* někdo jiný už locknul */

    if ($photo['locked_by_user_id']
        && $photo['locked_by_user_id'] != $user['id']) {

        select_finish(false, 'Fotografii mezitím zamkl jiný uživatel.');
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

    select_finish();
}

/* UNLOCK */

if ($action === 'unlock') {

    /* odemknout může jen autor */

    if ($photo['locked_by_user_id'] != $user['id']) {
        select_finish(false, 'Fotografii může odemknout jen uživatel, který ji zamkl.');
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

    select_finish();
}

select_finish(false, 'Neznámá akce.');
