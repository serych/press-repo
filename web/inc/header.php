<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$user = current_user();
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="wrap">
        <div class="brand"><?= h(APP_NAME) ?></div>
        <?php if ($user): ?>
        <nav class="top-nav">
            <a href="/dashboard.php">Dashboard</a>
            <a href="/photos.php">Fotografie</a>

            <?php if (has_permission('users.manage')): ?>
                <a href="/users.php">Uživatelé</a>
            <?php endif; ?>

            <a href="/logout.php">Odhlásit</a>
        </nav>
        <?php endif; ?>
    </div>
</header>
<main class="wrap">