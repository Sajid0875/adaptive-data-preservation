<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();
$pdo = db();

$message = '';
$error = '';
$action = (string)post('action', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create') {
            $name = trim((string)post('name', ''));
            $description = trim((string)post('description', ''));
            if ($name === '') throw new RuntimeException('Name is required.');
            if (mb_strlen($name) > 255) throw new RuntimeException('Name must be 255 characters or fewer.');
            if (mb_strlen($description) > 2000) throw new RuntimeException('Description must be 2000 characters or fewer.');
            $stmt = $pdo->prepare('INSERT INTO universes(name, description) VALUES (:name, :description)');
            $stmt->execute([':name' => $name, ':description' => $description]);
            $message = 'Universe created.';
        } elseif ($action === 'update') {
            $universeId = (int)post('universe_id', 0);
            $name = trim((string)post('name', ''));
            $description = trim((string)post('description', ''));
            if ($universeId <= 0) throw new RuntimeException('Invalid universe.');
            if ($name === '') throw new RuntimeException('Name is required.');
            if (mb_strlen($name) > 255) throw new RuntimeException('Name must be 255 characters or fewer.');
            if (mb_strlen($description) > 2000) throw new RuntimeException('Description must be 2000 characters or fewer.');
            $stmt = $pdo->prepare('UPDATE universes SET name = :name, description = :description WHERE universe_id = :id');
            $stmt->execute([':name' => $name, ':description' => $description, ':id' => $universeId]);
            $message = 'Universe updated.';
        } elseif ($action === 'delete') {
            $universeId = (int)post('universe_id', 0);
            if ($universeId <= 0) throw new RuntimeException('Invalid universe.');
            $stmt = $pdo->prepare('DELETE FROM universes WHERE universe_id = :id');
            $stmt->execute([':id' => $universeId]);
            $message = 'Universe deleted.';
        }
    } catch (RuntimeException $t) { $error = $t->getMessage(); } catch (Throwable $t) { error_log('universes.php error: ' . $t->getMessage()); $error = 'An unexpected database error occurred. Please try again.'; }
}

$editId = (int)get('edit', 0);
$editUniverse = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM universes WHERE universe_id = :id');
    $stmt->execute([':id' => $editId]);
    $editUniverse = $stmt->fetch() ?: null;
}

$search = get('search', '');
$query = "SELECT * FROM universes WHERE 1=1";
$params = [];
if ($search !== '') {
    $query .= " AND (name ILIKE ? OR description ILIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$query .= " ORDER BY universe_id ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$universes = $stmt->fetchAll();

// Get snapshot counts and entropy per universe
$uniStats = [];
$statsQuery = $pdo->query("SELECT u.universe_id, COUNT(s.snapshot_id) as snap_count, 
    MAX(s.created_at) as last_snap, COALESCE(AVG(em.entropy_score),0) as avg_entropy
    FROM universes u 
    LEFT JOIN state_snapshots s ON s.universe_id = u.universe_id
    LEFT JOIN entropy_metrics em ON em.snapshot_id = s.snapshot_id
    GROUP BY u.universe_id");
foreach ($statsQuery as $s) { $uniStats[$s['universe_id']] = $s; }

$statuses = ['OPTIMAL','NOMINAL','ACTIVE','STRESSED','CRITICAL DECAY'];
$statusColors = ['OPTIMAL'=>'green','NOMINAL'=>'green','ACTIVE'=>'green','STRESSED'=>'amber','CRITICAL DECAY'=>'red'];
$icons = ['⊕','⊞','(·)','▣','◈'];

$title = 'Universes';
$active = 'universes';
include __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?><div class="card" style="border-color:var(--accent-green);margin-bottom:16px;padding:12px 16px;font-size:13px;color:var(--accent-green)"><?= h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="card" style="border-color:var(--accent-red);margin-bottom:16px;padding:12px 16px;font-size:13px;color:var(--accent-red)"><?= h($error) ?></div><?php endif; ?>

<div class="page-header">
  <div class="page-header-left">
    <h1 class="page-title">Universes</h1>
    <p class="page-subtitle">Real-time monitoring of multiversal data preservation states.</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('active')">+ New Universe</button>
  </div>
</div>

<div class="card" style="margin-bottom: 24px;">
  <form method="GET" style="display:flex; gap:16px; align-items:flex-end;">
    <div style="flex:1;">
      <label style="color:var(--text-muted); font-size:12px; display:block; margin-bottom:4px;">Search Name or Description</label>
      <input type="text" name="search" class="search-input" style="width:100%;" value="<?= h($search) ?>" placeholder="Search...">
    </div>
    <div>
      <button type="submit" class="btn btn-primary">Search</button>
      <a href="universes.php" class="btn btn-outline">Clear</a>
    </div>
  </form>
</div>

<div class="grid-2" style="margin-bottom:24px">
  <?php foreach ($universes as $i => $u):
    $uid = $u['universe_id'];
    $st = $uniStats[$uid] ?? ['snap_count'=>0,'last_snap'=>null,'avg_entropy'=>0];
    $entropy = (float)$st['avg_entropy'];
    $statusIdx = $entropy > 0.8 ? 4 : ($entropy > 0.5 ? 3 : ($entropy > 0.1 ? 2 : ($entropy > 0 ? 1 : 0)));
    $status = $statuses[$statusIdx];
    $sColor = $statusColors[$status];
    $icon = $icons[$i % count($icons)];
    $isCritical = $statusIdx >= 4;
  ?>
  <div class="universe-card <?= $isCritical ? 'critical' : '' ?>">
    <div class="universe-header">
      <div class="universe-icon"><?= $icon ?></div>
      <div style="flex:1">
        <div class="universe-name"><?= h($u['name']) ?></div>
        <div class="universe-status">
          <span class="status-dot <?= $sColor ?>"></span>
          STATUS: <span style="color:var(--accent-<?= $sColor ?>)"><?= $status ?></span>
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-size:10px;color:var(--text-dim);font-family:var(--font-mono)">LAST SNAPSHOT</div>
        <div style="font-size:11px;font-family:var(--font-mono);color:var(--text-secondary)"><?= $st['last_snap'] ? h(substr((string)$st['last_snap'],0,19)) : 'None' ?></div>
      </div>
    </div>
    <div class="universe-meta">
      <div>ENTROPY TREND</div>
      <div><?= h(number_format($entropy, 4)) ?> Δ / Hr</div>
    </div>
    <div class="entropy-trend-bars">
      <?php for($b=0;$b<12;$b++):
        $bClass = $entropy > 0.5 ? ($b > 8 ? 'danger' : 'warn') : '';
        $h = 8 + (intval(substr(md5($uid . $b), 0, 1), 16) % 11);
      ?><div class="entropy-trend-bar <?= $bClass ?>" style="height:<?= $h ?>px"></div>
      <?php endfor; ?>
    </div>
    <div class="universe-actions">
      <a class="btn btn-outline btn-sm" href="snapshots.php">Snapshot Now</a>
      <a class="btn btn-outline btn-sm" href="universes.php?edit=<?= h((string)$u['universe_id']) ?>">View Details</a>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$universes): ?>
  <div class="card" style="grid-column:span 2;text-align:center;padding:40px;color:var(--text-muted)">No universes yet. Create one to get started.</div>
  <?php endif; ?>
</div>

<?php if ($editUniverse): ?>
<div class="card" style="margin-bottom:24px">
  <div class="card-title" style="margin-bottom:16px">Edit Universe: <?= h($editUniverse['name']) ?></div>
  <form method="post">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="universe_id" value="<?= h((string)$editUniverse['universe_id']) ?>">
    <div class="form-group">
      <label class="form-label">Name</label>
      <input class="form-input" name="name" required value="<?= h($editUniverse['name']) ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Description</label>
      <textarea class="form-textarea" name="description"><?= h($editUniverse['description']) ?></textarea>
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-primary" type="submit">Save Changes</button>
      <a class="btn btn-outline" href="universes.php">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div style="display:flex;gap:16px;padding:16px 20px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-lg)">
  <div><span class="stat-label">TOTAL CAPACITY</span><br><span style="font-family:var(--font-mono);font-weight:700"><?= count($universes) ?> Universes</span></div>
  <div style="border-left:1px solid var(--border-color);padding-left:16px"><span class="stat-label">SYSTEM LOAD</span><br><span style="font-family:var(--font-mono);font-weight:700">14.01%</span></div>
  <div style="border-left:1px solid var(--border-color);padding-left:16px"><span class="stat-label">ACTIVE CLUSTERS</span><br><span style="font-family:var(--font-mono);font-weight:700"><?= count($universes) ?> / <?= count($universes)+1 ?></span></div>
  <div style="flex:1;text-align:right;color:var(--text-dim);font-size:12px;font-style:italic;align-self:center">Preservation cycle in progress...</div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
  <div class="modal-box" style="position:relative">
    <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('active')">&times;</button>
    <div class="modal-title">+ New Universe</div>
    <form method="post">
      <input type="hidden" name="action" value="create">
      <div class="form-group">
        <label class="form-label">Name</label>
        <input class="form-input" name="name" required placeholder="e.g. Andromeda Ops">
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-textarea" name="description" placeholder="Universe description..."></textarea>
      </div>
      <button class="btn btn-primary" type="submit">Create Universe</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
