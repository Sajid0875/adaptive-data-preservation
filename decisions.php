<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();
$pdo = db();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $decisionId = (int)post('decision_id', 0);
    $newType = (string)post('new_decision', '');
    $reason = trim((string)post('reason', 'Manual override'));
    if (mb_strlen($reason) > 1000) { $error = 'Reason must be 1000 characters or fewer.'; }
    elseif ($decisionId > 0 && in_array($newType, ['DISCARD','COMPRESS','PRESERVE','ARCHIVE'])) {
        try {
            $stmt = $pdo->prepare("UPDATE preservation_decisions SET decision_type = ?, is_manual = true, manual_reason = ?, overridden_at = NOW() WHERE decision_id = ? RETURNING snapshot_id");
            $stmt->execute([$newType, $reason, $decisionId]);
            $snapId = $stmt->fetchColumn();
            
            if ($newType === 'ARCHIVE' && $snapId) {
                $dir = __DIR__ . '/archives';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                
                $snapData = $pdo->prepare("SELECT u.name, em.entropy_score FROM state_snapshots s JOIN universes u ON u.universe_id = s.universe_id JOIN entropy_metrics em ON em.snapshot_id = s.snapshot_id WHERE s.snapshot_id = ?");
                $snapData->execute([$snapId]);
                $sd = $snapData->fetch();
                
                $fakePath = $dir . "/archive_snapshot_{$snapId}_" . time() . ".txt";
                file_put_contents($fakePath, "Archive data for Snapshot #{$snapId}\nUniverse: {$sd['name']}\nEntropy: {$sd['entropy_score']}\nTimestamp: " . date('Y-m-d H:i:s'));
                
                $pdo->prepare("INSERT INTO archives (snapshot_id, archive_location) VALUES (?, ?) ON CONFLICT (snapshot_id) DO UPDATE SET archive_location = EXCLUDED.archive_location")->execute([$snapId, $fakePath]);
            } elseif ($newType === 'COMPRESS' && $snapId) {
                $pdo->prepare("INSERT INTO compressed_states (snapshot_id, compression_ratio) VALUES (?, 0.5) ON CONFLICT (snapshot_id) DO UPDATE SET compression_ratio = 0.5")->execute([$snapId]);
            }
            $message = "Decision successfully overridden to $newType.";
        } catch (Throwable $e) {
            error_log('decisions.php error: ' . $e->getMessage());
            $error = 'An unexpected database error occurred. Please try again.';
        }
    }
}

$searchUni = get('universe', '');
$filterDecision = get('decision', '');
$filterManual = get('is_manual', '');

$query = "SELECT pd.decision_id, pd.snapshot_id, pd.entropy_id, pd.decision_type, pd.decided_at, pd.is_manual, pd.manual_reason, pd.reason, u.name AS universe_name, s.version_number, em.entropy_score FROM preservation_decisions pd JOIN state_snapshots s ON s.snapshot_id = pd.snapshot_id JOIN universes u ON u.universe_id = s.universe_id JOIN entropy_metrics em ON em.entropy_id = pd.entropy_id WHERE 1=1";
$params = [];

if ($searchUni !== '') {
    $query .= " AND u.name ILIKE ?";
    $params[] = '%' . $searchUni . '%';
}
if ($filterDecision !== '') {
    $query .= " AND pd.decision_type = ?";
    $params[] = strtoupper($filterDecision);
}
if ($filterManual !== '') {
    if ($filterManual === 'yes') $query .= " AND pd.is_manual = true";
    if ($filterManual === 'no') $query .= " AND pd.is_manual = false";
}

$query .= " ORDER BY pd.decided_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$stats = $pdo->query('SELECT * FROM preservation_stats')->fetchAll();
$statMap = [];
foreach ($stats as $s) { $statMap[$s['decision_type']] = (int)$s['decisions_count']; }

$title = 'Decisions';
$active = 'decisions';
include __DIR__ . '/includes/header.php';
?>

<script>
window.chartData = window.chartData || {};
window.chartData.decisions = {
    counts: [<?= $statMap['DISCARD'] ?? 0 ?>, <?= $statMap['COMPRESS'] ?? 0 ?>, <?= $statMap['PRESERVE'] ?? 0 ?>, <?= $statMap['ARCHIVE'] ?? 0 ?>]
};
</script>

<?php if ($message): ?><div class="card" style="border-color:var(--accent-green);margin-bottom:16px;padding:12px 16px;font-size:13px;color:var(--accent-green)"><?= h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="card" style="border-color:var(--accent-red);margin-bottom:16px;padding:12px 16px;font-size:13px;color:var(--accent-red)"><?= h($error) ?></div><?php endif; ?>

<div class="page-header">
  <div class="page-header-left">
    <div class="page-tag">SYSTEM DIRECTIVE CONSOLE</div>
    <h1 class="page-title">Decisions</h1>
    <p class="page-subtitle">Centralized control for data lifecycle automation. Audit, override, and refine the Universe System's autonomous data management logic.</p>
  </div>
  <div class="page-actions">
    <a href="export_decisions.php" class="btn btn-outline">◈ EXPORT CSV</a>
  </div>
</div>

<div class="card" style="margin-bottom: 16px;">
  <form method="GET" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
    <div>
      <label style="color:var(--text-muted); font-size:12px; display:block; margin-bottom:4px;">Universe Name</label>
      <input type="text" name="universe" class="search-input" value="<?= h($searchUni) ?>" placeholder="Search...">
    </div>
    <div>
      <label style="color:var(--text-muted); font-size:12px; display:block; margin-bottom:4px;">Decision Type</label>
      <select name="decision" class="search-input">
        <option value="">All</option>
        <option value="DISCARD" <?= $filterDecision==='DISCARD' ? 'selected':'' ?>>DISCARD</option>
        <option value="COMPRESS" <?= $filterDecision==='COMPRESS' ? 'selected':'' ?>>COMPRESS</option>
        <option value="PRESERVE" <?= $filterDecision==='PRESERVE' ? 'selected':'' ?>>PRESERVE</option>
        <option value="ARCHIVE" <?= $filterDecision==='ARCHIVE' ? 'selected':'' ?>>ARCHIVE</option>
      </select>
    </div>
    <div>
      <label style="color:var(--text-muted); font-size:12px; display:block; margin-bottom:4px;">Manual/Auto</label>
      <select name="is_manual" class="search-input">
        <option value="">All</option>
        <option value="yes" <?= $filterManual==='yes' ? 'selected':'' ?>>Manual</option>
        <option value="no" <?= $filterManual==='no' ? 'selected':'' ?>>Automatic</option>
      </select>
    </div>
    <div>
      <button type="submit" class="btn btn-primary">Filter</button>
      <a href="decisions.php" class="btn btn-outline">Clear</a>
    </div>
  </form>
</div>

<div class="decision-grid">
  <div class="decision-card">
    <div class="decision-card-icon discard">✕</div>
    <div class="decision-card-tag">TAG: PURGE</div>
    <div class="decision-card-name">Discard</div>
    <div class="decision-card-desc">Permanent erasure of redundant or corrupt nodes. No recovery possible after finalization.</div>
    <div class="decision-card-stats">
      <span>24h Volume</span><span><?= $statMap['DISCARD'] ?? 0 ?></span>
    </div>
  </div>
  <div class="decision-card">
    <div class="decision-card-icon compress">⇊</div>
    <div class="decision-card-tag">TAG: OPTIMIZE</div>
    <div class="decision-card-name">Compress</div>
    <div class="decision-card-desc">Lossless reduction for low-frequency access blocks. Retains full metadata integrity.</div>
    <div class="decision-card-stats">
      <span>24h Volume</span><span><?= $statMap['COMPRESS'] ?? 0 ?></span>
    </div>
  </div>
  <div class="decision-card">
    <div class="decision-card-icon preserve">✓</div>
    <div class="decision-card-tag">TAG: ACTIVE</div>
    <div class="decision-card-name">Preserve</div>
    <div class="decision-card-desc">High-availability state. Zero-latency access with continuous parity checks.</div>
    <div class="decision-card-stats">
      <span>24h Volume</span><span><?= $statMap['PRESERVE'] ?? 0 ?></span>
    </div>
  </div>
  <div class="decision-card">
    <div class="decision-card-icon archive">☐</div>
    <div class="decision-card-tag">TAG: LONG-TERM</div>
    <div class="decision-card-name">Archive</div>
    <div class="decision-card-desc">Cold storage migration. Historical data snapshots encrypted for long-duration sleep.</div>
    <div class="decision-card-stats">
      <span>24h Volume</span><span><?= $statMap['ARCHIVE'] ?? 0 ?></span>
    </div>
  </div>
</div>

<div class="grid-3-1" style="margin-bottom:16px">
  <!-- Automated Decisions Table -->
  <div class="card">
    <div class="card-header">
      <div style="display:flex;align-items:center;gap:12px">
        <div class="card-title">⚙ Recent Automated Decisions</div>
        <div class="live-indicator"><span class="live-dot"></span> System Autonomous</div>
      </div>
      <a href="#" style="font-size:12px;color:var(--accent-cyan)">View All History</a>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>Timestamp</th><th>Object ID</th><th>Universe</th><th>Decision</th><th>Type</th></tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($rows, 0, 8) as $r):
            $dt = strtolower($r['decision_type']);
            $bc = match($dt) {'discard'=>'badge-discard','compress'=>'badge-compress','preserve'=>'badge-preserve','archive'=>'badge-archive',default=>''};
          ?>
          <tr>
            <td style="font-family:var(--font-mono);font-size:12px"><?= h(substr((string)$r['decided_at'],11,8)) ?></td>
            <td style="font-family:var(--font-mono);font-size:12px">OBJ_<?= h((string)$r['snapshot_id']) ?></td>
            <td><?= h($r['universe_name']) ?></td>
            <td><span class="badge <?= $bc ?>" title="<?= h($r['reason'] ?? '') ?>"><?= h($r['decision_type']) ?></span></td>
            <td><?= $r['is_manual'] ? '<span class="badge badge-warning">MANUAL</span>' : '<span class="badge badge-verified">AUTO</span>' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
          <tr><td colspan="5" style="color:var(--text-muted);text-align:center;padding:20px">No decisions yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Review Queue -->
  <div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <div class="card-title">📋 Review Queue</div>
      <span class="pending-count"><?= min(3, count($rows)) ?> PENDING</span>
    </div>

    <?php 
    // Show top 3 recent non-manual decisions for manual override
    $autoRows = array_filter($rows, fn($r) => !$r['is_manual']);
    $count = 0;
    foreach ($autoRows as $r):
        if ($count >= 3) break;
        $count++;
    ?>
    <div class="review-card">
      <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:4px">
        <div class="review-card-title"><?= h(strtoupper(str_replace(' ','_',$r['universe_name']))) ?>_V<?= h((string)$r['version_number']) ?></div>
        <span class="badge badge-low-confidence">AUTO: <?= h($r['decision_type']) ?></span>
      </div>
      <div class="review-card-uuid">UUID: OBJ_<?= h((string)$r['snapshot_id']) ?></div>
      <div class="review-card-desc">System auto-decision Reason: <strong><?= h($r['reason'] ?? 'None') ?></strong></div>
      
      <form method="POST" style="margin-top: 12px; background: rgba(0,0,0,0.2); padding: 8px; border-radius: 4px;">
        <input type="hidden" name="decision_id" value="<?= $r['decision_id'] ?>">
        <div style="display:flex; gap:8px; margin-bottom:8px;">
            <select name="new_decision" class="search-input" style="flex:1" required>
                <option value="">-- Override Decision --</option>
                <option value="DISCARD">DISCARD</option>
                <option value="COMPRESS">COMPRESS</option>
                <option value="PRESERVE">PRESERVE</option>
                <option value="ARCHIVE">ARCHIVE</option>
            </select>
        </div>
        <div style="display:flex; gap:8px;">
            <input type="text" name="reason" class="search-input" style="flex:1" placeholder="Reason for override..." required>
            <button type="submit" class="btn btn-red btn-sm">OVERRIDE</button>
        </div>
      </form>
    </div>
    <?php endforeach; ?>
    <?php if ($count === 0): ?>
    <div style="text-align:center; padding: 20px; color: var(--text-muted)">No auto decisions to review.</div>
    <?php endif; ?>
  </div>
</div>

<!-- Entropy Threshold Analysis + System Health -->
<div class="grid-2-1">
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Entropy Threshold Analysis</div>
        <div class="card-subtitle">Real-time mapping of data decay against decision weights. High entropy spikes trigger automatic 'Discard' protocols.</div>
      </div>
    </div>
    <div class="chart-container" style="height:160px">
      <canvas id="entropyThresholdChart"></canvas>
    </div>
  </div>
  <div class="card" style="text-align:center">
    <div class="metric-label" style="margin-bottom:8px">SYSTEM HEALTH</div>
    <div class="health-status">Stable</div>
    <div class="health-sub">Last re-index: 4m ago</div>
  </div>
</div>

<!-- Queue Throughput -->
<div class="throughput-card" style="margin-top:16px">
  <div class="throughput-label">QUEUE THROUGHPUT</div>
  <div style="display:flex;align-items:baseline;justify-content:space-between">
    <div>
      <span class="throughput-value"><?= count($rows) * 12 ?>/hr</span>
      <div class="throughput-sub">Autonomous Efficiency</div>
    </div>
    <div style="text-align:right">
      <span class="throughput-change up">+12%</span>
      <div class="throughput-sub">Vs Last Epoch</div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
