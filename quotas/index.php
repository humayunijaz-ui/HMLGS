<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';

$pageTitle = 'Admission Quotas';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['quota_name'] ?? '');
        $code = strtoupper(trim($_POST['quota_code'] ?? ''));
        if ($name === '' || $code === '') {
            flash_set('error', 'Quota name and code are required.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO admission_quotas (quota_name, quota_code) VALUES (?, ?)");
                $stmt->execute([$name, $code]);
                audit_log($pdo, 'Create admission quota', 'quotas', $pdo->lastInsertId(), $name);
                flash_set('success', 'Admission quota added.');
            } catch (PDOException $e) {
                flash_set('error', 'Quota code must be unique. (Have you run database/migration_quota.sql yet?)');
            }
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE admission_quotas SET status = IF(status='Active','Inactive','Active') WHERE id = ?")->execute([$id]);
        audit_log($pdo, 'Toggle quota status', 'quotas', $id);
        flash_set('success', 'Quota status updated.');
    }
    redirect('quotas/index.php');
}

$rows = [];
$migrationNeeded = false;
try {
    $rows = $pdo->query("SELECT * FROM admission_quotas ORDER BY quota_name")->fetchAll();
} catch (PDOException $e) {
    $migrationNeeded = true;
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
  <h4 class="mb-3">Admission Quotas</h4>

  <?php if ($migrationNeeded): ?>
    <div class="alert alert-danger">
      The <code>admission_quotas</code> table doesn't exist yet. Please import
      <code>database/migration_quota.sql</code> via phpMyAdmin first, then reload this page.
    </div>
  <?php endif; ?>

  <?php if ($m = flash_get('success')): ?><div class="alert alert-success alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
  <?php if ($m = flash_get('error')): ?><div class="alert alert-danger alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

  <?php if (!$migrationNeeded): ?>
  <div class="card p-3 mb-4">
    <h6>Add Admission Quota</h6>
    <form method="post" class="row g-2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="col-md-4"><input type="text" name="quota_name" class="form-control" placeholder="e.g. Open Merit" required></div>
      <div class="col-md-2"><input type="text" name="quota_code" class="form-control" placeholder="Code e.g. OPEN" required></div>
      <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add</button></div>
    </form>
  </div>

  <div class="card p-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="mb-0">Quota Categories</h6>
      <a href="<?= BASE_URL ?>quotas/matrix.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-table"></i> Configure Seat Matrix</a>
    </div>
    <table class="table table-hover align-middle">
      <thead><tr><th>#</th><th>Name</th><th>Code</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $q): ?>
        <tr>
          <td><?= $q['id'] ?></td>
          <td><?= e($q['quota_name']) ?></td>
          <td><?= e($q['quota_code']) ?></td>
          <td><span class="badge <?= $q['status']==='Active' ? 'bg-primary' : 'bg-secondary' ?>"><?= e($q['status']) ?></span></td>
          <td>
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?= $q['id'] ?>">
              <button class="btn btn-sm btn-outline-secondary"><?= $q['status']==='Active' ? 'Deactivate' : 'Activate' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">No admission quotas yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
