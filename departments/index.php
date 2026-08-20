<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/check_auth.php';

$pageTitle = 'Departments';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['department_name'] ?? '');
        $code = strtoupper(trim($_POST['department_code'] ?? ''));
        if ($name === '' || $code === '') {
            flash_set('error', 'Department name and code are required.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO departments (department_name, department_code) VALUES (?, ?)");
                $stmt->execute([$name, $code]);
                audit_log($pdo, 'Create department', 'departments', $pdo->lastInsertId(), $name);
                flash_set('success', 'Department added.');
            } catch (PDOException $e) {
                flash_set('error', 'Department code must be unique.');
            }
        }
    } elseif ($action === 'toggle_status') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE departments SET status = IF(status='Active','Inactive','Active') WHERE id = ?")->execute([$id]);
        audit_log($pdo, 'Toggle department status', 'departments', $id);
        flash_set('success', 'Department status updated.');
    }
    redirect('departments/index.php');
}

$rows = $pdo->query("SELECT * FROM departments ORDER BY department_name")->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<div class="container-fluid py-4">
  <h4 class="mb-3">Departments</h4>
  <?php if ($m = flash_get('success')): ?><div class="alert alert-success alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
  <?php if ($m = flash_get('error')): ?><div class="alert alert-danger alert-dismissible fade show"><?= e($m) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

  <div class="card p-3 mb-4">
    <h6>Add Department</h6>
    <form method="post" class="row g-2">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="col-md-4"><input type="text" name="department_name" class="form-control" placeholder="Department Name" required></div>
      <div class="col-md-2"><input type="text" name="department_code" class="form-control" placeholder="Code e.g. CS" required></div>
      <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add</button></div>
    </form>
  </div>

  <div class="card p-3">
    <table class="table table-hover align-middle">
      <thead><tr><th>#</th><th>Name</th><th>Code</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $d): ?>
        <tr>
          <td><?= $d['id'] ?></td>
          <td><?= e($d['department_name']) ?></td>
          <td><?= e($d['department_code']) ?></td>
          <td><span class="badge <?= $d['status']==='Active' ? 'bg-primary' : 'bg-secondary' ?>"><?= e($d['status']) ?></span></td>
          <td>
            <a href="<?= BASE_URL ?>programs/index.php?department_id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary">View Programs</a>
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="id" value="<?= $d['id'] ?>">
              <button class="btn btn-sm btn-outline-secondary"><?= $d['status']==='Active' ? 'Deactivate' : 'Activate' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
