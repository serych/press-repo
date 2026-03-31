<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/db.php';

require_login();

$user = current_user();
$userId = (int)$user['id'];
$pdo = db();

$jobId = (int)($_GET['job'] ?? 0);

$stmt = $pdo->prepare("
SELECT
COUNT(*) total,
SUM(status='downloaded') downloaded
FROM download_job_items
WHERE job_id=?
");

$stmt->execute([$jobId]);
$row = $stmt->fetch();

$next = $pdo->prepare("
SELECT id
FROM download_job_items
WHERE job_id=?
AND status='queued'
ORDER BY seq_no
LIMIT 1
");

$next->execute([$jobId]);
$nextId = $next->fetchColumn();

echo json_encode([
    'total' => (int)$row['total'],
    'downloaded' => (int)$row['downloaded'],
    'next_item' => $nextId ? (int)$nextId : null
]);