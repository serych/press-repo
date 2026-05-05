<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/db.php';

require_login();

$user = current_user();
$userId = (int)$user['id'];
$pdo = db();

$jobId  = (int)($_GET['job'] ?? 0);
$itemId = (int)($_GET['item'] ?? 0);

if ($jobId <= 0 || $itemId <= 0) {
    http_response_code(400);
    exit('Neplatný požadavek.');
}

/*
 * Najdi položku fronty, ověř že patří aktuálnímu uživateli
 * a že fotka je stále zamčená tímto uživatelem.
 */
$stmt = $pdo->prepare("
    SELECT
        i.id AS item_id,
        i.status AS item_status,
        i.photo_id,
        p.filename,
        p.filepath,
        p.status AS photo_status,
        p.locked_by_user_id,
        p.event_photographer_allowed,
        p.is_blocked,
        j.user_id AS job_user_id
    FROM download_job_items i
    INNER JOIN download_jobs j ON j.id = i.job_id
    INNER JOIN photos p ON p.id = i.photo_id
    WHERE i.id = ?
      AND i.job_id = ?
      AND j.user_id = ?
    LIMIT 1
");
$stmt->execute([$itemId, $jobId, $userId]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit('Položka fronty nebyla nalezena.');
}

if ((int)$row['locked_by_user_id'] !== $userId) {
    http_response_code(403);
    exit('Fotografie není zamčena tímto uživatelem.');
}

if ((int)($row['event_photographer_allowed'] ?? 1) !== 1) {
    http_response_code(403);
    exit('Fotografie není aktivní pro tento event.');
}

if (!empty($row['is_blocked'])) {
    http_response_code(403);
    exit('Fotografie je zablokovaná.');
}

if ($row['photo_status'] !== 'locked') {
    http_response_code(409);
    exit('Fotografie už není ve stavu locked.');
}

$path = (string)$row['filepath'];
$filename = (string)$row['filename'];

if ($path === '' || !is_file($path)) {
    $pdo->prepare("
        UPDATE download_job_items
        SET status = 'failed',
            reason = 'file_missing'
        WHERE id = ?
    ")->execute([$itemId]);

    http_response_code(404);
    exit('Soubor nebyl nalezen.');
}

/*
 * Označení položky jako stažené a přepnutí fotky do downloaded.
 * Pro vaše workflow je to správný okamžik:
 * server právě soubor vydává ke stažení.
 */
$pdo->beginTransaction();

try {
    $pdo->prepare("
        UPDATE download_job_items
        SET status = 'downloaded',
            downloaded_at = NOW()
        WHERE id = ?
    ")->execute([$itemId]);

    $pdo->prepare("
        UPDATE photos
        SET status = 'downloaded',
            downloaded = 1,
            downloaded_by_user_id = CASE
                WHEN downloaded_at IS NULL THEN ?
                ELSE downloaded_by_user_id
            END,
            downloaded_at = COALESCE(downloaded_at, NOW())
        WHERE id = ?
          AND status = 'locked'
          AND locked_by_user_id = ?
          AND event_photographer_allowed = 1
          AND is_blocked = 0
    ")->execute([
        $userId,
        (int)$row['photo_id'],
        $userId
    ]);

    $pdo->prepare("
        INSERT INTO photo_log (photo_id, user_id, action, ip, created_at)
        VALUES (?, ?, 'downloaded', ?, NOW())
    ")->execute([
        (int)$row['photo_id'],
        $userId,
        inet_pton($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    exit('Chyba při přípravě downloadu.');
}

/*
 * Pošli soubor browseru
 */
$mime = 'application/octet-stream';
if (function_exists('mime_content_type')) {
    $detected = @mime_content_type($path);
    if (is_string($detected) && $detected !== '') {
        $mime = $detected;
    }
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Content-Length: ' . (string)filesize($path));
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($path);
exit;
