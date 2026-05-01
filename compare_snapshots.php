<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();
$pdo = db();

$snap1_id = (int)get('snap1', 0);
$snap2_id = (int)get('snap2', 0);

$snapshots = $pdo->query("SELECT snapshot_id, universe_name, version_number FROM snapshot_summary ORDER BY snapshot_created_at DESC")->fetchAll();

$data1 = null;
$data2 = null;
$changes1 = [];
$changes2 = [];

if ($snap1_id && $snap2_id) {
    $stmt = $pdo->prepare("SELECT * FROM snapshot_summary WHERE snapshot_id = ?");
    $stmt->execute([$snap1_id]);
    $data1 = $stmt->fetch();
    
    $stmt->execute([$snap2_id]);
    $data2 = $stmt->fetch();

    $cStmt = $pdo->prepare("SELECT COUNT(*) as c, SUM(change_weight) as w FROM state_changes WHERE snapshot_id = ?");
    $cStmt->execute([$snap1_id]);
    $changes1 = $cStmt->fetch();

    $cStmt->execute([$snap2_id]);
    $changes2 = $cStmt->fetch();
}

$title = 'Compare Snapshots';
$active = 'compare';
include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div class="page-header-left">
    <div class="page-tag">SYSTEM DIRECTIVE CONSOLE</div>
    <h1 class="page-title">Compare Snapshots</h1>
    <p class="page-subtitle">Analyze metadata differences between two historical data states.</p>
  </div>
</div>

<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <div class="card-title">Select Snapshots to Compare</div>
    </div>
    <form method="GET" style="display:flex; gap:16px; align-items:flex-end;">
        <div>
            <label style="color:var(--text-muted); font-size:12px; display:block; margin-bottom:4px;">Snapshot A</label>
            <select name="snap1" class="search-input" style="width:300px; padding:8px;" required>
                <option value="">-- Select Snapshot A --</option>
                <?php foreach($snapshots as $s): ?>
                    <option value="<?= $s['snapshot_id'] ?>" <?= $snap1_id === $s['snapshot_id'] ? 'selected' : '' ?>>
                        #<?= $s['snapshot_id'] ?> - <?= h($s['universe_name']) ?> (v<?= $s['version_number'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="color:var(--text-muted); font-size:12px; display:block; margin-bottom:4px;">Snapshot B</label>
            <select name="snap2" class="search-input" style="width:300px; padding:8px;" required>
                <option value="">-- Select Snapshot B --</option>
                <?php foreach($snapshots as $s): ?>
                    <option value="<?= $s['snapshot_id'] ?>" <?= $snap2_id === $s['snapshot_id'] ? 'selected' : '' ?>>
                        #<?= $s['snapshot_id'] ?> - <?= h($s['universe_name']) ?> (v<?= $s['version_number'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary" style="padding: 8px 16px;">COMPARE</button>
        </div>
    </form>
</div>

<?php if ($data1 && $data2): ?>
<div class="grid-2-1" style="grid-template-columns: 1fr 1fr 1fr;">
    <div class="card">
        <div class="card-header"><div class="card-title" style="color:var(--accent-cyan)">Snapshot A (#<?= $data1['snapshot_id'] ?>)</div></div>
        <table style="width:100%; text-align:left; border-collapse: collapse;">
            <tbody>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Universe</th><td><?= h($data1['universe_name']) ?></td></tr>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Version</th><td>v<?= $data1['version_number'] ?></td></tr>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Size</th><td><?= $data1['snapshot_size_mb'] ?> MB</td></tr>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Entropy</th><td><?= number_format((float)$data1['entropy_score'], 6) ?></td></tr>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Decision</th><td><span class="badge badge-<?= strtolower($data1['decision_type'] ?? '') ?>"><?= $data1['decision_type'] ?? 'NONE' ?></span></td></tr>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Integrity</th><td><?= $data1['integrity_status'] ?></td></tr>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Changes</th><td><?= (int)$changes1['c'] ?></td></tr>
                <tr><th style="padding: 12px 0;">Total Weight</th><td><?= (int)$changes1['w'] ?></td></tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-header"><div class="card-title" style="color:var(--accent-amber)">Snapshot B (#<?= $data2['snapshot_id'] ?>)</div></div>
        <table style="width:100%; text-align:left; border-collapse: collapse;">
            <tbody>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Universe</th><td><?= h($data2['universe_name']) ?></td></tr>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Version</th><td>v<?= $data2['version_number'] ?></td></tr>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Size</th><td><?= $data2['snapshot_size_mb'] ?> MB</td></tr>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Entropy</th><td><?= number_format((float)$data2['entropy_score'], 6) ?></td></tr>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Decision</th><td><span class="badge badge-<?= strtolower($data2['decision_type'] ?? '') ?>"><?= $data2['decision_type'] ?? 'NONE' ?></span></td></tr>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Integrity</th><td><?= $data2['integrity_status'] ?></td></tr>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Changes</th><td><?= (int)$changes2['c'] ?></td></tr>
                <tr><th style="padding: 12px 0;">Total Weight</th><td><?= (int)$changes2['w'] ?></td></tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-header"><div class="card-title" style="color:var(--text-muted)">Difference (B - A)</div></div>
        <table style="width:100%; text-align:left; border-collapse: collapse;">
            <tbody>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Size Diff</th>
                    <?php $sizeDiff = $data2['snapshot_size_mb'] - $data1['snapshot_size_mb']; ?>
                    <td style="color: <?= $sizeDiff > 0 ? 'var(--accent-red)' : ($sizeDiff < 0 ? 'var(--accent-green)' : 'var(--text-muted)') ?>">
                        <?= $sizeDiff > 0 ? '+' : '' ?><?= number_format($sizeDiff, 3) ?> MB
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Entropy Diff</th>
                    <?php $entDiff = $data2['entropy_score'] - $data1['entropy_score']; ?>
                    <td style="color: <?= $entDiff > 0 ? 'var(--accent-amber)' : ($entDiff < 0 ? 'var(--accent-cyan)' : 'var(--text-muted)') ?>">
                        <?= $entDiff > 0 ? '+' : '' ?><?= number_format($entDiff, 6) ?>
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid var(--border-color);"><th style="padding: 12px 0;">Changes Diff</th>
                    <?php $cDiff = (int)$changes2['c'] - (int)$changes1['c']; ?>
                    <td style="color: <?= $cDiff > 0 ? 'white' : 'var(--text-muted)' ?>">
                        <?= $cDiff > 0 ? '+' : '' ?><?= $cDiff ?>
                    </td>
                </tr>
                <tr><th style="padding: 12px 0;">Weight Diff</th>
                    <?php $wDiff = (int)$changes2['w'] - (int)$changes1['w']; ?>
                    <td style="color: <?= $wDiff > 0 ? 'white' : 'var(--text-muted)' ?>">
                        <?= $wDiff > 0 ? '+' : '' ?><?= $wDiff ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php elseif($snap1_id || $snap2_id): ?>
<div class="card"><p style="color:var(--accent-red)">Error: One or both snapshots not found.</p></div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
