<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';

$pageTitle = 'Hostel Sessions';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['session_name'] ?? '');
        if ($name === '') {
            flash_set('error', 'Session name is required.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO hostel_sessions (session_name) VALUES (?)");
            $stmt->execute([$name]);
            audit_log($pdo, 'Create session', 'sessions', $pdo->lastInsertId(), $name);
            flash_set('success', 'Hostel session created.');
        }
    } elseif ($action === 'activate') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE hostel_sessions SET is_active = 0")->execute();
        $pdo->prepare("UPDATE hostel_sessions SET is_active = 1 WHERE id = ?")->execute([$id]);
        audit_log($pdo, 'Activate session', 'sessions', $id);
        flash_set('success', 'Session activated.');
    } elseif ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE hostel_sessions SET status = IF(status='Active','Inactive','Active') WHERE id = ?");
        $stmt->execute([$id]);
        audit_log($pdo, 'Toggle session status', 'sessions', $id);
        flash_set('success', 'Session status updated.');
    }
    redirect('sessions/index.php');
}

$sessionsList = $pdo->query("SELECT * FROM hostel_sessions ORDER BY id DESC")->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Hostel Sessions</h4>
  </div>

  <?php if ($m = flash_get('success')): ?><div class="alert alert-success alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
  <?php if ($m = flash_get('error')): ?><div class="alert alert-danger alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

  <div class="card p-3 mb-4">
    <h6>Add New Session</h6>
    <form method="post" class="row g-2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="col-md-4">
        <input type="text" name="session_name" class="form-control" placeholder="e.g. 2026-27" required>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add</button>
      </div>
    </form>
  </div>

  <div class="card p-3">
    <table class="table table-hover align-middle">
      <thead><tr><th>#</th><th>Session</th><th>Active</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($sessionsList as $s): ?>
        <tr>
          <td><?= $s['id'] ?></td>
          <td><?= e($s['session_name']) ?></td>
          <td><?= $s['is_active'] ? '<span class="badge bg-success">Active Session</span>' : '' ?></td>
          <td><span class="badge <?= $s['status']==='Active' ? 'bg-primary' : 'bg-secondary' ?>"><?= e($s['status']) ?></span></td>
          <td><?= format_datetime($s['created_at']) ?></td>
          <td>
            <?php if (!$s['is_active']): ?>
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="activate">
              <input type="hidden" name="id" value="<?= $s['id'] ?>">
              <button class="btn btn-sm btn-outline-success">Set Active</button>
            </form>
            <?php endif; ?>
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?= $s['id'] ?>">
              <button class="btn btn-sm btn-outline-secondary"><?= $s['status']==='Active' ? 'Deactivate' : 'Activate' ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
