<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/functions.php';

if (is_logged_in()) {
    redirect(default_homepage_for_user(current_user()));
}

redirect('/login.php');