<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/help.php';

require_login();

$user = current_user();
if (!help_can_view($user)) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$id = max(0, (int)($_GET['id'] ?? 0));
$inline = (string)($_GET['mode'] ?? '') === 'view';
$document = $id > 0 ? help_document_get($id) : null;

if (!$document || empty($document['filepath']) || !is_file((string)$document['filepath'])) {
    http_response_code(404);
    exit('Dokument nebyl nalezen.');
}

$filepath = (string)$document['filepath'];
$filename = basename((string)($document['filename'] ?? 'napoveda.pdf'));
if ($filename === '') {
    $filename = 'napoveda.pdf';
}

header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($filename) . '"');
header('Content-Length: ' . (string)filesize($filepath));
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($filepath);
exit;
