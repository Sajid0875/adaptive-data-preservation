<?php

declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();

$pdo = db();

$totalUniverses = (int)$pdo->query('SELECT COUNT(*) FROM universes')->fetchColumn();
$totalSnapshots = (int)$pdo->query('SELECT COUNT(*) FROM state_snapshots')->fetchColumn();
$avgEntropy = $pdo->query('SELECT COALESCE(AVG(entropy_score),0) FROM entropy_metrics')->fetchColumn();

$decisionCounts = [
    'DISCARD' => 0,
    'COMPRESS' => 0,
    'PRESERVE' => 0,
    'ARCHIVE' => 0,
];
$stmt = $pdo->query('SELECT decision_type, COUNT(*) AS c FROM preservation_decisions GROUP BY decision_type');
foreach ($stmt as $row) {
    $decisionCounts[$row['decision_type']] = (int)$row['c'];
}

$title = 'Dashboard';
$active = 'dashboard';
include __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
  <div class="col-md-3">
    <div class="card">
      <div class="card-body">
        <div class="text-muted small">Total Universes</div>
        <div class="display-6"><?= h((string)$totalUniverses) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card">
      <div class="card-body">
        <div class="text-muted small">Total Snapshots</div>
        <div class="display-6"><?= h((string)$totalSnapshots) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card">
      <div class="card-body">
        <div class="text-muted small">Average Entropy</div>
        <div class="display-6"><?= h(number_format((float)$avgEntropy, 6)) ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card">
      <div class="card-body">
        <div class="text-muted small">Decision Coverage</div>
        <div class="small mt-2">
          <div>DISCARD: <strong><?= h((string)$decisionCounts['DISCARD']) ?></strong></div>
          <div>COMPRESS: <strong><?= h((string)$decisionCounts['COMPRESS']) ?></strong></div>
          <div>PRESERVE: <strong><?= h((string)$decisionCounts['PRESERVE']) ?></strong></div>
          <div>ARCHIVE: <strong><?= h((string)$decisionCounts['ARCHIVE']) ?></strong></div>
        </div>
      </div>
    </div>
  </div>
</div>

<hr class="my-4">

<div class="row g-3">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <h2 class="h5 m-0">Recent Snapshot Summary</h2>
          <a class="btn btn-sm btn-outline-primary" href="snapshots.php">Manage Snapshots</a>
        </div>
        <div class="table-responsive mt-3">
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>Universe</th>
                <th>Snapshot</th>
                <th>Version</th>
                <th>Size (MB)</th>
                <th>Entropy</th>
                <th>Decision</th>
                <th>Integrity</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $rows = $pdo->query('SELECT * FROM snapshot_summary ORDER BY snapshot_created_at DESC LIMIT 10')->fetchAll();
              foreach ($rows as $r):
              ?>
                <tr>
                  <td><?= h($r['universe_name']) ?></td>
                  <td>#<?= h((string)$r['snapshot_id']) ?></td>
                  <td><?= h((string)$r['version_number']) ?></td>
                  <td><?= h((string)$r['snapshot_size_mb']) ?></td>
                  <td><?= h(number_format((float)($r['entropy_score'] ?? 0), 6)) ?></td>
                  <td><?= h((string)($r['decision_type'] ?? '')) ?></td>
                  <td><?= h((string)($r['integrity_status'] ?? '')) ?></td>
                  <td><?= h((string)$r['snapshot_created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-muted">No snapshots yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
