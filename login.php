<?php

declare(strict_types=1);
require_once __DIR__ . '/config/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (get('logout') === '1') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    redirect('login.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string)post('username', '');
    $password = (string)post('password', '');

    // Hardcoded admin-only login (as required)
    $adminUser = 'admin';
    $adminPass = 'admin123';

    if ($username === $adminUser && $password === $adminPass) {
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_username'] = $adminUser;
        redirect('dashboard.php');
    }

    $error = 'Invalid credentials.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - Universe System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
          <div class="card-body p-4">
            <h1 class="h4 mb-3">Admin Login</h1>
            <p class="text-muted small mb-4">Use the hardcoded admin account.</p>

            <?php if ($error): ?>
              <div class="alert alert-danger"><?= h($error) ?></div>
            <?php endif; ?>

            <form method="post" class="vstack gap-3">
              <div>
                <label class="form-label">Username</label>
                <input class="form-control" name="username" required value="<?= h((string)post('username','')) ?>">
              </div>
              <div>
                <label class="form-label">Password</label>
                <input type="password" class="form-control" name="password" required>
              </div>
              <button class="btn btn-primary w-100" type="submit">Login</button>
            </form>

            <hr>
            <div class="small text-muted">
              Default: <code>admin</code> / <code>admin123</code>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
