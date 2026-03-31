<?php
declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
function default_homepage_for_user(?array $user = null): string
{
    $user = $user ?? current_user();

    if (!$user) {
        return '/login.php';
    }

    $roleCode = (string)($user['role_code'] ?? '');

    switch ($roleCode) {
        case 'photographer':
            return '/photos-status.php'; // mini verze pro mobil
        default:
            return '/photos.php';
    }
}