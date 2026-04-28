<?php

declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();

$pdo = db();

$rows = $pdo->query(
    "SELECT em.entropy_id, em.snapshot_id, em.entropy_score, em.calculated_at,
            u.name AS universe_name, s.version_number, s.snapshot_size_mb, s.created_at AS snapshot_created_at
     FROM entropy_metrics em
     JOIN state_snapshots s ON s.snapshot_id = em.snapshot_id
     JOIN universes u ON u.universe_id = s.universe_id
     ORDER BY em.calculated_at DESC"
)->fetchAll();

$title = 'Entropy Metrics';
$active = 'entropy';
include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-body">
    <h2 class="h5">Entropy Values</h2>
    <div class="table-responsive mt-3">
      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th>Entropy ID</th>
            <th>Snapshot</th>
            <th>Universe</th>
            <th>Version</th>
            <th>Size (MB)</th>
            <th>Entropy</th>
            <th>Calculated</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= h((string)$r['entropy_id']) ?></td>
              <td>#<?= h((string)$r['snapshot_id']) ?></td>
              <td><?= h($r['universe_name']) ?></td>
              <td><?= h((string)$r['version_number']) ?></td>
              <td><?= h((string)$r['snapshot_size_mb']) ?></td>
              <td><?= h(number_format((float)$r['entropy_score'], 6)) ?></td>
              <td><?= h((string)$r['calculated_at']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-muted">No entropy metrics yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<hr class="my-4">

<div class="card">
  <div class="card-body">
    <h2 class="h5">Entropy Trends (Daily)</h2>
    <div class="table-responsive mt-3">
      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th>Universe</th>
            <th>Day</th>
            <th>Avg Entropy</th>
            <th>Max Entropy</th>
            <th>Snapshots</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $trend = $pdo->query('SELECT * FROM entropy_trends ORDER BY universe_id, day')->fetchAll();
          foreach ($trend as $t):
          ?>
            <tr>
              <td><?= h($t['universe_name']) ?></td>
              <td><?= h((string)$t['day']) ?></td>
              <td><?= h(number_format((float)$t['avg_entropy'], 6)) ?></td>
              <td><?= h(number_format((float)$t['max_entropy'], 6)) ?></td>
              <td><?= h((string)$t['snapshots_count']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$trend): ?>
            <tr><td colspan="5" class="text-muted">No trend data yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
