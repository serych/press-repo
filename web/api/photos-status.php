<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/db.php';

require_login();

if (!has_permission('photos.view')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

const FTP_ROOT = '/var/www/press/ftp';
const UPLOADING_RECENT_SECONDS = 300;

function get_extension(string $file): string
{
    return strtolower(pathinfo($file, PATHINFO_EXTENSION));
}

function is_supported_upload_file(string $file): bool
{
    $ext = get_extension($file);

    return in_array($ext, [
        'cr2','cr3','nef','nrw','arw','sr2','srf','raf','rw2','orf','dng','pef','iiq','3fr','jpg','jpeg'
    ], true);
}

function should_ignore_upload_file(string $file): bool
{
    $filename = basename($file);
    $lower = strtolower($filename);

    if ($filename === '' || $filename[0] === '.') {
        return true;
    }

    foreach (['.thumb.jpg', '.thumb.jpeg', '.tmp', '.part', '.swp', '.swx', '.ds_store'] as $suffix) {
        if (str_ends_with($lower, $suffix)) {
            return true;
        }
    }

    return false;
}

function count_uploading_files_for_user(PDO $pdo, string $ftpUser): int
{
    $userRoot = FTP_ROOT . '/' . $ftpUser;

    if (!is_dir($userRoot)) {
        return 0;
    }

    $stmt = $pdo->prepare("
        SELECT filepath
        FROM photos
        WHERE ftp_user = ?
    ");
    $stmt->execute([$ftpUser]);

    $knownFiles = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $path) {
        $knownFiles[(string)$path] = true;
    }

    $now = time();
    $count = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($userRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        $path = $fileInfo->getPathname();

        if (should_ignore_upload_file($path)) {
            continue;
        }

        if (!is_supported_upload_file($path)) {
            continue;
        }

        if (isset($knownFiles[$path])) {
            continue;
        }

        if ($fileInfo->getSize() <= 0) {
            continue;
        }

        if (($now - $fileInfo->getMTime()) > UPLOADING_RECENT_SECONDS) {
            continue;
        }

        $count++;
    }

    return $count;
}

$user = current_user();
$userId = (int)($user['id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    exit('Neplatný uživatel.');
}

$pdo = db();

$stmt = $pdo->prepare("
    SELECT
        u.ftp_user
    FROM users u
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userRow) {
    http_response_code(404);
    exit('Uživatel nebyl nalezen.');
}

$ftpUser = trim((string)($userRow['ftp_user'] ?? ''));

if ($ftpUser === '') {
    http_response_code(400);
    exit('U uživatele chybí FTP účet.');
}

$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.filename,
        p.preview_filepath,
        p.status,
        p.uploaded_at,
        p.locked_by_user_id,
        lu.user AS locked_by_user,
        lu.jmeno AS locked_jmeno,
        lu.prijmeni AS locked_prijmeni
    FROM photos p
    LEFT JOIN users lu ON lu.id = p.locked_by_user_id
    WHERE p.ftp_user = ?
      AND p.status <> 'deleted'
    ORDER BY p.uploaded_at DESC, p.id DESC
    LIMIT 200
");
$stmt->execute([$ftpUser]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM photos
    WHERE ftp_user = ?
      AND status = 'processing'
");
$stmt->execute([$ftpUser]);
$processingCount = (int)$stmt->fetchColumn();

$uploadingCount = count_uploading_files_for_user($pdo, $ftpUser);

$data = [];

foreach ($rows as $row) {
    $lockedByName = trim(
        ((string)($row['locked_jmeno'] ?? '')) . ' ' .
        ((string)($row['locked_prijmeni'] ?? ''))
    );

    $statusText = 'připraveno';
    $statusClass = 'status-ready';
    $statusNote = '';

    switch ((string)$row['status']) {
        case 'downloaded':
            $statusText = 'staženo';
            $statusClass = 'status-downloaded';
            break;

        case 'locked':
            $statusText = 'zamknuto';
            $statusClass = 'status-locked';

            if ($lockedByName !== '') {
                $statusNote = $lockedByName;
            } elseif (!empty($row['locked_by_user'])) {
                $statusNote = (string)$row['locked_by_user'];
            }
            break;

        case 'processing':
            $statusText = 'zpracování';
            $statusClass = 'status-processing';
            break;

        case 'uploaded':
            $statusText = 'nahráno';
            $statusClass = 'status-uploaded';
            break;

        case 'error':
            $statusText = 'chyba';
            $statusClass = 'status-error';
            break;
    }

    $data[] = [
        'id' => (int)$row['id'],
        'filename' => (string)$row['filename'],
        'preview_url' => !empty($row['preview_filepath'])
            ? '/preview.php?id=' . (int)$row['id']
            : '',
        'status' => (string)$row['status'],
        'status_text' => $statusText,
        'status_class' => $statusClass,
        'status_note' => $statusNote,
        'uploaded_at' => (string)$row['uploaded_at'],
    ];
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo json_encode([
    'ok' => true,
    'ftp_user' => $ftpUser,
    'uploading_count' => $uploadingCount,
    'processing_count' => $processingCount,
    'items' => $data,
], JSON_UNESCAPED_UNICODE);