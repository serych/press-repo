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
$action = $_GET['action'] ?? 'select';

if ($id <= 0) {
    http_response_code(404);
    exit('Neplatné ID');
}

$sql = "SELECT id, user_id FROM photos WHERE id = :id LIMIT 1";
$stmt = db()->prepare($sql);
$stmt->execute(['id' => $id]);
$photo = $stmt->fetch();

if (!$photo) {
    http_response_code(404);
    exit('Fotografie neexistuje');
}

if ($action === 'unselect') {

    $sql = "
        UPDATE photos
        SET
            status = 'ready',
            selected_at = NULL
        WHERE id = :id
    ";

    db()->prepare($sql)->execute(['id' => $id]);

    $logAction = 'unselected';

} else {

    $sql = "
        UPDATE photos
        SET
            status = 'selected',
            selected_at = NOW()
        WHERE id = :id
    ";

    db()->prepare($sql)->execute(['id' => $id]);

    $logAction = 'selected';
}

/* log */

$user = current_user();

$sql = "
INSERT INTO photo_log
(photo_id, user_id, action, created_at)
VALUES (:photo_id, :user_id, :action, NOW())
";

db()->prepare($sql)->execute([
    'photo_id' => $id,
    'user_id' => $user['id'],
    'action' => $logAction
]);

/* redirect zpět */

$back = $_SERVER['HTTP_REFERER'] ?? '/photos.php';
redirect($back);