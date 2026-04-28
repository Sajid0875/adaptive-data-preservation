<?php

declare(strict_types=1);
require_once __DIR__ . '/config/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!empty($_SESSION['is_admin'])) {
    redirect('dashboard.php');
}
redirect('login.php');
