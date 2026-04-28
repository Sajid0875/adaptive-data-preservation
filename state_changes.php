<?php

declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();

$pdo = db();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $snapshotId = (int)post('snapshot_id', 0);
        $changeType = (string)post('change_type', '');
        $weight = (int)post('change_weight', 0); // 0 => auto via trigger

        if ($snapshotId <= 0) {
            throw new RuntimeException('Snapshot is required.');
        }
        if (!in_array($changeType, ['CREATE','UPDATE','DELETE','CORRUPTION'], true)) {
            throw new RuntimeException('Invalid change type.');
        }
        if ($weight < 0) {
            throw new RuntimeException('Change weight must be >= 0.');
        }

        $stmt = $pdo->prepare('INSERT INTO state_changes(snapshot_id, change_type, change_weight) VALUES (:sid, :ct, :w)');
        $stmt->execute([':sid' => $snapshotId, ':ct' => $changeType, ':w' => $weight]);
        $message = 'Change added. Entropy + decision recomputed automatically.';
    } catch (Throwable $t) {
        $error = $t->getMessage();
    }
}

$snapshots = $pdo->query(
    "SELECT s.snapshot_id, u.name AS universe_name, s.version_number, s.created_at
     FROM state_snapshots s
     JOIN universes u ON u.universe_id = s.universe_id
     ORDER BY s.created_at DESC"
)->fetchAll();

$changes = $pdo->query(
    "SELECT c.change_id, c.snapshot_id, u.name AS universe_name, s.version_number, c.change_type, c.change_weight, c.created_at
     FROM state_changes c
     JOIN state_snapshots s ON s.snapshot_id = c.snapshot_id
     JOIN universes u ON u.universe_id = s.universe_id
     ORDER BY c.created_at DESC"
)->fetchAll();

$title = 'State Changes';
$active = 'changes';
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
        <h2 class="h5">Add Change</h2>
        <form method="post" class="vstack gap-3 mt-3">
          <div>
            <label class="form-label">Snapshot</label>
            <select class="form-select" name="snapshot_id" required>
              <option value="">Select...</option>
              <?php foreach ($snapshots as $s): ?>
                <option value="<?= h((string)$s['snapshot_id']) ?>">
                  #<?= h((string)$s['snapshot_id']) ?> — <?= h($s['universe_name']) ?> (v<?= h((string)$s['version_number']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Change Type</label>
            <select class="form-select" name="change_type" required>
              <?php foreach (['CREATE','UPDATE','DELETE','CORRUPTION'] as $t): ?>
                <option value="<?= h($t) ?>"><?= h($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Change Weight (optional)</label>
            <input type="number" class="form-control" name="change_weight" min="0" value="0">
            <div class="form-text">Use <code>0</code> to auto-assign weights (CREATE=1, UPDATE=2, DELETE=3, CORRUPTION=5).</div>
          </div>
          <button class="btn btn-primary" type="submit">Add Change</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-body">
        <h2 class="h5">Recent Changes</h2>
        <div class="table-responsive mt-3">
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Snapshot</th>
                <th>Universe</th>
                <th>Version</th>
                <th>Type</th>
                <th>Weight</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($changes as $c): ?>
                <tr>
                  <td><?= h((string)$c['change_id']) ?></td>
                  <td>#<?= h((string)$c['snapshot_id']) ?></td>
                  <td><?= h($c['universe_name']) ?></td>
                  <td><?= h((string)$c['version_number']) ?></td>
                  <td><?= h($c['change_type']) ?></td>
                  <td><?= h((string)$c['change_weight']) ?></td>
                  <td><?= h((string)$c['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$changes): ?>
                <tr><td colspan="7" class="text-muted">No changes yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
