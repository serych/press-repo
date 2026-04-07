<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/chat.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$userId = (int)($user['id'] ?? 0);

echo json_encode(
    chat_unread_summary_for_user($userId),
    JSON_UNESCAPED_UNICODE
);