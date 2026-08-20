<?php
require_once __DIR__ . '/config/config.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard/index.php');
}

$error = flash_get('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'Active' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];

            $upd = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            $upd->execute([$user['id']]);

            audit_log($pdo, 'Login', 'auth', $user['id'], 'User logged in');
            redirect('dashboard/index.php');
        } else {
            $error = 'Invalid username or password.';
            audit_log($pdo, 'Failed login attempt', 'auth', null, 'Username: ' . $username);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: linear-gradient(135deg,#305496,#1b2a4a); min-height:100vh; display:flex; align-items:center; }
.login-card { max-width: 420px; margin: 0 auto; border-radius: 12px; }
</style>
</head>
<body>
<div class="container">
  <div class="card login-card shadow p-4">
    <div class="text-center mb-3">
      <i class="bi bi-building" style="font-size:2.5rem;color:#305496;"></i>
      <h5 class="mt-2 mb-0">HMLGS</h5>
      <small class="text-muted">Hostel Merit List Generator System</small>
    </div>
    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-box-arrow-in-right"></i> Login
      </button>
    </form>
  </div>
</div>
</body>
</html>
