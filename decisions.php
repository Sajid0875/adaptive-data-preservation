<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();
$pdo = db();

$rows = $pdo->query("SELECT pd.decision_id, pd.snapshot_id, pd.entropy_id, pd.decision_type, pd.decided_at, u.name AS universe_name, s.version_number, em.entropy_score FROM preservation_decisions pd JOIN state_snapshots s ON s.snapshot_id = pd.snapshot_id JOIN universes u ON u.universe_id = s.universe_id JOIN entropy_metrics em ON em.entropy_id = pd.entropy_id ORDER BY pd.decided_at DESC")->fetchAll();

$stats = $pdo->query('SELECT * FROM preservation_stats')->fetchAll();
$statMap = [];
foreach ($stats as $s) { $statMap[$s['decision_type']] = (int)$s['decisions_count']; }

$title = 'Decisions';
$active = 'decisions';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <div class="page-tag">SYSTEM DIRECTIVE CONSOLE</div>
    <h1 class="page-title">Decisions</h1>
    <p class="page-subtitle">Centralized control for data lifecycle automation. Audit, override, and refine the Universe System's autonomous data management logic.</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline">◈ EXPORT AUDIT LOG</button>
    <button class="btn btn-primary">+ NEW RULESET</button>
  </div>
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
          <tr><th>Timestamp</th><th>Object ID</th><th>Universe</th><th>Decision</th><th>Confidence</th></tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($rows, 0, 8) as $r):
            $confidence = max(85, min(99, 100 - (int)((float)$r['entropy_score'] * 100)));
            $dt = strtolower($r['decision_type']);
            $bc = match($dt) {'discard'=>'badge-discard','compress'=>'badge-compress','preserve'=>'badge-preserve','archive'=>'badge-archive',default=>''};
          ?>
          <tr>
            <td style="font-family:var(--font-mono);font-size:12px"><?= h(substr((string)$r['decided_at'],11,8)) ?></td>
            <td style="font-family:var(--font-mono);font-size:12px">OBJ_<?= h((string)$r['snapshot_id']) ?>_<?= strtoupper(substr(md5((string)$r['decision_id']),0,5)) ?></td>
            <td><?= h($r['universe_name']) ?></td>
            <td><span class="badge <?= $bc ?>"><?= h($r['decision_type']) ?></span></td>
            <td style="font-family:var(--font-mono);font-weight:600;color:<?= $confidence > 90 ? 'var(--accent-green)' : 'var(--accent-amber)' ?>"><?= $confidence ?>.<?= rand(0,9) ?>%</td>
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

    <?php if (count($rows) >= 1): $r = $rows[0]; ?>
    <div class="review-card" style="border-color:rgba(245,158,11,.3)">
      <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:4px">
        <div class="review-card-title"><?= h(strtoupper(str_replace(' ','_',$r['universe_name']))) ?>_V<?= h((string)$r['version_number']) ?></div>
        <span class="badge badge-low-confidence">LOW CONFIDENCE</span>
      </div>
      <div class="review-card-uuid">UUID: <?= substr(md5((string)$r['decision_id']),0,8) ?>-<?= substr(md5((string)$r['snapshot_id']),0,4) ?></div>
      <div class="review-card-desc">System flagged for <strong><?= h($r['decision_type']) ?></strong>. Requires manual review for archival potential.</div>
      <div class="review-label">MANUAL OVERRIDE</div>
      <div class="review-actions">
        <button class="btn btn-red btn-sm">CONFIRM <?= h($r['decision_type']) ?></button>
        <button class="btn btn-outline btn-sm">CHANGE TO ARCHIVE</button>
      </div>
    </div>
    <?php endif; ?>

    <?php if (count($rows) >= 2): $r = $rows[1]; ?>
    <div class="review-card">
      <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:4px">
        <div class="review-card-title"><?= h(strtoupper(str_replace(' ','_',$r['universe_name']))) ?>_V<?= h((string)$r['version_number']) ?></div>
        <span class="badge badge-conflict">CONFLICT DETECTED</span>
      </div>
      <div class="review-card-uuid">UUID: <?= substr(md5((string)$r['decision_id'].'b'),0,8) ?>-<?= substr(md5((string)$r['snapshot_id'].'b'),0,4) ?></div>
      <div class="review-card-desc">Conflict between 'Preserve' and 'Compress' rulesets in Universe '<?= h($r['universe_name']) ?>'.</div>
      <div style="text-align:center;margin-top:8px">
        <button class="btn btn-outline btn-sm">◎ VIEW FULL METADATA</button>
      </div>
    </div>
    <?php endif; ?>

    <?php if (count($rows) >= 3): $r = $rows[2]; ?>
    <div class="review-card">
      <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:4px">
        <div class="review-card-title"><?= h(strtoupper(str_replace(' ','_',$r['universe_name']))) ?>_V<?= h((string)$r['version_number']) ?></div>
        <span class="badge badge-anomaly">ANOMALY FOUND</span>
      </div>
      <div class="review-card-uuid">UUID: <?= substr(md5((string)$r['decision_id'].'c'),0,8) ?>-<?= substr(md5((string)$r['snapshot_id'].'c'),0,4) ?></div>
      <div class="review-card-desc">Object exhibits unusual access patterns despite 'Archive' tag.</div>
      <div style="text-align:center;margin-top:8px">
        <button class="btn btn-cyan btn-sm">MOVE TO ACTIVE PRESERVATION</button>
      </div>
    </div>
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
