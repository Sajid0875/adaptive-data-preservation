<?php
// includes/header.php

declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$title = $title ?? 'Universe Preservation System';
$active = $active ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
  <aside class="sidebar border-end">
    <div class="p-3">
      <div class="fw-bold">Universe System</div>
      <div class="text-muted small">Entropy-aware preservation</div>
    </div>
    <nav class="nav flex-column px-2 pb-3">
      <a class="nav-link <?= $active === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">Dashboard</a>
      <a class="nav-link <?= $active === 'universes' ? 'active' : '' ?>" href="universes.php">Universes</a>
      <a class="nav-link <?= $active === 'snapshots' ? 'active' : '' ?>" href="snapshots.php">Snapshots</a>
      <a class="nav-link <?= $active === 'changes' ? 'active' : '' ?>" href="state_changes.php">State Changes</a>
      <a class="nav-link <?= $active === 'entropy' ? 'active' : '' ?>" href="entropy.php">Entropy</a>
      <a class="nav-link <?= $active === 'decisions' ? 'active' : '' ?>" href="decisions.php">Decisions</a>
      <a class="nav-link <?= $active === 'archives' ? 'active' : '' ?>" href="archives.php">Archives / Compression</a>
      <a class="nav-link <?= $active === 'integrity' ? 'active' : '' ?>" href="integrity_checks.php">Integrity Checks</a>
      <a class="nav-link <?= $active === 'audit' ? 'active' : '' ?>" href="audit_logs.php">Audit Logs</a>
      <hr>
      <a class="nav-link" href="login.php?logout=1">Logout</a>
    </nav>
  </aside>

  <main class="flex-grow-1">
    <header class="border-bottom bg-white">
      <div class="container-fluid py-3">
        <h1 class="h4 m-0"><?= h($title) ?></h1>
      </div>
    </header>
    <div class="container-fluid py-4">
