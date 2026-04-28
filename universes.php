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
            if ($name === '') {
                throw new RuntimeException('Name is required.');
            }
            $stmt = $pdo->prepare('INSERT INTO universes(name, description) VALUES (:name, :description)');
            $stmt->execute([':name' => $name, ':description' => $description]);
            $message = 'Universe created.';
        } elseif ($action === 'update') {
            $universeId = (int)post('universe_id', 0);
            $name = trim((string)post('name', ''));
            $description = trim((string)post('description', ''));
            if ($universeId <= 0) {
                throw new RuntimeException('Invalid universe.');
            }
            if ($name === '') {
                throw new RuntimeException('Name is required.');
            }
            $stmt = $pdo->prepare('UPDATE universes SET name = :name, description = :description WHERE universe_id = :id');
            $stmt->execute([':name' => $name, ':description' => $description, ':id' => $universeId]);
            $message = 'Universe updated.';
        } elseif ($action === 'delete') {
            $universeId = (int)post('universe_id', 0);
            if ($universeId <= 0) {
                throw new RuntimeException('Invalid universe.');
            }
            $stmt = $pdo->prepare('DELETE FROM universes WHERE universe_id = :id');
            $stmt->execute([':id' => $universeId]);
            $message = 'Universe deleted.';
        }
    } catch (Throwable $t) {
        $error = $t->getMessage();
    }
}

$editId = (int)get('edit', 0);
$editUniverse = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM universes WHERE universe_id = :id');
    $stmt->execute([':id' => $editId]);
    $editUniverse = $stmt->fetch() ?: null;
}

$universes = $pdo->query('SELECT * FROM universes ORDER BY universe_id ASC')->fetchAll();

$title = 'Universes (CRUD)';
$active = 'universes';
include __DIR__ . '/includes/header.php';
?>

<?php if ($message): ?>
  <div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-body">
        <h2 class="h5"><?= $editUniverse ? 'Edit Universe' : 'Add Universe' ?></h2>
        <form method="post" class="vstack gap-3 mt-3">
          <input type="hidden" name="action" value="<?= $editUniverse ? 'update' : 'create' ?>">
          <?php if ($editUniverse): ?>
            <input type="hidden" name="universe_id" value="<?= h((string)$editUniverse['universe_id']) ?>">
          <?php endif; ?>
          <div>
            <label class="form-label">Name</label>
            <input class="form-control" name="name" required value="<?= h((string)($editUniverse['name'] ?? '')) ?>">
          </div>
          <div>
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="4"><?= h((string)($editUniverse['description'] ?? '')) ?></textarea>
          </div>
          <button class="btn btn-primary" type="submit"><?= $editUniverse ? 'Save Changes' : 'Create' ?></button>
          <?php if ($editUniverse): ?>
            <a class="btn btn-outline-secondary" href="universes.php">Cancel</a>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <h2 class="h5 m-0">All Universes</h2>
        </div>
        <div class="table-responsive mt-3">
          <table class="table table-sm table-striped">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Description</th>
                <th>Created</th>
                <th style="width: 170px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($universes as $u): ?>
                <tr>
                  <td><?= h((string)$u['universe_id']) ?></td>
                  <td><?= h($u['name']) ?></td>
                  <td class="text-truncate" style="max-width: 280px;"><?= h($u['description']) ?></td>
                  <td><?= h((string)$u['created_at']) ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-primary" href="universes.php?edit=<?= h((string)$u['universe_id']) ?>">Edit</a>
                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this universe? This will delete its snapshots and related data.');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="universe_id" value="<?= h((string)$u['universe_id']) ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$universes): ?>
                <tr><td colspan="5" class="text-muted">No universes yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
