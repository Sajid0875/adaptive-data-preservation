<?php

declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();

$pdo = db();

$rows = $pdo->query(
    "SELECT ic.check_id, ic.snapshot_id, ic.status, ic.checked_at,
            u.name AS universe_name, s.version_number
     FROM integrity_checks ic
     JOIN state_snapshots s ON s.snapshot_id = ic.snapshot_id
     JOIN universes u ON u.universe_id = s.universe_id
     ORDER BY ic.checked_at DESC"
)->fetchAll();

$title = 'Integrity Checks';
$active = 'integrity';
include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-body">
    <h2 class="h5">Validation Results</h2>
    <div class="table-responsive mt-3">
      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th>Check ID</th>
            <th>Snapshot</th>
            <th>Universe</th>
            <th>Version</th>
            <th>Status</th>
            <th>Checked</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= h((string)$r['check_id']) ?></td>
              <td>#<?= h((string)$r['snapshot_id']) ?></td>
              <td><?= h($r['universe_name']) ?></td>
              <td><?= h((string)$r['version_number']) ?></td>
              <td>
                <?php if ($r['status'] === 'VALID'): ?>
                  <span class="badge text-bg-success">VALID</span>
                <?php else: ?>
                  <span class="badge text-bg-danger">CORRUPTED</span>
                <?php endif; ?>
              </td>
              <td><?= h((string)$r['checked_at']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-muted">No integrity checks yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
