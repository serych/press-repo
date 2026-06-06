<?php
declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_bytes_human(?int $bytes): string
{
    if ($bytes === null || $bytes < 0) {
        return '—';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = (float)$bytes;
    $unitIndex = 0;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
        $value /= 1024;
        $unitIndex++;
    }

    if ($unitIndex === 0) {
        return (string)$bytes . ' B';
    }

    $decimals = $value >= 10 ? 1 : 2;
    $formatted = number_format($value, $decimals, ',', ' ');
    $formatted = rtrim(rtrim($formatted, '0'), ',');

    return $formatted . ' ' . $units[$unitIndex];
}

function redirect(string $url): never
{
    header("Location: {$url}");
    exit;
}

function post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}
function is_probably_mobile_device(): bool
{
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

    if ($ua === '') {
        return false;
    }

    $needles = [
        'android',
        'iphone',
        'ipad',
        'ipod',
        'mobile',
        'opera mini',
        'windows phone',
        'blackberry'
    ];

    foreach ($needles as $needle) {
        if (strpos($ua, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function default_homepage_for_user(?array $user = null): string
{
    $user = $user ?? current_user();

    if (!$user) {
        return '/login.php';
    }

    $roleCode = (string)($user['role_code'] ?? '');

    if ($roleCode === 'journalist') {
        return '/galerie.php';
    }

    if ($roleCode === 'photographer') {
        return '/photos-status.php';
    }

    if ($roleCode === 'press_operator') {
        return is_probably_mobile_device() ? '/photos-status.php' : '/photos.php';
    }

    return '/dashboard.php';
}
