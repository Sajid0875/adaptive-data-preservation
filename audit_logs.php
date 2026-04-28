<?php

declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
require_login();

$pdo = db();

$limit = (int)get('limit', 200);
if ($limit <= 0 || $limit > 2000) {
    $limit = 200;
}

$stmt = $pdo->prepare('SELECT log_id, action, entity_name, entity_id, timestamp FROM audit_logs ORDER BY timestamp DESC LIMIT :lim');
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$title = 'Audit Logs';
$active = 'audit';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center">
  <div>
    <h2 class="h5 m-0">System Logs</h2>
    <div class="text-muted small">Auto-captured via triggers on core tables.</div>
  </div>
  <form class="d-flex gap-2" method="get">
    <select class="form-select form-select-sm" name="limit">
      <?php foreach ([50, 200, 500, 1000, 2000] as $opt): ?>
        <option value="<?= h((string)$opt) ?>" <?= $opt === $limit ? 'selected' : '' ?>><?= h((string)$opt) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-outline-primary" type="submit">Apply</button>
  </form>
</div>

<div class="card mt-3">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Action</th>
            <th>Entity</th>
            <th>Entity ID</th>
            <th>Timestamp</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= h((string)$r['log_id']) ?></td>
              <td><?= h($r['action']) ?></td>
              <td><?= h($r['entity_name']) ?></td>
              <td><?= $r['entity_id'] !== null ? h((string)$r['entity_id']) : '-' ?></td>
              <td><?= h((string)$r['timestamp']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="5" class="text-muted">No audit logs yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
