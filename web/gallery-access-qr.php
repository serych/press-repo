<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/gallery_access.php';

require_login();

$eventId = max(0, (int)($_GET['event_id'] ?? 0));
$event = $eventId > 0 ? events_get($eventId) : null;
$access = $event ? gallery_access_get($eventId) : null;
$dashboardEvent = events_get_current_dashboard_event();
$isCurrentDashboardEvent = $dashboardEvent && (int)$dashboardEvent['id'] === $eventId;

if (!has_permission('users.manage') && !$isCurrentDashboardEvent) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

if (!$event || !$access || empty($access['token'])) {
    http_response_code(404);
    exit('QR kód nebyl nalezen.');
}

$url = gallery_access_url($access);
$download = !empty($_GET['download']);
$filename = 'galerie-' . preg_replace('~[^a-z0-9_-]+~i', '-', (string)$event['slug']) . '-' . (string)$access['token'] . '-print.png';

// Vysoké rozlišení pro tisk kartiček. CSS v administraci si obrázek jen zmenší pro náhled.
$cmd = 'qrencode -t PNG -s 32 -m 4 -o - ' . escapeshellarg($url);
$png = shell_exec($cmd);

if (!is_string($png) || $png === '') {
    http_response_code(500);
    exit('QR kód se nepodařilo vygenerovat.');
}

header('Content-Type: image/png');
header('Content-Length: ' . strlen($png));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . rawurlencode($filename) . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');

echo $png;
