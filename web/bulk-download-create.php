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

/*
 * vezmeme všechny fotky locked tímto uživatelem
 */
$stmt = $pdo->prepare("
    SELECT id
    FROM photos
    WHERE status = 'locked'
      AND locked_by_user_id = ?
      AND event_photographer_allowed = 1
    ORDER BY uploaded_at ASC, id ASC
");
$stmt->execute([$userId]);

$ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

if (!$ids) {
    http_response_code(400);
    exit('Nemáte žádné zamèené fotografie ke stažení.');
}

$pdo->beginTransaction();

try {
    /*
     * vytvoøení jobu
     */
    $stmt = $pdo->prepare("
        INSERT INTO download_jobs (user_id, status)
        VALUES (?, 'prepared')
    ");
    $stmt->execute([$userId]);

    $jobId = (int)$pdo->lastInsertId();

    /*
     * insert položek
     */
    $insert = $pdo->prepare("
        INSERT INTO download_job_items
        (job_id, photo_id, seq_no, status, locked_at)
        VALUES (?, ?, ?, 'queued', NOW())
    ");

    $seq = 1;

    foreach ($ids as $photoId) {
        $insert->execute([
            $jobId,
            $photoId,
            $seq++
        ]);
    }

    $pdo->commit();

    $total = count($ids);

    /*
     * redirect zpìt do galerie s parametry pro spuštìní downloadu
     */
    header("Location: /photos.php?download_job=" . $jobId . "&download_total=" . $total);
    exit;

} catch (Throwable $e) {
    $pdo->rollBack();

    http_response_code(500);
    echo "bulk create failed";
}