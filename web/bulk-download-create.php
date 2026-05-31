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

function bulk_download_error_page(string $message, int $statusCode = 400): never
{
    http_response_code($statusCode);

    require_once __DIR__ . '/inc/header.php';
    ?>
    <section class="panel">
        <div class="page-head">
            <h1>Hromadné stažení</h1>
            <a href="/photos.php" class="button">Zpět na fotografie</a>
        </div>

        <div class="card">
            <div class="alert-info">
                <?= h($message) ?>
            </div>
        </div>
    </section>
    <?php
    require_once __DIR__ . '/inc/footer.php';
    exit;
}

// Vezmeme všechny fotky zamčené tímto uživatelem.
$stmt = $pdo->prepare("
    SELECT id
    FROM photos
    WHERE status = 'locked'
      AND locked_by_user_id = ?
      AND event_photographer_allowed = 1
      AND is_blocked = 0
    ORDER BY uploaded_at ASC, id ASC
");
$stmt->execute([$userId]);

$ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

if (!$ids) {
    bulk_download_error_page('Nemáte žádné zamčené fotografie ke stažení.');
}

$pdo->beginTransaction();

try {
    // Vytvoření jobu.
    $stmt = $pdo->prepare("
        INSERT INTO download_jobs (user_id, status)
        VALUES (?, 'prepared')
    ");
    $stmt->execute([$userId]);

    $jobId = (int)$pdo->lastInsertId();

    // Insert položek.
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
            $seq++,
        ]);
    }

    $pdo->commit();

    $total = count($ids);

    // Redirect zpět do galerie s parametry pro spuštění downloadu.
    header("Location: /photos.php?download_job=" . $jobId . "&download_total=" . $total);
    exit;
} catch (Throwable $e) {
    $pdo->rollBack();
    bulk_download_error_page('Hromadný download se nepodařilo připravit.', 500);
}
