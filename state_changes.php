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
        $weight = (int)post('change_weight', 0);
        if ($snapshotId <= 0) throw new RuntimeException('Snapshot is required.');
        if (!in_array($changeType, ['CREATE','UPDATE','DELETE','CORRUPTION'], true)) throw new RuntimeException('Invalid change type.');
        if ($weight < 0) throw new RuntimeException('Change weight must be >= 0.');
        $stmt = $pdo->prepare('INSERT INTO state_changes(snapshot_id, change_type, change_weight) VALUES (:sid, :ct, :w)');
        $stmt->execute([':sid' => $snapshotId, ':ct' => $changeType, ':w' => $weight]);
        $message = 'Change added. Entropy + decision recomputed automatically.';
    } catch (Throwable $t) { $error = $t->getMessage(); }
}

$snapshots = $pdo->query("SELECT s.snapshot_id, u.name AS universe_name, s.version_number, s.created_at FROM state_snapshots s JOIN universes u ON u.universe_id = s.universe_id ORDER BY s.created_at DESC")->fetchAll();
$changes = $pdo->query("SELECT c.change_id, c.snapshot_id, u.name AS universe_name, s.version_number, c.change_type, c.change_weight, c.created_at FROM state_changes c JOIN state_snapshots s ON s.snapshot_id = c.snapshot_id JOIN universes u ON u.universe_id = s.universe_id ORDER BY c.created_at DESC")->fetchAll();

// Count by type
$typeCounts = ['CREATE'=>0,'UPDATE'=>0,'DELETE'=>0,'CORRUPTION'=>0];
foreach ($changes as $c) { $typeCounts[$c['change_type']] = ($typeCounts[$c['change_type']] ?? 0) + 1; }

$title = 'State Changes';
$active = 'changes';
include __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?><div class="card" style="border-color:var(--accent-green);margin-bottom:16px;padding:12px 16px;font-size:13px;color:var(--accent-green)"><?= h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="card" style="border-color:var(--accent-red);margin-bottom:16px;padding:12px 16px;font-size:13px;color:var(--accent-red)"><?= h($error) ?></div><?php endif; ?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">⟐ State Changes</h1>
    <p class="page-subtitle">Visualizing dimensional data drift and structural deltas across snapshot timelines.</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline">Filter Range</button>
    <button class="btn btn-primary">Export Delta Report</button>
  </div>
</div>

<!-- Drift Timeline Chart -->
<div class="card" style="margin-bottom:16px">
  <div class="card-header">
    <div>
      <div class="card-title">Dimensional Drift Timeline</div>
      <div class="card-subtitle">Comparative analysis across snapshots</div>
    </div>
  </div>
  <div class="chart-container tall">
    <canvas id="driftChart"></canvas>
  </div>
</div>

<div class="grid-2-1" style="margin-bottom:16px">
  <!-- Composition Breakdown -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Composition Breakdown</div>
      <span class="badge badge-info">COMPARING</span>
    </div>
    <div style="margin-top:8px">
      <div class="comp-bar-row">
        <div class="comp-bar-label">CREATE Events</div>
        <div class="comp-bar-track"><div class="comp-bar-fill cyan" style="width:<?= $typeCounts['CREATE'] ? min(100, $typeCounts['CREATE']*15) : 5 ?>%"></div></div>
        <div class="comp-bar-value"><?= $typeCounts['CREATE'] ?></div>
      </div>
      <div class="comp-bar-row">
        <div class="comp-bar-label">UPDATE Events</div>
        <div class="comp-bar-track"><div class="comp-bar-fill green" style="width:<?= $typeCounts['UPDATE'] ? min(100, $typeCounts['UPDATE']*15) : 5 ?>%"></div></div>
        <div class="comp-bar-value"><?= $typeCounts['UPDATE'] ?></div>
      </div>
      <div class="comp-bar-row">
        <div class="comp-bar-label">DELETE Events</div>
        <div class="comp-bar-track"><div class="comp-bar-fill indigo" style="width:<?= $typeCounts['DELETE'] ? min(100, $typeCounts['DELETE']*15) : 5 ?>%"></div></div>
        <div class="comp-bar-value"><?= $typeCounts['DELETE'] ?></div>
      </div>
      <div class="comp-bar-row">
        <div class="comp-bar-label">CORRUPTION Events</div>
        <div class="comp-bar-track"><div class="comp-bar-fill red" style="width:<?= $typeCounts['CORRUPTION'] ? min(100, $typeCounts['CORRUPTION']*20) : 5 ?>%"></div></div>
        <div class="comp-bar-value"><?= $typeCounts['CORRUPTION'] ?></div>
      </div>
    </div>
  </div>

  <!-- Entropy Velocity -->
  <div class="card">
    <div class="card-title">Entropy Velocity</div>
    <div class="card-subtitle">Rate of data decay per cycle</div>
    <div style="text-align:center;margin:20px 0 10px">
      <div class="big-metric">4.2 <span class="big-metric-unit">mE/s</span></div>
    </div>
    <div style="text-align:center;margin-bottom:12px">
      <span class="badge badge-warning">WARNING: THRESHOLD NEARING</span>
    </div>
    <div class="chart-container" style="height:80px">
      <canvas id="velocityChart"></canvas>
    </div>
  </div>
</div>

<!-- Recent State Events -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Recent State Events</div>
    <button class="btn btn-outline btn-sm" onclick="document.getElementById('changeModal').classList.add('active')">+ Add Change</button>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>ID</th><th>Snapshot</th><th>Universe</th><th>Version</th><th>Type</th><th>Weight</th><th>Created</th></tr>
      </thead>
      <tbody>
        <?php foreach ($changes as $c): ?>
        <tr>
          <td style="font-family:var(--font-mono)"><?= h((string)$c['change_id']) ?></td>
          <td style="color:var(--accent-cyan);font-family:var(--font-mono)">#<?= h((string)$c['snapshot_id']) ?></td>
          <td><?= h($c['universe_name']) ?></td>
          <td style="font-family:var(--font-mono)"><?= h((string)$c['version_number']) ?></td>
          <td>
            <?php
            $ctc = match($c['change_type']) {'CREATE'=>'badge-preserve','UPDATE'=>'badge-compress','DELETE'=>'badge-discard','CORRUPTION'=>'badge-failing',default=>''};
            ?>
            <span class="badge <?= $ctc ?>"><?= h($c['change_type']) ?></span>
          </td>
          <td style="font-family:var(--font-mono)"><?= h((string)$c['change_weight']) ?></td>
          <td style="font-family:var(--font-mono);font-size:11px"><?= h((string)$c['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$changes): ?>
        <tr><td colspan="7" style="color:var(--text-muted);text-align:center;padding:20px">No changes yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Change Modal -->
<div class="modal-overlay" id="changeModal">
  <div class="modal-box" style="position:relative">
    <button class="modal-close" onclick="document.getElementById('changeModal').classList.remove('active')">&times;</button>
    <div class="modal-title">Add State Change</div>
    <form method="post">
      <div class="form-group">
        <label class="form-label">Snapshot</label>
        <select class="form-select" name="snapshot_id" required>
          <option value="">Select...</option>
          <?php foreach ($snapshots as $s): ?>
          <option value="<?= h((string)$s['snapshot_id']) ?>">#<?= h((string)$s['snapshot_id']) ?> — <?= h($s['universe_name']) ?> (v<?= h((string)$s['version_number']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Change Type</label>
        <select class="form-select" name="change_type" required>
          <?php foreach (['CREATE','UPDATE','DELETE','CORRUPTION'] as $t): ?>
          <option value="<?= h($t) ?>"><?= h($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Change Weight (0 = auto)</label>
        <input type="number" class="form-input" name="change_weight" min="0" value="0">
      </div>
      <button class="btn btn-primary" type="submit">Add Change</button>
    </form>
  </div>
</div>

<button class="fab" onclick="document.getElementById('changeModal').classList.add('active')" title="Add Change">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
</button>

<?php include __DIR__ . '/includes/footer.php'; ?>
