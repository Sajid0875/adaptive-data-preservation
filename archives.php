<?php

declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();

$pdo = db();

$archives = $pdo->query(
    "SELECT a.archive_id, a.snapshot_id, a.archive_location, a.archived_at,
            u.name AS universe_name, s.version_number, em.entropy_score, pd.decision_type
     FROM archives a
     JOIN state_snapshots s ON s.snapshot_id = a.snapshot_id
     JOIN universes u ON u.universe_id = s.universe_id
     LEFT JOIN entropy_metrics em ON em.snapshot_id = s.snapshot_id
     LEFT JOIN preservation_decisions pd ON pd.snapshot_id = s.snapshot_id
     ORDER BY a.archived_at DESC"
)->fetchAll();

$compressed = $pdo->query(
    "SELECT cs.compressed_id, cs.snapshot_id, cs.compression_ratio, cs.compressed_at,
            u.name AS universe_name, s.version_number, em.entropy_score, pd.decision_type
     FROM compressed_states cs
     JOIN state_snapshots s ON s.snapshot_id = cs.snapshot_id
     JOIN universes u ON u.universe_id = s.universe_id
     LEFT JOIN entropy_metrics em ON em.snapshot_id = s.snapshot_id
     LEFT JOIN preservation_decisions pd ON pd.snapshot_id = s.snapshot_id
     ORDER BY cs.compressed_at DESC"
)->fetchAll();

$title = 'Archives / Compression';
$active = 'archives';
include __DIR__ . '/includes/header.php';
?>

<div class="card">
  <div class="card-body">
    <h2 class="h5">Archived Snapshots</h2>
    <div class="table-responsive mt-3">
      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th>Archive ID</th>
            <th>Snapshot</th>
            <th>Universe</th>
            <th>Version</th>
            <th>Entropy</th>
            <th>Decision</th>
            <th>Location</th>
            <th>Archived</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($archives as $a): ?>
            <tr>
              <td><?= h((string)$a['archive_id']) ?></td>
              <td>#<?= h((string)$a['snapshot_id']) ?></td>
              <td><?= h($a['universe_name']) ?></td>
              <td><?= h((string)$a['version_number']) ?></td>
              <td><?= h(number_format((float)($a['entropy_score'] ?? 0), 6)) ?></td>
              <td><?= h((string)($a['decision_type'] ?? '')) ?></td>
              <td class="text-truncate" style="max-width: 260px;"><?= h($a['archive_location']) ?></td>
              <td><?= h((string)$a['archived_at']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$archives): ?>
            <tr><td colspan="8" class="text-muted">No archives yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<hr class="my-4">

<div class="card">
  <div class="card-body">
    <h2 class="h5">Compressed Snapshots</h2>
    <div class="table-responsive mt-3">
      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th>Compressed ID</th>
            <th>Snapshot</th>
            <th>Universe</th>
            <th>Version</th>
            <th>Entropy</th>
            <th>Decision</th>
            <th>Ratio</th>
            <th>Compressed</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($compressed as $c): ?>
            <tr>
              <td><?= h((string)$c['compressed_id']) ?></td>
              <td>#<?= h((string)$c['snapshot_id']) ?></td>
              <td><?= h($c['universe_name']) ?></td>
              <td><?= h((string)$c['version_number']) ?></td>
              <td><?= h(number_format((float)($c['entropy_score'] ?? 0), 6)) ?></td>
              <td><?= h((string)($c['decision_type'] ?? '')) ?></td>
              <td><?= h((string)$c['compression_ratio']) ?></td>
              <td><?= h((string)$c['compressed_at']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$compressed): ?>
            <tr><td colspan="8" class="text-muted">No compressed states yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
