<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/db.php';

require_login();

if (!has_permission('users.manage')) {
    http_response_code(403);
    exit('Přístup odepřen.');
}

$eventId = max(0, (int)($_GET['id'] ?? 0));
$format = strtolower(trim((string)($_GET['format'] ?? 'csv')));
if (!in_array($format, ['csv', 'xls'], true)) {
    $format = 'csv';
}

$event = $eventId > 0 ? events_get($eventId) : null;
if (!$event) {
    http_response_code(404);
    exit('Event nebyl nalezen.');
}

$table = events_report_table(events_report_rows($eventId));

if ($format === 'xls') {
    $filename = events_report_download_filename($event, 'xls');

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    echo "\xEF\xBB\xBF";
    echo "<!doctype html>\n";
    echo "<html><head><meta charset=\"utf-8\"></head><body>\n";
    echo "<table border=\"1\">\n";

    foreach ($table as $rowIndex => $row) {
        echo "<tr>";
        foreach ($row as $value) {
            $tag = $rowIndex === 0 ? 'th' : 'td';
            echo '<' . $tag . '>' . h((string)$value) . '</' . $tag . '>';
        }
        echo "</tr>\n";
    }

    echo "</table>\n";
    echo "</body></html>\n";
    exit;
}

$filename = events_report_download_filename($event, 'csv');

$csvEncoding = 'Windows-1250';

header('Content-Type: text/csv; charset=windows-1250');
header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$out = fopen('php://output', 'wb');
if ($out === false) {
    http_response_code(500);
    exit('Export se nepodařilo připravit.');
}

fwrite($out, "sep=;\r\n");

foreach ($table as $row) {
    $encodedRow = array_map(static function (string $value) use ($csvEncoding): string {
        $encoded = iconv('UTF-8', $csvEncoding . '//TRANSLIT//IGNORE', $value);
        return is_string($encoded) ? $encoded : $value;
    }, $row);

    fputcsv($out, $encodedRow, ';', '"', '\\', "\r\n");
}

fclose($out);
