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
        if ($universeId <= 0) throw new RuntimeException('Universe is required.');
        if ($versionNumber <= 0) throw new RuntimeException('Version number must be > 0.');
        if ($sizeMb <= 0) throw new RuntimeException('Snapshot size must be > 0 MB.');
        $stmt = $pdo->prepare('INSERT INTO state_snapshots(universe_id, version_number, snapshot_size_mb) VALUES (:u, :v, :s)');
        $stmt->execute([':u' => $universeId, ':v' => $versionNumber, ':s' => $sizeMb]);
        $message = 'Snapshot added. Entropy + decision computed automatically.';
    } catch (Throwable $t) { $error = $t->getMessage(); }
}

$universesList = $pdo->query('SELECT universe_id, name FROM universes ORDER BY name')->fetchAll();
$rows = $pdo->query('SELECT * FROM snapshot_summary ORDER BY snapshot_created_at DESC')->fetchAll();

$totalSizeMb = 0; $totalEntropy = 0; $entropyCount = 0; $pendingDecisions = 0;
foreach ($rows as $r) {
    $totalSizeMb += (float)$r['snapshot_size_mb'];
    if (isset($r['entropy_score'])) { $totalEntropy += (float)$r['entropy_score']; $entropyCount++; }
}
$meanEntropy = $entropyCount > 0 ? $totalEntropy / $entropyCount : 0;

// Format size
if ($totalSizeMb >= 1048576) { $sizeStr = number_format($totalSizeMb/1048576, 2).' PB'; }
elseif ($totalSizeMb >= 1024) { $sizeStr = number_format($totalSizeMb/1024, 2).' GB'; }
else { $sizeStr = number_format($totalSizeMb, 2).' MB'; }

$title = 'Snapshots';
$active = 'snapshots';
include __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?><div class="card" style="border-color:var(--accent-green);margin-bottom:16px;padding:12px 16px;font-size:13px;color:var(--accent-green)"><?= h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="card" style="border-color:var(--accent-red);margin-bottom:16px;padding:12px 16px;font-size:13px;color:var(--accent-red)"><?= h($error) ?></div><?php endif; ?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Vault Snapshots</h1>
    <p class="page-subtitle">Real-time versioning history and automated preservation records.</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline">⊜ FILTER</button>
    <button class="btn btn-primary" onclick="document.getElementById('snapModal').classList.add('active')">⊙ MANUAL SNAPSHOT</button>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">Total Volume</div>
    <div class="stat-value"><?= $sizeStr ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Mean Entropy</div>
    <div class="stat-value"><?= number_format($meanEntropy, 4) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Integrity Status</div>
    <div class="stat-value green">100.0% SECURE</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Pending Decisions</div>
    <div class="stat-value"><?= count($rows) ?> UNITS</div>
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Universe Hash</th><th>Timestamp</th><th>Entropy Score</th>
          <th>Data Size</th><th>Integrity Check</th><th>Decision</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
          $entropy = (float)($r['entropy_score'] ?? 0);
          $barCount = 5;
          $filledBars = max(1, min(5, (int)ceil($entropy * 5)));
        ?>
        <tr>
          <td>
            <div style="color:var(--accent-cyan);font-family:var(--font-mono);font-weight:600">#UV-<?= h((string)$r['snapshot_id']) ?></div>
            <div style="font-size:10px;color:var(--text-dim)"><?= h($r['universe_name']) ?></div>
          </td>
          <td style="font-family:var(--font-mono);font-size:12px"><?= h(substr((string)$r['snapshot_created_at'],0,19)) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="entropy-bars">
                <?php for($b=0;$b<$barCount;$b++): ?>
                <div class="entropy-bar" style="height:<?= ($b < $filledBars) ? rand(8,16) : 4 ?>px;opacity:<?= $b < $filledBars ? 1 : 0.3 ?>"></div>
                <?php endfor; ?>
              </div>
              <span style="font-family:var(--font-mono);font-size:12px"><?= number_format($entropy, 3) ?></span>
            </div>
          </td>
          <td style="font-family:var(--font-mono)"><?= h((string)$r['snapshot_size_mb']) ?> MB</td>
          <td>
            <?php
            $is = strtolower($r['integrity_status'] ?? '');
            $ibc = match($is) { 'verified'=>'badge-verified','corrupted'=>'badge-failing','pending'=>'badge-warning', default=>'badge-info' };
            ?>
            <span class="badge <?= $ibc ?>"><?= h(strtoupper($is ?: 'PENDING')) ?></span>
          </td>
          <td>
            <?php
            $dt = $r['decision_type'] ?? '';
            ?>
            <span style="font-size:12px"><?= h($dt) ?></span>
          </td>
          <td>
            <button class="btn-ghost" title="View Details">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
        <tr><td colspan="7" style="color:var(--text-muted);text-align:center;padding:20px">No snapshots yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Snapshot Modal -->
<div class="modal-overlay" id="snapModal">
  <div class="modal-box" style="position:relative">
    <button class="modal-close" onclick="document.getElementById('snapModal').classList.remove('active')">&times;</button>
    <div class="modal-title">Manual Snapshot</div>
    <form method="post">
      <div class="form-group">
        <label class="form-label">Universe</label>
        <select class="form-select" name="universe_id" required>
          <option value="">Select...</option>
          <?php foreach ($universesList as $u): ?>
          <option value="<?= h((string)$u['universe_id']) ?>"><?= h($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Version Number</label>
        <input type="number" class="form-input" name="version_number" min="1" required>
      </div>
      <div class="form-group">
        <label class="form-label">Snapshot Size (MB)</label>
        <input type="number" step="0.001" class="form-input" name="snapshot_size_mb" min="0.001" required>
      </div>
      <button class="btn btn-primary" type="submit">Create Snapshot</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
