<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/photos.php';
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
        'cr2', 'cr3', 'nef', 'nrw', 'arw', 'sr2', 'srf', 'raf', 'rw2', 'orf', 'dng', 'pef', 'iiq', '3fr', 'jpg', 'jpeg'
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

function count_uploading_files(PDO $pdo, ?string $ftpUserFilter = null): int
{
    $knownFiles = [];

    if ($ftpUserFilter !== null && $ftpUserFilter !== '') {
        $stmt = $pdo->prepare("
            SELECT filepath
            FROM photos
            WHERE ftp_user = ?
        ");
        $stmt->execute([$ftpUserFilter]);

        $roots = [FTP_ROOT . '/' . $ftpUserFilter];
    } else {
        $stmt = $pdo->query("
            SELECT filepath
            FROM photos
        ");

        $roots = [];
        foreach (glob(FTP_ROOT . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $roots[] = $dir;
        }
    }

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $path) {
        $knownFiles[(string)$path] = true;
    }

    $now = time();
    $count = 0;

    foreach ($roots as $root) {
        if (!is_dir($root)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
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
    }

    return $count;
}

$ftpUser = isset($_GET['ftp_user']) ? trim((string)$_GET['ftp_user']) : '';
$status  = isset($_GET['status']) ? trim((string)($_GET['status'] ?? '')) : '';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;
$offset = ($page - 1) * $perPage;
$currentEvent = photos_get_current_event();
$currentEventId = !empty($currentEvent['id']) ? (int)$currentEvent['id'] : 0;

$filters = [
    'event_id' => $currentEventId,
    'ftp_user' => $ftpUser,
    'status'   => $status,
];

$total = photos_count($filters);
$photos = photos_feed($filters, $perPage, $offset);
$totalPages = max(1, (int)ceil($total / $perPage));

$currentUser = current_user();
$currentUserId = (int)$currentUser['id'];

$lockedMineCount = 0;
foreach ($photos as $p) {
    if (
        ($p['status'] ?? '') === 'locked'
        && (int)($p['locked_by_user_id'] ?? 0) === $currentUserId
    ) {
        $lockedMineCount++;
    }
}

$pdo = db();

if ($ftpUser !== '') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM photos
        WHERE status = 'processing'
          AND ftp_user = ?
    ");
    $stmt->execute([$ftpUser]);
} else {
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM photos
        WHERE status = 'processing'
    ");
}

$processingCount = (int)$stmt->fetchColumn();
$uploadingCount = count_uploading_files($pdo, $ftpUser !== '' ? $ftpUser : null);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo json_encode([
    'ok' => true,
    'current_user_id' => $currentUserId,
    'locked_mine_count' => $lockedMineCount,
    'uploading_count' => $uploadingCount,
    'processing_count' => $processingCount,
    'total' => $total,
    'total_pages' => $totalPages,
    'items' => array_map(static function (array $p): array {
        return [
            'id' => (int)$p['id'],
            'filename' => (string)$p['filename'],
            'ftp_user' => (string)$p['ftp_user'],
            'status' => (string)$p['status'],
            'uploaded_at' => (string)$p['uploaded_at'],
            'published_duration_label' => (!empty($p['first_published_at']) && !empty($p['captured_at']))
                ? photos_format_duration_between((string)$p['captured_at'], (string)$p['first_published_at'])
                : '',
            'preview_exists' => !empty($p['preview_filepath']),
            'locked_by_user_id' => !empty($p['locked_by_user_id']) ? (int)$p['locked_by_user_id'] : null,
            'locked_by_user' => (string)($p['locked_by_user'] ?? ''),
            'locked_jmeno' => (string)($p['locked_jmeno'] ?? ''),
            'locked_prijmeni' => (string)($p['locked_prijmeni'] ?? ''),
            'exif_problem' => !empty($p['exif_problem']),
            'exif_problem_note' => (string)($p['exif_problem_note'] ?? ''),
            'event_photographer_allowed' => photos_is_event_photographer_allowed($p),
        ];
    }, $photos),
], JSON_UNESCAPED_UNICODE);
