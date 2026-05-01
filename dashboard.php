<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();
$pdo = db();

$totalUniverses = (int)$pdo->query('SELECT COUNT(*) FROM universes')->fetchColumn();
$totalSnapshots = (int)$pdo->query('SELECT COUNT(*) FROM state_snapshots')->fetchColumn();
$avgEntropy = $pdo->query('SELECT COALESCE(AVG(entropy_score),0) FROM entropy_metrics')->fetchColumn();
$maxEntropy = (float)$pdo->query('SELECT COALESCE(MAX(entropy_score),0) FROM entropy_metrics')->fetchColumn();

$decisionCounts = ['DISCARD'=>0,'COMPRESS'=>0,'PRESERVE'=>0,'ARCHIVE'=>0];
$stmt = $pdo->query('SELECT decision_type, COUNT(*) AS c FROM preservation_decisions GROUP BY decision_type');
foreach ($stmt as $row) { $decisionCounts[$row['decision_type']] = (int)$row['c']; }

$totalDecisions = array_sum($decisionCounts);
$rows = $pdo->query('SELECT * FROM snapshot_summary ORDER BY snapshot_created_at DESC LIMIT 10')->fetchAll();

// Entropy trends for dashboard
$trendRows = $pdo->query('SELECT day, avg_entropy FROM entropy_trends ORDER BY day ASC LIMIT 14')->fetchAll();
$trendLabels = [];
$trendData = [];
foreach ($trendRows as $tr) {
    $trendLabels[] = substr($tr['day'], 0, 10);
    $trendData[] = round((float)$tr['avg_entropy'], 4);
}

$chartData = [
    'decisionDist' => array_values($decisionCounts),
    'trendLabels' => $trendLabels,
    'trendData' => $trendData
];

$title = 'Dashboard';
$active = 'dashboard';
include __DIR__ . '/includes/header.php';
?>

<script>
window.chartData = window.chartData || {};
window.chartData.dashboard = <?= json_encode($chartData) ?>;
</script>

<?php if ($maxEntropy > 0.7): 
    $highEntSnap = $pdo->query('SELECT snapshot_id, universe_name, entropy_score, decision_type FROM snapshot_summary WHERE entropy_score > 0.7 ORDER BY entropy_score DESC LIMIT 1')->fetch();
?>
<div class="card" style="border-left: 4px solid var(--accent-red); background-color: rgba(239, 68, 68, 0.05); margin-bottom: 24px;">
    <div style="display: flex; align-items: center; gap: 12px; color: var(--accent-red);">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <div>
            <strong>CRITICAL: High Entropy Detected</strong><br>
            Universe: <?= h($highEntSnap['universe_name']) ?> | Snapshot #<?= $highEntSnap['snapshot_id'] ?> | Score: <?= number_format((float)$highEntSnap['entropy_score'], 6) ?> | Action: <?= h($highEntSnap['decision_type']) ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="page-header">
  <div class="page-header-left">
    <div class="page-tag">SYSTEM DIRECTIVE CONSOLE</div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Centralized overview of the Universe System's data preservation state.</p>
  </div>
</div>

<div class="grid-2-1" style="margin-bottom:24px;">
    <div class="card">
        <div class="card-header"><div class="card-title">Entropy Trend (14 Days)</div></div>
        <div style="height: 200px;"><canvas id="dashTrendChart"></canvas></div>
    </div>
    <div class="card">
        <div class="card-header"><div class="card-title">Decision Distribution</div></div>
        <div style="height: 200px;"><canvas id="dashDistChart"></canvas></div>
    </div>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-label">Total Universes</div>
    <div class="stat-value cyan" data-count="<?= $totalUniverses ?>"><?= h((string)$totalUniverses) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Total Snapshots</div>
    <div class="stat-value" data-count="<?= $totalSnapshots ?>"><?= h((string)$totalSnapshots) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Mean Entropy</div>
    <div class="stat-value green"><?= h(number_format((float)$avgEntropy, 6)) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Total Decisions</div>
    <div class="stat-value amber" data-count="<?= $totalDecisions ?>"><?= h((string)$totalDecisions) ?></div>
  </div>
</div>

<div class="decision-grid">
  <div class="decision-card">
    <div class="decision-card-icon discard">✕</div>
    <div class="decision-card-tag">TAG: PURGE</div>
    <div class="decision-card-name">Discard</div>
    <div class="decision-card-desc">Permanent erasure of redundant or corrupt nodes. No recovery possible after finalization.</div>
    <div class="decision-card-stats">
      <span>Count</span><span><?= h((string)$decisionCounts['DISCARD']) ?></span>
    </div>
  </div>
  <div class="decision-card">
    <div class="decision-card-icon compress">⇊</div>
    <div class="decision-card-tag">TAG: OPTIMIZE</div>
    <div class="decision-card-name">Compress</div>
    <div class="decision-card-desc">Lossless reduction for low-frequency access blocks. Retains full metadata integrity.</div>
    <div class="decision-card-stats">
      <span>Count</span><span><?= h((string)$decisionCounts['COMPRESS']) ?></span>
    </div>
  </div>
  <div class="decision-card">
    <div class="decision-card-icon preserve">✓</div>
    <div class="decision-card-tag">TAG: ACTIVE</div>
    <div class="decision-card-name">Preserve</div>
    <div class="decision-card-desc">High-availability state. Zero-latency access with continuous parity checks.</div>
    <div class="decision-card-stats">
      <span>Count</span><span><?= h((string)$decisionCounts['PRESERVE']) ?></span>
    </div>
  </div>
  <div class="decision-card">
    <div class="decision-card-icon archive">☐</div>
    <div class="decision-card-tag">TAG: LONG-TERM</div>
    <div class="decision-card-name">Archive</div>
    <div class="decision-card-desc">Cold storage migration. Historical data snapshots encrypted for long-duration sleep.</div>
    <div class="decision-card-stats">
      <span>Count</span><span><?= h((string)$decisionCounts['ARCHIVE']) ?></span>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">Recent Snapshot Summary</div>
      <div class="card-subtitle">Latest 10 snapshots across all universes</div>
    </div>
    <a class="btn btn-outline btn-sm" href="snapshots.php">Manage Snapshots</a>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Universe</th><th>Snapshot</th><th>Version</th><th>Size (MB)</th>
          <th>Entropy</th><th>Decision</th><th>Integrity</th><th>Created</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td style="color:var(--accent-cyan)"><?= h($r['universe_name']) ?></td>
          <td>#<?= h((string)$r['snapshot_id']) ?></td>
          <td><?= h((string)$r['version_number']) ?></td>
          <td style="font-family:var(--font-mono)"><?= h((string)$r['snapshot_size_mb']) ?></td>
          <td style="font-family:var(--font-mono)"><?= h(number_format((float)($r['entropy_score'] ?? 0), 6)) ?></td>
          <td>
            <?php
            $dt = strtolower($r['decision_type'] ?? '');
            $bc = match($dt) { 'discard'=>'badge-discard','compress'=>'badge-compress','preserve'=>'badge-preserve','archive'=>'badge-archive', default=>'' };
            ?>
            <?php if($dt): ?><span class="badge <?= $bc ?>" title="<?= h($r['reason'] ?? '') ?>"><?= h(strtoupper($dt)) ?></span><?php endif; ?>
          </td>
          <td>
            <?php
            $is = strtolower($r['integrity_status'] ?? '');
            $ibc = match($is) { 'valid'=>'badge-verified','corrupted'=>'badge-failing','pending'=>'badge-warning', default=>'' };
            ?>
            <?php if($is): ?><span class="badge <?= $ibc ?>"><?= h(strtoupper($is)) ?></span><?php endif; ?>
          </td>
          <td style="font-family:var(--font-mono);font-size:11px"><?= h((string)$r['snapshot_created_at']) ?></td>
        </tr>
        <?php if (!empty($r['reason'])): ?>
        <tr>
          <td colspan="8" style="padding: 4px 16px 12px 16px; border-top: none; color: var(--text-muted); font-size: 12px; font-family: var(--font-mono);">
            Reason: <?= h($r['reason']) ?>
          </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
        <tr><td colspan="8" style="color:var(--text-muted);text-align:center;padding:20px">No snapshots yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
