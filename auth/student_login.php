<?php
require_once __DIR__ . '/../config/config.php';

if (!empty($_SESSION['student_cnic'])) {
    redirect('student/merit.php');
}

$error = flash_get('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter your CNIC / B-Form number (used as both username and password).';
    } elseif ($username !== $password) {
        $error = 'Invalid CNIC / B-Form number.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM hostel_applications WHERE cnic_b_form = ? LIMIT 1");
        $stmt->execute([$username]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            session_regenerate_id(true);
            $_SESSION['student_cnic'] = $username;
            audit_log($pdo, 'Student login', 'student_auth', null, 'CNIC: ' . $username);
            redirect('student/merit.php');
        } else {
            $error = 'No application found for this CNIC / B-Form number.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Login - <?= APP_NAME ?></title>
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
      <i class="bi bi-mortarboard" style="font-size:2.5rem;color:#305496;"></i>
      <h5 class="mt-2 mb-0">Student Portal</h5>
      <small class="text-muted">Hostel Merit List Generator System</small>
    </div>
    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?= e($error) ?></div>
    <?php endif; ?>
    <p class="small text-muted">Login using your <strong>CNIC / B-Form Number</strong> as both the username and password (e.g. <code>12345-1234567-1</code>).</p>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">CNIC / B-Form Number</label>
        <input type="text" name="username" class="form-control" placeholder="e.g. 12345-1234567-1" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Password (same as CNIC / B-Form Number)</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-box-arrow-in-right"></i> Login
      </button>
    </form>
    <div class="text-center mt-3">
      <a href="<?= BASE_URL ?>login.php" class="small text-muted">Admin Login</a>
    </div>
  </div>
</div>
</body>
</html>
