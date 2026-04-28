<?php
// config/db.php

declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '5432';
    $name = getenv('DB_NAME') ?: 'universe_preservation';
    $user = getenv('DB_USER') ?: 'postgres';
    $pass = getenv('DB_PASS') ?: '';

    $dsn = "pgsql:host={$host};port={$port};dbname={$name}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

function require_login(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['is_admin'])) {
        redirect('login.php');
    }
}

function post(string $key, $default = null) {
    return $_POST[$key] ?? $default;
}

function get(string $key, $default = null) {
    return $_GET[$key] ?? $default;
}
