<?php

declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();

$pdo = db();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $universeId = (int)post('universe_id', 0);
        $versionNumber = (int)post('version_number', 0);
        $sizeMb = (float)post('snapshot_size_mb', 0);

        if ($universeId <= 0) {
            throw new RuntimeException('Universe is required.');
        }
        if ($versionNumber <= 0) {
            throw new RuntimeException('Version number must be > 0.');
        }
        if ($sizeMb <= 0) {
            throw new RuntimeException('Snapshot size must be > 0 MB.');
        }

        $stmt = $pdo->prepare('INSERT INTO state_snapshots(universe_id, version_number, snapshot_size_mb) VALUES (:u, :v, :s)');
        $stmt->execute([':u' => $universeId, ':v' => $versionNumber, ':s' => $sizeMb]);
        $message = 'Snapshot added. Entropy + decision computed automatically.';
    } catch (Throwable $t) {
        $error = $t->getMessage();
    }
}

$universes = $pdo->query('SELECT universe_id, name FROM universes ORDER BY name')->fetchAll();

$rows = $pdo->query('SELECT * FROM snapshot_summary ORDER BY snapshot_created_at DESC')->fetchAll();

$title = 'Snapshots';
$active = 'snapshots';
include __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
  <div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-body">
        <h2 class="h5">Add Snapshot</h2>
        <form method="post" class="vstack gap-3 mt-3">
          <div>
            <label class="form-label">Universe</label>
            <select class="form-select" name="universe_id" required>
              <option value="">Select...</option>
              <?php foreach ($universes as $u): ?>
                <option value="<?= h((string)$u['universe_id']) ?>"><?= h($u['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Version Number</label>
            <input type="number" class="form-control" name="version_number" min="1" required>
          </div>
          <div>
            <label class="form-label">Snapshot Size (MB)</label>
            <input type="number" step="0.001" class="form-control" name="snapshot_size_mb" min="0.001" required>
          </div>
          <button class="btn btn-primary" type="submit">Add Snapshot</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-body">
        <h2 class="h5">All Snapshots</h2>
        <div class="table-responsive mt-3">
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>Universe</th>
                <th>Snapshot</th>
                <th>Version</th>
                <th>Size</th>
                <th>Entropy</th>
                <th>Decision</th>
                <th>Integrity</th>
                <th>Compressed</th>
                <th>Archived</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= h($r['universe_name']) ?></td>
                  <td>#<?= h((string)$r['snapshot_id']) ?></td>
                  <td><?= h((string)$r['version_number']) ?></td>
                  <td><?= h((string)$r['snapshot_size_mb']) ?> MB</td>
                  <td><?= h(number_format((float)($r['entropy_score'] ?? 0), 6)) ?></td>
                  <td><?= h((string)($r['decision_type'] ?? '')) ?></td>
                  <td><?= h((string)($r['integrity_status'] ?? '')) ?></td>
                  <td><?= $r['compression_ratio'] !== null ? h((string)$r['compression_ratio']) : '-' ?></td>
                  <td><?= $r['archive_location'] ? h((string)$r['archive_location']) : '-' ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr><td colspan="9" class="text-muted">No snapshots yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
