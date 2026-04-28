<?php

declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();

$pdo = db();

$rows = $pdo->query(
    "SELECT pd.decision_id, pd.snapshot_id, pd.entropy_id, pd.decision_type, pd.decided_at,
            u.name AS universe_name, s.version_number, em.entropy_score
     FROM preservation_decisions pd
     JOIN state_snapshots s ON s.snapshot_id = pd.snapshot_id
     JOIN universes u ON u.universe_id = s.universe_id
     JOIN entropy_metrics em ON em.entropy_id = pd.entropy_id
     ORDER BY pd.decided_at DESC"
)->fetchAll();

$stats = $pdo->query('SELECT * FROM preservation_stats')->fetchAll();

$title = 'Preservation Decisions';
$active = 'decisions';
include __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-body">
        <h2 class="h5">Preservation Stats</h2>
        <div class="table-responsive mt-3">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Decision</th>
                <th class="text-end">Count</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($stats as $s): ?>
                <tr>
                  <td><?= h($s['decision_type']) ?></td>
                  <td class="text-end"><?= h((string)$s['decisions_count']) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$stats): ?>
                <tr><td colspan="2" class="text-muted">No decisions yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-body">
        <h2 class="h5">Decision List</h2>
        <div class="table-responsive mt-3">
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Snapshot</th>
                <th>Universe</th>
                <th>Version</th>
                <th>Entropy</th>
                <th>Decision</th>
                <th>Decided</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= h((string)$r['decision_id']) ?></td>
                  <td>#<?= h((string)$r['snapshot_id']) ?></td>
                  <td><?= h($r['universe_name']) ?></td>
                  <td><?= h((string)$r['version_number']) ?></td>
                  <td><?= h(number_format((float)$r['entropy_score'], 6)) ?></td>
                  <td><?= h($r['decision_type']) ?></td>
                  <td><?= h((string)$r['decided_at']) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-muted">No decisions yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
