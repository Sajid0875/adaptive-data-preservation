<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();
$pdo = db();

$rows = $pdo->query("SELECT em.entropy_id, em.snapshot_id, em.entropy_score, em.calculated_at, u.name AS universe_name, s.version_number, s.snapshot_size_mb, s.created_at AS snapshot_created_at FROM entropy_metrics em JOIN state_snapshots s ON s.snapshot_id = em.snapshot_id JOIN universes u ON u.universe_id = s.universe_id ORDER BY em.calculated_at DESC")->fetchAll();

$avgEntropy = 0; $maxEntropy = 0;
if ($rows) {
    $total = 0;
    foreach ($rows as $r) { $total += (float)$r['entropy_score']; $maxEntropy = max($maxEntropy, (float)$r['entropy_score']); }
    $avgEntropy = $total / count($rows);
}
$stablePercent = max(0, min(100, (1 - $avgEntropy) * 100));

$trend = $pdo->query('SELECT * FROM entropy_trends ORDER BY universe_id, day')->fetchAll();

// Per-universe entropy
$uniEntropy = [];
foreach ($rows as $r) {
    $uname = $r['universe_name'];
    if (!isset($uniEntropy[$uname])) $uniEntropy[$uname] = ['scores'=>[], 'max'=>0];
    $uniEntropy[$uname]['scores'][] = (float)$r['entropy_score'];
    $uniEntropy[$uname]['max'] = max($uniEntropy[$uname]['max'], (float)$r['entropy_score']);
}

$title = 'Entropy Analytics';
$active = 'entropy';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Entropy Analytics</h1>
    <p class="page-subtitle">Real-time monitoring of data decay and probabilistic noise across all preservation universes.</p>
  </div>
  <div class="page-actions">
    <div class="live-indicator"><span class="live-dot"></span> SYNC: LIVE</div>
  </div>
</div>

<div class="grid-2-1" style="margin-bottom:16px">
  <!-- Mean System Entropy -->
  <div class="card">
    <div class="card-header">
      <div>
        <div class="card-title">Mean System Entropy</div>
        <div class="card-subtitle">GLOBAL PRESERVATION INDEX</div>
      </div>
      <div style="text-align:right">
        <span style="color:var(--accent-cyan);font-family:var(--font-mono);font-size:12px"><?= number_format($avgEntropy, 4) ?> Δ/s</span>
        <div style="font-size:10px;color:var(--text-dim);font-family:var(--font-mono)">DRIFT RATE</div>
      </div>
    </div>
    <div style="display:flex;align-items:center;justify-content:center;padding:20px 0">
      <div style="position:relative;width:240px;height:200px">
        <canvas id="entropyGauge" width="240" height="200"></canvas>
        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-40%);text-align:center">
          <div style="font-size:36px;font-weight:700;font-family:var(--font-mono)"><?= number_format($stablePercent, 1) ?>%</div>
          <div style="font-size:10px;letter-spacing:1.5px;color:var(--accent-green);font-family:var(--font-mono)">STABLE SYSTEM</div>
        </div>
      </div>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text-dim);font-family:var(--font-mono);padding:0 20px">
      <span>0.0 UNIFIED VACUUM</span>
      <span>CRITICAL DECAY 1.0</span>
    </div>
  </div>

  <!-- Risk Threshold Alerts -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">⚠ Risk Threshold Alerts</div>
    </div>
    <?php
    $alertUniverses = [];
    foreach ($uniEntropy as $name => $data) {
        $maxE = $data['max'];
        $level = $maxE > 0.7 ? 'critical' : ($maxE > 0.3 ? 'warning' : 'ok');
        $alertUniverses[] = ['name'=>$name, 'entropy'=>$maxE, 'level'=>$level];
    }
    usort($alertUniverses, fn($a,$b) => $b['entropy'] <=> $a['entropy']);
    ?>
    <?php foreach (array_slice($alertUniverses, 0, 4) as $au): ?>
    <div class="alert-item">
      <div class="alert-icon <?= $au['level'] ?>">
        <?= $au['level'] === 'critical' ? '△' : ($au['level'] === 'warning' ? '!' : '◎') ?>
      </div>
      <div class="alert-text">
        <div class="alert-name"><?= h($au['name']) ?></div>
        <div class="alert-detail">ENTROPY: <?= number_format($au['entropy'], 2) ?> - <?= strtoupper($au['level']) ?></div>
      </div>
      <?php if ($au['level'] === 'critical'): ?>
      <span class="badge badge-critical">SEAL</span>
      <?php elseif ($au['level'] === 'warning'): ?>
      <button class="btn btn-outline btn-sm">MONITOR</button>
      <?php else: ?>
      <span style="color:var(--text-dim)">🔒</span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$alertUniverses): ?>
    <div style="padding:20px;text-align:center;color:var(--text-muted)">No entropy data yet.</div>
    <?php endif; ?>

    <div style="border-top:1px solid var(--border-color);padding:16px 0 0;margin-top:12px">
      <div class="metric-label">DECAY MITIGATION</div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-top:6px">
        <div style="font-size:22px;font-weight:700;font-family:var(--font-mono)"><?= number_format(count($rows) * 1.2, 1) ?> TB/hr</div>
        <div style="width:32px;height:32px;border:2px solid var(--accent-cyan);border-radius:50%;border-top-color:transparent;animation:spin 2s linear infinite"></div>
      </div>
    </div>
  </div>
</div>

<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<!-- Universe Sector Decay Trends -->
<div class="card" style="margin-bottom:16px">
  <div class="card-header">
    <div class="card-title">Universe Sector Decay Trends</div>
    <div style="display:flex;gap:16px;font-size:11px;color:var(--text-muted)">
      <span><span class="status-dot cyan"></span> Preservation Flow</span>
      <span><span class="status-dot amber"></span> Drift Noise</span>
    </div>
  </div>
  <div class="grid-3">
    <?php
    $sectorIdx = 0;
    $sectorNames = array_keys($uniEntropy);
    foreach (array_slice($sectorNames, 0, 3) as $sName):
      $drift = number_format($uniEntropy[$sName]['max'] * 100, 3);
    ?>
    <div class="sector-card">
      <div class="sector-header">
        <span class="sector-name"><?= h(strtoupper(substr($sName, 0, 16))) ?></span>
        <span class="sector-drift positive">+<?= $drift ?>%</span>
      </div>
      <div class="sector-coords">COORDINATES: <?= rand(10,99) ?>.<?= rand(0,9) ?>.<?= rand(0,9) ?>.<?= rand(10,99) ?></div>
      <div class="chart-container" style="height:60px">
        <canvas class="sector-chart"></canvas>
      </div>
    </div>
    <?php $sectorIdx++; endforeach; ?>
    <?php if (!$sectorNames): ?>
    <div style="grid-column:span 3;text-align:center;padding:20px;color:var(--text-muted)">No sector data available.</div>
    <?php endif; ?>
  </div>
</div>

<!-- Entropy Metrics Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Entropy Metrics Log</div>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>ID</th><th>Snapshot</th><th>Universe</th><th>Version</th><th>Size (MB)</th><th>Entropy</th><th>Calculated</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td style="font-family:var(--font-mono)"><?= h((string)$r['entropy_id']) ?></td>
          <td style="color:var(--accent-cyan);font-family:var(--font-mono)">#<?= h((string)$r['snapshot_id']) ?></td>
          <td><?= h($r['universe_name']) ?></td>
          <td style="font-family:var(--font-mono)"><?= h((string)$r['version_number']) ?></td>
          <td style="font-family:var(--font-mono)"><?= h((string)$r['snapshot_size_mb']) ?></td>
          <td style="font-family:var(--font-mono)"><?= h(number_format((float)$r['entropy_score'], 6)) ?></td>
          <td style="font-family:var(--font-mono);font-size:11px"><?= h((string)$r['calculated_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
        <tr><td colspan="7" style="color:var(--text-muted);text-align:center;padding:20px">No entropy metrics yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
